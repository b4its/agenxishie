<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AcademicVenue;
use Filament\Pages\Page;

/**
 * VenueCatalogPage: jelajahi katalog jurnal SINTA / Scopus / WoS — pencarian
 * dan filter berdasarkan cakupan, peringkat indeks, dan bidang keilmuan.
 *
 * Katalog ini menjadi sumber pencocokan VenueMatcherAgent; halaman ini memberi
 * pengguna transparansi & eksplorasi langsung atas katalog tersebut.
 */
class VenueCatalogPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static ?string $navigationLabel = 'Venue Catalog';

    protected static ?string $title = 'Journal Venue Catalog';

    protected static ?int $navigationSort = 6;

    protected string $view = 'filament.pages.venue-catalog';

    public string $search = '';

    public string $scope = '';

    public string $tier = '';

    /**
     * Opsi cakupan (scope_type) dari data aktual.
     *
     * @return array<string, string>
     */
    public function scopeOptions(): array
    {
        return AcademicVenue::query()
            ->select('scope_type')
            ->distinct()
            ->orderBy('scope_type')
            ->pluck('scope_type', 'scope_type')
            ->map(static fn (string $s): string => strtoupper($s))
            ->all();
    }

    /**
     * Opsi peringkat indeks (index_tier) dari data aktual.
     *
     * @return array<int, string>
     */
    public function tierOptions(): array
    {
        return AcademicVenue::query()
            ->select('index_tier')
            ->distinct()
            ->orderBy('index_tier')
            ->pluck('index_tier')
            ->all();
    }

    /**
     * Venue yang lolos filter, beserta ringkasan.
     *
     * @return array<int, array<string, mixed>>
     */
    public function venues(): array
    {
        $query = AcademicVenue::query()->where('is_active', true)->orderBy('journal_name');

        $query->when(trim($this->search) !== '', function ($q): void {
            $term = '%'.trim($this->search).'%';
            $q->where(function ($inner) use ($term): void {
                $inner->where('journal_name', 'like', $term)
                    ->orWhere('publisher', 'like', $term)
                    ->orWhere('aims_and_scope', 'like', $term);
            });
        });

        $query->when($this->scope !== '', fn ($q) => $q->where('scope_type', $this->scope));
        $query->when($this->tier !== '', fn ($q) => $q->where('index_tier', $this->tier));

        return $query->limit(200)->get()->map(static fn (AcademicVenue $venue): array => [
            'id' => $venue->id,
            'journal_name' => $venue->journal_name,
            'publisher' => $venue->publisher,
            'scope_type' => $venue->scope_type,
            'index_tier' => $venue->index_tier,
            'field_of_study' => $venue->field_of_study,
            'apc' => $venue->apc_estimate !== null ? (float) $venue->apc_estimate : null,
            'submission_url' => $venue->submission_url,
            'review_time_days' => $venue->review_time_days,
            'aims_and_scope' => (string) $venue->aims_and_scope,
        ])->all();
    }

    /**
     * Reset seluruh filter.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->scope = '';
        $this->tier = '';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'venues' => $this->venues(),
            'scopeOptions' => $this->scopeOptions(),
            'tierOptions' => $this->tierOptions(),
            'totalVenues' => AcademicVenue::query()->where('is_active', true)->count(),
        ];
    }
}
