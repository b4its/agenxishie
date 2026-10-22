<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\User;
use App\Models\XishieProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<XishieProject>
 */
class XishieProjectFactory extends Factory
{
    protected $model = XishieProject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(DocumentType::cases());
        $field = fake()->randomElement(FieldOfStudy::cases());

        return [
            'user_id' => User::factory(),
            'title' => fake()->sentence(8),
            'field_of_study' => $field,
            'document_type' => $type,
            'target_scope' => PublicationTarget::NATIONAL_SINTA,
            'target_tier' => 'SINTA 2',
            'control_mode' => ControlMode::HITL,
            'current_stage' => ProjectStage::DRAFT,
            'guideline_type' => 'default',
            'citation_style' => CitationStyle::from($type->defaultCitationStyle()),
            'guideline_rules' => null,
            'onboarded_at' => now(),
        ];
    }

    public function journal(): static
    {
        return $this->state(fn (): array => [
            'document_type' => DocumentType::JOURNAL,
            'target_scope' => PublicationTarget::INTERNATIONAL_SCOPUS,
            'target_tier' => 'Q2',
            'citation_style' => CitationStyle::IEEE,
        ]);
    }

    public function automation(): static
    {
        return $this->state(fn (): array => ['control_mode' => ControlMode::AUTOMATION]);
    }

    public function manual(): static
    {
        return $this->state(fn (): array => ['control_mode' => ControlMode::MANUAL]);
    }
}
