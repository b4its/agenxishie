<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PlatformRecommendationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformRecommendation extends Model
{
    /** @use HasFactory<PlatformRecommendationFactory> */
    use HasFactory;

    protected $table = 'platform_recommendations';

    protected $fillable = [
        'project_id',
        'academic_venue_id',
        'journal_name',
        'publisher',
        'index_tier',
        'match_score',
        'scope_reasoning',
        'apc_estimate',
        'submission_url',
        'is_selected',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'match_score' => 'decimal:2',
            'apc_estimate' => 'decimal:2',
            'is_selected' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(XishieProject::class, 'project_id');
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(AcademicVenue::class, 'academic_venue_id');
    }
}
