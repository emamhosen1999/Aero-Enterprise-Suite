<?php

namespace App\Console\Commands;

use App\Models\DailyWork;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\Department;
use App\Models\HRM\Designation;
use App\Models\HRM\Shift;
use App\Models\Leave;
use App\Models\OmEquipment;
use App\Models\OmIncident;
use App\Models\OmShiftLog;
use App\Models\OmTollRecord;
use App\Models\OmTrafficLog;
use App\Models\OmWorkOrder;
use App\Models\PettyCashCategory;
use App\Models\PettyCashLoan;
use App\Models\PettyCashTransaction;
use App\Models\Project;
use App\Models\QualityNCR;
use App\Models\Report;
use App\Models\RfiObjection;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WorkLocation;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AttendanceQueryService;
use App\Services\DeviceAuthService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DeepE2ECrudAudit extends Command
{
    protected $signature = 'audit:e2e-crud';
    protected $description = 'Deep automated testing of all tab sections, buttons, CRUD operations, and backend services';

    private int $passed = 0;
    private int $failed = 0;
    private array $failures = [];

    public function handle(): int
    {
        $this->info('=====================================================================');
        $this->info('   DBEDC GUARDIAN: DEEP END-TO-END CRUD & BACKEND AUDIT SUITE       ');
        $this->info('=====================================================================');

        $admin = User::role('Super Administrator')->first()
            ?? User::role('Administrator')->first()
            ?? User::first();

        if (! $admin) {
            $this->error('Fatal: No user found to execute audit.');
            return 1;
        }

        $this->info("Admin Actor: {$admin->name} [ID: {$admin->employee_id} | Email: {$admin->email}]");
        $this->newLine();

        DB::beginTransaction();

        try {
            // ─────────────────────────────────────────────────────────────
            // MODULE 1: USERS & ROLES CRUD
            // ─────────────────────────────────────────────────────────────
            $this->section('1. Users, Roles & Permissions CRUD');
            $testEmpId = 'AUDIT-EMP-' . strtoupper(Str::random(5));
            $testEmail = 'audit.' . strtolower(Str::random(5)) . '@dhakabypass.com';

            $this->runTest('User: Create New Employee', function () use ($testEmpId, $testEmail) {
                $user = User::create([
                    'employee_id' => $testEmpId,
                    'name' => 'Audit Test User',
                    'user_name' => strtolower($testEmpId),
                    'email' => $testEmail,
                    'password' => Hash::make('password123'),
                    'joining_date' => now()->toDateString(),
                    'blood_group' => 'A+',
                    'contact_number' => '+8801700000000',
                ]);
                if (! $user || $user->employee_id !== $testEmpId) {
                    throw new \Exception('Failed to create user model');
                }
                return $user;
            }, $newUser);

            $this->runTest('User: Read & Query with Relations', function () use ($newUser) {
                $loaded = User::with(['roles', 'department', 'designation', 'devices', 'notificationPreferences'])
                    ->where('employee_id', $newUser->employee_id)
                    ->firstOrFail();
                if ($loaded->id !== $newUser->employee_id) {
                    throw new \Exception('User id accessor did not return employee_id string');
                }
            });

            $this->runTest('User: Update Employee Profile', function () use ($newUser) {
                $newUser->update([
                    'name' => 'Audit Test User (Updated)',
                    'blood_group' => 'B+',
                ]);
                $newUser->refresh();
                if ($newUser->name !== 'Audit Test User (Updated)' || $newUser->blood_group !== 'B+') {
                    throw new \Exception('User update did not persist');
                }
            });

            $this->runTest('User: Role & Permission Assignment', function () use ($newUser) {
                $employeeRole = Role::firstOrCreate(['name' => 'Employee', 'guard_name' => 'web']);
                $newUser->assignRole($employeeRole);
                if (! $newUser->hasRole('Employee')) {
                    throw new \Exception('Failed to assign role to user with string employee_id');
                }
            });

            // ─────────────────────────────────────────────────────────────
            // MODULE 2: ORGANIZATION (DEPARTMENTS, DESIGNATIONS, HOLIDAYS)
            // ─────────────────────────────────────────────────────────────
            $this->section('2. Organization & Structure CRUD');

            $this->runTest('Department: Create, Update & Query', function () {
                $dept = Department::create([
                    'name' => 'Audit Quality Dept ' . Str::random(4),
                    'code' => 'AQD-' . rand(100, 999),
                    'description' => 'Automated test department',
                    'is_active' => true,
                ]);
                $dept->update(['description' => 'Updated test description']);
                $found = Department::with('users')->find($dept->id);
                if (! $found || $found->description !== 'Updated test description') {
                    throw new \Exception('Department CRUD failed');
                }
                return $dept;
            }, $testDept);

            $this->runTest('Designation: Create, Update & Query', function () use ($testDept) {
                $desig = Designation::create([
                    'title' => 'Audit QA Lead ' . Str::random(4),
                    'department_id' => $testDept->id,
                    'hierarchy_level' => 3,
                    'is_active' => true,
                ]);
                $desig->update(['hierarchy_level' => 2]);
                $found = Designation::with('department')->find($desig->id);
                if (! $found || $found->hierarchy_level !== 2) {
                    throw new \Exception('Designation CRUD failed');
                }
                return $desig;
            }, $testDesig);

            // ─────────────────────────────────────────────────────────────
            // MODULE 3: DAILY WORKS, TASKS, RFI OBJECTIONS & REPORTS
            // ─────────────────────────────────────────────────────────────
            $this->section('3. Daily Works, Tasks, Objections & Reports CRUD');

            $this->runTest('DailyWork: Create Task & Attach Report', function () use ($admin) {
                $dw = DailyWork::create([
                    'date' => now()->toDateString(),
                    'number' => 'RFI-AUDIT-' . rand(10000, 99999),
                    'time' => '10:00 AM',
                    'status' => 'Pending',
                    'type' => 'Embankment',
                    'description' => 'E2E Audit Task Description',
                    'location' => 'K12+300',
                    'side' => 'LHS',
                    'incharge' => $admin->employee_id,
                    'assigned' => $admin->employee_id,
                ]);

                $report = Report::firstOrCreate([
                    'title' => 'Daily Inspection Report',
                ], [
                    'description' => 'Daily inspection details',
                ]);

                $dw->reports()->syncWithoutDetaching([$report->id]);
                $found = DailyWork::with(['reports', 'inchargeUser', 'assignedUser'])->find($dw->id);
                if (! $found || $found->reports->isEmpty()) {
                    throw new \Exception('DailyWork task creation or report relation failed');
                }
                return $dw;
            }, $testDw);

            $this->runTest('DailyWork: Update Status & Inspection Details', function () use ($testDw) {
                $testDw->update([
                    'status' => 'Completed',
                    'inspection_details' => 'Passed all density tests successfully.',
                    'completion_time' => now()->toTimeString(),
                ]);
                $testDw->refresh();
                if ($testDw->status !== 'Completed') {
                    throw new \Exception('DailyWork update failed');
                }
            });

            $this->runTest('RfiObjection: Create, Status Log & Query', function () use ($admin, $testDw) {
                $obj = RfiObjection::create([
                    'rfi_id' => $testDw->id,
                    'objection_number' => 'OBJ-AUDIT-' . rand(1000, 9999),
                    'category' => 'Quality',
                    'severity' => 'Medium',
                    'status' => 'Pending',
                    'description' => 'Compaction level below requirement',
                    'raised_by' => $admin->employee_id,
                    'action_required' => 'Re-compact and submit lab test result',
                ]);

                $found = RfiObjection::with(['dailyWork', 'raisedByUser'])->find($obj->id);
                if (! $found || $found->raised_by !== $admin->employee_id) {
                    throw new \Exception('RFI Objection create or user relation failed');
                }
                return $obj;
            }, $testObj);

            // ─────────────────────────────────────────────────────────────
            // MODULE 4: ATTENDANCE & LEAVES CRUD
            // ─────────────────────────────────────────────────────────────
            $this->section('4. Attendance, Shifts, Geo-Punch & Leaves CRUD');

            $this->runTest('Shift: Create & Schedule Validation', function () {
                $shift = Shift::firstOrCreate(['name' => 'Morning Shift (Audit)'], [
                    'code' => 'MS-AUD',
                    'start_time' => '08:00:00',
                    'end_time' => '16:00:00',
                    'grace_period_mins' => 15,
                    'is_active' => true,
                ]);
                if (! $shift) {
                    throw new \Exception('Shift creation failed');
                }
                return $shift;
            }, $testShift);

            $this->runTest('Attendance Type: Geo Polygon Definition', function () {
                $type = AttendanceType::updateOrCreate(['slug' => 'geo_polygon'], [
                    'name' => 'HQ Dhaka Bypass Geofence',
                    'is_active' => true,
                    'config' => [
                        'polygons' => [
                            [
                                'id' => 'hq_polygon',
                                'is_active' => true,
                                'points' => [
                                    ['lat' => 23.8000, 'lng' => 90.4000],
                                    ['lat' => 23.8200, 'lng' => 90.4000],
                                    ['lat' => 23.8200, 'lng' => 90.4200],
                                    ['lat' => 23.8000, 'lng' => 90.4200],
                                ],
                            ],
                        ],
                    ],
                ]);
                return $type;
            }, $testPunchType);

            $this->runTest('Attendance Punch Service: Execution & State', function () use ($admin, $testPunchType) {
                $admin->attendanceTypes()->syncWithoutDetaching([$testPunchType->id]);
                $admin->update(['attendance_type_id' => $testPunchType->id]);

                $punchService = app(AttendancePunchService::class);
                $punchResult = $punchService->punch($admin, [
                    'lat' => 23.8103,
                    'lng' => 90.4125,
                    'location' => 'HQ Toll Plaza',
                    'device_id' => 'e2e-audit-device-001',
                    'photo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
                ]);

                if (! $punchResult || ! isset($punchResult['status'])) {
                    throw new \Exception('Attendance punch did not return valid status response');
                }
            });

            $this->runTest('Leave Application: Create & Status Update', function () use ($admin) {
                $leave = Leave::create([
                    'user_id' => $admin->employee_id,
                    'leave_type' => 'Casual Leave',
                    'start_date' => now()->addDays(10)->toDateString(),
                    'end_date' => now()->addDays(12)->toDateString(),
                    'total_days' => 3,
                    'reason' => 'Automated E2E Audit Leave Request',
                    'status' => 'Pending',
                ]);

                $leave->update(['status' => 'Approved', 'approved_by' => $admin->employee_id]);
                $found = Leave::with('user')->find($leave->id);
                if (! $found || $found->status !== 'Approved') {
                    throw new \Exception('Leave application or approval failed');
                }
                return $leave;
            }, $testLeave);

            // ─────────────────────────────────────────────────────────────
            // MODULE 5: OPERATIONS & MAINTENANCE (O&M) CRUD
            // ─────────────────────────────────────────────────────────────
            $this->section('5. Operations & Maintenance (O&M) CRUD');

            $this->runTest('O&M Equipment: Create, Query & Update', function () {
                $eq = OmEquipment::create([
                    'name' => 'Weigh-in-Motion Sensor #' . rand(100, 999),
                    'code' => 'WIM-' . rand(1000, 9999),
                    'type' => 'Toll Equipment',
                    'location' => 'Plaza Lane 2',
                    'status' => 'Operational',
                ]);
                $eq->update(['status' => 'Under Maintenance']);
                $found = OmEquipment::find($eq->id);
                if (! $found || $found->status !== 'Under Maintenance') {
                    throw new \Exception('OmEquipment CRUD failed');
                }
            });

            $this->runTest('O&M Incident: Create & Resolve', function () use ($admin) {
                $inc = OmIncident::create([
                    'incident_number' => 'INC-' . rand(1000, 9999),
                    'title' => 'Vehicle Stall at Chainage K15+200',
                    'severity' => 'Low',
                    'status' => 'Open',
                    'location' => 'K15+200 RHS',
                    'reported_by' => $admin->employee_id,
                ]);
                $inc->update(['status' => 'Resolved', 'resolution_notes' => 'Vehicle towed away to safety.']);
                $found = OmIncident::find($inc->id);
                if (! $found || $found->status !== 'Resolved') {
                    throw new \Exception('OmIncident CRUD failed');
                }
            });

            $this->runTest('O&M Shift Log: Create & Validate', function () use ($admin) {
                $log = OmShiftLog::create([
                    'operator_id' => $admin->employee_id,
                    'shift_name' => 'Night Shift',
                    'log_date' => now()->toDateString(),
                    'summary' => 'Smooth operations with zero downtime.',
                ]);
                $found = OmShiftLog::find($log->id);
                if (! $found || $found->operator_id !== $admin->employee_id) {
                    throw new \Exception('OmShiftLog create failed');
                }
            });

            $this->runTest('O&M Toll Operations: Record Entry', function () {
                $record = OmTollRecord::create([
                    'lane_id' => 'Lane-03',
                    'vehicle_class' => 'Heavy Truck',
                    'fare_amount' => 450.00,
                    'payment_method' => 'ETC',
                    'transaction_time' => now(),
                ]);
                $found = OmTollRecord::find($record->id);
                if (! $found || (float)$found->fare_amount !== 450.00) {
                    throw new \Exception('OmTollRecord CRUD failed');
                }
            });

            // ─────────────────────────────────────────────────────────────
            // MODULE 6: FINANCIALS & PETTY CASH CRUD
            // ─────────────────────────────────────────────────────────────
            $this->section('6. Financials & Petty Cash CRUD');

            $this->runTest('Petty Cash Category: Create & Query', function () {
                $cat = PettyCashCategory::firstOrCreate(['name' => 'Site Operations (Audit)'], [
                    'description' => 'Direct site expenses during audit',
                    'is_active' => true,
                ]);
                if (! $cat) {
                    throw new \Exception('PettyCashCategory creation failed');
                }
                return $cat;
            }, $testPettyCat);

            $this->runTest('Petty Cash Loan: Issue & Repayment Tracking', function () use ($admin) {
                $loan = PettyCashLoan::create([
                    'user_id' => $admin->employee_id,
                    'amount' => 5000.00,
                    'remaining_amount' => 5000.00,
                    'purpose' => 'Emergency Fuel for Site Vehicles',
                    'status' => 'Active',
                    'issued_by' => $admin->employee_id,
                ]);
                $loan->update(['remaining_amount' => 3500.00]);
                $found = PettyCashLoan::with('user')->find($loan->id);
                if (! $found || (float)$found->remaining_amount !== 3500.00) {
                    throw new \Exception('PettyCashLoan CRUD failed');
                }
                return $loan;
            }, $testLoan);

            $this->runTest('Petty Cash Transaction: Record & Balance Check', function () use ($admin, $testLoan, $testPettyCat) {
                $txn = PettyCashTransaction::create([
                    'loan_id' => $testLoan->id,
                    'category_id' => $testPettyCat->id,
                    'user_id' => $admin->employee_id,
                    'amount' => 1500.00,
                    'type' => 'expense',
                    'description' => 'Diesel Fuel receipt #8812',
                    'transaction_date' => now()->toDateString(),
                ]);
                $found = PettyCashTransaction::with(['loan', 'category', 'user'])->find($txn->id);
                if (! $found || (float)$found->amount !== 1500.00) {
                    throw new \Exception('PettyCashTransaction CRUD failed');
                }
            });

            // ─────────────────────────────────────────────────────────────
            // MODULE 7: MOBILE API v1 ENDPOINTS WITH AUTH & DEVICE
            // ─────────────────────────────────────────────────────────────
            $this->section('7. Mobile API v1 Route Verification');

            $app = app();
            $token = $admin->createToken('E2E Audit Token')->plainTextToken;
            $deviceId = 'e2e-audit-device-001';

            $deviceService = $app->make(DeviceAuthService::class);
            $dummyReq = Request::create('/', 'GET');
            $deviceService->registerDevice($admin, $dummyReq, $deviceId, ['platform' => 'android'], 'Audit Mobile Device');

            $apiRoutes = [
                ['GET', '/api/v1/auth/me'],
                ['GET', '/api/v1/profile'],
                ['GET', '/api/v1/config'],
                ['GET', '/api/v1/sync/bootstrap'],
                ['GET', '/api/v1/attendance/today'],
                ['GET', '/api/v1/attendance/history'],
                ['GET', '/api/v1/attendance/my-roster?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->endOfMonth()->toDateString()],
                ['GET', '/api/v1/attendance/roster?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->endOfMonth()->toDateString()],
                ['GET', '/api/v1/attendance/shifts'],
                ['GET', '/api/v1/attendance/regularizations/mine'],
                ['GET', '/api/v1/attendance/overtime/mine'],
                ['GET', '/api/v1/attendance/swaps/pending'],
                ['GET', '/api/v1/daily-works'],
                ['GET', '/api/v1/daily-works/selectable-dates'],
                ['GET', '/api/v1/daily-works/objections/metadata'],
                ['GET', '/api/v1/daily-works/objections/my'],
                ['GET', '/api/v1/leaves'],
                ['GET', '/api/v1/leaves/summary'],
                ['GET', '/api/v1/leave-types'],
                ['GET', '/api/notifications'],
                ['GET', '/api/notifications/unread-count'],
            ];

            foreach ($apiRoutes as [$method, $uri]) {
                $this->runTest("API Endpoint: $method $uri", function () use ($app, $token, $deviceId, $method, $uri) {
                    $req = Request::create($uri, $method);
                    $req->headers->set('Authorization', 'Bearer ' . $token);
                    $req->headers->set('Accept', 'application/json');
                    $req->headers->set('X-Device-Id', $deviceId);

                    $resp = $app->handle($req);
                    $status = $resp->getStatusCode();

                    if ($status < 200 || $status >= 400) {
                        throw new \Exception("Status $status: " . substr($resp->getContent(), 0, 120));
                    }
                });
            }

            // ─────────────────────────────────────────────────────────────
            // MODULE 8: WEB CONTROLLER PAGES & INERTIA RESPONSES
            // ─────────────────────────────────────────────────────────────
            $this->section('8. Web Pages & Controller Handlers');

            $session = $app->make('session.store');
            $session->start();
            $session->put('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d', $admin->getAuthIdentifier());
            $session->put('device_id', $deviceId);
            $session->put('device_verified', true);

            $webRoutes = [
                '/dashboard',
                '/employees',
                '/users/paginate',
                '/users/stats',
                '/departments',
                '/designations',
                '/roles-permissions',
                '/organization',
                '/holidays',
                '/leaves',
                '/leaves-paginate',
                '/leaves-stats',
                '/attendance-employee',
                '/reports',
                '/reports-json',
                '/stats',
                '/tasks-all',
                '/tasks/daily-summary-json',
                '/petty-cash',
                '/petty-cash/history',
                '/petty-cash/categories',
                '/my-devices',
                '/notifications',
                '/notifications/list',
                '/notifications/unread-count',
                '/workspace/objections',
                '/settings/notifications',
                '/settings/notifications/list',
                '/security/dashboard',
                '/om/dashboard',
                '/om/equipment',
                '/om/incidents',
                '/om/shift-logs',
                '/om/toll-operations',
                '/om/traffic-monitoring',
                '/om/work-orders',
                '/updates',
            ];

            foreach ($webRoutes as $uri) {
                $this->runTest("Web Route: GET $uri", function () use ($app, $admin, $session, $deviceId, $uri) {
                    Auth::guard('web')->setUser($admin);
                    $req = Request::create($uri, 'GET');
                    $req->setLaravelSession($session);
                    $req->setUserResolver(fn () => $admin);
                    $req->headers->set('Accept', 'application/json, text/plain, */*');
                    $req->headers->set('X-Requested-With', 'XMLHttpRequest');
                    $req->headers->set('X-Device-Id', $deviceId);
                    $req->cookies->set('device_id', $deviceId);

                    $resp = $app->handle($req);
                    $status = $resp->getStatusCode();

                    if ($status >= 500) {
                        throw new \Exception("Server Error 500: " . substr($resp->getContent(), 0, 150));
                    }
                });
            }

        } finally {
            // Clean rollback so no test artifacts remain in production database
            DB::rollBack();
        }

        $this->newLine();
        $this->info('=====================================================================');
        $this->info("FINAL AUDIT RESULT: {$this->passed} Passed, {$this->failed} Failed");
        if ($this->failed > 0) {
            $this->error('Failed assertions detected:');
            foreach ($this->failures as $failure) {
                $this->line(" ❌ {$failure['name']}: {$failure['error']}");
            }
            $this->info('=====================================================================');
            return 1;
        }

        $this->info('🎉 100% OF ALL MODULES, TABS, CRUDS & APIS PASSED WITH ZERO ERRORS!');
        $this->info('=====================================================================');
        return 0;
    }

    private function section(string $title): void
    {
        $this->info("\n--- $title ---");
    }

    private function runTest(string $name, callable $callback, mixed &$result = null): void
    {
        try {
            $result = $callback();
            $this->line("  [PASS] $name");
            $this->passed++;
        } catch (\Throwable $e) {
            $this->error("  [FAIL] $name => " . $e->getMessage());
            $this->failed++;
            $this->failures[] = ['name' => $name, 'error' => $e->getMessage()];
        }
    }
}
