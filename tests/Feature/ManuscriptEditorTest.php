<?php

declare(strict_types=1);

use App\Domain\Workspace\Enums\ProjectStage;
use App\Filament\Pages\AcademicWorkspacePage;
use App\Models\User;
use App\Models\XishieProject;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Livewire\Livewire;

/**
 * Editor naskah inline di Academic Workspace: menyunting, menambah,
 * menghapus, mengurutkan, dan menyimpan sub-bab langsung dari editor.
 */
it('renders the manuscript editor with a textarea per existing section', function (): void {
    $project = makeProject();
    seedReferences($project);

    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);
    $project->refresh();

    $stage = $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    $sectionCount = count((array) $stage->sections_content);

    expect($sectionCount)->toBeGreaterThan(0);

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->assertSet('editorSections', fn (array $sections): bool => count($sections) === $sectionCount)
        ->assertSee('Simpan Naskah')
        ->assertSee('Sub-bab');
});

it('saves inline editor changes to sections content, compiled content, and word count', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $page = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);

    $sections = $page->get('editorSections');
    expect($sections)->not->toBeEmpty();

    $editedText = 'Kalimat hasil suntingan manual pada editor naskah.';
    $sections[0]['content'] = $editedText;

    $page->set('editorSections', $sections)
        ->call('saveEditor')
        ->assertHasNoErrors();

    $stage = $project->fresh()->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect((string) $stage->sections_content[0]['content'])->toBe($editedText)
        ->and((string) $stage->compiled_content)->toContain($editedText)
        ->and($stage->word_count)->toBeGreaterThan(0);
});

it('adds, removes, and reorders editor sections without losing other sections', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $page = Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id]);
    $originalCount = count($page->get('editorSections'));

    $page->call('addEditorSection');
    expect($page->get('editorSections'))->toHaveCount($originalCount + 1);

    $page->call('removeEditorSection', $originalCount);
    expect($page->get('editorSections'))->toHaveCount($originalCount);

    $headingsBefore = array_column($page->get('editorSections'), 'heading');
    $page->call('moveEditorSection', 1, -1);
    $headingsAfter = array_column($page->get('editorSections'), 'heading');

    expect($headingsAfter[0])->toBe($headingsBefore[1])
        ->and($headingsAfter[1])->toBe($headingsBefore[0]);
});

it('persists a newly added manual section and keeps stage status untouched', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    $stageBefore = $project->fresh()->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->call('addEditorSection')
        ->call('saveEditor')
        ->assertHasNoErrors();

    $stage = $project->fresh()->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();

    expect((string) $stage->compiled_content)->toContain('Sub-bab Baru')
        ->and($stage->status)->toBe($stageBefore->status);
});

it('marks the editor dirty when a section is added or removed', function (): void {
    $project = makeProject();
    seedReferences($project);
    app(XishieSupervisor::class)->run($project, ProjectStage::DRAFT);

    Livewire::test(AcademicWorkspacePage::class, ['projectId' => $project->id])
        ->assertSet('editorDirty', false)
        ->call('addEditorSection')
        ->assertSet('editorDirty', true)
        ->call('saveEditor')
        ->assertSet('editorDirty', false);
});

it('shows an actionable empty state when no project exists', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    // Singkirkan seluruh proyek agar workspace masuk ke empty state.
    XishieProject::query()->delete();

    $this->get('/admin/academic-workspace')
        ->assertStatus(200)
        ->assertSee('Belum ada proyek untuk disunting')
        ->assertSee('Buat Proyek Baru');
});

it('resolves the manuscript editor at a predictable admin slug', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->get('/admin/academic-workspace')->assertStatus(200);
    $this->get('/admin/projects')->assertStatus(200);
});
