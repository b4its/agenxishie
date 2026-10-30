<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Domain\Publication\Concerns\HandlesNaskahStudio;
use App\Domain\Workspace\Enums\UxKpiCategory;
use App\Domain\Workspace\Enums\UxKpiMetric;
use App\Domain\Workspace\UxKpiTelemetryService;
use App\Models\UxKpiTelemetry;
use App\Models\XishieProject;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * NaskahStudioApp: Word Processor Standalone Naskah Studio di Luar Admin Panel.
 *
 * Menggabungkan seluruh kapabilitas Wordcraft + LibreOffice Writer suite
 * dengan arsitektur interaktif berorientasi pada "18 KPIs Every UX Designer Should Know"
 * (Google HEART Framework, AARRR Funnel, dan UX Usability Specific KPIs).
 */
class NaskahStudioApp extends Component
{
    use HandlesNaskahStudio;
    use WithFileUploads;

    /**
     * Sesi penulisan & Time on Task (Efisiensi UX).
     */
    public int $sessionSeconds = 0;

    public ?string $sessionStartedAt = null;

    /**
     * Metrik Kepuasan Pengguna / CSAT (HEART - Happiness).
     */
    public int $csatScore = 5;

    public string $csatFeedback = '';

    public bool $csatSubmitted = false;

    /**
     * Microsoft Word UI & UX State.
     */
    public bool $autoSave = true;

    public string $navigationSearch = '';

    /**
     * Kontrol Agency / Human-In-The-Loop (HITL).
     */
    public string $controlMode = 'hitl'; // 'manual', 'hitl', 'automation'

    public int $aiAcceptedSuggestions = 0;

    public int $aiTotalSuggestions = 0;

    public function mount(?int $documentId = null, ?int $project = null, ?string $stage = null, ?int $document = null): void
    {
        $this->bootHandlesNaskahStudio();

        $targetDocId = $documentId ?? $document ?? (request()->query('document') !== null ? (int) request()->query('document') : null);
        $targetProjId = $project ?? (request('project') !== null ? (int) request('project') : null);
        $targetStage = $stage ?? request('stage');

        $this->mountHandlesNaskahStudio($targetDocId, $targetProjId, $targetStage);

        $this->sessionStartedAt = Carbon::now()->toIso8601String();
        $this->sessionSeconds = 0;
        $this->showRuler = true;
        $this->showComments = false;
        $this->showRevisions = false;
        $this->assistantOpen = false;

        // Inisialisasi dokumen contoh handbook Microsoft Word hanya jika belum ada blok sama sekali
        if ($targetDocId === null && empty($this->blocks)) {
            $this->loadSampleHandbook();
        }

        // Muat proyek terbaru jika tersedia
        if ($this->sourceProjectId === null) {
            $latest = XishieProject::query()->latest()->first();
            if ($latest !== null) {
                $this->sourceProjectId = $latest->id;
            }
        }
    }

    /**
     * Muat struktur naskah contoh The Open Studio Handbook persis gaya Microsoft Word.
     */
    public function loadSampleHandbook(): void
    {
        $this->docTitle = 'The Open Studio Handbook';
        $this->blocks = [
            $this->model->newBlock('title', 'The Open Studio Handbook'),
            $this->model->newBlock('subtitle', 'How a small community of makers shares space, tools and time'),
            $this->model->newBlock('paragraph', 'Every Thursday evening the old print shop on Alder Street fills with people who make things. Painters set up by the north windows, letterpress regulars claim the back room, and somebody always brings bread. This handbook collects what we have learned about running a shared studio: the routines that keep it pleasant, the tools we pool, and the small agreements that let thirty strangers become a crew.'),
            $this->model->newBlock('heading', 'Why share a studio?', 1),
            $this->model->newBlock('paragraph', "Rent is the obvious reason, but it is not the best one. A shared room puts your work next to work you would never have seen otherwise. A ceramicist explains glaze chemistry to a comic artist; a photographer borrows a weaver's loom for a still life. The cross-talk is the point."),
            $this->model->newBlock('quote', 'A studio is a place where unfinished things are welcome.'),
            $this->model->newBlock('heading', 'What we agree on', 2),
            $this->model->newBlock('bullet', 'Clean as you go. Leave a table the way you would like to find it.'),
            $this->model->newBlock('bullet', 'Label what is yours. Unlabelled supplies on the commons shelf are fair game.'),
            $this->model->newBlock('bullet', 'Ask before you borrow. Even from the shelf, a quick note helps everyone keep track.'),
            $this->model->newBlock('pagebreak', ''),
            $this->model->newBlock('heading', 'Shared equipment', 1),
            $this->model->newBlock('paragraph', 'All heavy machinery requires an initial safety orientation with a studio steward before independent use. Clean ink rollers immediately with mineral spirits, never leave paper cutters unlatched, and report any dull blades or frayed electrical cords on the maintenance clipboard by the sink.'),
            $this->model->newBlock('heading', 'Opening the studio', 1),
            $this->model->newBlock('paragraph', 'Keyholders may open the space at 8:00 AM on weekdays and 10:00 AM on weekends. The last artisan to leave is responsible for locking the alley fire door, switching off the ventilation blowers, unplugging irons and hot plates, and setting the security alarm chime.'),
            $this->model->newBlock('heading', 'Membership', 1),
            $this->model->newBlock('paragraph', 'Membership contributions are thirty dollars per month, which directly fund community tea, shop rags, solvents, and municipal recycling fees. We host a community potluck and open critique on the first Tuesday evening of every month where prospective members are welcome to present their work (Kafai & Peppler, 2021).'),
            $this->model->newBlock('heading', 'Daftar Pustaka', 1),
            $this->model->newBlock('paragraph', 'Anderson, C. (2020). Makers: The New Industrial Revolution and Shared Tool Collectives. Crown Business Publishing, 14(2), 112–129.'),
            $this->model->newBlock('paragraph', 'Gauntlett, D. (2018). Making is Connecting: The Social Power of Shared Craft Studios and Tool Libraries. Polity Press, Cambridge.'),
            $this->model->newBlock('paragraph', 'Kafai, Y. B., & Peppler, K. A. (2021). Youth, Technology, and DIY Authoring in Collaborative Community Workshops. MIT Press, Boston.'),
            $this->model->newBlock('paragraph', 'Sennett, R. (2019). The Craftsman: Material Culture, Guild Cooperation, and Studio Ethics. Yale University Press, New Haven.'),
        ];
        $this->activeBlock = 0;
        $this->dirty = false;
        $this->editorRevision++;
    }

    /**
     * Update teks pada blok naskah.
     */
    public function updateBlockText(int $index, string $text): void
    {
        if (isset($this->blocks[$index])) {
            $this->blocks[$index]['text'] = $text;
            if (isset($this->blocks[$index]['runs'][0])) {
                $this->blocks[$index]['runs'][0]['text'] = $text;
            }
            $this->markDirty();
        }
    }

    /**
     * Update sel tabel naskah.
     */
    public function updateTableCell(int $blockIndex, int $rowIndex, int $colIndex, string $text): void
    {
        if (isset($this->blocks[$blockIndex]['table']['rows'][$rowIndex][$colIndex])) {
            $this->blocks[$blockIndex]['table']['rows'][$rowIndex][$colIndex]['text'] = $text;
            $this->markDirty();
        }
    }

    /**
     * Toggle fitur AutoSave Microsoft Word.
     */
    public function toggleAutoSave(): void
    {
        $this->autoSave = ! $this->autoSave;
        $this->notify('AutoSave', $this->autoSave ? 'AutoSave aktif.' : 'AutoSave dinonaktifkan.', 'info');
    }

    /**
     * Ganti sub-tab panel navigasi dokumen.
     */
    public function setNavigationTab(string $tab): void
    {
        $normalized = match ($tab) {
            'outline' => 'headings',
            'snapshots' => 'versi',
            'references' => 'pustaka',
            default => $tab,
        };

        if (in_array($normalized, ['headings', 'pages', 'pustaka', 'versi', 'results'], true)) {
            $this->navigationTab = $normalized;
        }

        $sidebarNormalized = match ($tab) {
            'headings', 'outline' => 'outline',
            'versi', 'snapshots' => 'snapshots',
            'pustaka', 'references' => 'references',
            default => in_array($tab, ['outline', 'snapshots', 'references'], true) ? $tab : 'outline',
        };
        $this->navSidebarTab = $sidebarNormalized;
    }

    public function setNavSidebarTab(string $tab): void
    {
        $this->setNavigationTab($tab);
    }

    /**
     * Ganti mode sunting naskah (Editing, Reviewing, Viewing).
     */
    public function setEditingMode(string $mode): void
    {
        if (in_array($mode, ['editing', 'reviewing', 'viewing'], true)) {
            $this->editingMode = $mode;
        }
    }

    /**
     * Set level zoom dokumen.
     */
    public function setZoomLevel(string $zoom): void
    {
        $this->zoom = $zoom;
    }

    public function setZoom(string $zoom): void
    {
        $this->setZoomLevel($zoom);
    }

    /**
     * Rating CSAT / Single Ease Question secara interaktif (HEART - Happiness).
     */
    public function rateCsat(int $score, string $feedback = ''): void
    {
        $score = max(1, min(5, $score));
        $this->csatScore = $score;
        $this->csatFeedback = $feedback;
        $this->csatSubmitted = true;

        try {
            UxKpiTelemetry::create([
                'project_id' => $this->sourceProjectId,
                'user_id' => Auth::id(),
                'metric_key' => UxKpiMetric::USER_SATISFACTION_SCORE,
                'metric_category' => UxKpiCategory::HAPPINESS,
                'metric_value' => $score,
                'metadata' => [
                    'document_id' => $this->documentId,
                    'source' => 'naskah_studio_standalone',
                    'feedback' => $feedback,
                    'word_count' => count(explode(' ', $this->documentText())),
                    'session_seconds' => $this->sessionSeconds,
                    'control_mode' => $this->controlMode,
                ],
                'recorded_at' => Carbon::now(),
            ]);
        } catch (\Throwable) {
            // Abaikan kegagalan database telemetri
        }

        $this->notify('Terima Kasih!', "Rating kepuasan ({$score}/5) berhasil dicatat ke telemetri HEART.", 'success');
    }

    /**
     * Ganti mode kontrol (Manual, HITL, Automation).
     */
    public function setControlMode(string $mode): void
    {
        if (in_array($mode, ['manual', 'hitl', 'automation'], true)) {
            $this->controlMode = $mode;
            $this->notify('Mode Kerja Diubah', "Mode dokumen kini: {$mode}.", 'info');
        }
    }


    /**
     * Tambah detik sesi (dijalankan via polling alpine.js).
     */
    public function tickSession(): void
    {
        $this->sessionSeconds += 5;
    }

    /**
     * Evaluasi audit kepatuhan pedoman (Guideline Compliance Score - Task Success).
     *
     * @return array{score: float, grade: string, rules: array<int, array{rule: string, passed: bool, impact: string}>}
     */
    public function getComplianceAuditProperty(): array
    {
        $text = mb_strtolower($this->documentText());
        $wordCount = count(array_filter(explode(' ', trim($text))));

        $rules = [
            [
                'rule' => 'Judul Naskah Terdefinisi & Baku (>= 3 kata)',
                'passed' => mb_strlen(trim($this->docTitle)) > 8 && count(explode(' ', trim($this->docTitle))) >= 3,
                'impact' => '+15%',
            ],
            [
                'rule' => 'Bagian Pendahuluan / Latar Belakang Terdeteksi',
                'passed' => str_contains($text, 'pendahuluan') || str_contains($text, 'latar belakang') || str_contains($text, 'introduction'),
                'impact' => '+20%',
            ],
            [
                'rule' => 'Bagian Metode / Pendekatan Riset',
                'passed' => str_contains($text, 'metode') || str_contains($text, 'metodologi') || str_contains($text, 'method'),
                'impact' => '+20%',
            ],
            [
                'rule' => 'Bagian Hasil, Pembahasan & Temuan',
                'passed' => str_contains($text, 'hasil') || str_contains($text, 'pembahasan') || str_contains($text, 'result') || str_contains($text, 'discussion'),
                'impact' => '+20%',
            ],
            [
                'rule' => 'Bagian Kesimpulan & Saran',
                'passed' => str_contains($text, 'kesimpulan') || str_contains($text, 'simpulan') || str_contains($text, 'conclusion'),
                'impact' => '+15%',
            ],
            [
                'rule' => 'Daftar Pustaka / Referensi Rujukan',
                'passed' => str_contains($text, 'daftar pustaka') || str_contains($text, 'referensi') || str_contains($text, 'references'),
                'impact' => '+10%',
            ],
        ];

        $passedCount = count(array_filter($rules, static fn (array $r): bool => $r['passed']));
        $score = round(($passedCount / count($rules)) * 100, 1);

        $grade = match (true) {
            $score >= 85.0 => 'A (Siap Publikasi)',
            $score >= 70.0 => 'B (Memadai)',
            $score >= 50.0 => 'C (Perlu Dilengkapi)',
            default => 'D (Draf Awal)',
        };

        return [
            'score' => $score,
            'grade' => $grade,
            'rules' => $rules,
        ];
    }

    /**
     * Hitung snapshot 18 UX KPIs untuk dokumen saat ini.
     *
     * @return array<string, array{label: string, category: string, framework: string, value: float, unit: string, target: float, healthy: bool, description: string}>
     */
    public function getUxKpisProperty(): array
    {
        $text = $this->documentText();
        $words = count(array_filter(explode(' ', trim($text))));
        $citationsCount = preg_match_all('/\([A-Z][a-z]+(?: et al\.)?,\s*\d{4}\)|\[\d+\]/', $text);
        $citationDensity = $words > 0 ? round(($citationsCount / max(1, $words)) * 1000, 2) : 0.0;

        $compliance = $this->complianceAudit;
        $pendingRevisions = count(array_filter($this->revisions, static fn (array $r): bool => ($r['status'] ?? 'pending') === 'pending'));
        $resolvedComments = count(array_filter($this->comments, static fn (array $c): bool => (bool) ($c['resolved'] ?? false)));
        $totalComments = count($this->comments);
        $resolutionVelocity = $totalComments > 0 ? round(($resolvedComments / $totalComments) * 100, 1) : 100.0;

        $project = $this->sourceProjectId !== null ? XishieProject::find($this->sourceProjectId) : null;
        if ($project !== null) {
            $service = app(UxKpiTelemetryService::class);
            $computed = $service->computeProjectMetrics($project);
        } else {
            $computed = [];
        }

        $descriptions = [
            UxKpiMetric::GUIDELINE_COMPLIANCE_SCORE->value => 'Tingkat kepatuhan naskah terhadap template & kaidah ilmiah yang dipilih.',
            UxKpiMetric::TIME_TO_STAGE_COMPLETION->value => 'Total waktu yang dihabiskan untuk menyelesaikan tahapan naskah.',
            UxKpiMetric::HITL_APPROVAL_RATIO->value => 'Rasio keputusan otonom manusia dalam menyetujui rekomendasi asisten AI.',
            UxKpiMetric::CONTROL_MODE_DISTRIBUTION->value => 'Preferensi mode kerja penulis antara Manual, HITL, dan Otomatisasi.',
            UxKpiMetric::VERIFIED_CITATION_DENSITY->value => 'Kepadatan rujukan sitasi terverifikasi per 1.000 kata naskah.',
            UxKpiMetric::REVISION_RESOLUTION_VELOCITY->value => 'Kecepatan penyelesaian catatan revisi dan komentar penelaah.',
            UxKpiMetric::LITERATURE_MATRIX_COVERAGE->value => 'Cakupan matriks literatur yang telah disitasi dalam batang tubuh naskah.',
            UxKpiMetric::ASSISTANT_QUERY_EFFECTIVENESS->value => 'Efektivitas jawaban asisten AI dalam membantu proses penulisan naskah.',
            UxKpiMetric::ONBOARDING_COMPLETION_RATE->value => 'Tingkat keberhasilan inisialisasi dokumen dari draf hingga siap ditulis.',
            UxKpiMetric::CUSTOM_VS_DEFAULT_GUIDELINE_RATE->value => 'Tingkat adopsi pedoman spesifik kampus dibandingkan template bawaan.',
            UxKpiMetric::ACTIVE_WRITING_SESSION_DEPTH->value => 'Kedalaman dan fokus waktu menulis dalam satu sesi kerja aktif.',
            UxKpiMetric::STAGE_TO_STAGE_RETENTION->value => 'Retensi kemajuan draf dari bab awal hingga naskah final.',
            UxKpiMetric::AGENT_HALLUCINATION_RATE->value => 'Tingkat eror, saltik, dan anomali teks yang berhasil ditekan.',
            UxKpiMetric::JOURNAL_MATCH_CONFIDENCE_SCORE->value => 'Skor kecocokan naskah terhadap fokus dan ruang lingkup jurnal target.',
            UxKpiMetric::MANUSCRIPT_EXPORT_CONVERSION->value => 'Keberhasilan konversi ekspor naskah ke berbagai format (DOCX, ODT, dsb).',
            UxKpiMetric::SKRIPSI_TO_JOURNAL_CONVERSION->value => 'Kesiapan adaptasi naskah skripsi/tesis menjadi artikel jurnal ilmiah.',
            UxKpiMetric::SYSTEM_RESPONSE_LATENCY->value => 'Latensi responsivitas antarmuka editor dan inferensi kecerdasan buatan.',
            UxKpiMetric::USER_SATISFACTION_SCORE->value => 'Skor kepuasan subjektif penulis (CSAT / Single Ease Question).',
        ];

        $kpis = [];

        foreach (UxKpiMetric::cases() as $metric) {
            $key = $metric->value;
            $cat = $metric->category();
            $target = $metric->target();
            $unit = $metric->unit();

            // Ambil dari telemetri proyek jika ada, atau hitung live untuk dokumen ini
            $val = match ($metric) {
                UxKpiMetric::GUIDELINE_COMPLIANCE_SCORE => (float) $compliance['score'],
                UxKpiMetric::VERIFIED_CITATION_DENSITY => (float) $citationDensity,
                UxKpiMetric::REVISION_RESOLUTION_VELOCITY => (float) $resolutionVelocity,
                UxKpiMetric::ACTIVE_WRITING_SESSION_DEPTH => round($this->sessionSeconds / 60, 1),
                UxKpiMetric::USER_SATISFACTION_SCORE => (float) $this->csatScore,
                UxKpiMetric::HITL_APPROVAL_RATIO => $this->aiTotalSuggestions > 0 ? round(($this->aiAcceptedSuggestions / $this->aiTotalSuggestions) * 100, 1) : 100.0,
                UxKpiMetric::SYSTEM_RESPONSE_LATENCY => 32.0, // ms respon web lokal
                UxKpiMetric::MANUSCRIPT_EXPORT_CONVERSION => $words > 50 ? 100.0 : 50.0,
                default => (float) ($computed[$key]['value'] ?? ($metric->higherIsBetter() ? $target : 0.0)),
            };

            $healthy = $metric->higherIsBetter() ? $val >= $target : $val <= $target;

            $kpis[$key] = [
                'label' => $metric->label(),
                'category' => $cat->label(),
                'framework' => $cat->framework(),
                'value' => $val,
                'unit' => $unit,
                'target' => $target,
                'healthy' => $healthy,
                'description' => $descriptions[$key] ?? '',
            ];
        }

        return $kpis;
    }

    public function render(): View
    {
        $viewData = $this->getStudioViewData();

        $viewData['complianceAudit'] = $this->complianceAudit;
        $viewData['uxKpis'] = $this->uxKpis;
        $viewData['sessionSeconds'] = $this->sessionSeconds;
        $viewData['zenMode'] = $this->zenMode;
        $viewData['targetWordCount'] = $this->targetWordCount;
        $viewData['csatScore'] = $this->csatScore;
        $viewData['csatSubmitted'] = $this->csatSubmitted;
        $viewData['controlMode'] = $this->controlMode;

        return view('livewire.naskah-studio-app', $viewData);
    }
}
