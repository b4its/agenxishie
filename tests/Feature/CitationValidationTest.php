<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;

it('accepts citations that exist in project_references', function (): void {
    $project = makeProject();
    seedReferences($project);

    $keys = $project->references()->pluck('citation_key')->all();

    $result = app(ScholarScoutAgent::class)->validateCitations($project, $keys);

    expect($result['valid'])->toBe($keys)
        ->and($result['rejected'])->toBeEmpty();
});

it('rejects hallucinated citations not present in project_references', function (): void {
    $project = makeProject();
    seedReferences($project);

    $result = app(ScholarScoutAgent::class)->validateCitations($project, [
        'Prasetyo 2023',
        'FakeAuthor 1999',
        'Unknown 2020',
    ]);

    expect($result['valid'])->toBe(['Prasetyo 2023'])
        ->and($result['rejected'])->toContain('FakeAuthor 1999')
        ->and($result['rejected'])->toContain('Unknown 2020');
});

it('extracts citation keys from text', function (): void {
    $scout = app(ScholarScoutAgent::class);

    $keys = $scout->extractCitationKeys(
        'Metode ini terbukti efektif [Prasetyo, 2023] dan diperkuat [Kumar, 2022].',
    );

    expect($keys)->toBe(['Prasetyo, 2023', 'Kumar, 2022']);
});

it('only writes citations bound to real references', function (): void {
    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $result = app(AcademicScribeAgent::class)->execute($project, $stage);

    $allowed = $project->references()->pluck('citation_key')->all();

    expect($result->citationsUsed)->not->toBeEmpty();

    foreach ($result->citationsUsed as $citation) {
        expect($allowed)->toContain($citation);
    }

    expect($result->hallucinationCount())->toBe(0);
});

it('flags hallucinations when the scribe produces unknown citations', function (): void {
    $project = makeProject();

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    // Tanpa referensi sama sekali, kunci sitasi apa pun harus ditolak.
    $result = app(ScholarScoutAgent::class)->validateCitations($project, ['Ghost 2024']);

    expect($result['valid'])->toBeEmpty()
        ->and($result['rejected'])->toBe(['Ghost 2024']);
});

it('builds a literature matrix from extracted references', function (): void {
    $project = makeProject();
    seedReferences($project);

    $matrix = app(ScholarScoutAgent::class)->literatureMatrix($project);

    expect($matrix)->toHaveCount(2)
        ->and($matrix[0])->toHaveKeys(['citation_key', 'title', 'method', 'result', 'gap']);
});

it('stores embeddings and reference metadata as json', function (): void {
    $project = makeProject();
    seedReferences($project);

    $reference = $project->references()->first();

    expect($reference->embedding)->toBeArray()
        ->and($reference->embedding)->toHaveCount(1536)
        ->and($reference->matrix_extraction)->toBeArray()
        ->and($reference->is_verified)->toBeTrue();
});
