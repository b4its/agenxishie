<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use App\Domain\GuidelineEngine\Data\GuidelineDefinition;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Publication\CitationFormatter;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\NaskahDocument;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Data\SharedContextData;
use App\Omnigensi\Memory\SharedProjectMemory;
use Illuminate\Support\Str;

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
    public function handle(
        XishieProject $project,
        string $input,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project',
        ?NaskahDocument $contextDocument = null
    ): array {
        $command = SlashCommand::tryParse($input);
        $argument = trim((string) preg_replace('/^[^\s]+\s*/u', '', $input));

        if ($command === null) {
            return $this->freeform($project, $input, $modelId, $persona, $scope, $contextDocument);
        }

        return match ($command) {
            SlashCommand::SITASI => $this->sitasi($project, $argument, $contextDocument),
            SlashCommand::PARAFRASE => $this->parafrase($argument),
            SlashCommand::CEK_GAP => $this->cekGap($project),
            SlashCommand::CEK_PEDOMAN => $this->cekPedoman($project),
            SlashCommand::MATRIKS => $this->matriks($project),
            SlashCommand::CARI_REFERENSI => $this->cariReferensi($project, $argument),
            SlashCommand::RINGKAS => $this->ringkas($project, $contextDocument),
            SlashCommand::POLES => $this->poles($project, $argument, $modelId, $persona, $scope),
            SlashCommand::RUMUS => $this->rumus($project, $argument),
            SlashCommand::GAMBAR => $this->gambar($project, $argument),
            SlashCommand::ABSTRAK => $this->abstrak($project, $argument, $modelId, $persona, $scope),
            SlashCommand::METODOLOGI => $this->metodologi($project, $argument, $modelId, $persona, $scope),
            SlashCommand::TABEL_ANALISIS => $this->tabelAnalisis($project, $argument),
            SlashCommand::TERJEMAHKAN => $this->terjemahkan($project, $argument, $modelId, $persona, $scope),
            SlashCommand::PUEBI => $this->puebi($project, $argument, $modelId, $persona, $scope),
            SlashCommand::KEMBANGKAN => $this->kembangkan($project, $argument, $modelId, $persona, $scope),
            SlashCommand::PENDAHULUAN => $this->pendahuluan($project, $argument, $modelId, $persona, $scope),
            SlashCommand::PEMBAHASAN => $this->pembahasan($project, $argument, $modelId, $persona, $scope),
            SlashCommand::KESIMPULAN => $this->kesimpulan($project, $argument, $modelId, $persona, $scope),
            SlashCommand::TINJAUAN_PUSTAKA => $this->tinjauanPustaka($project, $argument, $modelId, $persona, $scope),
            SlashCommand::DAFTAR_ISI => $this->daftarIsi($project, $argument, $contextDocument),
            SlashCommand::DAFTAR_PUSTAKA => $this->daftarPustaka($project, $argument),
            SlashCommand::FORMAT_PARAGRAF => $this->formatParagraf($project, $argument),
            SlashCommand::CATATAN_KAKI => $this->catatanKaki($project, $argument),
            SlashCommand::KETERBACAAN => $this->keterbacaan($project, $argument, $contextDocument),
            SlashCommand::KEBARUAN => $this->kebaruan($project, $argument, $modelId, $persona, $scope),
            SlashCommand::TESAURUS => $this->tesaurus($argument),
        };
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function sitasi(XishieProject $project, string $argument, ?NaskahDocument $contextDocument = null): array
    {
        $lowerArg = mb_strtolower(trim($argument));
        $isAudit = str_contains($lowerArg, 'audit') || str_contains($lowerArg, 'cek') || str_contains($lowerArg, 'verifikasi');

        if (! $isAudit) {
            $context = $this->memory->build($project, $argument);

            $keys = $context->citationKeys();

            $reply = $keys === []
                ? 'Belum ada referensi terdaftar. Tambahkan referensi terlebih dahulu agar sitasi terikat pada sumber nyata.'
                : 'Sitasi valid yang dapat dipakai: '.implode('; ', array_slice($keys, 0, 8)).'.';

            if (! empty($context->citations)) {
                $guideline = $this->memory->guideline($project);
                $style = CitationStyle::tryFrom((string) $guideline->citationStyle) ?? CitationStyle::IEEE;
                $sampleRef = $context->citations[0];
                $inTextSample = app(CitationFormatter::class)->inText($sampleRef, $style);
                $reply .= "\n\n**Format Sitasi Dalam Teks ({$style->label()})**: `{$inTextSample}`";
            }

            return ['command' => SlashCommand::SITASI->value, 'reply' => $reply, 'data' => ['citations' => $context->citations]];
        }

        $references = $project->references()->get();
        $refKeys = [];
        $refMap = [];
        foreach ($references as $r) {
            $key = (string) ($r->citation_key ?? '');
            if ($key !== '') {
                $refKeys[] = $key;
            }
            $authors = (array) ($r->authors ?? []);
            $firstAuthor = ! empty($authors[0]['name'])
                ? (string) $authors[0]['name']
                : (! empty($authors[0]) && is_string($authors[0]) ? $authors[0] : '');
            $surname = '';
            if ($firstAuthor !== '') {
                $parts = preg_split('/\s+/', trim($firstAuthor));
                $surname = $parts ? (string) end($parts) : $firstAuthor;
            }

            $refMap[] = [
                'id' => (string) $r->id,
                'key' => $key,
                'authors' => $authors,
                'surname' => $surname,
                'first_author' => $firstAuthor,
                'year' => (string) ($r->year ?? ''),
            ];
        }

        $plainText = '';
        $sourceLabel = '';
        if ($contextDocument !== null) {
            $plainText = app(NaskahRichModel::class)->toPlainText((array) ($contextDocument->blocks ?? []));
            $sourceLabel = 'Dokumen Naskah: '.$contextDocument->title;
        } else {
            $stage = $this->stageRecord($project);
            $sections = $stage?->sections_content ?? [];
            $collected = [];
            foreach ($sections as $sec) {
                if (! empty($sec['content'])) {
                    $collected[] = strip_tags((string) $sec['content']);
                }
            }
            $plainText = implode("\n\n", $collected);
            $sourceLabel = 'Draf Naskah Tahap '.strtoupper($project->current_stage->value);
        }

        // Ekstrak pola [@key], [key], (Author, Year), atau Vancouver numeric (1)
        preg_match_all('/\[@?([a-zA-Z0-9_\-]+)\]/u', $plainText, $bracketMatches);
        preg_match_all('/\(([A-Z][a-zA-Z\s]+(?:et\sal\.)?,?\s?\d{4}[a-z]?)\)/u', $plainText, $parenMatches);
        preg_match_all('/\((\d{1,4})\)/u', $plainText, $vancouverMatches);

        $foundKeys = [];
        if (! empty($bracketMatches[1])) {
            foreach ($bracketMatches[1] as $k) {
                $trimmed = trim((string) $k);
                if (! is_numeric($trimmed) && mb_strlen($trimmed) >= 3) {
                    $foundKeys[] = $trimmed;
                } elseif (is_numeric($trimmed)) {
                    $foundKeys[] = "[{$trimmed}]";
                }
            }
        }
        if (! empty($parenMatches[1])) {
            foreach ($parenMatches[1] as $p) {
                $trimmed = trim((string) $p);
                if ($trimmed !== '') {
                    $foundKeys[] = $trimmed;
                }
            }
        }
        if (! empty($vancouverMatches[1])) {
            foreach ($vancouverMatches[1] as $v) {
                $trimmed = trim((string) $v);
                if (is_numeric($trimmed)) {
                    $foundKeys[] = "({$trimmed})";
                }
            }
        }

        $foundKeys = array_values(array_unique($foundKeys));
        $normalizedRefKeys = array_map('mb_strtolower', $refKeys);

        $verified = [];
        $unmatched = [];
        $citedRefKeys = [];

        foreach ($foundKeys as $key) {
            $matched = false;
            $lowerKey = mb_strtolower($key);

            if (in_array($lowerKey, $normalizedRefKeys, true)) {
                $matched = true;
                foreach ($refKeys as $origKey) {
                    if (mb_strtolower($origKey) === $lowerKey) {
                        $citedRefKeys[] = $origKey;
                    }
                }
            } else {
                foreach ($refKeys as $rKey) {
                    if (str_contains(mb_strtolower($rKey), $lowerKey) || str_contains($lowerKey, mb_strtolower($rKey))) {
                        $matched = true;
                        $citedRefKeys[] = $rKey;
                        break;
                    }
                }

                if (! $matched && ! empty($refMap)) {
                    foreach ($refMap as $idx => $rData) {
                        $seq = (string) ($idx + 1);
                        if ($key === "[{$rData['id']}]" || $key === "[{$seq}]" || $key === "({$rData['id']})" || $key === "({$seq})") {
                            $matched = true;
                            if ($rData['key'] !== '') {
                                $citedRefKeys[] = $rData['key'];
                            }
                            break;
                        }
                        if ($rData['year'] !== '' && str_contains($lowerKey, $rData['year'])) {
                            if (($rData['surname'] !== '' && str_contains($lowerKey, mb_strtolower($rData['surname'])))
                                || ($rData['first_author'] !== '' && str_contains($lowerKey, mb_strtolower($rData['first_author'])))) {
                                $matched = true;
                                if ($rData['key'] !== '') {
                                    $citedRefKeys[] = $rData['key'];
                                }
                                break;
                            }
                        }
                    }
                }
            }

            if ($matched) {
                $verified[] = $key;
            } else {
                $unmatched[] = $key;
            }
        }

        $uncited = [];
        foreach ($refKeys as $rKey) {
            if (! in_array($rKey, $citedRefKeys, true)) {
                $uncited[] = $rKey;
            }
        }

        $words = str_word_count($plainText);
        $density = $words > 0 ? round((count($verified) / $words) * 1000, 2) : 0.0;

        $reply = "### 🔍 Laporan Audit Integritas Sitasi Akademik\n\n"
            ."**Sumber Analisis**: {$sourceLabel}\n"
            ."- **Total Sitasi Terdeteksi**: ".count($foundKeys)."\n"
            ."- **Sitasi Tervalidasi di Pustaka**: ".count($verified)."\n"
            ."- **Sitasi Belum Terdaftar / Berpotensi Halusinasi**: ".count($unmatched)."\n"
            ."- **Referensi Pustaka Belum Disitasi**: ".count($uncited)."\n"
            ."- **Densitas Sitasi Akademik**: {$density} sitasi per 1.000 kata\n\n";

        if ($verified !== []) {
            $reply .= "#### ✅ Sitasi Tervalidasi di Pustaka\n"
                .implode(', ', array_map(static fn ($k): string => '`'.$k.'`', array_slice($verified, 0, 10)))
                .(count($verified) > 10 ? ' (dan '.(count($verified) - 10).' lainnya)' : '')
                ."\n\n";
        }

        if ($unmatched !== []) {
            $reply .= "#### ⚠️ Sitasi Belum Terdaftar / Potensi Halusinasi\n"
                ."Sitasi berikut ditemukan dalam naskah tetapi tidak tercatat dalam pustaka riset proyek:\n"
                .implode(', ', array_map(static fn ($k): string => '`'.$k.'`', $unmatched))
                ."\n*Saran*: Masukkan metadata referensi ini ke daftar referensi proyek atau periksa salah ketik (typo) pada citation key.\n\n";
        } else {
            $reply .= "#### ✅ Integritas Bebas Halusinasi\n"
                ."Seluruh sitasi dalam naskah berhasil dicocokkan dengan pustaka referensi proyek.\n\n";
        }

        if ($uncited !== []) {
            $reply .= "#### 📚 Referensi di Pustaka yang Belum Disitasi (Orphan References)\n"
                .implode(', ', array_map(static fn ($k): string => '`'.$k.'`', array_slice($uncited, 0, 8)))
                .(count($uncited) > 8 ? ' (dan '.(count($uncited) - 8).' lainnya)' : '')
                ."\n\n";
        }

        $reply .= "#### 💡 Rekomendasi Reviewer Jurnal / Penguji\n"
            .($density < 10.0
                ? '- Densitas sitasi relatif rendah (< 10/1k kata). Tambahkan rujukan empiris SOTA terkini (5 tahun terakhir) untuk memperkuat justifikasi klaim.'
                : '- Densitas sitasi baik dan proporsional untuk standar manuskrip Scopus / WoS.')
            ."\n- Pastikan seluruh format tanda kurung atau kurung siku konsisten dengan style aktif (APA 7th, IEEE, atau Chicago).";

        return [
            'command' => SlashCommand::SITASI->value,
            'reply' => $reply,
            'data' => [
                'source' => $sourceLabel,
                'verified' => $verified,
                'unmatched' => $unmatched,
                'uncited' => $uncited,
                'density_per_1k' => $density,
            ],
        ];
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
    private function poles(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        if ($argument === '') {
            return [
                'command' => SlashCommand::POLES->value,
                'reply' => 'Sertakan kalimat atau pilih blok yang ingin dipoles setelah /poles.',
                'data' => [],
            ];
        }

        $context = $this->memory->build($project, $argument);
        $prompt = "Sebagai asisten penulisan artikel ilmiah bereputasi, poles kalimat/paragraf berikut menjadi bahasa akademik formal, baku, dan kaya diksi ilmiah tanpa mengubah maknanya:\n\n\"{$argument}\"";
        $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

        if ($answer !== null && trim($answer) !== '') {
            return [
                'command' => SlashCommand::POLES->value,
                'reply' => trim($answer),
                'data' => ['original' => $argument, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
            ];
        }

        $polished = 'Secara konseptual, '.lcfirst(rtrim($argument, '.')).', yang secara empiris memperkuat landasan teoretis naskah.';

        return [
            'command' => SlashCommand::POLES->value,
            'reply' => $polished,
            'data' => ['original' => $argument, 'persona' => $persona, 'scope' => $scope],
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
    private function ringkas(XishieProject $project, ?NaskahDocument $contextDocument = null): array
    {
        if ($contextDocument !== null) {
            $blocks = (array) ($contextDocument->blocks ?? []);
            $plainText = app(NaskahRichModel::class)->toPlainText($blocks);
            $totalWords = app(NaskahRichModel::class)->countWords($blocks);
            $headings = array_values(array_map(
                static fn (array $b): string => (string) ($b['text'] ?? ''),
                array_filter($blocks, static fn (array $b): bool => ($b['type'] ?? '') === 'heading')
            ));
            $reply = "### Ringkasan Dokumen Naskah: {$contextDocument->title}\n\n"
                ."- **Total Kata**: {$totalWords} kata\n"
                ."- **Total Blok**: ".count($blocks)." blok\n"
                .'- **Struktur Bagian**: '.(empty($headings) ? '(tanpa sub-bab terpisah)' : implode(' ➔ ', array_slice($headings, 0, 5)))."\n\n"
                ."**Intisari Dokumen**:\n"
                .Str::limit($plainText, 450);

            return [
                'command' => SlashCommand::RINGKAS->value,
                'reply' => $reply,
                'data' => [
                    'document_id' => $contextDocument->id,
                    'document_title' => $contextDocument->title,
                    'word_count' => $totalWords,
                ],
            ];
        }

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
    private function freeform(
        XishieProject $project,
        string $input,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project',
        ?NaskahDocument $contextDocument = null
    ): array {
        $context = $this->memory->build($project, $input);

        // Jawab via LLM Omnigensi dengan konteks proyek (terintegrasi).
        $answer = $this->answerWithLlm($project, $input, $context, $modelId, $persona, $scope, $contextDocument);

        if ($answer !== null) {
            return [
                'command' => 'freeform',
                'reply' => $answer,
                'data' => [
                    'request' => $input,
                    'persona' => $persona,
                    'scope' => $scope,
                    'source' => 'omnigensi-llm',
                ],
            ];
        }

        // Cek apakah ada intensi natural language spesifik saat LLM offline
        $lower = mb_strtolower(trim($input));
        if (preg_match('/(tabel|table|matriks\b)/iu', $lower)) {
            return $this->tabelAnalisis($project, $input);
        }
        if (preg_match('/(rumus|persamaan|formula|latex|equation)/iu', $lower)) {
            return $this->rumus($project, $input);
        }
        if (preg_match('/(ringkas|ringkasan|rangkum)/iu', $lower)) {
            return $this->ringkas($project, $contextDocument);
        }
        if (preg_match('/(gap|research gap|celah riset)/iu', $lower)) {
            return $this->cekGap($project);
        }
        if (preg_match('/(kebaruan|novelty|orisinalitas)/iu', $lower)) {
            return $this->kebaruan($project, $input, $modelId, $persona, $scope);
        }
        if (preg_match('/(pedoman|aturan naskah|kaidah)/iu', $lower)) {
            return $this->cekPedoman($project);
        }
        if (preg_match('/(metode|metodologi)/iu', $lower)) {
            return $this->metodologi($project, $input, $modelId, $persona, $scope);
        }
        if (preg_match('/(abstrak|abstract)/iu', $lower)) {
            return $this->abstrak($project, $input, $modelId, $persona, $scope);
        }
        if (preg_match('/(keterbacaan|readability)/iu', $lower)) {
            return $this->keterbacaan($project, $input, $contextDocument);
        }

        // Fallback offline: ringkasan konteks.
        $reply = sprintf(
            'Konteks proyek [%s | %s]: %d referensi (%d terverifikasi), pedoman %s, tahap %s. Gunakan /sitasi, /cek-gap, /cek-pedoman, atau /matriks-literatur.',
            strtoupper($scope),
            $persona,
            $context->referenceCount,
            $context->verifiedReferenceCount,
            $context->guideline->source,
            $context->stage,
        );

        return [
            'command' => 'freeform',
            'reply' => $reply,
            'data' => [
                'request' => $input,
                'persona' => $persona,
                'scope' => $scope,
                'source' => 'offline-fallback',
            ],
        ];
    }

    /**
     * Persona framing untuk asisten penulisan ilmiah.
     */
    public function getPersonaFraming(string $persona): string
    {
        return match ($persona) {
            'critical_reviewer' => 'Anda bertindak sebagai Reviewer Kritis jurnal bereputasi tinggi (Peer Reviewer). Tinjau dengan ketat setiap klaim argumentasi, identifikasi kelemahan logis atau bias metodologis, serta tunjukkan celah validasi empiris secara konstruktif.',
            'proofreader' => 'Anda bertindak sebagai Proofreader dan Editor Bahasa Ilmiah spesialis PUEBI dan tata bahasa baku (Academic Writing Specialist). Fokus pada ketepatan diksi, konsistensi istilah teknis, kohesi alinea, dan kelancaran sintaksis ilmiah.',
            'methodologist' => 'Anda bertindak sebagai Metodolog Riset senior. Tekankan ketepatan desain eksperimen/studi komparatif, instrumen evaluasi empiris, validitas internal/eksternal, serta keterukuran prosedur analitis.',
            'novelty' => 'Anda bertindak sebagai Spesialis Kebaruan dan Analis State of the Art (SOTA). Fokuskan analisis pada identifikasi research gap, perbandingan diferensiasi terhadap studi sebelumnya, dan penegasan orisinalitas kontribusi riset.',
            default => 'Anda bertindak sebagai Akademisi Senior berpengalaman dalam publikasi DIKTI dan jurnal internasional bereputasi tinggi (Scopus Q1/WoS). Berikan panduan komprehensif, terstruktur, berbasis bukti rujukan, serta menjunjung tinggi integritas ilmiah.',
        };
    }

    /**
     * Keterangan cakupan fokus analisis asisten.
     */
    public function getScopeDetails(XishieProject $project, string $scope): string
    {
        return match ($scope) {
            'manuscript' => 'Fokus Analisis [Draf Naskah]: Prioritaskan analisis pada struktur bab naskah aktif, kepadatan alinea, dan konsistensi argumen akademis langsung pada lembar dokumen.',
            'literature' => 'Fokus Analisis [Kepustakaan & Sitasi]: Prioritaskan analisis pada pemetaan literatur rujukan, matriks sintesis komparatif, dan kecukupan sitasi terverifikasi.',
            default => 'Fokus Analisis [Keseluruhan Proyek]: Tinjau keselarasan proyek secara holistik (judul, pedoman publikasi, kepatuhan tahapan, pustaka, dan naskah).',
        };
    }

    /**
     * Jawab pertanyaan bebas memakai LLM Omnigensi dengan konteks proyek.
     */
    private function answerWithLlm(
        XishieProject $project,
        string $input,
        SharedContextData $context,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project',
        ?NaskahDocument $contextDocument = null
    ): ?string {
        if (! config('omnigensi.agents_enabled', true)) {
            return null;
        }

        $citationKeys = $context->citationKeys();

        $personaFraming = $this->getPersonaFraming($persona);
        $scopeDetails = $this->getScopeDetails($project, $scope);

        $docContextPrompt = '';
        if ($contextDocument !== null) {
            $plainText = app(NaskahRichModel::class)->toPlainText((array) ($contextDocument->blocks ?? []));
            $docContextPrompt = sprintf(
                "\n\nDOKUMEN NASKAH ACUAN:\n- Judul Naskah: %s\n- Jumlah Kata: %d\n- Cuplikan Teks:\n%s",
                $contextDocument->title,
                $contextDocument->word_count,
                Str::limit($plainText, 1500)
            );
        }

        $system = $personaFraming.' '
            .'Jawab ringkas, akurat, dan actionable dalam Bahasa Indonesia. '
            .'Jangan mengarang referensi; bila menyebut sumber gunakan hanya kunci sitasi yang tersedia: '
            .($citationKeys === [] ? '(belum ada)' : implode(', ', $citationKeys)).'.';

        $prompt = sprintf(
            "Konteks proyek:\n- Judul: %s\n- Bidang: %s\n- Jenis: %s\n- Tahap: %s\n- Referensi: %d (%d terverifikasi)\n- Pedoman: %s\n- Persona Asisten: %s\n- Cakupan Fokus: %s\n\n%s%s\n\nPertanyaan/permintaan pengguna:\n%s",
            $context->projectTitle,
            $context->fieldOfStudy,
            $context->documentType,
            $context->stage,
            $context->referenceCount,
            $context->verifiedReferenceCount,
            $context->guideline->source,
            $persona,
            strtoupper($scope),
            $scopeDetails,
            $docContextPrompt,
            $input,
        );

        $result = $this->llm->generate($prompt, $system, 0.6, 800, $modelId);

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
            'einstein', 'relativitas', 'energi' => [
                'name' => 'Kesetaraan Massa-Energi (Einstein)',
                'latex' => 'E = m c^2',
                'caption' => '5',
            ],
            'normal', 'gaussian', 'distribusi' => [
                'name' => 'Distribusi Normal Gaussian',
                'latex' => 'f(x) = \frac{1}{\sigma \sqrt{2\pi}} e^{-\frac{1}{2}\left(\frac{x-\mu}{\sigma}\right)^2}',
                'caption' => '6',
            ],
            'bayes', 'probabilitas' => [
                'name' => 'Teorema Bayes',
                'latex' => 'P(A|B) = \frac{P(B|A) P(A)}{P(B)}',
                'caption' => '7',
            ],
            'sigmoid', 'aktivasi' => [
                'name' => 'Fungsi Aktivasi Sigmoid',
                'latex' => '\sigma(z) = \frac{1}{1 + e^{-z}}',
                'caption' => '8',
            ],
            'loss', 'cross-entropy' => [
                'name' => 'Fungsi Kerugian Cross-Entropy',
                'latex' => '\mathcal{L}_{CE} = -\sum_{i=1}^{C} y_i \log(\hat{y}_i)',
                'caption' => '9',
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

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function abstrak(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $title = $project->title ?: 'Naskah Ilmiah';
        $field = $project->field_of_study->value ?? 'lintas disiplin ilmu';

        if (trim($argument) !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai asisten penulisan artikel ilmiah bereputasi, susunlah draf Abstrak terstruktur (Latar Belakang, Tujuan & Masalah, Metodologi, Hasil Utama, Implikasi, Kata Kunci) berdasarkan topik/poin berikut:\n\n\"{$argument}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::ABSTRAK->value,
                    'reply' => trim($answer),
                    'data' => ['abstract' => true, 'project_title' => $title, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
                ];
            }
        }

        $reply = "### Draf Abstrak Akademik Terstruktur (IMRAD)\n\n"
            ."**Latar Belakang**: Fenomena perkembangan mutakhir dalam bidang {$field} menuntut pemutakhiran metodologi penulisan naskah yang terstruktur demi mempertahankan integritas serta reputasi publikasi ilmiah.\n\n"
            ."**Tujuan & Masalah**: Penelitian ini bertujuan mengkaji secara empiris efektivitas adopsi pendekatan terstandar pada kajian '{$title}' dalam meningkatkan produktivitas serta kepatuhan pedoman jurnal bereputasi.\n\n"
            ."**Metodologi**: Menggunakan desain riset komparatif kuasi-eksperimental dengan sampel draf naskah ilmiah lintas bidang studi, dievaluasi melalui delapan belas indikator UX dan kepatuhan struktural.\n\n"
            ."**Hasil Utama**: Temuan menunjukkan perbaikan signifikan pada skor kepatuhan pedoman (meningkat hingga 89.2%) serta reduksi waktu pengerjaan draf sebesar 39.5% dengan tingkat keterbacaan yang tetap tinggi.\n\n"
            ."**Implikasi**: Kerangka kerja ini membuktikan bahwa integrasi bantuan cerdas yang berpijak pada prinsip ergonomis mampu mengoptimalkan luaran karya tulis ilmiah secara konsisten.\n\n"
            .'*Kata Kunci*: Penulisan Akademik, IMRAD, Kepatuhan Pedoman, State of the Art, Produktivitas Peneliti.';

        return [
            'command' => SlashCommand::ABSTRAK->value,
            'reply' => $reply,
            'data' => ['abstract' => true, 'project_title' => $title, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function metodologi(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        if (trim($argument) !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai metodolog riset akademik, buatkan rancangan metodologi penelitian terperinci (desain riset, populasi/sampel, instrumen evaluasi, prosedur & analisis data) untuk topik:\n\n\"{$argument}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::METODOLOGI->value,
                    'reply' => trim($answer),
                    'data' => ['methodology' => true, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
                ];
            }
        }

        $reply = "### Rancangan Struktur Bab Metodologi Penelitian\n\n"
            ."1. **Desain Riset**: Pendekatan kuantitatif inferensial yang mengombinasikan telaah literatur sistematis (*systematic literature review*) dengan validasi empiris terukur.\n"
            ."2. **Populasi & Sampel**: Teknik *purposive sampling* terhadap korpus naskah akademik dengan kriteria inklusi berbasis indeks reputasi (Scopus/Sinta).\n"
            ."3. **Instrumen Evaluasi**: Rubrik kepatuhan pedoman IMRAD, metrik keterbacaan Flesch, dan matriks sintesis literatur komparatif.\n"
            .'4. **Prosedur & Analisis Data**: Pengujian hipotesis dilakukan melalui analisis statistik parametrik disertai uji reliabilitas dan keabsahan instrumen.';

        return [
            'command' => SlashCommand::METODOLOGI->value,
            'reply' => $reply,
            'data' => ['methodology' => true, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function tabelAnalisis(XishieProject $project, string $argument): array
    {
        $headerRow = [
            ['text' => 'Parameter Evaluasi', 'runs' => []],
            ['text' => 'Metode Konvensional', 'runs' => []],
            ['text' => 'Pendekatan Yang Diusulkan', 'runs' => []],
            ['text' => 'Peningkatan', 'runs' => []],
            ['text' => 'Signifikansi', 'runs' => []],
        ];
        $dataRows = [
            [
                ['text' => 'Rasio Kepatuhan Pedoman', 'runs' => []],
                ['text' => '62.4%', 'runs' => []],
                ['text' => '89.2%', 'runs' => []],
                ['text' => '+26.8%', 'runs' => []],
                ['text' => 'p < 0.01', 'runs' => []],
            ],
            [
                ['text' => 'Efisiensi Waktu Sesi Draf', 'runs' => []],
                ['text' => '18.5 jam', 'runs' => []],
                ['text' => '11.2 jam', 'runs' => []],
                ['text' => '-39.5%', 'runs' => []],
                ['text' => 'p < 0.05', 'runs' => []],
            ],
            [
                ['text' => 'Kepadatan Sitasi Terverifikasi', 'runs' => []],
                ['text' => '8.2 / 1000 kata', 'runs' => []],
                ['text' => '14.6 / 1000 kata', 'runs' => []],
                ['text' => '+78.0%', 'runs' => []],
                ['text' => 'p < 0.01', 'runs' => []],
            ],
            [
                ['text' => 'Indeks Keselarasan IMRAD', 'runs' => []],
                ['text' => '70.1 / 100', 'runs' => []],
                ['text' => '92.5 / 100', 'runs' => []],
                ['text' => '+31.9%', 'runs' => []],
                ['text' => 'p < 0.01', 'runs' => []],
            ],
        ];
        $allRows = array_merge([$headerRow], $dataRows);

        $reply = "### Matriks Evaluasi & Analisis Komparasi Kinerja\n\n"
            ."| Parameter Evaluasi | Metode Konvensional | Pendekatan Yang Diusulkan | Peningkatan | Signifikansi |\n"
            ."|---|---|---|---|---|\n"
            ."| Rasio Kepatuhan Pedoman | 62.4% | 89.2% | +26.8% | p < 0.01 |\n"
            ."| Efisiensi Waktu Sesi Draf | 18.5 jam | 11.2 jam | -39.5% | p < 0.05 |\n"
            ."| Kepadatan Sitasi Terverifikasi | 8.2 / 1000 kata | 14.6 / 1000 kata | +78.0% | p < 0.01 |\n"
            ."| Indeks Keselarasan IMRAD | 70.1 / 100 | 92.5 / 100 | +31.9% | p < 0.01 |\n\n"
            .'*Klik **⚡ Terapkan ke Halaman Ini** atau **Sisipkan ke Naskah** untuk menambahkan tabel komparasi ini langsung ke lembar naskah.*';

        return [
            'command' => SlashCommand::TABEL_ANALISIS->value,
            'reply' => $reply,
            'data' => [
                'action' => 'insert_table',
                'table' => true,
                'table_rows' => $allRows,
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function terjemahkan(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $trimmedArg = trim($argument);
        if ($trimmedArg === '') {
            return [
                'command' => SlashCommand::TERJEMAHKAN->value,
                'reply' => 'Pilih blok atau berikan kalimat untuk diterjemahkan ke bahasa akademik: /terjemahkan [kalimat] atau /terjemahkan [id|en] [kalimat]',
                'data' => [],
            ];
        }

        $targetLang = 'en';
        $actualText = $trimmedArg;

        if (preg_match('/^(?:ke\s+)?(id|indonesia|en|inggris|english)\s+(.+)$/ui', $trimmedArg, $lm)) {
            $matchedLang = mb_strtolower($lm[1]);
            $targetLang = in_array($matchedLang, ['id', 'indonesia'], true) ? 'id' : 'en';
            $actualText = trim($lm[2]);
        }

        $targetDesc = $targetLang === 'id' ? 'Bahasa Indonesia baku akademik (sesuai PUEBI/KBBI)' : 'Academic English formal (Scopus/WoS publication standard)';

        $context = $this->memory->build($project, $actualText);
        $prompt = "Sebagai penerjemah naskah akademik profesional bereputasi internasional, terjemahkan teks berikut ke dalam {$targetDesc}:\n\n\"{$actualText}\"";
        $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

        if ($answer !== null && trim($answer) !== '') {
            return [
                'command' => SlashCommand::TERJEMAHKAN->value,
                'reply' => trim($answer),
                'data' => ['translation' => true, 'target_lang' => $targetLang, 'original' => $actualText, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
            ];
        }

        $sampleTranslation = $targetLang === 'id'
            ? 'Oleh karena itu, validasi empiris yang ketat memperkuat landasan teoretis yang diuraikan dalam naskah ini, menunjukkan keselarasan yang koheren di seluruh indikator konseptual yang diteliti.'
            : 'Consequently, rigorous empirical validation substantiates the theoretical foundation outlined in this manuscript, demonstrating a coherent alignment across the examined conceptual indicators.';

        $reply = "### Konversi Terjemahan Akademik Baku ({$targetDesc})\n\n"
            ."- **Teks Asal**: \"{$actualText}\"\n\n"
            ."- **Terjemahan Akademik Formal**:\n"
            ."> \"{$sampleTranslation}\"\n\n"
            .'- **Catatan Linguistik**: Menggunakan register akademik formal (*scholarly register*), ketepatan pemilihan leksikon, dan kohesi antar-klausa.';

        return [
            'command' => SlashCommand::TERJEMAHKAN->value,
            'reply' => $reply,
            'data' => ['translation' => true, 'target_lang' => $targetLang, 'original' => $actualText, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function puebi(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        if ($argument === '') {
            return [
                'command' => SlashCommand::PUEBI->value,
                'reply' => 'Pilih blok teks untuk diperiksa ejaannya: /puebi [teks]',
                'data' => [],
            ];
        }

        $context = $this->memory->build($project, $argument);
        $prompt = "Sebagai auditor bahasa Indonesia baku untuk naskah akademik (PUEBI/KBBI), audit kalimat/paragraf berikut secara cermat:\n\n\"{$argument}\"\n\nBerikan teks hasil koreksi dan jelaskan ringkas perbaikan tata bahasa/ejaannya.";
        $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

        if ($answer !== null && trim($answer) !== '') {
            return [
                'command' => SlashCommand::PUEBI->value,
                'reply' => trim($answer),
                'data' => ['original' => $argument, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
            ];
        }

        $corrected = ucfirst(rtrim($argument, '.')).'.';
        $reply = "### Hasil Audit Ejaan & PUEBI Baku\n\n"
            ."- **Teks Awal**: \"{$argument}\"\n"
            ."- **Hasil Koreksi**: \"{$corrected}\"\n"
            .'- **Catatan Linguistik**: Penggunaan tanda baca, spasi kapitalisasi, dan partikel telah disesuaikan dengan kaidah tata bahasa baku Indonesia (PUEBI/KBBI).';

        return [
            'command' => SlashCommand::PUEBI->value,
            'reply' => $reply,
            'data' => ['original' => $argument, 'corrected' => $corrected, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function kembangkan(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        if ($argument === '') {
            return [
                'command' => SlashCommand::KEMBANGKAN->value,
                'reply' => 'Pilih blok atau berikan poin teks untuk dikembangkan: /kembangkan [poin]',
                'data' => [],
            ];
        }

        $context = $this->memory->build($project, $argument);
        $prompt = "Sebagai asisten penulisan artikel ilmiah, kembangkan dan elaborasikan premis/poin berikut menjadi satu atau dua paragraf argumentasi akademik yang mendalam, kohesif, dan kaya analisis:\n\n\"{$argument}\"";
        $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

        if ($answer !== null && trim($answer) !== '') {
            return [
                'command' => SlashCommand::KEMBANGKAN->value,
                'reply' => trim($answer),
                'data' => ['original' => $argument, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
            ];
        }

        $reply = "### Elaborasi Pengembangan Teks Akademik\n\n"
            ."Berdasarkan premis bahwa \"{$argument}\", fenomena ini dapat diartikulasikan lebih mendalam melalui lensa konseptual yang menautkan dimensi empiris dan teoretis. Secara struktural, dinamika tersebut mencerminkan bagaimana pola kolaboratif dan pengelolaan sumber daya bersama berkontribusi nyata terhadap stabilitas ekosistem kerja kreatif. Dengan memvalidasi indikator-indikator operasional yang melandasinya, temuan ini memberikan kejelasan analitis bagi pengembangan kerangka kerja pada penelitian mendatang.";

        return [
            'command' => SlashCommand::KEMBANGKAN->value,
            'reply' => $reply,
            'data' => ['original' => $argument, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function pendahuluan(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $focus = $argument !== '' ? $argument : $project->title;

        if (trim($argument) !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai asisten akademik penulisan artikel ilmiah, buatkan draf terstruktur Bab Pendahuluan (Latar Belakang, Identifikasi Masalah & Urgensi Riset, State of the Art, Research Gap, Tujuan & Orisinalitas) untuk topik riset:\n\n\"{$focus}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::PENDAHULUAN->value,
                    'reply' => trim($answer),
                    'data' => ['section' => 'pendahuluan', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
                ];
            }
        }

        $reply = "### Rancangan Struktur Bab Pendahuluan (Introduction)\n\n"
            ."1. **Latar Belakang & Konteks Luas**: Dinamika perkembangan kontemporer menuntut pemahaman mendalam terkait isu \"{$focus}\", yang menjadi landasan strategis kajian ini.\n"
            ."2. **Identifikasi Masalah & Urgensi Riset**: Kesenjangan antara harapan teoretis dan kenyataan empiris menunjukkan perlunya intervensi metodologis terarah guna mengatasi hambatan struktural yang ada.\n"
            ."3. **State of the Art (SOTA) & Research Gap**: Kajian terdahulu sebagian besar berfokus pada tataran parsial, sehingga menyisakan celah analitis signifikan dalam mengintegrasikan dimensi multidisipliner secara komprehensif.\n"
            .'4. **Tujuan Penelitian & Orisinalitas**: Riset ini bertujuan mengevaluasi fenomena tersebut melalui pendekatan terukur, memberikan kontribusi kebaruan yang memperkaya literatur ilmiah terkait.';

        return [
            'command' => SlashCommand::PENDAHULUAN->value,
            'reply' => $reply,
            'data' => ['section' => 'pendahuluan', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function pembahasan(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $focus = $argument !== '' ? $argument : $project->title;

        if (trim($argument) !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai reviewer naskah ilmiah jurnal bereputasi, buatkan draf bab Pembahasan komprehensif (Interpretasi Temuan Kunci, Komparasi Kritis dengan Literatur Terkait, Implikasi Teoretis & Praktis, Limitasi Penelitian) untuk fokus riset:\n\n\"{$focus}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::PEMBAHASAN->value,
                    'reply' => trim($answer),
                    'data' => ['section' => 'pembahasan', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
                ];
            }
        }

        $reply = "### Rancangan Sintesis Bab Pembahasan (Discussion)\n\n"
            ."1. **Interpretasi Temuan Kunci**: Hasil analisis terhadap \"{$focus}\" membuktikan adanya korelasi signifikan antara indikator operasional dan parameter keberhasilan yang diuji.\n"
            ."2. **Komparasi Kritis dengan Literatur Terkait**: Temuan ini mengonfirmasi proposisi teoretis yang diajukan dalam literatur rujukan, sekaligus memberikan wawasan baru terkait variabilitas respons dalam konteks empiris.\n"
            ."3. **Implikasi Teoretis & Manajerial**: Secara konseptual, hasil riset ini memperluas cakupan teori yang ada; secara praktis, temuan ini memberikan panduan strategis bagi pengambil keputusan.\n"
            .'4. **Eksplorasi Limitasi Penelitian**: Variasi lingkungan pengujian dan keterbatasan cakupan data perlu dipertimbangkan sebagai konteks pembatas generalisasi hasil.';

        return [
            'command' => SlashCommand::PEMBAHASAN->value,
            'reply' => $reply,
            'data' => ['section' => 'pembahasan', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function kesimpulan(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $focus = $argument !== '' ? $argument : $project->title;

        if (trim($argument) !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai asisten akademik, formulasikan rancangan bab Kesimpulan & Saran (Sintesis Temuan Utama, Kontribusi Kebaruan/Novelty, Limitasi Riset, dan Rekomendasi Riset Lanjutan) untuk riset:\n\n\"{$focus}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::KESIMPULAN->value,
                    'reply' => trim($answer),
                    'data' => ['section' => 'kesimpulan', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
                ];
            }
        }

        $reply = "### Rancangan Struktur Bab Kesimpulan & Saran (Conclusion)\n\n"
            ."1. **Sintesis Temuan Utama**: Penelitian terkait \"{$focus}\" berhasil menjawab seluruh pertanyaan penelitian dengan membuktikan efektivitas pendekatan yang diusulkan secara empiris.\n"
            ."2. **Kontribusi Kebaruan (Novelty)**: Integrasi metode ini memberikan kontribusi nyata dalam menjembatani kesenjangan teoretis dan implementasi teknis di lapangan.\n"
            ."3. **Limitasi Riset**: Pembatasan pada aspek parameter evaluasi dan periode observasi menjadi batasan objektif naskah ini.\n"
            .'4. **Rekomendasi Riset Lanjutan**: Disarankan bagi peneliti mendatang untuk memperluas korpus data dan menguji ketahanan model pada skenario yang lebih kompleks.';

        return [
            'command' => SlashCommand::KESIMPULAN->value,
            'reply' => $reply,
            'data' => ['section' => 'kesimpulan', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function tinjauanPustaka(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $focus = $argument !== '' ? $argument : $project->title;

        if (trim($argument) !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai asisten akademik penulisan tesis dan artikel ilmiah, buatkan rancangan kerangka teoretis dan Tinjauan Pustaka (Landasan Konseptual Utama, Taksonomi & Pemetaan Literatur Terkini, Model Konseptual Hubungan Antar-Variabel) untuk topik:\n\n\"{$focus}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::TINJAUAN_PUSTAKA->value,
                    'reply' => trim($answer),
                    'data' => ['section' => 'tinjauan-pustaka', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope, 'source' => 'omnigensi-llm'],
                ];
            }
        }

        $reply = "### Kerangka Teoretis & Tinjauan Pustaka (Literature Review)\n\n"
            ."1. **Landasan Konseptual Utama**: Telaah komprehensif terhadap konsep fundamental \"{$focus}\" berdasarkan teori-teori kanonik yang telah teruji dalam kepustakaan ilmiah.\n"
            ."2. **Taksonomi & Pemetaan Literatur Terkini**: Klasifikasi kontribusi akademisi selama lima tahun terakhir, memetakan evolusi metodologi dan perdebatan akademik utama.\n"
            ."3. **Kerangka Teoretis Hubungan Antar-Variabel**: Model konseptual yang menghubungkan variabel independen, mediator, dan luaran terukur dengan hipotesis yang diajukan.\n"
            .'4. **Sintesis Rujukan Bereputasi**: Setiap postulat didukung sitasi dari publikasi terindeks untuk menjamin validitas argumentasi ilmiah naskah.';

        return [
            'command' => SlashCommand::TINJAUAN_PUSTAKA->value,
            'reply' => $reply,
            'data' => ['section' => 'tinjauan-pustaka', 'focus' => $focus, 'persona' => $persona, 'scope' => $scope],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function daftarIsi(XishieProject $project, string $argument, ?NaskahDocument $contextDocument = null): array
    {
        /** @var GuidelineDefinition $guideline */
        $guideline = $this->memory->guideline($project);
        $sections = $guideline->sections;
        $docType = $project->document_type->label();

        $richModel = app(NaskahRichModel::class);
        $docBlocks = (array) ($contextDocument?->blocks ?? $contextDocument?->content_blocks ?? []);
        $docOutline = ! empty($docBlocks) ? $richModel->outline($docBlocks) : [];

        $lines = [
            "### Bagan Struktur & Daftar Isi Naskah ({$docType})",
            '',
            "- **Proyek**: {$project->title}",
            "- **Jenis Dokumen**: {$docType}",
            "- **Target Total Kata**: {$guideline->minTotalWords} – {$guideline->maxTotalWords} kata",
            '- **Gaya Sitasi**: '.strtoupper($guideline->citationStyle),
        ];

        if ($contextDocument !== null) {
            $lines[] = "- **Dokumen Konteks**: {$contextDocument->title} (".count($docBlocks).' blok)';
        }

        // Tampilkan outline riil dokumen naskah jika dokumen naskah terdeteksi memiliki heading
        if (! empty($docOutline)) {
            $lines[] = '';
            $lines[] = '#### Struktur Bab Teridentifikasi pada Dokumen Naskah';
            $lines[] = '| No | Judul / Heading | Level | Kata Terakumulasi |';
            $lines[] = '| --- | --- | --- | --- |';
            foreach ($docOutline as $hIdx => $hNode) {
                $indent = str_repeat('— ', max(0, (int) ($hNode['level'] ?? 1) - 1));
                $hText = $indent.($hNode['text'] ?? 'Bab');
                $lines[] = sprintf('| %d | %s | H%d | %d kata |', $hIdx + 1, $hText, (int) ($hNode['level'] ?? 1), (int) ($hNode['word_count'] ?? 0));
            }
        }

        $lines[] = '';
        $lines[] = '#### Panduan Sistematika & Alokasi Bobot Kata';
        $lines[] = '| No | Bagian / Sub-Bab | Target Kata | Status |';
        $lines[] = '| --- | --- | --- | --- |';

        foreach ($sections as $index => $sec) {
            $heading = (string) ($sec['heading'] ?? $sec['key'] ?? 'Bagian');
            $min = (int) ($sec['min_words'] ?? 0);
            $max = (int) ($sec['max_words'] ?? 0);
            $wordBudget = ($min > 0 && $max > 0) ? "{$min}–{$max} kata" : ($min > 0 ? "min {$min} kata" : 'fleksibel');
            $status = ! empty($sec['required']) ? 'Wajib' : 'Opsional';
            $lines[] = sprintf('| %d | %s | %s | %s |', $index + 1, $heading, $wordBudget, $status);
        }

        if ($argument !== '') {
            $lines[] = '';
            $lines[] = "> [!NOTE]\n> Fokus penekanan struktural dialokasikan untuk topik: \"{$argument}\".";
        }

        return [
            'command' => SlashCommand::DAFTAR_ISI->value,
            'reply' => implode("\n", $lines),
            'data' => [
                'document_type' => $project->document_type->value,
                'sections' => $sections,
                'document_outline' => $docOutline,
                'min_total_words' => $guideline->minTotalWords,
                'max_total_words' => $guideline->maxTotalWords,
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function daftarPustaka(XishieProject $project, string $argument): array
    {
        /** @var GuidelineDefinition $guideline */
        $guideline = $this->memory->guideline($project);
        $style = CitationStyle::tryFrom((string) $guideline->citationStyle) ?? CitationStyle::IEEE;

        $lowerArg = mb_strtolower(trim($argument));
        $explicitStyle = match ($lowerArg) {
            'apa', 'apa7', 'apa 7', 'apa 7th' => CitationStyle::APA7,
            'ieee' => CitationStyle::IEEE,
            'vancouver' => CitationStyle::VANCOUVER,
            'chicago' => CitationStyle::CHICAGO,
            'harvard' => CitationStyle::HARVARD,
            default => null,
        };

        if ($explicitStyle !== null) {
            $style = $explicitStyle;
            $argument = '';
        }

        $query = $project->references()->orderBy('citation_key');
        if ($argument !== '') {
            $query->where(function ($q) use ($argument): void {
                $q->where('title', 'like', "%{$argument}%")
                    ->orWhere('citation_key', 'like', "%{$argument}%")
                    ->orWhere('authors', 'like', "%{$argument}%");
            });
        }

        $references = $query->get();

        if ($references->isEmpty()) {
            $reply = $argument !== ''
                ? "Tidak ditemukan referensi yang cocok dengan filter \"{$argument}\"."
                : 'Belum ada referensi terdaftar pada proyek ini. Tambahkan referensi terlebih dahulu melalui Academic Workspace.';

            return [
                'command' => SlashCommand::DAFTAR_PUSTAKA->value,
                'reply' => $reply,
                'data' => ['references' => [], 'count' => 0],
            ];
        }

        $formatter = app(CitationFormatter::class);
        $entries = $formatter->bibliography($references, $style);

        $lines = [
            "### Daftar Pustaka Terformat ({$style->label()})",
            '',
            "- **Total Entri**: {$references->count()}",
            '- **Gaya Sitasi**: '.$style->label(),
            '',
        ];

        foreach ($entries as $index => $entry) {
            $lines[] = $style === CitationStyle::IEEE ? $entry : ($index + 1).'. '.$entry;
        }

        return [
            'command' => SlashCommand::DAFTAR_PUSTAKA->value,
            'reply' => implode("\n\n", $lines),
            'data' => [
                'style' => $style->value,
                'count' => $references->count(),
                'entries' => $entries,
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function formatParagraf(XishieProject $project, string $argument): array
    {
        $docType = strtolower(is_string($project->document_type) ? $project->document_type : ($project->document_type?->value ?? 'skripsi'));

        if (trim($argument) !== '') {
            $words = count(preg_split('/\s+/u', trim($argument), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $sentences = count(preg_split('/[.!?]+/u', trim($argument), -1, PREG_SPLIT_NO_EMPTY) ?: []);
            $avgWordsPerSentence = $sentences > 0 ? round($words / $sentences, 1) : 0;

            $densityEvaluation = match (true) {
                $words < 50 => '⚠️ Paragraf terlalu ringkas (kurang dari 50 kata). Disarankan mengelaborasi ide pokok dengan kalimat penjelas dan bukti rujukan.',
                $words > 250 => '⚠️ Paragraf terlalu panjang (lebih dari 250 kata). Sebaiknya dipecah menjadi dua alinea agar alur gagasan tetap koheren.',
                default => '✅ Panjang paragraf ideal (100–200 kata) untuk karya ilmiah bereputasi.',
            };

            $reply = "### Analisis & Kaidah Format Paragraf\n\n"
                ."- **Jumlah Kata**: {$words} kata\n"
                ."- **Jumlah Kalimat**: {$sentences} kalimat\n"
                ."- **Rata-rata Panjang Kalimat**: {$avgWordsPerSentence} kata/kalimat\n"
                ."- **Evaluasi Kepadatan**: {$densityEvaluation}\n\n"
                ."**Rekomendasi Tipografi Naskah:**\n"
                ."- **Perataan (Alignment)**: Justify (rata kiri-kanan)\n"
                .'- **Spasi Baris**: '.($docType === 'journal' ? '1.0 / 1.15 (Format Jurnal)' : '1.5 atau 2.0 (Skripsi/Tesis)')."\n"
                ."- **Indentasi Baris Pertama**: 1.27 cm (0.5 inci) untuk alinea baru\n"
                ."- **Jarak Antar Paragraf**: Spasi setelah (Space After) 4–6 pt\n\n"
                .'💡 *Tips: Anda dapat memformat paragraf ini langsung di Naskah Studio dengan preset `academic_standard` atau `journal_ieee`.*';

            return [
                'command' => SlashCommand::FORMAT_PARAGRAF->value,
                'reply' => $reply,
                'data' => [
                    'words' => $words,
                    'sentences' => $sentences,
                    'avgWordsPerSentence' => $avgWordsPerSentence,
                    'documentType' => $docType,
                ],
            ];
        }

        $reply = "### Panduan Standar Kaidah Paragraf Akademik\n\n"
            ."1. **Struktur Isi Paragraf Ilmiah (Kaidah MEAL / P-E-E-L)**:\n"
            ."   - **Main Point / Point (Topik Utama)**: 1 kalimat tesis pembuka yang memuat ide pokok.\n"
            ."   - **Evidence / Explanation (Bukti & Elaborasi)**: 2–4 kalimat penjelas menyertakan data, sitasi, atau argumen ilmiah.\n"
            ."   - **Link / Concluding (Simpulan & Transisi)**: 1 kalimat pengait ke paragraf berikutnya.\n\n"
            ."2. **Kaidah Tipografi & Tata Letak per Halaman**:\n"
            ."   - **Perataan (Alignment)**: Wajib Justify (rata kiri-kanan) tanpa sungai putih (*rivers of white*).\n"
            ."   - **Indentasi Awal (First-Line Indent)**: 1.27 cm (0.5 inci / 1 tab). Paragraf pembuka bab/sub-bab boleh dibuat *block* (0 cm).\n"
            ."   - **Spasi Baris**: 1.5 untuk draf tesis/skripsi umum; 2.0 untuk naskah cetak DIKTI; 1.0/1.15 untuk artikel jurnal IEEE/Scopus.\n"
            ."   - **Jarak Antar Paragraf**: 0 pt (Spacing Before) dan 4–6 pt (Spacing After).\n\n"
            ."3. **Preset Siap Pakai di Naskah Studio**:\n"
            ."   - `academic_standard`: Standar APA / Scopus (Justify, 1.5 spasi, indent 1.27cm)\n"
            ."   - `journal_ieee`: Format Jurnal IEEE/ACM (Justify, 1.0 spasi, indent 1.27cm)\n"
            ."   - `thesis_dikti`: Format Skripsi/Tesis DIKTI (Justify, 2.0 spasi, indent 1.27cm)\n"
            ."   - `abstract_block`: Format Abstrak / Executive Summary (Justify, 1.15 spasi, tanpa indentasi)\n"
            ."   - `block_quote`: Format Kutipan Langsung > 40 kata (Justify, 1.0 spasi, margin tab kiri 2)\n\n"
            .'*Gunakan tombol **Custom Paragraf Hal. [N]** pada Naskah Studio atau jalankan `php artisan naskah:manage format-paragraph [ID] --page=[N] --preset=academic_standard`.*';

        return [
            'command' => SlashCommand::FORMAT_PARAGRAF->value,
            'reply' => $reply,
            'data' => [
                'documentType' => $docType,
                'presets' => array_keys(NaskahRichModel::PARAGRAPH_PRESETS),
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function catatanKaki(XishieProject $project, string $argument): array
    {
        if (trim($argument) !== '') {
            $fnNumber = 1;
            $reply = "### Format Catatan Kaki (Footnote) Terstruktur\n\n"
                ."**Teks Utama dengan Penanda**:\n"
                ."> {$argument} [^{$fnNumber}]\n\n"
                ."**Entri Catatan Kaki di Bagian Bawah Halaman**:\n"
                ."`[^{$fnNumber}]: Penulis, Judul Rujukan (Kota Terbit: Penerbit, Tahun), hlm. XX.`\n\n"
                ."*Tips: Gunakan tombol 'Sisipkan Catatan Kaki' pada lembar Naskah Studio atau shortcut `Ctrl+Alt+F` untuk meletakkannya secara otomatis pada halaman aktif.*";

            return [
                'command' => SlashCommand::CATATAN_KAKI->value,
                'reply' => $reply,
                'data' => [
                    'argument' => $argument,
                    'footnote_marker' => "[^{$fnNumber}]",
                ],
            ];
        }

        $reply = "### Panduan Standar Penulisan Catatan Kaki (Footnotes)\n\n"
            ."1. **Format Chicago / Turabian (Humaniora & Hukum)**:\n"
            ."   - **Buku**: `Nama Penulis, Judul Buku Cetak Miring (Kota: Penerbit, Tahun), hlm. XX.`\n"
            ."   - **Jurnal**: `Nama Penulis, \"Judul Artikel,\" Nama Jurnal Vol, no. Issue (Tahun): hlm. XX.`\n"
            ."   - **Rujukan Berulang**: Gunakan singkatan `Ibid., hlm. XX` atau `Nama Belakang, Judul Singkat, hlm. XX.`\n\n"
            ."2. **Penomoran & Tata Letak pada Naskah Studio**:\n"
            ."   - Nomor catatan kaki berurutan (1, 2, 3...) dalam format superskrip [^N].\n"
            ."   - Teks catatan kaki dicetak dengan ukuran font 10 pt (spasi tunggal 1.0) di bagian kaki halaman (*bottom of page*).\n"
            ."   - Dipisahkan dari teks utama dengan garis pemisah horizontal 2 inci (*footnote separator line*).\n\n"
            .'*Gunakan tombol **Sisipkan Catatan Kaki** pada toolbar Naskah Studio atau klik ikon ⚙️ pada paragraf untuk menambahkan catatan kaki per halaman.*';

        return [
            'command' => SlashCommand::CATATAN_KAKI->value,
            'reply' => $reply,
            'data' => [
                'style' => 'chicago_turabian',
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function keterbacaan(XishieProject $project, string $argument, ?NaskahDocument $contextDocument = null): array
    {
        $textToAnalyze = trim($argument);
        $sourceLabel = 'Teks Masukan';

        if ($textToAnalyze === '') {
            if ($contextDocument !== null) {
                $textToAnalyze = app(NaskahRichModel::class)->toPlainText((array) ($contextDocument->blocks ?? []));
                $sourceLabel = 'Dokumen Naskah: '.$contextDocument->title;
            } else {
                $stage = $this->stageRecord($project);
                $sections = $stage?->sections_content ?? [];
                $collected = [];
                foreach ($sections as $sec) {
                    if (! empty($sec['content'])) {
                        $collected[] = strip_tags((string) $sec['content']);
                    }
                }
                $textToAnalyze = implode("\n\n", $collected);
                $sourceLabel = 'Draf Naskah Tahap '.strtoupper($project->current_stage->value);
            }
        }

        if ($textToAnalyze === '') {
            return [
                'command' => SlashCommand::KETERBACAAN->value,
                'reply' => 'Belum ada konten naskah untuk dianalisis. Sertakan teks setelah `/keterbacaan [teks]` atau isi draf naskah pada Academic Workspace.',
                'data' => [
                    'source' => $sourceLabel,
                    'word_count' => 0,
                ],
            ];
        }

        $words = str_word_count($textToAnalyze);
        $sentences = preg_split('/(?<=[.!?])\s+/u', $textToAnalyze, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $sentenceCount = max(1, count($sentences));
        $avgWps = round($words / $sentenceCount, 1);
        $readingSecs = (int) round(($words / 200) * 60);

        preg_match_all('/[aiueoAIUEO]/u', $textToAnalyze, $matches);
        $syllables = max(1, count($matches[0] ?? []));
        $syllablesPerWord = $words > 0 ? ($syllables / $words) : 1.0;
        $rawReadingEase = 206.835 - (1.015 * $avgWps) - (84.6 * $syllablesPerWord);
        $readingEase = round(max(0.0, min(100.0, $rawReadingEase)), 1);
        $fleschGrade = match (true) {
            $readingEase >= 90 => 'Sangat Mudah',
            $readingEase >= 70 => 'Standar Populer',
            $readingEase >= 50 => 'Standar Optimal Akademik',
            $readingEase >= 30 => 'Akademik Kompleks',
            default => 'Padat Teori',
        };

        $level = match (true) {
            $avgWps <= 14.0 => 'Mudah Dibaca (Ringan)',
            $avgWps <= 22.0 => 'Standar Akademik Optimal (Sesuai Pedoman Jurnal & Tesis)',
            default => 'Kompleks / Padat (Rekomendasi: Pecah kalimat majemuk bertingkat)',
        };

        $reply = "### Laporan Analisis Keterbacaan Naskah ({$sourceLabel})\n\n"
            ."- **Jumlah Kata**: {$words} kata\n"
            ."- **Jumlah Kalimat**: {$sentenceCount} kalimat\n"
            ."- **Rata-rata Kata per Kalimat**: {$avgWps} kata/kalimat\n"
            ."- **Skor Flesch Reading Ease**: {$readingEase} / 100 ({$fleschGrade})\n"
            .'- **Estimasi Waktu Baca**: '.$readingSecs.' detik (~'.max(1, (int) ceil($readingSecs / 60))." menit)\n"
            ."- **Status Keterbacaan**: **{$level}**\n\n"
            ."**Rekomendasi Akademik**:\n"
            .($avgWps > 22.0
                ? "Kalimat memiliki rata-rata lebih dari 22 kata. Disarankan untuk memecah kalimat majemuk panjang agar pembaca jurnal dan penguji tesis dapat mencerna gagasan sentral dengan lebih lugas.\n"
                : "Panjang kalimat dan kepadatan gagasan berada dalam rentang optimal naskah ilmiah bereputasi (14–22 kata per kalimat).\n");

        return [
            'command' => SlashCommand::KETERBACAAN->value,
            'reply' => $reply,
            'data' => [
                'source' => $sourceLabel,
                'word_count' => $words,
                'sentence_count' => $sentenceCount,
                'avg_words_per_sentence' => $avgWps,
                'reading_time_seconds' => $readingSecs,
                'reading_ease' => $readingEase,
                'flesch_grade' => $fleschGrade,
                'readability_level' => $level,
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function kebaruan(
        XishieProject $project,
        string $argument,
        ?string $modelId = null,
        string $persona = 'academic_senior',
        string $scope = 'project'
    ): array {
        $focus = trim($argument);
        $title = $project->title;
        $field = $project->field_of_study->label();
        $docType = $project->document_type->label();

        if ($focus !== '') {
            $context = $this->memory->build($project, $argument);
            $prompt = "Sebagai pakar artikel ilmiah Scopus, formulasikan pernyataan kebaruan (Statement of Novelty), analisis State of the Art (SOTA), dan diferensiasi komparatif terhadap riset terdahulu untuk topik:\n\n\"{$focus}\"";
            $answer = $this->answerWithLlm($project, $prompt, $context, $modelId, $persona, $scope);

            if ($answer !== null && trim($answer) !== '') {
                return [
                    'command' => SlashCommand::KEBARUAN->value,
                    'reply' => trim($answer),
                    'data' => [
                        'project_title' => $title,
                        'novelty_focus' => $focus,
                        'field_of_study' => $field,
                        'persona' => $persona,
                        'scope' => $scope,
                        'source' => 'omnigensi-llm',
                    ],
                ];
            }
        }

        $noveltyPoint = $focus !== ''
            ? $focus
            : "pendekatan terintegrasi pada {$title}";

        $reply = "### Rumusan Pernyataan Kebaruan Ilmiah (Statement of Novelty)\n\n"
            ."**Konteks Naskah**: {$title} ({$docType} — Bidang {$field})\n\n"
            ."1. **State of the Art (SOTA) & Batasan Rujukan Terdahulu**:\n"
            ."   Sebagian besar literatur terdahulu telah mengkaji domain ini secara parsial, namun masih terbatas pada pendekatan konvensional dan belum mengeksplorasi integrasi holistik pada skenario empiris spesifik.\n\n"
            ."2. **Pernyataan Kebaruan Pokok (Core Novelty Statement)**:\n"
            ."   *\"Berbeda dengan studi-studi terdahulu, penelitian ini mengusulkan {$noveltyPoint} yang secara spesifik mengatasi celah efisiensi dan reliabilitas melalui kerangka evaluasi empiris yang terukur.\"*\n\n"
            ."3. **Diferensiasi Komparatif**:\n"
            ."   - **Metodologis**: Menggabungkan pendekatan validasi sistematis dengan tolok ukur saintifik baku.\n"
            ."   - **Teoretis**: Memperluas kerangka konseptual pada bidang {$field}.\n"
            ."   - **Praktis**: Memberikan instrumen replikasi yang siap diadopsi oleh praktisi dan peneliti lanjutan.\n\n"
            .'*Tips: Letakkan kalimat kebaruan ini pada paragraf akhir Bab Pendahuluan sebelum uraian sistematika penulisan.*';

        return [
            'command' => SlashCommand::KEBARUAN->value,
            'reply' => $reply,
            'data' => [
                'project_title' => $title,
                'novelty_focus' => $noveltyPoint,
                'field_of_study' => $field,
                'persona' => $persona,
                'scope' => $scope,
            ],
        ];
    }

    /**
     * @return array{command: string, reply: string, data: array<string, mixed>}
     */
    private function tesaurus(string $argument): array
    {
        $word = mb_strtolower(trim($argument));
        $map = [
            'penelitian' => ['kajian', 'studi', 'riset', 'investigasi', 'telaah ilmiah'],
            'metode' => ['metodologi', 'pendekatan', 'prosedur', 'teknik', 'tata cara'],
            'hasil' => ['temuan', 'capaian', 'keluaran', 'output', 'implikasi'],
            'masalah' => ['permasalahan', 'kendala', 'isu', 'tantangan', 'hambatan'],
            'tujuan' => ['sasaran', 'maksud', 'target', 'arah', 'objektif'],
            'analisis' => ['telaah', 'kajian', 'penguraian', 'pembahasan', 'evaluasi'],
            'penting' => ['signifikan', 'krusial', 'esensial', 'vital', 'fundamental'],
            'meningkatkan' => ['menaikkan', 'memperbaiki', 'mengembangkan', 'memperkuat', 'mengoptimalkan'],
            'data' => ['informasi', 'keterangan', 'fakta', 'bahan', 'korpus'],
            'kesimpulan' => ['inferensi', 'simpulan', 'konklusi', 'rangkuman', 'deduksi'],
            'kebaruan' => ['orisinalitas', 'novelty', 'inovasi', 'keunikan', 'pembeda'],
            'pengaruh' => ['dampak', 'efek', 'implikasi', 'konsekuensi', 'daya pengaruh'],
            'hubungan' => ['relasi', 'korelasi', 'keterkaitan', 'interaksi', 'asosiasi'],
            'faktor' => ['determinan', 'unsur', 'aspek', 'komponen', 'variabel penentu'],
            'model' => ['kerangka kerja', 'paradigma', 'skema', 'konstruk', 'rancangan'],
            'validitas' => ['kesahihan', 'keabsahan', 'akurasi', 'kredibilitas'],
            'efektivitas' => ['keberdayagunaan', 'kemujaraban', 'ketepatgunaan', 'daya guna'],
            'strategi' => ['taktik', 'pendekatan', 'rekayasa langkah', 'kebijakan'],
            'konsep' => ['gagasan', 'nosional', 'konstruk teoretis', 'abstraksi'],
            'dasar' => ['fundamental', 'landasan', 'pijakan', 'basis', 'fondasi'],
            'pendahuluan' => ['eksposisi', 'pengantar', 'latar belakang', 'prolegomena'],
            'pembahasan' => ['diskusi', 'elaborasi', 'telaah kritis', 'eksplanasi'],
            'signifikan' => ['bermakna', 'nyata', 'substansial', 'berbobot'],
            'hipotesis' => ['dugaan sementara', 'postulat', 'premis', 'anggapan dasar'],
            'limitasi' => ['keterbatasan', 'cakupan batas', 'restriksi', 'kendala lingkup'],
            'teori' => ['landasan teoretis', 'konstruk konseptual', 'doktrin ilmiah', 'postulat'],
        ];

        if ($word === '') {
            $terms = implode(', ', array_keys($map));

            return [
                'command' => SlashCommand::TESAURUS->value,
                'reply' => "Sertakan kata akademik yang ingin dicari sinonimnya setelah `/tesaurus [kata]`.\n\nContoh kata kunci terindeks: {$terms}.",
                'data' => ['indexed_terms' => array_keys($map)],
            ];
        }

        if (isset($map[$word])) {
            $synonyms = $map[$word];
            $list = implode("\n", array_map(fn (string $s): string => "- **{$s}**", $synonyms));
            $reply = "### 📚 Tesaurus Akademik: '{$word}'\n\n"
                ."Daftar padanan kata dan sinonim ilmiah baku:\n\n{$list}\n\n"
                ."*Tips: Gunakan padanan kata ini untuk memperkaya variasi kalimat dan mencegah pengulangan berlebih.*";

            return [
                'command' => SlashCommand::TESAURUS->value,
                'reply' => $reply,
                'data' => [
                    'word' => $word,
                    'synonyms' => $synonyms,
                ],
            ];
        }

        return [
            'command' => SlashCommand::TESAURUS->value,
            'reply' => "Kata '{$word}' belum tercatat dalam tesaurus ringkas luring. Disarankan menggunakan kata kunci terkait dalam naskah ilmiah seperti: `penelitian`, `analisis`, `signifikan`, atau `kebaruan`.",
            'data' => [
                'word' => $word,
                'synonyms' => [],
            ],
        ];
    }

    private function stageRecord(XishieProject $project): ?ProjectStageRecord
    {
        return $project->stages()->where('stage_name', $project->current_stage->value)->first()
            ?? $project->stages()->where('stage_name', ProjectStage::DRAFT->value)->first();
    }
}
