<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\HRM\Leave;
use App\Models\HRM\LeaveSetting;
use App\Models\HRM\LeaveLedger;
use App\Services\Leave\LeaveQueryService;

$nymul = User::where('name', 'like', '%Nymul%')->first();
$casual = LeaveSetting::where('type', 'like', '%Casual%')->first();

echo "User ID: {$nymul->id}\n";
echo "Casual LeaveSetting ID: {$casual->id}, days: {$casual->days}\n\n";

// Existing leaves for Nymul
$leaves = Leave::where('user_id', $nymul->id)->get();
echo "Total Leaves for Nymul: " . $leaves->count() . "\n";
foreach ($leaves as $l) {
    echo " -> ID: {$l->id}, Type: {$l->leave_type}, From: {$l->from_date}, To: {$l->to_date}, Days: {$l->days_count}, Status: {$l->status}\n";
}

echo "\nLedger DB Records:\n";
$ledgers = LeaveLedger::where('user_id', $nymul->id)->get();
foreach ($ledgers as $lg) {
    echo " -> ID: {$lg->id}, TypeID: {$lg->leave_type_id}, Year: {$lg->year}, Accrued: {$lg->accrued_days}, Used: {$lg->used_days}, Pending: {$lg->pending_days}\n";
}

// Check how frontend/query service calculates balances
$req = new \Illuminate\Http\Request(['user_id' => $nymul->id]);
$queryService = app(LeaveQueryService::class);
$balances = $queryService->getLeaveBalancesForDashboard($req);
echo "\nQueryService Balances Output for Nymul:\n";
print_r($balances);
