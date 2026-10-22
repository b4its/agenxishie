<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AcademicVenueFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AcademicVenue extends Model
{
    /** @use HasFactory<AcademicVenueFactory> */
    use HasFactory;

    protected $table = 'academic_venues';

    protected $fillable = [
        'journal_name',
        'publisher',
        'scope_type',
        'index_tier',
        'field_of_study',
        'aims_and_scope',
        'apc_estimate',
        'submission_url',
        'review_time_days',
        'template_rules',
        'aims_scope_embedding',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'template_rules' => 'array',
            'aims_scope_embedding' => 'array',
            'apc_estimate' => 'decimal:2',
            'review_time_days' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(PlatformRecommendation::class, 'academic_venue_id');
    }

    /**
     * @return array<int, float>
     */
    public function embeddingVector(): array
    {
        return array_map(
            static fn ($value): float => (float) $value,
            (array) ($this->aims_scope_embedding ?? []),
        );
    }

    public function searchableText(): string
    {
        return trim(((string) $this->journal_name).' '.((string) $this->aims_and_scope));
    }
}
