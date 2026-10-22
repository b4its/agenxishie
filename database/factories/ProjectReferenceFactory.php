<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ProjectReference;
use App\Models\XishieProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProjectReference>
 */
class ProjectReferenceFactory extends Factory
{
    protected $model = ProjectReference::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $year = fake()->numberBetween(2015, (int) now()->year);

        return [
            'project_id' => XishieProject::factory(),
            'citation_key' => fake()->lastName().' '.$year,
            'doi' => '10.'.fake()->numberBetween(1000, 9999).'/'.fake()->slug(2),
            'title' => fake()->sentence(8),
            'abstract' => fake()->paragraph(),
            'authors' => [fake()->lastName(), fake()->lastName()],
            'year' => $year,
            'journal_name' => fake()->company(),
            'sinta_or_scopus_tier' => fake()->randomElement(['SINTA 2', 'Q1', 'Q3']),
            'matrix_extraction' => [
                'method' => fake()->word(),
                'result' => fake()->sentence(6),
                'gap' => fake()->sentence(5),
            ],
            'embedding' => null,
            'source' => 'manual',
            'is_verified' => true,
        ];
    }
}
