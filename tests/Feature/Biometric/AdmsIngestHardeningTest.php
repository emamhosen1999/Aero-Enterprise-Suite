<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDownloadSession;
use App\Models\User;
use App\Services\Biometric\BiometricProcessingService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * ADMS ingest hardening — three separate defects, one ingest path.
 *
 *  1. **Duplicate staging.** The capture path staged every pushed line with a
 *     plain `insert()` and no dedupe, so each re-download re-staged the device's
 *     whole history. Production held 48,244 rows stamped `duplicate` against 953
 *     `processed`. `biometric_att_logs` now carries a unique key over the punch
 *     natural key (device, PIN, punch_time, check_type) and the capture insert is
 *     idempotent against it.
 *
 *  2. **Handshake honesty (matrix §3).** We advertised `ATTPHOTOStamp` for a
 *     table we have no handler for, and asserted `PushProtVer=2.4.1` while the
 *     device announced its own on every init.
 *
 *  3. **errorlog (matrix §1).** Device fault reports were logged and dropped.
 *
 * The through-line in every test below: none of this may cost a single real
 * punch. A unique key that rejects genuine punches, or a handshake a terminal
 * stops answering, is a worse outcome than the duplicates it was meant to fix.
 */
class AdmsIngestHardeningTest extends TestCase
{
    use RefreshDatabase;

    private const PUNCH_UNIQUE_INDEX = 'biometric_att_logs_punch_natural_key_unique';

    private const DUPLICATE_ARCHIVE = 'biometric_att_log_duplicates';

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────

    private function device(array $overrides = []): BiometricDevice
    {
        // auth_token is unique on biometric_devices, so every fixture device
        // gets its own — several tests here stand up two readers at once.
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'adms',
            'auth_token' => 'token-'.uniqid(),
            'is_active' => true,
        ], $overrides));
    }

    /**
     * A user the punch pipeline will actually accept: biometric attendance type,
     * with this device inside that type's zone.
     */
    private function zonedUser(string $employeeId, BiometricDevice ...$devices): User
    {
        // Seeded by 2026_05_12_000003_seed_biometric_attendance_type.
        $type = AttendanceType::where('slug', 'biometric')->firstOrFail();

        foreach ($devices as $device) {
            $type->biometricDevices()->syncWithoutDetaching([$device->id]);
        }

        return User::factory()->create([
            'employee_id' => $employeeId,
            'attendance_type_id' => $type->id,
        ]);
    }

    private function push(string $serial, string $query, string $body)
    {
        $uri = '/iclock/cdata?SN='.rawurlencode($serial).$query;

        return $this->call('POST', $uri, [], [], [], ['CONTENT_TYPE' => 'text/plain'], $body);
    }

    private function attlogPush(BiometricDevice $device, string $body)
    {
        return $this->push($device->serial_number, '&table=ATTLOG', $body);
    }

    /**
     * An open download session, which is what flips processAttendanceLogs() into
     * its capture-only branch — the branch that produced the 48k duplicates.
     */
    private function openSession(BiometricDevice $device): BiometricDownloadSession
    {
        return BiometricDownloadSession::create([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'manual',
            'status' => 'in_progress',
            'started_at' => now(),
        ]);
    }

    private function service(): BiometricProcessingService
    {
        return app(BiometricProcessingService::class);
    }

    /**
     * Stand in for the unknown-user remediation flow.
     *
     * The live push path auto-creates a soft-deleted placeholder for an unknown
     * PIN, so remediation activates THAT user rather than creating another one
     * (`users.employee_id` is unique), zones the device, and resets the affected
     * rows to `downloaded` — the status the importer selects on.
     */
    private function relinkPlaceholder(string $pin, BiometricDevice $device): User
    {
        $type = AttendanceType::where('slug', 'biometric')->firstOrFail();
        $type->biometricDevices()->syncWithoutDetaching([$device->id]);

        $user = User::withTrashed()->where('employee_id', $pin)->firstOrFail();
        $user->restore();
        $user->forceFill(['attendance_type_id' => $type->id])->save();

        DB::table('biometric_att_logs')->where('user_pin', $pin)->update([
            'user_id' => $user->id,
            'punch_status' => 'downloaded',
            'punch_status_reason' => 'Relinked to employee',
            'updated_at' => now(),
        ]);

        return $user->refresh();
    }

    private function attLogCount(BiometricDevice $device): int
    {
        return DB::table('biometric_att_logs')
            ->where('biometric_device_id', $device->id)
            ->count();
    }

    // ── 1. duplicate staging ────────────────────────────────────────

    /**
     * The headline case, on the branch that actually caused it.
     *
     * With a download session open every pushed line is staged verbatim. Two
     * pushes of the same line is exactly what a repeated CHECK_ATTLOG over a
     * rolling window does to us, tens of thousands of times.
     */
    public function test_the_same_downloaded_line_pushed_twice_stages_only_one_row(): void
    {
        $device = $this->device();
        $this->openSession($device);

        $line = "4301\t2026-07-15 09:00:00\t0\t1";

        $this->attlogPush($device, $line)->assertOk();
        $this->attlogPush($device, $line)->assertOk();

        $this->assertSame(1, $this->attLogCount($device), 'a re-downloaded punch must not be staged twice');
        $this->assertDatabaseHas('biometric_att_logs', [
            'biometric_device_id' => $device->id,
            'user_pin' => '4301',
            'punch_status' => 'downloaded',
        ]);
    }

    /**
     * The same guarantee on the live push branch (no session open).
     *
     * Note what is asserted about the surviving row: it keeps `processed`. The
     * old behaviour wrote a SECOND row and stamped it `duplicate`; the fix must
     * not instead overwrite the first row's status, because that would erase the
     * provenance of a punch that really did reach `attendances`.
     */
    public function test_the_same_live_attlog_line_pushed_twice_stages_only_one_row(): void
    {
        $device = $this->device();
        $user = $this->zonedUser('4302', $device);

        $line = "4302\t2026-07-15 09:00:00\t0";

        $this->attlogPush($device, $line)->assertOk();
        $this->attlogPush($device, $line)->assertOk();

        $this->assertSame(1, $this->attLogCount($device));
        $this->assertDatabaseHas('biometric_att_logs', [
            'biometric_device_id' => $device->id,
            'user_pin' => '4302',
            'punch_status' => 'processed',
        ]);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    /**
     * A whole re-pushed batch, mixing punches we have with one we do not.
     *
     * This is the shape of a real re-download: the overlap is silently absorbed
     * and the genuinely new punch still lands. Absorbing everything would be a
     * regression dressed up as a fix.
     */
    public function test_a_re_pushed_batch_only_stages_the_lines_it_has_not_seen(): void
    {
        $device = $this->device();
        $this->openSession($device);

        $this->attlogPush($device, implode("\n", [
            "4303\t2026-07-15 09:00:00\t0",
            "4303\t2026-07-15 18:00:00\t1",
        ]))->assertOk();

        $this->assertSame(2, $this->attLogCount($device));

        $this->attlogPush($device, implode("\n", [
            "4303\t2026-07-15 09:00:00\t0",
            "4303\t2026-07-15 18:00:00\t1",
            "4303\t2026-07-16 09:00:00\t0",
        ]))->assertOk();

        $this->assertSame(3, $this->attLogCount($device), 'only the unseen line may be staged');
        $this->assertDatabaseHas('biometric_att_logs', [
            'biometric_device_id' => $device->id,
            'punch_time' => '2026-07-16 09:00:00',
        ]);
    }

    /**
     * The other half of the contract, and the one that matters most: the key
     * must not swallow punches that are genuinely different.
     *
     * Same PIN at a different time, the same instant at a different reader, and
     * an in/out pair recorded at the same second — the last is why `check_type`
     * is part of the key rather than left out.
     */
    public function test_the_unique_key_does_not_reject_genuinely_distinct_punches(): void
    {
        $deviceA = $this->device(['name' => 'HQ Gate']);
        $deviceB = $this->device(['name' => 'Warehouse Gate']);
        $this->openSession($deviceA);
        $this->openSession($deviceB);

        // Same PIN, same device, different times.
        $this->attlogPush($deviceA, "4304\t2026-07-15 09:00:00\t0")->assertOk();
        $this->attlogPush($deviceA, "4304\t2026-07-15 09:00:01\t0")->assertOk();

        // Same PIN, same instant, a different reader.
        $this->attlogPush($deviceB, "4304\t2026-07-15 09:00:00\t0")->assertOk();

        // Same reader, same PIN, same instant — opposite direction.
        $this->attlogPush($deviceA, "4304\t2026-07-15 09:00:00\t1")->assertOk();

        // A different person at the same reader in the same second.
        $this->attlogPush($deviceA, "4305\t2026-07-15 09:00:00\t0")->assertOk();

        // Reader A: 4304 in @09:00:00, 4304 in @09:00:01, 4304 out @09:00:00,
        // 4305 in @09:00:00. Reader B: 4304 in @09:00:00. Five distinct punches,
        // five rows — the key rejected none of them.
        $this->assertSame(4, $this->attLogCount($deviceA));
        $this->assertSame(1, $this->attLogCount($deviceB));
        $this->assertSame(5, DB::table('biometric_att_logs')->count());
    }

    /**
     * End to end, unchanged: a live ATTLOG push still becomes attendance.
     *
     * Without this the duplicate assertions above would be satisfied just as
     * well by an ingest path that stopped working entirely.
     */
    public function test_a_live_attlog_push_still_yields_attendance_end_to_end(): void
    {
        $device = $this->device();
        $user = $this->zonedUser('4306', $device);

        $this->attlogPush($device, implode("\n", [
            "4306\t2026-07-15 09:05:00\t0",
            "4306\t2026-07-15 18:10:00\t1",
        ]))->assertOk();

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances, 'ATTLOG must still produce a real attendance row');
        $this->assertSame('2026-07-15 09:05:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15 18:10:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));

        $this->assertSame(2, $this->attLogCount($device));
        $this->assertSame(
            0,
            DB::table('biometric_att_logs')->where('punch_status', '!=', 'processed')->count()
        );
    }

    /**
     * The direct (non-ADMS) webhook shares the staging table, so it meets the
     * same constraint. A redelivered punch must come back as "already recorded",
     * not as a 500 from a raw constraint violation.
     */
    public function test_direct_webhook_redelivery_is_answered_without_a_second_row(): void
    {
        $device = $this->device();
        $user = $this->zonedUser('4307', $device);

        $payload = [
            'auth_token' => $device->auth_token,
            'device_serial' => $device->serial_number,
            'device_user_id' => '4307',
            'punch_time' => '2026-07-15 09:00:00',
            'check_type' => 'in',
        ];

        $this->postJson('/api/biometric/webhook', $payload)->assertOk();
        $this->assertSame(1, $this->attLogCount($device));

        $second = $this->postJson('/api/biometric/webhook', $payload);
        $second->assertOk();
        $this->assertStringContainsString('Duplicate', $second->json('message'));

        $this->assertSame(1, $this->attLogCount($device));
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertDatabaseHas('biometric_att_logs', [
            'biometric_device_id' => $device->id,
            'user_pin' => '4307',
            'punch_status' => 'processed',
        ]);
    }

    /**
     * The constraint is a database constraint, not a code convention.
     *
     * Asserted directly so nobody can satisfy the tests above with an
     * application-level check that a raw insert elsewhere would walk straight
     * past.
     */
    public function test_the_punch_natural_key_is_enforced_by_the_database(): void
    {
        $this->assertTrue(
            Schema::hasIndex('biometric_att_logs', self::PUNCH_UNIQUE_INDEX),
            'the punch natural key must exist as a real unique index'
        );

        $device = $this->device();

        $row = [
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => '4308',
            'punch_time' => '2026-07-15 09:00:00',
            'check_type' => 'in',
            'punch_status' => 'downloaded',
            'occurred_at' => '2026-07-15 09:00:00',
            'created_at' => now(),
            'updated_at' => now(),
        ];

        DB::table('biometric_att_logs')->insert($row);

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('biometric_att_logs')->insert($row);
    }

    // ── 1b. the one-time collapse of pre-existing duplicates ────────

    /**
     * The risky half of the migration, rehearsed.
     *
     * Production had ~64,000 rows and tens of thousands of duplicates when this
     * ran, so the collapse decides which row of each punch survives and where
     * the rest go. This drops the index and the archive, rebuilds the
     * pre-migration state by hand, and runs the real migration object over it.
     *
     * What is pinned: the most meaningful status wins, ties go to the earliest
     * id, nothing is deleted without a full copy landing in the archive, and
     * distinct punches are not touched.
     */
    public function test_the_migration_collapses_pre_existing_duplicates_and_archives_every_row_it_removes(): void
    {
        $device = $this->device();
        $other = $this->device(['name' => 'Warehouse Gate']);

        $this->dropPunchUniqueKey();

        // One punch, staged five times — the production shape in miniature.
        $failedFirst = $this->rawLog($device, '5001', '2026-07-15 09:00:00', 'in', 'failed');
        $duplicate = $this->rawLog($device, '5001', '2026-07-15 09:00:00', 'in', 'duplicate');
        $processed = $this->rawLog($device, '5001', '2026-07-15 09:00:00', 'in', 'processed');
        $downloaded = $this->rawLog($device, '5001', '2026-07-15 09:00:00', 'in', 'downloaded');
        $failedLast = $this->rawLog($device, '5001', '2026-07-15 09:00:00', 'in', 'failed');

        // Genuinely distinct punches that must survive untouched.
        $otherTime = $this->rawLog($device, '5001', '2026-07-15 18:00:00', 'out', 'processed');
        $otherDevice = $this->rawLog($other, '5001', '2026-07-15 09:00:00', 'in', 'processed');
        $otherType = $this->rawLog($device, '5001', '2026-07-15 09:00:00', 'out', 'failed');

        $this->assertSame(8, DB::table('biometric_att_logs')->count());

        $this->runPunchUniqueKeyMigration();

        // The `processed` row is the only one that proves attendance was
        // recorded, so it is the row that survives.
        $survivors = DB::table('biometric_att_logs')
            ->where('biometric_device_id', $device->id)
            ->where('user_pin', '5001')
            ->where('punch_time', '2026-07-15 09:00:00')
            ->where('check_type', 'in')
            ->pluck('id')
            ->all();

        $this->assertSame([$processed], $survivors);

        // Everything else is in the archive, in full, pointing at the keeper.
        $archived = DB::table(self::DUPLICATE_ARCHIVE)->orderBy('source_id')->get();
        $this->assertEqualsCanonicalizing(
            [$failedFirst, $duplicate, $downloaded, $failedLast],
            $archived->pluck('source_id')->map(fn ($id) => (int) $id)->all()
        );

        foreach ($archived as $entry) {
            $this->assertSame($processed, (int) $entry->kept_att_log_id);

            $payload = json_decode((string) $entry->payload, true);
            $this->assertIsArray($payload);
            $this->assertSame((int) $entry->source_id, (int) $payload['id']);
            $this->assertSame('5001', $payload['user_pin']);
            $this->assertArrayHasKey('raw_data', $payload, 'the archive keeps the whole original row');
        }

        // Distinct punches are untouched.
        foreach ([$otherTime, $otherDevice, $otherType] as $id) {
            $this->assertDatabaseHas('biometric_att_logs', ['id' => $id]);
        }

        $this->assertSame(4, DB::table('biometric_att_logs')->count());
        $this->assertTrue(Schema::hasIndex('biometric_att_logs', self::PUNCH_UNIQUE_INDEX));
    }

    /**
     * Ties break on the lowest id: the original capture, not a re-staging.
     */
    public function test_the_collapse_keeps_the_earliest_row_when_statuses_tie(): void
    {
        $device = $this->device();

        $this->dropPunchUniqueKey();

        $first = $this->rawLog($device, '5002', '2026-07-15 09:00:00', 'in', 'processed');
        $second = $this->rawLog($device, '5002', '2026-07-15 09:00:00', 'in', 'processed');
        $third = $this->rawLog($device, '5002', '2026-07-15 09:00:00', 'in', 'processed');

        $this->runPunchUniqueKeyMigration();

        $this->assertDatabaseHas('biometric_att_logs', ['id' => $first]);
        $this->assertDatabaseMissing('biometric_att_logs', ['id' => $second]);
        $this->assertDatabaseMissing('biometric_att_logs', ['id' => $third]);
        $this->assertSame(2, DB::table(self::DUPLICATE_ARCHIVE)->count());
    }

    /**
     * Rows orphaned by a deleted device (`biometric_device_id` is nullOnDelete)
     * are exempt: NULLs never collide in a unique index on either driver, so the
     * collapse must leave them alone rather than invent a device for them.
     */
    public function test_the_collapse_leaves_device_less_rows_alone(): void
    {
        $this->dropPunchUniqueKey();

        foreach ([1, 2] as $ignored) {
            DB::table('biometric_att_logs')->insert([
                'biometric_device_id' => null,
                'serial_number' => 'SN-GONE',
                'user_pin' => '5003',
                'punch_time' => '2026-07-15 09:00:00',
                'check_type' => 'in',
                'punch_status' => 'processed',
                'occurred_at' => '2026-07-15 09:00:00',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->runPunchUniqueKeyMigration();

        $this->assertSame(2, DB::table('biometric_att_logs')->whereNull('biometric_device_id')->count());
        $this->assertSame(0, DB::table(self::DUPLICATE_ARCHIVE)->count());
    }

    /**
     * `down()` is a real rollback: the constraint goes, and every archived row
     * comes back with its original id.
     */
    public function test_the_migration_restores_archived_rows_on_rollback(): void
    {
        $device = $this->device();

        $this->dropPunchUniqueKey();

        $keeper = $this->rawLog($device, '5004', '2026-07-15 09:00:00', 'in', 'processed');
        $loser = $this->rawLog($device, '5004', '2026-07-15 09:00:00', 'in', 'failed');

        $migration = $this->punchUniqueKeyMigration();
        $migration->up();

        $this->assertDatabaseMissing('biometric_att_logs', ['id' => $loser]);

        $migration->down();

        $this->assertFalse(Schema::hasIndex('biometric_att_logs', self::PUNCH_UNIQUE_INDEX));
        $this->assertDatabaseHas('biometric_att_logs', ['id' => $keeper, 'punch_status' => 'processed']);
        $this->assertDatabaseHas('biometric_att_logs', ['id' => $loser, 'punch_status' => 'failed']);
        $this->assertFalse(Schema::hasTable(self::DUPLICATE_ARCHIVE));
    }

    // ── 1c. relinked punches with no owning session ─────────────────

    /**
     * A relinked `unknown_user` punch must actually reach `attendances`.
     *
     * The remediation flow resets such rows to `punch_status = 'downloaded'`,
     * which is the status the importer selects on — but the importer also scoped
     * rows to a download session's `created_at` window, and these rows were
     * minted by the LIVE push path, so their created_at falls inside no session
     * window. The admin saw "linked successfully" and the punch stayed staged
     * forever (12 such rows on the live system).
     *
     * Everything below the relink is the production path: no session is
     * fabricated, and the import is triggered exactly as it always is.
     */
    public function test_a_relinked_live_push_punch_with_no_session_is_still_imported(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00'));

        $device = $this->device();

        // 1. An unknown PIN punches. The live push path stages it as
        //    `unknown_user` against a soft-deleted placeholder — no session
        //    exists, and none ever will for this row.
        $this->attlogPush($device, "6001\t2026-07-15 09:00:00\t0")->assertOk();
        $this->attlogPush($device, "6001\t2026-07-15 18:00:00\t1")->assertOk();

        // The first push mints the placeholder (`unknown_user`); the second finds
        // it already there and stalls on eligibility instead — neither becomes
        // attendance, and neither belongs to a session.
        $this->assertSame(
            1,
            DB::table('biometric_att_logs')->where('punch_status', 'unknown_user')->count()
        );
        $this->assertSame(0, Attendance::count());
        $this->assertSame(0, BiometricDownloadSession::count(), 'the live push path creates no session');

        // 2. An admin links that PIN to a real employee and resets the rows.
        $user = $this->relinkPlaceholder('6001', $device);

        // 3. The sweep the importer now runs for session-less rows.
        $result = $this->service()->importSessionlessDownloadedLogs($device);

        $this->assertSame(2, $result['imported'], 'a relinked punch must not be left staged forever');
        $this->assertSame(
            0,
            DB::table('biometric_att_logs')->where('punch_status', 'downloaded')->count()
        );

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances);
        $this->assertSame('2026-07-15 09:00:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-15 18:00:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));

        // The relink changed user_id, not the punch natural key, so no row
        // collided with the new unique constraint.
        $this->assertSame(2, $this->attLogCount($device));
    }

    /**
     * The sweep also runs as the second pass of an ordinary session import, so
     * every existing trigger picks orphaned rows up without knowing they exist.
     */
    public function test_a_session_import_also_sweeps_session_less_rows_for_that_device(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00'));

        $device = $this->device();

        $this->attlogPush($device, "6002\t2026-07-15 09:00:00\t0")->assertOk();

        $user = $this->relinkPlaceholder('6002', $device);

        // A finished session for the same device whose own window captured
        // nothing — the state the console command and the finalising job import.
        $session = BiometricDownloadSession::create([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'manual',
            'status' => 'completed',
            'started_at' => now()->addMinutes(5),
            'completed_at' => now()->addMinutes(6),
        ]);

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(1, $result['imported']);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    /**
     * The sweep must not replay a batch a device is still pushing.
     *
     * An open session means more lines are on the way, and replaying half a
     * batch mis-pairs in/out punches — the same reason the console command
     * refuses to import unfinished sessions.
     */
    public function test_the_sweep_stands_down_while_a_download_is_in_progress(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00'));

        $device = $this->device();

        $this->attlogPush($device, "6003\t2026-07-15 09:00:00\t0")->assertOk();

        $user = $this->relinkPlaceholder('6003', $device);

        $this->openSession($device);

        $this->assertSame(
            ['imported' => 0, 'duplicates' => 0, 'failed' => 0, 'skipped_unknown' => 0],
            $this->service()->importSessionlessDownloadedLogs($device)
        );

        $this->assertSame(
            'downloaded',
            DB::table('biometric_att_logs')->where('user_pin', '6003')->value('punch_status'),
            'the row is deferred, never dropped'
        );
        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
    }

    /**
     * The last gap: a device that has never completed a download session has no
     * session for the importer to iterate, so `biometric:import-downloaded` had
     * nothing to hang the sweep off. It now sweeps such devices directly.
     */
    public function test_the_console_command_sweeps_a_device_with_no_session_at_all(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00'));

        $device = $this->device();

        $this->attlogPush($device, "6005\t2026-07-15 09:00:00\t0")->assertOk();

        $user = $this->relinkPlaceholder('6005', $device);

        $this->assertSame(0, BiometricDownloadSession::count());

        $this->artisan('biometric:import-downloaded')
            ->expectsOutputToContain('Total imported: 1')
            ->assertExitCode(0);

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    /**
     * Rows a session DOES own are left to that session, so the sweep cannot
     * double-import or reorder a normal download.
     */
    public function test_the_sweep_ignores_rows_a_session_window_already_covers(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 20:00:00'));

        $device = $this->device();
        $this->zonedUser('6004', $device);

        $session = $this->openSession($device);
        $this->attlogPush($device, "6004\t2026-07-15 09:00:00\t0")->assertOk();
        $session->update(['status' => 'completed', 'completed_at' => now()]);

        $this->assertSame(
            ['imported' => 0, 'duplicates' => 0, 'failed' => 0, 'skipped_unknown' => 0],
            $this->service()->importSessionlessDownloadedLogs($device)
        );

        $this->assertSame(
            'downloaded',
            DB::table('biometric_att_logs')->where('user_pin', '6004')->value('punch_status')
        );
    }

    // ── 2. handshake (matrix §3) ────────────────────────────────────

    /**
     * The keys a terminal actually needs are all still there.
     *
     * This handshake is what keeps a live device talking to us; the ATTPHOTO and
     * PushProtVer changes must be removals and substitutions, not a rewrite.
     */
    public function test_the_handshake_still_returns_what_a_device_expects(): void
    {
        $device = $this->device();

        $response = $this->get('/iclock/cdata?SN='.rawurlencode($device->serial_number)
            .'&options=all&pushver=2.4.1&language=69&DeviceType=att&PushOptionsFlag=1');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        $this->assertNotEmpty($response->headers->get('SessionID'));

        $body = $response->getContent();

        foreach ([
            "GET OPTION FROM: {$device->serial_number}",
            'ATTLOGStamp=',
            'OPERLOGStamp=9999',
            'errorDelay=30',
            'delay=10',
            'transTimes=00:00;14:05',
            'transFlag=1111000000',
            'encrypt=None',
            'ServerVer=2.4.1',
            'PushProtVer=',
        ] as $expected) {
            $this->assertStringContainsString($expected, $body, "the handshake must still emit {$expected}");
        }

        // transFlag is untouched: its digits enable the ATTLOG / OPERLOG /
        // USERINFO pushes we do consume, and the bit ordering is single-source.
        $this->assertStringContainsString('transFlag=1111000000', $body);
    }

    /**
     * We stop inviting a push we have no handler for.
     *
     * A `*Stamp` key is the per-table sync cursor that asks the device to send
     * that table. Advertising `ATTPHOTOStamp` asked the terminal to upload
     * capture photos over a shared-hosting link so we could discard them.
     */
    public function test_the_handshake_no_longer_advertises_attendance_photos(): void
    {
        $device = $this->device();

        $body = $this->service()->buildHandshakeOptionsBody($device->serial_number);

        $this->assertStringNotContainsString('ATTPHOTOStamp', $body);
        $this->assertStringContainsString('ATTLOGStamp=', $body, 'attendance is untouched');
    }

    /**
     * `PushProtVer` follows the device instead of overriding it.
     *
     * Conservative in one direction on purpose: agreeing with a terminal cannot
     * make it expect behaviour we do not implement, whereas asserting a version
     * it never claimed can.
     */
    public function test_push_prot_ver_echoes_a_version_the_device_announced(): void
    {
        $device = $this->device();

        $body = $this->service()->buildHandshakeOptionsBody($device->serial_number, '2.2.14');

        $this->assertStringContainsString('PushProtVer=2.2.14', $body);
        // ServerVer describes this server and is ours to assert unconditionally.
        $this->assertStringContainsString('ServerVer=2.4.1', $body);
    }

    /**
     * Anything that is not a plain dotted version falls back to the value that
     * is working in production — including the MB460's own
     * `Ver 2.0.33S-20220623`, which is a firmware string, not a protocol
     * version, and a CRLF injection attempt, which must never reach the wire.
     */
    public function test_push_prot_ver_falls_back_for_anything_that_is_not_a_version(): void
    {
        $device = $this->device();

        foreach ([null, '', '   ', 'Ver 2.0.33S-20220623', '2.4.1\r\nATTLOGStamp=0', 'latest', '9.9.9.9.9'] as $announced) {
            $body = $this->service()->buildHandshakeOptionsBody($device->serial_number, $announced);

            $this->assertStringContainsString(
                'PushProtVer=2.4.1',
                $body,
                'an unusable pushver must leave the handshake exactly as it is in production'
            );
        }

        // And the injection attempt never becomes extra handshake keys.
        $body = $this->service()->buildHandshakeOptionsBody($device->serial_number, "2.4.1\r\nATTLOGStamp=0");
        $this->assertStringNotContainsString('ATTLOGStamp=0', $body);
    }

    /**
     * An init handshake with no `pushver` at all — the shape the endpoint has
     * always been hit with — produces a byte-identical body to the negotiated
     * default.
     */
    public function test_a_handshake_without_pushver_is_unchanged(): void
    {
        $device = $this->device();

        $response = $this->get('/iclock/cdata?SN='.rawurlencode($device->serial_number));

        $response->assertOk();
        $this->assertSame(
            $this->service()->buildHandshakeOptionsBody($device->serial_number),
            $response->getContent()
        );
        $this->assertStringContainsString('PushProtVer=2.4.1', $response->getContent());
    }

    // ── 3. errorlog / rtlog (matrix §1) ─────────────────────────────

    public function test_errorlog_is_persisted_to_the_operation_log(): void
    {
        $device = $this->device();

        $body = "ErrCode=3\tErrMsg=sensor read failed\tDateTime=2026-07-15 09:00:00\r\n"
            ."ErrCode=8\tErrMsg=flash write error";

        $response = $this->push($device->serial_number, '&table=errorlog', $body);

        $response->assertOk();
        $this->assertSame('OK', $response->getContent(), 'a device reporting a fault must not be told to retry');

        $this->assertSame(
            2,
            DB::table('biometric_oper_logs')
                ->where('biometric_device_id', $device->id)
                ->where('operation_type', BiometricProcessingService::ERRORLOG_OPERATION_TYPE)
                ->count()
        );

        $first = DB::table('biometric_oper_logs')
            ->where('biometric_device_id', $device->id)
            ->orderBy('id')
            ->first();

        $this->assertStringContainsString('sensor read failed', $first->raw_data);
        $this->assertSame('sensor read failed', json_decode($first->context, true)['ErrMsg']);

        // A fault report is never attendance.
        $this->assertSame(0, DB::table('biometric_att_logs')->count());
    }

    /**
     * `rtlog` mirrors punches ATTLOG already delivers, so it stays logged and
     * dropped: persisting it would duplicate attendance data at the highest
     * volume on the protocol for no information gain.
     */
    public function test_rtlog_is_accepted_but_stored_nowhere(): void
    {
        $device = $this->device();

        $response = $this->push($device->serial_number, '&table=rtlog', "4309\t2026-07-15 09:00:00\t0");

        $response->assertOk();
        $this->assertSame('OK', $response->getContent());

        $this->assertSame(0, DB::table('biometric_att_logs')->count());
        $this->assertSame(0, DB::table('biometric_oper_logs')->count());
    }

    /**
     * The volume guard: a terminal stuck in a fault loop cannot fill the
     * database from one request.
     */
    public function test_an_oversized_errorlog_push_is_capped(): void
    {
        $device = $this->device();

        $lines = [];
        for ($i = 0; $i < BiometricProcessingService::ERRORLOG_MAX_LINES + 25; $i++) {
            $lines[] = "ErrCode=3\tErrMsg=fault {$i}";
        }

        $stored = $this->service()->storeErrorLog(implode("\n", $lines), $device->serial_number, $device);

        $this->assertSame(BiometricProcessingService::ERRORLOG_MAX_LINES, $stored);
        $this->assertSame(
            BiometricProcessingService::ERRORLOG_MAX_LINES,
            DB::table('biometric_oper_logs')->count()
        );
    }

    // ── helpers for the migration rehearsal ─────────────────────────

    /**
     * Recreate the pre-migration schema: no unique key, no archive table.
     */
    private function dropPunchUniqueKey(): void
    {
        if (Schema::hasIndex('biometric_att_logs', self::PUNCH_UNIQUE_INDEX)) {
            Schema::table('biometric_att_logs', function (Blueprint $table) {
                $table->dropUnique(self::PUNCH_UNIQUE_INDEX);
            });
        }

        Schema::dropIfExists(self::DUPLICATE_ARCHIVE);
    }

    private function punchUniqueKeyMigration(): object
    {
        return require database_path(
            'migrations/2026_08_03_000001_add_punch_natural_key_unique_to_biometric_att_logs_table.php'
        );
    }

    private function runPunchUniqueKeyMigration(): void
    {
        $this->punchUniqueKeyMigration()->up();
    }

    /**
     * A staged row written exactly the way the capture path writes one, but with
     * no idempotency — i.e. the pre-fix behaviour this migration cleans up after.
     */
    private function rawLog(
        BiometricDevice $device,
        string $pin,
        string $punchTime,
        string $checkType,
        string $status
    ): int {
        return DB::table('biometric_att_logs')->insertGetId([
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => $pin,
            'punch_time' => $punchTime,
            'check_type' => $checkType,
            'punch_status' => $status,
            'punch_status_reason' => 'staged by test',
            'raw_data' => $pin."\t".$punchTime,
            'context' => json_encode([]),
            'occurred_at' => $punchTime,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
