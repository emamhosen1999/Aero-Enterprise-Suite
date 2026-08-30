<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$columns = Illuminate\Support\Facades\Schema::getColumnListing('client_error_logs');
echo "=== TABLE COLUMNS ===\n";
echo implode(', ', $columns) . "\n\n";

$errors = Illuminate\Support\Facades\DB::table('client_error_logs')
    ->orderByDesc('last_seen_at')
    ->limit(15)
    ->get();

echo "=== RECENT CLIENT DIAGNOSTICS ERRORS ===\n\n";
foreach ($errors as $e) {
    $row = (array) $e;
    foreach ($row as $k => $v) {
        if (is_string($v) && strlen($v) > 200) {
            $v = substr($v, 0, 200) . '...';
        }
        echo "  $k: $v\n";
    }
    echo str_repeat('-', 80) . "\n";
}
