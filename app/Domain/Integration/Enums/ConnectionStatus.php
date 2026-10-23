<?php

declare(strict_types=1);

namespace App\Domain\Integration\Enums;

/**
 * Status konektivitas proyek target eksternal terhadap hub Xishie.
 *
 * Membedakan "server hidup" (reachable) dari "benar-benar terhubung"
 * (terautentikasi via API key dan endpoint handshake merespon sesuai kontrak).
 */
enum ConnectionStatus: string
{
    case UNVERIFIED = 'unverified';
    case CONNECTED = 'connected';
    case UNAUTHORIZED = 'unauthorized';
    case DEGRADED = 'degraded';
    case TIMEOUT = 'timeout';
    case UNREACHABLE = 'unreachable';

    public function label(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Belum Diverifikasi',
            self::CONNECTED => 'Terhubung',
            self::UNAUTHORIZED => 'Terdaftar, Kunci Belum Aktif',
            self::DEGRADED => 'Server Merespon Galat',
            self::TIMEOUT => 'Tidak Merespon (Timeout)',
            self::UNREACHABLE => 'Tidak Dapat Dijangkau',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'UNVERIFIED',
            self::CONNECTED => 'CONNECTED',
            self::UNAUTHORIZED => 'AUTH PENDING',
            self::DEGRADED => 'DEGRADED',
            self::TIMEOUT => 'TIMEOUT',
            self::UNREACHABLE => 'UNREACHABLE',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::UNVERIFIED => 'Proyek terdaftar tetapi handshake end-to-end belum pernah dieksekusi.',
            self::CONNECTED => 'Handshake berhasil: server merespon dan API key diterima endpoint target.',
            self::UNAUTHORIZED => 'Server target merespon, namun menolak kredensial (HTTP 401/403). Pasang API key di proyek target.',
            self::DEGRADED => 'Server target merespon dengan galat internal (HTTP 5xx). Periksa log proyek target.',
            self::TIMEOUT => 'Koneksi melewati batas waktu probe. Target lambat atau jaringan terblokir.',
            self::UNREACHABLE => 'Tidak ada respon sama sekali. Server mati, URL salah, atau port tidak terbuka.',
        };
    }

    /**
     * Apakah proyek benar-benar dapat bertukar data dengan hub.
     */
    public function isHealthy(): bool
    {
        return $this === self::CONNECTED;
    }

    /**
     * Apakah server target setidaknya hidup (meski kredensial belum aktif).
     */
    public function isReachable(): bool
    {
        return in_array($this, [self::CONNECTED, self::UNAUTHORIZED, self::DEGRADED], true);
    }

    /**
     * Token warna semantik untuk indikator UI.
     */
    public function tone(): string
    {
        return match ($this) {
            self::CONNECTED => 'pass',
            self::UNAUTHORIZED => 'hitl',
            self::DEGRADED => 'hitl',
            self::TIMEOUT => 'hitl',
            self::UNREACHABLE => 'alert',
            self::UNVERIFIED => 'manual',
        };
    }

    public static function fromLegacy(string $status): self
    {
        return match ($status) {
            'online', 'connected' => self::CONNECTED,
            'degraded' => self::DEGRADED,
            'timeout' => self::TIMEOUT,
            'offline', 'unreachable' => self::UNREACHABLE,
            default => self::UNVERIFIED,
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
