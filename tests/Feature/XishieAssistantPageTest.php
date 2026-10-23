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
        ->set('message', 'Ringkas proyek saya')
        ->call('send');

    $log = $page->get('log');

    expect($log)->toHaveCount(2)
        ->and($log[1]['content'])->toContain('Konteks proyek');
});

it('renders markdown (table) in assistant replies', function (): void {
    $page = Livewire::test(XishieAssistantPage::class);

    $html = $page->instance()->markdownToHtml("| A | B |\n|---|---|\n| 1 | 2 |");

    expect($html)->toContain('<table>')->toContain('<td>1</td>');
});
