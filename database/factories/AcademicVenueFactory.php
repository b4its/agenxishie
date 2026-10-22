<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AcademicVenue;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AcademicVenue>
 */
class AcademicVenueFactory extends Factory
{
    protected $model = AcademicVenue::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'journal_name' => fake()->unique()->company().' Journal',
            'publisher' => fake()->company(),
            'scope_type' => fake()->randomElement(['sinta', 'scopus', 'wos']),
            'index_tier' => fake()->randomElement(['SINTA 2', 'Q1', 'Q3', 'WoS']),
            'field_of_study' => 'stem',
            'aims_and_scope' => fake()->paragraph(),
            'apc_estimate' => fake()->randomFloat(2, 0, 2000),
            'submission_url' => fake()->url(),
            'review_time_days' => fake()->numberBetween(20, 90),
            'template_rules' => ['citation_style' => 'apa7'],
            'aims_scope_embedding' => null,
            'is_active' => true,
        ];
    }
}
