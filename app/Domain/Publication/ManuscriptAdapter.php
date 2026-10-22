<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\AcademicVenue;
use App\Models\PlatformRecommendation;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;

/**
 * ManuscriptAdapter: menerapkan template aturan sebuah venue (jurnal target)
 * ke naskah proyek — tombol "Adapt Formatting to This Journal" (PRD 3.6).
 *
 * Hasil: ringkasan naskah (judul, abstrak, kata kunci, daftar pustaka sesuai
 * gaya sitasi venue) tersimpan di komentar/template_rules dan dipakai untuk
 * membangun naskah siap kirim.
 */
final class ManuscriptAdapter
{
    public function __construct(
        private readonly CitationFormatter $citationFormatter,
    ) {}

    /**
     * Bangun naskah terformat untuk sebuah rekomendasi venue.
     *
     * @return array{
     *     journal_name: string,
     *     index_tier: string|null,
     *     citation_style: string,
     *     max_words: int|null,
     *     manuscript: string,
     *     word_count: int,
     *     within_limit: bool
     * }
     */
    public function adapt(XishieProject $project, PlatformRecommendation $recommendation): array
    {
        $venue = $recommendation->academic_venue_id !== null
            ? AcademicVenue::find($recommendation->academic_venue_id)
            : null;

        $rules = (array) ($venue?->template_rules ?? []);
        $citationStyle = CitationStyle::tryFrom((string) ($rules['citation_style'] ?? ''))
            ?? ($project->citation_style instanceof CitationStyle ? $project->citation_style : CitationStyle::APA7);
        $maxWords = isset($rules['max_words']) ? (int) $rules['max_words'] : null;

        $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first()
            ?? $project->stages()->where('stage_name', $project->current_stage->value)->first();

        $body = $final instanceof ProjectStageRecord ? $final->compile() : '';
        $abstract = $this->abstractOf($final);
        $keywords = $this->keywords($project, $abstract);

        $bibliography = $this->citationFormatter->bibliography(
            $project->references()->orderBy('citation_key')->get(),
            $citationStyle,
        );

        $manuscript = $this->render(
            title: (string) $project->title,
            journal: (string) $recommendation->journal_name,
            abstract: $abstract,
            keywords: $keywords,
            body: $body,
            bibliography: $bibliography,
            citationStyle: $citationStyle,
        );

        $wordCount = str_word_count(strip_tags($manuscript));

        return [
            'journal_name' => (string) $recommendation->journal_name,
            'index_tier' => $recommendation->index_tier,
            'citation_style' => $citationStyle->value,
            'max_words' => $maxWords,
            'manuscript' => $manuscript,
            'word_count' => $wordCount,
            'within_limit' => $maxWords === null || $wordCount <= $maxWords,
        ];
    }

    private function abstractOf(?ProjectStageRecord $stage): string
    {
        if ($stage === null) {
            return '';
        }

        foreach ((array) ($stage->sections_content ?? []) as $section) {
            if (is_array($section) && str_contains((string) ($section['key'] ?? ''), 'abstract')) {
                return trim((string) ($section['content'] ?? ''));
            }
        }

        return '';
    }

    /**
     * @return array<int, string>
     */
    private function keywords(XishieProject $project, string $abstract): array
    {
        $candidates = array_merge(
            (array) $project->field_of_study->literatureSources(),
            [$project->field_of_study->label(), $project->document_type->label()],
        );

        preg_match_all('/\b\p{L}{5,}\b/u', $abstract, $matches);

        $words = array_slice(array_values(array_unique(array_map(
            static fn (string $word): string => mb_strtolower($word),
            $matches[0] ?? [],
        ))), 0, 5);

        return array_values(array_unique(array_merge($words, array_slice($candidates, 0, 3))));
    }

    /**
     * @param  array<int, string>  $keywords
     * @param  array<int, string>  $bibliography
     */
    private function render(
        string $title,
        string $journal,
        string $abstract,
        array $keywords,
        string $body,
        array $bibliography,
        CitationStyle $citationStyle,
    ): string {
        $lines = [
            '# '.$title,
            '',
            '*Naskah diformat untuk: '.$journal.'*',
            '',
            '## Abstract',
            $abstract !== '' ? $abstract : '(Abstrak belum tersedia.)',
            '',
            '**Keywords:** '.implode(', ', $keywords),
            '',
            '## Manuscript Body',
            $body !== '' ? $body : '(Naskah belum tersedia.)',
            '',
            '## References ('.$citationStyle->label().')',
        ];

        foreach ($bibliography as $index => $entry) {
            $lines[] = ($index + 1).'. '.$entry;
        }

        return implode("\n", $lines);
    }
}
