<?php

declare(strict_types=1);

namespace App\Domain\Revision\Enums;

enum RevisionStatus: string
{
    case PENDING = 'pending';
    case IN_PROGRESS = 'in_progress';
    case RESOLVED = 'resolved';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::IN_PROGRESS => 'Dikerjakan',
            self::RESOLVED => 'Selesai',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::PENDING => 'danger',
            self::IN_PROGRESS => 'warning',
            self::RESOLVED => 'success',
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
