<?php

declare(strict_types=1);

namespace App\Filament\Pages;

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
        $recommendation = $this->getProject()?->recommendations()->find($recommendationId);

        if ($recommendation === null) {
            return;
        }

        Notification::make()
            ->title('Adaptasi format diterapkan')
            ->body("Template {$recommendation->journal_name} akan diterapkan pada naskah akhir.")
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
