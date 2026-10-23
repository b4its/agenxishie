<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Publication\ManuscriptExporter;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Research\ReferenceImportService;
use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Filament\Pages\PlatformMatcherPage;
use App\Filament\Pages\ProjectsPage;
use App\Filament\Pages\SmartRevisionCenterPage;
use App\Filament\Pages\VenueCatalogPage;
use App\Filament\Pages\XishieAssistantPage;
use App\Models\AcademicVenue;
use App\Models\PlatformRecommendation;
use App\Models\ProjectRevision;
use App\Models\XishieProject;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Livewire\Livewire;

it('converts skripsi to journal manuscript with IMRaD sections and citations', function (): void {
    $project = makeProject(['document_type' => DocumentType::SKRIPSI]);
    seedReferences($project);

    // Siapkan draf proposal & final.
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);
    app(XishieSupervisor::class)->run($project, ProjectStage::PROPOSAL);
    app(XishieSupervisor::class)->run($project, ProjectStage::FINAL);

    $scribe = app(AcademicScribeAgent::class);
    $result = $scribe->convertToJournalManuscript($project);

    expect($result)->toHaveKeys(['title', 'sections', 'compiled', 'word_count', 'citations_used'])
        ->and($result['sections'])->toHaveCount(5)
        ->and($result['word_count'])->toBeGreaterThan(50)
        ->and($result['citations_used'])->not->toBeEmpty();

    $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
    $pending = (array) ($final->hitl_pending_payload ?? []);

    expect($pending)->toHaveKey('extracted_journal_manuscript')
        ->and($pending['extracted_journal_manuscript']['word_count'])->toBe($result['word_count']);

    // Log agen konversi tercatat.
    $log = $project->agentLogs()->where('action', 'convert:skripsi_to_journal')->first();
    expect($log)->not->toBeNull();

    // KPI #16 Skripsi-to-Journal conversion bernilai 100%.
    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project);
    expect((float) $metrics[UxKpiMetric::SKRIPSI_TO_JOURNAL_CONVERSION->value]['value'])->toBe(100.0);
});

it('extracts and exports journal manuscript from workspace Livewire action', function (): void {
    $project = makeProject(['document_type' => DocumentType::SKRIPSI]);
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::FINAL);

    // Panggil aksi ekstrak via Livewire.
    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('extractJournalManuscript')
        ->assertSuccessful();

    $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
    $pending = (array) ($final->hitl_pending_payload ?? []);
    expect($pending)->toHaveKey('extracted_journal_manuscript');

    // Unduh naskah jurnal terkonversi (Markdown & DOCX).
    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportJournalManuscript', ManuscriptExporter::FORMAT_MARKDOWN)
        ->assertSuccessful();

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportJournalManuscript', ManuscriptExporter::FORMAT_DOCX)
        ->assertSuccessful();

    $log = $project->agentLogs()->where('action', 'export:journal:markdown')->first();
    expect($log)->not->toBeNull()
        ->and($log->payload['type'])->toBe('skripsi_to_journal');
});

it('regenerates a single section in the stage without altering other sections', function (): void {
    $project = makeProject();
    seedReferences($project);
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    app(AcademicScribeAgent::class)->run($project, $stage);
    $initialSections = $stage->fresh()->sections_content;
    expect($initialSections)->not->toBeEmpty();

    $targetKey = $initialSections[0]['key'];
    $secondKey = $initialSections[1]['key'] ?? null;
    $secondContentBefore = $initialSections[1]['content'] ?? null;

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('regenerateSingleSection', $targetKey)
        ->assertSuccessful();

    $freshSections = $stage->fresh()->sections_content;
    expect($freshSections[0]['key'])->toBe($targetKey);

    if ($secondKey !== null) {
        expect($freshSections[1]['content'])->toBe($secondContentBefore);
    }

    $log = $project->agentLogs()->where('action', 'regenerate_section:'.$targetKey)->first();
    expect($log)->not->toBeNull();
});

it('adds manual references from workspace action with proper citation key and metadata', function (): void {
    $project = makeProject();

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call(
            'addManualReference',
            'Kajian Arsitektur Transformer Modern',
            'Santoso, Wijaya',
            2024,
            'Jurnal Rekayasa Sistem',
            '10.1234/jrs.2024.01',
            'Penelitian komparatif arsitektur self-attention pada NLP.',
            'Empirical benchmark',
            'Akurasi meningkat 12%',
            'Kebutuhan komputasi tinggi',
        )
        ->assertSuccessful();

    $ref = $project->references()->where('title', 'Kajian Arsitektur Transformer Modern')->first();
    expect($ref)->not->toBeNull()
        ->and($ref->year)->toBe(2024)
        ->and($ref->doi)->toBe('10.1234/jrs.2024.01')
        ->and($ref->citation_key)->toContain('2024')
        ->and($ref->matrix_extraction)->toHaveKeys(['method', 'result', 'gap']);
});

it('applies revision diff to the active stage sections content', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    $stage->forceFill([
        'sections_content' => [
            ['key' => 'chapter1', 'heading' => 'Bab 1 Pendahuluan', 'content' => 'Teks lama yang perlu direvisi.'],
            ['key' => 'chapter2', 'heading' => 'Bab 2 Tinjauan Pustaka', 'content' => 'Teks bab dua.'],
        ],
    ])->save();

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'pembimbing_1',
        'raw_feedback' => 'Perbaiki latar belakang Bab 1.',
        'action_items' => [
            ['id' => 1, 'task' => 'Perbaiki latar belakang', 'section_key' => 'chapter1', 'status' => 'pending'],
        ],
        'diff_payload' => [
            [
                'section_key' => 'chapter1',
                'before' => 'Teks lama yang perlu direvisi.',
                'after' => 'Teks baru yang disempurnakan dengan argumen tajam.',
                'note' => 'Perbaiki latar belakang Bab 1.',
            ],
        ],
        'response_matrix_doc' => '',
        'status' => RevisionStatus::PENDING,
    ]);

    Livewire::test(SmartRevisionCenterPage::class, ['projectId' => $project->id])
        ->call('applyRevisionDiff', $revision->id, 'chapter1')
        ->assertSuccessful();

    $stage->refresh();
    $sections = collect($stage->sections_content)->keyBy('key');

    expect($sections['chapter1']['content'])->toBe('Teks baru yang disempurnakan dengan argumen tajam.');

    $revision->refresh();
    expect($revision->action_items[0]['status'])->toBe('resolved')
        ->and($revision->status->value)->toBe('resolved')
        ->and($revision->resolutionPercentage())->toBe(100.0);
});

it('filters and sorts recommendations in platform matcher', function (): void {
    $project = makeProject();

    $venueA = AcademicVenue::create([
        'journal_name' => 'Jurnal Alpha (SINTA 2)',
        'publisher' => 'Univ A',
        'scope_type' => 'sinta',
        'index_tier' => 'SINTA 2',
        'field_of_study' => 'STEM',
        'aims_and_scope' => 'Computer science and data',
        'apc_estimate' => 500000,
        'is_active' => true,
    ]);

    $venueB = AcademicVenue::create([
        'journal_name' => 'Journal Beta (Scopus Q1)',
        'publisher' => 'Publisher B',
        'scope_type' => 'scopus',
        'index_tier' => 'Q1',
        'field_of_study' => 'STEM',
        'aims_and_scope' => 'International computing journal',
        'apc_estimate' => 2000000,
        'is_active' => true,
    ]);

    PlatformRecommendation::create([
        'project_id' => $project->id,
        'academic_venue_id' => $venueA->id,
        'journal_name' => $venueA->journal_name,
        'publisher' => $venueA->publisher,
        'index_tier' => $venueA->index_tier,
        'match_score' => 88.5,
        'scope_reasoning' => 'Sesuai dengan topik',
        'apc_estimate' => $venueA->apc_estimate,
        'submission_url' => 'http://example.com/a',
    ]);

    PlatformRecommendation::create([
        'project_id' => $project->id,
        'academic_venue_id' => $venueB->id,
        'journal_name' => $venueB->journal_name,
        'publisher' => $venueB->publisher,
        'index_tier' => $venueB->index_tier,
        'match_score' => 95.0,
        'scope_reasoning' => 'Sangat cocok untuk Scopus',
        'apc_estimate' => $venueB->apc_estimate,
        'submission_url' => 'http://example.com/b',
    ]);

    $component = Livewire::test(PlatformMatcherPage::class, ['projectId' => $project->id]);

    // Filter SINTA: hanya venueA yang muncul.
    $component->set('filterScope', 'SINTA');
    $recs = $component->viewData('recommendations');
    expect($recs)->toHaveCount(1)
        ->and($recs->first()->journal_name)->toContain('Alpha');

    // Filter Q: hanya venueB yang muncul.
    $component->set('filterScope', 'Q');
    $recs = $component->viewData('recommendations');
    expect($recs)->toHaveCount(1)
        ->and($recs->first()->journal_name)->toContain('Beta');

    // Urutkan berdasarkan APC terendah (semua).
    $component->set('filterScope', '');
    $component->set('sortBy', 'apc');
    $recs = $component->viewData('recommendations');
    expect($recs->first()->journal_name)->toContain('Alpha');
});

it('renders the Retro Blue landing page with 200 OK and branding', function (): void {
    $response = $this->get('/');

    $response->assertStatus(200)
        ->assertSee('AGEN XISHIE')
        ->assertSee('OMNIGENSI MULTI-AGENT')
        ->assertSee('Academic Workspace')
        ->assertSee('Smart Revision Center')
        ->assertSee('Platform Matcher');
});

it('parses BibTeX strings and imports references into the project', function (): void {
    $project = makeProject();
    $importer = app(ReferenceImportService::class);

    $bibtex = <<<'BIB'
@article{pratama2024iot,
  title = {Optimasi Jaringan IoT untuk Smart Agriculture Berbasis LoRaWAN},
  author = {Pratama, Aditya and Santoso, Budi},
  journal = {Jurnal Rekayasa Sistem},
  year = {2024},
  doi = {10.1234/jrs.2024.01},
  abstract = {Penelitian ini mengevaluasi metode routing LoRaWAN dengan hasil akurasi 94% namun gap pada latency tinggi.}
}
@inproceedings{wijaya2023ai,
  title = {Penerapan Deep Learning untuk Klasifikasi Citra},
  author = {Wijaya, Hendra},
  booktitle = {Prosiding Konferensi Nasional},
  year = {2023},
  abstract = {Eksperimen menggunakan model CNN dengan hasil evaluasi F1-score 0.88.}
}
BIB;

    $parsed = $importer->parseBibtex($bibtex);
    expect($parsed)->toHaveCount(2)
        ->and($parsed[0]['citation_key'])->toBe('pratama2024iot')
        ->and($parsed[0]['year'])->toBe(2024)
        ->and($parsed[0]['authors'])->toHaveCount(2)
        ->and($parsed[1]['citation_key'])->toBe('wijaya2023ai');

    $count = $importer->importBibtex($project, $bibtex);
    expect($count)->toBe(2);

    $ref = $project->references()->where('citation_key', 'pratama2024iot')->first();
    expect($ref)->not->toBeNull()
        ->and($ref->is_verified)->toBeTrue()
        ->and($ref->source)->toBe('bibtex_import');
});

it('exports literature matrix to CSV format with correct header and rows', function (): void {
    $project = makeProject();
    seedReferences($project);

    $builder = app(LiteratureMatrixBuilder::class);
    $csv = $builder->toCsv($project);

    expect($csv)->toContain('Citation Key')
        ->and($csv)->toContain('Authors')
        ->and($csv)->toContain('Verified')
        ->and($csv)->toContain('Yes');

    // Uji Livewire export handler.
    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportLiteratureMatrix', 'csv')
        ->assertSuccessful();

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportLiteratureMatrix', 'markdown')
        ->assertSuccessful();
});

it('duplicates a project with all stages and references', function (): void {
    $project = makeProject(['title' => 'Original Master Thesis']);
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $initialCount = XishieProject::count();

    Livewire::test(ProjectsPage::class)
        ->call('duplicateProject', $project->id)
        ->assertSuccessful();

    expect(XishieProject::count())->toBe($initialCount + 1);

    $cloned = XishieProject::where('title', 'like', 'Salinan dari Original Master Thesis%')->first();
    expect($cloned)->not->toBeNull()
        ->and($cloned->stages()->count())->toBe($project->stages()->count())
        ->and($cloned->references()->count())->toBe($project->references()->count());
});

it('sets a journal venue as project target from venue catalog', function (): void {
    $project = makeProject(['title' => 'Target Selection Project']);
    $venue = AcademicVenue::create([
        'journal_name' => 'Jurnal Sains Komputer Terapan',
        'publisher' => 'Pusat Riset Komputasi',
        'scope_type' => 'sinta',
        'index_tier' => 'Sinta 2',
        'field_of_study' => 'STEM',
        'aims_and_scope' => 'Jurnal komputasi terapan dan informatika',
        'apc_estimate' => 500000,
        'is_active' => true,
    ]);

    Livewire::test(VenueCatalogPage::class, ['projectId' => $project->id])
        ->call('setAsProjectTarget', $venue->id)
        ->assertSuccessful();

    $targetRec = $project->recommendations()->where('is_selected', true)->first();
    expect($targetRec)->not->toBeNull()
        ->and($targetRec->academic_venue_id)->toBe($venue->id)
        ->and($targetRec->journal_name)->toBe($venue->journal_name);
});

it('executes /ringkas command and exports assistant transcript', function (): void {
    $project = makeProject(['title' => 'Assistant Executive Summary Project']);
    seedReferences($project);
    $stage = app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $service = app(XishieAssistantService::class);
    $res = $service->handle($project, '/ringkas');

    expect($res['command'])->toBe('/ringkas')
        ->and($res['reply'])->toContain('Ringkasan Eksekutif Proyek')
        ->and($res['reply'])->toContain('Total Kata Naskah')
        ->and($res['data'])->toHaveKey('total_words');

    // Uji Livewire chat dan ekspor transkrip.
    $component = Livewire::test(XishieAssistantPage::class, ['projectId' => $project->id])
        ->set('message', '/ringkas')
        ->call('send')
        ->assertSuccessful();

    expect($component->get('log'))->toHaveCount(2);

    $component->call('exportTranscript')
        ->assertSuccessful();
});
