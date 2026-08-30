<?php

declare(strict_types=1);

namespace App\Services\Aeon;

use App\Contracts\Ai\AiProvider;
use App\Models\Aeon\Embedding;
use App\Services\Aeon\Data\SchemaCatalog;
use Illuminate\Support\Facades\Log;

/**
 * Builds and updates the vector knowledge index for DBEDC Guardian.
 * Embeds registered modules, live table schemas, and curated documentation.
 */
class IndexingService
{
    public function __construct(
        private AiProvider $provider,
        private SchemaCatalog $schema
    ) {}

    /**
     * Run the knowledge base indexing process.
     *
     * @return array{sources: int, indexed: int, skipped: int}
     */
    public function index(bool $fresh = false): array
    {
        if ($fresh) {
            Embedding::truncate();
        }

        $chunks = $this->gatherChunks();
        $indexed = 0;
        $skipped = 0;

        foreach ($chunks as $chunk) {
            $checksum = sha1($chunk['text']);
            $existing = Embedding::where('source_type', $chunk['type'])
                ->where('source_ref', $chunk['ref'])
                ->first();

            if ($existing && $existing->checksum === $checksum) {
                $skipped++;
                continue;
            }

            $vectors = $this->provider->embed([$chunk['text']]);
            $vector = $vectors[0] ?? [];

            if (empty($vector)) {
                Log::warning('Aeon Indexing failed to embed chunk', ['ref' => $chunk['ref']]);
                continue;
            }

            Embedding::updateOrCreate(
                [
                    'source_type' => $chunk['type'],
                    'source_ref' => $chunk['ref'],
                ],
                [
                    'title' => $chunk['title'],
                    'chunk_text' => $chunk['text'],
                    'vector' => $vector,
                    'dims' => count($vector),
                    'checksum' => $checksum,
                ]
            );

            $indexed++;
        }

        return [
            'sources' => count($chunks),
            'indexed' => $indexed,
            'skipped' => $skipped,
        ];
    }

    /**
     * Gather knowledge chunks across modules, schema catalog, and domain guidance.
     *
     * @return array<int, array{type: string, ref: string, title: string, text: string}>
     */
    private function gatherChunks(): array
    {
        $chunks = [];

        // 1. Registered Guardian Modules
        $modules = (array) config('modules', []);
        foreach ($modules as $code => $mod) {
            $name = (string) ($mod['name'] ?? $code);
            $desc = (string) ($mod['description'] ?? '');
            $route = (string) ($mod['route'] ?? '');
            $keywords = implode(', ', (array) ($mod['keywords'] ?? []));

            $text = "Module: {$name}\nRoute: {$route}\nDescription: {$desc}\nKeywords: {$keywords}";
            $chunks[] = [
                'type' => 'module',
                'ref' => (string) $code,
                'title' => $name,
                'text' => $text,
            ];
        }

        // 2. Live Database Schema
        $tables = $this->schema->all();
        foreach ($tables as $name => $meta) {
            $cols = implode(', ', $meta['columns'] ?? []);
            $text = "Database Table: {$name} ({$meta['label']})\nAvailable Columns: {$cols}";
            $chunks[] = [
                'type' => 'schema',
                'ref' => $name,
                'title' => "Schema: {$meta['label']}",
                'text' => $text,
            ];
        }

        return $chunks;
    }
}
