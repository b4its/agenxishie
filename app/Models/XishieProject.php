<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use Database\Factories\XishieProjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class XishieProject extends Model
{
    /** @use HasFactory<XishieProjectFactory> */
    use HasFactory;

    protected $table = 'xishie_projects';

    protected $fillable = [
        'user_id',
        'title',
        'field_of_study',
        'document_type',
        'target_scope',
        'target_tier',
        'control_mode',
        'current_stage',
        'guideline_type',
        'citation_style',
        'guideline_rules',
        'custom_guideline_path',
        'onboarded_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'field_of_study' => FieldOfStudy::class,
            'document_type' => DocumentType::class,
            'target_scope' => PublicationTarget::class,
            'control_mode' => ControlMode::class,
            'current_stage' => ProjectStage::class,
            'citation_style' => CitationStyle::class,
            'guideline_rules' => 'array',
            'onboarded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(ProjectStageRecord::class, 'project_id');
    }

    public function references(): HasMany
    {
        return $this->hasMany(ProjectReference::class, 'project_id');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(ProjectRevision::class, 'project_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(PlatformRecommendation::class, 'project_id')->orderByDesc('match_score');
    }

    public function agentLogs(): HasMany
    {
        return $this->hasMany(OmnigensiAgentLog::class, 'project_id');
    }

    public function kpiTelemetries(): HasMany
    {
        return $this->hasMany(UxKpiTelemetry::class, 'project_id');
    }

    public function stageRecord(ProjectStage $stage): ?ProjectStageRecord
    {
        return $this->stages->firstWhere('stage_name', $stage);
    }

    public function complianceScore(): float
    {
        return (float) ($this->stageRecord($this->current_stage)?->compliance_score ?? 0.0);
    }

    public function wordCount(): int
    {
        return (int) ($this->stageRecord($this->current_stage)?->word_count ?? 0);
    }
}
