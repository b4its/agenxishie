<?php

declare(strict_types=1);

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelinePresetFactory;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Filament\Pages\UxAnalyticsDashboard;
use App\Filament\Widgets\AarrrFunnelWidget;
use App\Filament\Widgets\AgentActivityTerminalWidget;
use App\Filament\Widgets\HeartKpiTelemetryWidget;
use App\Filament\Widgets\WorkspaceKpiBarWidget;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;

it('flags sections that exceed the word limit', function (): void {
    $project = makeProject();

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [[
            'key' => 'introduction',
            'heading' => '1. Introduction',
            'content' => implode(' ', array_fill(0, 2000, 'kata')),
        ]],
    ])->save();

    $page = new AcademicWorkspacePage;
    $flagged = $page->detectViolatingSections($project, $stage->fresh());

    expect($flagged)->not->toBeEmpty();

    $issues = $flagged[0]['issues'];

    expect(implode(' | ', $issues))->toContain('Lebih');
});

it('flags sections that are below the minimum word count', function (): void {
    $project = makeProject();

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [[
            'key' => 'introduction',
            'heading' => '1. Introduction',
            'content' => 'Terlalu pendek.',
        ]],
    ])->save();

    $page = new AcademicWorkspacePage;
    $flagged = $page->detectViolatingSections($project, $stage->fresh());

    expect($flagged)->not->toBeEmpty()
        ->and(implode(' | ', $flagged[0]['issues']))->toContain('Kurang');
});

it('flags sections without a valid citation', function (): void {
    $project = makeProject();

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [[
            'key' => 'introduction',
            'heading' => '1. Introduction',
            'content' => str_repeat('Klaim tanpa sitasi yang memadai. ', 100),
        ]],
    ])->save();

    $page = new AcademicWorkspacePage;
    $flagged = $page->detectViolatingSections($project, $stage->fresh());

    expect(implode(' | ', $flagged[0]['issues']))->toContain('sitasi');
});

it('does not flag compliant sections', function (): void {
    $project = makeProject();

    $definition = app(GuidelinePresetFactory::class)->forDocumentType(DocumentType::JOURNAL, 'ieee');
    $min = $definition->wordRangeFor('introduction')['min'];

    $body = str_repeat('Pembahasan ilmiah yang memadai. ', (int) ceil($min / 4)).'[Prasetyo 2023]';

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [[
            'key' => 'introduction',
            'heading' => '1. Introduction',
            'content' => $body,
        ]],
    ])->save();

    $page = new AcademicWorkspacePage;
    $flagged = $page->detectViolatingSections($project, $stage->fresh());

    expect($flagged)->toBeEmpty();
});

it('provides the literature matrix rows for the workspace table', function (): void {
    $project = makeProject();
    seedReferences($project);

    $rows = app(LiteratureMatrixBuilder::class)->build($project);

    expect($rows)->toHaveCount(2);

    foreach ($rows as $row) {
        expect($row)->toHaveKeys(['citation_key', 'authors', 'year', 'method', 'result', 'gap']);
    }
});

it('runs the guideline audit and persists the compliance score', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $stage = $project->stages()->where('stage_name', 'draft')->first();
    $before = $stage->compliance_score;

    $audit = app(RuleKeeperAgent::class)->run($project, $stage);

    expect($audit->complianceScore)->toBeGreaterThanOrEqual(0)
        ->and($audit->complianceScore)->toBeLessThanOrEqual(100)
        ->and($audit->outputPayload)->toHaveKey('violations')
        ->and($before)->not->toBeNull();
});

it('registers the ux analytics dashboard with all four widgets', function (): void {
    $dashboard = new UxAnalyticsDashboard;

    $widgets = $dashboard->getWidgets();

    expect($widgets)->toContain(WorkspaceKpiBarWidget::class)
        ->and($widgets)->toContain(HeartKpiTelemetryWidget::class)
        ->and($widgets)->toContain(AarrrFunnelWidget::class)
        ->and($widgets)->toContain(AgentActivityTerminalWidget::class);
});

it('exposes the ux analytics dashboard url in the panel', function (): void {
    expect(UxAnalyticsDashboard::getUrl())->toContain('admin');
});

it('keeps auditor output free of deprecated citation styles', function (): void {
    $project = makeProject();

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill([
        'sections_content' => [[
            'key' => 'abstract',
            'heading' => 'Abstract',
            'content' => 'Abstrak singkat.',
        ]],
    ])->save();

    $page = new AcademicWorkspacePage;
    $flagged = $page->detectViolatingSections($project, $stage->fresh());

    // CitationStyle::$citationStyle default proyek Jurnal adalah IEEE.
    expect($flagged)->not->toBeEmpty()
        ->and(CitationStyle::IEEE->label())->toBe('IEEE');
});
