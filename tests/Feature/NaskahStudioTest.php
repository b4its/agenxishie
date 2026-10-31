<?php

declare(strict_types=1);

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Publication\CitationFormatter;
use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\NaskahStudioPage;
use App\Models\NaskahDocument;
use App\Models\ProjectReference;
use App\Models\User;
use App\Models\XishieProject;
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

it('renders equation block and opens equation dialog with presets in filament studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Model Fisika Relativitas'],
            [
                'type' => 'equation',
                'equation' => [
                    'latex' => 'E = mc^2',
                    'caption' => '(1)',
                    'display' => 'block',
                ],
            ],
        ])
        ->set('activeBlock', 1);

    $page->assertSeeHtml('latex-math-render')
        ->assertSeeHtml('E = mc^2');

    // Open equation dialog
    $page->call('openDialog', 'equation');
    $page->assertSet('dialog', 'equation')
        ->assertSeeHtml('Sisipkan Rumus Matematika')
        ->assertSeeHtml('Rumus ABC')
        ->assertSeeHtml('Pythagoras');

    // Choose preset and submit
    $page->call('setEquationPreset', 'pythagoras');
    expect($page->get('equationLatex'))->toBe('a^2 + b^2 = c^2');

    $page->call('insertEquation');
    $blocks = $page->get('blocks');
    expect(count($blocks))->toBeGreaterThanOrEqual(2)
        ->and($page->get('dialog'))->toBe('');
});

it('preserves table of contents block during manuscript page synchronization in studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Buku Panduan Terbuka'],
            ['type' => 'toc', 'text' => 'Daftar Isi Otomatis'],
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Latar belakang penelitian komprehensif.'],
        ]);

    $html = '<h1 class="word-title-text">Buku Panduan Terbuka</h1>'
        .'<div class="word-toc-block">Daftar Isi Otomatis</div>'
        .'<h2 class="word-heading-1">Bab I Pendahuluan</h2>'
        .'<p class="word-paragraph-text">Latar belakang penelitian komprehensif yang telah diperbarui.</p>';

    $page->call('syncManuscriptPages', [$html]);

    $types = array_column($page->get('blocks'), 'type');
    expect($types)->toContain('title', 'toc', 'heading', 'paragraph');
});

it('renders table of contents block properly in filament naskah studio page', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Isi bab pendahuluan.'],
        ])
        ->call('insertTableOfContents');

    $blocks = $page->get('blocks');
    $tocBlock = collect($blocks)->firstWhere('type', 'toc');
    expect($tocBlock)->not->toBeNull()
        ->and($tocBlock['table']['rows'])->not->toBeEmpty();

    $page->assertSeeHtml('ns-toc-block')
        ->assertSeeHtml('Daftar Isi Otomatis')
        ->assertSeeHtml('Bab I Pendahuluan');
});

it('closes dialog and updates editor revision on table manipulations and citation insertion', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            [
                'type' => 'table',
                'table' => [
                    'rows' => [
                        [['text' => 'H1', 'runs' => []], ['text' => 'H2', 'runs' => []]],
                        [['text' => 'D1', 'runs' => []], ['text' => 'D2', 'runs' => []]],
                    ],
                    'header' => true,
                ],
            ],
        ]);

    $revBefore = $page->get('editorRevision');
    $page->call('tableInsertRow', 0, 1);
    expect($page->get('editorRevision'))->toBeGreaterThan($revBefore)
        ->and(count($page->get('blocks')[0]['table']['rows']))->toBe(3);

    $page->call('openDialog', 'sources');
    expect($page->get('dialog'))->toBe('sources');

    $page->call('insertCitation', '(Smith, 2024)');
    expect($page->get('dialog'))->toBe('');
});

it('merges blocks with prev and next like Microsoft Word and LibreOffice Writer Backspace and Delete', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Halo dunia ', 'runs' => []],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'akademik Indonesia.', 'runs' => []],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf ketiga.', 'runs' => []],
        ]);

    // Backspace at pos 0 in block 1 merges block 1 into block 0
    $page->call('mergeBlockWithPrev', 1);
    expect($page->get('blocks'))->toHaveCount(2)
        ->and($page->get('blocks')[0]['text'])->toBe('Halo dunia akademik Indonesia.')
        ->and($page->get('activeBlock'))->toBe(0);

    // Delete at end of block 0 merges block 1 into block 0
    $page->call('mergeBlockWithNext', 0);
    expect($page->get('blocks'))->toHaveCount(1)
        ->and($page->get('blocks')[0]['text'])->toBe('Halo dunia akademik Indonesia.Paragraf ketiga.')
        ->and($page->get('activeBlock'))->toBe(0);
});

it('renders copilot button in filament topbar', function (): void {
    $page = Livewire::test(NaskahStudioPage::class);
    $page->assertSeeHtml('Copilot');
});

it('renders filament naskah studio without leaking raw javascript code and renders markdown in assistant chat', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('assistantOpen', true)
        ->set('assistantLog', [
            [
                'role' => 'assistant',
                'content' => "**Polesan Kalimat:**\n- Butir rekomendasi pertama\n- Butir rekomendasi kedua",
                'command' => '/poles',
                'timestamp' => now()->toIso8601String(),
            ],
        ]);

    $html = $page->html();
    expect($html)->not->toContain('${innerHTML}')
        ->and($html)->not->toContain('${innerHtml}')
        ->and($html)->not->toContain('; } else { newEl.innerHTML =')
        ->and($html)->toContain('<strong>Polesan Kalimat:</strong>')
        ->and($html)->toContain('<li>Butir rekomendasi pertama</li>')
        ->and($html)->toContain('word-mini-dropdown');
});

it('renders filament naskah studio with multi-page sheets, headers and footers', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Judul Dokumen Multi-Halaman', 'align' => 'center'],
            ['type' => 'paragraph', 'text' => 'Paragraf halaman pertama.', 'align' => 'left'],
            ['type' => 'pagebreak', 'text' => '', 'align' => 'left'],
            ['type' => 'paragraph', 'text' => 'Paragraf halaman kedua.', 'align' => 'left'],
        ]);

    $html = $page->html();
    expect($html)->toContain('id="word-manuscript-editor"')
        ->and($html)->toContain('id="word-page-1"')
        ->and($html)->toContain('id="word-page-2"')
        ->and($html)->toContain('id="word-page-content-1"')
        ->and($html)->toContain('id="word-page-content-2"')
        ->and($html)->toContain('Halaman 1 dari 2')
        ->and($html)->toContain('Halaman 2 dari 2')
        ->and($html)->toContain('&mdash; 1 &mdash;')
        ->and($html)->toContain('&mdash; 2 &mdash;');
});

it('supports setRibbonTab for draw and help tabs and normalizes dialog aliases', function (): void {
    $page = Livewire::test(NaskahStudioPage::class);

    $page->call('setRibbonTab', 'draw');
    expect($page->get('activeTab'))->toBe('draw');

    $page->call('setRibbonTab', 'help');
    expect($page->get('activeTab'))->toBe('help');

    // Test dialog alias normalization
    $page->call('openDialog', 'cross_reference');
    expect($page->get('dialog'))->toBe('crossref');

    $page->call('openDialog', 'citations');
    expect($page->get('dialog'))->toBe('sources');

    $page->call('openDialog', 'wordcount');
    expect($page->get('dialog'))->toBe('stats');

    $page->call('openDialog', 'pages_setup');
    expect($page->get('dialog'))->toBe('pagesetup');
});

it('renders crossref, sources, and share dialogs in filament naskah studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Teks naskah utama'],
            ['type' => 'equation', 'equation' => ['latex' => 'E=mc^2', 'caption' => '1']],
        ]);

    // Dialog Crossref
    $page->call('openDialog', 'crossref');
    expect($page->html())->toContain('Rujukan Silang Akademik')
        ->and($page->html())->toContain('Persamaan (1)');

    // Dialog Sources
    $page->call('openDialog', 'sources');
    expect($page->html())->toContain('Pengelola Sumber Pustaka &amp; Sitasi')
        ->and($page->html())->toContain('Anderson, C.');

    // Dialog Share
    $page->call('openDialog', 'share');
    expect($page->html())->toContain('Bagikan Dokumen')
        ->and($page->html())->toContain('DOCX');
});

it('exports document in html and json formats in naskah studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('docTitle', 'Naskah Riset Ekspor')
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Naskah Riset Ekspor'],
            ['type' => 'paragraph', 'text' => 'Paragraf percobaan ekspor.'],
        ]);

    $htmlExport = $page->instance()->export('html');
    expect($htmlExport)->not->toBeNull();
    ob_start();
    $htmlExport->sendContent();
    $htmlContent = ob_get_clean();
    expect($htmlContent)->toContain('<!DOCTYPE html>')
        ->and($htmlContent)->toContain('Naskah Riset Ekspor');

    $jsonExport = $page->instance()->export('json');
    expect($jsonExport)->not->toBeNull();
    ob_start();
    $jsonExport->sendContent();
    $jsonContent = ob_get_clean();
    $data = json_decode($jsonContent, true);
    expect($data)->toBeArray()
        ->and($data['title'])->toBe('Naskah Riset Ekspor')
        ->and($data['blocks'])->toHaveCount(2);
});

it('supports findNext and replaceCurrent operations in naskah studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Algoritma machine learning pertama.'],
            ['type' => 'paragraph', 'text' => 'Algoritma deep learning kedua.'],
        ])
        ->set('findQuery', 'Algoritma')
        ->set('replaceQuery', 'Metode');

    // First findNext should focus block #1 (index 0)
    $page->call('findNext');
    expect($page->get('activeBlock'))->toBe(0)
        ->and($page->get('findResult'))->toContain('Ditemukan 2 kemunculan');

    // Second findNext should focus block #2 (index 1)
    $page->call('findNext');
    expect($page->get('activeBlock'))->toBe(1)
        ->and($page->get('findResult'))->toContain('Posisi #2');

    // Replace current on block #2
    $page->call('replaceCurrent');
    expect($page->get('blocks')[1]['text'])->toBe('Metode deep learning kedua.')
        ->and($page->get('blocks')[0]['text'])->toBe('Algoritma machine learning pertama.');
});

it('exports document bibliography in bibtex format in naskah studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('docTitle', 'Pustaka Riset BibTeX')
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Naskah pengujian ekspor bibtex.'],
        ]);

    $bibExport = $page->instance()->export('bibtex');
    expect($bibExport)->not->toBeNull();
    ob_start();
    $bibExport->sendContent();
    $bibContent = ob_get_clean();

    expect($bibContent)->toContain('@')
        ->and($bibContent)->toContain('title =')
        ->and($bibContent)->toContain('year =');
});

it('supports target word count configuration and progress tracking in naskah studio', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('docTitle', 'Naskah Target Kata')
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Satu dua tiga empat lima enam tujuh delapan sembilan sepuluh.'],
        ]);

    expect($page->get('targetWordCount'))->toBe(1500);

    // Reset target to 0
    $page->call('setTargetWordCount', 0);
    expect($page->get('targetWordCount'))->toBe(0)
        ->and($page->instance()->getTargetWordProgress())->toBeNull();

    // Set target to 20 words
    $page->call('setTargetWordCount', 20);
    expect($page->get('targetWordCount'))->toBe(20)
        ->and($page->instance()->getTargetWordProgress())->toBe(50); // 10 / 20 = 50%

    // Save document and verify target_word_count persisted
    $page->call('save');
    $docId = $page->get('documentId');
    expect($docId)->not->toBeNull();

    $doc = \App\Models\NaskahDocument::find($docId);
    expect($doc->style_meta['target_word_count'] ?? 0)->toBe(20);

    // Open and verify reload
    $reloadPage = Livewire::test(NaskahStudioPage::class, ['document' => $docId]);
    expect($reloadPage->get('targetWordCount'))->toBe(20);
});

it('inserts formatted bibliography with hanging indents based on citation style in naskah studio', function (): void {
    $project = makeProject();
    $project->references()->create([
        'citation_key' => 'creswell2018',
        'title' => 'Research Design Qualitative Quantitative and Mixed Methods',
        'authors' => ['John W. Creswell', 'J. David Creswell'],
        'year' => 2018,
        'journal_name' => 'SAGE Publications',
    ]);

    $page = Livewire::test(NaskahStudioPage::class, ['project' => $project->id])
        ->set('citationStyle', 'IEEE')
        ->call('insertBibliography', $project->id);

    $blocks = $page->get('blocks');
    expect($blocks)->not->toBeEmpty();

    // Must have heading "Daftar Pustaka"
    $hasHeading = false;
    $hasHangingIndent = false;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'heading' && ($b['text'] ?? '') === 'Daftar Pustaka') {
            $hasHeading = true;
        }
        if (! empty($b['hangingIndent'])) {
            $hasHangingIndent = true;
        }
    }

    expect($hasHeading)->toBeTrue()
        ->and($hasHangingIndent)->toBeTrue();
});

it('renders novelty and puebi audit actions in naskah studio help ribbon', function (): void {
    $page = Livewire::test(NaskahStudioPage::class)
        ->call('setRibbonTab', 'help');

    expect($page->get('activeTab'))->toBe('help')
        ->and($page->html())->toContain('Cek Pedoman')
        ->and($page->html())->toContain('Cek Gap')
        ->and($page->html())->toContain('Kebaruan')
        ->and($page->html())->toContain('PUEBI');
});

it('supports snapshot diff comparison and snapshot branching into a new document in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    $project = makeProject();

    $initialBlocks = [
        ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
        ['type' => 'paragraph', 'text' => 'Latar belakang riset awal lima kata saja.'],
    ];

    $page = Livewire::test(NaskahStudioPage::class, ['project' => $project->id])
        ->set('docTitle', 'Naskah Uji Snapshot')
        ->set('blocks', $initialBlocks)
        ->call('createSnapshot', 'Draf Awal');

    $snapshots = $page->get('snapshots');
    expect(count($snapshots))->toBe(1);

    // Now modify the document (add more blocks)
    $expandedBlocks = [
        ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
        ['type' => 'paragraph', 'text' => 'Latar belakang riset awal lima kata saja.'],
        ['type' => 'paragraph', 'text' => 'Paragraf tambahan kedua untuk memperjelas konteks riset secara mendalam.'],
    ];
    $page->set('blocks', $expandedBlocks);

    // Run snapshot comparison
    $diff = $page->instance()->compareSnapshot(0);
    expect($diff)->not->toBeNull()
        ->and($diff['snapshot_label'])->toBe('Draf Awal')
        ->and($diff['delta_blocks'])->toBe(1)
        ->and($diff['delta_words'])->toBeGreaterThan(0)
        ->and($page->get('dialog'))->toBe('snapshot_diff');

    // Branch snapshot into new document
    $branchedDoc = $page->instance()->branchSnapshotToDocument(0);
    expect($branchedDoc)->not->toBeNull()
        ->and($branchedDoc->title)->toContain('(Cabang: Draf Awal)')
        ->and($branchedDoc->project_id)->toBe($project->id)
        ->and(count($branchedDoc->blocks))->toBe(2);
});

it('supports proofing language switching and custom academic dictionary in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    expect($page->get('proofingLanguage'))->toBe('Indonesia (PUEBI)');

    // Switch to English
    $page->call('setProofingLanguage', 'English (Academic en-US)');
    expect($page->get('proofingLanguage'))->toBe('English (Academic en-US)');

    // Add custom word to dictionary
    $page->call('addCustomWordToDictionary', 'ScopusQ1');
    expect($page->get('styleMeta')['custom_dictionary'] ?? [])->toContain('scopusq1');

    // Spellcheck does not flag whitelisted custom word
    $page->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Penelitian ini menargetkan jurnal scopusq1 bereputasi.'],
    ])->call('runSpellCheck');

    expect($page->get('spellResult'))->not->toContain('scopusq1');
});

it('supports target word count presets for academic genres in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    $page->call('setTargetWordCountPreset', 'skripsi');
    expect($page->get('targetWordCount'))->toBe(12000);

    $page->call('setTargetWordCountPreset', 'tesis');
    expect($page->get('targetWordCount'))->toBe(25000);

    $page->call('setTargetWordCountPreset', 'jurnal_scopus');
    expect($page->get('targetWordCount'))->toBe(6000);

    $page->call('setTargetWordCountPreset', 'abstrak');
    expect($page->get('targetWordCount'))->toBe(250);
});

it('supports table styles, merge field tags, endnotes, and heading cross references in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $tableBlock = [
        'type' => 'table',
        'table' => [
            'header' => true,
            'style' => 'apa',
            'rows' => [
                [['text' => 'Variabel', 'runs' => []], ['text' => 'Skor', 'runs' => []]],
                [['text' => 'X', 'runs' => []], ['text' => '85', 'runs' => []]],
            ],
        ],
    ];

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Metodologi Penelitian'],
            $tableBlock,
            ['type' => 'paragraph', 'text' => 'Pembahasan data statistik awal.'],
        ]);

    // Test tableSetStyle
    $page->call('tableSetStyle', 1, 'grid');
    expect($page->get('blocks')[1]['table']['style'])->toBe('grid');

    $page->call('tableSetStyle', 1, 'striped');
    expect($page->get('blocks')[1]['table']['style'])->toBe('striped');

    // Test Mail Merge Headers and Field insertion
    $page->set('mergeCsv', "peneliti,institusi,kota\nDr. Aris,Universitas Indonesia,Depok");
    $headers = $page->instance()->getMergeHeaders();
    expect($headers)->toBe(['peneliti', 'institusi', 'kota']);

    $page->set('activeBlock', 2);
    $page->call('insertMergeField', 'peneliti');
    expect($page->get('blocks')[2]['text'])->toContain('{{peneliti}}');

    // Test insertEndnote
    $page->call('insertEndnote', 'Elaborasi metodologis berdasarkan Creswell (2018).');
    $blocks = $page->get('blocks');
    $endnotes = array_filter($blocks, fn ($b): bool => ($b['type'] ?? '') === 'endnote-ref');
    expect(count($endnotes))->toBe(1);

    // Test cross-reference with heading
    $captions = $page->instance()->getManuscriptCaptions();
    expect($captions)->toHaveKey('headings')
        ->and($captions['headings'])->not->toBeEmpty();

    $page->call('insertCrossReference', 'heading', 'Metodologi Penelitian');
    expect($page->get('blocks')[2]['text'])->toContain('Bagian "Metodologi Penelitian"');
});

it('supports document duplication and active block case transformation in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $originalDoc = NaskahDocument::create([
        'user_id' => $user->id,
        'title' => 'Naskah Asli Penelitian',
        'source_type' => NaskahDocument::SOURCE_LOCAL,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'analisis data mendalam'],
            ['type' => 'paragraph', 'text' => 'ini adalah paragraf penjelasan.'],
        ],
        'comments' => [],
        'revisions' => [],
        'page_setup' => ['orientation' => 'portrait'],
        'style_meta' => ['target_word_count' => 5000],
        'word_count' => 8,
    ]);

    $page = Livewire::test(NaskahStudioPage::class)
        ->call('openDocument', $originalDoc->id);

    // Test transformActiveBlockCase
    $page->set('activeBlock', 0);

    // UPPERCASE
    $page->call('transformActiveBlockCase', 'upper');
    expect($page->get('blocks')[0]['text'])->toBe('ANALISIS DATA MENDALAM');

    // lowercase
    $page->call('transformActiveBlockCase', 'lower');
    expect($page->get('blocks')[0]['text'])->toBe('analisis data mendalam');

    // Title Case
    $page->call('transformActiveBlockCase', 'title');
    expect($page->get('blocks')[0]['text'])->toBe('Analisis Data Mendalam');

    // Sentence case
    $page->call('transformActiveBlockCase', 'sentence');
    expect($page->get('blocks')[0]['text'])->toBe('Analisis data mendalam');

    // Test duplicateDocument
    $clonedDoc = $page->instance()->duplicateDocument();
    expect($clonedDoc)->not->toBeNull()
        ->and($clonedDoc->id)->not->toBe($originalDoc->id)
        ->and($clonedDoc->title)->toContain('Salinan dari')
        ->and($page->get('documentId'))->toBe($clonedDoc->id);

    // Verify cloned document in DB
    $dbDoc = NaskahDocument::find($clonedDoc->id);
    expect($dbDoc)->not->toBeNull()
        ->and($dbDoc->blocks[0]['text'])->toBe('Analisis data mendalam')
        ->and($dbDoc->style_meta['target_word_count'])->toBe(5000);
});

it('supports academic cover page insertion and watermark management in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Initial blocks count
    $initialCount = count($page->get('blocks'));

    // Insert Cover Page
    $page->call('insertCoverPage', 'skripsi', [
        'title' => 'EVALUASI MODEL TRANSFORMER',
        'author' => 'Dr. Aris Wibowo',
        'institution' => 'Universitas Indonesia',
        'year' => '2026',
    ]);

    $blocks = $page->get('blocks');
    expect(count($blocks))->toBe($initialCount + 5)
        ->and($blocks[0]['text'])->toBe('EVALUASI MODEL TRANSFORMER')
        ->and($blocks[1]['text'])->toBe('PROPOSAL PENELITIAN SKRIPSI')
        ->and($blocks[2]['text'])->toContain('Dr. Aris Wibowo')
        ->and($blocks[3]['text'])->toContain('Universitas Indonesia')
        ->and($blocks[4]['type'])->toBe('pagebreak');

    // Test Watermark
    $page->call('setWatermark', 'RAHASIA');
    expect($page->get('pageSetup')['watermark'])->toBe('RAHASIA');

    $page->call('setWatermark', '');
    expect($page->get('pageSetup')['watermark'])->toBe('');
});

it('supports dynamic bibliography update and style reformatting in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Initial bibliography insertion
    $page->call('insertBibliography');
    $blocks = $page->get('blocks');
    $bibHeadingIdx = null;
    foreach ($blocks as $idx => $b) {
        if (($b['type'] ?? '') === 'heading' && str_contains(strtolower($b['text']), 'daftar pustaka')) {
            $bibHeadingIdx = $idx;
            break;
        }
    }
    expect($bibHeadingIdx)->not->toBeNull();

    // Change citation style to IEEE
    $page->call('setCitationStyle', 'IEEE');
    expect($page->get('citationStyle'))->toBe('IEEE');

    // Trigger updateBibliography
    $page->call('updateBibliography');
    $updatedBlocks = $page->get('blocks');

    // Check that bibliography entries exist and are formatted
    expect(count($updatedBlocks))->toBeGreaterThan($bibHeadingIdx);
    $firstEntry = $updatedBlocks[$bibHeadingIdx + 1];
    expect($firstEntry['hangingIndent'])->toBeTrue()
        ->and($firstEntry['spacingBefore'])->toBe(4.0)
        ->and($firstEntry['spacingAfter'])->toBe(6.0);
});

it('supports heading promotion, demotion, and section reordering in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Prepare clear structured blocks with 2 chapters and subsections
    $testBlocks = [
        ['id' => 'b0', 'type' => 'heading', 'level' => 1, 'text' => 'Bab I: Pendahuluan'],
        ['id' => 'b1', 'type' => 'paragraph', 'text' => 'Latar belakang penelitian ini adalah...'],
        ['id' => 'b2', 'type' => 'heading', 'level' => 2, 'text' => '1.1 Rumusan Masalah'],
        ['id' => 'b3', 'type' => 'paragraph', 'text' => 'Rumusan masalah dirinci sebagai berikut...'],
        ['id' => 'b4', 'type' => 'heading', 'level' => 1, 'text' => 'Bab II: Kajian Pustaka'],
        ['id' => 'b5', 'type' => 'paragraph', 'text' => 'Teori yang mendasari mencakup...'],
    ];

    $page->set('blocks', $testBlocks);

    // Test promoteHeading: H2 at index 2 promoted to H1
    $page->call('promoteHeading', 2);
    $blocks = $page->get('blocks');
    expect($blocks[2]['level'])->toBe(1);

    // Promote again at level 1 should stay at level 1 (boundary check)
    $page->call('promoteHeading', 2);
    $blocks = $page->get('blocks');
    expect($blocks[2]['level'])->toBe(1);

    // Test demoteHeading: H1 at index 2 demoted back to H2
    $page->call('demoteHeading', 2);
    $blocks = $page->get('blocks');
    expect($blocks[2]['level'])->toBe(2);

    // Test moveSection DOWN:
    // Move Bab I (index 0, spanning blocks 0..3) DOWN past Bab II (index 4, spanning blocks 4..5)
    $page->call('moveSection', 0, 'down');
    $reorderedBlocks = $page->get('blocks');

    // After moving down: Bab II (and its paragraph) should be at index 0 and 1
    // Bab I (and its subsections) should be at index 2..5
    expect($reorderedBlocks[0]['text'])->toBe('Bab II: Kajian Pustaka')
        ->and($reorderedBlocks[1]['text'])->toBe('Teori yang mendasari mencakup...')
        ->and($reorderedBlocks[2]['text'])->toBe('Bab I: Pendahuluan')
        ->and($reorderedBlocks[3]['text'])->toBe('Latar belakang penelitian ini adalah...')
        ->and($reorderedBlocks[4]['text'])->toBe('1.1 Rumusan Masalah')
        ->and($reorderedBlocks[5]['text'])->toBe('Rumusan masalah dirinci sebagai berikut...');

    // Test moveSection UP:
    // Move Bab I (now at index 2) UP back to the top
    $page->call('moveSection', 2, 'up');
    $restoredBlocks = $page->get('blocks');
    expect($restoredBlocks[0]['text'])->toBe('Bab I: Pendahuluan')
        ->and($restoredBlocks[2]['text'])->toBe('1.1 Rumusan Masalah')
        ->and($restoredBlocks[4]['text'])->toBe('Bab II: Kajian Pustaka');
});

it('supports zen focus mode toggling and section word counts in outline', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Initial zenMode is false
    expect($page->get('zenMode'))->toBeFalse();

    // Toggle zenMode to true
    $page->call('toggleZenMode');
    expect($page->get('zenMode'))->toBeTrue();

    // Toggle zenMode back to false
    $page->call('toggleZenMode');
    expect($page->get('zenMode'))->toBeFalse();

    // Test outline section word count calculation
    $testBlocks = [
        ['id' => 'b0', 'type' => 'heading', 'level' => 1, 'text' => 'Bab I: Pendahuluan'],
        ['id' => 'b1', 'type' => 'paragraph', 'text' => 'Ini adalah paragraf pengantar dengan sepuluh kata yang sangat jelas.'],
        ['id' => 'b2', 'type' => 'heading', 'level' => 2, 'text' => '1.1 Latar Belakang'],
        ['id' => 'b3', 'type' => 'paragraph', 'text' => 'Latar belakang riset ini sangat mendalam dan terstruktur.'],
        ['id' => 'b4', 'type' => 'heading', 'level' => 1, 'text' => 'Bab II: Metode'],
        ['id' => 'b5', 'type' => 'paragraph', 'text' => 'Metode riset menggunakan desain kuantitatif eksperimental empiris.'],
    ];

    $page->set('blocks', $testBlocks);
    $outline = $page->instance()->outline();

    expect(count($outline))->toBe(3)
        ->and($outline[0]['text'])->toBe('Bab I: Pendahuluan')
        ->and($outline[0]['word_count'])->toBeGreaterThan(15)
        ->and($outline[1]['text'])->toBe('1.1 Latar Belakang')
        ->and($outline[1]['word_count'])->toBeGreaterThan(5)
        ->and($outline[2]['text'])->toBe('Bab II: Metode')
        ->and($outline[2]['word_count'])->toBeGreaterThan(8);
});

it('supports citation auditing, hallucination detection, and density scoring in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $project = XishieProject::factory()->create(['title' => 'Riset Komputasi Biomedis']);

    // Create 2 valid references in the project
    $project->references()->create([
        'citation_key' => 'vaswani2017',
        'title' => 'Attention Is All You Need',
        'authors' => ['Ashish Vaswani'],
        'year' => 2017,
    ]);

    $project->references()->create([
        'citation_key' => 'goodfellow2014',
        'title' => 'Generative Adversarial Nets',
        'authors' => ['Ian Goodfellow'],
        'year' => 2014,
    ]);

    $page = Livewire::test(NaskahStudioPage::class, ['project' => $project->id]);

    // Set manuscript blocks containing:
    // - vaswani2017 (valid)
    // - fakecitation2099 (hallucinated / unmatched)
    // - goodfellow2014 is uncited
    $manuscriptBlocks = [
        ['id' => 'b0', 'type' => 'heading', 'level' => 1, 'text' => 'Bab I: Pendahuluan'],
        ['id' => 'b1', 'type' => 'paragraph', 'text' => 'Transformer arsitektur diperkenalkan oleh [@vaswani2017] secara revolusioner.'],
        ['id' => 'b2', 'type' => 'paragraph', 'text' => 'Namun model kuantum spekulatif oleh [@fakecitation2099] masih belum terbukti di lapangan.'],
    ];

    $page->set('blocks', $manuscriptBlocks);

    // Run citation audit
    $page->call('auditCitations', $project->id);

    $audit = $page->get('lastCitationAudit');

    expect($audit)->not->toBeNull()
        ->and($audit['verified'])->toContain('vaswani2017')
        ->and($audit['unmatched'])->toContain('fakecitation2099')
        ->and($audit['uncited'])->toContain('goodfellow2014')
        ->and($audit['total_citations'])->toBe(2)
        ->and($audit['density_per_1k'])->toBeGreaterThan(0.0);
});

it('supports document line numbers toggle and gutter indicators for peer review in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Initial state: lineNumbers false
    $pageSetup = $page->get('pageSetup');
    expect($pageSetup['lineNumbers'])->toBeFalse();

    // Toggle on
    $page->call('toggleLineNumbers');
    $pageSetup = $page->get('pageSetup');
    expect($pageSetup['lineNumbers'])->toBeTrue();

    // Toggle off
    $page->call('toggleLineNumbers');
    $pageSetup = $page->get('pageSetup');
    expect($pageSetup['lineNumbers'])->toBeFalse();
});

it('supports academic scratchpad notes management and insertion to manuscript in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Initial state: empty scratchpad
    expect($page->get('scratchpadNotes'))->toBe('');

    // Switch navigator to notes
    $page->call('setNavSidebarTab', 'notes');
    expect($page->get('navSidebarTab'))->toBe('notes');

    // Save scratchpad notes
    $notes = "Gagasan pokok: integrasi transformer dan graph neural network.\n\nPerlu diuji dengan benchmark QM9.";
    $page->call('saveScratchpadNotes', $notes);
    expect($page->get('scratchpadNotes'))->toBe($notes);

    $initialBlockCount = count($page->get('blocks'));

    // Insert notes to manuscript
    $page->call('insertNotesToManuscript');
    $blocks = $page->get('blocks');

    expect(count($blocks))->toBeGreaterThan($initialBlockCount);
    $lastBlock = end($blocks);
    expect($lastBlock['type'])->toBe('paragraph')
        ->and($lastBlock['text'])->toContain('QM9');

    // Clear scratchpad notes
    $page->call('clearScratchpadNotes');
    expect($page->get('scratchpadNotes'))->toBe('');
});

it('supports review ribbon audit citations and view ribbon line numbers and zen mode actions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Call audit citations from review ribbon context
    $page->call('setRibbonTab', 'review');
    expect($page->get('activeTab'))->toBe('review');
    $page->call('auditCitations');
    expect($page->get('lastCitationAudit'))->not->toBeNull();

    // Call view ribbon toggles
    $page->call('setRibbonTab', 'view');
    expect($page->get('activeTab'))->toBe('view');

    $page->call('toggleLineNumbers');
    $pageSetup = $page->get('pageSetup');
    expect($pageSetup['lineNumbers'])->toBeTrue();

    $page->call('toggleZenMode');
    expect($page->get('zenMode'))->toBeTrue();
});

it('supports deleting message from studio copilot drawer and parsing markdown notes into manuscript', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Test deleteAssistantMessage
    $page->set('assistantLog', [
        ['role' => 'user', 'content' => 'Pertanyaan 1'],
        ['role' => 'assistant', 'content' => 'Jawaban 1'],
        ['role' => 'user', 'content' => 'Pertanyaan 2'],
    ]);

    $page->call('deleteAssistantMessage', 1);
    $log = $page->get('assistantLog');
    expect(count($log))->toBe(2)
        ->and($log[0]['content'])->toBe('Pertanyaan 1')
        ->and($log[1]['content'])->toBe('Pertanyaan 2');

    // Test rich markdown parsing in scratchpad notes
    $initialCount = count($page->get('blocks'));
    $markdownNotes = <<<'EOT'
## Catatan Telaah Model
> Pertimbangkan variansi kovariat pada analisis regresi.
- Poin evaluasi pertama
- Poin evaluasi kedua
EOT;

    $page->set('scratchpadNotes', $markdownNotes);
    $page->call('insertNotesToManuscript');

    $blocks = $page->get('blocks');
    expect(count($blocks))->toBeGreaterThan($initialCount);

    $types = array_column($blocks, 'type');
    expect($types)->toContain('heading')
        ->and($types)->toContain('quote')
        ->and($types)->toContain('bullet');
});

it('supports bidirectional find navigation, reversible replace undo, and interactive thesaurus synonym application', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // Setup blocks with repeating keyword
    $page->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Penelitian ini berfokus pada evaluasi model.'],
        ['type' => 'paragraph', 'text' => 'Banyak penelitian sejenis menunjukkan signifikansi hasil.'],
        ['type' => 'paragraph', 'text' => 'Hasil akhir penelitian memberikan kontribusi baru.'],
    ]);
    $page->set('activeBlock', 0);

    // 1. Test bidirectional find: findNext & findPrevious
    $page->set('findQuery', 'penelitian');
    $page->call('findNext');
    expect($page->get('findResult'))->toContain('Ditemukan 3 kemunculan')
        ->and($page->get('findCurrentHitIndex'))->toBe(0);

    $page->call('findNext');
    expect($page->get('findCurrentHitIndex'))->toBe(1)
        ->and($page->get('activeBlock'))->toBe(1);

    $page->call('findPrevious');
    expect($page->get('findCurrentHitIndex'))->toBe(0)
        ->and($page->get('activeBlock'))->toBe(0);

    // 2. Test replaceAll with undo restoration
    $page->set('replaceQuery', 'kajian');
    $page->call('replaceAll');

    $replacedBlocks = $page->get('blocks');
    expect($replacedBlocks[0]['text'])->toContain('kajian ini berfokus')
        ->and($replacedBlocks[1]['text'])->toContain('Banyak kajian sejenis')
        ->and($replacedBlocks[2]['text'])->toContain('Hasil akhir kajian');

    // Undo should revert replaceAll
    $page->call('undo');
    $restoredBlocks = $page->get('blocks');
    expect($restoredBlocks[0]['text'])->toContain('Penelitian ini berfokus');

    // 3. Test expanded academic thesaurus & applyThesaurusSynonym
    $page->set('thesaurusWord', 'kesimpulan');
    $page->call('runThesaurus');

    $synonyms = $page->get('thesaurusSynonyms');
    expect($synonyms)->toContain('simpulan')
        ->and($synonyms)->toContain('konklusi')
        ->and($synonyms)->toContain('inferensi');

    // Apply synonym to active block
    $page->set('thesaurusWord', 'penelitian');
    $page->call('applyThesaurusSynonym', 'investigasi');
    $updatedBlock = $page->get('blocks')[0];
    expect($updatedBlock['text'])->toContain('investigasi ini berfokus');
});

it('supports sketch callouts, safe mail merge with undo, and heading change detection in snapshot comparison', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    // 1. Test sketch callout insertion
    $page->call('insertCallout', 'sketch');
    $blocks = $page->get('blocks');
    $lastBlock = end($blocks);
    expect($lastBlock['type'])->toBe('callout')
        ->and($lastBlock['calloutType'])->toBe('sketch')
        ->and($lastBlock['text'])->toContain('Catatan Sketsa & Diagram');

    // 2. Test mail merge field insertion and safe undo
    $initialCount = count($blocks);
    $page->set('mergeCsv', "nama,kampus\nBudi,UI\nDewi,ITB");
    $page->set('mergeTemplate', "Mahasiswa {{nama}} dari {{kampus}}.");
    $page->call('insertMerge');

    $mergedBlocks = $page->get('blocks');
    expect(count($mergedBlocks))->toBe($initialCount + 2)
        ->and($mergedBlocks[count($mergedBlocks) - 2]['text'])->toBe('Mahasiswa Budi dari UI.')
        ->and($mergedBlocks[count($mergedBlocks) - 1]['text'])->toBe('Mahasiswa Dewi dari ITB.');

    // Undo should restore prior blocks
    $page->call('undo');
    expect(count($page->get('blocks')))->toBe($initialCount);

    // 3. Test snapshot diff with heading change detection
    $page->set('blocks', [
        ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
        ['type' => 'paragraph', 'text' => 'Teks awal pendahuluan.'],
    ]);
    $page->call('saveSnapshot', 'Versi Awal');

    // Add a new heading and remove prior heading
    $page->set('blocks', [
        ['type' => 'heading', 'level' => 1, 'text' => 'Bab II Kajian Pustaka'],
        ['type' => 'paragraph', 'text' => 'Teks kajian pustaka baru.'],
    ]);

    $diff = $page->call('compareSnapshot', 0)->get('snapshotDiffResult');
    expect($diff)->not->toBeNull()
        ->and($diff['new_headings'])->toContain('Bab II Kajian Pustaka')
        ->and($diff['removed_headings'])->toContain('Bab I Pendahuluan');
});

it('supports undo reversibility for citations, bibliography, footnotes, and symbols in naskah studio', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $page = Livewire::test(NaskahStudioPage::class);

    $page->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Klaim utama naskah ini.'],
    ]);
    $page->set('activeBlock', 0);

    // 1. Citation undo
    $page->call('insertCitation', '(Gauntlett, 2018)');
    expect($page->get('blocks')[0]['text'])->toContain('(Gauntlett, 2018)');

    $page->call('undo');
    expect($page->get('blocks')[0]['text'])->toBe('Klaim utama naskah ini.');

    // 2. Footnote undo
    $page->set('footnoteText', 'Keterangan tambahan rujukan metodologis.');
    $page->call('insertFootnote');
    expect(count($page->get('blocks')))->toBe(2);

    $page->call('undo');
    expect(count($page->get('blocks')))->toBe(1);

    // 3. Symbol undo
    $page->call('insertSymbol', '±');
    expect($page->get('blocks')[0]['text'])->toContain('±');

    $page->call('undo');
    expect($page->get('blocks')[0]['text'])->toBe('Klaim utama naskah ini.');

    // 4. Bibliography insertion undo
    $page->call('insertBibliography');
    expect(count($page->get('blocks')))->toBeGreaterThan(3);

    $page->call('undo');
    expect(count($page->get('blocks')))->toBe(1);
});

it('supports undo reversibility for drop cap, heading hierarchy, block operations, and extended command palette', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(NaskahStudioPage::class);

    $page->set('blocks', [
        ['type' => 'heading', 'level' => 2, 'text' => 'Bab I Pendahuluan'],
        ['type' => 'paragraph', 'text' => 'Paragraf pertama naskah riset.'],
    ]);
    $page->set('activeBlock', 1);

    // 1. Drop Cap toggle & undo
    $page->call('toggleDropCap', 1);
    expect($page->get('blocks')[1]['dropCap'] ?? false)->toBeTrue();

    $page->call('undo');
    expect($page->get('blocks')[1]['dropCap'] ?? false)->toBeFalse();

    // 2. Heading promote & demote undo
    $page->call('promoteHeading', 0);
    expect($page->get('blocks')[0]['level'])->toBe(1);

    $page->call('undo');
    expect($page->get('blocks')[0]['level'])->toBe(2);

    $page->call('demoteHeading', 0);
    expect($page->get('blocks')[0]['level'])->toBe(3);

    $page->call('undo');
    expect($page->get('blocks')[0]['level'])->toBe(2);

    // 3. Block operations undo (addBlock, removeBlock, duplicateBlock)
    $page->call('addBlock', 'paragraph');
    expect(count($page->get('blocks')))->toBe(3);

    $page->call('undo');
    expect(count($page->get('blocks')))->toBe(2);

    $page->call('duplicateBlock', 1);
    expect(count($page->get('blocks')))->toBe(3);

    $page->call('undo');
    expect(count($page->get('blocks')))->toBe(2);

    $page->call('removeBlock', 1);
    expect(count($page->get('blocks')))->toBe(1);

    $page->call('undo');
    expect(count($page->get('blocks')))->toBe(2);

    // 4. Extended command palette execution
    $page->set('activeBlock', 1);
    $page->call('executeCommand', 'format.drop_cap');
    expect($page->get('blocks')[1]['dropCap'] ?? false)->toBeTrue();

    $page->call('executeCommand', 'insert.sketch');
    $blocks = $page->get('blocks');
    expect(end($blocks)['type'])->toBe('callout')
        ->and(end($blocks)['calloutType'])->toBe('sketch');

    $page->call('executeCommand', 'file.snapshots');
    expect($page->get('dialog'))->toBe('snapshots');

    $page->call('executeCommand', 'tools.merge');
    expect($page->get('dialog'))->toBe('merge');

    $page->call('executeCommand', 'view.zen_mode');
    expect($page->get('zenMode'))->toBeTrue();

    $page->call('executeCommand', 'view.line_numbers');
    expect($page->get('pageSetup')['lineNumbers'])->toBeTrue();

    $page->call('executeCommand', 'references.audit');
    expect($page->get('lastCitationAudit'))->not->toBeNull();
});

it('supports custom dictionary management, scratchpad persistence, and vancouver citation style', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(NaskahStudioPage::class);

    // 1. Custom dictionary addition and removal
    $page->call('addCustomWordToDictionary', 'TransformerX');
    expect($page->get('customDictionary'))->toContain('transformerx');

    $page->call('removeCustomWordFromDictionary', 'TransformerX');
    expect($page->get('customDictionary'))->not->toContain('transformerx');

    // Add back for save verification
    $page->call('addCustomWordToDictionary', 'DeepLearningV3');
    $page->call('saveScratchpadNotes', 'Catatan penting validasi eksperimen.');
    $page->call('setCitationStyle', 'VANCOUVER');
    $page->call('save');

    $docId = $page->get('documentId');
    expect($docId)->not->toBeNull();

    $doc = NaskahDocument::find($docId);
    expect($doc->style_meta['scratchpad_notes'])->toBe('Catatan penting validasi eksperimen.')
        ->and($doc->style_meta['custom_dictionary'])->toContain('deeplearningv3')
        ->and($doc->style_meta['citation_style'])->toBe('VANCOUVER');

    // 2. Load document into new component instance and verify properties loaded
    $newPage = Livewire::test(NaskahStudioPage::class)
        ->call('loadDocument', $doc);

    expect($newPage->get('scratchpadNotes'))->toBe('Catatan penting validasi eksperimen.')
        ->and($newPage->get('customDictionary'))->toContain('deeplearningv3')
        ->and($newPage->get('citationStyle'))->toBe('VANCOUVER');

    // 3. Vancouver citation insertion
    $newPage->call('insertCitation');
    $activeBlock = $newPage->get('activeBlock');
    expect($newPage->get('blocks')[$activeBlock]['text'])->toContain('(1)');
});

it('supports table count calculation and column formula undo', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(NaskahStudioPage::class);

    $page->set('blocks', [
        [
            'type' => 'table',
            'table' => [
                'header' => true,
                'rows' => [
                    [['text' => 'Model'], ['text' => 'Akurasi']],
                    [['text' => 'ResNet-50'], ['text' => '85.5']],
                    [['text' => 'Transformer'], ['text' => '92.3']],
                    [['text' => 'Agnes-3.0'], ['text' => '96.8']],
                ],
            ],
        ],
    ]);

    // Test tableCalculateColumn with count
    $page->call('tableCalculateColumn', 0, 1, 'count');
    $rows = $page->get('blocks')[0]['table']['rows'];
    $lastRow = end($rows);

    expect($lastRow[0]['text'])->toBe('COUNT')
        ->and($lastRow[1]['text'])->toBe('3');

    // Test undo
    $page->call('undo');
    expect(count($page->get('blocks')[0]['table']['rows']))->toBe(4);
});

it('supports table median calculation and custom table caption with cross reference', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(NaskahStudioPage::class);

    $page->set('blocks', [
        [
            'type' => 'table',
            'table' => [
                'header' => true,
                'rows' => [
                    [['text' => 'Sampel'], ['text' => 'Skor']],
                    [['text' => 'A'], ['text' => '10']],
                    [['text' => 'B'], ['text' => '20']],
                    [['text' => 'C'], ['text' => '30']],
                ],
            ],
        ],
    ]);

    // Test tableCalculateColumn with median (10, 20, 30 -> median = 20)
    $page->call('tableCalculateColumn', 0, 1, 'median');
    $rows = $page->get('blocks')[0]['table']['rows'];
    $lastRow = end($rows);

    expect($lastRow[0]['text'])->toBe('MEDIAN')
        ->and($lastRow[1]['text'])->toBe('20');

    // Test tableSetCaption
    $page->call('tableSetCaption', 0, 'Tabel 1: Hasil Skor Sampel');
    expect($page->get('blocks')[0]['table']['caption'])->toBe('Tabel 1: Hasil Skor Sampel');

    $captions = $page->instance()->getManuscriptCaptions();
    expect($captions['tables'])->toHaveCount(1)
        ->and($captions['tables'][0]['caption'])->toBe('Tabel 1: Hasil Skor Sampel');
});

it('supports multi-style citation audit and reference search filtering in studio', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    // Create references with authors and citation keys
    $ref1 = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'vaswani2017attention',
        'title' => 'Attention Is All You Need',
        'authors' => [['name' => 'Ashish Vaswani'], ['name' => 'Noam Shazeer']],
        'year' => 2017,
        'journal_name' => 'NeurIPS',
        'is_verified' => true,
    ]);

    $ref2 = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'creswell2018',
        'title' => 'Research Design Qualitative and Quantitative',
        'authors' => [['name' => 'John W. Creswell']],
        'year' => 2018,
        'journal_name' => 'SAGE Publications',
        'is_verified' => true,
    ]);

    $page = Livewire::test(NaskahStudioPage::class, ['project' => $project->id]);

    // Test search filter in references navigator
    $page->set('referenceSearchQuery', 'Attention');
    $viewRefs = $page->viewData('projectReferences');
    expect($viewRefs)->toHaveCount(1)
        ->and($viewRefs->first()->citation_key)->toBe('vaswani2017attention');

    // Test audit with both author-year format and IEEE numeric format
    $page->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Menurut (Vaswani et al., 2017), self-attention sangat efektif.'],
        ['type' => 'paragraph', 'text' => 'Pendekatan campuran dirujuk pada [2] dalam literatur metodologi.'],
        ['type' => 'paragraph', 'text' => 'Klaim fiktif tidak berdasar oleh (Fiktif, 2099).'],
    ]);

    $audit = $page->instance()->auditCitations($project->id);

    expect($audit['verified'])->toContain('Vaswani et al., 2017')
        ->and($audit['verified'])->toContain('[2]')
        ->and($audit['unmatched'])->toContain('Fiktif, 2099')
        ->and($audit['total_citations'])->toBe(3);
});

it('supports important and caution callouts, footnote with text, and dynamic page numbering', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(NaskahStudioPage::class);

    // Test insertCallout with important
    $page->call('insertCallout', 'important', 'Perhatikan konvergensi matriks gradien.');
    $blocks = $page->get('blocks');
    $calloutBlock = end($blocks);
    expect($calloutBlock['type'])->toBe('callout')
        ->and($calloutBlock['calloutType'])->toBe('important')
        ->and($calloutBlock['text'])->toBe('Perhatikan konvergensi matriks gradien.');

    // Test insertCallout with caution
    $page->call('insertCallout', 'caution');
    $blocks = $page->get('blocks');
    $cautionBlock = end($blocks);
    expect($cautionBlock['calloutType'])->toBe('caution')
        ->and($cautionBlock['text'])->toContain('potensi galat');

    // Test insertFootnote with direct parameter
    $page->call('insertFootnote', 'Lihat lampiran 2 untuk pembuktian lema.');
    $blocks = $page->get('blocks');
    $fnBlock = end($blocks);
    expect($fnBlock['type'])->toBe('footnote-ref')
        ->and($fnBlock['text'])->toBe('Lihat lampiran 2 untuk pembuktian lema.');

    // Test insertPageNumberField with multi-page state
    $page->set('currentPage', 3);
    $page->set('totalPages', 8);
    $page->call('insertPageNumberField');
    $blocks = $page->get('blocks');
    $pnBlock = end($blocks);
    expect($pnBlock['text'])->toBe('Halaman 3 dari 8')
        ->and($pnBlock['align'])->toBe('center');
});

it('supports copilot drawer message deletion and restoration, and equation model execution with actual latex formula', function (): void {
    $this->actingAs(User::factory()->create());
    $page = Livewire::test(NaskahStudioPage::class);

    // Test copilot drawer message deletion and restore
    $page->set('assistantLog', [
        ['role' => 'user', 'content' => 'Bagaimana metodologi penelitian kuantitatif?'],
        ['role' => 'assistant', 'content' => 'Gunakan desain eksperimental acak terkontrol.'],
    ]);

    $page->call('deleteAssistantMessage', 1);
    expect($page->get('assistantLog'))->toHaveCount(1);

    $page->call('restoreLastDeletedAssistantMessage');
    expect($page->get('assistantLog'))->toHaveCount(2)
        ->and($page->get('assistantLog')[1]['content'])->toContain('eksperimental acak');

    // Test executeModelOnEquation uses actual equation latex
    $page->set('blocks', [
        [
            'type' => 'equation',
            'equation' => [
                'latex' => 'E = mc^2',
                'caption' => '(1.1)',
                'display' => 'block',
            ],
        ],
    ]);

    $page->call('executeModelOnEquation', 0);
    $blocks = $page->get('blocks');
    expect(count($blocks))->toBe(2)
        ->and($blocks[1]['type'])->toBe('paragraph')
        ->and($blocks[1]['text'])->toContain('Persamaan (1.1)');
});

it('supports bibtex export with project resolution, latex table caption and footnote exports, and flesch readability grade', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'vaswani2017attention',
        'title' => 'Attention Is All You Need',
        'authors' => [['name' => 'Ashish Vaswani']],
        'year' => 2017,
        'journal_name' => 'NeurIPS',
        'is_verified' => true,
    ]);

    $doc = NaskahDocument::create([
        'title' => 'Dokumen Naskah Riset AI',
        'project_id' => $project->id,
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Ini adalah teks paragraf pengantar riset translasi bahasa alami yang komprehensif.'],
            ['type' => 'footnote-ref', 'text' => 'Lihat catatan teknis arsitektur transformer.'],
            ['type' => 'endnote-ref', 'text' => 'Data observasi tambahan tersedia pada repositori publik.'],
            [
                'type' => 'table',
                'caption' => 'Tabel 1: Ringkasan Metrik Akurasi BLEU',
                'table' => [
                    'caption' => 'Tabel 1: Ringkasan Metrik Akurasi BLEU',
                    'rows' => [
                        [['text' => 'Model'], ['text' => 'BLEU Score']],
                        [['text' => 'Transformer-Base'], ['text' => '27.3']],
                    ],
                ],
            ],
        ],
        'word_count' => 30,
        'last_saved_at' => now(),
    ]);

    $page = Livewire::test(NaskahStudioPage::class);
    $page->call('openDocument', $doc->id);

    // 1. BibTeX Export resolves project references even if sourceProjectId was not preset
    $response = $page->instance()->export('bibtex');
    expect($response)->toBeInstanceOf(\Symfony\Component\HttpFoundation\StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $bibOutput = ob_get_clean();

    expect($bibOutput)->toContain('@article{vaswani2017attention')
        ->and($bibOutput)->toContain('Attention Is All You Need')
        ->and($bibOutput)->toContain('Ashish Vaswani');

    // 2. LaTeX Export handles footnotes, endnotes, and table caption
    $model = app(NaskahRichModel::class);
    $latex = $model->toLatex($page->get('blocks'), 'Dokumen Naskah Riset AI');

    expect($latex)->toContain('\\footnote{Lihat catatan teknis arsitektur transformer.}')
        ->and($latex)->toContain('\\footnote{Data observasi tambahan tersedia pada repositori publik.}')
        ->and($latex)->toContain('\\caption{Tabel 1: Ringkasan Metrik Akurasi BLEU}');

    // 3. Markdown and HTML exports format footnotes
    $md = $model->toMarkdown($page->get('blocks'));
    expect($md)->toContain('[^nota]: Lihat catatan teknis arsitektur transformer.')
        ->and($md)->toContain('[^akhir]: Data observasi tambahan tersedia pada repositori publik.');

    $html = $model->toHtml($page->get('blocks'));
    expect($html)->toContain('Catatan Kaki')
        ->and($html)->toContain('Catatan Akhir');

    // 4. computeStatistics returns fleschGrade
    $stats = $model->computeStatistics($page->get('blocks'));
    expect($stats)->toHaveKey('fleschGrade')
        ->and($stats['fleschGrade'])->toBeString();

    // 5. /keterbacaan assistant slash command outputs flesch grade
    $reply = $page->instance()->answerResponse($project, '/keterbacaan');
    expect($reply['reply'])->toContain('Skor Flesch Reading Ease')
        ->and($reply['reply'])->toContain($stats['fleschGrade']);
});

it('supports multi-format table caption export, footnote markdown parsing, and accurate uncited citation auditing', function (): void {
    $this->actingAs(User::factory()->create());
    $project = makeProject();

    $ref1 = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'gauntlett2018making',
        'title' => 'Making is Connecting',
        'authors' => [['name' => 'David Gauntlett']],
        'year' => 2018,
        'journal_name' => 'Polity Press',
        'is_verified' => true,
    ]);

    $ref2 = ProjectReference::create([
        'project_id' => $project->id,
        'citation_key' => 'sennett2019craftsman',
        'title' => 'The Craftsman',
        'authors' => [['name' => 'Richard Sennett']],
        'year' => 2019,
        'journal_name' => 'Yale University Press',
        'is_verified' => true,
    ]);

    $model = app(NaskahRichModel::class);

    // 1. Table caption export in HTML, Markdown, and RTF
    $tableBlock = $model->newTableBlock([
        [['text' => 'Indikator'], ['text' => 'Nilai']],
        [['text' => 'F1-Score'], ['text' => '0.94']],
    ], true, 'Tabel 2: Evaluasi Performa Klasifikasi');

    $html = $model->toHtml([$tableBlock]);
    expect($html)->toContain('<caption')
        ->and($html)->toContain('Tabel 2: Evaluasi Performa Klasifikasi');

    $md = $model->toMarkdown([$tableBlock]);
    expect($md)->toContain('*Tabel 2: Evaluasi Performa Klasifikasi*');

    $rtf = $model->toRtf([$tableBlock]);
    expect($rtf)->toContain('Tabel 2: Evaluasi Performa Klasifikasi')
        ->and($rtf)->toContain('F1-Score');

    // 2. parseMarkdownToBlocks parses footnote and endnote definitions
    $markdownSample = "Paragraf pembuka pembahasan.\n\n[^1]: Rujukan catatan kaki primer.\n\n[^akhir]: Rujukan catatan akhir dokumen.";
    $parsedBlocks = $model->parseMarkdownToBlocks($markdownSample);

    expect($parsedBlocks)->toHaveCount(3)
        ->and($parsedBlocks[1]['type'])->toBe('footnote-ref')
        ->and($parsedBlocks[1]['text'])->toBe('Rujukan catatan kaki primer.')
        ->and($parsedBlocks[2]['type'])->toBe('endnote-ref')
        ->and($parsedBlocks[2]['text'])->toBe('Rujukan catatan akhir dokumen.');

    // 3. auditCitations accurately verifies author-year citation and excludes from uncited
    $page = Livewire::test(NaskahStudioPage::class);
    $page->set('sourceProjectId', $project->id);
    $page->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Berdasarkan telaah empiris (Gauntlett, 2018), interaksi komunitas memicu inovasi.'],
    ]);

    $audit = $page->instance()->auditCitations($project->id);

    expect($audit['verified'])->toContain('Gauntlett, 2018')
        ->and($audit['unmatched'])->toBeEmpty()
        ->and($audit['uncited'])->toContain('sennett2019craftsman')
        ->and($audit['uncited'])->not->toContain('gauntlett2018making');
});

it('supports dynamic table of contents generation and multi-format rendering across html, latex, markdown, and rtf', function (): void {
    $model = app(NaskahRichModel::class);

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Urgensi riset dan motivasi telaah empiris.'],
            ['type' => 'heading', 'level' => 2, 'text' => 'Rumusan Masalah'],
            ['type' => 'paragraph', 'text' => 'Pertanyaan penelitian dirumuskan secara terstruktur.'],
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab II Tinjauan Pustaka'],
            ['type' => 'paragraph', 'text' => 'Kajian literatur dan sintesis teori.'],
        ])
        ->call('insertTableOfContents');

    $blocks = $page->get('blocks');
    $tocHeadingCount = collect($blocks)->filter(fn ($b) => ($b['type'] ?? '') === 'heading' && trim($b['text'] ?? '') === 'Daftar Isi')->count();
    expect($tocHeadingCount)->toBe(1);

    // Call updateTableOfContents to verify idempotency (no duplicate headings)
    $page->call('updateTableOfContents');
    $updatedBlocks = $page->get('blocks');
    $tocHeadingCountAfter = collect($updatedBlocks)->filter(fn ($b) => ($b['type'] ?? '') === 'heading' && trim($b['text'] ?? '') === 'Daftar Isi')->count();
    expect($tocHeadingCountAfter)->toBe(1);

    $tocBlock = collect($updatedBlocks)->firstWhere('type', 'toc');
    expect($tocBlock)->not->toBeNull()
        ->and($tocBlock['table']['rows'])->not->toBeEmpty();

    // Verify multi-format exports
    // 1. HTML export with two-column layout
    $html = $model->toHtml($updatedBlocks, 'Dokumen Uji', ['columns' => 2]);
    expect($html)->toContain('table-of-contents')
        ->and($html)->toContain('Bab I Pendahuluan')
        ->and($html)->toContain('Bab II Tinjauan Pustaka')
        ->and($html)->toContain('column-count:2');

    // 2. LaTeX export with twocolumn documentclass and \tableofcontents
    $latex = $model->toLatex($updatedBlocks, 'Dokumen Uji', ['columns' => 2]);
    expect($latex)->toContain('twocolumn')
        ->and($latex)->toContain('\\tableofcontents')
        ->and($latex)->toContain('\\usepackage{graphicx}');

    // 3. Markdown export
    $md = $model->toMarkdown($updatedBlocks);
    expect($md)->toContain('### Daftar Isi')
        ->and($md)->toContain('Bab I Pendahuluan')
        ->and($md)->toContain('Bab II Tinjauan Pustaka');

    // 4. RTF export
    $rtf = $model->toRtf($updatedBlocks);
    expect($rtf)->toContain('DAFTAR ISI')
        ->and($rtf)->toContain('Bab I Pendahuluan')
        ->and($rtf)->toContain('Bab II Tinjauan Pustaka');
});

it('supports in-text citation formatting across styles and rich docx and odt multi-block exports', function (): void {
    $formatter = app(CitationFormatter::class);

    // 1. Single author APA
    $ref1 = new ProjectReference([
        'id' => 1,
        'citation_key' => 'smith2024',
        'authors' => [['name' => 'John Smith']],
        'year' => 2024,
    ]);
    expect($formatter->inText($ref1, CitationStyle::APA7))->toBe('(Smith, 2024)')
        ->and($formatter->inText($ref1, CitationStyle::IEEE))->toBe('[1]')
        ->and($formatter->inText($ref1, CitationStyle::VANCOUVER))->toBe('(1)')
        ->and($formatter->inText($ref1, CitationStyle::CHICAGO))->toBe('(Smith 2024)');

    // 2. Two authors APA & Harvard
    $ref2 = new ProjectReference([
        'id' => 2,
        'citation_key' => 'smith2024dual',
        'authors' => [['name' => 'John Smith'], ['name' => 'Alice Jones']],
        'year' => 2024,
    ]);
    expect($formatter->inText($ref2, CitationStyle::APA7))->toBe('(Smith & Jones, 2024)')
        ->and($formatter->inText($ref2, CitationStyle::HARVARD))->toBe('(Smith and Jones, 2024)');

    // 3. Three+ authors et al.
    $ref3 = new ProjectReference([
        'id' => 3,
        'citation_key' => 'vaswani2017',
        'authors' => [['name' => 'Ashish Vaswani'], ['name' => 'Noam Shazeer'], ['name' => 'Niki Parmar']],
        'year' => 2017,
    ]);
    expect($formatter->inText($ref3, CitationStyle::APA7))->toBe('(Vaswani et al., 2017)');

    // 4. DOCX & ODT multi-block exports with table captions, footnotes, endnotes, and toc
    $docxExporter = app(NaskahDocxExporter::class);
    $odtExporter = app(NaskahOdtExporter::class);

    $blocks = [
        ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
        ['type' => 'callout', 'calloutType' => 'warning', 'text' => 'Perhatian metodologis.'],
        ['type' => 'equation', 'latex' => 'y = mx + c', 'caption' => '(1)'],
        ['type' => 'table', 'table' => ['caption' => 'Tabel 1: Ringkasan Metrik', 'rows' => [[['text' => 'A'], ['text' => 'B']]]]],
        ['type' => 'toc', 'table' => ['rows' => [[['text' => 'Judul'], ['text' => 'Halaman']], [['text' => 'Bab I'], ['text' => '1']]]]],
        ['type' => 'footnote-ref', 'text' => 'Catatan kaki penjelasan.'],
        ['type' => 'endnote-ref', 'text' => 'Catatan akhir referensi.'],
    ];

    $docxBinary = $docxExporter->build($blocks, 'Uji Naskah');
    $zipDocx = new \ZipArchive;
    $tempDocx = tempnam(sys_get_temp_dir(), 'docx_test');
    file_put_contents($tempDocx, $docxBinary);
    $zipDocx->open($tempDocx);
    $docxXml = $zipDocx->getFromName('word/document.xml');
    $zipDocx->close();
    @unlink($tempDocx);

    expect($docxXml)->toContain('Tabel 1: Ringkasan Metrik')
        ->and($docxXml)->toContain('[Catatan Kaki]')
        ->and($docxXml)->toContain('[Catatan Akhir]');

    $odtBinary = $odtExporter->build($blocks, 'Uji Naskah');
    $zipOdt = new \ZipArchive;
    $tempOdt = tempnam(sys_get_temp_dir(), 'odt_test');
    file_put_contents($tempOdt, $odtBinary);
    $zipOdt->open($tempOdt);
    $odtXml = $zipOdt->getFromName('content.xml');
    $zipOdt->close();
    @unlink($tempOdt);

    expect($odtXml)->toContain('Tabel 1: Ringkasan Metrik')
        ->and($odtXml)->toContain('[Catatan Kaki]')
        ->and($odtXml)->toContain('[Catatan Akhir]')
        ->and($odtXml)->toContain('y = mx + c');

    // 5. Cross-Reference modal includes Headings
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Teks acuan.'],
        ])
        ->set('activeBlock', 1)
        ->call('openDialog', 'crossref');

    $page->assertSee('Bagian &amp; Bab Dokumen (Headings)', false)
        ->assertSee('H1: Bab I Pendahuluan', false);

    $page->call('insertCrossReference', 'heading', 'Bab I Pendahuluan');
    $blocks = $page->get('blocks');
    $lastBlock = end($blocks);
    expect($lastBlock['text'])->toContain('Bab I Pendahuluan');
});

it('supports rich block parsing for callouts, tables, equations, footnotes and wordcraft paraphrase and translate', function (): void {
    $parser = app(NaskahBlockParser::class);

    // 1. Parse markdown with callout, table, equation, footnote
    $markdown = <<<'MD'
# Bab I Pendahuluan
Ini pengantar penelitian.

> [!NOTE] Perhatian terhadap batasan komputasi.

$$ \int_0^1 x^2 dx = \frac{1}{3} $$

| Parameter | Baseline | Proposed |
| --- | --- | --- |
| Akurasi | 0.82 | 0.94 |
| F1-Score | 0.80 | 0.93 |

[^nota]: Evaluasi dihitung pada 5-fold cross validation.
MD;

    $blocks = $parser->parse($markdown);
    $types = array_column($blocks, 'type');

    expect($types)->toContain('heading')
        ->and($types)->toContain('paragraph')
        ->and($types)->toContain('callout')
        ->and($types)->toContain('equation')
        ->and($types)->toContain('table')
        ->and($types)->toContain('footnote-ref');

    // Callout attributes
    $callout = $blocks[2];
    expect($callout['calloutType'])->toBe('note')
        ->and($callout['text'])->toContain('Perhatian terhadap batasan komputasi.');

    // Equation attributes
    $equation = $blocks[3];
    expect($equation['latex'])->toContain('\int_0^1');

    // Table attributes & row count
    $table = $blocks[4];
    expect($table['table']['rows'])->toHaveCount(3)
        ->and($table['table']['rows'][0][0]['text'])->toBe('Parameter');

    // 2. Word count including table
    $wordCount = $parser->countWords($blocks);
    expect($wordCount)->toBeGreaterThan(20);

    // 3. Normalization preserves attributes
    $normalized = $parser->normalize($blocks);
    expect($normalized[2]['calloutType'])->toBe('note')
        ->and($normalized[4]['table']['rows'])->toHaveCount(3);

    // 4. Wordcraft in-block paraphrase and translate
    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Banyak sekali data yang membuat hasil sangat bagus dikarenakan model baru.'],
        ])
        ->set('activeBlock', 0);

    // AI Paraphrase
    $page->call('aiParaphraseBlock', 0);
    $paraphrasedText = $page->get('blocks')[0]['text'];
    expect($paraphrasedText)->not->toBe('Banyak sekali data yang membuat hasil sangat bagus dikarenakan model baru.')
        ->and($paraphrasedText)->toContain('sejumlah besar');

    // AI Translate to English
    $page->call('aiTranslateBlock', 0, 'en');
    $translatedText = $page->get('blocks')[0]['text'];
    expect($translatedText)->toContain('empirical');

    // Command palette execution for paraphrase and translate
    $page->call('executeCommand', 'ai.paraphrase');
    expect($page->get('blocks')[0]['text'])->not->toBeEmpty();

    $page->call('executeCommand', 'ai.translate', ['target' => 'id']);
    expect($page->get('blocks')[0]['text'])->not->toBeEmpty();
});

it('supports literature matrix table block insertion, idempotent bibliography, and statistical formulas', function (): void {
    $project = makeProject();
    seedReferences($project);

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('sourceProjectId', $project->id)
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'text' => 'Tinjauan pustaka terkait.'],
        ])
        ->set('activeBlock', 1);

    // 1. Insert literature matrix table block directly
    $page->call('insertLiteratureMatrixTable', $project->id);
    $blocks = $page->get('blocks');
    $lastBlock = end($blocks);
    expect($lastBlock['type'])->toBe('table')
        ->and($lastBlock['table']['caption'])->toContain('Matriks Sintesis Literatur')
        ->and($lastBlock['table']['rows'])->toHaveCount(3);

    // 2. Command palette execution for insert.matrix
    $page->call('executeCommand', 'insert.matrix');
    expect(count($page->get('blocks')))->toBeGreaterThan(3);

    // 3. Idempotent bibliography generation (calling insert twice does not duplicate heading)
    $page->call('insertBibliography', $project->id);
    $blocksAfterFirstBib = $page->get('blocks');
    $bibHeadingCount1 = 0;
    foreach ($blocksAfterFirstBib as $b) {
        if (($b['type'] ?? '') === 'heading' && preg_match('/^(daftar pustaka|bibliography|references)/iu', trim((string) ($b['text'] ?? '')))) {
            $bibHeadingCount1++;
        }
    }
    expect($bibHeadingCount1)->toBe(1);

    // Second call should update in-place, keeping heading count at 1
    $page->call('insertBibliography', $project->id);
    $blocksAfterSecondBib = $page->get('blocks');
    $bibHeadingCount2 = 0;
    foreach ($blocksAfterSecondBib as $b) {
        if (($b['type'] ?? '') === 'heading' && preg_match('/^(daftar pustaka|bibliography|references)/iu', trim((string) ($b['text'] ?? '')))) {
            $bibHeadingCount2++;
        }
    }
    expect($bibHeadingCount2)->toBe(1);

    // 4. Insert assistant response containing table_block
    $builder = app(\App\Domain\Research\LiteratureMatrixBuilder::class);
    $tableBlock = $builder->toTableBlock($project);
    $page->set('assistantLog', [
        [
            'role' => 'assistant',
            'content' => 'Berikut matriks komparasi literatur:',
            'data' => [
                'table_block' => $tableBlock,
            ],
        ],
    ]);
    $page->call('insertAssistantResponse', 0);
    $blocksWithAssistantTable = $page->get('blocks');
    $insertedBlock = $blocksWithAssistantTable[$page->get('activeBlock')];
    expect($insertedBlock['type'])->toBe('table')
        ->and($insertedBlock['table']['caption'])->toContain('Matriks Sintesis Literatur');
});

it('supports table sample standard deviation calculation and callout alert preservation', function (): void {
    $this->actingAs(User::factory()->create());

    $page = Livewire::test(NaskahStudioPage::class);

    // 1. Table sample standard deviation calculation
    $page->set('blocks', [
        [
            'type' => 'table',
            'level' => 0,
            'text' => '',
            'table' => [
                'header' => true,
                'rows' => [
                    [['text' => 'Item'], ['text' => 'Nilai']],
                    [['text' => 'A'], ['text' => '10']],
                    [['text' => 'B'], ['text' => '20']],
                    [['text' => 'C'], ['text' => '30']],
                ],
            ],
        ],
    ]);

    $page->call('tableCalculateColumn', 0, 1, 'stddev');
    $rows = $page->get('blocks')[0]['table']['rows'];
    $lastRow = end($rows);

    expect($lastRow[0]['text'])->toBe('STDDEV')
        ->and($lastRow[1]['text'])->toBe('10');

    // 2. Callout preservation for sketch, important, caution
    $richModel = app(NaskahRichModel::class);
    $callouts = [
        ['type' => 'callout', 'text' => 'Catatan alur sketsa', 'calloutType' => 'sketch'],
        ['type' => 'callout', 'text' => 'Peringatan penting', 'calloutType' => 'important'],
        ['type' => 'callout', 'text' => 'Perhatian batas riset', 'calloutType' => 'caution'],
    ];

    $normalized = $richModel->normalize($callouts);
    expect($normalized[0]['calloutType'])->toBe('sketch')
        ->and($normalized[1]['calloutType'])->toBe('important')
        ->and($normalized[2]['calloutType'])->toBe('caution');

    // 3. Docx export preserves callout styling prefixes and colors
    $docxBinary = app(\App\Domain\Publication\NaskahDocxExporter::class)->build($normalized, 'Naskah Uji Callout');
    $zip = new \ZipArchive();
    $tempDocx = tempnam(sys_get_temp_dir(), 'docx_callout_');
    file_put_contents($tempDocx, $docxBinary);
    $opened = $zip->open($tempDocx);
    expect($opened)->toBeTrue();
    $xml = $zip->getFromName('word/document.xml');
    $zip->close();
    unlink($tempDocx);

    expect($xml)->toContain('[Sketsa &amp; Diagram]')
        ->and($xml)->toContain('[Penting]')
        ->and($xml)->toContain('[Perhatian]')
        ->and($xml)->toContain('8E44AD')
        ->and($xml)->toContain('A80000');
});

it('supports table and equation caption promotion, csv manuscript export, and manuscript exporter csv format', function (): void {
    $this->actingAs(User::factory()->create());

    // 1. Caption promotion in NaskahBlockParser
    $parser = app(\App\Domain\Publication\NaskahBlockParser::class);
    $markdown = <<<MD
# Pengujian Caption Otomatis

Tabel 1: Hasil Akurasi Model
| Model | Akurasi | F1-Score |
| SVM | 88.5% | 87.2% |
| CNN | 94.1% | 93.8% |

Persamaan 1: Model Regresi Linear
$$ y = \beta_0 + \beta_1 x + \varepsilon $$
MD;

    $blocks = $parser->parse($markdown);

    $tableBlock = null;
    $equationBlock = null;
    foreach ($blocks as $b) {
        if ($b['type'] === 'table') {
            $tableBlock = $b;
        } elseif ($b['type'] === 'equation') {
            $equationBlock = $b;
        }
    }

    expect($tableBlock)->not->toBeNull()
        ->and($tableBlock['table']['caption'])->toBe('Tabel 1: Hasil Akurasi Model')
        ->and($equationBlock)->not->toBeNull()
        ->and($equationBlock['caption'])->toBe('Persamaan 1: Model Regresi Linear');

    // 2. CSV export via NaskahRichModel and HandlesNaskahStudio
    $richModel = app(\App\Domain\Publication\NaskahRichModel::class);
    $csvString = $richModel->toCsv($blocks, 'Pengujian Caption Otomatis');
    expect($csvString)->toContain('Tabel 1: Hasil Akurasi Model')
        ->and($csvString)->toContain('SVM,88.5%,87.2%')
        ->and($csvString)->toContain('CNN,94.1%,93.8%');

    $page = Livewire::test(NaskahStudioPage::class);
    $page->set('blocks', $blocks);
    $page->call('export', 'csv')
        ->assertFileDownloaded();

    // 3. ManuscriptExporter CSV export
    $project = makeProject();
    $exporter = app(\App\Domain\Publication\ManuscriptExporter::class);
    expect($exporter->isSupported('csv'))->toBeTrue();

    $csvRender = $exporter->render($project, 'csv');
    expect($csvRender)->toContain('# Metadata Naskah')
        ->and($csvRender)->toContain('# Bagian Naskah')
        ->and($csvRender)->toContain('Bab / Bagian');
});

it('supports findNext, replaceCurrent, and replaceAll on table cells, captions, and equation blocks in naskah studio', function (): void {
    $tableBlock = [
        'type' => 'table',
        'table' => [
            'caption' => 'Tabel Hasil Optimasi Baseline',
            'rows' => [
                [
                    ['text' => 'Model'],
                    ['text' => 'Akurasi Baseline'],
                ],
                [
                    ['text' => 'ResNet-50'],
                    ['text' => '89.5% Baseline'],
                ],
            ],
        ],
    ];

    $equationBlock = [
        'type' => 'equation',
        'latex' => 'E = m \cdot c^2',
        'caption' => 'Persamaan Energi Einstein',
    ];

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            $tableBlock,
            $equationBlock,
        ])
        ->set('findQuery', 'Baseline')
        ->set('replaceQuery', 'Benchmark');

    // First findNext should focus table block (index 0)
    $page->call('findNext');
    expect($page->get('activeBlock'))->toBe(0)
        ->and($page->get('findResult'))->toContain('Ditemukan 3 kemunculan');

    // replaceCurrent on table block: replaces in caption first
    $page->call('replaceCurrent');
    expect($page->get('blocks')[0]['table']['caption'])->toBe('Tabel Hasil Optimasi Benchmark')
        ->and($page->get('findResult'))->toContain('Ditemukan 2 kemunculan');

    // Second replaceCurrent: replaces in the first matching cell ("Akurasi Baseline" -> "Akurasi Benchmark")
    $page->call('replaceCurrent');
    $rows = $page->get('blocks')[0]['table']['rows'];
    expect($rows[0][1]['text'])->toBe('Akurasi Benchmark')
        ->and($rows[1][1]['text'])->toBe('89.5% Baseline');

    // Test equation search and replace
    $page->set('findQuery', 'm \cdot c^2')
        ->set('replaceQuery', 'h \cdot \nu')
        ->set('activeBlock', 1);

    $page->call('replaceCurrent');
    expect($page->get('blocks')[1]['latex'])->toBe('E = h \cdot \nu')
        ->and($page->get('blocks')[1]['text'])->toBe('E = h \cdot \nu');

    // Test replaceAll across equation
    $page->set('findQuery', '\nu')
        ->set('replaceQuery', 'f')
        ->call('replaceAll');
    expect($page->get('blocks')[1]['latex'])->toBe('E = h \cdot f');
});

it('supports applyAssistantToCurrentPage with structured table_block and includes amsmath and amssymb in latex exports', function (): void {
    // 1. Test applyAssistantToCurrentPage with table_block
    $tableBlock = [
        'type' => 'table',
        'table' => [
            'caption' => 'Tabel Matriks Fitur Evaluasi',
            'rows' => [
                [['text' => 'Fitur'], ['text' => 'Status']],
                [['text' => 'Tabel Terstruktur'], ['text' => 'Didukung']],
            ],
        ],
    ];

    $page = Livewire::test(NaskahStudioPage::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Paragraf awal naskah pengujian.'],
        ])
        ->set('assistantLog', [
            [
                'role' => 'assistant',
                'content' => 'Berikut tabel matriks fitur:',
                'data' => [
                    'table_block' => $tableBlock,
                ],
            ],
        ]);

    $result = $page->instance()->applyAssistantToCurrentPage(0, 1);
    expect($result)->toBeTrue();

    $blocks = $page->get('blocks');
    $hasTable = false;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'table' && ($b['table']['caption'] ?? '') === 'Tabel Matriks Fitur Evaluasi') {
            $hasTable = true;
            break;
        }
    }
    expect($hasTable)->toBeTrue();

    // 2. Test amsmath, amssymb in NaskahRichModel::toLatex()
    $richModel = app(\App\Domain\Publication\NaskahRichModel::class);
    $latexStr = $richModel->toLatex($blocks, 'Naskah Pengujian Formula');
    expect($latexStr)->toContain('\usepackage{amsmath,amssymb}')
        ->and($latexStr)->toContain('\begin{table}');

    // 3. Test amsmath, amssymb in ManuscriptExporter
    $project = makeProject();
    $exporter = app(\App\Domain\Publication\ManuscriptExporter::class);
    $manuscriptLatex = $exporter->render($project, 'latex');
    expect($manuscriptLatex)->toContain('\usepackage{amsmath,amssymb}');
});

it('supports multiline callout parsing, rich rtf equations, and styled html and odt callout exports', function (): void {
    $parser = app(\App\Domain\Publication\NaskahBlockParser::class);
    $richModel = app(\App\Domain\Publication\NaskahRichModel::class);
    $odtExporter = app(\App\Domain\Publication\NaskahOdtExporter::class);

    // 1. Multiline callout parsing
    $markdown = "> [!WARNING]\n> Baris pertama peringatan metodologi.\n> Baris kedua penjelasan keterbatasan studi.";
    $parsedBlocks = $parser->parse($markdown);

    expect($parsedBlocks)->toHaveCount(1)
        ->and($parsedBlocks[0]['type'])->toBe('callout')
        ->and($parsedBlocks[0]['calloutType'])->toBe('warning')
        ->and($parsedBlocks[0]['text'])->toContain('Baris pertama peringatan metodologi.')
        ->and($parsedBlocks[0]['text'])->toContain('Baris kedua penjelasan keterbatasan studi.');

    // 2. HTML styled callout export
    $testBlocks = [
        ['type' => 'callout', 'calloutType' => 'warning', 'text' => 'Peringatan bias sampel.'],
        ['type' => 'callout', 'calloutType' => 'caution', 'text' => 'Perhatian risiko kebocoran data.'],
        ['type' => 'callout', 'calloutType' => 'important', 'text' => 'Penting untuk replikasi.'],
        ['type' => 'table', 'table' => ['caption' => 'Tabel Parameter Model', 'rows' => [[['text' => 'Epoch'], ['text' => '100']]]]],
        ['type' => 'equation', 'latex' => 'f(x) = \sigma(Wx + b)', 'caption' => 'Persamaan Aktivasi Sigmoid'],
    ];

    $html = $richModel->toHtml($testBlocks, 'Uji Dokumen Ekspor Kaya');
    expect($html)->toContain('[Peringatan]')
        ->and($html)->toContain('border-left:4px solid #d97706')
        ->and($html)->toContain('[Perhatian]')
        ->and($html)->toContain('border-left:4px solid #dc2626')
        ->and($html)->toContain('[Penting]')
        ->and($html)->toContain('border-left:4px solid #7c3aed');

    // 3. Plain text includes table caption and equation
    $plain = $richModel->toPlainText($testBlocks);
    expect($plain)->toContain('Tabel Parameter Model')
        ->and($plain)->toContain('f(x) = \sigma(Wx + b) (Persamaan Aktivasi Sigmoid)');

    // 4. RTF includes equation block and standardized callout labels
    $rtf = $richModel->toRtf($testBlocks, 'Uji RTF Formula');
    expect($rtf)->toContain('[Peringatan]')
        ->and($rtf)->toContain('sigma(Wx + b)')
        ->and($rtf)->toContain('Persamaan Aktivasi Sigmoid');

    // 5. ODT includes standardized callout label
    $odtBinary = $odtExporter->build($testBlocks, 'Uji ODT');
    $zipOdt = new \ZipArchive;
    $tempOdt = tempnam(sys_get_temp_dir(), 'odt_test');
    file_put_contents($tempOdt, $odtBinary);
    $zipOdt->open($tempOdt);
    $odtXml = $zipOdt->getFromName('content.xml');
    $zipOdt->close();
    @unlink($tempOdt);

    expect($odtXml)->toContain('[Peringatan]')
        ->and($odtXml)->toContain('[Perhatian]')
        ->and($odtXml)->toContain('[Penting]');
});

it('synchronizes document word count and statistics across wordcraft ai block actions and block modifications', function () {
    $user = User::factory()->create();
    $project = XishieProject::factory()->create(['user_id' => $user->id]);
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'project_id' => $project->id,
        'title' => 'Dokumen Pengujian AI Telemetri',
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Banyak sekali data yang dikarenakan oleh situasi ini dimana hasilnya sangat bagus.'],
        ],
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    $initialWords = $component->get('docWordCount');
    expect($initialWords)->toBeGreaterThan(0);
    expect($component->get('statistics')['words'])->toBe($initialWords);

    // 1. aiParaphraseBlock replaces colloquial words with formal academic phrasing and updates docWordCount & statistics
    $component->call('aiParaphraseBlock', 0);
    $paraWords = $component->get('docWordCount');
    expect($component->get('blocks')[0]['text'])->toContain('sejumlah besar')
        ->and($component->get('blocks')[0]['text'])->toContain('optimal dan signifikan')
        ->and($component->get('statistics')['words'])->toBe($paraWords);

    // 2. aiTranslateBlock translates text and updates word count and statistics
    $component->call('aiTranslateBlock', 0, 'en');
    $enWords = $component->get('docWordCount');
    expect($component->get('blocks')[0]['text'])->toContain('rigorous empirical validation')
        ->and($component->get('statistics')['words'])->toBe($enWords);

    // 3. updateBlockText updates docWordCount and statistics
    $component->call('updateBlockText', 0, 'Satu dua tiga empat lima enam.');
    expect($component->get('docWordCount'))->toBe(6)
        ->and($component->get('statistics')['words'])->toBe(6);

    // 4. addBlock updates docWordCount and statistics
    $component->call('addBlock', 'paragraph');
    expect($component->get('docWordCount'))->toBe(6)
        ->and($component->get('statistics')['paragraphs'])->toBeGreaterThanOrEqual(2);

    // 5. duplicateBlock updates docWordCount and statistics
    $component->call('duplicateBlock', 0);
    expect($component->get('docWordCount'))->toBe(12)
        ->and($component->get('statistics')['words'])->toBe(12);

    // 6. removeBlock updates docWordCount and statistics
    $component->call('removeBlock', 1);
    expect($component->get('docWordCount'))->toBe(6)
        ->and($component->get('statistics')['words'])->toBe(6);
});

it('supports thesaurus command, structured table payloads, and statistics synchronization in find-replace and thesaurus application', function () {
    $user = User::factory()->create();
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'title' => 'Dokumen Pengujian Tesaurus & Cari Ganti',
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Kami melakukan analisis mendalam terhadap data penelitian ini.'],
        ],
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. answerResponse with /tesaurus returns synonyms and structured data
    $thesaurusResp = $component->instance()->answerResponse(null, '/tesaurus analisis');
    expect($thesaurusResp['reply'])->toContain('Tesaurus Akademik')
        ->and($thesaurusResp['reply'])->toContain('telaah')
        ->and($thesaurusResp['data']['word'])->toBe('analisis')
        ->and($thesaurusResp['data']['synonyms'])->toContain('telaah');

    // 2. answerResponse with /tabel-analisis returns structured table_block
    $tableResp = $component->instance()->answerResponse(null, '/tabel-analisis');
    expect($tableResp['data'])->toHaveKey('table_block')
        ->and($tableResp['data']['table_block']['type'])->toBe('table')
        ->and($tableResp['data']['table_block']['table']['rows'])->toHaveCount(5);

    // 3. answerResponse with /matriks-literatur returns structured table_block
    $matrixResp = $component->instance()->answerResponse(null, '/matriks-literatur');
    expect($matrixResp['data'])->toHaveKey('table_block')
        ->and($matrixResp['data']['table_block']['type'])->toBe('table');

    // 4. applyThesaurusSynonym replaces the target word and synchronizes statistics and docWordCount
    $component->set('thesaurusWord', 'analisis');
    $component->call('applyThesaurusSynonym', 'kajian kritis komparatif');
    expect($component->get('blocks')[0]['text'])->toContain('kajian kritis komparatif')
        ->and($component->get('docWordCount'))->toBeGreaterThan(8)
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));

    // 5. replaceCurrent updates docWordCount and statistics
    $component->set('findQuery', 'data penelitian');
    $component->set('replaceQuery', 'bukti empiris primer');
    $component->call('replaceCurrent');
    expect($component->get('blocks')[0]['text'])->toContain('bukti empiris primer')
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));

    // 6. replaceAll updates docWordCount and statistics
    $component->set('findQuery', 'mendalam');
    $component->set('replaceQuery', 'sistematis komprehensif');
    $component->call('replaceAll');
    expect($component->get('blocks')[0]['text'])->toContain('sistematis komprehensif')
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));
});

it('supports advanced docx and odt multi-level list exports, styled runs, and structured assistant application for novelty, footnotes, and methodology', function () {
    $user = User::factory()->create();
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'title' => 'Naskah Pengujian Format Multi-Level & Asisten Terstruktur',
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Paragraf pembuka.'],
        ],
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. Test NaskahRichModel parseMarkdownToBlocks indented listLevel detection
    $model = app(\App\Domain\Publication\NaskahRichModel::class);
    $markdown = "1. Item Utama\n  - Sub Bullet Level 1\n    - Sub Bullet Level 2\n2. Item Utama Dua";
    $parsedBlocks = $model->parseMarkdownToBlocks($markdown);
    expect($parsedBlocks)->toHaveCount(4)
        ->and($parsedBlocks[0]['type'])->toBe('numbered')
        ->and($parsedBlocks[0]['listLevel'])->toBe(0)
        ->and($parsedBlocks[1]['type'])->toBe('bullet')
        ->and($parsedBlocks[1]['listLevel'])->toBe(1)
        ->and($parsedBlocks[2]['type'])->toBe('bullet')
        ->and($parsedBlocks[2]['listLevel'])->toBe(2);

    // 2. Test NaskahDocxExporter with multi-level lists, hanging indent, shading, and table margins
    $docxExporter = app(\App\Domain\Publication\NaskahDocxExporter::class);
    $docxBlocks = [
        ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
        [
            'type' => 'paragraph',
            'text' => 'Paragraf dengan hanging indent dan latar belakang.',
            'hangingIndent' => true,
            'backgroundColor' => '#f1f5f9',
            'borderColor' => '#2563eb',
        ],
        ['type' => 'numbered', 'listLevel' => 0, 'text' => 'Tujuan Utama'],
        ['type' => 'numbered', 'listLevel' => 1, 'text' => 'Sub-tujuan Eksplorasi'],
        ['type' => 'bullet', 'listLevel' => 0, 'text' => 'Poin Analisis'],
        ['type' => 'bullet', 'listLevel' => 1, 'text' => 'Sub-poin Empiris'],
        [
            'type' => 'table',
            'table' => [
                'header' => true,
                'caption' => 'Tabel Pengujian Sel dan Format',
                'rows' => [
                    [['text' => 'Header 1', 'align' => 'center'], ['text' => 'Header 2', 'align' => 'right']],
                    [['text' => 'Data 1', 'bg' => '#e2e8f0'], ['text' => 'Data 2', 'highlight' => '#fef08a']],
                ],
            ],
        ],
    ];

    $docxBinary = $docxExporter->build($docxBlocks, 'Naskah Docx Canggih');
    expect(strlen($docxBinary))->toBeGreaterThan(500);

    $tempDocx = tempnam(sys_get_temp_dir(), 'test_docx_');
    file_put_contents($tempDocx, $docxBinary);
    $zipDocx = new ZipArchive;
    expect($zipDocx->open($tempDocx))->toBeTrue();
    $numberingXml = $zipDocx->getFromName('word/numbering.xml');
    $contentTypesXml = $zipDocx->getFromName('[Content_Types].xml');
    $documentXml = $zipDocx->getFromName('word/document.xml');
    $documentRelsXml = $zipDocx->getFromName('word/_rels/document.xml.rels');
    $zipDocx->close();
    @unlink($tempDocx);

    expect($numberingXml)->not->toBeFalse()
        ->and($numberingXml)->toContain('<w:abstractNum w:abstractNumId="1">')
        ->and($numberingXml)->toContain('<w:abstractNum w:abstractNumId="2">')
        ->and($numberingXml)->toContain('<w:num w:numId="1">')
        ->and($contentTypesXml)->toContain('word/numbering.xml')
        ->and($documentRelsXml)->toContain('Target="numbering.xml"')
        ->and($documentXml)->toContain('w:hanging="720"')
        ->and($documentXml)->toContain('w:tblCellMar');

    // 3. Test NaskahOdtExporter with styled runs, inline math, and grouped multi-level lists
    $odtExporter = app(\App\Domain\Publication\NaskahOdtExporter::class);
    $odtBlocks = [
        ['type' => 'heading', 'level' => 1, 'text' => 'Judul ODT'],
        [
            'type' => 'paragraph',
            'text' => 'Teks dengan format tebal dan formula $E = mc^2$ di dalam alinea.',
            'runs' => [
                ['text' => 'Teks dengan format '],
                ['text' => 'tebal', 'bold' => true],
                ['text' => ' dan formula $E = mc^2$ di dalam alinea.'],
            ],
            'align' => 'justify',
        ],
        ['type' => 'numbered', 'listLevel' => 0, 'text' => 'Langkah Pertama'],
        ['type' => 'numbered', 'listLevel' => 1, 'text' => 'Rincian Langkah Pertama'],
        ['type' => 'callout', 'calloutType' => 'theorem', 'text' => 'Teorema Pembuktian Matematis'],
    ];

    $odtBinary = $odtExporter->build($odtBlocks, 'Naskah ODT Canggih');
    expect(strlen($odtBinary))->toBeGreaterThan(500);

    $tempOdt = tempnam(sys_get_temp_dir(), 'test_odt_');
    file_put_contents($tempOdt, $odtBinary);
    $zipOdt = new ZipArchive;
    expect($zipOdt->open($tempOdt))->toBeTrue();
    $odtContentXml = $zipOdt->getFromName('content.xml');
    $odtStylesXml = $zipOdt->getFromName('styles.xml');
    $zipOdt->close();
    @unlink($tempOdt);

    expect($odtContentXml)->not->toBeFalse()
        ->and($odtStylesXml)->toContain('NumberedList')
        ->and($odtStylesXml)->toContain('T_Math')
        ->and($odtContentXml)->toContain('text:style-name="T_Bold"')
        ->and($odtContentXml)->toContain('text:style-name="T_Math"')
        ->and($odtContentXml)->toContain('text:style-name="NumberedList"');

    // 4. Test assistant /kebaruan returns callout_block and applies to document
    $noveltyResp = $component->instance()->answerResponse(null, '/kebaruan pendekatan agen otonom');
    expect($noveltyResp['data'])->toHaveKey('callout_block')
        ->and($noveltyResp['data']['callout_block']['type'])->toBe('callout');

    $component->set('assistantLog', [
        ['role' => 'assistant', 'content' => $noveltyResp['reply'], 'data' => $noveltyResp['data']],
    ]);
    $component->call('applyAssistantToCurrentPage', 0);
    $blocks = $component->get('blocks');
    $lastBlock = end($blocks);
    expect($lastBlock['type'])->toBe('callout')
        ->and($lastBlock['calloutType'])->toBe('theorem')
        ->and($lastBlock['text'])->toContain('Pernyataan Kebaruan Ilmiah');

    // 5. Test assistant /catatan-kaki returns footnote_block and applies to document
    $fnResp = $component->instance()->answerResponse(null, '/catatan-kaki Rujukan buku Smith (2024)');
    expect($fnResp['data'])->toHaveKey('footnote_block')
        ->and($fnResp['data']['footnote_block']['type'])->toBe('footnote-ref');

    $component->set('assistantLog', [
        ['role' => 'assistant', 'content' => $fnResp['reply'], 'data' => $fnResp['data']],
    ]);
    $component->call('applyAssistantToCurrentPage', 0);
    $blocksAfterFn = $component->get('blocks');
    $fnBlock = end($blocksAfterFn);
    expect($fnBlock['type'])->toBe('footnote-ref')
        ->and($fnBlock['text'])->toContain('Rujukan buku Smith (2024)');

    // 6. Test assistant /metodologi returns multi-block payload and applies to document
    $methodResp = $component->instance()->answerResponse(null, '/metodologi');
    expect($methodResp['data'])->toHaveKey('blocks')
        ->and($methodResp['data']['blocks'])->toBeArray()
        ->and(count($methodResp['data']['blocks']))->toBeGreaterThanOrEqual(4);

    $initialBlockCount = count($component->get('blocks'));
    $component->set('assistantLog', [
        ['role' => 'assistant', 'content' => $methodResp['reply'], 'data' => $methodResp['data']],
    ]);
    $component->call('applyAssistantToCurrentPage', 0);
    expect(count($component->get('blocks')))->toBeGreaterThan($initialBlockCount)
        ->and($component->get('docWordCount'))->toBeGreaterThan(0)
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));
});

it('supports comment filtering, resolve all, comment and revision navigation, and telemetry synchronization', function () {
    $user = User::factory()->create();
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'title' => 'Dokumen Pengujian Komentar & Revisi',
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Paragraf pertama untuk dikomentari.'],
            ['type' => 'paragraph', 'text' => 'Paragraf kedua untuk revisi teks.'],
            ['type' => 'paragraph', 'text' => 'Paragraf ketiga catatan penutup.'],
        ],
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. Add comments on different blocks
    $component->set('activeBlock', 0);
    $component->set('commentDraft', 'Perbaiki diksi kalimat pembuka');
    $component->call('addComment');

    $component->set('activeBlock', 2);
    $component->set('commentDraft', 'Tambahkan implikasi penelitian di sini');
    $component->call('addComment');

    expect($component->get('comments'))->toHaveCount(2)
        ->and($component->get('comments')[0]['body'])->toBe('Perbaiki diksi kalimat pembuka')
        ->and($component->get('comments')[1]['body'])->toBe('Tambahkan implikasi penelitian di sini');

    // 2. Test comment filter (all, active, resolved)
    $component->call('setCommentFilter', 'active');
    expect($component->get('commentFilter'))->toBe('active');
    $component->call('setCommentFilter', 'resolved');
    expect($component->get('commentFilter'))->toBe('resolved');
    $component->call('setCommentFilter', 'all');
    expect($component->get('commentFilter'))->toBe('all');

    // 3. Test comment navigation (previousComment, nextComment)
    $component->set('activeBlock', 0);
    $component->call('nextComment');
    expect($component->get('activeBlock'))->toBe(2);

    $component->call('previousComment');
    expect($component->get('activeBlock'))->toBe(0);

    // 4. Test resolveComment and resolveAllComments
    $component->call('resolveComment', 0);
    expect($component->get('comments')[0]['resolved'])->toBeTrue()
        ->and($component->get('comments')[1]['resolved'])->toBeFalse();

    $component->call('resolveAllComments');
    expect($component->get('comments')[0]['resolved'])->toBeTrue()
        ->and($component->get('comments')[1]['resolved'])->toBeTrue();

    // 5. Test revisions: recordRevision, nextRevision, previousRevision
    $component->call('recordRevision', 1, 'Paragraf kedua untuk revisi teks.', 'Paragraf kedua yang telah disempurnakan.');
    expect($component->get('revisions'))->toHaveCount(1)
        ->and($component->get('revisions')[0]['status'])->toBe('pending');

    $component->set('activeBlock', 0);
    $component->call('nextRevision');
    expect($component->get('activeBlock'))->toBe(1);

    // 6. Test acceptRevision and telemetry synchronization
    $component->call('acceptRevision', 0);
    expect($component->get('revisions')[0]['status'])->toBe('accepted')
        ->and($component->get('blocks')[1]['text'])->toBe('Paragraf kedua yang telah disempurnakan.')
        ->and($component->get('docWordCount'))->toBeGreaterThan(0)
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));

    // 7. Test rejectRevision and telemetry synchronization
    $component->call('recordRevision', 1, 'Paragraf kedua yang telah disempurnakan.', 'Perubahan tidak diinginkan.');
    $component->call('rejectRevision', 1);
    expect($component->get('revisions')[1]['status'])->toBe('rejected')
        ->and($component->get('blocks')[1]['text'])->toBe('Paragraf kedua yang telah disempurnakan.')
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));

    // 8. Test insertCrossReference telemetry synchronization
    $component->set('activeBlock', 0);
    $wordsBeforeRef = $component->get('docWordCount');
    $component->call('insertCrossReference', 'table', '1');
    expect($component->get('blocks')[0]['text'])->toContain('Tabel 1')
        ->and($component->get('docWordCount'))->toBeGreaterThan($wordsBeforeRef)
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));

    // 9. Test insertEquation telemetry synchronization
    $wordsBeforeEq = $component->get('docWordCount');
    $component->set('equationLatex', 'E = mc^2');
    $component->set('equationCaption', '(1)');
    $component->call('insertEquation');
    expect($component->get('docWordCount'))->toBeGreaterThanOrEqual($wordsBeforeEq)
        ->and($component->get('statistics')['words'])->toBe($component->get('docWordCount'));
});

it('supports mail merge semicolon parsing, template presets, preview navigation, pagebreaks, and advanced equation presets', function () {
    $user = User::factory()->create();
    $project = XishieProject::factory()->create(['user_id' => $user->id]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['project' => $project->id]);

    // 1. Test setMergeTemplatePreset and preview
    $component->call('setMergeTemplatePreset', 'reviewer_invitation');
    expect($component->get('mergeTemplate'))->toContain('Mitra Bestari')
        ->and($component->get('mergeCsv'))->toContain('Ahmad Fauzi')
        ->and($component->get('mergePreviews'))->toHaveCount(2)
        ->and($component->get('currentMergePreviewIndex'))->toBe(0)
        ->and($component->get('mergePreviews')[0]['text'])->toContain('Ahmad Fauzi');

    // 2. Test nextMergePreview and previousMergePreview
    $component->call('nextMergePreview');
    expect($component->get('currentMergePreviewIndex'))->toBe(1)
        ->and($component->get('mergePreviews')[1]['text'])->toContain('Sri Wahyuni');

    $component->call('previousMergePreview');
    expect($component->get('currentMergePreviewIndex'))->toBe(0);

    // 3. Test semicolon delimiter CSV parsing
    $component->set('mergeCsv', "peneliti;institusi;status\nDr. Hendra;UGM;Diterima\nProf. Maya;ITB;Revisi");
    $component->set('mergeTemplate', 'Yth. {{peneliti}} ({{institusi}}), naskah dinyatakan {{status}}.');
    $component->call('previewMerge');
    expect($component->get('mergePreviews'))->toHaveCount(2)
        ->and($component->get('mergePreviews')[0]['text'])->toBe('Yth. Dr. Hendra (UGM), naskah dinyatakan Diterima.')
        ->and($component->get('mergePreviews')[1]['text'])->toBe('Yth. Prof. Maya (ITB), naskah dinyatakan Revisi.');

    // 4. Test insertMerge with mergeSeparatePages = true
    $initialBlockCount = count($component->get('blocks'));
    $component->set('mergeSeparatePages', true);
    $component->call('insertMerge');

    $blocks = $component->get('blocks');
    expect(count($blocks))->toBeGreaterThan($initialBlockCount);

    $pagebreaks = array_values(array_filter($blocks, static fn ($b) => ($b['type'] ?? '') === 'pagebreak'));
    expect($pagebreaks)->not->toBeEmpty();

    $mergedParagraphs = array_values(array_filter($blocks, static fn ($b) => str_contains((string) ($b['text'] ?? ''), 'Dr. Hendra')));
    expect($mergedParagraphs)->not->toBeEmpty();

    // 5. Test advanced equation presets
    $component->call('setEquationPreset', 'bayes');
    expect($component->get('equationLatex'))->toBe('P(A|B) = \frac{P(B|A) P(A)}{P(B)}');

    $component->call('setEquationPreset', 'f1');
    expect($component->get('equationLatex'))->toContain('F_1 = 2 \cdot \frac{\text{Precision}');

    $component->call('setEquationPreset', 'rmse');
    expect($component->get('equationLatex'))->toContain('\text{RMSE} = \sqrt');

    $component->call('setEquationPreset', 'sigmoid');
    expect($component->get('equationLatex'))->toContain('\sigma(z)');

    $component->call('setEquationPreset', 'entropy');
    expect($component->get('equationLatex'))->toContain('H(X) = -\sum');
});

it('supports footnote and endnote editing, sequential renumbering, and assistant diagram blocks in naskah studio', function (): void {
    $user = User::factory()->create();
    $project = XishieProject::factory()->create(['user_id' => $user->id]);
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Pengujian Footnote & Diagram',
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'project_id' => $project->id,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kajian awal meninjau literatur primer [^2] dan catatan akhir [#2].'],
            ['type' => 'footnote-ref', 'level' => 0, 'text' => '[^2] Keterangan metodologis kedua.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kajian lanjutan meninjau literatur sekunder [^1] dan catatan akhir [#1].'],
            ['type' => 'footnote-ref', 'level' => 0, 'text' => '[^1] Keterangan metodologis kesatu.'],
            ['type' => 'endnote-ref', 'level' => 0, 'text' => '[#2] Elaborasi teoretis kedua.'],
            ['type' => 'endnote-ref', 'level' => 0, 'text' => '[#1] Elaborasi teoretis kesatu.'],
        ],
        'word_count' => 50,
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. Test editFootnote and update existing footnote block
    $component->call('editFootnote', 2);
    expect($component->get('editingFootnoteIndex'))->toBe(2)
        ->and($component->get('dialog'))->toBe('footnote')
        ->and($component->get('footnoteText'))->toContain('Keterangan metodologis kedua');

    $component->set('footnoteText', 'Keterangan metodologis kedua yang telah diperbarui.');
    $component->call('insertFootnote');

    expect($component->get('editingFootnoteIndex'))->toBeNull()
        ->and($component->get('dialog'))->toBe('')
        ->and($component->get('blocks')[2]['text'])->toContain('Keterangan metodologis kedua yang telah diperbarui');

    // 2. Test editEndnote and update existing endnote block
    $component->call('editEndnote', 5);
    expect($component->get('editingEndnoteIndex'))->toBe(5)
        ->and($component->get('dialog'))->toBe('endnote')
        ->and($component->get('endnoteText'))->toBe('Elaborasi teoretis kedua.');

    $component->set('endnoteText', 'Elaborasi teoretis kedua hasil revisi.');
    $component->call('insertEndnote');

    expect($component->get('editingEndnoteIndex'))->toBeNull()
        ->and($component->get('dialog'))->toBe('')
        ->and($component->get('blocks')[5]['text'])->toContain('Elaborasi teoretis kedua hasil revisi');

    // 3. Test sequential renumbering of footnotes
    $component->call('renumberFootnotes');
    $blocks = $component->get('blocks');

    // Block 2 was [^2], now should be sequentially [^1]
    expect($blocks[2]['text'])->toStartWith('[^1]')
        ->and($blocks[4]['text'])->toStartWith('[^2]');

    // References in paragraphs should also be updated
    expect($blocks[1]['text'])->toContain('[^1]')
        ->and($blocks[3]['text'])->toContain('[^2]');

    // 4. Test sequential renumbering of endnotes
    $component->call('renumberEndnotes');
    $blocks = $component->get('blocks');

    // Block 5 was [#2], now should be sequentially [#1]
    expect($blocks[5]['text'])->toStartWith('[#1]')
        ->and($blocks[6]['text'])->toStartWith('[#2]');

    expect($blocks[1]['text'])->toContain('[#1]')
        ->and($blocks[3]['text'])->toContain('[#2]');

    // 5. Test applyAssistantToCurrentPage with structured image_block
    $svgDataUri = 'data:image/svg+xml;base64,'.base64_encode('<svg><text>Flowchart</text></svg>');
    $component->set('assistantLog', [
        [
            'role' => 'assistant',
            'content' => 'Berikut alur riset rekomendasi.',
            'data' => [
                'image_block' => [
                    'type' => 'image',
                    'level' => 0,
                    'text' => 'Gambar 1: Alur Metodologi Riset Komputasi',
                    'align' => 'center',
                    'image' => [
                        'src' => $svgDataUri,
                        'alt' => 'Alur Metodologi',
                        'caption' => 'Gambar 1: Alur Metodologi Riset Komputasi',
                        'width' => 540,
                    ],
                ],
            ],
        ],
    ]);

    $applied = $component->instance()->applyAssistantToCurrentPage(0, 1);
    expect($applied)->toBeTrue();

    $updatedBlocks = $component->get('blocks');
    $insertedBlock = $updatedBlocks[$component->get('activeBlock')];
    expect($insertedBlock['type'])->toBe('image')
        ->and($insertedBlock['image']['src'])->toBe($svgDataUri)
        ->and($insertedBlock['image']['caption'])->toBe('Gambar 1: Alur Metodologi Riset Komputasi');
});

it('supports table sorting, table transpose, and Vancouver citation style in naskah studio', function (): void {
    $user = User::factory()->create();
    $project = XishieProject::factory()->create(['user_id' => $user->id]);
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Uji Tabel dan Sitasi',
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'project_id' => $project->id,
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Tabel Hasil Evaluasi'],
            [
                'type' => 'table',
                'level' => 0,
                'table' => [
                    'header' => true,
                    'rows' => [
                        [['text' => 'Model', 'runs' => []], ['text' => 'Akurasi', 'runs' => []]],
                        [['text' => 'Model C', 'runs' => []], ['text' => '78.5%', 'runs' => []]],
                        [['text' => 'Model A', 'runs' => []], ['text' => '94.2%', 'runs' => []]],
                        [['text' => 'Model B', 'runs' => []], ['text' => '82.0%', 'runs' => []]],
                    ],
                ],
            ],
        ],
        'word_count' => 30,
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. Test tableSortColumn Ascending on Column 0 (Model Name)
    $component->call('tableSortColumn', 1, 0, 'asc');
    $rows = $component->get('blocks')[1]['table']['rows'];
    expect($rows[0][0]['text'])->toBe('Model')
        ->and($rows[1][0]['text'])->toBe('Model A')
        ->and($rows[2][0]['text'])->toBe('Model B')
        ->and($rows[3][0]['text'])->toBe('Model C');

    // 2. Test tableSortColumn Descending on Column 1 (Numeric Akurasi)
    $component->call('tableSortColumn', 1, 1, 'desc');
    $rowsDesc = $component->get('blocks')[1]['table']['rows'];
    expect($rowsDesc[0][0]['text'])->toBe('Model')
        ->and($rowsDesc[1][0]['text'])->toBe('Model A')
        ->and($rowsDesc[2][0]['text'])->toBe('Model B')
        ->and($rowsDesc[3][0]['text'])->toBe('Model C');

    // 3. Test tableTranspose
    $component->call('tableTranspose', 1);
    $transposedRows = $component->get('blocks')[1]['table']['rows'];
    expect(count($transposedRows))->toBe(2)
        ->and(count($transposedRows[0]))->toBe(4)
        ->and($transposedRows[0][0]['text'])->toBe('Model')
        ->and($transposedRows[1][0]['text'])->toBe('Akurasi');

    // 4. Test Vancouver citation style
    $component->call('setCitationStyle', 'Vancouver');
    expect($component->get('citationStyle'))->toBe('VANCOUVER');

    $activeIdx = $component->get('activeBlock');
    $component->call('insertCitation');
    $blocks = $component->get('blocks');
    expect($blocks[$activeIdx]['text'])->toContain('(1)');
});

it('supports match case and whole word find-replace, table column alignment, and table data clearing in naskah studio', function (): void {
    $user = User::factory()->create();
    $project = XishieProject::factory()->create(['user_id' => $user->id]);
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Uji Cari-Ganti Lanjutan dan Format Tabel',
        'source_type' => NaskahDocument::SOURCE_PROJECT,
        'project_id' => $project->id,
        'blocks' => [
            ['type' => 'paragraph', 'text' => 'Kucing adalah hewan mamalia. Mengkucingkan kucing liar itu kucingku.'],
            [
                'type' => 'table',
                'level' => 0,
                'table' => [
                    'header' => true,
                    'rows' => [
                        [['text' => 'Nama Hewan', 'runs' => []], ['text' => 'Populasi', 'runs' => []]],
                        [['text' => 'kucing anggora', 'runs' => []], ['text' => '120', 'runs' => []]],
                        [['text' => 'Kucing persia', 'runs' => []], ['text' => '85', 'runs' => []]],
                    ],
                ],
            ],
        ],
        'word_count' => 20,
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. Test Match Case Find
    $component->set('findQuery', 'Kucing')
        ->set('findMatchCase', true)
        ->set('findWholeWord', false)
        ->call('findText');
    expect($component->get('findResult'))->toContain('Ditemukan 2 kemunculan');

    // 2. Test Whole Word Find
    $component->set('findQuery', 'kucing')
        ->set('findMatchCase', false)
        ->set('findWholeWord', true)
        ->call('findText');
    expect($component->get('findResult'))->toContain('Ditemukan 4 kemunculan');

    // 3. Test Whole Word + Match Case Replace All
    $component->set('findQuery', 'kucing')
        ->set('replaceQuery', 'felis')
        ->set('findMatchCase', true)
        ->set('findWholeWord', true)
        ->call('replaceAll');
    $blocks = $component->get('blocks');
    expect($blocks[0]['text'])->toBe('Kucing adalah hewan mamalia. Mengkucingkan felis liar itu kucingku.')
        ->and($blocks[1]['table']['rows'][1][0]['text'])->toBe('felis anggora')
        ->and($blocks[1]['table']['rows'][2][0]['text'])->toBe('Kucing persia');

    // 4. Test tableSetColumnAlign
    $component->call('tableSetColumnAlign', 1, 1, 'center');
    $updatedTable = $component->get('blocks')[1]['table']['rows'];
    expect($updatedTable[0][1]['align'])->toBe('center')
        ->and($updatedTable[1][1]['align'])->toBe('center');

    // 5. Test tableClearDataRows
    $component->call('tableClearDataRows', 1);
    $clearedTable = $component->get('blocks')[1]['table']['rows'];
    expect($clearedTable[0][0]['text'])->toBe('Nama Hewan')
        ->and($clearedTable[1][0]['text'])->toBe('')
        ->and($clearedTable[1][1]['text'])->toBe('')
        ->and($clearedTable[2][0]['text'])->toBe('');

    // 6. Test word count and telemetry sync on tableDeleteRow and tableInsertRow
    $component->call('tableInsertRow', 1, 0);
    expect($component->get('editorRevision'))->toBeGreaterThan(0);

    $component->call('tableDeleteRow', 1, 1);
    expect($component->get('blocks')[1]['table']['rows'])->toHaveCount(3);
});

it('supports markdown parsing of images, pagebreaks, inline links, and academic link presets', function () {
    $user = User::factory()->create();
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'title' => 'Uji Markdown dan Tautan',
        'blocks' => [
            ['id' => 'p1', 'type' => 'paragraph', 'text' => 'Teks awal paragraf.', 'runs' => []],
        ],
        'word_count' => 3,
    ]);

    // 1. Model & Parser Markdown round-trip testing
    $model = app(\App\Domain\Publication\NaskahRichModel::class);
    $parser = app(\App\Domain\Publication\NaskahBlockParser::class);

    $markdownInput = "Berikut adalah dokumentasi:\n\n![Arsitektur Sistem](https://xishie.id/img/arch.png)\n\n---\n\nKunjungi [Repositori Resmi](https://github.com/xishie/core) untuk kode sumber.\n\n- Baca juga [Paper DOI](https://doi.org/10.1234/5678) sekarang.";

    $blocksFromModel = $model->parseMarkdownToBlocks($markdownInput);
    $blocksFromParser = $parser->parse($markdownInput);

    expect($blocksFromModel[1]['type'])->toBe('image')
        ->and($blocksFromModel[1]['image']['src'])->toBe('https://xishie.id/img/arch.png')
        ->and($blocksFromModel[1]['image']['alt'])->toBe('Arsitektur Sistem')
        ->and($blocksFromModel[2]['type'])->toBe('pagebreak')
        ->and($blocksFromModel[3]['type'])->toBe('paragraph')
        ->and($blocksFromModel[3]['text'])->toBe('Kunjungi Repositori Resmi untuk kode sumber.')
        ->and($blocksFromModel[3]['runs'][1]['text'])->toBe('Repositori Resmi')
        ->and($blocksFromModel[3]['runs'][1]['link'])->toBe('https://github.com/xishie/core')
        ->and($blocksFromModel[4]['type'])->toBe('bullet')
        ->and($blocksFromModel[4]['text'])->toBe('Baca juga Paper DOI sekarang.')
        ->and($blocksFromModel[4]['runs'][1]['link'])->toBe('https://doi.org/10.1234/5678');

    expect($blocksFromParser[1]['type'])->toBe('image')
        ->and($blocksFromParser[1]['image']['src'])->toBe('https://xishie.id/img/arch.png')
        ->and($blocksFromParser[2]['type'])->toBe('pagebreak');

    // 2. Export verification (ODT and DOCX)
    $odtExporter = app(\App\Domain\Publication\NaskahOdtExporter::class);
    $odtBinary = $odtExporter->build($blocksFromModel, 'Dokumen Uji');
    expect(strlen($odtBinary))->toBeGreaterThan(500);

    // Unzip ODT in memory to inspect content.xml
    $tempOdt = tempnam(sys_get_temp_dir(), 'odt_test_');
    file_put_contents($tempOdt, $odtBinary);
    $zip = new ZipArchive;
    $zip->open($tempOdt);
    $contentXml = $zip->getFromName('content.xml');
    $zip->close();
    @unlink($tempOdt);

    expect($contentXml)->toContain('xmlns:xlink="http://www.w3.org/1999/xlink"')
        ->and($contentXml)->toContain('<text:a xlink:type="simple" xlink:href="https://github.com/xishie/core">');

    $docxExporter = app(\App\Domain\Publication\NaskahDocxExporter::class);
    $docxBinary = $docxExporter->build($blocksFromModel, 'Dokumen Uji');
    expect(strlen($docxBinary))->toBeGreaterThan(500);

    $tempDocx = tempnam(sys_get_temp_dir(), 'docx_test_');
    file_put_contents($tempDocx, $docxBinary);
    $zipDocx = new ZipArchive;
    $zipDocx->open($tempDocx);
    $wordDocXml = $zipDocx->getFromName('word/document.xml');
    $zipDocx->close();
    @unlink($tempDocx);

    expect($wordDocXml)->toContain('w:color w:val="2563EB"');

    // 3. Component test for setLinkPreset & insertLink
    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    $component->call('setLinkPreset', 'doi');
    expect($component->get('linkUrl'))->toBe('https://doi.org/10.');

    $component->call('setLinkPreset', 'arxiv');
    expect($component->get('linkUrl'))->toBe('https://arxiv.org/abs/');

    $component->call('setLinkPreset', 'scholar');
    expect($component->get('linkUrl'))->toBe('https://scholar.google.com/scholar?q=');

    $component->call('setLinkPreset', 'github');
    expect($component->get('linkUrl'))->toBe('https://github.com/');

    $component->call('setLinkPreset', 'dataset');
    expect($component->get('linkUrl'))->toBe('https://huggingface.co/datasets/');

    // Insert Link into active block
    $component->set('activeBlock', 0)
        ->set('linkUrl', 'https://doi.org/10.5555/sample')
        ->set('linkText', 'Referensi DOI 5555')
        ->call('insertLink');

    $updatedBlocks = $component->get('blocks');
    expect($updatedBlocks[0]['text'])->toContain('Referensi DOI 5555')
        ->and(end($updatedBlocks[0]['runs'])['link'])->toBe('https://doi.org/10.5555/sample')
        ->and($component->get('linkUrl'))->toBe('')
        ->and($component->get('linkText'))->toBe('');
});

it('supports aiSummarizeBlock, aiKeyPointsBlock, link dialog aliases, and model preset aliases', function () {
    $user = User::factory()->create();
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'title' => 'Uji Wordcraft Summarize dan Key Points',
        'blocks' => [
            ['id' => 'p1', 'type' => 'paragraph', 'text' => 'Model deep learning ini dilatih menggunakan dataset multimodal berskala besar. Evaluasi performa menunjukkan akurasi yang lebih tinggi dibanding arsitektur transformer tradisional.', 'runs' => []],
        ],
        'word_count' => 20,
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    // 1. Test dialog aliases
    $component->call('openDialog', 'tautan');
    expect($component->get('dialog'))->toBe('link');

    $component->call('openDialog', 'hyperlink');
    expect($component->get('dialog'))->toBe('link');

    // 2. Test aiSummarizeBlock
    $component->set('activeBlock', 0)
        ->call('aiSummarizeBlock', 0);

    $blocksAfterSum = $component->get('blocks');
    expect($blocksAfterSum[0]['text'])->not->toBeEmpty()
        ->and($component->get('docWordCount'))->toBeGreaterThan(0)
        ->and($component->get('editorRevision'))->toBeGreaterThan(0);

    // 3. Test aiKeyPointsBlock
    $component->set('activeBlock', 0)
        ->call('aiKeyPointsBlock', 0);

    $blocksAfterKP = $component->get('blocks');
    expect(count($blocksAfterKP))->toBeGreaterThan(1)
        ->and($blocksAfterKP[1]['type'])->toBe('bullet');

    // 4. Test executeModelPreset with alias 'kebaruan'
    $component->call('executeModelPreset', 'kebaruan');
    expect($component->get('isReviewingModelOutput'))->toBeTrue()
        ->and($component->get('docWordCount'))->toBeGreaterThan(0);

    $component->call('acceptModelOutput');
    expect($component->get('isReviewingModelOutput'))->toBeFalse()
        ->and($component->get('docWordCount'))->toBeGreaterThan(0);

    // 5. Test command execution
    $component->call('executeCommand', 'ai.key_points');
    expect(count($component->get('blocks')))->toBeGreaterThan(2);
});

it('supports rich latex exports with href and grouped list environments, and inline link parsing in parser', function () {
    $parser = app(\App\Domain\Publication\NaskahBlockParser::class);
    $model = app(\App\Domain\Publication\NaskahRichModel::class);

    $rawMarkdown = "Paragraf dengan [Tautan Web](https://xishie.id) penting.\n\n> Kutipan dengan [Rujukan](https://doi.org/10.1234/5678).\n\n- Butir satu dengan [Kode](https://github.com/org/repo)\n- Butir dua biasa\n\n1. Langkah pertama\n2. Langkah kedua dengan [Dataset](https://huggingface.co/datasets/sample)";

    $parsedBlocks = $parser->parse($rawMarkdown);

    expect($parsedBlocks[0]['type'])->toBe('paragraph')
        ->and($parsedBlocks[0]['text'])->toBe('Paragraf dengan Tautan Web penting.')
        ->and($parsedBlocks[0]['runs'][1]['text'])->toBe('Tautan Web')
        ->and($parsedBlocks[0]['runs'][1]['link'])->toBe('https://xishie.id')
        ->and($parsedBlocks[1]['type'])->toBe('quote')
        ->and($parsedBlocks[1]['runs'][1]['link'])->toBe('https://doi.org/10.1234/5678')
        ->and($parsedBlocks[2]['type'])->toBe('bullet')
        ->and($parsedBlocks[2]['runs'][1]['link'])->toBe('https://github.com/org/repo')
        ->and($parsedBlocks[5]['type'])->toBe('numbered')
        ->and($parsedBlocks[5]['runs'][1]['link'])->toBe('https://huggingface.co/datasets/sample');

    // LaTeX export testing
    $latex = $model->toLatex($parsedBlocks, 'Naskah Uji LaTeX');

    expect($latex)->toContain('\href{https://xishie.id}{Tautan Web}')
        ->and($latex)->toContain('\begin{itemize}')
        ->and($latex)->toContain('\end{itemize}')
        ->and($latex)->toContain('\begin{enumerate}')
        ->and($latex)->toContain('\end{enumerate}')
        ->and(substr_count($latex, '\begin{itemize}'))->toBe(1)
        ->and(substr_count($latex, '\begin{enumerate}'))->toBe(1);

    // Cross reference runs integrity
    $user = User::factory()->create();
    $doc = NaskahDocument::factory()->create([
        'user_id' => $user->id,
        'title' => 'Uji Crossref',
        'blocks' => [
            ['id' => 'p1', 'type' => 'paragraph', 'text' => 'Berdasarkan uraian pada', 'runs' => [
                ['text' => 'Berdasarkan uraian pada', 'bold' => true, 'italic' => false, 'underline' => false, 'strike' => false, 'sub' => false, 'sup' => false, 'code' => false, 'smallCaps' => false, 'allCaps' => false, 'color' => null, 'highlight' => null, 'link' => null],
            ]],
            ['id' => 't1', 'type' => 'table', 'table' => ['rows' => [[['text' => 'Col 1']]], 'caption' => 'Tabel 1']],
        ],
        'word_count' => 5,
    ]);

    $component = Livewire::actingAs($user)
        ->test(NaskahStudioPage::class, ['document' => $doc->id]);

    $component->set('activeBlock', 0)
        ->call('insertCrossReference', 'table', 'Tabel 1');

    $blocks = $component->get('blocks');
    expect($blocks[0]['text'])->toBe('Berdasarkan uraian pada Tabel 1')
        ->and($blocks[0]['runs'][0]['text'])->toBe('Berdasarkan uraian pada')
        ->and($blocks[0]['runs'][0]['bold'])->toBeTrue()
        ->and($blocks[0]['runs'][1]['text'])->toBe(' Tabel 1');
});

it('supports nested list indentation parsing, markdown table cell runs, rich html callouts and rtf styled runs', function () {
    $parser = app(\App\Domain\Publication\NaskahBlockParser::class);
    $model = app(\App\Domain\Publication\NaskahRichModel::class);

    $rawMarkdown = "- Butir Induk\n  - Sub Butir Level 1\n    - Sub Butir Level 2\n\n1. Langkah Induk\n  1. Sub Langkah 1\n\n| **Metode** | [Tautan Dokumentasi](https://xishie.id/docs) |\n|---|---|\n| *CNN* | [Akurasi 98%](https://arxiv.org/abs/1234) |\n\n> [!NOTE] Perhatikan **parameter** kunci ini.\n\n[^1]: Penjelasan *detail* sitasi [web](https://xishie.id).";

    $parsed = $parser->parse($rawMarkdown);

    // 1. Verify parser nested listLevel
    expect($parsed[0]['type'])->toBe('bullet')
        ->and($parsed[0]['listLevel'] ?? 0)->toBe(0)
        ->and($parsed[1]['type'])->toBe('bullet')
        ->and($parsed[1]['listLevel'] ?? 0)->toBe(1)
        ->and($parsed[2]['type'])->toBe('bullet')
        ->and($parsed[2]['listLevel'] ?? 0)->toBe(2)
        ->and($parsed[3]['type'])->toBe('numbered')
        ->and($parsed[3]['listLevel'] ?? 0)->toBe(0)
        ->and($parsed[4]['type'])->toBe('numbered')
        ->and($parsed[4]['listLevel'] ?? 0)->toBe(1);

    // 2. Verify table cell runs parsing
    $tableBlock = $parsed[5];
    expect($tableBlock['type'])->toBe('table')
        ->and($tableBlock['table']['rows'][0][0]['runs'][0]['bold'])->toBeTrue()
        ->and($tableBlock['table']['rows'][0][1]['runs'][0]['link'])->toBe('https://xishie.id/docs')
        ->and($tableBlock['table']['rows'][1][0]['runs'][0]['italic'])->toBeTrue()
        ->and($tableBlock['table']['rows'][1][1]['runs'][0]['link'])->toBe('https://arxiv.org/abs/1234');

    // 3. Verify callout and footnote-ref runs parsing
    $calloutBlock = $parsed[6];
    expect($calloutBlock['type'])->toBe('callout')
        ->and($calloutBlock['runs'][1]['bold'])->toBeTrue()
        ->and($calloutBlock['runs'][1]['text'])->toBe('parameter');

    $footnoteBlock = $parsed[7];
    expect($footnoteBlock['type'])->toBe('footnote-ref')
        ->and($footnoteBlock['runs'][1]['italic'])->toBeTrue()
        ->and($footnoteBlock['runs'][3]['link'])->toBe('https://xishie.id');

    // 4. Verify toMarkdown with nested listLevel and table cell runs
    $exportedMd = $model->toMarkdown($parsed);
    expect($exportedMd)->toContain('  - Sub Butir Level 1')
        ->and($exportedMd)->toContain('    - Sub Butir Level 2')
        ->and($exportedMd)->toContain('  1. Sub Langkah 1')
        ->and($exportedMd)->toContain('| **Metode** | [Tautan Dokumentasi](https://xishie.id/docs) |')
        ->and($exportedMd)->toContain('| *CNN* | [Akurasi 98%](https://arxiv.org/abs/1234) |');

    // 5. Verify toHtml callout runs and table cell styling
    $html = $model->toHtml($parsed, 'Uji HTML Kaya');
    expect($html)->toContain('<strong>parameter</strong>')
        ->and($html)->toContain('<em>detail</em>')
        ->and($html)->toContain('<a href="https://xishie.id"')
        ->and($html)->toContain('<a href="https://xishie.id/docs"');

    // 6. Verify toRtf formatted runs
    $rtf = $model->toRtf($parsed, 'Uji RTF Kaya');
    expect($rtf)->toContain('\b parameter\b0')
        ->and($rtf)->toContain('\i detail\i0')
        ->and($rtf)->toContain('HYPERLINK "https://xishie.id/docs"')
        ->and($rtf)->toContain('\li720');
});
