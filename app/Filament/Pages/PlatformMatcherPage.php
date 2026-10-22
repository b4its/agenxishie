<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Publication\ManuscriptAdapter;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * PlatformMatcherPage: kartu rekomendasi jurnal SINTA / Scopus / WoS
 * hasil VenueMatcherAgent beserta Match Score dan Aims & Scope.
 */
class PlatformMatcherPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Platform Matcher';

    protected static ?string $title = 'Manuscript Platform Matcher';

    protected static ?int $navigationSort = 4;

    protected string $view = 'filament.pages.platform-matcher';

    public ?int $projectId = null;

    public function mount(): void
    {
        $this->projectId = request()->query('project') !== null
            ? (int) request()->query('project')
            : XishieProject::query()->latest()->value('id');
    }

    public function getProject(): ?XishieProject
    {
        return $this->projectId !== null ? XishieProject::find($this->projectId) : null;
    }

    public function runMatcher(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $stage = app(XishieSupervisor::class)->ensureStage($project, $project->current_stage);
        $result = app(VenueMatcherAgent::class)->run($project, $stage);

        Notification::make()
            ->title('Pencocokan venue selesai')
            ->body($result->metadata['message'] ?? '')
            ->success()
            ->send();
    }

    public function adaptFormatting(int $recommendationId): void
    {
        $project = $this->getProject();
        $recommendation = $project?->recommendations()->find($recommendationId);

        if ($project === null || $recommendation === null) {
            return;
        }

        $result = app(ManuscriptAdapter::class)->adapt($project, $recommendation);

        // Simpan naskah terformat sebagai artefak stage Final.
        $final = $project->stages()->firstOrCreate(
            ['stage_name' => ProjectStage::FINAL->value],
            ['started_at' => now()],
        );

        $rules = (array) ($final->hitl_pending_payload ?? []);
        $rules['adapted_manuscript'] = [
            'journal_name' => $result['journal_name'],
            'index_tier' => $result['index_tier'],
            'citation_style' => $result['citation_style'],
            'word_count' => $result['word_count'],
            'within_limit' => $result['within_limit'],
            'manuscript' => $result['manuscript'],
            'adapted_at' => now()->toIso8601String(),
        ];

        $final->forceFill(['hitl_pending_payload' => $rules])->save();

        Notification::make()
            ->title('Naskah diformat untuk '.$result['journal_name'])
            ->body(sprintf(
                'Gaya %s, %s kata%s.',
                strtoupper($result['citation_style']),
                number_format($result['word_count']),
                $result['within_limit'] ? ' (sesuai batas)' : ' (melebihi batas '.$result['max_words'].' kata)',
            ))
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->getProject();

        return [
            'project' => $project,
            'recommendations' => $project?->recommendations()->get() ?? collect(),
        ];
    }
}
