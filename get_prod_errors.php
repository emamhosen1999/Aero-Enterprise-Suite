<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$errors = App\Models\ClientErrorLog::whereNull('resolved_at')
    ->orderByDesc('last_seen_at')
    ->take(30)
    ->get(['id', 'error_type', 'message', 'severity', 'source', 'screen', 'path', 'count', 'last_seen_at', 'file', 'line', 'status_code', 'context']);

echo json_encode($errors, JSON_PRETTY_PRINT);
