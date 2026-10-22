<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelineResolverService;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Contracts\XishieAgentContract;
use App\Omnigensi\Data\AgentExecutionResultData;
use App\Omnigensi\Memory\SharedProjectMemory;

/**
 * RuleKeeperAgent: mengekstrak pedoman penulisan (default/custom) menjadi
 * skema aturan dan mengaudit kepatuhan naskah (Guideline Compliance Score).
 */
final class RuleKeeperAgent implements XishieAgentContract
{
    public function __construct(
        private readonly GuidelineResolverService $resolver,
        private readonly SharedProjectMemory $memory,
    ) {}

    public function name(): string
    {
        return 'Rule-Keeper';
    }

    public function execute(XishieProject $project, ProjectStageRecord $stage, array $context = []): AgentExecutionResultData
    {
        $startedAt = microtime(true);

        $guideline = $this->memory->guideline($project);

        // Bila aturan custom diberikan pada context (hasil ekstraksi PDF/DOCX),
        // simpan ke proyek agar konsisten dipakai agen lain.
        if (isset($context['extracted_rules']) && is_array($context['extracted_rules'])) {
            $project->forceFill([
                'guideline_rules' => $context['extracted_rules'],
                'guideline_type' => 'custom',
            ])->save();

            $guideline = GuidelineDefinition::fromArray($context['extracted_rules']);
        } elseif ($project->guideline_rules === null) {
            $project->forceFill(['guideline_rules' => $guideline->toArray()])->save();
        }

        $audit = $this->audit($stage, $guideline);

        return AgentExecutionResultData::success(
            agentName: $this->name(),
            outputPayload: $audit,
            complianceScore: (float) $audit['score'],
            ttftMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            tokenUsage: 0,
            metadata: [
                'message' => sprintf('Audit pedoman selesai: %.1f%% patuh.', $audit['score']),
                'citation_style' => $guideline->citationStyle,
            ],
        );
    }

    /**
     * Audit naskah terhadap aturan pedoman.
     *
     * @return array{score: float, violations: array<int, array<string, mixed>>, checks: array<int, array<string, mixed>>, word_count: int, section_count: int}
     */
    public function audit(ProjectStageRecord $stage, GuidelineDefinition $guideline): array
    {
        $sections = (array) ($stage->sections_content ?? []);
        $presentKeys = array_map(static fn (array $section): string => (string) ($section['key'] ?? ''), $sections);
        $expectedKeys = $guideline->stages[$stage->stage_name->value] ?? [];

        $checks = [];
        $violations = [];

        // 1. Kelengkapan struktur section untuk tahap ini.
        foreach ($expectedKeys as $key) {
            $present = in_array($key, $presentKeys, true);
            $checks[] = ['check' => "section:{$key}", 'passed' => $present];

            if (! $present) {
                $violations[] = ['type' => 'missing_section', 'section' => $key];
            }
        }

        // 2. Batas kata per section.
        $totalWords = 0;

        foreach ($sections as $section) {
            $key = (string) ($section['key'] ?? '');
            $words = str_word_count(strip_tags((string) ($section['content'] ?? '')));
            $totalWords += $words;
            $range = $guideline->wordRangeFor($key);

            if ($range === null) {
                continue;
            }

            $passed = $words >= $range['min'] && $words <= $range['max'];
            $checks[] = ['check' => "words:{$key}", 'passed' => $passed, 'actual' => $words, 'expected' => $range];

            if (! $passed) {
                $violations[] = ['type' => 'word_limit', 'section' => $key, 'actual' => $words, 'expected' => $range];
            }
        }

        // 3. Batas kata total (bila naskah tersedia).
        if ($totalWords > 0 && $guideline->maxTotalWords > 0) {
            $passed = $totalWords <= $guideline->maxTotalWords;
            $checks[] = ['check' => 'words:total', 'passed' => $passed, 'actual' => $totalWords, 'expected' => $guideline->maxTotalWords];

            if (! $passed) {
                $violations[] = ['type' => 'total_word_limit', 'actual' => $totalWords, 'expected' => $guideline->maxTotalWords];
            }
        }

        // 4. Kepatuhan gaya sitasi.
        $citationStyle = CitationStyle::tryFrom($guideline->citationStyle) ?? CitationStyle::APA7;
        $compiled = $stage->compile();

        if ($compiled !== '') {
            $hasCitation = preg_match($citationStyle->inTextPattern(), $compiled) === 1;
            $checks[] = ['check' => 'citation_style', 'passed' => $hasCitation, 'style' => $citationStyle->value];

            if (! $hasCitation) {
                $violations[] = ['type' => 'missing_citation', 'style' => $citationStyle->value];
            }
        }

        $passedCount = count(array_filter($checks, static fn (array $check): bool => (bool) $check['passed']));
        $score = $checks === [] ? 0.0 : round(($passedCount / count($checks)) * 100, 2);

        return [
            'score' => $score,
            'violations' => $violations,
            'checks' => $checks,
            'word_count' => $totalWords,
            'section_count' => count($sections),
        ];
    }
}
