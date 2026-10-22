<?php

declare(strict_types=1);

namespace App\Omnigensi\Contracts;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use App\Omnigensi\Data\AgentExecutionResultData;

/**
 * Kontrak standar seluruh agen spesialis Omnigensi.
 *
 * Signature mengikuti PRD FASE 3:
 * execute(XishieProject $project, ProjectStage $stage, array $context): AgentExecutionResultData
 */
interface XishieAgentContract
{
    /**
     * Nama agen untuk telemetri & logging.
     */
    public function name(): string;

    /**
     * Jalankan agen terhadap proyek pada tahap tertentu.
     *
     * @param  array<string, mixed>  $context
     */
    public function execute(
        XishieProject $project,
        ProjectStage $stage,
        array $context = [],
    ): AgentExecutionResultData;
}
