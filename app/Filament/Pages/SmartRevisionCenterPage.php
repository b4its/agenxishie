<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Research\PdfTextExtractor;
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
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
     * Berkas dokumen komentar reviewer (PDF/DOCX) — PRD 3.5 input multi-sumber.
     */
    public mixed $feedbackUpload = null;

    public string $feedbackImportStatus = '';

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

    /**
     * Impor catatan revisi dari berkas PDF/DOCX yang diunggah reviewer
     * (PRD 3.5: "teks poin-poin atau unggahan dokumen komentar").
     */
    public function updatedFeedbackUpload(): void
    {
        if ($this->feedbackUpload === null) {
            return;
        }

        try {
            $binary = $this->feedbackUpload->get();
            $filename = (string) $this->feedbackUpload->getClientOriginalName();

            $text = str_ends_with(mb_strtolower($filename), '.pdf')
                ? app(PdfTextExtractor::class)->extract($binary)
                : $this->extractPlainText($binary);

            if (trim($text) === '') {
                throw new \RuntimeException('Tidak ada teks yang dapat diekstrak dari berkas komentar.');
            }

            $state = $this->form->getState();

            $this->form->fill([
                'reviewer_role' => $state['reviewer_role'] ?? 'reviewer_jurnal',
                'raw_feedback' => $text,
            ]);

            $this->feedbackImportStatus = 'OK: catatan revisi dimuat dari '.$filename;

            Notification::make()->title('Dokumen komentar dimuat')->success()->send();
        } catch (\Throwable $exception) {
            $this->feedbackImportStatus = 'Gagal: '.$exception->getMessage();

            Notification::make()->title('Gagal memuat dokumen')->body($exception->getMessage())->danger()->send();
        } finally {
            $this->feedbackUpload = null;
        }
    }

    /**
     * Ekstraksi teks sederhana (DOCX = arsip zip berisi word/document.xml).
     */
    private function extractPlainText(string $binary): string
    {
        $plain = mb_convert_encoding($binary, 'UTF-8', 'UTF-8');

        // DOCX: baca word/document.xml dari arsip zip.
        if (str_starts_with($plain, "PK\x03\x04") && class_exists(\ZipArchive::class)) {
            $temp = tempnam(sys_get_temp_dir(), 'xishie-docx-');

            if ($temp !== false) {
                file_put_contents($temp, $binary);
                $zip = new \ZipArchive;

                if ($zip->open($temp) === true) {
                    $xml = $zip->getFromName('word/document.xml');

                    if (is_string($xml)) {
                        $text = strip_tags((string) preg_replace('/<\/w:p>/u', "\n", $xml));
                        $plain = html_entity_decode($text, ENT_QUOTES | ENT_XML1, 'UTF-8');
                    }

                    $zip->close();
                }

                @unlink($temp);
            }
        }

        // Fallback: buang karakter non-printable dari berkas teks biasa.
        return trim((string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/u', ' ', $plain));
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

        app(PeerCriticAgent::class)->run($project, $stage, [
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
     * Unduh "Response to Reviewers" (matriks perbaikan revisi) sebagai Markdown.
     */
    public function downloadResponseMatrix(int $revisionId): ?StreamedResponse
    {
        $revision = $this->getProject()?->revisions()->find($revisionId);

        if ($revision === null) {
            Notification::make()->title('Revisi tidak ditemukan')->warning()->send();

            return null;
        }

        $project = $this->getProject();
        $base = Str::slug((string) ($project?->title ?? 'naskah')) ?: 'naskah';
        $filename = $base.'-response-to-reviewers-'.$revision->id.'.md';

        $body = '# Response to Reviewers'."\n\n";
        $body .= 'Proyek: '.($project?->title ?? '-')."\n";
        $body .= 'Reviewer: '.$revision->reviewer_role."\n";
        $body .= 'Status: '.$revision->status->label().' ('.$revision->resolutionPercentage()."% selesai)\n\n";
        $body .= (string) ($revision->response_matrix_doc ?? '')."\n";

        return response()->streamDownload(
            static fn () => print $body,
            $filename,
            ['Content-Type' => 'text/markdown'],
        );
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
