<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$errors = App\Models\ClientErrorLog::whereNull('resolved_at')
    ->orderByDesc('id')
    ->get(['id', 'error_type', 'message', 'source', 'path', 'screen', 'file', 'line', 'status_code', 'count', 'last_seen_at']);

foreach ($errors as $e) {
    echo "ID: {$e->id} | Source: {$e->source} | Type: {$e->error_type} | Count: {$e->count}\n";
    echo "Path: {$e->path} | Screen: {$e->screen} | File: {$e->file}:{$e->line}\n";
    echo "Message: " . substr($e->message, 0, 150) . "\n";
    echo str_repeat('-', 80) . "\n";
}
