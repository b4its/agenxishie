<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\XishieProject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * ProjectsPage: manajemen siklus hidup proyek — daftar semua proyek beserta
 * statistik ringkas, buka workspace, hapus proyek (beserta seluruh turunannya),
 * dan pintasan membuat proyek baru.
 */
class ProjectsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?string $title = 'Projects';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.projects';

    public string $search = '';

    public function mount(): void
    {
        //
    }

    /**
     * Daftar proyek (difilter kata kunci) beserta statistik ringkas.
     *
     * @return array<int, array<string, mixed>>
     */
    public function projects(): array
    {
        $query = XishieProject::query()->latest();

        $query->when(trim($this->search) !== '', function ($q): void {
            $term = '%'.trim($this->search).'%';
            $q->where(function ($inner) use ($term): void {
                $inner->where('title', 'like', $term)
                    ->orWhere('field_of_study', 'like', $term);
            });
        });

        $kpi = app(UxKpiTelemetryService::class);

        return $query->get()->map(function (XishieProject $project) use ($kpi): array {
            $metrics = $kpi->computeProjectMetrics($project);

            return [
                'id' => $project->id,
                'title' => $project->title,
                'field' => $project->field_of_study->label(),
                'document' => $project->document_type->label(),
                'stage' => $project->current_stage->label(),
                'control_mode' => $project->control_mode->shortLabel(),
                'compliance' => (float) ($metrics['guideline_compliance_score']['value'] ?? 0.0),
                'references' => $project->references()->count(),
                'revisions' => $project->revisions()->count(),
                'recommendations' => $project->recommendations()->count(),
                'updated_at' => $project->updated_at,
            ];
        })->all();
    }

    /**
     * Buka workspace proyek.
     */
    public function openWorkspace(int $projectId): void
    {
        $project = XishieProject::find($projectId);

        if ($project === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $this->redirect(AcademicWorkspacePage::getUrl(['project' => $project->id]));
    }

    /**
     * Hapus proyek beserta seluruh turunannya (stage, referensi, revisi, dsb).
     */
    public function deleteProject(int $projectId): void
    {
        $project = XishieProject::find($projectId);

        if ($project === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $title = $project->title;
        $project->delete();

        Notification::make()
            ->title('Proyek dihapus')
            ->body('"'.$title.'" beserta seluruh datanya telah dihapus.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Buat proyek baru (pintasan ke wizard onboarding).
     */
    public function newProject(): void
    {
        $this->redirect(ProjectOnboardingWizard::getUrl());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'projects' => $this->projects(),
        ];
    }
}
