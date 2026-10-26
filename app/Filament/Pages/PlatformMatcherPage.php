<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Publication\ManuscriptAdapter;
use App\Domain\Publication\ManuscriptExporter;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * PlatformMatcherPage: kartu rekomendasi jurnal SINTA / Scopus / WoS
 * hasil VenueMatcherAgent beserta Match Score dan Aims & Scope.
 */
class PlatformMatcherPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-library';

    protected static ?string $navigationLabel = 'Platform Matcher';

    protected static ?string $title = 'Manuscript Platform Matcher';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.platform-matcher';

    public ?int $projectId = null;

    public string $filterScope = '';

    public string $sortBy = 'match_score';

    public ?int $previewRecommendationId = null;

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

        $this->dispatch('$refresh');
    }

    /**
     * Tetapkan sebuah rekomendasi sebagai jurnal target proyek (tunggal).
     */
    public function selectTarget(int $recommendationId): void
    {
        $project = $this->getProject();
        $recommendation = $project?->recommendations()->find($recommendationId);

        if ($project === null || $recommendation === null) {
            return;
        }

        // Hanya satu target: reset yang lain, set yang ini.
        $project->recommendations()->update(['is_selected' => false]);
        $recommendation->forceFill(['is_selected' => true])->save();

        Notification::make()
            ->title('Jurnal target ditetapkan')
            ->body($recommendation->journal_name)
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Hapus satu rekomendasi yang sudah tidak relevan.
     */
    public function deleteRecommendation(int $recommendationId): void
    {
        $recommendation = $this->getProject()?->recommendations()->find($recommendationId);

        if ($recommendation === null) {
            Notification::make()->title('Rekomendasi tidak ditemukan')->warning()->send();

            return;
        }

        $recommendation->delete();

        Notification::make()->title('Rekomendasi dihapus')->success()->send();

        $this->dispatch('$refresh');
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
     * Unduh naskah siap kirim (hasil format venue terpilih) dalam format
     * tertentu — PRD KPI #15 "Manuscript Export Conversion".
     */
    public function exportManuscript(string $format): ?StreamedResponse
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return null;
        }

        $exporter = app(ManuscriptExporter::class);

        if (! $exporter->isSupported($format)) {
            Notification::make()->title('Format tidak didukung')->danger()->send();

            return null;
        }

        // Gunakan naskah teradaptasi bila tersedia (venue terpilih).
        $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
        $adapted = (array) (($final?->hitl_pending_payload ?? [])['adapted_manuscript'] ?? []);
        $stage = $project->current_stage;

        $content = $exporter->render($project, $format, $stage, $adapted !== [] ? $adapted : null);
        $meta = $exporter->formats()[$format];
        $filename = $exporter->filename($project, $format);

        $this->recordExportTelemetry($project, $format, $filename);

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => $meta['mime']],
        );
    }

    /**
     * Unduh daftar rekomendasi jurnal hasil analisis VenueMatcherAgent (CSV / Markdown).
     */
    public function exportRecommendations(string $format = 'csv'): ?StreamedResponse
    {
        $project = $this->getProject();
        if ($project === null) {
            return null;
        }

        $recs = $project->recommendations()->get();
        if ($recs->isEmpty()) {
            Notification::make()->title('Belum ada rekomendasi jurnal untuk proyek ini')->warning()->send();

            return null;
        }

        $base = Str::slug((string) $project->title) ?: 'venue-recommendations';

        if ($format === 'markdown' || $format === 'md') {
            $filename = $base.'-journal-recommendations.md';
            $lines = [
                '# Rekomendasi Jurnal Target — '.$project->title,
                '- **Bidang**: '.$project->field_of_study->label(),
                '- **Target Scope**: '.$project->target_scope->label(),
                '- **Total Rekomendasi**: '.$recs->count(),
                '',
                '| No | Nama Jurnal | Penerbit | Tier | Match Score | Estimasi APC | Target Terpilih |',
                '| --- | --- | --- | --- | --- | --- | --- |',
            ];

            foreach ($recs as $idx => $r) {
                $apc = $r->apc_estimate !== null && (float) $r->apc_estimate > 0
                    ? 'Rp '.number_format((float) $r->apc_estimate, 0, ',', '.')
                    : 'Gratis';

                $lines[] = sprintf(
                    '| %d | %s | %s | %s | %.1f%% | %s | %s |',
                    $idx + 1,
                    str_replace('|', '\\|', (string) $r->journal_name),
                    str_replace('|', '\\|', (string) ($r->publisher ?? '-')),
                    (string) $r->index_tier,
                    (float) $r->match_score,
                    $apc,
                    $r->is_selected ? 'Ya' : 'Tidak',
                );
            }

            $content = implode("\n", $lines);
            $mime = 'text/markdown';
        } elseif ($format === 'json') {
            $filename = $base.'-journal-recommendations.json';
            $data = [
                'project_id' => $project->id,
                'project_title' => $project->title,
                'field_of_study' => $project->field_of_study->value,
                'total_recommendations' => $recs->count(),
                'recommendations' => $recs->map(static fn ($r): array => [
                    'id' => $r->id,
                    'journal_name' => $r->journal_name,
                    'publisher' => $r->publisher,
                    'index_tier' => $r->index_tier,
                    'match_score' => (float) $r->match_score,
                    'apc_estimate' => $r->apc_estimate,
                    'is_selected' => (bool) $r->is_selected,
                    'submission_url' => $r->submission_url,
                ])->all(),
                'exported_at' => now()->toIso8601String(),
            ];
            $content = (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $mime = 'application/json';
        } elseif ($format === 'html') {
            $filename = $base.'-journal-recommendations.html';
            $pTitle = htmlspecialchars((string) $project->title, ENT_QUOTES, 'UTF-8');
            $pField = htmlspecialchars($project->field_of_study->label(), ENT_QUOTES, 'UTF-8');
            $html = "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n";
            $html .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
            $html .= "<title>Rekomendasi Jurnal - {$pTitle}</title>\n";
            $html .= "<style>\nbody { font-family: system-ui, sans-serif; padding: 2rem; color: #1e293b; }\n";
            $html .= "table { width: 100%; border-collapse: collapse; margin-top: 1rem; }\n";
            $html .= "th, td { border: 1px solid #cbd5e1; padding: 0.6rem 0.8rem; text-align: left; }\n";
            $html .= "th { background: #f1f5f9; }\n";
            $html .= ".score { font-weight: bold; color: #0284c7; }\n";
            $html .= "</style>\n</head>\n<body>\n";
            $html .= "<h1>Rekomendasi Venue Jurnal Ilmiah</h1>\n";
            $html .= "<p>Proyek: <strong>{$pTitle}</strong> | Bidang: <strong>{$pField}</strong></p>\n";
            $html .= "<table>\n<thead><tr><th>No</th><th>Nama Jurnal</th><th>Penerbit</th><th>Tier</th><th>Match Score</th><th>Estimasi APC</th><th>Target Terpilih</th></tr></thead>\n<tbody>\n";
            foreach ($recs as $idx => $r) {
                $apc = $r->apc_estimate !== null && (float) $r->apc_estimate > 0
                    ? 'Rp '.number_format((float) $r->apc_estimate, 0, ',', '.')
                    : 'Gratis';
                $jName = htmlspecialchars((string) $r->journal_name, ENT_QUOTES, 'UTF-8');
                $pub = htmlspecialchars((string) ($r->publisher ?? '-'), ENT_QUOTES, 'UTF-8');
                $tier = htmlspecialchars((string) $r->index_tier, ENT_QUOTES, 'UTF-8');
                $score = number_format((float) $r->match_score, 1);
                $sel = $r->is_selected ? 'Ya' : 'Tidak';
                $html .= '<tr><td>'.($idx + 1)."</td><td>{$jName}</td><td>{$pub}</td><td>{$tier}</td><td class=\"score\">{$score}%</td><td>{$apc}</td><td>{$sel}</td></tr>\n";
            }
            $html .= "</tbody></table>\n</body></html>";
            $content = $html;
            $mime = 'text/html; charset=UTF-8';
        } else {
            $filename = $base.'-journal-recommendations.csv';
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['No', 'Nama Jurnal', 'Penerbit', 'Tier', 'Match Score', 'Estimasi APC', 'Target Terpilih', 'Aims and Scope', 'Submission URL']);

            foreach ($recs as $idx => $r) {
                fputcsv($handle, [
                    $idx + 1,
                    $r->journal_name,
                    $r->publisher ?? '-',
                    $r->index_tier,
                    (float) $r->match_score,
                    $r->apc_estimate !== null && (float) $r->apc_estimate > 0 ? (float) $r->apc_estimate : 0,
                    $r->is_selected ? 'Ya' : 'Tidak',
                    $r->scope_reasoning ?? '',
                    $r->submission_url ?? '',
                ]);
            }

            rewind($handle);
            $content = stream_get_contents($handle) ?: '';
            fclose($handle);
            $mime = 'text/csv';
        }

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => $mime],
        );
    }

    /**
     * Catat peristiwa ekspor sebagai jejak agen (audit + umpan KPI #15).
     */
    private function recordExportTelemetry(XishieProject $project, string $format, string $filename): void
    {
        OmnigensiAgentLog::create([
            'project_id' => $project->id,
            'agent_name' => 'Xishie-Exporter',
            'action' => 'export:'.$format,
            'control_mode' => $project->control_mode->value,
            'ttft_ms' => 0,
            'execution_time_ms' => 0,
            'token_usage' => 0,
            'hallucination_rejected_count' => 0,
            'status' => 'success',
            'payload' => [
                'format' => $format,
                'stage' => $project->current_stage->value,
                'filename' => $filename,
                'recorded_by' => 'platform_matcher',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->getProject();
        $recommendations = collect();

        if ($project !== null) {
            $query = $project->recommendations();

            if ($this->filterScope !== '') {
                $query->where('index_tier', 'like', '%'.$this->filterScope.'%');
            }

            match ($this->sortBy) {
                'apc' => $query->reorder('apc_estimate', 'asc'),
                'journal_name' => $query->reorder('journal_name', 'asc'),
                default => $query->reorder('match_score', 'desc'),
            };

            $recommendations = $query->get();
        }

        $final = $project?->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
        $adapted = (array) (($final?->hitl_pending_payload ?? [])['adapted_manuscript'] ?? []);

        return [
            'project' => $project,
            'recommendations' => $recommendations,
            'adaptedManuscript' => $adapted !== [] ? $adapted : null,
            'exportFormats' => app(ManuscriptExporter::class)->formats(),
        ];
    }
}
