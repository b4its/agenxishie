<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Domain\Workspace\NextActionService;
use App\Models\XishieProject;
use Filament\Widgets\Widget;

/**
 * NextActionsWidget: daftar langkah berikutnya per proyek (panduan aksi),
 * dihitung oleh NextActionService agar pengguna tidak menebak tombol.
 */
class NextActionsWidget extends Widget
{
    protected string $view = 'filament.widgets.next-actions';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 1;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $service = app(NextActionService::class);

        $projects = XishieProject::query()->latest('updated_at')->limit(5)->get();

        $items = $projects->map(static fn (XishieProject $project): array => [
            'id' => $project->id,
            'title' => $project->title,
            'stage' => $project->current_stage->label(),
            'actions' => $service->forProject($project),
        ])->all();

        return ['items' => $items];
    }
}
