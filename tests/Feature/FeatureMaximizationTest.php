<?php

declare(strict_types=1);

namespace Tests\Feature;

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
