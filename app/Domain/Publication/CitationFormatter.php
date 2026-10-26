<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Models\ProjectReference;

/**
 * CitationFormatter: merender daftar pustaka sesuai gaya sitasi
 * (APA 7th, IEEE, Vancouver, Chicago).
 */
final class CitationFormatter
{
    /**
     * @param  iterable<int, ProjectReference>  $references
     * @return array<int, string>
     */
    public function bibliography(iterable $references, CitationStyle $style): array
    {
        $entries = [];

        foreach ($references as $reference) {
            $entries[] = $this->format($reference, $style);
        }

        return $entries;
    }

    public function format(ProjectReference $reference, CitationStyle $style): string
    {
        $authors = $this->authors($reference, $style);
        $year = $reference->year ?? 'n.d.';
        $title = (string) $reference->title;
        $journal = (string) ($reference->journal_name ?? '');
        $doi = $reference->doi !== null ? ' https://doi.org/'.$reference->doi : '';

        return match ($style) {
            CitationStyle::APA7 => sprintf(
                '%s (%s). %s. %s.%s',
                $authors,
                $year,
                $title,
                $journal,
                $doi,
            ),
            CitationStyle::IEEE => sprintf(
                '[%s] %s, "%s," %s, %s.%s',
                (string) $reference->citation_key,
                $authors,
                $title,
                $journal,
                $year,
                $doi,
            ),
            CitationStyle::VANCOUVER => sprintf(
                '%s. %s. %s. %s.%s',
                $authors,
                $title,
                $journal,
                $year,
                $doi,
            ),
            CitationStyle::CHICAGO => sprintf(
                '%s. "%s." %s (%s).%s',
                $authors,
                $title,
                $journal,
                $year,
                $doi,
            ),
            CitationStyle::HARVARD => sprintf(
                '%s (%s) \'%s\', %s.%s',
                $authors,
                $year,
                $title,
                $journal,
                $doi,
            ),
        };
    }

    private function authors(ProjectReference $reference, CitationStyle $style): string
    {
        $authors = array_map(static fn ($author): string => (string) $author, (array) ($reference->authors ?? []));

        if ($authors === []) {
            return 'Anonim';
        }

        return match ($style) {
            CitationStyle::APA7, CitationStyle::HARVARD => $this->apaAuthors($authors),
            CitationStyle::IEEE => $this->ieeeAuthors($authors),
            CitationStyle::VANCOUVER => implode(', ', array_slice($authors, 0, 6)),
            CitationStyle::CHICAGO => $this->chicagoAuthors($authors),
        };
    }

    /**
     * @param  array<int, string>  $authors
     */
    private function apaAuthors(array $authors): string
    {
        if (count($authors) === 1) {
            return $authors[0];
        }

        if (count($authors) <= 20) {
            $last = array_pop($authors);

            return implode(', ', $authors).', & '.$last;
        }

        return $authors[0].' et al.';
    }

    /**
     * @param  array<int, string>  $authors
     */
    private function ieeeAuthors(array $authors): string
    {
        if (count($authors) > 6) {
            return $authors[0].' et al.';
        }

        return implode(', ', $authors);
    }

    /**
     * @param  array<int, string>  $authors
     */
    private function chicagoAuthors(array $authors): string
    {
        if (count($authors) > 3) {
            return $authors[0].' et al.';
        }

        return implode(', ', $authors);
    }
}
