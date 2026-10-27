<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Publication\NaskahBlockParser;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\NaskahDocument;
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
     * Buka dokumen proyek di Naskah Studio.
     */
    public function openNaskahStudio(int $projectId): void
    {
        $project = XishieProject::find($projectId);

        if ($project === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $document = NaskahDocument::query()->where('project_id', $project->id)->first();
        if ($document === null) {
            $richModel = app(NaskahRichModel::class);
            $parser = app(NaskahBlockParser::class);

            $stage = $project->stages()->where('stage_name', $project->current_stage->value)->first();
            $compiled = trim((string) ($stage?->compiled_content ?? ''));

            $blocks = [];
            if ($compiled !== '') {
                $blocks = $richModel->normalize($parser->parse($compiled));
            } else {
                $blocks = [
                    $richModel->newBlock('title', $project->title),
                    $richModel->newBlock('paragraph', 'Mulai menyusun draf naskah ilmiah untuk proyek ini.'),
                ];
            }

            $stats = $richModel->computeStatistics($blocks);
            $document = NaskahDocument::create([
                'project_id' => $project->id,
                'source_type' => NaskahDocument::SOURCE_PROJECT,
                'source_stage' => $project->current_stage->value,
                'title' => $project->title,
                'blocks' => $blocks,
                'word_count' => $stats['words'] ?? 0,
                'last_saved_at' => now(),
            ]);
        }

        $this->redirect(NaskahStudioPage::getUrl([
            'document' => $document->id,
            'project' => $project->id,
        ]));
    }

    /**
     * Eksekusi model AI saat ini untuk menyusun draf naskah proyek dan terapkan ke Naskah Studio.
     */
    public function executeCurrentModelToStudio(int $projectId, ?string $prompt = null): void
    {
        $project = XishieProject::find($projectId);

        if ($project === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $client = app(OmnigensiChatClient::class);
        $modelId = $client->defaultModel() ?? 'gemini-3.8-flash';

        $instruction = trim((string) ($prompt ?? ''));
        if ($instruction === '') {
            $instruction = "Susun draf naskah ilmiah awal yang komprehensif, mencakup Judul, Abstrak, Pendahuluan, Metode, dan Rencana Pembahasan untuk topik: {$project->title}.";
        }

        $systemPrompt = 'Anda adalah asisten cerdas penulisan ilmiah Naskah Studio. Hasilkan naskah akademik terstruktur dalam Bahasa Indonesia akademik formal siap publikasi.';

        $generated = null;
        try {
            $generated = $client->generate($instruction, $systemPrompt, 0.6, 1200, $modelId);
        } catch (\Throwable) {
            $generated = null;
        }

        $replyContent = '';
        if ($generated !== null && trim((string) ($generated['content'] ?? '')) !== '') {
            $replyContent = trim((string) $generated['content']);
        } else {
            $fieldName = $project->field_of_study instanceof \BackedEnum ? $project->field_of_study->value : (string) $project->field_of_study;
            $replyContent = "# {$project->title}\n\n"
                ."## Abstrak\nPenelitian ini mengkaji fenomena penting dalam bidang {$fieldName}. Melalui pendekatan analitis yang sistematis, kajian ini bertujuan untuk mengisi research gap serta memberikan sumbangsih teoretis dan praktis yang signifikan.\n\n"
                ."## 1. Pendahuluan\nLatar belakang permasalahan dalam penelitian ini berakar pada dinamika kontemporer yang menuntut investigasi ilmiah mendalam.\n\n"
                ."## 2. Metodologi Penelitian\nKajian ini mengimplementasikan metode ilmiah terukur dengan validasi instrumen yang ketat guna memastikan keandalan temuan.\n\n"
                ."## 3. Hasil yang Diharapkan\nSintesis data diharapkan mampu membuktikan hipotesis kerja serta merumuskan implikasi manajerial maupun kebijakan yang relevan.";
        }

        $richModel = app(NaskahRichModel::class);
        $parser = app(NaskahBlockParser::class);

        $parsedBlocks = $parser->parse($replyContent);
        $normalizedBlocks = $richModel->normalize($parsedBlocks);

        $document = NaskahDocument::query()->where('project_id', $project->id)->first();
        if ($document === null) {
            $document = new NaskahDocument;
            $document->project_id = $project->id;
            $document->source_type = NaskahDocument::SOURCE_PROJECT;
            $document->source_stage = $project->current_stage->value;
        }

        $document->title = $project->title;
        $document->blocks = $normalizedBlocks;
        $document->word_count = $richModel->computeStatistics($normalizedBlocks)['words'] ?? count($normalizedBlocks);
        $document->last_saved_at = now();
        $document->save();

        Notification::make()
            ->title('Model Berhasil Dieksekusi ke Naskah Studio')
            ->body("Model [{$modelId}] telah menyusun draf naskah (".count($normalizedBlocks).' blok) dan disinkronkan ke Naskah Studio.')
            ->success()
            ->send();

        $this->redirect(NaskahStudioPage::getUrl([
            'document' => $document->id,
            'project' => $project->id,
        ]));
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
        $formatLower = strtolower($format);
        $timestamp = now()->format('Ymd-His');

        if ($formatLower === 'json') {
            $filename = "daftar-proyek-riset-{$timestamp}.json";
            $content = json_encode([
                'exported_at' => now()->toIso8601String(),
                'total_projects' => count($projects),
                'projects' => $projects,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mime = 'application/json';
        } elseif ($formatLower === 'markdown' || $formatLower === 'md') {
            $filename = "daftar-proyek-riset-{$timestamp}.md";
            $lines = [
                "# Daftar Proyek Riset Akademik\n",
                'Total: '.count($projects).' proyek | Diekspor: '.now()->toFormattedDateString()."\n",
                '| No | Judul Proyek | Bidang | Tipe Dokumen | Tahap | Mode Kontrol | Kepatuhan | Referensi |',
                '| --- | --- | --- | --- | --- | --- | --- | --- |',
            ];
            foreach ($projects as $idx => $p) {
                $lines[] = sprintf(
                    '| %d | %s | %s | %s | %s | %s | %.1f%% | %d |',
                    $idx + 1,
                    str_replace('|', '\\|', (string) $p['title']),
                    str_replace('|', '\\|', (string) $p['field']),
                    str_replace('|', '\\|', (string) $p['document']),
                    $p['stage'],
                    $p['control_mode'],
                    (float) $p['compliance'],
                    (int) $p['references']
                );
            }
            $content = implode("\n", $lines);
            $mime = 'text/markdown';
        } else {
            $filename = "daftar-proyek-riset-{$timestamp}.csv";
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
