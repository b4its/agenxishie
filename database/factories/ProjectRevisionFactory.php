<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Revision\Enums\RevisionStatus;
use App\Models\ProjectRevision;
use App\Models\XishieProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectRevision>
 */
class ProjectRevisionFactory extends Factory
{
    protected $model = ProjectRevision::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => XishieProject::factory(),
            'stage_id' => null,
            'reviewer_role' => 'pembimbing_1',
            'raw_feedback' => fake()->paragraph(),
            'action_items' => [],
            'diff_payload' => [],
            'response_matrix_doc' => null,
            'status' => RevisionStatus::PENDING,
        ];
    }
}
