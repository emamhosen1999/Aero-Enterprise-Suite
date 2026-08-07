<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\BiometricDevice;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * `biometric:correct-historical-clock-offset` — the retroactive repair.
 *
 * ── What is being repaired ──────────────────────────────────────────────────
 *
 * The production MB460 (`AF6P231260266`) has stamped every punch exactly two
 * hours in the future since installation. `DeviceClockService` now corrects that
 * at ingest, but only from 2026-08-06; the 459 biometric-derived `attendances`
 * rows written before that still hold the skewed moment. A 09:00 arrival sits in
 * the table as 11:00 and reads as two hours late; a 17:00 departure reads as two
 * hours of overtime. This command shifts that history back by 7200 s.
 *
 * ── Why these particular tests ──────────────────────────────────────────────
 *
 * This is a one-shot bulk rewrite of payroll-relevant data on a live system, so
 * the tests are written against the ways it could silently do damage, not
 * against its happy path:
 *
 *  - **It must not run by accident.** Dry run is the default and `--device` /
 *    `--seconds` are required with no fallback, so a bare invocation, or one
 *    that forgets the offset, changes nothing.
 *  - **It must not touch data it did not come for.** Web, mobile and manual
 *    attendance carries no `biometric_att_logs` row; another device's history is
 *    another device's problem; `--from`/`--until` mean what they say.
 *  - **It must never shift the same punch twice.** That is the failure that
 *    would be invisible: a second -2 h shift looks exactly as plausible as the
 *    first, and nothing downstream would flag it. Two tests cover it — one that
 *    runs the command twice, and one that proves the guard is the database's
 *    UNIQUE constraint and not the command's `WHERE NOT EXISTS`, so deleting the
 *    filter cannot quietly re-enable double-shifting.
 *  - **It must be reversible.** The archive holds the complete original row, and
 *    the test compares it column-for-column against what was there before rather
 *    than against a hand-written subset.
 *  - **It must refuse what it cannot do safely.** A punch that would land on the
 *    previous calendar day makes `attendances.date` disagree with its own
 *    punches and can collide with the adjacent day's row. The command aborts the
 *    whole run instead of writing part of it.
 */
class HistoricalClockCorrectionTest extends TestCase
{
    use RefreshDatabase;

    private const COMMAND = 'biometric:correct-historical-clock-offset';

    private const LEDGER = 'attendance_clock_corrections';

    /**
     * The offset applied to history: exactly two hours.
     *
     * NOT the live estimator's 7195. That number is the true offset minus
     * transport latency — observed samples ran 7186-7197 and never once exceeded
     * 7197, which they would have to for 7195 to be the centre of a real
     * distribution. A constant 2 h timezone error minus a few seconds of network
     * fits every sample; 7200 is the clock, 7195 is the clock seen through a
     * network.
     */
    private const OFFSET = 7200;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ──────────────────────────────────────────────────────────────
    //  Dry run
    // ──────────────────────────────────────────────────────────────

    public function test_dry_run_is_the_default_and_writes_nothing(): void
    {
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
        ])->assertExitCode(0);

        // The punch is exactly as it was. Not "close to", not "re-saved with the
        // same value" — untouched.
        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    public function test_dry_run_reports_the_row_count_and_a_before_after_sample(): void
    {
        $device = $this->device();
        $user = $this->user();
        $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        // Captured rather than matched with expectsOutputToContain(): the whole
        // point of the sample is that a reviewer sees the before and the after
        // side by side on one line, and the ordered per-write matcher cannot
        // assert two substrings of the SAME line.
        $output = $this->runCommand([
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
        ]);

        $this->assertStringContainsString('DRY RUN', $output);
        $this->assertStringContainsString('Attendance rows selected', $output);
        $this->assertMatchesRegularExpression('/Attendance rows selected\s*\|\s*1\s/', $output);

        // Before and after, on one line, in that order.
        $this->assertMatchesRegularExpression(
            '/2026-06-01 11:00:00.*2026-06-01 09:00:00.*2026-06-01 19:00:00.*2026-06-01 17:00:00/',
            $output
        );

        $this->assertStringContainsString('Rows crossing a date boundary', $output);
    }

    // ──────────────────────────────────────────────────────────────
    //  Applying
    // ──────────────────────────────────────────────────────────────

    public function test_apply_shifts_biometric_punches_back_by_the_offset(): void
    {
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        // The whole point: the 09:00 arrival that was recorded as 11:00 is 09:00
        // again, and the 17:00 departure stops looking like two hours of OT.
        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
    }

    public function test_web_and_manual_attendance_is_never_touched(): void
    {
        $device = $this->device();
        $biometricUser = $this->user();
        $webUser = $this->user();

        $biometric = $this->biometricAttendance($device, $biometricUser, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        // No biometric_att_logs row at all — a browser or mobile punch.
        $web = $this->attendance($webUser->id, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        // Same user as a real biometric punch, same day, but entered by an admin
        // at a time the device never reported. Sharing a user is not enough to be
        // selected; the timestamp itself has to be the one the log recorded.
        $manual = $this->attendance($biometricUser->id, '2026-06-02', '2026-06-02 10:15:00', '2026-06-02 18:45:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($biometric, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
        $this->assertPunches($web, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertPunches($manual, '2026-06-02 10:15:00', '2026-06-02 18:45:00');

        $this->assertDatabaseCount(self::LEDGER, 1);
        $this->assertDatabaseHas(self::LEDGER, ['attendance_id' => $biometric]);
    }

    public function test_an_open_row_is_corrected_on_the_half_it_has(): void
    {
        $device = $this->device();
        $user = $this->user();

        // Punched in, never punched out. The null must survive as a null rather
        // than being coerced into a shifted epoch.
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', null);

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', null);
    }

    public function test_only_the_punch_the_device_actually_reported_is_shifted(): void
    {
        // A mixed day: the employee punched in through the web at 09:00 and out
        // on the terminal, which reported 19:00 for a real 17:00. The row is
        // selected because its punchOUT matches a device log — but its punchIN
        // was never skewed and must not move. Shifting the whole row would take a
        // correct 09:00 web punch to 07:00 and invent two hours of work.
        $device = $this->device();
        $user = $this->user();

        $id = $this->attendance($user->id, '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 19:00:00');
        $this->attLog($device, $user, '2026-06-01 19:00:00', 'out');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        $ledger = DB::table(self::LEDGER)->where('attendance_id', $id)->first();

        $this->assertNotNull($ledger);
        $this->assertNull($ledger->punchin_before, 'A punch this device never reported must not be recorded as shifted.');
        $this->assertNull($ledger->punchin_after);
        $this->assertSame('2026-06-01 19:00:00', $this->normalise($ledger->punchout_before));
        $this->assertSame('2026-06-01 17:00:00', $this->normalise($ledger->punchout_after));
    }

    public function test_mixed_rows_are_counted_and_an_open_row_is_not_one(): void
    {
        // "Mixed" means two punches from two sources — the case an operator has
        // to know about, because half the row is already correct. A row that was
        // simply never punched out has one punch, not two sources, and counting
        // it as mixed would inflate the number that matters.
        $device = $this->device();

        // Mixed: web punch-in at 09:00, device punch-out at a skewed 19:00.
        $mixedUser = $this->user();
        $this->attendance($mixedUser->id, '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 19:00:00');
        $this->attLog($device, $mixedUser, '2026-06-01 19:00:00', 'out');

        // Open: one punch, one source.
        $this->biometricAttendance($device, $this->user(), '2026-06-02', '2026-06-02 11:00:00', null);
        // Wholly this device's.
        $this->biometricAttendance($device, $this->user(), '2026-06-03', '2026-06-03 11:00:00', '2026-06-03 19:00:00');

        $output = $this->runCommand([
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
        ]);

        $this->assertMatchesRegularExpression('/Attendance rows selected\s*\|\s*3\s/', $output);
        $this->assertMatchesRegularExpression('/Mixed rows[^|]*\|\s*1\s/', $output);
    }

    public function test_a_device_punch_in_with_a_manual_punch_out_shifts_only_the_punch_in(): void
    {
        // The mirror case. The terminal reported the arrival; an admin closed the
        // day by hand at a time the device never saw.
        $device = $this->device();
        $user = $this->user();

        $id = $this->attendance($user->id, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 17:30:00');
        $this->attLog($device, $user, '2026-06-01 11:00:00', 'in');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:30:00');
    }

    public function test_a_punch_out_only_correction_cannot_stale_the_policy_verdict(): void
    {
        // PunchPolicyGuard::assess() has exactly one call site — punchIn() — and
        // reads the punch-in moment alone. A row whose punch-OUT moved and whose
        // punch-in did not therefore has a verdict that is still exactly as
        // valid as it was, and the report must not count it as at risk.
        $device = $this->device();
        $user = $this->user();

        $id = $this->attendance($user->id, '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 19:00:00');
        $this->attLog($device, $user, '2026-06-01 19:00:00', 'out');

        DB::table('attendances')->where('id', $id)->update([
            'policy_status' => 'provisional',
            'needs_approval' => true,
            'policy_exception_reason' => 'outside permitted window',
        ]);

        $output = $this->runCommand([
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ]);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        // Counted as a non-accepted row, but NOT as one whose verdict moved.
        $this->assertMatchesRegularExpression('/policy_status != accepted\s*\|\s*1\s/', $output);
        $this->assertMatchesRegularExpression('/rows whose punch-in actually moved\s*\|\s*0\s/', $output);
    }

    public function test_a_punch_matching_only_another_devices_log_is_not_shifted(): void
    {
        // Two terminals, one day: the skewed MB460 recorded the arrival, a
        // healthy reader recorded the departure. Only the skewed device's punch
        // is wrong, and only it may move.
        $skewed = $this->device(['serial_number' => 'SKEWED-1']);
        $healthy = $this->device(['serial_number' => 'HEALTHY-1']);
        $user = $this->user();

        $id = $this->attendance($user->id, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 17:00:00');
        $this->attLog($skewed, $user, '2026-06-01 11:00:00', 'in');
        $this->attLog($healthy, $user, '2026-06-01 17:00:00', 'out');

        $this->artisan(self::COMMAND, [
            '--device' => 'SKEWED-1',
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
    }

    public function test_a_negative_offset_shifts_punches_forward(): void
    {
        // The sign convention is load-bearing and shared with DeviceClockService:
        // --seconds is device_time minus server_time, so a device that ran SLOW
        // (negative) wrote punches that are too EARLY and they move forward.
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => -3600,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 10:00:00', '2026-06-01 18:00:00');
    }

    public function test_rows_already_corrected_at_ingest_are_not_selected_again(): void
    {
        $device = $this->device();
        $user = $this->user();

        // This is what the forward fix writes: punch_time keeps the device's raw
        // claim, corrected_punch_time holds the moment that actually reached
        // attendance. The attendance row is ALREADY right. A selection that
        // matched on raw punch_time alone would shift it a second time.
        $id = $this->attendance($user->id, '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        $this->attLog($device, $user, '2026-06-01 11:00:00', 'in', [
            'corrected_punch_time' => '2026-06-01 09:00:00',
            'clock_offset_applied_seconds' => -self::OFFSET,
        ]);

        // Deliberately give the raw log timestamp a row to collide with, so the
        // test fails if corrected_punch_time is ignored: attendance holds 09:00,
        // and 09:00 is also what a naive raw match would look for elsewhere.
        $this->attLog($device, $user, '2026-06-01 09:00:00', 'out', [
            'corrected_punch_time' => '2026-06-01 07:00:00',
        ]);

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    // ──────────────────────────────────────────────────────────────
    //  Idempotency — the primary design constraint
    // ──────────────────────────────────────────────────────────────

    public function test_a_second_run_does_not_shift_the_same_punch_again(): void
    {
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $options = [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ];

        $this->artisan(self::COMMAND, $options)->assertExitCode(0);
        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        // Run it again, exactly as an operator who is unsure whether the first
        // run finished would. 07:00 is what a double shift looks like.
        $this->artisan(self::COMMAND, $options)->assertExitCode(0);
        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        // And the ledger did not grow: the row was excluded, not re-archived
        // with a no-op update.
        $this->assertDatabaseCount(self::LEDGER, 1);
    }

    public function test_a_second_run_with_a_different_offset_still_does_not_shift(): void
    {
        // The guard is keyed on attendances.id, not on the offset or the range,
        // so an operator who re-runs with a corrected --seconds does not get a
        // second shift on top of the first. They get nothing, and have to repair
        // from the archive deliberately.
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => 60,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
        $this->assertDatabaseCount(self::LEDGER, 1);
    }

    public function test_a_row_already_in_the_ledger_is_never_shifted_even_though_it_still_matches(): void
    {
        // THE guard test.
        //
        // Here the attendance row still satisfies every selection criterion — its
        // punchin is still exactly a processed, uncorrected log's punch_time, so
        // the biometric-match half of the query would happily return it. The only
        // thing keeping it out is its presence in the ledger.
        //
        // Verified by mutation: deleting the whereNotExists from candidates()
        // turns this test red and leaves every other test in this file green —
        // the "second run" tests above would keep passing for an accidental
        // reason, because at a 7200 s shift the corrected punch no longer matches
        // any log row. What that mutation run also showed is that the punch STILL
        // did not move: with the filter gone the row is selected, the archive
        // insert hits the UNIQUE on attendance_id, the run aborts with a non-zero
        // exit and `attendances` is untouched. So the assertions below are
        // ordered accordingly — the data must be safe either way, and the exit
        // code is what says the cheap pre-filter is still doing its job.
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        DB::table(self::LEDGER)->insert([
            'attendance_id' => $id,
            'biometric_device_id' => $device->id,
            'device_serial' => $device->serial_number,
            'user_id' => $user->id,
            'attendance_date' => '2026-06-01',
            'applied_seconds' => -self::OFFSET,
            'punchin_before' => '2026-06-01 11:00:00',
            'punchin_after' => '2026-06-01 09:00:00',
            'punchout_before' => '2026-06-01 19:00:00',
            'punchout_after' => '2026-06-01 17:00:00',
            'payload' => json_encode(['id' => $id]),
            'run_id' => '00000000-0000-4000-8000-000000000000',
            'archived_at' => now(),
            'applied_at' => now(),
        ]);

        $exitCode = Artisan::call(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ]);

        // Unconditional: the punch does not move, by either layer of the guard.
        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertDatabaseCount(self::LEDGER, 1);

        // And it was excluded cleanly rather than by crashing into the unique
        // index — a successful run that found nothing to do.
        $this->assertSame(0, $exitCode, 'A ledgered row must be filtered out of the selection, not caught by the UNIQUE constraint at write time.');
    }

    public function test_an_archived_but_unconfirmed_row_is_reported_and_not_adopted(): void
    {
        // What a crash between the archive commit and the update transaction
        // leaves behind: archived, guarded, never shifted. The command cannot
        // tell a shifted row from an unshifted one by looking at it, so adopting
        // it would be a coin flip on a payroll figure. It must stay out of the
        // selection AND be surfaced, or the under-correction is silent.
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        DB::table(self::LEDGER)->insert([
            'attendance_id' => $id,
            'biometric_device_id' => $device->id,
            'device_serial' => $device->serial_number,
            'user_id' => $user->id,
            'attendance_date' => '2026-06-01',
            'applied_seconds' => -self::OFFSET,
            'punchin_before' => '2026-06-01 11:00:00',
            'punchin_after' => '2026-06-01 09:00:00',
            'punchout_before' => '2026-06-01 19:00:00',
            'punchout_after' => '2026-06-01 17:00:00',
            'payload' => json_encode(['id' => $id]),
            'run_id' => '00000000-0000-4000-8000-000000000002',
            'archived_at' => now(),
            'applied_at' => null,
        ]);

        $output = $this->runCommand([
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ]);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertStringContainsString('applied_at = NULL', $output);
    }

    public function test_the_ledger_refuses_a_second_entry_for_the_same_attendance_row(): void
    {
        // The filter in candidates() is an optimisation. THIS is the guarantee:
        // two runs racing each other cannot both pass a check-then-act, because
        // the database decides. The losing run's archive insert throws before its
        // UPDATE is ever issued.
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $threw = false;

        try {
            DB::table(self::LEDGER)->insert([
                'attendance_id' => $id,
                'biometric_device_id' => $device->id,
                'device_serial' => $device->serial_number,
                'user_id' => $user->id,
                'attendance_date' => '2026-06-01',
                'applied_seconds' => -self::OFFSET,
                'payload' => json_encode([]),
                'run_id' => '00000000-0000-4000-8000-000000000001',
                'archived_at' => now(),
                'applied_at' => now(),
            ]);
        } catch (QueryException) {
            $threw = true;
        }

        $this->assertTrue($threw, 'attendance_clock_corrections.attendance_id must be UNIQUE: it is the only thing that makes a concurrent double-shift impossible.');
        $this->assertDatabaseCount(self::LEDGER, 1);
    }

    // ──────────────────────────────────────────────────────────────
    //  The archive
    // ──────────────────────────────────────────────────────────────

    public function test_the_archive_reproduces_the_original_row_exactly(): void
    {
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        // Derived state the correction deliberately does not recompute. It has to
        // survive into the payload verbatim, or the archive is not a restore.
        DB::table('attendances')->where('id', $id)->update([
            'policy_status' => 'provisional',
            'needs_approval' => true,
            'policy_exception_reason' => 'Punch outside permitted window',
            'symbol' => 'P',
            'punchin_location' => 'Gate A',
        ]);

        $before = (array) DB::table('attendances')->find($id);

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $ledger = DB::table(self::LEDGER)->where('attendance_id', $id)->first();

        $this->assertNotNull($ledger);

        // Column for column, including the id, and compared against whatever the
        // table actually had rather than a list this test remembered to write
        // down. A column added to `attendances` next year is archived without
        // anyone updating this assertion.
        $this->assertSame($before, json_decode((string) $ledger->payload, true));

        $this->assertSame('2026-06-01 11:00:00', $this->normalise($ledger->punchin_before));
        $this->assertSame('2026-06-01 09:00:00', $this->normalise($ledger->punchin_after));
        $this->assertSame('2026-06-01 19:00:00', $this->normalise($ledger->punchout_before));
        $this->assertSame('2026-06-01 17:00:00', $this->normalise($ledger->punchout_after));

        $this->assertSame(-self::OFFSET, (int) $ledger->applied_seconds);
        $this->assertSame($device->id, (int) $ledger->biometric_device_id);
        $this->assertSame($device->serial_number, $ledger->device_serial);
        $this->assertNotNull($ledger->applied_at, 'applied_at is stamped in the same transaction as the UPDATE; a null here would mean the row is guarded but not corrected.');

        // The archive is a restore path, so prove it restores.
        DB::table('attendances')->where('id', $id)->update(
            array_diff_key(json_decode((string) $ledger->payload, true), ['id' => null])
        );

        $this->assertSame($before, (array) DB::table('attendances')->find($id));
    }

    public function test_every_row_of_a_run_shares_one_run_id(): void
    {
        $device = $this->device();

        for ($i = 0; $i < 3; $i++) {
            $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        }

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
            // Force more than one batch, so the run id is proven to span chunks
            // rather than being regenerated per transaction.
            '--chunk' => 1,
        ])->assertExitCode(0);

        $runIds = DB::table(self::LEDGER)->distinct()->pluck('run_id');

        $this->assertCount(3, DB::table(self::LEDGER)->get());
        $this->assertCount(1, $runIds);
    }

    // ──────────────────────────────────────────────────────────────
    //  Date boundary
    // ──────────────────────────────────────────────────────────────

    public function test_a_punch_that_would_cross_a_date_boundary_aborts_the_whole_run(): void
    {
        $device = $this->device();
        $safeUser = $this->user();
        $crossingUser = $this->user();

        $safe = $this->biometricAttendance($device, $safeUser, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        // 01:30 minus two hours is 23:30 the day before. attendances.date would
        // then disagree with its own punchin, and the row can collide with the
        // previous day's row for the same user.
        $crossing = $this->biometricAttendance($device, $crossingUser, '2026-06-02', '2026-06-02 01:30:00', '2026-06-02 09:30:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(1);

        // Nothing at all was written — not even the rows that were individually
        // safe. A half-applied payroll correction is worse than none.
        $this->assertPunches($safe, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertPunches($crossing, '2026-06-02 01:30:00', '2026-06-02 09:30:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    public function test_the_dry_run_names_the_boundary_crossing_rows(): void
    {
        $device = $this->device();
        $user = $this->user();
        $crossing = $this->biometricAttendance($device, $user, '2026-06-02', '2026-06-02 01:30:00', '2026-06-02 09:30:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
        ])
            ->expectsOutputToContain('ABORTED')
            ->expectsOutputToContain((string) $crossing)
            ->assertExitCode(1);

        $this->assertPunches($crossing, '2026-06-02 01:30:00', '2026-06-02 09:30:00');
    }

    public function test_narrowing_the_range_past_a_crossing_row_lets_the_rest_through(): void
    {
        // The abort is not a dead end: the operator is told to exclude the
        // offending rows and handle them by hand, and that has to actually work.
        $device = $this->device();
        $safe = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $crossing = $this->biometricAttendance($device, $this->user(), '2026-06-02', '2026-06-02 01:30:00', '2026-06-02 09:30:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--until' => '2026-06-01',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($safe, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
        $this->assertPunches($crossing, '2026-06-02 01:30:00', '2026-06-02 09:30:00');
    }

    // ──────────────────────────────────────────────────────────────
    //  Scoping
    // ──────────────────────────────────────────────────────────────

    public function test_from_and_until_bound_the_selection(): void
    {
        $device = $this->device();

        $before = $this->biometricAttendance($device, $this->user(), '2026-05-31', '2026-05-31 11:00:00', '2026-05-31 19:00:00');
        $inside = $this->biometricAttendance($device, $this->user(), '2026-06-15', '2026-06-15 11:00:00', '2026-06-15 19:00:00');
        $after = $this->biometricAttendance($device, $this->user(), '2026-07-01', '2026-07-01 11:00:00', '2026-07-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--from' => '2026-06-01',
            '--until' => '2026-06-30',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($before, '2026-05-31 11:00:00', '2026-05-31 19:00:00');
        $this->assertPunches($inside, '2026-06-15 09:00:00', '2026-06-15 17:00:00');
        $this->assertPunches($after, '2026-07-01 11:00:00', '2026-07-01 19:00:00');

        // A payroll period corrected on its own leaves the rest still correctable
        // later — the ledger only claims what was actually done.
        $this->assertDatabaseCount(self::LEDGER, 1);
        $this->assertDatabaseHas(self::LEDGER, ['attendance_id' => $inside]);
    }

    public function test_the_range_boundaries_are_inclusive(): void
    {
        $device = $this->device();
        $first = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $last = $this->biometricAttendance($device, $this->user(), '2026-06-30', '2026-06-30 11:00:00', '2026-06-30 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--from' => '2026-06-01',
            '--until' => '2026-06-30',
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($first, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
        $this->assertPunches($last, '2026-06-30 09:00:00', '2026-06-30 17:00:00');
    }

    public function test_another_devices_history_is_left_alone(): void
    {
        $skewed = $this->device(['serial_number' => 'AF6P231260266']);
        $healthy = $this->device(['serial_number' => 'HEALTHY-0001']);

        $skewedRow = $this->biometricAttendance($skewed, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $healthyRow = $this->biometricAttendance($healthy, $this->user(), '2026-06-01', '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => 'AF6P231260266',
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($skewedRow, '2026-06-01 09:00:00', '2026-06-01 17:00:00');

        // The device with a correct clock is why --device is required. Applying
        // one unit's offset to another's history would corrupt good data.
        $this->assertPunches($healthyRow, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
        $this->assertDatabaseCount(self::LEDGER, 1);
        $this->assertDatabaseHas(self::LEDGER, ['attendance_id' => $skewedRow]);
    }

    public function test_a_device_that_does_not_exist_corrects_nothing(): void
    {
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => 'NOT-A-REAL-SERIAL',
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    public function test_a_device_can_be_named_by_id(): void
    {
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => (string) $device->id,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(0);

        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
    }

    // ──────────────────────────────────────────────────────────────
    //  Refusals
    // ──────────────────────────────────────────────────────────────

    public function test_device_is_required(): void
    {
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    public function test_seconds_is_required_and_has_no_default(): void
    {
        // The whole reason this is required: 7200 was argued for from this
        // device's samples. Inheriting it silently on the next device would
        // corrupt that device's history with this one's number.
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    public function test_a_zero_offset_is_refused_rather_than_burning_the_guard(): void
    {
        // --seconds=0 would archive every matching row, changing nothing, and
        // permanently exclude them all from a later real correction.
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => 0,
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertDatabaseCount(self::LEDGER, 0);
        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
    }

    public function test_an_implausibly_large_offset_is_refused(): void
    {
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => 999999,
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
        $this->assertDatabaseCount(self::LEDGER, 0);
    }

    public function test_a_non_numeric_offset_is_refused(): void
    {
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => '2h',
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
    }

    public function test_an_inverted_date_range_is_refused(): void
    {
        $device = $this->device();
        $id = $this->biometricAttendance($device, $this->user(), '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--from' => '2026-06-30',
            '--until' => '2026-06-01',
            '--apply' => true,
        ])->assertExitCode(1);

        $this->assertPunches($id, '2026-06-01 11:00:00', '2026-06-01 19:00:00');
    }

    // ──────────────────────────────────────────────────────────────
    //  Derived state
    // ──────────────────────────────────────────────────────────────

    public function test_policy_state_is_reported_as_stale_and_left_exactly_as_it_was(): void
    {
        // Not a nicety. `policy_status` / `needs_approval` /
        // `policy_exception_reason` were decided by PunchPolicyGuard against the
        // device's WRONG time, and correcting the punch does not re-decide them.
        // Recomputing here would silently overwrite exceptions an approver may
        // already have actioned, using today's roster and policy rather than the
        // ones in force on the historical date. The command reports; a human
        // decides.
        $device = $this->device();
        $user = $this->user();
        $id = $this->biometricAttendance($device, $user, '2026-06-01', '2026-06-01 11:00:00', '2026-06-01 19:00:00');

        DB::table('attendances')->where('id', $id)->update([
            'policy_status' => 'provisional',
            'needs_approval' => true,
            'policy_exception_reason' => 'Punch outside permitted window',
            'symbol' => 'P',
        ]);

        $output = $this->runCommand([
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
        ]);

        $this->assertStringContainsString('STALE', $output);

        // The stored reason is the time-dependent one, and the report has to say
        // so rather than lumping every exception together — 'flagged by policy'
        // is decided without reference to the clock and is NOT stale.
        $this->assertStringContainsString('outside permitted window', $output);
        $this->assertStringContainsString('time-dependent', $output);

        // Only a row whose punch-IN moved can carry a stale verdict, and this
        // one's did.
        $this->assertMatchesRegularExpression('/rows whose punch-in actually moved\s*\|\s*1\s/', $output);

        $row = DB::table('attendances')->find($id);

        $this->assertSame('provisional', $row->policy_status);
        $this->assertEquals(1, $row->needs_approval);
        $this->assertSame('Punch outside permitted window', $row->policy_exception_reason);
        $this->assertSame('P', $row->symbol);

        // …while the punches themselves did move. The point is that the two are
        // now inconsistent, on purpose and in the open.
        $this->assertPunches($id, '2026-06-01 09:00:00', '2026-06-01 17:00:00');
    }

    // ──────────────────────────────────────────────────────────────
    //  Scale
    // ──────────────────────────────────────────────────────────────

    public function test_chunking_covers_every_row_across_many_batches(): void
    {
        // Keyset chunking on a set that shrinks as it is walked: each corrected
        // row acquires a ledger entry and drops out of the candidate query. An
        // OFFSET-paged implementation would skip a row per page here.
        $device = $this->device();
        $user = $this->user();
        $ids = [];

        for ($day = 1; $day <= 12; $day++) {
            $date = sprintf('2026-06-%02d', $day);
            $ids[] = $this->biometricAttendance($device, $user, $date, $date.' 11:00:00', $date.' 19:00:00');
        }

        $this->artisan(self::COMMAND, [
            '--device' => $device->serial_number,
            '--seconds' => self::OFFSET,
            '--apply' => true,
            '--chunk' => 5,
        ])->assertExitCode(0);

        foreach ($ids as $index => $id) {
            $date = sprintf('2026-06-%02d', $index + 1);
            $this->assertPunches($id, $date.' 09:00:00', $date.' 17:00:00');
        }

        $this->assertDatabaseCount(self::LEDGER, 12);
    }

    // ──────────────────────────────────────────────────────────────
    //  Fixtures
    // ──────────────────────────────────────────────────────────────

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'adms',
            'auth_token' => 'token-'.uniqid(),
            'is_active' => true,
        ], $overrides));
    }

    private function user(): User
    {
        return User::factory()->create();
    }

    /**
     * An attendance row exactly as the pre-fix biometric path left it: the
     * punch times in `attendances` are byte-identical to the raw `punch_time`
     * the device reported, and the log rows carry no `corrected_punch_time`.
     */
    private function biometricAttendance(
        BiometricDevice $device,
        User $user,
        string $date,
        ?string $punchin,
        ?string $punchout
    ): int {
        $id = $this->attendance($user->id, $date, $punchin, $punchout);

        if ($punchin !== null) {
            $this->attLog($device, $user, $punchin, 'in');
        }

        if ($punchout !== null) {
            $this->attLog($device, $user, $punchout, 'out');
        }

        return $id;
    }

    private function attendance(int $userId, string $date, ?string $punchin, ?string $punchout): int
    {
        return (int) DB::table('attendances')->insertGetId([
            'user_id' => $userId,
            'date' => $date,
            'punchin' => $punchin,
            'punchout' => $punchout,
            'policy_status' => 'accepted',
            'needs_approval' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function attLog(BiometricDevice $device, User $user, string $punchTime, string $checkType, array $overrides = []): void
    {
        DB::table('biometric_att_logs')->insert(array_merge([
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => (string) $user->employee_id,
            'user_id' => $user->id,
            'punch_time' => $punchTime,
            'corrected_punch_time' => null,
            'check_type' => $checkType,
            'punch_status' => 'processed',
            'occurred_at' => $punchTime,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    // ──────────────────────────────────────────────────────────────
    //  Assertions
    // ──────────────────────────────────────────────────────────────

    /**
     * Run the command and return everything it printed.
     *
     * @param  array<string, mixed>  $options
     */
    private function runCommand(array $options): string
    {
        Artisan::call(self::COMMAND, $options);

        return Artisan::output();
    }

    private function assertPunches(int $id, ?string $punchin, ?string $punchout): void
    {
        $row = DB::table('attendances')->find($id);

        $this->assertNotNull($row, "Attendance {$id} disappeared.");
        $this->assertSame($punchin, $this->normalise($row->punchin), "punchin of attendance {$id}");
        $this->assertSame($punchout, $this->normalise($row->punchout), "punchout of attendance {$id}");
    }

    /**
     * Compare timestamps as `Y-m-d H:i:s` regardless of how the driver echoed
     * them back. SQLite returns the string it was given; MySQL normalises. The
     * assertion is about the moment, not about the storage format.
     */
    private function normalise($value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse((string) $value)->format('Y-m-d H:i:s');
    }
}
