<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Re-key the clock-correction ledger from one row per ATTENDANCE to one row per
 * corrected PUNCH COLUMN.
 *
 * ── Why the original key was wrong ──────────────────────────────────────────
 *
 * The first run corrected 459 rows on the live MB460 and was right about every
 * one of them. What it could not express is a row that is corrected TWICE for
 * two different reasons.
 *
 * Attendance 9901 (user 134, 2026-07-04) is the case that proved it. Its
 * punch-in matched a `processed` log and was corrected, 11:40:46 -> 09:40:46.
 * Its punch-out matched a log marked `duplicate` — a genuine device punch whose
 * LOG row was a redundant re-capture — and the old selection skipped it. The
 * ledger then recorded "attendance 9901: done", and because `attendance_id` was
 * UNIQUE, the punch-out could never be corrected on a later run: the archive
 * insert would collide before the update was issued. The day reads 09:40 →
 * 19:09, 9.5 hours where 7.5 were worked. A mixed row, which is worse than a
 * uniformly wrong one, because nothing about it looks wrong.
 *
 * The guard was protecting the right thing at the wrong granularity. Whether a
 * punch may be shifted is a property of that punch, not of the row it shares
 * with another one.
 *
 * ── What this migration does ────────────────────────────────────────────────
 *
 * Adds `punch_column` ('punchin' | 'punchout'), normalises every existing row so
 * that it describes exactly one corrected punch, and moves the UNIQUE index from
 * `(attendance_id)` to `(attendance_id, punch_column)`.
 *
 * The normalisation is mechanical and total:
 *
 *   both punches corrected   -> the row keeps its punch-in correction and a
 *                               SIBLING row is inserted carrying the punch-out
 *                               correction, same payload, same run_id, same
 *                               applied_at. Nothing is lost; the pair says
 *                               exactly what the single row said.
 *   one punch corrected      -> `punch_column` is stamped. No data changes.
 *   neither punch corrected  -> archived but never shifted (the crash case, or
 *                               an unparseable punch). Split into TWO guard rows
 *                               so the whole attendance row stays excluded,
 *                               which is precisely the old behaviour: this
 *                               command cannot tell a shifted row from an
 *                               unshifted one by looking at it, so it refuses to
 *                               adopt either half and reports instead.
 *
 * ── Why a split is safe on an archive ───────────────────────────────────────
 *
 * `payload` — the complete original `attendances` row — is copied verbatim to
 * the sibling, so both rows still restore the same original and the restore path
 * is unchanged. `run_id`, `applied_seconds`, `archived_at` and `applied_at` are
 * copied too, so the pair still attributes the work to the run that did it. The
 * only values that MOVE are `punchout_before` / `punchout_after`, from the
 * original row to its sibling, inside one transaction. No correction is
 * invented, none is discarded, and the count of corrected punches before and
 * after is asserted equal below.
 *
 * ── Ordering, and why the old index is dropped before the backfill ──────────
 *
 * The split INSERTs a second row for an `attendance_id` that already has one, so
 * `attendance_clock_corrections_attendance_unique` has to be gone before it runs
 * or the backfill collides with the constraint it is replacing. The new
 * composite unique is created only after the data satisfies it, and after a
 * verification pass that re-asks the database rather than trusting this file's
 * reasoning. If anything fails to reconcile the migration throws with the
 * offending rows named, before any index is attempted.
 *
 * ── Portability ─────────────────────────────────────────────────────────────
 *
 * Schema builder and query builder only, no `DB::getDriverName()` branch, so the
 * SQLite the tests run against and the MySQL production runs against take the
 * identical path. `punch_column` is added nullable — SQLite cannot add a NOT NULL
 * column to a populated table without a default, and a DEFAULT on a guard column
 * is a footgun: an insert that forgot the column would silently guard 'punchin'.
 * It is tightened to NOT NULL with `change()` only after the backfill has filled
 * every row, so no default is ever needed and none exists.
 */
return new class extends Migration
{
    private const TABLE = 'attendance_clock_corrections';

    private const COLUMN = 'punch_column';

    private const OLD_INDEX = 'attendance_clock_corrections_attendance_unique';

    private const NEW_INDEX = 'attendance_clock_corrections_attendance_column_unique';

    private const PUNCH_IN = 'punchin';

    private const PUNCH_OUT = 'punchout';

    /** Ledger rows read per pass while normalising. */
    private const PAGE = 500;

    public function up(): void
    {
        if (! Schema::hasTable(self::TABLE)) {
            return;
        }

        if (Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        // Measured BEFORE anything moves, and asserted against afterwards. A
        // corrected punch is one with a recorded `*_before`; that count must be
        // identical on the other side of the normalisation.
        $expectedCorrections = $this->correctedPunchCount();
        $expectedAttendances = DB::table(self::TABLE)->distinct()->count('attendance_id');

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COLUMN, 16)->nullable()->after('attendance_id');
        });

        // Must go before the split: the split inserts a second row per
        // attendance_id, which is exactly what this index forbids.
        if (Schema::hasIndex(self::TABLE, self::OLD_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::OLD_INDEX);
            });
        }

        DB::transaction(fn () => $this->normalise());

        $this->verify($expectedCorrections, $expectedAttendances);

        // Every row is filled, so NOT NULL needs no default. Done after the
        // backfill precisely so that no default has to exist.
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string(self::COLUMN, 16)->change();
        });

        if (! Schema::hasIndex(self::TABLE, self::NEW_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(['attendance_id', self::COLUMN], self::NEW_INDEX);
            });
        }

        Log::info('attendance_clock_corrections re-keyed per punch column', [
            'index' => self::NEW_INDEX,
            'corrected_punches' => $expectedCorrections,
            'attendance_rows' => $expectedAttendances,
            'ledger_rows' => DB::table(self::TABLE)->count(),
        ]);
    }

    /**
     * Merge the per-column rows back into one row per attendance.
     *
     * The exact inverse of the split: a punch-out sibling's values are folded
     * back into its punch-in partner and the sibling is removed, so a rolled-back
     * database holds the same corrections in the same shape it held them before.
     */
    public function down(): void
    {
        if (! Schema::hasTable(self::TABLE) || ! Schema::hasColumn(self::TABLE, self::COLUMN)) {
            return;
        }

        if (Schema::hasIndex(self::TABLE, self::NEW_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::NEW_INDEX);
            });
        }

        DB::transaction(fn () => $this->merge());

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn(self::COLUMN);
        });

        if (! Schema::hasIndex(self::TABLE, self::OLD_INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique('attendance_id', self::OLD_INDEX);
            });
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Normalisation
    // ──────────────────────────────────────────────────────────────

    private function normalise(): void
    {
        $lastId = 0;

        while (true) {
            $rows = DB::table(self::TABLE)
                ->whereNull(self::COLUMN)
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit(self::PAGE)
                ->get();

            if ($rows->isEmpty()) {
                return;
            }

            $lastId = (int) $rows->last()->id;

            foreach ($rows as $row) {
                $this->normaliseRow($row);
            }
        }
    }

    private function normaliseRow(object $row): void
    {
        $hasIn = $row->punchin_before !== null;
        $hasOut = $row->punchout_before !== null;

        // Exactly one punch corrected: stamp it and change nothing else.
        if ($hasIn && ! $hasOut) {
            $this->stamp($row->id, self::PUNCH_IN);

            return;
        }

        if ($hasOut && ! $hasIn) {
            $this->stamp($row->id, self::PUNCH_OUT);

            return;
        }

        // Both corrected, or neither. Either way the row currently speaks for
        // both columns, so it becomes two rows that each speak for one.
        $this->splitRow($row);
    }

    private function stamp(int $id, string $column): void
    {
        DB::table(self::TABLE)->where('id', $id)->update([self::COLUMN => $column]);
    }

    /**
     * Turn one row that covers both punches into a punch-in row and a punch-out
     * row.
     *
     * The sibling is built from the original's own values, so a reviewer reading
     * either row sees the same device, user, date, offset, run and payload it
     * always had. The punch-out values MOVE rather than being duplicated: leaving
     * them on the punch-in row would make each row claim a correction it does not
     * own, and the verification below would — correctly — refuse to continue.
     */
    private function splitRow(object $row): void
    {
        $sibling = (array) $row;

        unset($sibling['id']);

        $sibling[self::COLUMN] = self::PUNCH_OUT;
        $sibling['punchin_before'] = null;
        $sibling['punchin_after'] = null;

        DB::table(self::TABLE)->insert($sibling);

        DB::table(self::TABLE)->where('id', $row->id)->update([
            self::COLUMN => self::PUNCH_IN,
            'punchout_before' => null,
            'punchout_after' => null,
        ]);
    }

    /**
     * Fold punch-out rows back into their punch-in partners.
     */
    private function merge(): void
    {
        $pairs = DB::table(self::TABLE)
            ->select('attendance_id')
            ->groupBy('attendance_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('attendance_id');

        foreach ($pairs as $attendanceId) {
            $rows = DB::table(self::TABLE)
                ->where('attendance_id', $attendanceId)
                ->orderBy('id')
                ->get();

            $keeper = $rows->first();
            $out = $rows->firstWhere(self::COLUMN, self::PUNCH_OUT);

            if ($out === null || (int) $out->id === (int) $keeper->id) {
                continue;
            }

            DB::table(self::TABLE)->where('id', $keeper->id)->update([
                'punchout_before' => $out->punchout_before,
                'punchout_after' => $out->punchout_after,
            ]);

            DB::table(self::TABLE)->where('id', $out->id)->delete();
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Verification
    // ──────────────────────────────────────────────────────────────

    /**
     * Re-ask the database whether the normalisation actually holds.
     *
     * Runs BEFORE the unique index is attempted, so a failure aborts with a
     * readable message and an unindexed but intact table, rather than halfway
     * through a DDL with a driver error.
     */
    private function verify(int $expectedCorrections, int $expectedAttendances): void
    {
        $unstamped = DB::table(self::TABLE)->whereNull(self::COLUMN)->count();

        if ($unstamped > 0) {
            throw new RuntimeException(
                self::TABLE." still has {$unstamped} row(s) with no ".self::COLUMN.
                '. The unique index was NOT created. No corrections were lost; inspect and re-run.'
            );
        }

        $unknown = DB::table(self::TABLE)
            ->whereNotIn(self::COLUMN, [self::PUNCH_IN, self::PUNCH_OUT])
            ->count();

        if ($unknown > 0) {
            throw new RuntimeException(
                self::TABLE." has {$unknown} row(s) with an unrecognised ".self::COLUMN.' value.'
            );
        }

        // A punch-in row must not carry punch-out values, and vice versa —
        // otherwise a row claims a correction another row also claims, and the
        // per-column guard would be guarding the wrong thing.
        $bleed = DB::table(self::TABLE)
            ->where(function ($query) {
                $query->where(self::COLUMN, self::PUNCH_IN)
                    ->where(function ($q) {
                        $q->whereNotNull('punchout_before')->orWhereNotNull('punchout_after');
                    });
            })
            ->orWhere(function ($query) {
                $query->where(self::COLUMN, self::PUNCH_OUT)
                    ->where(function ($q) {
                        $q->whereNotNull('punchin_before')->orWhereNotNull('punchin_after');
                    });
            })
            ->count();

        if ($bleed > 0) {
            throw new RuntimeException(
                self::TABLE." has {$bleed} row(s) carrying the other column's correction. Aborting before the index."
            );
        }

        $corrections = $this->correctedPunchCount();

        if ($corrections !== $expectedCorrections) {
            throw new RuntimeException(
                self::TABLE.' corrected-punch count changed during normalisation: expected '.
                $expectedCorrections.', found '.$corrections.'. Nothing was indexed.'
            );
        }

        $attendances = DB::table(self::TABLE)->distinct()->count('attendance_id');

        if ($attendances !== $expectedAttendances) {
            throw new RuntimeException(
                self::TABLE.' now covers '.$attendances.' attendance rows, expected '.$expectedAttendances.'.'
            );
        }

        $duplicates = DB::table(self::TABLE)
            ->select('attendance_id', self::COLUMN)
            ->groupBy('attendance_id', self::COLUMN)
            ->havingRaw('COUNT(*) > 1')
            ->limit(5)
            ->get();

        if ($duplicates->isNotEmpty()) {
            $sample = $duplicates
                ->map(fn ($row) => 'attendance='.$row->attendance_id.' column='.$row->{self::COLUMN})
                ->implode('; ');

            throw new RuntimeException(
                self::TABLE.' still holds duplicate (attendance_id, '.self::COLUMN.') pairs; '.
                'the unique index was NOT created. Sample: '.$sample
            );
        }
    }

    /**
     * How many individual punches this ledger claims to have corrected.
     *
     * The invariant the normalisation preserves. Counted from `*_before`, which
     * is written if and only if that punch was shifted.
     */
    private function correctedPunchCount(): int
    {
        return DB::table(self::TABLE)->whereNotNull('punchin_before')->count()
            + DB::table(self::TABLE)->whereNotNull('punchout_before')->count();
    }
};
