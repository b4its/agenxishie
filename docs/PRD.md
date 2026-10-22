# PRODUCT REQUIREMENTS DOCUMENT (PRD): AGEN XISHIE

| Metadata Proyek | Detail Spesifikasi |
| :--- | :--- |
| **Nama Produk** | **Agen Xishie** (Intelligent Academic & Publication Workspace) |
| **Repositori Utama** | `https://github.com/b4its/agenxishie` |
| **Engine Multi-Agent** | `https://github.com/b4its/omnigensi` (Embedded Modular Monolith) |
| **Pola Arsitektur** | Modular Monolith Architecture (Single Deployment Unit, Shared State & MySQL DB) |
| **Tech Stack Inti** | PHP 8.4+, Laravel 13, Filament 5, Livewire 3, Tailwind CSS, MySQL 8.4+ (InnoDB + JSON + FULLTEXT), Laravel Reverb |
| **Design System** | **Retro Blue Data-Literate UI** (Neo-Brutalist Retro Blueprint + 18 UX KPIs Telemetry) |

---

## 1. Visi & Tujuan Produk

**Agen Xishie** adalah platform kerja akademik cerdas (*Agentic Academic Workspace*) yang memandu peneliti, mahasiswa, dan akademisi dari tahap ideasi hingga publikasi naskah ilmiah (**Jurnal, Skripsi, Tesis, Disertasi**) baik untuk target **Nasional (SINTA 1-6)** maupun **Internasional (Scopus Q1-Q4 / Web of Science)**.

Sistem ini menggabungkan manajemen dokumen 3 tahap (**Draft, Proposal, Final**), kepatuhan pedoman penulisan otomatis, riset literatur anti-halusinasi, manajemen revisi dosen/reviewer, serta rekomendasi platform publikasi yang digerakkan oleh orkestrator multi-agent **Omnigensi** dalam tiga mode kendali: **Automation**, **Human-in-the-Loop (HITL)**, dan **Manual**.

---

## 2. Arsitektur Sistem: Modular Monolith (`agenxishie` + `omnigensi`)

Seluruh komponen berjalan dalam satu kesatuan *codebase* (`agenxishie`) di atas database **MySQL** tanpa pemisahan *microservice* eksternal untuk menjamin *zero network-hop latency*, transaksi ACID yang konsisten, dan *real-time state streaming*.

```text
agenxishie/
├── app/
│   ├── Filament/                        # Presentation Layer (Filament 5 + Retro Blue UI)
│   │   ├── Pages/
│   │   │   ├── ProjectOnboardingWizard.php
│   │   │   ├── AcademicWorkspacePage.php
│   │   │   ├── SmartRevisionCenterPage.php
│   │   │   └── PlatformMatcherPage.php
│   │   └── Widgets/
│   │       ├── WorkspaceKpiBarWidget.php
│   │       ├── HeartKpiTelemetryWidget.php
│   │       ├── AarrrFunnelWidget.php
│   │       └── AgentActivityTerminalWidget.php
│   ├── Domain/                          # Core Academic Business Logic
│   │   ├── Personalization/             # Bidang Keilmuan, Jenis Topik, Target Publikasi
│   │   ├── GuidelineEngine/             # Default Presets & Custom PDF/DOCX Rule Extractor
│   │   ├── Workspace/                   # State Manager: Draft, Proposal, Final
│   │   ├── Research/                    # API Scholar, PDF Parser, Literature Matrix, Citations
│   │   ├── Assistant/                   # Context-Aware Xishie Assistant Chat & Commands
│   │   ├── Revision/                    # Feedback Parser, Actionable Todo, Side-by-Side Diff
│   │   └── Publication/                 # SINTA 1-6 & Scopus Q1-Q4 Matcher & Converter
│   └── Omnigensi/                       # Multi-Agent Engine ([github.com/b4its/omnigensi](https://github.com/b4its/omnigensi))
│       ├── Contracts/
│       │   └── XishieAgentContract.php  # Kontrak standar seluruh agen spesialis
│       ├── Data/
│       │   └── AgentExecutionResultData.php # Readonly DTO hasil eksekusi & metrik agen
│       ├── Orchestrator/
│       │   ├── XishieSupervisor.php     # Top-level Agent Coordinator
│       │   ├── ExecutionModeRouter.php  # Router: Automation, HITL, Manual
│       │   └── ApprovalGateManager.php  # Checkpoint locker untuk mode HITL
│       ├── Agents/
│       │   ├── RuleKeeperAgent.php      # Agen Ekstraksi & Audit Pedoman Penulisan
│       │   ├── ScholarScoutAgent.php    # Agen Riset Literatur & Validasi Sitasi
│       │   ├── AcademicScribeAgent.php  # Agen Penulis Akademik (Draft/Proposal/Final)
│       │   ├── PeerCriticAgent.php      # Agen Analisis Revisi Dosen/Reviewer
│       │   └── VenueMatcherAgent.php    # Agen Rekomendasi Platform Jurnal/Konferensi
│       └── Memory/
│           ├── SharedProjectMemory.php  # Konteks bersama berbasis MySQL JSON + FULLTEXT
│           └── HybridVectorMatcherService.php # Kalkulasi Cosine Similarity di atas MySQL
```

---

## 3. Spesifikasi Fungsional End-to-End (Core Modules)

### 3.1. Modul Personalisasi & Inisialisasi Proyek

1. **Pemilihan Bidang Keilmuan**:
   - Pilihan taksonomi: *STEM & Computer Science*, *Kesehatan & Medis*, *Sosial & Humaniora*, *Ekonomi & Bisnis*, *Hukum & Kebijakan*, *Pendidikan*, atau *Multi-disiplin*.
   - Berdampak pada *academic tone*, metodologi riset bawaan, dan sumber database pencarian literatur.

2. **Pemilihan Jenis Topik / Dokumen**:
   - Pilihan: `Jurnal Ilmiah`, `Skripsi`, `Tesis`, `Disertasi`.
   - Menentukan kedalaman teori, tuntutan *novelty*, dan struktur bab di setiap tahapan.

3. **Konfigurasi Panduan Penulisan (Guideline Engine)**:
   - **Mode Default**: Memuat *preset* baku secara otomatis berdasarkan Jenis Topik (struktur IMRaD untuk Jurnal; Bab 1-3 Proposal & Bab 1-5 Final standar DIKTI untuk Skripsi/Tesis/Disertasi) beserta gaya sitasi (`APA 7th`, `IEEE`, `Vancouver`, `Chicago`).
   - **Mode Custom Upload**: Pengguna mengunggah file PDF/DOCX pedoman kampus atau *Author Guidelines* jurnal. `RuleKeeperAgent` mengekstrak aturan (struktur bab, batas kata, format sitasi, aturan abstrak) ke dalam skema `guideline_rules` (MySQL `JSON`).

4. **Penentuan Target Akhir**:
   - **Internasional**: Terindeks Scopus (`Q1`, `Q2`, `Q3`, `Q4`), Web of Science (WoS), atau Prosiding Internasional (IEEE/ACM/Springer).
   - **Nasional**: Terakreditasi ARJUNA/SINTA (`SINTA 1` s.d. `SINTA 6`).
   - **Repositori Kampus + Konversi Publikasi**: Menyelesaikan naskah Skripsi/Tesis/Disertasi sekaligus menghasilkan ekstrak *manuscript* jurnal siap terbit.

5. **Pemilihan Basis Kendali (`Control Mode`)**:
   - Dapat dipilih saat inisialisasi dan diubah (*hot-swap*) kapan saja di dalam workspace: `Automation`, `Human-in-the-Loop (HITL)`, atau `Manual`.

### 3.2. Modul Workspace 3 Tahapan (`Draft` -> `Proposal` -> `Final`)

Workspace memiliki 3 tahapan sekuensial namun fleksibel:

| Tahap | Output pada Skripsi / Tesis / Disertasi | Output pada Jurnal Ilmiah |
| --- | --- | --- |
| **1. DRAFT** | Perumusan masalah, *Research Gap*, *State of the Art (SOTA)*, Matriks Literatur, Kerangka Berpikir, dan Outline Bab. | Identifikasi *Novelty*, pemetaan literatur utama, rumusan kontribusi, dan kerangka IMRaD. |
| **2. PROPOSAL** | Naskah lengkap **Bab 1 (Pendahuluan)**, **Bab 2 (Tinjauan Pustaka)**, **Bab 3 (Metodologi Penelitian)**, dan Daftar Pustaka awal (Siap Seminar Proposal). | Draf **Introduction**, **Related Work**, dan **Methodology / Experimental Setup**. |
| **3. FINAL** | Integrasi data hasil riset ke **Bab 4 (Hasil & Pembahasan)**, **Bab 5 (Kesimpulan & Saran)**, Abstrak (ID/EN), pemeriksaan kepatuhan penuh, serta ekstrak naskah publikasi. | Naskah utuh **Results, Discussion, Conclusion**, Abstrak, *Keywords*, dan pemformatan sesuai *template* platform target. |

### 3.3. Spesifikasi 3 Basis Kendali (`Omnigensi Execution Modes`)

1. **Mode `Automation` (Full Agentic Pipeline)**:
   - `XishieSupervisor` menggerakkan `ScholarScoutAgent` -> `AcademicScribeAgent` -> `RuleKeeperAgent` secara beruntun di *background queue* dengan pengecekan state *idempotent*.
   - Menghasilkan draf lengkap lintas sub-bab tanpa intervensi manual, sembari menampilkan telemetri progres secara *real-time*.

2. **Mode `Human-in-the-Loop / HITL` (Co-Pilot with Approval Gates)**:
   - Setiap kali agen menyelesaikan 1 artefak atau 1 sub-bab, state proyek berubah menjadi `waiting_approval`.
   - Muncul komponen **Retro Approval Gate** di bawah blok editor dengan 3 aksi: `[Approve & Continue]`, `[Request Revision with Prompt]`, atau `[Edit Manually]`.

3. **Mode `Manual` (AI-Assisted Academic Editor)**:
   - Agen tidak menulis otomatis ke kanvas utama. Pengguna menulis mandiri menggunakan editor.
   - `Omnigensi` bekerja secara *on-demand* melalui *slash command* (`/sitasi`, `/parafrase`, `/cek-gap`) dan memantau skor kepatuhan pedoman (`Compliance Score`) secara pasif.

### 3.4. Modul Fitur Riset & Xishie Assistant

1. **Fitur Riset (`ScholarScoutAgent`)**:
   - Pencarian paper terintegrasi (Semantic Scholar, OpenAlex, Crossref, Garuda/SINTA metadata).
   - *Upload* PDF referensi untuk diekstrak menjadi **Tabel Matriks Literatur** (Penulis, Tahun, Metode, Hasil, Gap/Keterbatasan).
   - **Strict Citation Binding**: Setiap kalimat berargumen yang dihasilkan agen wajib menyertakan referensi dari tabel `project_references`. Dilarang menghasilkan sitasi fiktif.

2. **Xishie Assistant**:
   - Panel diskusi kontekstual yang memahami 100% isi naskah aktif, pedoman penulisan yang berlaku, daftar referensi proyek, serta catatan revisi melalui `SharedProjectMemory` (MySQL `FULLTEXT` + `JSON` embedding).
   - Dilengkapi fitur **Live Guideline Auditor** yang menandai sub-bab yang melanggar batas kata atau aturan sitasi.

### 3.5. Modul Fitur Revisi (`Smart Revision & Review Tracker`)

1. **Input Masukan Multi-Sumber**: Menerima catatan revisi dari Dosen Pembimbing 1/2, Dosen Penguji, atau Reviewer Jurnal (teks poin-poin atau unggahan dokumen komentar).
2. **Dekomposisi Tugas (`PeerCriticAgent`)**: Memecah catatan mentah menjadi *Actionable Revision Checklist* dan memetakan ke ID sub-bab/paragraf yang terdampak di editor.
3. **Diff & Response Matrix**:
   - Menampilkan perbandingan teks *Before vs After* (*side-by-side diff*) dari kolom `diff_payload`.
   - Menghasilkan dokumen **Tabel Matriks Perbaikan Revisi / Response to Reviewers** secara otomatis.

### 3.6. Modul Rekomendasi Platform (`Manuscript Matcher`)

1. **Analisis Naskah (`VenueMatcherAgent`)**: Mengekstrak kata kunci dan vektor semantik (`JSON` float array) dari Judul, Abstrak, Bidang Keilmuan, dan Metodologi pada tahap `Final`.
2. **Pencocokan Target Hybrid di MySQL**:
   - Menyaring kandidat awal dari tabel master `academic_venues` menggunakan MySQL `FULLTEXT` (`MATCH(title, aims_and_scope) AGAINST(...)`) dan filter indeks (`SINTA 1-6` atau `Scopus Q1-Q4` / `WoS`).
   - Menghitung skor kemiripan semantik presisi (*Cosine Similarity*) melalui `HybridVectorMatcherService`.

3. **Output Rekomendasi**: Menampilkan *Match Score (%)*, alasan kecocokan ruang lingkup (*Aims & Scope*), kuartil/peringkat SINTA, estimasi waktu publikasi, APC (biaya), tautan pengajuan, dan tombol **Adapt Formatting to This Journal**.

---

## 4. Sistem Desain UI/UX: "Retro Blue Data-Literate System"

Filosofi desain: *"Design without data is just opinion with better typography."* Antarmuka menggabungkan estetika **Retro OS / Technical Blueprint** berwarna dasar biru dengan transparansi metrik perilaku dan kualitas penulisan.

### 4.1. Palet Warna & Token Desain

- **Background Blueprint Canvas**: `#F0F6FF` dengan grid titik/garis `#CBD5E1` (`16px`).
- **Primary Retro Cobalt Blue**: `#1E40AF` (Title bar, navigasi utama, header tabel).
- **Secondary Electric Blue**: `#2563EB` (Tombol utama, indikator agen aktif).
- **Soft Cyan Surface**: `#E0F2FE` (Kartu metrik KPI, kotak dialog HITL Gate).
- **Ink Navy Border & Hard Shadow**: Border `2px solid #0F172A`, Shadow `4px 4px 0px #0F172A` (tanpa efek blur).
- **Tipografi**:
  - Heading & KPI Numbers: `Space Grotesk` (Bold, Uppercase tracking).
  - Academic Editor Body: `Source Serif 4` / `Merriweather`.
  - Telemetry, Logs & Metrics: `JetBrains Mono`.

### 4.2. Implementasi 18 UX & Product KPIs (HEART + AARRR Framework)

Sistem wajib memiliki telemetri terukur yang divisualisasikan ke dalam **Workspace Telemetry Bar** (untuk pengguna) dan **UX Analytics Dashboard** (untuk evaluasi produk):

| No | Nama KPI | Kategori Framework | Cara Pengukuran di Agen Xishie |
| --- | --- | --- | --- |
| 1 | **Guideline Compliance Score** | Task Success (UX) | Persentase aturan pedoman (kata, sitasi, bab) yang terpenuhi (`0-100%`). |
| 2 | **Time-to-Stage Completion** | Efficiency (UX) | Durasi rata-rata penyelesaian dari `Draft` ke `Proposal` hingga `Final`. |
| 3 | **HITL Approval Ratio** | Happiness / Quality (HEART) | Rasio draf agen yang langsung disetujui (`Approved`) vs diminta ulang (`Regenerated`). |
| 4 | **Control Mode Distribution** | Adoption (HEART) | Persentase penggunaan mode `Automation` vs `HITL` vs `Manual` per sesi. |
| 5 | **Verified Citation Density** | Task Quality (UX) | Jumlah sitasi valid per 1.000 kata yang terhubung ke DOI/PDF nyata. |
| 6 | **Revision Resolution Velocity** | Efficiency (UX) | Kecepatan penyelesaian butir revisi dosen/reviewer (`Resolved / Total Items`). |
| 7 | **Literature Matrix Coverage** | Engagement (HEART) | Jumlah paper yang diekstrak ke dalam matriks komparasi SOTA per proyek. |
| 8 | **Assistant Query Effectiveness** | Task Success (UX) | Rasio respons `Xishie Assistant` yang disematkan/diterapkan ke dalam editor naskah. |
| 9 | **Onboarding Completion Rate** | Acquisition/Activation (AARRR) | Persentase pengguna yang menuntaskan Wizard Personalisasi hingga masuk ke tahap `Draft`. |
| 10 | **Custom vs Default Guideline Rate** | Adoption (HEART) | Rasio pengguna yang mengunggah pedoman khusus (PDF/DOCX) dibanding memakai preset default. |
| 11 | **Active Writing Session Depth** | Engagement (HEART) | Durasi aktif dan jumlah kata tervalidasi per sesi kerja di dalam workspace. |
| 12 | **Stage-to-Stage Retention** | Retention (AARRR) | Persentase proyek yang berlanjut dari `Draft` -> `Proposal` -> `Final`. |
| 13 | **Agent Error / Hallucination Rate** | Error Rate (UX) | Persentase klaim/sitasi yang gagal divalidasi oleh `RuleKeeperAgent` & `ScholarScoutAgent`. |
| 14 | **Journal Match Confidence Score** | Task Success (UX) | Skor kemiripan semantik tertinggi antara abstrak naskah dengan jurnal SINTA/Scopus. |
| 15 | **Manuscript Export Conversion** | Conversion (AARRR) | Persentase proyek tahap `Final` yang diekspor ke format siap kirim (DOCX/LaTeX/PDF). |
| 16 | **Skripsi-to-Journal Conversion** | Referral/Expansion (AARRR) | Persentase pengguna Skripsi/Tesis yang menggunakan fitur konversi otomatis ke format Jurnal. |
| 17 | **System Response Latency (TTFT)** | Performance (UX) | *Time-to-First-Token* dan waktu eksekusi per sub-agent di dalam modul `Omnigensi`. |
| 18 | **User Satisfaction Score (CSAT/NPS)** | Happiness (HEART) | Rating kepuasan pengguna setelah menyelesaikan satu tahapan dokumen atau siklus revisi. |

---

## 5. Skema Database Inti (MySQL 8.4+ InnoDB)

1. `xishie_projects`: Menyimpan metadata proyek, `user_id`, `title`, `field_of_study`, `document_type` (`journal`, `skripsi`, `thesis`, `dissertation`), `target_scope` (`international_scopus`, `national_sinta`, `campus_repo`), `target_tier`, `control_mode` (`automation`, `hitl`, `manual`), `current_stage` (`draft`, `proposal`, `final`), `guideline_type` (`default`, `custom`), dan `guideline_rules` (`JSON`).
2. `project_stages`: Menyimpan data per tahapan (`draft`, `proposal`, `final`), `status` (`locked`, `in_progress`, `waiting_approval`, `completed`), `outline_schema` (`JSON`), `sections_content` (`JSON`), `compiled_content` (`LONGTEXT`), `hitl_pending_payload` (`JSON`), dan `compliance_score` (`DECIMAL(5,2)`).
3. `project_references`: Menyimpan pustaka riset, `citation_key`, `doi`, `title`, `abstract` (`TEXT`), `authors` (`JSON`), `year`, `journal_name`, `sinta_or_scopus_tier`, `matrix_extraction` (`JSON`: metode, hasil, gap), `embedding` (`JSON` array float 1536 dimensi), serta indeks `FULLTEXT(title, abstract)`.
4. `project_revisions`: Menyimpan siklus revisi, `project_id`, `stage_id`, `reviewer_role`, `raw_feedback` (`LONGTEXT`), `action_items` (`JSON` dengan pemetaan blok paragraf & status `pending`/`resolved`), `diff_payload` (`JSON`), `response_matrix_doc` (`LONGTEXT`), dan `status`.
5. `academic_venues`: Katalog master jurnal & konferensi target (`journal_name`, `publisher`, `scope_type` [`sinta`/`scopus`/`wos`], `index_tier` [`SINTA 1-6` / `Q1-Q4`], `field_of_study`, `aims_and_scope` [`TEXT`], `apc_estimate`, `submission_url`, `template_rules` [`JSON`], `aims_scope_embedding` [`JSON`]), dilengkapi indeks `FULLTEXT(journal_name, aims_and_scope)`.
6. `platform_recommendations`: Menyimpan hasil rekomendasi jurnal per proyek (`project_id`, `academic_venue_id`, `journal_name`, `publisher`, `index_tier`, `match_score` [`DECIMAL(5,2)`], `scope_reasoning` [`TEXT`], `apc_estimate`, dan `submission_url`).
7. `omnigensi_agent_logs`: Menyimpan jejak eksekusi multi-agent (`project_id`, `stage_id`, `agent_name`, `action`, `control_mode`, `ttft_ms`, `execution_time_ms`, `token_usage`, `hallucination_rejected_count`, `status`, `payload` [`JSON`]).
8. `ux_kpi_telemetries`: Menyimpan snapshot teragregasi dari 18 metrik UX KPIs (HEART & AARRR) per proyek dan sesi pengguna (`project_id`, `user_id`, `metric_key`, `metric_category`, `metric_value` [`DECIMAL(10,4)`], `metadata` [`JSON`], `recorded_at`).
