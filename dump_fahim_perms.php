<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;

$user = User::where('email', 'fahim@dhakabypass.com')->first();
echo "User: " . $user->name . "\n";
echo "Roles: " . implode(', ', $user->getRoleNames()->toArray()) . "\n";
echo "Can designations.view? " . ($user->can('designations.view') ? 'YES' : 'NO') . "\n";
echo "Can quality.ncr.view? " . ($user->can('quality.ncr.view') ? 'YES' : 'NO') . "\n";
echo "Can holidays.view? " . ($user->can('holidays.view') ? 'YES' : 'NO') . "\n";

$allPerms = $user->getAllPermissions()->pluck('name')->toArray();
echo "Total Permissions Count: " . count($allPerms) . "\n";
echo "All Permissions: " . implode(', ', $allPerms) . "\n";
