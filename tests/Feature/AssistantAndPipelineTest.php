<?php

declare(strict_types=1);

use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\GuidelineEngine\CustomGuidelineParser;
use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelinePresetFactory;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Publication\CitationFormatter;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Jobs\RunFullPipelineJob;
use App\Omnigensi\Jobs\RunStagePipelineJob;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Illuminate\Support\Facades\Queue;

it('dispatches automation pipeline jobs to the queue', function (): void {
    Queue::fake();

    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);

    RunStagePipelineJob::dispatchFor($project, ProjectStage::DRAFT);
    RunFullPipelineJob::dispatchFor($project);

    Queue::assertPushed(RunStagePipelineJob::class);
    Queue::assertPushed(RunFullPipelineJob::class);
});

it('runs the stage pipeline job and completes the stage', function (): void {
    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    RunStagePipelineJob::dispatchSync($project->id, ProjectStage::DRAFT->value);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($draft->status->value)->toBe('completed');
});

it('skips the automation job when control mode was swapped', function (): void {
    $project = makeProject(['control_mode' => ControlMode::MANUAL]);
    seedReferences($project);

    RunStagePipelineJob::dispatchSync($project->id, ProjectStage::DRAFT->value);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($draft->sections_content)->toBeNull();
});

it('accepts the enum-based agent contract signature', function (): void {
    $project = makeProject();
    seedReferences($project);

    $result = app(AcademicScribeAgent::class)->execute($project, ProjectStage::DRAFT);

    expect($result->isSuccessful())->toBeTrue()
        ->and($result->citationsUsed)->not->toBeEmpty();
});

it('parses slash commands from assistant input', function (): void {
    expect(SlashCommand::tryParse('/sitasi topik saya'))->toBe(SlashCommand::SITASI)
        ->and(SlashCommand::tryParse('/cek-gap'))->toBe(SlashCommand::CEK_GAP)
        ->and(SlashCommand::tryParse('halo biasa'))->toBeNull()
        ->and(SlashCommand::names())->toContain('/parafrase', '/cek-pedoman', '/matriks-literatur');
});

it('answers /sitasi with verified citation keys', function (): void {
    $project = makeProject();
    seedReferences($project);

    $result = app(XishieAssistantService::class)->handle($project, '/sitasi deteksi anomali');

    expect($result['command'])->toBe('/sitasi')
        ->and($result['reply'])->toContain('Prasetyo 2023');
});

it('answers /parafrase with a rewritten sentence', function (): void {
    $project = makeProject();

    $result = app(XishieAssistantService::class)->handle($project, '/parafrase Metode ini sangat efektif');

    expect($result['reply'])->toContain('Dengan demikian')
        ->and($result['data']['original'])->toBe('Metode ini sangat efektif');
});

it('answers /cek-gap from the literature matrix', function (): void {
    $project = makeProject();
    seedReferences($project);

    $result = app(XishieAssistantService::class)->handle($project, '/cek-gap');

    expect($result['data']['gaps'])->not->toBeEmpty()
        ->and($result['reply'])->toContain('Gap');
});

it('answers /cek-pedoman with a compliance score', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $result = app(XishieAssistantService::class)->handle($project, '/cek-pedoman');

    expect($result['data'])->toHaveKey('score')
        ->and($result['reply'])->toContain('Compliance');
});

it('answers unknown input with project context', function (): void {
    $project = makeProject();

    $result = app(XishieAssistantService::class)->handle($project, 'jelaskan konteks');

    expect($result['command'])->toBe('freeform')
        ->and($result['reply'])->toContain('referensi');
});

it('extracts custom guideline rules from uploaded text', function (): void {
    $fallback = app(GuidelinePresetFactory::class)->forDocumentType(DocumentType::SKRIPSI, 'apa7');

    $text = "BAB I PENDAHULUAN\nBab ini memuat latar belakang.\n"
        ."BAB II TINJAUAN PUSTAKA\nBerisi kajian teori.\n"
        ."BAB III METODOLOGI PENELITIAN\nSesuai panduan kampus.\n"
        ."Panjang naskah 8.000 - 15.000 kata dengan sitasi APA 7th.\n"
        .'Abstrak 200 - 250 kata.';

    $definition = app(CustomGuidelineParser::class)->parse($text, $fallback);

    expect($definition)->toBeInstanceOf(GuidelineDefinition::class)
        ->and($definition->source)->toBe('custom_upload')
        ->and($definition->citationStyle)->toBe('apa7')
        ->and($definition->minTotalWords)->toBe(8000)
        ->and($definition->maxTotalWords)->toBe(15000)
        ->and($definition->minAbstractWords)->toBe(200)
        ->and($definition->maxAbstractWords)->toBe(250)
        ->and($definition->sections)->not->toBeEmpty();
});

it('rejects empty guideline text', function (): void {
    $fallback = app(GuidelinePresetFactory::class)->forDocumentType(DocumentType::JOURNAL, 'ieee');

    expect(fn () => app(CustomGuidelineParser::class)->parse('', $fallback))
        ->toThrow(InvalidArgumentException::class);
});

it('formats bibliography entries for each citation style', function (CitationStyle $style, string $needle): void {
    $project = makeProject();
    seedReferences($project);

    $reference = $project->references()->first();
    $formatted = app(CitationFormatter::class)->format($reference, $style);

    expect($formatted)->toContain($needle)
        ->and($formatted)->toContain((string) $reference->title);
})->with([
    'apa7' => [CitationStyle::APA7, '2023'],
    'ieee' => [CitationStyle::IEEE, 'Prasetyo 2023'],
    'vancouver' => [CitationStyle::VANCOUVER, 'Prasetyo'],
    'chicago' => [CitationStyle::CHICAGO, 'Prasetyo'],
]);

it('builds a markdown literature matrix', function (): void {
    $project = makeProject();
    seedReferences($project);

    $builder = app(LiteratureMatrixBuilder::class);

    expect($builder->build($project))->toHaveCount(2)
        ->and($builder->toMarkdown($project))->toContain('| No | Penulis | Tahun |');
});
