<?php

declare(strict_types=1);

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Illuminate\Support\Facades\Http;

/**
 * Integrasi LLM Omnigensi pada agen: penulisan & pencarian referensi nyata,
 * dengan fallback template saat backend tidak tersedia.
 */
function freshProjectWithRefs(): XishieProject
{
    $project = makeProject();
    seedReferences($project);

    return $project;
}

it('writes sections with the real LLM when agents are enabled', function (): void {
    config()->set('omnigensi.agents_enabled', true);
    config()->set('omnigensi.api_key', 'omni_live_test');
    $this->app->forgetInstance(OmnigensiChatClient::class);

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/integrations/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'deepseek', 'display_name' => 'DeepSeek', 'provider' => '9router', 'provider_id' => 'p1', 'is_default' => true],
        ]], 200),
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'model' => 'deepseek',
            'client' => 'agen xishie',
            'choices' => [[
                'index' => 0,
                'message' => ['role' => 'assistant', 'content' => 'Paragraf hasil LLM yang menyebutkan temuan penting.'],
                'finish_reason' => 'stop',
            ]],
            'latency_ms' => 12,
        ], 200),
    ]);

    $project = freshProjectWithRefs();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    $result = app(AcademicScribeAgent::class)->run($project, $stage);

    $sections = $stage->fresh()->sections_content;

    expect($result->isSuccessful())->toBeTrue()
        ->and($sections)->not->toBeEmpty()
        ->and($sections[0]['content'])->toContain('Paragraf hasil LLM');
});

it('falls back to deterministic template when agents are disabled', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $project = freshProjectWithRefs();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    $result = app(AcademicScribeAgent::class)->run($project, $stage);

    $sections = $stage->fresh()->sections_content;

    expect($result->isSuccessful())->toBeTrue()
        ->and($sections)->not->toBeEmpty()
        // Template menyebut judul proyek pada abstrak.
        ->and(json_encode($sections))->toContain($project->title);
});

it('suggests references as JSON from the LLM', function (): void {
    config()->set('omnigensi.agents_enabled', true);
    config()->set('omnigensi.api_key', 'omni_live_test');
    $this->app->forgetInstance(OmnigensiChatClient::class);

    $json = json_encode([
        ['title' => 'Deep Learning for Anomaly Detection', 'authors' => ['Doe'], 'year' => 2023, 'journal_name' => 'IEEE Access', 'abstract' => 'x', 'method' => 'CNN', 'result' => 'F1 0.9', 'gap' => 'realtime', 'doi' => null],
        ['title' => 'IoT Security Survey', 'authors' => ['Smith'], 'year' => 2022, 'journal_name' => 'Sensors', 'abstract' => 'y', 'method' => 'survey', 'result' => 'taxonomy', 'gap' => 'deployment', 'doi' => '10.1/x'],
    ]);

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/integrations/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'deepseek', 'display_name' => 'DeepSeek', 'provider' => '9router', 'provider_id' => 'p1', 'is_default' => true],
        ]], 200),
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'model' => 'deepseek', 'client' => 'agen xishie',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => "```json\n{$json}\n```"], 'finish_reason' => 'stop']],
            'latency_ms' => 10,
        ], 200),
    ]);

    $project = makeProject();
    $refs = app(ScholarScoutAgent::class)->suggestReferences($project, 2);

    expect($refs)->toHaveCount(2)
        ->and($refs[0]['title'])->toBe('Deep Learning for Anomaly Detection')
        ->and($refs[0]['source'])->toBe('omnigensi-llm')
        ->and($refs[1]['doi'])->toBe('10.1/x');
});

it('returns no suggestions when agents are disabled', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $project = makeProject();

    expect(app(ScholarScoutAgent::class)->suggestReferences($project))->toBe([]);
});
