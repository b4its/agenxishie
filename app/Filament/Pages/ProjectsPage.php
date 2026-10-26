<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\XishieProject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ProjectsPage: manajemen siklus hidup proyek — daftar semua proyek beserta
 * statistik ringkas, buka workspace, hapus proyek (beserta seluruh turunannya),
 * dan pintasan membuat proyek baru.
 */
class ProjectsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Projects';

    protected static ?string $slug = 'projects';

    protected static ?string $title = 'Projects';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.projects';

    public string $search = '';

    public string $filterMode = '';

    public string $filterStage = '';

    public function mount(): void
    {
        //
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->filterMode = '';
        $this->filterStage = '';
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

        $query->when($this->filterMode !== '', fn ($q) => $q->where('control_mode', $this->filterMode));
        $query->when($this->filterStage !== '', fn ($q) => $q->where('current_stage', $this->filterStage));

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
     * Duplikasi proyek riset (menyalin metadata, stages, dan pustaka referensi).
     */
    public function duplicateProject(int $projectId): void
    {
        $project = XishieProject::find($projectId);

        if ($project === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $clone = $project->replicate(['id', 'created_at', 'updated_at']);
        $clone->title = 'Salinan dari '.$project->title;
        $clone->save();

        foreach ($project->stages as $stage) {
            $stageClone = $stage->replicate(['id', 'created_at', 'updated_at']);
            $stageClone->project_id = $clone->id;
            $stageClone->save();
        }

        foreach ($project->references as $ref) {
            $refClone = $ref->replicate(['id', 'created_at', 'updated_at']);
            $refClone->project_id = $clone->id;
            $refClone->save();
        }

        Notification::make()
            ->title('Proyek diduplikasi')
            ->body('Berhasil membuat salinan: "'.$clone->title.'".')
            ->success()
            ->send();

        $this->dispatch('$refresh');
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
     * Ekspor daftar proyek ke format CSV atau JSON.
     */
    public function exportProjects(string $format = 'csv'): ?StreamedResponse
    {
        $projects = $this->projects();
        $format = strtolower($format) === 'json' ? 'json' : 'csv';
        $filename = 'daftar-proyek-riset-'.now()->format('Ymd-His').'.'.$format;

        if ($format === 'json') {
            $content = json_encode([
                'exported_at' => now()->toIso8601String(),
                'total_projects' => count($projects),
                'projects' => $projects,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mime = 'application/json';
        } else {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, [
                'No',
                'Judul Proyek',
                'Bidang Studi',
                'Tipe Dokumen',
                'Tahapan',
                'Mode Kontrol',
                'Skor Kepatuhan (%)',
                'Total Referensi',
                'Total Revisi',
                'Total Rekomendasi',
                'Terakhir Diperbarui',
            ]);

            foreach ($projects as $idx => $p) {
                fputcsv($handle, [
                    $idx + 1,
                    $p['title'],
                    $p['field'],
                    $p['document'],
                    $p['stage'],
                    $p['control_mode'],
                    $p['compliance'],
                    $p['references'],
                    $p['revisions'],
                    $p['recommendations'],
                    $p['updated_at'] ? $p['updated_at']->format('Y-m-d H:i:s') : '-',
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle);
            fclose($handle);
            $mime = 'text/csv';
        }

        Notification::make()
            ->title('Daftar Proyek Diekspor')
            ->body(count($projects)." proyek berhasil diekspor ke format {$format}.")
            ->success()
            ->send();

        return response()->streamDownload(
            static fn () => print (string) $content,
            $filename,
            ['Content-Type' => $mime],
        );
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
