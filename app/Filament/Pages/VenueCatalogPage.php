<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Models\AcademicVenue;
use App\Models\PlatformRecommendation;
use App\Models\XishieProject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

    public string $sortBy = 'name';

    public ?int $projectId = null;

    public function mount(): void
    {
        $this->projectId = request()->query('project') !== null
            ? (int) request()->query('project')
            : XishieProject::query()->latest('updated_at')->value('id');
    }

    /**
     * Opsi proyek terdaftar.
     *
     * @return array<int, string>
     */
    public function projectOptions(): array
    {
        return XishieProject::query()
            ->latest('updated_at')
            ->pluck('title', 'id')
            ->all();
    }

    /**
     * ID venue yang sedang menjadi target terpilih proyek aktif.
     */
    public function selectedVenueId(): ?int
    {
        if ($this->projectId === null) {
            return null;
        }

        return PlatformRecommendation::query()
            ->where('project_id', $this->projectId)
            ->where('is_selected', true)
            ->value('academic_venue_id');
    }

    /**
     * Jadikan venue ini sebagai target jurnal proyek aktif.
     */
    public function setAsProjectTarget(int $venueId): void
    {
        $project = $this->projectId !== null ? XishieProject::find($this->projectId) : null;
        if ($project === null) {
            Notification::make()
                ->title('Pilih proyek terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        $venue = AcademicVenue::find($venueId);
        if ($venue === null) {
            return;
        }

        $project->recommendations()->update(['is_selected' => false]);

        $rec = PlatformRecommendation::query()->firstOrNew([
            'project_id' => $project->id,
            'academic_venue_id' => $venue->id,
        ]);

        $rec->journal_name = $venue->journal_name;
        $rec->publisher = $venue->publisher;
        $rec->index_tier = $venue->index_tier;
        $rec->match_score = $rec->match_score ?? 85.00;
        $rec->scope_reasoning = $rec->scope_reasoning ?? 'Dipilih langsung oleh peneliti melalui Venue Catalog.';
        $rec->apc_estimate = $venue->apc_estimate;
        $rec->submission_url = $venue->submission_url;
        $rec->is_selected = true;
        $rec->save();

        Notification::make()
            ->title('Target Jurnal Ditetapkan')
            ->body("Jurnal '{$venue->journal_name}' ditetapkan sebagai target untuk {$project->title}.")
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

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

        match ($this->sortBy) {
            'apc_asc' => $query->reorder('apc_estimate', 'asc')->orderBy('journal_name'),
            'review_asc' => $query->reorder('review_time_days', 'asc')->orderBy('journal_name'),
            default => $query->reorder('journal_name', 'asc'),
        };

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
     * Ekspor daftar jurnal hasil filter ke format CSV atau JSON.
     */
    public function exportCatalog(string $format = 'csv'): ?StreamedResponse
    {
        $venues = $this->venues();

        if (empty($venues)) {
            Notification::make()->title('Tidak ada venue yang lolos filter untuk diekspor')->warning()->send();

            return null;
        }

        $timestamp = now()->format('Ymd_His');

        if ($format === 'json') {
            $filename = "katalog-jurnal-{$timestamp}.json";
            $data = [
                'total' => count($venues),
                'exported_at' => now()->toIso8601String(),
                'filters' => [
                    'search' => $this->search,
                    'scope' => $this->scope,
                    'tier' => $this->tier,
                    'sort_by' => $this->sortBy,
                ],
                'venues' => $venues,
            ];
            $content = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $mime = 'application/json';
        } elseif ($format === 'markdown' || $format === 'md') {
            $filename = "katalog-jurnal-{$timestamp}.md";
            $lines = [
                "# Katalog Target Publikasi Jurnal Ilmiah\n",
                'Total: '.count($venues).' venue | Waktu Ekspor: '.now()->toFormattedDateString()."\n",
                '| No | Nama Jurnal | Penerbit | Scope | Tier | Estimasi APC | Waktu Review |',
                '| --- | --- | --- | --- | --- | --- | --- |',
            ];
            foreach ($venues as $idx => $v) {
                $apc = $v['apc'] !== null && (float) $v['apc'] > 0
                    ? 'Rp '.number_format((float) $v['apc'], 0, ',', '.')
                    : 'Gratis';
                $lines[] = sprintf(
                    '| %d | %s | %s | %s | %s | %s | %s hari |',
                    $idx + 1,
                    str_replace('|', '\\|', (string) $v['journal_name']),
                    str_replace('|', '\\|', (string) ($v['publisher'] ?? '-')),
                    strtoupper((string) $v['scope_type']),
                    (string) $v['index_tier'],
                    $apc,
                    (string) ($v['review_time_days'] ?? '-')
                );
            }
            $content = implode("\n", $lines);
            $mime = 'text/markdown';
        } else {
            $filename = "katalog-jurnal-{$timestamp}.csv";
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['No', 'Nama Jurnal', 'Penerbit', 'Scope', 'Tier', 'Bidang Keilmuan', 'Estimasi APC', 'Waktu Review (Hari)', 'URL Submission', 'Aims and Scope']);

            foreach ($venues as $idx => $v) {
                $apc = $v['apc'] !== null && (float) $v['apc'] > 0
                    ? 'Rp '.number_format((float) $v['apc'], 0, ',', '.')
                    : 'Gratis';

                fputcsv($handle, [
                    $idx + 1,
                    $v['journal_name'],
                    $v['publisher'] ?? '-',
                    strtoupper((string) $v['scope_type']),
                    $v['index_tier'],
                    $v['field_of_study'] ?? '-',
                    $apc,
                    $v['review_time_days'] ?? '-',
                    $v['submission_url'] ?? '-',
                    str_replace(["\r", "\n"], ' ', (string) ($v['aims_and_scope'] ?? '')),
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);
            $mime = 'text/csv';
        }

        Notification::make()
            ->title('Katalog Jurnal Diekspor')
            ->body(count($venues)." jurnal berhasil diekspor ke format {$format}.")
            ->success()
            ->send();

        return response()->streamDownload(
            static fn () => print (string) $content,
            $filename,
            ['Content-Type' => $mime],
        );
    }

    /**
     * Reset seluruh filter.
     */
    public function resetFilters(): void
    {
        $this->search = '';
        $this->scope = '';
        $this->tier = '';
        $this->sortBy = 'name';
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
            'projectOptions' => $this->projectOptions(),
            'selectedVenueId' => $this->selectedVenueId(),
        ];
    }
}
