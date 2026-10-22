<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Enums;

enum StageStatus: string
{
    case LOCKED = 'locked';
    case IN_PROGRESS = 'in_progress';
    case WAITING_APPROVAL = 'waiting_approval';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::LOCKED => 'Terkunci',
            self::IN_PROGRESS => 'Sedang Berjalan',
            self::WAITING_APPROVAL => 'Menunggu Persetujuan',
            self::COMPLETED => 'Selesai',
        };
    }

    public function isFinished(): bool
    {
        return $this === self::COMPLETED;
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::IN_PROGRESS, self::WAITING_APPROVAL], true);
    }

    public function color(): string
    {
        return match ($this) {
            self::LOCKED => 'gray',
            self::IN_PROGRESS => 'info',
            self::WAITING_APPROVAL => 'warning',
            self::COMPLETED => 'success',
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
