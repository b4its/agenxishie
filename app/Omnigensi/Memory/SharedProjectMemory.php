<?php

declare(strict_types=1);

namespace App\Omnigensi\Memory;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\GuidelineEngine\GuidelineResolverService;
use App\Models\ProjectReference;
use App\Models\XishieProject;
use App\Omnigensi\Data\SharedContextData;

/**
 * Konteks bersama proyek yang disuntikkan ke seluruh agen.
 *
 * Menggabungkan pedoman aktif, referensi paling relevan (MySQL FULLTEXT +
 * Cosine Similarity), catatan revisi terbuka, dan ringkasan naskah terkini.
 */
final class SharedProjectMemory
{
    public function __construct(
        private readonly HybridVectorMatcherService $matcher,
        private readonly GuidelineResolverService $guidelineResolver,
    ) {}

    public function build(XishieProject $project, string $focusQuery = '', int $referenceLimit = 8): SharedContextData
    {
        $guideline = $this->guidelineResolver->resolve($project);
        $references = $this->relevantReferences($project, $focusQuery, $referenceLimit);
        $revisions = $project->revisions()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest()
            ->limit(10)
            ->get();

        return new SharedContextData(
            projectTitle: (string) $project->title,
            fieldOfStudy: $project->field_of_study->value,
            documentType: $project->document_type->value,
            controlMode: $project->control_mode->value,
            stage: $project->current_stage->value,
            guideline: $guideline,
            referenceCount: $project->references()->count(),
            verifiedReferenceCount: $project->references()->where('is_verified', true)->count(),
            citations: $references,
            openRevisions: $revisions->map(static fn ($revision): array => [
                'id' => $revision->id,
                'reviewer_role' => $revision->reviewer_role,
                'raw_feedback' => $revision->raw_feedback,
                'status' => $revision->status->value,
            ])->all(),
            pendingFeedback: $revisions->pluck('raw_feedback')->implode("\n"),
        );
    }

    /**
     * Referensi paling relevan untuk sebuah kueri fokus (mis. judul sub-bab).
     *
     * @return array<int, array{citation_key: string, title: string, year: int|null, score: float}>
     */
    public function relevantReferences(XishieProject $project, string $focusQuery = '', int $limit = 8): array
    {
        $query = $focusQuery !== '' ? $focusQuery : (string) $project->title;
        $vector = $this->matcher->embed($query);

        $matches = $this->matcher->search(
            modelClass: ProjectReference::class,
            queryText: $query,
            queryVector: $vector,
            fullTextColumns: ['title', 'abstract'],
            embeddingColumn: 'embedding',
            limit: $limit,
            filters: ['project_id' => $project->id],
        );

        if ($matches === []) {
            $fallback = $project->references()
                ->when($focusQuery !== '', function ($q) use ($focusQuery): void {
                    $q->where(function ($sub) use ($focusQuery): void {
                        $sub->where('title', 'like', "%{$focusQuery}%")
                            ->orWhere('citation_key', 'like', "%{$focusQuery}%");
                    });
                })
                ->limit($limit)
                ->get();

            if ($fallback->isEmpty() && $focusQuery !== '') {
                $fallback = $project->references()->limit($limit)->get();
            }

            return $fallback->map(static fn (ProjectReference $ref): array => [
                'citation_key' => (string) $ref->citation_key,
                'title' => (string) $ref->title,
                'authors' => $ref->authors,
                'year' => $ref->year,
                'score' => 1.0,
            ])->all();
        }

        return array_map(static fn (array $match): array => [
            'citation_key' => (string) $match['model']->citation_key,
            'title' => (string) $match['model']->title,
            'authors' => $match['model']->authors,
            'year' => $match['model']->year,
            'score' => round($match['score'], 4),
        ], $matches);
    }

    /**
     * Kunci sitasi valid yang boleh dipakai agen penulis.
     *
     * @return array<int, string>
     */
    public function allowedCitationKeys(XishieProject $project): array
    {
        return $project->references()->pluck('citation_key')->all();
    }

    public function guideline(XishieProject $project): GuidelineDefinition
    {
        return $this->guidelineResolver->resolve($project);
    }
}
