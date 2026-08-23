<?php

namespace App\Console\Commands;

use App\Models\DailyWork;
use App\Models\HRM\AttendanceType;
use App\Models\HRM\Department;
use App\Models\HRM\Designation;
use App\Models\Leave;
use App\Models\Report;
use App\Models\RfiObjection;
use App\Models\User;
use App\Models\UserDevice;
use App\Services\Attendance\AttendancePunchService;
use App\Services\Attendance\AttendanceQueryService;
use App\Services\DeviceAuthService;
use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

class AuditSystemDeep extends Command
{
    protected $signature = 'audit:system-deep';
    protected $description = 'Perform deep end-to-end automated testing of all web and mobile API routes and core services';

    public function handle(): int
    {
        $this->info('=====================================================');
        $this->info('      DBEDC GUARDIAN - DEEP SYSTEM AUDIT SUITE       ');
        $this->info('=====================================================');

        $totalTests = 0;
        $passedTests = 0;
        $failedTests = 0;
        $failures = [];

        // 1. Authenticate / Setup Test User
        $user = User::whereNotNull('email')->first();
        if (!$user) {
            $this->error('No users found in database to run audit.');
            return 1;
        }

        $this->info("Target Test User: {$user->name} ({$user->employee_id} / {$user->email})");

        // 2. Test Mobile API Authentication & Token Creation
        $this->newLine();
        $this->info('--- 1. Testing Mobile API Authentication & Token ---');
        $totalTests++;
        try {
            $token = $user->createToken('Deep Audit Token')->plainTextToken;
            if (empty($token)) {
                throw new \Exception('Failed to generate Sanctum plainTextToken');
            }
            $this->line(" [PASS] Sanctum Token Creation: " . substr($token, 0, 20) . "...");
            $passedTests++;
        } catch (\Throwable $e) {
            $this->error(" [FAIL] Sanctum Token Creation: " . $e->getMessage());
            $failedTests++;
            $failures[] = ['category' => 'Auth', 'test' => 'Sanctum Token Creation', 'error' => $e->getMessage()];
        }

        // 3. Test Mobile API Endpoints
        $this->newLine();
        $this->info('--- 2. Testing Mobile API v1 Endpoints ---');

        $apiEndpoints = [
            ['GET', '/api/v1/auth/me', []],
            ['GET', '/api/v1/profile', []],
            ['GET', '/api/v1/config', []],
            ['GET', '/api/v1/sync/bootstrap', []],
            ['GET', '/api/v1/attendance/today', []],
            ['GET', '/api/v1/attendance/history', []],
            ['GET', '/api/v1/attendance/my-roster?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->endOfMonth()->toDateString(), []],
            ['GET', '/api/v1/attendance/roster?from=' . now()->startOfMonth()->toDateString() . '&to=' . now()->endOfMonth()->toDateString(), []],
            ['GET', '/api/v1/attendance/shifts', []],
            ['GET', '/api/v1/attendance/regularizations/mine', []],
            ['GET', '/api/v1/attendance/overtime/mine', []],
            ['GET', '/api/v1/attendance/swaps/pending', []],
            ['GET', '/api/v1/daily-works', []],
            ['GET', '/api/v1/daily-works/selectable-dates', []],
            ['GET', '/api/v1/daily-works/objections/metadata', []],
            ['GET', '/api/v1/daily-works/objections/my', []],
            ['GET', '/api/v1/leaves', []],
            ['GET', '/api/v1/leaves/summary', []],
            ['GET', '/api/v1/leave-types', []],
            ['GET', '/api/notifications', []],
            ['GET', '/api/notifications/unread-count', []],
        ];

        $app = app();

        foreach ($apiEndpoints as [$method, $uri, $params]) {
            $totalTests++;
            try {
                $req = Request::create($uri, $method, $params);
                $req->headers->set('Authorization', 'Bearer ' . $token);
                $req->headers->set('Accept', 'application/json');
                $req->headers->set('X-Device-Id', 'audit-device-deep-001');

                $resp = $app->handle($req);
                $status = $resp->getStatusCode();

                if ($status >= 200 && $status < 400) {
                    $this->line(" [PASS] $method $uri => Status $status");
                    $passedTests++;
                } else {
                    $content = substr($resp->getContent(), 0, 150);
                    $this->error(" [FAIL] $method $uri => Status $status: $content");
                    $failedTests++;
                    $failures[] = ['category' => 'API', 'test' => "$method $uri", 'error' => "Status $status: $content"];
                }
            } catch (\Throwable $e) {
                $this->error(" [CRASH] $method $uri => Exception: " . $e->getMessage());
                $failedTests++;
                $failures[] = ['category' => 'API', 'test' => "$method $uri", 'error' => $e->getMessage()];
            }
        }

        // 4. Test Mobile API Attendance Punch
        $this->newLine();
        $this->info('--- 3. Testing Mobile Attendance Punch Service ---');
        $totalTests++;
        try {
            $punchType = AttendanceType::updateOrCreate(['slug' => 'geo_polygon'], [
                'name' => 'HQ Geofence',
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

            $punchReq = Request::create('/api/v1/attendance/punch', 'POST', [
                'lat' => 23.8103,
                'lng' => 90.4125,
                'location' => 'HQ Toll Plaza Entrance',
                'photo' => 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==',
            ]);
            $punchReq->headers->set('Authorization', 'Bearer ' . $token);
            $punchReq->headers->set('Accept', 'application/json');
            $punchReq->headers->set('X-Device-Id', 'audit-device-deep-001');

            $punchResp = $app->handle($punchReq);
            $pStatus = $punchResp->getStatusCode();

            if ($pStatus >= 200 && $pStatus < 400) {
                $this->line(" [PASS] POST /api/v1/attendance/punch => Status $pStatus");
                $passedTests++;
            } else {
                $pContent = substr($punchResp->getContent(), 0, 150);
                $this->error(" [FAIL] POST /api/v1/attendance/punch => Status $pStatus: $pContent");
                $failedTests++;
                $failures[] = ['category' => 'Punch', 'test' => 'POST /api/v1/attendance/punch', 'error' => "Status $pStatus: $pContent"];
            }
        } catch (\Throwable $e) {
            $this->error(" [CRASH] POST /api/v1/attendance/punch => Exception: " . $e->getMessage());
            $failedTests++;
            $failures[] = ['category' => 'Punch', 'test' => 'POST /api/v1/attendance/punch', 'error' => $e->getMessage()];
        }

        // 5. Test Web Routes & Controller Handlers
        $this->newLine();
        $this->info('--- 4. Testing Web Routes & Controllers ---');

        $deviceId = 'a1b2c3d4-e5f6-47a8-b9c0-d1e2f3a4b5c6';
        $dummyReq = Request::create('/', 'GET');
        $dummyReq->headers->set('User-Agent', 'Audit System Deep Browser');
        $deviceService = $app->make(DeviceAuthService::class);
        $deviceService->registerDevice($user, $dummyReq, $deviceId, ['platform' => 'desktop'], 'Audit Browser');

        $session = $app->make('session.store');
        $session->start();
        $session->put('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d', $user->getAuthIdentifier());
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
            $totalTests++;
            try {
                Auth::login($user);
                $req = Request::create($uri, 'GET');
                $version = \Inertia\Inertia::getVersion();
                $req->setLaravelSession($session);
                $req->setUserResolver(fn() => $user);
                $req->headers->set('Accept', 'application/json, text/plain, */*');
                $req->headers->set('X-Inertia', 'true');
                if ($version) {
                    $req->headers->set('X-Inertia-Version', $version);
                }
                $req->headers->set('X-Requested-With', 'XMLHttpRequest');
                $req->headers->set('X-Device-Id', $deviceId);
                $req->cookies->set('device_id', $deviceId);

                $resp = $app->handle($req);
                $status = $resp->getStatusCode();

                if ($status >= 200 && $status < 400) {
                    $this->line(" [PASS] GET $uri => Status $status");
                    $passedTests++;
                } else {
                    $content = substr($resp->getContent(), 0, 150);
                    $this->error(" [FAIL] GET $uri => Status $status: $content");
                    $failedTests++;
                    $failures[] = ['category' => 'Web', 'test' => "GET $uri", 'error' => "Status $status: $content"];
                }
            } catch (\Throwable $e) {
                $this->error(" [CRASH] GET $uri => Exception: " . $e->getMessage());
                $failedTests++;
                $failures[] = ['category' => 'Web', 'test' => "GET $uri", 'error' => $e->getMessage()];
            }
        }

        // 6. Summary Report
        $this->newLine();
        $this->info('=====================================================');
        $this->info("AUDIT SUMMARY: $passedTests / $totalTests Passed (" . round(($passedTests / max(1, $totalTests)) * 100, 1) . "%)");
        if ($failedTests > 0) {
            $this->error("Failed Tests: $failedTests");
            foreach ($failures as $f) {
                $this->line(" - [{$f['category']}] {$f['test']}: {$f['error']}");
            }
            $this->info('=====================================================');
            return 1;
        } else {
            $this->info("ALL TESTS PASSED WITH ZERO ERRORS! 100% HEALTHY.");
            $this->info('=====================================================');
            return 0;
        }
    }
}
