<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\AcademicVenue;
use App\Models\ProjectReference;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Memory\HybridVectorMatcherService;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Database\Seeders\AcademicVenueSeeder;

it('computes cosine similarity correctly', function (): void {
    $matcher = app(HybridVectorMatcherService::class);

    expect($matcher->cosineSimilarity([1.0, 0.0], [1.0, 0.0]))->toBe(1.0)
        ->and($matcher->cosineSimilarity([1.0, 0.0], [0.0, 1.0]))->toBe(0.0)
        ->and($matcher->cosineSimilarity([1.0, 1.0], [1.0, 1.0]))->toBeFloat();
});

it('returns zero similarity for empty or zero vectors', function (): void {
    $matcher = app(HybridVectorMatcherService::class);

    expect($matcher->cosineSimilarity([], []))->toBe(0.0)
        ->and($matcher->cosineSimilarity([0.0, 0.0], [1.0, 1.0]))->toBe(0.0);
});

it('produces deterministic embeddings of fixed dimension', function (): void {
    $matcher = app(HybridVectorMatcherService::class);

    $a = $matcher->embed('machine learning anomaly detection');
    $b = $matcher->embed('machine learning anomaly detection');
    $c = $matcher->embed('jantung kesehatan masyarakat epidemiologi');

    expect($a)->toHaveCount(1536)
        ->and($a)->toBe($b)
        ->and($matcher->cosineSimilarity($a, $b))->toBeGreaterThan(0.99)
        ->and($matcher->cosineSimilarity($a, $c))->toBeLessThan(0.99);
});

it('ranks semantically closer references higher', function (): void {
    $project = makeProject();
    $matcher = app(HybridVectorMatcherService::class);

    $close = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'Close 2023',
        'title' => 'Network anomaly detection with deep learning',
        'abstract' => 'deep learning network anomaly detection intrusion benchmark',
        'embedding' => $matcher->embed('network anomaly detection deep learning intrusion benchmark'),
        'is_verified' => true,
    ]);

    $medium = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'Medium 2022',
        'title' => 'Network security monitoring systems',
        'abstract' => 'network security monitoring infrastructure telemetry',
        'embedding' => $matcher->embed('network security monitoring infrastructure telemetry'),
        'is_verified' => true,
    ]);

    // Cosine similarity harus benar-benar membedakan kedekatan semantik.
    $queryVector = $matcher->embed('network anomaly detection deep learning intrusion benchmark');

    $closeScore = $matcher->cosineSimilarity($queryVector, $close->embeddingVector());
    $mediumScore = $matcher->cosineSimilarity($queryVector, $medium->embeddingVector());

    expect($closeScore)->toBeGreaterThan($mediumScore)
        ->and($closeScore)->toBeGreaterThan(0.5)
        ->and($mediumScore)->toBeLessThan($closeScore);

    // Hasil pencarian juga harus menempatkan yang paling dekat di depan.
    $results = $matcher->search(
        modelClass: ProjectReference::class,
        queryText: 'network anomaly detection deep learning intrusion benchmark',
        queryVector: $queryVector,
        filters: ['project_id' => $project->id],
    );

    expect($results)->not->toBeEmpty()
        ->and($results[0]['model']->id)->toBe($close->id);
});

it('matches venues through the hybrid search service', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $matcher = app(HybridVectorMatcherService::class);

    $results = $matcher->search(
        modelClass: AcademicVenue::class,
        queryText: 'machine learning computer science engineering',
        queryVector: $matcher->embed('machine learning computer science engineering'),
        fullTextColumns: ['journal_name', 'aims_and_scope'],
        embeddingColumn: 'aims_scope_embedding',
        filters: ['is_active' => true],
    );

    expect($results)->not->toBeEmpty();
});

it('generates recommendations ordered by match score', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $project = makeProject();
    seedReferences($project);

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::FINAL);

    $result = app(VenueMatcherAgent::class)->run($project, $stage);

    $recommendations = $project->recommendations()->get();

    expect($recommendations)->not->toBeEmpty()
        ->and((float) $recommendations->first()->match_score)->toBeGreaterThanOrEqual(
            (float) $recommendations->last()->match_score,
        );

    foreach ($recommendations as $recommendation) {
        expect($recommendation->scope_reasoning)->not->toBeEmpty()
            ->and($recommendation->journal_name)->not->toBeEmpty();
    }
});

it('is idempotent when recommendations are regenerated', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::FINAL);

    $agent = app(VenueMatcherAgent::class);
    $agent->run($project, $stage);
    $firstCount = $project->recommendations()->count();

    $agent->run($project, $stage);
    $secondCount = $project->recommendations()->count();

    expect($firstCount)->toBe($secondCount);
});

it('stores venue embeddings as json arrays', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $venue = AcademicVenue::first();

    expect($venue->aims_scope_embedding)->toBeArray()
        ->and($venue->aims_scope_embedding)->toHaveCount(1536)
        ->and($venue->template_rules)->toBeArray();
});
