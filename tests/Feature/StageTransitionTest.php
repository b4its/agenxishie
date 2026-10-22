<?php

declare(strict_types=1);

use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\StageStateManager;
use App\Models\XishieProject;
use App\Omnigensi\Orchestrator\XishieSupervisor;

it('initializes three stages with draft unlocked', function (): void {
    $project = makeProject();

    expect($project->stages()->count())->toBe(3);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    $proposal = $project->stages()->where('stage_name', ProjectStage::PROPOSAL->value)->first();
    $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();

    expect($draft->status)->toBe(StageStatus::IN_PROGRESS)
        ->and($proposal->status)->toBe(StageStatus::LOCKED)
        ->and($final->status)->toBe(StageStatus::LOCKED)
        ->and($project->current_stage)->toBe(ProjectStage::DRAFT);
});

it('persists the default guideline preset as json', function (): void {
    $project = makeProject();

    expect($project->guideline_rules)->toBeArray()
        ->and($project->guideline_rules['source'])->toBe('default_imrad')
        ->and($project->guideline_rules['sections'])->not->toBeEmpty();
});

it('applies the DIKTI chapter preset for theses', function (string $type, string $expectedSource): void {
    $project = makeProject(['document_type' => DocumentType::from($type)]);

    expect($project->guideline_rules['source'])->toBe($expectedSource);

    $finalHeadings = $project->guideline_rules['stages']['final'];

    expect($finalHeadings)->toContain('chapter4')
        ->and($finalHeadings)->toContain('chapter5');
})->with([
    'skripsi' => [DocumentType::SKRIPSI->value, 'default_dikti_skripsi'],
    'tesis' => [DocumentType::THESIS->value, 'default_dikti_thesis'],
    'disertasi' => [DocumentType::DISSERTATION->value, 'default_dikti_dissertation'],
]);

it('blocks advancing to the next stage before the previous one completes', function (): void {
    $project = makeProject();
    $manager = app(StageStateManager::class);

    expect(fn () => $manager->advance($project))
        ->toThrow(InvalidArgumentException::class);
});

it('advances draft -> proposal -> final in sequence', function (): void {
    $project = makeProject();
    $manager = app(StageStateManager::class);

    $manager->complete($project, ProjectStage::DRAFT);
    $manager->advance($project);

    expect($project->refresh()->current_stage)->toBe(ProjectStage::PROPOSAL);

    $manager->complete($project, ProjectStage::PROPOSAL);
    $manager->advance($project);

    expect($project->refresh()->current_stage)->toBe(ProjectStage::FINAL);
});

it('reports progression status for every stage', function (): void {
    $project = makeProject();
    $manager = app(StageStateManager::class);
    $manager->complete($project, ProjectStage::DRAFT);

    $progression = $manager->progression($project->refresh());

    expect($progression)->toHaveCount(3)
        ->and($progression[0]['status'])->toBe(StageStatus::COMPLETED->value)
        ->and($progression[2]['status'])->toBe(StageStatus::LOCKED->value);
});

it('runs the full stage transition flow for each document type', function (DocumentType $type): void {
    $project = makeProject([
        'document_type' => $type,
        'field_of_study' => FieldOfStudy::STEM,
        'target_scope' => PublicationTarget::NATIONAL_SINTA,
        'control_mode' => ControlMode::AUTOMATION,
    ]);

    seedReferences($project);

    $supervisor = app(XishieSupervisor::class);
    $manager = app(StageStateManager::class);

    foreach ([ProjectStage::DRAFT, ProjectStage::PROPOSAL, ProjectStage::FINAL] as $stage) {
        $result = $supervisor->run($project, $stage);
        expect($result['mode'])->toBe('automation');

        $record = $project->stages()->where('stage_name', $stage->value)->first();
        expect($record->status)->toBe(StageStatus::COMPLETED);

        if ($stage !== ProjectStage::FINAL) {
            $manager->advance($project);
            $project->refresh();
        }
    }

    expect($project->refresh()->current_stage)->toBe(ProjectStage::FINAL);
})->with([
    'jurnal' => [DocumentType::JOURNAL],
    'skripsi' => [DocumentType::SKRIPSI],
    'tesis' => [DocumentType::THESIS],
    'disertasi' => [DocumentType::DISSERTATION],
]);

it('stores section content and word counts per stage', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($draft->sections_content)->toBeArray()
        ->and($draft->sections_content)->not->toBeEmpty()
        ->and($draft->word_count)->toBeGreaterThan(0);
});

it('keeps project metadata consistent with onboarding input', function (): void {
    $project = makeProject([
        'title' => 'Judul Uji Personalisasi',
        'target_tier' => 'SINTA 1',
        'control_mode' => ControlMode::MANUAL,
    ]);

    expect($project->title)->toBe('Judul Uji Personalisasi')
        ->and($project->target_tier)->toBe('SINTA 1')
        ->and($project->control_mode)->toBe(ControlMode::MANUAL)
        ->and($project)->toBeInstanceOf(XishieProject::class);
});
