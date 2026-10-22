<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\XishieProject;
use Filament\Widgets\Widget;

/**
 * Retro KPI Bar: kartu metrik utama di bagian atas workspace.
 */
class WorkspaceKpiBarWidget extends Widget
{
    protected string $view = 'filament.widgets.workspace-kpi-bar';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    public ?int $projectId = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->projectId !== null ? XishieProject::find($this->projectId) : XishieProject::query()->latest()->first();

        if ($project === null) {
            return ['project' => null, 'cards' => []];
        }

        $service = app(UxKpiTelemetryService::class);
        $metrics = $service->computeProjectMetrics($project);

        $keys = [
            UxKpiMetric::GUIDELINE_COMPLIANCE_SCORE,
            UxKpiMetric::VERIFIED_CITATION_DENSITY,
            UxKpiMetric::REVISION_RESOLUTION_VELOCITY,
            UxKpiMetric::JOURNAL_MATCH_CONFIDENCE_SCORE,
            UxKpiMetric::AGENT_HALLUCINATION_RATE,
        ];

        $cards = [];

        foreach ($keys as $key) {
            $data = $metrics[$key->value];
            $cards[] = [
                'key' => $key->value,
                'label' => $data['label'],
                'value' => $data['value'],
                'unit' => $data['unit'],
                'healthy' => $data['healthy'],
            ];
        }

        return [
            'project' => $project,
            'cards' => $cards,
        ];
    }
}
