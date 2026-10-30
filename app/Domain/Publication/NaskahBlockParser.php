<?php

declare(strict_types=1);

namespace App\Domain\Publication;

use App\Models\NaskahDocument;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;

/**
 * NaskahBlockParser: mengubah teks bebas (hasil ekstraksi DOCX/PDF/TXT/MD)
 * atau isi sub-bab proyek menjadi blok naskah terstruktur untuk Naskah Studio.
 *
 * Blok: ['type' => heading|paragraph|quote|bullet|numbered, 'level' => int, 'text' => string]
 * Deteksi judul dari penanda markdown (#, ##) maupun baris pendek tanpa
 * tanda baca akhir yang berdiri sendiri, agar dokumen lokal tetap terbaca.
 */
final class NaskahBlockParser
{
    /**
     * Urai teks polos/markdown menjadi blok naskah.
     *
     * @return array<int, array{type: string, level: int, text: string}>
     */
    public function parse(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        $lines = preg_split('/\n/u', $text) ?: [];

        $blocks = [];
        $buffer = [];

        $flush = function () use (&$buffer, &$blocks): void {
            $paragraph = trim(implode(' ', $buffer));
            $buffer = [];

            if ($paragraph !== '') {
                $blocks[] = ['type' => 'paragraph', 'level' => 0, 'text' => $this->normalizeSpaces($paragraph)];
            }
        };

        foreach ($lines as $rawLine) {
            $line = trim($rawLine);

            if ($line === '') {
                $flush();

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/u', $line, $m) === 1) {
                $flush();
                $blocks[] = [
                    'type' => 'heading',
                    'level' => min(3, strlen($m[1])),
                    'text' => $this->normalizeSpaces(trim($m[2])),
                ];

                continue;
            }

            if (preg_match('/^>\s*(?:\[!(NOTE|TIP|WARNING|IMPORTANT|CAUTION)\]|\*\*\[(.*?)\]\*\*)\s*(.*)$/ui', $line, $cm) === 1) {
                $flush();
                $cType = strtolower(($cm[1] ?? '') !== '' ? $cm[1] : ($cm[2] ?? 'catatan'));
                $blocks[] = [
                    'type' => 'callout',
                    'calloutType' => $cType,
                    'level' => 0,
                    'text' => $this->normalizeSpaces(trim($cm[3] ?? '')),
                ];

                continue;
            }

            if (preg_match('/^\[\^([^\]]+)\]:\s*(.+)$/u', $line, $fm) === 1) {
                $flush();
                $blocks[] = [
                    'type' => 'footnote-ref',
                    'level' => 0,
                    'text' => $this->normalizeSpaces(trim($fm[2])),
                ];

                continue;
            }

            if (preg_match('/^\$\$\s*(.+?)\s*\$\$$/u', $line, $em) === 1) {
                $flush();
                $eqCaption = '';
                $lastIdx = count($blocks) - 1;
                if ($lastIdx >= 0 && in_array($blocks[$lastIdx]['type'] ?? '', ['paragraph', 'heading'], true)) {
                    $prevText = trim((string) ($blocks[$lastIdx]['text'] ?? ''));
                    if (preg_match('/^(?:persamaan|equation|rumus)\s*\d*[:.-]\s*(.+)$/ui', $prevText)) {
                        $eqCaption = $prevText;
                        array_pop($blocks);
                    }
                }

                $blocks[] = [
                    'type' => 'equation',
                    'level' => 0,
                    'text' => trim($em[1]),
                    'latex' => trim($em[1]),
                    'caption' => $eqCaption,
                ];

                continue;
            }

            if (preg_match('/^\|(.+)\|$/u', $line, $tm) === 1) {
                if (preg_match('/^\|[\s\-:|]+\|$/', $line) === 1) {
                    continue;
                }
                $flush();
                $cells = array_map(static fn (string $c): array => ['text' => trim($c)], explode('|', $tm[1]));
                $lastIdx = count($blocks) - 1;
                if ($lastIdx >= 0 && ($blocks[$lastIdx]['type'] ?? '') === 'table') {
                    $blocks[$lastIdx]['table']['rows'][] = $cells;
                } else {
                    $caption = '';
                    if ($lastIdx >= 0 && in_array($blocks[$lastIdx]['type'] ?? '', ['paragraph', 'heading'], true)) {
                        $prevText = trim((string) ($blocks[$lastIdx]['text'] ?? ''));
                        if (preg_match('/^(?:tabel|table)\s*\d*[:.-]\s*(.+)$/ui', $prevText)) {
                            $caption = $prevText;
                            array_pop($blocks);
                        }
                    }

                    $blocks[] = [
                        'type' => 'table',
                        'level' => 0,
                        'text' => '',
                        'table' => [
                            'rows' => [$cells],
                            'header' => true,
                            'caption' => $caption,
                        ],
                    ];
                }

                continue;
            }

            if (preg_match('/^>\s?(.+)$/u', $line, $m) === 1) {
                $flush();
                $blocks[] = ['type' => 'quote', 'level' => 0, 'text' => $this->normalizeSpaces(trim($m[1]))];

                continue;
            }

            if (preg_match('/^[-*•]\s+(.+)$/u', $line, $m) === 1) {
                $flush();
                $blocks[] = ['type' => 'bullet', 'level' => 0, 'text' => $this->normalizeSpaces(trim($m[1]))];

                continue;
            }

            if (preg_match('/^\d+[.)]\s+(.+)$/u', $line, $m) === 1) {
                $flush();
                $blocks[] = ['type' => 'numbered', 'level' => 0, 'text' => $this->normalizeSpaces(trim($m[1]))];

                continue;
            }

            // Baris pendek berdiri sendiri tanpa tanda baca akhir -> anggap sub-judul.
            if ($buffer === [] && $this->looksLikeHeading($line)) {
                $blocks[] = ['type' => 'heading', 'level' => 1, 'text' => $this->normalizeSpaces($line)];

                continue;
            }

            $buffer[] = $line;
        }

        $flush();

        return $blocks;
    }

    /**
     * Ubah isi sub-bab sebuah tahap proyek menjadi blok naskah.
     *
     * @return array<int, array{type: string, level: int, text: string}>
     */
    public function fromProjectStage(XishieProject $project, ProjectStageRecord $stage): array
    {
        $blocks = [];
        $sections = (array) ($stage->sections_content ?? []);

        foreach ($sections as $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? ''));
            $content = trim((string) ($section['content'] ?? ''));

            if ($heading !== '') {
                $blocks[] = ['type' => 'heading', 'level' => 1, 'text' => $heading];
            }

            if ($content !== '') {
                foreach ($this->parse($content) as $parsed) {
                    // Isi sub-bab selalu dimulai sebagai paragraf, bukan judul.
                    if ($parsed['type'] === 'heading') {
                        $parsed['type'] = 'paragraph';
                        $parsed['level'] = 0;
                    }

                    $blocks[] = $parsed;
                }
            }
        }

        // Bila proyek tanpa sub-bab, pakai naskah gabungan sebagai fallback.
        if ($blocks === []) {
            $compiled = trim((string) ($stage->compiled_content ?? ''));

            if ($compiled !== '') {
                $blocks = $this->parse($compiled);
            }
        }

        return $blocks;
    }

    /**
     * Hitung total kata dari sekumpulan blok.
     *
     * @param  array<int, array<string, mixed>>  $blocks
     */
    public function countWords(array $blocks): int
    {
        $total = 0;

        foreach ($blocks as $block) {
            $text = strip_tags((string) ($block['text'] ?? ''));

            if (($block['type'] ?? '') === 'table') {
                foreach ((array) ($block['table']['rows'] ?? []) as $row) {
                    foreach ((array) $row as $cell) {
                        $text .= ' '.strip_tags((string) (is_array($cell) ? ($cell['text'] ?? '') : $cell));
                    }
                }
            }

            $total += str_word_count($text);
        }

        return $total;
    }

    /**
     * Normalisasi blok dari input pengguna agar aman & konsisten.
     *
     * @param  array<int|string, mixed>  $blocks
     * @return array<int, array<string, mixed>>
     */
    public function normalize(array $blocks): array
    {
        $normalized = [];

        foreach ($blocks as $block) {
            if (! is_array($block)) {
                continue;
            }

            $type = (string) ($block['type'] ?? 'paragraph');

            if (! in_array($type, NaskahDocument::BLOCK_TYPES, true)) {
                $type = 'paragraph';
            }

            $level = $type === 'heading' ? max(1, min(3, (int) ($block['level'] ?? 1))) : 0;

            $item = [
                'type' => $type,
                'level' => $level,
                'text' => trim((string) ($block['text'] ?? '')),
            ];

            foreach (['table', 'calloutType', 'equation', 'latex', 'caption', 'image', 'runs', 'align'] as $extraKey) {
                if (isset($block[$extraKey])) {
                    $item[$extraKey] = $block[$extraKey];
                }
            }

            $normalized[] = $item;
        }

        return $normalized;
    }

    /**
     * Baris pendek tanpa tanda akhir dan tanpa titik -> kandidat judul.
     */
    private function looksLikeHeading(string $line): bool
    {
        $length = mb_strlen($line);

        if ($length === 0 || $length > 80) {
            return false;
        }

        // Kalimat lengkap biasanya diakhiri tanda baca.
        if (preg_match('/[.!?;:,]$/u', $line) === 1) {
            return false;
        }

        // Harus ada huruf dan maksimal 12 kata.
        if (preg_match('/\p{L}/u', $line) !== 1) {
            return false;
        }

        return count(preg_split('/\s+/u', $line) ?: []) <= 12;
    }

    private function normalizeSpaces(string $text): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
