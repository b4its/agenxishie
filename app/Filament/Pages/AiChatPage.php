<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\OmnigensiChatClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use League\CommonMark\CommonMarkConverter;

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

    protected static ?int $navigationSort = 5;

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

        try {
            $history = array_map(
                static fn (array $m): array => ['role' => $m['role'], 'content' => $m['content']],
                $this->messages,
            );

            $client = app(OmnigensiChatClient::class);

            // Jalur resmi integrasi (X-API-Key) bila tersedia; jika tidak,
            // fallback ke endpoint chat operator.
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
            $this->messages[] = [
                'role' => 'assistant',
                'content' => '[Gagal] '.$exception->getMessage(),
            ];

            Notification::make()
                ->title('Gagal mendapatkan jawaban')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }
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
            $converter = new CommonMarkConverter([
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
