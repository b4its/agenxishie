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
