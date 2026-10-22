<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents\Concerns;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;

/**
 * Menyelesaikan record stage dari enum ProjectStage agar agen dapat
 * memakai signature kontrak yang berbasis enum (sesuai PRD FASE 3).
 */
trait ResolvesStageRecord
{
    /**
     * Ambil (atau buat) record stage untuk enum tahap pada proyek.
     */
    protected function stageRecord(XishieProject $project, ProjectStage $stage): ProjectStageRecord
    {
        /** @var ProjectStageRecord $record */
        $record = $project->stages()->firstOrCreate(
            ['stage_name' => $stage->value],
            ['started_at' => now()],
        );

        return $record;
    }
}
