<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Models\ProjectRevision;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * UxKpiTelemetryService: menghitung dan menyimpan 18 metrik UX & Product
 * (HEART + AARRR) dari omnigensi_agent_logs dan tabel domain terkait.
 */
final class UxKpiTelemetryService
{
    /**
     * Hitung snapshot seluruh metrik untuk sebuah proyek.
     *
     * @return array<string, array{label: string, category: string, framework: string, value: float, unit: string, target: float, healthy: bool}>
     */
    public function computeProjectMetrics(XishieProject $project): array
    {
        $metrics = [];

        foreach (UxKpiMetric::cases() as $metric) {
            $value = $this->computeMetric($metric, $project);
            $target = $metric->target();

            $healthy = $metric->higherIsBetter() ? $value >= $target : $value <= $target;

            $metrics[$metric->value] = [
                'label' => $metric->label(),
                'category' => $metric->category()->label(),
                'framework' => $metric->category()->framework(),
                'value' => round($value, 4),
                'unit' => $metric->unit(),
                'target' => $target,
                'healthy' => $healthy,
            ];
        }

        return $metrics;
    }

    /**
     * Hitung satu metrik.
     */
    public function computeMetric(UxKpiMetric $metric, XishieProject $project): float
    {
        return match ($metric) {
            UxKpiMetric::GUIDELINE_COMPLIANCE_SCORE => $this->complianceScore($project),
            UxKpiMetric::TIME_TO_STAGE_COMPLETION => $this->timeToStageCompletion($project),
            UxKpiMetric::HITL_APPROVAL_RATIO => $this->hitlApprovalRatio($project),
            UxKpiMetric::CONTROL_MODE_DISTRIBUTION => $this->controlModeDistribution($project),
            UxKpiMetric::VERIFIED_CITATION_DENSITY => $this->verifiedCitationDensity($project),
            UxKpiMetric::REVISION_RESOLUTION_VELOCITY => $this->revisionResolutionVelocity($project),
            UxKpiMetric::LITERATURE_MATRIX_COVERAGE => $this->literatureMatrixCoverage($project),
            UxKpiMetric::ASSISTANT_QUERY_EFFECTIVENESS => $this->assistantQueryEffectiveness($project),
            UxKpiMetric::ONBOARDING_COMPLETION_RATE => $project->onboarded_at !== null ? 100.0 : 0.0,
            UxKpiMetric::CUSTOM_VS_DEFAULT_GUIDELINE_RATE => $project->guideline_type === 'custom' ? 100.0 : 0.0,
            UxKpiMetric::ACTIVE_WRITING_SESSION_DEPTH => $this->activeWritingSessionDepth($project),
            UxKpiMetric::STAGE_TO_STAGE_RETENTION => $this->stageRetention($project),
            UxKpiMetric::AGENT_HALLUCINATION_RATE => $this->hallucinationRate($project),
            UxKpiMetric::JOURNAL_MATCH_CONFIDENCE_SCORE => $this->journalMatchConfidence($project),
            UxKpiMetric::MANUSCRIPT_EXPORT_CONVERSION => $this->manuscriptExportConversion($project),
            UxKpiMetric::SKRIPSI_TO_JOURNAL_CONVERSION => $this->skripsiToJournalConversion($project),
            UxKpiMetric::SYSTEM_RESPONSE_LATENCY => $this->systemResponseLatency($project),
            UxKpiMetric::USER_SATISFACTION_SCORE => $this->satisfactionScore($project),
        };
    }

    /**
     * Simpan snapshot seluruh metrik ke ux_kpi_telemetries.
     *
     * @return Collection<int, UxKpiTelemetry>
     */
    public function recordProjectSnapshot(XishieProject $project): Collection
    {
        $metrics = $this->computeProjectMetrics($project);

        return collect($metrics)->map(function (array $data, string $key) use ($project): UxKpiTelemetry {
            $metric = UxKpiMetric::from($key);

            return UxKpiTelemetry::updateOrCreate(
                [
                    'project_id' => $project->id,
                    'metric_key' => $metric->value,
                    'recorded_at' => Carbon::now()->startOfMinute(),
                ],
                [
                    'user_id' => $project->user_id,
                    'metric_category' => $metric->category(),
                    'metric_value' => $data['value'],
                    'metadata' => [
                        'label' => $data['label'],
                        'unit' => $data['unit'],
                        'target' => $data['target'],
                        'healthy' => $data['healthy'],
                        'framework' => $data['framework'],
                    ],
                ],
            );
        })->values();
    }

    /**
     * Kumpulkan metrik untuk widget dashboard.
     *
     * @return array<string, mixed>
     */
    public function dashboardSummary(XishieProject $project): array
    {
        $metrics = $this->computeProjectMetrics($project);

        $byFramework = ['UX' => [], 'HEART' => [], 'AARRR' => []];

        foreach ($metrics as $key => $data) {
            $byFramework[$data['framework']][$key] = $data;
        }

        return [
            'all' => $metrics,
            'ux' => $byFramework['UX'],
            'heart' => $byFramework['HEART'],
            'aarrr' => $byFramework['AARRR'],
        ];
    }

    private function complianceScore(XishieProject $project): float
    {
        return (float) ($project->stages()->whereNotNull('compliance_score')->max('compliance_score') ?? 0.0);
    }

    private function timeToStageCompletion(XishieProject $project): float
    {
        $hours = 0.0;

        foreach ($project->stages()->whereNotNull('completed_at')->whereNotNull('started_at')->get() as $stage) {
            $hours += (float) $stage->started_at->diffInHours($stage->completed_at);
        }

        return round($hours, 4);
    }

    private function hitlApprovalRatio(XishieProject $project): float
    {
        $approved = $project->stages()
            ->whereNotNull('hitl_pending_payload')
            ->get()
            ->filter(static fn ($stage): bool => ($stage->hitl_pending_payload['decision'] ?? null) === 'approved')
            ->count();

        $regenerated = $project->stages()
            ->whereNotNull('hitl_pending_payload')
            ->get()
            ->filter(static fn ($stage): bool => ($stage->hitl_pending_payload['decision'] ?? null) === 'regenerated')
            ->count();

        $total = $approved + $regenerated;

        return $total === 0 ? 0.0 : round(($approved / $total) * 100, 2);
    }

    private function controlModeDistribution(XishieProject $project): float
    {
        $logs = $project->agentLogs()->get();

        if ($logs->isEmpty()) {
            return 0.0;
        }

        $counts = $logs->groupBy('control_mode')->map->count();
        $dominant = (float) ($counts->max() ?? 0);

        return round(($dominant / $logs->count()) * 100, 2);
    }

    private function verifiedCitationDensity(XishieProject $project): float
    {
        $words = (int) ($project->stages()->sum('word_count') ?? 0);
        $verified = $project->references()->where('is_verified', true)->count();

        if ($words <= 0) {
            return 0.0;
        }

        return round(($verified / $words) * 1000, 4);
    }

    private function revisionResolutionVelocity(XishieProject $project): float
    {
        $revisions = $project->revisions()->get();

        if ($revisions->isEmpty()) {
            return 0.0;
        }

        $total = 0;
        $resolved = 0;

        /** @var ProjectRevision $revision */
        foreach ($revisions as $revision) {
            foreach ((array) ($revision->action_items ?? []) as $item) {
                $total++;

                if (($item['status'] ?? null) === RevisionStatus::RESOLVED->value) {
                    $resolved++;
                }
            }
        }

        return $total === 0 ? 0.0 : round(($resolved / $total) * 100, 2);
    }

    private function literatureMatrixCoverage(XishieProject $project): float
    {
        $references = $project->references()->get();

        if ($references->isEmpty()) {
            return 0.0;
        }

        $extracted = $references->filter(static fn ($reference): bool => ! empty($reference->matrix_extraction))->count();

        return round(($extracted / $references->count()) * 100, 2);
    }

    private function assistantQueryEffectiveness(XishieProject $project): float
    {
        $logs = $project->agentLogs()->where('agent_name', 'Xishie-Assistant')->get();

        if ($logs->isEmpty()) {
            return 0.0;
        }

        $applied = $logs->filter(static fn ($log): bool => (bool) ($log->payload['metadata']['applied'] ?? false))->count();

        return round(($applied / $logs->count()) * 100, 2);
    }

    private function activeWritingSessionDepth(XishieProject $project): float
    {
        $stages = $project->stages()->get();
        $minutes = 0.0;

        foreach ($stages as $stage) {
            if ($stage->started_at !== null && $stage->completed_at !== null) {
                $minutes += (float) $stage->started_at->diffInMinutes($stage->completed_at);
            }
        }

        return round($minutes, 2);
    }

    private function stageRetention(XishieProject $project): float
    {
        $completed = $project->stages()->where('status', 'completed')->count();

        return round(($completed / count(ProjectStage::cases())) * 100, 2);
    }

    private function hallucinationRate(XishieProject $project): float
    {
        $logs = $project->agentLogs()->get();

        if ($logs->isEmpty()) {
            return 0.0;
        }

        $rejected = (int) $logs->sum('hallucination_rejected_count');
        $tokens = (int) $logs->sum('token_usage');

        $denominator = max($tokens, (int) $logs->sum('hallucination_rejected_count') + $tokens, 1);

        return round(($rejected / max($denominator, 1)) * 100, 4);
    }

    private function journalMatchConfidence(XishieProject $project): float
    {
        return (float) ($project->recommendations()->max('match_score') ?? 0.0);
    }

    private function manuscriptExportConversion(XishieProject $project): float
    {
        $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();

        if ($final === null || $final->status->value !== 'completed') {
            return 0.0;
        }

        return $project->recommendations()->exists() ? 100.0 : 50.0;
    }

    private function skripsiToJournalConversion(XishieProject $project): float
    {
        if (! $project->document_type->isThesisFamily()) {
            return 0.0;
        }

        $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();

        if ($final === null) {
            return 0.0;
        }

        $sections = (array) ($final->sections_content ?? []);
        $hasAbstract = collect($sections)->contains(static fn ($section): bool => is_array($section) && ($section['key'] ?? '') === 'abstract');

        return $hasAbstract ? 100.0 : 0.0;
    }

    private function systemResponseLatency(XishieProject $project): float
    {
        return (float) ($project->agentLogs()->whereNotNull('ttft_ms')->avg('ttft_ms') ?? 0.0);
    }

    private function satisfactionScore(XishieProject $project): float
    {
        $latest = $project->kpiTelemetries()
            ->where('metric_key', UxKpiMetric::USER_SATISFACTION_SCORE->value)
            ->latest('recorded_at')
            ->first();

        return $latest === null ? 0.0 : (float) $latest->metric_value;
    }
}
