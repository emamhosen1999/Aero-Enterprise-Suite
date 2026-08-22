<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

echo "=== PRODUCTION SYSTEM AUDIT ===\n";

$user = User::whereNotNull('email')->first();
if (!$user) {
    echo "No users found in database!\n";
    exit(1);
}

echo "Authenticated user for test: {$user->name} ({$user->employee_id} / {$user->email})\n";

// Test Sanctum token creation
$token = $user->createToken('Audit Test Token')->plainTextToken;
echo "Sanctum Token generated successfully: " . substr($token, 0, 15) . "...\n\n";

// Endpoints to test
$endpoints = [
    ['GET', '/api/v1/auth/me'],
    ['GET', '/api/v1/profile'],
    ['GET', '/api/v1/config'],
    ['GET', '/api/v1/sync/bootstrap'],
    ['GET', '/api/v1/attendance/today'],
    ['GET', '/api/v1/attendance/history'],
    ['GET', '/api/v1/daily-works'],
    ['GET', '/api/v1/daily-works/selectable-dates'],
    ['GET', '/api/v1/daily-works/objections/metadata'],
    ['GET', '/api/v1/daily-works/objections/my'],
    ['GET', '/api/v1/leaves'],
    ['GET', '/api/v1/leaves/summary'],
    ['GET', '/api/v1/leave-types'],
    ['GET', '/api/v1/notifications'],
    ['GET', '/api/v1/notifications/unread-count'],
    ['GET', '/api/v1/shifts'],
    ['GET', '/api/v1/roster/current-month'],
    ['GET', '/api/v1/shift-swaps/available-counterparts'],
];

$passCount = 0;
$failCount = 0;

foreach ($endpoints as [$method, $uri]) {
    $req = Request::create($uri, $method);
    $req->headers->set('Authorization', 'Bearer ' . $token);
    $req->headers->set('Accept', 'application/json');
    $req->headers->set('X-Device-Id', 'audit-device-001');

    $response = $app->handle($req);
    $status = $response->getStatusCode();

    if ($status >= 200 && $status < 400) {
        echo " [PASS] $method $uri => Status $status\n";
        $passCount++;
    } else {
        echo " [FAIL] $method $uri => Status $status\n";
        $content = substr($response->getContent(), 0, 200);
        echo "        Error: $content\n";
        $failCount++;
    }
}

echo "\nSummary: $passCount passed, $failCount failed.\n";
