<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\HRM\LeaveSetting;
use App\Services\Leave\LeaveLedgerService;
use App\Services\Leave\LeaveAccrualService;

$nymul = User::where('name', 'like', '%Nymul%')->first();
if (!$nymul) {
    echo "Nymul not found!\n";
    exit;
}

echo "User: {$nymul->name} (ID: {$nymul->id})\n";

$casualSetting = LeaveSetting::where('type', 'like', '%Casual%')->first();
echo "Casual Leave Setting: ID {$casualSetting?->id}, Type: {$casualSetting?->type}, Days: {$casualSetting?->days}, Allow Negative: " . ($casualSetting?->allow_negative ? 'YES' : 'NO') . "\n";

$ledger = app(LeaveLedgerService::class);
$year = 2026;
$date = \Carbon\Carbon::parse('2026-07-24');

$isTracked = $ledger->isTracked($nymul->id, $casualSetting->id, $year);
echo "Is Tracked for 2026? " . ($isTracked ? 'YES' : 'NO') . "\n";

if (!$isTracked) {
    echo "Seeding ledger for 2026...\n";
    app(LeaveAccrualService::class)->seedFor($nymul->id, $year);
    $isTracked = $ledger->isTracked($nymul->id, $casualSetting->id, $year);
    echo "Is Tracked after seed? " . ($isTracked ? 'YES' : 'NO') . "\n";
}

$available = $ledger->available($nymul->id, $casualSetting->id, $date);
echo "Available Casual Leave Balance for {$nymul->name} on 2026-07-24: {$available}\n";

$records = \App\Models\HRM\LeaveLedger::where('user_id', $nymul->id)->get();
echo "Ledger Records Count: " . $records->count() . "\n";
foreach ($records as $r) {
    echo " -> Type: {$r->leave_type_id}, Year: {$r->year}, Accrued: {$r->accrued_days}, Used: {$r->used_days}, Pending: {$r->pending_days}, Carried: {$r->carried_forward_days}\n";
}
