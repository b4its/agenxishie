<?php

declare(strict_types=1);

namespace App\Omnigensi\Orchestrator;

use App\Domain\Workspace\Enums\ControlMode;
use App\Models\XishieProject;

/**
 * ExecutionModeRouter: menentukan langkah eksekusi berdasarkan mode kendali.
 *
 * Perilaku "idempotent" di sini berarti router selalu membaca ulang
 * control_mode dari database (bukan dari memori proses) sehingga perubahan
 * mode secara hot-swap langsung dipatuhi pada langkah berikutnya.
 */
final class ExecutionModeRouter
{
    public const RUN_AUTOMATION = 'automation';

    public const RUN_HITL_STEP = 'hitl_step';

    public const RUN_MANUAL_TELEMETRY = 'manual_telemetry';

    public function route(XishieProject $project): string
    {
        // Muat ulang dari DB agar hot-swap mode langsung terlihat.
        $fresh = $project->newQuery()->find($project->getKey());
        $mode = $fresh?->control_mode ?? $project->control_mode;

        return match ($mode) {
            ControlMode::AUTOMATION => self::RUN_AUTOMATION,
            ControlMode::HITL => self::RUN_HITL_STEP,
            ControlMode::MANUAL => self::RUN_MANUAL_TELEMETRY,
        };
    }

    public function modeOf(XishieProject $project): ControlMode
    {
        $fresh = $project->newQuery()->find($project->getKey());

        return $fresh?->control_mode ?? $project->control_mode;
    }
}
