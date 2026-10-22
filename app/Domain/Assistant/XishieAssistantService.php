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
    private function freeform(XishieProject $project, string $input): array
    {
        $context = $this->memory->build($project, $input);

        $reply = sprintf(
            'Konteks proyek: %d referensi (%d terverifikasi), pedoman %s, tahap %s. Gunakan /sitasi, /cek-gap, /cek-pedoman, atau /matriks-literatur.',
            $context->referenceCount,
            $context->verifiedReferenceCount,
            $context->guideline->source,
            $context->stage,
        );

        return ['command' => 'freeform', 'reply' => $reply, 'data' => ['request' => $input]];
    }

    private function stageRecord(XishieProject $project): ?ProjectStageRecord
    {
        return $project->stages()->where('stage_name', $project->current_stage->value)->first()
            ?? $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    }
}
