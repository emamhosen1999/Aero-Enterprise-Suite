<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\HRM\LeaveSetting;

$settings = LeaveSetting::all();
echo "Total LeaveSettings: " . $settings->count() . "\n";
foreach ($settings as $s) {
    echo "ID: {$s->id} | Type: {$s->type} | Days: {$s->days} | Accrual Method: '{$s->accrual_method}' | Allow Negative: '{$s->allow_negative}'\n";
}
