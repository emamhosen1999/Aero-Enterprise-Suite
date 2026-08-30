<?php

declare(strict_types=1);

namespace App\Services\Aeon\Tools;

use App\Contracts\Ai\AeonToolContract;
use App\Services\Aeon\Data\QueryTool;

/**
 * Discovers, registers, and routes calls to all active Aeon tools.
 */
class ToolRegistry
{
    /** @var array<string, AeonToolContract> */
    private array $tools = [];

    public function __construct(
        QueryTool $queryTool,
        PrepareOperationTool $prepareOperationTool,
        NavigateTool $navigateTool,
        ExpresswayIntelligenceTool $expresswayTool,
        QualityAssuranceTool $qaTool,
        HumanResourcesTool $hrmTool,
        PettyCashTool $pettyCashTool,
        ExecutiveBriefingTool $executiveBriefingTool,
        AssetMaintenanceTool $assetMaintenanceTool
    ) {
        $this->register($queryTool);
        $this->register($prepareOperationTool);
        $this->register($navigateTool);
        $this->register($expresswayTool);
        $this->register($qaTool);
        $this->register($hrmTool);
        $this->register($pettyCashTool);
        $this->register($executiveBriefingTool);
        $this->register($assetMaintenanceTool);
    }

    public function register(AeonToolContract $tool): void
    {
        $this->tools[$tool->name()] = $tool;
    }

    /**
     * @return array<int, array{name: string, description: string, parameters: array<string, mixed>}>
     */
    public function declarations(): array
    {
        $out = [];
        foreach ($this->tools as $tool) {
            $out[] = [
                'name' => $tool->name(),
                'description' => $tool->description(),
                'parameters' => $tool->parameters(),
            ];
        }

        return $out;
    }

    public function find(string $name): ?AeonToolContract
    {
        return $this->tools[$name] ?? null;
    }

    /**
     * @return array<string, AeonToolContract>
     */
    public function all(): array
    {
        return $this->tools;
    }
}
