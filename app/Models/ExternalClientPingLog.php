<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Log riwayat probe/ping konektivitas ke proyek target eksternal
 * untuk melacak uptime, tren latensi, dan riwayat status koneksi berkala.
 */
class ExternalClientPingLog extends Model
{
    use HasFactory;

    protected $table = 'external_client_ping_logs';

    protected $fillable = [
        'external_client_id',
        'status',
        'status_code',
        'latency_ms',
        'target_server',
        'error_message',
        'probed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status_code' => 'integer',
            'latency_ms' => 'integer',
            'probed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ExternalClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(ExternalClient::class, 'external_client_id');
    }
}
