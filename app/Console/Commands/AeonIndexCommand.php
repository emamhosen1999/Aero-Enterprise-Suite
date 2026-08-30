<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Aeon\IndexingService;
use Illuminate\Console\Command;

class AeonIndexCommand extends Command
{
    protected $signature = 'aeon:index {--fresh : Clear the existing vector index and re-index from scratch}';

    protected $description = "Build Aeon AI Copilot's vector knowledge base (Guardian modules + database schema)";

    public function handle(IndexingService $indexer): int
    {
        $fresh = (bool) $this->option('fresh');
        $this->info('Indexing DBEDC Guardian knowledge base'.($fresh ? ' (fresh rebuild)...' : '...'));

        $result = $indexer->index($fresh);

        $this->line("  Total Sources Gathered: {$result['sources']}");
        $this->line("  Vector Chunks Embedded: {$result['indexed']}");
        $this->line("  Skipped (Unchanged):    {$result['skipped']}");
        $this->info('Aeon knowledge base indexing complete.');

        return self::SUCCESS;
    }
}
