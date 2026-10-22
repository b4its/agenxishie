<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Revision\Enums\RevisionStatus;
use Database\Factories\ProjectRevisionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectRevision extends Model
{
    /** @use HasFactory<ProjectRevisionFactory> */
    use HasFactory;

    protected $table = 'project_revisions';

    protected $fillable = [
        'project_id',
        'stage_id',
        'reviewer_role',
        'raw_feedback',
        'action_items',
        'diff_payload',
        'response_matrix_doc',
        'status',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action_items' => 'array',
            'diff_payload' => 'array',
            'status' => RevisionStatus::class,
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

    /**
     * Rasio butir revisi yang sudah diselesaikan (0-100).
     */
    public function resolutionPercentage(): float
    {
        $items = (array) ($this->action_items ?? []);
        $total = count($items);

        if ($total === 0) {
            return 0.0;
        }

        $resolved = 0;

        foreach ($items as $item) {
            $status = is_array($item) ? ($item['status'] ?? 'pending') : 'pending';

            if ($status === RevisionStatus::RESOLVED->value) {
                $resolved++;
            }
        }

        return round(($resolved / $total) * 100, 2);
    }
}
