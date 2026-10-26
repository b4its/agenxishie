<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Http\Controllers\Controller;
use App\Models\NaskahDocument;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NaskahStudioApiController: antarmuka API / MCP server untuk agen AI
 * dan integrasi eksternal guna menginspeksi dan memanipulasi dokumen naskah secara programatik (Wordcraft MCP style).
 */
class NaskahStudioApiController extends Controller
{
    public function __construct(
        private readonly NaskahRichModel $model,
        private readonly NaskahDocxExporter $docx,
        private readonly NaskahOdtExporter $odt,
    ) {}

    public function index(): JsonResponse
    {
        $documents = NaskahDocument::query()->latest('updated_at')->limit(30)->get();

        return response()->json([
            'status' => 'success',
            'data' => $documents->map(fn (NaskahDocument $d): array => [
                'id' => $d->id,
                'title' => $d->title,
                'source_type' => $d->source_type,
                'word_count' => $d->word_count,
                'last_saved_at' => $d->last_saved_at?->toIso8601String(),
            ]),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $blocks = $this->model->normalize((array) $document->blocks);
        $stats = $this->model->computeStatistics($blocks);
        $outline = $this->model->outline($blocks);

        return response()->json([
            'status' => 'success',
            'data' => [
                'id' => $document->id,
                'title' => $document->title,
                'blocks' => $blocks,
                'outline' => $outline,
                'statistics' => $stats,
                'comments' => $document->commentsList(),
                'revisions' => $document->revisionsList(),
                'page_setup' => $document->page_setup,
                'style_meta' => $document->style_meta,
            ],
        ]);
    }

    public function autoCorrect(int $id): JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $blocks = $this->model->normalize((array) $document->blocks);
        $total = 0;

        foreach ($blocks as $i => $block) {
            $text = (string) ($block['text'] ?? '');
            if (trim($text) === '') {
                continue;
            }
            $res = $this->model->autoCorrect($text);
            if ($res['count'] > 0) {
                $total += $res['count'];
                $blocks[$i]['text'] = $res['text'];
                $blocks[$i]['runs'] = [];
            }
        }

        $document->update(['blocks' => $blocks]);

        return response()->json([
            'status' => 'success',
            'message' => "Auto-correct selesai: {$total} perbaikan diterapkan.",
            'corrections_count' => $total,
            'blocks_count' => count($blocks),
        ]);
    }

    public function export(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $format = strtolower((string) $request->query('format', 'docx'));
        $blocks = $this->model->normalize((array) $document->blocks);

        if ($format === 'docx') {
            $binary = $this->docx->build($blocks, $document->title);

            return response()->streamDownload(static function () use ($binary): void {
                echo $binary;
            }, "naskah-{$id}.docx", ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);
        }

        if ($format === 'odt') {
            $binary = $this->odt->build($blocks, $document->title);

            return response()->streamDownload(static function () use ($binary): void {
                echo $binary;
            }, "naskah-{$id}.odt", ['Content-Type' => 'application/vnd.oasis.opendocument.text']);
        }

        [$content, $ext, $mime] = match ($format) {
            'html' => [$this->model->toHtml($blocks, $document->title), 'html', 'text/html; charset=UTF-8'],
            'latex', 'tex' => [$this->model->toLatex($blocks, $document->title), 'tex', 'application/x-tex; charset=UTF-8'],
            'rtf' => [$this->model->toRtf($blocks, $document->title), 'rtf', 'application/rtf; charset=UTF-8'],
            'txt' => [$this->model->toPlainText($blocks), 'txt', 'text/plain; charset=UTF-8'],
            'json' => [(string) json_encode([
                'id' => $document->id,
                'title' => $document->title,
                'source_type' => $document->source_type,
                'word_count' => $document->word_count,
                'blocks' => $blocks,
                'page_setup' => $document->page_setup,
                'style_meta' => $document->style_meta,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), 'json', 'application/json; charset=UTF-8'],
            default => [$this->model->toMarkdown($blocks, $document->title), 'md', 'text/markdown; charset=UTF-8'],
        };

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, "naskah-{$id}.{$ext}", ['Content-Type' => $mime]);
    }

    public function exportPage(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $pageNumber = max(1, (int) $request->query('page', 1));
        $format = strtolower((string) $request->query('format', 'md'));
        $blocks = $this->model->normalize((array) $document->blocks);
        $paginated = $this->model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            return response()->json(['status' => 'error', 'message' => "Halaman #{$pageNumber} tidak ditemukan"], 404);
        }

        $pageBlocks = array_map(static fn (array $item): array => $item['block'], $targetPage['blocks']);

        [$content, $ext, $mime] = match ($format) {
            'html' => [$this->model->toHtml($pageBlocks, "{$document->title} - Hal {$pageNumber}"), 'html', 'text/html; charset=UTF-8'],
            'txt', 'text' => [$this->model->toPlainText($pageBlocks), 'txt', 'text/plain; charset=UTF-8'],
            default => [$this->model->toMarkdown($pageBlocks, "{$document->title} - Hal {$pageNumber}"), 'md', 'text/markdown; charset=UTF-8'],
        };

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, "naskah-{$id}-hal-{$pageNumber}.{$ext}", ['Content-Type' => $mime]);
    }

    public function auditPage(Request $request, int $id): JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $pageNumber = max(1, (int) $request->input('page', 1));
        $blocks = $this->model->normalize((array) $document->blocks);
        $paginated = $this->model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            return response()->json(['status' => 'error', 'message' => "Halaman #{$pageNumber} tidak ditemukan"], 404);
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

        return response()->json([
            'status' => 'success',
            'data' => [
                'page' => $pageNumber,
                'score' => $score,
                'grade' => $score >= 80 ? 'Sesuai' : 'Perlu Perbaikan',
                'paragraphs_count' => $parasCount,
                'words_count' => $totalWords,
                'issues' => $issues,
            ],
        ]);
    }

    public function cleanPage(Request $request, int $id): JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $pageNumber = max(1, (int) $request->input('page', 1));
        $blocks = $this->model->normalize((array) $document->blocks);
        $paginated = $this->model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            return response()->json(['status' => 'error', 'message' => "Halaman #{$pageNumber} tidak ditemukan"], 404);
        }

        $cleanedCount = 0;
        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx])) {
                continue;
            }
            $currentText = (string) ($blocks[$idx]['text'] ?? '');
            $newText = $this->model->cleanTextSpacing($currentText);
            if ($newText !== $currentText) {
                $blocks[$idx]['text'] = $newText;
                $blocks[$idx]['runs'] = $this->model->normalizeRuns($blocks[$idx]['runs'] ?? [], $newText);
                $cleanedCount++;
            }
        }

        $document->update([
            'blocks' => $blocks,
            'word_count' => $this->model->countWords($blocks),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil merapikan spasi pada {$cleanedCount} paragraf di halaman #{$pageNumber}.",
            'cleaned_count' => $cleanedCount,
            'page' => $pageNumber,
        ]);
    }

    public function formatParagraph(Request $request, int $id): JsonResponse
    {
        $document = NaskahDocument::query()->find($id);
        if ($document === null) {
            return response()->json(['status' => 'error', 'message' => 'Dokumen tidak ditemukan'], 404);
        }

        $pageNumber = max(1, (int) $request->input('page', 1));
        $preset = (string) $request->input('preset', 'academic_standard');
        $blocks = $this->model->normalize((array) $document->blocks);
        $paginated = $this->model->paginate($blocks);

        $targetPage = null;
        foreach ($paginated as $p) {
            if ((int) $p['pageNumber'] === $pageNumber) {
                $targetPage = $p;
                break;
            }
        }

        if ($targetPage === null) {
            return response()->json(['status' => 'error', 'message' => "Halaman #{$pageNumber} tidak ditemukan"], 404);
        }

        $presets = [
            'academic_standard' => ['align' => 'justify', 'lineSpacing' => 1.5, 'firstLineIndent' => true, 'spacingAfter' => 6],
            'journal_ieee' => ['align' => 'justify', 'lineSpacing' => 1.0, 'firstLineIndent' => true, 'spacingAfter' => 4],
            'thesis_dikti' => ['align' => 'justify', 'lineSpacing' => 2.0, 'firstLineIndent' => true, 'spacingAfter' => 6],
            'abstract_block' => ['align' => 'justify', 'lineSpacing' => 1.0, 'firstLineIndent' => false, 'spacingAfter' => 10],
            'block_quote' => ['align' => 'justify', 'lineSpacing' => 1.0, 'firstLineIndent' => false, 'indent' => 1, 'spacingAfter' => 8],
        ];

        $rule = $presets[$preset] ?? $presets['academic_standard'];
        $updatedCount = 0;

        foreach ($targetPage['blocks'] as $item) {
            $idx = $item['index'];
            if (! isset($blocks[$idx]) || ($blocks[$idx]['type'] ?? '') !== 'paragraph') {
                continue;
            }
            $blocks[$idx] = array_merge($blocks[$idx], $rule);
            $updatedCount++;
        }

        $document->update(['blocks' => $blocks]);

        return response()->json([
            'status' => 'success',
            'message' => "Berhasil menerapkan preset '{$preset}' pada {$updatedCount} paragraf di halaman #{$pageNumber}.",
            'preset' => $preset,
            'updated_count' => $updatedCount,
            'page' => $pageNumber,
        ]);
    }
}
