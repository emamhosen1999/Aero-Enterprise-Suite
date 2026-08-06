<?php

namespace App\Services\Biometric;

use App\Models\HRM\BiometricDevice;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Measure a biometric terminal's clock error, and correct punches by it.
 *
 * ── Why this exists ─────────────────────────────────────────────────────────
 *
 * The production MB460 (`AF6P231260266`) has stamped every punch two hours in
 * the future since installation. 827 live pushes across four months put the
 * median at exactly -2.00 h (device time minus server receive time = +7196 s),
 * stable to the second — a timezone the firmware recomputes from, not drift.
 * Fixing the clock at the wall does not hold: it returns to +2h. So an 09:00
 * arrival was recorded as 11:00 and read as two hours late, and a 17:00
 * departure read as two hours of overtime.
 *
 * The only durable fix is for the ERP to hold the authority: measure what the
 * device's clock is doing, store it, and correct at ingest.
 *
 * ── Sign convention (one convention, used everywhere) ───────────────────────
 *
 *   offset_seconds  = device_time - server_receive_time
 *                     POSITIVE means the device reads LATER than we do.
 *                     The MB460 measures +7196.
 *   applied_seconds = the signed correction ADDED to a raw device timestamp.
 *                     It is always the negation of the trusted offset, so the
 *                     MB460's punches are shifted by -7196.
 *
 * ── How it is measured ──────────────────────────────────────────────────────
 *
 * From LIVE pushes only. A live push is the device telling us about a punch as
 * it happens, so the gap between the timestamp it sends and the moment the
 * request lands is the clock error plus a second or two of network. A
 * downloaded/batched log is the opposite: the device is replaying history on
 * request, and the same subtraction would read as an enormous negative offset
 * that has nothing to do with its clock. Batch rows are therefore never
 * sampled — the capture path already knows which is which (an open download
 * session, `punch_status = 'downloaded'`), and this service is only ever called
 * from the live branch.
 *
 * Three further defences against a poisoned estimate:
 *
 *  - **Median, never mean.** A device that was offline and is catching up pushes
 *    genuinely old punches through the live path. One such burst would drag a
 *    mean by hours; it moves a median by nothing until it is the majority of the
 *    window.
 *  - **One sample per push.** A catch-up burst arrives as many lines in one
 *    body. Sampling every line would let a single burst fill the whole window,
 *    so a push contributes exactly one sample, taken from its NEWEST punch —
 *    the line most likely to be "just now".
 *  - **A plausibility bound.** Anything beyond MAX_PLAUSIBLE_OFFSET_SECONDS is
 *    a batch artefact rather than a clock, and is discarded before it is stored.
 *
 * ── Never double-correct: the failure mode this is designed against ─────────
 *
 * If someone does fix the device clock, a statically-applied offset would start
 * corrupting data in the opposite direction — every punch pushed two hours into
 * the PAST — and nothing would say so. Four properties prevent that, and the
 * first is the important one:
 *
 *  1. **Every measurement is taken from raw device time.** `biometric_att_logs.
 *     punch_time` keeps the untouched device timestamp; the corrected value goes
 *     in its own column. So the estimator never observes its own output, and
 *     there is no feedback loop that could hold a stale offset in place.
 *  2. **The estimate is a rolling window of the newest SAMPLE_WINDOW samples.**
 *     A fixed clock starts producing ~0 samples immediately, and once they are
 *     the majority the median moves to ~0 — a step, not a smear, which is a
 *     second reason to prefer the median: it does not spend days applying a
 *     half-wrong intermediate correction the way an average would.
 *  3. **Correction is gated on |offset| >= MIN_APPLY_SECONDS.** Once the median
 *     collapses to ~0, correction stops entirely rather than shifting punches by
 *     a few noise seconds.
 *  4. **Correction is applied at exactly one place per ingest path**, from the
 *     raw value, and the applied amount is recorded on the row. A replay of the
 *     same row re-corrects the raw value rather than compounding.
 */
class DeviceClockService
{
    public const SAMPLES_TABLE = 'biometric_device_clock_samples';

    /**
     * Samples the median is taken over.
     *
     * Sized for convergence, not for statistical luxury. The live MB460 pushes
     * roughly 7 punches a day, so 25 samples is about three days of evidence and
     * a corrected clock flips the median within ~two days. A much larger window
     * would be a marginally steadier estimate that kept applying a known-wrong
     * correction for weeks after the device was fixed.
     */
    public const SAMPLE_WINDOW = 25;

    /**
     * Samples required before an offset is trusted enough to act on.
     *
     * Below this the offset is still measured and still shown — an admin can see
     * "we think this device is 2h fast, from 3 samples" — but nothing is
     * corrected. One or two samples cannot distinguish a skewed clock from a
     * device that pushed two old punches.
     */
    public const MIN_TRUSTED_SAMPLES = 5;

    /**
     * Smallest offset worth correcting, in seconds.
     *
     * Below a minute the "offset" is transport latency and second-boundary
     * rounding, not accuracy: correcting a punch by 3 seconds changes nothing
     * anybody can act on while making every stored row look adjusted. A device
     * a full minute or more out is a real problem worth fixing in the data.
     */
    public const MIN_APPLY_SECONDS = 60;

    /**
     * Beyond this, the sample is a batch artefact, not a clock error (2 days).
     *
     * A live push from a device that has been offline can legitimately carry a
     * punch from yesterday; two days is comfortably past anything a timezone or
     * a wrong-by-a-day clock produces, and well short of the months a history
     * replay spans.
     */
    public const MAX_PLAUSIBLE_OFFSET_SECONDS = 172800;

    /**
     * How long a measurement stays actionable without fresh evidence.
     *
     * The estimate is only recomputed when a device pushes, so a unit that has
     * gone quiet would otherwise keep its last offset applied forever — to
     * downloaded logs in particular, which are imported long after the fact. A
     * measurement nobody has re-confirmed in a month stops being applied and the
     * device falls back to its own timestamps, which is the honest default.
     */
    public const MAX_OFFSET_AGE_DAYS = 30;

    /**
     * Rows kept per device before the oldest are pruned.
     *
     * Deliberately larger than SAMPLE_WINDOW: the extra rows are not used by the
     * estimator, they are the audit trail behind it — enough history to show an
     * admin that an offset has been stable for weeks, or that it just changed.
     */
    public const MAX_STORED_SAMPLES = 200;

    // Reasons an offset is or is not being applied. Surfaced in snapshot() so a
    // UI can say WHY, which is the difference between an empty screen and an
    // explanation.
    public const REASON_APPLIED = 'applied';

    public const REASON_NOT_MEASURED = 'not_measured';

    public const REASON_INSUFFICIENT_SAMPLES = 'insufficient_samples';

    public const REASON_BELOW_THRESHOLD = 'below_threshold';

    public const REASON_STALE = 'stale';

    // ──────────────────────────────────────────────────────────────
    //  Measurement
    // ──────────────────────────────────────────────────────────────

    /**
     * Record one live-push observation and refresh the device's estimate.
     *
     * Callers must pass the device's OWN timestamp for the punch, untouched, and
     * only from the live push path. `$receivedAt` defaults to now(), which is the
     * moment the request is being handled.
     *
     * @param  string|CarbonInterface  $deviceTime  the punch time as the device stated it
     * @return int|null the offset stored, or null when the sample was rejected
     */
    public function recordLiveSample(BiometricDevice $device, $deviceTime, ?CarbonInterface $receivedAt = null): ?int
    {
        $moment = $this->parse($deviceTime);

        if ($moment === null) {
            return null;
        }

        $received = $receivedAt ? Carbon::instance($receivedAt->toDateTime()) : Carbon::now();
        $offset = $moment->getTimestamp() - $received->getTimestamp();

        if (abs($offset) > self::MAX_PLAUSIBLE_OFFSET_SECONDS) {
            // Almost certainly a history replay that reached the live branch:
            // real clock error, even a wrong timezone or a wrong day, is far
            // smaller than this. Logged rather than silently dropped, because a
            // device that produces these constantly is itself a finding.
            Log::info('Device clock sample discarded as implausible', [
                'device_id' => $device->id,
                'serial' => $device->serial_number,
                'offset_seconds' => $offset,
                'device_time' => $moment->toDateTimeString(),
                'received_at' => $received->toDateTimeString(),
            ]);

            return null;
        }

        DB::table(self::SAMPLES_TABLE)->insert([
            'biometric_device_id' => $device->id,
            'offset_seconds' => $offset,
            'device_time' => $moment->toDateTimeString(),
            'received_at' => $received->toDateTimeString(),
            'created_at' => Carbon::now(),
        ]);

        $this->pruneSamples($device);
        $this->recomputeOffset($device);

        return $offset;
    }

    /**
     * Recompute the device's stored estimate from its newest samples.
     *
     * Returns the state it wrote, so a caller that has just recorded a sample
     * does not have to re-read the row.
     *
     * @return array{offset_seconds: int|null, samples: int, measured_at: Carbon|null}
     */
    public function recomputeOffset(BiometricDevice $device): array
    {
        $offsets = DB::table(self::SAMPLES_TABLE)
            ->where('biometric_device_id', $device->id)
            ->where('received_at', '>=', Carbon::now()->subDays(self::MAX_OFFSET_AGE_DAYS)->toDateTimeString())
            ->orderByDesc('id')
            ->limit(self::SAMPLE_WINDOW)
            ->pluck('offset_seconds')
            ->map(fn ($value) => (int) $value)
            ->all();

        if ($offsets === []) {
            // Nothing usable. The estimate is deliberately NOT cleared: an
            // existing measurement ages out through offsetState()'s staleness
            // rule, which is visible, rather than by silently becoming null.
            return [
                'offset_seconds' => $this->storedOffset($device),
                'samples' => (int) ($device->getAttribute('clock_offset_samples') ?? 0),
                'measured_at' => $device->getAttribute('clock_offset_measured_at'),
            ];
        }

        $median = $this->median($offsets);
        $measuredAt = Carbon::now();

        // forceFill rather than update(): these three columns are operational
        // measurements, never a form's business, and this must work regardless
        // of what the model's $fillable happens to say. It also refreshes the
        // in-memory instance the ingest path is holding, so the very next punch
        // in this same request sees the new estimate.
        $device->forceFill([
            'clock_offset_seconds' => $median,
            'clock_offset_samples' => count($offsets),
            'clock_offset_measured_at' => $measuredAt,
        ])->save();

        return [
            'offset_seconds' => $median,
            'samples' => count($offsets),
            'measured_at' => $measuredAt,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Application
    // ──────────────────────────────────────────────────────────────

    /**
     * Whether this device's measured offset is currently trusted, and why.
     *
     * @return array{
     *     measured: bool,
     *     offset_seconds: int|null,
     *     samples: int,
     *     measured_at: CarbonInterface|null,
     *     applied: bool,
     *     applied_seconds: int,
     *     reason: string
     * }
     */
    public function offsetState(BiometricDevice $device): array
    {
        $offset = $this->storedOffset($device);
        $samples = (int) ($device->getAttribute('clock_offset_samples') ?? 0);
        $measuredAt = $device->getAttribute('clock_offset_measured_at');
        $measuredAt = $measuredAt ? Carbon::parse($measuredAt) : null;

        $state = [
            'measured' => $offset !== null,
            'offset_seconds' => $offset,
            'samples' => $samples,
            'measured_at' => $measuredAt,
            'applied' => false,
            'applied_seconds' => 0,
            'reason' => self::REASON_NOT_MEASURED,
        ];

        // NULL is not zero. Null means nobody has measured this device, which is
        // the absence of evidence; a measured 0 is evidence that its clock
        // agrees with ours. Only the second is a reason to stop looking.
        if ($offset === null) {
            return $state;
        }

        if ($samples < self::MIN_TRUSTED_SAMPLES) {
            $state['reason'] = self::REASON_INSUFFICIENT_SAMPLES;

            return $state;
        }

        if ($measuredAt !== null && $measuredAt->lt(Carbon::now()->subDays(self::MAX_OFFSET_AGE_DAYS))) {
            $state['reason'] = self::REASON_STALE;

            return $state;
        }

        if (abs($offset) < self::MIN_APPLY_SECONDS) {
            // This is also the state a corrected device lands in: the median has
            // collapsed back toward zero and correction switches itself off.
            $state['reason'] = self::REASON_BELOW_THRESHOLD;

            return $state;
        }

        $state['applied'] = true;
        $state['applied_seconds'] = -$offset;
        $state['reason'] = self::REASON_APPLIED;

        return $state;
    }

    /**
     * The signed correction currently being added to this device's timestamps.
     * Zero whenever nothing is being corrected.
     */
    public function appliedOffsetSeconds(BiometricDevice $device): int
    {
        return $this->offsetState($device)['applied_seconds'];
    }

    /**
     * Correct one RAW device timestamp.
     *
     * The contract callers depend on: `$rawPunchTime` must be exactly what the
     * device sent, never a value this method has already returned. Correcting is
     * a pure function of (raw value, device's current estimate), so replaying the
     * same raw value later re-corrects it — it never compounds.
     *
     * When nothing is applied, `punch_time` comes back as the caller's own
     * string, byte for byte. A device with no trusted measurement therefore
     * behaves exactly as it did before this feature existed.
     *
     * @return array{punch_time: string, applied: bool, applied_seconds: int, raw_punch_time: string}
     */
    public function correct(BiometricDevice $device, string $rawPunchTime): array
    {
        $unchanged = [
            'punch_time' => $rawPunchTime,
            'applied' => false,
            'applied_seconds' => 0,
            'raw_punch_time' => $rawPunchTime,
        ];

        $state = $this->offsetState($device);

        if (! $state['applied']) {
            return $unchanged;
        }

        $moment = $this->parse($rawPunchTime);

        if ($moment === null) {
            // Unparseable device timestamps are already handled downstream by
            // AttendancePunchService (it falls back to server time). Correcting
            // is not the place to start rejecting them.
            return $unchanged;
        }

        return [
            'punch_time' => $moment->copy()->addSeconds($state['applied_seconds'])->format('Y-m-d H:i:s'),
            'applied' => true,
            'applied_seconds' => $state['applied_seconds'],
            'raw_punch_time' => $rawPunchTime,
        ];
    }

    // ──────────────────────────────────────────────────────────────
    //  Read model
    // ──────────────────────────────────────────────────────────────

    /**
     * Everything an admin screen needs to understand this device's clock.
     *
     * Silent correction is its own hazard: if punches are being moved by two
     * hours, that has to be visible next to the device, with the evidence count
     * and the age of the measurement, or the next person to debug an attendance
     * discrepancy has no way to know it is happening. A controller-owned endpoint
     * consumes this; this service does not know about routes.
     *
     * @return array<string, mixed>
     */
    public function snapshot(BiometricDevice $device): array
    {
        $state = $this->offsetState($device);
        $offset = $state['offset_seconds'];

        return [
            'device_id' => $device->id,
            'serial_number' => $device->serial_number,

            // What we measured.
            'measured' => $state['measured'],
            'offset_seconds' => $offset,
            'offset_human' => $offset === null ? null : $this->humanise($offset),
            // Plain-language direction, so a UI does not have to know the sign
            // convention to render a sentence.
            'device_is_ahead' => $offset === null ? null : $offset > 0,
            'sample_count' => $state['samples'],
            'measured_at' => $state['measured_at']?->toIso8601String(),

            // What we are doing about it.
            'is_applied' => $state['applied'],
            'applied_seconds' => $state['applied_seconds'],
            'applied_human' => $state['applied'] ? $this->humanise($state['applied_seconds']) : null,
            'reason' => $state['reason'],
            'reason_label' => $this->reasonLabel($state),

            // The thresholds the decision was made against, so the UI can
            // explain "3 of 5 samples" without hardcoding our constants.
            'min_samples' => self::MIN_TRUSTED_SAMPLES,
            'apply_threshold_seconds' => self::MIN_APPLY_SECONDS,
            'sample_window' => self::SAMPLE_WINDOW,
            'max_age_days' => self::MAX_OFFSET_AGE_DAYS,
        ];
    }

    /**
     * The newest raw samples behind the estimate, newest first.
     *
     * @return array<int, array{offset_seconds: int, device_time: string|null, received_at: string|null}>
     */
    public function recentSamples(BiometricDevice $device, int $limit = self::SAMPLE_WINDOW): array
    {
        return DB::table(self::SAMPLES_TABLE)
            ->where('biometric_device_id', $device->id)
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get()
            ->map(fn ($row) => [
                'offset_seconds' => (int) $row->offset_seconds,
                'device_time' => $row->device_time,
                'received_at' => $row->received_at,
            ])
            ->all();
    }

    // ──────────────────────────────────────────────────────────────
    //  Internals
    // ──────────────────────────────────────────────────────────────

    /**
     * The device's stored estimate, preserving the null/zero distinction that a
     * plain `(int)` cast would destroy.
     */
    private function storedOffset(BiometricDevice $device): ?int
    {
        $offset = $device->getAttribute('clock_offset_seconds');

        return $offset === null ? null : (int) $offset;
    }

    /**
     * Middle value of a sample set.
     *
     * An even-sized window averages the two middle samples, which for a constant
     * offset (the case that matters here) returns that constant exactly.
     *
     * @param  list<int>  $offsets
     */
    private function median(array $offsets): int
    {
        sort($offsets);

        $count = count($offsets);
        $middle = intdiv($count, 2);

        if ($count % 2 === 1) {
            return $offsets[$middle];
        }

        return (int) round(($offsets[$middle - 1] + $offsets[$middle]) / 2);
    }

    /**
     * Keep the sample table bounded per device.
     *
     * Finds the id of the MAX_STORED_SAMPLES-th newest row and deletes anything
     * older. Deliberately not `DELETE ... WHERE id NOT IN (SELECT ... LIMIT n)`:
     * MySQL rejects a LIMIT inside an IN subquery, so that form would work on
     * SQLite and fail in production — the exact split-behaviour trap this
     * feature is under instruction to avoid.
     */
    private function pruneSamples(BiometricDevice $device): void
    {
        $cutoff = DB::table(self::SAMPLES_TABLE)
            ->where('biometric_device_id', $device->id)
            ->orderByDesc('id')
            ->offset(self::MAX_STORED_SAMPLES)
            ->limit(1)
            ->value('id');

        if ($cutoff === null) {
            return;
        }

        DB::table(self::SAMPLES_TABLE)
            ->where('biometric_device_id', $device->id)
            ->where('id', '<=', $cutoff)
            ->delete();
    }

    /**
     * Parse a device timestamp without letting a malformed one throw into the
     * ingest path. A terminal that pushes garbage must not take the endpoint
     * down; it simply contributes no sample and gets no correction.
     */
    private function parse($value): ?Carbon
    {
        if ($value instanceof CarbonInterface) {
            return Carbon::instance($value->toDateTime());
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Signed, human-readable duration: `+2h 0m 4s`, `-1h 59m 56s`, `+8s`.
     */
    private function humanise(int $seconds): string
    {
        $sign = $seconds < 0 ? '-' : '+';
        $abs = abs($seconds);

        $hours = intdiv($abs, 3600);
        $minutes = intdiv($abs % 3600, 60);
        $rest = $abs % 60;

        $parts = [];

        if ($hours > 0) {
            $parts[] = $hours.'h';
        }

        if ($hours > 0 || $minutes > 0) {
            $parts[] = $minutes.'m';
        }

        $parts[] = $rest.'s';

        return $sign.implode(' ', $parts);
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private function reasonLabel(array $state): string
    {
        return match ($state['reason']) {
            self::REASON_APPLIED => 'Punches from this device are being corrected by '.$this->humanise($state['applied_seconds']).'.',
            self::REASON_INSUFFICIENT_SAMPLES => 'Measured from '.$state['samples'].' of '.self::MIN_TRUSTED_SAMPLES.' samples needed — not corrected yet.',
            self::REASON_BELOW_THRESHOLD => 'This device\'s clock agrees with the server to within '.self::MIN_APPLY_SECONDS.'s — nothing is corrected.',
            self::REASON_STALE => 'Last measured over '.self::MAX_OFFSET_AGE_DAYS.' days ago — correction suspended until the device pushes again.',
            default => 'This device\'s clock has not been measured yet — punches are stored exactly as it reports them.',
        };
    }
}
