<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use App\Omnigensi\Enums\AgentStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OmnigensiAgentLog>
 */
class OmnigensiAgentLogFactory extends Factory
{
    protected $model = OmnigensiAgentLog::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => XishieProject::factory(),
            'stage_id' => null,
            'agent_name' => fake()->randomElement(['Scholar-Scout', 'Academic-Scribe', 'Rule-Keeper']),
            'action' => fake()->sentence(4),
            'control_mode' => 'automation',
            'ttft_ms' => fake()->numberBetween(1, 200),
            'execution_time_ms' => fake()->numberBetween(5, 800),
            'token_usage' => fake()->numberBetween(100, 2000),
            'hallucination_rejected_count' => 0,
            'status' => AgentStatus::SUCCESS,
            'payload' => [],
        ];
    }
}
