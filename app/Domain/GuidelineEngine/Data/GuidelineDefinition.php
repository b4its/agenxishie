<?php

declare(strict_types=1);

namespace App\Domain\GuidelineEngine\Data;

/**
 * Struktur aturan pedoman penulisan yang tersimpan pada kolom
 * xishie_projects.guideline_rules (MySQL JSON).
 *
 * @phpstan-type SectionRule array{key: string, heading: string, min_words: int, max_words: int, required: bool}
 * @phpstan-type GuidelineShape array{
 *     source: string,
 *     citation_style: string,
 *     total_word_range: array{min: int, max: int},
 *     abstract_required: bool,
 *     abstract_word_range: array{min: int, max: int},
 *     sections: array<int, SectionRule>,
 *     stages: array<string, array<int, string>>,
 *     notes: array<int, string>
 * }
 */
final readonly class GuidelineDefinition
{
    /**
     * @param  array<int, array{key: string, heading: string, min_words: int, max_words: int, required: bool}>  $sections
     * @param  array<string, array<int, string>>  $stages
     * @param  array<int, string>  $notes
     */
    public function __construct(
        public string $source,
        public string $citationStyle,
        public int $minTotalWords,
        public int $maxTotalWords,
        public bool $abstractRequired,
        public int $minAbstractWords,
        public int $maxAbstractWords,
        public array $sections,
        public array $stages,
        public array $notes = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'citation_style' => $this->citationStyle,
            'total_word_range' => ['min' => $this->minTotalWords, 'max' => $this->maxTotalWords],
            'abstract_required' => $this->abstractRequired,
            'abstract_word_range' => ['min' => $this->minAbstractWords, 'max' => $this->maxAbstractWords],
            'sections' => $this->sections,
            'stages' => $this->stages,
            'notes' => $this->notes,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $total = $data['total_word_range'] ?? [];
        $abstract = $data['abstract_word_range'] ?? [];

        return new self(
            source: (string) ($data['source'] ?? 'unknown'),
            citationStyle: (string) ($data['citation_style'] ?? 'apa7'),
            minTotalWords: (int) ($total['min'] ?? 0),
            maxTotalWords: (int) ($total['max'] ?? 0),
            abstractRequired: (bool) ($data['abstract_required'] ?? false),
            minAbstractWords: (int) ($abstract['min'] ?? 0),
            maxAbstractWords: (int) ($abstract['max'] ?? 0),
            sections: (array) ($data['sections'] ?? []),
            stages: (array) ($data['stages'] ?? []),
            notes: (array) ($data['notes'] ?? []),
        );
    }

    /**
     * Heading section untuk sebuah tahap, berurutan.
     *
     * @return array<int, string>
     */
    public function sectionHeadingsForStage(string $stage): array
    {
        $keys = $this->stages[$stage] ?? [];
        $headings = [];

        foreach ($keys as $key) {
            foreach ($this->sections as $section) {
                if ($section['key'] === $key) {
                    $headings[] = $section['heading'];

                    break;
                }
            }
        }

        return $headings;
    }

    /**
     * @return array{min: int, max: int}|null
     */
    public function wordRangeFor(string $sectionKey): ?array
    {
        foreach ($this->sections as $section) {
            if ($section['key'] === $sectionKey) {
                return ['min' => $section['min_words'], 'max' => $section['max_words']];
            }
        }

        return null;
    }

    public function headingForKey(string $sectionKey): ?string
    {
        foreach ($this->sections as $section) {
            if ($section['key'] === $sectionKey) {
                return $section['heading'];
            }
        }

        return null;
    }
}
