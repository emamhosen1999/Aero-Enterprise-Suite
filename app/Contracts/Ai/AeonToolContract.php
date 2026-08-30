<?php

declare(strict_types=1);

namespace App\Contracts\Ai;

/**
 * A tool Aeon can invoke to fetch data or perform operations in DBEDC Guardian.
 *
 * run() returns:
 *   [
 *     'text' => string, // Summary text for fallback/log
 *     'blocks' => array<int, array<string, mixed>>, // Generative UI blocks (stats, table, chart, form, action)
 *     'data' => array<string, mixed>, // Compact machine-readable payload fed BACK to the model for multi-step reasoning
 *     'terminal' => bool // If true, pauses agent loop because an action/form is presented to the user
 *   ]
 */
interface AeonToolContract
{
    /** Unique function name the AI calls (e.g. query_data, prepare_operation). */
    public function name(): string;

    /** Concise description of what the tool does and when the model should use it. */
    public function description(): string;

    /**
     * JSON Schema properties for tool arguments.
     *
     * @return array<string, mixed>
     */
    public function parameters(): array;

    /**
     * Execute the tool for the given user.
     *
     * @param  array<string, mixed>  $args
     * @return array{text: string, blocks: array<int, array<string, mixed>>, data?: array<string, mixed>, terminal?: bool}
     */
    public function run(array $args, ?int $userId): array;
}
