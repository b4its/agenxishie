<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Revision\Enums\RevisionStatus;
use App\Models\XishieProject;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

/**
 * SmartRevisionCenterPage: pusat revisi dosen/reviewer dengan checklist,
 * side-by-side diff, dan matriks Response to Reviewers.
 */
class SmartRevisionCenterPage extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-arrow-path-rounded-square';

    protected static ?string $navigationLabel = 'Smart Revision Center';

    protected static ?string $title = 'Smart Revision Center';

    protected static ?int $navigationSort = 3;

    protected string $view = 'filament.pages.smart-revision-center';

    public ?int $projectId = null;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->projectId = request()->query('project') !== null
            ? (int) request()->query('project')
            : XishieProject::query()->latest()->value('id');

        $this->form->fill([
            'reviewer_role' => 'pembimbing_1',
            'raw_feedback' => '',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                Select::make('reviewer_role')
                    ->label('Sumber Masukan')
                    ->options([
                        'pembimbing_1' => 'Dosen Pembimbing 1',
                        'pembimbing_2' => 'Dosen Pembimbing 2',
                        'penguji' => 'Dosen Penguji',
                        'reviewer_jurnal' => 'Reviewer Jurnal',
                    ])
                    ->required(),

                Textarea::make('raw_feedback')
                    ->label('Catatan Revisi (satu poin per baris)')
                    ->rows(6)
                    ->required()
                    ->placeholder("1. Perbaiki rumusan masalah pada Bab 1\n2. Tambahkan sitasi terbaru pada Bab 2\n3. Metodologi kurang rinci"),
            ]);
    }

    public function analyseFeedback(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $state = $this->form->getState();
        $stage = app(XishieSupervisor::class)->ensureStage($project, $project->current_stage);

        app(PeerCriticAgent::class)->execute($project, $stage, [
            'raw_feedback' => (string) $state['raw_feedback'],
            'reviewer_role' => (string) $state['reviewer_role'],
        ]);

        Notification::make()->title('Catatan revisi dianalisis')->success()->send();

        $this->form->fill(['reviewer_role' => $state['reviewer_role'], 'raw_feedback' => '']);
    }

    public function markResolved(int $revisionId, int $itemId): void
    {
        $revision = $this->getProject()?->revisions()->find($revisionId);

        if ($revision === null) {
            return;
        }

        $items = (array) ($revision->action_items ?? []);

        foreach ($items as $index => $item) {
            if ((int) ($item['id'] ?? 0) === $itemId) {
                $items[$index]['status'] = RevisionStatus::RESOLVED->value;
            }
        }

        $revision->forceFill([
            'action_items' => $items,
            'response_matrix_doc' => app(PeerCriticAgent::class)->renderResponseMatrix(
                $revision->fresh() ?? $revision,
            ),
        ])->save();

        if ($revision->resolutionPercentage() >= 100.0) {
            $revision->forceFill(['status' => RevisionStatus::RESOLVED])->save();
        } else {
            $revision->forceFill(['status' => RevisionStatus::IN_PROGRESS])->save();
        }

        Notification::make()->title('Butir revisi ditandai selesai')->success()->send();
    }

    public function getProject(): ?XishieProject
    {
        return $this->projectId !== null ? XishieProject::find($this->projectId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->getProject();

        if ($project === null) {
            return ['project' => null, 'revisions' => collect()];
        }

        return [
            'project' => $project,
            'revisions' => $project->revisions()->latest('id')->get(),
        ];
    }
}
