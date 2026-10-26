<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Publication\NaskahOdtExporter;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\NaskahDocument;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * NaskahStudioWebController: Controller Web Publik untuk Naskah Studio Standalone.
 *
 * Mengakomodasi akses penuh ke word processor di luar admin panel (`/studio`)
 * dan dashboard 18 UX KPIs (`/analytics`), bebas dari dependensi Filament.
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
     * Tampilkan Dashboard 18 UX KPIs & Product Telemetry (Standalone di Luar Admin Panel).
     */
    public function analytics(Request $request): View
    {
        $projectId = $request->query('project') ? (int) $request->query('project') : null;
        $project = $projectId ? XishieProject::find($projectId) : XishieProject::query()->latest()->first();

        $service = app(UxKpiTelemetryService::class);
        $summary = $project ? $service->dashboardSummary($project) : [
            'all' => [],
            'ux' => [],
            'heart' => [],
            'aarrr' => [],
        ];

        $allMetrics = $summary['all'] ?? [];
        $healthyCount = count(array_filter($allMetrics, static fn (array $m): bool => (bool) ($m['healthy'] ?? false)));
        $totalCount = count($allMetrics);
        $healthScore = $totalCount > 0 ? round(($healthyCount / $totalCount) * 100, 1) : 100.0;

        $projects = XishieProject::query()->orderByDesc('updated_at')->get(['id', 'title', 'document_type', 'control_mode']);
        $recentTelemetries = UxKpiTelemetry::query()->latest('recorded_at')->limit(12)->get();

        return view('studio.analytics', [
            'project' => $project,
            'projects' => $projects,
            'summary' => $summary,
            'healthyCount' => $healthyCount,
            'totalCount' => $totalCount,
            'healthScore' => $healthScore,
            'recentTelemetries' => $recentTelemetries,
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
            'json' => response()->streamDownload(
                fn () => print ((string) json_encode([
                    'id' => $doc->id,
                    'title' => $title,
                    'word_count' => $doc->word_count,
                    'page_setup' => $pageSetup,
                    'blocks' => $blocks,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)),
                "{$slug}.json",
                ['Content-Type' => 'application/json; charset=utf-8']
            ),
            default => response()->streamDownload(
                fn () => print ($this->model->toPlainText($blocks)),
                "{$slug}.txt",
                ['Content-Type' => 'text/plain; charset=utf-8']
            ),
        };
    }
}
