<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\XishieAssistantService;
use App\Domain\GuidelineEngine\Enums\CitationStyle;
use App\Domain\GuidelineEngine\GuidelineResolverService;
use App\Domain\Publication\ManuscriptExporter;
use App\Domain\Research\LiteratureMatrixBuilder;
use App\Domain\Research\ReferenceImportService;
use App\Domain\Workspace\Enums\ControlMode;
use App\Domain\Workspace\Enums\ProjectStage;
use App\Domain\Workspace\Enums\StageStatus;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\StageStateManager;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\OmnigensiAgentLog;
use App\Models\ProjectRevision;
use App\Models\ProjectStageRecord;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use App\Omnigensi\Agents\AcademicScribeAgent;
use App\Omnigensi\Agents\PeerCriticAgent;
use App\Omnigensi\Agents\RuleKeeperAgent;
use App\Omnigensi\Agents\ScholarScoutAgent;
use App\Omnigensi\Agents\VenueMatcherAgent;
use App\Omnigensi\Orchestrator\XishieSupervisor;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AcademicWorkspacePage: layar kerja utama 3 panel (navigator & riset,
 * editor naskah + HITL gate, Xishie Assistant & telemetri).
 */
class AcademicWorkspacePage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static ?string $navigationLabel = 'Academic Workspace';

    protected static ?string $slug = 'academic-workspace';

    protected static ?string $title = 'Academic Workspace & Editor Naskah';

    protected static ?int $navigationSort = 3;

    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.academic-workspace';

    public ?int $projectId = null;

    public string $assistantMessage = '';

    /**
     * Berkas PDF referensi yang diunggah pengguna (Livewire temporary upload).
     */
    public mixed $referenceUpload = null;

    public string $importStatus = '';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $assistantLog = [];

    /**
     * State editor naskah inline: daftar sub-bab yang sedang disunting.
     *
     * @var array<int, array{key: string, heading: string, content: string}>
     */
    public array $editorSections = [];

    /**
     * Penanda ada perubahan yang belum disimpan ke database.
     */
    public bool $editorDirty = false;

    /**
     * Waktu simpan terakhir (untuk umpan balik UI).
     */
    public ?string $editorSavedAt = null;

    /**
     * Apakah state editor sudah disinkronkan dari database pada request ini.
     */
    public bool $editorHydrated = false;

    public function mount(): void
    {
        $projectParam = request()->query('project');

        $this->projectId = $projectParam !== null
            ? (int) $projectParam
            : XishieProject::query()->latest()->value('id');

        $this->hydrateEditor();
    }

    /**
     * Muat ulang state editor dari sub-bab tahap aktif.
     */
    public function hydrateEditor(): void
    {
        $stage = $this->stage();

        $this->editorSections = $this->normalizeSections((array) ($stage?->sections_content ?? []));
        $this->editorDirty = false;
        $this->editorHydrated = true;
        $this->editorSavedAt = $stage?->updated_at?->diffForHumans();
    }

    /**
     * Sinkronkan editor dengan data terbaru saat jumlah sub-bab di DB berubah
     * (mis. setelah generateFullDraft / regenerate / ganti proyek).
     */
    public function syncEditorIfStale(): void
    {
        if ($this->editorDirty) {
            return;
        }

        $stage = $this->stage();
        $dbSections = $this->normalizeSections((array) ($stage?->sections_content ?? []));

        if (! $this->editorHydrated || count($dbSections) !== count($this->editorSections)) {
            $this->hydrateEditor();
        }
    }

    /**
     * Normalisasi sub-bab menjadi struktur {key, heading, content} yang konsisten.
     *
     * @param  array<int|string, mixed>  $sections
     * @return array<int, array{key: string, heading: string, content: string}>
     */
    private function normalizeSections(array $sections): array
    {
        $normalized = [];

        foreach (array_values($sections) as $index => $section) {
            if (! is_array($section)) {
                continue;
            }

            $heading = trim((string) ($section['heading'] ?? ''));
            $key = trim((string) ($section['key'] ?? ''));

            if ($key === '') {
                $key = Str::slug($heading) ?: 'section-'.($index + 1);
            }

            $normalized[] = [
                'key' => $key,
                'heading' => $heading,
                'content' => (string) ($section['content'] ?? ''),
            ];
        }

        return $normalized;
    }

    /**
     * Tandai editor berubah (dipanggil dari wire:model pengikatan input).
     */
    public function markEditorDirty(): void
    {
        $this->editorDirty = true;
    }

    /**
     * Simpan seluruh sub-bab hasil suntingan inline ke tahap aktif.
     * Hanya memperbarui isi naskah — status tahap tidak diubah menjadi selesai.
     */
    public function saveEditor(): void
    {
        $project = $this->getProject();
        $stage = $this->stage();

        if ($project === null || $stage === null) {
            Notification::make()->title('Tidak ada tahap aktif untuk disimpan')->warning()->send();

            return;
        }

        $sections = [];
        foreach ($this->normalizeSections($this->editorSections) as $section) {
            $sections[] = [
                'key' => $section['key'],
                'heading' => $section['heading'],
                'content' => $section['content'],
                'citation_keys' => [],
            ];
        }

        $stage->forceFill([
            'sections_content' => $sections,
            'compiled_content' => null,
        ])->save();
        $stage->recalculateWordCount();
        $stage->forceFill(['compiled_content' => $stage->compile()])->save();

        $this->hydrateEditor();
        $this->editorSavedAt = 'baru saja';

        Notification::make()
            ->title('Naskah tersimpan')
            ->body($stage->word_count.' kata · '.count($sections).' sub-bab disimpan.')
            ->success()
            ->send();
    }

    /**
     * Tambah sub-bab kosong baru di akhir naskah.
     */
    public function addEditorSection(): void
    {
        $this->editorSections[] = [
            'key' => 'custom_'.(count($this->editorSections) + 1),
            'heading' => 'Sub-bab Baru',
            'content' => '',
        ];
        $this->editorDirty = true;
    }

    /**
     * Hapus satu sub-bab dari naskah berdasarkan indeks.
     */
    public function removeEditorSection(int $index): void
    {
        if (! isset($this->editorSections[$index])) {
            return;
        }

        array_splice($this->editorSections, $index, 1);
        $this->editorSections = array_values($this->editorSections);
        $this->editorDirty = true;
    }

    /**
     * Duplikasi satu sub-bab dari naskah beserta isinya.
     */
    public function duplicateEditorSection(int $index): void
    {
        if (! isset($this->editorSections[$index])) {
            return;
        }

        $source = $this->editorSections[$index];
        $clone = [
            'key' => $source['key'].'_salinan_'.(count($this->editorSections) + 1),
            'heading' => $source['heading'].' (Salinan)',
            'content' => $source['content'],
        ];

        array_splice($this->editorSections, $index + 1, 0, [$clone]);
        $this->editorSections = array_values($this->editorSections);
        $this->editorDirty = true;

        Notification::make()
            ->title('Sub-bab diduplikasi')
            ->body("'{$clone['heading']}' ditambahkan.")
            ->success()
            ->send();
    }

    /**
     * Geser sub-bab naik/turun untuk mengatur urutan naskah.
     */
    public function moveEditorSection(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (! isset($this->editorSections[$index], $this->editorSections[$target])) {
            return;
        }

        [$this->editorSections[$index], $this->editorSections[$target]] = [
            $this->editorSections[$target],
            $this->editorSections[$index],
        ];
        $this->editorDirty = true;
    }

    /**
     * Pindah proyek aktif dari workspace (multi-proyek).
     */
    public function switchProject(int $projectId): void
    {
        $project = XishieProject::find($projectId);

        if ($project === null) {
            Notification::make()->title('Proyek tidak ditemukan')->danger()->send();

            return;
        }

        $this->projectId = $project->id;

        Notification::make()->title('Proyek aktif: '.$project->title)->success()->send();

        $this->dispatch('$refresh');
    }

    public function getProject(): ?XishieProject
    {
        return $this->projectId !== null ? XishieProject::find($this->projectId) : null;
    }

    public function switchControlMode(string $mode): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $controlMode = ControlMode::tryFrom($mode);

        if ($controlMode === null) {
            return;
        }

        $project->forceFill(['control_mode' => $controlMode])->save();

        Notification::make()
            ->title("Mode kendali diubah ke {$controlMode->label()}")
            ->success()
            ->send();
    }

    public function runPipeline(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $result = app(XishieSupervisor::class)->run($project);

        Notification::make()
            ->title('Pipeline Omnigensi dijalankan')
            ->body('Mode: '.$result['mode'].'. '.(isset($result['compliance_score']) ? 'Compliance: '.$result['compliance_score'].'%.' : ''))
            ->success()
            ->send();
    }

    /**
     * Jalankan graf multi-agen dinamis (GraphRunner) untuk tahap aktif.
     *
     * Berbeda dari `runPipeline` (pipeline tetap per control_mode), aksi ini
     * menelusuri skema graf: urutan agen, siklus reviewer, dan conditional
     * edge ditentukan oleh graf. Cocok untuk eksperimen alur kolaborasi agen.
     */
    public function runMultiAgentGraph(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        try {
            $outcome = app(XishieSupervisor::class)->runGraph(
                $project,
                $project->current_stage,
                null,
                [],
                2,
            );
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Gagal menjalankan graf multi-agen')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return;
        }

        $executed = implode(' -> ', $outcome['graph']['nodes_executed'] ?? []);

        Notification::make()
            ->title('Graf multi-agen selesai')
            ->body(sprintf(
                'Node: %s | Revisi: %d | Disetujui: %s',
                $executed,
                (int) ($outcome['graph']['revision_count'] ?? 0),
                ($outcome['graph']['is_approved'] ?? false) ? 'ya' : 'belum',
            ))
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    public function advanceStage(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        try {
            app(StageStateManager::class)->advance($project);
        } catch (\InvalidArgumentException $exception) {
            Notification::make()->title('Tidak dapat lanjut')->body($exception->getMessage())->warning()->send();

            return;
        }

        $project->refresh();

        // Otomatis susun draf lengkap untuk tahap baru agar tidak kosong.
        $generated = $this->buildDraftForCurrentStage($project);

        Notification::make()
            ->title('Tahap berikutnya dibuka: '.$project->current_stage->label())
            ->body($generated
                ? 'Draf awal telah disusun otomatis ('.$generated.' sub-bab).'
                : 'Klik "Susun Draf Lengkap" untuk menulis naskah tahap ini.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Berpindah tampilan ke tahap yang telah terbuka (Draft / Proposal / Final).
     */
    public function selectStage(string $stageName): void
    {
        $project = $this->getProject();
        if ($project === null) {
            return;
        }

        $target = ProjectStage::tryFrom($stageName);
        if ($target === null) {
            return;
        }

        $stageRecord = $project->stages()->where('stage_name', $target->value)->first();
        if ($stageRecord === null || $stageRecord->status === StageStatus::LOCKED) {
            Notification::make()
                ->title('Tahap terkunci')
                ->body('Selesaikan tahap sebelumnya terlebih dahulu sebelum beralih ke tahap ini.')
                ->warning()
                ->send();

            return;
        }

        $project->forceFill(['current_stage' => $target])->save();

        Notification::make()
            ->title('Beralih ke tahap: '.$target->label())
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Reset / bersihkan draf naskah pada tahap aktif untuk disusun ulang.
     */
    public function resetStageContent(): void
    {
        $project = $this->getProject();
        $stage = $this->stage();

        if ($project === null || $stage === null) {
            return;
        }

        $stage->forceFill([
            'sections_content' => null,
            'compiled_content' => null,
            'word_count' => 0,
            'compliance_score' => 0.0,
            'hitl_pending_payload' => null,
        ])->save();

        Notification::make()
            ->title('Draf tahap dibersihkan')
            ->body('Naskah tahap '.$project->current_stage->label().' telah dikosongkan. Klik "Susun Draf Lengkap" untuk menyusun ulang.')
            ->info()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Susun draf LENGKAP untuk tahap aktif dalam satu aksi (tanpa klik manual
     * per sub-bab). Menyiapkan referensi otomatis bila belum ada, lalu
     * menulis seluruh sub-bab dan menghitung kepatuhan pedoman.
     */
    public function generateFullDraft(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $sections = $this->buildDraftForCurrentStage($project);

        Notification::make()
            ->title($sections > 0 ? 'Draf lengkap disusun' : 'Belum ada konten yang dihasilkan')
            ->body($sections > 0
                ? $sections.' sub-bab ditulis untuk tahap '.$project->current_stage->label().'.'
                : 'Backend Omnigensi tidak tersedia atau pedoman kosong.')
            ->when($sections > 0, fn ($n) => $n->success(), fn ($n) => $n->warning())
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Tulis seluruh sub-bab tahap aktif memakai agen (bukan langkah mikro HITL).
     *
     * @return int jumlah sub-bab yang dihasilkan
     */
    private function buildDraftForCurrentStage(XishieProject $project): int
    {
        $stage = app(XishieSupervisor::class)->ensureStage($project, $project->current_stage);

        // 1) Pastikan ada referensi agar sitasi & matriks literatur terisi.
        if ($project->references()->count() === 0) {
            $scout = app(ScholarScoutAgent::class);
            $candidates = $scout->suggestReferences($project, 4);

            if ($candidates !== []) {
                $scout->ingestCandidates($project, $candidates);
            }
        }

        // 2) Tulis SEMUA sub-bab (regenerasi penuh => konten selalu bersih &
        //    terbaru; tanpa single_section/preserve_existing).
        $scribe = app(AcademicScribeAgent::class)->run($project, $stage);

        // 3) Hitung kepatuhan pedoman & simpan.
        $audit = app(RuleKeeperAgent::class)->run($project, $stage);
        $stage->forceFill([
            'compliance_score' => $audit->complianceScore,
            'compiled_content' => $stage->compile(),
        ])->save();

        app(UxKpiTelemetryService::class)->recordProjectSnapshot($project->refresh());

        return count((array) ($scribe->outputPayload['sections'] ?? []));
    }

    public function approvePendingSection(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        app(XishieSupervisor::class)->approve($project, $project->current_stage);

        Notification::make()->title('Draf disetujui')->success()->send();
    }

    public function requestRegeneration(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        app(XishieSupervisor::class)->requestRevision($project, $project->current_stage, 'Perkuat argumen dan sitasi.');

        Notification::make()->title('Permintaan revisi dikirim')->warning()->send();
    }

    /**
     * HITL "Edit Manually": simpan suntingan pengguna langsung ke stage.
     *
     * @param  array<int, array<string, mixed>>  $sections
     */
    public function saveManualEdit(array $sections): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        app(XishieSupervisor::class)->manualEdit($project, $project->current_stage, $sections);

        Notification::make()
            ->title('Suntingan manual disimpan')
            ->body('Stage ditandai selesai dengan konten hasil editan pengguna.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Simpan suntingan manual dari editor teks bebas.
     *
     * Teks dipecah per penanda "## Heading"; bagian tanpa heading menjadi
     * satu section "manual".
     */
    public function saveManualEditText(string $text): void
    {
        $sections = $this->parseManualText($text);

        if ($sections === []) {
            Notification::make()->title('Naskah kosong, tidak ada yang disimpan')->warning()->send();

            return;
        }

        $this->saveManualEdit($sections);
    }

    /**
     * @return array<int, array{key: string, heading: string, content: string}>
     */
    public function parseManualText(string $text): array
    {
        $normalized = trim(str_replace("\r\n", "\n", $text));

        if ($normalized === '') {
            return [];
        }

        // Pecah berdasarkan heading Markdown "## ...".
        $parts = preg_split('/^##\s+/mu', $normalized) ?: [];
        $sections = [];

        foreach ($parts as $part) {
            $part = trim($part);

            if ($part === '') {
                continue;
            }

            $lines = explode("\n", $part, 2);
            $heading = trim($lines[0]);
            $content = trim($lines[1] ?? '');

            if ($content === '' && count($parts) === 1) {
                $heading = 'Naskah Manual';
                $content = $part;
            }

            $sections[] = [
                'key' => Str::slug($heading) ?: 'manual',
                'heading' => $heading,
                'content' => $content,
            ];
        }

        return $sections;
    }

    /**
     * Teks gabungan stage aktif untuk editor manual.
     */
    public function editableContent(): string
    {
        $stage = $this->stage();

        return $stage?->compile() ?? '';
    }

    /**
     * Impor berkas PDF yang diunggah menjadi referensi proyek (PRD 3.4).
     */
    public function updatedReferenceUpload(): void
    {
        $project = $this->getProject();

        if ($project === null || $this->referenceUpload === null) {
            return;
        }

        try {
            $binary = $this->referenceUpload->get();
            $filename = (string) $this->referenceUpload->getClientOriginalName();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

            if ($ext === 'bib') {
                $count = app(ReferenceImportService::class)->importBibtex($project, $binary);
                $this->importStatus = "OK: {$count} referensi BibTeX berhasil diimpor.";

                Notification::make()
                    ->title("{$count} referensi BibTeX diimpor")
                    ->success()
                    ->send();
            } else {
                $reference = app(ReferenceImportService::class)->import($project, $binary, $filename);
                $this->importStatus = 'OK: referensi "'.$reference->citation_key.'" berhasil diimpor.';

                Notification::make()
                    ->title('PDF referensi diimpor')
                    ->body($reference->title)
                    ->success()
                    ->send();
            }
        } catch (\Throwable $exception) {
            $this->importStatus = 'Gagal: '.$exception->getMessage();

            Notification::make()
                ->title('Gagal mengimpor PDF')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->referenceUpload = null;
            $this->dispatch('$refresh');
        }
    }

    public function fetchReferences(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $scout = app(ScholarScoutAgent::class);

        // Cari referensi nyata via LLM Omnigensi (terintegrasi).
        $candidates = $scout->suggestReferences($project, 4);

        if ($candidates !== []) {
            $added = $scout->ingestCandidates($project, $candidates);

            Notification::make()
                ->title($added.' referensi ditambahkan dari Omnigensi')
                ->body('Dihasilkan model AI Omnigensi.')
                ->success()
                ->send();

            $this->dispatch('$refresh');

            return;
        }

        // Fallback offline: satu referensi sintetis agar pipeline tetap jalan.
        $scout->ingestCandidates($project, [
            [
                'title' => 'A Systematic Review of '.$project->title,
                'abstract' => 'Kajian sistematis terkait '.$project->field_of_study->label().' dengan pendekatan '.$project->field_of_study->defaultMethodology().'.',
                'authors' => ['Rahman', 'Salsabila'],
                'year' => now()->year - 1,
                'method' => 'systematic review',
                'result' => 'Sintesis temuan utama',
                'gap' => 'Validasi empiris terbatas',
                'source' => 'offline-fallback',
            ],
        ]);

        Notification::make()
            ->title('Referensi ditambahkan (fallback offline)')
            ->body('Backend Omnigensi tidak tersedia; memakai referensi sintetis.')
            ->warning()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Tandai/batalkan verifikasi sebuah referensi (hanya referensi terverifikasi
     * yang boleh dijadikan sitasi).
     */
    public function toggleReferenceVerified(int $referenceId): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $reference = $project->references()->find($referenceId);

        if ($reference === null) {
            Notification::make()->title('Referensi tidak ditemukan')->warning()->send();

            return;
        }

        $reference->forceFill(['is_verified' => ! $reference->is_verified])->save();

        Notification::make()
            ->title($reference->is_verified ? 'Referensi diverifikasi' : 'Verifikasi referensi dibatalkan')
            ->body($reference->citation_key)
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Hapus sebuah referensi dari proyek.
     */
    public function deleteReference(int $referenceId): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $reference = $project->references()->find($referenceId);

        if ($reference === null) {
            Notification::make()->title('Referensi tidak ditemukan')->warning()->send();

            return;
        }

        $key = $reference->citation_key;
        $reference->delete();

        Notification::make()
            ->title('Referensi dihapus')
            ->body($key)
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    public function matchVenues(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            return;
        }

        $stage = app(XishieSupervisor::class)->ensureStage($project, $project->current_stage);
        $result = app(VenueMatcherAgent::class)->run($project, $stage);

        Notification::make()
            ->title('Rekomendasi venue diperbarui')
            ->body($result->metadata['message'] ?? '')
            ->success()
            ->send();
    }

    public function sendAssistantMessage(): void
    {
        $project = $this->getProject();

        if ($project === null || trim($this->assistantMessage) === '') {
            return;
        }

        $message = trim($this->assistantMessage);

        $this->assistantLog[] = [
            'role' => 'user',
            'content' => $message,
        ];

        // Slash command kontekstual: /sitasi, /parafrase, /cek-gap, /cek-pedoman,
        // /matriks-literatur, /cari-referensi — atau jawaban kontekstual bebas.
        $result = app(XishieAssistantService::class)->handle($project, $message);

        // Catat query assistant agar KPI "Assistant Query Effectiveness" terukur
        // (PRD KPI #8). "applied" menandai respons yang menghasilkan artefak
        // siap tempel (sitasi, matriks, parafrase, audit).
        OmnigensiAgentLog::create([
            'project_id' => $project->id,
            'stage_id' => $this->stage()?->id,
            'agent_name' => 'Xishie-Assistant',
            'action' => $result['command'],
            'control_mode' => $project->control_mode->value,
            'ttft_ms' => 0,
            'execution_time_ms' => 0,
            'token_usage' => str_word_count((string) $result['reply']),
            'hallucination_rejected_count' => 0,
            'status' => 'success',
            'payload' => [
                'query' => $message,
                'command' => $result['command'],
                'metadata' => [
                    'applied' => in_array($result['command'], ['/sitasi', '/parafrase', '/matriks-literatur', '/cek-pedoman'], true),
                ],
            ],
        ]);

        $this->assistantLog[] = [
            'role' => 'assistant',
            'content' => $result['reply'],
        ];

        $this->assistantMessage = '';
    }

    /**
     * Catat rating kepuasan pengguna (CSAT 1-5) — PRD KPI #18.
     */
    public function rateSatisfaction(int $rating): void
    {
        $project = $this->getProject();

        if ($project === null || $rating < 1 || $rating > 5) {
            return;
        }

        UxKpiTelemetry::create([
            'project_id' => $project->id,
            'user_id' => $project->user_id,
            'metric_key' => UxKpiMetric::USER_SATISFACTION_SCORE,
            'metric_category' => UxKpiMetric::USER_SATISFACTION_SCORE->category(),
            'metric_value' => $rating,
            'metadata' => [
                'stage' => $project->current_stage->value,
                'recorded_by' => 'workspace_widget',
            ],
            'recorded_at' => now(),
        ]);

        Notification::make()
            ->title('Terima kasih atas penilaian Anda')
            ->body("Rating {$rating}/5 tercatat pada telemetri CSAT.")
            ->success()
            ->send();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $project = $this->getProject();

        if ($project !== null) {
            $this->syncEditorIfStale();
        }

        if ($project === null) {
            return [
                'project' => null,
                'progression' => [],
                'sections' => [],
                'metrics' => [],
                'stage' => null,
                'recommendations' => [],
                'references' => [],
                'pendingGate' => null,
                'agentLogs' => [],
                'exportFormats' => app(ManuscriptExporter::class)->formats(),
                'projects' => XishieProject::query()->latest()->get(['id', 'title']),
            ];
        }

        $stage = $project->stages()->where('stage_name', $project->current_stage->value)->first();
        $finalRecord = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
        $extractedJournal = (array) (($finalRecord?->hitl_pending_payload ?? [])['extracted_journal_manuscript'] ?? []);
        $metrics = app(UxKpiTelemetryService::class)->computeProjectMetrics($project);

        $pending = null;

        if ($stage !== null && $stage->status === StageStatus::WAITING_APPROVAL) {
            $pending = [
                'agent' => $stage->hitl_pending_payload['agent_name'] ?? 'Omnigensi',
                'section' => $stage->hitl_pending_payload['payload']['pending_section'] ?? null,
            ];
        }

        return [
            'project' => $project,
            'stage' => $stage,
            'stageLabel' => $project->current_stage->label(),
            'progression' => app(StageStateManager::class)->progression($project),
            'sections' => (array) ($stage?->sections_content ?? []),
            'metrics' => $metrics,
            'recommendations' => $project->recommendations()->get(),
            'references' => $project->references()->latest()->limit(20)->get(),
            'literatureMatrix' => app(LiteratureMatrixBuilder::class)->build($project),
            'violationSections' => $stage !== null ? $this->detectViolatingSections($project, $stage) : [],
            'pendingGate' => $pending,
            'agentLogs' => $project->agentLogs()->latest('id')->limit(15)->get(),
            'exportFormats' => app(ManuscriptExporter::class)->formats(),
            'extractedJournalManuscript' => $extractedJournal !== [] ? $extractedJournal : null,
            'projects' => XishieProject::query()->latest()->get(['id', 'title']),
        ];
    }

    /**
     * Live Guideline Auditor: tandai sub-bab yang melanggar batas kata atau
     * tidak memiliki sitasi valid (PRD 3.4 & FASE 5).
     *
     * @return array<int, array{key: string, heading: string, issues: array<int, string>}>
     */
    public function detectViolatingSections(XishieProject $project, ProjectStageRecord $stage): array
    {
        $guideline = app(GuidelineResolverService::class)->resolve($project);
        $citationStyle = CitationStyle::tryFrom($guideline->citationStyle)
            ?? CitationStyle::APA7;

        $flagged = [];

        foreach ((array) ($stage->sections_content ?? []) as $section) {
            if (! is_array($section)) {
                continue;
            }

            $key = (string) ($section['key'] ?? '');
            $content = (string) ($section['content'] ?? '');
            $words = str_word_count(strip_tags($content));
            $issues = [];

            $range = $guideline->wordRangeFor($key);

            if ($range !== null && $words < $range['min']) {
                $issues[] = sprintf('Kurang %d kata (min %d)', $range['min'] - $words, $range['min']);
            }

            if ($range !== null && $words > $range['max']) {
                $issues[] = sprintf('Lebih %d kata (maks %d)', $words - $range['max'], $range['max']);
            }

            if ($content !== '' && preg_match($citationStyle->inTextPattern(), $content) !== 1) {
                $issues[] = 'Belum ada sitasi ('.$citationStyle->label().')';
            }

            if ($issues !== []) {
                $flagged[] = [
                    'key' => $key,
                    'heading' => (string) ($section['heading'] ?? $key),
                    'issues' => $issues,
                ];
            }
        }

        return $flagged;
    }

    /**
     * Tombol "Audit Pedoman Instan": jalankan RuleKeeperAgent dan tampilkan hasil.
     */
    public function auditGuideline(): void
    {
        $project = $this->getProject();
        $stage = $this->stage();

        if ($project === null || $stage === null) {
            Notification::make()->title('Belum ada naskah untuk diaudit')->warning()->send();

            return;
        }

        $audit = app(RuleKeeperAgent::class)->run($project, $stage);

        $stage->forceFill([
            'compliance_score' => $audit->complianceScore,
            'compiled_content' => $stage->compile(),
        ])->save();

        $violationCount = count((array) ($audit->outputPayload['violations'] ?? []));

        Notification::make()
            ->title(sprintf('Audit pedoman: %.1f%% patuh', $audit->complianceScore))
            ->body($violationCount === 0
                ? 'Tidak ada pelanggaran terdeteksi.'
                : $violationCount.' pelanggaran terdeteksi pada naskah.')
            ->when($violationCount === 0, fn ($n) => $n->success(), fn ($n) => $n->warning())
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Export naskah tahap aktif ke format unduhan (KPI #15).
     */
    public function exportManuscript(string $format): ?StreamedResponse
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return null;
        }

        $exporter = app(ManuscriptExporter::class);

        if (! $exporter->isSupported($format)) {
            Notification::make()->title('Format tidak didukung')->danger()->send();

            return null;
        }

        $content = $exporter->render($project, $format, $project->current_stage);
        $meta = $exporter->formats()[$format];
        $filename = $exporter->filename($project, $format);

        // Catat peristiwa ekspor sebagai jejak agen (audit + umpan KPI #15).
        OmnigensiAgentLog::create([
            'project_id' => $project->id,
            'stage_id' => $this->stage()?->id,
            'agent_name' => 'Xishie-Exporter',
            'action' => 'export:'.$format,
            'control_mode' => $project->control_mode->value,
            'ttft_ms' => 0,
            'execution_time_ms' => 0,
            'token_usage' => 0,
            'hallucination_rejected_count' => 0,
            'status' => 'success',
            'payload' => [
                'format' => $format,
                'stage' => $project->current_stage->value,
                'filename' => $filename,
            ],
        ]);

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => $meta['mime']],
        );
    }

    /**
     * Tulis ulang (regenerasi) satu sub-bab spesifik pada stage aktif (PRD 3.4 & Live Auditor).
     */
    public function regenerateSingleSection(string $sectionKey): void
    {
        $project = $this->getProject();
        $stage = $this->stage();

        if ($project === null || $stage === null) {
            Notification::make()->title('Tidak ada stage aktif')->warning()->send();

            return;
        }

        $result = app(AcademicScribeAgent::class)->regenerateSection($project, $stage, $sectionKey);

        Notification::make()
            ->title('Sub-bab ditulis ulang')
            ->body(sprintf('"%s" diperbarui. Kepatuhan pedoman: %.1f%%.', $result['section']['heading'], $result['compliance_score']))
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Ekstrak naskah jurnal ilmiah (IMRaD) dari Skripsi / Tesis / Disertasi (PRD FASE 3 & KPI #16).
     */
    public function extractJournalManuscript(): void
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        if (! $project->document_type->isThesisFamily()) {
            Notification::make()
                ->title('Fitur ini khusus untuk Skripsi, Tesis, dan Disertasi')
                ->warning()
                ->send();

            return;
        }

        $result = app(AcademicScribeAgent::class)->convertToJournalManuscript($project);

        Notification::make()
            ->title('Naskah jurnal (IMRaD) berhasil diekstrak')
            ->body(sprintf('Tersusun 5 bagian standar IMRaD (%s kata) dengan sitasi terikat.', number_format($result['word_count'])))
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Unduh naskah jurnal yang diekstrak dari skripsi/tesis (KPI #15 & #16).
     */
    public function exportJournalManuscript(string $format): ?StreamedResponse
    {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return null;
        }

        $final = $project->stages()->where('stage_name', ProjectStage::FINAL->value)->first();
        $extracted = (array) (($final?->hitl_pending_payload ?? [])['extracted_journal_manuscript'] ?? []);

        if ($extracted === []) {
            Notification::make()
                ->title('Naskah jurnal belum diekstrak')
                ->body('Klik "Ekstrak Naskah Jurnal" terlebih dahulu.')
                ->warning()
                ->send();

            return null;
        }

        $exporter = app(ManuscriptExporter::class);
        if (! $exporter->isSupported($format)) {
            Notification::make()->title('Format tidak didukung')->danger()->send();

            return null;
        }

        $content = $exporter->render($project, $format, ProjectStage::FINAL, $extracted);
        $meta = $exporter->formats()[$format];
        $base = Str::slug((string) $project->title) ?: 'naskah';
        $filename = $base.'-journal.'.$meta['extension'];

        OmnigensiAgentLog::create([
            'project_id' => $project->id,
            'stage_id' => $final?->id,
            'agent_name' => 'Xishie-Exporter',
            'action' => 'export:journal:'.$format,
            'control_mode' => $project->control_mode->value,
            'ttft_ms' => 0,
            'execution_time_ms' => 0,
            'token_usage' => 0,
            'hallucination_rejected_count' => 0,
            'status' => 'success',
            'payload' => [
                'format' => $format,
                'type' => 'skripsi_to_journal',
                'filename' => $filename,
            ],
        ]);

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => $meta['mime']],
        );
    }

    /**
     * Tambahkan referensi ilmiah secara manual ke dalam proyek.
     */
    public function addManualReference(
        string $title,
        string $authors,
        int $year,
        ?string $journal = null,
        ?string $doi = null,
        ?string $abstract = null,
        ?string $method = null,
        ?string $result = null,
        ?string $gap = null,
    ): void {
        $project = $this->getProject();

        if ($project === null) {
            Notification::make()->title('Tidak ada proyek aktif')->warning()->send();

            return;
        }

        $title = trim($title);
        if ($title === '') {
            Notification::make()->title('Judul referensi wajib diisi')->danger()->send();

            return;
        }

        $parsedAuthors = array_values(array_filter(array_map('trim', explode(',', $authors))));
        if ($parsedAuthors === []) {
            $parsedAuthors = ['Anonim'];
        }

        $candidate = [
            'title' => $title,
            'authors' => $parsedAuthors,
            'year' => $year,
            'journal_name' => $journal !== null && trim($journal) !== '' ? trim($journal) : null,
            'doi' => $doi !== null && trim($doi) !== '' ? trim($doi) : null,
            'abstract' => $abstract !== null && trim($abstract) !== '' ? trim($abstract) : "Kajian tentang {$title}",
            'method' => $method !== null && trim($method) !== '' ? trim($method) : 'empiris',
            'result' => $result !== null && trim($result) !== '' ? trim($result) : 'kontribusi kajian',
            'gap' => $gap !== null && trim($gap) !== '' ? trim($gap) : null,
            'source' => 'manual-entry',
        ];

        $count = app(ScholarScoutAgent::class)->ingestCandidates($project, [$candidate]);

        Notification::make()
            ->title('Referensi ditambahkan')
            ->body('"'.$title.'" berhasil dicatat ke matriks literatur.')
            ->success()
            ->send();

        $this->dispatch('$refresh');
    }

    /**
     * Impor teks mentah BibTeX ke dalam referensi proyek.
     */
    public function importBibtexText(string $bibtexText): void
    {
        $project = $this->getProject();
        if ($project === null || trim($bibtexText) === '') {
            return;
        }

        try {
            $count = app(ReferenceImportService::class)->importBibtex($project, $bibtexText);
            $this->importStatus = "OK: {$count} referensi BibTeX berhasil diimpor.";

            Notification::make()
                ->title("{$count} referensi BibTeX ditambahkan")
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            $this->importStatus = 'Gagal: '.$exception->getMessage();

            Notification::make()
                ->title('Gagal mengimpor BibTeX')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        } finally {
            $this->dispatch('$refresh');
        }
    }

    /**
     * Unduh Tabel Matriks Literatur (SOTA) sebagai berkas CSV atau Markdown.
     */
    public function exportLiteratureMatrix(string $format = 'csv'): ?StreamedResponse
    {
        $project = $this->getProject();
        if ($project === null) {
            return null;
        }

        $builder = app(LiteratureMatrixBuilder::class);
        $base = Str::slug((string) $project->title) ?: 'matriks';

        if ($format === 'docx') {
            $content = $builder->toDocx($project);
            $filename = $base.'-literature-matrix.docx';
            $mime = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
        } elseif ($format === 'markdown' || $format === 'md') {
            $content = "# Matriks Literatur (SOTA) — {$project->title}\n\n".$builder->toMarkdown($project);
            $filename = $base.'-literature-matrix.md';
            $mime = 'text/markdown';
        } elseif ($format === 'json') {
            $content = $builder->toJson($project);
            $filename = $base.'-literature-matrix.json';
            $mime = 'application/json';
        } else {
            $content = $builder->toCsv($project);
            $filename = $base.'-literature-matrix.csv';
            $mime = 'text/csv';
        }

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => $mime],
        );
    }

    /**
     * Unduh seluruh daftar pustaka referensi proyek sebagai berkas BibTeX (.bib).
     */
    public function exportReferencesBibtex(): ?StreamedResponse
    {
        $project = $this->getProject();
        if ($project === null) {
            return null;
        }

        if ($project->references()->count() === 0) {
            Notification::make()->title('Belum ada referensi pada proyek ini')->warning()->send();

            return null;
        }

        $exporter = app(ManuscriptExporter::class);
        $content = $exporter->render($project, ManuscriptExporter::FORMAT_BIBTEX, $project->current_stage);
        $base = Str::slug((string) $project->title) ?: 'references';
        $filename = $base.'-references.bib';

        return response()->streamDownload(
            static fn () => print $content,
            $filename,
            ['Content-Type' => 'application/x-bibtex'],
        );
    }

    /**
     * Preview action items revisi terakhir (untuk panel revisi ringkas).
     */
    public function latestRevision(): ?ProjectRevision
    {
        $project = $this->getProject();

        return $project?->revisions()->latest('id')->first();
    }

    public function stage(): ?ProjectStageRecord
    {
        $project = $this->getProject();

        return $project?->stages()->where('stage_name', $project->current_stage->value)->first();
    }

    public function critiques(): PeerCriticAgent
    {
        return app(PeerCriticAgent::class);
    }

    public function stageLabel(): string
    {
        return $this->getProject()?->current_stage->label() ?? ProjectStage::DRAFT->label();
    }
}
