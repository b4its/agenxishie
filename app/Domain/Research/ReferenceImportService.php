<?php

declare(strict_types=1);

namespace App\Domain\Research;

use App\Models\ProjectReference;
use App\Models\XishieProject;
use App\Omnigensi\Agents\ScholarScoutAgent;
use RuntimeException;

/**
 * ReferenceImportService: mengimpor berkas PDF referensi menjadi entri
 * project_references beserta ekstraksi matriks literatur.
 *
 * PRD 3.4: "Upload PDF referensi untuk diekstrak menjadi Tabel Matriks Literatur
 * (Penulis, Tahun, Metode, Hasil, Gap/Keterbatasan)."
 */
final class ReferenceImportService
{
    public function __construct(
        private readonly PdfTextExtractor $extractor,
        private readonly ScholarScoutAgent $scholarScout,
    ) {}

    /**
     * Impor satu dokumen PDF (biner) sebagai referensi proyek.
     */
    public function import(
        XishieProject $project,
        string $binary,
        string $filename,
        ?string $citationKey = null,
    ): ProjectReference {
        $text = $this->extractor->extract($binary);

        if (trim($text) === '') {
            throw new RuntimeException(
                "Tidak ada teks yang dapat diekstrak dari '{$filename}'. "
                .'Berkas mungkin hasil pindai (gambar); unggah PDF berbasis teks.',
            );
        }

        $metadata = $this->guessMetadata($text, $filename);

        $this->scholarScout->ingestCandidates($project, [[
            'title' => $metadata['title'],
            'abstract' => $metadata['abstract'],
            'authors' => $metadata['authors'],
            'year' => $metadata['year'],
            'citation_key' => $citationKey ?? $metadata['citation_key'],
            'method' => $metadata['method'],
            'result' => $metadata['result'],
            'gap' => $metadata['gap'],
            'source' => 'pdf_upload',
            'is_verified' => true,
        ]]);

        $reference = $project->references()
            ->where('citation_key', $citationKey ?? $metadata['citation_key'])
            ->first();

        if ($reference === null) {
            throw new RuntimeException('Gagal menyimpan referensi hasil impor PDF.');
        }

        $reference->forceFill([
            'pdf_path' => 'references/'.$filename,
        ])->save();

        return $reference;
    }

    /**
     * Tebak metadata dari teks awal PDF (pendekatan heuristik).
     *
     * @return array{title: string, abstract: string, authors: array<int, string>, year: int|null, citation_key: string, method: string|null, result: string|null, gap: string|null}
     */
    public function guessMetadata(string $text, string $filename): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');

        // Judul: baris awal yang cukup panjang sebelum abstrak.
        $title = 'Referensi '.pathinfo($filename, PATHINFO_FILENAME);
        $abstract = '';

        $lines = array_values(array_filter(
            array_map('trim', preg_split('/\n/u', (string) preg_replace('/\. (?=[A-Z][a-z])/u', ".\n", $normalized)) ?: []),
            static fn (string $line): bool => $line !== '',
        ));

        foreach (array_slice($lines, 0, 5) as $line) {
            if (mb_strlen($line) >= 20 && mb_strlen($line) <= 250) {
                $title = $line;

                break;
            }
        }

        if (preg_match('/(?:Abstract|ABSTRAK)[.:\s]+(.{80,1200}?)(?:Keywords|Kata Kunci|1\.\s|$)/su', $normalized, $m)) {
            $abstract = trim($m[1]);
        }

        if ($abstract === '') {
            $abstract = mb_substr($normalized, 0, 600);
        }

        $year = null;

        if (preg_match('/\b(19|20)\d{2}\b/u', $normalized, $m)) {
            $year = (int) $m[0];
        }

        $authors = [];

        if (preg_match('/^([A-Z][A-Za-z\-\.]+(?:\s+[A-Z][A-Za-z\-\.]+){0,2}(?:,\s*[A-Z][A-Za-z\-\.]+(?:\s+[A-Z][A-Za-z\-\.]+){0,2})*)/u', $normalized, $m)) {
            $authors = array_values(array_filter(array_map('trim', preg_split('/,\s*/u', $m[1]) ?: [])));
        }

        $firstAuthor = $authors[0] ?? 'Anonim';
        $firstWord = preg_split('/\s+/u', $firstAuthor)[0] ?? 'Anonim';

        return [
            'title' => $title,
            'abstract' => $abstract,
            'authors' => $authors !== [] ? $authors : ['Anonim'],
            'year' => $year,
            'citation_key' => $firstWord.' '.($year ?? (int) now()->year),
            'method' => $this->detect($normalized, ['metode', 'method', 'experiment', 'eksperimen', 'survey', 'autoencoder', 'regression']),
            'result' => $this->detect($normalized, ['hasil', 'result', 'accuracy', 'akurasi', 'f1', 'precision']),
            'gap' => $this->detect($normalized, ['gap', 'limitation', 'keterbatasan', 'future work', 'penelitian selanjutnya']),
        ];
    }

    /**
     * @param  array<int, string>  $keywords
     */
    private function detect(string $text, array $keywords): ?string
    {
        foreach ($keywords as $keyword) {
            if (preg_match('/[^.]{0,120}\b'.preg_quote($keyword, '/').'\b[^.]{0,120}\./iu', $text, $m)) {
                return trim($m[0]);
            }
        }

        return null;
    }
}
