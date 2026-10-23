<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\NaskahDocument;
use App\Models\XishieProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NaskahDocument>
 */
class NaskahDocumentFactory extends Factory
{
    protected $model = NaskahDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $blocks = [
            ['type' => 'heading', 'level' => 1, 'text' => fake()->sentence(4)],
            ['type' => 'paragraph', 'text' => fake()->paragraph(5)],
            ['type' => 'heading', 'level' => 2, 'text' => fake()->sentence(3)],
            ['type' => 'paragraph', 'text' => fake()->paragraph(6)],
        ];

        return [
            'user_id' => null,
            'project_id' => null,
            'title' => fake()->sentence(4),
            'source_type' => NaskahDocument::SOURCE_LOCAL,
            'source_stage' => null,
            'source_filename' => null,
            'blocks' => $blocks,
            'word_count' => 0,
            'last_saved_at' => now(),
        ];
    }

    public function withWords(): static
    {
        return $this->afterCreating(function (NaskahDocument $document): void {
            $document->forceFill(['word_count' => $document->computeWordCount()])->saveQuietly();
        });
    }

    public function forProject(XishieProject $project): static
    {
        return $this->state(fn (array $attributes): array => [
            'project_id' => $project->id,
            'source_type' => NaskahDocument::SOURCE_PROJECT,
            'source_stage' => $project->current_stage->value,
        ]);
    }
}
