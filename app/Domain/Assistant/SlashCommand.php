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
    case FORMAT_PARAGRAF = '/format-paragraf';
    case CATATAN_KAKI = '/catatan-kaki';
    case KETERBACAAN = '/keterbacaan';
    case KEBARUAN = '/kebaruan';
    case TESAURUS = '/tesaurus';

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
            self::FORMAT_PARAGRAF => 'Panduan kaidah tipografi paragraf akademik dan rekomendasi preset tata letak.',
            self::CATATAN_KAKI => 'Panduan penulisan catatan kaki (footnote) dan standar Chicago/Turabian.',
            self::KETERBACAAN => 'Audit keterbacaan, densitas kata, dan estimasi waktu baca naskah akademik.',
            self::KEBARUAN => 'Rumuskan pernyataan kebaruan ilmiah (novelty statement) dan orisinalitas riset.',
            self::TESAURUS => 'Eksplorasi sinonim akademik baku untuk memperkaya variasi leksikon ilmiah.',
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

        if (in_array($firstToken, ['/audit-sitasi', '/cek-sitasi', '/verifikasi-sitasi', '/sitasi-audit'], true)) {
            return self::SITASI;
        }
        if (in_array($firstToken, ['/perbaiki-ejaan', '/ejaan', '/spelling'], true)) {
            return self::PUEBI;
        }
        if (in_array($firstToken, ['/metode', '/methodology', '/method'], true)) {
            return self::METODOLOGI;
        }
        if (in_array($firstToken, ['/ringkaskan', '/summary', '/summarize', '/ringkasan'], true)) {
            return self::RINGKAS;
        }
        if (in_array($firstToken, ['/latex', '/equation', '/formula'], true)) {
            return self::RUMUS;
        }
        if (in_array($firstToken, ['/diagram', '/chart', '/figure'], true)) {
            return self::GAMBAR;
        }
        if (in_array($firstToken, ['/abstract'], true)) {
            return self::ABSTRAK;
        }
        if (in_array($firstToken, ['/translate', '/terjemah', '/trans'], true)) {
            return self::TERJEMAHKAN;
        }
        if (in_array($firstToken, ['/paraphrase', '/parafrasa'], true)) {
            return self::PARAFRASE;
        }
        if (in_array($firstToken, ['/matrix', '/matriks', '/lit-matrix'], true)) {
            return self::MATRIKS;
        }
        if (in_array($firstToken, ['/footnotes', '/footnote', '/catatan_kaki', '/catatan-kaki'], true)) {
            return self::CATATAN_KAKI;
        }
        if (in_array($firstToken, ['/guideline', '/pedoman', '/cek_pedoman'], true)) {
            return self::CEK_PEDOMAN;
        }
        if (in_array($firstToken, ['/gap', '/cek_gap'], true)) {
            return self::CEK_GAP;
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
        if (in_array($firstToken, ['/toc', '/outline', '/struktur', '/daftar_isi'], true)) {
            return self::DAFTAR_ISI;
        }
        if (in_array($firstToken, ['/bibliografi', '/pustaka', '/references', '/bibliography', '/daftar_pustaka'], true)) {
            return self::DAFTAR_PUSTAKA;
        }
        if (in_array($firstToken, ['/paragraf', '/aturan-paragraf', '/format-paragraf', '/paragraph-rules', '/paragraph'], true)) {
            return self::FORMAT_PARAGRAF;
        }
        if (in_array($firstToken, ['/readability', '/baca', '/audit-baca', '/skor-baca', '/keterbacaan'], true)) {
            return self::KETERBACAAN;
        }
        if (in_array($firstToken, ['/novelty', '/orisinalitas', '/kontribusi', '/kebaruan'], true)) {
            return self::KEBARUAN;
        }
        if (in_array($firstToken, ['/thesaurus', '/sinonim', '/tesaurus', '/padanan-kata'], true)) {
            return self::TESAURUS;
        }

        return self::tryFrom($firstToken);
    }
}
