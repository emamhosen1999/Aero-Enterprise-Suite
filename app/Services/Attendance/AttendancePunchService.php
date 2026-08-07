<?php

namespace App\Services\Attendance;

use App\Events\Domain\AttendancePunched;
use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceAuditLog;
use App\Services\Attendance\Contracts\ScheduleResolver;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Service for handling attendance punch operations
 */
class AttendancePunchService
{
    private const DEDUPE_WINDOW_SECONDS = 30;

    private const MAX_OVERNIGHT_HOURS = 18;

    /**
     * Last-resort clamp on a device timestamp that is in the FUTURE.
     *
     * ── Why this value did not catch the two-hour device, and stays anyway ───
     *
     * The production MB460 (`AF6P231260266`) reported every punch exactly two
     * hours ahead for four months. This guard rejects `punch_time > now + 2h`;
     * the real offset measured 7,196 s — 1 h 59 m 56 s — so it passed by four
     * seconds, and 827 punches were written two hours late.
     *
     * The tempting fix is to lower the number. It is the wrong fix, because this
     * guard is the wrong instrument for a constant offset:
     *
     *  - **It is one-sided.** It sees only clocks running FAST. A device two
     *    hours SLOW would be just as wrong and would never trip it, whatever
     *    the threshold.
     *  - **Its remedy destroys the evidence.** When it fires it substitutes
     *     server time for the device's, so the punch's real moment is gone and
     *     the skew can never be measured from it afterwards. Applied to a
     *     systematically-skewed device it would silently convert every punch
     *     into "whenever the push happened to arrive" — which is precisely the
     *     bug this service was written to avoid (see resolvePunchTime).
     *  - **Any threshold is arbitrary.** Two hours here is one timezone step;
     *     one hour is another; there is no value that distinguishes "wrong
     *     clock" from "genuine punch" by size alone.
     *
     * The instrument that does work is measurement: DeviceClockService samples
     * device-vs-server time on live pushes, takes a median, and
     * BiometricProcessingService corrects at ingest. By the time a punch reaches
     * this service its systematic offset is already gone, so what this guard now
     * sees is RESIDUAL error — and 2 h of residual really is nonsense worth
     * clamping. Kept unchanged, therefore, as a backstop for the one case
     * correction cannot cover: a device whose clock is wildly wrong and whose
     * offset has not yet been measured (fewer than
     * DeviceClockService::MIN_TRUSTED_SAMPLES samples), where server time is
     * genuinely the better of two bad answers.
     */
    private const MAX_CLOCK_DRIFT_HOURS = 2;

    private const SYNC_CAPTURE_FUTURE_SKEW_MINUTES = 2;

    private const SYNC_CAPTURE_MAX_AGE_HOURS = 72;

    /**
     * Tolerance window (minutes) used when deciding whether a post-midnight
     * punch-in belongs to the PRIOR business day's overnight shift. Mirrors
     * the spirit of AttendanceStatusService::OUTSIDE_WINDOW_MINUTES.
     */
    private const REBIND_TOLERANCE_MINUTES = 120;

    /**
     * The two rejection messages that get written to
     * `biometric_att_logs.punch_status_reason` when a device punch does not
     * become attendance.
     *
     * Constants rather than four repeated literals because
     * `biometric:replay-orphaned-punches` selects historical failures by matching
     * this exact text. A silent edit to one of the strings would make that command
     * quietly select nothing while still reporting success, so the producer and
     * the consumer now share one definition.
     */
    public const NO_OPEN_RECORD_MESSAGE = 'No open attendance record to punch out from.';

    public const ALREADY_PUNCHED_IN_MESSAGE = 'Already punched in for this period.';

    /**
     * ── OUT-first recovery ──────────────────────────────────────────────────
     *
     * A ZKTeco terminal stamps every punch with a direction byte: `0` = in,
     * `1` = out. That byte is whatever mode the terminal is sitting in, and a
     * terminal can be left in OUT mode. When it is, the FIRST punch of the day
     * arrives as a check-out, finds nothing open to close, and is discarded —
     * the employee's whole day is then absent from `attendances`.
     *
     * Measured on the production MB460 (`AF6P231260266`), reconciling its
     * complete 1,054-record history against attendance: 540 device user-days
     * produced 33 with no attendance at all (6.1%). 22 of those are exactly this
     * — the day's first punch carrying `status=1`. It is not individual user
     * error: on 2026-07-11 three different employees (PINs 307, 302, 304) all had
     * an OUT-first day at once, which is one terminal in the wrong mode, not
     * three people making the same mistake.
     *
     * The mapping is NOT wrong and is not changed: `status=1` genuinely is out,
     * and that is what the device said. What changes is what we do with an out
     * punch that has nothing to close.
     *
     * **The rule, in full** — an out punch is recorded as the day's check-in when
     * ALL of these hold (see isRecoverableOrphanedOutPunch):
     *
     *   1. `check_type` is exactly `out`. Not `break_out`, not `ot_out`: those
     *      describe an interruption to a day that is already under way, and
     *      manufacturing a workday out of a break punch would be inventing
     *      attendance rather than recovering it.
     *   2. The punch is device-sourced (`source` = `biometric`/`device`). This is
     *      the SAME trust boundary resolvePunchTime() already uses, and
     *      GuardsServerAuthoritativePunchTime strips `source` from every
     *      human-facing punch request — so a web or mobile punch structurally
     *      cannot reach this rule, and no new trust surface is created.
     *   3. It is not the offline sync channel (`sync_capture`). That channel
     *      replays a human's queued punch and its payload is client-shaped.
     *   4. findOpenAttendanceToClose() returned NULL — there is genuinely nothing
     *      to close, today or on an eligible prior-day overnight row.
     *   5. There is NO attendance row of any kind on the resolved business date —
     *      not merely no OPEN one.
     *
     * **Why it cannot misfire on a real check-out.** Conditions 4 and 5 are
     * evaluated in the branch that today already returns an error, so a punch that
     * closes a real punch-in never reaches the rule at all — it has already
     * returned from punchOut() several lines earlier. Condition 5 is what keeps
     * the second half honest: after a normal in→out day the row exists but is
     * closed, so a stray third out punch is still rejected exactly as it is today
     * rather than opening a phantom second day.
     *
     * **The second OUT punch of an OUT-first day.** Once the 11:03 punch has been
     * promoted, the day HAS an open row, so the 19:08 punch takes the ordinary
     * punchOut() path one branch above and closes it. The day ends up in + out,
     * never two check-ins, and this needs no special case — it falls out of
     * condition 4.
     *
     * **Ordering against device clock correction.** Correction happens strictly
     * earlier, at ingest in BiometricProcessingService, and only ever touches the
     * timestamp — never the direction byte. So correction can neither create nor
     * erase an OUT-first day, and by the time this rule runs $punchTime is already
     * the corrected moment: the business date, the drift backstop and the promoted
     * punch-in all see the same corrected value a normal check-in would.
     */
    public const RECOVERY_OUT_FIRST = 'device_out_first_promoted_to_in';

    /**
     * `attendance_audit_logs.action` written for every recovered day.
     *
     * The recovery is recorded there — not on the attendance row, and not by
     * rewriting what the device said — for three reasons:
     *
     *  - `biometric_att_logs.check_type` must keep holding the device's own
     *    account. It is also part of the punch natural key made UNIQUE by
     *    migration 2026_08_03_000001, so moving it would let a re-pushed punch
     *    slip past that constraint.
     *  - `attendances` has no column for this and inventing one is a schema change
     *    for something the system already has a table for.
     *  - The write happens inside this service, so EVERY ingest path gets it — the
     *    live ADMS push, the downloaded-log import, the direct webhook and the
     *    replay command alike — instead of each caller having to remember.
     *
     * An auditor can therefore tell a recovered day from a normal one with one
     * query, and the row says which moment was promoted and what the device
     * actually reported. The two paths this service owns additionally stamp
     * RECOVERY_REASON onto `biometric_att_logs.punch_status_reason`, so the same
     * fact is visible from the device side of the ATTLOG screen.
     */
    public const RECOVERY_AUDIT_ACTION = 'biometric_out_first_recovery';

    public const RECOVERY_REASON = 'Device reported this punch as a check-out, but the day had no attendance record; recorded as the day\'s check-in.';

    /**
     * Process punch in/out for a user
     */
    public function processPunch($user, Request $request): array
    {
        try {
            // Anti-falsification: a field method that structurally requires a capture
            // photo (geo-polygon / route-waypoint) must not accept a photo-less punch.
            // Covers BOTH the web and mobile punch paths (both delegate here).
            if ($photoError = $this->guardRequiredPhoto($user, $request)) {
                return $photoError;
            }

            $punchTime = $this->resolvePunchTime($request);
            $punchDate = $this->resolveBusinessDate($user->id, $punchTime);

            // Honour explicit check_type sent by biometric devices (ZKTeco: in/out/break_*).
            // Absent check_type falls back to the original toggle behaviour (for manual punches).
            $checkType = $request->input('check_type');

            $existingAttendance = $this->getExistingAttendance($user->id, $punchDate);

            $isOutPunch = in_array($checkType, ['out', 'break_out', 'ot_out']);
            $isInPunch = in_array($checkType, ['in',  'break_in',  'ot_in']);

            if ($isOutPunch) {
                $openRow = $this->findOpenAttendanceToClose($user->id, $punchTime);
                if ($openRow) {
                    return $this->punchOut($openRow, $request, $user, $punchTime);
                }

                // Nothing to close. Before rejecting, ask whether this is the
                // OUT-first case (see RECOVERY_OUT_FIRST). If it is, fall through
                // into the transaction, which re-asks the same two questions under
                // lockForUpdate and creates the row — a promotion is a punch-IN and
                // must be raced-protected exactly like every other punch-in. This
                // read is only a cheap pre-check; the locked one decides.
                if (! $this->isRecoverableOrphanedOutPunch($request, $existingAttendance)) {
                    return [
                        'status' => 'error',
                        'message' => self::NO_OPEN_RECORD_MESSAGE,
                        'code' => 422,
                    ];
                }

                return DB::transaction(function () use ($user, $request) {
                    return $this->processPunchInTransaction($user, $request);
                }, 5);
            }

            if ($isInPunch && $existingAttendance && ! $existingAttendance->punchout) {
                return [
                    'status' => 'error',
                    'message' => self::ALREADY_PUNCHED_IN_MESSAGE,
                    'code' => 422,
                ];
            }

            // No explicit check_type (manual toggle) or explicit 'in': decide by existing record.
            if (! $isInPunch && $existingAttendance && ! $existingAttendance->punchout) {
                $lastEvent = $existingAttendance->punchout ?? $existingAttendance->punchin;
                if ($lastEvent && Carbon::parse($lastEvent)->diffInSeconds($punchTime) < self::DEDUPE_WINDOW_SECONDS) {
                    return [
                        'status' => 'error',
                        'message' => 'Duplicate punch ignored. Please wait a moment and try again.',
                        'code' => 429,
                    ];
                }

                $openRow = $this->findOpenAttendanceToClose($user->id, $punchTime) ?? $existingAttendance;

                return $this->punchOut($openRow, $request, $user, $punchTime);
            }

            // Run punch-in logic inside a transaction to avoid races
            return DB::transaction(function () use ($user, $request) {
                return $this->processPunchInTransaction($user, $request);
            }, 5);

        } catch (\Exception $e) {
            Log::error('Attendance punch error: '.$e->getMessage(), [
                'user_id' => $user->id,
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'status' => 'error',
                'message' => 'Failed to record attendance. Please try again.',
                'code' => 500,
            ];
        }
    }

    /**
     * Get existing attendance for user and date
     */
    private function getExistingAttendance(int $userId, Carbon $date): ?Attendance
    {
        return Attendance::where('user_id', $userId)
            ->whereDate('date', $date)
            ->latest()
            ->first();
    }

    /**
     * Resolve the correct BUSINESS date (attendances.date) for a punch-in moment.
     *
     * A shift that crosses midnight is rostered on day D but its window runs into
     * D+1. Without this, a punch-in captured after midnight (e.g. a late-arriving
     * night-shift officer at 00:15) is floored to the CALENDAR date of the punch
     * (D+1) instead of the ROSTERED date (D) — leaving the officer wrongly marked
     * ABSENT on D and creating a phantom unscheduled row on D+1.
     *
     * Rule: if YESTERDAY's resolved schedule (relative to the punch) is a working
     * day, crosses midnight, and the punch falls inside that shift's window
     * (start - REBIND_TOLERANCE_MINUTES through end) — and there is no already
     * COMPLETED attendance row for yesterday covering it — bind the punch to
     * yesterday's date instead of today's. An OPEN prior-day row is intentionally
     * still eligible for rebind so the "already punched in" collision check
     * (which reads the resolved date) correctly finds it, rather than creating a
     * second row on the wrong day.
     *
     * Ties are broken in favour of TODAY's own schedule when the punch is also
     * plausibly an early arrival for today's shift and today's start is closer.
     *
     * Day-shift users are entirely unaffected: yesterday never crosses midnight,
     * so the very first check returns today's calendar date unchanged.
     */
    private function resolveBusinessDate(int $userId, Carbon $punchTime): Carbon
    {
        $today = $punchTime->copy()->startOfDay();
        $prevDay = $today->copy()->subDay();

        $resolver = app(ScheduleResolver::class);
        $prevSchedule = $resolver->resolve($userId, $prevDay);

        if (! $prevSchedule->isWorkingDay || ! $prevSchedule->crossesMidnight) {
            return $today;
        }

        $prevWindowStart = $prevSchedule->start->copy()->subMinutes(self::REBIND_TOLERANCE_MINUTES);
        $prevWindowEnd = $prevSchedule->end->copy();

        if ($punchTime->lessThan($prevWindowStart) || $punchTime->greaterThan($prevWindowEnd)) {
            return $today;
        }

        // A COMPLETED row already covers yesterday's shift — this punch is a
        // distinct, unscheduled event and must not be folded into that closed day.
        $prevRow = $this->getExistingAttendance($userId, $prevDay);
        if ($prevRow && $prevRow->punchout !== null) {
            return $today;
        }

        // Tie-break: today may ALSO have a legitimate claim (e.g. an early
        // arrival for today's own shift). Prefer whichever start is closer.
        $todaySchedule = $resolver->resolve($userId, $today);
        if ($todaySchedule->isWorkingDay) {
            $todayWindowStart = $todaySchedule->start->copy()->subMinutes(self::REBIND_TOLERANCE_MINUTES);
            $todayWindowEnd = $todaySchedule->end->copy();

            $nearToday = $punchTime->greaterThanOrEqualTo($todayWindowStart)
                && $punchTime->lessThanOrEqualTo($todayWindowEnd);

            if ($nearToday) {
                $distPrev = abs($punchTime->diffInSeconds($prevSchedule->start));
                $distToday = abs($punchTime->diffInSeconds($todaySchedule->start));

                if ($distToday < $distPrev) {
                    return $today;
                }
            }
        }

        return $prevDay;
    }

    /**
     * Resolve the authoritative punch moment.
     *
     * Trusted device/biometric sources carry the REAL punch time in `punch_time`
     * (a device may push or be back-downloaded hours later) — honour it so worked
     * minutes / late / OT / overnight detection compute on the true moment, not the
     * server's processing time. Manual/web punches always use server time, so a user
     * cannot back-date their own punch.
     *
     * **Device time remains the basis; it is now a CORRECTED device time.** A
     * biometric caller is expected to have applied that device's measured clock
     * offset before it gets here (BiometricProcessingService does this on both
     * the live-push and the downloaded-import path, from the raw timestamp the
     * device sent, which is preserved on `biometric_att_logs.punch_time`). This
     * service deliberately does NOT apply the offset itself: correction has to
     * happen exactly once, and a second application here — over a value the
     * ingest path had already adjusted — is the double-correction failure this
     * whole mechanism is designed against. Nothing in this method knows about
     * device clocks, and that is the point.
     */
    private function resolvePunchTime(Request $request): Carbon
    {
        // Bounded offline-capture channel (sync push only). The `sync_capture`
        // attribute is set server-side by DataSyncService and can never come from
        // client input, so a human punch request (which is additionally scrubbed by
        // GuardsServerAuthoritativePunchTime) can never reach this branch. The
        // capture time here has already been bounded/rejected upstream; the same
        // window is re-checked defensively before it is trusted.
        if ($request->attributes->get('sync_capture') === true) {
            $captured = $this->boundedSyncCaptureTime($request->input('captured_at'));

            if ($captured !== null) {
                return $captured;
            }

            return Carbon::now();
        }

        $raw = $request->input('punch_time');
        $source = $request->input('source');

        if ($raw && in_array($source, ['biometric', 'device'], true)) {
            try {
                $parsed = Carbon::parse($raw);
                $now = Carbon::now();
                // Reject future-dated / over-drifted device clocks: a real punch is never in the
                // future, and a clock running fast beyond tolerance is drift, not a real moment.
                if ($parsed->greaterThan($now->copy()->addHours(self::MAX_CLOCK_DRIFT_HOURS))) {
                    Log::warning('Rejected future/over-drifted device punch_time; using server time', [
                        'punch_time' => $raw,
                        'now' => $now->toDateTimeString(),
                    ]);

                    return $now;
                }

                return $parsed;
            } catch (\Throwable $e) {
                // Unparseable device timestamp — fall back to server time rather than fail capture.
            }
        }

        return Carbon::now();
    }

    /**
     * Bound a client-asserted offline capture time for the sync channel.
     * Returns null when unparseable, in the future beyond a small skew, or older
     * than the offline window — callers then fall back to server time.
     */
    private function boundedSyncCaptureTime($raw): ?Carbon
    {
        if (! is_string($raw) || trim($raw) === '') {
            return null;
        }

        try {
            $parsed = Carbon::parse($raw);
        } catch (\Throwable $e) {
            return null;
        }

        $now = Carbon::now();

        if ($parsed->greaterThan($now->copy()->addMinutes(self::SYNC_CAPTURE_FUTURE_SKEW_MINUTES))) {
            return null;
        }

        if ($parsed->lessThan($now->copy()->subHours(self::SYNC_CAPTURE_MAX_AGE_HOURS))) {
            return null;
        }

        return $parsed;
    }

    /**
     * Resolve the open attendance row that an OUT punch should close.
     *
     * Returns today's open row (existing behavior) OR, if none, the previous
     * day's open row when (a) the resolved shift for the open row's punch-in
     * crosses midnight AND (b) the out-punch is within MAX_OVERNIGHT_HOURS of
     * that punch-in. This only changes WHICH open row an out-punch closes —
     * it never blocks capture.
     */
    private function findOpenAttendanceToClose(int $userId, CarbonInterface $punchMoment, bool $lock = false): ?Attendance
    {
        // 1) Today's open row — existing behavior.
        $todayQuery = Attendance::where('user_id', $userId)
            ->whereDate('date', $punchMoment->copy()->startOfDay())
            ->whereNull('punchout');
        if ($lock) {
            $todayQuery->lockForUpdate();
        }
        $today = $todayQuery->latest()->first();
        if ($today) {
            return $today;
        }

        // 2) Overnight: prior-day open row whose shift crosses midnight, within the bounded window.
        $priorQuery = Attendance::where('user_id', $userId)
            ->whereDate('date', $punchMoment->copy()->subDay()->startOfDay())
            ->whereNull('punchout');
        if ($lock) {
            $priorQuery->lockForUpdate();
        }
        $prior = $priorQuery->latest()->first();
        if (! $prior || ! $prior->punchin) {
            return null;
        }
        $in = Carbon::parse($prior->punchin);
        if ($in->diffInHours($punchMoment) > self::MAX_OVERNIGHT_HOURS) {
            return null;
        }
        // Resolve against the row's BUSINESS date (attendances.date), not the raw
        // punch-in timestamp: after cross-midnight rebinding the two can differ
        // (a late arrival keeps its real capture time but is dated to the shift's
        // rostered day), and the business date is what the roster is keyed on.
        $shift = app(ScheduleResolver::class)->resolve($userId, $prior->date);

        return $shift->crossesMidnight ? $prior : null;
    }

    /**
     * Process punch out
     */
    private function punchOut(Attendance $attendance, Request $request, $user, Carbon $punchTime): array
    {
        if ($attendance->punchin && $punchTime->lessThanOrEqualTo(Carbon::parse($attendance->punchin))) {
            return [
                'status' => 'error',
                'message' => 'Punch-out cannot be before punch-in.',
                'code' => 422,
            ];
        }

        $attendance->update([
            'punchout' => $punchTime,
            'punchout_location' => $this->formatLocation($request),
        ]);

        $this->stampOfflineFlag($attendance, $request);

        // Handle photo upload for polygon/route types
        $this->handlePhotoUpload($attendance, $request, 'punchout_photo', $user);

        // Domain bus (additive, after-commit). When this runs inside the punch
        // transaction the event is held until commit and dropped on rollback.
        AttendancePunched::dispatch(
            $user->id ?? null,
            $attendance->id,
            AttendancePunched::ACTION_OUT,
            $this->businessDateKey($attendance, $punchTime),
        );

        return [
            'status' => 'success',
            'message' => 'Successfully punched out!',
            'action' => 'punch_out',
            'attendance_id' => $attendance->id,
        ];
    }

    /**
     * Process punch in
     */
    private function punchIn($user, Carbon $date, Request $request, Carbon $punchTime): array
    {
        $attendance = Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'punchin' => $punchTime,
            'punchin_location' => $this->formatLocation($request),
        ]);

        $this->stampOfflineFlag($attendance, $request);

        // Handle photo upload for polygon/route types
        $this->handlePhotoUpload($attendance, $request, 'punchin_photo', $user);

        try {
            $assessment = app(PunchPolicyGuard::class)->assess($user->id, $punchTime);
            $attendance->forceFill([
                'policy_status' => $assessment['policy_status'],
                'needs_approval' => $assessment['needs_approval'],
                'policy_exception_reason' => $assessment['reason'],
            ])->save();
            $warning = $assessment['warning'] ?? null;
        } catch (\Throwable $e) {
            try {
                Log::error('Punch policy assessment failed: '.$e->getMessage(), ['user_id' => $user->id, 'attendance_id' => $attendance->id]);
            } catch (\Throwable) {
                // Swallow logging failures too: a log driver/disk failure must never
                // propagate into the surrounding DB::transaction and roll back the
                // just-created Attendance row. Capture is never blocked.
            }
            $warning = null; // capture is never blocked: degrade to accepted defaults
        }

        $result = [
            'status' => 'success',
            'message' => 'Successfully punched in!',
            'action' => 'punch_in',
            'attendance_id' => $attendance->id,
        ];

        if ($warning !== null) {
            $result['warning'] = $warning;
        }

        // Domain bus (additive, after-commit). punchIn always runs inside
        // processPunchInTransaction, so a rolled-back capture emits nothing.
        AttendancePunched::dispatch(
            $user->id ?? null,
            $attendance->id,
            AttendancePunched::ACTION_IN,
            $this->businessDateKey($attendance, $punchTime),
        );

        return $result;
    }

    /**
     * Realtime bucket key for an attendance row: the BUSINESS date the row is
     * filed under (which for an overnight shift is the prior day), falling back
     * to the punch moment only when the row somehow carries no date.
     */
    private function businessDateKey(Attendance $attendance, Carbon $punchTime): string
    {
        $date = $attendance->date;

        if ($date instanceof CarbonInterface) {
            return $date->format('Y-m-d');
        }

        if (is_string($date) && $date !== '') {
            try {
                return Carbon::parse($date)->format('Y-m-d');
            } catch (\Throwable) {
                // fall through to the punch moment
            }
        }

        return $punchTime->format('Y-m-d');
    }

    /**
     * Reject a photo-less punch when the user's resolved attendance methods ALL
     * require a verification photo (geo-polygon / route-waypoint).
     *
     * Conservative by design: if the user holds ANY non-photo method (wifi/IP,
     * QR, biometric), the punch is allowed through with no photo — they may have
     * used that method. Photo is therefore never blanket-required, and the whole
     * check is behind a config kill-switch for staged rollout.
     */
    private function guardRequiredPhoto($user, Request $request): ?array
    {
        if (! config('attendance.require_photo_for_field_methods', true)) {
            return null;
        }

        $types = method_exists($user, 'resolvedAttendanceTypes')
            ? $user->resolvedAttendanceTypes()
            : collect();

        if ($types->isEmpty()) {
            return null;
        }

        $allRequirePhoto = $types->every(fn ($type) => $type && $this->typeRequiresPhoto($type));
        if (! $allRequirePhoto) {
            return null;
        }

        $photo = $request->input('photo');
        if (is_string($photo) && trim($photo) !== '') {
            return null;
        }

        try {
            Log::warning('Attendance punch rejected: verification photo required but absent', [
                'user_id' => $user->id,
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable) {
            // A logging/disk failure must never turn a rejected punch into a 500.
        }

        return [
            'status' => 'error',
            'message' => 'A verification photo is required for this attendance method. Please capture a photo and try again.',
            'code' => 422,
        ];
    }

    /**
     * Whether an attendance type structurally requires a capture photo.
     * Mirrors the geo_polygon / route_waypoint taxonomy used by handlePhotoUpload()
     * and the team-locations `requires_photo` flag.
     */
    private function typeRequiresPhoto($type): bool
    {
        $baseSlug = preg_replace('/_\d+$/', '', (string) ($type->slug ?? ''));

        return in_array($baseSlug, ['geo_polygon', 'route_waypoint'], true);
    }

    /**
     * Handle photo upload using Media Library
     */
    private function handlePhotoUpload(Attendance $attendance, Request $request, string $collection, $user): void
    {
        $photoData = $request->input('photo');

        if (! $photoData) {
            return;
        }

        try {
            $attendanceType = $user->attendanceType;
            if (! $attendanceType) {
                return;
            }

            $baseSlug = preg_replace('/_\d+$/', '', $attendanceType->slug);
            if (! in_array($baseSlug, ['geo_polygon', 'route_waypoint'])) {
                return;
            }

            if (! preg_match('/^data:image\/(\w+);base64,/', $photoData, $matches)) {
                return;
            }

            $extension = $matches[1];
            $photoDataPart = substr($photoData, strpos($photoData, ',') + 1);
            $bytes = base64_decode($photoDataPart);

            if ($bytes === false) {
                Log::warning('Failed to decode base64 photo data');

                return;
            }

            $filename = 'attendance_'.$attendance->id.'_'.$collection.'_'.time().'.'.$extension;
            $tempDir = storage_path('app/temp');
            if (! file_exists($tempDir)) {
                mkdir($tempDir, 0755, true);
            }

            $tempPath = $tempDir.DIRECTORY_SEPARATOR.$filename;
            file_put_contents($tempPath, $bytes);

            if (method_exists($attendance, 'addMedia')) {
                $attendance->addMedia($tempPath)
                    ->usingFileName($filename)
                    ->toMediaCollection($collection);
            }

            @unlink($tempPath);
        } catch (\Exception $e) {
            Log::error('Photo upload failed: '.$e->getMessage(), [
                'attendance_id' => $attendance->id,
                'collection' => $collection,
            ]);
        }
    }

    private function processPunchInTransaction($user, Request $request): array
    {
        $punchTime = $this->resolvePunchTime($request);
        $punchDate = $this->resolveBusinessDate($user->id, $punchTime);

        // Honour explicit check_type sent by biometric devices (ZKTeco: in/out/break_*).
        // Absent check_type falls back to the original toggle behaviour (for manual punches).
        $checkType = $request->input('check_type');

        // Fetch existing attendance row with FOR UPDATE to prevent concurrent modifications
        $existingAttendance = Attendance::where('user_id', $user->id)
            ->whereDate('date', $punchDate)
            ->lockForUpdate()
            ->latest()
            ->first();

        $isOutPunch = in_array($checkType, ['out', 'break_out', 'ot_out']);
        $isInPunch = in_array($checkType, ['in',  'break_in',  'ot_in']);

        if ($isOutPunch) {
            $openRow = $this->findOpenAttendanceToClose($user->id, $punchTime, lock: true);
            if ($openRow) {
                return $this->punchOut($openRow, $request, $user, $punchTime);
            }

            // The authoritative decision, taken with $existingAttendance read
            // FOR UPDATE. Two concurrent OUT-first punches therefore cannot both
            // see an empty day and both create a row: the loser blocks here, and
            // by the time it proceeds it sees the winner's row and is rejected —
            // or, if the winner's row is still open, the branch above closes it.
            if ($this->isRecoverableOrphanedOutPunch($request, $existingAttendance)) {
                return $this->recoverOrphanedOutPunch($user, $punchDate, $request, $punchTime);
            }

            return [
                'status' => 'error',
                'message' => self::NO_OPEN_RECORD_MESSAGE,
                'code' => 422,
            ];
        }

        if ($isInPunch && $existingAttendance && ! $existingAttendance->punchout) {
            return [
                'status' => 'error',
                'message' => self::ALREADY_PUNCHED_IN_MESSAGE,
                'code' => 422,
            ];
        }

        // No explicit check_type (manual toggle) or explicit 'in': decide by existing record.
        if (! $isInPunch && $existingAttendance && ! $existingAttendance->punchout) {
            if (! $checkType) {
                $lastEvent = $existingAttendance->punchout ?? $existingAttendance->punchin;
                if ($lastEvent && Carbon::parse($lastEvent)->diffInSeconds($punchTime) < self::DEDUPE_WINDOW_SECONDS) {
                    return [
                        'status' => 'error',
                        'message' => 'Duplicate punch ignored. Please wait a moment and try again.',
                        'code' => 429,
                    ];
                }
            }

            $openRow = $this->findOpenAttendanceToClose($user->id, $punchTime, lock: true) ?? $existingAttendance;

            return $this->punchOut($openRow, $request, $user, $punchTime);
        }

        // ── Night-shift overnight close for manual (no check_type) punches ──
        // When there is no open row TODAY and no explicit check_type, check
        // whether a prior-day row should be closed (overnight shift).  The
        // findOpenAttendanceToClose method already safely gates this: it only
        // returns a prior-day row when the shift crosses_midnight AND the
        // punch is within MAX_OVERNIGHT_HOURS of the punch-in, so day-shift
        // workers are never wrongly paired.
        if (! $checkType) {
            $overnightRow = $this->findOpenAttendanceToClose($user->id, $punchTime, lock: true);
            if ($overnightRow) {
                return $this->punchOut($overnightRow, $request, $user, $punchTime);
            }
        }

        return $this->punchIn($user, $punchDate, $request, $punchTime);
    }

    /**
     * Whether an out punch with nothing to close is the OUT-first case, and may
     * therefore be recorded as the day's check-in.
     *
     * Every clause is a REFUSAL, and the two that carry the weight are the last
     * one and the caller's own findOpenAttendanceToClose() — see the
     * RECOVERY_OUT_FIRST block for the full argument. This method is only ever
     * reached from the branch that would otherwise return NO_OPEN_RECORD_MESSAGE,
     * so it cannot affect a punch-out that has a punch-in to close: that punch
     * returned from punchOut() before this was called.
     *
     * @param  Attendance|null  $existingAttendance  ANY row on the resolved
     *                                               business date, open or closed
     */
    private function isRecoverableOrphanedOutPunch(Request $request, ?Attendance $existingAttendance): bool
    {
        // A day that already has a row is not a lost day. This is what keeps a
        // stray third out punch (after a complete in→out day) rejected exactly as
        // it is today, instead of opening a phantom second attendance record.
        if ($existingAttendance !== null) {
            return false;
        }

        // Exactly a plain check-out. `break_out` / `ot_out` describe an
        // interruption to a day already under way; promoting one would invent a
        // workday rather than recover one.
        if ($request->input('check_type') !== 'out') {
            return false;
        }

        // The offline sync channel replays a human's queued punch through a
        // client-shaped payload. Checked BEFORE the source test, because that
        // payload could itself carry `source`.
        if ($request->attributes->get('sync_capture') === true) {
            return false;
        }

        // Device-sourced only — the same trust boundary resolvePunchTime() uses,
        // and one GuardsServerAuthoritativePunchTime strips from every
        // human-facing punch request. Web / GPS / QR / manual punches send no
        // `source` at all and are structurally unable to reach this rule.
        return in_array($request->input('source'), ['biometric', 'device'], true);
    }

    /**
     * Record an orphaned device check-out as the day's check-in.
     *
     * Deliberately just punchIn() plus an audit trail: a recovered punch-in must
     * be indistinguishable from an ordinary one in `attendances` — same policy
     * assessment, same domain event, same business date — because everything
     * downstream (worked minutes, late/OT, the monthly grid) reads those rows and
     * must not need to know this happened. What makes it distinguishable is the
     * audit row, not a special-cased attendance row.
     */
    private function recoverOrphanedOutPunch($user, Carbon $date, Request $request, Carbon $punchTime): array
    {
        $result = $this->punchIn($user, $date, $request, $punchTime);

        if (($result['status'] ?? null) !== 'success') {
            return $result;
        }

        // Consumed by BiometricProcessingService to stamp RECOVERY_REASON onto
        // the ATTLOG row, so the recovery is visible from the device side too.
        $result['recovery'] = self::RECOVERY_OUT_FIRST;
        $result['recovery_reason'] = self::RECOVERY_REASON;

        $this->recordOrphanedOutRecovery($user, $result['attendance_id'] ?? null, $date, $punchTime, $request);

        try {
            Log::info('Attendance: orphaned device check-out recorded as the day\'s check-in', [
                'user_id' => $user->id ?? null,
                'attendance_id' => $result['attendance_id'] ?? null,
                'date' => $date->format('Y-m-d'),
                'punch_time' => $punchTime->format('Y-m-d H:i:s'),
                'device_check_type' => 'out',
                'device_serial' => $request->input('device_serial'),
                'device_user_id' => $request->input('device_user_id'),
            ]);
        } catch (\Throwable) {
            // Capture is never blocked by a log driver failure.
        }

        return $result;
    }

    /**
     * Write the audit row that separates a recovered day from a normal one.
     *
     * `actor_id` is NULL on purpose: no human decided this, the ingest rules did,
     * and attributing it to a person would be a lie in the one table that exists
     * to say who did what. `before` is NULL because nothing was overwritten — the
     * day had no attendance at all, which is the whole point.
     *
     * A failure here must never roll the punch back. The attendance record is the
     * thing the business needs; the audit row is how we explain it. Losing the
     * explanation is bad, losing the day again is worse — and the Log line in the
     * caller is a second, independent record of the same event.
     */
    private function recordOrphanedOutRecovery($user, ?int $attendanceId, Carbon $date, Carbon $punchTime, Request $request): void
    {
        try {
            AttendanceAuditLog::create([
                'actor_id' => null,
                'attendance_id' => $attendanceId,
                'action' => self::RECOVERY_AUDIT_ACTION,
                'before' => null,
                'after' => [
                    'user_id' => $user->id ?? null,
                    'date' => $date->format('Y-m-d'),
                    'punchin' => $punchTime->format('Y-m-d H:i:s'),
                    // What the terminal actually reported, kept verbatim beside
                    // what we did with it.
                    'device_check_type' => 'out',
                    'recorded_as' => 'in',
                    'source' => (string) $request->input('source'),
                    'device_serial' => $request->input('device_serial'),
                    'device_user_id' => $request->input('device_user_id'),
                ],
                'reason' => self::RECOVERY_REASON,
            ]);
        } catch (\Throwable $e) {
            try {
                Log::error('Failed to write orphaned-check-out recovery audit row: '.$e->getMessage(), [
                    'user_id' => $user->id ?? null,
                    'attendance_id' => $attendanceId,
                ]);
            } catch (\Throwable) {
                // Logging the logging failure is where this stops.
            }
        }
    }

    /**
     * Flag an attendance row that was captured offline and replayed through the
     * bounded sync channel. Keeps the audit trail honest: the punch is attributed
     * to its real capture moment but marked as device-asserted. forceFill is used
     * because `was_offline` is intentionally not mass-assignable.
     */
    private function stampOfflineFlag(Attendance $attendance, Request $request): void
    {
        if ($request->attributes->get('sync_capture') === true) {
            $attendance->forceFill(['was_offline' => true])->save();
        }
    }

    /**
     * Format location data from request
     */
    private function formatLocation(Request $request): ?string
    {
        $lat = $request->input('lat');
        $lng = $request->input('lng');
        $qrCode = $request->input('qr_code');

        if (! $lat && ! $lng && ! $qrCode) {
            return null;
        }

        $locationData = [
            'lat' => $lat,
            'lng' => $lng,
            'address' => $request->input('address', ''),
            'timestamp' => now()->toISOString(),
        ];

        if ($qrCode) {
            $locationData['qr_code'] = $qrCode;
        }

        return json_encode($locationData);
    }
}
