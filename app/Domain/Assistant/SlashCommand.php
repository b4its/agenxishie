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
    case PENDAHULUAN = '/pendahuluan';
    case PEMBAHASAN = '/pembahasan';
    case KESIMPULAN = '/kesimpulan';
    case TINJAUAN_PUSTAKA = '/tinjauan-pustaka';
    case DAFTAR_ISI = '/daftar-isi';
    case DAFTAR_PUSTAKA = '/daftar-pustaka';

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
            self::PENDAHULUAN => 'Rancang narasi Bab Pendahuluan (Latar Belakang, Urgensi, SOTA, dan Research Gap).',
            self::PEMBAHASAN => 'Sintesis pembahasan kritis, komparasi temuan empiris dengan rujukan terdahulu.',
            self::KESIMPULAN => 'Rumuskan kesimpulan bernas, kontribusi teoretis/praktis, limitasi, dan saran.',
            self::TINJAUAN_PUSTAKA => 'Bangun kerangka teoretis, landasan konseptual, dan sintesis literatur.',
            self::DAFTAR_ISI => 'Susun bagan struktur dan daftar isi naskah sesuai jenis dokumen dan pedoman.',
            self::DAFTAR_PUSTAKA => 'Susun daftar pustaka lengkap dari referensi proyek sesuai gaya sitasi aktif.',
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
        if (in_array($firstToken, ['/latar-belakang', '/intro', '/introduction'], true)) {
            return self::PENDAHULUAN;
        }
        if (in_array($firstToken, ['/diskusi', '/discussion'], true)) {
            return self::PEMBAHASAN;
        }
        if (in_array($firstToken, ['/penutup', '/saran', '/conclusion'], true)) {
            return self::KESIMPULAN;
        }
        if (in_array($firstToken, ['/literatur', '/teori', '/kerangka-teori', '/tinjauan', '/literature'], true)) {
            return self::TINJAUAN_PUSTAKA;
        }
        if (in_array($firstToken, ['/toc', '/outline', '/struktur'], true)) {
            return self::DAFTAR_ISI;
        }
        if (in_array($firstToken, ['/bibliografi', '/pustaka', '/references', '/bibliography'], true)) {
            return self::DAFTAR_PUSTAKA;
        }

        return self::tryFrom($firstToken);
    }
}
