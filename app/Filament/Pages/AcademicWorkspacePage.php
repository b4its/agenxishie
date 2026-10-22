<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\StageStateManager;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\ProjectRevision;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

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
        $result = app(VenueMatcherAgent::class)->execute($project, $stage);

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

        $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project);
        $compliance = $metrics['guideline_compliance_score']['value'] ?? 0;

        $this->assistantLog[] = [
            'role' => 'assistant',
            'content' => sprintf(
                'Berdasarkan SharedProjectMemory: %d referensi (%d terverifikasi), compliance %.1f%%. Saran: perkuat bagian yang masih melanggar pedoman dan tambahkan sitasi valid.',
                $project->references()->count(),
                $project->references()->where('is_verified', true)->count(),
                $compliance,
            ),
        ];

        $this->assistantMessage = '';
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
            'pendingGate' => $pending,
            'agentLogs' => $project->agentLogs()->latest('id')->limit(15)->get(),
        ];
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
