<?php

declare(strict_types=1);

use App\Domain\Publication\ManuscriptExporter;
use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Filament\Pages\PlatformMatcherPage;
use App\Filament\Pages\SmartRevisionCenterPage;
use App\Models\ProjectRevision;
use App\Models\XishieProject;
use App\Omnigensi\Orchestrator\XishieSupervisor;

/**
 * Ekspor naskah siap kirim (KPI #15 "Manuscript Export Conversion").
 */
function readyProject(): XishieProject
{
    $project = makeProject();
    seedReferences($project);

    // Isi naskah tahap Draft agar ada isi untuk diekspor.
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    return $project;
}

it('lists the supported export formats with metadata', function (): void {
    $formats = app(ManuscriptExporter::class)->formats();

    expect($formats)->toHaveKeys(['docx', 'markdown', 'latex', 'bibtex', 'text'])
        ->and($formats['docx']['extension'])->toBe('docx')
        ->and($formats['latex']['extension'])->toBe('tex');
});

it('renders markdown with title, sections and bibliography', function (): void {
    $project = readyProject();

    $md = app(ManuscriptExporter::class)->render($project, ManuscriptExporter::FORMAT_MARKDOWN);

    expect($md)->toContain('# '.$project->title)
        ->and($md)->toContain('## ')
        ->and(mb_strlen($md))->toBeGreaterThan(50);
});

it('renders a complete standalone LaTeX document', function (): void {
    $project = readyProject();

    $tex = app(ManuscriptExporter::class)->render($project, ManuscriptExporter::FORMAT_LATEX);

    expect($tex)->toContain('\documentclass')
        ->and($tex)->toContain('\begin{document}')
        ->and($tex)->toContain('\end{document}');
});

it('renders BibTeX entries for the project references', function (): void {
    $project = readyProject();

    $bib = app(ManuscriptExporter::class)->render($project, ManuscriptExporter::FORMAT_BIBTEX);

    expect($bib)->toContain('@')
        ->and($bib)->toContain('title = {');
});

it('renders plain text output', function (): void {
    $project = readyProject();

    $txt = app(ManuscriptExporter::class)->render($project, ManuscriptExporter::FORMAT_TEXT);

    expect($txt)->toContain($project->title)
        ->and($txt)->not->toContain('<w:');
});

it('produces a valid OOXML docx archive', function (): void {
    $project = readyProject();

    $binary = app(ManuscriptExporter::class)->render($project, ManuscriptExporter::FORMAT_DOCX);

    expect($binary)->not->toBe('');
    expect(substr($binary, 0, 2))->toBe('PK'); // zip magic

    $tmp = tempnam(sys_get_temp_dir(), 'xishie-test-docx-');
    file_put_contents($tmp, $binary);

    $zip = new ZipArchive;
    expect($zip->open($tmp))->toBeTrue();

    $required = ['[Content_Types].xml', '_rels/.rels', 'word/document.xml', 'word/styles.xml'];

    foreach ($required as $entry) {
        $xml = $zip->getFromName($entry);
        expect($xml)->not->toBeFalse("[{$entry}] missing");

        $dom = new DOMDocument;
        expect(@$dom->loadXML((string) $xml))->toBeTrue("[{$entry}] not well-formed XML");
    }

    $zip->close();
    @unlink($tmp);
});

it('builds a filename from the project title and format extension', function (): void {
    $project = readyProject();
    $exporter = app(ManuscriptExporter::class);

    expect($exporter->filename($project, ManuscriptExporter::FORMAT_DOCX))->toEndWith('.docx')
        ->and($exporter->filename($project, ManuscriptExporter::FORMAT_LATEX))->toEndWith('.tex');
});

it('rejects an unsupported format', function (): void {
    $project = readyProject();

    app(ManuscriptExporter::class)->render($project, 'pdf');
})->throws(RuntimeException::class);

it('records an export event and closes the KPI #15 conversion from the workspace action', function (): void {
    $project = readyProject();

    Livewire\Livewire::test(AcademicWorkspacePage::class)
        ->call('exportManuscript', ManuscriptExporter::FORMAT_MARKDOWN)
        ->assertSuccessful();

    $log = $project->agentLogs()
        ->where('agent_name', 'Xishie-Exporter')
        ->where('action', 'export:markdown')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->payload['format'])->toBe('markdown');

    // Metrik konversi ekspor kini mencerminkan ekspor nyata (100%).
    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project);
    expect((float) $metrics[UxKpiMetric::MANUSCRIPT_EXPORT_CONVERSION->value]['value'])->toBe(100.0);
});

it('exports from the platform matcher using the adapted manuscript', function (): void {
    $project = readyProject();

    // Bekali naskah Final hasil adaptasi venue.
    $final = $project->stages()->firstOrCreate(
        ['stage_name' => ProjectStage::FINAL->value],
        ['started_at' => now()],
    );
    $final->forceFill(['hitl_pending_payload' => [
        'adapted_manuscript' => [
            'manuscript' => "## Abstrak\n\nNaskah teradaptasi untuk jurnal target.",
            'journal_name' => 'Jurnal Uji',
        ],
    ]])->save();

    Livewire\Livewire::test(PlatformMatcherPage::class)
        ->call('exportManuscript', ManuscriptExporter::FORMAT_MARKDOWN)
        ->assertSuccessful();

    $log = $project->agentLogs()
        ->where('agent_name', 'Xishie-Exporter')
        ->where('action', 'export:markdown')
        ->first();

    expect($log)->not->toBeNull();
});

it('runs the multi-agent graph from the workspace action', function (): void {
    $project = readyProject();

    Livewire\Livewire::test(AcademicWorkspacePage::class)
        ->call('runMultiAgentGraph')
        ->assertSuccessful();

    // Graph mencatat log per node yang dieksekusi.
    expect($project->agentLogs()->where('agent_name', '!=', 'Xishie-Exporter')->count())
        ->toBeGreaterThan(0);
});

it('downloads the response-to-reviewers matrix from the revision center', function (): void {
    $project = readyProject();

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'reviewer_role' => 'pembimbing_1',
        'raw_feedback' => 'Perbaiki rumusan masalah.',
        'action_items' => [['id' => 1, 'task' => 'Perbaiki rumusan masalah', 'section_key' => 'chapter1', 'status' => 'pending']],
        'response_matrix_doc' => "| No | Catatan | Status |\n| --- | --- | --- |\n| 1 | Perbaiki rumusan masalah | pending |",
        'status' => RevisionStatus::PENDING,
    ]);

    Livewire\Livewire::test(SmartRevisionCenterPage::class)
        ->call('downloadResponseMatrix', $revision->id)
        ->assertSuccessful();
});
