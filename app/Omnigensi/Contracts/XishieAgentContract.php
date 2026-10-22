<?php

declare(strict_types=1);

namespace App\Omnigensi\Contracts;

use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Data\AgentExecutionResultData;

/**
 * Kontrak standar seluruh agen spesialis Omnigensi.
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
        ProjectStageRecord $stage,
        array $context = [],
    ): AgentExecutionResultData;
}
