<?php

declare(strict_types=1);

namespace App\Omnigensi\Orchestrator;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Data\AgentExecutionResultData;

/**
 * XishieSupervisor: koordinator tingkat atas yang menggerakkan seluruh agen
 * sesuai control_mode proyek (Automation / HITL / Manual).
 */
final class XishieSupervisor
{
    public function __construct(
        private readonly ExecutionModeRouter $router,
        private readonly ApprovalGateManager $approvalGate,
        private readonly AgentTelemetryRecorder $telemetry,
        private readonly RuleKeeperAgent $ruleKeeper,
        private readonly ScholarScoutAgent $scholarScout,
        private readonly AcademicScribeAgent $academicScribe,
        private readonly VenueMatcherAgent $venueMatcher,
        private readonly UxKpiTelemetryService $kpi,
    ) {}

    /**
     * Pastikan record stage tersedia untuk sebuah tahap.
     */
    public function ensureStage(XishieProject $project, ProjectStage $stage): ProjectStageRecord
    {
        /** @var ProjectStageRecord $record */
        $record = $project->stages()->firstOrCreate(
            ['stage_name' => $stage->value],
            [
                'status' => $stage === ProjectStage::DRAFT ? StageStatus::IN_PROGRESS : StageStatus::LOCKED,
                'started_at' => $stage === ProjectStage::DRAFT ? now() : null,
            ],
        );

        return $record;
    }

    /**
     * Titik masuk utama eksekusi pipeline untuk sebuah tahap.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function run(XishieProject $project, ?ProjectStage $stage = null, array $context = []): array
    {
        $stage ??= $project->current_stage;
        $record = $this->ensureStage($project, $stage);

        // Resolve pedoman lebih dulu agar seluruh mode punya aturan yang sama.
        $this->ruleKeeper->execute($project, $record, $context);

        $route = $this->router->route($project);

        return match ($route) {
            ExecutionModeRouter::RUN_AUTOMATION => $this->runAutomation($project, $record, $context),
            ExecutionModeRouter::RUN_HITL_STEP => $this->runHitlStep($project, $record, $context),
            default => $this->runManualTelemetry($project, $record, $context),
        };
    }

    /**
     * Mode Automation: pipeline penuh Scholar -> Scribe -> RuleKeeper -> Venue (Final).
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function runAutomation(XishieProject $project, ProjectStageRecord $stage, array $context): array
    {
        $stage->forceFill(['status' => StageStatus::IN_PROGRESS])->save();

        // Langkah 1: riset & pemetaan literatur.
        $scholar = $this->scholarScout->execute($project, $stage, $context);
        $this->telemetry->record($project, $stage, $scholar, 'Mencari & mengekstrak literatur');

        // Hot-swap check: bila mode berubah di tengah jalan, hentikan rantai.
        if ($this->router->route($project) !== ExecutionModeRouter::RUN_AUTOMATION) {
            return [
                'mode' => 'automation',
                'aborted' => true,
                'reason' => 'Control mode berubah saat pipeline berjalan.',
                'last_agent' => $scholar->agentName,
            ];
        }

        // Langkah 2: penulisan naskah.
        $scribe = $this->academicScribe->execute($project, $stage, $context);
        $this->telemetry->record($project, $stage, $scribe, 'Menyusun naskah tahap '.$stage->stage_name->label());

        // Langkah 3: audit kepatuhan.
        $audit = $this->ruleKeeper->execute($project, $stage, $context);
        $this->telemetry->record($project, $stage, $audit, 'Audit kepatuhan pedoman');

        $stage->forceFill([
            'status' => StageStatus::COMPLETED,
            'compiled_content' => $stage->compile(),
            'compliance_score' => $audit->complianceScore,
            'completed_at' => now(),
        ])->save();

        // Langkah 4: rekomendasi venue untuk tahap Final.
        $venueResult = null;

        if ($stage->stage_name === ProjectStage::FINAL) {
            $venueResult = $this->venueMatcher->execute($project, $stage, $context);
            $this->telemetry->record($project, $stage, $venueResult, 'Mencocokkan venue publikasi');
        }

        $this->kpi->recordProjectSnapshot($project);

        return [
            'mode' => 'automation',
            'stage' => $stage->stage_name->value,
            'compliance_score' => $audit->complianceScore,
            'citations' => $scribe->citationsUsed,
            'hallucinations' => $scribe->hallucinationsRejected,
            'recommendations' => $venueResult?->outputPayload['recommendations'] ?? [],
        ];
    }

    /**
     * Mode HITL: satu langkah mikro, lalu kunci di Approval Gate.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function runHitlStep(XishieProject $project, ProjectStageRecord $stage, array $context): array
    {
        $stage->forceFill(['status' => StageStatus::IN_PROGRESS])->save();

        $scholar = $this->scholarScout->execute($project, $stage, $context);
        $this->telemetry->record($project, $stage, $scholar, 'Menyiapkan referensi untuk sub-bab');

        $context['single_section'] = true;
        $context['preserve_existing'] = true;

        $scribe = $this->academicScribe->execute($project, $stage, $context);
        $this->telemetry->record($project, $stage, $scribe, 'Draf sub-bab menunggu persetujuan');

        $this->approvalGate->lock($project, $stage, $scribe, $scribe->agentName);

        $this->kpi->recordProjectSnapshot($project);

        return [
            'mode' => 'hitl',
            'stage' => $stage->stage_name->value,
            'waiting_approval' => true,
            'pending_section' => $scribe->outputPayload['pending_section'] ?? null,
        ];
    }

    /**
     * Mode Manual: tidak menulis otomatis; hanya audit & telemetri pasif.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function runManualTelemetry(XishieProject $project, ProjectStageRecord $stage, array $context): array
    {
        $audit = $this->ruleKeeper->execute($project, $stage, $context);
        $this->telemetry->record($project, $stage, $audit, 'Audit pasif mode manual');

        $stage->forceFill([
            'compiled_content' => $stage->compile(),
            'compliance_score' => $audit->complianceScore,
        ])->save();

        $this->kpi->recordProjectSnapshot($project);

        return [
            'mode' => 'manual',
            'stage' => $stage->stage_name->value,
            'compliance_score' => $audit->complianceScore,
            'violations' => $audit->outputPayload['violations'] ?? [],
        ];
    }

    /**
     * Approve artefak HITL dan lanjutkan.
     */
    public function approve(XishieProject $project, ProjectStage $stage): ProjectStageRecord
    {
        $record = $this->ensureStage($project, $stage);
        $approved = $this->approvalGate->approve($project, $record);

        $this->kpi->recordProjectSnapshot($project);

        return $approved;
    }

    /**
     * Minta regenerasi dengan instruksi tambahan.
     */
    public function requestRevision(XishieProject $project, ProjectStage $stage, string $instruction): ProjectStageRecord
    {
        $record = $this->ensureStage($project, $stage);

        return $this->approvalGate->requestRevision($record, $instruction);
    }

    /**
     * Simpan hasil suntingan manual pengguna.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function manualEdit(XishieProject $project, ProjectStage $stage, array $sections): ProjectStageRecord
    {
        $record = $this->ensureStage($project, $stage);

        return $this->approvalGate->manualEdit($record, $sections);
    }

    /**
     * Hasil eksekusi terakhir yang tersimpan (untuk keperluan UI/test).
     */
    public function lastResult(XishieProject $project): ?AgentExecutionResultData
    {
        $log = $project->agentLogs()->latest('id')->first();

        if ($log === null) {
            return null;
        }

        return AgentExecutionResultData::success(
            agentName: (string) $log->agent_name,
            outputPayload: (array) ($log->payload['output_payload'] ?? []),
            complianceScore: (float) ($log->payload['compliance_score'] ?? 0.0),
        );
    }
}
