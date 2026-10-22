---

## 2. Skrip Master Prompt Implementasi (`PROMPT_IMPLEMENTATION.md`)

Simpan blok di bawah ini sebagai `PROMPT_IMPLEMENTATION.md` di root repositori `agenxishie`:

```markdown
# MASTER IMPLEMENTATION PROMPT: AGEN XISHIE (MODULAR MONOLITH + OMNIGENSI ON MYSQL)

## PERAN & KONTEKS UTAMA
Anda adalah Principal Full-Stack Software Architect & Senior UI/UX Engineer yang ahli dalam ekosistem **Laravel 13**, **Filament 5**, **Livewire 3**, **Tailwind CSS**, **MySQL 8.4+ (InnoDB)**, dan orkestrasi **Multi-Agent System**.

Tugas Anda adalah mengimplementasikan sistem **Agen Xishie** secara *end-to-end* di repositori `https://github.com/b4its/agenxishie` dengan mengintegrasikan engine multi-agent dari `https://github.com/b4its/omnigensi` menggunakan pola **Modular Monolith Architecture** di atas database **MySQL**.

---

## ATURAN TEKNIS WAJIB (STRICT GUARDRAILS)

1. **Modular Monolith**: Integrasikan seluruh kemampuan multi-agent `omnigensi` di dalam namespace `App\Omnigensi\` pada codebase `agenxishie`. Jangan membuat microservice terpisah yang membutuhkan komunikasi HTTP antar-service. Gunakan internal Service Container, Database Transaction MySQL bersama, Laravel Queue, dan Laravel Events/Reverb.
2. **Database MySQL Native**:
   - Gunakan koneksi `mysql` (InnoDB engine, `utf8mb4_unicode_ci`).
   - Gunakan tipe kolom `json()` untuk struktur dokumen dinamis dan penyimpanan vektor *embedding* (`array<float>`).
   - Gunakan `fullText()` index pada kolom teks pencarian di tabel `project_references` dan `academic_venues` yang dipadukan dengan `HybridVectorMatcherService` (kalkulasi *Cosine Similarity* di PHP) untuk pencarian semantik tanpa ketergantungan ekstensi non-MySQL.
3. **Kebersihan Karakter Kode**: Jangan pernah menggunakan karakter `U+00A0` (*non-breaking space*) di dalam kode program, migration, maupun blade template. Selalu gunakan spasi ASCII standar (`U+0020`).
4. **Strict Typing & Modern PHP**: Wajib menggunakan `declare(strict_types=1);`, PHP 8.4+ typed properties, Backed Enums, Readonly Classes, dan DTOs (Data Transfer Objects).
5. **Desain Visual**: Wajib menerapkan tema **Retro Blue Data-Literate UI** (warna dasar Cobalt Blue `#1E40AF`, Electric Blue `#2563EB`, Background Blueprint `#F0F6FF`, border tegas `2px solid #0F172A`, dan hard shadow `4px 4px 0px #0F172A` tanpa blur) serta menampilkan kartu metrik dari **18 UX KPIs (Google HEART & AARRR Frameworks)**.

---

## TAHAPAN IMPLEMENTASI SISTEM (STEP-BY-STEP EXECUTION PLAN)

Lakukan implementasi secara berurutan mengikuti 6 fase di bawah ini:

### FASE 1: Fondasi Database MySQL & Enums Domain (`App\Domain` & `database/migrations`)
Buatlah Backed Enums, Migration MySQL (InnoDB), dan Eloquent Models (dengan `$casts` ke `array` / `Enum`) untuk entitas inti berikut:

1. **Enums**:
   - `App\Domain\Personalization\Enums\FieldOfStudy`: `STEM`, `HEALTH`, `SOCIAL_HUMANITIES`, `ECONOMICS_BUSINESS`, `LAW_POLICY`, `EDUCATION`, `MULTIDISCIPLINARY`.
   - `App\Domain\Personalization\Enums\DocumentType`: `JOURNAL` (Jurnal), `SKRIPSI` (Skripsi), `THESIS` (Tesis), `DISSERTATION` (Disertasi).
   - `App\Domain\Personalization\Enums\PublicationTarget`: `INTERNATIONAL_SCOPUS`, `NATIONAL_SINTA`, `CAMPUS_REPOSITORY`.
   - `App\Domain\Workspace\Enums\ProjectStage`: `DRAFT`, `PROPOSAL`, `FINAL`.
   - `App\Domain\Workspace\Enums\ControlMode`: `AUTOMATION`, `HITL`, `MANUAL`.
   - `App\Domain\Workspace\Enums\StageStatus`: `LOCKED`, `IN_PROGRESS`, `WAITING_APPROVAL`, `COMPLETED`.

2. **MySQL Migrations & Models (8 Tabel Inti)**:
   - `xishie_projects`: Konfigurasi personalisasi (`field_of_study`, `document_type`, `target_scope`, `target_tier`), pedoman penulisan (`default` / `custom`), `guideline_rules` (`json`), `control_mode`, dan `current_stage`.
   - `project_stages`: Menyimpan state tiap tahap (`draft`, `proposal`, `final`), `status`, `outline_schema` (`json`), `sections_content` (`json`), `compiled_content` (`longText`), `compliance_score` (`decimal(5,2)`), dan `hitl_pending_payload` (`json`).
   - `project_references`: Menyimpan referensi ilmiah (`citation_key`, `doi`, `title`, `abstract`, `authors` [`json`], `year`, `journal_name`, `sinta_or_scopus_tier`), `matrix_extraction` (`json`: metode, temuan, research gap), `embedding` (`json`), dan `$table->fullText(['title', 'abstract'])`.
   - `project_revisions`: Menyimpan umpan balik dosen/reviewer (`project_id`, `stage_id`, `reviewer_role`, `raw_feedback` [`longText`], `action_items` [`json`], `diff_payload` [`json`], `response_matrix_doc` [`longText`], dan `status`).
   - `academic_venues`: Katalog master jurnal SINTA 1-6 & Scopus Q1-Q4 / WoS (`journal_name`, `publisher`, `scope_type`, `index_tier`, `field_of_study`, `aims_and_scope` [`text`], `apc_estimate`, `submission_url`, `template_rules` [`json`], `aims_scope_embedding` [`json`]), beserta `$table->fullText(['journal_name', 'aims_and_scope'])`.
   - `platform_recommendations`: Menyimpan hasil rekomendasi platform jurnal per proyek (`project_id`, `academic_venue_id`, `journal_name`, `publisher`, `index_tier`, `match_score`, `scope_reasoning`, `apc_estimate`, `submission_url`).
   - `omnigensi_agent_logs`: Menyimpan jejak eksekusi multi-agent (`project_id`, `stage_id`, `agent_name`, `action`, `control_mode`, `ttft_ms`, `execution_time_ms`, `token_usage`, `hallucination_rejected_count`, `status`, `payload` [`json`]).
   - `ux_kpi_telemetries`: Menyimpan snapshot 18 metrik HEART & AARRR per proyek dan sesi pengguna (`project_id`, `user_id`, `metric_key`, `metric_category`, `metric_value`, `metadata` [`json`], `recorded_at`).

---

### FASE 2: Guideline Engine & Preset Standar (`App\Domain\GuidelineEngine`)
Bangun service `GuidelineResolverService`:
1. **Default Guideline Presets**:
   - Jika `DocumentType::JOURNAL`: Muat struktur IMRaD (`Abstract`, `1. Introduction`, `2. Related Work & Literature Matrix`, `3. Methodology`, `4. Results & Discussion`, `5. Conclusion`), standar sitasi IEEE/APA, dan target kata 4.000-7.000 kata.
   - Jika `DocumentType::SKRIPSI`, `THESIS`, atau `DISSERTATION`:
     - Tahap `DRAFT`: Rumusan Masalah, Keaslian Penelitian (SOTA), Matriks Literatur, Kerangka Pikir.
     - Tahap `PROPOSAL`: Bab 1 Pendahuluan, Bab 2 Tinjauan Pustaka, Bab 3 Metodologi Penelitian.
     - Tahap `FINAL`: Bab 1 s.d. Bab 3 (tervalidasi), Bab 4 Hasil dan Pembahasan, Bab 5 Kesimpulan dan Saran, Abstrak (ID/EN), serta opsi ekstraksi naskah publikasi jurnal.
2. **Custom Guideline Parser**:
   - Sediakan service upload PDF/DOCX buku pedoman penulisan yang memanggil `RuleKeeperAgent` untuk mengekstrak aturan penulisan menjadi struktur JSON yang disimpan ke kolom `xishie_projects.guideline_rules`.

---

### FASE 3: Orkestrator Multi-Agent `Omnigensi` (`App\Omnigensi`)
Implementasikan modul `Omnigensi` di dalam `app/Omnigensi/` dengan komponen berikut:

1. **Kontrak, DTO & Memori Hybrid MySQL**:
   - `App\Omnigensi\Contracts\XishieAgentContract`: Interface standar untuk semua agen dengan method `execute(XishieProject $project, ProjectStage $stage, array $context = []): AgentExecutionResultData`.
   - `App\Omnigensi\Data\AgentExecutionResultData`: Readonly DTO yang memuat `outputPayload`, `complianceScore`, `citationsUsed`, `hallucinationsRejected`, `ttftMs`, `executionTimeMs`, dan `tokenUsage`.
   - `App\Omnigensi\Memory\HybridVectorMatcherService`: Menyediakan pencarian kandidat dengan MySQL `MATCH(...) AGAINST(... IN NATURAL LANGUAGE MODE)` yang dilanjutkan dengan perhitungan *Cosine Similarity* atas array vektor dari kolom `JSON` `embedding`.
   - `App\Omnigensi\Memory\SharedProjectMemory`: Mengambil konteks terpadu proyek dari MySQL (pedoman aktif, referensi relevan, catatan revisi) untuk disuntikkan ke seluruh agen.

2. **`XishieSupervisor` (`App\Omnigensi\Orchestrator\XishieSupervisor`)**:
   - Menerima `XishieProject` dan `ProjectStage`.
   - Memeriksa `project->control_mode` secara *idempotent* di setiap langkah:
     - **`ControlMode::AUTOMATION`**: Menjalankan pipeline penuh (`ScholarScoutAgent` -> `AcademicScribeAgent` -> `RuleKeeperAgent` -> `VenueMatcherAgent` jika tahap `FINAL`) melalui Laravel Queue Jobs beruntun. Jika pengguna mengubah `control_mode` saat job berjalan, hentikan rantai otomatis sesuai mode baru.
     - **`ControlMode::HITL`**: Menjalankan satu langkah agen pada sub-bab aktif, menyimpan draf ke `hitl_pending_payload`, mengubah status menjadi `StageStatus::WAITING_APPROVAL`, dan menghentikan pipeline hingga pengguna menekan tombol **Approve** atau **Request Changes**.
     - **`ControlMode::MANUAL`**: Menonaktifkan penulisan otomatis; agen hanya dipanggil secara eksplisit melalui aksi asisten atau *command palette*, sementara `RuleKeeperAgent` menghitung `compliance_score` di latar belakang.

3. **Lima Agen Spesialis (`App\Omnigensi\Agents\`)**:
   - `RuleKeeperAgent`: Mengekstrak pedoman penulisan dan menghitung `Guideline Compliance Score` (0-100%).
   - `ScholarScoutAgent`: Mencari paper, mengekstrak matriks literatur (Metode, Hasil, Gap), dan memvalidasi kunci sitasi (`[Author, Year]`) agar 100% merujuk pada `project_references`.
   - `AcademicScribeAgent`: Menulis isi naskah sesuai bidang keilmuan, jenis dokumen, dan tahapan (`Draft`, `Proposal`, `Final`), termasuk mengonversi Skripsi/Tesis tahap `Final` menjadi draf *manuscript* jurnal.
   - `PeerCriticAgent`: Menganalisis teks revisi dosen/reviewer, memecahnya menjadi daftar tugas (`action_items`), menghasilkan *side-by-side diff* perbaikan di `diff_payload`, dan menyusun tabel *Response to Reviewers* di `response_matrix_doc`.
   - `VenueMatcherAgent`: Menggunakan `HybridVectorMatcherService` pada tabel `academic_venues` untuk mencocokkan abstrak, kata kunci, dan ruang lingkup naskah ke jurnal **SINTA (1-6)** atau **Scopus (Q1-Q4) / WoS** beserta *Match Score (%)* dan menyimpannya ke `platform_recommendations`.

---

### FASE 4: Sistem Desain "Retro Blue" & Telemetri 18 UX KPIs (`resources/css` & `App\Filament\Widgets`)
1. **Styling Retro Blue (`retro-blue-theme.css`)**:
   - Konfigurasikan variabel CSS untuk panel bergaya jendela retro dengan *title bar* biru `#1E40AF`, tombol kontrol jendela `[-][□][×]`, latar kertas blueprint `#F0F6FF`, border `2px solid #0F172A`, dan bayangan keras `4px 4px 0px #0F172A`.
2. **Service & Widget 18 UX KPIs (`UxKpiTelemetryService` & `WorkspaceKpiBarWidget`)**:
   - Baca log dari `omnigensi_agent_logs` dan simpan agregasi ke `ux_kpi_telemetries` untuk menampilkan:
     - **Task & Quality KPIs**: *Guideline Compliance Score (%)*, *Verified Citation Density*, *Revision Resolution Velocity*, *Journal Match Confidence*, *Agent Hallucination Rejection Rate*.
     - **HEART Framework KPIs**: *HITL Approval Ratio*, *Control Mode Utilization*, *Literature Matrix Coverage*, *Active Session Depth*, *CSAT Rating*.
     - **AARRR Funnel KPIs**: *Onboarding Activation Rate*, *Stage-to-Stage Retention (Draft -> Proposal -> Final)*, *Manuscript Export Conversion*, *Skripsi-to-Journal Conversion*.

---

### FASE 5: Antarmuka Pengguna Filament 5 (`App\Filament\Pages`)
Bangun 4 halaman terpadu di Filament 5:
1. **`ProjectOnboardingWizard`**:
   - Step 1: Judul Rencana & Pilih Bidang Keilmuan.
   - Step 2: Pilih Jenis Topik (`Jurnal`, `Skripsi`, `Tesis`, `Disertasi`) & Target Akhir (`Internasional Scopus/WoS` atau `Nasional SINTA 1-6`).
   - Step 3: Konfigurasi Panduan Penulisan (Pilih Preset Default atau Upload Pedoman Custom).
   - Step 4: Pilih Basis Kendali (`Automation`, `Human-in-the-Loop`, `Manual`) dengan penjelasan visual cara kerja masing-masing mode.
2. **`AcademicWorkspacePage` (Layar Kerja Utama 3-Panel Retro Blue)**:
   - **Header Bar**: Stepper Tahapan (`1. DRAFT` -> `2. PROPOSAL` -> `3. FINAL`), Switcher Basis Kendali *real-time* (`[AUTO | HITL | MANUAL]`), Badge Target (`SINTA / SCOPUS`), dan **Retro KPI Scorecards**.
   - **Panel Kiri**: Navigator Struktur Bab + Tab **Fitur Riset** (Pencarian Paper, Upload PDF, dan Tabel Matriks Literatur).
   - **Panel Tengah**: Editor Naskah Akademik + Komponen **HITL Approval Gate** (muncul saat status `WAITING_APPROVAL` dengan tombol `Approve`, `Regenerate`, `Manual Edit`).
   - **Panel Kanan**: **Xishie Assistant** (Chat kontekstual, tombol audit pedoman instan, dan *Live Terminal Log* aktivitas agen `Omnigensi`).
3. **`SmartRevisionCenterPage` (Fitur Revisi)**:
   - Form input catatan revisi dosen pembimbing / reviewer jurnal.
   - Daftar *Actionable Checklist* yang terhubung ke sub-bab, tampilan *Side-by-Side Diff* (Teks Lama vs Usulan `PeerCriticAgent`), dan generator tabel **Matriks Perbaikan Revisi**.
4. **`PlatformMatcherPage` (Rekomendasi Platform Naskah)**:
   - Menampilkan kartu rekomendasi jurnal **SINTA 1-6** atau **Scopus Q1-Q4** hasil analisis `VenueMatcherAgent`.
   - Menampilkan *Match Score (%)*, kesesuaian *Aims & Scope*, estimasi APC, frekuensi terbit, dan tombol **Format Naskah ke Template Jurnal Ini**.

---

### FASE 6: Pengujian (Automated Tests)
Buat pengujian Fitur dan Unit menggunakan Pest / PHPUnit di atas database MySQL testing:
1. Uji alur transisi state `Draft` -> `Proposal` -> `Final` pada keempat jenis topik (`Jurnal`, `Skripsi`, `Tesis`, `Disertasi`).
2. Uji perilaku `XishieSupervisor` saat berpindah antara mode `Automation`, `HITL` (memastikan eksekusi berhenti di *Approval Gate*), dan `Manual`.
3. Uji validasi sitasi pada `ScholarScoutAgent` agar menolak sitasi yang tidak terdaftar di `project_references`.
4. Uji akurasi `HybridVectorMatcherService` (MySQL `FULLTEXT` + *Cosine Similarity*) dan kalkulasi 18 metrik pada `UxKpiTelemetryService`.
