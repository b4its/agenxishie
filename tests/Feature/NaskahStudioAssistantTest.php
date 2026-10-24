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

it('supports running assistant tools with explicit selected text and extended tools', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Teks naskah umum.'],
        ])
        ->set('activeBlock', 0);

    // 1. Run /poles with explicitly selected text
    $selectedPhrase = 'Komunitas pengrajin membagi ruang dan perkakas kerja';
    $page->call('runTool', '/poles', $selectedPhrase);
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Secara konseptual, komunitas pengrajin membagi ruang dan perkakas kerja');

    // 2. Run /matriks-literatur
    $page->call('runTool', '/matriks-literatur');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Matriks Sintesis Literatur & State of the Art (SOTA)');

    // 3. Run /cari-referensi
    $page->call('runTool', '/cari-referensi');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Rekomendasi Referensi Relevan');

    // 4. Run /kembangkan
    $page->call('runTool', '/kembangkan', 'Pengelolaan studio bersama menekan biaya operasional');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Elaborasi Pengembangan Teks Akademik');

    // 5. Run /ringkaskan
    $page->call('runTool', '/ringkaskan', 'Ruang kerja bersama menyediakan fasilitas lengkap untuk semua anggota');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Secara ringkas');

    // 6. Run /perbaiki-ejaan
    $page->call('runTool', '/perbaiki-ejaan', 'alat alat dan tehnik analisa data');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Hasil Audit Ejaan & PUEBI Baku');
});

it('provides rich academic writing guidance for freeform questions', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('assistantInput', 'Bagaimana cara menyusun abstrak naskah yang baik?')
        ->call('sendAssistant');

    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Panduan Penulisan Abstrak Naskah')
        ->and(end($log)['content'])->toContain('Latar Belakang')
        ->and(end($log)['content'])->toContain('Metodologi');
});

it('supports /rumus and /gambar slash commands with academic recommendations', function (): void {
    $page = Livewire::test(NaskahStudioApp::class);

    // 1. Uji /rumus regresi
    $page->call('runTool', '/rumus regresi');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Saran Rumus LaTeX')
        ->and(end($log)['content'])->toContain('y = \\beta_0')
        ->and($page->get('equationLatex'))->toContain('y = \\beta_0');

    // 2. Uji /rumus kuadrat
    $page->call('runTool', '/rumus kuadrat');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Rumus Kuadrat (ABC)')
        ->and($page->get('equationLatex'))->toContain('\\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}');

    // 3. Uji /gambar
    $page->call('runTool', '/gambar');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Panduan & Generator Diagram Ilmiah Naskah')
        ->and(end($log)['content'])->toContain('Flowchart')
        ->and(end($log)['content'])->toContain('Chart');
});

it('inserts or replaces manuscript blocks with rich equation blocks from assistant recommendations', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Persamaan model regresi:'],
            ['type' => 'paragraph', 'text' => 'Teks yang akan diganti.'],
        ])
        ->set('activeBlock', 0);

    // 1. Run /rumus regresi
    $page->call('runTool', '/rumus regresi');
    $log = $page->get('assistantLog');
    $lastIndex = count($log) - 1;

    // 2. Insert assistant formula as new equation block below block 0
    $page->call('insertAssistantResponse', $lastIndex);

    $blocksAfterInsert = $page->get('blocks');
    expect($blocksAfterInsert[1]['type'])->toBe('equation')
        ->and($blocksAfterInsert[1]['equation']['latex'])->toContain('y = \\beta_0')
        ->and($blocksAfterInsert[1]['equation']['caption'])->toBe('(1)');

    // 3. Replace block with assistant equation recommendation
    $page->set('activeBlock', 2);
    $page->call('runTool', '/rumus kuadrat');
    $log2 = $page->get('assistantLog');
    $lastIndex2 = count($log2) - 1;

    $page->call('replaceActiveBlockWithAssistant', $lastIndex2);

    $blocksAfterReplace = $page->get('blocks');
    expect($blocksAfterReplace[2]['type'])->toBe('equation')
        ->and($blocksAfterReplace[2]['equation']['latex'])->toContain('\\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}');
});

it('inserts assistant markdown tables and callouts into manuscript as first-class blocks', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'paragraph', 'text' => 'Teks awal.'],
        ])
        ->set('activeBlock', 0);

    // 1. Uji /matriks-literatur -> Insert response as table block
    $page->call('runTool', '/matriks-literatur');
    $log = $page->get('assistantLog');
    $lastIdx = count($log) - 1;

    $page->call('insertAssistantResponse', $lastIdx);
    $blocks = $page->get('blocks');

    // Harusnya terbentuk blok tabel terstruktur
    expect(count($blocks))->toBeGreaterThan(1);
    $hasTableBlock = false;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'table') {
            $hasTableBlock = true;
            expect($b['table']['rows'])->not->toBeEmpty();
            break;
        }
    }
    expect($hasTableBlock)->toBeTrue();

    // 2. Uji callout ilmiah
    $page->set('assistantLog', [
        [
            'role' => 'assistant',
            'content' => "> [!WARNING] Perhatikan limitasi sampel dan bias representasi pada metodologi survei.\n\nParagraf penjelasan tambahan.",
        ],
    ]);
    $page->set('activeBlock', count($blocks) - 1);
    $page->call('insertAssistantResponse', 0);

    $updatedBlocks = $page->get('blocks');
    $hasCallout = false;
    foreach ($updatedBlocks as $b) {
        if (($b['type'] ?? '') === 'callout') {
            $hasCallout = true;
            expect($b['calloutType'])->toBe('warning')
                ->and($b['text'])->toContain('Perhatikan limitasi sampel');
            break;
        }
    }
    expect($hasCallout)->toBeTrue();
});

it('supports enriched academic commands (/abstrak, /metodologi, /tabel-analisis, /terjemahkan) and follow-up inquiry', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('docTitle', 'Penerapan Algoritma Genetika Pada Optimasi Jaringan')
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Penerapan Algoritma Genetika Pada Optimasi Jaringan'],
            ['type' => 'paragraph', 'text' => 'Data empiris menunjukkan peningkatan efisiensi secara konsisten.'],
        ])
        ->set('activeBlock', 1);

    // 1. Uji /abstrak
    $page->call('runTool', '/abstrak');
    $log = $page->get('assistantLog');
    $lastMsg = end($log);
    expect($lastMsg['content'])->toContain('Draf Abstrak Akademik Terstruktur (IMRAD)')
        ->and($lastMsg['content'])->toContain('Latar Belakang')
        ->and($lastMsg['content'])->toContain('Metodologi')
        ->and($lastMsg['content'])->toContain('Hasil Utama');

    // 2. Uji /metodologi
    $page->call('runTool', '/metodologi');
    $log2 = $page->get('assistantLog');
    expect(end($log2)['content'])->toContain('Rancangan Struktur Bab Metodologi Penelitian')
        ->and(end($log2)['content'])->toContain('Desain Riset')
        ->and(end($log2)['content'])->toContain('Populasi & Sampel');

    // 3. Uji /tabel-analisis
    $page->call('runTool', '/tabel-analisis');
    $log3 = $page->get('assistantLog');
    $tabelIdx = count($log3) - 1;
    expect($log3[$tabelIdx]['content'])->toContain('Matriks Evaluasi & Analisis Komparasi Kinerja')
        ->and($log3[$tabelIdx]['content'])->toContain('Rasio Kepatuhan Pedoman');

    // Sisipkan tabel analisis ke dalam naskah
    $page->call('insertAssistantResponse', $tabelIdx);
    $blocks = $page->get('blocks');
    $hasNewTable = false;
    foreach ($blocks as $b) {
        if (($b['type'] ?? '') === 'table') {
            $hasNewTable = true;
            break;
        }
    }
    expect($hasNewTable)->toBeTrue();

    // 4. Uji /terjemahkan
    $page->call('runTool', '/terjemahkan');
    $log4 = $page->get('assistantLog');
    expect(end($log4)['content'])->toContain('Konversi Terjemahan Akademik Baku')
        ->and(end($log4)['content'])->toContain('Consequently');

    // 5. Uji sendAssistantWithText (follow-up quick inquiry)
    $page->call('sendAssistantWithText', 'Jelaskan rekomendasi ini secara lebih mendalam dengan rincian operasional.');
    $log5 = $page->get('assistantLog');
    expect(count($log5))->toBeGreaterThan(count($log4));
});

it('supports /puebi and /kembangkan slash commands', function (): void {
    $page = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Analisis Kebijakan'],
            ['type' => 'paragraph', 'text' => 'studi ini membuktikan pengaruh variabel x thdp y.'],
        ])
        ->set('activeBlock', 1);

    // 1. /puebi
    $page->call('runTool', '/puebi');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('Hasil Audit Ejaan & PUEBI Baku');

    // 2. /kembangkan
    $page->call('runTool', '/kembangkan');
    $log2 = $page->get('assistantLog');
    expect(end($log2)['content'])->toContain('Elaborasi Pengembangan Teks Akademik');
});

it('executes rich academic slash commands when connected to an active project', function (): void {
    $project = XishieProject::factory()->create([
        'title' => 'Studi Komparasi Model Pembelajaran Mesin',
    ]);

    $page = Livewire::test(NaskahStudioApp::class)
        ->set('sourceProjectId', $project->id)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Studi Komparasi Model Pembelajaran Mesin'],
            ['type' => 'paragraph', 'text' => 'Implementasi algoritma ini menghasilkan akurasi tinggi pada dataset uji.'],
        ])
        ->set('activeBlock', 1);

    // 1. /abstrak with project
    $page->call('runTool', '/abstrak');
    $logAbstrak = $page->get('assistantLog');
    expect(end($logAbstrak)['content'])->toContain('Draf Abstrak Akademik Terstruktur (IMRAD)')
        ->and(end($logAbstrak)['content'])->toContain('Studi Komparasi Model Pembelajaran Mesin');

    // 2. /metodologi with project
    $page->call('runTool', '/metodologi');
    $logMetode = $page->get('assistantLog');
    expect(end($logMetode)['content'])->toContain('Rancangan Struktur Bab Metodologi Penelitian');

    // 3. /tabel-analisis with project
    $page->call('runTool', '/tabel-analisis');
    $logTabel = $page->get('assistantLog');
    expect(end($logTabel)['content'])->toContain('Matriks Evaluasi & Analisis Komparasi Kinerja');

    // 4. /terjemahkan with project
    $page->call('runTool', '/terjemahkan');
    $logTr = $page->get('assistantLog');
    expect(end($logTr)['content'])->toContain('Konversi Terjemahan Akademik Baku');
});

it('automatically captures active block text for /poles and /puebi when project is connected', function (): void {
    $project = XishieProject::factory()->create([
        'title' => 'Riset Evaluasi Kebijakan Publik',
    ]);

    $page = Livewire::test(NaskahStudioApp::class)
        ->set('sourceProjectId', $project->id)
        ->set('blocks', [
            ['type' => 'title', 'text' => 'Riset Evaluasi Kebijakan Publik'],
            ['type' => 'paragraph', 'text' => 'kebijakan ini berdampak langsung thdp efisiensi anggaran daerah'],
        ])
        ->set('activeBlock', 1);

    // 1. /poles without explicit arguments captures active block
    $page->call('runTool', '/poles');
    $log = $page->get('assistantLog');
    expect(end($log)['content'])->toContain('kebijakan ini berdampak langsung thdp efisiensi anggaran daerah')
        ->and(end($log)['content'])->not->toContain('Sertakan kalimat atau pilih blok');

    // 2. /puebi without explicit arguments captures active block
    $page->call('runTool', '/puebi');
    $logPuebi = $page->get('assistantLog');
    expect(end($logPuebi)['content'])->toContain('Hasil Audit Ejaan & PUEBI Baku')
        ->and(end($logPuebi)['content'])->toContain('kebijakan ini berdampak langsung thdp efisiensi anggaran daerah');

    // 3. /rumus primes the equationLatex property
    $page->call('runTool', '/rumus kuadrat');
    expect($page->get('equationLatex'))->toContain('\\frac{-b \\pm \\sqrt{b^2 - 4ac}}{2a}');
});

it('renders prominent copilot ribbon button and detailed stats dialog with academic compliance', function (): void {
    $response = $this->get('/studio');
    $response->assertOk()
        ->assertSee("wire:click=\"toggleTaskPane('assistant')\"", false)
        ->assertSee('Copilot')
        ->assertSee('Xishie Assistant');

    // Test stats dialog
    $page = Livewire::test(NaskahStudioApp::class)
        ->call('loadSampleHandbook')
        ->call('openDialog', 'stats');

    $page->assertSee('Word Count &amp; Readability Statistics', false)
        ->assertSee('WORDS', false)
        ->assertSee('CHARACTERS', false)
        ->assertSee('FLESCH READING EASE', false)
        ->assertSee('Academic Compliance', false);

    // Test welcome page showcases Xishie Assistant & AI Copilot as user feature
    $welcome = $this->get('/');
    $welcome->assertOk()
        ->assertSee('Xishie Assistant &amp; AI Copilot', false)
        ->assertSee('Asisten cerdas telaah naskah interaktif');
});
