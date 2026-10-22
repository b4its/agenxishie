<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

use App\Omnigensi\Data\AgentExecutionResultData;

/**
 * GraphState: keadaan bersama yang mengalir antar node selama eksekusi graf
 * multi-agen. Port dari `DynamicTeamState` (omnigensi `app/engine/state.py`).
 *
 * Berbeda dari TypedDict Python, kelas ini bersifat mutable dan menyediakan
 * helper untuk pola reducer (riwayat eksekusi diakumulasi dengan penambahan).
 */
final class GraphState
{
    /**
     * @param  array<int, array<string, mixed>>  $executionHistory
     */
    public function __construct(
        public string $taskPrompt = '',
        public string $sharedContext = '',
        public string $currentDraft = '',
        public string $reviewerFeedback = '',
        public bool $isApproved = false,
        public int $revisionCount = 0,
        public bool $simulated = false,
        public array $executionHistory = [],
    ) {}

    /**
     * Catat satu langkah eksekusi (reducer `operator.add` pada Python).
     *
     * @param  array<string, mixed>  $entry
     */
    public function appendHistory(array $entry): void
    {
        $this->executionHistory[] = $entry;
    }

    /**
     * Terapkan hasil eksekusi agen ke state sesuai peran node.
     *
     * @param  array<string, mixed>  $step  metadata langkah dari runner
     */
    public function applyResult(AgentExecutionResultData $result, array $step): void
    {
        $payload = $result->outputPayload;

        // Node reviewer menetapkan keputusan siklus.
        if (isset($step['node_type']) && $step['node_type'] === 'reviewer') {
            $approved = (bool) ($payload['is_approved'] ?? $result->isSuccessful());

            $this->isApproved = $approved;
            $this->reviewerFeedback = (string) ($payload['feedback'] ?? $payload['reason'] ?? '');

            if (! $approved) {
                $this->revisionCount++;
            }

            return;
        }

        // Node worker / supervisor memperbarui draf dan konteks bersama.
        $draft = (string) ($payload['compiled_content']
            ?? $payload['draft']
            ?? $result->metadata['compiled_content']
            ?? '');

        if ($draft !== '') {
            $this->currentDraft = $draft;
            $this->sharedContext = trim($this->sharedContext."\n\n### ".($step['node_id'] ?? 'node')."\n".$draft);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'task_prompt' => $this->taskPrompt,
            'shared_context' => $this->sharedContext,
            'current_draft' => $this->currentDraft,
            'reviewer_feedback' => $this->reviewerFeedback,
            'is_approved' => $this->isApproved,
            'revision_count' => $this->revisionCount,
            'simulated' => $this->simulated,
            'execution_history' => $this->executionHistory,
        ];
    }
}
