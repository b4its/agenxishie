<?php

declare(strict_types=1);

namespace Tests\Feature;

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
        ->assertSee('18 UX KPIs')
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
