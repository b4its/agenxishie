<?php

declare(strict_types=1);

namespace App\Domain\GuidelineEngine;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use InvalidArgumentException;

/**
 * CustomGuidelineParser: mengekstrak aturan pedoman penulisan dari teks
 * hasil ekstraksi PDF/DOCX menjadi struktur JSON (GuidelineDefinition).
 *
 * Ekstraksi berbasis pola deterministik (judul bab, batas kata, gaya sitasi)
 * sehingga dapat diuji tanpa dependensi biner PDF. Ekstraksi teks dari
 * berkas diserahkan ke layer pemanggil (RuleKeeperAgent).
 */
final class CustomGuidelineParser
{
    /**
     * Ekstrak aturan dari teks pedoman.
     *
     * @param  string  $text  teks mentah pedoman
     * @param  GuidelineDefinition  $fallback  preset yang dipakai bila sebagian aturan tak terdeteksi
     */
    public function parse(string $text, GuidelineDefinition $fallback): GuidelineDefinition
    {
        if (trim($text) === '') {
            throw new InvalidArgumentException('Teks pedoman kosong; tidak dapat diekstrak.');
        }

        $sections = $this->extractSections($text, $fallback);
        $totalWords = $this->extractTotalWordRange($text) ?? ['min' => $fallback->minTotalWords, 'max' => $fallback->maxTotalWords];
        $abstract = $this->extractAbstractRange($text);
        $citationStyle = $this->extractCitationStyle($text) ?? $fallback->citationStyle;
        $stages = $this->mapStages($sections, $fallback);

        return new GuidelineDefinition(
            source: 'custom_upload',
            citationStyle: $citationStyle,
            minTotalWords: $totalWords['min'],
            maxTotalWords: $totalWords['max'],
            abstractRequired: $abstract !== null || $fallback->abstractRequired,
            minAbstractWords: $abstract['min'] ?? $fallback->minAbstractWords,
            maxAbstractWords: $abstract['max'] ?? $fallback->maxAbstractWords,
            sections: $sections,
            stages: $stages,
            notes: ['Diekstrak otomatis dari berkas pedoman pengguna.'],
        );
    }

    /**
     * Ekstrak daftar bab/section dari teks.
     *
     * @return array<int, array{key: string, heading: string, min_words: int, max_words: int, required: bool}>
     */
    private function extractSections(string $text, GuidelineDefinition $fallback): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $text) ?: [];
        $sections = [];

        foreach ($lines as $line) {
            $trimmed = trim($line);

            // Cocokkan pola "BAB I ...", "BAB 1 ...", "1. Introduction", "Abstrak".
            if (! preg_match('/^(BAB\s+[IVX0-9]+|Abstrak|Abstract|\d+\.\s+[A-Z][^\n]{3,80})/u', $trimmed)) {
                continue;
            }

            $heading = preg_replace('/\s{2,}/u', ' ', $trimmed) ?? $trimmed;
            $key = $this->keyForHeading($heading);

            if (isset($sections[$key])) {
                continue;
            }

            $fallbackSection = $this->findFallbackSection($fallback, $key);

            $sections[$key] = [
                'key' => $key,
                'heading' => $heading,
                'min_words' => $fallbackSection['min_words'] ?? 300,
                'max_words' => $fallbackSection['max_words'] ?? 2000,
                'required' => true,
            ];
        }

        // Bila tidak ada heading terdeteksi, pakai struktur fallback.
        return $sections !== [] ? array_values($sections) : $fallback->sections;
    }

    private function keyForHeading(string $heading): string
    {
        $lower = mb_strtolower($heading);

        return match (true) {
            str_contains($lower, 'abstrak'), str_contains($lower, 'abstract') => 'abstract',
            str_contains($lower, 'pendahuluan'), str_contains($lower, 'introduction') => 'chapter1',
            str_contains($lower, 'tinjauan'), str_contains($lower, 'related work') => 'chapter2',
            str_contains($lower, 'metodologi'), str_contains($lower, 'methodology') => 'chapter3',
            str_contains($lower, 'hasil'), str_contains($lower, 'results') => 'chapter4',
            str_contains($lower, 'kesimpulan'), str_contains($lower, 'conclusion') => 'chapter5',
            default => 'section_'.substr(md5($lower), 0, 8),
        };
    }

    /**
     * @return array{min_words: int, max_words: int}|null
     */
    private function findFallbackSection(GuidelineDefinition $fallback, string $key): ?array
    {
        foreach ($fallback->sections as $section) {
            if ($section['key'] === $key) {
                return ['min_words' => $section['min_words'], 'max_words' => $section['max_words']];
            }
        }

        return null;
    }

    /**
     * @param  array<int, array{key: string, heading: string, min_words: int, max_words: int, required: bool}>  $sections
     * @return array<string, array<int, string>>
     */
    private function mapStages(array $sections, GuidelineDefinition $fallback): array
    {
        $keys = array_map(static fn (array $section): string => $section['key'], $sections);

        $draft = array_values(array_filter($keys, static fn (string $key): bool => str_contains($key, 'statement') || str_contains($key, 'sota') || str_contains($key, 'abstract')));
        $proposal = array_values(array_filter($keys, static fn (string $key): bool => in_array($key, ['chapter1', 'chapter2', 'chapter3'], true)));

        return [
            'draft' => $draft !== [] ? $draft : $fallback->stages['draft'] ?? $keys,
            'proposal' => $proposal !== [] ? $proposal : $fallback->stages['proposal'] ?? $keys,
            'final' => $keys,
        ];
    }

    /**
     * @return array{min: int, max: int}|null
     */
    private function extractTotalWordRange(string $text): ?array
    {
        if (preg_match('/(\d[\d\.]{2,6})\s*(?:-|s\/d|sampai|hingga|to)\s*(\d[\d\.]{2,6})\s*(?:kata|words)/iu', $text, $m)) {
            return ['min' => (int) str_replace('.', '', $m[1]), 'max' => (int) str_replace('.', '', $m[2])];
        }

        if (preg_match('/(?:maksimal|maximum|max\.?)\s*(\d[\d\.]{2,6})\s*(?:kata|words)/iu', $text, $m)) {
            return ['min' => 0, 'max' => (int) str_replace('.', '', $m[1])];
        }

        return null;
    }

    /**
     * @return array{min: int, max: int}|null
     */
    private function extractAbstractRange(string $text): ?array
    {
        if (preg_match('/abstrak[^\n]{0,80}?(\d{2,4})\s*(?:-|s\/d|sampai|hingga|to)\s*(\d{2,4})\s*(?:kata|words)/iu', $text, $m)) {
            return ['min' => (int) $m[1], 'max' => (int) $m[2]];
        }

        return null;
    }

    private function extractCitationStyle(string $text): ?string
    {
        $lower = mb_strtolower($text);

        return match (true) {
            str_contains($lower, 'apa') => 'apa7',
            str_contains($lower, 'ieee') => 'ieee',
            str_contains($lower, 'vancouver') => 'vancouver',
            str_contains($lower, 'chicago') => 'chicago',
            str_contains($lower, 'harvard') => 'harvard',
            default => null,
        };
    }
}
