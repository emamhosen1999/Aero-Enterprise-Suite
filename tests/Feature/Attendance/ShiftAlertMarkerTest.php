<?php

namespace Tests\Feature\Attendance;

use App\Models\HRM\RosterDay;
use App\Models\HRM\Shift;
use App\Models\User;
use App\Notifications\Attendance\MissingPunchInNotification;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The dedupe marker store behind `attendance:shift-alerts`.
 *
 * ShiftLifecycleAlertsTest covers WHICH alert fires in which window. This
 * covers what happens when the thing that stops them firing twice cannot
 * persist anything at all — which is not a hypothetical:
 *
 *   Production ran CACHE_STORE=null. NullStore::put() is hardcoded to return
 *   false, so Cache::add() returned false, so every marker read as "already
 *   claimed" and all three alerts were skipped. 3,672 scheduled runs delivered
 *   zero notifications and logged "reminders: 0, overdue: 0, absence: 0" every
 *   time — a line indistinguishable from a genuinely quiet day. Fixing the
 *   driver made the very next run send four absence escalations that had been
 *   overdue for weeks.
 *
 * The property under test is the one the incident violated: an alerting system
 * must never silently send nothing. Concretely — a marker store that cannot
 * hold a marker must never be able to suppress an alert, and must never be able
 * to do so quietly.
 */
class ShiftAlertMarkerTest extends TestCase
{
    use RefreshDatabase;

    private const DATE = '2026-07-20';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * One employee rostered onto a 07:00–15:00 shift, with the clock parked at
     * 07:20 — inside the overdue-punch-in window, so exactly one alert is due.
     */
    private function employeeDueAnOverdueAlert(): User
    {
        $employee = User::factory()->create();

        $shift = Shift::factory()->create([
            'code' => 'MRN',
            'start_time' => '07:00',
            'end_time' => '15:00',
            'crosses_midnight' => false,
        ]);

        RosterDay::create([
            'user_id' => $employee->id,
            'date' => self::DATE,
            'shift_id' => $shift->id,
            'source' => 'manual',
            'locked' => true,
        ]);

        Carbon::setTestNow(self::DATE.' 07:20:00');

        return $employee;
    }

    /**
     * Make every store the command could reach unable to persist.
     *
     * `cache.default` is what production had wrong; the two entries in
     * MARKER_STORE_FALLBACKS are what the command tries next. Rewriting their
     * drivers rather than deleting them keeps `Cache::store()` resolvable, so
     * the code under test takes the same path it would against a null driver, a
     * dead redis or an unwritable cache directory — all of which arrive as the
     * same false from add().
     */
    private function breakEveryStore(): void
    {
        config([
            'cache.default' => 'null',
            'cache.stores.database.driver' => 'null',
            'cache.stores.file.driver' => 'null',
        ]);

        Cache::purge('database');
        Cache::purge('file');
    }

    /**
     * Run the command and hand back its exit code with everything it printed.
     *
     * `$this->artisan()->expectsOutputToContain()` cannot be used for these:
     * it registers one Mockery expectation per substring, and a single written
     * line satisfies only the first of them — so asserting three things about
     * one summary line fails however right the line is. The buffered output is
     * also closer to what an operator sees, since the scheduler appends exactly
     * this stream to storage/logs/shift-alerts.log.
     *
     * @return array{0: int, 1: string}
     */
    private function runAlerts(): array
    {
        $code = Artisan::call('attendance:shift-alerts');

        return [$code, Artisan::output()];
    }

    // ──────────────────────────────────────────────────────────────
    //  The incident
    // ──────────────────────────────────────────────────────────────

    public function test_a_dead_marker_store_never_suppresses_an_alert(): void
    {
        // This is the regression, stated as plainly as it can be: with the exact
        // configuration production ran, the employee got nothing. They must now
        // get the alert.
        Notification::fake();
        $employee = $this->employeeDueAnOverdueAlert();

        $this->breakEveryStore();

        $this->runAlerts();

        Notification::assertSentTo($employee, MissingPunchInNotification::class);
    }

    public function test_a_dead_marker_store_is_announced_on_the_console(): void
    {
        // Delivering the alert is not enough. The scheduler appends this
        // command's output to storage/logs/shift-alerts.log (routes/console.php),
        // so the console is where an operator finds out — and what they find has
        // to name the broken store and say what to set it to, or the report is
        // just noise they will learn to skip.
        Notification::fake();
        $this->employeeDueAnOverdueAlert();

        $this->breakEveryStore();

        [$code, $output] = $this->runAlerts();

        $this->assertStringContainsString('No cache store can hold a shift-alert marker', $output);
        // Names the store that is wrong…
        $this->assertStringContainsString('default "null"', $output);
        // …says what it costs…
        $this->assertStringContainsString('WITHOUT de-duplication', $output);
        // …and says what to do about it.
        $this->assertStringContainsString('CACHE_STORE', $output);

        // Non-zero, so a scheduler or uptime monitor sees it even if nobody
        // reads the log. The alerts themselves still went out.
        $this->assertSame(1, $code);
    }

    public function test_the_summary_line_says_de_duplication_is_off(): void
    {
        // The summary is the line that gets read at a glance, and the one that
        // lied for 3,672 runs. It must carry the state of the marker store, so
        // a zero in it can be interpreted rather than assumed.
        Notification::fake();
        $this->employeeDueAnOverdueAlert();

        $this->breakEveryStore();

        [$code, $output] = $this->runAlerts();

        $this->assertStringContainsString('markers: DISABLED (no usable cache store)', $output);
        $this->assertSame(1, $code);
    }

    public function test_a_dead_store_re_sends_rather_than_going_quiet(): void
    {
        // Failing toward noise, made explicit. Without a marker store there is
        // no way to know an alert already went out, so the command repeats it.
        // Two identical reminders are an annoyance somebody complains about; a
        // missing absence escalation is invisible, which is why the trade goes
        // this way and not the other.
        Notification::fake();
        $employee = $this->employeeDueAnOverdueAlert();

        $this->breakEveryStore();

        $this->runAlerts();
        $this->runAlerts();

        Notification::assertSentToTimes($employee, MissingPunchInNotification::class, 2);
    }

    // ──────────────────────────────────────────────────────────────
    //  Falling back to a store that does work
    // ──────────────────────────────────────────────────────────────

    public function test_a_broken_default_falls_back_to_a_working_store_and_still_de_duplicates(): void
    {
        // "At most once" is this command's own guarantee, so it is not left to
        // CACHE_STORE. With the default broken but the database cache table
        // present, dedupe keeps working — the employee is alerted once across
        // two runs, not twice.
        Notification::fake();
        $employee = $this->employeeDueAnOverdueAlert();

        config(['cache.default' => 'null']);

        [$first] = $this->runAlerts();
        [$second, $output] = $this->runAlerts();

        $this->assertSame(0, $first);
        $this->assertSame(0, $second);
        Notification::assertSentToTimes($employee, MissingPunchInNotification::class, 1);

        // The second run suppressed the alert because a marker was there to
        // find — the proof that the fallback store is really holding them.
        $this->assertStringContainsString('1 suppressed as already sent', $output);
        $this->assertStringContainsString('markers: database', $output);
    }

    public function test_falling_back_is_still_reported_as_a_misconfiguration(): void
    {
        // The fallback rescues the alerts; it must not bury the cause. A cache
        // default that cannot hold a value is broken for everything else in the
        // application too, and this command is now the only thing that noticed.
        Notification::fake();
        $this->employeeDueAnOverdueAlert();

        config(['cache.default' => 'null']);

        [$code, $output] = $this->runAlerts();

        $this->assertStringContainsString('Cache store "null" cannot hold a shift-alert marker', $output);
        $this->assertStringContainsString('CACHE_STORE', $output);
        // Rescued, not failed: dedupe is intact, so this is a warning and the
        // exit code stays clean.
        $this->assertSame(0, $code);
    }

    // ──────────────────────────────────────────────────────────────
    //  The healthy path is unchanged
    // ──────────────────────────────────────────────────────────────

    public function test_a_working_store_de_duplicates_silently_and_exits_zero(): void
    {
        Notification::fake();
        $employee = $this->employeeDueAnOverdueAlert();

        [$first, $firstOutput] = $this->runAlerts();
        [$second] = $this->runAlerts();

        $this->assertSame(0, $first);
        $this->assertSame(0, $second);
        Notification::assertSentToTimes($employee, MissingPunchInNotification::class, 1);

        // No warning, no error: a working store says nothing at all.
        $this->assertStringNotContainsString('cannot hold', $firstOutput);
        $this->assertStringContainsString('markers: array', $firstOutput);
    }

    public function test_a_working_store_reports_the_roster_size_and_the_suppression_count(): void
    {
        // "0 sent" is ambiguous on its own — it is the correct output both for a
        // quiet day and for a total outage. The roster size and the suppression
        // count are what tell them apart, so they are part of the contract, not
        // decoration.
        Notification::fake();
        $this->employeeDueAnOverdueAlert();

        [, $first] = $this->runAlerts();

        $this->assertStringContainsString('1 rostered employee(s) evaluated', $first);
        $this->assertStringContainsString('overdue: 1', $first);
        $this->assertStringContainsString('0 suppressed as already sent', $first);

        // Second pass: nothing new to send, and the line says why — the alert
        // was suppressed, not absent.
        [, $second] = $this->runAlerts();

        $this->assertStringContainsString('1 rostered employee(s) evaluated', $second);
        $this->assertStringContainsString('overdue: 0', $second);
        $this->assertStringContainsString('1 suppressed as already sent', $second);
    }

    public function test_an_empty_roster_is_distinguishable_from_a_suppressed_one(): void
    {
        // The other half of the same point: zero alerts because nobody is
        // rostered now reads differently from zero alerts because they already
        // went out — and from zero alerts because the store is dead.
        Notification::fake();
        Carbon::setTestNow(self::DATE.' 07:20:00');

        [$code, $output] = $this->runAlerts();

        $this->assertStringContainsString('0 rostered employee(s) evaluated', $output);
        $this->assertStringContainsString('0 suppressed as already sent', $output);
        $this->assertSame(0, $code);
    }
}
