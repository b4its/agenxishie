<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ExternalClientFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
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
        'description',
        'allowed_models',
        'status',
        'last_status_code',
        'last_latency_ms',
        'target_server',
        'last_ping_at',
        'last_error_message',
        'inbound_requests_count',
        'last_inbound_at',
        'last_inbound_endpoint',
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
            'last_inbound_at' => 'datetime',
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
     * Simpan hasil probe status koneksi (ping).
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
    }
}
