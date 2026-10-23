<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Integration\Enums\ConnectionStatus;
use Database\Factories\ExternalClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Representasi klien eksternal atau proyek lain (misal agen xishie di localhost:8208)
 * yang terdaftar secara modular untuk mengonsumsi API/model Xishie serta dipantau konektivitasnya.
 */
class ExternalClient extends Model
{
    /** @use HasFactory<ExternalClientFactory> */
    use HasFactory;

    protected $table = 'external_clients';

    protected $fillable = [
        'name',
        'client_slug',
        'api_key',
        'target_url',
        'webhook_url',
        'webhook_secret',
        'handshake_endpoint',
        'description',
        'allowed_models',
        'status',
        'connection_status',
        'last_status_code',
        'last_latency_ms',
        'target_server',
        'target_app_name',
        'target_framework',
        'target_version',
        'last_ping_at',
        'last_handshake_at',
        'last_error_message',
        'last_handshake_message',
        'inbound_requests_count',
        'last_inbound_at',
        'last_inbound_endpoint',
        'last_webhook_at',
        'last_webhook_status',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'allowed_models' => 'array',
            'is_active' => 'boolean',
            'last_ping_at' => 'datetime',
            'last_handshake_at' => 'datetime',
            'last_inbound_at' => 'datetime',
            'last_webhook_at' => 'datetime',
            'last_status_code' => 'integer',
            'last_latency_ms' => 'integer',
            'inbound_requests_count' => 'integer',
        ];
    }

    /**
     * Hasilkan API key acak dengan entropi tinggi dan awalan unik.
     */
    public static function generateApiKey(): string
    {
        return 'xsk_'.bin2hex(random_bytes(24));
    }

    /**
     * Buat slug unik dari nama proyek.
     */
    public static function createUniqueSlug(string $name): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'client-'.Str::random(6);
        }

        $slug = $base;
        $counter = 1;
        while (static::where('client_slug', $slug)->exists()) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }

    /**
     * Cek apakah klien diizinkan mengakses model/agent tertentu.
     */
    public function isModelAllowed(string $model): bool
    {
        $allowed = $this->allowed_models ?? ['*'];

        if (in_array('*', $allowed, true)) {
            return true;
        }

        return in_array($model, $allowed, true);
    }

    /**
     * Catat hit masuk (inbound API request) dari klien ini.
     */
    public function recordInboundHit(string $endpoint): void
    {
        $this->inbound_requests_count++;
        $this->last_inbound_at = now();
        $this->last_inbound_endpoint = $endpoint;
        $this->saveQuietly();
    }

    /**
     * Catat hasil pengiriman webhook ke proyek klien.
     */
    public function recordWebhookResult(string $status): void
    {
        $this->last_webhook_at = now();
        $this->last_webhook_status = $status;
        $this->saveQuietly();
    }

    /**
     * Status konektivitas terstruktur (fallback ke kolom legacy `status`).
     */
    public function getConnectionStateAttribute(): ConnectionStatus
    {
        $raw = $this->connection_status;

        if (is_string($raw) && $raw !== '') {
            $state = ConnectionStatus::tryFrom($raw);
            if ($state !== null) {
                return $state;
            }
        }

        return ConnectionStatus::fromLegacy((string) $this->status);
    }

    /**
     * Catat hasil handshake end-to-end ke proyek klien.
     */
    public function recordHandshakeResult(
        ConnectionStatus $state,
        ?int $statusCode,
        ?int $latencyMs,
        ?string $message = null,
        ?string $server = null,
        ?string $appName = null,
        ?string $framework = null,
        ?string $version = null,
    ): void {
        $this->connection_status = $state->value;
        $this->status = $state->isReachable() ? 'online' : ($state === ConnectionStatus::TIMEOUT ? 'timeout' : 'offline');
        $this->last_status_code = $statusCode;
        $this->last_latency_ms = $latencyMs;
        $this->last_handshake_at = now();
        $this->last_handshake_message = $message !== null ? Str::limit($message, 500) : null;
        $this->last_error_message = $state->isHealthy() ? null : ($message !== null ? Str::limit($message, 250) : null);
        $this->target_server = $server ?? $this->target_server;
        $this->target_app_name = $appName ?? $this->target_app_name;
        $this->target_framework = $framework ?? $this->target_framework;
        $this->target_version = $version ?? $this->target_version;
        $this->last_ping_at = now();
        $this->saveQuietly();

        $this->pingLogs()->create([
            'status' => $state->isReachable() ? 'online' : ($state === ConnectionStatus::TIMEOUT ? 'timeout' : 'offline'),
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'target_server' => $server,
            'error_message' => $state->isHealthy() ? null : $message,
            'probed_at' => now(),
        ]);
    }

    /**
     * Relasi riwayat probe status koneksi.
     *
     * @return HasMany<ExternalClientPingLog, $this>
     */
    public function pingLogs(): HasMany
    {
        return $this->hasMany(ExternalClientPingLog::class, 'external_client_id');
    }

    /**
     * Hitung persentase uptime dari riwayat probe terbaru.
     */
    public function getUptimePercentage(int $sampleSize = 50): float
    {
        $logs = $this->pingLogs()->latest('probed_at')->limit($sampleSize)->get();
        if ($logs->isEmpty()) {
            return $this->status === 'online' ? 100.0 : 0.0;
        }

        $onlineCount = $logs->where('status', 'online')->count();

        return round(($onlineCount / $logs->count()) * 100, 1);
    }

    /**
     * Ambil urutan probe terbaru untuk visualisasi baris status / spark timeline.
     *
     * @return array<int, array{status: string, latency_ms: int|null, status_code: int|null, probed_at: string}>
     */
    public function getRecentPings(int $limit = 10): array
    {
        return $this->pingLogs()
            ->latest('probed_at')
            ->limit($limit)
            ->get()
            ->reverse()
            ->values()
            ->map(static fn (ExternalClientPingLog $log): array => [
                'status' => $log->status,
                'latency_ms' => $log->latency_ms,
                'status_code' => $log->status_code,
                'probed_at' => $log->probed_at?->format('H:i:s') ?? '',
            ])
            ->all();
    }

    /**
     * Simpan hasil probe status koneksi (ping) dan catat ke riwayat log.
     */
    public function recordPingResult(
        string $status,
        ?int $statusCode,
        ?int $latencyMs,
        ?string $server = null,
        ?string $errorMessage = null,
    ): void {
        $this->status = $status;
        $this->last_status_code = $statusCode;
        $this->last_latency_ms = $latencyMs;
        $this->target_server = $server;
        $this->last_error_message = $errorMessage;
        $this->last_ping_at = now();
        $this->saveQuietly();

        $this->pingLogs()->create([
            'status' => $status,
            'status_code' => $statusCode,
            'latency_ms' => $latencyMs,
            'target_server' => $server,
            'error_message' => $errorMessage,
            'probed_at' => now(),
        ]);
    }
}
