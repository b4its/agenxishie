<?php

declare(strict_types=1);

namespace App\Models;

use App\Omnigensi\Enums\AgentStatus;
use Database\Factories\OmnigensiAgentLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OmnigensiAgentLog extends Model
{
    /** @use HasFactory<OmnigensiAgentLogFactory> */
    use HasFactory;

    protected $table = 'omnigensi_agent_logs';

    protected $fillable = [
        'project_id',
        'stage_id',
        'agent_name',
        'action',
        'control_mode',
        'ttft_ms',
        'execution_time_ms',
        'token_usage',
        'hallucination_rejected_count',
        'status',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => AgentStatus::class,
            'ttft_ms' => 'integer',
            'execution_time_ms' => 'integer',
            'token_usage' => 'integer',
            'hallucination_rejected_count' => 'integer',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(XishieProject::class, 'project_id');
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(ProjectStageRecord::class, 'stage_id');
    }
}
