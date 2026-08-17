<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Services\Leave\LeaveAccrualService;

$nymul = User::where('name', 'like', '%Nymul%')->first();
echo "Nymul ID: {$nymul->id}, Name: {$nymul->name}, Joining Date: " . var_export($nymul->date_of_joining, true) . "\n";

$accrualService = app(LeaveAccrualService::class);
$posted = $accrualService->grantAnnual(2026, $nymul->id);
echo "Posted Annual Entitlement Rows: {$posted}\n";

$ledgers = \App\Models\HRM\LeaveLedger::where('user_id', $nymul->id)->get();
foreach ($ledgers as $l) {
    echo " -> ID: {$l->id}, Type: {$l->leave_type}, Year: {$l->period_year}, Txn: {$l->txn_type}, Amount: {$l->amount}, Balance: {$l->balance_after}\n";
}
