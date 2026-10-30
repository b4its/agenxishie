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

    /**
     * Format sitasi dalam teks (in-text citation).
     *
     * @param  ProjectReference|array<string, mixed>  $reference
     */
    public function inText(ProjectReference|array $reference, CitationStyle $style, ?int $numericIndex = null): string
    {
        $isModel = $reference instanceof ProjectReference;
        $rawNum = $numericIndex ?? ($isModel ? ($reference->id ?? $reference->getAttribute('id')) : ($reference['id'] ?? null));
        $num = (! empty($rawNum) && is_numeric($rawNum)) ? (int) $rawNum : 1;
        $authors = (array) ($isModel ? ($reference->authors ?? []) : ($reference['authors'] ?? []));
        $year = (string) (($isModel ? $reference->year : ($reference['year'] ?? null)) ?: 'n.d.');
        $rawKey = (string) (($isModel ? $reference->citation_key : ($reference['citation_key'] ?? null)) ?? '');

        $cleanAuthors = [];
        foreach ($authors as $author) {
            $raw = is_array($author) ? ($author['name'] ?? $author['full_name'] ?? '') : (string) $author;
            $raw = trim($raw);
            if ($raw === '') {
                continue;
            }
            if (str_contains($raw, ',')) {
                $cleanAuthors[] = trim(explode(',', $raw)[0]);
            } else {
                $parts = preg_split('/\s+/', $raw);
                $cleanAuthors[] = $parts ? (string) end($parts) : $raw;
            }
        }

        if ($cleanAuthors === [] && $rawKey !== '') {
            $cleanKey = trim($rawKey, '[]()');
            if (preg_match('/^([A-Z][a-zA-Z\-]+)/', $cleanKey, $m)) {
                $cleanAuthors[] = $m[1];
            }
        }

        $authorCount = count($cleanAuthors);
        $primaryAuthor = $cleanAuthors[0] ?? ($rawKey !== '' ? trim($rawKey, '[]()') : 'Anonim');

        return match ($style) {
            CitationStyle::IEEE => "[{$num}]",
            CitationStyle::VANCOUVER => "({$num})",
            CitationStyle::APA7 => match (true) {
                $authorCount === 0 => "({$primaryAuthor}, {$year})",
                $authorCount === 1 => "({$cleanAuthors[0]}, {$year})",
                $authorCount === 2 => "({$cleanAuthors[0]} & {$cleanAuthors[1]}, {$year})",
                default => "({$cleanAuthors[0]} et al., {$year})",
            },
            CitationStyle::HARVARD => match (true) {
                $authorCount === 0 => "({$primaryAuthor}, {$year})",
                $authorCount === 1 => "({$cleanAuthors[0]}, {$year})",
                $authorCount === 2 => "({$cleanAuthors[0]} and {$cleanAuthors[1]}, {$year})",
                default => "({$cleanAuthors[0]} et al., {$year})",
            },
            CitationStyle::CHICAGO => match (true) {
                $authorCount === 0 => "({$primaryAuthor} {$year})",
                $authorCount === 1 => "({$cleanAuthors[0]} {$year})",
                $authorCount === 2 => "({$cleanAuthors[0]} and {$cleanAuthors[1]} {$year})",
                default => "({$cleanAuthors[0]} et al. {$year})",
            },
        };
    }

    private function authors(ProjectReference $reference, CitationStyle $style): string
    {
        $authors = array_map(static function ($author): string {
            if (is_array($author)) {
                return (string) ($author['name'] ?? $author['full_name'] ?? '');
            }

            return (string) $author;
        }, (array) ($reference->authors ?? []));

        $authors = array_values(array_filter($authors, static fn ($a) => trim($a) !== ''));

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
