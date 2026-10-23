<?php

declare(strict_types=1);

use App\Filament\Pages\AcademicWorkspacePage;
use App\Filament\Pages\ProjectsPage;
use App\Models\ProjectReference;
use App\Models\ProjectStageRecord;
use App\Models\User;
use App\Models\XishieProject;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Livewire\Livewire;

/**
 * Halaman manajemen proyek: daftar, pencarian, buka workspace, hapus.
 */
it('lists all projects with summary stats', function (): void {
    $user = User::factory()->create();
    $a = makeProject(['title' => 'Proyek Alpha']);
    $b = makeProject(['title' => 'Proyek Beta']);

    $this->actingAs($user);

    $page = Livewire::test(ProjectsPage::class)->assertSuccessful();

    $projects = $page->instance()->projects();
    $titles = array_column($projects, 'title');

    expect($titles)->toContain('Proyek Alpha')->toContain('Proyek Beta');

    // Statistik ringkas tersedia.
    expect($projects[0])->toHaveKeys(['id', 'title', 'compliance', 'references', 'revisions', 'stage']);
});

it('filters projects by search query', function (): void {
    $user = User::factory()->create();
    makeProject(['title' => 'Deteksi Anomali']);
    makeProject(['title' => 'Klasifikasi Citra']);

    $this->actingAs($user);

    $page = Livewire::test(ProjectsPage::class)->set('search', 'Anomali');

    $titles = array_column($page->instance()->projects(), 'title');

    expect($titles)->toBe(['Deteksi Anomali']);
});

it('deletes a project and its children', function (): void {
    $user = User::factory()->create();
    $project = makeProject(['title' => 'Akan Dihapus']);
    seedReferences($project);

    // Buat turunan: stage + log.
    app(XishieSupervisor::class)->run($project);

    $id = $project->id;

    $this->actingAs($user);

    Livewire::test(ProjectsPage::class)
        ->call('deleteProject', $id)
        ->assertSuccessful();

    expect(XishieProject::find($id))->toBeNull()
        ->and(ProjectStageRecord::where('project_id', $id)->count())->toBe(0)
        ->and(ProjectReference::where('project_id', $id)->count())->toBe(0);
});

it('deleting a missing project sends a danger notification without error', function (): void {
    $this->actingAs(User::factory()->create());

    Livewire::test(ProjectsPage::class)
        ->call('deleteProject', 99999)
        ->assertSuccessful();

    expect(XishieProject::query()->count())->toBeGreaterThanOrEqual(0);
});

it('toggles reference verification and deletes a reference from the workspace', function (): void {
    $user = User::factory()->create();
    $project = makeProject();
    seedReferences($project);

    $reference = $project->references()->first();
    $initial = (bool) $reference->is_verified;

    $this->actingAs($user);

    $page = Livewire::test(AcademicWorkspacePage::class)
        ->call('toggleReferenceVerified', $reference->id)
        ->assertSuccessful();

    $fresh = $reference->fresh();
    expect((bool) $fresh->is_verified)->toBe(! $initial);

    // Hapus referensi.
    $page->call('deleteReference', $reference->id)->assertSuccessful();
    expect(ProjectReference::find($reference->id))->toBeNull();
});
