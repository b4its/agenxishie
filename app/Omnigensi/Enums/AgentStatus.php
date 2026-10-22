<?php

declare(strict_types=1);

namespace App\Omnigensi\Enums;

enum AgentStatus: string
{
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case WAITING_APPROVAL = 'waiting_approval';

    public function label(): string
    {
        return match ($this) {
            self::SUCCESS => 'Sukses',
            self::FAILED => 'Gagal',
            self::SKIPPED => 'Dilewati',
            self::WAITING_APPROVAL => 'Menunggu Persetujuan',
        };
    }

    public function isSuccessful(): bool
    {
        return $this === self::SUCCESS;
    }
}
