<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Database\Seeders\AcademicVenueSeeder;

it('writes full drafts automatically in automation mode', function (): void {
    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    $result = app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($result['mode'])->toBe('automation')
        ->and($draft->status)->toBe(StageStatus::COMPLETED)
        ->and($draft->sections_content)->not->toBeEmpty();
});

it('stops at the approval gate in hitl mode', function (): void {
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    seedReferences($project);

    $result = app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($result['mode'])->toBe('hitl')
        ->and($result['waiting_approval'])->toBeTrue()
        ->and($draft->status)->toBe(StageStatus::WAITING_APPROVAL)
        ->and($draft->hitl_pending_payload)->toBeArray();
});

it('completes the stage after hitl approval', function (): void {
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    seedReferences($project);

    $supervisor = app(XishieSupervisor::class);
    $supervisor->run($project, ProjectStage::DRAFT);
    $supervisor->approve($project, ProjectStage::DRAFT);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($draft->status)->toBe(StageStatus::COMPLETED)
        ->and($draft->hitl_pending_payload['decision'])->toBe('approved');
});

it('records a regeneration decision when revision is requested', function (): void {
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    seedReferences($project);

    $supervisor = app(XishieSupervisor::class);
    $supervisor->run($project, ProjectStage::DRAFT);
    $supervisor->requestRevision($project, ProjectStage::DRAFT, 'Perkuat sitasi.');

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($draft->status)->toBe(StageStatus::IN_PROGRESS)
        ->and($draft->hitl_pending_payload['decision'])->toBe('regenerated');
});

it('does not autowrite content in manual mode', function (): void {
    $project = makeProject(['control_mode' => ControlMode::MANUAL]);
    seedReferences($project);

    $result = app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($result['mode'])->toBe('manual')
        ->and($draft->sections_content)->toBeNull()
        ->and($draft->status)->not->toBe(StageStatus::COMPLETED);
});

it('computes a passive compliance score in manual mode', function (): void {
    $project = makeProject(['control_mode' => ControlMode::MANUAL]);

    $result = app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    expect($result)->toHaveKey('compliance_score')
        ->and($result['violations'])->toBeArray();
});

it('honors a hot-swap of control mode between runs', function (): void {
    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    $supervisor = app(XishieSupervisor::class);
    $supervisor->run($project, ProjectStage::DRAFT);

    $project->forceFill(['control_mode' => ControlMode::MANUAL])->save();

    $result = $supervisor->run($project, ProjectStage::PROPOSAL);

    expect($result['mode'])->toBe('manual');
});

it('logs agent telemetry for each executed agent', function (): void {
    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $agents = $project->agentLogs()->pluck('agent_name')->unique();

    expect($agents)->toContain('Scholar-Scout')
        ->and($agents)->toContain('Academic-Scribe')
        ->and($agents)->toContain('Rule-Keeper');
});

it('generates venue recommendations at the final stage in automation', function (): void {
    $this->seed(AcademicVenueSeeder::class);

    $project = makeProject(['control_mode' => ControlMode::AUTOMATION]);
    seedReferences($project);

    $result = app(XishieSupervisor::class)->run($project, ProjectStage::FINAL);

    expect($result['recommendations'])->not->toBeEmpty()
        ->and($project->recommendations()->count())->toBeGreaterThan(0);
});

it('allows manual editing of a stage', function (): void {
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    $supervisor = app(XishieSupervisor::class);

    $supervisor->run($project, ProjectStage::DRAFT);
    $supervisor->manualEdit($project, ProjectStage::DRAFT, [
        ['key' => 'introduction', 'heading' => '1. Introduction', 'content' => 'Tulisan manual pengguna tanpa sitasi.'],
    ]);

    $draft = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($draft->status)->toBe(StageStatus::COMPLETED)
        ->and($draft->sections_content[0]['content'])->toContain('Tulisan manual');
});
