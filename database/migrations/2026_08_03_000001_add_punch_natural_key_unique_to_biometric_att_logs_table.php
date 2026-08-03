<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Stop `biometric_att_logs` re-staging the same punch forever.
 *
 * The ADMS capture path inserted every pushed/downloaded line with a plain
 * `insert()` and no dedupe, so every re-download of a device's history staged
 * the whole window again. On the live MB460 (`AF6P231260266`) that produced
 * 48,244 rows stamped `duplicate` against 953 `processed`.
 *
 * The fix is a unique constraint over the punch natural key, plus an idempotent
 * insert in BiometricProcessingService. This migration is the constraint, and —
 * because the production table already holds tens of thousands of rows that
 * violate it — the one-time collapse that lets the index build.
 *
 * ── The key: (biometric_device_id, user_pin, punch_time, check_type) ─────────
 *
 * That is what physically identifies one punch: *this* reader, *this* enrolled
 * PIN, *this* instant, *this* direction. The device itself cannot produce two
 * distinct punches with all four equal — a person cannot be read twice by the
 * same terminal in the same second — so nothing genuine is ever rejected.
 *
 *  - `biometric_device_id` and not `serial_number`: they are 1:1 (the capture
 *    path writes both from the same lookup), the integer keeps the index narrow,
 *    and it is the column every other query already joins on. See the NULL note
 *    below for the one case where that choice matters.
 *  - `check_type` is IN the key, deliberately widening it. A punch re-pushed by
 *    the device always carries the same Status byte, so including it costs no
 *    dedupe power; leaving it out would make an in/out pair recorded at the same
 *    second by the same reader collide, and silently drop a real punch. When the
 *    two options are "might keep one extra row" and "might delete a real punch",
 *    a live attendance table takes the extra row.
 *  - `verify_code` / `work_code` are NOT in the key. They are attributes of a
 *    punch, not its identity, and a device that re-sends the same punch with a
 *    blank verify code would otherwise slip straight past the constraint.
 *
 * NULL `biometric_device_id`: both MySQL and SQLite treat NULLs in a unique
 * index as always distinct, so rows whose device was deleted (the column is
 * `nullOnDelete`) are exempt from both the collapse and the constraint. That is
 * intentional — those rows are orphaned history, they have no device to re-push
 * them, and rewriting them to satisfy an index would be the migration inventing
 * provenance it does not have.
 *
 * ── Pre-existing duplicates: archived, never silently dropped ────────────────
 *
 * This runs against a live table holding real attendance provenance, so losing
 * rows is not an acceptable side effect of adding an index. Every row this
 * migration removes from `biometric_att_logs` is first copied — in full, as JSON,
 * with the id of the row that was kept in its place — into
 * `biometric_att_log_duplicates`. Nothing is destroyed; the collapse is
 * reviewable after the fact and `down()` puts every row back.
 *
 * Which row survives, most to least meaningful:
 *
 *   processed    the punch reached the `attendances` table — the only row that
 *                proves attendance was actually recorded
 *   duplicate    the punch is accounted for by another attendance row
 *   downloaded   staged and still awaiting import — real, unfinished work
 *   unknown_user carries a resolved/placeholder user link an admin may act on
 *   wrong_device a real punch, rejected by zoning
 *   failed       nothing happened; the least informative row of the set
 *
 * Ties break on the LOWEST id: the first capture of that punch is the original,
 * and the tens of thousands of higher ids for the same key are the re-stagings
 * this migration exists to stop.
 *
 * ── Portability ─────────────────────────────────────────────────────────────
 *
 * No driver guard anywhere. The collapse is query-builder only and the index is
 * declared through the schema builder, so MySQL and the SQLite used by the test
 * suite take exactly the same path — a MySQL-only `if` here would mean the
 * constraint the tests prove is not the constraint production gets.
 *
 * Duplicate groups are located with the database's own GROUP BY rather than by
 * comparing keys in PHP, so grouping obeys the server's collation (utf8mb4
 * case-insensitive on MySQL, binary on SQLite) — the same rules the unique index
 * itself will apply. The final verification below re-asks the database, so if
 * any of that reasoning is wrong the migration aborts with a readable error
 * BEFORE the index is attempted, instead of failing halfway through a DDL.
 */
return new class extends Migration
{
    private const TABLE = 'biometric_att_logs';

    private const ARCHIVE = 'biometric_att_log_duplicates';

    private const INDEX = 'biometric_att_logs_punch_natural_key_unique';

    /** @var list<string> */
    private const KEY = ['biometric_device_id', 'user_pin', 'punch_time', 'check_type'];

    /**
     * Lower rank wins. An unknown/blank status ranks last but still beats being
     * deleted outright — it is only ever compared against other rows of the same
     * punch.
     */
    private const STATUS_RANK = [
        'processed' => 1,
        'duplicate' => 2,
        'downloaded' => 3,
        'unknown_user' => 4,
        'wrong_device' => 5,
        'failed' => 6,
    ];

    /** Duplicate key groups collapsed per pass. */
    private const PAGE = 200;

    /** Rows moved to the archive per statement. */
    private const BATCH = 500;

    public function up(): void
    {
        $this->createArchiveTable();

        $archived = $this->collapseDuplicates();

        $this->assertNoDuplicatesRemain();

        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(self::KEY, self::INDEX);
            });
        }

        Log::info('biometric_att_logs: punch natural key enforced', [
            'index' => self::INDEX,
            'key' => self::KEY,
            'rows_archived' => $archived,
            'archive_table' => self::ARCHIVE,
        ]);
    }

    public function down(): void
    {
        if (Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->dropUnique(self::INDEX);
            });
        }

        // Put back everything the collapse took out. The index is dropped first,
        // so the restore cannot fail against the very constraint it is undoing.
        $this->restoreArchivedRows();

        Schema::dropIfExists(self::ARCHIVE);
    }

    /**
     * Cold storage for the rows the collapse removes.
     *
     * Deliberately has no foreign keys: an archive whose rows can be deleted by
     * a cascade from elsewhere is not an archive. The key columns are duplicated
     * out of the JSON so the table can be searched without decoding every row,
     * and `punch_status` is a plain string rather than the enum the live table
     * uses, because history should not be re-validated against today's rules.
     */
    private function createArchiveTable(): void
    {
        if (Schema::hasTable(self::ARCHIVE)) {
            return;
        }

        Schema::create(self::ARCHIVE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->index()->comment('original biometric_att_logs.id');
            $table->unsignedBigInteger('kept_att_log_id')->nullable()->index()->comment('the row kept for this punch');
            $table->unsignedBigInteger('biometric_device_id')->nullable()->index();
            $table->string('serial_number')->nullable();
            $table->string('user_pin')->nullable();
            $table->timestamp('punch_time')->nullable();
            $table->string('check_type')->nullable();
            $table->string('punch_status')->nullable();
            $table->json('payload')->comment('the complete original row');
            $table->timestamp('archived_at')->nullable();
        });
    }

    /**
     * Collapse every duplicate punch group down to one row.
     *
     * Each pass asks the database for up to PAGE duplicate keys, reads back the
     * rows for exactly those keys, and archives + deletes all but the winner.
     * There is no OFFSET: a collapsed group stops satisfying `HAVING COUNT(*) > 1`
     * and drops out of the result on its own, so an unpaged LIMIT always returns
     * work that still needs doing. Archive and delete share a transaction per
     * page, so a row can never be deleted without its copy being committed.
     *
     * @return int rows archived
     */
    private function collapseDuplicates(): int
    {
        $archived = 0;

        while (true) {
            $groups = $this->duplicateKeyGroups(self::PAGE);

            if ($groups->isEmpty()) {
                return $archived;
            }

            $rows = $this->rowsForKeys($groups);
            $plan = $this->planCollapse($rows);

            if ($plan === []) {
                // The database says these keys are duplicated but re-reading them
                // produced nothing to remove. Rather than spin forever, stop and
                // let a human look; no rows have been touched at this point.
                throw new RuntimeException(
                    'biometric_att_logs: duplicate groups reported but no collapsible rows were found. '.
                    'Aborting before the unique index is created; no rows were removed in this pass.'
                );
            }

            foreach (array_chunk($plan, self::BATCH, true) as $batch) {
                $archived += $this->archiveAndDelete($batch);
            }
        }
    }

    /**
     * Key tuples that currently have more than one row.
     *
     * @return Collection<int, object>
     */
    private function duplicateKeyGroups(int $limit)
    {
        return DB::table(self::TABLE)
            ->select(self::KEY)
            ->selectRaw('COUNT(*) as duplicate_rows')
            ->whereNotNull('biometric_device_id')
            ->groupBy(self::KEY)
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get();
    }

    /**
     * Every row belonging to the given key tuples.
     *
     * An OR of exact ANDs rather than four `whereIn`s: `whereIn` on each column
     * separately would match the cross product and pull in unrelated rows.
     *
     * @param  Collection<int, object>  $groups
     * @return Collection<int, object>
     */
    private function rowsForKeys($groups)
    {
        return DB::table(self::TABLE)
            ->where(function ($query) use ($groups) {
                foreach ($groups as $group) {
                    $query->orWhere(function ($q) use ($group) {
                        foreach (self::KEY as $column) {
                            $q->where($column, $group->{$column});
                        }
                    });
                }
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * Decide, per punch, which row survives.
     *
     * @param  Collection<int, object>  $rows
     * @return array<int, array{row: object, kept: int}> loser id => what to archive
     */
    private function planCollapse($rows): array
    {
        $grouped = [];

        foreach ($rows as $row) {
            $grouped[$this->groupKey($row)][] = $row;
        }

        $plan = [];

        foreach ($grouped as $group) {
            if (count($group) < 2) {
                continue;
            }

            usort($group, function ($a, $b) {
                return [$this->statusRank($a->punch_status), (int) $a->id]
                    <=> [$this->statusRank($b->punch_status), (int) $b->id];
            });

            $keeper = array_shift($group);

            foreach ($group as $loser) {
                $plan[(int) $loser->id] = ['row' => $loser, 'kept' => (int) $keeper->id];
            }
        }

        return $plan;
    }

    /**
     * Group rows the way the database grouped them.
     *
     * Values come straight back from the same GROUP BY / WHERE that selected
     * them, so string casting is enough to re-associate them here; the database
     * — not this string — is what decided two rows share a punch.
     */
    private function groupKey(object $row): string
    {
        return implode("\0", array_map(
            fn (string $column) => (string) $row->{$column},
            self::KEY
        ));
    }

    private function statusRank(?string $status): int
    {
        return self::STATUS_RANK[(string) $status] ?? 99;
    }

    /**
     * @param  array<int, array{row: object, kept: int}>  $batch
     * @return int rows archived
     */
    private function archiveAndDelete(array $batch): int
    {
        $records = [];

        foreach ($batch as $id => $entry) {
            $row = $entry['row'];

            $records[] = [
                'source_id' => $id,
                'kept_att_log_id' => $entry['kept'],
                'biometric_device_id' => $row->biometric_device_id,
                'serial_number' => $row->serial_number ?? null,
                'user_pin' => $row->user_pin ?? null,
                'punch_time' => $row->punch_time ?? null,
                'check_type' => $row->check_type ?? null,
                'punch_status' => $row->punch_status ?? null,
                'payload' => json_encode((array) $row),
                'archived_at' => now(),
            ];
        }

        $ids = array_keys($batch);

        DB::transaction(function () use ($records, $ids) {
            DB::table(self::ARCHIVE)->insert($records);
            DB::table(self::TABLE)->whereIn('id', $ids)->delete();
        });

        return count($records);
    }

    /**
     * Last gate before the DDL.
     *
     * Asks the database itself whether any punch key still has more than one
     * row. If the collapse missed anything — a collation subtlety, a row written
     * by another connection while this ran — the migration fails here with the
     * offending key in the message, rather than at `ALTER TABLE` with a driver
     * error and a half-applied schema.
     */
    private function assertNoDuplicatesRemain(): void
    {
        $remaining = $this->duplicateKeyGroups(5);

        if ($remaining->isEmpty()) {
            return;
        }

        $sample = $remaining->map(fn ($group) => sprintf(
            'device=%s pin=%s time=%s type=%s (%d rows)',
            $group->biometric_device_id,
            $group->user_pin,
            $group->punch_time,
            $group->check_type,
            $group->duplicate_rows
        ))->implode('; ');

        throw new RuntimeException(
            'biometric_att_logs still contains duplicate punches after the collapse; '.
            'the unique index was NOT created and no further rows were removed. Sample: '.$sample
        );
    }

    /**
     * Move archived rows back into the live table.
     *
     * Payload keys are intersected with the table's real columns, so a restore
     * still works if the schema has moved on since the archive was written.
     * Original ids are preserved and rows that somehow already exist are left
     * alone — a partially-run `down()` can simply be run again.
     */
    private function restoreArchivedRows(): void
    {
        if (! Schema::hasTable(self::ARCHIVE)) {
            return;
        }

        $columns = Schema::getColumnListing(self::TABLE);

        DB::table(self::ARCHIVE)->orderBy('id')->chunk(self::BATCH, function ($archived) use ($columns) {
            $rows = [];

            foreach ($archived as $entry) {
                $payload = json_decode((string) $entry->payload, true);

                if (! is_array($payload)) {
                    continue;
                }

                $row = array_intersect_key($payload, array_flip($columns));

                if ($row === [] || DB::table(self::TABLE)->where('id', $entry->source_id)->exists()) {
                    continue;
                }

                $rows[] = $row;
            }

            if ($rows !== []) {
                DB::table(self::TABLE)->insert($rows);
            }
        });
    }
};
