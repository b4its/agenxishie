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
            default => [$this->model->toMarkdown($blocks, $document->title), 'md', 'text/markdown; charset=UTF-8'],
        };

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, "naskah-{$id}.{$ext}", ['Content-Type' => $mime]);
    }
}
