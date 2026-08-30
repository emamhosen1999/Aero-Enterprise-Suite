<?php

declare(strict_types=1);

namespace App\Services\Aeon\Providers;

use App\Contracts\Ai\AiChatResult;
use App\Contracts\Ai\AiProvider;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Universal OpenAI-compatible AI model driver.
 * Works against OpenAI, DeepSeek, OpenRouter, Groq, Ollama, LM Studio, vLLM.
 */
class OpenAiCompatProvider implements AiProvider
{
    private string $key;
    private string $model;
    private string $base;
    private int $timeout;

    public function __construct()
    {
        $cfg = (array) config('aeon.providers.openai', []);
        $this->key = (string) ($cfg['api_key'] ?? '');
        $this->model = (string) ($cfg['model'] ?? 'gpt-4o-mini');
        $this->base = rtrim((string) ($cfg['base_url'] ?? 'https://api.openai.com/v1'), '/');
        $this->timeout = (int) ($cfg['timeout'] ?? 45);
    }

    public function chat(array $messages, array $tools = [], array $options = []): AiChatResult
    {
        $wire = [];
        $turn = 0;
        $pendingIds = [];

        foreach ($messages as $m) {
            $role = $m['role'] ?? 'user';

            if ($role === 'tool') {
                foreach (array_values((array) ($m['results'] ?? [])) as $i => $r) {
                    $wire[] = [
                        'role' => 'tool',
                        'tool_call_id' => $pendingIds[$i] ?? ('call_'.$turn.'_'.$i),
                        'content' => json_encode($r['response'] ?? [], JSON_UNESCAPED_UNICODE),
                    ];
                }
                continue;
            }

            $entry = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];

            if ($role === 'assistant' && ! empty($m['tool_calls'])) {
                $turn++;
                $pendingIds = [];
                $entry['tool_calls'] = [];

                foreach (array_values((array) $m['tool_calls']) as $i => $call) {
                    $id = 'call_'.$turn.'_'.$i;
                    $pendingIds[] = $id;
                    $entry['tool_calls'][] = [
                        'id' => $id,
                        'type' => 'function',
                        'function' => [
                            'name' => (string) ($call['name'] ?? ''),
                            'arguments' => json_encode($call['args'] ?? [], JSON_UNESCAPED_UNICODE),
                        ],
                    ];
                }
                if ($entry['content'] === '') {
                    $entry['content'] = null;
                }
            }

            $wire[] = $entry;
        }

        $payload = [
            'model' => $this->model,
            'messages' => $wire,
            'temperature' => (float) ($options['temperature'] ?? config('aeon.providers.openai.temperature', 0.3)),
            'max_tokens' => (int) ($options['max_tokens'] ?? config('aeon.providers.openai.max_tokens', 4096)),
        ];

        if (! empty($tools)) {
            $payload['tools'] = array_map(static fn ($t) => [
                'type' => 'function',
                'function' => [
                    'name' => $t['name'],
                    'description' => $t['description'] ?? '',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => (object) ($t['parameters'] ?? []),
                    ],
                ],
            ], $tools);
        }

        try {
            $req = Http::timeout($this->timeout)->retry(2, 500, throw: false);
            if (! empty($this->key)) {
                $req = $req->withToken($this->key);
            }

            $res = $req->post("{$this->base}/chat/completions", $payload);

            if ($res->failed()) {
                $err = (string) data_get($res->json(), 'error.message', 'HTTP '.$res->status());
                return AiChatResult::failed('AI API error: '.$err, $this->model);
            }

            $json = $res->json();
            $msg = (array) data_get($json, 'choices.0.message', []);
            $toolCalls = [];

            foreach ((array) ($msg['tool_calls'] ?? []) as $tc) {
                $args = json_decode((string) data_get($tc, 'function.arguments', '{}'), true);
                $toolCalls[] = [
                    'name' => (string) data_get($tc, 'function.name', ''),
                    'args' => is_array($args) ? $args : [],
                ];
            }

            return new AiChatResult(
                content: (string) ($msg['content'] ?? ''),
                toolCalls: $toolCalls,
                tokensUsed: (int) data_get($json, 'usage.total_tokens', 0),
                model: (string) data_get($json, 'model', $this->model),
            );
        } catch (\Throwable $e) {
            Log::error('Aeon OpenAI provider chat failed', ['error' => $e->getMessage()]);
            return AiChatResult::failed($e->getMessage(), $this->model);
        }
    }

    public function embed(array $texts, array $options = []): array
    {
        $embedModel = (string) config('aeon.providers.openai.embed_model', 'text-embedding-3-small');
        try {
            $req = Http::timeout($this->timeout);
            if (! empty($this->key)) {
                $req = $req->withToken($this->key);
            }

            $res = $req->post("{$this->base}/embeddings", [
                'model' => $embedModel,
                'input' => array_values($texts),
            ]);

            if ($res->failed()) {
                return array_fill(0, count($texts), []);
            }

            $rows = (array) data_get($res->json(), 'data', []);
            usort($rows, static fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

            return array_map(static fn ($r) => (array) ($r['embedding'] ?? []), $rows);
        } catch (\Throwable $e) {
            Log::error('Aeon OpenAI embed failed', ['error' => $e->getMessage()]);
            return array_fill(0, count($texts), []);
        }
    }

    public function isAvailable(): bool
    {
        try {
            $req = Http::timeout(5);
            if (! empty($this->key)) {
                $req = $req->withToken($this->key);
            }

            return $req->get("{$this->base}/models")->successful();
        } catch (\Throwable) {
            return false;
        }
    }
}
