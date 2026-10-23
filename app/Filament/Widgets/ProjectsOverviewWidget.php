<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\XishieProject;
use Filament\Widgets\Widget;

/**
 * ProjectsOverviewWidget: ringkasan seluruh proyek di dashboard — tahap,
 * kepatuhan, jumlah referensi, plus tautan cepat ke workspace.
 */
class ProjectsOverviewWidget extends Widget
{
    protected string $view = 'filament.widgets.projects-overview';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $projects = XishieProject::query()->latest('updated_at')->limit(20)->get();

        $rows = $projects->map(static function (XishieProject $project): array {
            $record = $project->stages()->where('stage_name', $project->current_stage->value)->first();

            return [
                'id' => $project->id,
                'title' => $project->title,
                'stage' => $project->current_stage->label(),
                'control_mode' => $project->control_mode->shortLabel(),
                'compliance' => (float) ($record?->compliance_score ?? 0.0),
                'references' => $project->references()->count(),
                'updated_at' => $project->updated_at,
                'url' => '/admin/academic-workspace-page?project='.$project->id,
            ];
        })->all();

        return [
            'rows' => $rows,
            'total' => XishieProject::query()->count(),
        ];
    }
}
