<?php

declare(strict_types=1);

namespace App\Domain\Research;

/**
 * PdfTextExtractor: ekstraksi teks dari berkas PDF sederhana (tanpa
 * dependensi ekstensi PHP tambahan).
 *
 * Strategi: membaca stream PDF, mendekompres objek FlateDecode (zlib tersedia
 * sebagai fungsi bawaan PHP), lalu mengambil operator teks `Tj`/`TJ`.
 * Untuk PDF berbasis scan (gambar), ekstraksi teks tidak dapat dilakukan dan
 * hasilnya kosong — pemanggil harus menangani kasus tersebut.
 */
final class PdfTextExtractor
{
    /**
     * Ekstrak teks dari konten biner PDF.
     */
    public function extract(string $binary): string
    {
        if (! str_starts_with(ltrim($binary), '%PDF')) {
            return '';
        }

        $chunks = [];

        // Ekstrak seluruh stream objek.
        if (preg_match_all('/stream\r?\n(.*?)endstream/s', $binary, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = $this->inflate($stream);

                if ($decoded !== null) {
                    $chunks[] = $decoded;
                }
            }
        }

        // PDF tanpa kompresi: ambil langsung operator teks.
        $chunks[] = $binary;

        $text = [];

        foreach ($chunks as $chunk) {
            $text[] = $this->extractOperators($chunk);
        }

        $merged = trim(preg_replace('/[ \t]+/u', ' ', implode(' ', $text)) ?? '');

        return trim(preg_replace('/\s{2,}/u', ' ', $merged) ?? '');
    }

    private function inflate(string $stream): ?string
    {
        if (function_exists('gzuncompress')) {
            $result = @gzuncompress($stream);

            if ($result !== false) {
                return $result;
            }
        }

        if (function_exists('gzinflate')) {
            $result = @gzinflate($stream);

            if ($result !== false) {
                return $result;
            }
        }

        return null;
    }

    /**
     * Ambil string literal dari operator teks (Tj, TJ, ').
     */
    private function extractOperators(string $content): string
    {
        $parts = [];

        if (preg_match_all('/\((?<text>(?:\\\\.|[^\\\\()])*)\)\s*(?:Tj|TJ|\')/s', $content, $matches)) {
            foreach ($matches['text'] as $literal) {
                $parts[] = $this->unescape($literal);
            }
        }

        // Array TJ: [(A) -200 (B)] TJ
        if (preg_match_all('/\[(?<arr>.*?)\]\s*TJ/s', $content, $arrays)) {
            foreach ($arrays['arr'] as $array) {
                if (preg_match_all('/\((?<text>(?:\\\\.|[^\\\\()])*)\)/s', $array, $lits)) {
                    foreach ($lits['text'] as $literal) {
                        $parts[] = $this->unescape($literal);
                    }
                }
            }
        }

        return implode(' ', $parts);
    }

    private function unescape(string $value): string
    {
        return str_replace(
            ['\\(', '\\)', '\\\\', '\\n', '\\r', '\\t'],
            ['(', ')', '\\', ' ', ' ', ' '],
            $value,
        );
    }
}
