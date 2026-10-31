<?php

declare(strict_types=1);

namespace App\Domain\Research;

use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
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

                $authorList = array_map(static function ($author): string {
                    if (is_array($author)) {
                        return (string) ($author['name'] ?? $author['full_name'] ?? '');
                    }

                    return (string) $author;
                }, (array) ($reference->authors ?? []));
                $authorList = array_values(array_filter($authorList, static fn (string $a): bool => trim($a) !== ''));

                return [
                    'id' => $reference->id,
                    'citation_key' => (string) $reference->citation_key,
                    'authors' => implode(', ', $authorList),
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
     * Render matriks sebagai blok tabel Naskah Studio.
     *
     * @return array{type: string, level: int, text: string, table: array{header: bool, caption: string, rows: array<int, array<int, array{text: string}>>}}
     */
    public function toTableBlock(XishieProject $project): array
    {
        $rows = $this->build($project);
        $tableRows = [
            [
                ['text' => 'No'],
                ['text' => 'Penulis'],
                ['text' => 'Tahun'],
                ['text' => 'Metode'],
                ['text' => 'Hasil Temuan'],
                ['text' => 'Gap/Keterbatasan'],
            ],
        ];

        foreach ($rows as $index => $row) {
            $tableRows[] = [
                ['text' => (string) ($index + 1)],
                ['text' => (string) ($row['authors'] !== '' ? $row['authors'] : $row['citation_key'])],
                ['text' => (string) ($row['year'] ?? '-')],
                ['text' => (string) ($row['method'] ?? '-')],
                ['text' => (string) ($row['result'] ?? '-')],
                ['text' => (string) ($row['gap'] ?? '-')],
            ];
        }

        return [
            'type' => 'table',
            'level' => 0,
            'text' => '',
            'table' => [
                'header' => true,
                'caption' => 'Tabel: Matriks Sintesis Literatur (State of the Art)',
                'rows' => $tableRows,
            ],
        ];
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

    /**
     * Render matriks literatur (SOTA) sebagai dokumen Word DOCX profesional (lanskap).
     */
    public function toDocx(XishieProject $project): string
    {
        $rows = $this->build($project);

        $blocks = [
            [
                'type' => 'heading',
                'level' => 1,
                'text' => 'Tabel Matriks Literatur (State-of-the-Art)',
            ],
            [
                'type' => 'paragraph',
                'text' => "Proyek Riset: {$project->title} | Bidang: {$project->field_of_study->label()} | Total Referensi: ".count($rows),
            ],
        ];

        $tableRows = [
            [
                ['text' => 'No'],
                ['text' => 'Kunci Sitasi'],
                ['text' => 'Penulis'],
                ['text' => 'Tahun'],
                ['text' => 'Metode'],
                ['text' => 'Hasil Temuan'],
                ['text' => 'Gap / Keterbatasan'],
            ],
        ];

        foreach ($rows as $index => $row) {
            $tableRows[] = [
                ['text' => (string) ($index + 1)],
                ['text' => (string) $row['citation_key']],
                ['text' => (string) $row['authors']],
                ['text' => (string) ($row['year'] ?? '-')],
                ['text' => (string) ($row['method'] ?? '-')],
                ['text' => (string) ($row['result'] ?? '-')],
                ['text' => (string) ($row['gap'] ?? '-')],
            ];
        }

        $blocks[] = [
            'type' => 'table',
            'table' => [
                'rows' => $tableRows,
                'header' => true,
            ],
        ];

        return app(NaskahDocxExporter::class)->build($blocks, 'Matriks Literatur - '.$project->title, [
            'orientation' => 'landscape',
        ]);
    }

    /**
     * Render matriks sebagai dokumen HTML mandiri yang bersih.
     */
    public function toHtml(XishieProject $project): string
    {
        $rows = $this->build($project);
        $title = htmlspecialchars($project->title, ENT_QUOTES, 'UTF-8');
        $field = htmlspecialchars($project->field_of_study->label(), ENT_QUOTES, 'UTF-8');

        $html = "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "<title>Matriks Literatur - {$title}</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding: 2rem; color: #1e293b; line-height: 1.5; }\n";
        $html .= "h1 { font-size: 1.5rem; color: #0f172a; margin-bottom: 0.5rem; }\n";
        $html .= "p.meta { font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; }\n";
        $html .= "table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.875rem; }\n";
        $html .= "th, td { border: 1px solid #cbd5e1; padding: 0.6rem 0.8rem; text-align: left; vertical-align: top; }\n";
        $html .= "th { background-color: #f1f5f9; font-weight: 600; color: #334155; }\n";
        $html .= "tr:nth-child(even) { background-color: #f8fafc; }\n";
        $html .= ".badge { display: inline-block; padding: 0.15rem 0.4rem; border-radius: 4px; font-size: 0.75rem; font-weight: 600; background: #e0e7ff; color: #3730a3; }\n";
        $html .= "</style>\n</head>\n<body>\n";
        $html .= "<h1>Tabel Matriks Literatur (State-of-the-Art)</h1>\n";
        $html .= "<p class=\"meta\">Proyek: <strong>{$title}</strong> | Bidang: <strong>{$field}</strong> | Total Referensi: <strong>".count($rows)."</strong></p>\n";
        $html .= "<table>\n<thead>\n<tr>\n";
        $html .= "<th>No</th><th>Kunci Sitasi</th><th>Penulis</th><th>Tahun</th><th>Metode</th><th>Hasil Temuan</th><th>Gap / Keterbatasan</th>\n";
        $html .= "</tr>\n</thead>\n<tbody>\n";

        foreach ($rows as $index => $row) {
            $num = $index + 1;
            $key = htmlspecialchars((string) $row['citation_key'], ENT_QUOTES, 'UTF-8');
            $authors = htmlspecialchars((string) $row['authors'], ENT_QUOTES, 'UTF-8');
            $year = htmlspecialchars((string) ($row['year'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $method = htmlspecialchars((string) ($row['method'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $result = htmlspecialchars((string) ($row['result'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $gap = htmlspecialchars((string) ($row['gap'] ?? '-'), ENT_QUOTES, 'UTF-8');

            $html .= "<tr>\n";
            $html .= "<td>{$num}</td><td><span class=\"badge\">{$key}</span></td><td>{$authors}</td><td>{$year}</td><td>{$method}</td><td>{$result}</td><td>{$gap}</td>\n";
            $html .= "</tr>\n";
        }

        $html .= "</tbody>\n</table>\n</body>\n</html>";

        return $html;
    }

    /**
     * Render matriks sebagai berkas Rich Text Format (.rtf).
     */
    public function toRtf(XishieProject $project): string
    {
        $rows = $this->build($project);
        $title = $project->title;
        $field = $project->field_of_study->label();

        $rtfEsc = static function (string $text): string {
            $out = '';
            $len = mb_strlen($text);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($text, $i, 1);
                $ord = mb_ord($char);
                if ($ord < 128) {
                    if ($char === '\\' || $char === '{' || $char === '}') {
                        $out .= '\\'.$char;
                    } elseif ($char === "\n") {
                        $out .= '\\par ';
                    } else {
                        $out .= $char;
                    }
                } else {
                    $out .= '\\u'.$ord.'?';
                }
            }

            return $out;
        };

        $out = "{\\rtf1\\ansi\\deff0\n{\\fonttbl{\\f0\\froman\\fcharset0 Times New Roman;}{\\f1\\fswiss\\fcharset0 Arial;}}\n";
        $out .= "{\\colortbl ;\\red30\\green64\\blue175;\\red51\\green65\\blue85;}\n";

        $out .= '\\pard\\qc\\b\\fs32\\cf1 Tabel Matriks Literatur (State-of-the-Art)\\b0\\par\\par\n';
        $out .= '\\pard\\ql\\fs22\\b Proyek: \\b0 '.$rtfEsc($title)."\\par\n";
        $out .= '\\pard\\ql\\fs22\\b Bidang Ilmu: \\b0 '.$rtfEsc($field)."\\par\n";
        $out .= '\\pard\\ql\\fs22\\b Total Referensi: \\b0 '.count($rows)." rujukan\\par\\par\n";

        foreach ($rows as $index => $row) {
            $num = $index + 1;
            $key = (string) $row['citation_key'];
            $authors = (string) $row['authors'];
            $year = (string) ($row['year'] ?? '-');
            $method = (string) ($row['method'] ?? '-');
            $result = (string) ($row['result'] ?? '-');
            $gap = (string) ($row['gap'] ?? '-');

            $out .= "\\pard\\ql\\b [Entry #{$num}] [{$key}] \\b0 {$authors} ({$year})\\par\n";
            $out .= '\\pard\\ql\\li360\\b Metode: \\b0 '.$rtfEsc($method)."\\par\n";
            $out .= '\\pard\\ql\\li360\\b Temuan: \\b0 '.$rtfEsc($result)."\\par\n";
            $out .= '\\pard\\ql\\li360\\b Gap / Limitasi: \\b0 '.$rtfEsc($gap)."\\par\\par\n";
        }

        $out .= "}\n";

        return $out;
    }

    /**
     * Hitung metrik sintesis literatur dari korpus referensi proyek.
     *
     * @return array{
     *     total_references: int,
     *     verified_count: int,
     *     unverified_count: int,
     *     year_range: array{min: int|null, max: int|null},
     *     with_method_count: int,
     *     with_result_count: int,
     *     with_gap_count: int,
     *     completion_rate: float
     * }
     */
    public function synthesisStats(XishieProject $project): array
    {
        $rows = $this->build($project);
        $total = count($rows);

        $verified = 0;
        $years = [];
        $withMethod = 0;
        $withResult = 0;
        $withGap = 0;

        foreach ($rows as $r) {
            if ($r['is_verified']) {
                $verified++;
            }
            if ($r['year'] !== null && is_numeric($r['year'])) {
                $years[] = (int) $r['year'];
            }
            if (! empty($r['method']) && trim((string) $r['method']) !== '-' && trim((string) $r['method']) !== '') {
                $withMethod++;
            }
            if (! empty($r['result']) && trim((string) $r['result']) !== '-' && trim((string) $r['result']) !== '') {
                $withResult++;
            }
            if (! empty($r['gap']) && trim((string) $r['gap']) !== '-' && trim((string) $r['gap']) !== '') {
                $withGap++;
            }
        }

        $minYear = ! empty($years) ? min($years) : null;
        $maxYear = ! empty($years) ? max($years) : null;
        $unverified = $total - $verified;

        $completionRate = $total > 0
            ? round((($withMethod + $withResult + $withGap) / ($total * 3)) * 100, 1)
            : 0.0;

        return [
            'total_references' => $total,
            'verified_count' => $verified,
            'unverified_count' => $unverified,
            'year_range' => ['min' => $minYear, 'max' => $maxYear],
            'with_method_count' => $withMethod,
            'with_result_count' => $withResult,
            'with_gap_count' => $withGap,
            'completion_rate' => $completionRate,
        ];
    }

    /**
     * Render matriks literatur (SOTA) sebagai kode tabel LaTeX tabular.
     */
    public function toLatex(XishieProject $project): string
    {
        $rows = $this->build($project);
        $escape = static fn (string $s): string => str_replace(
            ['\\', '&', '%', '$', '#', '_', '{', '}', '~', '^'],
            ['\\textbackslash{}', '\\&', '\\%', '\\$', '\\#', '\\_', '\\{', '\\}', '\\textasciitilde{}', '\\textasciicircum{}'],
            $s,
        );

        $out = "% Tabel Matriks Literatur (State of the Art) - {$escape($project->title)}\n";
        $out .= "\\begin{table}[htbp]\n";
        $out .= "\\centering\n";
        $out .= "\\small\n";
        $out .= "\\begin{tabular}{|c|p{2.5cm}|c|p{3cm}|p{3.5cm}|p{3cm}|}\n";
        $out .= "\\hline\n";
        $out .= "\\textbf{No} & \\textbf{Penulis} & \\textbf{Tahun} & \\textbf{Metode} & \\textbf{Hasil Temuan} & \\textbf{Gap/Keterbatasan} \\\\\n";
        $out .= "\\hline\n";

        foreach ($rows as $index => $row) {
            $num = $index + 1;
            $author = $row['authors'] !== '' ? $row['authors'] : $row['citation_key'];
            $year = (string) ($row['year'] ?? '-');
            $method = (string) ($row['method'] ?? '-');
            $result = (string) ($row['result'] ?? '-');
            $gap = (string) ($row['gap'] ?? '-');

            $out .= sprintf(
                "%d & %s & %s & %s & %s & %s \\\\\n\\hline\n",
                $num,
                $escape($author),
                $escape($year),
                $escape($method),
                $escape($result),
                $escape($gap)
            );
        }

        $out .= "\\end{tabular}\n";
        $out .= "\\caption{Matriks Sintesis Literatur (State of the Art) - ".$escape($project->title)."}\n";
        $out .= "\\label{tab:sota-matrix}\n";
        $out .= "\\end{table}\n";

        return $out;
    }

    /**
     * Render matriks literatur sebagai berkas OpenDocument (.odt).
     */
    public function toOdt(XishieProject $project): string
    {
        $rows = $this->build($project);
        $blocks = [
            [
                'type' => 'heading',
                'level' => 1,
                'text' => 'Tabel Matriks Literatur (State-of-the-Art)',
            ],
            [
                'type' => 'paragraph',
                'text' => "Proyek Riset: {$project->title} | Bidang: {$project->field_of_study->label()} | Total Referensi: ".count($rows),
            ],
            $this->toTableBlock($project),
        ];

        return app(NaskahOdtExporter::class)->build($blocks, 'Matriks Literatur - '.$project->title);
    }

    private function escape(string $value): string
    {
        return str_replace('|', '\\|', $value);
    }
}
