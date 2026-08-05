<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Give `biometric_templates` a per-finger identity.
 *
 * `BiometricProcessingService::processTemplateUpload()` keyed its
 * `updateOrInsert` on (device_user_id, biometric_device_id, template_type) and
 * never captured `FID` at all, so a person's second enrolled finger OVERWROTE
 * their first. The live MB460 (`AF6P231260266`) holds 26 fingerprints across 13
 * employees — an average of two per person — so the roaming restore path could
 * only ever have given about half of them back, silently, at the exact moment a
 * recovery was being relied on.
 *
 * The capture fix is in the service. This is the schema half: the slot key that
 * makes two fingers for one person two rows instead of one.
 *
 * ── The key: (biometric_device_id, device_user_id, template_type, finger_index)
 *
 * That is what physically identifies one stored biometric: *this* reader, *this*
 * enrolled PIN, *this* modality, *this* slot on the device. A device cannot hold
 * two different templates in one slot, so nothing genuine is ever rejected, and
 * a re-push of the same finger updates the row it should update.
 *
 * None of the four columns is nullable once this migration has run, which is the
 * whole point. Both MySQL and SQLite treat NULLs in a unique index as always
 * distinct, so a nullable `finger_index` would be *invisible* to the constraint —
 * every row would satisfy it and the defect would survive the fix. `finger_index`
 * is therefore made NOT NULL, and the two "no meaningful index" cases are given
 * explicit sentinel values rather than NULL:
 *
 *   -1  NO_FINGER_INDEX     face and palm. There is no finger. It must NOT be 0,
 *                           because 0 is a real finger slot — a face row keyed at
 *                           0 would sit in the same slot identity as somebody's
 *                           thumb, which is precisely the collision the key
 *                           exists to prevent. (`template_type` is in the key as
 *                           well, so this is belt and braces; the sentinel keeps
 *                           the invariant true even for a consumer that keys on
 *                           PIN + slot alone, which is what the device does.)
 *    0  UNKNOWN_FINGER_INDEX a fingerprint whose FID we do not know.
 *
 * ── Existing rows: kept, canonicalised, never destroyed ─────────────────────
 *
 * Production already holds real captured templates. A template is the only copy
 * of somebody's enrolment we have, so "drop the ambiguous rows" is not on the
 * table.
 *
 *  - **Face / palm rows → -1.** Unconditionally, not just where NULL. Nothing
 *    has ever written a finger index to a face row, and the value is meaningless
 *    for the modality by construction; leaving a stray number there would let two
 *    face rows for one person coexist under the new key, which is the duplicate
 *    we are trying to remove. `template_data` is untouched.
 *
 *  - **Fingerprint rows → 0.** They were captured before FID was parsed, so we
 *    genuinely do not know which finger each one is. 0 is chosen over a distinct
 *    "unknown" sentinel because it is what these rows ALREADY restore as today —
 *    `TemplateRoamingService::FALLBACK_FINGER_INDEX` is 0, so a legacy row keeps
 *    behaving exactly as it does now, and the migration changes no outcome. It
 *    also keeps the row inside the addressable 0-9 range, so when the device next
 *    pushes that person's real finger 0 the row is UPDATED in place with better
 *    information instead of a stale unknown row lingering forever beside it. A
 *    separate sentinel would do the opposite: an unreplaceable row that queues a
 *    second `UPDATE FINGERTMP` writing the same physical slot as the real one.
 *    The residual cost is small and bounded — at worst one row per person is
 *    labelled finger 0 while the finger it came from was another one, which is
 *    exactly the state we are in today, and it self-corrects on the next push.
 *
 * ── Pre-existing duplicates: archived, never silently dropped ────────────────
 *
 * Backfilling to a shared index is only safe if the pre-existing rows are already
 * unique under the new key, and nothing has ever guaranteed that: the old logical
 * key was enforced by an `updateOrInsert` (a non-atomic SELECT-then-write) with NO
 * database constraint behind it, so two template pushes landing together could
 * both insert. Canonicalising then makes those rows collide, and the unique index
 * would fail on a live table halfway through a deploy.
 *
 * So the same discipline the `biometric_att_logs` collapse used
 * (2026_08_03_000001) applies here: every row this migration removes is first
 * copied in full, as JSON, with the id of the row kept in its place, into
 * `biometric_template_duplicates`. Nothing is destroyed, the collapse is
 * reviewable afterwards, and `down()` puts every row back.
 *
 * Which row survives: the FRESHEST (`updated_at` desc, then highest id). This is
 * deliberately the opposite of the att-log collapse, which kept the lowest id —
 * there the duplicates were re-stagings of one event and the first capture was
 * the original; here each row is a re-enrolment of the same slot, and the newest
 * template is the one that actually matches the finger on the person's hand.
 * `restoreTemplatesToDevice()` already resolves cross-device conflicts the same
 * way (`orderByDesc('updated_at')`), so the two agree.
 *
 * ── Portability ─────────────────────────────────────────────────────────────
 *
 * No driver guard anywhere. Every statement is query-builder or schema-builder,
 * so MySQL and the SQLite the test suite runs on take the same path — a
 * MySQL-only `if` here would mean the constraint the tests prove is not the
 * constraint production gets, which is the failure 2026_07_29_000002 exists to
 * clean up after. The duplicate groups are located with the database's own
 * GROUP BY, so grouping obeys the server's collation (utf8mb4 case-insensitive
 * on MySQL, binary on SQLite) — the same rules the index itself will apply — and
 * the final check re-asks the database, so a wrong assumption aborts the
 * migration with a readable error BEFORE the DDL rather than during it.
 */
return new class extends Migration
{
    private const TABLE = 'biometric_templates';

    private const ARCHIVE = 'biometric_template_duplicates';

    private const INDEX = 'biometric_templates_finger_slot_unique';

    /** @var list<string> */
    private const KEY = ['biometric_device_id', 'device_user_id', 'template_type', 'finger_index'];

    private const FINGERPRINT = 'fingerprint';

    /**
     * Face / palm sentinel. Mirrors TemplateRoamingService::NO_FINGER_INDEX —
     * deliberately duplicated rather than imported, because a migration that
     * references application code stops being runnable the day that class moves.
     */
    private const NO_FINGER_INDEX = -1;

    /** Mirrors TemplateRoamingService::FALLBACK_FINGER_INDEX. */
    private const UNKNOWN_FINGER_INDEX = 0;

    /** Duplicate slot groups collapsed per pass. */
    private const PAGE = 200;

    /** Rows moved to the archive per statement. */
    private const BATCH = 500;

    public function up(): void
    {
        $this->ensureColumnExists();
        $this->createArchiveTable();

        $canonicalised = $this->canonicaliseFingerIndex();
        $archived = $this->collapseDuplicateSlots();

        $this->assertNoDuplicateSlotsRemain();

        // NOT NULL only after every row has a value, or the change itself fails.
        // The default is the unknown-fingerprint sentinel on purpose: a writer
        // that omits the column is describing a fingerprint whose slot it does
        // not know, and a device push must still STORE a template rather than
        // error out (which, on ADMS, means the device retries the same payload
        // forever).
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->integer('finger_index')
                ->default(self::UNKNOWN_FINGER_INDEX)
                ->nullable(false)
                ->change();
        });

        if (! Schema::hasIndex(self::TABLE, self::INDEX)) {
            Schema::table(self::TABLE, function (Blueprint $table) {
                $table->unique(self::KEY, self::INDEX);
            });
        }

        Log::info('biometric_templates: finger slot key enforced', [
            'index' => self::INDEX,
            'key' => self::KEY,
            'rows_canonicalised' => $canonicalised,
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

        // Nullable again before anything tries to write a NULL back, and before
        // the archived rows return — they must not be rejected by the very
        // constraint this is undoing.
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->integer('finger_index')->nullable()->default(null)->change();
        });

        $this->restoreArchivedRows();

        // The face/palm sentinel is unambiguous — nothing but this migration can
        // have written -1 — so it reverts cleanly to NULL.
        //
        // The fingerprint backfill deliberately does NOT revert. After down() a
        // stored 0 and a stored NULL are behaviourally identical (the roaming
        // service normalises NULL to slot 0 either way), and blanking every 0
        // would also erase the real FIDs captured since this migration ran —
        // destroying information the device gave us in order to undo a change
        // that had no observable effect.
        DB::table(self::TABLE)
            ->where('finger_index', self::NO_FINGER_INDEX)
            ->update(['finger_index' => null]);

        Schema::dropIfExists(self::ARCHIVE);
    }

    /**
     * The column has existed (nullable, unwritten) since the table was created,
     * but a schema that is missing it must not make this migration explode —
     * it is added in the shape the create migration declares.
     */
    private function ensureColumnExists(): void
    {
        if (Schema::hasColumn(self::TABLE, 'finger_index')) {
            return;
        }

        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->integer('finger_index')->nullable()->after('template_type');
        });
    }

    /**
     * Cold storage for the rows the collapse removes.
     *
     * No foreign keys: an archive whose rows can be cascaded away by a delete
     * elsewhere is not an archive. The key columns are lifted out of the JSON so
     * it can be searched without decoding every row, and `template_type` is a
     * plain string rather than the enum the live table uses, because history
     * should not be re-validated against today's rules.
     *
     * `payload` contains `template_data` — the biometric itself. That is
     * deliberate (the archive must be able to restore the row) and it is the
     * reason nothing here is ever written to a log file.
     */
    private function createArchiveTable(): void
    {
        if (Schema::hasTable(self::ARCHIVE)) {
            return;
        }

        Schema::create(self::ARCHIVE, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('source_id')->index()->comment('original biometric_templates.id');
            $table->unsignedBigInteger('kept_template_id')->nullable()->index()->comment('the row kept for this slot');
            $table->unsignedBigInteger('biometric_device_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('device_user_id')->nullable();
            $table->string('template_type')->nullable();
            $table->integer('finger_index')->nullable();
            $table->string('template_version')->nullable();
            $table->json('payload')->comment('the complete original row, including template_data');
            $table->timestamp('archived_at')->nullable();
        });
    }

    /**
     * Give every existing row a non-null, modality-correct finger index.
     *
     * Written as two targeted UPDATEs rather than a row-by-row pass: the whole
     * point is that the database applies one rule per modality, atomically, with
     * no PHP in the loop to get it wrong on a table of unknown size.
     *
     * `!=` alone would not match NULL rows on either driver (NULL != -1 is NULL,
     * not true), hence the explicit `whereNull` OR in each clause.
     *
     * @return int rows changed
     */
    private function canonicaliseFingerIndex(): int
    {
        $touched = DB::table(self::TABLE)
            ->where('template_type', '!=', self::FINGERPRINT)
            ->where(function ($query) {
                $query->whereNull('finger_index')
                    ->orWhere('finger_index', '!=', self::NO_FINGER_INDEX);
            })
            ->update(['finger_index' => self::NO_FINGER_INDEX]);

        // Unknown, or a negative value that would collide with the face sentinel.
        $touched += DB::table(self::TABLE)
            ->where('template_type', self::FINGERPRINT)
            ->where(function ($query) {
                $query->whereNull('finger_index')
                    ->orWhere('finger_index', '<', 0);
            })
            ->update(['finger_index' => self::UNKNOWN_FINGER_INDEX]);

        return $touched;
    }

    /**
     * Collapse every duplicated slot down to one row.
     *
     * Each pass asks the database for up to PAGE duplicated keys, reads back the
     * rows for exactly those keys, and archives + deletes all but the winner.
     * There is no OFFSET: a collapsed group stops satisfying `HAVING COUNT(*) > 1`
     * and drops out of the result on its own, so an unpaged LIMIT always returns
     * work that still needs doing. Archive and delete share a transaction per
     * batch, so a row can never be deleted without its copy being committed.
     *
     * @return int rows archived
     */
    private function collapseDuplicateSlots(): int
    {
        $archived = 0;

        while (true) {
            $groups = $this->duplicateSlotGroups(self::PAGE);

            if ($groups->isEmpty()) {
                return $archived;
            }

            $plan = $this->planCollapse($this->rowsForKeys($groups));

            if ($plan === []) {
                // The database says these slots are duplicated but re-reading
                // them produced nothing to remove. Stop rather than spin; no
                // rows have been touched at this point.
                throw new RuntimeException(
                    'biometric_templates: duplicate slots reported but no collapsible rows were found. '.
                    'Aborting before the unique index is created; no rows were removed in this pass.'
                );
            }

            foreach (array_chunk($plan, self::BATCH, true) as $batch) {
                $archived += $this->archiveAndDelete($batch);
            }
        }
    }

    /**
     * Slot tuples that currently have more than one row.
     *
     * @return Collection<int, object>
     */
    private function duplicateSlotGroups(int $limit)
    {
        return DB::table(self::TABLE)
            ->select(self::KEY)
            ->selectRaw('COUNT(*) as duplicate_rows')
            ->groupBy(self::KEY)
            ->havingRaw('COUNT(*) > 1')
            ->limit($limit)
            ->get();
    }

    /**
     * Every row belonging to the given slot tuples.
     *
     * An OR of exact ANDs rather than four `whereIn`s: `whereIn` per column would
     * match the cross product and pull in unrelated rows.
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
     * Decide, per slot, which row survives: newest `updated_at`, then highest id.
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

            // Descending, so the survivor is first. Timestamps are stored as
            // 'Y-m-d H:i:s' on both drivers, where lexical order IS chronological
            // order; a NULL sorts last, which is right — a row with no update
            // time is the least evidenced of the set.
            usort($group, function ($a, $b) {
                return [(string) ($b->updated_at ?? ''), (int) $b->id]
                    <=> [(string) ($a->updated_at ?? ''), (int) $a->id];
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
     * The values come straight back from the same GROUP BY / WHERE that selected
     * them, so string casting is enough to re-associate them here; the database —
     * not this string — is what decided two rows share a slot.
     */
    private function groupKey(object $row): string
    {
        return implode("\0", array_map(
            fn (string $column) => (string) $row->{$column},
            self::KEY
        ));
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
                'kept_template_id' => $entry['kept'],
                'biometric_device_id' => $row->biometric_device_id ?? null,
                'user_id' => $row->user_id ?? null,
                'device_user_id' => $row->device_user_id ?? null,
                'template_type' => $row->template_type ?? null,
                'finger_index' => $row->finger_index ?? null,
                'template_version' => $row->template_version ?? null,
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
     * Asks the database itself whether any slot still holds more than one row. If
     * the collapse missed anything — a collation subtlety, a row written by
     * another connection while this ran — the migration fails here with the
     * offending slot in the message, rather than at `ALTER TABLE` with a driver
     * error and a half-applied schema.
     */
    private function assertNoDuplicateSlotsRemain(): void
    {
        $remaining = $this->duplicateSlotGroups(5);

        if ($remaining->isEmpty()) {
            return;
        }

        $sample = $remaining->map(fn ($group) => sprintf(
            'device=%s pin=%s type=%s fid=%s (%d rows)',
            $group->biometric_device_id,
            $group->device_user_id,
            $group->template_type,
            $group->finger_index,
            $group->duplicate_rows
        ))->implode('; ');

        throw new RuntimeException(
            'biometric_templates still contains duplicate finger slots after the collapse; '.
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
