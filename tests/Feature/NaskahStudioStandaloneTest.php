<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Workspace\Enums\UxKpiCategory;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Livewire\NaskahStudioApp;
use App\Models\NaskahDocument;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use Livewire\Livewire;

it('renders the standalone naskah studio page outside of admin panel', function (): void {
    $response = $this->get('/studio');

    $response->assertOk()
        ->assertSee('NASKAH STUDIO')
        ->assertSee('Xishie Assistant')
        ->assertSee('Waktu Sesi')
        ->assertSee('Kepatuhan');
});

it('redirects /naskah-studio alias to /studio', function (): void {
    $response = $this->get('/naskah-studio');

    $response->assertRedirect('/studio');
});

it('computes all eighteen ux kpis across heart, aarrr, and usability categories in standalone studio', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    $kpis = $page->get('uxKpis');

    expect($kpis)->toBeArray()
        ->and(count($kpis))->toBe(18);

    foreach (UxKpiMetric::cases() as $metric) {
        expect($kpis)->toHaveKey($metric->value);
        $entry = $kpis[$metric->value];
        expect($entry)->toHaveKeys(['label', 'category', 'framework', 'value', 'unit', 'target', 'healthy', 'description']);
    }

    // Periksa pembagian kerangka HEART, AARRR, dan UX
    $heartMetrics = array_filter($kpis, fn ($m): bool => $m['framework'] === 'HEART');
    $aarrrMetrics = array_filter($kpis, fn ($m): bool => $m['framework'] === 'AARRR');
    $uxMetrics = array_filter($kpis, fn ($m): bool => $m['framework'] === 'UX');

    expect(count($heartMetrics))->toBeGreaterThan(0)
        ->and(count($aarrrMetrics))->toBeGreaterThan(0)
        ->and(count($uxMetrics))->toBeGreaterThan(0);
});

it('evaluates guideline compliance score and rule checklist in real time', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('docTitle', 'Analisis Pengaruh Metrik UX Terhadap Kepuasan Penulis Naskah')
        ->set('blocks', [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan dan Latar Belakang'],
            ['type' => 'paragraph', 'text' => 'Penelitian ini menggunakan metode kuantitatif terukur.'],
            ['type' => 'heading', 'level' => 2, 'text' => 'Hasil dan Pembahasan Riset'],
            ['type' => 'paragraph', 'text' => 'Kesimpulan akhir menunjukkan signifikansi korelasi.'],
            ['type' => 'paragraph', 'text' => 'Daftar Pustaka: Smith (2024).'],
        ]);

    $audit = $page->get('complianceAudit');

    expect($audit['score'])->toBeGreaterThanOrEqual(80.0)
        ->and($audit['grade'])->toContain('A')
        ->and($audit['rules'])->toBeArray();
});

it('records interactive csat rating into ux kpi telemetry', function (): void {
    $project = XishieProject::factory()->create();

    $page = Livewire::test(NaskahStudioApp::class)
        ->set('sourceProjectId', $project->id)
        ->call('rateCsat', 5, 'Sangat intuitif dan membantu penulisan naskah');

    $page->assertSet('csatScore', 5)
        ->assertSet('csatSubmitted', true);

    expect(UxKpiTelemetry::query()
        ->where('metric_key', UxKpiMetric::USER_SATISFACTION_SCORE)
        ->where('metric_category', UxKpiCategory::HAPPINESS)
        ->where('metric_value', 5)
        ->exists()
    )->toBeTrue();
});

it('toggles zen focus mode and kpi drawer', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    expect($page->get('zenMode'))->toBeFalse();
    $page->call('toggleZenMode');
    expect($page->get('zenMode'))->toBeTrue();

    expect($page->get('kpiDrawerOpen'))->toBeFalse();
    $page->call('toggleKpiDrawer', 'AARRR');
    expect($page->get('kpiDrawerOpen'))->toBeTrue()
        ->and($page->get('kpiActiveFramework'))->toBe('AARRR');
});

it('runs typographical auto-correct and table formulas outside admin', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Simbol -> panah dan (c) hak cipta serta 1/2...'],
            [
                'type' => 'table',
                'table' => [
                    'header' => true,
                    'rows' => [
                        [['text' => 'Metrik', 'runs' => []], ['text' => 'Nilai', 'runs' => []]],
                        [['text' => 'A', 'runs' => []], ['text' => '100', 'runs' => []]],
                        [['text' => 'B', 'runs' => []], ['text' => '200', 'runs' => []]],
                    ],
                ],
            ],
        ]);

    $page->call('runAutoCorrect');
    $text = $page->get('blocks')[0]['text'];
    expect($text)->toContain('→')
        ->and($text)->toContain('©');

    $page->call('tableSumColumn', 1, 1);
    $rows = $page->get('blocks')[1]['table']['rows'];
    expect(end($rows)[1]['text'])->toBe('300');
});

it('creates, restores and deletes version snapshots in standalone studio', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Versi 1 Asli'],
        ])
        ->call('createSnapshot', 'Draf Awal');

    expect($page->get('snapshots'))->toHaveCount(1);

    $page->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Teks Diedit Banyak'],
    ]);

    $page->call('restoreSnapshot', 0);
    expect($page->get('blocks')[0]['text'])->toBe('Versi 1 Asli');

    $page->call('deleteSnapshot', 0);
    expect($page->get('snapshots'))->toHaveCount(0);
});

it('streams direct document export files without admin login', function (): void {
    $document = NaskahDocument::factory()->create([
        'title' => 'Naskah Penelitian Terbuka',
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Pendahuluan Riset'],
            ['type' => 'paragraph', 'text' => 'Ini adalah naskah lengkap yang diuji.'],
        ],
    ]);

    $formats = ['docx', 'odt', 'latex', 'rtf', 'md', 'txt', 'html'];

    foreach ($formats as $fmt) {
        $res = $this->get("/studio/export/{$document->id}/{$fmt}");
        $res->assertOk();
    }
});

it('supports opening and switching dialogs in standalone studio', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    $dialogs = ['find', 'stats', 'pagesetup', 'spelling', 'thesaurus', 'table', 'image', 'link', 'footnote', 'symbols', 'merge', 'comment', 'palette', 'properties', 'snapshots', 'shortcuts'];

    foreach ($dialogs as $dlg) {
        $page->call('openDialog', $dlg)
            ->assertSet('dialog', $dlg);
    }

    $page->call('closeDialog')
        ->assertSet('dialog', '');
});

it('supports comments and revisions lifecycle outside admin', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('commentDraft', 'Perbaiki metodologi bab 2')
        ->call('addComment');

    expect($page->get('comments'))->toHaveCount(1);
    expect($page->get('comments')[0]['body'])->toBe('Perbaiki metodologi bab 2');

    $page->call('resolveComment', 0);
    expect($page->get('comments')[0]['resolved'])->toBeTrue();

    $page->call('deleteComment', 0);
    expect($page->get('comments'))->toHaveCount(0);

    // Revisions
    $page->set('revisions', [
        [
            'author' => 'Reviewer 1',
            'blockIndex' => 0,
            'before' => 'Draf lama',
            'after' => 'Draf baru yang lebih baik',
            'status' => 'pending',
            'timestamp' => now()->toIso8601String(),
        ],
    ]);

    $page->call('acceptRevision', 0);
    expect($page->get('revisions')[0]['status'])->toBe('accepted');
});

it('executes wordcraft in-block actions in standalone studio', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Pernyataan singkat.'],
        ]);

    $page->call('aiElaborateBlock', 0);
    expect(mb_strlen($page->get('blocks')[0]['text']))->toBeGreaterThan(15);

    $page->call('aiFixTone', 0, 'akademik');
    expect($page->get('blocks')[0]['text'])->not->toBeEmpty();
});

it('renders the standalone 18 ux kpis analytics dashboard outside admin panel', function (): void {
    $project = XishieProject::factory()->create([
        'title' => 'Riset Evaluasi Usabilitas Interaktif',
        'document_type' => 'journal',
    ]);

    $response = $this->get('/analytics');

    $response->assertOk()
        ->assertSee('18 UX &amp; PRODUCT METRICS DASHBOARD', false)
        ->assertSee('GOOGLE HEART HEALTH')
        ->assertSee('AARRR PIRATE FUNNEL')
        ->assertSee('USABILITY SPECIFIC METRICS')
        ->assertSee('Understanding Metrics')
        ->assertSee($project->title);
});

it('redirects /ux-analytics alias to /analytics', function (): void {
    $response = $this->get('/ux-analytics');

    $response->assertRedirect('/analytics');
});

it('renders microsoft word layout with autosave, ribbon groups and navigation pane', function (): void {
    $response = $this->get('/studio');

    $response->assertOk()
        ->assertSee('AutoSave')
        ->assertSee('The Open Studio Handbook')
        ->assertSee('Navigation')
        ->assertSee('Headings')
        ->assertSee('Why share a studio?')
        ->assertSee('What we agree on')
        ->assertSee('Accessibility: Good to go');
});

it('supports microsoft word navigation tabs, editing modes and autosave toggling', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    expect($page->get('autoSave'))->toBeTrue();
    $page->call('toggleAutoSave');
    expect($page->get('autoSave'))->toBeFalse();

    $page->call('setNavigationTab', 'pages');
    expect($page->get('navigationTab'))->toBe('pages');

    $page->call('setEditingMode', 'reviewing');
    expect($page->get('editingMode'))->toBe('reviewing');

    $page->call('loadSampleHandbook');
    expect($page->get('docTitle'))->toBe('The Open Studio Handbook')
        ->and(count($page->get('blocks')))->toBeGreaterThanOrEqual(10);
});

it('supports all eleven ribbon tabs and their dedicated operations', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    $tabs = ['file', 'home', 'insert', 'draw', 'design', 'layout', 'references', 'mailings', 'review', 'view', 'help'];

    foreach ($tabs as $tab) {
        $page->set('activeTab', $tab)
            ->assertSet('activeTab', $tab);
    }

    // Tab Design & Layout operations
    $page->call('applyTheme', 'modern')
        ->assertSet('styleMeta.theme', 'modern');

    $page->call('updatePageSetup', 'orientation', 'landscape');
    expect($page->get('pageSetup')['orientation'])->toBe('landscape');

    // Tab View zoom operations
    $page->call('setZoomLevel', '125');
    expect($page->get('zoom'))->toBe('125');

    $page->call('setZoom', '100');
    expect($page->get('zoom'))->toBe('100');

    $page->call('zoomIn');
    expect($page->get('zoom'))->toBe('125');

    $page->call('zoomOut');
    expect($page->get('zoom'))->toBe('100');
});

it('handles live inline block updates and table cell editing', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Teks awal'],
            [
                'type' => 'table',
                'table' => [
                    'header' => true,
                    'rows' => [
                        [['text' => 'Header 1', 'runs' => []], ['text' => 'Header 2', 'runs' => []]],
                        [['text' => 'Sel 1', 'runs' => []], ['text' => 'Sel 2', 'runs' => []]],
                    ],
                ],
            ],
        ]);

    $page->call('updateBlockText', 0, 'Teks telah diedit secara langsung');
    expect($page->get('blocks')[0]['text'])->toBe('Teks telah diedit secara langsung');

    $page->call('updateTableCell', 1, 1, 1, 'Nilai Sel Baru');
    expect($page->get('blocks')[1]['table']['rows'][1][1]['text'])->toBe('Nilai Sel Baru');
});

it('renders all six ux guide resource links in the telemetry drawer', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('toggleKpiDrawer', 'RESOURCES');

    $page->assertSee('lnkd.in/dWGVK2Tp')
        ->assertSee('lnkd.in/dfbfSg8T')
        ->assertSee('lnkd.in/dAS5bdx5')
        ->assertSee('lnkd.in/dvA_si5X')
        ->assertSee('lnkd.in/dSNEHVRx')
        ->assertSee('lnkd.in/dsMA7AFH');
});

it('supports enter to insert paragraph and backspace to delete empty blocks', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    $initialCount = count($page->get('blocks'));
    $page->call('insertParagraphAfter', 0);

    expect(count($page->get('blocks')))->toBe($initialCount + 1)
        ->and($page->get('activeBlock'))->toBe(1)
        ->and($page->get('blocks')[1]['type'])->toBe('paragraph')
        ->and($page->get('blocks')[1]['text'])->toBe('');

    $page->call('removeBlockAndFocusPrev', 1);

    expect(count($page->get('blocks')))->toBe($initialCount)
        ->and($page->get('activeBlock'))->toBe(0);
});

it('loads authentic microsoft word sample handbook with complete sections on both pages', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    $blocks = $page->get('blocks');

    // Pastikan tidak ada heading kosong yang berdiri sendiri seperti to-do
    foreach ($blocks as $i => $block) {
        if ($block['type'] === 'heading') {
            // Heading harus memiliki teks
            expect($block['text'])->not->toBeEmpty();
            // Blok berikutnya (jika bukan heading terakhir) tidak boleh heading kosong
            if (isset($blocks[$i + 1]) && $blocks[$i + 1]['type'] !== 'pagebreak') {
                expect($blocks[$i + 1]['text'])->not->toBeEmpty();
            }
        }
    }

    $outline = $page->instance()->outline();
    $outlineTexts = array_column($outline, 'text');

    expect($outlineTexts)->toContain('Why share a studio?')
        ->and($outlineTexts)->toContain('What we agree on')
        ->and($outlineTexts)->toContain('Shared equipment')
        ->and($outlineTexts)->toContain('Opening the studio')
        ->and($outlineTexts)->toContain('Membership')
        ->and($outlineTexts)->toContain('Daftar Pustaka');

    // Uji pemilihan gaya sitasi akademik (APA, IEEE, dsb.) dan penyisipan kutipan acuan naskah
    $page->call('setCitationStyle', 'IEEE');
    expect($page->get('citationStyle'))->toBe('IEEE');

    $page->call('selectBlock', 2);
    $page->call('insertCitation');
    $currentBlocks = $page->get('blocks');
    expect($currentBlocks[2]['text'])->toContain('[1]');
});

it('paginates manuscript into separate physical sheets with authentic academic page numbers like Word and PDF viewer', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    $paginated = $page->instance()->getPaginatedBlocks();

    // Pastikan terpecah menjadi minimal 2 lembar halaman terpisah (tidak meluap keluar layout)
    expect(count($paginated))->toBeGreaterThanOrEqual(2);

    // Halaman 1 (Awal BAB): nomor halaman 1, isFirstPage true
    expect($paginated[0]['pageNumber'])->toBe(1)
        ->and($paginated[0]['isFirstPage'])->toBeTrue();

    // Halaman 2 (BAB Selanjutnya): nomor halaman 2, isFirstPage false
    expect($paginated[1]['pageNumber'])->toBe(2)
        ->and($paginated[1]['isFirstPage'])->toBeFalse();

    // Periksa bahwa tampilan merender lembar halaman terpisah dengan ID word-page-1 dan word-page-2
    $page->assertSee('id="word-page-1"', false)
        ->assertSee('id="word-page-2"', false)
        ->assertSee('class="word-sheet-page-bottom"', false)
        ->assertSee('class="word-sheet-page-top"', false);

    // Uji dokumen baru berukuran 1 halaman: jika hanya sedikit konten, tampilkan HANYA 1 halaman
    $page->call('newDocument');
    $paginatedSingle = $page->instance()->getPaginatedBlocks();
    expect(count($paginatedSingle))->toBe(1)
        ->and($paginatedSingle[0]['pageNumber'])->toBe(1);

    $page->assertSee('Page 1 of 1', false)
        ->assertDontSee('id="word-page-2"', false);

    // Uji penambahan konten teks panjang melebihi kapasitas halaman: halaman berkembang dinamis tanpa batas
    for ($i = 0; $i < 60; $i++) {
        $page->call('insertParagraphAfter', 0);
        $page->call('updateBlockText', 1, 'Paragraf naskah yang berisi teks deskripsi analisis untuk menguji pembagian halaman dinamis Microsoft Word yang berkembang luas tanpa batasan dua halaman.');
    }
    $paginatedMulti = $page->instance()->getPaginatedBlocks();
    expect(count($paginatedMulti))->toBeGreaterThanOrEqual(4);

    // Uji sinkronisasi HTML antar lembar: tidak boleh menyuntikkan pagebreak redundan secara sembarangan
    $page->call('syncManuscriptPages', [
        '<p>Paragraf di lembar pertama</p>',
        '<p>Paragraf di lembar kedua</p>',
    ]);
    $blocksAfterSync = $page->get('blocks');
    $pagebreakCount = count(array_filter($blocksAfterSync, fn ($b): bool => ($b['type'] ?? '') === 'pagebreak'));
    expect($pagebreakCount)->toBe(0);

    // Uji penyusutan kembali ke 1 halaman jika konten dihapus atau reset
    $page->call('newDocument');
    $paginatedReset = $page->instance()->getPaginatedBlocks();
    expect(count($paginatedReset))->toBe(1);
});

it('persists typed and edited text seamlessly without text loss or hardcoded string overrides', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    $initialRev = $page->get('editorRevision');

    // 1. Uji pengeditan paragraf: teks baru harus tersimpan persis dan tidak ditimpa teks bawaan template
    $page->call('syncManuscriptPages', [
        '<h1 class="word-title-text">Judul Baru Hasil Editan Penulis</h1>'.
        '<p class="word-paragraph-text">Teks paragraf baru yang diketik oleh pengguna, tidak boleh hilang atau berbalik ke handbook bawaan.</p>',
    ]);

    $blocks = $page->get('blocks');
    expect($blocks[0]['text'])->toBe('Judul Baru Hasil Editan Penulis')
        ->and($blocks[1]['text'])->toBe('Teks paragraf baru yang diketik oleh pengguna, tidak boleh hilang atau berbalik ke handbook bawaan.');

    // syncManuscriptPages tidak boleh menaikkan editorRevision agar Livewire tidak menimpa DOM contenteditable saat pengguna mengetik
    expect($page->get('editorRevision'))->toBe($initialRev);

    // Pastikan teks hasil editan terlihat di HTML yang dirender
    $page->assertSee('Judul Baru Hasil Editan Penulis', false)
        ->assertSee('Teks paragraf baru yang diketik oleh pengguna', false);

    // 2. Uji teks format kaya (bold, italic, tags) dipertahankan tanpa hilang
    $page->call('syncManuscriptPages', [
        '<p class="word-paragraph-text">Paragraf dengan kata <strong>tebal</strong> dan <em>miring</em> serta catatan.</p>',
    ]);
    $richBlocks = $page->get('blocks');
    expect($richBlocks[0]['text'])->toContain('<strong>tebal</strong>')
        ->and($richBlocks[0]['text'])->toContain('<em>miring</em>');

    // 3. Uji teks polos langsung tanpa tag p tetap tersimpan sebagai paragraf
    $page->call('syncManuscriptPages', [
        'Teks polos langsung diketik tanpa tag pembungkus HTML',
    ]);
    $rawBlocks = $page->get('blocks');
    expect(count($rawBlocks))->toBeGreaterThanOrEqual(1)
        ->and($rawBlocks[0]['text'])->toBe('Teks polos langsung diketik tanpa tag pembungkus HTML');

    // 4. Tindakan struktural (seperti newDocument, loadSampleHandbook, applyStyle) menaikkan editorRevision
    $page->call('newDocument');
    expect($page->get('editorRevision'))->toBeGreaterThan($initialRev);
});

it('prevents manuscript word and sentence loss on save and empty blur syncs', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    $originalBlockCount = count($page->get('blocks'));
    $originalWords = $page->get('statistics')['words'] ?? 0;
    expect($originalBlockCount)->toBeGreaterThan(0)
        ->and($originalWords)->toBeGreaterThan(0);

    // 1. Uji pemanggilan syncManuscriptPages([]) dengan array kosong (mis. blur/querySelector kosong)
    // JANGAN PERNAH menghapus kalimat atau kata yang sudah ada
    $page->call('syncManuscriptPages', []);
    expect(count($page->get('blocks')))->toBe($originalBlockCount)
        ->and(count($page->get('blocks')))->toBeGreaterThan(1);

    // 2. Uji atomic save dengan page HTML langsung dari editor klien
    $myContent = [
        '<h1 class="word-title-text">Naskah Penelitian Ilmiah AI</h1>'.
        '<p class="word-paragraph-text">Seluruh kalimat dan kata dalam naskah ini harus tersimpan utuh dan tidak boleh hilang sedikitpun saat disimpan.</p>',
    ];

    $page->call('save', $myContent);

    // Pastikan blocks tersinkronisasi dan tersimpan di database
    $blocksAfterSave = $page->get('blocks');
    expect($blocksAfterSave[0]['text'])->toBe('Naskah Penelitian Ilmiah AI')
        ->and($blocksAfterSave[1]['text'])->toContain('Seluruh kalimat dan kata dalam naskah ini harus tersimpan utuh')
        ->and($page->get('dirty'))->toBeFalse();

    $docId = $page->get('documentId');
    expect($docId)->not->toBeNull();

    $savedDoc = NaskahDocument::query()->find($docId);
    expect($savedDoc)->not->toBeNull()
        ->and($savedDoc->title)->toBe('Naskah Penelitian Ilmiah AI')
        ->and($savedDoc->word_count)->toBeGreaterThan(10)
        ->and($savedDoc->blocks[0]['text'])->toBe('Naskah Penelitian Ilmiah AI')
        ->and($savedDoc->blocks[1]['text'])->toContain('Seluruh kalimat dan kata dalam naskah ini harus tersimpan utuh');

    // 3. Uji pemanggilan save([]) dengan array kosong tidak mengosongkan naskah
    $page->call('save', []);
    $savedDocAgain = NaskahDocument::query()->find($docId);
    expect($savedDocAgain->word_count)->toBeGreaterThan(10);

    // 4. Uji kutipan dan tabel dipertahankan saat di-parse
    $page->call('save', [
        '<blockquote class="word-quote-block"><p class="word-quote-text">Kutipan bernas tanpa nesting p ganda.</p></blockquote>'.
        '<table><tr><th>Kolom 1</th><th>Kolom 2</th></tr><tr><td>Data A</td><td>Data B</td></tr></table>',
    ]);
    $richParsed = $page->get('blocks');
    expect($richParsed[0]['type'])->toBe('quote')
        ->and($richParsed[0]['text'])->toBe('Kutipan bernas tanpa nesting p ganda.')
        ->and($richParsed[1]['type'])->toBe('table')
        ->and(count($richParsed[1]['table']['rows']))->toBe(2);
});

it('manages citation sources and generates dynamic academic bibliography and toc', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    // Buka dialog Source Manager
    $page->call('openDialog', 'sources')
        ->assertSee('Source Manager &amp; Citations', false)
        ->assertSee('Anderson, C.', false);

    // Sisipkan sitasi spesifik
    $page->call('insertCitation', '(Anderson, 2020)');
    $blocks = $page->get('blocks');
    expect(json_encode($blocks))->toContain('(Anderson, 2020)');

    // Sisipkan daftar isi otomatis
    $page->call('insertTableOfContents');
    $blocksWithToc = $page->get('blocks');
    expect(array_column($blocksWithToc, 'type'))->toContain('toc');

    // Sisipkan daftar pustaka akademik
    $page->call('insertBibliography');
    $blocksWithBib = $page->get('blocks');
    $hasBibHeading = false;
    foreach ($blocksWithBib as $b) {
        if (($b['type'] ?? '') === 'heading' && str_contains($b['text'] ?? '', 'Daftar Pustaka')) {
            $hasBibHeading = true;
            break;
        }
    }
    expect($hasBibHeading)->toBeTrue();
});

it('supports share dialog, academic margin presets, and dynamic line spacing', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    // 1. Uji Share Dialog via openDialog dan Command Palette
    $page->call('openDialog', 'share')
        ->assertSee('Share — The Open Studio Handbook')
        ->assertSee('Tautan Bagikan Dokumen')
        ->assertSee('DOCX')
        ->assertSee('PDF')
        ->assertSee('LaTeX');

    $page->call('closeDialog');
    expect($page->get('dialog'))->toBe('');

    $page->call('executeCommand', 'file.share');
    expect($page->get('dialog'))->toBe('share');

    // 2. Uji Margin Presets (Standar DIKTI 4-4-3-3 cm dan Normal 1 inch)
    $page->call('setMarginsPreset', 'dikti');
    $setup = $page->get('pageSetup');
    expect((float) $setup['marginTop'])->toBe(40.0)
        ->and((float) $setup['marginRight'])->toBe(30.0)
        ->and((float) $setup['marginBottom'])->toBe(30.0)
        ->and((float) $setup['marginLeft'])->toBe(40.0);

    $page->call('setMarginsPreset', 'normal');
    $setupNormal = $page->get('pageSetup');
    expect((float) $setupNormal['marginTop'])->toBe(25.4)
        ->and((float) $setupNormal['marginRight'])->toBe(25.4);

    // 3. Uji Line Spacing akademik
    $page->call('setLineSpacing', '1.5');
    expect((float) $page->get('pageSetup')['lineSpacing'])->toBe(1.5);

    $page->call('setLineSpacing', '2.0');
    expect((float) $page->get('pageSetup')['lineSpacing'])->toBe(2.0);

    // 4. Uji Font Family typography
    $page->set('fontFamily', 'times');
    expect($page->get('fontFamily'))->toBe('times');
});

it('supports inserting, editing and rendering LaTeX equations with presets and shortcuts', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    // 1. Uji pembukaan dialog equation
    $page->call('openDialog', 'equation')
        ->assertSee('Insert Equation (Rumus Matematika / LaTeX)', false)
        ->assertSee('Pilih Templat Cepat (Presets)', false)
        ->assertSee('Rumus ABC', false)
        ->assertSee('Pythagoras', false)
        ->assertSee('Distribusi Normal', false);

    // 2. Uji pemilihan preset rumus (misal: Pythagoras)
    $page->call('setEquationPreset', 'pythagoras');
    expect($page->get('equationLatex'))->toBe('a^2 + b^2 = c^2')
        ->and($page->get('equationCaption'))->toBe('(1)');

    // 3. Uji penyisipan rumus ke dalam dokumen
    $page->call('insertEquation');
    $blocks = $page->get('blocks');
    $foundEquation = false;
    $eqIndex = null;
    foreach ($blocks as $idx => $b) {
        if (($b['type'] ?? '') === 'equation') {
            $foundEquation = true;
            $eqIndex = $idx;
            expect($b['equation']['latex'])->toBe('a^2 + b^2 = c^2')
                ->and($b['equation']['caption'])->toBe('(1)');
            break;
        }
    }
    expect($foundEquation)->toBeTrue()
        ->and($eqIndex)->not->toBeNull();

    // Pastikan equation dirender di kanvas HTML
    $page->assertSee('word-equation-block', false)
        ->assertSee('data-latex="a^2 + b^2 = c^2"', false)
        ->assertSee('(1)', false);

    // 4. Uji edit rumus yang sudah ada
    $page->call('editEquation', $eqIndex);
    expect($page->get('dialog'))->toBe('equation')
        ->and($page->get('editingEquationIndex'))->toBe($eqIndex)
        ->and($page->get('equationLatex'))->toBe('a^2 + b^2 = c^2');

    // Ubah rumusnya (misal: Teorema Kalkulus)
    $page->call('setEquationPreset', 'integral')
        ->set('equationCaption', '(2)')
        ->call('insertEquation');

    $updatedBlocks = $page->get('blocks');
    expect($updatedBlocks[$eqIndex]['equation']['latex'])->toContain('\\int_{a}^{b}')
        ->and($updatedBlocks[$eqIndex]['equation']['caption'])->toBe('(2)');

    // 5. Uji eksekusi shortcut / command palette insert.equation
    $page->call('executeCommand', 'insert.equation');
    expect($page->get('dialog'))->toBe('equation');
});

it('supports inserting images via URL, upload, and academic SVG diagrams with captions and alignment', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    // 1. Uji dialog picture & academic diagram
    $page->call('openDialog', 'image')
        ->assertSee('Insert Picture', false)
        ->assertSee('Academic Diagram', false)
        ->assertSee('Unggah dari Komputer', false)
        ->assertSee('Gambar dari Tautan URL', false)
        ->assertSee('Diagram Akademik Cepat', false);

    // 2. Uji penyisipan gambar via URL
    $page->call('insertImageUrl', 'https://images.unsplash.com/photo-sample.jpg', 'Gambar 1: Sampel Ilustrasi Studio', 480, 'center');
    $blocks = $page->get('blocks');
    $foundImage = false;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'image' && ($b['image']['src'] ?? '') === 'https://images.unsplash.com/photo-sample.jpg') {
            $foundImage = true;
            expect($b['image']['caption'])->toBe('Gambar 1: Sampel Ilustrasi Studio')
                ->and((int) $b['image']['width'])->toBe(480)
                ->and($b['align'])->toBe('center');
            break;
        }
    }
    expect($foundImage)->toBeTrue();

    // 3. Uji penyisipan Diagram Akademik (Flowchart & Bar Chart)
    $page->call('insertSampleDiagram', 'flowchart');
    $page->call('insertSampleDiagram', 'barchart');

    $diagramBlocks = array_values(array_filter($page->get('blocks'), static fn (array $b): bool => ($b['type'] ?? '') === 'image' && str_starts_with((string) ($b['image']['src'] ?? ''), 'data:image/svg+xml;base64,')));
    expect(count($diagramBlocks))->toBeGreaterThanOrEqual(2);

    // Pastikan elemen <figure class="word-image-figure"> ter-render di kanvas HTML
    $page->assertSee('word-image-figure', false)
        ->assertSee('Gambar 1: Alur Metodologi Penelitian', false)
        ->assertSee('Gambar 2: Perbandingan Akurasi Model terhadap Baseline', false);
});

it('parses equation and image HTML blocks without loss during syncManuscriptPages', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    $html = '<h1 class="word-title-text">Dokumen Eksperimen LaTeX & Diagram</h1>'
        .'<p class="word-paragraph-text">Berikut adalah persamaan matematika terpusat:</p>'
        .'<div class="word-equation-block" data-latex="E = mc^2" data-caption="(1)" data-display="block">'
        .'<div class="word-equation-render"><span class="latex-math-render">E = mc^2</span></div>'
        .'</div>'
        .'<figure class="word-image-figure" data-caption="Gambar 1: Diagram Sistem" data-width="480" data-align="center">'
        .'<img src="https://example.com/system.png" alt="Diagram Sistem" width="480">'
        .'<figcaption>Gambar 1: Diagram Sistem</figcaption>'
        .'</figure>'
        .'<p class="word-paragraph-text">Paragraf penutup setelah diagram.</p>';

    $page->call('syncManuscriptPages', [$html]);

    $blocks = $page->get('blocks');
    expect(count($blocks))->toBe(5)
        ->and($blocks[0]['type'])->toBe('title')
        ->and($blocks[1]['type'])->toBe('paragraph')
        ->and($blocks[2]['type'])->toBe('equation')
        ->and($blocks[2]['equation']['latex'])->toBe('E = mc^2')
        ->and($blocks[2]['equation']['caption'])->toBe('(1)')
        ->and($blocks[3]['type'])->toBe('image')
        ->and($blocks[3]['image']['src'])->toBe('https://example.com/system.png')
        ->and($blocks[3]['image']['caption'])->toBe('Gambar 1: Diagram Sistem')
        ->and((int) $blocks[3]['image']['width'])->toBe(480)
        ->and($blocks[4]['type'])->toBe('paragraph');
});

it('exports equations and images correctly to DOCX, HTML, Markdown and LaTeX', function (): void {
    $model = app(NaskahRichModel::class);
    $docxExporter = app(NaskahDocxExporter::class);

    $blocks = [
        $model->newBlock('title', 'Dokumen Uji Rumus & Gambar'),
        $model->newBlock('paragraph', 'Paragraf pengantar dokumen ilmiah.'),
        $model->newEquationBlock('f(x) = x^2 + 2x + 1', 'Persamaan 1'),
        $model->newImageBlock('https://example.com/test.png', 'Grafik Sampel', 480, 'center', 'Gambar 1: Kurva Fungsi'),
    ];

    // 1. Ekspor ke HTML
    $html = $model->toHtml($blocks, 'Dokumen Uji Rumus & Gambar');
    expect($html)->toContain('class="equation-block"')
        ->and($html)->toContain('f(x) = x^2 + 2x + 1')
        ->and($html)->toContain('Persamaan 1')
        ->and($html)->toContain('<figure')
        ->and($html)->toContain('src="https://example.com/test.png"');

    // 2. Ekspor ke Markdown
    $md = $model->toMarkdown($blocks, 'Dokumen Uji Rumus & Gambar');
    expect($md)->toContain('$$')
        ->and($md)->toContain('f(x) = x^2 + 2x + 1')
        ->and($md)->toContain('*(Persamaan 1)*')
        ->and($md)->toContain('![Gambar 1: Kurva Fungsi](https://example.com/test.png)');

    // 3. Ekspor ke LaTeX
    $latex = $model->toLatex($blocks, 'Dokumen Uji Rumus & Gambar');
    expect($latex)->toContain('\\begin{equation}')
        ->and($latex)->toContain('f(x) = x^2 + 2x + 1')
        ->and($latex)->toContain('\\end{equation}')
        ->and($latex)->toContain('\\begin{figure}')
        ->and($latex)->toContain('\\caption{Gambar 1: Kurva Fungsi}');

    // 4. Ekspor ke DOCX
    $docxBinary = $docxExporter->toDocx($blocks, 'Dokumen Uji Rumus & Gambar');
    expect(strlen($docxBinary))->toBeGreaterThan(500);

    // Cek bahwa ZIP valid berisi w:document dengan Cambria Math font
    $zip = new \ZipArchive;
    $tempFile = tempnam(sys_get_temp_dir(), 'docx_test');
    file_put_contents($tempFile, $docxBinary);
    expect($zip->open($tempFile))->toBeTrue();
    $docXml = $zip->getFromName('word/document.xml');
    expect($docXml)->toContain('Cambria Math')
        ->and($docXml)->toContain('f(x) = x^2 + 2x + 1')
        ->and($docXml)->toContain('Persamaan 1');
    $zip->close();
    @unlink($tempFile);
});

it('supports math symbols palette, inline equation insertion, and visual image resizing controls', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    // 1. Uji penambahan simbol matematika ke rumus (appendEquationSymbol)
    $page->set('equationLatex', 'E =')
        ->call('appendEquationSymbol', 'mc^2')
        ->call('appendEquationSymbol', '\\pm')
        ->call('appendEquationSymbol', '\\Delta');
    expect($page->get('equationLatex'))->toContain('E = mc^2 \\pm \\Delta');

    // 2. Uji penyisipan rumus inline ke paragraf aktif
    $page->call('selectBlock', 1);
    $initialText = (string) ($page->get('blocks')[1]['text'] ?? '');
    $page->set('equationLatex', '\\alpha = 0.05')
        ->call('insertInlineEquation');

    $updatedBlock = $page->get('blocks')[1];
    expect($updatedBlock['text'])->toContain('$\\alpha = 0.05$');

    // 3. Uji pengubahan ukuran (setImageWidth) dan perataan (setImageAlign) gambar
    $page->call('insertImageUrl', 'https://example.com/figure.png', 'Gambar Riset', 480, 'center');
    $imgIndex = $page->get('activeBlock');
    expect($page->get('blocks')[$imgIndex]['image']['width'])->toBe(480);

    $page->call('setImageWidth', $imgIndex, 640);
    expect($page->get('blocks')[$imgIndex]['image']['width'])->toBe(640);

    $page->call('setImageAlign', $imgIndex, 'right');
    expect($page->get('blocks')[$imgIndex]['align'])->toBe('right');

    // 4. Uji diagram Venn dan Timeline riset
    $page->call('insertSampleDiagram', 'venn');
    $page->call('insertSampleDiagram', 'timeline');
    $allBlocks = $page->get('blocks');
    $vennFound = false;
    $timelineFound = false;
    foreach ($allBlocks as $b) {
        if (($b['type'] ?? '') === 'image' && str_contains((string) ($b['image']['caption'] ?? ''), 'Irisan')) {
            $vennFound = true;
        }
        if (($b['type'] ?? '') === 'image' && str_contains((string) ($b['image']['caption'] ?? ''), 'Milestone')) {
            $timelineFound = true;
        }
    }
    expect($vennFound)->toBeTrue()
        ->and($timelineFound)->toBeTrue();
});

it('supports footnote insertion at cursor position, html roundtrip sync, and docx export', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    $page->call('selectBlock', 2);
    $page->set('footnoteText', 'Penelitian ini didanai oleh Hibah Riset Kemendikbud 2026.')
        ->call('insertFootnote');

    $blocks = $page->get('blocks');
    expect($blocks[3]['type'])->toBe('footnote-ref')
        ->and($blocks[3]['text'])->toBe('Penelitian ini didanai oleh Hibah Riset Kemendikbud 2026.');

    // Uji sync html roundtrip
    $html = '<h2 class="word-heading-1">Metodologi</h2>'
        .'<p class="word-paragraph-text">Teks penjelasan metode riset.</p>'
        .'<div class="word-footnote-block">⚓ Catatan Kaki: Catatan rujukan lapangan.</div>';
    $page->call('syncManuscriptPages', [$html]);

    $syncedBlocks = $page->get('blocks');
    expect(count($syncedBlocks))->toBe(3)
        ->and($syncedBlocks[2]['type'])->toBe('footnote-ref')
        ->and($syncedBlocks[2]['text'])->toBe('Catatan rujukan lapangan.');

    // Uji DOCX export
    $docxExporter = app(NaskahDocxExporter::class);
    $docxBinary = $docxExporter->toDocx($syncedBlocks, 'Naskah dengan Catatan Kaki');
    $zip = new \ZipArchive;
    $tempFile = tempnam(sys_get_temp_dir(), 'docx_footnote_test');
    file_put_contents($tempFile, $docxBinary);
    expect($zip->open($tempFile))->toBeTrue();
    $docXml = $zip->getFromName('word/document.xml');
    expect($docXml)->toContain('[Catatan Kaki] Catatan rujukan lapangan.');
    $zip->close();
    @unlink($tempFile);
});

it('supports editing existing images, updating properties, expanded presets, and inserting cross references', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook');

    // 1. Insert image and test editing image properties
    $page->call('insertImageUrl', 'https://example.com/figure1.png', 'Gambar 1: Desain Awal', 480, 'center');
    $imgIndex = $page->get('activeBlock');

    $page->call('editImage', $imgIndex);
    expect($page->get('editingImageIndex'))->toBe($imgIndex)
        ->and($page->get('dialog'))->toBe('image')
        ->and($page->get('imageUrl'))->toBe('https://example.com/figure1.png')
        ->and($page->get('imageCaption'))->toBe('Gambar 1: Desain Awal');

    // Update image properties
    $page->set('imageCaption', 'Gambar 1: Desain Arsitektur Final')
        ->set('imageWidth', '600')
        ->set('imageAlign', 'right')
        ->call('updateImageProperties');

    $updatedBlocks = $page->get('blocks');
    expect($updatedBlocks[$imgIndex]['image']['caption'])->toBe('Gambar 1: Desain Arsitektur Final')
        ->and($updatedBlocks[$imgIndex]['image']['width'])->toBe(600)
        ->and($updatedBlocks[$imgIndex]['align'])->toBe('right')
        ->and($page->get('editingImageIndex'))->toBeNull()
        ->and($page->get('dialog'))->toBe('');

    // 2. Uji expanded equation presets
    $page->call('setEquationPreset', 'euler');
    expect($page->get('equationLatex'))->toBe('e^{i\\pi} + 1 = 0');

    $page->call('setEquationPreset', 'regression');
    expect($page->get('equationLatex'))->toContain('y = \\beta_0 + \\beta_1 x_1');

    $page->call('setEquationPreset', 'poisson');
    expect($page->get('equationLatex'))->toContain('P(X=k) = \\frac{\\lambda^k e^{-\\lambda}}{k!}');

    $page->call('setEquationPreset', 'taylor');
    expect($page->get('equationLatex'))->toContain('f(x) = \\sum_{n=0}^{\\infty}');

    // 3. Insert equation and verify captions collection
    $page->call('insertEquation', 'e^{i\\pi} + 1 = 0', '1');
    $captions = $page->instance()->getManuscriptCaptions();

    expect(count($captions['equations']))->toBeGreaterThanOrEqual(1)
        ->and(count($captions['images']))->toBeGreaterThanOrEqual(1);

    // 4. Test cross reference insertion
    $page->call('selectBlock', 0);
    $initialText = (string) ($page->get('blocks')[0]['text'] ?? '');
    $page->call('insertCrossReference', 'equation', '1');

    $block0 = $page->get('blocks')[0];
    expect($block0['text'])->toContain('persamaan (1)');

    $page->call('insertCrossReference', 'image', '1');
    $block0After = $page->get('blocks')[0];
    expect($block0After['text'])->toContain('Gambar 1');
});
