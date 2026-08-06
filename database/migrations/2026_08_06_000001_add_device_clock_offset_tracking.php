<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-device clock offset: measure it, store it, prove what was applied.
 *
 * ── The incident ────────────────────────────────────────────────────────────
 *
 * The production MB460 (`AF6P231260266`) has reported timestamps exactly two
 * hours in the future since the day it was installed. Measured against the
 * moment our server received each LIVE push:
 *
 *     2026-05  n=64   median -2.00 h      2026-07  n=422  median -2.00 h
 *     2026-06  n=254  median -2.00 h      2026-08  n=87   median -2.00 h
 *
 * Stable to the second across 827 samples and four months, so this is a
 * constant offset — a timezone the device recomputes from (UTC+8 against a
 * business running UTC+6) — not drift. Correcting the clock on the unit does
 * not hold; it returns to +2h. The ERP therefore has to hold the authority,
 * which means the offset has to be a stored, measured, visible property of the
 * device rather than something a human keeps re-fixing at the wall.
 *
 * ── What this migration adds, and why each piece exists ─────────────────────
 *
 * 1. `biometric_devices.clock_offset_seconds` / `_samples` / `_measured_at` —
 *    the current estimate, materialised on the device row so the ingest path
 *    costs no extra query per punch and the admin UI can render it directly.
 *
 *    **`clock_offset_seconds` is NULLABLE and null is load-bearing.** Null means
 *    "never measured", which must behave differently from a measured zero: a
 *    measured zero is positive evidence that this device's clock agrees with
 *    ours, whereas null is the absence of evidence. A DEFAULT 0 would have made
 *    every device in the table claim a verified-correct clock the moment this
 *    migration ran.
 *
 * 2. `biometric_device_clock_samples` — the rolling raw evidence the estimate is
 *    computed from. A child table rather than a JSON blob on the device row for
 *    two reasons: an append is atomic (concurrent pushes cannot lose each
 *    other's sample the way a read-modify-write on a JSON column would), and the
 *    median is computed by ordering rows in the database instead of decoding an
 *    array. It is pruned to a bounded number of rows per device by
 *    DeviceClockService, so it cannot grow without limit on a busy reader.
 *
 * 3. `biometric_att_logs.corrected_punch_time` / `clock_offset_applied_seconds`
 *    — what we wrote into `attendances` and the exact adjustment used to get
 *    there. Both nullable; both null means no correction was applied and
 *    `punch_time` is what was used.
 *
 *    **`punch_time` deliberately keeps holding the RAW device timestamp**, so
 *    the correction is additive and the device's own account of the punch is
 *    never overwritten. Two hard reasons, beyond the auditor's:
 *
 *      - `punch_time` is part of the punch natural key
 *        (biometric_device_id, user_pin, punch_time, check_type) that migration
 *        2026_08_03_000001 made unique. That key is what stops a re-pushed punch
 *        being staged twice. If corrected time lived there, the key would move
 *        every time the offset estimate moved, and a device redelivering a punch
 *        it already sent would sail straight past the constraint — reopening the
 *        48k-duplicate defect that key was created to close.
 *      - Correction is recomputed FROM the raw column on every replay. Keeping
 *        raw immutable is what makes re-importing a row idempotent instead of
 *        correcting an already-corrected value a second time.
 *
 * ── Portability ─────────────────────────────────────────────────────────────
 *
 * No `DB::getDriverName()` branch anywhere: schema-builder only, so the SQLite
 * the test suite runs on and the MySQL production runs on take the identical
 * path. A driver-only branch in this repo once meant the thing the tests proved
 * was not the thing production got. Every column add is `hasColumn`-guarded so a
 * partially-applied run can be re-run, and `after()` is honoured by MySQL and
 * ignored by SQLite, which is a cosmetic difference only.
 */
return new class extends Migration
{
    private const SAMPLES_TABLE = 'biometric_device_clock_samples';

    public function up(): void
    {
        Schema::table('biometric_devices', function (Blueprint $table) {
            // Signed: a device can be behind us as easily as ahead of us.
            // Positive = the device's clock reads LATER than ours (our MB460:
            // +7196, one second short of two hours plus its round trip).
            if (! Schema::hasColumn('biometric_devices', 'clock_offset_seconds')) {
                $table->integer('clock_offset_seconds')->nullable()->after('last_log_download_at');
            }

            // How many samples the stored estimate was computed from. Below
            // DeviceClockService::MIN_TRUSTED_SAMPLES the offset is measured but
            // NOT trusted, and nothing is corrected.
            if (! Schema::hasColumn('biometric_devices', 'clock_offset_samples')) {
                $table->unsignedInteger('clock_offset_samples')->nullable()->after('clock_offset_seconds');
            }

            // When the estimate was last recomputed, i.e. when this device last
            // gave us live evidence. An estimate nobody has re-confirmed in a
            // long time stops being applied — see DeviceClockService::offsetState().
            if (! Schema::hasColumn('biometric_devices', 'clock_offset_measured_at')) {
                $table->timestamp('clock_offset_measured_at')->nullable()->after('clock_offset_samples');
            }
        });

        if (! Schema::hasTable(self::SAMPLES_TABLE)) {
            Schema::create(self::SAMPLES_TABLE, function (Blueprint $table) {
                $table->id();
                $table->foreignId('biometric_device_id')->constrained('biometric_devices')->cascadeOnDelete();

                // device_time - received_at, in seconds.
                $table->integer('offset_seconds');

                // Both sides of the subtraction are kept so a sample can be
                // re-derived and argued with, rather than being a number with no
                // provenance.
                $table->timestamp('device_time');
                $table->timestamp('received_at');

                $table->timestamp('created_at')->nullable();

                // The estimator reads "newest N samples for this device", which
                // is exactly this index.
                $table->index(['biometric_device_id', 'id'], 'bio_dev_clock_samples_device_id_idx');
            });
        }

        Schema::table('biometric_att_logs', function (Blueprint $table) {
            // The moment actually written to `attendances`. NULL = no correction
            // was applied and `punch_time` is what was used.
            if (! Schema::hasColumn('biometric_att_logs', 'corrected_punch_time')) {
                $table->timestamp('corrected_punch_time')->nullable()->after('punch_time');
            }

            // The signed adjustment added to the raw device time to get there.
            // Stored alongside the result rather than inferred by subtracting the
            // two timestamps, so a row still says what was done to it even if the
            // device's estimate has since moved.
            if (! Schema::hasColumn('biometric_att_logs', 'clock_offset_applied_seconds')) {
                $table->integer('clock_offset_applied_seconds')->nullable()->after('corrected_punch_time');
            }
        });
    }

    public function down(): void
    {
        Schema::table('biometric_att_logs', function (Blueprint $table) {
            foreach (['corrected_punch_time', 'clock_offset_applied_seconds'] as $column) {
                if (Schema::hasColumn('biometric_att_logs', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::dropIfExists(self::SAMPLES_TABLE);

        Schema::table('biometric_devices', function (Blueprint $table) {
            foreach (['clock_offset_seconds', 'clock_offset_samples', 'clock_offset_measured_at'] as $column) {
                if (Schema::hasColumn('biometric_devices', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
