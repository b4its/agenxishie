<?php

declare(strict_types=1);

use App\Filament\Pages\XishieAssistantPage;
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
