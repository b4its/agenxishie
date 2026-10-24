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
    case RINGKAS = '/ringkas';
    case POLES = '/poles';
    case RUMUS = '/rumus';
    case GAMBAR = '/gambar';
    case ABSTRAK = '/abstrak';
    case METODOLOGI = '/metodologi';
    case TABEL_ANALISIS = '/tabel-analisis';
    case TERJEMAHKAN = '/terjemahkan';
    case PUEBI = '/puebi';
    case KEMBANGKAN = '/kembangkan';

    public function description(): string
    {
        return match ($this) {
            self::SITASI => 'Cari sitasi valid dari project_references untuk sebuah klaim.',
            self::PARAFRASE => 'Usulkan parafrase akademik untuk kalimat terpilih.',
            self::CEK_GAP => 'Identifikasi research gap dari matriks literatur.',
            self::CEK_PEDOMAN => 'Audit kepatuhan naskah terhadap pedoman aktif.',
            self::MATRIKS => 'Bangun tabel matriks literatur (SOTA).',
            self::CARI_REFERENSI => 'Cari referensi relevan via memori hybrid MySQL.',
            self::RINGKAS => 'Ringkasan eksekutif progres naskah pada stage aktif.',
            self::POLES => 'Poles kalimat dengan tata bahasa akademik baku dan kohesif.',
            self::RUMUS => 'Rekomendasikan atau siapkan rumus matematika dalam format LaTeX.',
            self::GAMBAR => 'Panduan dan generator diagram ilmiah serta penyisipan gambar.',
            self::ABSTRAK => 'Susun draf abstrak akademik terstruktur berformat IMRAD.',
            self::METODOLOGI => 'Rancang struktur dan instrumen bab metodologi penelitian.',
            self::TABEL_ANALISIS => 'Buat tabel matriks komparasi data dan evaluasi empiris.',
            self::TERJEMAHKAN => 'Terjemahkan teks ke dalam Bahasa Inggris akademik formal.',
            self::PUEBI => 'Audit dan koreksi tata bahasa, ejaan, dan tanda baca sesuai PUEBI.',
            self::KEMBANGKAN => 'Elaborasi dan kembangkan poin gagasan menjadi paragraf utuh.',
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
        $firstToken = mb_strtolower(explode(' ', $trimmed)[0] ?? '');

        if ($firstToken === '/perbaiki-ejaan') {
            return self::PUEBI;
        }
        if ($firstToken === '/metode') {
            return self::METODOLOGI;
        }
        if ($firstToken === '/ringkaskan') {
            return self::RINGKAS;
        }
        if (in_array($firstToken, ['/latex', '/equation'], true)) {
            return self::RUMUS;
        }
        if ($firstToken === '/diagram') {
            return self::GAMBAR;
        }

        return self::tryFrom($firstToken);
    }
}
