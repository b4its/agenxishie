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

        return [
            'project' => $project,
            'recommendations' => $project?->recommendations()->get() ?? collect(),
            'exportFormats' => app(ManuscriptExporter::class)->formats(),
        ];
    }
}
