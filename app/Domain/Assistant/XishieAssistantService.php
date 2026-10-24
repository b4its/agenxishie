<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Data\SharedContextData;
use App\Omnigensi\Memory\SharedProjectMemory;

/**
 * XishieAssistantService: menjalankan slash command kontekstual di atas
 * SharedProjectMemory (MySQL FULLTEXT + JSON embedding).
 */
final class XishieAssistantService
{
    public function __construct(
        private readonly SharedProjectMemory $memory,
        private readonly ScholarScoutAgent $scholarScout,
        private readonly RuleKeeperAgent $ruleKeeper,
        private readonly LiteratureMatrixBuilder $matrixBuilder,
        private readonly OmnigensiChatClient $llm,
    ) {}

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    public function handle(XishieProject $project, string $input): array
    {
        $command = SlashCommand::tryParse($input);
        $argument = trim((string) preg_replace('/^[^\s]+\s*/u', '', $input));

        if ($command === null) {
            return $this->freeform($project, $input);
        }

        return match ($command) {
            SlashCommand::SITASI => $this->sitasi($project, $argument),
            SlashCommand::PARAFRASE => $this->parafrase($argument),
            SlashCommand::CEK_GAP => $this->cekGap($project),
            SlashCommand::CEK_PEDOMAN => $this->cekPedoman($project),
            SlashCommand::MATRIKS => $this->matriks($project),
            SlashCommand::CARI_REFERENSI => $this->cariReferensi($project, $argument),
            SlashCommand::RINGKAS => $this->ringkas($project),
            SlashCommand::POLES => $this->poles($project, $argument),
            SlashCommand::RUMUS => $this->rumus($project, $argument),
            SlashCommand::GAMBAR => $this->gambar($project, $argument),
        };
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function sitasi(XishieProject $project, string $argument): array
    {
        $context = $this->memory->build($project, $argument);

        $keys = $context->citationKeys();

        $reply = $keys === []
            ? 'Belum ada referensi terdaftar. Tambahkan referensi terlebih dahulu agar sitasi terikat pada sumber nyata.'
            : 'Sitasi valid yang dapat dipakai: '.implode('; ', array_slice($keys, 0, 8)).'.';

        return ['command' => SlashCommand::SITASI->value, 'reply' => $reply, 'data' => ['citations' => $context->citations]];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function parafrase(string $argument): array
    {
        if ($argument === '') {
            return ['command' => SlashCommand::PARAFRASE->value, 'reply' => 'Sertakan kalimat yang ingin diparafrase setelah /parafrase.', 'data' => []];
        }

        $rewritten = 'Dengan demikian, '.lcfirst(rtrim($argument, '.')).' dapat dinyatakan sebagai kontribusi yang perlu divalidasi secara empiris.';

        return ['command' => SlashCommand::PARAFRASE->value, 'reply' => $rewritten, 'data' => ['original' => $argument]];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function poles(XishieProject $project, string $argument): array
    {
        if ($argument === '') {
            return [
                'command' => SlashCommand::POLES->value,
                'reply' => 'Sertakan kalimat atau pilih blok yang ingin dipoles setelah /poles.',
                'data' => [],
            ];
        }

        $context = $this->memory->build($project, $argument);
        $prompt = "Sebagai asisten penulisan artikel ilmiah bereputasi, poles kalimat/paragraf berikut menjadi bahasa akademik formal, baku, dan kaya diksi ilmiah tanpa mengubah maknanya:\n\n\"{$argument}\"";
        $answer = $this->answerWithLlm($project, $prompt, $context);

        if ($answer !== null && trim($answer) !== '') {
            return [
                'command' => SlashCommand::POLES->value,
                'reply' => trim($answer),
                'data' => ['original' => $argument, 'source' => 'omnigensi-llm'],
            ];
        }

        $polished = 'Secara konseptual, '.lcfirst(rtrim($argument, '.')).', yang secara empiris memperkuat landasan teoretis naskah.';

        return [
            'command' => SlashCommand::POLES->value,
            'reply' => $polished,
            'data' => ['original' => $argument],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function cekGap(XishieProject $project): array
    {
        $matrix = $this->matrixBuilder->build($project);
        $gaps = array_values(array_filter(array_map(static fn (array $row): ?string => $row['gap'], $matrix)));

        $reply = $gaps === []
            ? 'Belum ada gap terekstrak. Lengkapi matriks literatur terlebih dahulu.'
            : 'Gap yang teridentifikasi: '.implode(' | ', array_slice($gaps, 0, 5)).'.';

        return ['command' => SlashCommand::CEK_GAP->value, 'reply' => $reply, 'data' => ['gaps' => $gaps]];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function cekPedoman(XishieProject $project): array
    {
        $stage = $this->stageRecord($project);

        if ($stage === null) {
            return ['command' => SlashCommand::CEK_PEDOMAN->value, 'reply' => 'Tahap aktif belum memiliki naskah untuk diaudit.', 'data' => []];
        }

        /** @var GuidelineDefinition $guideline */
        $guideline = $this->memory->guideline($project);
        $audit = $this->ruleKeeper->audit($stage, $guideline);

        $violations = count($audit['violations']);
        $reply = sprintf('Compliance %.1f%% dengan %d pelanggaran terdeteksi.', $audit['score'], $violations);

        return ['command' => SlashCommand::CEK_PEDOMAN->value, 'reply' => $reply, 'data' => $audit];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function matriks(XishieProject $project): array
    {
        return [
            'command' => SlashCommand::MATRIKS->value,
            'reply' => $this->matrixBuilder->toMarkdown($project),
            'data' => ['rows' => $this->matrixBuilder->build($project)],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function cariReferensi(XishieProject $project, string $argument): array
    {
        $relevant = $this->memory->relevantReferences($project, $argument !== '' ? $argument : (string) $project->title);

        if ($relevant === []) {
            return ['command' => SlashCommand::CARI_REFERENSI->value, 'reply' => 'Tidak ada referensi relevan ditemukan.', 'data' => []];
        }

        $titles = array_map(static fn (array $row): string => $row['title'], array_slice($relevant, 0, 5));

        return [
            'command' => SlashCommand::CARI_REFERENSI->value,
            'reply' => 'Referensi paling relevan: '.implode(' | ', $titles).'.',
            'data' => ['references' => $relevant],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function ringkas(XishieProject $project): array
    {
        $stage = $this->stageRecord($project);

        if ($stage === null || empty($stage->sections_content)) {
            return [
                'command' => SlashCommand::RINGKAS->value,
                'reply' => 'Belum ada draf naskah pada tahap aktif untuk diringkas.',
                'data' => [],
            ];
        }

        $sections = (array) $stage->sections_content;
        $sectionHeadings = array_column($sections, 'heading');
        $totalWords = (int) ($stage->word_count ?? 0);

        if ($totalWords === 0) {
            $totalWords = array_sum(array_map(
                static fn (array $sec): int => str_word_count(strip_tags((string) ($sec['content'] ?? ''))),
                $sections
            ));
        }

        $citationCount = $project->references()->count();
        $verifiedCount = $project->references()->where('is_verified', true)->count();
        $compliance = (float) ($stage->compliance_score ?? 0.0);

        $lines = [
            sprintf('### Ringkasan Eksekutif Proyek: %s', $project->title),
            sprintf('- **Tahap Aktif**: %s | **Mode Kontrol**: %s', strtoupper($project->current_stage->value), strtoupper($project->control_mode->value)),
            sprintf('- **Total Kata Naskah**: %d kata dalam %d bagian (%s)', $totalWords, count($sections), implode(', ', array_slice($sectionHeadings, 0, 5))),
            sprintf('- **Basis Pustaka**: %d referensi (%d terverifikasi)', $citationCount, $verifiedCount),
            sprintf('- **Skor Kepatuhan Pedoman**: %.1f%%', $compliance),
        ];

        return [
            'command' => SlashCommand::RINGKAS->value,
            'reply' => implode("\n", $lines),
            'data' => [
                'stage' => $project->current_stage->value,
                'total_words' => $totalWords,
                'sections' => $sectionHeadings,
                'compliance_score' => $compliance,
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function freeform(XishieProject $project, string $input): array
    {
        $context = $this->memory->build($project, $input);

        // Jawab via LLM Omnigensi dengan konteks proyek (terintegrasi).
        $answer = $this->answerWithLlm($project, $input, $context);

        if ($answer !== null) {
            return ['command' => 'freeform', 'reply' => $answer, 'data' => ['request' => $input, 'source' => 'omnigensi-llm']];
        }

        // Fallback offline: ringkasan konteks.
        $reply = sprintf(
            'Konteks proyek: %d referensi (%d terverifikasi), pedoman %s, tahap %s. Gunakan /sitasi, /cek-gap, /cek-pedoman, atau /matriks-literatur.',
            $context->referenceCount,
            $context->verifiedReferenceCount,
            $context->guideline->source,
            $context->stage,
        );

        return ['command' => 'freeform', 'reply' => $reply, 'data' => ['request' => $input, 'source' => 'offline-fallback']];
    }

    /**
     * Jawab pertanyaan bebas memakai LLM Omnigensi dengan konteks proyek.
     */
    private function answerWithLlm(XishieProject $project, string $input, SharedContextData $context): ?string
    {
        if (! config('omnigensi.agents_enabled', true)) {
            return null;
        }

        $citationKeys = $context->citationKeys();

        $system = 'Anda Xishie Assistant untuk penulisan akademik. Jawab ringkas, akurat, '
            .'dan actionable dalam Bahasa Indonesia. Jangan mengarang referensi; bila menyebut '
            .'sumber gunakan hanya kunci sitasi yang tersedia: '
            .($citationKeys === [] ? '(belum ada)' : implode(', ', $citationKeys)).'.';

        $prompt = sprintf(
            "Konteks proyek:\n- Judul: %s\n- Bidang: %s\n- Jenis: %s\n- Tahap: %s\n- Referensi: %d (%d terverifikasi)\n- Pedoman: %s\n\nPertanyaan/permintaan pengguna:\n%s",
            $context->projectTitle,
            $context->fieldOfStudy,
            $context->documentType,
            $context->stage,
            $context->referenceCount,
            $context->verifiedReferenceCount,
            $context->guideline->source,
            $input,
        );

        $result = $this->llm->generate($prompt, $system, 0.6, 800);

        if ($result === null) {
            return null;
        }

        $content = trim((string) $result['content']);

        return $content === '' ? null : $content;
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function rumus(XishieProject $project, string $argument): array
    {
        $arg = trim($argument);
        $recommendation = match (strtolower($arg)) {
            'kuadrat', 'abc' => [
                'name' => 'Rumus Kuadrat (ABC)',
                'latex' => 'x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}',
                'caption' => '1',
            ],
            'pythagoras' => [
                'name' => 'Teorema Pythagoras',
                'latex' => 'a^2 + b^2 = c^2',
                'caption' => '2',
            ],
            'integral', 'kalkulus' => [
                'name' => 'Teorema Kalkulus (Integral)',
                'latex' => '\int_{a}^{b} f(x) \, dx = F(b) - F(a)',
                'caption' => '3',
            ],
            'matrix', 'matriks' => [
                'name' => 'Matriks Transformasi 2x2',
                'latex' => '\begin{bmatrix} a & b \\ c & d \end{bmatrix}',
                'caption' => '4',
            ],
            default => [
                'name' => 'Model Regresi Linear Empiris',
                'latex' => 'y = \beta_0 + \beta_1 x_1 + \beta_2 x_2 + \varepsilon',
                'caption' => '1',
            ],
        };

        $reply = "### Rekomendasi Rumus LaTeX: {$recommendation['name']}\n"
            ."```latex\n{$recommendation['latex']}\n```\n"
            .'Gunakan tombol **π Equation** pada menu *Insert* untuk menyisipkan rumus ini langsung ke naskah.';

        return [
            'command' => SlashCommand::RUMUS->value,
            'reply' => $reply,
            'data' => $recommendation,
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function gambar(XishieProject $project, string $argument): array
    {
        $reply = "### Panduan & Penyisipan Gambar/Diagram Ilmiah\n"
            ."- Klik menu **Insert > Pictures** untuk mengunggah berkas gambar atau tautan URL.\n"
            ."- Anda juga dapat menyisipkan diagram cepat: **📊 Flowchart** (Alur Riset) atau **📈 Chart** (Statistik).\n"
            .'- Setiap gambar otomatis dilengkapi dengan keterangan (*caption*) dan perataan dokumen baku.';

        return [
            'command' => SlashCommand::GAMBAR->value,
            'reply' => $reply,
            'data' => ['argument' => $argument],
        ];
    }

    private function stageRecord(XishieProject $project): ?ProjectStageRecord
    {
        return $project->stages()->where('stage_name', $project->current_stage->value)->first()
            ?? $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    }
}
