<?php

declare(strict_types=1);

namespace App\Domain\GuidelineEngine\Enums;

enum CitationStyle: string
{
    case APA7 = 'apa7';
    case IEEE = 'ieee';
    case VANCOUVER = 'vancouver';
    case CHICAGO = 'chicago';

    public function label(): string
    {
        return match ($this) {
            self::APA7 => 'APA 7th Edition',
            self::IEEE => 'IEEE',
            self::VANCOUVER => 'Vancouver',
            self::CHICAGO => 'Chicago',
        };
    }

    /**
     * Pola sitasi dalam teks yang dikenali (dipakai validator).
     */
    public function inTextPattern(): string
    {
        return match ($this) {
            self::APA7 => '/\([A-Z][A-Za-z\-]+(?:\s(?:et al\.|&|dan)\s?[A-Za-z\-]*)?,\s\d{4}[a-z]?\)/u',
            self::IEEE => '/\[\d{1,3}(?:[,\-]\s?\d{1,3})*\]/u',
            self::VANCOUVER => '/\(\d{1,3}(?:[,\-]\s?\d{1,3})*\)/u',
            self::CHICAGO => '/\([A-Z][A-Za-z\-]+\s\d{4}(?::\s?\d+)?\)/u',
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
