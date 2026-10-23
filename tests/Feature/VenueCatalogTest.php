<?php

declare(strict_types=1);

use App\Domain\Revision\Enums\RevisionStatus;
use App\Filament\Pages\SmartRevisionCenterPage;
use App\Filament\Pages\VenueCatalogPage;
use App\Models\AcademicVenue;
use App\Models\ProjectRevision;
use App\Models\User;
use App\Models\XishieProject;
use Livewire\Livewire;

/**
 * Katalog venue (SINTA/Scopus) + hapus revisi.
 */
function seedVenues(): void
{
    AcademicVenue::create([
        'journal_name' => 'IEEE Access',
        'publisher' => 'IEEE',
        'scope_type' => 'scopus',
        'index_tier' => 'Q1',
        'field_of_study' => 'STEM',
        'aims_and_scope' => 'Multidisciplinary engineering and computer science.',
        'apc_estimate' => 1750,
        'review_time_days' => 30,
        'is_active' => true,
    ]);

    AcademicVenue::create([
        'journal_name' => 'Jurnal RESTI',
        'publisher' => 'Badan Riset',
        'scope_type' => 'sinta',
        'index_tier' => 'SINTA 2',
        'field_of_study' => 'STEM',
        'aims_and_scope' => 'Rekayasa sistem dan teknologi informasi.',
        'apc_estimate' => 0,
        'review_time_days' => 45,
        'is_active' => true,
    ]);
}

it('lists active venues in the catalog', function (): void {
    $this->actingAs(User::factory()->create());
    seedVenues();

    $page = Livewire::test(VenueCatalogPage::class)->assertSuccessful();
    $venues = $page->instance()->venues();

    expect($venues)->toHaveCount(2)
        ->and(array_column($venues, 'journal_name'))->toContain('IEEE Access');
});

it('filters the catalog by scope and tier', function (): void {
    $this->actingAs(User::factory()->create());
    seedVenues();

    $scopus = Livewire::test(VenueCatalogPage::class)->set('scope', 'scopus')->instance()->venues();
    expect($scopus)->toHaveCount(1)->and($scopus[0]['journal_name'])->toBe('IEEE Access');

    $sinta = Livewire::test(VenueCatalogPage::class)->set('tier', 'SINTA 2')->instance()->venues();
    expect($sinta)->toHaveCount(1)->and($sinta[0]['journal_name'])->toBe('Jurnal RESTI');
});

it('searches venues by name', function (): void {
    $this->actingAs(User::factory()->create());
    seedVenues();

    $result = Livewire::test(VenueCatalogPage::class)->set('search', 'IEEE')->instance()->venues();

    expect($result)->toHaveCount(1)->and($result[0]['journal_name'])->toBe('IEEE Access');
});

it('resets filters', function (): void {
    $this->actingAs(User::factory()->create());
    seedVenues();

    $page = Livewire::test(VenueCatalogPage::class)
        ->set('search', 'zzz')
        ->set('scope', 'scopus')
        ->call('resetFilters');

    expect($page->get('search'))->toBe('')
        ->and($page->get('scope'))->toBe('')
        ->and($page->instance()->venues())->toHaveCount(2);
});

it('deletes a revision from the revision center', function (): void {
    $user = User::factory()->create();
    $project = makeProject();

    $revision = ProjectRevision::create([
        'project_id' => $project->id,
        'reviewer_role' => 'pembimbing_1',
        'raw_feedback' => 'Perbaiki.',
        'action_items' => [['id' => 1, 'task' => 'A', 'section_key' => 'chapter1', 'status' => 'pending']],
        'response_matrix_doc' => 'x',
        'status' => RevisionStatus::PENDING,
    ]);

    $this->actingAs($user);

    Livewire::test(SmartRevisionCenterPage::class)
        ->call('deleteRevision', $revision->id)
        ->assertSuccessful();

    expect(ProjectRevision::find($revision->id))->toBeNull();
    expect(XishieProject::find($project->id))->not->toBeNull();
});
