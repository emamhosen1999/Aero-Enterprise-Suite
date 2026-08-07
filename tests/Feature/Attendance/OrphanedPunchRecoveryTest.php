<?php

namespace Tests\Feature\Attendance;

use App\Models\HRM\Attendance;
use App\Models\HRM\AttendanceAuditLog;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\BiometricAttLog;
use App\Models\HRM\BiometricDevice;
use App\Models\User;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Biometric\BiometricProcessingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A ZKTeco terminal stamps each punch with a direction byte (0 = in, 1 = out)
 * that is simply whatever mode the terminal is sitting in. Left in OUT mode, it
 * sends the day's FIRST punch as a check-out; that punch finds nothing open to
 * close and the employee's entire day never reaches `attendances`.
 *
 * Measured on the production MB460 (`AF6P231260266`) across its complete 1,054
 * record history: 540 device user-days produced 33 with no attendance (6.1%), 22
 * of them exactly this. On 2026-07-11 three employees (PINs 307, 302, 304) had an
 * OUT-first day at once — one terminal in the wrong mode, not three mistakes.
 *
 * These tests pin BOTH halves of the fix, and the second half matters more than
 * the first: the promotion must fire on a genuinely orphaned check-out and must
 * NOT fire on a real one. Every "does not promote" test below is a guard against
 * this change becoming a worse data-integrity bug than the one it fixes.
 */
class OrphanedPunchRecoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────

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
        $type = AttendanceType::where('slug', 'biometric')->firstOrFail();
        $type->biometricDevices()->syncWithoutDetaching([$device->id]);

        return User::factory()->create([
            'employee_id' => $employeeId,
            'attendance_type_id' => $type->id,
        ]);
    }

    /** An employee configured for WiFi/IP attendance — policy, not a defect. */
    private function wifiUser(string $employeeId): User
    {
        $type = AttendanceType::create([
            'name' => 'WiFi IP 3',
            'slug' => 'wifi_ip_3',
            'is_active' => true,
            'priority' => 5,
            'config' => ['validation_mode' => 'any'],
            'required_permissions' => [],
        ]);

        return User::factory()->create([
            'employee_id' => $employeeId,
            'attendance_type_id' => $type->id,
        ]);
    }

    /** A device punch, exactly as BiometricProcessingService builds it. */
    private function devicePunch(string $checkType, string $punchTime): Request
    {
        return Request::create('/biometric/punch', 'POST', [
            'device_serial' => 'SN-TEST',
            'device_user_id' => '307',
            'source' => 'biometric',
            'punch_time' => $punchTime,
            'check_type' => $checkType,
        ]);
    }

    private function punchService(): AttendancePunchService
    {
        return app(AttendancePunchService::class);
    }

    /** A captured ATTLOG row that was rejected — the shape the replay selects. */
    private function failedLog(BiometricDevice $device, string $pin, string $punchTime, string $checkType, string $reason): int
    {
        return DB::table('biometric_att_logs')->insertGetId([
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => $pin,
            'user_id' => User::withTrashed()->where('employee_id', $pin)->value('id'),
            'punch_time' => $punchTime,
            'check_type' => $checkType,
            'punch_status' => 'failed',
            'punch_status_reason' => $reason,
            'raw_data' => $pin."\t".$punchTime."\t".($checkType === 'out' ? '1' : '0'),
            'context' => json_encode([]),
            'occurred_at' => $punchTime,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);
    }

    // ── the defect: an OUT-first day must produce attendance ────────

    public function test_out_first_day_produces_attendance(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 11:05:00'));
        $user = User::factory()->create();

        $result = $this->punchService()->processPunch($user, $this->devicePunch('out', '2026-07-11 11:03:32'));

        $this->assertSame('success', $result['status']);
        $this->assertSame('punch_in', $result['action']);
        $this->assertSame(AttendancePunchService::RECOVERY_OUT_FIRST, $result['recovery']);

        $attendance = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-07-11 11:03:32', Carbon::parse($attendance->punchin)->format('Y-m-d H:i:s'));
        $this->assertNull($attendance->punchout);
        $this->assertSame('2026-07-11', Carbon::parse($attendance->date)->format('Y-m-d'));
    }

    public function test_two_out_punches_become_in_and_out_not_two_check_ins(): void
    {
        // The verbatim production pair for PIN 307 on 2026-07-11: both punches
        // carried status=1.
        Carbon::setTestNow(Carbon::parse('2026-07-11 19:10:00'));
        $user = User::factory()->create();

        $first = $this->punchService()->processPunch($user, $this->devicePunch('out', '2026-07-11 11:03:32'));
        $second = $this->punchService()->processPunch($user, $this->devicePunch('out', '2026-07-11 19:08:17'));

        $this->assertSame('punch_in', $first['action']);
        $this->assertSame('punch_out', $second['action']);

        // The second OUT is a genuine close, so it must NOT be recovered.
        $this->assertArrayNotHasKey('recovery', $second);

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances, 'An OUT-first day must produce ONE row, not two check-ins.');
        $this->assertSame('2026-07-11 11:03:32', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-11 19:08:17', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));
    }

    // ── the boundary: promotion must not misfire ────────────────────

    public function test_a_genuine_check_out_still_closes_its_open_record(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-13 17:05:00'));
        $user = User::factory()->create();

        $in = $this->punchService()->processPunch($user, $this->devicePunch('in', '2026-07-13 09:00:00'));
        $out = $this->punchService()->processPunch($user, $this->devicePunch('out', '2026-07-13 17:00:00'));

        $this->assertSame('punch_in', $in['action']);
        $this->assertSame('punch_out', $out['action']);
        $this->assertArrayNotHasKey('recovery', $out);
        $this->assertSame($in['attendance_id'], $out['attendance_id'], 'The out punch must close the row the in punch opened.');

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances);
        $this->assertSame('2026-07-13 09:00:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-13 17:00:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));

        // No recovery was applied, so nothing may claim one.
        $this->assertSame(0, AttendanceAuditLog::where('action', AttendancePunchService::RECOVERY_AUDIT_ACTION)->count());
    }

    public function test_a_stray_out_punch_after_a_complete_day_is_still_rejected(): void
    {
        // The single most dangerous misfire: the day is finished, so a further out
        // punch must NOT open a phantom second attendance record.
        Carbon::setTestNow(Carbon::parse('2026-07-13 19:05:00'));
        $user = User::factory()->create();

        $this->punchService()->processPunch($user, $this->devicePunch('in', '2026-07-13 09:00:00'));
        $this->punchService()->processPunch($user, $this->devicePunch('out', '2026-07-13 17:00:00'));

        $stray = $this->punchService()->processPunch($user, $this->devicePunch('out', '2026-07-13 19:00:00'));

        $this->assertSame('error', $stray['status']);
        $this->assertSame(AttendancePunchService::NO_OPEN_RECORD_MESSAGE, $stray['message']);
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
    }

    public function test_break_out_and_ot_out_are_never_promoted(): void
    {
        // Only a plain check-out is the terminal's IN/OUT mode. A break/OT punch
        // describes an interruption to a day already under way; promoting one
        // would invent a workday rather than recover one.
        Carbon::setTestNow(Carbon::parse('2026-07-14 13:05:00'));

        foreach (['break_out', 'ot_out'] as $checkType) {
            $user = User::factory()->create();

            $result = $this->punchService()->processPunch($user, $this->devicePunch($checkType, '2026-07-14 13:00:00'));

            $this->assertSame('error', $result['status'], $checkType.' must not be promoted');
            $this->assertSame(AttendancePunchService::NO_OPEN_RECORD_MESSAGE, $result['message']);
            $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
        }
    }

    public function test_web_and_manual_punches_are_unaffected(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-15 17:00:00'));

        // A web punch carries no `source` — GuardsServerAuthoritativePunchTime
        // strips it — so it can never reach the promotion rule.
        $web = User::factory()->create();
        $webResult = $this->punchService()->processPunch($web, new Request(['check_type' => 'out']));

        $this->assertSame('error', $webResult['status']);
        $this->assertSame(AttendancePunchService::NO_OPEN_RECORD_MESSAGE, $webResult['message']);
        $this->assertSame(0, Attendance::where('user_id', $web->id)->count());

        // Even if a request forges `source`, the offline sync channel is excluded.
        $sync = User::factory()->create();
        $syncRequest = $this->devicePunch('out', '2026-07-15 16:00:00');
        $syncRequest->attributes->set('sync_capture', true);

        $syncResult = $this->punchService()->processPunch($sync, $syncRequest);

        $this->assertSame('error', $syncResult['status']);
        $this->assertSame(AttendancePunchService::NO_OPEN_RECORD_MESSAGE, $syncResult['message']);
        $this->assertSame(0, Attendance::where('user_id', $sync->id)->count());
    }

    public function test_a_normal_in_then_out_day_is_byte_for_byte_unchanged(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-16 18:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('4310', $device);
        $service = app(BiometricProcessingService::class);

        $service->processAttendanceLogs("4310\t2026-07-16 09:00:00\t0", $device, $device->serial_number);
        $service->processAttendanceLogs("4310\t2026-07-16 17:30:00\t1", $device, $device->serial_number);

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances);
        $this->assertSame('2026-07-16 09:00:00', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-16 17:30:00', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));

        // Both ATTLOG rows processed, with NO recovery note — a normally paired
        // day must stay indistinguishable from what it was before this change.
        $logs = BiometricAttLog::where('user_pin', '4310')->orderBy('punch_time')->get();
        $this->assertCount(2, $logs);
        foreach ($logs as $log) {
            $this->assertSame('processed', $log->punch_status);
            $this->assertNull($log->punch_status_reason);
        }

        $this->assertSame(0, AttendanceAuditLog::where('action', AttendancePunchService::RECOVERY_AUDIT_ACTION)->count());
    }

    // ── audit: the raw device account survives, the recovery is recorded ──

    public function test_recovery_preserves_the_raw_check_type_and_is_audited(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-07-11 11:05:00'));

        $device = $this->device();
        $user = $this->zonedUser('307', $device);

        // status=1 on the wire — the device's own account of an OUT punch.
        app(BiometricProcessingService::class)
            ->processAttendanceLogs("307\t2026-07-11 11:03:32\t1", $device, $device->serial_number);

        $attendance = Attendance::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('2026-07-11 11:03:32', Carbon::parse($attendance->punchin)->format('Y-m-d H:i:s'));

        // The ATTLOG row still says what the terminal said. It is also part of the
        // punch natural key made UNIQUE by migration 2026_08_03_000001, so moving
        // it would let a re-pushed punch slip past that constraint.
        $log = BiometricAttLog::where('user_pin', '307')->firstOrFail();
        $this->assertSame('out', $log->check_type);
        $this->assertSame('2026-07-11 11:03:32', $log->punch_time->format('Y-m-d H:i:s'));

        // …and records that a recovery was applied, and why.
        $this->assertSame('processed', $log->punch_status);
        $this->assertSame(AttendancePunchService::RECOVERY_REASON, $log->punch_status_reason);

        // The authoritative trail, written on every ingest path.
        $audit = AttendanceAuditLog::where('action', AttendancePunchService::RECOVERY_AUDIT_ACTION)->firstOrFail();
        $this->assertSame($attendance->id, $audit->attendance_id);
        $this->assertNull($audit->actor_id, 'No human decided this; the ingest rules did.');
        $this->assertSame(AttendancePunchService::RECOVERY_REASON, $audit->reason);
        $this->assertSame('out', $audit->after['device_check_type']);
        $this->assertSame('in', $audit->after['recorded_as']);
        $this->assertSame('2026-07-11 11:03:32', $audit->after['punchin']);
    }

    // ── the replay command ──────────────────────────────────────────

    public function test_dry_run_is_the_default_and_writes_nothing(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('302', $device);

        $this->failedLog($device, '302', '2026-07-11 10:15:00', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);
        $this->failedLog($device, '302', '2026-07-11 18:40:00', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);

        $this->artisan('biometric:replay-orphaned-punches')
            ->expectsOutputToContain('DRY RUN (default)')
            ->expectsOutputToContain('RECOVERABLE')
            ->expectsOutputToContain('1 user-day(s) would GAIN attendance')
            ->expectsOutputToContain('in 10:15 → out 18:40')
            ->expectsOutputToContain('nothing was written')
            ->assertExitCode(0);

        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
        $this->assertSame(2, BiometricAttLog::where('punch_status', 'failed')->count());
    }

    public function test_apply_recovers_the_user_day_and_is_idempotent(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('307', $device);

        $this->failedLog($device, '307', '2026-07-11 11:03:32', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);
        $this->failedLog($device, '307', '2026-07-11 19:08:17', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);

        $this->artisan('biometric:replay-orphaned-punches --apply')->assertExitCode(0);

        $attendances = Attendance::where('user_id', $user->id)->get();
        $this->assertCount(1, $attendances, 'Two OUT punches must become one in+out day.');
        $this->assertSame('2026-07-11 11:03:32', Carbon::parse($attendances[0]->punchin)->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-11 19:08:17', Carbon::parse($attendances[0]->punchout)->format('Y-m-d H:i:s'));

        $this->assertSame(2, BiometricAttLog::where('punch_status', 'processed')->count());
        $this->assertSame(0, BiometricAttLog::where('punch_status', 'failed')->count());

        // Replaying twice must not double-create.
        $this->artisan('biometric:replay-orphaned-punches --apply')
            ->expectsOutputToContain('Nothing to replay')
            ->assertExitCode(0);

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertSame(
            '2026-07-11 19:08:17',
            Carbon::parse(Attendance::where('user_id', $user->id)->first()->punchout)->format('Y-m-d H:i:s')
        );

        // Backstop: even if a processed row is forced back to `failed`, the
        // duplicate check stops it from creating a second attendance record.
        DB::table('biometric_att_logs')
            ->where('user_pin', '307')
            ->update(['punch_status' => 'failed', 'punch_status_reason' => AttendancePunchService::NO_OPEN_RECORD_MESSAGE]);

        $this->artisan('biometric:replay-orphaned-punches --apply')->assertExitCode(0);

        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertSame(2, BiometricAttLog::where('punch_status', 'duplicate')->count());
    }

    public function test_attendance_type_failures_are_reported_but_never_forced(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));

        $device = $this->device();
        $wifi = $this->wifiUser('154');
        $none = User::factory()->create(['employee_id' => '155', 'attendance_type_id' => null]);

        $this->failedLog($device, '154', '2026-07-20 09:12:00', 'in', BiometricProcessingService::REASON_NOT_BIOMETRIC_PREFIX.'wifi_ip_3');
        $this->failedLog($device, '155', '2026-07-20 09:14:00', 'in', BiometricProcessingService::REASON_NO_ATTENDANCE_TYPE);

        // One expectation per OUTPUT LINE: expectsOutputToContain() matches each
        // expectation against a separate write and consumes it, so two substrings
        // of the same line can never both be satisfied. Asserting the whole header
        // contiguously is the stronger check anyway.
        $this->artisan('biometric:replay-orphaned-punches --apply')
            ->expectsOutputToContain('NEEDS CONFIGURATION REVIEW — reported, NOT replayed')
            ->expectsOutputToContain('wifi_ip_3')
            ->expectsOutputToContain(BiometricProcessingService::REASON_NO_ATTENDANCE_TYPE)
            ->expectsOutputToContain('Nothing to replay')
            ->assertExitCode(0);

        // The policy stands: no attendance is manufactured for either employee,
        // and their rows are left exactly as they were.
        $this->assertSame(0, Attendance::where('user_id', $wifi->id)->count());
        $this->assertSame(0, Attendance::where('user_id', $none->id)->count());
        $this->assertSame(2, BiometricAttLog::where('punch_status', 'failed')->count());
    }

    public function test_a_recoverable_row_is_not_forced_when_configuration_has_since_changed(): void
    {
        // The row was rejected for the recoverable reason, but the employee has
        // since been moved to WiFi attendance. Replaying against the stale reason
        // would override the CURRENT policy, so it must be reported instead.
        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));

        $device = $this->device();
        $user = $this->wifiUser('120');

        $this->failedLog($device, '120', '2026-07-22 10:00:00', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);

        $this->artisan('biometric:replay-orphaned-punches --apply')
            ->expectsOutputToContain('NEEDS CONFIGURATION REVIEW')
            ->expectsOutputToContain('Nothing to replay')
            ->assertExitCode(0);

        $this->assertSame(0, Attendance::where('user_id', $user->id)->count());
    }

    public function test_already_punched_in_failures_are_reported_as_needing_no_action(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));

        $device = $this->device();
        $user = $this->zonedUser('304', $device);

        Attendance::create([
            'user_id' => $user->id,
            'date' => '2026-07-23',
            'punchin' => '2026-07-23 09:00:00',
        ]);

        $this->failedLog($device, '304', '2026-07-23 09:02:00', 'in', AttendancePunchService::ALREADY_PUNCHED_IN_MESSAGE);

        $this->artisan('biometric:replay-orphaned-punches --apply')
            ->expectsOutputToContain('NO ACTION NEEDED')
            ->expectsOutputToContain('Nothing to replay')
            ->assertExitCode(0);

        // Untouched: the day already had attendance, so there was nothing lost.
        $this->assertSame(1, Attendance::where('user_id', $user->id)->count());
        $this->assertSame(1, BiometricAttLog::where('punch_status', 'failed')->count());
    }

    public function test_device_and_date_filters_scope_the_run(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-06 09:00:00'));

        $gate = $this->device(['name' => 'HQ Gate']);
        $warehouse = $this->device(['name' => 'Warehouse Gate']);

        $atGate = $this->zonedUser('501', $gate);
        $atWarehouse = $this->zonedUser('502', $warehouse);

        $this->failedLog($gate, '501', '2026-07-11 10:00:00', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);
        $this->failedLog($warehouse, '502', '2026-07-11 10:00:00', 'out', AttendancePunchService::NO_OPEN_RECORD_MESSAGE);

        $this->artisan('biometric:replay-orphaned-punches --device='.$gate->serial_number.' --apply')
            ->assertExitCode(0);

        $this->assertSame(1, Attendance::where('user_id', $atGate->id)->count());
        $this->assertSame(0, Attendance::where('user_id', $atWarehouse->id)->count());

        // A range that excludes the remaining punch selects nothing.
        $this->artisan('biometric:replay-orphaned-punches --from=2026-07-12 --apply')
            ->expectsOutputToContain('Nothing to replay')
            ->assertExitCode(0);

        $this->assertSame(0, Attendance::where('user_id', $atWarehouse->id)->count());

        // A range that includes it does.
        $this->artisan('biometric:replay-orphaned-punches --from=2026-07-11 --until=2026-07-11 --apply')
            ->assertExitCode(0);

        $this->assertSame(1, Attendance::where('user_id', $atWarehouse->id)->count());
    }

    public function test_apply_and_dry_run_together_are_refused(): void
    {
        $this->artisan('biometric:replay-orphaned-punches --apply --dry-run')
            ->expectsOutputToContain('contradict each other')
            ->assertExitCode(1);
    }
}
