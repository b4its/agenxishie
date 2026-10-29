<?php

declare(strict_types=1);

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\PeerCriticAgent;
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

it('answers free-form assistant questions with the LLM', function (): void {
    config()->set('omnigensi.agents_enabled', true);
    config()->set('omnigensi.api_key', 'omni_live_test');
    $this->app->forgetInstance(OmnigensiChatClient::class);

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/integrations/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'deepseek', 'display_name' => 'DeepSeek', 'provider' => '9router', 'provider_id' => 'p1', 'is_default' => true],
        ]], 200),
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'model' => 'deepseek', 'client' => 'agen xishie',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => 'Saran dari AI Omnigensi.'], 'finish_reason' => 'stop']],
            'latency_ms' => 8,
        ], 200),
    ]);

    $project = freshProjectWithRefs();
    $result = app(XishieAssistantService::class)->handle($project, 'Bagaimana cara memperkuat metodologi?');

    expect($result['reply'])->toBe('Saran dari AI Omnigensi.')
        ->and($result['data']['source'])->toBe('omnigensi-llm');
});

it('falls back to a context summary when the assistant LLM is disabled', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $project = freshProjectWithRefs();
    $result = app(XishieAssistantService::class)->handle($project, 'Apa saja referensi saya?');

    expect($result['data']['source'])->toBe('offline-fallback')
        ->and($result['reply'])->toContain('Konteks proyek');
});

it('analyses reviewer feedback into action items with the LLM', function (): void {
    config()->set('omnigensi.agents_enabled', true);
    config()->set('omnigensi.api_key', 'omni_live_test');
    $this->app->forgetInstance(OmnigensiChatClient::class);

    $json = json_encode([
        ['task' => 'Perjelas rumusan masalah', 'section_key' => 'chapter1', 'suggestion' => 'Rumusan masalah dipertegas.'],
        ['task' => 'Tambahkan sitasi terbaru', 'section_key' => 'chapter2', 'suggestion' => 'Sitasi 2023-2024 ditambahkan.'],
    ]);

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/integrations/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'deepseek', 'display_name' => 'DeepSeek', 'provider' => '9router', 'provider_id' => 'p1', 'is_default' => true],
        ]], 200),
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'model' => 'deepseek', 'client' => 'agen xishie',
            'choices' => [['index' => 0, 'message' => ['role' => 'assistant', 'content' => $json], 'finish_reason' => 'stop']],
            'latency_ms' => 9,
        ], 200),
    ]);

    $project = freshProjectWithRefs();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill(['sections_content' => [
        ['key' => 'chapter1', 'heading' => 'Bab 1', 'content' => 'latar belakang'],
        ['key' => 'chapter2', 'heading' => 'Bab 2', 'content' => 'tinjauan'],
    ]])->save();

    $revision = app(PeerCriticAgent::class)->analyse($project, $stage, 'Tolong perbaiki rumusan masalah dan sitasi.');

    expect($revision->action_items)->toHaveCount(2)
        ->and($revision->action_items[0]['task'])->toBe('Perjelas rumusan masalah')
        ->and($revision->diff_payload[0]['after'])->toBe('Rumusan masalah dipertegas.');

    config()->set('omnigensi.agents_enabled', false);
    config()->set('omnigensi.api_key', '');
    $this->app->forgetInstance(OmnigensiChatClient::class);
});

it('normalizes an array custom_guideline_path to a string at the model layer', function (): void {
    $project = makeProject();

    $project->custom_guideline_path = ['guidelines/x.docx'];
    $project->save();

    expect($project->fresh()->custom_guideline_path)->toBe('guidelines/x.docx');

    $project->custom_guideline_path = [];
    $project->save();

    expect($project->fresh()->custom_guideline_path)->toBeNull();
});

it('cleans LLM scribe output: strips preamble, markdown headings and meta', function (): void {
    $agent = app(AcademicScribeAgent::class);
    $method = new ReflectionMethod($agent, 'cleanSectionContent');
    $method->setAccessible(true);

    $raw = "Berikut adalah draf untuk sub-bab **1. Introduction** pada proposal jurnal ilmiah Anda:\n\n"
        ."## 1. Introduction\n\n"
        ."Pertumbuhan trafik jaringan mendorong kebutuhan deteksi anomali [Ravi 2021].\n\n"
        .'Semoga membantu.';

    $clean = $method->invoke($agent, $raw, '1. Introduction');

    expect($clean)->not->toContain('Berikut adalah draf')
        ->and($clean)->not->toContain('## ')
        ->and($clean)->not->toContain('Semoga membantu')
        ->and($clean)->toContain('Pertumbuhan trafik jaringan')
        ->and($clean)->toContain('[Ravi 2021]');
});

it('builds a valid "Surname YYYY" citation key even for initial-style authors', function (): void {
    $agent = app(ScholarScoutAgent::class);
    $method = new ReflectionMethod($agent, 'buildCitationKey');
    $method->setAccessible(true);

    // "R. Smith" harus menjadi "Smith 2019" (bukan "R. 2019").
    $key = $method->invoke($agent, ['authors' => ['R. Smith'], 'year' => 2019]);

    expect($key)->toBe('Smith 2019')
        ->and($key)->toMatch('/^[A-Z][A-Za-z\-]+ \d{4}$/');
});
