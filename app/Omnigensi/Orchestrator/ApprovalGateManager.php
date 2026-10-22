<?php

declare(strict_types=1);

namespace App\Omnigensi\Orchestrator;

use App\Domain\Workspace\Enums\StageStatus;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Data\AgentExecutionResultData;

/**
 * ApprovalGateManager: checkpoint locker untuk mode HITL.
 *
 * Menyimpan draf agen ke hitl_pending_payload, mengunci stage menjadi
 * WAITING_APPROVAL, dan menyediakan aksi approve / request revision / manual edit.
 */
final class ApprovalGateManager
{
    public function lock(
        XishieProject $project,
        ProjectStageRecord $stage,
        AgentExecutionResultData $result,
        string $agentName,
    ): ProjectStageRecord {
        $stage->forceFill([
            'status' => StageStatus::WAITING_APPROVAL,
            'hitl_pending_payload' => [
                'agent_name' => $agentName,
                'payload' => $result->outputPayload,
                'ttft_ms' => $result->ttftMs,
                'execution_time_ms' => $result->executionTimeMs,
                'locked_at' => now()->toIso8601String(),
            ],
        ])->save();

        return $stage;
    }

    public function isWaiting(ProjectStageRecord $stage): bool
    {
        return $stage->status === StageStatus::WAITING_APPROVAL;
    }

    /**
     * Approve: terapkan draf pending ke sections_content dan tandai selesai.
     */
    public function approve(XishieProject $project, ProjectStageRecord $stage): ProjectStageRecord
    {
        $pending = (array) ($stage->hitl_pending_payload ?? []);
        $sections = $pending['payload']['sections'] ?? null;

        if (is_array($sections) && $sections !== []) {
            $existing = collect((array) ($stage->sections_content ?? []))->keyBy('key');

            foreach ($sections as $section) {
                if (is_array($section) && isset($section['key'])) {
                    $existing->put($section['key'], $section);
                }
            }

            $stage->sections_content = $existing->values()->all();
        }

        $stage->forceFill([
            'status' => StageStatus::COMPLETED,
            'hitl_pending_payload' => ['decision' => 'approved', 'decided_at' => now()->toIso8601String()],
            'completed_at' => now(),
        ])->save();

        $stage->recalculateWordCount();

        return $stage;
    }

    /**
     * Request revision: catat instruksi tambahan dan kembalikan stage ke in_progress.
     */
    public function requestRevision(ProjectStageRecord $stage, string $instruction): ProjectStageRecord
    {
        $stage->forceFill([
            'status' => StageStatus::IN_PROGRESS,
            'hitl_pending_payload' => [
                'decision' => 'regenerated',
                'instruction' => $instruction,
                'decided_at' => now()->toIso8601String(),
            ],
        ])->save();

        return $stage;
    }

    /**
     * Manual edit: pengguna menyunting langsung; pending payload dibersihkan.
     */
    public function manualEdit(ProjectStageRecord $stage, array $sections): ProjectStageRecord
    {
        $stage->forceFill([
            'sections_content' => $sections,
            'status' => StageStatus::COMPLETED,
            'hitl_pending_payload' => ['decision' => 'manual_edit', 'decided_at' => now()->toIso8601String()],
            'completed_at' => now(),
        ])->save();

        $stage->recalculateWordCount();

        return $stage;
    }
}
