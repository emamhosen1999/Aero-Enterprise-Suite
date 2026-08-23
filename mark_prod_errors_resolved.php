<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$count = App\Models\ClientErrorLog::whereNull('resolved_at')->count();
App\Models\ClientErrorLog::whereNull('resolved_at')->update([
    'resolved_at' => now(),
    'resolved_by' => 1,
]);

echo "Successfully marked {$count} client diagnostic error groups as resolved.\n";
