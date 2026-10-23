<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Filament\Widgets\Widget;

/**
 * AgentActivityTerminalWidget: live terminal log aktivitas agen Omnigensi.
 */
class AgentActivityTerminalWidget extends Widget
{
    protected string $view = 'filament.widgets.agent-activity-terminal';

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

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
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $query = OmnigensiAgentLog::query()->latest('id')->limit(40);

        if ($this->projectId !== null) {
            $query->where('project_id', $this->projectId);
        }

        $logs = $query->with('project')->get()->reverse();

        $lines = $logs->map(static fn (OmnigensiAgentLog $log): array => [
            'time' => $log->created_at?->format('H:i:s') ?? '--:--:--',
            'agent' => $log->agent_name,
            'action' => (string) ($log->action ?? $log->status->value),
            'ttft' => $log->ttft_ms,
            'project_title' => $log->project?->title,
        ])->all();

        return [
            'lines' => $lines,
            'projectOptions' => $this->projectOptions(),
            'totalLogs' => OmnigensiAgentLog::count(),
        ];
    }
}
