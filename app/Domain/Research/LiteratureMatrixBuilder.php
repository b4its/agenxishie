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
                    'id' => $reference->id,
                    'citation_key' => (string) $reference->citation_key,
                    'authors' => implode(', ', (array) ($reference->authors ?? [])),
                    'year' => $reference->year,
                    'is_verified' => (bool) $reference->is_verified,
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

    /**
     * Render matriks sebagai string CSV.
     */
    public function toCsv(XishieProject $project): string
    {
        $rows = $this->build($project);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['No', 'Citation Key', 'Authors', 'Year', 'Verified', 'Method', 'Result', 'Gap']);

        foreach ($rows as $index => $row) {
            fputcsv($handle, [
                $index + 1,
                $row['citation_key'],
                $row['authors'],
                $row['year'] ?? '',
                $row['is_verified'] ? 'Yes' : 'No',
                $row['method'] ?? '',
                $row['result'] ?? '',
                $row['gap'] ?? '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Render matriks sebagai string JSON terstruktur.
     */
    public function toJson(XishieProject $project): string
    {
        $rows = $this->build($project);

        return (string) json_encode([
            'project_id' => $project->id,
            'project_title' => $project->title,
            'generated_at' => now()->toIso8601String(),
            'total_references' => count($rows),
            'matrix' => $rows,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}
