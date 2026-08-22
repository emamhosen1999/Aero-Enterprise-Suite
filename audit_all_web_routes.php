<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

echo "=== WEB ROUTES PRODUCTION AUDIT ===\n";

$admin = User::whereNotNull('email')->first();
if (!$admin) {
    echo "No admin user found!\n";
    exit(1);
}

Auth::login($admin);
echo "Acting as admin user: {$admin->name} ({$admin->employee_id})\n\n";

$routes = [
    '/dashboard',
    '/employees',
    '/users/paginate',
    '/users/stats',
    '/departments',
    '/designations',
    '/roles',
    '/roles-permissions',
    '/organization',
    '/holidays',
    '/leaves',
    '/leaves-paginate',
    '/leaves-stats',
    '/leaves/analytics',
    '/leaves/pending-approvals',
    '/leaves/bulk/calendar-data',
    '/leaves/bulk/leave-types',
    '/leave-summary',
    '/attendance',
    '/attendance-employee',
    '/attendance/admin/dashboard',
    '/attendance/admin/records',
    '/attendance/admin/settings',
    '/attendance/admin/shifts',
    '/attendance/admin/roster',
    '/attendance/admin/shift-swaps',
    '/attendance/admin/regularizations',
    '/attendance/admin/overtime',
    '/attendance/admin/comp-off',
    '/attendance/daily-timesheet',
    '/timesheet',
    '/daily-works',
    '/daily-works-json',
    '/daily-works-summary',
    '/reports',
    '/reports-json',
    '/stats',
    '/tasks-all',
    '/tasks/daily-summary-json',
    '/work-location',
    '/work-location_json',
    '/profiles/search',
    '/quality/ncr',
    '/petty-cash',
    '/petty-cash/transactions',
    '/petty-cash/history',
    '/petty-cash/categories',
    '/petty-cash/analytics',
    '/petty-cash/admin/overview',
    '/petty-cash/audit-log',
    '/my-devices',
    '/letters',
    '/letters-paginate',
    '/notifications',
    '/notifications/list',
    '/notifications/unread-count',
    '/workspace/objections',
    '/settings/biometric-devices',
    '/settings/biometric-devices/active',
    '/settings/biometric-devices/attlogs',
    '/settings/biometric-devices/download-history',
    '/settings/biometric-devices/health',
    '/settings/biometric-devices/logs',
    '/settings/biometric-devices/operlogs',
    '/settings/biometric-devices/templates',
    '/settings/notifications',
    '/settings/notifications/list',
    '/settings/request-logs',
    '/settings/request-logs/list',
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

$passCount = 0;
$failCount = 0;
$serverErrors = [];

$deviceId = 'audit-web-device-001';
$session = $app->make('session.store');
$session->start();
$session->put('login_web_59ba36addc2b2f9401580f014c7f58ea4e30989d', $admin->getAuthIdentifier());
$session->put('device_id', $deviceId);
$session->put('device_verified', true);

// Ensure user has device record
\App\Models\UserDevice::updateOrCreate([
    'user_id' => $admin->id,
    'device_id' => $deviceId,
], [
    'device_name' => 'Audit Browser',
    'device_type' => 'desktop',
    'device_token' => hash('sha256', 'audit-device-token'),
    'is_active' => true,
    'last_active_at' => now(),
]);

foreach ($routes as $uri) {
    Auth::login($admin);
    $req = Request::create($uri, 'GET');
    $req->setLaravelSession($session);
    $req->setUserResolver(fn() => $admin);
    $req->headers->set('Accept', 'application/json, text/plain, */*');
    $req->headers->set('X-Inertia', 'true');
    $req->headers->set('X-Inertia-Version', '');
    $req->headers->set('X-Requested-With', 'XMLHttpRequest');
    $req->headers->set('X-Device-Id', $deviceId);
    $req->cookies->set('device_id', $deviceId);

    try {
        $response = $app->handle($req);
        $status = $response->getStatusCode();

        if ($status >= 200 && $status < 400) {
            echo " [PASS] GET $uri => Status $status\n";
            $passCount++;
        } else {
            echo " [FAIL] GET $uri => Status $status\n";
            $content = substr($response->getContent(), 0, 200);
            echo "        Output: $content\n";
            $failCount++;
            if ($status >= 500) {
                $serverErrors[] = ['uri' => $uri, 'status' => $status, 'error' => $content];
            }
        }
    } catch (\Throwable $e) {
        echo " [CRASH] GET $uri => Exception: {$e->getMessage()}\n";
        $failCount++;
        $serverErrors[] = ['uri' => $uri, 'status' => 500, 'error' => $e->getMessage()];
    }
}

echo "\n=============================================\n";
echo "Web Audit Summary: $passCount passed, $failCount failed.\n";
if (!empty($serverErrors)) {
    echo "500 Server Errors detected:\n";
    foreach ($serverErrors as $err) {
        echo "  - {$err['uri']} => {$err['error']}\n";
    }
} else {
    echo "ZERO 500 Server Errors! All pages render cleanly.\n";
}
echo "=============================================\n";
