<?php

namespace Tests\Feature\Biometric;

use App\Jobs\ExportAttendanceReport;
use App\Jobs\ProcessBiometricDownloadSession;
use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\BiometricAttLog;
use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDeviceCommand;
use App\Models\HRM\BiometricDownloadSession;
use App\Models\User;
use App\Services\Attendance\AttendanceReportService;
use App\Services\Biometric\BiometricProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Tests\TestCase;

/**
 * The biometric sync engine: the wiring that gets device punches all the way into
 * `attendances` without anyone pressing a button.
 *
 * Capture and import are two separate halves — the ADMS push only parks lines in
 * biometric_att_logs as `downloaded` (see BiometricDownloadedImportTest for the
 * import rules themselves). These tests pin the automation around them:
 *   - the scheduled download asks the device for a BOUNDED window, because the
 *     unbounded default re-pushes the entire device history on every run;
 *   - a manual download is still unbounded, so a backfill still works;
 *   - the download job drains its own session the moment it completes;
 *   - an export drains anything still staged before compiling the timesheet;
 *   - and a per-session lock keeps those three triggers from racing each other.
 */
class BiometricSyncEngineTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── fixtures (mirrors BiometricDownloadedImportTest) ─────────────

    private function biometricType(): AttendanceType
    {
        // Seeded by 2026_05_12_000003_seed_biometric_attendance_type.
        return AttendanceType::where('slug', 'biometric')->firstOrFail();
    }

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'SN-'.uniqid(),
            'protocol' => 'adms',
            'is_active' => true,
        ], $overrides));
    }

    private function zonedUser(string $employeeId, BiometricDevice $device): User
    {
        $type = $this->biometricType();
        $type->biometricDevices()->syncWithoutDetaching([$device->id]);

        return User::factory()->create([
            'employee_id' => $employeeId,
            'attendance_type_id' => $type->id,
        ]);
    }

    /**
     * A finished session, with the real lifecycle ordering: the session row is
     * created first, the device then starts pushing, and only after that do
     * captured rows exist. (processAttendanceLogs() only captures while a session
     * is pending/in_progress, so a captured row's created_at is always >= its
     * session's created_at — the pre-export scoping query relies on that.)
     */
    private function completedSession(BiometricDevice $device): BiometricDownloadSession
    {
        $session = BiometricDownloadSession::create([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'manual',
            'status' => 'completed',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);

        $session->forceFill(['created_at' => now()->subMinutes(3)])->saveQuietly();

        return $session->refresh();
    }

    /**
     * Insert a captured row exactly the way the $isDownloading branch of
     * processAttendanceLogs() does.
     */
    private function downloadedLog(BiometricDevice $device, string $pin, string $punchTime, string $checkType = 'in'): int
    {
        return DB::table('biometric_att_logs')->insertGetId([
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => $pin,
            'user_id' => User::withTrashed()->where('employee_id', $pin)->value('id'),
            'punch_time' => $punchTime,
            'check_type' => $checkType,
            'punch_status' => 'downloaded',
            'punch_status_reason' => 'Downloaded via active sync session',
            'raw_data' => $pin."\t".$punchTime,
            'context' => json_encode([]),
            'occurred_at' => $punchTime,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);
    }

    private function service(): BiometricProcessingService
    {
        return app(BiometricProcessingService::class);
    }

    // ── Decision 1: the scheduled window is bounded ──────────────────

    public function test_scheduled_download_asks_the_device_for_a_bounded_three_day_window(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));
        Queue::fake();

        $device = $this->device();

        $this->artisan('biometric:scheduled-log-download')->assertExitCode(0);

        $command = BiometricDeviceCommand::where('biometric_device_id', $device->id)
            ->where('command_type', 'CHECK_ATTLOG')
            ->firstOrFail();

        $this->assertSame('2026-06-16 20:00:00', $command->payload['start_time']);
        $this->assertSame('2026-06-20 20:00:00', $command->payload['end_time']);

        $adms = $command->toAdmsString();
        $this->assertStringContainsString('StartTime=2026-06-16 20:00:00', $adms);
        $this->assertStringContainsString('EndTime=2026-06-20 20:00:00', $adms);
        // The whole point: never the "re-push your entire history" default.
        $this->assertStringNotContainsString('2000-01-01', $adms);

        // The window is tunable without touching code.
        BiometricDeviceCommand::query()->delete();
        BiometricDownloadSession::query()->delete();
        $device->update(['last_log_download_at' => null]);

        $this->artisan('biometric:scheduled-log-download', ['--days' => 7])->assertExitCode(0);

        $weekly = BiometricDeviceCommand::where('command_type', 'CHECK_ATTLOG')->firstOrFail();
        $this->assertSame('2026-06-12 20:00:00', $weekly->payload['start_time']);
    }

    public function test_manual_download_still_requests_the_full_unbounded_history(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();

        $session = $this->service()->initiateLogDownload($device, 'manual');

        $command = $session->command;
        $this->assertNotNull($command);
        $this->assertNull($command->payload);

        $adms = $command->toAdmsString();
        $this->assertStringContainsString('StartTime=2000-01-01 00:00:00', $adms);
        $this->assertStringContainsString('EndTime=2026-06-20 20:00:00', $adms);
    }

    public function test_hours_guard_skips_a_device_that_synced_recently(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));
        Queue::fake();

        $recent = $this->device([
            'name' => 'Recently synced',
            'last_log_download_at' => now()->subMinutes(20),
        ]);
        $stale = $this->device([
            'name' => 'Stale',
            'last_log_download_at' => now()->subHours(3),
        ]);

        $this->artisan('biometric:scheduled-log-download', ['--hours' => 1])
            ->expectsOutputToContain('Skipping device Recently synced')
            ->assertExitCode(0);

        $this->assertSame(0, BiometricDeviceCommand::where('biometric_device_id', $recent->id)->count());
        $this->assertSame(1, BiometricDeviceCommand::where('biometric_device_id', $stale->id)->count());
        $this->assertSame(0, BiometricDownloadSession::where('biometric_device_id', $recent->id)->count());
    }

    // ── Decision 3: the job imports when the session completes ───────

    public function test_download_job_imports_staged_rows_once_the_session_completes(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('5101', $device);

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'CHECK_ATTLOG',
            'status' => 'executed',
        ]);

        $session = BiometricDownloadSession::create([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'scheduled',
            'status' => 'in_progress',
            'command_id' => $command->id,
            'started_at' => now()->subMinutes(2),
        ]);

        $inLog = $this->downloadedLog($device, '5101', '2026-06-19 08:00:00', 'in');
        $outLog = $this->downloadedLog($device, '5101', '2026-06-19 17:30:00', 'out');

        (new ProcessBiometricDownloadSession($session))->handle($this->service());

        $this->assertSame('completed', $session->fresh()->status);
        $this->assertSame('processed', BiometricAttLog::find($inLog)->punch_status);
        $this->assertSame('processed', BiometricAttLog::find($outLog)->punch_status);

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances);
        $this->assertSame('2026-06-19 08:00:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-19 17:30:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));
    }

    public function test_download_job_does_not_import_while_the_command_is_still_pending(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('5102', $device);

        $command = BiometricDeviceCommand::create([
            'biometric_device_id' => $device->id,
            'command_type' => 'CHECK_ATTLOG',
            'status' => 'pending',
        ]);

        $session = BiometricDownloadSession::create([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'scheduled',
            'status' => 'pending',
            'command_id' => $command->id,
            'started_at' => now()->subMinutes(2),
        ]);

        $logId = $this->downloadedLog($device, '5102', '2026-06-19 08:00:00', 'in');

        // The device has not picked the command up yet; the job releases itself.
        // Importing here would replay a half-received batch.
        (new ProcessBiometricDownloadSession($session))->handle($this->service());

        $this->assertSame('pending', $session->fresh()->status);
        $this->assertSame('downloaded', BiometricAttLog::find($logId)->punch_status);
        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
    }

    // ── Decision 4: exports drain what is still staged ───────────────

    public function test_export_imports_pending_rows_before_compiling_the_report(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));
        Storage::fake('public');
        Excel::fake();

        $device = $this->device();
        $user = $this->zonedUser('5103', $device);
        $session = $this->completedSession($device);

        $logId = $this->downloadedLog($device, '5103', '2026-06-19 08:00:00', 'in');

        $admin = User::factory()->create();

        $job = new ExportAttendanceReport('daily_excel', '2026-06-19', null, $admin->id, 'timesheet.xlsx');
        $job->handle(app(AttendanceReportService::class));

        $this->assertSame('processed', BiometricAttLog::find($logId)->punch_status);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertNotNull($session->fresh());
    }

    public function test_export_pre_sync_costs_one_query_and_imports_nothing_when_nothing_is_staged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));
        Storage::fake('public');
        Excel::fake();

        $device = $this->device();
        $this->zonedUser('5104', $device);
        // A finished session exists, but every row it captured was already imported.
        $this->completedSession($device);
        $logId = $this->downloadedLog($device, '5104', '2026-06-19 08:00:00', 'in');
        DB::table('biometric_att_logs')->where('id', $logId)->update(['punch_status' => 'processed']);

        $admin = User::factory()->create();
        $attendanceCountBefore = Attendance::count();

        $queries = [];
        DB::listen(function ($query) use (&$queries) {
            $queries[] = $query->sql;
        });

        $job = new ExportAttendanceReport('daily_excel', '2026-06-19', null, $admin->id, 'timesheet.xlsx');
        $job->handle(app(AttendanceReportService::class));

        $sessionLookups = array_values(array_filter(
            $queries,
            fn ($sql) => str_contains($sql, 'biometric_download_sessions')
        ));

        // One query to find sessions with staged rows — and that is the whole cost.
        $this->assertCount(1, $sessionLookups, 'Pre-export sync must be a single scoping query, not one per session.');
        $this->assertStringContainsString('exists', strtolower($sessionLookups[0]));

        // Nothing was imported: the importer never selected or rewrote a log row.
        $importerReads = array_filter(
            $queries,
            fn ($sql) => str_starts_with($sql, 'select * from "biometric_att_logs"')
        );
        $this->assertCount(0, $importerReads);

        $attLogWrites = array_filter(
            $queries,
            fn ($sql) => str_starts_with($sql, 'update "biometric_att_logs"')
        );
        $this->assertCount(0, $attLogWrites);

        $this->assertSame($attendanceCountBefore, Attendance::count());
    }

    // ── Decision 5: the per-session lock ─────────────────────────────

    public function test_a_second_concurrent_importer_no_ops_while_the_session_lock_is_held(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('5105', $device);
        $session = $this->completedSession($device);

        $logId = $this->downloadedLog($device, '5105', '2026-06-19 08:00:00', 'in');

        // Stand in for the importer that got there first.
        $lock = Cache::lock(BiometricProcessingService::importLockKey($session), 60);
        $this->assertTrue($lock->get());

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(
            ['imported' => 0, 'duplicates' => 0, 'failed' => 0, 'skipped_unknown' => 0],
            $result
        );
        $this->assertSame('downloaded', BiometricAttLog::find($logId)->punch_status);
        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());

        // Once the first importer is done the row is still there to be picked up —
        // the lock defers work, it never drops it.
        $lock->release();

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(1, $result['imported']);
        $this->assertSame('processed', BiometricAttLog::find($logId)->punch_status);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }
}
