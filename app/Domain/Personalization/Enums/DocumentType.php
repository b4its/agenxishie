<?php

declare(strict_types=1);

namespace App\Domain\Personalization\Enums;

enum DocumentType: string
{
    case JOURNAL = 'journal';
    case SKRIPSI = 'skripsi';
    case THESIS = 'thesis';
    case DISSERTATION = 'dissertation';

    public function label(): string
    {
        return match ($this) {
            self::JOURNAL => 'Jurnal Ilmiah',
            self::SKRIPSI => 'Skripsi',
            self::THESIS => 'Tesis',
            self::DISSERTATION => 'Disertasi',
        };
    }

    public function isThesisFamily(): bool
    {
        return $this !== self::JOURNAL;
    }

    /**
     * Kedalaman teori yang dituntut untuk jenis dokumen.
     */
    public function theoryDepth(): int
    {
        return match ($this) {
            self::JOURNAL => 3,
            self::SKRIPSI => 4,
            self::THESIS => 5,
            self::DISSERTATION => 6,
        };
    }

    /**
     * Tingkat tuntutan kebaruan (novelty).
     */
    public function noveltyRequirement(): string
    {
        return match ($this) {
            self::JOURNAL => 'kebaruan kontribusi ilmiah yang terukur pada satu permasalahan',
            self::SKRIPSI => 'pengembangan atau penerapan metodologi pada kasus terpilih',
            self::THESIS => 'pengembangan model/metode dengan validasi empiris',
            self::DISSERTATION => 'kontribusi teoretis orisinal dan pengembangan teori baru',
        };
    }

    public function defaultCitationStyle(): string
    {
        return match ($this) {
            self::JOURNAL => 'ieee',
            self::SKRIPSI => 'apa7',
            self::THESIS => 'apa7',
            self::DISSERTATION => 'apa7',
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
