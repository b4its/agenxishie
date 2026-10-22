<?php

declare(strict_types=1);

namespace App\Domain\Workspace\Enums;

enum ControlMode: string
{
    case AUTOMATION = 'automation';
    case HITL = 'hitl';
    case MANUAL = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::AUTOMATION => 'Automation',
            self::HITL => 'Human-in-the-Loop',
            self::MANUAL => 'Manual',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::AUTOMATION => 'AUTO',
            self::HITL => 'HITL',
            self::MANUAL => 'MANUAL',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AUTOMATION => 'Agen menjalankan pipeline penuh secara otomatis di background queue.',
            self::HITL => 'Agen berhenti di setiap sub-bab dan menunggu persetujuan pengguna.',
            self::MANUAL => 'Agen tidak menulis otomatis; hanya membantu saat dipanggil pengguna.',
        };
    }

    public function runsAutomatically(): bool
    {
        return $this === self::AUTOMATION;
    }

    public function requiresApproval(): bool
    {
        return $this === self::HITL;
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
