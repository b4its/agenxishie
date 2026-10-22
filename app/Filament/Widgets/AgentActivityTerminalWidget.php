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

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public ?int $projectId = null;

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->projectId !== null
            ? XishieProject::find($this->projectId)
            : XishieProject::query()->latest()->first();

        $logs = $project === null
            ? collect()
            : OmnigensiAgentLog::query()
                ->where('project_id', $project->id)
                ->latest('id')
                ->limit(30)
                ->get()
                ->reverse();

        $lines = $logs->map(static fn (OmnigensiAgentLog $log): array => [
            'time' => $log->created_at?->format('H:i:s') ?? '--:--:--',
            'agent' => $log->agent_name,
            'action' => (string) ($log->action ?? $log->status->value),
            'ttft' => $log->ttft_ms,
        ])->all();

        return ['lines' => $lines];
    }
}
