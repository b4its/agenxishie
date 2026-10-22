<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * OmnigensiChatClient: klien ke backend Omnigensi untuk AI Chat & pemilihan
 * model (port dari endpoint /chat/models dan /chat/completions omnigensi).
 *
 * Alur:
 *  1. Tukar password operator menjadi bearer token (di-cache selama sesi).
 *  2. Ambil daftar model aktif dari registry provider.
 *  3. Kirim percakapan; backend mengalirkan SSE (start/token/done/error) yang
 *     di sini dirangkai kembali menjadi teks akhir.
 */
final class OmnigensiChatClient
{
    private const CACHE_KEY = 'omnigensi:chat:token';

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $password,
        private readonly int $timeout,
        private readonly int $connectTimeout,
        private readonly bool $verifyTls,
        private readonly string $apiKey = '',
    ) {}

    /**
     * Apakah proyek ini punya API Key integrasi (jalur resmi /integrations/v1).
     */
    public function hasIntegrationKey(): bool
    {
        return $this->apiKey !== '';
    }

    /**
     * Apakah backend Omnigensi dapat dijangkau (dipakai UI untuk degradasi
     * anggun saat backend mati).
     */
    public function isAvailable(): bool
    {
        try {
            $this->http()->get('/health')->throw();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Daftar model yang dapat dipilih (flatten dari seluruh provider aktif).
     *
     * @return array<int, array{provider_id: string, provider_name: string, model_id: string, display_name: string, context_length: int|null, is_default: bool}>
     */
    public function models(): array
    {
        $response = $this->authorized()->get('/api/v1/chat/models');
        $response->throw();

        $models = (array) ($response->json('models') ?? []);

        return array_map(static fn (array $m): array => [
            'provider_id' => (string) ($m['provider_id'] ?? ''),
            'provider_name' => (string) ($m['provider_name'] ?? ''),
            'model_id' => (string) ($m['model_id'] ?? ''),
            'display_name' => (string) ($m['display_name'] ?? $m['model_id'] ?? ''),
            'context_length' => isset($m['context_length']) ? (int) $m['context_length'] : null,
            'is_default' => (bool) ($m['is_default'] ?? false),
        ], $models);
    }

    /**
     * Kirim percakapan dan kembalikan teks jawaban lengkap.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, provider_name: string, model_id: string, latency_ms: int}
     */
    public function complete(string $providerId, string $modelId, array $messages, float $temperature = 0.7, int $maxTokens = 2048): array
    {
        $response = $this->authorized()
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/api/v1/chat/completions', [
                'provider_id' => $providerId,
                'model_id' => $modelId,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]);

        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();

            throw new RuntimeException('Omnigensi menolak permintaan chat: '.
                (is_string($detail) ? $detail : json_encode($detail)));
        }

        return $this->parseSse($response->body());
    }

    /**
     * Handshake integrasi via API Key (endpoint /integrations/v1/handshake).
     * Memverifikasi koneksi dan mengembalikan model yang tersedia.
     *
     * @return array{status: string, client_name: string, target_url: string, omnigensi_version: string, allowed_models: array<int, string>, available_models: array<int, array<string, mixed>>}
     */
    public function handshake(): array
    {
        $response = $this->integrationHttp()->get('/api/v1/integrations/v1/handshake');
        $response->throw();

        return [
            'status' => (string) $response->json('status', 'unknown'),
            'client_name' => (string) $response->json('client_name', ''),
            'target_url' => (string) $response->json('target_url', ''),
            'omnigensi_version' => (string) $response->json('omnigensi_version', ''),
            'allowed_models' => (array) $response->json('allowed_models', ['*']),
            'available_models' => (array) $response->json('available_models', []),
        ];
    }

    /**
     * Daftar model yang diizinkan untuk proyek ini via API Key
     * (endpoint /integrations/v1/models).
     *
     * @return array<int, array{provider_id: string, provider_name: string, model_id: string, display_name: string, context_length: int|null, is_default: bool}>
     */
    public function integrationModels(): array
    {
        $response = $this->integrationHttp()->get('/api/v1/integrations/v1/models');
        $response->throw();

        $items = (array) $response->json('data', []);

        return array_map(static fn (array $m): array => [
            'provider_id' => (string) ($m['provider_id'] ?? ''),
            'provider_name' => (string) ($m['provider'] ?? ''),
            'model_id' => (string) ($m['id'] ?? ''),
            'display_name' => (string) ($m['display_name'] ?? $m['id'] ?? ''),
            'context_length' => isset($m['context_length']) ? (int) $m['context_length'] : null,
            'is_default' => (bool) ($m['is_default'] ?? false),
        ], $items);
    }

    /**
     * Completion lewat endpoint integrasi OpenAI-compatible
     * (/integrations/v1/chat/completions) memakai API Key proyek.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @return array{content: string, provider_name: string, model_id: string, latency_ms: int}
     */
    public function integrationComplete(string $modelId, array $messages, float $temperature = 0.7, int $maxTokens = 2048): array
    {
        $response = $this->integrationHttp()->post('/api/v1/integrations/v1/chat/completions', [
            'model' => $modelId,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => false,
        ]);

        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();

            throw new RuntimeException('Integrasi Omnigensi menolak permintaan: '.
                (is_string($detail) ? $detail : json_encode($detail)));
        }

        $content = (string) $response->json('choices.0.message.content', '');

        if (trim($content) === '') {
            throw new RuntimeException('Model tidak mengembalikan jawaban (integrasi).');
        }

        return [
            'content' => $content,
            'provider_name' => (string) $response->json('client', ''),
            'model_id' => (string) $response->json('model', $modelId),
            'latency_ms' => (int) $response->json('latency_ms', 0),
        ];
    }

    /**
     * Completion streaming lewat endpoint integrasi. Setiap potongan token
     * diteruskan ke $onToken agar UI dapat merender Markdown secara progresif.
     *
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  callable(string): void  $onToken
     * @return array{content: string, provider_name: string, model_id: string, latency_ms: int}
     */
    public function streamIntegration(
        string $modelId,
        array $messages,
        callable $onToken,
        float $temperature = 0.7,
        int $maxTokens = 2048,
    ): array {
        $response = $this->integrationHttp()
            ->withOptions(['stream' => true])
            ->withHeaders(['Accept' => 'text/event-stream'])
            ->post('/api/v1/integrations/v1/chat/completions', [
                'model' => $modelId,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
                'stream' => true,
            ]);

        if ($response->failed()) {
            $detail = $response->json('detail') ?? $response->body();

            throw new RuntimeException('Integrasi Omnigensi menolak permintaan: '.
                (is_string($detail) ? $detail : json_encode($detail)));
        }

        /** @var StreamInterface $stream */
        $stream = $response->toPsrResponse()->getBody();

        $content = '';
        $buffer = '';
        $latency = 0;
        $providerName = '';
        $modelIdFinal = $modelId;
        $error = null;
        $event = null;

        while (! $stream->eof()) {
            $chunk = (string) $stream->read(8192);

            if ($chunk === '') {
                continue;
            }

            $buffer .= str_replace("\r\n", "\n", $chunk);

            // Proses blok SSE lengkap (dipisah baris kosong).
            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $block = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);
                $event = null;
                $data = null;

                foreach (explode("\n", $block) as $line) {
                    if (str_starts_with($line, 'event:')) {
                        $event = trim(substr($line, 6));
                    } elseif (str_starts_with($line, 'data:')) {
                        $data = trim(substr($line, 5));
                    }
                }

                if ($event === null || $data === null) {
                    continue;
                }

                $decoded = json_decode($data, true);
                $decoded = is_array($decoded) ? $decoded : [];

                if ($event === 'start') {
                    $providerName = (string) ($decoded['client'] ?? $decoded['provider_name'] ?? '');
                    $modelIdFinal = (string) ($decoded['model'] ?? $modelId);
                } elseif ($event === 'token') {
                    $text = (string) ($decoded['text'] ?? '');

                    if ($text !== '') {
                        $content .= $text;
                        $onToken($text);
                    }
                } elseif ($event === 'done') {
                    $latency = (int) ($decoded['latency_ms'] ?? 0);
                } elseif ($event === 'error') {
                    $error = (string) ($decoded['message'] ?? 'Kesalahan tak dikenal');
                }
            }
        }

        if ($error !== null) {
            throw new RuntimeException('Model mengembalikan kesalahan: '.$error);
        }

        if (trim($content) === '') {
            throw new RuntimeException('Model tidak mengembalikan jawaban (stream).');
        }

        return [
            'content' => $content,
            'provider_name' => $providerName,
            'model_id' => $modelIdFinal,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Rangkai aliran SSE omnigensi menjadi hasil akhir.
     *
     * @return array{content: string, provider_name: string, model_id: string, latency_ms: int}
     */
    private function parseSse(string $body): array
    {
        $content = '';
        $latency = 0;
        $providerName = '';
        $modelId = '';
        $error = null;

        foreach (preg_split('/\r?\n\r?\n/', trim($body)) ?: [] as $block) {
            if (trim($block) === '') {
                continue;
            }

            $event = null;
            $data = null;

            foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
                if (str_starts_with($line, 'event:')) {
                    $event = trim(substr($line, 6));
                } elseif (str_starts_with($line, 'data:')) {
                    $data = trim(substr($line, 5));
                }
            }

            if ($event === null || $data === null) {
                continue;
            }

            $decoded = json_decode($data, true);
            $decoded = is_array($decoded) ? $decoded : [];

            match ($event) {
                'start' => (function () use (&$providerName, &$modelId, $decoded): void {
                    $providerName = (string) ($decoded['provider_name'] ?? '');
                    $modelId = (string) ($decoded['model_id'] ?? '');
                })(),
                'token' => $content .= (string) ($decoded['text'] ?? ''),
                'done' => $latency = (int) ($decoded['latency_ms'] ?? 0),
                'error' => $error = (string) ($decoded['message'] ?? 'Kesalahan tak dikenal'),
                default => null,
            };
        }

        if ($error !== null) {
            throw new RuntimeException('Model mengembalikan kesalahan: '.$error);
        }

        if (trim($content) === '') {
            throw new RuntimeException('Model tidak mengembalikan jawaban.');
        }

        return [
            'content' => $content,
            'provider_name' => $providerName,
            'model_id' => $modelId,
            'latency_ms' => $latency,
        ];
    }

    /**
     * Klien HTTP dengan bearer token operator (di-cache).
     */
    private function authorized(): PendingRequest
    {
        return $this->http()->withToken($this->token());
    }

    /**
     * Bearer token operator, di-cache selama TTL singkat. Dihapus saat 401.
     */
    public function token(bool $forceRefresh = false): string
    {
        if (! $forceRefresh) {
            $cached = Cache::get(self::CACHE_KEY);

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        $token = $this->login();

        Cache::put(self::CACHE_KEY, $token, now()->addMinutes(30));

        return $token;
    }

    private function login(): string
    {
        $response = $this->http()->post('/api/v1/auth/login', [
            'password' => $this->password,
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Gagal login ke Omnigensi (periksa OMNIGENSI_PASSWORD): HTTP '.$response->status());
        }

        $token = (string) ($response->json('token') ?? '');

        if ($token === '') {
            throw new RuntimeException('Omnigensi tidak mengembalikan token sesi.');
        }

        return $token;
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->baseUrl, '/'))
            ->timeout($this->timeout)
            ->connectTimeout($this->connectTimeout)
            ->withOptions(['verify' => $this->verifyTls])
            ->acceptJson();
    }

    /**
     * Klien HTTP untuk endpoint integrasi eksternal (X-API-Key).
     */
    private function integrationHttp(): PendingRequest
    {
        return $this->http()->withHeaders(['X-API-Key' => $this->apiKey]);
    }

    // -------------------------------------------------------------------------
    // Monitoring (operator) — mendaftar & memantau proyek terhubung.
    // -------------------------------------------------------------------------

    /**
     * Daftar proyek terhubung beserta status koneksinya (monitoring).
     *
     * @return array<int, array<string, mixed>>
     */
    public function integrationClients(): array
    {
        $response = $this->authorized()->get('/api/v1/integrations/clients');
        $response->throw();

        return (array) $response->json();
    }

    /**
     * Probe (ping) satu proyek terhubung via operator.
     *
     * @return array<string, mixed>
     */
    public function pingClient(string $clientId): array
    {
        $response = $this->authorized()->post('/api/v1/integrations/clients/'.rawurlencode($clientId).'/ping');
        $response->throw();

        return (array) $response->json();
    }

    /**
     * Probe seluruh proyek terhubung sekaligus.
     *
     * @return array<int, array<string, mixed>>
     */
    public function pingAll(): array
    {
        $response = $this->authorized()->post('/api/v1/integrations/ping-all');
        $response->throw();

        return (array) $response->json();
    }

    /**
     * Daftarkan/pulihkan konfigurasi default target agen xishie.
     *
     * @return array<string, mixed>
     */
    public function seedXishie(int $frontendPort = 8208): array
    {
        $response = $this->authorized()->post('/api/v1/integrations/seed-xishie', [
            'target_url' => 'http://localhost:'.$frontendPort.'/',
        ]);
        $response->throw();

        return (array) $response->json();
    }
}
