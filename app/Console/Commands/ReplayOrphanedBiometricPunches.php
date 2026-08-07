<?php

namespace App\Console\Commands;

use App\Models\HRM\Attendance;
use App\Models\HRM\BiometricAttLog;
use App\Models\HRM\BiometricDevice;
use App\Models\User;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Biometric\BiometricProcessingService;
use App\Services\Biometric\DeviceClockService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * Recover the attendance that biometric punches should have produced and did not.
 *
 * ── The backlog this exists to clear ────────────────────────────────────────
 *
 * A full raw pull from the production MB460 (`AF6P231260266`) — 1,054 records,
 * its complete history — reconciled against `attendances`:
 *
 *     540 device user-days  →  33 with no attendance at all  (6.1%)
 *
 * Every one of those punches was ingested successfully. The loss is entirely at
 * punch→attendance conversion, and 22 of the 33 are one cause: the terminal was
 * left in OUT mode, so the day's FIRST punch carried `status=1`, was processed as
 * a check-out with nothing open to close, and was rejected with
 * "No open attendance record to punch out from." Affected PINs: 120, 154, 155,
 * 302, 304, 307. On 2026-07-11 three of them had an OUT-first day simultaneously,
 * which is one terminal in the wrong mode rather than three people making the
 * same mistake.
 *
 * AttendancePunchService now records such a punch as the day's check-in (see
 * RECOVERY_OUT_FIRST). That fixes the forward path only. This command is the
 * backlog: it re-runs punches already sitting in `biometric_att_logs` through the
 * same rules, so the lost days can be recovered without touching the device.
 *
 * ── What it will and will not replay ────────────────────────────────────────
 *
 * Rows are selected by the reason they were rejected with, and the four real
 * reasons in production fall into three very different categories:
 *
 *   "No open attendance record to punch out from."
 *       RECOVERABLE. This is the OUT-first defect and the only category the
 *       punch-service change actually addresses. Replayed.
 *
 *   "User has no attendance type assigned"
 *   "Attendance type is not biometric: wifi_ip_3"
 *       NEEDS CONFIGURATION REVIEW — and deliberately NOT replayed. These are not
 *       defects. Those employees were configured for WiFi/GPS attendance at the
 *       time; a terminal read them anyway. Manufacturing biometric attendance for
 *       them would silently override an attendance policy an admin actually set,
 *       which is a worse outcome than the missing rows. They are reported, with
 *       names and counts, so a human can decide.
 *
 *   "Already punched in for this period."
 *       NO ACTION NEEDED. The day already has attendance; this was a redundant
 *       second in-punch, not a lost day. Reported for completeness.
 *
 * Anything else is left alone entirely — an unrecognised reason is not something
 * this command has an argument about.
 *
 * ── Dry run is the default ──────────────────────────────────────────────────
 *
 * Writing requires `--apply`. Without it the command reports exactly which
 * user-days would gain attendance and why each currently has none, and changes
 * nothing.
 *
 * ── Idempotency ─────────────────────────────────────────────────────────────
 *
 * Selection is `punch_status = 'failed'`. A successfully replayed row becomes
 * `processed`, so the second run does not select it. Behind that, two further
 * guards make a double-create impossible even if a row were re-selected:
 * isDuplicatePunch() rejects a punch whose moment already reached `attendances`,
 * and the promotion rule itself refuses to fire when the day already holds any
 * attendance row. A row that fails AGAIN keeps its `failed` status and will be
 * re-attempted by a later run — which is harmless, because the same two guards
 * apply, and honest, because it is still an unrecovered punch.
 *
 * ── Portability ─────────────────────────────────────────────────────────────
 *
 * Query builder and PHP only. Grouping, date arithmetic and every comparison
 * happen in PHP, so the SQLite the tests run on and the MySQL production runs on
 * execute the same statements.
 */
class ReplayOrphanedBiometricPunches extends Command
{
    protected $signature = 'biometric:replay-orphaned-punches
        {--device= : Serial number (preferred) or id. Omit to cover every device}
        {--from= : Only punches whose DEVICE timestamp is on or after this date (Y-m-d)}
        {--until= : Only punches whose DEVICE timestamp is on or before this date (Y-m-d)}
        {--dry-run : Report only. This is ALREADY THE DEFAULT and the flag is accepted for explicitness}
        {--apply : Actually write. Without this the command changes nothing}
        {--samples=40 : User-days listed per section before the list is truncated}';

    protected $description = 'Replay biometric punches that were captured but never became attendance (dry run by default)';

    private ?BiometricDevice $device = null;

    private ?string $from = null;

    private ?string $until = null;

    /** @var array<int, BiometricDevice|null> */
    private array $deviceCache = [];

    public function __construct(
        private readonly BiometricProcessingService $biometric,
        private readonly DeviceClockService $clock,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        if (($resolved = $this->resolveOptions()) !== self::SUCCESS) {
            return $resolved;
        }

        $this->printHeader();

        $buckets = $this->bucket($this->candidates());

        $groups = $this->groupIntoUserDays($buckets['recoverable']);

        $this->reportRecoverable($groups, $buckets['recoverable']);
        $this->reportConfigReview($buckets['config_review']);
        $this->reportNoAction($buckets['no_action']);
        $this->reportSkipped($buckets['skipped']);

        if ($buckets['recoverable']->isEmpty()) {
            $this->newLine();
            $this->info('Nothing to replay.');

            return self::SUCCESS;
        }

        if (! $this->option('apply')) {
            $this->newLine();
            $this->warn('DRY RUN — nothing was written. Re-run with --apply to record the attendance above.');

            return self::SUCCESS;
        }

        return $this->apply($buckets['recoverable'], $groups);
    }

    // ──────────────────────────────────────────────────────────────
    //  Options
    // ──────────────────────────────────────────────────────────────

    private function resolveOptions(): int
    {
        // Reset every per-run field FIRST.
        //
        // A Command is resolved once and reused for the life of the process, so
        // these properties survive from one invocation to the next: the scheduler,
        // a queued dispatch, `Artisan::call()` twice in a request, and the test
        // suite all share one instance. Assigning them only when their option is
        // present — which is what this method used to do — meant a run with no
        // `--device` silently inherited the PREVIOUS run's device and quietly
        // replayed nothing while reporting success. A stale filter on a recovery
        // command is a silent under-recovery, which is exactly the failure this
        // command exists to find.
        $this->device = null;
        $this->from = null;
        $this->until = null;
        $this->deviceCache = [];

        if ($this->option('apply') && $this->option('dry-run')) {
            $this->error('--apply and --dry-run contradict each other. Pass --apply to write, or neither (dry run is the default).');

            return self::FAILURE;
        }

        $deviceOption = trim((string) $this->option('device'));

        if ($deviceOption !== '') {
            $device = BiometricDevice::where('serial_number', $deviceOption)->first();

            if ($device === null && ctype_digit($deviceOption)) {
                $device = BiometricDevice::find((int) $deviceOption);
            }

            if ($device === null) {
                $this->error("No biometric device matches --device={$deviceOption} (tried serial number, then id).");

                return self::FAILURE;
            }

            $this->device = $device;
        }

        foreach (['from', 'until'] as $bound) {
            $raw = trim((string) $this->option($bound));

            if ($raw === '') {
                continue;
            }

            try {
                $this->{$bound} = Carbon::parse($raw)->format('Y-m-d');
            } catch (\Throwable) {
                $this->error("--{$bound} is not a date this command can read: {$raw}");

                return self::FAILURE;
            }
        }

        if ($this->from !== null && $this->until !== null && $this->from > $this->until) {
            $this->error("--from ({$this->from}) is after --until ({$this->until}); that range selects nothing.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────
    //  Selection
    // ──────────────────────────────────────────────────────────────

    /**
     * Every `failed` ATTLOG row whose rejection reason this command recognises.
     *
     * Matched against shared constants rather than copies of the strings, so the
     * producer (AttendancePunchService / validateAttendanceEligibility) and this
     * consumer cannot drift apart and leave the command silently selecting
     * nothing while still reporting success.
     *
     * Ordered by `punch_time`: in/out pairing is order-sensitive, and a day's
     * second OUT punch must not be replayed before the first has become the
     * check-in it will close.
     *
     * @return Collection<int, BiometricAttLog>
     */
    private function candidates(): Collection
    {
        $query = BiometricAttLog::query()
            ->where('punch_status', 'failed')
            ->where(function ($q) {
                $q->where('punch_status_reason', AttendancePunchService::NO_OPEN_RECORD_MESSAGE)
                    ->orWhere('punch_status_reason', AttendancePunchService::ALREADY_PUNCHED_IN_MESSAGE)
                    ->orWhere('punch_status_reason', BiometricProcessingService::REASON_NO_ATTENDANCE_TYPE)
                    ->orWhere('punch_status_reason', 'like', BiometricProcessingService::REASON_NOT_BIOMETRIC_PREFIX.'%');
            });

        if ($this->device !== null) {
            $query->where('biometric_device_id', $this->device->id);
        }

        // Bounded on the RAW device timestamp, which is the indexed column and the
        // date an operator reads off the terminal. A device with a measured clock
        // offset can therefore have a punch land on the adjacent day once
        // corrected; the report prints the corrected date, so that is visible
        // rather than hidden.
        if ($this->from !== null) {
            $query->where('punch_time', '>=', $this->from.' 00:00:00');
        }

        if ($this->until !== null) {
            $query->where('punch_time', '<=', $this->until.' 23:59:59');
        }

        return $query->orderBy('punch_time')->orderBy('id')->get();
    }

    /**
     * Sort candidates into what will be replayed, what will be reported, and what
     * cannot be acted on at all.
     *
     * ── Current eligibility decides, not the recorded reason ────────────────
     *
     * The recorded reason says why a punch failed THEN. It is evidence about a
     * moment in the past, and the only question that matters now is whether this
     * employee may record biometric attendance TODAY. So the reason no longer
     * routes anything (except "Already punched in", which is a statement about the
     * day rather than about configuration) — `validateAttendanceEligibility()` does,
     * and it is applied to every row in both directions:
     *
     *  - recorded as recoverable, ineligible NOW → configuration review. Replaying
     *    would override the policy an admin has since set.
     *  - recorded as a configuration failure, eligible NOW → RECOVERABLE. The
     *    reason has expired and the day is genuinely recoverable.
     *
     * The second direction is not hypothetical. PIN 307's punches were captured
     * while that PIN belonged to Keshab Lal Kundu, who was on `wifi_ip_3`, so they
     * were rejected with "Attendance type is not biometric: wifi_ip_3". PIN 307 has
     * since moved to Md. Abdul Hannan, who IS biometric-eligible, and the rows were
     * re-pointed to him. Matching on the recorded string left 2026-07-28 stranded in
     * configuration review even though it was recoverable — the reason described a
     * different person.
     *
     * This is not "retry everything": candidates() still selects only the four known
     * rejection reasons, and an employee genuinely on `wifi_ip` today is still
     * reported and never forced. What changed is which of those four is allowed to
     * be overruled by present-day fact.
     *
     * ── Attribution must agree before anything is replayed ──────────────────
     *
     * This command predicts using the row's stored `user_id`, but the replay path
     * (importDownloadedLog → resolveOrCreateUser) resolves the user by `user_pin`.
     * Those normally agree. When they do not — a PIN reassigned without the rows
     * being re-pointed, or a PIN nobody holds — the report would promise attendance
     * for one employee while the apply created it for another, or created a
     * placeholder user and no attendance at all. Recording attendance against the
     * wrong person is worse than leaving the day missing, so a divergence is
     * refused and named rather than guessed at.
     *
     * @param  Collection<int, BiometricAttLog>  $rows
     * @return array{recoverable: Collection<int, array<string, mixed>>, config_review: Collection<int, array<string, mixed>>, no_action: Collection<int, array<string, mixed>>, skipped: Collection<int, array<string, mixed>>}
     */
    private function bucket(Collection $rows): array
    {
        $recoverable = collect();
        $configReview = collect();
        $noAction = collect();
        $skipped = collect();

        foreach ($rows as $row) {
            $reason = (string) $row->punch_status_reason;

            $entry = [
                'log' => $row,
                'pin' => (string) $row->user_pin,
                'recorded_reason' => $reason,
                'check_type' => (string) $row->check_type,
            ];

            if ($reason === AttendancePunchService::ALREADY_PUNCHED_IN_MESSAGE) {
                $noAction[] = $entry + ['user' => $this->resolveUser($row)];

                continue;
            }

            $device = $this->deviceFor($row);

            if ($device === null) {
                // Its device row was deleted (`biometric_device_id` is
                // nullOnDelete). Zone eligibility and clock correction are both
                // properties of a device, so there is nothing to replay against.
                $skipped[] = $entry + ['why' => 'the device this punch came from no longer exists'];

                continue;
            }

            $user = $this->resolveUser($row);

            if ($user === null) {
                $skipped[] = $entry + ['why' => 'no user is linked to PIN '.$row->user_pin];

                continue;
            }

            // Who the REPLAY would attribute this punch to. It resolves by PIN,
            // this command predicts from the row's corrected `user_id`, and the
            // two must agree before anything is written.
            $replayUser = User::withTrashed()->where('employee_id', (string) $row->user_pin)->first();

            if ($replayUser === null || $replayUser->id !== $user->id) {
                $skipped[] = $entry + ['why' => sprintf(
                    'PIN %s resolves to %s but the row is attributed to %s — re-point the PIN or the row before replaying',
                    $row->user_pin,
                    $replayUser === null ? 'nobody' : '#'.$replayUser->id,
                    '#'.$user->id
                )];

                continue;
            }

            $entry['user'] = $user;
            $entry['device'] = $device;
            $entry['moment'] = $this->correctedMoment($device, $row);

            // The single routing decision, applied to every row regardless of the
            // reason it originally failed with. See the block comment above.
            $eligibility = $this->biometric->validateAttendanceEligibility($user, $device);

            if (! $eligibility['valid']) {
                $configReview[] = $entry + ['current_reason' => (string) $eligibility['reason']];

                continue;
            }

            $recoverable[] = $entry;
        }

        return [
            'recoverable' => $recoverable,
            'config_review' => $configReview,
            'no_action' => $noAction,
            'skipped' => $skipped,
        ];
    }

    private function deviceFor(BiometricAttLog $row): ?BiometricDevice
    {
        $id = $row->biometric_device_id;

        if ($id === null) {
            return null;
        }

        return $this->deviceCache[$id] ??= BiometricDevice::find($id);
    }

    /**
     * The user this punch belongs to.
     *
     * `withTrashed()` because the ingest path soft-deletes auto-created
     * placeholders, and an admin linking a PIN to a real employee is precisely the
     * case this command is asked to clear up afterwards.
     */
    private function resolveUser(BiometricAttLog $row): ?User
    {
        if ($row->user_id !== null) {
            $user = User::withTrashed()->find($row->user_id);

            if ($user !== null) {
                return $user;
            }
        }

        return User::withTrashed()->where('employee_id', (string) $row->user_pin)->first();
    }

    /**
     * The moment this punch would actually be recorded at.
     *
     * Recomputed from the immutable raw `punch_time` with the device's CURRENT
     * estimate — exactly what importDownloadedLog() will do at apply time — so the
     * dry run reports the times that would really land, not the device's skewed
     * ones.
     */
    private function correctedMoment(BiometricDevice $device, BiometricAttLog $row): Carbon
    {
        $raw = $row->punch_time instanceof Carbon
            ? $row->punch_time->format('Y-m-d H:i:s')
            : (string) $row->getRawOriginal('punch_time');

        return Carbon::parse($this->clock->correct($device, $raw)['punch_time']);
    }

    // ──────────────────────────────────────────────────────────────
    //  User-day grouping
    // ──────────────────────────────────────────────────────────────

    /**
     * Collapse recoverable punches into the user-days they belong to.
     *
     * Keyed on the CORRECTED punch's calendar date. That is not always the
     * business date — AttendancePunchService rebinds a post-midnight punch to the
     * prior day's overnight shift when the roster says so — but resolving the
     * roster for every historical date here would make the report depend on
     * schedule state this command has no business interpreting. The apply path
     * resolves the real business date, and the report says so rather than
     * pretending otherwise.
     *
     * @param  Collection<int, array<string, mixed>>  $recoverable
     * @return Collection<string, array<string, mixed>>
     */
    private function groupIntoUserDays(Collection $recoverable): Collection
    {
        $groups = [];

        foreach ($recoverable as $entry) {
            $date = $entry['moment']->format('Y-m-d');
            $key = $entry['user']->id.'|'.$date;

            $groups[$key] ??= [
                'user' => $entry['user'],
                'pin' => $entry['pin'],
                'date' => $date,
                'punches' => [],
            ];

            $groups[$key]['punches'][] = $entry;
        }

        foreach ($groups as $key => $group) {
            usort($group['punches'], fn ($a, $b) => $a['moment'] <=> $b['moment']);

            $groups[$key] = $group + $this->predict($group);
        }

        return collect($groups);
    }

    /**
     * What this user-day would become.
     *
     * Two different routes produce attendance and the report must not conflate
     * them, because only one of them is a recovery:
     *
     *  - the day's first punch is an IN punch → an ordinary check-in. This is the
     *    common shape once a configuration-failure row becomes eligible: those
     *    punches were usually never OUT-first, they were simply refused at the
     *    door, and replaying them takes the completely normal path.
     *  - the day's first punch is a plain OUT → promoted to the day's check-in by
     *    AttendancePunchService. Annotated as such so a reviewer can see which
     *    days depended on the promotion rule.
     *
     * `break_out` / `ot_out` first is neither: it is never promoted and has
     * nothing to close, so it would fail again and is reported as gaining nothing.
     *
     * @param  array<string, mixed>  $group
     * @return array<string, mixed>
     */
    private function predict(array $group): array
    {
        $punches = $group['punches'];
        $first = $punches[0];

        $hasAttendance = Attendance::where('user_id', $group['user']->id)
            ->whereDate('date', $group['date'])
            ->exists();

        if ($hasAttendance) {
            return [
                'gains' => false,
                'outcome' => 'day already has attendance — replay can only close an open row',
            ];
        }

        $opensDay = in_array($first['check_type'], ['in', 'break_in', 'ot_in'], true);
        $promoted = $first['check_type'] === 'out';

        if (! $opensDay && ! $promoted) {
            return [
                'gains' => false,
                'outcome' => 'first punch is '.$first['check_type'].', which neither opens a day nor is promotable',
            ];
        }

        // Appended, never prefixed, so the times stay the first thing read.
        $note = $promoted ? ' (first punch promoted from OUT)' : '';
        $in = $first['moment']->format('H:i');

        if (count($punches) < 2) {
            return [
                'gains' => true,
                'outcome' => 'in '.$in.' (no closing punch — day stays open)'.$note,
            ];
        }

        return [
            'gains' => true,
            'outcome' => 'in '.$in.' → out '.end($punches)['moment']->format('H:i').$note,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Report
    // ──────────────────────────────────────────────────────────────

    private function printHeader(): void
    {
        $this->newLine();
        $this->line('<comment>Orphaned biometric punch replay</comment>');
        $this->table([], [
            ['Mode', $this->option('apply') ? 'APPLY' : 'DRY RUN (default)'],
            ['Device', $this->device === null
                ? 'every device'
                : $this->device->serial_number.' (id '.$this->device->id.', '.($this->device->name ?? 'unnamed').')'],
            ['Device-time range', ($this->from ?? 'beginning').' .. '.($this->until ?? 'end')],
        ]);
    }

    /**
     * @param  Collection<string, array<string, mixed>>  $groups
     * @param  Collection<int, array<string, mixed>>  $recoverable
     */
    private function reportRecoverable(Collection $groups, Collection $recoverable): void
    {
        $this->newLine();
        $this->line('<comment>RECOVERABLE — the employee is biometric-eligible today</comment>');
        $this->line('  Bucketed on CURRENT eligibility, not on the reason each punch was rejected with: that reason');
        $this->line('  describes a moment in the past, and a PIN can change hands. A punch refused because the PIN\'s');
        $this->line('  previous holder was on WiFi attendance is recoverable once the PIN\'s current holder is not.');
        $this->line('  Where the day\'s first punch is a plain OUT, it is the OUT-first defect — the terminal was left in');
        $this->line('  OUT mode — and AttendancePunchService records it as the day\'s check-in when, and only when, that');
        $this->line('  day holds no attendance row at all.');

        if ($recoverable->isNotEmpty()) {
            $reasons = $recoverable->countBy('recorded_reason')->sortDesc();

            $this->line('  Originally rejected with:');

            foreach ($reasons as $reason => $count) {
                $this->line('    '.$count.' × "'.$reason.'"');
            }
        }

        if ($groups->isEmpty()) {
            $this->newLine();
            $this->line('  None.');

            return;
        }

        $limit = max(1, (int) $this->option('samples'));
        $sorted = $groups->sortBy([['date', 'asc'], ['pin', 'asc']])->values();

        $this->newLine();
        $this->table(
            ['PIN', 'Employee', 'Date', 'Device punches', 'Would become'],
            $sorted->take($limit)->map(fn (array $g) => [
                $g['pin'],
                $this->employeeLabel($g['user']),
                $g['date'],
                collect($g['punches'])
                    ->map(fn ($p) => $p['moment']->format('H:i').' '.$p['check_type'])
                    ->implode(', '),
                $g['gains'] ? $g['outcome'] : '— '.$g['outcome'],
            ])->all()
        );

        if ($sorted->count() > $limit) {
            $this->line('  … and '.($sorted->count() - $limit).' more user-day(s). Raise --samples to see them.');
        }

        $gaining = $sorted->where('gains', true)->count();

        $this->newLine();
        $this->line(sprintf(
            '  %d user-day(s) would GAIN attendance, from %d punch row(s) across %d user-day(s) in this bucket.',
            $gaining,
            $recoverable->count(),
            $sorted->count()
        ));
        $this->line('  Dates are the CORRECTED device time\'s calendar date. For an overnight shift the punch service may');
        $this->line('  bind a post-midnight punch to the prior rostered day, so the stored business date can differ.');
    }

    /**
     * The honest half of the report: real punches this command refuses to convert.
     *
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function reportConfigReview(Collection $entries): void
    {
        $this->newLine();
        $this->line('<comment>NEEDS CONFIGURATION REVIEW — reported, NOT replayed</comment>');

        if ($entries->isEmpty()) {
            $this->line('  None.');

            return;
        }

        $this->line('  These punches are real: a terminal read these employees and the records are on file. They are not');
        $this->line('  replayed because the employee is configured for a NON-BIOMETRIC method (WiFi/IP, GPS, QR) RIGHT NOW,');
        $this->line('  or for none at all. The reason below is re-evaluated on every run, so this is today\'s configuration');
        $this->line('  and not the reason the punch originally failed with. That is a policy decision an admin made, not a');
        $this->line('  defect — manufacturing biometric attendance here would silently override it.');
        $this->line('  <fg=yellow>To recover these days, change the employee\'s attendance configuration first, then re-run.</>');
        $this->newLine();

        $byUser = $entries->groupBy(fn (array $e) => $e['user']->id.'|'.$e['current_reason']);

        $rows = $byUser->map(function (Collection $group) {
            $first = $group->first();

            return [
                $first['pin'],
                $this->employeeLabel($first['user']),
                $first['current_reason'],
                (string) $group->count(),
                (string) $group->map(fn ($e) => $e['moment']->format('Y-m-d'))->unique()->count(),
            ];
        })->values();

        $limit = max(1, (int) $this->option('samples'));

        $this->table(['PIN', 'Employee', 'Reason (current)', 'Punches', 'User-days'], $rows->take($limit)->all());

        if ($rows->count() > $limit) {
            $this->line('  … and '.($rows->count() - $limit).' more.');
        }
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function reportNoAction(Collection $entries): void
    {
        $this->newLine();
        $this->line('<comment>NO ACTION NEEDED</comment>');

        if ($entries->isEmpty()) {
            $this->line('  None.');

            return;
        }

        $this->line(sprintf(
            '  %d punch(es) rejected with "%s". The day already had attendance — this was a redundant',
            $entries->count(),
            AttendancePunchService::ALREADY_PUNCHED_IN_MESSAGE
        ));
        $this->line('  second in-punch, not a lost day. Nothing to recover.');
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $entries
     */
    private function reportSkipped(Collection $entries): void
    {
        if ($entries->isEmpty()) {
            return;
        }

        $this->newLine();
        $this->line('<comment>SKIPPED — cannot be replayed</comment>');

        foreach ($entries->groupBy('why') as $why => $group) {
            $this->line('  '.$group->count().' punch(es): '.$why.'.');
        }
    }

    private function employeeLabel(User $user): string
    {
        $label = (string) ($user->name ?? 'unnamed');

        if ($user->trashed()) {
            $label .= ' [inactive]';
        }

        return $label;
    }

    // ──────────────────────────────────────────────────────────────
    //  Apply
    // ──────────────────────────────────────────────────────────────

    /**
     * Replay every recoverable row, oldest punch first.
     *
     * Order is load-bearing: a day's second OUT punch has to arrive after the
     * first has been promoted, or it has nothing to close and simply fails again.
     * `$recoverable` preserves the `punch_time` ordering of the selection query.
     *
     * @param  Collection<int, array<string, mixed>>  $recoverable
     * @param  Collection<string, array<string, mixed>>  $groups
     */
    private function apply(Collection $recoverable, Collection $groups): int
    {
        $this->newLine();
        $this->info('APPLYING — replaying '.$recoverable->count().' punch row(s).');

        $tally = ['imported' => 0, 'duplicate' => 0, 'unknown_user' => 0, 'failed' => 0];
        $stuck = [];

        foreach ($recoverable as $entry) {
            try {
                $outcome = $this->biometric->replayAttLog($entry['log'], $entry['device']);
            } catch (\Throwable $e) {
                // replayAttLog already swallows per-row failures and marks the row;
                // this is the belt to that braces. One bad row never aborts the run.
                $this->warn('  ATTLOG '.$entry['log']->id.' errored: '.$e->getMessage());
                $tally['failed']++;
                $stuck[] = $entry;

                continue;
            }

            $tally[$outcome] = ($tally[$outcome] ?? 0) + 1;

            if ($outcome !== 'imported' && $outcome !== 'duplicate') {
                $stuck[] = $entry;
            }
        }

        // Re-ask the database rather than trusting the prediction: the point of
        // this line is to state what actually happened.
        $gained = $groups->filter(fn (array $g) => Attendance::where('user_id', $g['user']->id)
            ->whereDate('date', $g['date'])
            ->exists())->count();

        $this->newLine();
        $this->table([], [
            ['Punch rows replayed', (string) $recoverable->count()],
            ['  → recorded', (string) $tally['imported']],
            ['  → already recorded (duplicate)', (string) $tally['duplicate']],
            ['  → still failed', (string) ($tally['failed'] + $tally['unknown_user'])],
            ['User-days now holding attendance', $gained.' of '.$groups->count()],
        ]);

        $this->reportStuckRows($stuck);

        Log::info('Orphaned biometric punch replay applied', [
            'device_id' => $this->device?->id,
            'serial' => $this->device?->serial_number,
            'from' => $this->from,
            'until' => $this->until,
            'rows_replayed' => $recoverable->count(),
            'user_days_with_attendance' => $gained,
        ] + $tally);

        return self::SUCCESS;
    }

    /**
     * Name every row that failed AGAIN, with the reason it now carries.
     *
     * A row that fails on replay keeps `punch_status = 'failed'`, so the next run
     * selects it and retries it. That is safe — the duplicate check and the
     * promotion rule both refuse a day that already has attendance — but it means
     * a permanently-failing row is retried forever and silently, and the only way
     * to notice was to re-run and compare counts.
     *
     * So the rows are named here, with the reason re-read from the database AFTER
     * the replay wrote it: the whole point is the CURRENT reason, which is usually
     * different from the one that put the row in scope. A human can act on this
     * list directly instead of discovering it by running the command twice.
     *
     * @param  list<array<string, mixed>>  $stuck
     */
    private function reportStuckRows(array $stuck): void
    {
        if ($stuck === []) {
            return;
        }

        $this->newLine();
        $this->line('<comment>STILL FAILING after replay — these need a human</comment>');
        $this->line('  They keep punch_status = failed and WILL be retried by the next run. That is safe but not');
        $this->line('  progress: a row that never succeeds will be retried forever unless someone looks at it.');
        $this->newLine();

        $reasons = BiometricAttLog::whereIn('id', array_map(fn (array $e) => $e['log']->id, $stuck))
            ->pluck('punch_status_reason', 'id');

        $limit = max(1, (int) $this->option('samples'));

        $this->table(
            ['ATTLOG', 'PIN', 'Employee', 'Punch (corrected)', 'Device', 'Now fails with'],
            collect($stuck)->take($limit)->map(fn (array $e) => [
                $e['log']->id,
                $e['pin'],
                isset($e['user']) ? $this->employeeLabel($e['user']) : '—',
                isset($e['moment']) ? $e['moment']->format('Y-m-d H:i') : '—',
                $e['check_type'],
                (string) ($reasons[$e['log']->id] ?? 'unknown'),
            ])->all()
        );

        if (count($stuck) > $limit) {
            $this->line('  … and '.(count($stuck) - $limit).' more. Raise --samples to see them.');
        }
    }
}
