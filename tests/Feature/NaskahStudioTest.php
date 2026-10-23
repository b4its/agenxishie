<?php

declare(strict_types=1);

use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\NaskahStudioPage;
use App\Models\NaskahDocument;
use App\Models\User;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->create());
});

it('renders the naskah studio with ribbon, canvas, navigation and floating assistant', function (): void {
    Livewire::test(NaskahStudioPage::class)
        ->assertSuccessful()
        ->assertSee('Home')
        ->assertSee('Naskah Studio')
        ->assertSee('Xishie Assistant')
        ->assertSee('Dari Dokumen Lokal')
        ->assertSee('Dari Proyek');
});

it('parses arbitrary document text into structured manuscript blocks', function (): void {
    $blocks = app(NaskahBlockParser::class)->parse(
        "# Abstrak\nIni paragraf pertama.\nLanjutan baris.\n\n## Bagian\n- Poin\n1. Nomor\n> Kutipan"
    );

    $types = array_column($blocks, 'type');

    expect($types)->toContain('heading')
        ->and($types)->toContain('paragraph')
        ->and($types)->toContain('bullet')
        ->and($types)->toContain('numbered')
        ->and($types)->toContain('quote')
        ->and($blocks[0])->toMatchArray(['type' => 'heading', 'text' => 'Abstrak'])
        ->and($blocks[1]['text'])->toBe('Ini paragraf pertama. Lanjutan baris.');
});

it('loads manuscript from a project stage into studio blocks', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('sourceProjectId', $project->id)
        ->set('sourceProjectStage', ProjectStage::DRAFT->value)
        ->call('loadFromProject');

    $blocks = $page->get('blocks');

    expect($blocks)->not->toBeEmpty()
        ->and($page->get('sourceType'))->toBe(NaskahDocument::SOURCE_PROJECT)
        ->and(collect($blocks)->contains(fn (array $b): bool => $b['type'] === 'heading'))->toBeTrue();
});

it('loads manuscript from a local uploaded text document', function (): void {
    $file = UploadedFile::fake()->createWithContent(
        'naskah.txt',
        "# Judul Naskah\nParagraf isi dokumen lokal.\n\n## Sub\nIsi sub bagian."
    );

    // Mengubah properti upload memicu hook updatedUpload secara otomatis.
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('upload', $file);

    expect($page->get('sourceType'))->toBe(NaskahDocument::SOURCE_LOCAL)
        ->and($page->get('docTitle'))->toBe('naskah')
        ->and(collect($page->get('blocks'))->contains(fn (array $b): bool => $b['type'] === 'heading'))->toBeTrue();
});

it('saves and reloads a studio document with word count', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('docTitle', 'Dokumen Uji')
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Satu dua tiga empat lima kata.'],
        ])
        ->call('save');

    $document = NaskahDocument::query()->first();

    expect($document)->not->toBeNull()
        ->and($document->title)->toBe('Dokumen Uji')
        ->and($document->word_count)->toBeGreaterThan(0)
        ->and($page->get('documentId'))->toBe($document->id)
        ->and($page->get('dirty'))->toBeFalse();
});

it('supports block add, remove, reorder and style application', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'A'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'B'],
        ]);

    $page->call('addBlock', 'heading');
    expect($page->get('blocks'))->toHaveCount(3);

    $page->call('removeBlock', 2);
    expect($page->get('blocks'))->toHaveCount(2);

    $page->call('moveBlock', 1, -1);
    expect($page->get('blocks')[0]['text'])->toBe('B');

    $page->call('applyStyle', 0, 'heading2');
    expect($page->get('blocks')[0])->toMatchArray(['type' => 'heading', 'level' => 2]);
});

it('toggles assistant tools and runs a selected tool through the floating panel', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('sourceProjectId', $project->id)
        ->set('sourceProjectStage', ProjectStage::DRAFT->value)
        ->call('loadFromProject')
        ->call('openAssistant')
        ->assertSet('assistantOpen', true)
        ->call('toggleTool', '/ringkas')
        ->assertSet('activeTools', ['/ringkas'])
        ->call('toggleTool', '/cek-pedoman')
        ->assertCount('activeTools', 2)
        ->call('toggleTool', '/ringkas')
        ->assertSet('activeTools', ['/cek-pedoman']);

    $page->call('runTool', '/ringkas');

    $log = $page->get('assistantLog');

    expect($log)->not->toBeEmpty()
        ->and($log[0]['role'])->toBe('user')
        ->and($log[array_key_last($log)]['role'])->toBe('assistant')
        ->and($log[array_key_last($log)]['content'])->not->toBe('');
});

it('answers a freeform assistant question using document context without a project', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [['type' => 'paragraph', 'level' => 0, 'text' => 'Kalimat konteks dokumen.']])
        ->set('assistantInput', 'Apa isi dokumen ini?')
        ->call('sendAssistant');

    $log = $page->get('assistantLog');

    expect($log)->not->toBeEmpty()
        ->and($log[0]['role'])->toBe('user')
        ->and($log[array_key_last($log)]['role'])->toBe('assistant');
});

it('exports the manuscript as markdown, text and html', function (): void {
    foreach (['md', 'txt', 'html'] as $format) {
        Livewire::test(NaskahStudioPage::class)
            ->set('docTitle', 'Ekspor Uji')
            ->set('blocks', [['type' => 'heading', 'level' => 1, 'text' => 'Judul'], ['type' => 'paragraph', 'text' => 'Isi.']])
            ->call('export', $format)
            ->assertFileDownloaded();
    }
});

it('opens a saved document by id', function (): void {
    $document = NaskahDocument::factory()->create([
        'title' => 'Dokumen Tersimpan',
        'blocks' => [['type' => 'paragraph', 'level' => 0, 'text' => 'Isi tersimpan.']],
        'word_count' => 2,
    ]);

    Livewire::test(NaskahStudioPage::class)
        ->call('openDocument', $document->id)
        ->assertSet('documentId', $document->id)
        ->assertSet('docTitle', 'Dokumen Tersimpan');
});
