<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use App\Domain\Research\PdfTextExtractor;
use ZipArchive;

/**
 * DocumentTextExtractor: ekstraksi teks dari berkas PDF, DOCX, atau teks
 * biasa menjadi string UTF-8. Dipakai bersama oleh Onboarding (pedoman
 * custom), Revision Center (komentar reviewer), dan impor referensi.
 *
 * DOCX dibaca dari arsip zip `word/document.xml`; PDF memakai
 * PdfTextExtractor (stream FlateDecode). Berkas teks polos dipakai apa adanya.
 */
final class DocumentTextExtractor
{
    public function __construct(
        private readonly PdfTextExtractor $pdfExtractor,
    ) {}

    /**
     * Deteksi tipe berkas dari isi biner, lalu ekstrak teksnya.
     */
    public function extract(string $binary): string
    {
        if ($binary === '') {
            return '';
        }

        // PDF: magic %PDF.
        if (str_starts_with(ltrim($binary), '%PDF')) {
            return $this->pdfExtractor->extract($binary);
        }

        // DOCX/OOXML: arsip zip (magic PK).
        if (str_starts_with($binary, "PK\x03\x04")) {
            $docx = $this->extractDocx($binary);

            if ($docx !== null) {
                return $docx;
            }
        }

        // Fallback: berkas teks biasa.
        $plain = mb_convert_encoding($binary, 'UTF-8', 'UTF-8');

        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $plain));
    }

    /**
     * Ekstrak teks dari DOCX (proxy dari SmartRevisionCenterPage).
     */
    private function extractDocx(string $binary): ?string
    {
        if (! class_exists(ZipArchive::class)) {
            return null;
        }

        $temp = tempnam(sys_get_temp_dir(), 'xishie-docx-');

        if ($temp === false) {
            return null;
        }

        file_put_contents($temp, $binary);

        $zip = new ZipArchive;
        $text = null;

        if ($zip->open($temp) === true) {
            $xml = $zip->getFromName('word/document.xml');

            if (is_string($xml)) {
                $withBreaks = (string) preg_replace('/<\/w:p>/u', "\n", $xml);
                $stripped = strip_tags($withBreaks);
                $text = trim(html_entity_decode($stripped, ENT_QUOTES | ENT_XML1, 'UTF-8'));
            }

            $zip->close();
        }

        @unlink($temp);

        return $text !== '' ? $text : null;
    }
}
