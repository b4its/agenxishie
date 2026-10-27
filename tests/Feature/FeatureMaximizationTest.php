<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\GuidelineEngine\CustomGuidelineParser;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelinePresetFactory;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Publication\CitationFormatter;
use App\Domain\Publication\ManuscriptExporter;
use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Research\ReferenceImportService;
use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\NextActionService;
use App\Domain\Workspace\StageStateManager;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Filament\Pages\AiChatPage;
use App\Filament\Pages\ExternalProjectsPage;
use App\Filament\Pages\NaskahStudioPage;
use App\Filament\Pages\OmnigensiMonitoringPage;
use App\Filament\Pages\PlatformMatcherPage;
use App\Filament\Pages\ProjectOnboardingWizard;
use App\Filament\Pages\ProjectsPage;
use App\Filament\Pages\SmartRevisionCenterPage;
use App\Filament\Pages\VenueCatalogPage;
use App\Filament\Pages\XishieAssistantPage;
use App\Filament\Widgets\AarrrFunnelWidget;
use App\Filament\Widgets\AgentActivityTerminalWidget;
use App\Filament\Widgets\HeartKpiTelemetryWidget;
use App\Filament\Widgets\ProjectsOverviewWidget;
use App\Filament\Widgets\WorkspaceKpiBarWidget;
use App\Livewire\NaskahStudioApp;
use App\Models\AcademicVenue;
use App\Models\ExternalClient;
use App\Models\NaskahDocument;
use App\Models\OmnigensiAgentLog;
use App\Models\PlatformRecommendation;
use App\Models\ProjectRevision;
use App\Models\XishieProject;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

it('exports literature matrix to JSON format', function (): void {
    $project = makeProject();
    seedReferences($project);

    $builder = app(LiteratureMatrixBuilder::class);
    $json = $builder->toJson($project);

    expect($json)->toBeJson()
        ->and($json)->toContain('project_id')
        ->and($json)->toContain('matrix');

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportLiteratureMatrix', 'json')
        ->assertSuccessful();
});

it('switches between unlocked stages in workspace via selectStage', function (): void {
    $project = makeProject();
    seedReferences($project);

    // Buka draft & proposal.
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);
    app(StageStateManager::class)->complete($project, ProjectStage::DRAFT);
    app(StageStateManager::class)->advance($project, ProjectStage::PROPOSAL);

    expect($project->fresh()->current_stage->value)->toBe(ProjectStage::PROPOSAL->value);

    // Kembali ke draft via selectStage.
    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('selectStage', ProjectStage::DRAFT->value)
        ->assertSuccessful();

    expect($project->fresh()->current_stage->value)->toBe(ProjectStage::DRAFT->value);

    // Coba ke final yang masih locked (harus ditolak).
    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('selectStage', ProjectStage::FINAL->value)
        ->assertSuccessful();

    expect($project->fresh()->current_stage->value)->toBe(ProjectStage::DRAFT->value);
});

it('resets stage draft content in workspace via resetStageContent', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    expect($stage->fresh()->sections_content)->not->toBeEmpty();

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('resetStageContent')
        ->assertSuccessful();

    $freshStage = $stage->fresh();
    expect($freshStage->sections_content)->toBeNull()
        ->and($freshStage->word_count)->toBe(0)
        ->and($freshStage->compiled_content)->toBeNull();
});

it('toggles revision action item status and recalculates resolution percentage', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'pembimbing_1',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => '1. Perjelas gap riset',
        'action_items' => [
            ['id' => 1, 'task' => 'Perjelas gap riset', 'section_key' => 'pendahuluan', 'status' => 'pending'],
            ['id' => 2, 'task' => 'Perbaiki sitasi', 'section_key' => 'pendahuluan', 'status' => 'pending'],
        ],
    ]);

    expect($revision->resolutionPercentage())->toBe(0.0);

    // Toggle item 1: pending -> resolved.
    Livewire::test(SmartRevisionCenterPage::class, ['projectId' => $project->id])
        ->call('toggleItemStatus', $revision->id, 1)
        ->assertSuccessful();

    $fresh = $revision->fresh();
    expect($fresh->resolutionPercentage())->toBe(50.0)
        ->and($fresh->status->value)->toBe(RevisionStatus::IN_PROGRESS->value);

    // Toggle item 1 lagi: resolved -> in_progress.
    Livewire::test(SmartRevisionCenterPage::class, ['projectId' => $project->id])
        ->call('toggleItemStatus', $revision->id, 1)
        ->assertSuccessful();

    $fresh = $revision->fresh();
    expect($fresh->resolutionPercentage())->toBe(0.0);
});

it('exports response matrix to CSV format from revision center', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'reviewer_jurnal',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => '1. Perkuat metodologi eksperimen',
        'action_items' => [
            ['id' => 1, 'task' => 'Perkuat metodologi eksperimen', 'section_key' => 'metode', 'status' => 'pending'],
        ],
    ]);

    $csv = app(PeerCriticAgent::class)->renderResponseMatrixCsv($revision);
    expect($csv)->toContain('No,"Catatan Reviewer",Bagian,Status,"Rencana Perbaikan"')
        ->and($csv)->toContain('Perkuat metodologi eksperimen');

    Livewire::test(SmartRevisionCenterPage::class, ['projectId' => $project->id])
        ->call('downloadResponseMatrix', $revision->id, 'csv')
        ->assertSuccessful();

    Livewire::test(SmartRevisionCenterPage::class, ['projectId' => $project->id])
        ->call('downloadResponseMatrix', $revision->id, 'markdown')
        ->assertSuccessful();
});

it('renders live terminal logs with project filtering', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    Livewire::test(AgentActivityTerminalWidget::class, ['projectId' => $project->id])
        ->assertSuccessful()
        ->assertSee('OMNIGENSI AGENT TERMINAL');

    Livewire::test(AgentActivityTerminalWidget::class, ['projectId' => null])
        ->assertSuccessful()
        ->assertSee('Semua Proyek');
});

it('exports AI chat transcript to Markdown format', function (): void {
    $component = Livewire::test(AiChatPage::class)
        ->set('messages', [
            ['role' => 'user', 'content' => 'Bagaimana metodologi penelitian kuantitatif?'],
            ['role' => 'assistant', 'content' => 'Metodologi kuantitatif berfokus pada data numerik.', 'model' => 'gpt-4o', 'latency_ms' => 120],
        ]);

    $component->call('exportChat')
        ->assertSuccessful();
});

it('exports project references as BibTeX from academic workspace', function (): void {
    $project = makeProject();
    seedReferences($project);

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportReferencesBibtex')
        ->assertSuccessful();
});

it('filters projects by control mode and stage in projects page', function (): void {
    makeProject(['title' => 'Alpha Auto Draft', 'control_mode' => ControlMode::AUTOMATION, 'current_stage' => ProjectStage::DRAFT]);
    makeProject(['title' => 'Beta HITL Proposal', 'control_mode' => ControlMode::HITL, 'current_stage' => ProjectStage::PROPOSAL]);
    makeProject(['title' => 'Gamma Manual Final', 'control_mode' => ControlMode::MANUAL, 'current_stage' => ProjectStage::FINAL]);

    $component = Livewire::test(ProjectsPage::class);

    // Filter mode automation.
    $component->set('filterMode', 'automation');
    $projects = collect($component->viewData('projects'));
    expect($projects->every(fn ($p) => str_contains(strtolower($p['control_mode']), 'auto')))->toBeTrue();

    // Filter stage proposal.
    $component->set('filterMode', '');
    $component->set('filterStage', 'proposal');
    $projects = collect($component->viewData('projects'));
    expect($projects->every(fn ($p) => strtolower($p['stage']) === 'proposal'))->toBeTrue();

    // Reset filters.
    $component->call('resetFilters');
    expect($component->get('filterMode'))->toBe('')
        ->and($component->get('filterStage'))->toBe('');
});

it('exports journal recommendations to CSV and Markdown from platform matcher', function (): void {
    $project = makeProject(['title' => 'Recommendation Export Project']);
    $venue = AcademicVenue::create([
        'journal_name' => 'Jurnal Ilmiah Komputer',
        'publisher' => 'Penerbit Riset',
        'scope_type' => 'sinta',
        'index_tier' => 'Sinta 1',
        'field_of_study' => 'STEM',
        'aims_and_scope' => 'Fokus pada komputasi',
        'apc_estimate' => 0,
        'is_active' => true,
    ]);

    PlatformRecommendation::create([
        'project_id' => $project->id,
        'academic_venue_id' => $venue->id,
        'journal_name' => $venue->journal_name,
        'publisher' => $venue->publisher,
        'index_tier' => $venue->index_tier,
        'match_score' => 92.0,
        'scope_reasoning' => 'Kesesuaian tinggi',
        'apc_estimate' => 0,
        'submission_url' => 'http://example.com',
        'is_selected' => true,
    ]);

    Livewire::test(PlatformMatcherPage::class, ['projectId' => $project->id])
        ->call('exportRecommendations', 'csv')
        ->assertSuccessful();

    Livewire::test(PlatformMatcherPage::class, ['projectId' => $project->id])
        ->call('exportRecommendations', 'markdown')
        ->assertSuccessful();
});

it('sorts venues in catalog by APC and review time', function (): void {
    AcademicVenue::create([
        'journal_name' => 'Journal Premium Fast',
        'publisher' => 'Publisher A',
        'scope_type' => 'scopus',
        'index_tier' => 'Q1',
        'aims_and_scope' => 'Fokus kajian premium',
        'apc_estimate' => 15000000,
        'review_time_days' => 14,
        'is_active' => true,
    ]);

    AcademicVenue::create([
        'journal_name' => 'Journal Free Slow',
        'publisher' => 'Publisher B',
        'scope_type' => 'sinta',
        'index_tier' => 'Sinta 2',
        'aims_and_scope' => 'Fokus kajian terbuka',
        'apc_estimate' => 0,
        'review_time_days' => 90,
        'is_active' => true,
    ]);

    $component = Livewire::test(VenueCatalogPage::class);

    // Sort by APC lowest: Journal Free Slow first.
    $component->set('sortBy', 'apc_asc');
    $venuesApc = collect($component->viewData('venues'));
    expect($venuesApc->first()['journal_name'])->toBe('Journal Free Slow');

    // Sort by Review fastest: Journal Premium Fast first.
    $component->set('sortBy', 'review_asc');
    $venuesReview = collect($component->viewData('venues'));
    expect($venuesReview->first()['journal_name'])->toBe('Journal Premium Fast');

    // Reset.
    $component->call('resetFilters');
    expect($component->get('sortBy'))->toBe('name');
});

it('toggles auto-refresh state in omnigensi monitoring page', function (): void {
    $component = Livewire::test(OmnigensiMonitoringPage::class)
        ->assertSuccessful()
        ->assertSet('autoRefresh', false);

    $component->set('autoRefresh', true)
        ->assertSet('autoRefresh', true);
});

it('applies research topic templates in onboarding wizard', function (): void {
    $component = Livewire::test(ProjectOnboardingWizard::class);

    $component->call('applyTemplate', 'ai_iot')
        ->assertSuccessful();

    $data = $component->get('data');
    expect($data['title'])->toContain('IoT')
        ->and($data['target_tier'])->toBe('Q2');

    $component->call('applyTemplate', 'nlp_health')
        ->assertSuccessful();

    $data = $component->get('data');
    expect($data['title'])->toContain('Transformer')
        ->and($data['target_tier'])->toBe('Q1');
});

it('suggests unresolved revisions and journal extraction in next actions', function (): void {
    $project = makeProject(['document_type' => DocumentType::SKRIPSI]);
    $project->forceFill(['current_stage' => ProjectStage::FINAL])->save();
    seedReferences($project);
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::FINAL);
    $stage->forceFill([
        'sections_content' => [['key' => 'bab_1', 'heading' => 'Bab 1', 'content' => 'Teks']],
        'status' => StageStatus::COMPLETED,
    ])->save();

    // Buat revisi unresolved.
    ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'pembimbing_1',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => '1. Perjelas bab 1',
        'action_items' => [['id' => 1, 'task' => 'Perjelas bab 1', 'status' => 'pending']],
    ]);

    $actions = app(NextActionService::class)->forProject($project);
    $actionKeys = array_column($actions, 'key');

    expect($actionKeys)->toContain('revision')
        ->and($actionKeys)->toContain('journal_extraction');
});

it('dynamically switches project in workspace KPI bar widget', function (): void {
    $projectA = makeProject(['title' => 'Project Alpha KPI']);
    $projectB = makeProject(['title' => 'Project Beta KPI']);

    $component = Livewire::test(WorkspaceKpiBarWidget::class, ['projectId' => $projectA->id]);
    expect($component->viewData('project')->id)->toBe($projectA->id);

    $component->set('projectId', $projectB->id);
    expect($component->viewData('project')->id)->toBe($projectB->id);
});

it('dynamically switches project in HEART and AARRR telemetry widget', function (): void {
    $projectA = makeProject(['title' => 'Project Alpha Telemetry']);
    $projectB = makeProject(['title' => 'Project Beta Telemetry']);

    $component = Livewire::test(HeartKpiTelemetryWidget::class, ['projectId' => $projectA->id]);
    expect($component->viewData('projectOptions'))->toHaveKey($projectA->id);

    $component->set('projectId', $projectB->id);
    expect($component->get('projectId'))->toBe($projectB->id);
});

it('displays revisions count in projects overview widget', function (): void {
    $project = makeProject(['title' => 'Overview Revision Project']);
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'pembimbing_1',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => 'Catatan',
    ]);

    $component = Livewire::test(ProjectsOverviewWidget::class);
    $rows = collect($component->viewData('rows'));
    $matched = $rows->firstWhere('id', $project->id);

    expect($matched)->not->toBeNull()
        ->and($matched['revisions'])->toBe(1);
});

it('supports tableCalculateColumn in studio for sum and average operations', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);
    $component->call('newDocument');

    // Buat tabel dengan angka
    $component->set('blocks', [
        [
            'type' => 'table',
            'table' => [
                'header' => true,
                'rows' => [
                    [['text' => 'Item'], ['text' => 'Nilai']],
                    [['text' => 'A'], ['text' => '10']],
                    [['text' => 'B'], ['text' => '20']],
                ],
            ],
            'align' => 'left',
        ],
    ]);

    $component->call('tableCalculateColumn', 0, 1, 'sum');
    $rows = $component->get('blocks')[0]['table']['rows'];
    $lastRow = end($rows);
    expect((string) $lastRow[1]['text'])->toContain('30');

    // Reset table dan uji rata-rata
    $component->set('blocks', [
        [
            'type' => 'table',
            'table' => [
                'header' => true,
                'rows' => [
                    [['text' => 'Item'], ['text' => 'Nilai']],
                    [['text' => 'A'], ['text' => '10']],
                    [['text' => 'B'], ['text' => '20']],
                ],
            ],
            'align' => 'left',
        ],
    ]);
    $component->call('tableCalculateColumn', 0, 1, 'avg');
    $updatedRows = $component->get('blocks')[0]['table']['rows'];
    $newLastRow = end($updatedRows);
    expect((string) $newLastRow[1]['text'])->toContain('15');
});

it('preserves first-line indent through sync, docx export, and rich model html', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);

    $html = '<p style="text-indent: 1.27cm;">Paragraf naskah dengan indentasi baris pertama baku.</p>';
    $component->call('save', [$html]);

    $blocks = $component->get('blocks');
    expect($blocks)->not->toBeEmpty()
        ->and($blocks[0]['firstLineIndent'] ?? false)->toBeTrue();

    // Pastikan DOCX menyertakan firstLine 720 dxa
    $docxExporter = app(NaskahDocxExporter::class);
    $docxBinary = $docxExporter->build($blocks, 'Test First Line');
    expect(strlen($docxBinary))->toBeGreaterThan(100);

    $zip = new \ZipArchive;
    $temp = tempnam(sys_get_temp_dir(), 'docx_test');
    file_put_contents($temp, $docxBinary);
    $zip->open($temp);
    $docXml = $zip->getFromName('word/document.xml');
    $zip->close();
    @unlink($temp);

    expect($docXml)->toContain('w:firstLine="720"');

    // Pastikan RichModel toHtml menghasilkan text-indent:1.27cm
    $model = app(NaskahRichModel::class);
    $htmlOutput = $model->toHtml($blocks, 'Test First Line');
    expect($htmlOutput)->toContain('text-indent:1.27cm');
});

it('exports naskah studio as structured json file', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);
    $component->call('newDocument');
    $component->set('docTitle', 'Judul Riset JSON');
    $component->set('blocks', [
        ['type' => 'paragraph', 'text' => 'Isi paragraf ilmiah.', 'runs' => [], 'align' => 'left'],
    ]);

    $response = $component->instance()->export('json');
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $json = ob_get_clean();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['title'])->toBe('Judul Riset JSON')
        ->and($decoded['blocks'])->toHaveCount(1);
});

it('renders native ODT OpenDocument archive from ManuscriptExporter', function (): void {
    $project = makeProject(['title' => 'Project ODT Export']);
    seedReferences($project);

    $exporter = app(ManuscriptExporter::class);
    expect($exporter->isSupported('odt'))->toBeTrue();

    $binary = $exporter->render($project, 'odt');
    expect(strlen($binary))->toBeGreaterThan(100);

    $zip = new \ZipArchive;
    $temp = tempnam(sys_get_temp_dir(), 'odt_test');
    file_put_contents($temp, $binary);
    $zip->open($temp);
    $contentXml = $zip->getFromName('content.xml');
    $manifestXml = $zip->getFromName('META-INF/manifest.xml');
    $zip->close();
    @unlink($temp);

    expect($contentXml)->toContain('Project ODT Export')
        ->and($manifestXml)->toContain('application/vnd.oasis.opendocument.text');
});

it('downloads Response to Reviewers as professional Word docx file', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::FINAL);

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'Reviewer 1 Scopus',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => "1. Perjelas novelty pada introduction.\n2. Tambahkan komparasi metode.",
        'action_items' => [
            ['id' => 1, 'task' => 'Perjelas novelty pada introduction', 'section_key' => 'introduction', 'status' => 'resolved'],
            ['id' => 2, 'task' => 'Tambahkan komparasi metode', 'section_key' => 'methods', 'status' => 'pending'],
        ],
    ]);

    $critic = app(PeerCriticAgent::class);
    $docxBinary = $critic->renderResponseMatrixDocx($revision);
    expect(strlen($docxBinary))->toBeGreaterThan(200);

    $response = Livewire::test(SmartRevisionCenterPage::class)
        ->instance()->downloadResponseMatrix($revision->id, 'docx');

    expect($response)->toBeInstanceOf(StreamedResponse::class);
});

it('exports filtered venue catalog to CSV and JSON formats', function (): void {
    AcademicVenue::create([
        'journal_name' => 'Jurnal Teknologi Informasi SINTA 2',
        'publisher' => 'Universitas Indonesia',
        'scope_type' => 'nasional',
        'index_tier' => 'S2',
        'field_of_study' => 'Informatika',
        'aims_and_scope' => 'Fokus kajian ilmu komputer, rekayasa perangkat lunak, dan kecerdasan buatan.',
        'apc_estimate' => 1500000,
        'review_time_days' => 45,
        'is_active' => true,
    ]);

    $page = Livewire::test(VenueCatalogPage::class);

    // Ekspor CSV
    $csvResponse = $page->instance()->exportCatalog('csv');
    expect($csvResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $csvResponse->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Jurnal Teknologi Informasi SINTA 2')
        ->and($csv)->toContain('Universitas Indonesia')
        ->and($csv)->toContain('S2');

    // Ekspor JSON
    $jsonResponse = $page->instance()->exportCatalog('json');
    expect($jsonResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $jsonResponse->sendContent();
    $json = ob_get_clean();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['total'])->toBeGreaterThanOrEqual(1)
        ->and($decoded['venues'][0]['journal_name'])->toBe('Jurnal Teknologi Informasi SINTA 2');
});

it('answers /rumus with Einstein, Bayes, and Gaussian distributions', function (): void {
    $project = makeProject();
    $assistant = app(XishieAssistantService::class);

    $einstein = $assistant->handle($project, '/rumus einstein');
    expect($einstein['reply'])->toContain('E = m c^2')
        ->and($einstein['data']['name'])->toContain('Einstein');

    $bayes = $assistant->handle($project, '/rumus bayes');
    expect($bayes['reply'])->toContain('P(A|B)')
        ->and($bayes['data']['name'])->toContain('Bayes');

    $gaussian = $assistant->handle($project, '/rumus gaussian');
    expect($gaussian['reply'])->toContain('Distribusi Normal Gaussian');
});

it('duplicates editor section in academic workspace', function (): void {
    $project = makeProject();
    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    $stage->update([
        'sections_content' => [
            ['heading' => 'Pendahuluan', 'content' => 'Latar belakang penelitian ini...'],
            ['heading' => 'Metodologi', 'content' => 'Metode yang digunakan adalah...'],
        ],
    ]);

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);

    $initialCount = count($component->get('editorSections'));
    expect($initialCount)->toBe(2);

    $firstSection = $component->get('editorSections')[0];
    $component->call('duplicateEditorSection', 0);

    $newSections = $component->get('editorSections');
    expect(count($newSections))->toBe(3)
        ->and($newSections[1]['heading'])->toBe($firstSection['heading'].' (Salinan)')
        ->and($newSections[1]['content'])->toBe($firstSection['content']);
});

it('updates paper size and switches orientation in naskah studio', function (): void {
    $studio = Livewire::test(NaskahStudioApp::class)
        ->call('setPaperSize', 'b5')
        ->call('setOrientation', 'landscape');

    expect($studio->get('pageSetup')['size'])->toBe('B5')
        ->and($studio->get('pageSetup')['orientation'])->toBe('landscape')
        ->and($studio->get('dirty'))->toBeTrue();

    $studio->call('setOrientation', 'portrait');
    expect($studio->get('pageSetup')['orientation'])->toBe('portrait');
});

it('exports projects roster to CSV and JSON formats', function (): void {
    makeProject(['title' => 'Studi AI Deep Learning']);

    $component = Livewire::test(ProjectsPage::class);

    // CSV
    $csvResponse = $component->instance()->exportProjects('csv');
    expect($csvResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $csvResponse->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Judul Proyek')
        ->and($csv)->toContain('Studi AI Deep Learning');

    // JSON
    $jsonResponse = $component->instance()->exportProjects('json');
    expect($jsonResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $jsonResponse->sendContent();
    $json = ob_get_clean();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['total_projects'])->toBeGreaterThanOrEqual(1)
        ->and(collect($decoded['projects'])->pluck('title'))->toContain('Studi AI Deep Learning');
});

it('exports AI chat transcript to JSON and TXT formats', function (): void {
    $component = Livewire::test(AiChatPage::class)
        ->set('messages', [
            ['role' => 'user', 'content' => 'Bagaimana metodologi penelitian kuantitatif?'],
            ['role' => 'assistant', 'content' => 'Metodologi kuantitatif berfokus pada data numerik.', 'model' => 'gpt-4o', 'latency_ms' => 120],
        ]);

    // JSON
    $jsonResponse = $component->instance()->exportChat('json');
    expect($jsonResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $jsonResponse->sendContent();
    $json = ob_get_clean();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['total_messages'])->toBe(2)
        ->and($decoded['messages'][0]['content'])->toBe('Bagaimana metodologi penelitian kuantitatif?');

    // TXT
    $txtResponse = $component->instance()->exportChat('txt');
    expect($txtResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $txtResponse->sendContent();
    $txt = ob_get_clean();

    expect($txt)->toContain('TRANSKRIP PERCAKAPAN AI CHAT')
        ->and($txt)->toContain('Bagaimana metodologi penelitian kuantitatif?')
        ->and($txt)->toContain('Metodologi kuantitatif berfokus pada data numerik.');
});

it('formats Harvard citation style and extracts it from guideline text', function (): void {
    $project = makeProject();
    seedReferences($project);
    $reference = $project->references()->first();

    $formatter = app(CitationFormatter::class);
    $formatted = $formatter->format($reference, CitationStyle::HARVARD);

    expect($formatted)->toContain('Prasetyo')
        ->and($formatted)->toContain((string) $reference->title)
        ->and(CitationStyle::HARVARD->label())->toBe('Harvard');

    $parser = app(CustomGuidelineParser::class);
    $fallback = app(GuidelinePresetFactory::class)->forDocumentType(DocumentType::JOURNAL, 'ieee');
    $guideline = $parser->parse('Pedoman sitasi penulisan naskah menggunakan gaya Harvard format referensi.', $fallback);
    expect($guideline->citationStyle)->toBe(CitationStyle::HARVARD->value);
});

it('exports literature review matrix to DOCX format in academic workspace', function (): void {
    $project = makeProject();
    seedReferences($project);

    // Direct builder test
    $builder = app(LiteratureMatrixBuilder::class);
    $docxBinary = $builder->toDocx($project);
    expect(strlen($docxBinary))->toBeGreaterThan(100)
        ->and(substr($docxBinary, 0, 2))->toBe('PK'); // ZIP archive header

    // Workspace Livewire export test
    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);
    $response = $component->instance()->exportLiteratureMatrix('docx');
    expect($response)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $response->sendContent();
    $content = ob_get_clean();

    expect(strlen((string) $content))->toBeGreaterThan(100)
        ->and(substr((string) $content, 0, 2))->toBe('PK');
});

it('exports connected external clients roster to CSV and JSON formats', function (): void {
    ExternalClient::factory()->create([
        'name' => 'Test External Agency',
        'target_url' => 'https://api.example.com',
        'api_key' => 'xsh_test_key_12345678901234567890',
        'status' => 'online',
        'last_latency_ms' => 42,
        'inbound_requests_count' => 150,
        'is_active' => true,
    ]);

    $component = Livewire::test(ExternalProjectsPage::class);

    // CSV
    $csvResponse = $component->instance()->exportClients('csv');
    expect($csvResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $csvResponse->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('Nama Klien')
        ->and($csv)->toContain('Test External Agency')
        ->and($csv)->toContain('https://api.example.com');

    // JSON
    $jsonResponse = $component->instance()->exportClients('json');
    expect($jsonResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $jsonResponse->sendContent();
    $json = ob_get_clean();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['total_clients'])->toBeGreaterThanOrEqual(1)
        ->and(collect($decoded['clients'])->pluck('name'))->toContain('Test External Agency');
});

it('renders Mermaid workflow graph and exports studio CLI to JSON', function (): void {
    // 1. omnigensi:graph --mermaid
    $this->artisan('omnigensi:graph --mermaid')
        ->expectsOutputToContain('flowchart TD')
        ->expectsOutputToContain('scout --> scribe')
        ->expectsOutputToContain('critic -->|approve| venue')
        ->assertSuccessful();

    // 2. naskah:manage export --format=json
    $document = NaskahDocument::query()->create([
        'title' => 'Dokumen CLI Export JSON',
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Teks naskah uji coba ekspor json.'],
        ],
        'word_count' => 7,
    ]);

    $tempFile = tempnam(sys_get_temp_dir(), 'naskah_json_').'.json';

    $this->artisan('naskah:manage export '.$document->id.' --format=json --output='.$tempFile)
        ->assertSuccessful();

    expect(file_exists($tempFile))->toBeTrue();
    $content = file_get_contents($tempFile);
    $decoded = json_decode((string) $content, true);

    expect($decoded)->toBeArray()
        ->and($decoded['title'])->toBe('Dokumen CLI Export JSON')
        ->and(count($decoded['blocks']))->toBe(2);

    @unlink($tempFile);
});

it('exports agent activity terminal logs to CSV and JSON formats', function (): void {
    $project = makeProject(['title' => 'Audit Proyek Log Agen']);

    OmnigensiAgentLog::create([
        'project_id' => $project->id,
        'agent_name' => 'ScholarScoutAgent',
        'action' => 'literature_search',
        'ttft_ms' => 150,
        'execution_time_ms' => 450,
        'token_usage' => 800,
        'status' => 'success',
    ]);

    $widget = Livewire::test(AgentActivityTerminalWidget::class, ['projectId' => $project->id]);

    // CSV
    $csvResponse = $widget->instance()->exportLogs('csv');
    expect($csvResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $csvResponse->sendContent();
    $csv = ob_get_clean();

    expect($csv)->toContain('ScholarScoutAgent')
        ->and($csv)->toContain('literature_search')
        ->and($csv)->toContain('Audit Proyek Log Agen');

    // JSON
    $jsonResponse = $widget->instance()->exportLogs('json');
    expect($jsonResponse)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $jsonResponse->sendContent();
    $json = ob_get_clean();

    $decoded = json_decode($json, true);
    expect($decoded)->toBeArray()
        ->and($decoded['total_logs'])->toBeGreaterThanOrEqual(1)
        ->and(collect($decoded['logs'])->pluck('agent_name'))->toContain('ScholarScoutAgent');
});

it('autocorrects scientific symbols and calculates min max on studio table columns', function (): void {
    // 1. autoCorrect scientific symbols
    $richModel = app(NaskahRichModel::class);
    $corrected = $richModel->autoCorrect('P != Q, suhu 25 degC, A <= B, C >= D, X <=> Y, nilai approx 3.14');
    expect($corrected['text'])->toContain('≠')
        ->and($corrected['text'])->toContain('°C')
        ->and($corrected['text'])->toContain('≤')
        ->and($corrected['text'])->toContain('≥')
        ->and($corrected['text'])->toContain('⇔')
        ->and($corrected['text'])->toContain('≈')
        ->and($corrected['count'])->toBeGreaterThanOrEqual(6);

    // 2. tableCalculateColumn min and max
    $studio = Livewire::test(NaskahStudioApp::class)
        ->set('blocks', [
            [
                'type' => 'table',
                'table' => [
                    'rows' => [
                        [['text' => 'Tahun'], ['text' => 'Skor']],
                        [['text' => '2021'], ['text' => '70']],
                        [['text' => '2022'], ['text' => '90']],
                        [['text' => '2023'], ['text' => '50']],
                    ],
                    'header' => true,
                ],
            ],
        ]);

    // Test MIN
    $studio->call('tableCalculateColumn', 0, 1, 'min');
    $rowsAfterMin = $studio->get('blocks')[0]['table']['rows'];
    expect(end($rowsAfterMin)[1]['text'])->toBe('50');

    // Test MAX
    $studio->call('tableCalculateColumn', 0, 1, 'max');
    $rowsAfterMax = $studio->get('blocks')[0]['table']['rows'];
    expect(end($rowsAfterMax)[1]['text'])->toBe('90');
});

it('exports connected projects portal diagnostics and direct JSON studio streams via web routes', function (): void {
    ExternalClient::factory()->create([
        'name' => 'Agency Web Export',
        'target_url' => 'https://agency.example.com',
        'status' => 'online',
    ]);

    // 1. /connected-projects/export/csv
    $responseCsv = $this->get('/connected-projects/export/csv');
    $responseCsv->assertSuccessful();
    expect($responseCsv->headers->get('content-type'))->toContain('text/csv');

    // 2. /connected-projects/export/json
    $responseJson = $this->get('/connected-projects/export/json');
    $responseJson->assertSuccessful();
    expect($responseJson->headers->get('content-type'))->toContain('application/json');
    $decodedJson = json_decode($responseJson->streamedContent(), true);
    expect($decodedJson['total_clients'])->toBeGreaterThanOrEqual(1);

    // 3. /studio/export/{id}/json
    $doc = NaskahDocument::query()->create([
        'title' => 'Naskah Direct Stream JSON',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Konten naskah langsung.'],
        ],
        'word_count' => 3,
    ]);

    $responseStudio = $this->get("/studio/export/{$doc->id}/json");
    $responseStudio->assertSuccessful();
    expect($responseStudio->headers->get('content-type'))->toContain('application/json');
    $decodedDoc = json_decode($responseStudio->streamedContent(), true);
    expect($decodedDoc['title'])->toBe('Naskah Direct Stream JSON');
});

it('exports HEART and AARRR framework KPI metrics to CSV and JSON formats', function (): void {
    $project = makeProject(['title' => 'Proyek Uji KPI HEART AARRR']);

    // 1. HEART Widget export
    $heartWidget = Livewire::test(HeartKpiTelemetryWidget::class, ['projectId' => $project->id]);

    $heartCsv = $heartWidget->instance()->exportMetrics('csv');
    expect($heartCsv)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $heartCsv->sendContent();
    $csvOutput = ob_get_clean();
    expect($csvOutput)->toContain('Metrik');

    $heartJson = $heartWidget->instance()->exportMetrics('json');
    expect($heartJson)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $heartJson->sendContent();
    $jsonOutput = ob_get_clean();
    $heartDecoded = json_decode((string) $jsonOutput, true);
    expect($heartDecoded['framework'])->toBe('HEART');

    // 2. AARRR Funnel Widget export
    $aarrrWidget = Livewire::test(AarrrFunnelWidget::class, ['projectId' => $project->id]);

    $aarrrJson = $aarrrWidget->instance()->exportMetrics('json');
    expect($aarrrJson)->toBeInstanceOf(StreamedResponse::class);

    ob_start();
    $aarrrJson->sendContent();
    $aarrrOutput = ob_get_clean();
    $aarrrDecoded = json_decode((string) $aarrrOutput, true);
    expect($aarrrDecoded['framework'])->toBe('AARRR');
});

it('exports xishie assistant transcripts in markdown, json, and txt formats', function (): void {
    $project = makeProject(['title' => 'Proyek Uji Asisten Ekspor']);

    $component = Livewire::test(XishieAssistantPage::class, ['project' => $project->id]);
    $component->set('log', [
        ['role' => 'user', 'content' => 'Bagaimana cara memvalidasi hipotesis?'],
        ['role' => 'assistant', 'content' => 'Gunakan uji signifikansi empiris dan metrik evaluasi yang tepat.'],
    ]);

    // 1. Markdown (.md)
    $mdResponse = $component->instance()->exportTranscript('markdown');
    expect($mdResponse)->toBeInstanceOf(StreamedResponse::class);
    ob_start();
    $mdResponse->sendContent();
    $mdOutput = ob_get_clean();
    expect($mdOutput)->toContain('Transkrip Sesi Xishie Assistant')
        ->and($mdOutput)->toContain('Proyek Uji Asisten Ekspor')
        ->and($mdOutput)->toContain('uji signifikansi empiris');

    // 2. JSON (.json)
    $jsonResponse = $component->instance()->exportTranscript('json');
    expect($jsonResponse)->toBeInstanceOf(StreamedResponse::class);
    ob_start();
    $jsonResponse->sendContent();
    $jsonOutput = ob_get_clean();
    $jsonDecoded = json_decode((string) $jsonOutput, true);
    expect($jsonDecoded['project']['title'])->toBe('Proyek Uji Asisten Ekspor')
        ->and($jsonDecoded['total_messages'])->toBe(2)
        ->and($jsonDecoded['messages'][0]['content'])->toContain('memvalidasi hipotesis');

    // 3. Plain Text (.txt)
    $txtResponse = $component->instance()->exportTranscript('txt');
    expect($txtResponse)->toBeInstanceOf(StreamedResponse::class);
    ob_start();
    $txtResponse->sendContent();
    $txtOutput = ob_get_clean();
    expect($txtOutput)->toContain('TRANSKRIP PERCAKAPAN XISHIE ASSISTANT')
        ->and($txtOutput)->toContain('[Peneliti]')
        ->and($txtOutput)->toContain('[Xishie Assistant]');
});

it('executes /daftar-isi and /daftar-pustaka slash commands with structure and formatted bibliography', function (): void {
    $project = makeProject(['title' => 'Riset Evaluasi Algoritma']);
    seedReferences($project);

    $service = app(XishieAssistantService::class);

    // 1. /daftar-isi
    $tocResult = $service->handle($project, '/daftar-isi');
    expect($tocResult['command'])->toBe('/daftar-isi')
        ->and($tocResult['reply'])->toContain('Bagan Struktur & Daftar Isi Naskah')
        ->and($tocResult['reply'])->toContain('Abstract')
        ->and($tocResult['reply'])->toContain('Introduction')
        ->and($tocResult['data']['sections'])->toBeArray();

    // Alias /toc
    $aliasToc = $service->handle($project, '/toc');
    expect($aliasToc['command'])->toBe('/daftar-isi');

    // 2. /daftar-pustaka
    $bibResult = $service->handle($project, '/daftar-pustaka');
    expect($bibResult['command'])->toBe('/daftar-pustaka')
        ->and($bibResult['reply'])->toContain('Daftar Pustaka Terformat')
        ->and($bibResult['data']['count'])->toBeGreaterThanOrEqual(1);

    // Alias /bibliografi
    $aliasBib = $service->handle($project, '/bibliografi');
    expect($aliasBib['command'])->toBe('/daftar-pustaka');
});

it('applies law governance and fintech economics templates in onboarding wizard', function (): void {
    $component = Livewire::test(ProjectOnboardingWizard::class);

    // 1. Law & Governance
    $component->call('applyTemplate', 'law_governance')
        ->assertSuccessful();

    $data = $component->get('data');
    expect($data['title'])->toContain('Perlindungan Data Pribadi')
        ->and($data['target_tier'])->toBe('SINTA 1')
        ->and($data['citation_style'])->toBe('harvard');

    // 2. FinTech & Economics
    $component->call('applyTemplate', 'fintech_economics')
        ->assertSuccessful();

    $data = $component->get('data');
    expect($data['title'])->toContain('Central Bank Digital Currency')
        ->and($data['target_tier'])->toBe('Q1')
        ->and($data['citation_style'])->toBe('harvard');
});

it('loads project naskah blocks automatically when project query parameter is passed to naskah studio', function (): void {
    $project = makeProject(['title' => 'Proyek Naskah Studio Query']);
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [
            ['key' => 'intro', 'heading' => 'Pendahuluan Query', 'content' => 'Paragraf pembuka otomatis masuk ke canvas studio.'],
        ],
        'status' => StageStatus::IN_PROGRESS,
    ])->save();

    $studio = Livewire::test(NaskahStudioApp::class, [
        'project' => $project->id,
        'stage' => ProjectStage::DRAFT->value,
    ]);
    expect($studio->get('sourceProjectId'))->toBe($project->id)
        ->and($studio->get('sourceType'))->toBe('project')
        ->and($studio->get('docTitle'))->toContain($project->title)
        ->and(count($studio->get('blocks')))->toBeGreaterThanOrEqual(1);
});

it('retrieves paragraphs for a specific page and customizes paragraph attributes per page in studio', function (): void {
    $studio = Livewire::test(NaskahStudioApp::class);
    $instance = $studio->instance();

    // 1. Ambil paragraf di Halaman 1
    $page1Paras = $instance->getPageParagraphs(1);
    expect($page1Paras)->toBeArray()
        ->and(count($page1Paras))->toBeGreaterThanOrEqual(1);

    $firstParaGlobalIndex = $page1Paras[0]['globalIndex'];

    // 2. Kustomisasi paragraf tertentu pada Halaman 1
    $success = $instance->customPageParagraph(1, 0, [
        'align' => 'justify',
        'firstLineIndent' => true,
        'lineSpacing' => 1.75,
        'spacingBefore' => 12.0,
        'spacingAfter' => 6.0,
    ]);
    expect($success)->toBeTrue();

    $blocks = $instance->blocks;
    expect($blocks[$firstParaGlobalIndex]['align'])->toBe('justify')
        ->and($blocks[$firstParaGlobalIndex]['firstLineIndent'])->toBeTrue()
        ->and($blocks[$firstParaGlobalIndex]['lineSpacing'])->toBe(1.75)
        ->and($blocks[$firstParaGlobalIndex]['spacingBefore'])->toBe(12.0)
        ->and($blocks[$firstParaGlobalIndex]['spacingAfter'])->toBe(6.0);

    // 3. Uji blockInlineStyle() menghasilkan CSS yang tepat
    $styleString = $instance->blockInlineStyle($blocks[$firstParaGlobalIndex]);
    expect($styleString)->toContain('text-align: justify')
        ->and($styleString)->toContain('text-indent: 1.27cm')
        ->and($styleString)->toContain('line-height: 1.75')
        ->and($styleString)->toContain('margin-top: 12pt')
        ->and($styleString)->toContain('margin-bottom: 6pt');

    // 4. Sisipkan paragraf kustom baru ke Halaman 1
    $newIndex = $instance->insertCustomParagraphOnPage(1, 'Paragraf empiris kustom baru.', [
        'align' => 'center',
        'calloutType' => 'theorem',
    ], 'start');
    expect($newIndex)->toBeInt();
    expect($instance->blocks[$newIndex]['text'])->toBe('Paragraf empiris kustom baru.')
        ->and($instance->blocks[$newIndex]['align'])->toBe('center');

    // 5. Format seluruh paragraf pada Halaman 1 secara kolektif
    $updatedCount = $instance->customAllPageParagraphs(1, [
        'align' => 'justify',
        'lineSpacing' => 1.5,
    ]);
    expect($updatedCount)->toBeGreaterThanOrEqual(2);

    // 6. Uji alur dialog Paragraph Customizer
    $instance->openParagraphCustomizer(1, 0);
    expect($instance->dialog)->toBe('paragraphCustomizer')
        ->and($instance->customParaPage)->toBe(1);

    $instance->customParaText = 'Teks disunting melalui dialog kustomisasi paragraf.';
    $instance->customParaAlign = 'right';
    $instance->applyParagraphCustomizer(false);
    expect($instance->dialog)->toBe('');
});

it('customizes section paragraphs in academic workspace editor', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [
            [
                'key' => 'intro',
                'heading' => 'Pendahuluan',
                'content' => "Paragraf pertama latar belakang penelitian.\n\nParagraf kedua urgensi rumusan masalah.",
            ],
        ],
        'status' => StageStatus::IN_PROGRESS,
    ])->save();

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);
    $component->call('customSectionParagraphs', 0, ['first_line_indent' => true])
        ->assertSuccessful();

    $sections = $component->get('editorSections');
    expect($sections[0]['content'])->toContain('    Paragraf pertama')
        ->and($sections[0]['content'])->toContain('    Paragraf kedua');
});

it('manages paragraph presets, stats, duplicates, and moves on studio pages', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Naskah Uji Presets Paragraf',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf pertama analisis sistem cerdas.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf kedua perbandingan performa latensi jaringan.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf ketiga evaluasi efisiensi energi.'],
        ],
        'word_count' => 18,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji statistik paragraf per halaman
    $stats = $instance->getPageParagraphStats(1);
    expect($stats['paragraphCount'])->toBe(3)
        ->and($stats['words'])->toBeGreaterThan(10)
        ->and($stats['characters'])->toBeGreaterThan(50);

    // 2. Terapkan preset academic_standard (APA/Scopus 1.5 spasi) ke Halaman 1
    $formattedCount = $instance->applyParagraphPresetToPage(1, 'academic_standard');
    expect($formattedCount)->toBe(3);
    expect($instance->blocks[0]['align'])->toBe('justify')
        ->and($instance->blocks[0]['lineSpacing'])->toBe(1.5)
        ->and($instance->blocks[0]['firstLineIndent'])->toBeTrue();

    // 3. Terapkan preset journal_ieee (IEEE 1.0 spasi)
    $formattedIeee = $instance->applyParagraphPresetToPage(1, 'journal_ieee');
    expect($formattedIeee)->toBe(3);
    expect($instance->blocks[0]['lineSpacing'])->toBe(1.0);

    // 4. Uji duplikasi paragraf di Halaman 1
    $dupIndex = $instance->duplicatePageParagraph(1, 0);
    expect($dupIndex)->toBeInt();
    expect(count($instance->blocks))->toBe(4);
    expect($instance->blocks[1]['text'])->toBe('Paragraf pertama analisis sistem cerdas.');

    // 5. Uji pemindahan paragraf ke bawah (down)
    $moved = $instance->movePageParagraph(1, 0, 1);
    expect($moved)->toBeTrue();

    // 6. Uji pemuatan preset ke modal customizer
    $instance->loadPresetIntoCustomizer('thesis_dikti');
    expect($instance->customParaLineSpacing)->toBe(2.0)
        ->and($instance->customParaAlign)->toBe('justify')
        ->and($instance->customParaFirstLineIndent)->toBeTrue();
});

it('executes paragraphs inspection and format-paragraph action via artisan naskah:manage', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen CLI Paragraph Format',
        'blocks' => [
            ['type' => 'heading', 'level' => 1, 'text' => 'Bab I Pendahuluan'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf CLI pertama untuk verifikasi pengujian terminal.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf CLI kedua untuk verifikasi format preset otomatis.'],
        ],
        'word_count' => 17,
    ]);

    // 1. Uji inspeksi paragraf pada halaman 1 via CLI
    $this->artisan('naskah:manage', [
        'action' => 'paragraphs',
        'documentId' => $doc->id,
        '--page' => 1,
    ])
        ->expectsOutputToContain('DAFTAR PARAGRAF HALAMAN #1')
        ->expectsOutputToContain('Paragraf CLI pertama')
        ->assertSuccessful();

    // 2. Uji format paragraf via CLI menggunakan preset journal_ieee
    $this->artisan('naskah:manage', [
        'action' => 'format-paragraph',
        'documentId' => $doc->id,
        '--page' => 1,
        '--preset' => 'journal_ieee',
    ])
        ->expectsOutputToContain('Berhasil memformat 2 paragraf pada halaman #1')
        ->assertSuccessful();

    $doc->refresh();
    $updatedBlocks = $doc->blocks;
    expect((float) $updatedBlocks[1]['lineSpacing'])->toBe(1.0)
        ->and($updatedBlocks[1]['align'])->toBe('justify')
        ->and((float) $updatedBlocks[2]['lineSpacing'])->toBe(1.0);
});

it('executes /format-paragraf slash command for academic typography and density audit', function (): void {
    $project = makeProject(['document_type' => 'skripsi']);
    $assistant = app(XishieAssistantService::class);

    // 1. Eksekusi tanpa argumen -> panduan format baku & daftar preset
    $resultGuide = $assistant->handle($project, '/format-paragraf');
    expect($resultGuide['command'])->toBe('/format-paragraf')
        ->and($resultGuide['reply'])->toContain('Panduan Standar Kaidah Paragraf Akademik')
        ->and($resultGuide['reply'])->toContain('academic_standard')
        ->and($resultGuide['reply'])->toContain('journal_ieee')
        ->and($resultGuide['reply'])->toContain('thesis_dikti')
        ->and($resultGuide['data']['presets'])->toContain('academic_standard');

    // 2. Eksekusi dengan argumen teks paragraf -> audit kepadatan dan kalimat
    $sampleParagraph = 'Teknologi kecerdasan artifisial berkembang pesat dalam dekade terakhir. Berbagai algoritma pembelajaran mesin telah diterapkan pada optimasi pemrosesan data ilmiah berdimensi tinggi. Hasil pengujian menunjukkan peningkatan akurasi hingga dua puluh persen dibandingkan metode konvensional.';
    $resultAudit = $assistant->handle($project, "/paragraf {$sampleParagraph}");
    expect($resultAudit['command'])->toBe('/format-paragraf')
        ->and($resultAudit['reply'])->toContain('Analisis & Kaidah Format Paragraf')
        ->and($resultAudit['reply'])->toContain('Jumlah Kata')
        ->and($resultAudit['reply'])->toContain('Jumlah Kalimat')
        ->and($resultAudit['data']['sentences'])->toBe(3);

    // 3. Verifikasi parsing alias
    expect(SlashCommand::tryParse('/format-paragraf'))->toBe(SlashCommand::FORMAT_PARAGRAF)
        ->and(SlashCommand::tryParse('/paragraf'))->toBe(SlashCommand::FORMAT_PARAGRAF)
        ->and(SlashCommand::tryParse('/aturan-paragraf'))->toBe(SlashCommand::FORMAT_PARAGRAF);
});

it('cleans spacing, transforms casing, merges, splits, and inserts paragraphs per page', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Pengujian Manipulasi Paragraf Hal',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf   pertama   dengan   spasi  ganda , dan tanda baca yang salah .'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'paragraf kedua huruf kecil semua. kalimat kedua di sini.'],
        ],
        'word_count' => 18,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji cleanPageParagraphSpaces
    $cleanedCount = $instance->cleanPageParagraphSpaces(1);
    expect($cleanedCount)->toBe(1);
    expect($instance->blocks[0]['text'])->toBe('Paragraf pertama dengan spasi ganda, dan tanda baca yang salah.');

    // 2. Uji transformPageParagraphCase (Sentence Case)
    $transformedCount = $instance->transformPageParagraphCase(1, 'sentence');
    expect($transformedCount)->toBeGreaterThanOrEqual(1);
    expect($instance->blocks[1]['text'])->toContain('Paragraf kedua huruf kecil semua.');

    // 3. Uji insertPageParagraphAt (sisipkan setelah paragraf 0)
    $insertedGlobalIndex = $instance->insertPageParagraphAt(1, 0, 'Paragraf sisipan baru.', ['align' => 'center'], 'after');
    expect($insertedGlobalIndex)->toBe(1);
    expect($instance->blocks[1]['text'])->toBe('Paragraf sisipan baru.')
        ->and($instance->blocks[1]['align'])->toBe('center');

    // 4. Uji splitPageParagraph (pecah paragraf)
    $instance->blocks[0]['text'] = 'Kalimat pertama bagian awal. Kalimat kedua bagian akhir.';
    $splitSuccess = $instance->splitPageParagraph(1, 0);
    expect($splitSuccess)->toBeTrue();
    expect($instance->blocks[0]['text'])->toBe('Kalimat pertama bagian awal.')
        ->and($instance->blocks[1]['text'])->toBe('Kalimat kedua bagian akhir.');

    // 5. Uji mergePageParagraphs (gabungkan paragraf 0 dan 1)
    $mergeSuccess = $instance->mergePageParagraphs(1, 0);
    expect($mergeSuccess)->toBeTrue();
    expect($instance->blocks[0]['text'])->toContain('Kalimat pertama bagian awal. Kalimat kedua bagian akhir.');
});

it('cleans spacing and transforms casing in academic workspace section paragraphs', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [
            [
                'key' => 'methods',
                'heading' => 'Metode Penelitian',
                'content' => "data  primer   diperoleh   melalui   survei  ,  wawancara  . \n\nanalisis  dilakukan   secara   kuantitatif  .",
            ],
        ],
        'status' => StageStatus::IN_PROGRESS,
    ])->save();

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);

    // 1. Bersihkan spasi
    $component->call('cleanSectionParagraphs', 0)
        ->assertSuccessful();

    $sectionsAfterClean = $component->get('editorSections');
    expect($sectionsAfterClean[0]['content'])->toContain('data primer diperoleh melalui survei, wawancara.');

    // 2. Ubah kapitalisasi ke sentence case
    $component->call('transformSectionParagraphsCase', 0, 'sentence')
        ->assertSuccessful();

    $sectionsAfterCase = $component->get('editorSections');
    expect($sectionsAfterCase[0]['content'])->toContain('Data primer')
        ->and($sectionsAfterCase[0]['content'])->toContain('Analisis dilakukan');
});

it('executes clean-page action via artisan naskah:manage to normalize paragraph spacing', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen CLI Clean Spacing',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Teks   CLI   dengan   spasi   ganda  .'],
        ],
        'word_count' => 6,
    ]);

    $this->artisan('naskah:manage', [
        'action' => 'clean-page',
        'documentId' => $doc->id,
        '--page' => 1,
    ])
        ->expectsOutputToContain('Berhasil merapikan spasi pada 1 paragraf di halaman #1')
        ->assertSuccessful();

    $doc->refresh();
    expect($doc->blocks[0]['text'])->toBe('Teks CLI dengan spasi ganda.');
});

it('performs find-replace, compliance audit, export, and import of page paragraphs', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Uji Find Replace Dan Audit',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kecerdasan buatan berbasis deep learning memberikan efisiensi tinggi.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Penerapan deep learning pada diagnostik medis sangat krusial.'],
        ],
        'word_count' => 17,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji findAndReplaceOnPage
    $replaceCount = $instance->findAndReplaceOnPage(1, 'deep learning', 'machine learning');
    expect($replaceCount)->toBe(2);
    expect($instance->blocks[0]['text'])->toContain('machine learning')
        ->and($instance->blocks[1]['text'])->toContain('machine learning');

    // 2. Uji auditPageParagraphCompliance
    $audit = $instance->auditPageParagraphCompliance(1);
    expect($audit['score'])->toBeInt()
        ->and($audit['paragraphCount'])->toBe(2)
        ->and($audit['grade'])->toBeString();

    // 3. Uji exportPageParagraphs (Markdown & Plain text)
    $mdExport = $instance->exportPageParagraphs(1, 'markdown');
    expect($mdExport)->toContain('machine learning');

    $txtExport = $instance->exportPageParagraphs(1, 'txt');
    expect($txtExport)->toContain('diagnostik medis');

    // 4. Uji importParagraphsToPage
    $importedCount = $instance->importParagraphsToPage(1, "Alinea impor pertama.\n\nAlinea impor kedua.");
    expect($importedCount)->toBe(2);
    expect(count($instance->blocks))->toBe(4);
});

it('performs find and replace in academic workspace section', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [
            [
                'key' => 'discussion',
                'heading' => 'Pembahasan',
                'content' => 'Metode lama menghasilkan akurasi rendah. Metode lama tidak efisien.',
            ],
        ],
        'status' => StageStatus::IN_PROGRESS,
    ])->save();

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);
    $component->call('findAndReplaceInSection', 0, 'Metode lama', 'Metode terbarukan')
        ->assertSuccessful();

    $sections = $component->get('editorSections');
    expect($sections[0]['content'])->toContain('Metode terbarukan menghasilkan akurasi rendah')
        ->and($sections[0]['content'])->not->toContain('Metode lama');
});

it('executes audit-page and find-replace actions via artisan naskah:manage', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen CLI Audit Dan Replace',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Model lama terbukti memiliki latensi tinggi pada inferensi real-time.'],
        ],
        'word_count' => 10,
    ]);

    // 1. CLI audit-page
    $this->artisan('naskah:manage', [
        'action' => 'audit-page',
        'documentId' => $doc->id,
        '--page' => 1,
    ])
        ->expectsOutputToContain('AUDIT KEPATUHAN PARAGRAF HALAMAN #1')
        ->assertSuccessful();

    // 2. CLI find-replace
    $this->artisan('naskah:manage', [
        'action' => 'find-replace',
        'documentId' => $doc->id,
        '--page' => 1,
        '--search' => 'Model lama',
        '--replace' => 'Model mutakhir',
    ])
        ->expectsOutputToContain("Berhasil mengganti 1 kemunculan 'Model lama' pada halaman #1")
        ->assertSuccessful();

    $doc->refresh();
    expect($doc->blocks[0]['text'])->toBe('Model mutakhir terbukti memiliki latensi tinggi pada inferensi real-time.');
});

it('polishes, cites, and toggles layout overrides on page paragraphs in naskah studio', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Layout Override Dan Sitasi',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'eksperimen dilakukan dimana variabel kontrol diukur.'],
        ],
        'word_count' => 7,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji polishPageParagraph (kapitalisasi awal kalimat & perbaikan kohesi)
    $polished = $instance->polishPageParagraph(1, 0);
    expect($polished)->toBeTrue();
    expect($instance->blocks[0]['text'])->toStartWith('Eksperimen');

    // 2. Uji citePageParagraph dengan format APA
    $citedApa = $instance->citePageParagraph(1, 0, 'Sugiyono, 2021', 'apa');
    expect($citedApa)->toBeTrue();
    expect($instance->blocks[0]['text'])->toContain('(Sugiyono, 2021).');

    // 3. Uji citePageParagraph dengan format IEEE
    $citedIeee = $instance->citePageParagraph(1, 0, '1', 'ieee');
    expect($citedIeee)->toBeTrue();
    expect($instance->blocks[0]['text'])->toContain('[1].');

    // 4. Uji setPageParagraphColumns & togglePageParagraphLineNumbers
    $instance->setPageParagraphColumns(1, 2);
    expect($instance->pageOverrides[1]['columns'])->toBe(2);

    $instance->togglePageParagraphLineNumbers(1);
    expect($instance->pageOverrides[1]['lineNumbers'])->toBeTrue();

    $instance->togglePageParagraphLineNumbers(1);
    expect($instance->pageOverrides[1]['lineNumbers'])->toBeFalse();
});

it('binds citations to section paragraphs in academic workspace', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [
            [
                'key' => 'methodology',
                'heading' => 'Metodologi Penelitian',
                'content' => 'Pendekatan kuantitatif digunakan untuk menguji hipotesis.',
            ],
        ],
        'status' => StageStatus::IN_PROGRESS,
    ])->save();

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);
    $component->call('citeSectionParagraph', 0, 'Creswell, 2018', 'apa')
        ->assertSuccessful();

    $sections = $component->get('editorSections');
    expect($sections[0]['content'])->toContain('(Creswell, 2018).');

    $component->call('citeSectionParagraph', 0, '2', 'ieee')
        ->assertSuccessful();

    $sectionsAfterIeee = $component->get('editorSections');
    expect($sectionsAfterIeee[0]['content'])->toContain('[2].');
});

it('executes export-page action via artisan naskah:manage to export single page content', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Uji CLI Ekspor Halaman',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Konten penting halaman pertama yang akan diekspor.'],
        ],
        'word_count' => 7,
    ]);

    // Ekspor format Markdown ke output console
    $this->artisan('naskah:manage', [
        'action' => 'export-page',
        'documentId' => $doc->id,
        '--page' => 1,
        '--format' => 'md',
    ])
        ->expectsOutputToContain('Konten penting halaman pertama yang akan diekspor.')
        ->assertSuccessful();

    // Ekspor format Plain Text ke output console
    $this->artisan('naskah:manage', [
        'action' => 'export-page',
        'documentId' => $doc->id,
        '--page' => 1,
        '--format' => 'txt',
    ])
        ->expectsOutputToContain('Konten penting halaman pertama yang akan diekspor.')
        ->assertSuccessful();
});

it('manages drop cap, alignment, footnotes, paraphrasing, and reordering of page paragraphs', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Uji DropCap Dan Footnote',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Banyak sekali data yang dikumpulkan dimana hasilnya sangat penting.', 'align' => 'left'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Eksperimen kedua membuktikan bahwa hipotesis awal valid.', 'align' => 'left'],
        ],
        'word_count' => 17,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji setPageParagraphAlignment (mengubah seluruh paragraf halaman ke justify)
    $alignedCount = $instance->setPageParagraphAlignment(1, 'justify');
    expect($alignedCount)->toBe(2);
    expect($instance->blocks[0]['align'])->toBe('justify')
        ->and($instance->blocks[1]['align'])->toBe('justify');

    // 2. Uji togglePageParagraphDropCap
    $dropCap = $instance->togglePageParagraphDropCap(1, 0);
    expect($dropCap)->toBeTrue();
    expect($instance->blocks[0]['dropCap'])->toBeTrue();

    // 3. Uji paraphrasePageParagraph
    $paraphrased = $instance->paraphrasePageParagraph(1, 0);
    expect($paraphrased)->toBeTrue();
    expect($instance->blocks[0]['text'])->toContain('sejumlah besar')
        ->and($instance->blocks[0]['text'])->toContain('esensial dan krusial');

    // 4. Uji insertPageParagraphFootnote
    $footnoteAdded = $instance->insertPageParagraphFootnote(1, 0, 'Lihat rincian survei pada Lampiran B.');
    expect($footnoteAdded)->toBeTrue();
    expect($instance->blocks[0]['text'])->toContain('[^1].');
    expect(count($instance->blocks))->toBe(3);
    expect($instance->blocks[1]['type'])->toBe('footnote-ref')
        ->and($instance->blocks[1]['text'])->toContain('Lampiran B');

    // 5. Uji reorderPageParagraphs
    $instance->blocks = [
        ['type' => 'paragraph', 'level' => 0, 'text' => 'Alinea Awal Pertama.'],
        ['type' => 'paragraph', 'level' => 0, 'text' => 'Alinea Kedua Berikutnya.'],
    ];
    $reordered = $instance->reorderPageParagraphs(1, [1, 0]);
    expect($reordered)->toBeTrue();
    expect($instance->blocks[0]['text'])->toBe('Alinea Kedua Berikutnya.')
        ->and($instance->blocks[1]['text'])->toBe('Alinea Awal Pertama.');
});

it('paraphrases and inserts footnotes into section paragraphs in academic workspace', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [
            [
                'key' => 'results',
                'heading' => 'Hasil dan Temuan',
                'content' => 'Banyak sekali responden yang menyatakan bahwa fitur ini sangat bagus.',
            ],
        ],
        'status' => StageStatus::IN_PROGRESS,
    ])->save();

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);

    // 1. Uji paraphraseSectionParagraph
    $component->call('paraphraseSectionParagraph', 0)
        ->assertSuccessful();

    $sections = $component->get('editorSections');
    expect($sections[0]['content'])->toContain('sejumlah besar')
        ->and($sections[0]['content'])->toContain('optimal dan signifikan');

    // 2. Uji insertSectionFootnote
    $component->call('insertSectionFootnote', 0, 'Data diolah dengan SPSS versi 28.')
        ->assertSuccessful();

    $sectionsAfterFootnote = $component->get('editorSections');
    expect($sectionsAfterFootnote[0]['content'])->toContain('[^1].')
        ->and($sectionsAfterFootnote[0]['content'])->toContain('[^1]: Data diolah dengan SPSS versi 28.');
});

it('executes polish-page and align-page actions via artisan naskah:manage', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen CLI Polish Dan Align',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'pengukuran sensor dilakukan dengan presisi tinggi  .', 'align' => 'left'],
        ],
        'word_count' => 7,
    ]);

    // 1. Uji align-page
    $this->artisan('naskah:manage', [
        'action' => 'align-page',
        'documentId' => $doc->id,
        '--page' => 1,
        '--align' => 'justify',
    ])
        ->expectsOutputToContain("Berhasil mengatur perataan 'justify' pada 1 paragraf di halaman #1")
        ->assertSuccessful();

    $doc->refresh();
    expect($doc->blocks[0]['align'])->toBe('justify');

    // 2. Uji polish-page
    $this->artisan('naskah:manage', [
        'action' => 'polish-page',
        'documentId' => $doc->id,
        '--page' => 1,
    ])
        ->expectsOutputToContain('Berhasil memoles 1 paragraf pada halaman #1')
        ->assertSuccessful();

    $doc->refresh();
    expect($doc->blocks[0]['text'])->toStartWith('Pengukuran sensor');
});

it('interacts with naskah studio API endpoints for page export, audit, clean, and format', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Naskah API Studio',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Analisis data kuantitatif dilakukan dengan teliti   .'],
        ],
        'word_count' => 7,
    ]);

    // 1. Uji GET /api/v1/naskah/documents/{id}/export-page
    $this->getJson("/api/v1/naskah/documents/{$doc->id}/export-page?page=1&format=md")
        ->assertOk()
        ->assertHeader('Content-Type', 'text/markdown; charset=UTF-8');

    // 2. Uji POST /api/v1/naskah/documents/{id}/audit-page
    $this->postJson("/api/v1/naskah/documents/{$doc->id}/audit-page", ['page' => 1])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonStructure(['data' => ['page', 'score', 'grade', 'paragraphs_count', 'words_count']]);

    // 3. Uji POST /api/v1/naskah/documents/{id}/clean-page
    $this->postJson("/api/v1/naskah/documents/{$doc->id}/clean-page", ['page' => 1])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('cleaned_count', 1);

    // 4. Uji POST /api/v1/naskah/documents/{id}/format-paragraph
    $this->postJson("/api/v1/naskah/documents/{$doc->id}/format-paragraph", [
        'page' => 1,
        'preset' => 'academic_standard',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('preset', 'academic_standard')
        ->assertJsonPath('updated_count', 1);

    $doc->refresh();
    expect($doc->blocks[0]['align'])->toBe('justify')
        ->and($doc->blocks[0]['lineSpacing'])->toBe(1.5);
});

it('formats paragraph via external integration API and validates JSON outputs in CLI commands', function (): void {
    $client = ExternalClient::create([
        'name' => 'External Testing Agent',
        'client_slug' => 'external-testing-agent',
        'api_key' => 'xsh_test_api_key_12345',
        'target_url' => 'http://localhost:8208',
        'allowed_models' => ['*'],
        'status' => 'online',
        'is_active' => true,
    ]);

    // 1. Uji POST /api/v1/external/format-paragraph
    $this->withHeaders(['X-Api-Key' => 'xsh_test_api_key_12345'])
        ->postJson('/api/v1/external/format-paragraph', [
            'text' => 'Teks   paragraf   eksternal   dengan   spasi   berlebih.',
            'preset' => 'journal_ieee',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('preset', 'journal_ieee')
        ->assertJsonPath('rules.align', 'justify')
        ->assertJsonPath('rules.line_spacing', 1);

    // 2. Uji CLI artisan xishie:ping-clients --json
    $this->artisan('xishie:ping-clients', ['--json' => true])
        ->expectsOutputToContain('"total_clients"')
        ->assertSuccessful();

    // 3. Uji CLI artisan omnigensi:graph --json --validate
    $this->artisan('omnigensi:graph', ['--json' => true, '--validate' => true])
        ->expectsOutputToContain('"valid": true')
        ->assertSuccessful();
});

it('executes /catatan-kaki slash command for Chicago and Turabian academic guidance', function (): void {
    $project = makeProject(['title' => 'Proyek Uji Catatan Kaki']);
    $service = app(XishieAssistantService::class);

    // 1. Eksekusi tanpa argumen (panduan umum)
    $resGeneral = $service->handle($project, '/catatan-kaki');
    expect($resGeneral['command'])->toBe('/catatan-kaki')
        ->and($resGeneral['reply'])->toContain('Format Chicago / Turabian')
        ->and($resGeneral['data']['style'])->toBe('chicago_turabian');

    // 2. Eksekusi dengan argumen kutipan spesifik
    $resWithArg = $service->handle($project, '/catatan-kaki Teori efisiensi pasar Eugene Fama');
    expect($resWithArg['command'])->toBe('/catatan-kaki')
        ->and($resWithArg['reply'])->toContain('Teori efisiensi pasar Eugene Fama [^1]')
        ->and($resWithArg['data']['footnote_marker'])->toBe('[^1]');
});

it('exports literature matrix, response to reviewers, catalog, recommendations, and projects in html and markdown formats', function (): void {
    $project = makeProject(['title' => 'Proyek Uji Ekspor Multi Format']);
    seedReferences($project);

    // 1. Uji LiteratureMatrixBuilder::toHtml dan AcademicWorkspacePage exportLiteratureMatrix('html')
    $builder = app(LiteratureMatrixBuilder::class);
    $matrixHtml = $builder->toHtml($project);
    expect($matrixHtml)->toContain('<!DOCTYPE html>')
        ->and($matrixHtml)->toContain('Tabel Matriks Literatur (State-of-the-Art)')
        ->and($matrixHtml)->toContain($project->title);

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportLiteratureMatrix', 'html')
        ->assertSuccessful();

    // 2. Uji PeerCriticAgent::renderResponseMatrixHtml dan SmartRevisionCenterPage downloadResponseMatrix('html')
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'reviewer_jurnal',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => 'Perbaiki gap riset pada pendahuluan',
        'action_items' => [
            ['id' => 1, 'task' => 'Perbaiki gap riset', 'section_key' => 'pendahuluan', 'status' => 'pending'],
        ],
    ]);

    $criticAgent = app(PeerCriticAgent::class);
    $respHtml = $criticAgent->renderResponseMatrixHtml($revision);
    expect($respHtml)->toContain('<!DOCTYPE html>')
        ->and($respHtml)->toContain('Response to Reviewers Matrix')
        ->and($respHtml)->toContain('Perbaiki gap riset');

    Livewire::test(SmartRevisionCenterPage::class, ['project' => $project->id])
        ->call('downloadResponseMatrix', $revision->id, 'html')
        ->assertSuccessful();

    // 3. Uji VenueCatalogPage::exportCatalog('markdown')
    Livewire::test(VenueCatalogPage::class, ['project' => $project->id])
        ->call('exportCatalog', 'markdown')
        ->assertSuccessful();

    // 4. Uji PlatformMatcherPage::exportRecommendations('html')
    Livewire::test(PlatformMatcherPage::class, ['project' => $project->id])
        ->call('exportRecommendations', 'html')
        ->assertSuccessful();

    // 5. Uji ProjectsPage::exportProjects('markdown')
    Livewire::test(ProjectsPage::class)
        ->call('exportProjects', 'markdown')
        ->assertSuccessful();
});

it('customizes page paragraphs with first line indent, hanging indent, line spacing, highlight, and readability metrics', function (): void {
    $project = makeProject();
    $doc = NaskahDocument::query()->create([
        'project_id' => $project->id,
        'title' => 'Naskah Analisis Spasi dan Indentasi',
        'document_type' => 'thesis',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kajian ini menganalisis dampak digitalisasi pada produktivitas riset akademis di perguruan tinggi.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Daftar pustaka referensi: Doe, J. (2025). Digital Humanities. Jakarta: Pustaka Sains.'],
        ],
        'word_count' => 24,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji setPageParagraphLineSpacing
    $instance->setPageParagraphLineSpacing(1, 1.5);
    expect($instance->pageOverrides[1]['lineSpacing'])->toBe(1.5);

    // 2. Uji togglePageParagraphFirstLineIndent (aktifkan lalu nonaktifkan)
    $toggled = $instance->togglePageParagraphFirstLineIndent(1, 0);
    expect($toggled)->toBeTrue();
    expect($instance->blocks[0]['firstLineIndent'])->toBeTrue();
    expect($instance->blockInlineStyle($instance->blocks[0]))->toContain('text-indent: 1.27cm');

    $toggledOff = $instance->togglePageParagraphFirstLineIndent(1, 0);
    expect($toggledOff)->toBeTrue();
    expect($instance->blocks[0]['firstLineIndent'])->toBeFalse();

    // 3. Uji togglePageParagraphHangingIndent (aktifkan hanging indent untuk referensi/sitasi)
    $hangingOn = $instance->togglePageParagraphHangingIndent(1, 1);
    expect($hangingOn)->toBeTrue();
    expect($instance->blocks[1]['hangingIndent'])->toBeTrue();
    expect($instance->blocks[1]['firstLineIndent'] ?? false)->toBeFalse();
    expect($instance->blockInlineStyle($instance->blocks[1]))->toContain('padding-left: 1.27cm; text-indent: -1.27cm');

    // 4. Uji highlightPageParagraph
    $highlighted = $instance->highlightPageParagraph(1, 0, '#fef08a');
    expect($highlighted)->toBeTrue();
    expect($instance->blocks[0]['backgroundColor'])->toBe('#fef08a');

    // Toggle off highlight
    $unhighlighted = $instance->highlightPageParagraph(1, 0, '#fef08a');
    expect($unhighlighted)->toBeTrue();
    expect(isset($instance->blocks[0]['backgroundColor']))->toBeFalse();

    // 5. Uji getPageParagraphReadability
    $readability = $instance->getPageParagraphReadability(1);
    expect($readability)->toBeArray()
        ->and($readability['page'])->toBe(1)
        ->and($readability['paragraph_count'])->toBe(2)
        ->and($readability['word_count'])->toBeGreaterThan(15)
        ->and($readability['sentence_count'])->toBeGreaterThanOrEqual(2)
        ->and($readability['reading_time_seconds'])->toBeGreaterThanOrEqual(1)
        ->and($readability['readability_level'])->toBeString();
});

it('toggles section paragraph indent and computes section readability in academic workspace', function (): void {
    $project = makeProject();
    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    $stage->update([
        'sections_content' => [
            [
                'key' => 'pendahuluan',
                'heading' => 'Pendahuluan',
                'content' => 'Perkembangan kecerdasan artifisial membawa disrupsi signifikan. Berbagai sektor industri mengalami otomatisasi tingkat tinggi.',
            ],
        ],
    ]);

    $component = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);
    $instance = $component->instance();

    // Uji toggleSectionParagraphFirstLineIndent
    expect($instance->editorSections[0]['firstLineIndent'] ?? false)->toBeFalse();
    $instance->toggleSectionParagraphFirstLineIndent(0);
    expect($instance->editorSections[0]['firstLineIndent'])->toBeTrue();
    $instance->toggleSectionParagraphFirstLineIndent(0);
    expect($instance->editorSections[0]['firstLineIndent'])->toBeFalse();

    // Uji getSectionReadability
    $readability = $instance->getSectionReadability(0);
    expect($readability['section_key'])->toBe('pendahuluan')
        ->and($readability['heading'])->toBe('Pendahuluan')
        ->and($readability['word_count'])->toBe(13)
        ->and($readability['sentence_count'])->toBe(2)
        ->and($readability['avg_words_per_sentence'])->toBe(6.5)
        ->and($readability['readability_level'])->toBe('Optimal');
});

it('manages page line spacing and page readability via artisan cli', function (): void {
    $project = makeProject();
    $doc = NaskahDocument::query()->create([
        'project_id' => $project->id,
        'title' => 'Dokumen CLI Spasi dan Keterbacaan',
        'document_type' => 'article',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kalimat pertama dalam bab pendahuluan untuk pengujian CLI.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kalimat kedua memberikan elaborasi lebih mendalam atas metode.'],
        ],
        'word_count' => 16,
    ]);

    // Uji linespacing-page
    $this->artisan("naskah:manage linespacing-page {$doc->id} --page=1 --spacing=1.5")
        ->assertSuccessful()
        ->expectsOutputToContain("Berhasil mengatur spasi baris '1.5'");

    $doc->refresh();
    expect($doc->blocks[0]['lineSpacing'])->toBe(1.5)
        ->and($doc->blocks[1]['lineSpacing'])->toBe(1.5);

    // Uji readability-page
    $this->artisan("naskah:manage readability-page {$doc->id} --page=1")
        ->assertSuccessful()
        ->expectsOutputToContain('STATISTIK KETERBACAAN HALAMAN #1')
        ->expectsOutputToContain('Total Paragraf: 2')
        ->expectsOutputToContain('Total Kata:');
});

it('exports academic manuscripts in rich text format (RTF) with full layout and bibliography hanging indent', function (): void {
    $project = makeProject(['title' => 'Pengaruh Otomatisasi AI Terhadap Produktivitas Riset']);
    seedReferences($project);

    $exporter = app(ManuscriptExporter::class);
    expect($exporter->isSupported('rtf'))->toBeTrue();

    // 1. Render RTF langsung dari ManuscriptExporter
    $rtf = $exporter->render($project, 'rtf');
    expect($rtf)->toContain('{\\rtf1\\ansi\\deff0')
        ->and($rtf)->toContain('Pengaruh Otomatisasi AI')
        ->and($rtf)->toContain('DAFTAR PUSTAKA')
        ->and($rtf)->toContain('\\fi-720'); // Hanging indent untuk sitasi/bibliografi

    // 2. Unduh via AcademicWorkspacePage
    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportManuscript', 'rtf')
        ->assertSuccessful();
});

it('executes /keterbacaan slash command and aliases for academic readability and word density audit', function (): void {
    $project = makeProject(['title' => 'Proyek Audit Keterbacaan']);
    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    $stage->update([
        'sections_content' => [
            [
                'heading' => 'Pendahuluan',
                'content' => 'Penelitian ini menyelidiki dinamika transformasi digital pada perguruan tinggi negeri. Metodologi yang digunakan adalah pendekatan kualitatif dengan wawancara mendalam.',
            ],
        ],
    ]);

    $service = app(XishieAssistantService::class);

    // 1. Eksekusi /keterbacaan dari stage aktif proyek
    $resDraft = $service->handle($project, '/keterbacaan');
    expect($resDraft['command'])->toBe('/keterbacaan')
        ->and($resDraft['reply'])->toContain('Laporan Analisis Keterbacaan Naskah')
        ->and($resDraft['reply'])->toContain('Jumlah Kata')
        ->and($resDraft['data']['word_count'])->toBeGreaterThan(15)
        ->and($resDraft['data']['sentence_count'])->toBe(2)
        ->and($resDraft['data']['reading_time_seconds'])->toBeGreaterThanOrEqual(1);

    // 2. Eksekusi dengan teks kustom dan alias /readability
    $customText = 'Kajian ini berfokus pada sintesis literatur. Evaluasi empiris dilakukan melalui eksperimen komparatif.';
    $resCustom = $service->handle($project, '/readability '.$customText);
    expect($resCustom['command'])->toBe('/keterbacaan')
        ->and($resCustom['data']['word_count'])->toBe(12)
        ->and($resCustom['data']['sentence_count'])->toBe(2)
        ->and($resCustom['data']['readability_level'])->toBe('Mudah Dibaca (Ringan)');
});

it('applies batch page paragraph indentation via artisan naskah:manage indent-page', function (): void {
    $project = makeProject();
    $doc = NaskahDocument::query()->create([
        'project_id' => $project->id,
        'title' => 'Naskah Uji CLI Indentasi Halaman',
        'document_type' => 'thesis',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Alinea awal pengujian batch indentasi naskah studio.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Alinea penutup pengujian CLI batch indentasi.'],
        ],
        'word_count' => 16,
    ]);

    // 1. Uji indent-page first-line
    $this->artisan("naskah:manage indent-page {$doc->id} --page=1 --type=first-line")
        ->assertSuccessful()
        ->expectsOutputToContain("Berhasil menerapkan indentasi 'first-line'");

    $doc->refresh();
    expect($doc->blocks[0]['firstLineIndent'])->toBeTrue()
        ->and(isset($doc->blocks[0]['hangingIndent']))->toBeFalse();

    // 2. Uji indent-page hanging
    $this->artisan("naskah:manage indent-page {$doc->id} --page=1 --type=hanging")
        ->assertSuccessful()
        ->expectsOutputToContain("Berhasil menerapkan indentasi 'hanging'");

    $doc->refresh();
    expect($doc->blocks[0]['hangingIndent'])->toBeTrue()
        ->and(isset($doc->blocks[0]['firstLineIndent']))->toBeFalse();
});

it('interacts with naskah studio api for readability-page and align-page, and external readability service', function (): void {
    $project = makeProject();
    $doc = NaskahDocument::query()->create([
        'project_id' => $project->id,
        'title' => 'Dokumen API Readability dan Align',
        'document_type' => 'journal',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Kajian teoritis membuktikan efisiensi sistem AI. Hasil pengujian menunjukkan peningkatan performa signifikan.'],
        ],
        'word_count' => 13,
    ]);

    // 1. Uji GET /api/v1/naskah/documents/{id}/readability-page
    $this->getJson("/api/v1/naskah/documents/{$doc->id}/readability-page?page=1")
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('page', 1)
        ->assertJsonPath('paragraph_count', 1)
        ->assertJsonPath('word_count', 12)
        ->assertJsonPath('sentence_count', 2);

    // 2. Uji POST /api/v1/naskah/documents/{id}/align-page
    $this->postJson("/api/v1/naskah/documents/{$doc->id}/align-page", [
        'page' => 1,
        'align' => 'center',
    ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('align', 'center')
        ->assertJsonPath('aligned_count', 1);

    $doc->refresh();
    expect($doc->blocks[0]['align'])->toBe('center');

    // 3. Uji POST /api/v1/external/readability
    $client = ExternalClient::query()->create([
        'name' => 'Readability Testing Client',
        'client_slug' => 'readability-testing-client',
        'api_key' => 'xsh_readability_key_9999',
        'target_url' => 'http://localhost:8208',
        'allowed_models' => ['*'],
        'status' => 'online',
        'is_active' => true,
    ]);

    $this->withHeaders(['X-Api-Key' => 'xsh_readability_key_9999'])
        ->postJson('/api/v1/external/readability', [
            'text' => 'Implementasi sistem terdesentralisasi memberikan keamanan data tinggi. Verifikasi kriptografi menjamin integritas transaksi.',
        ])
        ->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('client', 'readability-testing-client')
        ->assertJsonPath('word_count', 12)
        ->assertJsonPath('sentence_count', 2)
        ->assertJsonPath('avg_words_per_sentence', 6);
});

it('renders response to reviewers and literature matrix in rich text format (RTF)', function (): void {
    $project = makeProject(['title' => 'Pengujian Matriks RTF']);
    seedReferences($project);
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    // 1. Uji PeerCriticAgent::renderResponseMatrixRtf dan downloadResponseMatrix('rtf')
    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'stage_id' => $stage->id,
        'reviewer_role' => 'reviewer_utama',
        'status' => RevisionStatus::PENDING,
        'raw_feedback' => 'Tinjau kembali metodologi dan perjelas batasan penelitian.',
        'action_items' => [
            ['id' => 1, 'task' => 'Perjelas batasan penelitian', 'section_key' => 'metodologi', 'status' => 'pending'],
        ],
    ]);

    $criticAgent = app(PeerCriticAgent::class);
    $rtfMatrix = $criticAgent->renderResponseMatrixRtf($revision);
    expect($rtfMatrix)->toContain('{\\rtf1\\ansi\\deff0')
        ->and($rtfMatrix)->toContain('Response to Reviewers Matrix')
        ->and($rtfMatrix)->toContain('Perjelas batasan penelitian');

    Livewire::test(SmartRevisionCenterPage::class, ['project' => $project->id])
        ->call('downloadResponseMatrix', $revision->id, 'rtf')
        ->assertSuccessful();

    // 2. Uji LiteratureMatrixBuilder::toRtf dan exportLiteratureMatrix('rtf')
    $builder = app(LiteratureMatrixBuilder::class);
    $rtfLit = $builder->toRtf($project);
    expect($rtfLit)->toContain('{\\rtf1\\ansi\\deff0')
        ->and($rtfLit)->toContain('Tabel Matriks Literatur (State-of-the-Art)')
        ->and($rtfLit)->toContain('Pengujian Matriks RTF');

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('exportLiteratureMatrix', 'rtf')
        ->assertSuccessful();
});

it('exports venue catalog in standalone html format', function (): void {
    $project = makeProject();
    AcademicVenue::create([
        'journal_name' => 'Jurnal Sistem Informasi Nasional S1',
        'publisher' => 'Institut Teknologi Bandung',
        'scope_type' => 'sinta',
        'index_tier' => 'S1',
        'field_of_study' => 'Informatika',
        'aims_and_scope' => 'Publikasi riset sistem informasi dan ilmu komputer.',
        'apc_estimate' => 2000000,
        'review_time_days' => 30,
        'is_active' => true,
    ]);

    Livewire::test(VenueCatalogPage::class, ['project' => $project->id])
        ->call('exportCatalog', 'html')
        ->assertSuccessful();
});

it('manages batch page indentation and highlights in studio and cli', function (): void {
    $project = makeProject();
    $doc = NaskahDocument::query()->create([
        'project_id' => $project->id,
        'title' => 'Dokumen Batch Indent dan Highlight',
        'document_type' => 'thesis',
        'blocks' => [
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Alinea pertama naskah.'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Alinea kedua naskah.'],
        ],
        'word_count' => 6,
    ]);

    $app = Livewire::test(NaskahStudioApp::class);
    $instance = $app->instance();
    $instance->blocks = (array) $doc->blocks;

    // 1. Uji batchIndentPageParagraphs first-line
    $count = $instance->batchIndentPageParagraphs(1, 'first-line');
    expect($count)->toBe(2);
    expect($instance->blocks[0]['firstLineIndent'])->toBeTrue()
        ->and($instance->blocks[1]['firstLineIndent'])->toBeTrue();

    // 2. Uji batchIndentPageParagraphs hanging
    $countHanging = $instance->batchIndentPageParagraphs(1, 'hanging');
    expect($countHanging)->toBe(2);
    expect($instance->blocks[0]['hangingIndent'])->toBeTrue()
        ->and(isset($instance->blocks[0]['firstLineIndent']))->toBeFalse();

    // 3. Uji highlightPageParagraphs
    $countHl = $instance->highlightPageParagraphs(1, '#fed7aa');
    expect($countHl)->toBe(2);
    expect($instance->blocks[0]['backgroundColor'])->toBe('#fed7aa')
        ->and($instance->blocks[1]['backgroundColor'])->toBe('#fed7aa');

    // 4. Uji CLI highlight-page
    $this->artisan("naskah:manage highlight-page {$doc->id} --page=1 --color=#bbf7d0")
        ->assertSuccessful()
        ->expectsOutputToContain("Berhasil: warna sorot diatur ke '#bbf7d0'");

    $doc->refresh();
    expect($doc->blocks[0]['backgroundColor'])->toBe('#bbf7d0');

    $this->artisan("naskah:manage highlight-page {$doc->id} --page=1 --color=none")
        ->assertSuccessful()
        ->expectsOutputToContain('Berhasil: warna sorot dihapus');

    $doc->refresh();
    expect(isset($doc->blocks[0]['backgroundColor']))->toBeFalse();
});

it('executes /kebaruan and /novelty slash command for academic statement of novelty', function (): void {
    $project = makeProject(['title' => 'Rancang Bangun Sistem Verifikasi Konsensus']);
    $service = app(XishieAssistantService::class);

    // 1. Eksekusi /kebaruan tanpa argumen
    $res = $service->handle($project, '/kebaruan');
    expect($res['command'])->toBe('/kebaruan')
        ->and($res['reply'])->toContain('Rumusan Pernyataan Kebaruan Ilmiah (Statement of Novelty)')
        ->and($res['reply'])->toContain('Pernyataan Kebaruan Pokok (Core Novelty Statement)')
        ->and($res['data']['project_title'])->toBe('Rancang Bangun Sistem Verifikasi Konsensus');

    // 2. Eksekusi dengan alias /novelty dan argumen metode
    $resArg = $service->handle($project, '/novelty protokol Byzantine Fault Tolerance adaptif');
    expect($resArg['command'])->toBe('/kebaruan')
        ->and($resArg['reply'])->toContain('protokol Byzantine Fault Tolerance adaptif')
        ->and($resArg['data']['novelty_focus'])->toBe('protokol Byzantine Fault Tolerance adaptif');
});

it('synthesizes document-aware table and applies model output directly to manuscript page in naskah studio', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);
    $component->call('loadSampleHandbook');

    // 1. Eksekusi permintaan natural language untuk menambahkan tabel menyesuaikan isi dokumen
    $component->call('sendAssistantWithText', 'tambahkan tabel menyesuaikan dengan isi dokumen');

    $log = $component->get('assistantLog');
    expect($log)->not->toBeEmpty();
    $lastLog = end($log);
    $lastIndex = count($log) - 1;

    expect($lastLog['role'])->toBe('assistant')
        ->and($lastLog['reply'] ?? $lastLog['content'])->toContain('Tabel Matriks Dokumen')
        ->and($lastLog['data']['action'])->toBe('insert_table')
        ->and($lastLog['data']['table_rows'])->toBeArray();

    // Pastikan tabel memuat aspek kontekstual dari dokumen (Why share a studio?, What we agree on, etc.)
    $headingsInTable = array_map(static fn ($r) => $r[0]['text'] ?? '', $lastLog['data']['table_rows']);
    expect($headingsInTable)->toContain('Why share a studio?')
        ->and($headingsInTable)->toContain('What we agree on');

    // 2. Eksekusi dan terapkan model ke halaman aktif dokumen saat ini (Page 1)
    $initialBlockCount = count($component->get('blocks'));
    $component->call('applyAssistantToCurrentPage', $lastIndex, 1);

    $blocksAfterTable = $component->get('blocks');
    expect(count($blocksAfterTable))->toBe($initialBlockCount + 1);
    $activeBlockIndex = $component->get('activeBlock');
    $insertedBlock = $blocksAfterTable[$activeBlockIndex];
    expect($insertedBlock['type'])->toBe('table')
        ->and(count($insertedBlock['table']['rows']))->toBe(count($lastLog['data']['table_rows']))
        ->and($insertedBlock['table']['rows'][0][0]['text'])->toBe('Bagian / Aspek Naskah')
        ->and($insertedBlock['table']['rows'][1][0]['text'])->toBe('Why share a studio?');

    // 3. Uji penerapan rumus LaTeX ke halaman tertentu (Page 2)
    $component->call('sendAssistantWithText', 'tambahkan rumus normal gaussian');
    $logAfterFormula = $component->get('assistantLog');
    $formulaLog = end($logAfterFormula);
    $formulaLogIndex = count($logAfterFormula) - 1;

    expect($formulaLog['data']['latex'])->toContain('\\frac{1}{\\sigma');

    $component->call('applyAssistantToCurrentPage', $formulaLogIndex, 2);
    $blocksAfterFormula = $component->get('blocks');
    $equationBlockIndex = $component->get('activeBlock');
    $equationBlock = $blocksAfterFormula[$equationBlockIndex];
    expect($equationBlock['type'])->toBe('equation')
        ->and($equationBlock['equation']['latex'])->toContain('\\frac{1}{\\sigma');

    // 4. Uji replaceActiveBlockWithAssistant dengan blok tabel
    $component->set('activeBlock', 0); // Target blok pertama
    $component->call('replaceActiveBlockWithAssistant', $lastIndex);
    $blocksAfterReplace = $component->get('blocks');
    expect($blocksAfterReplace[0]['type'])->toBe('table')
        ->and(count($blocksAfterReplace[0]['table']['rows']))->toBe(count($lastLog['data']['table_rows']))
        ->and($blocksAfterReplace[0]['table']['rows'][0][0]['text'])->toBe('Bagian / Aspek Naskah');

    // 5. Uji freeform natural language intent di XishieAssistantService
    $project = makeProject(['title' => 'Studi Analisis Komparasi Kinerja']);
    $assistantService = app(XishieAssistantService::class);
    $freeformTableResult = $assistantService->handle($project, 'buatkan matriks tabel analisis komparasi');
    expect($freeformTableResult['command'])->toBe('/tabel-analisis')
        ->and($freeformTableResult['data']['table_rows'])->toBeArray()
        ->and($freeformTableResult['data']['action'])->toBe('insert_table');
});

it('performs white box verification on NaskahRichModel normalizeBlock and formatParagraphBlock preserving manual formatting', function (): void {
    $model = app(NaskahRichModel::class);

    // 1. Uji normalizeBlock dengan atribut manual lengkap
    $rawBlock = [
        'type' => 'paragraph',
        'text' => 'Sennett, R. (2019). The Craftsman. Material Culture, Solid Cooperation, and Studio Ethics.',
        'hangingIndent' => true,
        'firstLineIndent' => true, // Harus otomatis di-unset karena hangingIndent bernilai true
        'backgroundColor' => '#fef08a',
        'borderColor' => '#3b82f6',
        'rightIndent' => 2,
        'citation' => 'Sennett2019',
        'footnote' => 'Catatan kaki penjelasan edisi Yale University Press.',
        'dropCap' => true,
        'lineSpacing' => 1.5,
    ];

    $normalized = $model->normalizeBlock($rawBlock);

    expect($normalized['hangingIndent'])->toBeTrue()
        ->and($normalized['firstLineIndent'] ?? false)->toBeFalse()
        ->and($normalized['backgroundColor'])->toBe('#fef08a')
        ->and($normalized['borderColor'])->toBe('#3b82f6')
        ->and($normalized['rightIndent'])->toBe(2)
        ->and($normalized['citation'])->toBe('Sennett2019')
        ->and($normalized['footnote'])->toBe('Catatan kaki penjelasan edisi Yale University Press.')
        ->and($normalized['dropCap'])->toBeTrue()
        ->and($normalized['lineSpacing'])->toBe(1.5);

    // 2. Uji formatParagraphBlock dengan pergantian hanging indent dan first line indent
    $para = $model->newBlock('paragraph', 'Paragraf pengujian format');
    $formattedHanging = $model->formatParagraphBlock($para, [
        'hangingIndent' => true,
        'backgroundColor' => '#dbeafe',
        'borderColor' => '#2563eb',
    ]);
    expect($formattedHanging['hangingIndent'])->toBeTrue()
        ->and($formattedHanging['firstLineIndent'] ?? false)->toBeFalse()
        ->and($formattedHanging['backgroundColor'])->toBe('#dbeafe')
        ->and($formattedHanging['borderColor'])->toBe('#2563eb');

    // Beralih ke first line indent harus menghapus hanging indent
    $formattedFirstLine = $model->formatParagraphBlock($formattedHanging, [
        'firstLineIndent' => true,
    ]);
    expect($formattedFirstLine['firstLineIndent'])->toBeTrue()
        ->and($formattedFirstLine['hangingIndent'] ?? false)->toBeFalse();
});

it('performs white box verification on parseHtmlToBlocks stripping UI buttons and parsing sourceIndex and styles', function (): void {
    $studio = Livewire::test(NaskahStudioApp::class)->instance();

    // Simulasi HTML halaman naskah dengan tombol UI hover, alinea hanging indent, dan tabel
    $html = <<<'HTML'
    <div class="word-para-item-wrapper" style="position:relative;">
        <p class="word-paragraph-text" id="word-block-1" data-block-index="1" style="text-align: justify; padding-left: 1.27cm; text-indent: -1.27cm; background-color: #fef08a; border-left: 3px solid #3b82f6; line-height: 1.5;">
            Sennett, R. (2019). The Craftsman. Material Culture, Solid Cooperation, and Studio Ethics. Yale University Press.
        </p>
        <div contenteditable="false" style="position:absolute; right:4px;">
            <button type="button" class="word-tool-btn">⚙️</button>
            <button type="button" class="word-tool-btn">📐</button>
            <button type="button" class="word-tool-btn">🖍️</button>
            <button type="button" class="word-tool-btn">🔤</button>
            <button type="button" class="word-tool-btn">⬆️</button>
            <button type="button" class="word-tool-btn">⬇️</button>
            <button type="button" class="word-tool-btn">📄</button>
            <button type="button" class="word-tool-btn">✂️</button>
            <button type="button" class="word-tool-btn">🔄</button>
            <button type="button" class="word-tool-btn">✨</button>
            <button type="button" class="word-tool-btn">🗑️</button>
        </div>
    </div>
    <div class="word-equation-block" data-block-index="2" data-latex="E = mc^2" data-caption="Einstein" contenteditable="false">
        <div class="word-equation-actions">
            <button type="button">✏️ Edit Rumus</button>
        </div>
        <span>E = mc^2</span>
    </div>
    <ul data-block-index="3">
        <li data-block-index="3">Poin pertama</li>
        <li data-block-index="4">Poin kedua</li>
    </ul>
    HTML;

    $blocks = $studio->parseHtmlToBlocks($html);

    // 1. Blok 0 (Paragraf): Pastikan tidak ada tombol aksi/emoji yang bocor ke dalam teks
    expect($blocks[0]['type'])->toBe('paragraph')
        ->and($blocks[0]['text'])->toContain('Sennett, R. (2019)')
        ->and($blocks[0]['text'])->not->toContain('⚙️')
        ->and($blocks[0]['text'])->not->toContain('word-tool-btn')
        ->and($blocks[0]['text'])->not->toContain('<button')
        ->and($blocks[0]['_sourceIndex'])->toBe(1)
        ->and($blocks[0]['hangingIndent'])->toBeTrue()
        ->and($blocks[0]['firstLineIndent'] ?? false)->toBeFalse()
        ->and($blocks[0]['backgroundColor'])->toBe('#fef08a')
        ->and($blocks[0]['borderColor'])->toBe('#3b82f6')
        ->and($blocks[0]['align'])->toBe('justify')
        ->and($blocks[0]['lineSpacing'])->toBe(1.5);

    // 2. Blok 1 (Equation): Pastikan tidak terhapus dan tombol aksinya bersih
    expect($blocks[1]['type'])->toBe('equation')
        ->and($blocks[1]['_sourceIndex'])->toBe(2)
        ->and($blocks[1]['equation']['latex'])->toBe('E = mc^2')
        ->and($blocks[1]['equation']['caption'])->toBe('Einstein');

    // 3. Blok 2 & 3 (Bullet List): Pastikan _sourceIndex terbaca dari <li>
    expect($blocks[2]['type'])->toBe('bullet')
        ->and($blocks[2]['text'])->toBe('Poin pertama')
        ->and($blocks[2]['_sourceIndex'])->toBe(3)
        ->and($blocks[3]['type'])->toBe('bullet')
        ->and($blocks[3]['text'])->toBe('Poin kedua')
        ->and($blocks[3]['_sourceIndex'])->toBe(4);

    // 4. Uji alinea jamak di dalam pembungkus wrapper (misal pengguna menekan tombol Enter)
    $enterHtml = <<<'HTML'
    <div class="word-para-item-wrapper">
        <p class="word-paragraph-text" data-block-index="5">Baris alinea pertama sebelum enter</p>
        <p>Baris alinea kedua hasil tombol Enter pengguna</p>
    </div>
    HTML;
    $enterBlocks = $studio->parseHtmlToBlocks($enterHtml);
    expect(count($enterBlocks))->toBe(2)
        ->and($enterBlocks[0]['text'])->toBe('Baris alinea pertama sebelum enter')
        ->and($enterBlocks[0]['_sourceIndex'])->toBe(5)
        ->and($enterBlocks[1]['text'])->toBe('Baris alinea kedua hasil tombol Enter pengguna')
        ->and($enterBlocks[1]['_sourceIndex'] ?? null)->toBeNull();
});

it('performs white box verification on syncManuscriptPages smart reconciliation and pagebreak retention', function (): void {
    $studio = Livewire::test(NaskahStudioApp::class)->instance();

    // Inisialisasi blok awal: Judul (0), Paragraf (1), Pagebreak (2), Paragraf (3)
    $model = app(NaskahRichModel::class);
    $initialBlocks = [
        $model->newBlock('title', 'Naskah Uji Rekonsiliasi'),
        array_merge($model->newBlock('paragraph', 'Alinea awal Halaman 1'), [
            'firstLineIndent' => true,
            'backgroundColor' => '#fef08a',
            'dropCap' => true,
        ]),
        $model->newBlock('pagebreak', ''),
        array_merge($model->newBlock('paragraph', 'Alinea awal Halaman 2'), [
            'hangingIndent' => true,
        ]),
    ];
    $studio->blocks = $initialBlocks;

    // Simulasi HTML dari dua halaman yang dikirim browser
    $page1Html = <<<'HTML'
    <h1 class="word-title-text" data-block-index="0">Naskah Uji Rekonsiliasi Tersunting</h1>
    <div class="word-para-item-wrapper">
        <p class="word-paragraph-text word-drop-cap" data-block-index="1" style="text-indent: 1.27cm; background-color: #fef08a;">
            Alinea awal Halaman 1 yang disunting user dalam contenteditable
        </p>
    </div>
    HTML;

    $page2Html = <<<'HTML'
    <div class="word-para-item-wrapper">
        <p class="word-paragraph-text" data-block-index="3" style="padding-left: 1.27cm; text-indent: -1.27cm;">
            Alinea awal Halaman 2 yang disunting teksnya
        </p>
    </div>
    HTML;

    $studio->syncManuscriptPages([$page1Html, $page2Html]);

    $synced = $studio->blocks;

    // Pastikan total 4 blok tetap lengkap termasuk pemisah halaman (pagebreak) di antara halaman 1 dan 2
    expect(count($synced))->toBe(4)
        ->and($synced[0]['type'])->toBe('title')
        ->and($synced[0]['text'])->toBe('Naskah Uji Rekonsiliasi Tersunting')
        ->and($synced[1]['type'])->toBe('paragraph')
        ->and($synced[1]['text'])->toContain('yang disunting user dalam contenteditable')
        ->and($synced[1]['firstLineIndent'])->toBeTrue()
        ->and($synced[1]['backgroundColor'])->toBe('#fef08a')
        ->and($synced[1]['dropCap'])->toBeTrue()
        ->and($synced[2]['type'])->toBe('pagebreak')
        ->and($synced[3]['type'])->toBe('paragraph')
        ->and($synced[3]['text'])->toBe('Alinea awal Halaman 2 yang disunting teksnya')
        ->and($synced[3]['hangingIndent'])->toBeTrue();
});

it('performs black box verification of saving manually formatted naskah studio without losing styling or leaking buttons', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);

    // 1. Terapkan tata letak manual yang rapi melalui aksi studio
    $component->call('newDocument');
    $component->set('docTitle', 'The Open Studio Handbook');

    // Buat susunan blok manual yang kaya format
    $model = app(NaskahRichModel::class);
    $manualBlocks = [
        $model->newBlock('title', 'The Open Studio Handbook'),
        $model->newBlock('heading', 'Why share a studio?', 2),
        array_merge($model->newBlock('paragraph', 'Paragraf pertama dengan indentasi baris pertama baku 1.27cm dan sorotan kuning.'), [
            'firstLineIndent' => true,
            'backgroundColor' => '#fef08a',
            'lineSpacing' => 1.5,
        ]),
        $model->newBlock('pagebreak', ''),
        $model->newBlock('heading', 'Daftar Pustaka', 2),
        array_merge($model->newBlock('paragraph', 'Sennett, R. (2019). The Craftsman. Material Culture, Solid Cooperation, and Studio Ethics. Yale University Press, New Haven.'), [
            'hangingIndent' => true,
            'lineSpacing' => 1.5,
        ]),
    ];
    $component->set('blocks', $manualBlocks);

    // 2. Simulasi DOM browser yang dikirimkan saat Save (Ctrl+S / tombol Simpan)
    // Berisi tombol-tombol interaktif dan wrapper alinea
    $domPage1 = <<<'HTML'
    <h1 class="word-title-text" id="doc-title" data-block-index="0">The Open Studio Handbook</h1>
    <h3 class="word-heading-2" id="heading-1" data-block-index="1">Why share a studio?</h3>
    <div class="word-para-item-wrapper" style="position:relative;">
        <p class="word-paragraph-text" id="word-block-2" data-block-index="2" style="text-align: left; text-indent: 1.27cm; line-height: 1.5; background-color: #fef08a; padding: 6px 10px; border-radius: 4px;">
            Paragraf pertama dengan indentasi baris pertama baku 1.27cm dan sorotan kuning yang rapi.
        </p>
        <div contenteditable="false" style="position:absolute; right:4px; top:-12px;">
            <button type="button" class="word-tool-btn">⚙️</button>
            <button type="button" class="word-tool-btn">📐</button>
            <button type="button" class="word-tool-btn">🖍️</button>
            <button type="button" class="word-tool-btn">🔤</button>
            <button type="button" class="word-tool-btn">⬆️</button>
            <button type="button" class="word-tool-btn">⬇️</button>
            <button type="button" class="word-tool-btn">📄</button>
            <button type="button" class="word-tool-btn">✂️</button>
            <button type="button" class="word-tool-btn">🔄</button>
            <button type="button" class="word-tool-btn">✨</button>
            <button type="button" class="word-tool-btn">🗑️</button>
        </div>
    </div>
    HTML;

    $domPage2 = <<<'HTML'
    <h3 class="word-heading-2" id="heading-4" data-block-index="4">Daftar Pustaka</h3>
    <p class="word-bib-entry" id="word-block-5" data-block-index="5" style="text-align: left; padding-left: 1.27cm; text-indent: -1.27cm; line-height: 1.5;">
        Sennett, R. (2019). The Craftsman. Material Culture, Solid Cooperation, and Studio Ethics. Yale University Press, New Haven.
    </p>
    HTML;

    // 3. Panggil aksi Simpan (save) dengan DOM HTML lembar halaman
    $initialRevision = $component->get('editorRevision');
    $component->call('save', [$domPage1, $domPage2])
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    expect($component->get('editorRevision'))->toBeGreaterThan($initialRevision);

    // Lakukan simpan beruntun kedua untuk memastikan tidak ada degradasi format bertingkat
    $component->call('save', [$domPage1, $domPage2])
        ->assertSuccessful();

    // 4. Verifikasi State Komponen setelah Simpan
    $savedBlocks = $component->get('blocks');
    expect(count($savedBlocks))->toBe(6);

    // Periksa bahwa tidak ada tombol aksi/emoji yang bocor ke dalam teks paragraf
    foreach ($savedBlocks as $b) {
        $text = $b['text'] ?? '';
        expect($text)->not->toContain('⚙️')
            ->and($text)->not->toContain('word-tool-btn')
            ->and($text)->not->toContain('<button');
    }

    // Periksa keutuhan atribut alinea 1 (Halaman 1)
    expect($savedBlocks[2]['type'])->toBe('paragraph')
        ->and($savedBlocks[2]['text'])->toContain('Paragraf pertama dengan indentasi baris pertama baku 1.27cm')
        ->and($savedBlocks[2]['firstLineIndent'])->toBeTrue()
        ->and($savedBlocks[2]['hangingIndent'] ?? false)->toBeFalse()
        ->and($savedBlocks[2]['backgroundColor'])->toBe('#fef08a')
        ->and($savedBlocks[2]['lineSpacing'])->toBe(1.5);

    // Periksa pemisah halaman (pagebreak) tetap berada di antara halaman 1 dan 2
    expect($savedBlocks[3]['type'])->toBe('pagebreak');

    // Periksa keutuhan alinea Daftar Pustaka (Halaman 2): Hanging Indent 1.27cm sesuai Gambar Pengguna
    expect($savedBlocks[4]['type'])->toBe('heading')
        ->and($savedBlocks[4]['text'])->toBe('Daftar Pustaka')
        ->and($savedBlocks[5]['type'])->toBe('paragraph')
        ->and($savedBlocks[5]['text'])->toContain('Sennett, R. (2019)')
        ->and($savedBlocks[5]['hangingIndent'])->toBeTrue()
        ->and($savedBlocks[5]['firstLineIndent'] ?? false)->toBeFalse()
        ->and($savedBlocks[5]['lineSpacing'])->toBe(1.5);

    // 5. Verifikasi Persistensi Database (Tabel naskah_documents)
    $docId = $component->get('documentId');
    expect($docId)->not->toBeNull();

    $documentRecord = NaskahDocument::query()->find($docId);
    expect($documentRecord)->not->toBeNull()
        ->and($documentRecord->title)->toBe('The Open Studio Handbook')
        ->and($documentRecord->word_count)->toBeGreaterThan(10);

    $dbBlocks = $documentRecord->blocks;
    expect(count($dbBlocks))->toBe(6)
        ->and($dbBlocks[2]['firstLineIndent'])->toBeTrue()
        ->and($dbBlocks[2]['backgroundColor'])->toBe('#fef08a')
        ->and($dbBlocks[3]['type'])->toBe('pagebreak')
        ->and($dbBlocks[5]['hangingIndent'])->toBeTrue();

    // 6. Verifikasi re-load dokumen naskah (Simulasi pengguna membuka kembali dokumen)
    $reloadedComponent = Livewire::test(NaskahStudioApp::class, ['document' => $docId]);
    $reloadedBlocks = $reloadedComponent->get('blocks');

    expect(count($reloadedBlocks))->toBe(6)
        ->and($reloadedBlocks[2]['firstLineIndent'])->toBeTrue()
        ->and($reloadedBlocks[2]['backgroundColor'])->toBe('#fef08a')
        ->and($reloadedBlocks[3]['type'])->toBe('pagebreak')
        ->and($reloadedBlocks[5]['hangingIndent'])->toBeTrue()
        ->and($reloadedBlocks[5]['text'])->toContain('Sennett, R. (2019)');

    // 7. Verifikasi Ekspor JSON mempertahankan struktur tata letak manual
    $jsonResponse = $reloadedComponent->instance()->export('json');
    expect($jsonResponse)->not->toBeNull();
});

it('performs white box verification on pagebreak marker retention, sheet unwrapping, and paragraph spacing', function (): void {
    $studio = Livewire::test(NaskahStudioApp::class)->instance();

    // 1. Uji pemisah halaman (pagebreak) dengan contenteditable="false" tidak terhapus
    $pagebreakHtml = <<<'HTML'
    <p class="word-paragraph-text" data-block-index="0">Alinea terakhir pada lembar pertama naskah.</p>
    <div class="word-page-break-marker" contenteditable="false" style="border-top:2px dashed #0078d4;">
        <span>——— Pemisah Halaman (Page Break) ———</span>
    </div>
    <p class="word-paragraph-text" data-block-index="2">Alinea pembuka pada lembar kedua naskah.</p>
    HTML;

    $pbBlocks = $studio->parseHtmlToBlocks($pagebreakHtml);
    expect(count($pbBlocks))->toBe(3)
        ->and($pbBlocks[0]['type'])->toBe('paragraph')
        ->and($pbBlocks[1]['type'])->toBe('pagebreak')
        ->and($pbBlocks[2]['type'])->toBe('paragraph');

    // 2. Uji kontainer lembar .word-sheet-content dan .word-paper-sheet di-unwrap dan tidak menciut menjadi 1 blok raksasa
    $sheetHtml = <<<'HTML'
    <div class="word-paper-sheet" id="word-page-1">
        <div class="word-sheet-content" id="word-page-content-1">
            <h1 class="word-title-text" data-block-index="0">Judul Naskah Terstruktur</h1>
            <h2 class="word-heading-1" data-block-index="1">Pendahuluan</h2>
            <div class="word-para-item-wrapper">
                <p class="word-paragraph-text" data-block-index="2" style="text-indent: 1.27cm; margin-top: 14pt; margin-bottom: 8pt; line-height: 1.5;">
                    Paragraf pengantar yang telah dirapikan secara manual oleh penulis.
                </p>
            </div>
            <div class="word-para-item-wrapper">
                <p class="word-paragraph-text" data-block-index="3" style="margin-top: 6pt; margin-bottom: 12pt;">
                    Paragraf kedua tanpa indentasi baris pertama.
                </p>
            </div>
        </div>
    </div>
    HTML;

    $sheetBlocks = $studio->parseHtmlToBlocks($sheetHtml);
    expect(count($sheetBlocks))->toBe(4)
        ->and($sheetBlocks[0]['type'])->toBe('title')
        ->and($sheetBlocks[0]['text'])->toBe('Judul Naskah Terstruktur')
        ->and($sheetBlocks[1]['type'])->toBe('heading')
        ->and($sheetBlocks[1]['text'])->toBe('Pendahuluan')
        ->and($sheetBlocks[2]['type'])->toBe('paragraph')
        ->and($sheetBlocks[2]['firstLineIndent'])->toBeTrue()
        ->and($sheetBlocks[2]['spacingBefore'])->toBe(14.0)
        ->and($sheetBlocks[2]['spacingAfter'])->toBe(8.0)
        ->and($sheetBlocks[2]['lineSpacing'])->toBe(1.5)
        ->and($sheetBlocks[3]['type'])->toBe('paragraph')
        ->and($sheetBlocks[3]['firstLineIndent'])->toBeFalse()
        ->and($sheetBlocks[3]['spacingBefore'])->toBe(6.0)
        ->and($sheetBlocks[3]['spacingAfter'])->toBe(12.0);

    // 3. Uji rekonsiliasi cerdas saat penulis menghapus indentasi atau menghapus warna sorotan
    $studio->blocks = [
        $sheetBlocks[0],
        $sheetBlocks[1],
        array_merge($sheetBlocks[2], [
            'backgroundColor' => '#fef08a',
            'firstLineIndent' => true,
        ]),
    ];

    // Penulis menghapus indentasi dan menghapus warna kuning pada blok indeks 2
    $editedHtml = <<<'HTML'
    <h1 class="word-title-text" data-block-index="0">Judul Naskah Terstruktur</h1>
    <h2 class="word-heading-1" data-block-index="1">Pendahuluan</h2>
    <p class="word-paragraph-text" data-block-index="2" style="line-height: 1.5;">
        Paragraf pengantar yang telah dirapikan secara manual oleh penulis tanpa indentasi dan tanpa warna.
    </p>
    HTML;

    $studio->syncManuscriptPages([$editedHtml]);
    $reconciled = $studio->blocks;

    expect(count($reconciled))->toBe(3)
        ->and($reconciled[2]['firstLineIndent'])->toBeFalse()
        ->and($reconciled[2]['backgroundColor'] ?? null)->toBeNull()
        ->and($reconciled[2]['text'])->toContain('tanpa indentasi dan tanpa warna');
});

it('performs black box verification of multi-page save fidelity with manual breaks and rearranged blocks', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);
    $component->call('newDocument');
    $component->set('docTitle', 'Naskah Multi-Lembar Teratur');

    $model = app(NaskahRichModel::class);
    $initialBlocks = [
        $model->newBlock('title', 'Naskah Multi-Lembar Teratur'),
        array_merge($model->newBlock('paragraph', 'Paragraf lembar pertama dengan spasi sebelum dan sesudah alinea.'), [
            'spacingBefore' => 12.0,
            'spacingAfter' => 6.0,
            'firstLineIndent' => true,
        ]),
        $model->newBlock('pagebreak', ''),
        array_merge($model->newBlock('paragraph', 'Paragraf lembar kedua yang diatur secara manual.'), [
            'spacingBefore' => 18.0,
            'spacingAfter' => 12.0,
            'hangingIndent' => true,
        ]),
    ];
    $component->set('blocks', $initialBlocks);

    // Simulasi pengiriman HTML multi-halaman dari penyunting browser lengkap dengan wrapper lembar
    $domSheet1 = <<<'HTML'
    <div class="word-sheet-content" id="word-page-content-1">
        <h1 class="word-title-text" data-block-index="0">Naskah Multi-Lembar Teratur</h1>
        <div class="word-para-item-wrapper">
            <p class="word-paragraph-text" data-block-index="1" style="margin-top: 12pt; margin-bottom: 6pt; text-indent: 1.27cm;">
                Paragraf lembar pertama dengan spasi sebelum dan sesudah alinea.
            </p>
        </div>
        <div class="word-page-break-marker" contenteditable="false">
            <span>——— Pemisah Halaman ———</span>
        </div>
    </div>
    HTML;

    $domSheet2 = <<<'HTML'
    <div class="word-sheet-content" id="word-page-content-2">
        <div class="word-para-item-wrapper">
            <p class="word-paragraph-text" data-block-index="3" style="margin-top: 18pt; margin-bottom: 12pt; padding-left: 1.27cm; text-indent: -1.27cm;">
                Paragraf lembar kedua yang diatur secara manual.
            </p>
        </div>
    </div>
    HTML;

    // Simpan dokumen
    $component->call('save', [$domSheet1, $domSheet2])
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    $savedBlocks = $component->get('blocks');

    // Pastikan tidak ada blok yang hilang atau menciut
    expect(count($savedBlocks))->toBe(4)
        ->and($savedBlocks[0]['type'])->toBe('title')
        ->and($savedBlocks[1]['type'])->toBe('paragraph')
        ->and($savedBlocks[1]['spacingBefore'])->toBe(12.0)
        ->and($savedBlocks[1]['spacingAfter'])->toBe(6.0)
        ->and($savedBlocks[1]['firstLineIndent'])->toBeTrue()
        ->and($savedBlocks[2]['type'])->toBe('pagebreak')
        ->and($savedBlocks[3]['type'])->toBe('paragraph')
        ->and($savedBlocks[3]['spacingBefore'])->toBe(18.0)
        ->and($savedBlocks[3]['spacingAfter'])->toBe(12.0)
        ->and($savedBlocks[3]['hangingIndent'])->toBeTrue();

    // Verifikasi database
    $docId = $component->get('documentId');
    $dbDoc = NaskahDocument::query()->find($docId);
    expect($dbDoc)->not->toBeNull()
        ->and(count($dbDoc->blocks))->toBe(4)
        ->and($dbDoc->blocks[2]['type'])->toBe('pagebreak')
        ->and($dbDoc->blocks[1]['spacingBefore'])->toEqual(12.0);
});

it('performs white box verification on current assistant model selection and registry list in naskah studio', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);

    // 1. Model default saat awal belum diset
    $initialModel = $component->instance()->getCurrentAssistantModel();
    expect($initialModel)->not->toBeEmpty()
        ->and($initialModel)->toBe('qwen-2.5-72b-instruct');

    // 2. Mengambil daftar model yang tersedia
    $availableModels = $component->instance()->getAvailableAssistantModels();
    expect($availableModels)->toBeArray()
        ->and(count($availableModels))->toBeGreaterThanOrEqual(3);

    $modelIds = array_column($availableModels, 'id');
    expect($modelIds)->toContain('qwen-2.5-72b-instruct')
        ->and($modelIds)->toContain('deepseek-chat')
        ->and($modelIds)->toContain('gpt-4o');

    // 3. Mengubah model aktif saat ini
    $component->call('setAssistantModel', 'deepseek-chat')
        ->assertDispatched('studio-toast');

    expect($component->instance()->getCurrentAssistantModel())->toBe('deepseek-chat')
        ->and($component->get('currentAssistantModel'))->toBe('deepseek-chat');

    // 4. Mengubah via alias selectAssistantModel
    $component->call('selectAssistantModel', 'claude-3-5-sonnet');
    expect($component->instance()->getCurrentAssistantModel())->toBe('claude-3-5-sonnet');
});

it('performs white box verification on executing current model with equations, tables, and sections into naskah studio', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);
    $initialRevision = $component->get('editorRevision');
    $initialBlocksCount = count($component->get('blocks'));

    // 1. Eksekusi model untuk menyisipkan tabel ke halaman saat ini
    $component->call('executeCurrentModel', 'Sisipkan tabel evaluasi komparasi kinerja', 'current_page');
    expect($component->get('editorRevision'))->toBeGreaterThan($initialRevision);

    $blocksAfterTable = $component->get('blocks');
    expect(count($blocksAfterTable))->toBeGreaterThan($initialBlocksCount);

    $hasTable = false;
    foreach ($blocksAfterTable as $b) {
        if (($b['type'] ?? '') === 'table') {
            $hasTable = true;
            break;
        }
    }
    expect($hasTable)->toBeTrue();

    // Verifikasi log asisten mencatat model aktif
    $log = $component->get('assistantLog');
    $lastLog = end($log);
    expect($lastLog['role'])->toBe('assistant')
        ->and($lastLog['command'])->toBe('eksekusi-model')
        ->and($lastLog['model'])->toBe($component->instance()->getCurrentAssistantModel())
        ->and($lastLog['applied'])->toBeTrue();

    // 2. Ubah model aktif dan eksekusi rumus LaTeX ke active block
    $component->call('setAssistantModel', 'gpt-4o');
    $component->set('activeBlock', 1);
    $revisionBeforeEquation = $component->get('editorRevision');

    $component->call('executeCurrentModel', 'Formulasikan persamaan rumus regresi linear', 'active_block');
    expect($component->get('editorRevision'))->toBeGreaterThan($revisionBeforeEquation);

    $updatedBlock = $component->get('blocks')[1];
    expect($updatedBlock['type'])->toBe('equation')
        ->and($updatedBlock['equation']['latex'] ?? '')->not->toBeEmpty();

    // 3. Eksekusi penambahan ke akhir naskah (append)
    $revisionBeforeAppend = $component->get('editorRevision');
    $component->call('executeCurrentModel', 'Rumuskan kesimpulan naskah', 'append');
    expect($component->get('editorRevision'))->toBeGreaterThan($revisionBeforeAppend);

    $latestBlocks = $component->get('blocks');
    $appendedTexts = implode(' ', array_column(array_slice($latestBlocks, -6), 'text'));
    expect($appendedTexts)->toContain('Kesimpulan');
});

it('performs black box verification of executing current model in studio and persisting to database', function (): void {
    // 1. Buat dokumen awal di basis data
    $doc = NaskahDocument::query()->create([
        'title' => 'Naskah Uji Eksekusi Model LLM',
        'blocks' => [
            ['type' => 'title', 'level' => 0, 'text' => 'Naskah Uji Eksekusi Model LLM'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf pengantar awal sebelum dieksekusi dengan model terpilih.'],
        ],
        'word_count' => 18,
    ]);

    // 2. Mount Livewire NaskahStudioApp dengan dokumen yang sudah dibuat
    $component = Livewire::test(NaskahStudioApp::class, ['documentId' => $doc->id]);

    // 3. Interaksi pengguna memilih model dan mengeksekusi prompt
    $component->set('currentAssistantModel', 'deepseek-chat')
        ->set('executeModelTarget', 'current_page')
        ->set('executeModelPrompt', 'Kembangkan hipotesis riset dan tinjauan literatur')
        ->call('submitExecuteModel')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    // Pastikan prompt input dibersihkan setelah eksekusi
    expect($component->get('executeModelPrompt'))->toBe('')
        ->and($component->get('currentAssistantModel'))->toBe('deepseek-chat');

    // Pastikan blok dokumen bertambah hasil penerapan eksekusi model
    $runtimeBlocks = $component->get('blocks');
    expect(count($runtimeBlocks))->toBeGreaterThan(2);

    $assistantLog = $component->get('assistantLog');
    expect(count($assistantLog))->toBeGreaterThanOrEqual(2);

    $assistantReply = end($assistantLog);
    expect($assistantReply['role'])->toBe('assistant')
        ->and($assistantReply['model'])->toBe('deepseek-chat')
        ->and($assistantReply['applied'])->toBeTrue();

    // 4. Simpan naskah ke database
    $component->call('saveDocument')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    // 5. Verifikasi di basis data MySQL bahwa blok hasil eksekusi model tersimpan secara permanen
    $savedDoc = NaskahDocument::query()->find($doc->id);
    expect($savedDoc)->not->toBeNull()
        ->and(count($savedDoc->blocks))->toBe(count($runtimeBlocks));

    $foundModelContent = false;
    foreach ($savedDoc->blocks as $block) {
        $text = (string) ($block['text'] ?? '');
        if (str_contains($text, 'hipotesis') || str_contains($text, 'literatur') || str_contains($text, 'Sintesis Akademik') || str_contains($text, 'deepseek-chat')) {
            $foundModelContent = true;
            break;
        }
    }
    expect($foundModelContent)->toBeTrue();
});

it('performs white box verification on OmnigensiChatClient integration when executing current model in studio', function (): void {
    config()->set('omnigensi.agents_enabled', true);
    config()->set('omnigensi.api_key', 'omni_live_test');
    $this->app->forgetInstance(OmnigensiChatClient::class);

    Http::fake([
        '*/health' => Http::response(['status' => 'ok'], 200),
        '*/api/v1/integrations/v1/models' => Http::response(['object' => 'list', 'data' => [
            ['id' => 'claude-3-5-sonnet', 'display_name' => 'Claude 3.5 Sonnet', 'provider' => 'Anthropic', 'provider_id' => 'anthropic', 'is_default' => true],
        ]], 200),
        '*/api/v1/integrations/v1/chat/completions' => Http::response([
            'choices' => [
                ['message' => ['content' => "### Pembahasan Temuan Claude\n\nPenelitian ini membuktikan efektivitas integrasi kecerdasan komputasional.\n\n| Metrik | Skor |\n|---|---|\n| Akurasi | 98.4% |"]],
            ],
            'model' => 'claude-3-5-sonnet',
            'client' => 'Anthropic',
            'latency_ms' => 145,
        ], 200),
    ]);

    $component = Livewire::test(NaskahStudioApp::class);
    $component->call('setAssistantModel', 'claude-3-5-sonnet');

    $initialCount = count($component->get('blocks'));
    $component->call('executeModelToStudio', 'Ulas temuan riset komputasional', 'current_page')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    $blocks = $component->get('blocks');
    expect(count($blocks))->toBeGreaterThan($initialCount);

    $types = array_column($blocks, 'type');
    expect($types)->toContain('heading')
        ->and($types)->toContain('paragraph')
        ->and($types)->toContain('table');

    $log = $component->get('assistantLog');
    $lastEntry = end($log);
    expect($lastEntry['model'])->toBe('claude-3-5-sonnet')
        ->and($lastEntry['data']['source'])->toBe('llm')
        ->and($lastEntry['data']['latency_ms'])->toBe(145);
});

it('performs white box verification on automatic bibliography generation, review mode, and regeneration in studio', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);
    $initialBlocks = $component->get('blocks');
    $initialCount = count($initialBlocks);

    // 1. Eksekusi prompt "buatkan daftar pustaka"
    $component->call('executeCurrentModel', 'buatkan daftar pustaka', 'append')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    // Pastikan masuk ke mode review hasil model
    expect($component->get('isReviewingModelOutput'))->toBeTrue()
        ->and($component->get('modelGenerationCount'))->toBe(1)
        ->and($component->get('lastExecutedModelPrompt'))->toBe('buatkan daftar pustaka')
        ->and($component->get('backupBlocksBeforeReview'))->toEqual($initialBlocks);

    $blocksAfterGen = $component->get('blocks');
    expect(count($blocksAfterGen))->toBeGreaterThan($initialCount);

    // Cari heading "Daftar Pustaka" dan entri referensi
    $hasBiblioHeading = false;
    $hasHangingIndentEntry = false;
    foreach ($blocksAfterGen as $b) {
        if (($b['type'] ?? '') === 'heading' && str_contains((string) ($b['text'] ?? ''), 'Daftar Pustaka')) {
            $hasBiblioHeading = true;
        }
        if (($b['type'] ?? '') === 'paragraph' && ! empty($b['hangingIndent'])) {
            $hasHangingIndentEntry = true;
        }
    }
    expect($hasBiblioHeading)->toBeTrue()
        ->and($hasHangingIndentEntry)->toBeTrue();

    // 2. Simulasi review: jika belum sesuai, lakukan generate ulang
    $revisionBeforeRegen = $component->get('editorRevision');
    $component->call('regenerateCurrentModel')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    // Status review tetap aktif, versi generasi meningkat ke #2
    expect($component->get('isReviewingModelOutput'))->toBeTrue()
        ->and($component->get('modelGenerationCount'))->toBe(2)
        ->and($component->get('editorRevision'))->toBeGreaterThan($revisionBeforeRegen);

    // 3. Terima hasil review (accept)
    $component->call('acceptModelOutput');
    expect($component->get('isReviewingModelOutput'))->toBeFalse()
        ->and($component->get('pendingReviewBlockIndices'))->toBeEmpty()
        ->and($component->get('backupBlocksBeforeReview'))->toBeEmpty();

    // 4. Uji tolak (discard) pada generasi baru
    $savedBlocksCount = count($component->get('blocks'));
    $component->call('executeCurrentModel', 'tuliskan kesimpulan tambahan', 'append');
    expect($component->get('isReviewingModelOutput'))->toBeTrue();

    // Batalkan / tolak hasil
    $component->call('discardModelOutput')
        ->assertDispatched('studio-toast');

    expect($component->get('isReviewingModelOutput'))->toBeFalse()
        ->and(count($component->get('blocks')))->toBe($savedBlocksCount);
});

it('performs black box verification of prompting buatkan daftar pustaka with review and regeneration into database', function (): void {
    // 1. Dokumen naskah awal di basis data
    $doc = NaskahDocument::query()->create([
        'title' => 'Naskah Analisis Kolaboratif Studio',
        'blocks' => [
            ['type' => 'title', 'level' => 0, 'text' => 'Naskah Analisis Kolaboratif Studio'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Penelitian ini menelaah efisiensi penggunaan sumber daya pada studio terbuka.'],
        ],
        'word_count' => 17,
    ]);

    // 2. Mount Livewire NaskahStudioApp
    $component = Livewire::test(NaskahStudioApp::class, ['documentId' => $doc->id]);

    // 3. Pengguna memasukkan prompt "buatkan daftar pustaka" di UI dan submit
    $component->set('executeModelPrompt', 'buatkan daftar pustaka')
        ->set('executeModelTarget', 'append')
        ->call('submitExecuteModel')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    // Verifikasi kanvas terisi dan review aktif
    expect($component->get('isReviewingModelOutput'))->toBeTrue()
        ->and(count($component->get('blocks')))->toBeGreaterThan(2);

    // 4. Pengguna me-review hasil di kanvas dan menekan tombol Generate Ulang
    $component->call('regenerateCurrentModel')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    expect($component->get('modelGenerationCount'))->toBe(2);

    // 5. Pengguna merasa hasil generasi ke-2 sudah sesuai lalu menekan Terima & Terapkan
    $component->call('acceptModelOutput');
    expect($component->get('isReviewingModelOutput'))->toBeFalse();

    // 6. Simpan ke database
    $component->call('saveDocument')
        ->assertSuccessful()
        ->assertDispatched('studio-toast');

    // 7. Verifikasi persistensi di database
    $dbDoc = NaskahDocument::query()->find($doc->id);
    expect($dbDoc)->not->toBeNull()
        ->and(count($dbDoc->blocks))->toBeGreaterThan(2);

    $foundDaftarPustaka = false;
    foreach ($dbDoc->blocks as $block) {
        if (str_contains((string) ($block['text'] ?? ''), 'Daftar Pustaka')) {
            $foundDaftarPustaka = true;
            break;
        }
    }
    expect($foundDaftarPustaka)->toBeTrue();
});

it('performs white box verification on slash commands and direct page execution for current model in studio', function (): void {
    $component = Livewire::test(NaskahStudioApp::class);

    // 1. Eksekusi slash command /models
    $component->set('assistantInput', '/models')
        ->call('sendAssistant');

    $log = $component->get('assistantLog');
    $lastLog = end($log);
    expect($lastLog['role'])->toBe('assistant')
        ->and($lastLog['content'])->toContain('Daftar Model AI Tersedia');

    // 2. Eksekusi slash command /model gpt-4o untuk beralih model aktif
    $component->set('assistantInput', '/model gpt-4o')
        ->call('sendAssistant');

    expect($component->instance()->getCurrentAssistantModel())->toBe('gpt-4o');
    $logAfterSwitch = $component->get('assistantLog');
    $lastSwitchLog = end($logAfterSwitch);
    expect($lastSwitchLog['content'])->toContain('gpt-4o');

    // 3. Eksekusi slash command /eksekusi untuk menerapkan langsung ke kanvas naskah
    $initialBlocks = count($component->get('blocks'));
    $component->set('assistantInput', '/eksekusi Rumuskan bab kesimpulan penelitian')
        ->call('sendAssistant');

    expect(count($component->get('blocks')))->toBeGreaterThan($initialBlocks)
        ->and($component->get('isReviewingModelOutput'))->toBeTrue();

    // 4. Buka dialog eksekusi model via executeCommand
    $component->call('executeCommand', 'ai.execute_model');
    expect($component->get('dialog'))->toBe('execute_model');

    // 5. Eksekusi model langsung ke halaman target via executeModelToPage
    $component->call('executeModelToPage', 1, 'Sisipkan analisis hasil pengujian pada halaman satu')
        ->assertSuccessful();

    // 6. Eksekusi model dengan target new_page
    $blocksBeforeNewPage = count($component->get('blocks'));
    $component->call('executeCurrentModel', 'Tuliskan apendiks dan lampiran data', 'new_page');
    $blocksAfterNewPage = $component->get('blocks');
    expect(count($blocksAfterNewPage))->toBeGreaterThan($blocksBeforeNewPage);

    $hasPageBreak = false;
    foreach ($blocksAfterNewPage as $b) {
        if (($b['type'] ?? '') === 'pagebreak') {
            $hasPageBreak = true;
            break;
        }
    }
    expect($hasPageBreak)->toBeTrue();
});

it('performs white box and black box verification on artisan naskah:manage execute-model command', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Naskah Eksekusi CLI',
        'blocks' => [
            ['type' => 'title', 'level' => 0, 'text' => 'Dokumen Naskah Eksekusi CLI'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf awal sebelum eksekusi CLI.'],
        ],
        'word_count' => 12,
    ]);

    // Jalankan artisan command execute-model
    $exitCode = Artisan::call('naskah:manage', [
        'action' => 'execute-model',
        'documentId' => (string) $doc->id,
        '--prompt' => 'Buatkan tabel evaluasi metrik kinerja sistem',
        '--model' => 'deepseek-chat',
        '--target' => 'append',
    ]);

    expect($exitCode)->toBe(0);

    // Verifikasi perubahan pada basis data
    $updatedDoc = NaskahDocument::query()->find($doc->id);
    expect($updatedDoc)->not->toBeNull()
        ->and(count($updatedDoc->blocks))->toBeGreaterThan(2);

    $hasTable = false;
    foreach ($updatedDoc->blocks as $block) {
        if (($block['type'] ?? '') === 'table') {
            $hasTable = true;
            break;
        }
    }
    expect($hasTable)->toBeTrue();
});

it('performs black box verification on naskah studio REST API execute-model endpoint', function (): void {
    $doc = NaskahDocument::query()->create([
        'title' => 'Dokumen Naskah Eksekusi API',
        'blocks' => [
            ['type' => 'title', 'level' => 0, 'text' => 'Dokumen Naskah Eksekusi API'],
            ['type' => 'paragraph', 'level' => 0, 'text' => 'Paragraf pendahuluan untuk uji REST API.'],
        ],
        'word_count' => 14,
    ]);

    $response = $this->postJson(route('api.v1.naskah.execute-model', ['id' => $doc->id]), [
        'prompt' => 'Susun perumusan masalah dan tujuan penelitian',
        'model' => 'claude-3-5-sonnet',
        'target' => 'current_page',
        'page' => 1,
    ]);

    $response->assertStatus(200)
        ->assertJson([
            'status' => 'success',
            'data' => [
                'document_id' => $doc->id,
                'model' => 'claude-3-5-sonnet',
                'target' => 'current_page',
            ],
        ]);

    $dbDoc = NaskahDocument::query()->find($doc->id);
    expect(count($dbDoc->blocks))->toBeGreaterThan(2);
});

it('performs white box verification on executing and applying current model to studio from ai chat and assistant pages', function (): void {
    // 1. Eksekusi dari AiChatPage dengan model aktif
    $aiChat = Livewire::test(AiChatPage::class);
    $aiChat->set('selectedModel', 'omnigensi|gpt-4o')
        ->set('draft', 'Rancang kerangka bab metode penelitian kuantitatif')
        ->call('executeCurrentModelToStudio')
        ->assertSuccessful()
        ->assertNotified();

    $messages = $aiChat->get('messages');
    expect(count($messages))->toBeGreaterThanOrEqual(2);

    $lastMsg = end($messages);
    expect($lastMsg['role'])->toBe('assistant')
        ->and($lastMsg['model'])->toBe('gpt-4o');

    // Verifikasi dokumen studio terbuat atau terisi
    $latestDoc = NaskahDocument::query()->latest('id')->first();
    expect($latestDoc)->not->toBeNull()
        ->and(count($latestDoc->blocks))->toBeGreaterThan(1);

    // 2. Terapkan pesan spesifik dari AiChatPage ke studio
    $initialBlockCount = count($latestDoc->blocks);
    $aiChat->call('applyMessageToStudio', count($messages) - 1, $latestDoc->id, 'append')
        ->assertSuccessful()
        ->assertNotified();

    $reloadedDoc = NaskahDocument::query()->find($latestDoc->id);
    expect(count($reloadedDoc->blocks))->toBeGreaterThan($initialBlockCount);

    // 3. Terapkan dari XishieAssistantPage ke studio
    $project = makeProject(['document_type' => DocumentType::SKRIPSI]);
    $asstPage = Livewire::test(XishieAssistantPage::class, ['projectId' => $project->id]);
    $asstPage->set('message', '/sitasi')
        ->call('send');

    $asstLog = $asstPage->get('log');
    expect(count($asstLog))->toBeGreaterThanOrEqual(2);

    $asstPage->call('applyToNaskahStudio', count($asstLog) - 1)
        ->assertSuccessful()
        ->assertNotified();

    $projectDoc = NaskahDocument::query()
        ->where('source_type', NaskahDocument::SOURCE_PROJECT)
        ->where('project_id', $project->id)
        ->first();
    expect($projectDoc)->not->toBeNull()
        ->and(count($projectDoc->blocks))->toBeGreaterThan(1);
});

it('performs white box and black box verification on models discovery via CLI and API', function (): void {
    // 1. CLI naskah:manage models
    $exitCode = Artisan::call('naskah:manage', ['action' => 'models']);
    expect($exitCode)->toBe(0);

    $cliOutput = Artisan::output();
    expect($cliOutput)->toContain('Model ID')
        ->and($cliOutput)->toContain('Provider')
        ->and($cliOutput)->toContain('gemini-3.8-flash');

    // 2. REST API GET /api/v1/naskah/models
    $response = $this->getJson(route('api.v1.naskah.models'));
    $response->assertStatus(200)
        ->assertJsonStructure([
            'status',
            'data' => [
                '*' => ['id', 'name', 'provider', 'is_default'],
            ],
        ]);

    $data = $response->json('data');
    expect(count($data))->toBeGreaterThanOrEqual(3);
    $modelIds = array_column($data, 'id');
    expect($modelIds)->toContain('gemini-3.8-flash');
});

it('applies current stage and executes model to naskah studio from academic workspace', function (): void {
    $project = makeProject(['document_type' => DocumentType::SKRIPSI]);
    seedReferences($project);
    $stage = app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $workspace = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);

    // 1. Terapkan draf tahap aktif ke Naskah Studio
    $workspace->call('applyCurrentStageToNaskahStudio')
        ->assertSuccessful()
        ->assertNotified();

    $doc = NaskahDocument::query()
        ->where('project_id', $project->id)
        ->first();
    expect($doc)->not->toBeNull()
        ->and($doc->source_type)->toBe(NaskahDocument::SOURCE_PROJECT)
        ->and($doc->source_stage)->toBe(ProjectStage::DRAFT->value)
        ->and(count($doc->blocks))->toBeGreaterThan(1);

    $initialBlockCount = count($doc->blocks);

    // 2. Eksekusi model AI saat ini ke Naskah Studio
    $workspace->call('executeModelToNaskahStudio', 'Sempurnakan bab pendahuluan ini secara ilmiah.', 'append')
        ->assertSuccessful()
        ->assertNotified();

    $reloadedDoc = NaskahDocument::query()->find($doc->id);
    expect(count($reloadedDoc->blocks))->toBeGreaterThan($initialBlockCount);

    // 3. Terapkan pesan asisten ke Naskah Studio
    $prevCount = count($reloadedDoc->blocks);
    $workspace->set('assistantMessage', '/sitasi')
        ->call('sendAssistantMessage')
        ->assertSuccessful();

    $logCount = count($workspace->get('assistantLog'));
    expect($logCount)->toBeGreaterThanOrEqual(2);

    $workspace->call('applyAssistantMessageToStudio', $logCount - 1)
        ->assertSuccessful()
        ->assertNotified();

    $reloadedDoc2 = NaskahDocument::query()->find($doc->id);
    expect(count($reloadedDoc2->blocks))->toBeGreaterThan($prevCount);
});

it('executes current model from xishie assistant page directly to naskah studio', function (): void {
    $project = makeProject(['document_type' => DocumentType::SKRIPSI]);
    $asstPage = Livewire::test(XishieAssistantPage::class, ['projectId' => $project->id]);

    $asstPage->set('message', '/ringkas')
        ->call('executeCurrentModelToStudio')
        ->assertSuccessful()
        ->assertNotified();

    $projectDoc = NaskahDocument::query()
        ->where('source_type', NaskahDocument::SOURCE_PROJECT)
        ->where('project_id', $project->id)
        ->latest('updated_at')
        ->first();

    expect($projectDoc)->not->toBeNull()
        ->and(count($projectDoc->blocks))->toBeGreaterThan(1);
});

it('executes model directly on specific paragraph within naskah studio', function (): void {
    $studio = Livewire::test(NaskahStudioPage::class);
    $studio->call('loadSampleHandbook')
        ->assertSuccessful();

    $paragraphsBefore = $studio->get('blocks');
    expect(count($paragraphsBefore))->toBeGreaterThan(0);

    // Eksekusi model pada paragraf halaman 1, index 0
    $studio->call('executeModelOnParagraph', 1, 0, 'Poles kalimat dengan diksi akademik')
        ->assertSuccessful()
        ->assertNotified();

    $blocksAfter = $studio->get('blocks');
    expect($studio->get('isReviewingModelOutput'))->toBeTrue()
        ->and($blocksAfter[0]['is_under_review'] ?? false)->toBeTrue();
});
