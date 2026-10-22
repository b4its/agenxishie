<?php

declare(strict_types=1);

namespace App\Domain\Personalization;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelinePresetFactory;
use App\Domain\GuidelineEngine\GuidelineResolverService;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\StageStateManager;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\XishieProject;
use Illuminate\Support\Facades\DB;

/**
 * ProjectOnboardingService: membuat proyek baru dari wizard personalisasi
 * (bidang keilmuan, jenis dokumen, target, pedoman, dan basis kendali).
 */
final class ProjectOnboardingService
{
    public function __construct(
        private readonly StageStateManager $stageManager,
        private readonly GuidelineResolverService $guidelineResolver,
        private readonly UxKpiTelemetryService $kpi,
        private readonly GuidelinePresetFactory $presetFactory,
    ) {}

    /**
     * @param  array{
     *     title: string,
     *     field_of_study: FieldOfStudy|string,
     *     document_type: DocumentType|string,
     *     target_scope: PublicationTarget|string,
     *     target_tier?: string|null,
     *     control_mode: ControlMode|string,
     *     guideline_type?: string,
     *     citation_style?: string|null,
     *     guideline_rules?: array<string, mixed>|null,
     *     custom_guideline_path?: string|null
     * }  $data
     */
    public function create(int $userId, array $data): XishieProject
    {
        $field = $data['field_of_study'] instanceof FieldOfStudy
            ? $data['field_of_study']
            : FieldOfStudy::from((string) $data['field_of_study']);

        $type = $data['document_type'] instanceof DocumentType
            ? $data['document_type']
            : DocumentType::from((string) $data['document_type']);

        $target = $data['target_scope'] instanceof PublicationTarget
            ? $data['target_scope']
            : PublicationTarget::from((string) $data['target_scope']);

        $mode = $data['control_mode'] instanceof ControlMode
            ? $data['control_mode']
            : ControlMode::from((string) $data['control_mode']);

        $citationStyle = $data['citation_style'] ?? $type->defaultCitationStyle();

        $project = DB::transaction(function () use ($userId, $data, $field, $type, $target, $mode, $citationStyle): XishieProject {
            /** @var XishieProject $project */
            $project = XishieProject::create([
                'user_id' => $userId,
                'title' => (string) $data['title'],
                'field_of_study' => $field,
                'document_type' => $type,
                'target_scope' => $target,
                'target_tier' => $data['target_tier'] ?? null,
                'control_mode' => $mode,
                'guideline_type' => (string) ($data['guideline_type'] ?? 'default'),
                'citation_style' => CitationStyle::from((string) $citationStyle),
                'guideline_rules' => $data['guideline_rules'] ?? null,
                'custom_guideline_path' => $data['custom_guideline_path'] ?? null,
                'onboarded_at' => now(),
            ]);

            $this->stageManager->initialize($project);

            // Mode default: lenturkan preset baku ke kolom guideline_rules.
            if ($project->guideline_type === 'default') {
                $this->guidelineResolver->persistPreset($project);
            }

            return $project->refresh();
        });

        $this->kpi->recordProjectSnapshot($project);

        return $project;
    }

    /**
     * Payload preview pedoman sebelum proyek dibuat (dipakai wizard step 3).
     *
     * @return array<string, mixed>
     */
    public function previewGuideline(DocumentType $type, string $citationStyle): array
    {
        return $this->presetFactory->forDocumentType($type, $citationStyle)->toArray();
    }
}
