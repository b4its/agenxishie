<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents;

use App\Domain\Revision\Enums\RevisionStatus;
use App\Models\ProjectRevision;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
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
    public function name(): string
    {
        return 'Peer-Critic';
    }

    public function execute(XishieProject $project, ProjectStageRecord $stage, array $context = []): AgentExecutionResultData
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
}
