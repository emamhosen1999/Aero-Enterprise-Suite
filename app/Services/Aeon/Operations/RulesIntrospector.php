<?php

declare(strict_types=1);

namespace App\Services\Aeon\Operations;

use App\Services\Aeon\Data\SchemaCatalog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * Extracts validation contracts for write routes in DBEDC Guardian in three layers:
 *   1. FormRequest reflection (reads FormRequest::rules())
 *   2. Inline $request->validate() source code extraction
 *   3. Database schema fallback
 */
class RulesIntrospector
{
    private const SKIP = ['_token', '_method', 'g-recaptcha-response', 'password_confirmation', 'id'];

    public function __construct(private SchemaCatalog $schema) {}

    /**
     * @return array<string, array{required: bool, rules: array<int, string>}>
     */
    public function forAction(string $controller, string $method, ?string $fallbackTable = null): array
    {
        $rules = $this->fromFormRequest($controller, $method);

        if (empty($rules)) {
            $rules = $this->fromInlineValidate($controller, $method);
        }

        $out = $this->normalise($rules);

        if (empty($out) && $fallbackTable) {
            $out = $this->fromSchema($fallbackTable);
        }

        return $out;
    }

    /**
     * Layer 1: Reflect FormRequest parameter.
     */
    private function fromFormRequest(string $controller, string $method): array
    {
        try {
            if (! class_exists($controller)) {
                return [];
            }
            $rm = new \ReflectionMethod($controller, $method);
            foreach ($rm->getParameters() as $p) {
                $t = $p->getType();
                if (! $t || $t->isBuiltin()) {
                    continue;
                }
                $cn = $t->getName();
                if (is_subclass_of($cn, FormRequest::class)) {
                    $inst = new $cn();
                    if (method_exists($inst, 'rules')) {
                        $r = $inst->rules();
                        return is_array($r) ? $r : [];
                    }
                }
            }
        } catch (\Throwable) {
            // Fall through
        }

        return [];
    }

    /**
     * Layer 2: Heuristic inline validation scanner.
     */
    private function fromInlineValidate(string $controller, string $method): array
    {
        try {
            if (! class_exists($controller)) {
                return [];
            }
            $rm = new \ReflectionMethod($controller, $method);
            $file = $rm->getFileName();
            if (! $file || ! is_readable($file)) {
                return [];
            }
            $lines = file($file);
            $body = implode('', array_slice($lines, $rm->getStartLine() - 1, $rm->getEndLine() - $rm->getStartLine() + 1));

            $anchor = null;
            foreach (['->validate(', 'Validator::make('] as $needle) {
                $pos = strpos($body, $needle);
                if ($pos !== false) {
                    $anchor = $pos + strlen($needle);
                    break;
                }
            }
            if ($anchor === null) {
                return [];
            }

            $arr = $this->extractBracketArray(substr($body, $anchor));
            if ($arr === null) {
                return [];
            }

            return $this->parseRuleArray($arr);
        } catch (\Throwable) {
            return [];
        }
    }

    private function extractBracketArray(string $fragment): ?string
    {
        $start = strpos($fragment, '[');
        if ($start === false) {
            return null;
        }
        $depth = 0;
        $len = strlen($fragment);
        for ($i = $start; $i < $len; $i++) {
            $ch = $fragment[$i];
            if ($ch === '[') {
                $depth++;
            } elseif ($ch === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($fragment, $start + 1, $i - $start - 1);
                }
            }
        }

        return null;
    }

    private function parseRuleArray(string $src): array
    {
        $out = [];
        if (preg_match_all("/['\"]([A-Za-z0-9_\.]+)['\"]\s*=>\s*\[([^\]]*)\]/", $src, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                preg_match_all("/['\"]([^'\"]+)['\"]/", $m[2], $toks);
                $out[$m[1]] = $toks[1] ?? [];
            }
        }
        if (preg_match_all("/['\"]([A-Za-z0-9_\.]+)['\"]\s*=>\s*['\"]([^'\"]+)['\"]/", $src, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) {
                if (! array_key_exists($m[1], $out)) {
                    $out[$m[1]] = $m[2];
                }
            }
        }

        return $out;
    }

    /**
     * Layer 3: Database schema column fallback.
     */
    private function fromSchema(string $table): array
    {
        $entity = $this->schema->entity($table);
        if (! $entity) {
            return [];
        }
        $auto = ['id', 'created_at', 'updated_at', 'deleted_at', 'created_by', 'updated_by', 'deleted_by', 'tenant_id'];
        $out = [];
        foreach ($entity['columns'] as $col) {
            if (in_array($col, $auto, true) || $this->schema->isSensitive($col)) {
                continue;
            }
            $rules = [];
            if (Str::endsWith($col, '_id')) {
                $rules[] = 'exists:'.Str::plural(Str::beforeLast($col, '_id')).',id';
            } elseif (Str::endsWith($col, ['_at', '_date']) || $col === 'date') {
                $rules[] = 'date';
            } elseif (Str::startsWith($col, ['is_', 'has_'])) {
                $rules[] = 'boolean';
            }
            $out[$col] = ['required' => false, 'rules' => $rules];
        }

        return $out;
    }

    private function normalise(array $rules): array
    {
        $out = [];
        foreach ($rules as $field => $spec) {
            if (! is_string($field) || $field === '' || str_contains($field, '.') || str_contains($field, '*')) {
                continue;
            }
            if (in_array($field, self::SKIP, true)) {
                continue;
            }
            $tokens = is_array($spec) ? $spec : explode('|', (string) $spec);
            $strings = [];
            foreach ($tokens as $t) {
                if (is_string($t) && $t !== '') {
                    $strings[] = $t;
                }
            }
            $out[$field] = [
                'required' => in_array('required', $strings, true),
                'rules' => $strings,
            ];
        }

        return $out;
    }
}
