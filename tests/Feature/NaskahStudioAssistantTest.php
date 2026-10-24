<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\NaskahStudioApp;
use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Livewire\Livewire;

it('renders xishie assistant buttons in topbar and ribbon', function (): void {
    $response = $this->get('/studio');

    $response->assertOk()
        ->assertSee('Xishie Assistant')
        ->assertSee('Xishie AI')
        ->assertSee('Poles Teks')
        ->assertSee('Parafrase');
});

it('opens, closes and toggles the xishie assistant taskpane', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    expect($page->get('assistantOpen'))->toBeFalse();

    $page->call('openAssistant');
    expect($page->get('assistantOpen'))->toBeTrue();

    $page->call('closeAssistant');
    expect($page->get('assistantOpen'))->toBeFalse();

    $page->call('toggleAssistant');
    expect($page->get('assistantOpen'))->toBeTrue();

    $page->call('toggleAssistant');
    expect($page->get('assistantOpen'))->toBeFalse();
});

it('executes quick slash commands (/poles, /parafrase, /cek-pedoman, /ringkas, /cek-gap, /sitasi)', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Model Prediksi Berbasis Jaringan Syaraf Tiruan'],
            ['type' => 'paragraph', 'text' => 'Penelitian ini meneliti performa algoritma deep learning dalam klasifikasi citra medis.'],
        ])
        ->set('activeBlock', 1);

    // 1. Poles Teks
    $page->call('runTool', '/poles');
    $log = $page->get('assistantLog');
    expect($log)->not->toBeEmpty()
        ->and($page->get('assistantOpen'))->toBeTrue()
        ->and(end($log)['role'])->toBe('assistant')
        ->and(end($log)['content'])->toContain('Secara konseptual');

    // 2. Parafrase
    $page->call('runTool', '/parafrase');
    $log = $page->get('assistantLog');
    expect(end($log)['role'])->toBe('assistant')
        ->and(end($log)['content'])->toContain('dapat dinyatakan sebagai kontribusi');

    // 3. Cek Pedoman
    $page->call('runTool', '/cek-pedoman');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Hasil Audit Kepatuhan Pedoman Naskah');

    // 4. Ringkas Naskah
    $page->call('runTool', '/ringkas');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Ringkasan Eksekutif Naskah');

    // 5. Cek Gap
    $page->call('runTool', '/cek-gap');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Research Gap');

    // 6. Sitasi
    $page->call('runTool', '/sitasi');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Rekomendasi Sitasi');
});

it('answers freeform assistant queries in naskah studio', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('assistantInput', 'Berapa banyak kata dan blok pada naskah ini?')
        ->call('sendAssistant');

    $log = $page->get('assistantLog');
    expect($log)->toHaveCount(2)
        ->and($log[0]['role'])->toBe('user')
        ->and($log[0]['content'])->toBe('Berapa banyak kata dan blok pada naskah ini?')
        ->and($log[1]['role'])->toBe('assistant')
        ->and($log[1]['content'])->not->toBeEmpty();
});

it('inserts assistant response into manuscript as new blocks', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Judul Artikel'],
            ['type' => 'paragraph', 'text' => 'Paragraf pertama.'],
        ])
        ->set('activeBlock', 1);

    $initialCount = count($page->get('blocks'));
    $initialRevision = $page->get('editorRevision');

    $page->set('assistantLog', [
        [
            'role' => 'assistant',
            'content' => "Paragraf rekomendasi hasil telaah akademik.\n\nParagraf pendukung kedua.",
        ],
    ]);

    $page->call('insertAssistantResponse', 0);

    expect(count($page->get('blocks')))->toBe($initialCount + 2)
        ->and($page->get('editorRevision'))->toBeGreaterThan($initialRevision)
        ->and($page->get('dirty'))->toBeTrue()
        ->and($page->get('blocks')[2]['text'])->toBe('Paragraf rekomendasi hasil telaah akademik.')
        ->and($page->get('blocks')[3]['text'])->toBe('Paragraf pendukung kedua.');
});

it('replaces the active block with assistant recommendation', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Kalimat draf awal yang belum baku.'],
        ])
        ->set('activeBlock', 0);

    $initialRevision = $page->get('editorRevision');

    $page->set('assistantLog', [
        [
            'role' => 'assistant',
            'content' => 'Formulasi kalimat akademik yang telah disempurnakan dan baku.',
        ],
    ]);

    $page->call('replaceActiveBlockWithAssistant', 0);

    expect($page->get('blocks')[0]['text'])->toBe('Formulasi kalimat akademik yang telah disempurnakan dan baku.')
        ->and($page->get('editorRevision'))->toBeGreaterThan($initialRevision)
        ->and($page->get('dirty'))->toBeTrue();
});

it('clears assistant conversation log', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('assistantLog', [
            ['role' => 'user', 'content' => 'Halo'],
            ['role' => 'assistant', 'content' => 'Halo, ada yang bisa dibantu?'],
        ]);

    expect($page->get('assistantLog'))->not->toBeEmpty();

    $page->call('clearAssistantLog');

    expect($page->get('assistantLog'))->toBeEmpty();
});

it('records assistant logs in database when connected to a project', function (): void {
    $project = XishieProject::factory()->create([
        'title' => 'Riset Komparasi NLP',
    ]);

    $page = Livewire::test(NaskahStudioApp::class)
        ->set('sourceProjectId', $project->id)
        ->set('assistantInput', 'Bagaimana rekomendasi metodologi untuk proyek ini?')
        ->call('sendAssistant');

    expect(OmnigensiAgentLog::query()
        ->where('project_id', $project->id)
        ->where('agent_name', 'Xishie-Assistant')
        ->exists()
    )->toBeTrue();
});
