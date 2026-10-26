<?php

declare(strict_types=1);

namespace App\Filament\Widgets;

use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Filament\Widgets\Widget;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Unduh riwayat aktivitas agen sebagai berkas CSV atau JSON.
     */
    public function exportLogs(string $format = 'csv'): ?StreamedResponse
    {
        $query = OmnigensiAgentLog::query()->latest('id')->limit(200);

        if ($this->projectId !== null) {
            $query->where('project_id', $this->projectId);
        }

        $logs = $query->with('project')->get();
        $format = strtolower($format) === 'json' ? 'json' : 'csv';
        $filename = 'omnigensi-agent-logs-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'json') {
            $data = [
                'exported_at' => now()->toIso8601String(),
                'project_id' => $this->projectId,
                'total_logs' => $logs->count(),
                'logs' => $logs->map(static fn (OmnigensiAgentLog $log): array => [
                    'id' => $log->id,
                    'time' => $log->created_at?->toIso8601String(),
                    'project_id' => $log->project_id,
                    'project_title' => $log->project?->title,
                    'agent_name' => $log->agent_name,
                    'action' => (string) ($log->action ?? $log->status->value),
                    'ttft_ms' => $log->ttft_ms,
                    'execution_time_ms' => $log->execution_time_ms,
                    'token_usage' => $log->token_usage,
                    'status' => $log->status->value,
                ])->all(),
            ];
            $content = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $mime = 'application/json';
        } else {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['ID', 'Waktu', 'Proyek', 'Nama Agen', 'Aksi', 'TTFT (ms)', 'Waktu Eksekusi (ms)', 'Token', 'Status']);

            foreach ($logs as $log) {
                fputcsv($handle, [
                    $log->id,
                    $log->created_at?->format('Y-m-d H:i:s') ?? '-',
                    $log->project?->title ?? '-',
                    $log->agent_name,
                    (string) ($log->action ?? $log->status->value),
                    $log->ttft_ms ?? 0,
                    $log->execution_time_ms ?? 0,
                    $log->token_usage ?? 0,
                    $log->status->value,
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
