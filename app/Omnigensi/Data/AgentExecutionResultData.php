<?php

declare(strict_types=1);

namespace App\Omnigensi\Data;

use App\Omnigensi\Enums\AgentStatus;

/**
 * Readonly DTO yang memuat keluaran serta metrik sebuah eksekusi agen.
 */
final readonly class AgentExecutionResultData
{
    /**
     * @param  array<string, mixed>  $outputPayload
     * @param  array<int, string>  $citationsUsed
     * @param  array<int, string>  $hallucinationsRejected
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public string $agentName,
        public AgentStatus $status,
        public array $outputPayload = [],
        public float $complianceScore = 0.0,
        public array $citationsUsed = [],
        public array $hallucinationsRejected = [],
        public int $ttftMs = 0,
        public int $executionTimeMs = 0,
        public int $tokenUsage = 0,
        public array $metadata = [],
    ) {}

    public function isSuccessful(): bool
    {
        return $this->status->isSuccessful();
    }

    public function hallucinationCount(): int
    {
        return count($this->hallucinationsRejected);
    }

    /**
     * @param  array<string, mixed>  $outputPayload
     * @param  array<int, string>  $citationsUsed
     * @param  array<int, string>  $hallucinationsRejected
     * @param  array<string, mixed>  $metadata
     */
    public static function success(
        string $agentName,
        array $outputPayload = [],
        float $complianceScore = 0.0,
        array $citationsUsed = [],
        int $ttftMs = 0,
        int $executionTimeMs = 0,
        int $tokenUsage = 0,
        array $metadata = [],
        array $hallucinationsRejected = [],
    ): self {
        return new self(
            agentName: $agentName,
            status: AgentStatus::SUCCESS,
            outputPayload: $outputPayload,
            complianceScore: $complianceScore,
            citationsUsed: $citationsUsed,
            hallucinationsRejected: $hallucinationsRejected,
            ttftMs: $ttftMs,
            executionTimeMs: $executionTimeMs,
            tokenUsage: $tokenUsage,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<int, string>  $hallucinationsRejected
     * @param  array<string, mixed>  $metadata
     */
    public static function waitingApproval(
        string $agentName,
        array $outputPayload = [],
        float $complianceScore = 0.0,
        int $ttftMs = 0,
        int $executionTimeMs = 0,
        array $metadata = [],
    ): self {
        return new self(
            agentName: $agentName,
            status: AgentStatus::WAITING_APPROVAL,
            outputPayload: $outputPayload,
            complianceScore: $complianceScore,
            ttftMs: $ttftMs,
            executionTimeMs: $executionTimeMs,
            metadata: $metadata,
        );
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    public static function skipped(string $agentName, string $reason, array $metadata = []): self
    {
        return new self(
            agentName: $agentName,
            status: AgentStatus::SKIPPED,
            outputPayload: ['reason' => $reason],
            metadata: $metadata,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'agent_name' => $this->agentName,
            'status' => $this->status->value,
            'output_payload' => $this->outputPayload,
            'compliance_score' => $this->complianceScore,
            'citations_used' => $this->citationsUsed,
            'hallucinations_rejected' => $this->hallucinationsRejected,
            'ttft_ms' => $this->ttftMs,
            'execution_time_ms' => $this->executionTimeMs,
            'token_usage' => $this->tokenUsage,
            'metadata' => $this->metadata,
        ];
    }
}
