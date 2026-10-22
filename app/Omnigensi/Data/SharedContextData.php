<?php

declare(strict_types=1);

namespace App\Omnigensi\Data;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;

/**
 * Snapshot konteks terpadu proyek untuk disuntikkan ke seluruh agen.
 */
final readonly class SharedContextData
{
    /**
     * @param  array<int, array{citation_key: string, title: string, year: int|null, score: float}>  $citations
     * @param  array<int, array{id: int, reviewer_role: string, raw_feedback: string, status: string}>  $openRevisions
     */
    public function __construct(
        public string $projectTitle,
        public string $fieldOfStudy,
        public string $documentType,
        public string $controlMode,
        public string $stage,
        public GuidelineDefinition $guideline,
        public int $referenceCount,
        public int $verifiedReferenceCount,
        public array $citations,
        public array $openRevisions,
        public string $pendingFeedback,
    ) {}

    /**
     * @return array<int, string>
     */
    public function citationKeys(): array
    {
        return array_map(
            static fn (array $citation): string => $citation['citation_key'],
            $this->citations,
        );
    }

    public function hasOpenRevisions(): bool
    {
        return $this->openRevisions !== [];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'project_title' => $this->projectTitle,
            'field_of_study' => $this->fieldOfStudy,
            'document_type' => $this->documentType,
            'control_mode' => $this->controlMode,
            'stage' => $this->stage,
            'guideline' => $this->guideline->toArray(),
            'reference_count' => $this->referenceCount,
            'verified_reference_count' => $this->verifiedReferenceCount,
            'citations' => $this->citations,
            'open_revisions' => $this->openRevisions,
        ];
    }
}
