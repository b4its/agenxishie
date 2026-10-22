<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\XishieAssistantService;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelineResolverService;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Research\ReferenceImportService;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\StageStateManager;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\OmnigensiAgentLog;
use App\Models\ProjectRevision;
use App\Models\ProjectStageRecord;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;

/**
 * AcademicWorkspacePage: layar kerja utama 3 panel (navigator & riset,
 * editor naskah + HITL gate, Xishie Assistant & telemetri).
 */
class AcademicWorkspacePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Academic Workspace';

    protected static ?string $title = 'Academic Workspace';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.academic-workspace';

    public ?int $projectId = null;

    public string $assistantMessage = '';

    /**
     * Berkas PDF referensi yang diunggah pengguna (Livewire temporary upload).
     */
    public mixed $referenceUpload = null;

    public string $importStatus = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $assistantLog = [];

    public function mount(): void
    {
        $projectParam = request()->query('project');

        $this->projectId = $projectParam !== null
            ? (int) $projectParam
            : XishieProject::query()->latest()->value('id');
    }

    public function getProject(): ?XishieProject
    {
        return $this->projectId !== null ? XishieProject::find($this->projectId) : null;
    }

    public function switchControlMode(string $mode): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $controlMode = ControlMode::tryFrom($mode);

        if ($controlMode === null) {
            return;
        }

        $project->forceFill(['control_mode' => $controlMode])->save();

        Notification::make()
            ->title("Mode kendali diubah ke {$controlMode->label()}")
            ->success()
            ->send();
    }

    public function runPipeline(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $result = app(XishieSupervisor::class)->run($project);

        Notification::make()
            ->title('Pipeline Omnigensi dijalankan')
            ->body('Mode: '.$result['mode'].'. '.(isset($result['compliance_score']) ? 'Compliance: '.$result['compliance_score'].'%.' : ''))
            ->success()
            ->send();
    }

    public function advanceStage(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        try {
            app(StageStateManager::class)->advance($project);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()->title('Tidak dapat lanjut').body($exception->getMessage())->warning()->send();

            return;
        }

        Notification::make()->title('Tahap berikutnya dibuka')->success()->send();
    }

    public function approvePendingSection(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        app(XishieSupervisor::class)->approve($project, $project->current_stage);

        Notification::make()->title('Draf disetujui')->success()->send();
    }

    public function requestRegeneration(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        app(XishieSupervisor::class)->requestRevision($project, $project->current_stage, 'Perkuat argumen dan sitasi.');

        Notification::make()->title('Permintaan revisi dikirim')->warning()->send();
    }

    /**
     * HITL "Edit Manually": simpan suntingan pengguna langsung ke stage.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function saveManualEdit(array $sections): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        app(XishieSupervisor::class)->manualEdit($project, $project->current_stage, $sections);

        Notification::make()
            ->title('Suntingan manual disimpan')
            ->body('Stage ditandai selesai dengan konten hasil editan pengguna.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Simpan suntingan manual dari editor teks bebas.
     *
     * Teks dipecah per penanda "## Heading"; bagian tanpa heading menjadi
     * satu section "manual".
     */
    public function saveManualEditText(string $text): void
    {
        $sections = $this->parseManualText($text);

        if ($sections === []) {
            Notification::make()->title('Naskah kosong, tidak ada yang disimpan')->warning()->send();

            return;
        }

        $this->saveManualEdit($sections);
    }

    /**
     * @return array<int, array{key: string, heading: string, content: string}>
     */
    public function parseManualText(string $text): array
    {
        $normalized = trim(str_replace("\r\n", "\n", $text));

        if ($normalized === '') {
            return [];
        }

        // Pecah berdasarkan heading Markdown "## ...".
        $parts = preg_split('/^##\s+/mu', $normalized) ?: [];
        $sections = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $lines = explode("\n", $part, 2);
            $heading = trim($lines[0]);
            $content = trim($lines[1] ?? '');

            if ($content === '' && count($parts) === 1) {
                $heading = 'Naskah Manual';
                $content = $part;
            }

            $sections[] = [
                'key' => Str::slug($heading) ?: 'manual',
                'heading' => $heading,
                'content' => $content,
            ];
        }

        return $sections;
    }

    /**
     * Teks gabungan stage aktif untuk editor manual.
     */
    public function editableContent(): string
    {
        $stage = $this->stage();

        return $stage?->compile() ?? '';
    }

    /**
     * Impor berkas PDF yang diunggah menjadi referensi proyek (PRD 3.4).
     */
    public function updatedReferenceUpload(): void
    {
        $project = $this->getProject();

        if ($project === null || $this->referenceUpload === null) {
            return;
        }

        try {
            $binary = $this->referenceUpload->get();
            $filename = $this->referenceUpload->getClientOriginalName();

            $reference = app(ReferenceImportService::class)->import($project, $binary, $filename);

            $this->importStatus = 'OK: referensi "'.$reference->citation_key.'" berhasil diimpor.';

            Notification::make()
                ->title('PDF referensi diimpor')
                ->body($reference->title)
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            $this->importStatus = 'Gagal: '.$exception->getMessage();

            Notification::make()
                ->title('Gagal mengimpor PDF')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->referenceUpload = null;
            $this->dispatch('$refresh');
        }
    }

    public function fetchReferences(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $scout = app(ScholarScoutAgent::class);

        $scout->ingestCandidates($project, [
            [
                'title' => 'A Systematic Review of '.$project->title,
                'abstract' => 'Kajian sistematis terkait '.$project->field_of_study->label().' dengan pendekatan '.$project->field_of_study->defaultMethodology().'.',
                'authors' => ['Rahman', 'Salsabila'],
                'year' => now()->year - 1,
                'method' => 'systematic review',
                'result' => 'Sintesis temuan utama',
                'gap' => 'Validasi empiris terbatas',
                'source' => 'openalex',
            ],
        ]);

        Notification::make()->title('Referensi ditambahkan')->success()->send();
    }

    public function matchVenues(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $stage = app(XishieSupervisor::class)->ensureStage($project, $project->current_stage);
        $result = app(VenueMatcherAgent::class)->run($project, $stage);

        Notification::make()
            ->title('Rekomendasi venue diperbarui')
            ->body($result->metadata['message'] ?? '')
            ->success()
            ->send();
    }

    public function sendAssistantMessage(): void
    {
        $project = $this->getProject();

        if ($project === null || trim($this->assistantMessage) === '') {
            return;
        }

        $message = trim($this->assistantMessage);

        $this->assistantLog[] = [
            'role' => 'user',
            'content' => $message,
        ];

        // Slash command kontekstual: /sitasi, /parafrase, /cek-gap, /cek-pedoman,
        // /matriks-literatur, /cari-referensi — atau jawaban kontekstual bebas.
        $result = app(XishieAssistantService::class)->handle($project, $message);

        // Catat query assistant agar KPI "Assistant Query Effectiveness" terukur
        // (PRD KPI #8). "applied" menandai respons yang menghasilkan artefak
        // siap tempel (sitasi, matriks, parafrase, audit).
        OmnigensiAgentLog::create([
            'project_id' => $project->id,
            'stage_id' => $this->stage()?->id,
            'agent_name' => 'Xishie-Assistant',
            'action' => $result['command'],
            'control_mode' => $project->control_mode->value,
            'ttft_ms' => 0,
            'execution_time_ms' => 0,
            'token_usage' => str_word_count((string) $result['reply']),
            'hallucination_rejected_count' => 0,
            'status' => 'success',
            'payload' => [
                'query' => $message,
                'command' => $result['command'],
                'metadata' => [
                    'applied' => in_array($result['command'], ['/sitasi', '/parafrase', '/matriks-literatur', '/cek-pedoman'], true),
                ],
            ],
        ]);

        $this->assistantLog[] = [
            'role' => 'assistant',
            'content' => $result['reply'],
        ];

        $this->assistantMessage = '';
    }

    /**
     * Catat rating kepuasan pengguna (CSAT 1-5) — PRD KPI #18.
     */
    public function rateSatisfaction(int $rating): void
    {
        $project = $this->getProject();

        if ($project === null || $rating < 1 || $rating > 5) {
            return;
        }

        UxKpiTelemetry::create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'metric_key' => UxKpiMetric::USER_SATISFACTION_SCORE,
            'metric_category' => UxKpiMetric::USER_SATISFACTION_SCORE->category(),
            'metric_value' => $rating,
            'metadata' => [
                'stage' => $project->current_stage->value,
                'recorded_by' => 'workspace_widget',
            ],
            'recorded_at' => now(),
        ]);

        Notification::make()
            ->title('Terima kasih atas penilaian Anda')
            ->body("Rating {$rating}/5 tercatat pada telemetri CSAT.")
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->getProject();

        if ($project === null) {
            return [
                'project' => null,
                'progression' => [],
                'sections' => [],
                'metrics' => [],
                'stage' => null,
                'recommendations' => [],
                'references' => [],
                'pendingGate' => null,
                'agentLogs' => [],
            ];
        }

        $stage = $project->stages()->where('stage_name', $project->current_stage->value)->first();
        $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project);

        $pending = null;

        if ($stage !== null && $stage->status === StageStatus::WAITING_APPROVAL) {
            $pending = [
                'agent' => $stage->hitl_pending_payload['agent_name'] ?? 'Omnigensi',
                'section' => $stage->hitl_pending_payload['payload']['pending_section'] ?? null,
            ];
        }

        return [
            'project' => $project,
            'stage' => $stage,
            'stageLabel' => $project->current_stage->label(),
            'progression' => app(StageStateManager::class)->progression($project),
            'sections' => (array) ($stage?->sections_content ?? []),
            'metrics' => $metrics,
            'recommendations' => $project->recommendations()->get(),
            'references' => $project->references()->latest()->limit(20)->get(),
            'literatureMatrix' => app(LiteratureMatrixBuilder::class)->build($project),
            'violationSections' => $stage !== null ? $this->detectViolatingSections($project, $stage) : [],
            'pendingGate' => $pending,
            'agentLogs' => $project->agentLogs()->latest('id')->limit(15)->get(),
        ];
    }

    /**
     * Live Guideline Auditor: tandai sub-bab yang melanggar batas kata atau
     * tidak memiliki sitasi valid (PRD 3.4 & FASE 5).
     *
     * @return array<int, array{key: string, heading: string, issues: array<int, string>}>
     */
    public function detectViolatingSections(XishieProject $project, ProjectStageRecord $stage): array
    {
        $guideline = app(GuidelineResolverService::class)->resolve($project);
        $citationStyle = CitationStyle::tryFrom($guideline->citationStyle)
            ?? CitationStyle::APA7;

        $flagged = [];

        foreach ((array) ($stage->sections_content ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = (string) ($section['key'] ?? '');
            $content = (string) ($section['content'] ?? '');
            $words = str_word_count(strip_tags($content));
            $issues = [];

            $range = $guideline->wordRangeFor($key);

            if ($range !== null && $words < $range['min']) {
                $issues[] = sprintf('Kurang %d kata (min %d)', $range['min'] - $words, $range['min']);
            }

            if ($range !== null && $words > $range['max']) {
                $issues[] = sprintf('Lebih %d kata (maks %d)', $words - $range['max'], $range['max']);
            }

            if ($content !== '' && preg_match($citationStyle->inTextPattern(), $content) !== 1) {
                $issues[] = 'Belum ada sitasi ('.$citationStyle->label().')';
            }

            if ($issues !== []) {
                $flagged[] = [
                    'key' => $key,
                    'heading' => (string) ($section['heading'] ?? $key),
                    'issues' => $issues,
                ];
            }
        }

        return $flagged;
    }

    /**
     * Tombol "Audit Pedoman Instan": jalankan RuleKeeperAgent dan tampilkan hasil.
     */
    public function auditGuideline(): void
    {
        $project = $this->getProject();
        $stage = $this->stage();

        if ($project === null || $stage === null) {
            Notification::make()->title('Belum ada naskah untuk diaudit')->warning()->send();

            return;
        }

        $audit = app(RuleKeeperAgent::class)->run($project, $stage);

        $stage->forceFill([
            'compliance_score' => $audit->complianceScore,
            'compiled_content' => $stage->compile(),
        ])->save();

        $violationCount = count((array) ($audit->outputPayload['violations'] ?? []));

        Notification::make()
            ->title(sprintf('Audit pedoman: %.1f%% patuh', $audit->complianceScore))
            ->body($violationCount === 0
                ? 'Tidak ada pelanggaran terdeteksi.'
                : $violationCount.' pelanggaran terdeteksi pada naskah.')
            ->when($violationCount === 0, fn ($n) => $n->success(), fn ($n) => $n->warning())
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Preview action items revisi terakhir (untuk panel revisi ringkas).
     */
    public function latestRevision(): ?ProjectRevision
    {
        $project = $this->getProject();

        return $project?->revisions()->latest('id')->first();
    }

    public function stage(): ?ProjectStageRecord
    {
        $project = $this->getProject();

        return $project?->stages()->where('stage_name', $project->current_stage->value)->first();
    }

    public function critiques(): PeerCriticAgent
    {
        return app(PeerCriticAgent::class);
    }

    public function stageLabel(): string
    {
        return $this->getProject()?->current_stage->label() ?? ProjectStage::DRAFT->label();
    }
}
