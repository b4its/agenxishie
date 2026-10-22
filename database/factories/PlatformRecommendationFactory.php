<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\PlatformRecommendation;
use App\Models\XishieProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformRecommendation>
 */
class PlatformRecommendationFactory extends Factory
{
    protected $model = PlatformRecommendation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => XishieProject::factory(),
            'academic_venue_id' => null,
            'journal_name' => fake()->company().' Journal',
            'publisher' => fake()->company(),
            'index_tier' => fake()->randomElement(['SINTA 2', 'Q1']),
            'match_score' => fake()->randomFloat(2, 0, 100),
            'scope_reasoning' => fake()->sentence(10),
            'apc_estimate' => 0,
            'submission_url' => fake()->url(),
        ];
    }
}
