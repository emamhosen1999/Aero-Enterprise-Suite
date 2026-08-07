<?php

namespace Tests\Feature\Biometric;

use App\Models\HRM\Attendance;
use App\Models\HRM\BiometricDevice;
use App\Models\User;
use App\Services\Biometric\DeviceReconciliationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * Reconciliation: device punches vs the attendance they became.
 *
 * ── The measurement these tests are built from ──────────────────────────────
 *
 * A full raw pull off the production MB460 (`AF6P231260266`) returned 1,054
 * records, #1 → #1054 — the device's entire history — and reconciled as
 * `new=0, duplicate=1054`. Every device record is already in the ERP, so
 * ingestion is not the defect. Conversion is: 540 device user-days produced 33
 * that never became an `attendances` row, across 11 employees.
 *
 * The two properties that decide whether this report is usable at all are both
 * pinned here, and they pull in opposite directions:
 *
 *  - **A genuine gap must be flagged, with the reason attached.** 22 of the 33
 *    read "No open attendance record to punch out from." — the terminal sent the
 *    day's FIRST punch as a check-out. On 2026-07-11 that hit three employees
 *    simultaneously, which is what makes it the terminal's IN/OUT mode rather
 *    than user error.
 *  - **An employee whose ERP days far exceed device days must NOT be flagged.**
 *    Debashis Jha has 21 device days against 298 ERP days because he also
 *    punches over WiFi/GPS. There are 540 device user-days and thousands of ERP
 *    days; a reconciliation that called that a discrepancy would bury 33 real
 *    findings under hundreds of false ones and would be worse than useless.
 *
 * The third property is that "did not become attendance" is not one thing.
 * Configuration days ("Attendance type is not biometric: wifi_ip_3", "User has
 * no attendance type assigned") are the system doing exactly as configured and
 * must never be reported as data loss.
 */
class DeviceReconciliationTest extends TestCase
{
    use RefreshDatabase;

    /** The MB460's real error: exactly two hours fast. */
    private const OFFSET_SECONDS = 7200;

    /** The verbatim reason behind 22 of the 33 production gaps. */
    private const PAIRING_REASON = 'No open attendance record to punch out from.';

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Role::firstOrCreate(['name' => 'Admin']);
        Permission::firstOrCreate(['name' => 'attendance.settings']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    // ── fixtures ────────────────────────────────────────────────────

    private function service(): DeviceReconciliationService
    {
        return app(DeviceReconciliationService::class);
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        $admin->givePermissionTo('attendance.settings');

        return $admin;
    }

    private function device(array $overrides = []): BiometricDevice
    {
        return BiometricDevice::create(array_merge([
            'name' => 'Gate MB460',
            'serial_number' => 'AF6P231260266-'.uniqid(),
            'protocol' => 'adms',
            'is_active' => true,
        ], $overrides));
    }

    private function employee(string $pin): User
    {
        return User::factory()->create(['employee_id' => $pin]);
    }

    /**
     * Stage one punch exactly as the capture path writes it.
     *
     * `punch_time` is the device's RAW account and is never rewritten;
     * `corrected_punch_time` is non-null only when the clock correction was
     * applied to that row, and then it holds the moment that actually reached
     * `attendances`. Passing $corrected here is what reproduces the mixed table
     * production has: rows written before 2026-08-06 have none, later ones do.
     */
    private function punch(
        BiometricDevice $device,
        string $pin,
        ?User $user,
        string $rawTime,
        string $status = 'processed',
        ?string $reason = null,
        string $checkType = 'in',
        ?string $corrected = null,
    ): int {
        return DB::table('biometric_att_logs')->insertGetId([
            'biometric_device_id' => $device->id,
            'serial_number' => $device->serial_number,
            'user_pin' => $pin,
            'user_id' => $user?->id,
            'punch_time' => $rawTime,
            'corrected_punch_time' => $corrected,
            'clock_offset_applied_seconds' => $corrected === null ? null : -self::OFFSET_SECONDS,
            'check_type' => $checkType,
            'punch_status' => $status,
            'punch_status_reason' => $reason,
            'occurred_at' => $rawTime,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** An attendance row, created the way the app creates one. */
    private function attendance(User $user, string $date, ?string $in = null, ?string $out = null): Attendance
    {
        return Attendance::create([
            'user_id' => $user->id,
            'date' => $date,
            'punchin' => $in,
            'punchout' => $out,
        ]);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function employeeRow(array $report, string $pin): array
    {
        foreach ($report['employees'] as $row) {
            if ($row['pin'] === $pin) {
                return $row;
            }
        }

        $this->fail("PIN {$pin} is missing from the reconciliation report.");
    }

    // ──────────────────────────────────────────────────────────────
    //  The false-positive that would sink the report
    // ──────────────────────────────────────────────────────────────

    /**
     * Debashis Jha's shape: far more ERP days than device days, zero findings.
     *
     * He is on a WiFi attendance type as well as the reader, so most of his
     * attendance never touches this device. Every one of those days is correct
     * and none of them is this device's business. If `erp_days > device_days`
     * were ever treated as a discrepancy, this single employee would contribute
     * 277 false findings against a real total of 33.
     */
    public function test_an_employee_with_more_erp_days_than_device_days_is_never_flagged(): void
    {
        $device = $this->device();
        $user = $this->employee('120');

        // Two days on the reader.
        $this->punch($device, '120', $user, '2026-07-01 09:00:00');
        $this->punch($device, '120', $user, '2026-07-02 09:00:00');

        $this->attendance($user, '2026-07-01', '2026-07-01 09:00:00');
        $this->attendance($user, '2026-07-02', '2026-07-02 09:00:00');

        // Eight further days recorded over WiFi/GPS — this device never saw them.
        foreach (['03', '04', '05', '06', '07', '08', '09', '10'] as $day) {
            $this->attendance($user, "2026-07-{$day}", "2026-07-{$day} 09:05:00");
        }

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');
        $row = $this->employeeRow($report, '120');

        $this->assertSame(2, $row['device_days']);
        $this->assertSame(10, $row['erp_days'], 'Every attendance day in range counts, whatever channel wrote it.');
        $this->assertSame(0, $row['missing_days'], 'ERP days exceeding device days is normal and is not a finding.');
        $this->assertSame([], $row['missing']);

        $this->assertSame(0, $report['conversion']['missing_user_days']);
        $this->assertSame(0, $report['conversion']['employees_with_gaps']);
        $this->assertSame(2, $report['conversion']['device_user_days'], 'Only days the DEVICE saw are the denominator.');
        $this->assertSame(2, $report['conversion']['converted_user_days']);

        // The two device days are attributable to this device by timestamp; the
        // eight WiFi days are not, and must not be claimed.
        $this->assertSame(2, $row['device_derived_days']);
    }

    // ──────────────────────────────────────────────────────────────
    //  The finding
    // ──────────────────────────────────────────────────────────────

    /**
     * The production defect, reproduced: the day's FIRST punch arrives as a
     * check-out, the punch path has nothing to close, and the day silently never
     * becomes attendance.
     */
    public function test_a_device_day_with_no_attendance_is_flagged_with_its_reason(): void
    {
        $device = $this->device();
        $user = $this->employee('304');

        // A good day, to prove the report is not simply flagging everything.
        $this->punch($device, '304', $user, '2026-07-10 09:00:00');
        $this->attendance($user, '2026-07-10', '2026-07-10 09:00:00');

        // 2026-07-11: both punches rejected, nothing in `attendances`.
        $this->punch($device, '304', $user, '2026-07-11 09:02:00', 'failed', self::PAIRING_REASON, 'out');
        $this->punch($device, '304', $user, '2026-07-11 17:41:00', 'failed', self::PAIRING_REASON, 'out');

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');
        $row = $this->employeeRow($report, '304');

        $this->assertSame(2, $row['device_days']);
        $this->assertSame(1, $row['erp_days']);
        $this->assertSame(1, $row['missing_days']);

        $this->assertCount(1, $row['missing']);
        $missing = $row['missing'][0];

        $this->assertSame('2026-07-11', $missing['date']);
        $this->assertSame(2, $missing['punches']);
        $this->assertSame('2026-07-11 09:02:00', $missing['first_punch']);
        $this->assertSame('2026-07-11 17:41:00', $missing['last_punch']);
        $this->assertSame(DeviceReconciliationService::CATEGORY_PAIRING, $missing['category']);

        // The reason is carried verbatim, de-duplicated. A categorisation nobody
        // can check against what the pipeline actually wrote is not evidence.
        $this->assertSame([self::PAIRING_REASON], $missing['reasons']);
        $this->assertSame(['failed'], $missing['statuses']);
        $this->assertSame(['out'], $missing['check_types'], 'Both punches arrived as check-outs — that is the tell.');

        $this->assertSame(1, $report['conversion']['missing_user_days']);
        $this->assertSame(1, $report['conversion']['employees_with_gaps']);
        $this->assertSame(
            1,
            $report['conversion']['by_category'][DeviceReconciliationService::CATEGORY_PAIRING]
        );
    }

    /**
     * The 2026-07-11 signature: three employees losing the same day at once is
     * what proves it is the terminal's IN/OUT mode and not user error, so the
     * report has to make that visible rather than showing three unrelated rows.
     */
    public function test_a_simultaneous_failure_across_employees_is_reported_for_each(): void
    {
        $device = $this->device();

        foreach (['304', '307', '130'] as $pin) {
            $user = $this->employee($pin);
            $this->punch($device, $pin, $user, '2026-07-11 09:0'.substr($pin, -1).':00', 'failed', self::PAIRING_REASON, 'out');
        }

        $report = $this->service()->reconcile($device, '2026-07-11', '2026-07-11');

        $this->assertSame(3, $report['conversion']['device_user_days']);
        $this->assertSame(3, $report['conversion']['missing_user_days']);
        $this->assertSame(3, $report['conversion']['employees_with_gaps']);

        foreach (['304', '307', '130'] as $pin) {
            $row = $this->employeeRow($report, $pin);
            $this->assertSame(1, $row['missing_days']);
            $this->assertSame('2026-07-11', $row['missing'][0]['date']);
            $this->assertSame(DeviceReconciliationService::CATEGORY_PAIRING, $row['missing'][0]['category']);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Categorisation
    // ──────────────────────────────────────────────────────────────

    /**
     * Every reason string the two producing services actually emit, mapped.
     *
     * Taken from AttendancePunchService's result messages and
     * BiometricProcessingService::validateAttendanceEligibility rather than
     * invented, because a categoriser tested against strings nobody writes
     * proves nothing.
     */
    public function test_reasons_are_categorised_against_the_strings_the_pipeline_writes(): void
    {
        $service = $this->service();

        $cases = [
            // Recoverable — the punches exist, pairing failed.
            ['failed', self::PAIRING_REASON, DeviceReconciliationService::CATEGORY_PAIRING],
            ['failed', 'Punch-out cannot be before punch-in.', DeviceReconciliationService::CATEGORY_PAIRING],
            ['failed', 'Already punched in for this period.', DeviceReconciliationService::CATEGORY_PAIRING],
            ['failed', 'Duplicate punch ignored. Please wait a moment and try again.', DeviceReconciliationService::CATEGORY_PAIRING],

            // Configuration — NOT data loss.
            ['failed', 'Attendance type is not biometric: wifi_ip_3', DeviceReconciliationService::CATEGORY_CONFIGURATION],
            ['failed', 'User has no attendance type assigned', DeviceReconciliationService::CATEGORY_CONFIGURATION],
            ['failed', 'Device not in attendance zone', DeviceReconciliationService::CATEGORY_CONFIGURATION],
            ['wrong_device', null, DeviceReconciliationService::CATEGORY_CONFIGURATION],

            // Unknown PIN.
            ['unknown_user', 'Auto-created as inactive placeholder', DeviceReconciliationService::CATEGORY_UNKNOWN_PIN],
            ['unknown_user', null, DeviceReconciliationService::CATEGORY_UNKNOWN_PIN],

            // Staged, never imported.
            ['downloaded', 'Downloaded via active sync session', DeviceReconciliationService::CATEGORY_AWAITING_IMPORT],

            // Anything unrecognised, including a processed punch whose day has
            // no attendance row — which is a real anomaly, not a silent pass.
            ['failed', 'Pending processing', DeviceReconciliationService::CATEGORY_OTHER],
            ['failed', 'Something nobody has seen before', DeviceReconciliationService::CATEGORY_OTHER],
            ['processed', null, DeviceReconciliationService::CATEGORY_OTHER],
        ];

        foreach ($cases as [$status, $reason, $expected]) {
            $this->assertSame(
                $expected,
                $service->categorise($status, $reason),
                "status={$status} reason=".var_export($reason, true)
            );
        }
    }

    /**
     * Configuration days are counted, named, and kept out of the loss figure.
     *
     * "Attendance type is not biometric: wifi_ip_3" is the system doing exactly
     * what it was configured to do. It still means the punch did not become
     * attendance, so it is reported — but as its own category with `nature =
     * needs_review`, and the conversion sentence subtracts it from the number it
     * calls unaccounted for.
     */
    public function test_configuration_days_are_reported_but_never_as_data_loss(): void
    {
        $device = $this->device();
        $wifi = $this->employee('151');
        $broken = $this->employee('307');

        $this->punch($device, '151', $wifi, '2026-07-05 09:00:00', 'failed', 'Attendance type is not biometric: wifi_ip_3');
        $this->punch($device, '307', $broken, '2026-07-05 09:10:00', 'failed', self::PAIRING_REASON, 'out');

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');

        $this->assertSame(2, $report['conversion']['missing_user_days']);
        $this->assertSame(1, $report['conversion']['by_category'][DeviceReconciliationService::CATEGORY_CONFIGURATION]);
        $this->assertSame(1, $report['conversion']['by_category'][DeviceReconciliationService::CATEGORY_PAIRING]);

        $configured = $this->employeeRow($report, '151');
        $this->assertSame(
            DeviceReconciliationService::CATEGORY_CONFIGURATION,
            $configured['missing'][0]['category']
        );
        $this->assertSame(
            ['Attendance type is not biometric: wifi_ip_3'],
            $configured['missing'][0]['reasons']
        );

        $meta = DeviceReconciliationService::categoryMeta();
        $this->assertSame('needs_review', $meta[DeviceReconciliationService::CATEGORY_CONFIGURATION]['nature']);
        $this->assertSame('data_loss', $meta[DeviceReconciliationService::CATEGORY_PAIRING]['nature']);
        $this->assertSame('data_loss', $meta[DeviceReconciliationService::CATEGORY_UNKNOWN_PIN]['nature']);

        // The sentence an admin reads must separate the two halves: 2 days did
        // not convert, but only 1 of them is unaccounted for.
        $this->assertStringContainsString('1 unaccounted for', $report['headline']['conversion']);
        $this->assertStringContainsString('not data loss', $report['headline']['conversion']);
    }

    /**
     * One day, several disagreeing punches: the category that explains the whole
     * day wins, and every reason is still listed.
     */
    public function test_a_day_takes_the_most_explanatory_category_and_keeps_every_reason(): void
    {
        $device = $this->device();
        $user = $this->employee('169');

        $this->punch($device, '169', $user, '2026-07-06 09:00:00', 'failed', self::PAIRING_REASON, 'out');
        $this->punch($device, '169', $user, '2026-07-06 17:00:00', 'failed', 'User has no attendance type assigned');

        $report = $this->service()->reconcile($device, '2026-07-06', '2026-07-06');
        $missing = $this->employeeRow($report, '169')['missing'][0];

        $this->assertSame(
            DeviceReconciliationService::CATEGORY_CONFIGURATION,
            $missing['category'],
            'A whole-employee configuration fact explains the day; a single pairing failure does not.'
        );
        $this->assertCount(2, $missing['reasons']);
        $this->assertContains(self::PAIRING_REASON, $missing['reasons']);
        $this->assertContains('User has no attendance type assigned', $missing['reasons']);
    }

    /** A PIN nobody carries is its own category, and keeps its placeholder visible. */
    public function test_an_unknown_pin_is_categorised_as_such_and_marked_a_placeholder(): void
    {
        $device = $this->device();

        // Exactly what resolveOrCreateUser() mints: a soft-deleted stand-in.
        $placeholder = User::factory()->create([
            'employee_id' => '9999',
            'name' => 'Device User 9999',
        ]);
        $placeholder->delete();

        $this->punch($device, '9999', $placeholder, '2026-07-07 09:00:00', 'unknown_user', 'Auto-created as inactive placeholder');

        $report = $this->service()->reconcile($device, '2026-07-07', '2026-07-07');
        $row = $this->employeeRow($report, '9999');

        $this->assertTrue($row['is_placeholder'], 'A soft-deleted user behind a PIN means the PIN belongs to nobody.');
        $this->assertSame(1, $row['missing_days']);
        $this->assertSame(
            DeviceReconciliationService::CATEGORY_UNKNOWN_PIN,
            $row['missing'][0]['category']
        );
    }

    /** Staged by a download session and never imported is unfinished work, not a failure. */
    public function test_a_staged_but_unimported_punch_is_its_own_category(): void
    {
        $device = $this->device();
        $user = $this->employee('149');

        $this->punch($device, '149', $user, '2026-07-08 09:00:00', 'downloaded', 'Downloaded via active sync session');

        $report = $this->service()->reconcile($device, '2026-07-08', '2026-07-08');

        $this->assertSame(
            DeviceReconciliationService::CATEGORY_AWAITING_IMPORT,
            $this->employeeRow($report, '149')['missing'][0]['category']
        );
        $this->assertSame(
            'unfinished',
            DeviceReconciliationService::categoryMeta()[DeviceReconciliationService::CATEGORY_AWAITING_IMPORT]['nature']
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  The clock offset
    // ──────────────────────────────────────────────────────────────

    /**
     * A corrected punch is bucketed on the day it really happened, not the day
     * the device claimed — and the range filter agrees with the bucket.
     *
     * The device is exactly 2 h fast, so a raw 2026-07-12 01:30 punch is a real
     * 2026-07-11 23:30 punch and `AttendancePunchService` filed it under
     * 2026-07-11. Bucketing on the RAW timestamp would put the device day on
     * 07-12, find no attendance there, and invent a missing day — while the
     * genuine 07-11 attendance sat unmatched one row away. Bucketing on
     * `corrected_punch_time ?? punch_time` cannot drift, because it is the same
     * instant the attendance row was dated from.
     */
    public function test_a_corrected_punch_is_bucketed_on_the_corrected_day(): void
    {
        $device = $this->device();
        $user = $this->employee('152');

        // Raw 2026-07-12 01:30 → corrected 2026-07-11 23:30. Attendance was
        // written from the corrected moment, so it is dated 07-11.
        $this->punch($device, '152', $user, '2026-07-12 01:30:00', 'processed', null, 'out', '2026-07-11 23:30:00');
        $this->attendance($user, '2026-07-11', '2026-07-11 22:00:00', '2026-07-11 23:30:00');

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');
        $row = $this->employeeRow($report, '152');

        $this->assertSame(1, $row['device_days']);
        $this->assertSame(0, $row['missing_days'], 'The punch and its attendance are on the same day once corrected.');
        $this->assertSame(1, $row['device_derived_days'], 'Matching is against the corrected moment, which is what attendances holds.');

        // And the day it lands on is 07-11, provable by asking for that day only.
        $narrow = $this->service()->reconcile($device, '2026-07-11', '2026-07-11');
        $this->assertSame(1, $narrow['conversion']['device_user_days']);

        // Asking for 07-12 alone — the day the DEVICE claimed — must find nothing.
        $wrongDay = $this->service()->reconcile($device, '2026-07-12', '2026-07-12');
        $this->assertSame(0, $wrongDay['conversion']['device_user_days']);
        $this->assertSame([], $wrongDay['employees']);
    }

    /**
     * An uncorrected row — everything written before 2026-08-06 — still buckets
     * on its raw timestamp, because that is the moment its attendance was dated
     * from. The same rule covers both halves of the mixed table.
     */
    public function test_an_uncorrected_punch_still_buckets_on_its_raw_timestamp(): void
    {
        $device = $this->device();
        $user = $this->employee('153');

        $this->punch($device, '153', $user, '2026-07-03 11:00:00');
        $this->attendance($user, '2026-07-03', '2026-07-03 11:00:00');

        $report = $this->service()->reconcile($device, '2026-07-03', '2026-07-03');
        $row = $this->employeeRow($report, '153');

        $this->assertSame(1, $row['device_days']);
        $this->assertSame(0, $row['missing_days']);
        $this->assertSame([], $row['missing']);
    }

    /** The raw device time is kept next to the corrected one, so the shift is visible. */
    public function test_a_missing_day_shows_both_the_corrected_and_the_raw_device_time(): void
    {
        $device = $this->device();
        $user = $this->employee('155');

        $this->punch($device, '155', $user, '2026-07-09 11:00:00', 'failed', self::PAIRING_REASON, 'out', '2026-07-09 09:00:00');

        $report = $this->service()->reconcile($device, '2026-07-09', '2026-07-09');
        $missing = $this->employeeRow($report, '155')['missing'][0];

        $this->assertSame('2026-07-09 09:00:00', $missing['first_punch']);
        $this->assertSame('2026-07-09 11:00:00', $missing['first_punch_raw']);
        $this->assertTrue($missing['clock_corrected']);
    }

    // ──────────────────────────────────────────────────────────────
    //  The range
    // ──────────────────────────────────────────────────────────────

    public function test_the_range_filter_selects_only_days_inside_it(): void
    {
        $device = $this->device();
        $user = $this->employee('302');

        // Three device days, none of which became attendance.
        $this->punch($device, '302', $user, '2026-06-30 09:00:00', 'failed', self::PAIRING_REASON, 'out');
        $this->punch($device, '302', $user, '2026-07-15 09:00:00', 'failed', self::PAIRING_REASON, 'out');
        $this->punch($device, '302', $user, '2026-08-01 09:00:00', 'failed', self::PAIRING_REASON, 'out');

        $july = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');

        $this->assertSame(1, $july['conversion']['device_user_days']);
        $this->assertSame(1, $july['conversion']['missing_user_days']);
        $this->assertSame('2026-07-15', $this->employeeRow($july, '302')['missing'][0]['date']);
        $this->assertSame(['from' => '2026-07-01', 'until' => '2026-07-31', 'days' => 31], $july['range']);

        // Both bounds are inclusive.
        $edges = $this->service()->reconcile($device, '2026-06-30', '2026-08-01');
        $this->assertSame(3, $edges['conversion']['device_user_days']);
        $this->assertSame(3, $edges['conversion']['missing_user_days']);

        // A single day is one day, not zero.
        $single = $this->service()->reconcile($device, '2026-07-15', '2026-07-15');
        $this->assertSame(1, $single['range']['days']);
        $this->assertSame(1, $single['conversion']['device_user_days']);
    }

    /**
     * Attendance outside the range is not consulted.
     *
     * A day is missing because there is no attendance ON THAT DAY, so an
     * attendance row from a neighbouring month must neither rescue an in-range
     * device day nor be counted in `erp_days`.
     */
    public function test_attendance_outside_the_range_neither_rescues_nor_counts(): void
    {
        $device = $this->device();
        $user = $this->employee('301');

        $this->punch($device, '301', $user, '2026-07-20 09:00:00', 'failed', self::PAIRING_REASON, 'out');
        $this->attendance($user, '2026-06-20', '2026-06-20 09:00:00');
        $this->attendance($user, '2026-08-20', '2026-08-20 09:00:00');

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');
        $row = $this->employeeRow($report, '301');

        $this->assertSame(0, $row['erp_days']);
        $this->assertSame(1, $row['missing_days']);
    }

    public function test_the_range_defaults_to_the_last_thirty_days(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-07 10:00:00'));

        $report = $this->service()->reconcile($this->device());

        $this->assertSame('2026-08-07', $report['range']['until']);
        $this->assertSame('2026-07-09', $report['range']['from']);
        $this->assertSame(DeviceReconciliationService::DEFAULT_RANGE_DAYS, $report['range']['days']);
    }

    public function test_an_inverted_range_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reconcile($this->device(), '2026-07-31', '2026-07-01');
    }

    public function test_a_range_longer_than_the_cap_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service()->reconcile($this->device(), '2020-01-01', '2026-01-01');
    }

    // ──────────────────────────────────────────────────────────────
    //  Ingestion and conversion are never blended
    // ──────────────────────────────────────────────────────────────

    /**
     * The headline is two sentences about two systems, and there is no third
     * number that averages them.
     *
     * Ingestion is perfect (1,054 of 1,054) and conversion is 94 %. A single
     * "sync health" figure would read ~97 %, which is true of nothing and hides
     * which half is broken — the only thing the number is for.
     */
    public function test_ingestion_and_conversion_are_reported_separately(): void
    {
        $device = $this->device();
        $user = $this->employee('154');

        $this->punch($device, '154', $user, '2026-07-04 09:00:00', 'failed', self::PAIRING_REASON, 'out');

        // A completed full pull, with the production shape: nothing new.
        DB::table('biometric_download_sessions')->insert([
            'biometric_device_id' => $device->id,
            'trigger_type' => 'manual',
            'status' => 'completed',
            'total_records' => 1054,
            'processed_count' => 0,
            'duplicate_count' => 1054,
            'failed_count' => 0,
            'started_at' => '2026-08-06 10:00:00',
            'completed_at' => '2026-08-06 10:05:00',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');

        $this->assertArrayHasKey('ingestion', $report);
        $this->assertArrayHasKey('conversion', $report);
        $this->assertArrayNotHasKey('sync_health', $report);
        $this->assertArrayNotHasKey('health_score', $report);

        // Ingestion is stated from the pull that measured it, and attributed.
        $this->assertSame(1054, $report['ingestion']['last_full_pull']['total_records']);
        $this->assertSame(0, $report['ingestion']['last_full_pull']['new_records']);
        $this->assertSame(1054, $report['ingestion']['last_full_pull']['already_held']);
        $this->assertStringContainsString('1,054 of 1,054 device records ingested', $report['headline']['ingestion']);

        // Conversion is a separate sentence over a separate denominator.
        $this->assertStringContainsString('1 of 1 device user-day(s) did not become attendance', $report['headline']['conversion']);
        $this->assertNotSame($report['headline']['ingestion'], $report['headline']['conversion']);
    }

    /**
     * With no full pull on record, ingestion says so rather than inventing a
     * reassuring number out of what the ERP happens to hold.
     */
    public function test_ingestion_is_null_when_no_full_pull_has_ever_completed(): void
    {
        $device = $this->device();
        $user = $this->employee('153');

        $this->punch($device, '153', $user, '2026-07-04 09:00:00');
        $this->attendance($user, '2026-07-04', '2026-07-04 09:00:00');

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');

        $this->assertNull($report['ingestion']['last_full_pull']);
        $this->assertNull($report['headline']['ingestion']);
        $this->assertSame(1, $report['ingestion']['records_in_range']);
        $this->assertStringContainsString('every one of them became an attendance record', $report['headline']['conversion']);
    }

    /** Punches belonging to another device are never counted against this one. */
    public function test_another_devices_punches_are_not_reconciled_against_this_device(): void
    {
        $device = $this->device();
        $other = $this->device(['name' => 'Side Door']);
        $user = $this->employee('130');

        $this->punch($other, '130', $user, '2026-07-14 09:00:00', 'failed', self::PAIRING_REASON, 'out');

        $report = $this->service()->reconcile($device, '2026-07-01', '2026-07-31');

        $this->assertSame(0, $report['conversion']['device_user_days']);
        $this->assertSame([], $report['employees']);
    }

    // ──────────────────────────────────────────────────────────────
    //  Endpoint
    // ──────────────────────────────────────────────────────────────

    public function test_the_endpoint_returns_the_report(): void
    {
        $device = $this->device();
        $user = $this->employee('304');

        $this->punch($device, '304', $user, '2026-07-11 09:02:00', 'failed', self::PAIRING_REASON, 'out');

        $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.reconciliation', [
                'id' => $device->id,
                'from' => '2026-07-01',
                'until' => '2026-07-31',
            ]))
            ->assertOk()
            ->assertJsonPath('device.id', $device->id)
            ->assertJsonPath('range.from', '2026-07-01')
            ->assertJsonPath('conversion.missing_user_days', 1)
            ->assertJsonPath('employees.0.pin', '304')
            ->assertJsonPath('employees.0.missing.0.category', DeviceReconciliationService::CATEGORY_PAIRING)
            ->assertJsonPath('employees.0.missing.0.reasons.0', self::PAIRING_REASON);
    }

    public function test_the_endpoint_refuses_an_impossible_range_with_the_services_own_message(): void
    {
        $device = $this->device();

        $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.reconciliation', [
                'id' => $device->id,
                'from' => '2026-07-31',
                'until' => '2026-07-01',
            ]))
            ->assertStatus(422)
            ->assertJsonPath('message', 'The start date (2026-07-31) is after the end date (2026-07-01); that range selects nothing.');
    }

    public function test_the_endpoint_404s_for_a_device_that_does_not_exist(): void
    {
        $this->actingAs($this->admin())
            ->getJson(route('biometric-devices.reconciliation', ['id' => 987654]))
            ->assertStatus(404);
    }

    public function test_the_endpoint_requires_the_attendance_settings_permission(): void
    {
        $device = $this->device();

        $this->actingAs(User::factory()->create())
            ->getJson(route('biometric-devices.reconciliation', ['id' => $device->id]))
            ->assertForbidden();
    }

    // ───────────────────────────── Routing: the bulk/{id} collision

    /**
     * `{id}/reconciliation` must not re-open the collision that shipped once
     * already: an unconstrained `{id}` also matches the literal segment `bulk`,
     * which made `bulk/ping` resolve to `{id}/ping` with id="bulk" and silently
     * broke every bulk action. This asserts the new route is numeric-constrained
     * and that the routes it sits next to still resolve where they should.
     */
    public function test_the_reconciliation_route_does_not_collide_with_bulk_or_collection_routes(): void
    {
        $expected = [
            ['GET', 'settings/biometric-devices/7/reconciliation', 'biometric-devices.reconciliation', ['id' => '7']],
            ['POST', 'settings/biometric-devices/bulk/ping', 'biometric-devices.bulk.ping', []],
            ['GET', 'settings/biometric-devices/templates', 'biometric-devices.templates', []],
            ['GET', 'settings/biometric-devices/settings-catalogue', 'biometric-devices.settings-catalogue', []],
            ['GET', 'settings/biometric-devices/health', 'biometric-devices.health', []],
        ];

        foreach ($expected as [$method, $uri, $name, $parameters]) {
            $route = app('router')->getRoutes()->match(Request::create('/'.$uri, $method));

            $this->assertSame($name, $route->getName(), "{$method} {$uri} resolved to the wrong route");
            $this->assertSame($parameters, $route->parameters(), "{$method} {$uri} bound the wrong parameters");
        }
    }

    /** A non-numeric id must not reach the reconciliation route at all. */
    public function test_a_non_numeric_id_does_not_match_the_reconciliation_route(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app('router')->getRoutes()->match(
            Request::create('/settings/biometric-devices/bulk/reconciliation', 'GET')
        );
    }
}
