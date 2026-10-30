<?php

declare(strict_types=1);

use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\NaskahStudioPage;
use App\Models\NaskahDocument;
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
