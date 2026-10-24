<?php

declare(strict_types=1);

use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Publication\NaskahRichModel;
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

it('exports the manuscript as docx, odt, rtf, latex, markdown, text and html', function (): void {
    foreach (['docx', 'odt', 'rtf', 'latex', 'md', 'txt', 'html'] as $format) {
        Livewire::test(NaskahStudioPage::class)
            ->set('docTitle', 'Ekspor Lengkap')
            ->set('blocks', [
                ['type' => 'heading', 'level' => 1, 'text' => 'Judul Ekspor'],
                ['type' => 'paragraph', 'text' => 'Ini adalah naskah uji coba ekspor multifromat.'],
                ['type' => 'quote', 'text' => 'Kutipan penting.'],
                ['type' => 'bullet', 'text' => 'Poin pertama'],
                ['type' => 'table', 'table' => ['rows' => [[['text' => 'H1'], ['text' => 'H2']], [['text' => 'A'], ['text' => 'B']]], 'header' => true]],
            ])
            ->call('export', $format)
            ->assertFileDownloaded();
    }
});

it('computes detailed readability and document statistics', function (): void {
    $model = app(NaskahRichModel::class);
    $blocks = [
        ['type' => 'heading', 'level' => 1, 'text' => 'Analisis Metode Penelitian'],
        ['type' => 'paragraph', 'text' => 'Penelitian ini bertujuan untuk menganalisis efektivitas implementasi sistem informasi pada perguruan tinggi. Hasil menunjukkan peningkatan signifikan.'],
    ];

    $stats = $model->computeStatistics($blocks);

    expect($stats['words'])->toBeGreaterThan(10)
        ->and($stats['characters'])->toBeGreaterThan(50)
        ->and($stats['sentences'])->toBeGreaterThan(0)
        ->and($stats['readingEase'])->toBeGreaterThanOrEqual(0.0)
        ->and($stats['gradeLevel'])->toBeGreaterThanOrEqual(1.0)
        ->and($stats['readingTimeMinutes'])->toBeGreaterThanOrEqual(1);
});

it('supports table blocks and table operations', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('tableRows', 2)
        ->set('tableCols', 2)
        ->call('insertTable');

    $blocks = $page->get('blocks');
    $tableIndex = count($blocks) - 1;

    expect($blocks[$tableIndex]['type'])->toBe('table')
        ->and($blocks[$tableIndex]['table']['rows'])->toHaveCount(2)
        ->and($blocks[$tableIndex]['table']['rows'][0])->toHaveCount(2);

    $page->call('tableInsertRow', $tableIndex, 0);
    expect($page->get('blocks')[$tableIndex]['table']['rows'])->toHaveCount(3);

    $page->call('tableDeleteRow', $tableIndex, 1);
    expect($page->get('blocks')[$tableIndex]['table']['rows'])->toHaveCount(2);

    $page->call('tableInsertColumn', $tableIndex, 0);
    expect($page->get('blocks')[$tableIndex]['table']['rows'][0])->toHaveCount(3);

    $page->call('tableDeleteColumn', $tableIndex, 1);
    expect($page->get('blocks')[$tableIndex]['table']['rows'][0])->toHaveCount(2);

    $page->call('tableToggleHeader', $tableIndex);
    expect($page->get('blocks')[$tableIndex]['table']['header'])->toBeFalse();
});

it('supports comments lifecycle with resolve and reply', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('activeBlock', 0)
        ->set('commentDraft', 'Perlu ditambahkan sitasi.')
        ->call('addComment');

    expect($page->get('comments'))->toHaveCount(1)
        ->and($page->get('comments')[0]['body'])->toBe('Perlu ditambahkan sitasi.')
        ->and($page->get('comments')[0]['resolved'])->toBeFalse();

    $page->call('replyComment', 0, 'Baik, akan segera ditambahkan.');
    expect($page->get('comments')[0]['replies'])->toHaveCount(1);

    $page->call('resolveComment', 0);
    expect($page->get('comments')[0]['resolved'])->toBeTrue();

    $page->call('deleteComment', 0);
    expect($page->get('comments'))->toBeEmpty();
});

it('supports track changes and revisions acceptance and rejection', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Teks awal.'],
        ])
        ->call('recordRevision', 0, 'Teks awal.', 'Teks hasil revisi.')
        ->assertSet('dirty', true);

    expect($page->get('revisions'))->toHaveCount(1)
        ->and($page->get('revisions')[0]['status'])->toBe('pending');

    $page->call('acceptRevision', 0);
    expect($page->get('revisions')[0]['status'])->toBe('accepted')
        ->and($page->get('blocks')[0]['text'])->toBe('Teks hasil revisi.');

    $page->call('recordRevision', 0, 'Teks hasil revisi.', 'Teks yang ditolak.')
        ->call('rejectRevision', 1);

    expect($page->get('revisions')[1]['status'])->toBe('rejected')
        ->and($page->get('blocks')[0]['text'])->toBe('Teks hasil revisi.');
});

it('supports undo and redo stack and format painter', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'align' => 'center', 'indent' => 2, 'lineSpacing' => 1.7, 'text' => 'A'],
            ['type' => 'paragraph', 'align' => 'left', 'indent' => 0, 'lineSpacing' => 1.0, 'text' => 'B'],
        ]);

    $page->call('copyFormat', 0);
    expect($page->get('copiedFormat'))->toMatchArray(['align' => 'center', 'indent' => 2]);

    $page->call('pasteFormat', 1);
    expect($page->get('blocks')[1]['align'])->toBe('center')
        ->and($page->get('blocks')[1]['indent'])->toBe(2);

    $page->call('undo');
    expect($page->get('blocks')[1]['align'])->toBe('left');

    $page->call('redo');
    expect($page->get('blocks')[1]['align'])->toBe('center');
});

it('supports mail merge with csv parsing and document generation', function (): void {
    $csv = "nama,instansi\nBudi,UI\nSiti,ITB";
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('mergeCsv', $csv)
        ->set('mergeTemplate', 'Yth. {{nama}} dari {{instansi}}.')
        ->call('previewMerge');

    expect($page->get('mergePreviews'))->toHaveCount(2)
        ->and($page->get('mergePreviews')[0]['text'])->toBe('Yth. Budi dari UI.')
        ->and($page->get('mergePreviews')[1]['text'])->toBe('Yth. Siti dari ITB.');

    $initialBlockCount = count($page->get('blocks'));
    $page->call('insertMerge');
    expect(count($page->get('blocks')))->toBe($initialBlockCount + 2);
});

it('supports page setup, themes and watermark customization', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->call('applyTheme', 'modern')
        ->assertSet('styleMeta.theme', 'modern')
        ->call('updatePageSetup', 'watermark', 'RAHASIA')
        ->assertSet('pageSetup.watermark', 'RAHASIA')
        ->call('updatePageSetup', 'columns', 2)
        ->assertSet('pageSetup.columns', 2)
        ->call('toggleDarkMode')
        ->assertSet('darkMode', true);
});

it('supports wordcraft in-block ai actions', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Penelitian ini menganalisis efektivitas pembelajaran digital.'],
        ])
        ->call('aiElaborateBlock', 0);

    expect($page->get('blocks')[0]['text'])->not->toBeEmpty();
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

it('executes wordcraft id-driven commands and dispatches actions', function (): void {
    $page = Livewire::test(NaskahStudioPage::class);

    expect($page->instance()->commands())->not->toBeEmpty();

    $page->call('executeCommand', 'insert.paragraph');
    expect(count($page->get('blocks')))->toBeGreaterThan(2);

    $page->call('executeCommand', 'view.dark_mode');
    expect($page->get('darkMode'))->toBeTrue();

    $page->call('executeCommand', 'file.properties');
    expect($page->get('dialog'))->toBe('properties');
});

it('runs typographical auto-correct across blocks', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Tanda panah -> dan panah kiri <- serta (c) dan (tm) dan 1/2...'],
        ])
        ->call('runAutoCorrect');

    $text = $page->get('blocks')[0]['text'];
    expect($text)->toContain('→')
        ->and($text)->toContain('←')
        ->and($text)->toContain('©')
        ->and($text)->toContain('™')
        ->and($text)->toContain('½')
        ->and($text)->toContain('…');
});

it('performs table column sum and average calculations', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            [
                'type' => 'table',
                'table' => [
                    'header' => true,
                    'rows' => [
                        [['text' => 'Item'], ['text' => 'Nilai']],
                        [['text' => 'Uji 1'], ['text' => '10']],
                        [['text' => 'Uji 2'], ['text' => '20']],
                    ],
                ],
            ],
        ]);

    $page->call('tableSumColumn', 0, 1);
    $rows = $page->get('blocks')[0]['table']['rows'];
    expect(count($rows))->toBe(4)
        ->and($rows[3][0]['text'])->toBe('SUM')
        ->and($rows[3][1]['text'])->toBe('30');

    $page->call('tableAverageColumn', 0, 1);
    $rowsAfterAvg = $page->get('blocks')[0]['table']['rows'];
    expect(count($rowsAfterAvg))->toBe(5)
        ->and($rowsAfterAvg[4][0]['text'])->toBe('AVERAGE');
});

it('creates, restores and deletes version snapshots', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [['type' => 'paragraph', 'text' => 'Versi Satu']])
        ->set('snapshotLabel', 'Draft 1')
        ->call('createSnapshot');

    expect($page->get('snapshots'))->toHaveCount(1)
        ->and($page->get('snapshots')[0]['label'])->toBe('Draft 1');

    $page->set('blocks', [['type' => 'paragraph', 'text' => 'Versi Dua']])
        ->call('restoreSnapshot', 0);

    expect($page->get('blocks')[0]['text'])->toBe('Versi Satu');

    $page->call('deleteSnapshot', 0);
    expect($page->get('snapshots'))->toBeEmpty();
});

it('supports callout blocks and drop cap toggles', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->call('insertCallout', 'warning');

    $blocks = $page->get('blocks');
    $last = end($blocks);

    expect($last['type'])->toBe('callout')
        ->and($last['calloutType'])->toBe('warning');

    $page->call('toggleDropCap', 0);
    expect($page->get('blocks')[0]['dropCap'])->toBeTrue();
});

it('executes naskah studio cli command for inspect, stats, export and autocorrect', function (): void {
    $document = NaskahDocument::factory()->create([
        'title' => 'CLI Naskah Test',
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Judul CLI'],
            ['type' => 'paragraph', 'text' => 'Isi naskah CLI dengan tanda panah -> dan (c).'],
        ],
        'word_count' => 10,
    ]);

    $this->artisan('naskah:manage list')
        ->assertSuccessful();

    $this->artisan('naskah:manage inspect '.$document->id)
        ->assertSuccessful();

    $this->artisan('naskah:manage stats '.$document->id)
        ->assertSuccessful();

    $this->artisan('naskah:manage autocorrect '.$document->id)
        ->assertSuccessful();

    $tmp = tempnam(sys_get_temp_dir(), 'naskah-export-').'.docx';
    $this->artisan('naskah:manage export '.$document->id.' --format=docx --output='.$tmp)
        ->assertSuccessful();

    expect(file_exists($tmp))->toBeTrue();
    @unlink($tmp);
});

it('serves api endpoints for programmatic document inspection and export', function (): void {
    $document = NaskahDocument::factory()->create([
        'title' => 'API Naskah Test',
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Judul API'],
            ['type' => 'paragraph', 'text' => 'Isi naskah API -> (tm)'],
        ],
        'word_count' => 7,
    ]);

    $this->getJson(route('api.v1.naskah.index'))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->getJson(route('api.v1.naskah.show', $document->id))
        ->assertOk()
        ->assertJsonPath('data.title', 'API Naskah Test')
        ->assertJsonStructure(['data' => ['id', 'title', 'blocks', 'outline', 'statistics']]);

    $this->postJson(route('api.v1.naskah.autocorrect', $document->id))
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $this->get(route('api.v1.naskah.export', ['id' => $document->id, 'format' => 'docx']))
        ->assertOk();

    $this->get(route('api.v1.naskah.export', ['id' => $document->id, 'format' => 'odt']))
        ->assertOk();
});

it('supports assistant response insertion and replacement in filament naskah studio page', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan Awal'],
            ['type' => 'paragraph', 'text' => 'Teks lama yang akan diganti.'],
        ])
        ->set('activeBlock', 1)
        ->set('assistantLog', [
            [
                'role' => 'assistant',
                'content' => 'Teks baru hasil telaah asisten Xishie.',
            ],
        ]);

    $page->call('replaceActiveBlockWithAssistant', 0);
    expect($page->get('blocks')[1]['text'])->toBe('Teks baru hasil telaah asisten Xishie.');

    $page->call('insertAssistantResponse', 0);
    expect(count($page->get('blocks')))->toBe(3);
});
