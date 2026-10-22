<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents;

use App\Models\ProjectReference;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Contracts\XishieAgentContract;
use App\Omnigensi\Data\AgentExecutionResultData;
use App\Omnigensi\Memory\HybridVectorMatcherService;
use App\Omnigensi\Memory\SharedProjectMemory;

/**
 * ScholarScoutAgent: membangun korpus referensi proyek, mengekstrak matriks
 * literatur (metode/hasil/gap), dan memvalidasi kunci sitasi agar bebas
 * halusinasi (Strict Citation Binding).
 */
final class ScholarScoutAgent implements XishieAgentContract
{
    public function __construct(
        private readonly HybridVectorMatcherService $matcher,
        private readonly SharedProjectMemory $memory,
    ) {}

    public function name(): string
    {
        return 'Scholar-Scout';
    }

    public function execute(XishieProject $project, ProjectStageRecord $stage, array $context = []): AgentExecutionResultData
    {
        $startedAt = microtime(true);

        if (isset($context['candidate_references']) && is_array($context['candidate_references'])) {
            $this->ingestCandidates($project, $context['candidate_references']);
        }

        $focusQuery = (string) ($context['focus_query'] ?? $project->title);
        $relevant = $this->memory->relevantReferences($project, $focusQuery, 12);

        $ttft = max(1, (int) ((microtime(true) - $startedAt) * 1000));

        return AgentExecutionResultData::success(
            agentName: $this->name(),
            outputPayload: [
                'relevant_references' => $relevant,
                'matrix' => $this->literatureMatrix($project),
                'total_references' => $project->references()->count(),
            ],
            complianceScore: 0.0,
            citationsUsed: array_map(static fn (array $item): string => $item['citation_key'], $relevant),
            ttftMs: $ttft,
            executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            metadata: [
                'message' => sprintf('Menemukan %d referensi relevan.', count($relevant)),
            ],
        );
    }

    /**
     * Simpan kandidat referensi beserta embedding hasil ekstraksi.
     *
     * @param  array<int, array<string, mixed>>  $candidates
     */
    public function ingestCandidates(XishieProject $project, array $candidates): int
    {
        $count = 0;

        foreach ($candidates as $candidate) {
            if (! is_array($candidate) || empty($candidate['title'])) {
                continue;
            }

            $title = (string) $candidate['title'];
            $abstract = (string) ($candidate['abstract'] ?? '');
            $citationKey = (string) ($candidate['citation_key'] ?? $this->buildCitationKey($candidate));

            ProjectReference::updateOrCreate(
                ['project_id' => $project->id, 'citation_key' => $citationKey],
                [
                    'doi' => $candidate['doi'] ?? null,
                    'title' => $title,
                    'abstract' => $abstract,
                    'authors' => $candidate['authors'] ?? [],
                    'year' => isset($candidate['year']) ? (int) $candidate['year'] : null,
                    'journal_name' => $candidate['journal_name'] ?? null,
                    'sinta_or_scopus_tier' => $candidate['sinta_or_scopus_tier'] ?? null,
                    'matrix_extraction' => $candidate['matrix_extraction'] ?? [
                        'method' => $candidate['method'] ?? null,
                        'result' => $candidate['result'] ?? null,
                        'gap' => $candidate['gap'] ?? null,
                    ],
                    'embedding' => $this->matcher->embed($title.' '.$abstract),
                    'source' => $candidate['source'] ?? 'manual',
                    'is_verified' => (bool) ($candidate['is_verified'] ?? true),
                ],
            );

            $count++;
        }

        return $count;
    }

    /**
     * Matriks literatur (SOTA) dari seluruh referensi proyek.
     *
     * @return array<int, array<string, mixed>>
     */
    public function literatureMatrix(XishieProject $project): array
    {
        return $project->references()
            ->orderByDesc('year')
            ->get()
            ->map(static function (ProjectReference $reference): array {
                $matrix = (array) ($reference->matrix_extraction ?? []);

                return [
                    'citation_key' => $reference->citation_key,
                    'title' => $reference->title,
                    'authors' => $reference->authors,
                    'year' => $reference->year,
                    'method' => $matrix['method'] ?? null,
                    'result' => $matrix['result'] ?? null,
                    'gap' => $matrix['gap'] ?? null,
                ];
            })
            ->all();
    }

    /**
     * Validasi kunci sitasi: hanya kunci yang terdaftar di project_references
     * yang dianggap valid. Kunci asing dikembalikan sebagai "halusinasi".
     *
     * @param  array<int, string>  $candidateKeys
     * @return array{valid: array<int, string>, rejected: array<int, string>}
     */
    public function validateCitations(XishieProject $project, array $candidateKeys): array
    {
        $allowed = array_flip($this->memory->allowedCitationKeys($project));

        $valid = [];
        $rejected = [];

        foreach (array_unique($candidateKeys) as $key) {
            if (isset($allowed[$key])) {
                $valid[] = $key;
            } else {
                $rejected[] = $key;
            }
        }

        return ['valid' => array_values($valid), 'rejected' => array_values($rejected)];
    }

    /**
     * Ekstrak kunci sitasi yang muncul di dalam teks (format [Key] atau (Key)).
     *
     * @return array<int, string>
     */
    public function extractCitationKeys(string $text): array
    {
        preg_match_all('/\[([A-Za-z][A-Za-z0-9_\-]+,\s?\d{4}[a-z]?)\]/u', $text, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    /**
     * @param  array<string, mixed>  $candidate
     */
    private function buildCitationKey(array $candidate): string
    {
        $firstAuthor = 'Anon';

        if (isset($candidate['authors']) && is_array($candidate['authors']) && $candidate['authors'] !== []) {
            $author = (string) $candidate['authors'][0];
            $parts = preg_split('/\s+/u', trim($author)) ?: [];
            $firstAuthor = $parts[0] ?? 'Anon';
        }

        $year = isset($candidate['year']) ? (int) $candidate['year'] : (int) now()->year;

        return $firstAuthor.' '.$year;
    }
}
