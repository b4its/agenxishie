<?php

declare(strict_types=1);

namespace App\Domain\Assistant;

/**
 * SlashCommand: perintah on-demand Xishie Assistant (mode Manual / HITL),
 * sesuai PRD 3.3 (/sitasi, /parafrase, /cek-gap, /cek-pedoman).
 */
enum SlashCommand: string
{
    case SITASI = '/sitasi';
    case PARAFRASE = '/parafrase';
    case CEK_GAP = '/cek-gap';
    case CEK_PEDOMAN = '/cek-pedoman';
    case MATRIKS = '/matriks-literatur';
    case CARI_REFERENSI = '/cari-referensi';

    public function description(): string
    {
        return match ($this) {
            self::SITASI => 'Cari sitasi valid dari project_references untuk sebuah klaim.',
            self::PARAFRASE => 'Usulkan parafrase akademik untuk kalimat terpilih.',
            self::CEK_GAP => 'Identifikasi research gap dari matriks literatur.',
            self::CEK_PEDOMAN => 'Audit kepatuhan naskah terhadap pedoman aktif.',
            self::MATRIKS => 'Bangun tabel matriks literatur (SOTA).',
            self::CARI_REFERENSI => 'Cari referensi relevan via memori hybrid MySQL.',
        };
    }

    /**
     * @return array<int, string>
     */
    public static function names(): array
    {
        return array_map(static fn (self $command): string => $command->value, self::cases());
    }

    public static function tryParse(string $input): ?self
    {
        $trimmed = trim($input);
        $firstToken = explode(' ', $trimmed)[0] ?? '';

        return self::tryFrom(mb_strtolower($firstToken));
    }
}
