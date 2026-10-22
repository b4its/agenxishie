<?php

declare(strict_types=1);

namespace App\Domain\Personalization\Enums;

enum PublicationTarget: string
{
    case INTERNATIONAL_SCOPUS = 'international_scopus';
    case NATIONAL_SINTA = 'national_sinta';
    case CAMPUS_REPOSITORY = 'campus_repository';

    public function label(): string
    {
        return match ($this) {
            self::INTERNATIONAL_SCOPUS => 'Internasional (Scopus / WoS)',
            self::NATIONAL_SINTA => 'Nasional (SINTA 1-6)',
            self::CAMPUS_REPOSITORY => 'Repositori Kampus + Konversi Publikasi',
        };
    }

    public function scopeType(): string
    {
        return match ($this) {
            self::INTERNATIONAL_SCOPUS => 'scopus',
            self::NATIONAL_SINTA => 'sinta',
            self::CAMPUS_REPOSITORY => 'campus',
        };
    }

    /**
     * Peringkat target yang valid untuk cakupan ini.
     *
     * @return array<int, string>
     */
    public function tierOptions(): array
    {
        return match ($this) {
            self::INTERNATIONAL_SCOPUS => ['Q1', 'Q2', 'Q3', 'Q4', 'WoS'],
            self::NATIONAL_SINTA => ['SINTA 1', 'SINTA 2', 'SINTA 3', 'SINTA 4', 'SINTA 5', 'SINTA 6'],
            self::CAMPUS_REPOSITORY => ['Repositori'],
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
