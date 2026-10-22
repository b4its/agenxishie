<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use Database\Factories\ProjectStageRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Record state per tahap (draft / proposal / final) untuk sebuah proyek.
 *
 * Dinamai "ProjectStageRecord" agar tidak bertabrakan dengan enum ProjectStage.
 */
class ProjectStageRecord extends Model
{
    /** @use HasFactory<ProjectStageRecordFactory> */
    use HasFactory;

    protected $table = 'project_stages';

    protected $fillable = [
        'project_id',
        'stage_name',
        'status',
        'outline_schema',
        'sections_content',
        'compiled_content',
        'hitl_pending_payload',
        'compliance_score',
        'word_count',
        'started_at',
        'completed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stage_name' => ProjectStage::class,
            'status' => StageStatus::class,
            'outline_schema' => 'array',
            'sections_content' => 'array',
            'hitl_pending_payload' => 'array',
            'compliance_score' => 'decimal:2',
            'word_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(XishieProject::class, 'project_id');
    }

    /**
     * Gabungkan seluruh isi section menjadi satu naskah.
     */
    public function compile(): string
    {
        $sections = $this->sections_content ?? [];
        $parts = [];

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = (string) ($section['heading'] ?? '');
            $body = (string) ($section['content'] ?? '');

            if ($heading !== '') {
                $parts[] = "## {$heading}";
            }

            if ($body !== '') {
                $parts[] = $body;
            }
        }

        return trim(implode("\n\n", $parts));
    }

    public function recalculateWordCount(): int
    {
        $this->word_count = str_word_count(strip_tags($this->compile()));
        $this->save();

        return $this->word_count;
    }
}
