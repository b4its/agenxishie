<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\XishieProject;
use Filament\Widgets\Widget;

/**
 * HeartKpiTelemetryWidget: metrik kerangka HEART (Happiness, Adoption, Engagement).
 */
class HeartKpiTelemetryWidget extends Widget
{
    protected string $view = 'filament.widgets.kpi-framework-table';

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public string $framework = 'HEART';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = XishieProject::query()->latest()->first();

        if ($project === null) {
            return ['title' => 'HEART FRAMEWORK', 'rows' => [], 'framework' => $this->framework];
        }

        $summary = app(UxKpiTelemetryService::class)->dashboardSummary($project);
        $group = match ($this->framework) {
            'AARRR' => $summary['aarrr'],
            'UX' => $summary['ux'],
            default => $summary['heart'],
        };

        $rows = [];

        foreach ($group as $key => $data) {
            $rows[] = [
                'label' => $data['label'],
                'value' => $data['value'],
                'unit' => $data['unit'],
                'healthy' => $data['healthy'],
            ];
        }

        return [
            'title' => $this->framework.' FRAMEWORK',
            'rows' => $rows,
            'framework' => $this->framework,
        ];
    }
}
