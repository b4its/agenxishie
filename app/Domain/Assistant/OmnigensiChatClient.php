<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
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
    ) {}

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
}
