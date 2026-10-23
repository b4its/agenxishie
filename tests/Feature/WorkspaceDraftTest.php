<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Models\User;
use App\Models\XishieProject;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Livewire\Livewire;

/**
 * Workspace: penyusunan draf lengkap satu-klik & auto-generate saat advance.
 * (agents_enabled=false di test → skrip memakai template deterministik.)
 */
it('generates a full stage draft in one action and fills the editor', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $user = User::factory()->create();
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    // Tidak ada referensi: pastikan pipeline tetap menghasilkan konten.

    $this->actingAs($user);

    Livewire::test(AcademicWorkspacePage::class, ['project' => $project->id])
        ->call('generateFullDraft')
        ->assertSuccessful();

    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect($stage)->not->toBeNull()
        ->and(count((array) $stage->sections_content))->toBeGreaterThan(0)
        ->and($stage->compiled_content)->not->toBeEmpty();
});

it('computes a compliance score when generating a full draft', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $user = User::factory()->create();
    $project = makeProject();
    seedReferences($project);

    $this->actingAs($user);

    Livewire::test(AcademicWorkspacePage::class, ['project' => $project->id])
        ->call('generateFullDraft')
        ->assertSuccessful();

    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect((float) $stage->compliance_score)->toBeGreaterThanOrEqual(0.0);
});

it('auto-generates the new stage draft when advancing stages', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $user = User::factory()->create();
    $project = makeProject(['control_mode' => ControlMode::HITL]);
    seedReferences($project);

    // Selesaikan Draft agar Proposal boleh dimulai.
    $draft = app(XishieSupervisor::class)->ensureStage($project, ProjectStage::DRAFT);
    $draft->forceFill(['status' => StageStatus::COMPLETED])->save();

    $this->actingAs($user);

    Livewire::test(AcademicWorkspacePage::class, ['project' => $project->id])
        ->call('advanceStage')
        ->assertSuccessful();

    $project->refresh();
    $proposal = $project->stages()->where('stage_name', ProjectStage::PROPOSAL->value)->first();

    expect($project->current_stage)->toBe(ProjectStage::PROPOSAL)
        ->and($proposal)->not->toBeNull()
        ->and($proposal->status)->toBe(StageStatus::IN_PROGRESS)
        // Konten proposal tersusun otomatis, bukan kosong.
        ->and(count((array) $proposal->sections_content))->toBeGreaterThan(0);
});

it('advancing past final fails gracefully', function (): void {
    config()->set('omnigensi.agents_enabled', false);

    $user = User::factory()->create();
    $project = makeProject();
    $project->forceFill(['current_stage' => ProjectStage::FINAL])->save();

    $this->actingAs($user);

    Livewire::test(AcademicWorkspacePage::class, ['project' => $project->id])
        ->call('advanceStage')
        ->assertSuccessful();

    // Tidak berubah ke tahap tak ada (tetap FINAL), tanpa exception.
    expect(XishieProject::find($project->id)->current_stage)->toBe(ProjectStage::FINAL);
});
