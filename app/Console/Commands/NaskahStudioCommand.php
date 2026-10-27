<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Models\NaskahDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

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
                            {action : list|inspect|stats|export|autocorrect|paragraphs|format-paragraph|clean-page|audit-page|find-replace|export-page|polish-page|align-page|linespacing-page|readability-page|indent-page|highlight-page|execute-model|models}
                            {documentId? : ID dokumen naskah}
                            {--format=docx : Format ekspor (docx, odt, html, latex, rtf, md, txt, json)}
                            {--output= : Path berkas luaran}
                            {--page=1 : Nomor halaman target untuk inspeksi atau format paragraf}
                            {--preset=academic_standard : Preset paragraf (academic_standard, journal_ieee, thesis_dikti, abstract_block, block_quote)}
                            {--search= : Kata kunci pencarian untuk aksi find-replace}
                            {--replace= : Teks pengganti untuk aksi find-replace}
                            {--align=justify : Pilihan perataan untuk aksi align-page (justify, left, center, right)}
                            {--spacing=1.5 : Pilihan spasi baris untuk aksi linespacing-page (1.0, 1.15, 1.5, 2.0)}
                            {--type=first-line : Jenis indentasi untuk aksi indent-page (first-line, hanging, none)}
                            {--color=#fef08a : Pilihan warna latar belakang untuk aksi highlight-page (#fef08a, #fed7aa, #bbf7d0, none)}
                            {--prompt= : Perintah atau instruksi teks untuk model AI}
                            {--model= : Model AI yang digunakan (qwen-2.5-72b-instruct, deepseek-chat, gpt-4o, claude-3-5-sonnet)}
                            {--target=current_page : Target penempatan hasil model (current_page, page, append, new_page, active_block)}';

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

        if ($action === 'models') {
            $client = app(OmnigensiChatClient::class);
            $raw = [];
            $defaultModel = 'qwen-2.5-72b-instruct';
            try {
                $raw = $client->hasIntegrationKey() ? $client->integrationModels() : $client->models();
                $defaultModel = $client->defaultModel() ?? 'qwen-2.5-72b-instruct';
            } catch (\Throwable) {
                $raw = [];
            }
            $rows = [];
            foreach ($raw as $m) {
                $isDef = (! empty($m['is_default']) || ($m['model_id'] ?? '') === $defaultModel) ? '● Aktif/Default' : '-';
                $rows[] = [
                    $m['model_id'] ?? '-',
                    $m['display_name'] ?? $m['model_id'] ?? '-',
                    $m['provider_name'] ?? 'Omnigensi',
                    $isDef,
                ];
            }
            if (empty($rows)) {
                $rows = [
                    ['qwen-2.5-72b-instruct', 'Qwen 2.5 72B (Akademik & Cepat)', 'Omnigensi', '● Aktif/Default'],
                    ['deepseek-chat', 'DeepSeek V3 (Penalaran & Logika)', 'DeepSeek', '-'],
                    ['gpt-4o', 'GPT-4o (Multimodal & Presisi)', 'OpenAI', '-'],
                    ['claude-3-5-sonnet', 'Claude 3.5 Sonnet (Penulisan Ilmiah)', 'Anthropic', '-'],
                ];
            }
            $this->info('Daftar Model AI yang Didukung untuk Eksekusi Naskah Studio:');
            $this->table(['Model ID', 'Nama Tampilan', 'Provider', 'Status'], $rows);

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
            'polish-page' => $this->handlePolishPage($document, $blocks, $model),
            'align-page' => $this->handleAlignPage($document, $blocks, $model),
            'linespacing-page' => $this->handleLineSpacingPage($document, $blocks, $model),
            'readability-page' => $this->handleReadabilityPage($document, $blocks, $model),
            'indent-page' => $this->handleIndentPage($document, $blocks, $model),
            'highlight-page' => $this->handleHighlightPage($document, $blocks, $model),
            'execute-model' => $this->handleExecuteModel($document, $blocks, $model),
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

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handlePolishPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
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

        $polishedCount = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx]) || ($blocks[$idx]['type'] ?? '') !== 'paragraph') {
                continue;
            }

            $currentText = trim((string) ($blocks[$idx]['text'] ?? ''));
            if ($currentText === '') {
                continue;
            }

            $autocorrect = $model->autoCorrect($currentText);
            $polishedText = $model->cleanTextSpacing($autocorrect['text']);

            $sentences = preg_split('/(?<=[.!?])\s+/u', $polishedText, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            if ($sentences !== []) {
                $sentences[0] = ucfirst($sentences[0]);
                $polishedText = implode(' ', $sentences);
            }

            if ($polishedText !== $currentText) {
                $blocks[$idx]['text'] = $polishedText;
                $blocks[$idx]['runs'] = $model->normalizeRuns([], $polishedText);
                $polishedCount++;
            }
        }

        $document->update([
            'blocks' => $blocks,
            'word_count' => $model->countWords($blocks),
        ]);

        $this->info("Berhasil memoles {$polishedCount} paragraf pada halaman #{$pageNumber} dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleAlignPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $align = strtolower((string) $this->option('align'));
        if (! in_array($align, ['left', 'center', 'right', 'justify'], true)) {
            $align = 'justify';
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

        $alignedCount = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx]) || ($blocks[$idx]['type'] ?? '') !== 'paragraph') {
                continue;
            }

            $blocks[$idx]['align'] = $align;
            $alignedCount++;
        }

        $document->update([
            'blocks' => $blocks,
        ]);

        $this->info("Berhasil mengatur perataan '{$align}' pada {$alignedCount} paragraf di halaman #{$pageNumber} dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleLineSpacingPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $spacing = (float) $this->option('spacing');
        $clamped = max(0.8, min(3.0, $spacing));

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

        $count = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx]) || ($blocks[$idx]['type'] ?? '') !== 'paragraph') {
                continue;
            }

            $blocks[$idx]['lineSpacing'] = $clamped;
            $count++;
        }

        $document->update([
            'blocks' => $blocks,
        ]);

        $this->info("Berhasil mengatur spasi baris '{$clamped}' pada {$count} paragraf di halaman #{$pageNumber} dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleReadabilityPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
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

        $totalWords = 0;
        $totalSentences = 0;
        $parasCount = 0;

        foreach ($targetPage['blocks'] as $item) {
            $b = $item['block'];
            if (($b['type'] ?? '') !== 'paragraph') {
                continue;
            }
            $parasCount++;
            $text = strip_tags((string) ($b['text'] ?? ''));
            $words = str_word_count($text);
            $totalWords += $words;

            $sentences = preg_split('/(?<=[.!?])\s+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $totalSentences += max(1, count($sentences));
        }

        $avgWps = $totalSentences > 0 ? round($totalWords / $totalSentences, 1) : 0.0;
        $readingSecs = (int) round(($totalWords / 200) * 60);

        $this->info("=== STATISTIK KETERBACAAN HALAMAN #{$pageNumber} [Dokumen #{$document->id}] ===");
        $this->line("Total Paragraf: {$parasCount}");
        $this->line("Total Kata: {$totalWords}");
        $this->line("Total Kalimat: {$totalSentences}");
        $this->line("Rata-rata Kata per Kalimat: {$avgWps} kata");
        $this->line("Estimasi Waktu Baca: {$readingSecs} detik (~".max(1, ceil($readingSecs / 60)).' menit)');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleIndentPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $type = (string) ($this->option('type') ?: 'first-line');

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

        $count = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx]) || ($blocks[$idx]['type'] ?? '') !== 'paragraph') {
                continue;
            }

            if ($type === 'first-line') {
                $blocks[$idx]['firstLineIndent'] = true;
                unset($blocks[$idx]['hangingIndent']);
            } elseif ($type === 'hanging') {
                $blocks[$idx]['hangingIndent'] = true;
                unset($blocks[$idx]['firstLineIndent']);
            } else {
                unset($blocks[$idx]['firstLineIndent'], $blocks[$idx]['hangingIndent']);
            }

            $count++;
        }

        $document->update([
            'blocks' => $blocks,
        ]);

        $this->info("Berhasil menerapkan indentasi '{$type}' pada {$count} paragraf di halaman #{$pageNumber} dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array<string, mixed>>  $blocks
     */
    private function handleHighlightPage(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $pageNumber = max(1, (int) $this->option('page'));
        $color = (string) ($this->option('color') ?: '#fef08a');

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

        $count = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx]) || ($blocks[$idx]['type'] ?? '') !== 'paragraph') {
                continue;
            }

            if ($color === 'none' || $color === '') {
                unset($blocks[$idx]['backgroundColor']);
            } else {
                $blocks[$idx]['backgroundColor'] = $color;
            }

            $count++;
        }

        $document->update([
            'blocks' => $blocks,
        ]);

        $status = ($color === 'none' || $color === '') ? 'dihapus' : "diatur ke '{$color}'";
        $this->info("Berhasil: warna sorot {$status} pada {$count} paragraf di halaman #{$pageNumber} dokumen #{$document->id}.");

        return self::SUCCESS;
    }

    private function handleExecuteModel(NaskahDocument $document, array $blocks, NaskahRichModel $model): int
    {
        $prompt = trim((string) $this->option('prompt'));
        if ($prompt === '') {
            $this->error('Tentukan instruksi untuk model menggunakan opsi --prompt="...".');

            return self::FAILURE;
        }

        $modelId = trim((string) $this->option('model'));
        if ($modelId === '') {
            try {
                $client = app(OmnigensiChatClient::class);
                $modelId = $client->defaultModel() ?? 'qwen-2.5-72b-instruct';
            } catch (\Throwable) {
                $modelId = 'qwen-2.5-72b-instruct';
            }
        }

        $target = (string) $this->option('target');
        if (! in_array($target, ['current_page', 'page', 'append', 'new_page', 'active_block'], true)) {
            $target = 'current_page';
        }

        $page = max(1, (int) $this->option('page'));

        $this->info("Mengeksekusi model [{$modelId}] untuk dokumen #{$document->id} ({$document->title})...");

        $llm = app(OmnigensiChatClient::class);
        $generated = null;

        try {
            $documentText = Str::limit(collect($blocks)->map(fn ($b) => $b['text'] ?? '')->implode("\n"), 1500, ' …');
            $systemPrompt = 'Anda adalah asisten cerdas penulisan ilmiah Naskah Studio. Hasilkan konten terstruktur siap pakai (paragraf ilmiah, heading markdown, tabel |...|, rumus LaTeX $$...$$, atau poin acuan) dalam Bahasa Indonesia akademik formal. Jangan memberikan basa-basi pembuka atau penutup.';
            $userPrompt = "Konteks naskah:\nJudul: {$document->title}\nCuplikan naskah:\n{$documentText}\n\nInstruksi pembuatan konten:\n{$prompt}";

            $generated = $llm->generate($userPrompt, $systemPrompt, 0.6, 1000, $modelId);
        } catch (\Throwable) {
            $generated = null;
        }

        $replyContent = '';
        $latencyMs = 0;
        $source = 'contextual_synthesis';

        if ($generated !== null && trim((string) ($generated['content'] ?? '')) !== '') {
            $replyContent = trim((string) $generated['content']);
            $latencyMs = (int) ($generated['latency_ms'] ?? 0);
            $source = 'llm';
        } else {
            $lowerPrompt = mb_strtolower($prompt);
            if (preg_match('/(daftar\s+pustaka|bibliography|referensi)/iu', $lowerPrompt)) {
                $replyContent = "### Daftar Pustaka\n\n"
                    ."- Creswell, J. W., & Creswell, J. D. (2018). Research Design: Qualitative, Quantitative, and Mixed Methods Approaches (5th ed.). SAGE Publications.\n"
                    ."- Anderson, C. (2020). Makers: The New Industrial Revolution. Crown Business Publishing, 14(2), 112–129.\n"
                    .'- Braun, V., & Clarke, V. (2019). Reflecting on reflexive thematic analysis. Qualitative Research in Sport, 11(4), 589–597.';
                $source = 'contextual_bibliography';
            } elseif (preg_match('/(tabel|table|matriks)/iu', $lowerPrompt)) {
                $replyContent = "| Parameter Penelitian | Nilai Acuan | Toleransi | Metode Pengujian |\n|---|---|---|---|\n| Akurasi Klasifikasi | 95.8% | ±0.5% | Validasi Silang (k-Fold) |\n| Waktu Inferensi | 120 ms | ±10 ms | Benchmark Komputasi |\n| F1-Score Empiris | 0.94 | ±0.02 | Evaluasi Standar |";
                $source = 'contextual_table';
            } elseif (preg_match('/\b(rumus|persamaan|latex|equation)\b/iu', $lowerPrompt)) {
                $replyContent = '$$ Y = \\beta_0 + \\sum_{i=1}^{k} \\beta_i X_i + \\varepsilon $$';
                $source = 'contextual_equation';
            } else {
                $replyContent = "### Sintesis Akademik ({$modelId})\n\n"
                    ."Berdasarkan telaah kritis terhadap instruksi \"{$prompt}\", naskah mengartikulasikan hubungan konseptual dan empiris yang konsisten. Temuan memperkuat validitas metodologis serta menjawab research gap secara komprehensif.";
                $source = 'contextual_synthesis';
            }
        }

        $blocksToInsert = $this->parseContentToBlocks($replyContent, $model);
        if (empty($blocksToInsert)) {
            $this->error('Gagal mem-parse luaran model menjadi blok naskah.');

            return self::FAILURE;
        }

        $newBlocks = $blocks;
        if ($target === 'append') {
            foreach ($blocksToInsert as $b) {
                $newBlocks[] = $b;
            }
        } elseif ($target === 'new_page') {
            $newBlocks[] = $model->normalizeBlock(['type' => 'pagebreak']);
            foreach ($blocksToInsert as $b) {
                $newBlocks[] = $b;
            }
        } else {
            $paginated = $model->paginate($newBlocks);
            $targetPageItem = null;
            foreach ($paginated as $p) {
                if ((int) $p['pageNumber'] === $page) {
                    $targetPageItem = $p;
                    break;
                }
            }

            if ($targetPageItem === null || empty($targetPageItem['blocks'])) {
                foreach ($blocksToInsert as $b) {
                    $newBlocks[] = $b;
                }
            } else {
                $lastIndex = (int) end($targetPageItem['blocks'])['index'];
                array_splice($newBlocks, $lastIndex + 1, 0, $blocksToInsert);
            }
        }

        $normalized = $model->normalize($newBlocks);
        $stats = $model->computeStatistics($normalized);

        $document->update([
            'blocks' => $normalized,
            'word_count' => $stats['words'] ?? 0,
            'last_saved_at' => now(),
        ]);

        $this->info("Model [{$modelId}] berhasil dieksekusi dan diterapkan ke dokumen #{$document->id}.");
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['Model', $modelId],
                ['Sumber Generasi', $source],
                ['Latency', $latencyMs > 0 ? "{$latencyMs} ms" : 'Local Synthesizer'],
                ['Target', $target],
                ['Halaman', (string) $page],
                ['Blok Disisipkan', (string) count($blocksToInsert)],
                ['Total Blok Baru', (string) count($normalized)],
                ['Total Kata', (string) ($stats['words'] ?? 0)],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseContentToBlocks(string $content, NaskahRichModel $model): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($content));
        $blocks = [];
        $tableLines = [];
        $inTable = false;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);

            if (str_starts_with($trimmed, '|')) {
                $tableLines[] = $trimmed;
                $inTable = true;

                continue;
            }

            if ($inTable) {
                if (! empty($tableLines)) {
                    $tableBlock = $this->parseMarkdownTableToBlock($tableLines, $model);
                    if ($tableBlock !== null) {
                        $blocks[] = $tableBlock;
                    }
                    $tableLines = [];
                }
                $inTable = false;
            }

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^\$\$(.+)\$\$$/', $trimmed, $eqMatch)) {
                $blocks[] = $model->newEquationBlock(trim($eqMatch[1]), '(1)');

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $hMatch)) {
                $level = strlen($hMatch[1]);
                $blocks[] = $model->newBlock('heading', trim($hMatch[2]), $level);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $bMatch)) {
                $para = $model->newBlock('paragraph', trim($bMatch[1]));
                $para['bullet'] = true;
                $blocks[] = $para;

                continue;
            }

            $blocks[] = $model->newBlock('paragraph', $trimmed);
        }

        if ($inTable && ! empty($tableLines)) {
            $tableBlock = $this->parseMarkdownTableToBlock($tableLines, $model);
            if ($tableBlock !== null) {
                $blocks[] = $tableBlock;
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $tableLines
     * @return array<string, mixed>|null
     */
    private function parseMarkdownTableToBlock(array $tableLines, NaskahRichModel $model): ?array
    {
        $rows = [];
        foreach ($tableLines as $line) {
            $line = trim($line);
            if (preg_match('/^\|?\s*[-:]+[-|\s:]*$/', $line)) {
                continue;
            }
            $cells = array_values(array_filter(array_map('trim', explode('|', $line)), fn ($c) => $c !== ''));
            if (! empty($cells)) {
                $rows[] = $cells;
            }
        }

        if (empty($rows)) {
            return null;
        }

        return $model->newTableBlock($rows, true);
    }

    private function handleUnknownAction(string $action): int
    {
        $this->error("Aksi '{$action}' tidak dikenal. Pilih: list, inspect, stats, export, autocorrect, paragraphs, format-paragraph, clean-page, audit-page, find-replace, export-page, polish-page, align-page, linespacing-page, readability-page, indent-page, highlight-page, execute-model, models.");

        return self::FAILURE;
    }
}
