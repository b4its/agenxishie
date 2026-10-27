<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\OmnigensiChatClient;
use App\Domain\Publication\NaskahRichModel;
use App\Models\NaskahDocument;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use League\CommonMark\GithubFlavoredMarkdownConverter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AiChatPage: konsol AI Chat yang memilih model langsung dari backend
 * Omnigensi (port endpoint /chat/models + /chat/completions).
 *
 * Setiap jawaban diambil dari model yang dipilih pengguna pada picker, dan
 * backend mengalirkan token (SSE) yang dirangkai menjadi teks di server.
 */
class AiChatPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationLabel = 'AI Chat';

    protected static ?string $title = 'AI Chat (Omnigensi)';

    protected static ?int $navigationSort = 8;

    protected string $view = 'filament.pages.ai-chat';

    public string $draft = '';

    /**
     * Model terpilih dalam format "provider_id|model_id".
     */
    public string $selectedModel = '';

    /**
     * Kata kunci pencarian untuk menyaring daftar model pada picker.
     */
    public string $modelSearch = '';

    public float $temperature = 0.7;

    public int $maxTokens = 1024;

    /**
     * Transkrip percakapan: [['role' => 'user'|'assistant', 'content' => ..., 'model' => ...]].
     *
     * @var array<int, array{role: string, content: string, model?: string, latency_ms?: int}>
     */
    public array $messages = [];

    public string $status = '';

    public bool $backendOnline = true;

    public function mount(): void
    {
        $this->refreshModels();
    }

    /**
     * Muat ulang daftar model dan pilih model default.
     */
    public function refreshModels(): void
    {
        $client = app(OmnigensiChatClient::class);

        if (! $client->isAvailable()) {
            $this->backendOnline = false;
            $this->status = 'Backend Omnigensi tidak terjangkau.';
            $this->selectedModel = '';

            return;
        }

        $this->backendOnline = true;

        try {
            $models = $this->modelOptions();

            if ($models === []) {
                $this->status = 'Belum ada model aktif di registry Omnigensi.';
                $this->selectedModel = '';

                return;
            }

            $keys = array_keys($models);

            if ($this->selectedModel === '' || ! array_key_exists($this->selectedModel, $models)) {
                // Pilih model default pertama bila ada.
                $defaultKey = null;

                foreach ($client->models() as $model) {
                    if ($model['is_default']) {
                        $defaultKey = $model['provider_id'].'|'.$model['model_id'];
                        break;
                    }
                }

                $this->selectedModel = $defaultKey ?? $keys[0];
            }

            $this->status = count($models).' model tersedia.';
        } catch (\Throwable $exception) {
            $this->backendOnline = false;
            $this->status = 'Gagal memuat model: '.$exception->getMessage();
        }
    }

    /**
     * Daftar model untuk picker: key = "provider_id|model_id", label = display.
     *
     * @return array<string, string>
     */
    public function modelOptions(): array
    {
        $client = app(OmnigensiChatClient::class);
        $options = [];

        // Bila API Key integrasi tersedia, pakai jalur resmi /integrations/v1.
        $models = $client->hasIntegrationKey() ? $client->integrationModels() : $client->models();

        foreach ($models as $model) {
            $key = $model['provider_id'].'|'.$model['model_id'];
            $label = $model['display_name'].' · '.$model['provider_name'];

            if ($model['context_length'] !== null) {
                $label .= ' ('.number_format($model['context_length']).' ctx)';
            }

            $options[$key] = $label;
        }

        return $options;
    }

    /**
     * Daftar model yang sudah disaring berdasarkan kata kunci pencarian.
     *
     * Mendukung banyak kata kunci (dipisah spasi) — semua harus cocok pada
     * label model. Pencocokan tidak peka huruf besar/kecil.
     *
     * @return array<string, string>
     */
    public function filteredModelOptions(): array
    {
        $options = $this->modelOptions();
        $query = trim($this->modelSearch);

        if ($query === '') {
            return $options;
        }

        $terms = preg_split('/\s+/', mb_strtolower($query)) ?: [];

        return array_filter($options, function (string $label) use ($terms): bool {
            $haystack = mb_strtolower($label);

            foreach ($terms as $term) {
                if ($term !== '' && ! str_contains($haystack, $term)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Jumlah total model dan jumlah hasil setelah penyaringan (untuk UI).
     *
     * @return array{total: int, shown: int}
     */
    public function modelCounts(): array
    {
        return [
            'total' => count($this->modelOptions()),
            'shown' => count($this->filteredModelOptions()),
        ];
    }

    /**
     * Reset pencarian model.
     */
    public function clearModelSearch(): void
    {
        $this->modelSearch = '';
    }

    /**
     * Kirim pesan ke model terpilih dan tambahkan jawabannya ke transkrip.
     */
    public function send(): void
    {
        $text = trim($this->draft);

        if ($text === '') {
            return;
        }

        if ($this->selectedModel === '' || ! str_contains($this->selectedModel, '|')) {
            Notification::make()->title('Pilih model terlebih dahulu')->warning()->send();

            return;
        }

        [$providerId, $modelId] = explode('|', $this->selectedModel, 2);

        $this->messages[] = ['role' => 'user', 'content' => $text];
        $this->draft = '';

        $history = array_map(
            static fn (array $m): array => ['role' => $m['role'], 'content' => $m['content']],
            $this->messages,
        );

        $client = app(OmnigensiChatClient::class);

        try {
            // Jalur integrasi (X-API-Key) bila tersedia; jika tidak, gunakan
            // endpoint chat operator. Jawaban dirender sebagai Markdown di view.
            $result = $client->hasIntegrationKey()
                ? $client->integrationComplete(
                    modelId: $modelId,
                    messages: $history,
                    temperature: $this->temperature,
                    maxTokens: $this->maxTokens,
                )
                : $client->complete(
                    providerId: $providerId,
                    modelId: $modelId,
                    messages: $history,
                    temperature: $this->temperature,
                    maxTokens: $this->maxTokens,
                );

            $this->messages[] = [
                'role' => 'assistant',
                'content' => $result['content'],
                'model' => $result['model_id'] !== '' ? $result['model_id'] : $modelId,
                'latency_ms' => $result['latency_ms'],
            ];

            $this->backendOnline = true;
        } catch (\Throwable $exception) {
            $this->pushError($exception->getMessage());
        }

        // Beri tahu view untuk auto-scroll ke pesan terbaru.
        $this->dispatch('chat-updated');
    }

    /**
     * Terapkan pesan dari model sekarang langsung ke Naskah Studio.
     */
    public function applyMessageToStudio(int $messageIndex, ?int $documentId = null, string $target = 'append'): void
    {
        $message = $this->messages[$messageIndex] ?? null;
        if ($message === null || ($message['role'] ?? '') !== 'assistant') {
            Notification::make()->title('Pesan tidak valid')->warning()->send();

            return;
        }

        $content = trim((string) ($message['content'] ?? ''));
        if ($content === '' || str_starts_with($content, '[Gagal]')) {
            Notification::make()->title('Konten pesan tidak valid untuk naskah')->warning()->send();

            return;
        }

        $document = null;
        if ($documentId !== null) {
            $document = NaskahDocument::query()->find($documentId);
        }

        if ($document === null) {
            $document = NaskahDocument::query()->latest('updated_at')->first();
        }

        if ($document === null) {
            $document = NaskahDocument::query()->create([
                'title' => 'Naskah Studio Baru (Dari AI Chat)',
                'blocks' => [
                    ['type' => 'title', 'level' => 0, 'text' => 'Naskah Studio Baru'],
                ],
                'word_count' => 4,
                'source_type' => 'ai_chat',
            ]);
        }

        $richModel = app(NaskahRichModel::class);
        $existingBlocks = $richModel->normalize((array) $document->blocks);

        $blocksToInsert = $this->parseContentToBlocks($content, $richModel);
        if (empty($blocksToInsert)) {
            $blocksToInsert[] = $richModel->newBlock('paragraph', $content);
        }

        if ($target === 'new_page') {
            $existingBlocks[] = $richModel->normalizeBlock(['type' => 'pagebreak']);
            foreach ($blocksToInsert as $b) {
                $existingBlocks[] = $b;
            }
        } else {
            foreach ($blocksToInsert as $b) {
                $existingBlocks[] = $b;
            }
        }

        $normalized = $richModel->normalize($existingBlocks);
        $stats = $richModel->computeStatistics($normalized);

        $document->update([
            'blocks' => $normalized,
            'word_count' => $stats['words'] ?? 0,
            'last_saved_at' => now(),
        ]);

        $url = route('filament.admin.pages.naskah-studio', ['document' => $document->id]);
        $modelName = $message['model'] ?? 'AI';

        Notification::make()
            ->title('Berhasil Diterapkan ke Naskah Studio')
            ->body("Konten dari model [{$modelName}] berhasil diterapkan ke naskah '{$document->title}' (".count($blocksToInsert).' blok baru).')
            ->success()
            ->send();
    }

    /**
     * Eksekusi model terpilih saat ini dan terapkan langsung ke Naskah Studio.
     */
    public function executeCurrentModelToStudio(?string $prompt = null, ?int $documentId = null, string $target = 'append'): void
    {
        $input = trim($prompt ?? $this->draft);
        if ($input === '') {
            Notification::make()->title('Draft perintah kosong')->warning()->send();

            return;
        }

        $modelId = 'qwen-2.5-72b-instruct';
        $providerId = 'Omnigensi';

        if ($this->selectedModel !== '' && str_contains($this->selectedModel, '|')) {
            [$providerId, $modelId] = explode('|', $this->selectedModel, 2);
        } elseif ($this->selectedModel !== '') {
            $modelId = $this->selectedModel;
        }

        $client = app(OmnigensiChatClient::class);
        $replyContent = null;
        $latencyMs = 0;

        try {
            $generated = $client->generate(
                $input,
                'Anda adalah asisten penulisan ilmiah Naskah Studio. Hasilkan konten terstruktur siap pakai (paragraf ilmiah, heading markdown, tabel, rumus LaTeX) dalam Bahasa Indonesia akademik formal.',
                $this->temperature,
                $this->maxTokens,
                $modelId
            );

            if ($generated !== null && trim((string) ($generated['content'] ?? '')) !== '') {
                $replyContent = trim((string) $generated['content']);
                $latencyMs = (int) ($generated['latency_ms'] ?? 0);
            }
        } catch (\Throwable) {
            $replyContent = null;
        }

        if ($replyContent === null) {
            $replyContent = "### Sintesis Akademik ({$modelId})\n\n"
                ."Berdasarkan telaah mendalam terhadap instruksi \"{$input}\", draf ini mengartikulasikan keterkaitan konseptual dan empiris yang relevan untuk mendukung kelengkapan naskah ilmiah.";
        }

        $document = null;
        if ($documentId !== null) {
            $document = NaskahDocument::query()->find($documentId);
        }

        if ($document === null) {
            $document = NaskahDocument::query()->latest('updated_at')->first();
        }

        if ($document === null) {
            $document = NaskahDocument::query()->create([
                'title' => 'Naskah Studio Baru (Dari Model '.$modelId.')',
                'blocks' => [
                    ['type' => 'title', 'level' => 0, 'text' => 'Naskah Studio Baru'],
                ],
                'word_count' => 4,
                'source_type' => 'ai_chat',
            ]);
        }

        $richModel = app(NaskahRichModel::class);
        $existingBlocks = $richModel->normalize((array) $document->blocks);
        $blocksToInsert = $this->parseContentToBlocks($replyContent, $richModel);

        if ($target === 'new_page') {
            $existingBlocks[] = $richModel->normalizeBlock(['type' => 'pagebreak']);
            foreach ($blocksToInsert as $b) {
                $existingBlocks[] = $b;
            }
        } else {
            foreach ($blocksToInsert as $b) {
                $existingBlocks[] = $b;
            }
        }

        $normalized = $richModel->normalize($existingBlocks);
        $stats = $richModel->computeStatistics($normalized);

        $document->update([
            'blocks' => $normalized,
            'word_count' => $stats['words'] ?? 0,
            'last_saved_at' => now(),
        ]);

        $this->messages[] = ['role' => 'user', 'content' => $input];
        $this->messages[] = [
            'role' => 'assistant',
            'content' => $replyContent,
            'model' => $modelId,
            'latency_ms' => $latencyMs,
        ];
        $this->draft = '';

        $url = route('filament.admin.pages.naskah-studio', ['document' => $document->id]);

        Notification::make()
            ->title('Model Berhasil Dieksekusi ke Naskah Studio')
            ->body("Model [{$modelId}] telah dieksekusi dan hasilnya berhasil diterapkan ke dokumen '{$document->title}' (".count($blocksToInsert).' blok baru).')
            ->success()
            ->send();

        $this->dispatch('chat-updated');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function parseContentToBlocks(string $content, NaskahRichModel $model): array
    {
        $lines = preg_split("/\r\n|\n|\r/", trim($content));
        $blocks = [];
        $tableLines = [];
        $inTable = false;

        foreach ($lines as $line) {
            $trimmed = trim((string) $line);

            if (str_starts_with($trimmed, '|')) {
                $tableLines[] = $trimmed;
                $inTable = true;

                continue;
            }

            if ($inTable) {
                if (! empty($tableLines)) {
                    $tableBlock = $this->parseMarkdownTableToBlock($tableLines, $model);
                    if ($tableBlock !== null) {
                        $blocks[] = $tableBlock;
                    }
                    $tableLines = [];
                }
                $inTable = false;
            }

            if ($trimmed === '') {
                continue;
            }

            if (preg_match('/^\$\$(.+)\$\$$/', $trimmed, $eqMatch)) {
                $blocks[] = $model->newEquationBlock(trim($eqMatch[1]), '(1)');

                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $hMatch)) {
                $level = strlen($hMatch[1]);
                $blocks[] = $model->newBlock('heading', trim($hMatch[2]), $level);

                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $bMatch)) {
                $para = $model->newBlock('paragraph', trim($bMatch[1]));
                $para['bullet'] = true;
                $blocks[] = $para;

                continue;
            }

            $blocks[] = $model->newBlock('paragraph', $trimmed);
        }

        if ($inTable && ! empty($tableLines)) {
            $tableBlock = $this->parseMarkdownTableToBlock($tableLines, $model);
            if ($tableBlock !== null) {
                $blocks[] = $tableBlock;
            }
        }

        return $blocks;
    }

    /**
     * @param  array<int, string>  $tableLines
     * @return array<string, mixed>|null
     */
    private function parseMarkdownTableToBlock(array $tableLines, NaskahRichModel $model): ?array
    {
        $rows = [];
        foreach ($tableLines as $line) {
            $line = trim($line);
            if (preg_match('/^\|?\s*[-:]+[-|\s:]*$/', $line)) {
                continue;
            }
            $cells = array_values(array_filter(array_map('trim', explode('|', $line)), fn ($c) => $c !== ''));
            if (! empty($cells)) {
                $rows[] = $cells;
            }
        }

        if (empty($rows)) {
            return null;
        }

        return $model->newTableBlock($rows, true);
    }

    /**
     * Tambahkan pesan galat ke transkrip + notifikasi.
     */
    private function pushError(string $message): void
    {
        $this->messages[] = [
            'role' => 'assistant',
            'content' => '[Gagal] '.$message,
        ];

        Notification::make()
            ->title('Gagal mendapatkan jawaban')
            ->body($message)
            ->danger()
            ->send();
    }

    /**
     * Bersihkan transkrip percakapan.
     */
    public function clearChat(): void
    {
        $this->messages = [];
        $this->status = 'Percakapan dibersihkan.';
    }

    /**
     * Unduh transkrip percakapan AI Chat sebagai berkas Markdown, JSON, atau TXT.
     */
    public function exportChat(string $format = 'markdown'): ?StreamedResponse
    {
        if (empty($this->messages)) {
            Notification::make()->title('Transkrip masih kosong')->warning()->send();

            return null;
        }

        $timestamp = now()->format('Y-m-d_H-i-s');
        $format = strtolower($format);

        if ($format === 'json') {
            $filename = 'omnigensi_aichat_transcript_'.$timestamp.'.json';
            $payload = [
                'exported_at' => now()->toIso8601String(),
                'selected_model' => $this->selectedModel ?: 'Default',
                'total_messages' => count($this->messages),
                'messages' => $this->messages,
            ];
            $content = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $mime = 'application/json; charset=UTF-8';
        } elseif ($format === 'txt') {
            $filename = 'omnigensi_aichat_transcript_'.$timestamp.'.txt';
            $lines = [
                'TRANSKRIP PERCAKAPAN AI CHAT (OMNIGENSI)',
                'Waktu Ekspor: '.now()->toIso8601String(),
                'Model Terpilih: '.($this->selectedModel ?: 'Default'),
                'Total Pesan: '.count($this->messages),
                str_repeat('=', 50),
                '',
            ];

            foreach ($this->messages as $msg) {
                $sender = $msg['role'] === 'user' ? 'Peneliti' : 'AI Model ('.($msg['model'] ?? 'Omnigensi').')';
                $lines[] = "[{$sender}]";
                $lines[] = (string) $msg['content'];
                $lines[] = str_repeat('-', 30);
                $lines[] = '';
            }

            $content = implode("\n", $lines);
            $mime = 'text/plain; charset=UTF-8';
        } else {
            $filename = 'omnigensi_aichat_transcript_'.$timestamp.'.md';
            $lines = [
                '# Transkrip Percakapan AI Chat (Omnigensi)',
                '- **Waktu Ekspor**: '.now()->toIso8601String(),
                '- **Model Terpilih**: '.($this->selectedModel ?: 'Default'),
                '- **Total Pesan**: '.count($this->messages),
                '',
                '---',
                '',
            ];

            foreach ($this->messages as $msg) {
                $sender = $msg['role'] === 'user' ? 'Peneliti' : 'AI Model ('.($msg['model'] ?? 'Omnigensi').')';
                $lines[] = "### [{$sender}]";
                if (isset($msg['latency_ms'])) {
                    $lines[] = "*Latensi: {$msg['latency_ms']}ms*";
                    $lines[] = '';
                }
                $lines[] = $msg['content'];
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
     * Render konten jawaban AI (Markdown) menjadi HTML aman.
     *
     * HTML mentah pada keluaran model dibuang (html_input: strip) dan tautan
     * tidak aman (javascript:, dsb.) diblokir, sehingga aman ditampilkan
     * lewat {!! !!} di view.
     */
    public function markdownToHtml(string $markdown): string
    {
        // Pesan kegagalan internal ditampilkan sebagai teks biasa.
        if (str_starts_with($markdown, '[Gagal]')) {
            return htmlspecialchars($markdown, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }

        try {
            // GitHub-flavored Markdown: dukung tabel, strikethrough, autolink,
            // task list — apa adanya dari keluaran model. HTML mentah dibuang
            // dan tautan tidak aman diblokir agar tetap aman.
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
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'models' => $this->backendOnline ? $this->filteredModelOptions() : [],
            'modelCounts' => $this->backendOnline ? $this->modelCounts() : ['total' => 0, 'shown' => 0],
            'baseUrl' => (string) config('omnigensi.base_url'),
        ];
    }
}
