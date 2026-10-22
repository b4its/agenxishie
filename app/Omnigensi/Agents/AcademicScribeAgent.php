<?php

declare(strict_types=1);

namespace App\Omnigensi\Agents;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Contracts\XishieAgentContract;
use App\Omnigensi\Data\AgentExecutionResultData;
use App\Omnigensi\Data\SharedContextData;
use App\Omnigensi\Memory\SharedProjectMemory;
use Illuminate\Support\Str;

/**
 * AcademicScribeAgent: menulis isi naskah resmi per tahap sesuai bidang
 * keilmuan, jenis dokumen, dan pedoman aktif. Setiap paragraf argumen
 * menyertakan kunci sitasi yang terikat pada referensi proyek.
 *
 * Catatan: implementasi ini menghasilkan kerangka akademik deterministik
 * (template + sitasi nyata dari project_references) sebagai fondasi pipeline.
 * Penulisan berbasis LLM dapat dipasang pada method composeSection tanpa
 * mengubah kontrak agen.
 */
final class AcademicScribeAgent implements XishieAgentContract
{
    public function __construct(
        private readonly SharedProjectMemory $memory,
        private readonly ScholarScoutAgent $scholarScout,
    ) {}

    public function name(): string
    {
        return 'Academic-Scribe';
    }

    public function execute(XishieProject $project, ProjectStageRecord $stage, array $context = []): AgentExecutionResultData
    {
        $startedAt = microtime(true);
        $startedAtNs = hrtime(true);

        $contextData = $this->memory->build($project, (string) ($context['focus_query'] ?? $project->title));
        $guideline = $contextData->guideline;
        $headings = $guideline->sectionHeadingsForStage($stage->stage_name->value);

        $existing = collect((array) ($stage->sections_content ?? []))->keyBy('key');
        $sections = [];
        $citationsUsed = [];
        $hallucinations = [];

        foreach ($headings as $index => $heading) {
            $key = $this->keyForHeading($guideline, $heading);
            $existingSection = $existing->get($key);

            // Mode HITL dengan sections_existing: lewati bagian yang sudah ada.
            if (isset($context['preserve_existing']) && $existingSection !== null) {
                $sections[] = $existingSection;

                continue;
            }

            $composed = $this->composeSection($project, $heading, $key, $contextData);
            $sections[] = $composed;

            $validation = $this->scholarScout->validateCitations($project, $composed['citation_keys']);

            foreach ($validation['valid'] as $validKey) {
                $citationsUsed[$validKey] = true;
            }

            foreach ($validation['rejected'] as $rejectedKey) {
                $hallucinations[] = $rejectedKey;
            }

            // HITL: hanya satu sub-bab per eksekusi (langkah mikro).
            if (($context['single_section'] ?? false) === true && $index === 0) {
                break;
            }
        }

        $stage->forceFill(['sections_content' => $sections])->save();
        $stage->recalculateWordCount();

        $ttft = max(1, (int) ((microtime(true) - $startedAt) * 1000));

        if (($context['single_section'] ?? false) === true) {
            return AgentExecutionResultData::waitingApproval(
                agentName: $this->name(),
                outputPayload: [
                    'sections' => $sections,
                    'pending_section' => $sections[0]['heading'] ?? null,
                ],
                ttftMs: $ttft,
                executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
                metadata: [
                    'message' => 'Draf sub-bab siap ditinjau (Approval Gate).',
                    'word_count' => $stage->word_count,
                ],
            );
        }

        return AgentExecutionResultData::success(
            agentName: $this->name(),
            outputPayload: [
                'sections' => $sections,
                'compiled' => $stage->compile(),
            ],
            citationsUsed: array_keys($citationsUsed),
            hallucinationsRejected: array_values(array_unique($hallucinations)),
            ttftMs: $ttft,
            executionTimeMs: max(1, (int) ((microtime(true) - $startedAt) * 1000)),
            tokenUsage: $stage->word_count,
            metadata: [
                'message' => sprintf('Naskah tahap %s tersusun (%d kata).', $stage->stage_name->label(), $stage->word_count),
                'word_count' => $stage->word_count,
                'high_resolution_time' => hrtime(true) - $startedAtNs,
            ],
        );
    }

    /**
     * Susun satu section. Paragraf argumen menyertakan sitasi nyata.
     *
     * @return array{key: string, heading: string, content: string, citation_keys: array<int, string>}
     */
    private function composeSection(
        XishieProject $project,
        string $heading,
        string $key,
        SharedContextData $context,
    ): array {
        $citations = $context->citations;
        $keys = array_map(static fn (array $citation): string => $citation['citation_key'], $citations);
        $field = $project->field_of_study->label();
        $tone = $project->field_of_study->academicTone();
        $methodology = $project->field_of_study->defaultMethodology();
        $title = $context->projectTitle;

        $citationSuffix = $keys === []
            ? ''
            : ' ['.implode('; ', array_slice($keys, 0, 3)).']';

        $body = match (true) {
            str_contains($key, 'abstract') => sprintf(
                'Penelitian ini mengkaji %s pada bidang %s dengan pendekatan %s. '.
                'Kontribusi utama terletak pada %s. Hasil awal menunjukkan relevansi tinggi '.
                'terhadap kebutuhan praktis dan akademik.%s',
                $title,
                $field,
                $methodology,
                $project->document_type->noveltyRequirement(),
                $citationSuffix,
            ),
            str_contains($key, 'problem_statement') => sprintf(
                'Berdasarkan identifikasi kesenjangan pada bidang %s, permasalahan yang '.
                'dirumuskan adalah bagaimana menerapkan %s untuk menjawab kebutuhan penelitian '.
                'secara %s.%s',
                $field,
                $methodology,
                $tone,
                $citationSuffix,
            ),
            str_contains($key, 'sota') || str_contains($key, 'related_work') || str_contains($key, 'literature_matrix') => sprintf(
                'State of the art pada bidang %s menunjukkan perkembangan metodologis yang '.
                'signifikan. Studi sebelumnya menekankan %s, sementara celah utama terletak pada '.
                'validasi dan generalisasi temuan.%s',
                $field,
                $methodology,
                $citationSuffix,
            ),
            str_contains($key, 'framework') => sprintf(
                'Kerangka berpikir penelitian disusun dengan alur logis dari identifikasi masalah, '.
                'tinjauan literatur, perancangan metode, hingga evaluasi hasil.%s',
                $citationSuffix,
            ),
            str_contains($key, 'methodology') || str_contains($key, 'chapter3') => sprintf(
                'Metodologi yang digunakan adalah %s. Prosedur penelitian mencakup tahap '.
                'perancangan, pengumpulan data, analisis, dan validasi hasil sesuai kaidah %s.%s',
                $methodology,
                $tone,
                $citationSuffix,
            ),
            str_contains($key, 'chapter1') => sprintf(
                'Pendahuluan memaparkan latar belakang, rumusan masalah, tujuan, dan manfaat '.
                'penelitian pada bidang %s. Permasalahan difokuskan pada penerapan %s.%s',
                $field,
                $methodology,
                $citationSuffix,
            ),
            str_contains($key, 'chapter2') => sprintf(
                'Tinjauan pustaka mengelaborasi teori dan penelitian terdahulu yang relevan dengan '.
                '%s. Sintesis literatur digunakan untuk membangun dasar teoretis dan identifikasi '.
                'celah penelitian.%s',
                $field,
                $citationSuffix,
            ),
            str_contains($key, 'chapter4') || str_contains($key, 'results') => sprintf(
                'Hasil penelitian dianalisis secara %s dan dibahas terhadap penelitian sebelumnya. '.
                'Pembahasan mengaitkan temuan dengan teori dan implikasi praktis.%s',
                $tone,
                $citationSuffix,
            ),
            str_contains($key, 'chapter5') || str_contains($key, 'conclusion') => sprintf(
                'Kesimpulan merangkum temuan utama dan saran untuk penelitian lanjutan pada '.
                'bidang %s.%s',
                $field,
                $citationSuffix,
            ),
            default => sprintf(
                'Bagian %s membahas aspek %s secara %s.%s',
                $heading,
                $field,
                $tone,
                $citationSuffix,
            ),
        };

        return [
            'key' => $key,
            'heading' => $heading,
            'content' => $body,
            'citation_keys' => $keys,
        ];
    }

    private function keyForHeading(GuidelineDefinition $guideline, string $heading): string
    {
        foreach ($guideline->sections as $section) {
            if ($section['heading'] === $heading) {
                return $section['key'];
            }
        }

        return Str::slug($heading);
    }
}
