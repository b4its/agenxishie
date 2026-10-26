<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Publication\NaskahDocxExporter;
use App\Domain\Revision\Enums\RevisionStatus;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\ProjectRevision;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Agents\Concerns\ResolvesStageRecord;
use App\Omnigensi\Contracts\XishieAgentContract;
use App\Omnigensi\Data\AgentExecutionResultData;
use Illuminate\Support\Str;

/**
 * PeerCriticAgent: menganalisis catatan revisi dosen/reviewer, memecahnya
 * menjadi action items yang terpetakan ke sub-bab, menghasilkan side-by-side
 * diff, dan menyusun tabel Response to Reviewers.
 */
final class PeerCriticAgent implements XishieAgentContract
{
    use ResolvesStageRecord;

    public function __construct(
        private readonly OmnigensiChatClient $llm,
    ) {}

    public function name(): string
    {
        return 'Peer-Critic';
    }

    public function execute(XishieProject $project, ProjectStage $stage, array $context = []): AgentExecutionResultData
    {
        return $this->run($project, $this->stageRecord($project, $stage), $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function run(XishieProject $project, ProjectStageRecord $stage, array $context = []): AgentExecutionResultData
    {
        $startedAt = microtime(true);

        $rawFeedback = (string) ($context['raw_feedback'] ?? '');
        $reviewerRole = (string) ($context['reviewer_role'] ?? 'pembimbing_1');

        if ($rawFeedback === '') {
            return AgentExecutionResultData::skipped($this->name(), 'Tidak ada catatan revisi pada context.');
        }

        $revision = $this->analyse($project, $stage, $rawFeedback, $reviewerRole);

        return AgentExecutionResultData::success(
            agentName: $this->name(),
            outputPayload: [
                'revision_id' => $revision->id,
                'action_items' => $revision->action_items,
                'diff_payload' => $revision->diff_payload,
                'response_matrix_doc' => $revision->response_matrix_doc,
                'resolution_percentage' => $revision->resolutionPercentage(),
            ],
            ttftMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            metadata: [
                'message' => sprintf('Memecah catatan revisi menjadi %d butir tugas.', count((array) $revision->action_items)),
            ],
        );
    }

    /**
     * Pecah catatan mentah menjadi action items terstruktur.
     */
    public function analyse(
        XishieProject $project,
        ProjectStageRecord $stage,
        string $rawFeedback,
        string $reviewerRole = 'pembimbing_1',
    ): ProjectRevision {
        $sections = (array) ($stage->sections_content ?? []);
        $sectionKeys = array_map(static fn (array $section): string => (string) ($section['key'] ?? ''), $sections);

        $items = [];
        $diffs = [];

        // Analisis via LLM Omnigensi (action items + usulan perbaikan). Bila
        // backend tidak tersedia, pakai pemecahan pola di bawah (offline).
        $llm = $this->analyseWithLlm($project, $rawFeedback, $sections, $sectionKeys);

        if ($llm !== null) {
            $items = $llm['items'];
            $diffs = $llm['diffs'];
        } else {
            foreach ($this->splitFeedback($rawFeedback) as $index => $note) {
                $targetKey = $this->mapToSection($note, $sectionKeys, $index);

                $items[] = [
                    'id' => $index + 1,
                    'task' => $note,
                    'section_key' => $targetKey,
                    'status' => RevisionStatus::PENDING->value,
                    'paragraph_ref' => $targetKey.'#p'.($index + 1),
                ];

                $before = $this->sectionContent($sections, $targetKey);
                $diffs[] = [
                    'section_key' => $targetKey,
                    'before' => $before,
                    'after' => $this->proposeRevision($before, $note),
                    'note' => $note,
                ];
            }
        }

        $revision = ProjectRevision::create([
            'project_id' => $project->id,
            'stage_id' => $stage->id,
            'reviewer_role' => $reviewerRole,
            'raw_feedback' => $rawFeedback,
            'action_items' => $items,
            'diff_payload' => $diffs,
            'status' => RevisionStatus::PENDING,
        ]);

        $revision->forceFill([
            'response_matrix_doc' => $this->renderResponseMatrix($revision),
        ])->save();

        return $revision;
    }

    /**
     * @return array<int, string>
     */
    /**
     * Analisis catatan revisi via LLM Omnigensi menjadi action items + usulan
     * perbaikan (diff). Kembalikan null bila tidak tersedia (fallback offline).
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @param  array<int, string>  $sectionKeys
     * @return array{items: array<int, array<string, mixed>>, diffs: array<int, array<string, mixed>>}|null
     */
    private function analyseWithLlm(XishieProject $project, string $rawFeedback, array $sections, array $sectionKeys): ?array
    {
        if (! config('omnigensi.agents_enabled', true)) {
            return null;
        }

        $system = 'Anda Peer-Critic: analis revisi akademik. Pecah catatan reviewer menjadi '
            .'butir tugas terstruktur dan usulkan perbaikan konkret. Kembalikan JSON saja.';

        $prompt = sprintf(
            "Sub-bab tersedia (key): %s\n\nCatatan reviewer:\n%s\n\n".
            'Balas JSON array objek: {"task":string,"section_key":salah satu key,"suggestion":string}. '.
            'Maksimal 10 butir.',
            implode(', ', array_filter($sectionKeys)),
            $rawFeedback,
        );

        $result = $this->llm->generate($prompt, $system, 0.3, 1200);

        if ($result === null) {
            return null;
        }

        $decoded = $this->extractJsonArray((string) $result['content']);

        if ($decoded === []) {
            return null;
        }

        $items = [];
        $diffs = [];

        foreach ($decoded as $index => $item) {
            if (! is_array($item) || ! isset($item['task'])) {
                continue;
            }

            $targetKey = $this->resolveSectionKey((string) ($item['section_key'] ?? ''), $sectionKeys, $index);
            $task = (string) $item['task'];
            $suggestion = (string) ($item['suggestion'] ?? '');
            $before = $this->sectionContent($sections, $targetKey);

            $items[] = [
                'id' => $index + 1,
                'task' => $task,
                'section_key' => $targetKey,
                'status' => RevisionStatus::PENDING->value,
                'paragraph_ref' => $targetKey.'#p'.($index + 1),
                'suggestion' => $suggestion,
            ];

            $diffs[] = [
                'section_key' => $targetKey,
                'before' => $before,
                'after' => $suggestion !== '' ? $suggestion : $this->proposeRevision($before, $task),
                'note' => $task,
            ];
        }

        return $items === [] ? null : ['items' => $items, 'diffs' => $diffs];
    }

    /**
     * Pilih section key valid dari usulan LLM (fallback ke mapToSection).
     *
     * @param  array<int, string>  $sectionKeys
     */
    private function resolveSectionKey(string $candidate, array $sectionKeys, int $index): string
    {
        if ($candidate !== '' && in_array($candidate, $sectionKeys, true)) {
            return $candidate;
        }

        return $sectionKeys[$index % max(count($sectionKeys), 1)] ?? 'general';
    }

    /**
     * Ekstrak array JSON dari keluaran LLM.
     *
     * @return array<int, mixed>
     */
    private function extractJsonArray(string $content): array
    {
        if (preg_match('/\[.*\]/s', $content, $m) !== 1) {
            return [];
        }

        $decoded = json_decode($m[0], true);

        return is_array($decoded) ? $decoded : [];
    }

    private function splitFeedback(string $rawFeedback): array
    {
        $lines = preg_split('/\r\n|\r|\n/', $rawFeedback) ?: [];

        $items = [];

        foreach ($lines as $line) {
            $cleaned = trim(preg_replace('/^(\d+[\.\)]|[-*•])\s*/u', '', trim($line)) ?? '');

            if ($cleaned === '') {
                continue;
            }

            $items[] = $cleaned;
        }

        if ($items === []) {
            $items[] = trim($rawFeedback);
        }

        return $items;
    }

    /**
     * @param  array<int, string>  $sectionKeys
     */
    private function mapToSection(string $note, array $sectionKeys, int $index): string
    {
        $lower = Str::lower($note);

        foreach ($sectionKeys as $key) {
            if ($key !== '' && str_contains($lower, Str::lower($key))) {
                return $key;
            }
        }

        $chapterHints = [
            'abstrak' => 'abstract',
            'abstract' => 'abstract',
            'bab 1' => 'chapter1',
            'pendahuluan' => 'chapter1',
            'latar belakang' => 'chapter1',
            'bab 2' => 'chapter2',
            'tinjauan' => 'chapter2',
            'bab 3' => 'chapter3',
            'metodologi' => 'methodology',
            'bab 4' => 'chapter4',
            'hasil' => 'chapter4',
            'bab 5' => 'chapter5',
            'kesimpulan' => 'chapter5',
        ];

        foreach ($chapterHints as $hint => $key) {
            if (str_contains($lower, $hint)) {
                return $key;
            }
        }

        return $sectionKeys[$index % max(count($sectionKeys), 1)] ?? 'general';
    }

    /**
     * @param  array<int, array<string, mixed>>  $sections
     */
    private function sectionContent(array $sections, string $key): string
    {
        foreach ($sections as $section) {
            if (($section['key'] ?? null) === $key) {
                return (string) ($section['content'] ?? '');
            }
        }

        return '';
    }

    private function proposeRevision(string $before, string $note): string
    {
        $prefix = $before === '' ? '' : $before.' ';

        return trim($prefix.'[Revisi] '.$note);
    }

    /**
     * Susun dokumen matriks Response to Reviewers.
     */
    public function renderResponseMatrix(ProjectRevision $revision): string
    {
        $rows = ['| No | Catatan Reviewer | Bagian | Status | Rencana Perbaikan |', '| --- | --- | --- | --- | --- |'];

        foreach ((array) ($revision->action_items ?? []) as $item) {
            $rows[] = sprintf(
                '| %d | %s | %s | %s | %s |',
                (int) ($item['id'] ?? 0),
                str_replace('|', '\\|', (string) ($item['task'] ?? '')),
                (string) ($item['section_key'] ?? '-'),
                (string) ($item['status'] ?? 'pending'),
                'Perbaikan diterapkan pada bagian terkait.',
            );
        }

        return implode("\n", $rows);
    }

    /**
     * Susun dokumen matriks Response to Reviewers sebagai CSV.
     */
    public function renderResponseMatrixCsv(ProjectRevision $revision): string
    {
        $handle = fopen('php://temp', 'r+');
        if ($handle === false) {
            return '';
        }

        fputcsv($handle, ['No', 'Catatan Reviewer', 'Bagian', 'Status', 'Rencana Perbaikan']);

        foreach ((array) ($revision->action_items ?? []) as $item) {
            fputcsv($handle, [
                (int) ($item['id'] ?? 0),
                (string) ($item['task'] ?? ''),
                (string) ($item['section_key'] ?? '-'),
                (string) ($item['status'] ?? 'pending'),
                'Perbaikan diterapkan pada bagian terkait.',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv !== false ? $csv : '';
    }

    /**
     * Susun dokumen matriks Response to Reviewers sebagai JSON.
     */
    public function renderResponseMatrixJson(ProjectRevision $revision): string
    {
        $items = [];
        foreach ((array) ($revision->action_items ?? []) as $item) {
            $items[] = [
                'id' => (int) ($item['id'] ?? 0),
                'task' => (string) ($item['task'] ?? ''),
                'section_key' => (string) ($item['section_key'] ?? '-'),
                'status' => (string) ($item['status'] ?? 'pending'),
                'action_plan' => 'Perbaikan diterapkan pada bagian terkait.',
            ];
        }

        $payload = [
            'revision_id' => $revision->id,
            'reviewer_role' => $revision->reviewer_role,
            'status' => $revision->status->value,
            'resolution_percentage' => $revision->resolutionPercentage(),
            'total_items' => count($items),
            'action_items' => $items,
            'exported_at' => now()->toIso8601String(),
        ];

        return (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /**
     * Susun dokumen matriks Response to Reviewers sebagai berkas DOCX terstruktur.
     */
    public function renderResponseMatrixDocx(ProjectRevision $revision): string
    {
        $projectTitle = $revision->project?->title ?? 'Naskah Ilmiah';
        $blocks = [
            [
                'type' => 'heading',
                'level' => 1,
                'text' => 'Response to Reviewers Matrix',
            ],
            [
                'type' => 'paragraph',
                'text' => "Proyek: {$projectTitle} | Reviewer: {$revision->reviewer_role} | Status: {$revision->status->label()} ({$revision->resolutionPercentage()}% Selesai)",
            ],
        ];

        $tableRows = [
            [
                ['text' => 'No'],
                ['text' => 'Catatan Reviewer'],
                ['text' => 'Bagian / Sub-bab'],
                ['text' => 'Status'],
                ['text' => 'Respon & Tindakan Penulis'],
            ],
        ];

        foreach ((array) ($revision->action_items ?? []) as $item) {
            $tableRows[] = [
                ['text' => (string) ($item['id'] ?? 1)],
                ['text' => (string) ($item['task'] ?? '')],
                ['text' => (string) ($item['section_key'] ?? '-')],
                ['text' => strtoupper((string) ($item['status'] ?? 'pending'))],
                ['text' => 'Perbaikan telah diintegrasikan pada sub-bab terkait sesuai saran reviewer.'],
            ];
        }

        $blocks[] = [
            'type' => 'table',
            'table' => [
                'rows' => $tableRows,
                'header' => true,
            ],
        ];

        return app(NaskahDocxExporter::class)->build($blocks, 'Response to Reviewers - '.$revision->reviewer_role);
    }

    /**
     * Susun dokumen matriks Response to Reviewers sebagai berkas HTML mandiri yang rapi.
     */
    public function renderResponseMatrixHtml(ProjectRevision $revision): string
    {
        $projectTitle = htmlspecialchars($revision->project?->title ?? 'Naskah Ilmiah', ENT_QUOTES, 'UTF-8');
        $reviewer = htmlspecialchars($revision->reviewer_role, ENT_QUOTES, 'UTF-8');
        $status = htmlspecialchars($revision->status->label(), ENT_QUOTES, 'UTF-8');
        $percentage = $revision->resolutionPercentage();

        $html = "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n";
        $html .= "<meta charset=\"UTF-8\">\n<meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">\n";
        $html .= "<title>Response to Reviewers - {$projectTitle}</title>\n";
        $html .= "<style>\n";
        $html .= "body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; padding: 2rem; color: #1e293b; line-height: 1.5; }\n";
        $html .= "h1 { font-size: 1.5rem; color: #0f172a; margin-bottom: 0.5rem; }\n";
        $html .= "p.meta { font-size: 0.875rem; color: #64748b; margin-bottom: 1.5rem; }\n";
        $html .= "table { width: 100%; border-collapse: collapse; margin-top: 1rem; font-size: 0.875rem; }\n";
        $html .= "th, td { border: 1px solid #cbd5e1; padding: 0.6rem 0.8rem; text-align: left; vertical-align: top; }\n";
        $html .= "th { background-color: #f1f5f9; font-weight: 600; color: #334155; }\n";
        $html .= "tr:nth-child(even) { background-color: #f8fafc; }\n";
        $html .= ".status-resolved { color: #16a34a; font-weight: 600; }\n";
        $html .= ".status-pending { color: #d97706; font-weight: 600; }\n";
        $html .= "</style>\n</head>\n<body>\n";
        $html .= "<h1>Response to Reviewers Matrix</h1>\n";
        $html .= "<p class=\"meta\">Proyek: <strong>{$projectTitle}</strong> | Reviewer: <strong>{$reviewer}</strong> | Status: <strong>{$status} ({$percentage}% Selesai)</strong></p>\n";
        $html .= "<table>\n<thead>\n<tr>\n";
        $html .= "<th>No</th><th>Catatan Reviewer</th><th>Bagian / Sub-bab</th><th>Status</th><th>Respon & Tindakan Penulis</th>\n";
        $html .= "</tr>\n</thead>\n<tbody>\n";

        foreach ((array) ($revision->action_items ?? []) as $item) {
            $id = (int) ($item['id'] ?? 1);
            $task = htmlspecialchars((string) ($item['task'] ?? ''), ENT_QUOTES, 'UTF-8');
            $sec = htmlspecialchars((string) ($item['section_key'] ?? '-'), ENT_QUOTES, 'UTF-8');
            $st = (string) ($item['status'] ?? 'pending');
            $stClass = $st === 'resolved' ? 'status-resolved' : 'status-pending';
            $stLabel = strtoupper($st);

            $html .= "<tr>\n";
            $html .= "<td>{$id}</td><td>{$task}</td><td>{$sec}</td><td class=\"{$stClass}\">{$stLabel}</td><td>Perbaikan telah diintegrasikan pada sub-bab terkait sesuai saran reviewer.</td>\n";
            $html .= "</tr>\n";
        }

        $html .= "</tbody>\n</table>\n</body>\n</html>";

        return $html;
    }

    /**
     * Susun dokumen matriks Response to Reviewers sebagai berkas Rich Text Format (.rtf).
     */
    public function renderResponseMatrixRtf(ProjectRevision $revision): string
    {
        $rtfEsc = static function (string $text): string {
            $out = '';
            $len = mb_strlen($text);
            for ($i = 0; $i < $len; $i++) {
                $char = mb_substr($text, $i, 1);
                $ord = mb_ord($char);
                if ($ord < 128) {
                    if ($char === '\\' || $char === '{' || $char === '}') {
                        $out .= '\\'.$char;
                    } elseif ($char === "\n") {
                        $out .= '\\par ';
                    } else {
                        $out .= $char;
                    }
                } else {
                    $out .= '\\u'.$ord.'?';
                }
            }

            return $out;
        };

        $projectTitle = (string) ($revision->project?->title ?? 'Naskah Ilmiah');
        $reviewer = (string) $revision->reviewer_role;
        $status = (string) $revision->status->label();
        $percentage = $revision->resolutionPercentage();

        $out = "{\\rtf1\\ansi\\deff0\n{\\fonttbl{\\f0\\froman\\fcharset0 Times New Roman;}{\\f1\\fswiss\\fcharset0 Arial;}}\n";
        $out .= "{\\colortbl ;\\red30\\green64\\blue175;\\red22\\green163\\blue74;\\red217\\green119\\blue6;}\n";

        // Judul Utama
        $out .= '\\pard\\qc\\b\\fs32\\cf1 Response to Reviewers Matrix\\b0\\par\\par\n';
        $out .= '\\pard\\ql\\fs22\\b Proyek: \\b0 '.$rtfEsc($projectTitle)."\\par\n";
        $out .= '\\pard\\ql\\fs22\\b Reviewer: \\b0 '.$rtfEsc($reviewer)."\\par\n";
        $out .= '\\pard\\ql\\fs22\\b Status Revisi: \\b0 '.$rtfEsc($status)." ({$percentage}% Selesai)\\par\\par\n";

        // Poin-poin perbaikan
        $out .= "\\pard\\ql\\b\\fs24 Poin Tanggapan dan Perbaikan Naskah:\\b0\\par\\par\n";

        foreach ((array) ($revision->action_items ?? []) as $item) {
            $id = (int) ($item['id'] ?? 1);
            $task = (string) ($item['task'] ?? '');
            $sec = (string) ($item['section_key'] ?? '-');
            $st = (string) ($item['status'] ?? 'pending');
            $cfColor = $st === 'resolved' ? '\\cf2' : '\\cf3';

            $out .= "\\pard\\ql\\b [Poin #{$id}] Sub-bab: \\b0 ".$rtfEsc($sec)."\\par\n";
            $out .= '\\pard\\ql\\i Catatan Reviewer: \\i0 '.$rtfEsc($task)."\\par\n";
            $out .= "\\pard\\ql\\b Status: {$cfColor}".strtoupper($st)."\\cf0\\b0\\par\n";
            $out .= "\\pard\\ql\\b Tanggapan Penulis: \\b0 Perbaikan telah diintegrasikan pada sub-bab terkait sesuai saran reviewer.\\par\\par\n";
        }

        $out .= "}\n";

        return $out;
    }
}
