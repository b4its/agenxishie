<?php

declare(strict_types=1);

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Publication\CitationFormatter;
use App\Domain\Publication\ManuscriptAdapter;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Research\PdfTextExtractor;
use App\Domain\Research\ReferenceImportService;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Models\AcademicVenue;
use App\Models\OmnigensiAgentLog;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Database\Seeders\AcademicVenueSeeder;

/**
 * Membangun biner PDF minimal yang valid (stream tidak terkompresi)
 * agar ekstraksi teks dapat diuji tanpa berkas eksternal.
 */
function makePdf(string $text): string
{
    $stream = "BT /F1 12 Tf 72 720 Td ({$text}) Tj ET";

    return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n"
        ."2 0 obj\n<< /Length ".strlen($stream)." >>\nstream\n{$stream}\nendstream\nendobj\n"
        ."trailer\n<< /Root 1 0 R >>\n%%EOF";
}

it('extracts text from an uncompressed pdf stream', function (): void {
    $pdf = makePdf('Anomaly Detection in Network Traffic Using Deep Learning');

    $text = app(PdfTextExtractor::class)->extract($pdf);

    expect($text)->toContain('Anomaly Detection')
        ->and($text)->toContain('Deep Learning');
});

it('returns empty text for non-pdf input', function (): void {
    expect(app(PdfTextExtractor::class)->extract('bukan pdf sama sekali'))->toBe('');
});

it('imports a pdf as a project reference with matrix extraction', function (): void {
    $project = makeProject();

    $pdf = makePdf('Prasetyo Hidayat 2023 Anomaly Detection Using Autoencoder. '
        .'Abstract Penelitian ini mengusulkan autoencoder untuk deteksi anomali jaringan dengan akurasi tinggi. '
        .'Metode yang digunakan adalah eksperimen terkontrol.');

    $reference = app(ReferenceImportService::class)->import($project, $pdf, 'anomaly.pdf');

    expect($reference->citation_key)->not->toBeEmpty()
        ->and($reference->source)->toBe('pdf_upload')
        ->and($reference->is_verified)->toBeTrue()
        ->and($reference->embedding)->toHaveCount(1536)
        ->and($reference->pdf_path)->toContain('anomaly.pdf')
        ->and($project->references()->count())->toBe(1);
});

it('rejects a pdf without extractable text', function (): void {
    $project = makeProject();

    expect(fn () => app(ReferenceImportService::class)->import($project, makePdf(''), 'kosong.pdf'))
        ->toThrow(RuntimeException::class);
});

it('builds a literature matrix that includes imported references', function (): void {
    $project = makeProject();

    app(ReferenceImportService::class)->import(
        $project,
        makePdf('Kumar Singh 2022 Survey Machine Learning Intrusion Detection. Abstract A survey of datasets.'),
        'survey.pdf',
    );

    $rows = app(LiteratureMatrixBuilder::class)->build($project);

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toHaveKeys(['citation_key', 'authors', 'year', 'method', 'result', 'gap']);
});

it('parses manual edit text into sections', function (): void {
    $page = new AcademicWorkspacePage;

    $sections = $page->parseManualText("## Bab 1 Pendahuluan\nLatar belakang penelitian.\n\n## Bab 2 Tinjauan Pustaka\nKajian teori.");

    expect($sections)->toHaveCount(2)
        ->and($sections[0]['heading'])->toBe('Bab 1 Pendahuluan')
        ->and($sections[1]['content'])->toBe('Kajian teori.');
});

it('treats heading-less manual text as a single section', function (): void {
    $page = new AcademicWorkspacePage;

    $sections = $page->parseManualText('Naskah tanpa heading sama sekali.');

    expect($sections)->toHaveCount(1)
        ->and($sections[0]['key'])->not->toBeEmpty();
});

it('returns no sections for empty manual text', function (): void {
    $page = new AcademicWorkspacePage;

    expect($page->parseManualText('   '))->toBe([]);
});

it('records assistant query effectiveness telemetry', function (): void {
    $project = makeProject();
    seedReferences($project);

    OmnigensiAgentLog::create([
        'project_id' => $project->id,
        'agent_name' => 'Xishie-Assistant',
        'action' => '/sitasi',
        'control_mode' => $project->control_mode->value,
        'status' => 'success',
        'payload' => ['metadata' => ['applied' => true]],
    ]);

    OmnigensiAgentLog::create([
        'project_id' => $project->id,
        'agent_name' => 'Xishie-Assistant',
        'action' => 'freeform',
        'control_mode' => $project->control_mode->value,
        'status' => 'success',
        'payload' => ['metadata' => ['applied' => false]],
    ]);

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project->refresh());

    expect($metrics[UxKpiMetric::ASSISTANT_QUERY_EFFECTIVENESS->value]['value'])->toBe(50.0);
});

it('records csat rating into telemetry and reflects it in the kpi', function (): void {
    $project = makeProject();

    UxKpiTelemetry::create([
        'project_id' => $project->id,
        'user_id' => $project->user_id,
        'metric_key' => UxKpiMetric::USER_SATISFACTION_SCORE,
        'metric_category' => UxKpiMetric::USER_SATISFACTION_SCORE->category(),
        'metric_value' => 4,
        'metadata' => ['stage' => 'draft'],
        'recorded_at' => now(),
    ]);

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project->refresh());

    expect($metrics[UxKpiMetric::USER_SATISFACTION_SCORE->value]['value'])->toBe(4.0);
});

it('adapts the manuscript to a venue template and stores the artifact', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::FINAL);

    $recommendation = $project->recommendations()->first();
    expect($recommendation)->not->toBeNull();

    $result = app(ManuscriptAdapter::class)->adapt($project, $recommendation);

    expect($result['journal_name'])->toBe($recommendation->journal_name)
        ->and($result['manuscript'])->toContain('# '.$project->title)
        ->and($result['manuscript'])->toContain('## References')
        ->and($result['word_count'])->toBeGreaterThan(0)
        ->and($result['citation_style'])->toBeIn(['apa7', 'ieee', 'vancouver', 'chicago']);
});

it('detects when the adapted manuscript exceeds the venue word limit', function (): void {
    $project = makeProject();
    seedReferences($project);

    $venue = AcademicVenue::create([
        'journal_name' => 'Tiny Limit Journal',
        'publisher' => 'Test Press',
        'scope_type' => 'sinta',
        'index_tier' => 'SINTA 4',
        'field_of_study' => 'stem',
        'aims_and_scope' => 'Scope',
        'template_rules' => ['citation_style' => 'apa7', 'max_words' => 5],
        'is_active' => true,
    ]);

    $recommendation = $project->recommendations()->create([
        'academic_venue_id' => $venue->id,
        'journal_name' => $venue->journal_name,
        'publisher' => $venue->publisher,
        'index_tier' => $venue->index_tier,
        'match_score' => 90,
        'scope_reasoning' => 'mirip',
        'apc_estimate' => 0,
        'submission_url' => 'https://example.org',
    ]);

    $result = app(ManuscriptAdapter::class)->adapt($project, $recommendation);

    expect($result['max_words'])->toBe(5)
        ->and($result['within_limit'])->toBeFalse();
});

it('formats a bibliography for the venue citation style', function (): void {
    $project = makeProject();
    seedReferences($project);

    $entries = app(CitationFormatter::class)->bibliography(
        $project->references()->get(),
        CitationStyle::IEEE,
    );

    expect($entries)->toHaveCount(2);

    foreach ($entries as $entry) {
        expect($entry)->toContain('"');
    }
});

it('keeps reference imports scoped to their own project', function (): void {
    $a = makeProject();
    $b = makeProject();

    $pdf = makePdf('Prasetyo 2023 Deteksi Anomali. Abstract Uji cakupan proyek.');

    app(ReferenceImportService::class)->import($a, $pdf, 'a.pdf');

    expect($a->references()->count())->toBe(1)
        ->and($b->references()->count())->toBe(0)
        ->and(XishieProject::find($b->id)->references()->count())->toBe(0);
});

it('generates venue recommendations for a manual-mode project on demand', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $project = makeProject(['control_mode' => ControlMode::MANUAL]);
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::FINAL);

    $result = app(VenueMatcherAgent::class)->run($project, $stage);

    expect($result->outputPayload['recommendations'])->not->toBeEmpty()
        ->and($project->recommendations()->count())->toBeGreaterThan(0);
});
