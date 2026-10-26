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
                            {action : list|inspect|stats|export|autocorrect|paragraphs|format-paragraph|clean-page|audit-page|find-replace|export-page}
                            {documentId? : ID dokumen naskah}
                            {--format=docx : Format ekspor (docx, odt, html, latex, rtf, md, txt, json)}
                            {--output= : Path berkas luaran}
                            {--page=1 : Nomor halaman target untuk inspeksi atau format paragraf}
                            {--preset=academic_standard : Preset paragraf (academic_standard, journal_ieee, thesis_dikti, abstract_block, block_quote)}
                            {--search= : Kata kunci pencarian untuk aksi find-replace}
                            {--replace= : Teks pengganti untuk aksi find-replace}';

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
            'paragraphs' => $this->handleParagraphs($document, $blocks, $model),
            'format-paragraph' => $this->handleFormatParagraph($document, $blocks, $model),
            'clean-page' => $this->handleCleanPage($document, $blocks, $model),
            'audit-page' => $this->handleAuditPage($document, $blocks, $model),
            'find-replace' => $this->handleFindReplace($document, $blocks, $model),
            'export-page' => $this->handleExportPage($document, $blocks, $model),
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
            'json' => (string) json_encode([
                'id' => $document->id,
                'title' => $document->title,
                'word_count' => $document->word_count,
                'blocks' => $blocks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleParagraphs(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $paginated = $model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            $this->warn("Halaman #{$pageNumber} tidak ditemukan dalam dokumen #{$document->id} (total: ".count($paginated).' halaman).');

            return self::FAILURE;
        }

        $rows = [];
        $totalWords = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            $b = $item['block'];
            $type = (string) ($b['type'] ?? 'paragraph');
            if (! in_array($type, ['paragraph', 'quote', 'bullet', 'numbered', 'heading', 'callout', 'subtitle', 'title'], true)) {
                continue;
            }

            $text = trim((string) ($b['text'] ?? ''));
            $words = $text === '' ? 0 : count(preg_split('/\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $totalWords += $words;

            $snippet = mb_strlen($text) > 45 ? mb_substr($text, 0, 42).'...' : $text;
            $align = (string) ($b['align'] ?? 'left');
            $lineSpacing = (string) ($b['lineSpacing'] ?? '1.5');
            $indent = (int) ($b['indent'] ?? 0);
            $firstLineIndent = ! empty($b['firstLineIndent']) ? 'Ya (1.27cm)' : 'Tidak';
            $spacing = ($b['spacingBefore'] ?? 0).'pt / '.($b['spacingAfter'] ?? 6).'pt';

            $rows[] = [
                $idx,
                $type,
                $align,
                $lineSpacing,
                $firstLineIndent,
                $indent,
                $spacing,
                $words,
                $snippet,
            ];
        }

        $this->info("=== DAFTAR PARAGRAF HALAMAN #{$pageNumber} [Dokumen #{$document->id}: {$document->title}] ===");
        $this->table(
            ['Index', 'Tipe', 'Align', 'Spasi', 'Indent 1st', 'Indent (tab)', 'Spasi (Atas/Bwh)', 'Kata', 'Cuplikan'],
            $rows
        );
        $this->line('Total paragraf: '.count($rows)." | Total kata pada halaman ini: {$totalWords}");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleFormatParagraph(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $presetKey = (string) $this->option('preset');

        if (! isset(NaskahRichModel::PARAGRAPH_PRESETS[$presetKey])) {
            $available = implode(', ', array_keys(NaskahRichModel::PARAGRAPH_PRESETS));
            $this->error("Preset '{$presetKey}' tidak valid. Pilihan preset yang tersedia: {$available}");

            return self::FAILURE;
        }

        $paginated = $model->paginate($blocks);
        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            $this->warn("Halaman #{$pageNumber} tidak ditemukan dalam dokumen #{$document->id} (total: ".count($paginated).' halaman).');

            return self::FAILURE;
        }

        $updatedCount = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            $b = $blocks[$idx] ?? null;
            if ($b === null) {
                continue;
            }

            $type = (string) ($b['type'] ?? 'paragraph');
            if (in_array($type, ['paragraph', 'quote', 'bullet', 'numbered', 'callout'], true)) {
                $blocks[$idx] = $model->applyPresetToBlock($b, $presetKey);
                $updatedCount++;
            }
        }

        $document->update([
            'blocks' => $blocks,
            'word_count' => $model->countWords($blocks),
        ]);

        $presetLabel = NaskahRichModel::PARAGRAPH_PRESETS[$presetKey]['label'];
        $this->info("Berhasil memformat {$updatedCount} paragraf pada halaman #{$pageNumber} dengan preset '{$presetLabel}'.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleCleanPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $paginated = $model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            $this->warn("Halaman #{$pageNumber} tidak ditemukan dalam dokumen #{$document->id} (total: ".count($paginated).' halaman).');

            return self::FAILURE;
        }

        $cleanedCount = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx])) {
                continue;
            }
            $currentText = (string) ($blocks[$idx]['text'] ?? '');
            $newText = $model->cleanTextSpacing($currentText);
            if ($newText !== $currentText) {
                $blocks[$idx]['text'] = $newText;
                $blocks[$idx]['runs'] = $model->normalizeRuns($blocks[$idx]['runs'] ?? [], $newText);
                $cleanedCount++;
            }
        }

        $document->update([
            'blocks' => $blocks,
            'word_count' => $model->countWords($blocks),
        ]);

        $this->info("Berhasil merapikan spasi pada {$cleanedCount} paragraf di halaman #{$pageNumber} dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleAuditPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $paginated = $model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            $this->warn("Halaman #{$pageNumber} tidak ditemukan dalam dokumen #{$document->id}.");

            return self::FAILURE;
        }

        $issues = [];
        $totalWords = 0;
        $parasCount = 0;

        foreach ($targetPage['blocks'] as $item) {
            $b = $item['block'];
            $type = (string) ($b['type'] ?? 'paragraph');
            if ($type !== 'paragraph') {
                continue;
            }
            $parasCount++;
            $text = strip_tags((string) ($b['text'] ?? ''));
            $words = str_word_count($text);
            $totalWords += $words;

            if (($b['align'] ?? 'left') !== 'justify') {
                $issues[] = "Paragraf #{$parasCount} tidak rata justify ({$b['align']}).";
            }
            if (empty($b['firstLineIndent']) && $parasCount > 1) {
                $issues[] = "Paragraf #{$parasCount} tidak memiliki first-line indent.";
            }
            if ($words < 30) {
                $issues[] = "Paragraf #{$parasCount} terlalu pendek ({$words} kata).";
            }
        }

        $score = max(20, 100 - (count($issues) * 15));
        $this->info("=== AUDIT KEPATUHAN PARAGRAF HALAMAN #{$pageNumber} [Dokumen #{$document->id}] ===");
        $this->line("Skor Kepatuhan: {$score}% (".($score >= 80 ? 'Sesuai' : 'Perlu Perbaikan').')');
        $this->line("Total Paragraf: {$parasCount} | Total Kata: {$totalWords}");

        if ($issues !== []) {
            $this->warn("\nCatatan Tipografi:");
            foreach ($issues as $issue) {
                $this->line("  • {$issue}");
            }
        } else {
            $this->info("\nSemua paragraf pada halaman ini memenuhi kaidah tipografi akademik.");
        }

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleFindReplace(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $search = (string) $this->option('search');
        $replace = (string) $this->option('replace');

        if ($search === '') {
            $this->error('Tentukan kata yang dicari dengan opsi --search="..."');

            return self::FAILURE;
        }

        $paginated = $model->paginate($blocks);
        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            $this->warn("Halaman #{$pageNumber} tidak ditemukan dalam dokumen #{$document->id}.");

            return self::FAILURE;
        }

        $totalReplacements = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx])) {
                continue;
            }
            $text = (string) ($blocks[$idx]['text'] ?? '');
            $newText = str_ireplace($search, $replace, $text, $count);
            if ($count > 0) {
                $totalReplacements += $count;
                $blocks[$idx]['text'] = $newText;
                $blocks[$idx]['runs'] = $model->normalizeRuns($blocks[$idx]['runs'] ?? [], $newText);
            }
        }

        $document->update([
            'blocks' => $blocks,
            'word_count' => $model->countWords($blocks),
        ]);

        $this->info("Berhasil mengganti {$totalReplacements} kemunculan '{$search}' pada halaman #{$pageNumber}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleExportPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $format = strtolower((string) $this->option('format'));
        $output = (string) $this->option('output');

        $paginated = $model->paginate($blocks);
        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            $this->warn("Halaman #{$pageNumber} tidak ditemukan dalam dokumen #{$document->id}.");

            return self::FAILURE;
        }

        $pageBlocks = array_map(static fn (array $item): array => $item['block'], $targetPage['blocks']);
        $content = match ($format) {
            'html' => $model->toHtml($pageBlocks, "{$document->title} - Hal {$pageNumber}"),
            'text', 'txt' => $model->toPlainText($pageBlocks),
            default => $model->toMarkdown($pageBlocks, "{$document->title} - Hal {$pageNumber}"),
        };

        if ($output !== '') {
            file_put_contents($output, $content);
            $this->info("Halaman #{$pageNumber} berhasil diekspor ke berkas: {$output}");
        } else {
            $this->line($content);
        }

        return self::SUCCESS;
    }

    private function handleUnknownAction(string $action): int
    {
        $this->error("Aksi '{$action}' tidak dikenal. Pilih: list, inspect, stats, export, autocorrect, paragraphs, format-paragraph, clean-page, audit-page, find-replace, export-page.");

        return self::FAILURE;
    }
}
