<?php

declare(strict_types=1);

namespace App\Omnigensi\Orchestrator;

use App\Models\OmnigensiAgentLog;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Data\AgentExecutionResultData;
use App\Omnigensi\Events\AgentTelemetryUpdated;
use Illuminate\Support\Facades\Log;

/**
 * Mencatat jejak eksekusi agen ke omnigensi_agent_logs dan memancarkan
 * event telemetri untuk terminal UI.
 */
final class AgentTelemetryRecorder
{
    public function record(
        XishieProject $project,
        ?ProjectStageRecord $stage,
        AgentExecutionResultData $result,
        ?string $action = null,
    ): OmnigensiAgentLog {
        $log = OmnigensiAgentLog::create([
            'project_id' => $project->id,
            'stage_id' => $stage?->id,
            'agent_name' => $result->agentName,
            'action' => $action,
            'control_mode' => $project->control_mode->value,
            'ttft_ms' => $result->ttftMs,
            'execution_time_ms' => $result->executionTimeMs,
            'token_usage' => $result->tokenUsage,
            'hallucination_rejected_count' => $result->hallucinationCount(),
            'status' => $result->status,
            'payload' => $result->toArray(),
        ]);

        $this->broadcast($project, $result, $action);

        return $log;
    }

    public function broadcast(
        XishieProject $project,
        AgentExecutionResultData $result,
        ?string $action = null,
    ): void {
        $message = $action ?? ($result->metadata['message'] ?? $result->agentName.' selesai.');

        try {
            event(new AgentTelemetryUpdated(
                projectId: $project->id,
                agentName: $result->agentName,
                message: (string) $message,
                status: $result->status->value,
                metadata: [
                    'compliance_score' => $result->complianceScore,
                    'hallucinations_rejected' => $result->hallucinationCount(),
                    'execution_time_ms' => $result->executionTimeMs,
                ],
            ));
        } catch (\Throwable $exception) {
            // Broadcasting tidak boleh menggagalkan pipeline agen.
            Log::warning('Gagal memancarkan telemetri agen Omnigensi.', [
                'project_id' => $project->id,
                'agent' => $result->agentName,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
