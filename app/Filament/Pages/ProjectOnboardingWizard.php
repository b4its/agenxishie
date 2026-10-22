<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\Personalization\Enums\DocumentType;
use App\Domain\Personalization\Enums\FieldOfStudy;
use App\Domain\Personalization\Enums\PublicationTarget;
use App\Domain\Personalization\ProjectOnboardingService;
use App\Domain\Workspace\Enums\ControlMode;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

/**
 * ProjectOnboardingWizard: 4 langkah personalisasi proyek.
 */
class ProjectOnboardingWizard extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Onboarding Wizard';

    protected static ?string $title = 'Project Onboarding Wizard';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.project-onboarding-wizard';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public int $step = 1;

    public function mount(): void
    {
        $this->form->fill([
            'title' => '',
            'field_of_study' => FieldOfStudy::STEM->value,
            'document_type' => DocumentType::JOURNAL->value,
            'target_scope' => PublicationTarget::NATIONAL_SINTA->value,
            'target_tier' => 'SINTA 2',
            'guideline_type' => 'default',
            'citation_style' => CitationStyle::IEEE->value,
            'control_mode' => ControlMode::HITL->value,
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->statePath('data')
            ->schema([
                TextInput::make('title')
                    ->label('Judul Rencana Penelitian')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Contoh: Deteksi Anomali Jaringan Menggunakan Deep Learning')
                    ->visible(fn (): bool => $this->step === 1),

                Select::make('field_of_study')
                    ->label('Bidang Keilmuan')
                    ->options(FieldOfStudy::options())
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->step === 1),

                Select::make('document_type')
                    ->label('Jenis Topik / Dokumen')
                    ->options(DocumentType::options())
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->step === 2),

                Select::make('target_scope')
                    ->label('Target Akhir')
                    ->options(PublicationTarget::options())
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->step === 2),

                Select::make('target_tier')
                    ->label('Peringkat Target')
                    ->options(fn (): array => $this->tierOptions())
                    ->visible(fn (): bool => $this->step === 2),

                Select::make('guideline_type')
                    ->label('Konfigurasi Panduan Penulisan')
                    ->options([
                        'default' => 'Preset Default (otomatis sesuai jenis topik)',
                        'custom' => 'Upload Pedoman Custom (PDF/DOCX)',
                    ])
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->step === 3),

                Select::make('citation_style')
                    ->label('Gaya Sitasi')
                    ->options(CitationStyle::options())
                    ->required()
                    ->visible(fn (): bool => $this->step === 3),

                FileUpload::make('custom_guideline_path')
                    ->label('Berkas Pedoman (PDF/DOCX)')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->directory('guidelines')
                    ->visible(fn (): bool => $this->step === 3 && ($this->data['guideline_type'] ?? 'default') === 'custom'),

                Toggle::make('acknowledge_control_mode')
                    ->label('Saya memahami cara kerja basis kendali yang dipilih')
                    ->required()
                    ->visible(fn (): bool => $this->step === 4),

                Select::make('control_mode')
                    ->label('Basis Kendali Omnigensi')
                    ->options(ControlMode::options())
                    ->required()
                    ->live()
                    ->visible(fn (): bool => $this->step === 4),

                Textarea::make('control_mode_hint')
                    ->label('Penjelasan Mode')
                    ->disabled()
                    ->dehydrated(false)
                    ->rows(3)
                    ->visible(fn (): bool => $this->step === 4)
                    ->formatStateUsing(fn (): string => $this->controlModeDescription()),
            ]);
    }

    public function nextStep(): void
    {
        $this->step = min(4, $this->step + 1);
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function createProject(): void
    {
        // Hanya boleh membuat proyek dari langkah terakhir.
        if ($this->step < 4) {
            Notification::make()
                ->title('Selesaikan seluruh langkah wizard terlebih dahulu')
                ->warning()
                ->send();

            return;
        }

        if (! $this->validateStep()) {
            return;
        }

        $state = $this->form->getState();
        $documentType = DocumentType::from((string) $state['document_type']);

        $project = app(ProjectOnboardingService::class)->create(
            userId: (int) Auth::id(),
            data: [
                'title' => (string) $state['title'],
                'field_of_study' => (string) $state['field_of_study'],
                'document_type' => $documentType->value,
                'target_scope' => (string) $state['target_scope'],
                'target_tier' => $state['target_tier'] ?? null,
                'control_mode' => (string) $state['control_mode'],
                'guideline_type' => (string) ($state['guideline_type'] ?? 'default'),
                'citation_style' => (string) ($state['citation_style'] ?? $documentType->defaultCitationStyle()),
                'custom_guideline_path' => $state['custom_guideline_path'] ?? null,
            ],
        );

        // Jalankan pipeline sesuai mode kendali yang dipilih.
        app(XishieSupervisor::class)->run($project);

        Notification::make()
            ->title('Proyek berhasil dibuat')
            ->body("Pipeline mode {$project->control_mode->label()} telah dijalankan untuk tahap Draft.")
            ->success()
            ->send();

        $this->redirect(AcademicWorkspacePage::getUrl(['project' => $project->id]));
    }

    protected function validateStep(): bool
    {
        $fields = match ($this->step) {
            1 => ['title', 'field_of_study'],
            2 => ['document_type', 'target_scope'],
            3 => ['guideline_type', 'citation_style'],
            default => ['control_mode', 'acknowledge_control_mode'],
        };

        $state = $this->form->getState();

        foreach ($fields as $field) {
            $value = $state[$field] ?? null;

            if ($value === null || $value === '' || $value === false) {
                Notification::make()
                    ->title('Lengkapi langkah ini terlebih dahulu')
                    ->body("Field '{$field}' wajib diisi.")
                    ->warning()
                    ->send();

                return false;
            }
        }

        return true;
    }

    /**
     * @return array<string, string>
     */
    private function tierOptions(): array
    {
        $scope = PublicationTarget::tryFrom((string) ($this->data['target_scope'] ?? '')) ?? PublicationTarget::NATIONAL_SINTA;

        return array_combine($scope->tierOptions(), $scope->tierOptions()) ?: [];
    }

    private function controlModeDescription(): string
    {
        $mode = ControlMode::tryFrom((string) ($this->data['control_mode'] ?? '')) ?? ControlMode::HITL;

        return $mode->description();
    }
}
