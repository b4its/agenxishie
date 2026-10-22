<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents;

use App\Models\AcademicVenue;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Contracts\XishieAgentContract;
use App\Omnigensi\Data\AgentExecutionResultData;
use App\Omnigensi\Memory\HybridVectorMatcherService;

/**
 * VenueMatcherAgent: mencocokkan naskah tahap Final ke katalog jurnal
 * SINTA / Scopus / WoS melalui MySQL FULLTEXT + Cosine Similarity,
 * lalu menyimpan hasilnya ke platform_recommendations.
 */
final class VenueMatcherAgent implements XishieAgentContract
{
    public function __construct(
        private readonly HybridVectorMatcherService $matcher,
    ) {}

    public function name(): string
    {
        return 'Venue-Matcher';
    }

    public function execute(XishieProject $project, ProjectStageRecord $stage, array $context = []): AgentExecutionResultData
    {
        $startedAt = microtime(true);

        $recommendations = $this->generateRecommendations($project, $stage);

        $topScore = $recommendations === [] ? 0.0 : (float) $recommendations[0]['match_score'];

        return AgentExecutionResultData::success(
            agentName: $this->name(),
            outputPayload: ['recommendations' => $recommendations],
            complianceScore: $topScore,
            ttftMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            metadata: [
                'message' => sprintf('Menemukan %d rekomendasi venue (skor teratas %.1f%%).', count($recommendations), $topScore),
                'top_match_score' => $topScore,
            ],
        );
    }

    /**
     * Hasilkan dan simpan rekomendasi platform untuk proyek.
     *
     * @return array<int, array<string, mixed>>
     */
    public function generateRecommendations(XishieProject $project, ProjectStageRecord $stage): array
    {
        $queryText = $this->buildQueryText($project, $stage);
        $queryVector = $this->matcher->embed($queryText);

        $filters = ['is_active' => true];
        $scopeType = $project->target_scope->scopeType();

        if (in_array($scopeType, ['sinta', 'scopus'], true)) {
            $filters['scope_type'] = [$scopeType, 'wos'];
        }

        $matches = $this->matcher->search(
            modelClass: AcademicVenue::class,
            queryText: $queryText,
            queryVector: $queryVector,
            fullTextColumns: ['journal_name', 'aims_and_scope'],
            embeddingColumn: 'aims_scope_embedding',
            limit: 5,
            filters: $filters,
        );

        // Bersihkan rekomendasi lama agar idempoten.
        $project->recommendations()->delete();

        $results = [];

        foreach ($matches as $match) {
            /** @var AcademicVenue $venue */
            $venue = $match['model'];
            $score = round($match['score'] * 100, 2);

            $recommendation = $project->recommendations()->create([
                'academic_venue_id' => $venue->id,
                'journal_name' => $venue->journal_name,
                'publisher' => $venue->publisher,
                'index_tier' => $venue->index_tier,
                'match_score' => $score,
                'scope_reasoning' => $this->reasoning($venue, $score, $project),
                'apc_estimate' => $venue->apc_estimate,
                'submission_url' => $venue->submission_url,
            ]);

            $results[] = [
                'id' => $recommendation->id,
                'journal_name' => $venue->journal_name,
                'publisher' => $venue->publisher,
                'index_tier' => $venue->index_tier,
                'match_score' => $score,
                'scope_reasoning' => $recommendation->scope_reasoning,
                'apc_estimate' => $venue->apc_estimate,
                'submission_url' => $venue->submission_url,
            ];
        }

        return $results;
    }

    private function buildQueryText(XishieProject $project, ProjectStageRecord $stage): string
    {
        $parts = [
            (string) $project->title,
            $project->field_of_study->label(),
            $project->field_of_study->defaultMethodology(),
        ];

        foreach ((array) ($stage->sections_content ?? []) as $section) {
            if (is_array($section) && isset($section['key']) && $section['key'] === 'abstract') {
                $parts[] = (string) ($section['content'] ?? '');
            }
        }

        return trim(implode(' ', array_filter($parts)));
    }

    private function reasoning(AcademicVenue $venue, float $score, XishieProject $project): string
    {
        return sprintf(
            'Aims & Scope "%s" selaras dengan bidang %s dan pendekatan %s (skor %.1f%%).',
            $venue->journal_name,
            $project->field_of_study->label(),
            $project->field_of_study->defaultMethodology(),
            $score,
        );
    }
}
