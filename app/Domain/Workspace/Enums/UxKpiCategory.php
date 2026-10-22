<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Enums;

enum UxKpiCategory: string
{
    case TASK_SUCCESS = 'task_success';
    case TASK_QUALITY = 'task_quality';
    case EFFICIENCY = 'efficiency';
    case HAPPINESS = 'happiness';
    case ADOPTION = 'adoption';
    case ENGAGEMENT = 'engagement';
    case ERROR_RATE = 'error_rate';
    case PERFORMANCE = 'performance';
    case ACQUISITION = 'acquisition';
    case RETENTION = 'retention';
    case CONVERSION = 'conversion';
    case REFERRAL = 'referral';

    public function label(): string
    {
        return match ($this) {
            self::TASK_SUCCESS => 'Task Success (UX)',
            self::TASK_QUALITY => 'Task Quality (UX)',
            self::EFFICIENCY => 'Efficiency (UX)',
            self::HAPPINESS => 'Happiness (HEART)',
            self::ADOPTION => 'Adoption (HEART)',
            self::ENGAGEMENT => 'Engagement (HEART)',
            self::ERROR_RATE => 'Error Rate (UX)',
            self::PERFORMANCE => 'Performance (UX)',
            self::ACQUISITION => 'Acquisition / Activation (AARRR)',
            self::RETENTION => 'Retention (AARRR)',
            self::CONVERSION => 'Conversion (AARRR)',
            self::REFERRAL => 'Referral / Expansion (AARRR)',
        };
    }

    public function framework(): string
    {
        return match ($this) {
            self::HAPPINESS, self::ADOPTION, self::ENGAGEMENT => 'HEART',
            self::ACQUISITION, self::RETENTION, self::CONVERSION, self::REFERRAL => 'AARRR',
            default => 'UX',
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
