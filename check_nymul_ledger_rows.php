<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use App\Models\HRM\LeaveLedger;
use App\Models\HRM\LeaveSetting;

$nymul = User::where('name', 'like', '%Nymul%')->first();
echo "Nymul ID: {$nymul->id}\n";

$rows = LeaveLedger::where('user_id', $nymul->id)->get();
echo "Total Ledger Rows for Nymul: " . $rows->count() . "\n";
foreach ($rows as $r) {
    $type = LeaveSetting::find($r->leave_type);
    echo "ID: {$r->id} | TypeID: {$r->leave_type} ({$type?->type}) | Year: {$r->period_year} | Txn: {$r->txn_type} | Amount: {$r->amount} | Balance After: {$r->balance_after} | Reason: {$r->reason}\n";
}
