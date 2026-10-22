<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Enums;

enum UxKpiMetric: string
{
    case GUIDELINE_COMPLIANCE_SCORE = 'guideline_compliance_score';
    case TIME_TO_STAGE_COMPLETION = 'time_to_stage_completion';
    case HITL_APPROVAL_RATIO = 'hitl_approval_ratio';
    case CONTROL_MODE_DISTRIBUTION = 'control_mode_distribution';
    case VERIFIED_CITATION_DENSITY = 'verified_citation_density';
    case REVISION_RESOLUTION_VELOCITY = 'revision_resolution_velocity';
    case LITERATURE_MATRIX_COVERAGE = 'literature_matrix_coverage';
    case ASSISTANT_QUERY_EFFECTIVENESS = 'assistant_query_effectiveness';
    case ONBOARDING_COMPLETION_RATE = 'onboarding_completion_rate';
    case CUSTOM_VS_DEFAULT_GUIDELINE_RATE = 'custom_vs_default_guideline_rate';
    case ACTIVE_WRITING_SESSION_DEPTH = 'active_writing_session_depth';
    case STAGE_TO_STAGE_RETENTION = 'stage_to_stage_retention';
    case AGENT_HALLUCINATION_RATE = 'agent_hallucination_rate';
    case JOURNAL_MATCH_CONFIDENCE_SCORE = 'journal_match_confidence_score';
    case MANUSCRIPT_EXPORT_CONVERSION = 'manuscript_export_conversion';
    case SKRIPSI_TO_JOURNAL_CONVERSION = 'skripsi_to_journal_conversion';
    case SYSTEM_RESPONSE_LATENCY = 'system_response_latency';
    case USER_SATISFACTION_SCORE = 'user_satisfaction_score';

    public function label(): string
    {
        return match ($this) {
            self::GUIDELINE_COMPLIANCE_SCORE => 'Guideline Compliance Score',
            self::TIME_TO_STAGE_COMPLETION => 'Time-to-Stage Completion',
            self::HITL_APPROVAL_RATIO => 'HITL Approval Ratio',
            self::CONTROL_MODE_DISTRIBUTION => 'Control Mode Distribution',
            self::VERIFIED_CITATION_DENSITY => 'Verified Citation Density',
            self::REVISION_RESOLUTION_VELOCITY => 'Revision Resolution Velocity',
            self::LITERATURE_MATRIX_COVERAGE => 'Literature Matrix Coverage',
            self::ASSISTANT_QUERY_EFFECTIVENESS => 'Assistant Query Effectiveness',
            self::ONBOARDING_COMPLETION_RATE => 'Onboarding Completion Rate',
            self::CUSTOM_VS_DEFAULT_GUIDELINE_RATE => 'Custom vs Default Guideline Rate',
            self::ACTIVE_WRITING_SESSION_DEPTH => 'Active Writing Session Depth',
            self::STAGE_TO_STAGE_RETENTION => 'Stage-to-Stage Retention',
            self::AGENT_HALLUCINATION_RATE => 'Agent Error / Hallucination Rate',
            self::JOURNAL_MATCH_CONFIDENCE_SCORE => 'Journal Match Confidence Score',
            self::MANUSCRIPT_EXPORT_CONVERSION => 'Manuscript Export Conversion',
            self::SKRIPSI_TO_JOURNAL_CONVERSION => 'Skripsi-to-Journal Conversion',
            self::SYSTEM_RESPONSE_LATENCY => 'System Response Latency (TTFT)',
            self::USER_SATISFACTION_SCORE => 'User Satisfaction Score',
        };
    }

    public function category(): UxKpiCategory
    {
        return match ($this) {
            self::GUIDELINE_COMPLIANCE_SCORE,
            self::ASSISTANT_QUERY_EFFECTIVENESS,
            self::JOURNAL_MATCH_CONFIDENCE_SCORE => UxKpiCategory::TASK_SUCCESS,
            self::TIME_TO_STAGE_COMPLETION,
            self::REVISION_RESOLUTION_VELOCITY => UxKpiCategory::EFFICIENCY,
            self::HITL_APPROVAL_RATIO,
            self::USER_SATISFACTION_SCORE => UxKpiCategory::HAPPINESS,
            self::CONTROL_MODE_DISTRIBUTION,
            self::CUSTOM_VS_DEFAULT_GUIDELINE_RATE => UxKpiCategory::ADOPTION,
            self::LITERATURE_MATRIX_COVERAGE,
            self::ACTIVE_WRITING_SESSION_DEPTH => UxKpiCategory::ENGAGEMENT,
            self::VERIFIED_CITATION_DENSITY => UxKpiCategory::TASK_QUALITY,
            self::AGENT_HALLUCINATION_RATE => UxKpiCategory::ERROR_RATE,
            self::SYSTEM_RESPONSE_LATENCY => UxKpiCategory::PERFORMANCE,
            self::ONBOARDING_COMPLETION_RATE => UxKpiCategory::ACQUISITION,
            self::STAGE_TO_STAGE_RETENTION => UxKpiCategory::RETENTION,
            self::MANUSCRIPT_EXPORT_CONVERSION => UxKpiCategory::CONVERSION,
            self::SKRIPSI_TO_JOURNAL_CONVERSION => UxKpiCategory::REFERRAL,
        };
    }

    /**
     * Satuan tampilan metrik.
     */
    public function unit(): string
    {
        return match ($this) {
            self::GUIDELINE_COMPLIANCE_SCORE,
            self::JOURNAL_MATCH_CONFIDENCE_SCORE,
            self::HITL_APPROVAL_RATIO,
            self::CONTROL_MODE_DISTRIBUTION,
            self::ONBOARDING_COMPLETION_RATE,
            self::CUSTOM_VS_DEFAULT_GUIDELINE_RATE,
            self::STAGE_TO_STAGE_RETENTION,
            self::AGENT_HALLUCINATION_RATE,
            self::MANUSCRIPT_EXPORT_CONVERSION,
            self::SKRIPSI_TO_JOURNAL_CONVERSION,
            self::REVISION_RESOLUTION_VELOCITY,
            self::LITERATURE_MATRIX_COVERAGE,
            self::ASSISTANT_QUERY_EFFECTIVENESS => '%',
            self::TIME_TO_STAGE_COMPLETION => 'jam',
            self::VERIFIED_CITATION_DENSITY => 'sitasi/1k kata',
            self::ACTIVE_WRITING_SESSION_DEPTH => 'menit',
            self::SYSTEM_RESPONSE_LATENCY => 'ms',
            self::USER_SATISFACTION_SCORE => 'dari 5',
        };
    }

    /**
     * Nilai target minimum yang dianggap sehat untuk dashboard.
     */
    public function target(): float
    {
        return match ($this) {
            self::GUIDELINE_COMPLIANCE_SCORE => 85.0,
            self::JOURNAL_MATCH_CONFIDENCE_SCORE => 75.0,
            self::HITL_APPROVAL_RATIO => 70.0,
            self::ONBOARDING_COMPLETION_RATE => 80.0,
            self::STAGE_TO_STAGE_RETENTION => 60.0,
            self::AGENT_HALLUCINATION_RATE => 5.0,
            self::VERIFIED_CITATION_DENSITY => 8.0,
            self::REVISION_RESOLUTION_VELOCITY => 90.0,
            self::USER_SATISFACTION_SCORE => 4.0,
            default => 50.0,
        };
    }

    /**
     * Sejumlah metrik bersifat "semakin rendah semakin baik".
     */
    public function higherIsBetter(): bool
    {
        return match ($this) {
            self::AGENT_HALLUCINATION_RATE,
            self::SYSTEM_RESPONSE_LATENCY,
            self::TIME_TO_STAGE_COMPLETION => false,
            default => true,
        };
    }
}
