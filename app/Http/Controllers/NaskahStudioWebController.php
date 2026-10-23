<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Models\NaskahDocument;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NaskahStudioWebController: Controller Web Publik untuk Naskah Studio Standalone.
 *
 * Mengakomodasi akses penuh ke word processor di luar admin panel (`/studio`),
 * lengkap dengan unduhan ekspor multi-format langsung (ODT, DOCX, LaTeX, RTF, MD, TXT).
 */
class NaskahStudioWebController extends Controller
{
    public function __construct(
        private readonly NaskahRichModel $model,
        private readonly NaskahDocxExporter $docxExporter,
        private readonly NaskahOdtExporter $odtExporter,
    ) {}

    /**
     * Tampilkan antarmuka standalone Naskah Studio.
     */
    public function index(?int $document = null): View
    {
        return view('studio.index', [
            'documentId' => $document,
        ]);
    }

    /**
     * Ekspor dokumen naskah secara langsung via HTTP stream.
     */
    public function exportDirect(int $document, string $format): StreamedResponse|Response
    {
        $doc = NaskahDocument::findOrFail($document);
        $blocks = $this->model->normalize((array) ($doc->blocks ?? []));
        $title = $doc->title ?: 'naskah';
        $slug = Str::slug($title) ?: 'naskah';
        $pageSetup = (array) ($doc->page_setup ?? []);
        $styleMeta = (array) ($doc->style_meta ?? []);

        return match (strtolower($format)) {
            'docx' => response()->streamDownload(
                fn () => print ($this->docxExporter->export($blocks, $title, $pageSetup, $styleMeta)),
                "{$slug}.docx",
                ['Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document']
            ),
            'odt' => response()->streamDownload(
                fn () => print ($this->odtExporter->export($blocks, $title, $pageSetup, $styleMeta)),
                "{$slug}.odt",
                ['Content-Type' => 'application/vnd.oasis.opendocument.text']
            ),
            'rtf' => response()->streamDownload(
                fn () => print ($this->model->toRtf($blocks, $title)),
                "{$slug}.rtf",
                ['Content-Type' => 'application/rtf; charset=utf-8']
            ),
            'latex', 'tex' => response()->streamDownload(
                fn () => print ($this->model->toLatex($blocks, $title)),
                "{$slug}.tex",
                ['Content-Type' => 'application/x-latex; charset=utf-8']
            ),
            'md', 'markdown' => response()->streamDownload(
                fn () => print ($this->model->toMarkdown($blocks)),
                "{$slug}.md",
                ['Content-Type' => 'text/markdown; charset=utf-8']
            ),
            'html' => response()->streamDownload(
                fn () => print ($this->model->toHtml($blocks, $title)),
                "{$slug}.html",
                ['Content-Type' => 'text/html; charset=utf-8']
            ),
            default => response()->streamDownload(
                fn () => print ($this->model->toPlainText($blocks)),
                "{$slug}.txt",
                ['Content-Type' => 'text/plain; charset=utf-8']
            ),
        };
    }
}
