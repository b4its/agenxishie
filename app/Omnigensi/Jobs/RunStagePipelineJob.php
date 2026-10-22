<?php

declare(strict_types=1);

namespace App\Omnigensi\Jobs;

use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Models\XishieProject;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Bus\PendingDispatch;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job background untuk eksekusi pipeline Omnigensi mode Automation.
 *
 * PRD: "Menjalankan pipeline penuh ... melalui Laravel Queue Jobs beruntun.
 * Jika pengguna mengubah control_mode saat job berjalan, hentikan rantai
 * otomatis sesuai mode baru."
 */
final class RunStagePipelineJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public readonly int $projectId,
        public readonly string $stage,
        public readonly array $context = [],
    ) {}

    public static function dispatchFor(XishieProject $project, ProjectStage $stage, array $context = []): PendingDispatch
    {
        return self::dispatch($project->id, $stage->value, $context);
    }

    public function handle(XishieSupervisor $supervisor): void
    {
        $project = XishieProject::find($this->projectId);

        if ($project === null) {
            return;
        }

        // Idempotency: baca ulang control_mode dari database sebelum eksekusi.
        // Bila pengguna sudah beralih ke HITL/Manual, job Automation tidak boleh
        // menulis otomatis lagi.
        if ($project->control_mode !== ControlMode::AUTOMATION) {
            Log::info('RunStagePipelineJob dilewati: control_mode berubah.', [
                'project_id' => $project->id,
                'control_mode' => $project->control_mode->value,
            ]);

            return;
        }

        $supervisor->run($project, ProjectStage::from($this->stage), $this->context);
    }
}
