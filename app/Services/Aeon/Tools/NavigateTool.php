<?php

declare(strict_types=1);

namespace App\Services\Aeon\Tools;

use App\Contracts\Ai\AeonToolContract;
use Illuminate\Support\Str;

/**
 * Validated navigation tool that routes users directly to Guardian modules and pages.
 */
class NavigateTool implements AeonToolContract
{
    public function name(): string
    {
        return 'navigate';
    }

    public function description(): string
    {
        return 'Take the user directly to a specific module, page, or dashboard in DBEDC Guardian.';
    }

    public function parameters(): array
    {
        return [
            'destination' => [
                'type' => 'string',
                'description' => 'Target module or page (e.g. "attendance", "objections", "ncrs", "daily_works", "leaves", "petty_cash", "om_dashboard", "roles", "biometrics")',
            ],
            'reason' => [
                'type' => 'string',
                'description' => 'Why this navigation is recommended',
            ],
        ];
    }

    public function run(array $args, ?int $userId): array
    {
        $dest = strtolower(trim((string) ($args['destination'] ?? '')));
        $modules = (array) config('modules', []);

        $target = null;
        if (isset($modules[$dest])) {
            $target = $modules[$dest];
        } else {
            foreach ($modules as $key => $mod) {
                if (str_contains($key, $dest) || in_array($dest, $mod['keywords'] ?? [], true)) {
                    $target = $mod;
                    break;
                }
            }
        }

        if (! $target) {
            // Direct URL path check
            if (str_starts_with($dest, '/')) {
                $target = ['name' => Str::headline(trim($dest, '/')), 'route' => $dest];
            }
        }

        if (! $target) {
            return [
                'text' => "Could not locate navigation path for '{$dest}'.",
                'blocks' => [],
                'data' => ['status' => 'error', 'destination' => $dest],
                'terminal' => false,
            ];
        }

        $routeName = (string) ($target['name'] ?? Str::headline($dest));
        $routeUrl = (string) ($target['route'] ?? '/');

        return [
            'text' => "Navigating to {$routeName} ({$routeUrl}).",
            'blocks' => [
                [
                    'type' => 'action',
                    'kind' => 'navigate',
                    'title' => "Go to {$routeName}",
                    'desc' => $args['reason'] ?? "Open the {$routeName} page",
                    'route' => $routeUrl,
                    'confirm_label' => 'Open Page →',
                ],
            ],
            'data' => ['status' => 'success', 'route' => $routeUrl],
            'terminal' => true,
        ];
    }
}
