<?php

declare(strict_types=1);

namespace App\Omnigensi\Jobs;

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Menjalankan rantai pipeline berurutan Draft -> Proposal -> Final
 * di background queue sesuai PRD mode Automation.
 */
final class RunFullPipelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 900;

    public function __construct(
        public readonly int $projectId,
    ) {}

    public static function dispatchFor(XishieProject $project): PendingDispatch
    {
        return self::dispatch($project->id);
    }

    public function handle(): void
    {
        $project = XishieProject::find($this->projectId);

        if ($project === null || $project->control_mode !== ControlMode::AUTOMATION) {
            return;
        }

        foreach (ProjectStage::cases() as $stage) {
            // Hentikan rantai bila mode berubah di tengah jalan (hot-swap).
            $fresh = XishieProject::find($this->projectId);

            if ($fresh === null || $fresh->control_mode !== ControlMode::AUTOMATION) {
                return;
            }

            RunStagePipelineJob::dispatch($this->projectId, $stage->value);
        }
    }
}
