<?php

declare(strict_types=1);

namespace App\Domain\GuidelineEngine\Enums;

enum CitationStyle: string
{
    case APA7 = 'apa7';
    case IEEE = 'ieee';
    case VANCOUVER = 'vancouver';
    case CHICAGO = 'chicago';
    case HARVARD = 'harvard';

    public function label(): string
    {
        return match ($this) {
            self::APA7 => 'APA 7th Edition',
            self::IEEE => 'IEEE',
            self::VANCOUVER => 'Vancouver',
            self::CHICAGO => 'Chicago',
            self::HARVARD => 'Harvard',
        };
    }

    /**
     * Pola sitasi dalam teks yang dikenali (dipakai validator).
     *
     * Semua gaya menerima bentuk kunci sitasi internal proyek "[Author, Year]"
     * karena AcademicScribeAgent menulis sitasi terikat pada project_references,
     * di samping bentuk baku masing-masing gaya.
     */
    public function inTextPattern(): string
    {
        // Bentuk internal: [Prasetyo, 2023], [Prasetyo 2023], [Kumar et al., 2022].
        $internal = '\[[A-Z][A-Za-z\-]+(?:\s(?:et al\.|dkk\.|&|dan)\s?[A-Za-z\-]*)?,?\s\d{4}[a-z]?\]';

        return match ($this) {
            self::APA7 => '/(?:'.$internal.'|\([A-Z][A-Za-z\-]+(?:\s(?:et al\.|&|dan)\s?[A-Za-z\-]*)?,\s\d{4}[a-z]?\))/u',
            self::IEEE => '/(?:'.$internal.'|\[\d{1,3}(?:[,\-]\s?\d{1,3})*\])/u',
            self::VANCOUVER => '/(?:'.$internal.'|\(\d{1,3}(?:[,\-]\s?\d{1,3})*\))/u',
            self::CHICAGO => '/(?:'.$internal.'|\([A-Z][A-Za-z\-]+\s\d{4}(?::\s?\d+)?\))/u',
            self::HARVARD => '/(?:'.$internal.'|\([A-Z][A-Za-z\-]+(?:\s(?:et al\.|&|dan)\s?[A-Za-z\-]*)?,\s\d{4}[a-z]?\))/u',
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
