<?php

declare(strict_types=1);

namespace App\Domain\Research;

use App\Models\ProjectReference;
use App\Models\XishieProject;

/**
 * LiteratureMatrixBuilder: merangkum korpus referensi menjadi tabel
 * perbandingan SOTA (Penulis, Tahun, Metode, Hasil, Gap).
 */
final class LiteratureMatrixBuilder
{
    /**
     * @return array<int, array{
     *     citation_key: string,
     *     authors: string,
     *     year: int|null,
     *     method: string|null,
     *     result: string|null,
     *     gap: string|null
     * }>
     */
    public function build(XishieProject $project): array
    {
        return $project->references()
            ->orderByDesc('year')
            ->get()
            ->map(static function (ProjectReference $reference): array {
                $matrix = (array) ($reference->matrix_extraction ?? []);

                return [
                    'citation_key' => (string) $reference->citation_key,
                    'authors' => implode(', ', (array) ($reference->authors ?? [])),
                    'year' => $reference->year,
                    'method' => $matrix['method'] ?? null,
                    'result' => $matrix['result'] ?? null,
                    'gap' => $matrix['gap'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * Render matriks sebagai tabel Markdown.
     */
    public function toMarkdown(XishieProject $project): string
    {
        $rows = $this->build($project);

        $lines = [
            '| No | Penulis | Tahun | Metode | Hasil | Gap/Keterbatasan |',
            '| --- | --- | --- | --- | --- | --- |',
        ];

        foreach ($rows as $index => $row) {
            $lines[] = sprintf(
                '| %d | %s | %s | %s | %s | %s |',
                $index + 1,
                $this->escape((string) $row['authors']),
                (string) ($row['year'] ?? '-'),
                $this->escape((string) ($row['method'] ?? '-')),
                $this->escape((string) ($row['result'] ?? '-')),
                $this->escape((string) ($row['gap'] ?? '-')),
            );
        }

        return implode("\n", $lines);
    }

    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}
