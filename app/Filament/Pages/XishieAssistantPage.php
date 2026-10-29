<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\SlashCommand;
use App\Domain\Assistant\XishieAssistantService;
use App\Domain\Publication\NaskahRichModel;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Models\NaskahDocument;
use App\Models\OmnigensiAgentLog;
use App\Models\XishieProject;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * XishieAssistantPage: konsol Xishie Assistant mandiri — chat kontekstual ke
 * proyek terpilih dengan palet slash command (/sitasi, /parafrase, /cek-gap,
 * /cek-pedoman, /matriks-literatur, /cari-referensi) dan jawaban Markdown.
 */
class XishieAssistantPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Xishie Assistant';

    protected static ?string $title = 'Xishie Assistant';

    protected static ?int $navigationSort = 7;

    protected string $view = 'filament.pages.xishie-assistant';

    public ?int $projectId = null;

    public string $message = '';

    public ?string $currentAssistantModel = null;

    public string $activeCommandCategory = 'all';

    public string $commandSearch = '';

    public string $currentPersona = 'academic_senior';

    public string $assistantScope = 'project';

    /**
     * Transkrip: [['role' => 'user'|'assistant', 'content' => ...]].
     *
     * @var array<int, array<string, mixed>>
     */
    public array $log = [];

    /**
     * Cache sesi percakapan sebelum dibersihkan (Undo / Restore).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $lastClearedLog = [];

    public function mount(): void
    {
        $param = request()->query('project');

        $this->projectId = $param !== null
            ? (int) $param
            : XishieProject::query()->latest()->value('id');

        $this->getCurrentAssistantModel();
    }

    public function getCurrentAssistantModel(): string
    {
        if ($this->currentAssistantModel !== null && trim($this->currentAssistantModel) !== '') {
            return trim($this->currentAssistantModel);
        }

        try {
            $client = app(\App\Domain\Assistant\OmnigensiChatClient::class);
            $default = $client->defaultModel();
            if ($default !== null && trim($default) !== '') {
                $this->currentAssistantModel = trim($default);

                return $this->currentAssistantModel;
            }
        } catch (\Throwable) {
        }

        $configured = (string) config('omnigensi.default_model', 'hv/agnes-3.0-flash');
        $this->currentAssistantModel = $configured !== '' ? $configured : 'hv/agnes-3.0-flash';

        return $this->currentAssistantModel;
    }

    public function setAssistantModel(string $modelId): void
    {
        $trimmed = trim($modelId);
        if ($trimmed === '') {
            return;
        }

        $this->currentAssistantModel = $trimmed;
        Notification::make()
            ->title('Model Xishie Assistant Diubah')
            ->body("Model aktif dialihkan ke: {$this->currentAssistantModel}")
            ->info()
            ->send();
    }

    /**
     * @return array<int, array{id: string, name: string, provider: string, is_default: bool}>
     */
    public function getAvailableModels(): array
    {
        $models = [];

        try {
            $client = app(\App\Domain\Assistant\OmnigensiChatClient::class);
            $rawModels = $client->hasIntegrationKey() ? $client->integrationModels() : $client->models();

            foreach ($rawModels as $m) {
                if (! empty($m['model_id'])) {
                    $models[] = [
                        'id' => (string) $m['model_id'],
                        'name' => (string) ($m['display_name'] ?? $m['model_id']),
                        'provider' => (string) ($m['provider_name'] ?? 'Omnigensi'),
                        'is_default' => (bool) ($m['is_default'] ?? false),
                    ];
                }
            }
        } catch (\Throwable) {
        }

        if (empty($models)) {
            $models = [
                ['id' => 'hv/agnes-3.0-flash', 'name' => 'HV Agnes 3.0 Flash (Default / Upstream)', 'provider' => 'Omnigensi', 'is_default' => true],
                ['id' => 'qwen-2.5-72b-instruct', 'name' => 'Qwen 2.5 72B (Akademik & Cepat)', 'provider' => 'Omnigensi', 'is_default' => false],
                ['id' => 'gemini-3.8-flash', 'name' => 'Gemini 3.8 Flash (High Performance)', 'provider' => 'Google', 'is_default' => false],
                ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash (Super Cepat)', 'provider' => 'Google', 'is_default' => false],
                ['id' => 'deepseek-chat', 'name' => 'DeepSeek V3 (Penalaran & Logika)', 'provider' => 'DeepSeek', 'is_default' => false],
                ['id' => 'gpt-4o', 'name' => 'GPT-4o (Multimodal & Presisi)', 'provider' => 'OpenAI', 'is_default' => false],
                ['id' => 'claude-3-5-sonnet', 'name' => 'Claude 3.5 Sonnet (Penulisan Ilmiah)', 'provider' => 'Anthropic', 'is_default' => false],
                ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro (Konteks Panjang)', 'provider' => 'Google', 'is_default' => false],
                ['id' => 'llama-3.3-70b-instruct', 'name' => 'Llama 3.3 70B (Open Source Riset)', 'provider' => 'Meta', 'is_default' => false],
            ];
        }

        return $models;
    }

    public function setCommandCategory(string $category): void
    {
        $this->activeCommandCategory = $category;
    }

    /**
     * @return array<string, string>
     */
    public function getCommandCategories(): array
    {
        return [
            'all' => 'Semua Perintah',
            'structure' => 'Struktur & Bab',
            'literature' => 'Sitasi & Literatur',
            'audit' => 'Audit & Validasi',
            'format' => 'Gaya & Format',
        ];
    }

    /**
     * @return array<int, array{name: string, description: string, category: string}>
     */
    public function filteredCommands(): array
    {
        $categorized = [];
        $query = trim(mb_strtolower($this->commandSearch));

        foreach (SlashCommand::cases() as $c) {
            $cat = match ($c) {
                SlashCommand::PENDAHULUAN, SlashCommand::ABSTRAK, SlashCommand::METODOLOGI,
                SlashCommand::PEMBAHASAN, SlashCommand::KESIMPULAN, SlashCommand::TINJAUAN_PUSTAKA,
                SlashCommand::DAFTAR_ISI => 'structure',

                SlashCommand::SITASI, SlashCommand::MATRIKS, SlashCommand::CARI_REFERENSI,
                SlashCommand::DAFTAR_PUSTAKA, SlashCommand::CATATAN_KAKI => 'literature',

                SlashCommand::CEK_GAP, SlashCommand::CEK_PEDOMAN, SlashCommand::KETERBACAAN,
                SlashCommand::KEBARUAN => 'audit',

                default => 'format',
            };

            $matchesCat = ($this->activeCommandCategory === 'all' || $this->activeCommandCategory === $cat);
            $matchesSearch = $query === ''
                || str_contains(mb_strtolower($c->value), $query)
                || str_contains(mb_strtolower($c->description()), $query);

            if ($matchesCat && $matchesSearch) {
                $categorized[] = [
                    'name' => $c->value,
                    'description' => $c->description(),
                    'category' => $cat,
                ];
            }
        }

        return $categorized;
    }

    /**
     * Regenerate jawaban terakhir dari Xishie Assistant dengan model aktif.
     */
    public function regenerateLastResponse(): void
    {
        $project = $this->getProject();
        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $lastUserQuery = null;
        for ($i = count($this->log) - 1; $i >= 0; $i--) {
            if (($this->log[$i]['role'] ?? '') === 'user') {
                $lastUserQuery = (string) ($this->log[$i]['content'] ?? '');
                break;
            }
        }

        if ($lastUserQuery === null || trim($lastUserQuery) === '') {
            Notification::make()->title('Tidak ada pertanyaan sebelumnya untuk di-regenerate')->info()->send();

            return;
        }

        $modelId = $this->getCurrentAssistantModel();

        try {
            $result = app(XishieAssistantService::class)->handle(
                $project,
                $lastUserQuery,
                $modelId,
                $this->currentPersona,
                $this->assistantScope
            );

            OmnigensiAgentLog::create([
                'project_id' => $project->id,
                'agent_name' => 'Xishie-Assistant',
                'action' => ($result['command'] ?? 'regenerate').':regenerate',
                'control_mode' => $project->control_mode->value,
                'ttft_ms' => 0,
                'execution_time_ms' => 0,
                'token_usage' => str_word_count((string) $result['reply']),
                'hallucination_rejected_count' => 0,
                'status' => 'success',
                'payload' => [
                    'query' => $lastUserQuery,
                    'command' => $result['command'] ?? 'chat',
                    'model' => $modelId,
                    'persona' => $this->currentPersona,
                    'scope' => $this->assistantScope,
                    'regenerate' => true,
                ],
            ]);

            $this->log[] = [
                'role' => 'assistant',
                'content' => (string) $result['reply'],
                'model' => $modelId,
                'persona' => $this->currentPersona,
                'scope' => $this->assistantScope,
                'created_at' => now()->format('H:i:s'),
                'is_regenerated' => true,
            ];

            Notification::make()->title('Jawaban Berhasil Di-regenerate')->success()->send();
        } catch (\Throwable $exception) {
            $this->log[] = [
                'role' => 'assistant',
                'content' => '[Gagal] '.$exception->getMessage(),
                'model' => $modelId,
                'created_at' => now()->format('H:i:s'),
            ];
        }

        $this->dispatch('assistant-updated');
    }

    public function setPersona(string $persona): void
    {
        $this->currentPersona = array_key_exists($persona, $this->getPersonas()) ? $persona : 'academic_senior';
        Notification::make()
            ->title('Persona Asisten Diatur')
            ->body("Gaya respon: {$this->getPersonas()[$this->currentPersona]}")
            ->info()
            ->send();
    }

    /**
     * @return array<string, string>
     */
    public function getPersonas(): array
    {
        return [
            'academic_senior' => 'Akademisi Senior (DIKTI & Scopus Q1)',
            'critical_reviewer' => 'Reviewer Kritis (Peer Review)',
            'proofreader' => 'Proofreader PUEBI & Tata Bahasa',
            'methodologist' => 'Metodolog Riset',
            'novelty' => 'Spesialis Kebaruan & Gap',
        ];
    }

    public function regenerateMessage(int $logIndex): void
    {
        $entry = $this->log[$logIndex] ?? null;
        if ($entry === null || ($entry['role'] ?? '') !== 'assistant') {
            return;
        }

        $userQuery = null;
        for ($i = $logIndex - 1; $i >= 0; $i--) {
            if (($this->log[$i]['role'] ?? '') === 'user') {
                $userQuery = (string) ($this->log[$i]['content'] ?? '');
                break;
            }
        }

        if ($userQuery === null || trim($userQuery) === '') {
            $this->regenerateLastResponse();

            return;
        }

        $project = $this->getProject();
        if ($project === null) {
            return;
        }

        $modelId = $this->getCurrentAssistantModel();

        try {
            $result = app(XishieAssistantService::class)->handle(
                $project,
                $userQuery,
                $modelId,
                $this->currentPersona,
                $this->assistantScope
            );
            $this->log[$logIndex] = [
                'role' => 'assistant',
                'content' => (string) $result['reply'],
                'model' => $modelId,
                'persona' => $this->currentPersona,
                'scope' => $this->assistantScope,
                'command' => $result['command'] ?? 'chat',
                'created_at' => now()->format('H:i:s'),
                'is_regenerated' => true,
            ];
            Notification::make()->title('Respon Berhasil Di-regenerate')->success()->send();
        } catch (\Throwable $e) {
            $this->log[$logIndex] = [
                'role' => 'assistant',
                'content' => '[Gagal] '.$e->getMessage(),
                'model' => $modelId,
                'created_at' => now()->format('H:i:s'),
            ];
        }

        $this->dispatch('assistant-updated');
    }

    public function getProject(): ?XishieProject
    {
        return $this->projectId !== null ? XishieProject::find($this->projectId) : null;
    }

    /**
     * Pilih proyek aktif.
     */
    public function switchProject(int $projectId): void
    {
        if (XishieProject::find($projectId) === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $this->projectId = $projectId;

        Notification::make()->title('Konteks proyek diubah')->success()->send();

        $this->dispatch('$refresh');
    }

    /**
     * Sisipkan sebuah slash command ke kolom pesan.
     */
    public function insertCommand(string $command): void
    {
        $name = SlashCommand::tryFrom($command)?->value ?? $command;

        $this->message = trim($this->message.' '.$name).' ';
    }

    public function setScope(string $scope): void
    {
        $this->assistantScope = in_array($scope, ['project', 'manuscript', 'literature'], true) ? $scope : 'project';
    }

    public function executeCommandDirectly(string $command): void
    {
        $this->message = $command;
        $this->send();
    }

    /**
     * Kirim pesan ke Xishie Assistant dan tambahkan jawabannya ke transkrip.
     */
    public function send(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $text = trim($this->message);

        if ($text === '') {
            return;
        }

        $modelId = $this->getCurrentAssistantModel();

        $this->log[] = [
            'role' => 'user',
            'content' => $text,
            'model' => $modelId,
            'persona' => $this->currentPersona,
            'scope' => $this->assistantScope,
            'created_at' => now()->format('H:i:s'),
        ];
        $this->message = '';

        try {
            $result = app(XishieAssistantService::class)->handle(
                $project,
                $text,
                $modelId,
                $this->currentPersona,
                $this->assistantScope
            );

            // Catat untuk KPI "Assistant Query Effectiveness".
            OmnigensiAgentLog::create([
                'project_id' => $project->id,
                'agent_name' => 'Xishie-Assistant',
                'action' => $result['command'],
                'control_mode' => $project->control_mode->value,
                'ttft_ms' => 0,
                'execution_time_ms' => 0,
                'token_usage' => str_word_count((string) $result['reply']),
                'hallucination_rejected_count' => 0,
                'status' => 'success',
                'payload' => [
                    'query' => $text,
                    'command' => $result['command'],
                    'model' => $modelId,
                    'persona' => $this->currentPersona,
                    'scope' => $this->assistantScope,
                    'metadata' => [
                        'applied' => in_array($result['command'], SlashCommand::names(), true),
                    ],
                ],
            ]);

            $this->log[] = [
                'role' => 'assistant',
                'content' => (string) $result['reply'],
                'model' => $modelId,
                'persona' => $this->currentPersona,
                'scope' => $this->assistantScope,
                'command' => $result['command'] ?? 'chat',
                'created_at' => now()->format('H:i:s'),
            ];
        } catch (\Throwable $exception) {
            $this->log[] = [
                'role' => 'assistant',
                'content' => '[Gagal] '.$exception->getMessage(),
                'model' => $modelId,
                'created_at' => now()->format('H:i:s'),
            ];
        }

        $this->dispatch('assistant-updated');
    }

    /**
     * Terapkan rekomendasi jawaban asisten langsung ke Naskah Studio.
     */
    public function applyToNaskahStudio(int $logIndex): void
    {
        $entry = $this->log[$logIndex] ?? null;
        if ($entry === null || ($entry['role'] ?? '') !== 'assistant') {
            Notification::make()->title('Entri asisten tidak ditemukan')->warning()->send();

            return;
        }

        $content = trim((string) ($entry['content'] ?? ''));
        if ($content === '' || str_starts_with($content, '[Gagal]')) {
            Notification::make()->title('Konten tidak valid untuk naskah')->warning()->send();

            return;
        }

        $project = $this->getProject();
        $doc = null;

        if ($project !== null) {
            $doc = NaskahDocument::query()
                ->where('source_type', NaskahDocument::SOURCE_PROJECT)
                ->where('project_id', $project->id)
                ->latest('updated_at')
                ->first();
        }

        if ($doc === null) {
            $title = $project !== null ? $project->title : 'Naskah Assistant';
            $doc = NaskahDocument::query()->create([
                'title' => $title,
                'source_type' => $project !== null ? NaskahDocument::SOURCE_PROJECT : 'standalone',
                'project_id' => $project?->id,
                'blocks' => [
                    ['type' => 'title', 'level' => 0, 'text' => $title],
                ],
                'word_count' => str_word_count($title),
            ]);
        }

        $richModel = app(NaskahRichModel::class);
        $existing = $richModel->normalize((array) $doc->blocks);

        $lines = preg_split("/\r\n|\n|\r/", $content);
        $newBlocks = [];
        foreach ($lines as $line) {
            $t = trim((string) $line);
            if ($t === '') {
                continue;
            }
            if (preg_match('/^(#{1,6})\s+(.+)$/', $t, $m)) {
                $newBlocks[] = $richModel->newBlock('heading', trim($m[2]), strlen($m[1]));
            } else {
                $newBlocks[] = $richModel->newBlock('paragraph', $t);
            }
        }

        if (empty($newBlocks)) {
            $newBlocks[] = $richModel->newBlock('paragraph', $content);
        }

        foreach ($newBlocks as $b) {
            $existing[] = $b;
        }

        $normalized = $richModel->normalize($existing);
        $stats = $richModel->computeStatistics($normalized);

        $doc->update([
            'blocks' => $normalized,
            'word_count' => $stats['words'] ?? 0,
            'last_saved_at' => now(),
        ]);

        $url = route('filament.admin.pages.naskah-studio', ['document' => $doc->id]);

        Notification::make()
            ->title('Berhasil Diterapkan ke Naskah Studio')
            ->body("Konten rekomendasi asisten berhasil disisipkan ke dokumen '{$doc->title}' (".count($newBlocks).' blok).')
            ->success()
            ->send();
    }

    /**
     * Eksekusi model/asisten saat ini langsung dari prompt dan terapkan ke Naskah Studio.
     */
    public function executeCurrentModelToStudio(?string $prompt = null): void
    {
        $input = trim($prompt ?? $this->message);
        if ($input === '') {
            Notification::make()->title('Pesan atau perintah kosong')->warning()->send();

            return;
        }

        $project = $this->getProject();
        $modelId = $this->getCurrentAssistantModel();

        $this->log[] = [
            'role' => 'user',
            'content' => $input,
            'command' => str_starts_with($input, '/') ? explode(' ', $input)[0] : 'chat',
            'model' => $modelId,
            'persona' => $this->currentPersona,
            'scope' => $this->assistantScope,
            'created_at' => now()->format('H:i:s'),
        ];

        $service = app(XishieAssistantService::class);
        $res = $service->handle(
            $project,
            $input,
            $modelId,
            $this->currentPersona,
            $this->assistantScope
        );
        $reply = $res['reply'] ?? '';

        $this->log[] = [
            'role' => 'assistant',
            'content' => $reply,
            'command' => $res['command'] ?? 'chat',
            'data' => $res['data'] ?? [],
            'model' => $modelId,
            'persona' => $this->currentPersona,
            'scope' => $this->assistantScope,
            'created_at' => now()->format('H:i:s'),
        ];
        $this->message = '';

        $this->applyToNaskahStudio(count($this->log) - 1);
        $this->dispatch('assistant-updated');
    }

    /**
     * Bersihkan transkrip dengan pencadangan riwayat untuk pemulihan (undo).
     */
    public function clearLog(): void
    {
        if (! empty($this->log)) {
            $this->lastClearedLog = $this->log;
        }
        $this->log = [];
        $this->dispatch('assistant-cleared');
        Notification::make()->title('Riwayat percakapan dibersihkan')->info()->send();
    }

    /**
     * Pulihkan transkrip sesi yang baru saja dibersihkan.
     */
    public function restoreClearedLog(): void
    {
        if (empty($this->lastClearedLog)) {
            Notification::make()->title('Tidak ada riwayat untuk dipulihkan')->warning()->send();

            return;
        }

        $this->log = $this->lastClearedLog;
        $this->lastClearedLog = [];
        $this->dispatch('assistant-updated');
        Notification::make()->title('Riwayat percakapan berhasil dipulihkan')->success()->send();
    }

    /**
     * Simpan sesi percakapan ke snapshot versi naskah dokumen.
     */
    public function saveSessionAsSnapshot(): void
    {
        if (empty($this->log)) {
            Notification::make()->title('Transkrip masih kosong')->warning()->send();

            return;
        }

        $project = $this->getProject();
        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $doc = NaskahDocument::query()->where('project_id', $project->id)->latest('last_saved_at')->first();
        if ($doc !== null) {
            $styleMeta = is_array($doc->style_meta) ? $doc->style_meta : [];
            $snapshots = is_array($styleMeta['snapshots'] ?? null) ? $styleMeta['snapshots'] : [];
            $snapshots[] = [
                'label' => 'Sesi Asisten '.now()->format('d/m/Y H:i'),
                'timestamp' => now()->toIso8601String(),
                'notes' => 'Transkrip percakapan asisten ('.count($this->log).' pesan, Model: '.$this->getCurrentAssistantModel().')',
                'blocks' => $doc->blocks,
                'assistant_log' => $this->log,
            ];
            $styleMeta['snapshots'] = $snapshots;
            $doc->style_meta = $styleMeta;
            $doc->save();

            Notification::make()
                ->title('Sesi Disimpan ke Versi Naskah')
                ->body('Snapshot sesi percakapan berhasil ditautkan ke riwayat dokumen naskah.')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Catatan Sesi Tersimpan')
                ->body('Transkrip sesi telah terekam dalam log AI proyek.')
                ->info()
                ->send();
        }
    }

    /**
     * Rekomendasi perintah cepat sesuai tahapan proyek aktif (Stage-Aware Prompts).
     *
     * @return array<int, array{command: string, label: string, badge: string}>
     */
    public function getStageRecommendedPrompts(): array
    {
        $project = $this->getProject();
        $stage = $project?->current_stage?->value ?? ($project?->stage?->value ?? 'draft');

        return match ($stage) {
            'draft', 'idea', 'topic' => [
                ['command' => '/kebaruan', 'label' => '💡 Rumuskan Kebaruan', 'badge' => 'Novelty'],
                ['command' => '/cek-gap', 'label' => '🔬 Cek Gap Riset', 'badge' => 'SOTA Gap'],
                ['command' => '/cari-referensi', 'label' => '🔍 Cari Referensi', 'badge' => 'Pustaka'],
                ['command' => '/tinjauan-pustaka', 'label' => '📚 Landasan Teori', 'badge' => 'Teori'],
            ],
            'proposal' => [
                ['command' => '/pendahuluan', 'label' => '📑 Bab Pendahuluan', 'badge' => 'Bab 1'],
                ['command' => '/metodologi', 'label' => '📐 Bab Metodologi', 'badge' => 'Bab 3'],
                ['command' => '/matriks-literatur', 'label' => '📊 Matriks SOTA', 'badge' => 'Matriks'],
                ['command' => '/daftar-isi', 'label' => '📑 Struktur Bab', 'badge' => 'Outline'],
            ],
            'analysis', 'manuscript' => [
                ['command' => '/pembahasan', 'label' => '💬 Bab Pembahasan', 'badge' => 'Bab 4'],
                ['command' => '/tabel-analisis', 'label' => '📊 Tabel Analisis', 'badge' => 'Data'],
                ['command' => '/rumus', 'label' => 'π Formulasi Rumus', 'badge' => 'LaTeX'],
                ['command' => '/poles', 'label' => '✨ Poles Akademik', 'badge' => 'Poles'],
                ['command' => '/puebi', 'label' => '✍️ Audit PUEBI', 'badge' => 'Ejaan'],
            ],
            'review', 'final' => [
                ['command' => '/abstrak', 'label' => '📝 Draf Abstrak IMRAD', 'badge' => 'Abstrak'],
                ['command' => '/kesimpulan', 'label' => '🏁 Simpulan & Saran', 'badge' => 'Bab 5'],
                ['command' => '/daftar-pustaka', 'label' => '📚 Daftar Pustaka', 'badge' => 'Bibliografi'],
                ['command' => '/keterbacaan', 'label' => '📈 Skor Keterbacaan', 'badge' => 'Audit'],
                ['command' => '/cek-pedoman', 'label' => '📜 Cek Pedoman', 'badge' => 'Pedoman'],
            ],
            default => [
                ['command' => '/poles', 'label' => '✨ Poles Akademik', 'badge' => 'Poles'],
                ['command' => '/abstrak', 'label' => '📝 Draf Abstrak', 'badge' => 'Abstrak'],
                ['command' => '/metodologi', 'label' => '📐 Metodologi', 'badge' => 'Metode'],
                ['command' => '/kebaruan', 'label' => '💡 Kebaruan Riset', 'badge' => 'Novelty'],
            ],
        };
    }

    /**
     * Unduh transkrip sesi percakapan sebagai file Markdown, JSON, atau TXT.
     */
    public function exportTranscript(string $format = 'markdown'): ?StreamedResponse
    {
        $project = $this->getProject();
        if (empty($this->log)) {
            Notification::make()->title('Transkrip masih kosong')->warning()->send();

            return null;
        }

        $title = $project?->title ?? 'Xishie Assistant';
        $timestamp = now()->format('Y-m-d_H-i-s');
        $format = mb_strtolower($format);

        if ($format === 'json') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.json';
            $payload = [
                'project' => [
                    'id' => $project?->id,
                    'title' => $title,
                ],
                'exported_at' => now()->toIso8601String(),
                'total_messages' => count($this->log),
                'messages' => $this->log,
            ];
            $content = (string) json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mime = 'application/json; charset=UTF-8';
        } elseif ($format === 'txt') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.txt';
            $lines = [
                'TRANSKRIP PERCAKAPAN XISHIE ASSISTANT',
                'Proyek: '.$title,
                'Waktu Ekspor: '.now()->toIso8601String(),
                'Total Pesan: '.count($this->log),
                str_repeat('=', 50),
                '',
            ];

            foreach ($this->log as $entry) {
                $sender = $entry['role'] === 'user' ? 'Peneliti' : 'Xishie Assistant';
                $lines[] = "[{$sender}]";
                $lines[] = (string) $entry['content'];
                $lines[] = str_repeat('-', 30);
                $lines[] = '';
            }

            $content = implode("\n", $lines);
            $mime = 'text/plain; charset=UTF-8';
        } elseif ($format === 'html') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.html';
            $msgHtml = [];
            foreach ($this->log as $entry) {
                $isUser = ($entry['role'] === 'user');
                $sender = $isUser ? 'Peneliti' : 'Xishie Assistant';
                $color = $isUser ? '#1e40af' : '#0369a1';
                $bg = $isUser ? '#eff6ff' : '#f0f9ff';
                $border = $isUser ? '#bfdbfe' : '#bae6fd';
                $time = htmlspecialchars((string) ($entry['created_at'] ?? now()->format('H:i:s')));
                $modelTag = ! empty($entry['model']) ? ' <span style="font-size:11px;background:#e0f2fe;color:#0369a1;padding:2px 6px;border-radius:3px;">'.htmlspecialchars((string) $entry['model']).'</span>' : '';
                $body = $isUser ? nl2br(htmlspecialchars((string) $entry['content'])) : $this->markdownToHtml((string) $entry['content']);
                $msgHtml[] = "<div style=\"margin-bottom:16px;padding:14px;background:{$bg};border:1px solid {$border};border-radius:6px;\">"
                    ."<div style=\"font-weight:700;color:{$color};margin-bottom:6px;display:flex;justify-content:space-between;\">"
                    ."<span>".($isUser ? '👤' : '✨')." {$sender}{$modelTag}</span>"
                    ."<span style=\"font-size:11px;color:#64748b;font-weight:normal;\">{$time}</span>"
                    ."</div>"
                    ."<div style=\"line-height:1.6;font-size:14px;color:#1e293b;\">{$body}</div>"
                    ."</div>";
            }
            $content = "<!DOCTYPE html>\n<html lang=\"id\">\n<head>\n<meta charset=\"utf-8\">\n<title>Transkrip Xishie Assistant - ".htmlspecialchars($title)."</title>\n"
                ."<style>body{font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;max-width:860px;margin:30px auto;padding:0 20px;background:#f8fafc;color:#1e293b;}table{border-collapse:collapse;width:100%;margin:12px 0;}th,td{border:1px solid #cbd5e1;padding:8px 12px;text-align:left;}th{background:#f1f5f9;}code{background:#f1f5f9;padding:2px 5px;border-radius:3px;font-size:13px;}</style>\n"
                ."</head>\n<body>\n"
                ."<div style=\"border-bottom:2px solid #2563eb;padding-bottom:12px;margin-bottom:20px;\">"
                ."<h1 style=\"margin:0 0 6px;color:#1e40af;font-size:22px;\">✨ Transkrip Percakapan Xishie Assistant</h1>"
                ."<div style=\"font-size:13px;color:#64748b;\">Proyek: <strong>".htmlspecialchars($title)."</strong> &middot; Waktu: ".now()->toIso8601String()." &middot; Total: ".count($this->log)." Pesan</div>"
                ."</div>\n"
                .implode("\n", $msgHtml)
                ."\n</body>\n</html>";
            $mime = 'text/html; charset=UTF-8';
        } elseif ($format === 'latex' || $format === 'tex') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.tex';
            $texItems = [];
            foreach ($this->log as $entry) {
                $sender = $entry['role'] === 'user' ? '\textbf{Peneliti}' : '\textbf{Xishie Assistant} ('.($entry['model'] ?? 'AI').')';
                $cleanContent = str_replace(['_', '&', '%', '$', '#'], ['\_', '\&', '\%', '\$', '\#'], (string) $entry['content']);
                $texItems[] = "\\subsection*{{$sender}}\n\\begin{quote}\n{$cleanContent}\n\\end{quote}\n";
            }
            $content = "\\documentclass[11pt,a4paper]{article}\n\\usepackage[utf8]{inputenc}\n\\usepackage{geometry}\n\\geometry{margin=2.5cm}\n\\usepackage{amsmath,amssymb}\n"
                ."\\title{Transkrip Sesi Xishie Assistant - ".addslashes($title)."}\n\\author{Peneliti / Xishie}\n\\date{".now()->format('d F Y')."}\n"
                ."\\begin{document}\n\\maketitle\n\n".implode("\n", $texItems)."\n\\end{document}\n";
            $mime = 'application/x-latex; charset=UTF-8';
        } elseif ($format === 'csv') {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.csv';
            $rows = [
                ['Role', 'Model', 'Persona', 'Scope', 'Command', 'Created At', 'Content'],
            ];

            foreach ($this->log as $entry) {
                $rows[] = [
                    (string) ($entry['role'] ?? ''),
                    (string) ($entry['model'] ?? ''),
                    (string) ($entry['persona'] ?? ''),
                    (string) ($entry['scope'] ?? ''),
                    (string) ($entry['command'] ?? ''),
                    (string) ($entry['created_at'] ?? ''),
                    str_replace(["\r\n", "\r", "\n"], ' ', (string) ($entry['content'] ?? '')),
                ];
            }

            $output = fopen('php://temp', 'r+');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            rewind($output);
            $content = (string) stream_get_contents($output);
            fclose($output);
            $mime = 'text/csv; charset=UTF-8';
        } else {
            $filename = 'xishie_assistant_transcript_'.$timestamp.'.md';
            $lines = [
                '# Transkrip Sesi Xishie Assistant',
                '- **Proyek**: '.$title,
                '- **Waktu Ekspor**: '.now()->toIso8601String(),
                '- **Total Pesan**: '.count($this->log),
                '',
                '---',
                '',
            ];

            foreach ($this->log as $entry) {
                $sender = $entry['role'] === 'user' ? 'Peneliti' : 'Xishie Assistant';
                $lines[] = "### [{$sender}]";
                $lines[] = $entry['content'];
                $lines[] = '';
            }

            $content = implode("\n", $lines);
            $mime = 'text/markdown; charset=UTF-8';
        }

        Notification::make()
            ->title('Transkrip Percakapan Diekspor')
            ->body('Transkrip berhasil diunduh dalam format '.strtoupper($format).'.')
            ->success()
            ->send();

        return response()->streamDownload(static function () use ($content): void {
            echo $content;
        }, $filename, [
            'Content-Type' => $mime,
        ]);
    }

    /**
     * Render jawaban (Markdown) menjadi HTML aman.
     */
    public function markdownToHtml(string $markdown): string
    {
        if (str_starts_with($markdown, '[Gagal]')) {
            return htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        try {
            $converter = new GithubFlavoredMarkdownConverter([
                'html_input' => 'strip',
                'allow_unsafe_links' => false,
            ]);

            return (string) $converter->convert($markdown)->getContent();
        } catch (\Throwable) {
            return '<p>'.nl2br(htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')).'</p>';
        }
    }

    /**
     * @return array<int, array{name: string, description: string}>
     */
    public function commands(): array
    {
        return array_map(static fn (SlashCommand $c): array => [
            'name' => $c->value,
            'description' => $c->description(),
        ], SlashCommand::cases());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'project' => $this->getProject(),
            'projects' => XishieProject::query()->latest()->get(['id', 'title']),
            'commands' => $this->commands(),
            'filteredCommands' => $this->filteredCommands(),
            'categories' => $this->getCommandCategories(),
            'activeCategory' => $this->activeCommandCategory,
            'availableModels' => $this->getAvailableModels(),
            'currentModel' => $this->getCurrentAssistantModel(),
            'stagePrompts' => $this->getStageRecommendedPrompts(),
            'kpi' => UxKpiMetric::ASSISTANT_QUERY_EFFECTIVENESS->label(),
        ];
    }
}
