<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Models\NaskahDocument;
use Illuminate\Console\Command;

/**
 * NaskahStudioCommand: antarmuka baris perintah (CLI) naskah bergaya wordcraft-cli.
 *
 * Mengizinkan inspeksi naskah, statistik keterbacaan, ekspor multi-format (DOCX, ODT, HTML, LaTeX, RTF, MD, TXT),
 * serta auto-correct tipografi langsung dari terminal.
 */
class NaskahStudioCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'naskah:manage
                            {action : list|inspect|stats|export|autocorrect}
                            {documentId? : ID dokumen naskah}
                            {--format=docx : Format ekspor (docx, odt, html, latex, rtf, md, txt)}
                            {--output= : Path berkas luaran}';

    /**
     * @var string
     */
    protected $description = 'Kelola dokumen Naskah Studio via CLI (wordcraft-cli style)';

    public function handle(NaskahRichModel $model, NaskahDocxExporter $docx, NaskahOdtExporter $odt): int
    {
        $action = (string) $this->argument('action');
        $documentId = $this->argument('documentId');

        if ($action === 'list') {
            $docs = NaskahDocument::query()->latest('updated_at')->limit(20)->get();
            $this->table(
                ['ID', 'Judul', 'Sumber', 'Jumlah Kata', 'Terakhir Disimpan'],
                $docs->map(fn (NaskahDocument $d) => [
                    $d->id,
                    $d->title,
                    $d->source_type,
                    $d->word_count,
                    $d->last_saved_at?->toDateTimeString() ?? '-',
                ])
            );

            return self::SUCCESS;
        }

        if (! is_numeric($documentId)) {
            $this->error('Tentukan ID dokumen naskah (contoh: php artisan naskah:manage inspect 1).');

            return self::FAILURE;
        }

        $document = NaskahDocument::query()->find((int) $documentId);
        if ($document === null) {
            $this->error("Dokumen naskah dengan ID #{$documentId} tidak ditemukan.");

            return self::FAILURE;
        }

        $blocks = $model->normalize((array) $document->blocks);

        return match ($action) {
            'inspect' => $this->handleInspect($document, $blocks, $model),
            'stats' => $this->handleStats($document, $blocks, $model),
            'export' => $this->handleExport($document, $blocks, $model, $docx, $odt),
            'autocorrect' => $this->handleAutoCorrect($document, $blocks, $model),
            default => $this->handleUnknownAction($action),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleInspect(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $this->info("=== INSPEKSI NASKAH: {$document->title} (ID #{$document->id}) ===");
        $this->line("Sumber: {$document->source_type} (berkas: ".($document->source_filename ?? '-').')');
        $this->line('Jumlah Blok: '.count($blocks));
        $this->line('Jumlah Kata: '.$document->computeWordCount());

        $outline = $model->outline($blocks);
        if ($outline !== []) {
            $this->line("\nStruktur Judul (Outline):");
            foreach ($outline as $node) {
                $indent = str_repeat('  ', max(0, $node['level'] - 1));
                $this->line("{$indent}[H{$node['level']}] {$node['text']}");
            }
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleStats(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $stats = $model->computeStatistics($blocks);
        $this->info("=== STATISTIK & KETERBACAAN: {$document->title} ===");
        $this->table(
            ['Metrik', 'Nilai'],
            [
                ['Kata', number_format($stats['words'])],
                ['Karakter (dengan spasi)', number_format($stats['characters'])],
                ['Karakter (tanpa spasi)', number_format($stats['charactersNoSpaces'])],
                ['Kalimat', $stats['sentences']],
                ['Paragraf', $stats['paragraphs']],
                ['Suku Kata', $stats['syllables']],
                ['Flesch Reading Ease (0-100)', $stats['readingEase']],
                ['Flesch-Kincaid Grade Level', 'Kelas '.$stats['gradeLevel']],
                ['Estimasi Waktu Membaca', '~'.$stats['readingTimeMinutes'].' menit'],
                ['Estimasi Waktu Presentasi', '~'.$stats['speakingTimeMinutes'].' menit'],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleExport(NaskahDocument $document, array $blocks, NaskahRichModel $model, NaskahDocxExporter $docx, NaskahOdtExporter $odt): int
    {
        $format = strtolower((string) $this->option('format'));
        $output = (string) $this->option('output');

        if ($output === '') {
            $output = storage_path('app/naskah-export-'.$document->id.'.'.$format);
        }

        $binary = match ($format) {
            'docx' => $docx->build($blocks, $document->title),
            'odt' => $odt->build($blocks, $document->title),
            'html' => $model->toHtml($blocks, $document->title),
            'latex', 'tex' => $model->toLatex($blocks, $document->title),
            'rtf' => $model->toRtf($blocks, $document->title),
            'txt' => $model->toPlainText($blocks),
            default => $model->toMarkdown($blocks, $document->title),
        };

        file_put_contents($output, $binary);
        $this->info("Naskah berhasil diekspor ke: {$output} (format: {$format})");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleAutoCorrect(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $total = 0;
        foreach ($blocks as $i => $block) {
            $text = (string) ($block['text'] ?? '');
            if (trim($text) === '') {
                continue;
            }
            $res = $model->autoCorrect($text);
            if ($res['count'] > 0) {
                $total += $res['count'];
                $blocks[$i]['text'] = $res['text'];
                $blocks[$i]['runs'] = [];
            }
        }

        $document->update(['blocks' => $blocks]);
        $this->info("Auto-correct selesai: {$total} perbaikan tipografi disimpan pada dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    private function handleUnknownAction(string $action): int
    {
        $this->error("Aksi '{$action}' tidak dikenal. Pilih: list, inspect, stats, export, autocorrect.");

        return self::FAILURE;
    }
}
