<?php

declare(strict_types=1);

use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\XishieAssistantPage;
use App\Models\NaskahDocument;
use App\Models\ProjectReference;
use App\Models\User;
use App\Models\XishieProject;
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
    expect(count($allCommands))->toBe(count(SlashCommand::cases()));

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

    // Initial all commands
    expect(count($page->instance()->filteredCommands()))->toBe(count(SlashCommand::cases()));

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
        ->and($snapshots[0]['id'])->not->toBeEmpty()
        ->and($snapshots[0]['at'])->not->toBeEmpty()
        ->and($snapshots[0]['count'])->toBe(1)
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

it('supports only-starred toggle filter and clears starred messages', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $log = [
        ['role' => 'user', 'content' => 'Tanya bab 1', 'created_at' => '12:00:00'],
        ['role' => 'assistant', 'content' => 'Jawaban bab 1 penting', 'created_at' => '12:00:04'],
        ['role' => 'user', 'content' => 'Tanya bab 2', 'created_at' => '12:01:00'],
        ['role' => 'assistant', 'content' => 'Jawaban bab 2 umum', 'created_at' => '12:01:05'],
    ];

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', $log)
        ->call('toggleStarMessage', 1);

    expect($page->get('onlyStarred'))->toBeFalse();
    $page->call('toggleOnlyStarred');
    expect($page->get('onlyStarred'))->toBeTrue();

    // With onlyStarred active, export should export only starred messages
    $export = $page->instance()->exportTranscript('json');
    expect($export)->not->toBeNull();
    ob_start();
    $export->sendContent();
    $json = ob_get_clean();
    $data = json_decode($json, true);

    expect($data['total_messages'])->toBe(1)
        ->and($data['messages'][0]['content'])->toBe('Jawaban bab 1 penting');

    // Clear starred
    $page->call('clearStarred');
    expect($page->get('starredMessages'))->toBeEmpty();
});

it('tracks total generated words in telemetry session bar in assistant page', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $log = [
        ['role' => 'user', 'content' => 'Pertanyaan', 'created_at' => '12:00:00'],
        ['role' => 'assistant', 'content' => 'Satu dua tiga empat lima kata.', 'created_at' => '12:00:04'],
        ['role' => 'assistant', 'content' => 'Enam tujuh delapan sembilan sepuluh kata lagi.', 'created_at' => '12:01:05'],
    ];

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', $log);

    expect($page->instance()->getTotalWordsGenerated())->toBe(13)
        ->and($page->html())->toContain('GENERASI:')
        ->and($page->html())->toContain('13 kata')
        ->and($page->html())->toContain('6 kata')
        ->and($page->html())->toContain('7 kata');
});

it('exports assistant conversation transcript as a new NaskahDocument in Naskah Studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $project = makeProject(['title' => 'Riset Komparatif LLM']);

    $log = [
        ['role' => 'user', 'content' => 'Bagaimana metodologi benchmarking LLM?', 'created_at' => '10:00:00'],
        ['role' => 'assistant', 'content' => "Metodologi benchmarking memerlukan evaluasi holistik:\n\n| Metrik | Definisi | Skala |\n| Akurasi | Kebenaran jawaban | 0-100 |\n| Latensi | Waktu inferensi | ms |\n\n> Pastikan dataset evaluasi bebas dari kontaminasi data latih.", 'created_at' => '10:00:05'],
    ];

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', $log);

    $document = $page->instance()->exportTranscriptToDocument('Dokumen Hasil Benchmarking LLM');

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('Dokumen Hasil Benchmarking LLM')
        ->and($document->project_id)->toBe($project->id)
        ->and($document->source_type)->toBe(NaskahDocument::SOURCE_PROJECT)
        ->and($document->word_count)->toBeGreaterThan(10);

    $blocks = $document->blocks;
    expect(count($blocks))->toBeGreaterThanOrEqual(4);

    $types = array_column($blocks, 'type');
    expect($types)->toContain('heading')
        ->and($types)->toContain('callout')
        ->and($types)->toContain('table')
        ->and($types)->toContain('quote');

    // Also test with empty log returns null
    $pageEmpty = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', []);
    expect($pageEmpty->instance()->exportTranscriptToDocument())->toBeNull();
});

it('supports selecting context document and searching project references in assistant page', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $project = makeProject(['title' => 'Riset Evaluasi Asisten']);

    // Create a manuscript document for this project
    $doc = NaskahDocument::create([
        'title' => 'Draf Bab 1 Evaluasi',
        'project_id' => $project->id,
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Teks awal pendahuluan riset.'],
        ],
        'word_count' => 6,
    ]);

    // Create a reference
    $project->references()->create([
        'citation_key' => 'smith2023',
        'title' => 'Deep Learning in Natural Language Processing',
        'authors' => ['John Smith', 'Alice Johnson'],
        'year' => 2023,
        'journal_name' => 'IEEE Transactions on AI',
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Select context document
    $page->call('selectContextDocument', $doc->id);
    expect($page->get('activeDocumentId'))->toBe($doc->id);

    // Apply response to selected document
    $page->set('log', [
        ['role' => 'assistant', 'content' => "## Tambahan Pendahuluan\nParagraf tambahan dari asisten AI."],
    ])->call('applyToNaskahStudio', 0)
        ->assertNotified('Berhasil Diterapkan ke Naskah Studio');

    $updatedDoc = NaskahDocument::find($doc->id);
    expect($updatedDoc->word_count)->toBeGreaterThan(6);

    // Search project references
    $results = $page->instance()->searchProjectReferences('Deep Learning');
    expect(count($results))->toBe(1)
        ->and($results[0]['citation_key'])->toBe('smith2023')
        ->and($results[0]['title'])->toContain('Natural Language Processing');

    // Deselect context document
    $page->call('selectContextDocument', null);
    expect($page->get('activeDocumentId'))->toBeNull();
});

it('supports academic prompt templates catalog, category filtering, and direct execution', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = makeProject(['title' => 'Riset Pembelajaran Mesin']);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Check catalog toggle
    expect($page->get('showPromptTemplates'))->toBeFalse();
    $page->call('togglePromptTemplates');
    expect($page->get('showPromptTemplates'))->toBeTrue();

    // Check templates definitions
    $templates = $page->instance()->getAcademicPromptTemplates();
    expect($templates)->toHaveKeys(['gap_matrix', 'critical_lit_synthesis', 'method_justification', 'peer_review_rebuttal', 'structured_abstract', 'implications_limitations'])
        ->and($templates['gap_matrix']['title'])->toBe('Matriks Sintesis Research Gap');

    // Filter by category
    $page->call('setTemplateCategoryFilter', 'Audit & Validasi');
    $filtered = $page->instance()->getFilteredPromptTemplates();
    expect(count($filtered))->toBe(1)
        ->and(array_key_first($filtered))->toBe('gap_matrix');

    // Reset filter
    $page->call('setTemplateCategoryFilter', 'all');
    expect(count($page->instance()->getFilteredPromptTemplates()))->toBe(8);

    // Apply template into input message
    $page->call('applyPromptTemplate', 'gap_matrix', false);
    expect($page->get('message'))->toContain('Matriks Sintesis Research Gap');

    // Apply template with direct execution
    $page->call('applyPromptTemplate', 'structured_abstract', true);
    $log = $page->get('log');
    expect(count($log))->toBeGreaterThanOrEqual(2)
        ->and($log[0]['content'])->toContain('abstrak terstruktur')
        ->and($log[1]['role'])->toBe('assistant');
});

it('grounds assistant responses in selected context document', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = makeProject(['title' => 'Riset Bioinformatika']);

    $doc = NaskahDocument::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Draf Manuskrip Bioinformatika',
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan Bioinformatika'],
            ['type' => 'paragraph', 'text' => 'Bioinformatika mengintegrasikan sains komputasi dengan biologi molekuler untuk menganalisis data genomik skala masif.'],
        ],
        'word_count' => 16,
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Select context document
    $page->call('selectContextDocument', $doc->id);
    expect($page->get('activeDocumentId'))->toBe($doc->id);

    // Run /ringkas with selected document context
    $page->call('executeCommandDirectly', '/ringkas');
    $log = $page->get('log');
    $lastReply = end($log)['content'];
    expect($lastReply)->toContain('Ringkasan Dokumen Naskah: Draf Manuskrip Bioinformatika')
        ->and($lastReply)->toContain('Bioinformatika mengintegrasikan');

    // Run /keterbacaan with selected document context
    $page->call('executeCommandDirectly', '/keterbacaan');
    $log = $page->get('log');
    $readabilityReply = end($log)['content'];
    expect($readabilityReply)->toContain('Laporan Analisis Keterbacaan Naskah')
        ->and($readabilityReply)->toContain('Dokumen Naskah: Draf Manuskrip Bioinformatika');
});

it('supports reference search drawer and citation key insertion in assistant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = makeProject(['title' => 'Riset Deep Learning']);

    $project->references()->create([
        'citation_key' => 'vaswani2017',
        'title' => 'Attention Is All You Need',
        'authors' => ['Ashish Vaswani', 'Noam Shazeer'],
        'year' => 2017,
        'journal_name' => 'NeurIPS',
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Test toggle drawer
    expect($page->get('showReferenceDrawer'))->toBeFalse();
    $page->call('toggleReferenceDrawer');
    expect($page->get('showReferenceDrawer'))->toBeTrue();

    // Test searchProjectReferences
    $results = $page->instance()->searchProjectReferences('Vaswani');
    expect(count($results))->toBe(1)
        ->and($results[0]['citation_key'])->toBe('vaswani2017');

    // Test insertCitationKey
    $page->call('insertCitationKey', 'vaswani2017');
    expect($page->get('message'))->toContain('[@vaswani2017]');
});

it('supports prompt history tracking, reuse, and session summary compilation in assistant', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = makeProject(['title' => 'Riset Bioetika Kedokteran']);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Send first query
    $page->set('message', 'Bagaimana menyusun kerangka konseptual?')
        ->call('send');

    // Send second query
    $page->set('message', 'Buatkan daftar pertanyaan wawancara etis')
        ->call('send');

    $history = $page->get('promptHistory');
    expect(count($history))->toBe(2)
        ->and($history[0])->toBe('Buatkan daftar pertanyaan wawancara etis')
        ->and($history[1])->toBe('Bagaimana menyusun kerangka konseptual?');

    // Select prompt from history
    $page->call('selectPromptFromHistory', 'Bagaimana menyusun kerangka konseptual?');
    expect($page->get('message'))->toBe('Bagaimana menyusun kerangka konseptual?');

    // Generate session summary
    $page->call('generateSessionSummary');
    $log = $page->get('log');
    $lastMsg = end($log);

    expect($lastMsg['role'])->toBe('assistant')
        ->and($lastMsg['model'])->toBe('session_synthesis')
        ->and($lastMsg['content'])->toContain('Rangkuman Eksekutif Sesi Xishie Assistant')
        ->and($lastMsg['content'])->toContain('Riset Bioetika Kedokteran')
        ->and($lastMsg['content'])->toContain('Rekomendasi Langkah Tindak Lanjut Akademik');

    // Clear prompt history
    $page->call('clearPromptHistory');
    expect($page->get('promptHistory'))->toBe([]);
});

it('supports citation audit command and direct execution chip in assistant page', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject(['title' => 'Riset Neuroinformatika']);

    $project->references()->create([
        'citation_key' => 'lecun2015',
        'title' => 'Deep Learning',
        'authors' => ['Yann LeCun'],
        'year' => 2015,
    ]);

    $doc = NaskahDocument::factory()->create([
        'project_id' => $project->id,
        'title' => 'Draf Manuskrip Neuro',
        'blocks' => [
            ['id' => 'b1', 'type' => 'paragraph', 'text' => 'Pendekatan deep learning telah diuraikan oleh [@lecun2015].'],
            ['id' => 'b2', 'type' => 'paragraph', 'text' => 'Namun model kuantum [@unregistered2025] belum tervalidasi.'],
        ],
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);
    $page->set('activeDocumentId', $doc->id);

    // Direct execution of citation audit
    $page->call('executeCommandDirectly', '/sitasi audit');

    $log = $page->get('log');
    $lastMsg = end($log);

    expect($lastMsg['role'])->toBe('assistant')
        ->and($lastMsg['content'])->toContain('Laporan Audit Integritas Sitasi Akademik')
        ->and($lastMsg['content'])->toContain('lecun2015')
        ->and($lastMsg['content'])->toContain('unregistered2025')
        ->and($lastMsg['content'])->toContain('Densitas Sitasi Akademik')
        ->and($lastMsg['data']['verified'])->toContain('lecun2015')
        ->and($lastMsg['data']['unmatched'])->toContain('unregistered2025');
});

it('supports equation and quote parsing when applying assistant response to naskah studio', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $doc = NaskahDocument::factory()->create([
        'project_id' => $project->id,
        'title' => 'Naskah AI Eksperimen',
        'blocks' => [
            ['id' => 'b1', 'type' => 'title', 'level' => 0, 'text' => 'Naskah AI Eksperimen'],
        ],
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);
    $page->set('activeDocumentId', $doc->id);

    // Mock an assistant response with equation, quote, callout, table, and list
    $content = <<<'EOT'
# Teorema Utama

> Definisi formal ruang vektor Hilbert.

$$\mathcal{H} = \int |\psi\rangle d\mu$$

> [!NOTE] Perhatikan kondisi batas ortogonalitas fungsi gelombang.

| Parameter | Nilai |
| --- | --- |
| alpha | 0.05 |

- Asumsi normalitas residu
- Homoskedastisitas
EOT;

    $page->set('log', [
        [
            'role' => 'assistant',
            'content' => $content,
            'model' => 'agnes',
            'persona' => 'academic_senior',
            'scope' => 'project',
            'command' => 'chat',
            'data' => [],
            'created_at' => '10:00:00',
        ],
    ]);

    // Apply to Naskah Studio
    $page->call('applyToNaskahStudio', 0);

    $doc->refresh();
    $blocks = (array) $doc->blocks;
    $types = array_column($blocks, 'type');

    expect($types)->toContain('heading')
        ->and($types)->toContain('quote')
        ->and($types)->toContain('equation')
        ->and($types)->toContain('callout')
        ->and($types)->toContain('table')
        ->and($types)->toContain('bullet');
});

it('supports deleting individual message from assistant transcript and shifting starred indices', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    $log = [
        ['role' => 'user', 'content' => 'Pertanyaan 1', 'created_at' => '10:00:00'],
        ['role' => 'assistant', 'content' => 'Jawaban 1', 'created_at' => '10:00:02'],
        ['role' => 'user', 'content' => 'Pertanyaan 2', 'created_at' => '10:01:00'],
        ['role' => 'assistant', 'content' => 'Jawaban 2', 'created_at' => '10:01:02'],
    ];

    $page->set('log', $log);
    // Star index 1 (Jawaban 1) and index 3 (Jawaban 2)
    $page->set('starredMessages', [1, 3]);

    // Delete index 1 (Jawaban 1)
    $page->call('deleteMessage', 1);

    $remaining = $page->get('log');
    expect(count($remaining))->toBe(3)
        ->and($remaining[0]['content'])->toBe('Pertanyaan 1')
        ->and($remaining[1]['content'])->toBe('Pertanyaan 2')
        ->and($remaining[2]['content'])->toBe('Jawaban 2');

    // Index 3 should have shifted to index 2
    $starred = $page->get('starredMessages');
    expect($starred)->toBe([2]);
});

it('supports thesaurus slash command and direct execution chip in assistant', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Test indexed thesaurus word query
    $page->call('executeCommandDirectly', '/tesaurus penelitian');
    $log = $page->get('log');
    $lastReply = end($log)['content'] ?? '';

    expect($lastReply)->toContain('Tesaurus Akademik')
        ->and($lastReply)->toContain('kajian')
        ->and($lastReply)->toContain('riset')
        ->and($lastReply)->toContain('investigasi');

    // Test index catalog without argument
    $page->call('executeCommandDirectly', '/sinonim');
    $logAfter = $page->get('log');
    $catalogReply = end($logAfter)['content'] ?? '';

    expect($catalogReply)->toContain('Sertakan kata akademik')
        ->and($catalogReply)->toContain('penelitian')
        ->and($catalogReply)->toContain('metode');
});

it('provides project references on open drawer and filters dynamically on search', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();
    seedReferences($project);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Initial drawer open with empty query returns latest project references
    $initialRefs = $page->instance()->searchProjectReferences('');
    expect(count($initialRefs))->toBeGreaterThan(0)
        ->and($initialRefs[0])->toHaveKeys(['id', 'citation_key', 'title', 'authors']);

    // Filter with specific query
    $firstKey = $initialRefs[0]['citation_key'];
    $filteredRefs = $page->instance()->searchProjectReferences($firstKey);
    expect(count($filteredRefs))->toBeGreaterThan(0)
        ->and(array_column($filteredRefs, 'citation_key'))->toContain($firstKey);
});

it('tracks executed prompts into prompt history and links to studio from transcript export', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // 1. Direct model execution records into promptHistory
    $page->call('executeCurrentModelToStudio', 'Analisis kebaruan metodologi eksperimental');
    expect($page->get('promptHistory'))->toContain('Analisis kebaruan metodologi eksperimental');

    // 2. Export transcript to document creates NaskahDocument with proper link
    $doc = $page->instance()->exportTranscriptToDocument('Dokumen Hasil Sintesis');
    expect($doc)->not->toBeNull()
        ->and($doc->title)->toBe('Dokumen Hasil Sintesis');
});

it('supports search-filtered transcript export and metadata annotation', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', [
            ['role' => 'user', 'content' => 'Bagaimana metodologi penelitian kuantitatif?'],
            ['role' => 'assistant', 'content' => 'Metodologi kuantitatif menggunakan kuesioner skala Likert.'],
            ['role' => 'user', 'content' => 'Apa simpulan dari data temuan?'],
            ['role' => 'assistant', 'content' => 'Simpulan menunjukkan korelasi positif yang signifikan.'],
        ])
        ->set('transcriptSearch', 'metodologi');

    $doc = $page->instance()->exportTranscriptToDocument('Naskah Fokus Metodologi');
    expect($doc)->not->toBeNull()
        ->and($doc->title)->toBe('Naskah Fokus Metodologi');

    $blocks = $doc->blocks;
    expect($blocks[1]['text'])->toContain('Filter Pencarian: "metodologi"')
        ->and($blocks[2]['text'])->toContain('metodologi penelitian kuantitatif');
});

it('supports reference search by author or year and cleans citation key insertion', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'bengio2013representation',
        'title' => 'Representation Learning: A Review and New Perspectives',
        'authors' => [['name' => 'Yoshua Bengio'], ['name' => 'Aaron Courville']],
        'year' => 2013,
        'journal_name' => 'IEEE TPAMI',
        'is_verified' => true,
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);

    // Search by author name "Bengio"
    $results = $page->instance()->searchProjectReferences('Bengio');
    expect($results)->toHaveCount(1)
        ->and($results[0]['citation_key'])->toBe('bengio2013representation')
        ->and($results[0]['authors'])->toContain('Yoshua Bengio');

    // Search by year "2013"
    $yearResults = $page->instance()->searchProjectReferences('2013');
    expect($yearResults)->toHaveCount(1);

    // Test insertCitationKey with leading @
    $page->call('insertCitationKey', '@bengio2013representation');
    expect($page->get('message'))->toBe('[@bengio2013representation]');
});

it('supports message deletion and restoration, and prioritizes context document on snapshot save', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    // Create 2 documents for the project
    $doc1 = NaskahDocument::create([
        'title' => 'Dokumen Pertama',
        'project_id' => $project->id,
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [['type' => 'paragraph', 'text' => 'Teks dokumen 1']],
        'word_count' => 3,
        'last_saved_at' => now()->subDay(),
    ]);

    $doc2 = NaskahDocument::create([
        'title' => 'Dokumen Kedua Terpilih',
        'project_id' => $project->id,
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [['type' => 'paragraph', 'text' => 'Teks dokumen 2']],
        'word_count' => 3,
        'last_saved_at' => now()->subHours(2),
    ]);

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', [
            ['role' => 'user', 'content' => 'Pertanyaan nomor satu'],
            ['role' => 'assistant', 'content' => 'Jawaban nomor satu'],
            ['role' => 'user', 'content' => 'Pertanyaan nomor dua'],
            ['role' => 'assistant', 'content' => 'Jawaban nomor dua'],
        ]);

    // Test delete and restore message
    $page->call('deleteMessage', 1);
    expect($page->get('log'))->toHaveCount(3);

    $page->call('restoreLastDeletedMessage');
    expect($page->get('log'))->toHaveCount(4)
        ->and($page->get('log')[1]['content'])->toBe('Jawaban nomor satu');

    // Test saveSessionAsSnapshot prioritizing activeDocumentId
    $page->set('activeDocumentId', $doc1->id);
    $page->call('saveSessionAsSnapshot');

    $doc1->refresh();
    $doc2->refresh();

    // Snapshots should be attached to doc1 because activeDocumentId was set to doc1
    $meta1 = $doc1->style_meta;
    $meta2 = $doc2->style_meta;

    expect($meta1['snapshots'] ?? [])->toHaveCount(1)
        ->and($meta2['snapshots'] ?? [])->toHaveCount(0);

    // Test prompt templates expansion
    $templates = $page->instance()->getAcademicPromptTemplates();
    expect($templates)->toHaveKey('thesaurus_academic_enhancement')
        ->and($templates)->toHaveKey('discussion_synthesis');
});

it('supports simultaneous search-filtered and starred transcript export without index corruption', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $page = Livewire::test(XishieAssistantPage::class, ['project' => $project->id])
        ->set('log', [
            ['role' => 'user', 'content' => 'Pertanyaan awal tentang metodologi riset empiris'],
            ['role' => 'assistant', 'content' => 'Gunakan metode kuantitatif dengan kuesioner skala Likert'],
            ['role' => 'user', 'content' => 'Bagaimana teknik sampling yang relevan?'],
            ['role' => 'assistant', 'content' => 'Gunakan stratified random sampling untuk representasi populasi'],
            ['role' => 'user', 'content' => 'Bagaimana pengujian validitas instrumen?'],
            ['role' => 'assistant', 'content' => 'Gunakan korelasi Pearson bivariate dan cronbach alpha'],
        ]);

    // Star message at index 3 ('stratified random sampling') and message at index 5 ('validitas instrumen')
    $page->call('toggleStarMessage', 3);
    $page->call('toggleStarMessage', 5);

    // Filter by transcriptSearch 'sampling' AND onlyStarred=true
    $page->set('transcriptSearch', 'sampling');
    $page->set('onlyStarred', true);

    // Export transcript to document
    $doc = $page->instance()->exportTranscriptToDocument('Dokumen Fokus Sampling Berbintang');
    expect($doc)->not->toBeNull();

    $blocks = $doc->blocks;
    // Should contain message 3 ('stratified random sampling')
    $hasSampling = false;
    foreach ($blocks as $block) {
        if (str_contains($block['text'] ?? '', 'stratified random sampling')) {
            $hasSampling = true;
            break;
        }
    }
    expect($hasSampling)->toBeTrue();

    // Export transcript as JSON
    $response = $page->instance()->exportTranscript('json');
    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $jsonOutput = ob_get_clean();

    $data = json_decode($jsonOutput, true);
    expect($data['total_messages'])->toBe(1)
        ->and($data['messages'][0]['content'])->toContain('stratified random sampling');
});

it('supports multi-style citation audit with author-year matching and orphan reference exclusion', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'bengio2013representation',
        'title' => 'Representation Learning',
        'authors' => [['name' => 'Yoshua Bengio']],
        'year' => 2013,
        'journal_name' => 'IEEE TPAMI',
        'is_verified' => true,
    ]);

    ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'lecun2015deep',
        'title' => 'Deep Learning',
        'authors' => [['name' => 'Yann LeCun']],
        'year' => 2015,
        'journal_name' => 'Nature',
        'is_verified' => true,
    ]);

    $doc = NaskahDocument::create([
        'title' => 'Dokumen Naskah Machine Learning',
        'project_id' => $project->id,
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Berdasarkan telaah mendalam (Bengio, 2013), representasi fitur memegang peranan esensial.'],
        ],
        'word_count' => 12,
        'last_saved_at' => now(),
    ]);

    $service = app(XishieAssistantService::class);
    $result = $service->handle($project, '/sitasi audit', null, 'academic_senior', 'manuscript', $doc);

    expect($result['command'])->toBe('/sitasi')
        ->and($result['reply'])->toContain('Laporan Audit Integritas Sitasi Akademik')
        ->and($result['reply'])->toContain('Bengio, 2013')
        ->and($result['reply'])->toContain('lecun2015deep');
});

it('supports contextual document outline in /daftar_isi and explicit citation style switch in /daftar_pustaka', function (): void {
    $user = User::factory()->create();
    $project = XishieProject::factory()->create(['user_id' => $user->id]);

    $doc = NaskahDocument::create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Naskah Tesis Utama',
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Latar belakang penelitian.'],
            ['type' => 'heading', 'level' => 2, 'text' => 'Rumusan Masalah'],
            ['type' => 'paragraph', 'text' => 'Daftar pertanyaan riset.'],
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab II Metodologi'],
            ['type' => 'paragraph', 'text' => 'Rancangan eksperimen kuantitatif.'],
        ],
        'word_count' => 18,
        'last_saved_at' => now(),
    ]);

    $ref = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'vaswani2017attention',
        'title' => 'Attention Is All You Need',
        'authors' => [['name' => 'Ashish Vaswani'], ['name' => 'Noam Shazeer']],
        'year' => 2017,
        'journal_name' => 'Advances in Neural Information Processing Systems',
        'is_verified' => true,
    ]);

    $service = app(XishieAssistantService::class);

    // 1. /daftar_isi with context document
    $tocResult = $service->handle($project, '/daftar_isi', null, 'academic_senior', 'manuscript', $doc);
    expect($tocResult['command'])->toBe(SlashCommand::DAFTAR_ISI->value)
        ->and($tocResult['reply'])->toContain('Struktur Bab Teridentifikasi pada Dokumen Naskah')
        ->and($tocResult['reply'])->toContain('Bab I Pendahuluan')
        ->and($tocResult['reply'])->toContain('Bab II Metodologi')
        ->and($tocResult['data']['document_outline'])->not->toBeEmpty();

    // 2. /daftar_pustaka with explicit APA style switch
    $bibResult = $service->handle($project, '/daftar_pustaka apa', null, 'academic_senior', 'project', $doc);
    expect($bibResult['command'])->toBe(SlashCommand::DAFTAR_PUSTAKA->value)
        ->and($bibResult['reply'])->toContain('APA 7th Edition')
        ->and($bibResult['data']['style'])->toBe('apa7')
        ->and($bibResult['data']['count'])->toBe(1);

    // 3. /sitasi with topic query provides in-text citation format guidance
    $sitasiResult = $service->handle($project, '/sitasi attention', null, 'academic_senior', 'project', $doc);
    expect($sitasiResult['command'])->toBe(SlashCommand::SITASI->value)
        ->and($sitasiResult['reply'])->toContain('Format Sitasi Dalam Teks');
});
