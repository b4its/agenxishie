<?php

declare(strict_types=1);

use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\UxKpiCategory;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;

it('computes all eighteen ux kpi metrics', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project->refresh());

    expect($metrics)->toHaveCount(18);

    foreach (UxKpiMetric::cases() as $metric) {
        expect($metrics)->toHaveKey($metric->value);

        $entry = $metrics[$metric->value];

        expect($entry)->toHaveKeys(['label', 'category', 'framework', 'value', 'unit', 'target', 'healthy'])
            ->and($entry['value'])->toBeFloat();
    }
});

it('assigns each metric to the correct framework', function (): void {
    expect(UxKpiMetric::HITL_APPROVAL_RATIO->category()->framework())->toBe('HEART')
        ->and(UxKpiMetric::ONBOARDING_COMPLETION_RATE->category()->framework())->toBe('AARRR')
        ->and(UxKpiMetric::GUIDELINE_COMPLIANCE_SCORE->category())->toBe(UxKpiCategory::TASK_SUCCESS);
});

it('persists a telemetry snapshot for every metric', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(UxKpiTelemetryService::class)->recordProjectSnapshot($project->refresh());

    expect($project->kpiTelemetries()->count())->toBe(18);
});

it('reports onboarding completion from the onboarded timestamp', function (): void {
    $project = makeProject();

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project);

    expect($metrics[UxKpiMetric::ONBOARDING_COMPLETION_RATE->value]['value'])->toBe(100.0);
});

it('computes verified citation density above zero when references exist', function (): void {
    $project = makeProject();
    seedReferences($project);

    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $stage->forceFill(['word_count' => 1000])->save();

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project->refresh());

    expect($metrics[UxKpiMetric::VERIFIED_CITATION_DENSITY->value]['value'])->toBeGreaterThan(0);
});

it('measures revision resolution velocity from action items', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);

    $revision = app(PeerCriticAgent::class)->analyse(
        $project,
        $stage,
        "1. Perbaiki rumusan masalah\n2. Tambahkan sitasi",
        'pembimbing_1',
    );

    $items = (array) $revision->action_items;
    $items[0]['status'] = RevisionStatus::RESOLVED->value;
    $revision->forceFill(['action_items' => $items])->save();

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project->refresh());

    expect($metrics[UxKpiMetric::REVISION_RESOLUTION_VELOCITY->value]['value'])->toBe(50.0);
});

it('tracks hitl approval ratio', function (): void {
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    seedReferences($project);

    $supervisor = app(XishieSupervisor::class);
    $supervisor->run($project, ProjectStage::DRAFT);
    $supervisor->approve($project, ProjectStage::DRAFT);

    $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project->refresh());

    expect($metrics[UxKpiMetric::HITL_APPROVAL_RATIO->value]['value'])->toBe(100.0);
});

it('exposes metrics grouped by framework in the dashboard summary', function (): void {
    $project = makeProject();

    $summary = app(UxKpiTelemetryService::class)->dashboardSummary($project);

    expect($summary)->toHaveKeys(['all', 'ux', 'heart', 'aarrr'])
        ->and($summary['all'])->toHaveCount(18)
        ->and(array_merge($summary['ux'], $summary['heart'], $summary['aarrr']))->toHaveCount(18);
});

it('extracts actionable revision items and builds a response matrix', function (): void {
    $project = makeProject();
    $stage = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::PROPOSAL);

    $revision = app(PeerCriticAgent::class)->analyse(
        $project,
        $stage,
        "1. Perbaiki Bab 2 Tinjauan Pustaka\n2. Metodologi kurang rinci",
        'reviewer_jurnal',
    );

    expect($revision->action_items)->toHaveCount(2)
        ->and($revision->diff_payload)->toHaveCount(2)
        ->and($revision->response_matrix_doc)->toContain('Catatan Reviewer')
        ->and($revision->status)->toBe(RevisionStatus::PENDING);

    foreach ((array) $revision->action_items as $item) {
        expect($item)->toHaveKeys(['id', 'task', 'section_key', 'status', 'paragraph_ref'])
            ->and($item['status'])->toBe(RevisionStatus::PENDING->value);
    }
});
