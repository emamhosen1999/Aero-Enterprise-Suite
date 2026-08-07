<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * The archive and the idempotency ledger for the retroactive clock correction.
 *
 * ── What it is for ──────────────────────────────────────────────────────────
 *
 * `DeviceClockService` corrects the production MB460's two-hour clock error at
 * ingest, but it only started doing so on 2026-08-06. Every biometric punch
 * written before that landed in `attendances` two hours in the future: a 09:00
 * arrival stored as 11:00, a 17:00 departure looking like two hours of overtime.
 * `biometric:correct-historical-clock-offset` shifts that history back. This
 * table is what makes that operation safe to run against payroll data.
 *
 * It serves two purposes at once, and they are the same rows:
 *
 *  1. **Archive.** Before an `attendances` row is touched, the complete original
 *     row is copied here as JSON, with its id. Nothing is destroyed and nothing
 *     is inferred; a reviewer — or a restore — reads the exact bytes that were
 *     there before.
 *  2. **Idempotency ledger.** `attendance_id` is UNIQUE. A row that appears here
 *     has already been through the correction and can never go through it again,
 *     because the second archive insert violates the constraint before any
 *     `UPDATE` is issued.
 *
 * ── Why the guard is a UNIQUE constraint and not a query filter ──────────────
 *
 * Double-shifting a punch by another two hours is the single worst outcome this
 * feature can produce: it is silent, it is payroll-visible, and the second shift
 * looks exactly as plausible as the first. A `WHERE NOT EXISTS` alone would be a
 * check-then-act race — two operators, or an operator and a retry, could both
 * read "not yet corrected" and both write. The unique index makes the check and
 * the act the same statement, decided by the database.
 *
 * It is keyed on `attendances.id`, deliberately, and not on any punch value:
 *
 *  - a re-run with different `--seconds`, `--from`, `--until` or a different
 *    `--device` still collides on the same primary key;
 *  - it does not depend on the corrected timestamp no longer matching
 *    `biometric_att_logs.punch_time`. That happens to be true for a 7200 s
 *    shift, but it is a coincidence of this offset — with `--seconds` small
 *    enough, or a punch shifted onto another log's timestamp, the selection
 *    query alone would re-select the row. The ledger does not care.
 *
 * ── Why there is no foreign key ─────────────────────────────────────────────
 *
 * Same reason `biometric_att_log_duplicates` has none: an archive whose rows can
 * be removed by a cascade from elsewhere is not an archive. `attendances.user_id`
 * is `cascadeOnDelete`, so deleting a user deletes their attendance — and would
 * take the only record of what was done to it with them. The id is kept as a
 * plain integer and the payload stands on its own.
 *
 * ── `applied_at` is nullable, and that is the crash story ───────────────────
 *
 * The command commits the archive rows FIRST, in their own transaction, and only
 * then opens the transaction that updates `attendances` and stamps `applied_at`.
 * A crash in between therefore leaves an archived row with `applied_at IS NULL`:
 * guarded, but never actually shifted. That biases the failure toward
 * UNDER-correction, which is visible in a report and fixable by hand, instead of
 * toward a shifted row with no ledger entry — which the next run would shift
 * again. The command counts these and reports them rather than quietly adopting
 * them.
 *
 * ── Portability ─────────────────────────────────────────────────────────────
 *
 * Schema builder only, no `DB::getDriverName()` branch: SQLite (tests) and MySQL
 * (production) get the identical table and the identical unique index. A
 * driver-guarded migration in this repo once meant the thing the tests proved was
 * not the thing production ran.
 */
return new class extends Migration
{
    public const TABLE = 'attendance_clock_corrections';

    public function up(): void
    {
        if (Schema::hasTable(self::TABLE)) {
            return;
        }

        Schema::create(self::TABLE, function (Blueprint $table) {
            $table->id();

            // THE GUARD. One correction per attendance row, ever, enforced by the
            // database rather than by whoever remembers to filter.
            $table->unsignedBigInteger('attendance_id')->unique('attendance_clock_corrections_attendance_unique');

            // Which device's error this row was corrected for. Nullable and not a
            // foreign key: the device may be retired later, the history stays.
            $table->unsignedBigInteger('biometric_device_id')->nullable()->index();
            $table->string('device_serial')->nullable();

            $table->unsignedBigInteger('user_id')->nullable()->index();

            // The business date of the row. Duplicated out of the payload so a
            // payroll period can be queried without decoding every JSON blob.
            $table->date('attendance_date')->nullable()->index();

            // The signed adjustment ADDED to the stored punch times. The MB460's
            // history is corrected by -7200. Recorded per row rather than inferred
            // by subtracting the two timestamps, so the row still says what was
            // done to it even if someone later edits a punch by hand.
            $table->integer('applied_seconds');

            // Before/after for both punches, in the clear. `*_after` is what the
            // command wrote; if it does not match the live row today, the row has
            // been edited since and a reviewer can see that immediately.
            $table->dateTime('punchin_before')->nullable();
            $table->dateTime('punchin_after')->nullable();
            $table->dateTime('punchout_before')->nullable();
            $table->dateTime('punchout_after')->nullable();

            // The complete original `attendances` row, including its id. This is
            // what makes the operation reversible.
            $table->json('payload');

            // Groups every row written by one invocation, so a single run can be
            // reviewed — or reversed — as a unit.
            $table->uuid('run_id')->index();

            $table->timestamp('archived_at')->nullable();

            // NULL means archived but NOT confirmed shifted — see the header.
            $table->timestamp('applied_at')->nullable();
        });
    }

    /**
     * Rolling back refuses to destroy evidence of a payroll mutation.
     *
     * If any correction has actually been applied, this table is the only record
     * of the original punch times and the only thing stopping a re-run from
     * shifting them again. Dropping it as a side effect of `migrate:rollback`
     * would be silent data loss with no way back, so the rollback fails instead
     * and says exactly what to do. An unused table (nothing applied) rolls back
     * normally.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        $applied = DB::table(self::TABLE)->whereNotNull('applied_at')->count();

        if ($applied > 0) {
            throw new RuntimeException(
                self::TABLE." holds {$applied} applied clock corrections — it is the only record of the ".
                'original punch times and the only guard against re-shifting them. Refusing to drop it. '.
                'Restore the punch times from the archive first, then drop the table by hand.'
            );
        }

        Log::info('attendance_clock_corrections dropped (no applied corrections)');

        Schema::dropIfExists(self::TABLE);
    }
};
