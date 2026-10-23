<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\NextActionService;
use App\Filament\Pages\PlatformMatcherPage;
use App\Filament\Widgets\NextActionsWidget;
use App\Filament\Widgets\ProjectsOverviewWidget;
use App\Models\PlatformRecommendation;
use App\Models\User;
use Livewire\Livewire;

/**
 * Dashboard command-center (overview + next actions) & manajemen target jurnal.
 */
it('suggests fetching references when a project has none', function (): void {
    $project = makeProject(['control_mode' => ControlMode::HITL]);

    $actions = app(NextActionService::class)->forProject($project);
    $keys = array_column($actions, 'key');

    expect($keys)->toContain('references');
});

it('suggests drafting when the stage has no sections', function (): void {
    $project = makeProject();
    seedReferences($project); // referensi terverifikasi (default true)

    $keys = array_column(app(NextActionService::class)->forProject($project), 'key');

    expect($keys)->toContain('draft');
});

it('suggests advancing when a stage is completed', function (): void {
    $project = makeProject();
    seedReferences($project);
    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    $stage->forceFill(['status' => StageStatus::COMPLETED, 'sections_content' => [
        ['key' => 'chapter1', 'heading' => 'Bab 1', 'content' => 'isi'],
    ]])->save();

    $keys = array_column(app(NextActionService::class)->forProject($project), 'key');

    expect($keys)->toContain('advance')->not->toContain('draft');
});

it('shows an all-good action when nothing is pending', function (): void {
    $project = makeProject();
    seedReferences($project);
    $stage = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first()
        ?? $project->stages()->first();
    $project->forceFill(['current_stage' => ProjectStage::FINAL])->save();
    $project->stages()->where('stage_name', ProjectStage::FINAL->value)->update([
        'status' => StageStatus::COMPLETED,
        'sections_content' => json_encode([['key' => 'abstract', 'heading' => 'Abstrak', 'content' => 'isi [Ravi 2021]']]),
        'compliance_score' => 90,
    ]);

    // Punya rekomendasi jurnal agar tidak memicu saran 'venue'.
    PlatformRecommendation::create([
        'project_id' => $project->id, 'journal_name' => 'Jurnal Target', 'match_score' => 90,
    ]);

    $actions = app(NextActionService::class)->forProject($project->refresh());

    expect(array_column($actions, 'key'))->toContain('done');
});

it('renders the projects overview widget with project rows', function (): void {
    $this->actingAs(User::factory()->create());
    makeProject(['title' => 'Proyek Dashboard Alpha']);

    Livewire::test(ProjectsOverviewWidget::class)
        ->assertSuccessful()
        ->assertSee('Proyek Dashboard Alpha');
});

it('renders the next-actions widget with guidance', function (): void {
    $this->actingAs(User::factory()->create());
    makeProject(['title' => 'Proyek Butuh Aksi']);

    Livewire::test(NextActionsWidget::class)
        ->assertSuccessful()
        ->assertSee('Proyek Butuh Aksi');
});

it('selects a single target journal and clears the others', function (): void {
    $user = User::factory()->create();
    $project = makeProject();

    $a = PlatformRecommendation::create([
        'project_id' => $project->id, 'journal_name' => 'Jurnal A', 'match_score' => 90, 'is_selected' => false,
    ]);
    $b = PlatformRecommendation::create([
        'project_id' => $project->id, 'journal_name' => 'Jurnal B', 'match_score' => 80, 'is_selected' => false,
    ]);

    $this->actingAs($user);

    Livewire::test(PlatformMatcherPage::class)
        ->call('selectTarget', $b->id)
        ->assertSuccessful();

    expect((bool) $b->fresh()->is_selected)->toBeTrue()
        ->and((bool) $a->fresh()->is_selected)->toBeFalse();
});

it('deletes a stale recommendation', function (): void {
    $user = User::factory()->create();
    $project = makeProject();
    $rec = PlatformRecommendation::create([
        'project_id' => $project->id, 'journal_name' => 'Jurnal Usang', 'match_score' => 10,
    ]);

    $this->actingAs($user);

    Livewire::test(PlatformMatcherPage::class)
        ->call('deleteRecommendation', $rec->id)
        ->assertSuccessful();

    expect(PlatformRecommendation::find($rec->id))->toBeNull();
});
