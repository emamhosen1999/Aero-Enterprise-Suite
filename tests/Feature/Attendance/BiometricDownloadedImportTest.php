<?php

namespace Tests\Feature\Attendance;

use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\BiometricAttLog;
use App\Models\HRM\BiometricDevice;
use App\Models\HRM\BiometricDownloadSession;
use App\Models\User;
use App\Services\Biometric\BiometricProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * "Download Attendance Logs" only CAPTURES device logs: the ADMS push path parks
 * every line in biometric_att_logs with punch_status = 'downloaded' and skips user
 * resolution, eligibility, dedupe and the punch service entirely. Nothing ever
 * promoted those rows into attendance.
 *
 * BiometricProcessingService::importDownloadedLogs() is the missing second step.
 * These tests pin its contract: the live push rules are reused, the import is
 * idempotent, and rows replay in punch_time order (in/out pairing is order-sensitive).
 */
class BiometricDownloadedImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────

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

    /**
     * A user who may punch biometrically, plus a device inside that attendance
     * zone — both are required or validateAttendanceEligibility() rejects the punch.
     */
    private function zonedUser(string $employeeId, BiometricDevice $device): User
    {
        $type = $this->biometricType();
        $type->biometricDevices()->syncWithoutDetaching([$device->id]);

        return User::factory()->create([
            'employee_id' => $employeeId,
            'attendance_type_id' => $type->id,
        ]);
    }

    private function completedSession(BiometricDevice $device): BiometricDownloadSession
    {
        return BiometricDownloadSession::create([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'manual',
            'status' => 'completed',
            'started_at' => now()->subMinutes(2),
            'completed_at' => now(),
        ]);
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

    // ── tests ───────────────────────────────────────────────────────

    public function test_downloaded_rows_become_processed_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('4201', $device);
        $session = $this->completedSession($device);

        $inLog = $this->downloadedLog($device, '4201', '2026-06-19 08:00:00', 'in');
        $outLog = $this->downloadedLog($device, '4201', '2026-06-19 17:30:00', 'out');

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(
            ['imported' => 2, 'duplicates' => 0, 'failed' => 0, 'skipped_unknown' => 0],
            $result
        );

        $this->assertSame('processed', BiometricAttLog::find($inLog)->punch_status);
        $this->assertSame('processed', BiometricAttLog::find($outLog)->punch_status);

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances);
        $this->assertSame('2026-06-19 08:00:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-19 17:30:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));
    }

    public function test_import_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('4202', $device);
        $session = $this->completedSession($device);

        $inLog = $this->downloadedLog($device, '4202', '2026-06-19 08:00:00', 'in');
        $this->downloadedLog($device, '4202', '2026-06-19 17:30:00', 'out');

        $first = $this->service()->importDownloadedLogs($session);
        $this->assertSame(2, $first['imported']);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());

        // Second run: nothing is left on `downloaded`, so nothing is selected.
        $second = $this->service()->importDownloadedLogs($session);
        $this->assertSame(
            ['imported' => 0, 'duplicates' => 0, 'failed' => 0, 'skipped_unknown' => 0],
            $second
        );
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());

        // Backstop: even if a row is somehow re-captured as `downloaded`, the
        // duplicate check stops it from creating a second attendance record.
        DB::table('biometric_att_logs')->where('id', $inLog)->update(['punch_status' => 'downloaded']);

        $third = $this->service()->importDownloadedLogs($session);
        $this->assertSame(0, $third['imported']);
        $this->assertSame(1, $third['duplicates']);
        $this->assertSame('duplicate', BiometricAttLog::find($inLog)->punch_status);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    public function test_unknown_pin_is_marked_unknown_user_and_creates_no_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        // Zone the device against the biometric type so the ONLY thing wrong is the PIN.
        $this->zonedUser('4203', $device);
        $session = $this->completedSession($device);

        $logId = $this->downloadedLog($device, '999999', '2026-06-19 09:15:00', 'in');

        $attendanceCountBefore = Attendance::count();

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(1, $result['skipped_unknown']);
        $this->assertSame(0, $result['imported']);
        $this->assertSame(0, $result['failed']);

        $log = BiometricAttLog::find($logId);
        $this->assertSame('unknown_user', $log->punch_status);
        $this->assertNotNull($log->punch_status_reason);

        $this->assertSame($attendanceCountBefore, Attendance::count());
        $placeholder = User::withTrashed()->where('employee_id', '999999')->first();
        $this->assertNotNull($placeholder);
        $this->assertSame(0, Attendance::where('user_id', $placeholder->id)->count());
    }

    public function test_device_outside_the_users_zone_is_marked_wrong_device(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $zonedDevice = $this->device(['name' => 'HQ Gate']);
        $user = $this->zonedUser('4204', $zonedDevice);

        // A second device that is NOT linked to the user's attendance zone.
        $foreignDevice = $this->device(['name' => 'Warehouse Gate']);
        $session = $this->completedSession($foreignDevice);

        $logId = $this->downloadedLog($foreignDevice, '4204', '2026-06-19 08:05:00', 'in');

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(0, $result['imported']);
        $this->assertSame(1, $result['failed']);

        $log = BiometricAttLog::find($logId);
        $this->assertSame('wrong_device', $log->punch_status);
        $this->assertSame('Device not in attendance zone', $log->punch_status_reason);

        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
    }

    public function test_console_command_imports_a_session(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('4206', $device);
        $session = $this->completedSession($device);

        $this->downloadedLog($device, '4206', '2026-06-19 08:00:00', 'in');
        $this->downloadedLog($device, '4206', '2026-06-19 17:00:00', 'out');

        $this->artisan('biometric:import-downloaded', ['session' => $session->id])
            ->expectsOutputToContain('imported: 2')
            ->assertExitCode(0);

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());

        // Unknown session id is an operator error, not a silent no-op.
        $this->artisan('biometric:import-downloaded', ['session' => 999999])
            ->assertExitCode(1);
    }

    public function test_rows_are_replayed_in_punch_time_order(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-19 20:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('4205', $device);
        $session = $this->completedSession($device);

        // Deliberately inserted OUT of chronological order. Replayed by insertion
        // order the 14:00 out-punch would have nothing open to close and fail.
        $this->downloadedLog($device, '4205', '2026-06-19 14:00:00', 'out');
        $this->downloadedLog($device, '4205', '2026-06-19 18:00:00', 'out');
        $this->downloadedLog($device, '4205', '2026-06-19 09:00:00', 'in');
        $this->downloadedLog($device, '4205', '2026-06-19 15:00:00', 'in');

        $result = $this->service()->importDownloadedLogs($session);

        $this->assertSame(4, $result['imported']);
        $this->assertSame(0, $result['failed']);

        $attendances = Attendance::where('user_id', $user->id)->orderBy('punchin')->get();
        $this->assertCount(2, $attendances);

        $this->assertSame('2026-06-19 09:00:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-19 14:00:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-19 15:00:00', Carbon::parse($attendances[1]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-06-19 18:00:00', Carbon::parse($attendances[1]->punchout)->format('Y-m-d H:i:s'));

        $this->assertSame(
            0,
            BiometricAttLog::where('biometric_device_id', $device->id)->where('punch_status', 'downloaded')->count()
        );
    }
}
