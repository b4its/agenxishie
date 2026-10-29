<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\XishieAssistantPage;
use App\Models\NaskahDocument;
use App\Models\User;
use Livewire\Livewire;

/**
 * Konsol Xishie Assistant mandiri: render, palet slash command, kirim pesan.
 */
it('renders the assistant page with project context and command palette', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject(['title' => 'Proyek Asisten']);
    seedReferences($project);

    Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->assertSuccessful()
        ->assertSee('Xishie');
});

it('exposes the slash command palette', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    $names = array_column($page->instance()->commands(), 'name');

    expect($names)->toContain('/sitasi')
        ->and($names)->toContain('/parafrase')
        ->and($names)->toContain('/cek-gap')
        ->and($names)->toContain('/cek-pedoman')
        ->and($names)->toContain('/matriks-literatur')
        ->and($names)->toContain('/cari-referensi');
});

it('inserts a slash command into the message box', function (): void {
    Livewire::test(XishieAssistantPage::class)
        ->call('insertCommand', '/sitasi')
        ->assertSet('message', '/sitasi ');
});

it('answers a slash command and appends to the transcript', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject();
    seedReferences($project);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('message', '/sitasi bagaimana deteksi anomali?')
        ->call('send')
        ->assertSuccessful();

    $log = $page->get('log');

    expect($log)->toHaveCount(2)
        ->and($log[0]['role'])->toBe('user')
        ->and($log[1]['role'])->toBe('assistant')
        ->and($log[1]['content'])->not->toBe('');
});

it('answers a free-form question when agents are disabled (offline summary)', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('message', 'Ringkas naskah pada tahap aktif')
        ->call('send');

    $log = $page->get('log');

    // Intensi "ringkas" tanpa draf jatuh ke jawaban ringkas deterministik.
    expect($log)->toHaveCount(2)
        ->and($log[1]['content'])->toContain('Belum ada draf naskah');
});

it('renders markdown (table) in assistant replies', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    $html = $page->instance()->markdownToHtml("| A | B |\n|---|---|\n| 1 | 2 |");

    expect($html)->toContain('<table>')->toContain('<td>1</td>');
});

it('manages assistant models and default fallback in assistant page', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    $currentModel = $page->instance()->getCurrentAssistantModel();
    expect($currentModel)->not->toBeEmpty();

    $models = $page->instance()->getAvailableModels();
    expect($models)->toBeArray()
        ->and(count($models))->toBeGreaterThanOrEqual(3);

    $ids = array_column($models, 'id');
    expect($ids)->toContain('hv/agnes-3.0-flash')
        ->and($ids)->toContain('qwen-2.5-72b-instruct');

    $page->call('setAssistantModel', 'hv/agnes-3.0-flash')
        ->assertSet('currentAssistantModel', 'hv/agnes-3.0-flash');

    expect($page->instance()->getCurrentAssistantModel())->toBe('hv/agnes-3.0-flash');
});

it('categorizes and filters the 26 slash commands in assistant page', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    $categories = $page->instance()->getCommandCategories();
    expect($categories)->toHaveKeys(['all', 'structure', 'literature', 'audit', 'format']);

    // Default 'all'
    $allCommands = $page->instance()->filteredCommands();
    expect(count($allCommands))->toBe(26);

    // Structure category
    $page->call('setCommandCategory', 'structure');
    $structCommands = array_column($page->instance()->filteredCommands(), 'name');
    expect($structCommands)->toContain('/pendahuluan')
        ->and($structCommands)->toContain('/abstrak')
        ->and($structCommands)->toContain('/metodologi')
        ->and($structCommands)->not->toContain('/sitasi');

    // Literature category
    $page->call('setCommandCategory', 'literature');
    $litCommands = array_column($page->instance()->filteredCommands(), 'name');
    expect($litCommands)->toContain('/sitasi')
        ->and($litCommands)->toContain('/daftar-pustaka')
        ->and($litCommands)->not->toContain('/pendahuluan');
});

it('regenerates last response using the active assistant model', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject();
    seedReferences($project);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->call('setAssistantModel', 'hv/agnes-3.0-flash')
        ->set('message', '/abstrak')
        ->call('send');

    $logBefore = $page->get('log');
    expect($logBefore)->toHaveCount(2)
        ->and($logBefore[1]['role'])->toBe('assistant');

    // Regenerate
    $page->call('regenerateLastResponse');
    $logAfter = $page->get('log');

    expect($logAfter)->toHaveCount(3)
        ->and($logAfter[2]['role'])->toBe('assistant')
        ->and($logAfter[2]['is_regenerated'])->toBeTrue()
        ->and($logAfter[2]['model'])->toBe('hv/agnes-3.0-flash');
});

it('executes current model to naskah studio directly from assistant page', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->call('setAssistantModel', 'hv/agnes-3.0-flash')
        ->set('message', '/metodologi')
        ->call('executeCurrentModelToStudio')
        ->assertDispatched('assistant-updated');

    $log = $page->get('log');
    expect($log)->toHaveCount(2)
        ->and($log[1]['model'])->toBe('hv/agnes-3.0-flash');

    $doc = \App\Models\NaskahDocument::query()->where('project_id', $project->id)->first();
    expect($doc)->not->toBeNull()
        ->and(count($doc->blocks))->toBeGreaterThan(1);
});

it('filters slash commands dynamically using commandSearch query', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    // Initial all 26
    expect(count($page->instance()->filteredCommands()))->toBe(26);

    // Search query 'abstrak'
    $page->set('commandSearch', 'abstrak');
    $filtered = $page->instance()->filteredCommands();
    expect(count($filtered))->toBe(1)
        ->and($filtered[0]['name'])->toBe('/abstrak');

    // Search query 'sitasi'
    $page->set('commandSearch', 'sitasi');
    $filtered = $page->instance()->filteredCommands();
    expect(array_column($filtered, 'name'))->toContain('/sitasi');
});

it('exports assistant conversation transcript as styled HTML document', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject(['title' => 'Riset Evaluasi Model']);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('message', '/abstrak')
        ->call('send');

    $response = $page->instance()->exportTranscript('html');
    expect($response)->not->toBeNull()
        ->and($response->headers->get('content-type'))->toBe('text/html; charset=UTF-8');

    ob_start();
    $response->sendContent();
    $htmlContent = ob_get_clean();

    expect($htmlContent)->toContain('<!DOCTYPE html>')
        ->and($htmlContent)->toContain('Transkrip Percakapan Xishie Assistant')
        ->and($htmlContent)->toContain('Riset Evaluasi Model');
});

it('answers all extended slash commands in standalone naskah studio mode without project', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $studio = Livewire::test(\App\Livewire\NaskahStudioApp::class);

    $commands = [
        '/kebaruan' => 'Pernyataan Kebaruan Ilmiah',
        '/daftar-isi' => 'Bagan Outline & Struktur Dokumen',
        '/daftar-pustaka' => 'Daftar Pustaka Terstandar',
        '/format-paragraf' => 'Format Paragraf',
        '/catatan-kaki' => 'Format Catatan Kaki',
        '/keterbacaan' => 'Laporan Analisis Keterbacaan Dokumen',
    ];

    foreach ($commands as $cmd => $expectedFragment) {
        $res = $studio->instance()->answerResponse(null, $cmd);
        expect($res)->toHaveKey('reply')
            ->and($res['reply'])->toContain($expectedFragment);
    }
});

it('manages academic persona mode in assistant page', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    expect($page->get('currentPersona'))->toBe('academic_senior');

    $page->call('setPersona', 'critical_reviewer');
    expect($page->get('currentPersona'))->toBe('critical_reviewer');

    $page->call('setPersona', 'methodologist');
    expect($page->get('currentPersona'))->toBe('methodologist');

    expect($page->instance()->getPersonas())->toHaveKeys([
        'academic_senior',
        'critical_reviewer',
        'proofreader',
        'methodologist',
        'novelty',
    ]);
});

it('regenerates specific assistant message using regenerateMessage', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->call('setAssistantModel', 'hv/agnes-3.0-flash')
        ->set('message', '/abstrak')
        ->call('send');

    $log = $page->get('log');
    expect($log)->toHaveCount(2);

    $page->call('regenerateMessage', 1);

    $logAfter = $page->get('log');
    expect($logAfter)->toHaveCount(2)
        ->and($logAfter[1]['is_regenerated'])->toBeTrue()
        ->and($logAfter[1]['model'])->toBe('hv/agnes-3.0-flash');
});

it('exports assistant conversation transcript as LaTeX document', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject(['title' => 'Riset Evaluasi Model']);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('message', '/abstrak')
        ->call('send');

    $response = $page->instance()->exportTranscript('latex');
    expect($response)->not->toBeNull()
        ->and($response->headers->get('content-type'))->toBe('application/x-latex; charset=UTF-8');

    ob_start();
    $response->sendContent();
    $latexContent = ob_get_clean();

    expect($latexContent)->toContain('\\documentclass')
        ->and($latexContent)->toContain('Riset Evaluasi Model')
        ->and($latexContent)->toContain('\\begin{document}');
});

it('exports assistant conversation transcript as CSV spreadsheet document', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject(['title' => 'Riset Evaluasi Model']);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('message', '/abstrak')
        ->call('send');

    $response = $page->instance()->exportTranscript('csv');
    expect($response)->not->toBeNull()
        ->and($response->headers->get('content-type'))->toBe('text/csv; charset=UTF-8');

    ob_start();
    $response->sendContent();
    $csvContent = ob_get_clean();

    expect($csvContent)->toContain('Role,Model,Persona,Scope,Command')
        ->and($csvContent)->toContain('user')
        ->and($csvContent)->toContain('assistant');
});

it('switches assistant focus scope between project, manuscript, and literature', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    expect($page->get('assistantScope'))->toBe('project');

    $page->call('setScope', 'manuscript');
    expect($page->get('assistantScope'))->toBe('manuscript');

    $page->call('setScope', 'literature');
    expect($page->get('assistantScope'))->toBe('literature');
});

it('executes slash command immediately using executeCommandDirectly', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->call('executeCommandDirectly', '/sitasi');

    $log = $page->get('log');
    expect($log)->toHaveCount(2)
        ->and($log[0]['content'])->toBe('/sitasi')
        ->and($log[1]['role'])->toBe('assistant');
});

it('renders assistant session telemetry bar and direct execution buttons', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    $html = $page->html();
    expect($html)->toContain('SESI:')
        ->and($html)->toContain('⚡ Cek Kebaruan')
        ->and($html)->toContain('⚡ Cek Keterbacaan')
        ->and($html)->toContain('⚡ Format Pustaka');
});

it('provides stage-recommended prompts matching the active project stage', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject(['current_stage' => ProjectStage::DRAFT]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);
    $prompts = $page->instance()->getStageRecommendedPrompts();
    $commands = array_column($prompts, 'command');

    expect($commands)->toContain('/kebaruan')
        ->and($commands)->toContain('/cek-gap')
        ->and($commands)->toContain('/cari-referensi');

    // Switch to Proposal stage
    $project->current_stage = ProjectStage::PROPOSAL;
    $project->save();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);
    $prompts = $page->instance()->getStageRecommendedPrompts();
    $commands = array_column($prompts, 'command');

    expect($commands)->toContain('/pendahuluan')
        ->and($commands)->toContain('/metodologi')
        ->and($commands)->toContain('/matriks-literatur');

    // Switch to Final stage
    $project->current_stage = ProjectStage::FINAL;
    $project->save();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);
    $prompts = $page->instance()->getStageRecommendedPrompts();
    $commands = array_column($prompts, 'command');

    expect($commands)->toContain('/abstrak')
        ->and($commands)->toContain('/kesimpulan')
        ->and($commands)->toContain('/daftar-pustaka');
});

it('supports undo and restore for cleared conversation transcripts', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $initialLog = [
        ['role' => 'user', 'content' => 'Pertanyaan riset', 'created_at' => '10:00:00'],
        ['role' => 'assistant', 'content' => 'Jawaban akademik', 'created_at' => '10:00:02'],
    ];

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', $initialLog)
        ->call('clearLog')
        ->assertDispatched('assistant-cleared');

    expect($page->get('log'))->toBeEmpty()
        ->and($page->get('lastClearedLog'))->toHaveCount(2);

    // Pulihkan transkrip
    $page->call('restoreClearedLog')
        ->assertDispatched('assistant-updated');

    expect($page->get('log'))->toHaveCount(2)
        ->and($page->get('log')[0]['content'])->toBe('Pertanyaan riset')
        ->and($page->get('lastClearedLog'))->toBeEmpty();
});

it('archives assistant transcript into document snapshots', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $doc = NaskahDocument::factory()->create([
        'project_id' => $project->id,
        'title' => 'Naskah Proyek',
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Teks awal naskah'],
        ],
        'style_meta' => ['snapshots' => []],
    ]);

    $log = [
        ['role' => 'user', 'content' => 'Analisis metodologi', 'created_at' => '11:00:00'],
        ['role' => 'assistant', 'content' => 'Rekomendasi metode campuran', 'created_at' => '11:00:05'],
    ];

    Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', $log)
        ->call('saveSessionAsSnapshot');

    $doc->refresh();
    $snapshots = $doc->style_meta['snapshots'] ?? [];
    expect($snapshots)->toHaveCount(1)
        ->and($snapshots[0]['label'])->toContain('Sesi Asisten')
        ->and($snapshots[0]['assistant_log'])->toHaveCount(2);
});

it('supports starring and filtering assistant messages in conversation transcript', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $log = [
        ['role' => 'user', 'content' => 'Pertanyaan metode evaluasi', 'created_at' => '12:00:00'],
        ['role' => 'assistant', 'content' => 'Metode evaluasi F1-score dan AUC-ROC', 'created_at' => '12:00:04'],
        ['role' => 'user', 'content' => 'Pertanyaan kedua tentang dataset', 'created_at' => '12:01:00'],
        ['role' => 'assistant', 'content' => 'Dataset menggunakan repositori UCI', 'created_at' => '12:01:05'],
    ];

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', $log)
        ->call('toggleStarMessage', 1);

    expect($page->get('starredMessages'))->toBe([1]);

    // Unstar
    $page->call('toggleStarMessage', 1);
    expect($page->get('starredMessages'))->toBeEmpty();

    // Star again and check HTML render
    $page->call('toggleStarMessage', 1);
    expect($page->html())->toContain('★ penting')
        ->and($page->html())->toContain('Metode evaluasi F1-score');

    // Test transcript search filtering
    $page->set('transcriptSearch', 'dataset');
    expect($page->html())->toContain('Dataset menggunakan repositori UCI')
        ->and($page->html())->not->toContain('Metode evaluasi F1-score');
});
