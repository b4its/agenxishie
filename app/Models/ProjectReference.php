<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ProjectReferenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectReference extends Model
{
    /** @use HasFactory<ProjectReferenceFactory> */
    use HasFactory;

    protected $table = 'project_references';

    protected $fillable = [
        'project_id',
        'citation_key',
        'doi',
        'title',
        'abstract',
        'authors',
        'year',
        'journal_name',
        'sinta_or_scopus_tier',
        'matrix_extraction',
        'embedding',
        'source',
        'pdf_path',
        'is_verified',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'authors' => 'array',
            'matrix_extraction' => 'array',
            'embedding' => 'array',
            'year' => 'integer',
            'is_verified' => 'boolean',
        ];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(XishieProject::class, 'project_id');
    }

    /**
     * Teks gabungan untuk keperluan embedding / FULLTEXT.
     */
    public function searchableText(): string
    {
        return trim(implode(' ', array_filter([
            (string) $this->title,
            (string) $this->abstract,
            implode(', ', (array) ($this->authors ?? [])),
        ])));
    }

    /**
     * @return array<int, float>
     */
    public function embeddingVector(): array
    {
        return array_map(static fn ($value): float => (float) $value, (array) ($this->embedding ?? []));
    }
}
