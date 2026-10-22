<?php

declare(strict_types=1);

namespace App\Domain\Workspace;

use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Models\ProjectStageRecord;
use App\Models\XishieProject;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * StageStateManager: mengelola transisi state Draft -> Proposal -> Final
 * dengan aturan sekuensial yang eksplisit.
 */
final class StageStateManager
{
    /**
     * Inisialisasi seluruh tahap untuk proyek baru.
     */
    public function initialize(XishieProject $project): void
    {
        DB::transaction(function () use ($project): void {
            foreach (ProjectStage::cases() as $stage) {
                $project->stages()->firstOrCreate(
                    ['stage_name' => $stage->value],
                    [
                        'status' => $stage === ProjectStage::DRAFT ? StageStatus::IN_PROGRESS : StageStatus::LOCKED,
                        'started_at' => $stage === ProjectStage::DRAFT ? now() : null,
                    ],
                );
            }

            $project->forceFill(['current_stage' => ProjectStage::DRAFT])->save();
        });
    }

    /**
     * Apakah sebuah tahap boleh dimulai (tahap sebelumnya harus completed).
     */
    public function canStart(XishieProject $project, ProjectStage $stage): bool
    {
        $previous = $stage->previous();

        if ($previous === null) {
            return true;
        }

        $record = $project->stages()->where('stage_name', $previous->value)->first();

        return $record !== null && $record->status === StageStatus::COMPLETED;
    }

    /**
     * Pindahkan proyek ke tahap berikutnya bila memenuhi syarat.
     */
    public function advance(XishieProject $project, ?ProjectStage $to = null): ProjectStageRecord
    {
        $target = $to ?? $project->current_stage->next();

        if ($target === null) {
            throw new InvalidArgumentException('Tahap Final sudah tercapai; tidak ada tahap berikutnya.');
        }

        if (! $this->canStart($project, $target)) {
            $previous = $target->previous();

            throw new InvalidArgumentException(
                sprintf('Tahap %s belum dapat dimulai karena tahap %s belum selesai.', $target->label(), $previous?->label() ?? '-'),
            );
        }

        return DB::transaction(function () use ($project, $target): ProjectStageRecord {
            /** @var ProjectStageRecord $record */
            $record = $project->stages()->firstOrCreate(
                ['stage_name' => $target->value],
                ['started_at' => now()],
            );

            $record->forceFill([
                'status' => StageStatus::IN_PROGRESS,
                'started_at' => $record->started_at ?? now(),
                'completed_at' => null,
            ])->save();

            $project->forceFill(['current_stage' => $target])->save();

            return $record;
        });
    }

    /**
     * Tandai tahap selesai.
     */
    public function complete(XishieProject $project, ProjectStage $stage): ProjectStageRecord
    {
        /** @var ProjectStageRecord $record */
        $record = $project->stages()->firstOrCreate(['stage_name' => $stage->value]);

        $record->forceFill([
            'status' => StageStatus::COMPLETED,
            'completed_at' => now(),
            'compiled_content' => $record->compile(),
        ])->save();

        $record->recalculateWordCount();

        return $record;
    }

    /**
     * Status seluruh tahap dalam urutan.
     *
     * @return array<int, array{stage: string, label: string, status: string}>
     */
    public function progression(XishieProject $project): array
    {
        $records = $project->stages()->get()->keyBy(static fn ($record): string => $record->stage_name->value);

        $progression = [];

        foreach (ProjectStage::cases() as $stage) {
            $record = $records->get($stage->value);

            $progression[] = [
                'stage' => $stage->value,
                'label' => $stage->label(),
                'status' => $record?->status->value ?? StageStatus::LOCKED->value,
            ];
        }

        return $progression;
    }
}
