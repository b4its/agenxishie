<?php

declare(strict_types=1);

namespace App\Omnigensi\Graph;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Agents\Concerns\ResolvesStageRecord;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Data\AgentExecutionResultData;
use App\Omnigensi\Enums\AgentStatus;
use App\Omnigensi\Graph\Enums\EdgeType;
use App\Omnigensi\Graph\Enums\NodeType;
use App\Omnigensi\Orchestrator\AgentTelemetryRecorder;
use RuntimeException;

/**
 * GraphRunner: runtime eksekusi graf multi-agen dinamis.
 *
 * Port dari `compile_workflow_graph` (omnigensi `app/engine/compiler.py`).
 * Runtime menelusuri graf dari `entry_point`, mengeksekusi agen tiap node, dan
 * pada node reviewer memilih jalur `on_approve`/`on_reject` berdasarkan
 * keputusan `is_approved`. Bila jumlah revisi mencapai `max_revisions`, jalur
 * `on_approve` dipaksa (mencegah loop tak berujung) — sama seperti semantik
 * LangGraph pada omnigensi.
 */
final class GraphRunner
{
    use ResolvesStageRecord;

    /**
     * Ambang batas Guideline Compliance (%) agar node reviewer menyetujui draf.
     */
    public const DEFAULT_APPROVAL_THRESHOLD = 70.0;

    public function __construct(
        private readonly GraphValidator $validator,
        private readonly AgentRegistry $registry,
        private readonly RuleKeeperAgent $ruleKeeper,
        private readonly AgentTelemetryRecorder $telemetry,
    ) {}

    /**
     * Jalankan graf untuk sebuah proyek pada tahap tertentu.
     *
     * @param  array<string, mixed>  $schema  skema graf mentah (JSON)
     * @param  array<string, mixed>  $context  konteks tambahan untuk agen
     * @return array<string, mixed>
     */
    public function run(
        XishieProject $project,
        ProjectStage $stage,
        array $schema,
        array $context = [],
        int $maxRevisions = 3,
    ): array {
        $graph = WorkflowGraphSchema::fromArray($schema);
        $validation = $this->validator->validate($graph->toArray(), $this->registry->names());

        if (! $validation->valid) {
            throw new RuntimeException('Graf tidak valid: '.$validation->summary());
        }

        $record = $this->stageRecord($project, $stage);

        $threshold = (float) ($context['approval_threshold'] ?? self::DEFAULT_APPROVAL_THRESHOLD);
        $maxRevisions = max(1, $maxRevisions);

        $state = new GraphState(
            taskPrompt: (string) ($context['task_prompt'] ?? $project->title),
            sharedContext: (string) ($context['shared_context'] ?? ''),
        );

        $current = $graph->resolveEntryPoint();
        $visited = [];
        $steps = [];

        // Batas keras jumlah node dieksekusi untuk mencegah loop tak terbatas
        // pada graf yang salah kabel (defensive guard selain maxRevisions).
        $hardLimit = max(50, count($graph->nodes) * ($maxRevisions + 2));

        while ($current !== null && $current !== WorkflowGraphSchema::END) {
            if (count($steps) >= $hardLimit) {
                throw new RuntimeException('Graf melebihi batas eksekusi; periksa kabel edge/cycle.');
            }

            $node = $graph->findNode($current);

            if ($node === null) {
                throw new RuntimeException("Node '{$current}' tidak ditemukan di dalam graf.");
            }

            $visited[] = $current;
            $nodeType = $node['node_type'];
            $agent = $this->registry->make($node['agent']);

            $result = $this->executeNode($project, $stage, $record, $agent->name(), $node, $nodeType, $state, $context, $threshold);

            $this->telemetry->record($project, $record, $result, 'Graf: node '.$current.' ('.$nodeType.')');

            $this->applyState($state, $result, $node, $nodeType);

            $state->appendHistory([
                'node_id' => $current,
                'agent' => $node['agent'],
                'node_type' => $nodeType,
                'status' => $result->status->value,
                'compliance_score' => $result->complianceScore,
                'revision_count' => $state->revisionCount,
            ]);

            $steps[] = [
                'node_id' => $current,
                'agent' => $agent->name(),
                'node_type' => $nodeType,
                'status' => $result->status->value,
            ];

            $current = $this->nextNode($graph, $current, $state, $maxRevisions);
        }

        return [
            'graph' => [
                'entry_point' => $graph->resolveEntryPoint(),
                'nodes_executed' => $visited,
                'revision_count' => $state->revisionCount,
                'is_approved' => $state->isApproved,
            ],
            'steps' => $steps,
            'state' => $state->toArray(),
            'current_draft' => $state->currentDraft,
            'reviewer_feedback' => $state->reviewerFeedback,
        ];
    }

    /**
     * Eksekusi satu node, dengan semantik khusus untuk node reviewer.
     *
     * @param  array{node_id: string, agent: string, node_type: string}  $node
     * @param  array<string, mixed>  $context
     */
    private function executeNode(
        XishieProject $project,
        ProjectStage $stage,
        ProjectStageRecord $record,
        string $agentName,
        array $node,
        string $nodeType,
        GraphState $state,
        array $context,
        float $threshold,
    ): AgentExecutionResultData {
        if ($nodeType === NodeType::REVIEWER->value) {
            return $this->review($project, $stage, $record, $agentName, $state, $context, $threshold);
        }

        $agent = $this->registry->make($node['agent']);

        // Suntikkan konteks bersama agar agen hilir memakai hasil hulu.
        $nodeContext = $context;
        $nodeContext['shared_context'] = $state->sharedContext;

        if ($state->reviewerFeedback !== '') {
            $nodeContext['reviewer_feedback'] = $state->reviewerFeedback;
        }

        $result = $agent->execute($project, $stage, $nodeContext);

        // Agen menulis ke record stage lewat instance modelnya sendiri, jadi
        // segarkan record runner sebelum mengompilasi naskah terbaru.
        $record->refresh();

        // Simpan naskah yang dikompilasi ke metadata agar state dapat menyerapnya.
        if (method_exists($record, 'compile')) {
            $compiled = $record->compile();

            if ($compiled !== '') {
                $result = new AgentExecutionResultData(
                    agentName: $result->agentName,
                    status: $result->status,
                    outputPayload: $result->outputPayload,
                    complianceScore: $result->complianceScore,
                    citationsUsed: $result->citationsUsed,
                    hallucinationsRejected: $result->hallucinationsRejected,
                    ttftMs: $result->ttftMs,
                    executionTimeMs: $result->executionTimeMs,
                    tokenUsage: $result->tokenUsage,
                    metadata: array_merge($result->metadata, ['compiled_content' => $compiled]),
                );
            }
        }

        return $result;
    }

    /**
     * Keputusan reviewer: menyetujui bila Guideline Compliance memenuhi ambang.
     *
     * @param  array<string, mixed>  $context
     */
    private function review(
        XishieProject $project,
        ProjectStage $stage,
        ProjectStageRecord $record,
        string $agentName,
        GraphState $state,
        array $context,
        float $threshold,
    ): AgentExecutionResultData {
        $startedAt = microtime(true);

        $audit = $this->ruleKeeper->run($project, $record, $context);
        $score = $audit->complianceScore;

        $approved = $score >= $threshold;

        $feedback = $approved
            ? sprintf('Draf memenuhi ambang kepatuhan (%.1f%% >= %.1f%%).', $score, $threshold)
            : sprintf('Draf belum memenuhi ambang kepatuhan (%.1f%% < %.1f%%). Perbaiki bagian yang ditandai.', $score, $threshold);

        return new AgentExecutionResultData(
            agentName: $agentName,
            status: AgentStatus::SUCCESS,
            outputPayload: [
                'is_approved' => $approved,
                'feedback' => $feedback,
                'compliance_score' => $score,
                'threshold' => $threshold,
            ],
            complianceScore: $score,
            ttftMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            metadata: ['message' => $feedback],
        );
    }

    /**
     * Terapkan hasil node ke state (meniru reducer LangGraph).
     *
     * @param  array{node_id: string, agent: string, node_type: string}  $node
     */
    private function applyState(GraphState $state, AgentExecutionResultData $result, array $node, string $nodeType): void
    {
        $payload = $result->outputPayload;

        if ($nodeType === NodeType::REVIEWER->value) {
            $approved = (bool) ($payload['is_approved'] ?? false);

            $state->isApproved = $approved;
            $state->reviewerFeedback = (string) ($payload['feedback'] ?? '');

            if (! $approved) {
                $state->revisionCount++;
            }

            return;
        }

        $draft = (string) ($result->metadata['compiled_content']
            ?? $payload['compiled_content']
            ?? $payload['compiled']
            ?? '');

        if ($draft !== '') {
            $state->currentDraft = $draft;
            $state->sharedContext = trim($state->sharedContext."\n\n### ".$node['node_id']."\n".$draft);
        }
    }

    /**
     * Tentukan node berikutnya mengikuti semantik conditional/normal edge.
     */
    private function nextNode(WorkflowGraphSchema $graph, string $current, GraphState $state, int $maxRevisions): ?string
    {
        $edges = $graph->outgoingEdges($current);

        if ($edges === []) {
            return null;
        }

        foreach ($edges as $edge) {
            if ($edge['type'] !== EdgeType::CONDITIONAL->value) {
                continue;
            }

            $onApprove = $edge['on_approve'] ?? WorkflowGraphSchema::END;
            $onReject = $edge['on_reject'] ?? WorkflowGraphSchema::END;

            // Paksa jalur approve ketika batas revisi tercapai.
            $approved = $state->isApproved || $state->revisionCount >= $maxRevisions;

            $target = $approved ? $onApprove : $onReject;

            return $target === WorkflowGraphSchema::END ? null : $target;
        }

        // Gratis: pakai edge normal pertama.
        $target = $edges[0]['to'] ?? null;

        return $target === null || $target === WorkflowGraphSchema::END ? null : $target;
    }
}
