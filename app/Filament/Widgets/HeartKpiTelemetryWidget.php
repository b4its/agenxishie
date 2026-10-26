<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\XishieProject;
use Filament\Widgets\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * HeartKpiTelemetryWidget: metrik kerangka HEART (Happiness, Adoption, Engagement).
 */
class HeartKpiTelemetryWidget extends Widget
{
    protected string $view = 'filament.widgets.kpi-framework-table';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 1;

    protected static ?int $sort = 2;

    public string $framework = 'HEART';

    public ?int $projectId = null;

    /**
     * @return array<int, string>
     */
    public function projectOptions(): array
    {
        return XishieProject::query()
            ->latest('updated_at')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * Ekspor metrik framework (HEART / AARRR) ke CSV atau JSON.
     */
    public function exportMetrics(string $format = 'csv'): ?StreamedResponse
    {
        $project = $this->projectId !== null
            ? XishieProject::find($this->projectId)
            : XishieProject::query()->latest()->first();

        if ($project === null) {
            return null;
        }

        $summary = app(UxKpiTelemetryService::class)->dashboardSummary($project);
        $group = match ($this->framework) {
            'AARRR' => $summary['aarrr'],
            'UX' => $summary['ux'],
            default => $summary['heart'],
        };

        $format = strtolower($format) === 'json' ? 'json' : 'csv';
        $filename = strtolower($this->framework).'-kpi-metrics-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'json') {
            $data = [
                'framework' => $this->framework,
                'project_id' => $project->id,
                'project_title' => $project->title,
                'exported_at' => now()->toIso8601String(),
                'metrics' => $group,
            ];
            $content = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $mime = 'application/json';
        } else {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['Metrik', 'Nilai', 'Satuan', 'Status Sehat']);

            foreach ($group as $item) {
                fputcsv($handle, [
                    $item['label'],
                    $item['value'],
                    $item['unit'] ?? '-',
                    ($item['healthy'] ?? false) ? 'SEHAT' : 'PERHATIAN',
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle) ?: '';
            fclose($handle);
            $mime = 'text/csv';
        }

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => $mime],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->projectId !== null
            ? XishieProject::find($this->projectId)
            : XishieProject::query()->latest()->first();

        if ($project === null) {
            return [
                'title' => $this->framework.' FRAMEWORK',
                'rows' => [],
                'framework' => $this->framework,
                'projectOptions' => $this->projectOptions(),
            ];
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
            'projectOptions' => $this->projectOptions(),
        ];
    }
}
