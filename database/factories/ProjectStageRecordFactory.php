<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectStageRecord>
 */
class ProjectStageRecordFactory extends Factory
{
    protected $model = ProjectStageRecord::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => XishieProject::factory(),
            'stage_name' => ProjectStage::DRAFT,
            'status' => StageStatus::IN_PROGRESS,
            'outline_schema' => [],
            'sections_content' => [],
            'compiled_content' => null,
            'hitl_pending_payload' => null,
            'compliance_score' => null,
            'word_count' => 0,
            'started_at' => now(),
            'completed_at' => null,
        ];
    }
}
