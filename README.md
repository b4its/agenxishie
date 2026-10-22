# Xishie

Aplikasi Laravel 13 dengan PHP 8.4. Seluruh perintah pengembangan dibungkus dalam
`Makefile`, sehingga tidak perlu mengingat kombinasi perintah artisan/npm/docker.

## Agen Xishie — Intelligent Academic & Publication Workspace

*Agentic Academic Workspace* yang memandu peneliti dari ideasi hingga publikasi
(Jurnal, Skripsi, Tesis, Disertasi) untuk target **Nasional (SINTA 1-6)** maupun
**Internasional (Scopus Q1-Q4 / WoS)**.

- **Dokumen PRD**: [`docs/PRD.md`](docs/PRD.md)
- **Master prompt implementasi**: [`PROMPT_IMPLEMENTATION.md`](PROMPT_IMPLEMENTATION.md)

### Arsitektur

Modular Monolith di atas **MySQL 8.4 InnoDB**: UI Filament 5 (`app/Filament`),
logika domain (`app/Domain`), dan engine multi-agent `Omnigensi` (`app/Omnigensi`).
Pencarian semantik memakai **FULLTEXT MySQL** + **Cosine Similarity** di PHP
(`HybridVectorMatcherService`) tanpa ekstensi eksternal seperti pgvector.

| Lapisan | Lokasi |
| --- | --- |
| Enums domain (bidang, dokumen, tahap, mode kendali, KPI) | `app/Domain/*/Enums` |
| Guideline Engine (preset IMRaD & DIKTI) | `app/Domain/GuidelineEngine` |
| State 3 tahap (Draft → Proposal → Final) | `app/Domain/Workspace/StageStateManager` |
| Telemetri 18 UX KPI (HEART + AARRR) | `app/Domain/Workspace/UxKpiTelemetryService` |
| Orkestrator & 5 agen spesialis | `app/Omnigensi/{Orchestrator,Agents}` |
| Memori hybrid (FULLTEXT + cosine) | `app/Omnigensi/Memory` |
| UI Retro Blue & 4 halaman | `app/Filament`, `resources/css/retro-blue-theme.css` |

**5 agen Omnigensi**: `Rule-Keeper`, `Scholar-Scout`, `Academic-Scribe`,
`Peer-Critic`, `Venue-Matcher` — dikoordinasikan `XishieSupervisor` dalam tiga
mode kendali: `AUTOMATION`, `HITL` (approval gate), `MANUAL`.

- **Queue**: `RunStagePipelineJob` & `RunFullPipelineJob` menjalankan pipeline
  Automation di background, dan otomatis berhenti bila `control_mode` di-hot-swap.
- **Vektor & memori**: `HybridVectorMatcherService` (FULLTEXT + cosine) dan
  `SharedProjectMemory` menyuntikkan konteks ke seluruh agen.
- **Xishie Assistant**: slash command `/sitasi`, `/parafrase`, `/cek-gap`,
  `/cek-pedoman`, `/matriks-literatur`, `/cari-referensi`.
- **Fitur riset**: unggah PDF referensi (`PdfTextExtractor` + `ReferenceImportService`)
  yang diekstrak otomatis menjadi matriks literatur (metode/hasil/gap).
- **Revisi**: unggah dokumen komentar reviewer (PDF/DOCX) untuk dipecah menjadi
  checklist, diff, dan matriks Response to Reviewers.
- **Adaptive formatting**: `ManuscriptAdapter` memformat naskah ke template venue
  (gaya sitasi, batas kata, daftar pustaka) dari tombol "Adapt Formatting".
- **Telemetri CSAT**: rating 1-5 di panel assistant tercatat ke `ux_kpi_telemetries`.
- **Real-time**: Laravel Reverb menyiarkan telemetri agen (`AgentTelemetryUpdated`)
  ke `xishie.project.{id}` untuk terminal aktivitas live.

### 8 Tabel Inti (MySQL InnoDB)

`xishie_projects`, `project_stages`, `project_references`, `project_revisions`,
`academic_venues`, `platform_recommendations`, `omnigensi_agent_logs`,
`ux_kpi_telemetries` — kolom dinamis memakai `JSON` native MySQL, dan pencarian
teks memakai indeks `FULLTEXT`.

### Mengakses Workspace

```sh
make up                      # nyalakan stack
# login: test@example.com / password  →  http://localhost:8208/admin
```

Halaman tersedia: **Onboarding Wizard**, **Academic Workspace** (3 panel),
**Smart Revision Center**, dan **Platform Matcher**.

### Pengujian

Test berjalan di atas MySQL (`xishie_testing`):

```sh
make test-db                                   # sekali: buat DB testing
make test IN_DOCKER=1
make lint IN_DOCKER=1
```

Membuat database testing sekali saja:

```sh
docker compose exec db mysql -uroot -e "CREATE DATABASE IF NOT EXISTS xishie_testing"
```

## Prasyarat

Pilih salah satu:

- **Docker** (disarankan) — tidak perlu PHP/Composer di host.
- **PHP 8.4 + Composer + Node 20+** bila ingin `make` dijalankan langsung di host.

Cek ketersediaan tools:

```sh
make check-env
```

## Mulai Cepat

```sh
make setup     # salin .env, install dependency, generate key, migrate + seed, build aset
make up        # nyalakan Nginx + PHP-FPM + MySQL  →  http://localhost:8208
```

Saat stack Docker berjalan, semua target bisa dieksekusi **di dalam container**
dengan menambahkan `IN_DOCKER=1`:

```sh
make test IN_DOCKER=1
make routes IN_DOCKER=1
make fresh IN_DOCKER=1
make db-table TABLE="users" IN_DOCKER=1
```

Bila host tidak punya PHP dan stack Docker belum menyala, gunakan gerbang CI:

```sh
make ci        # composer install + pint --test + test di image terisolasi
```

## Daftar Perintah

Jalankan `make` (atau `make help`) untuk melihat seluruh perintah beserta deskripsinya.

| Kategori | Perintah |
| --- | --- |
| Setup | `setup`, `env`, `install`, `key`, `build`, `storage-link`, `storage-unlink` |
| Pengembangan | `serve`/`dev`, `dev-list`, `serve-http`, `tinker`, `routes`, `logs`, `queue`, `queue-work`, `queue-restart`, `schedule`, `schedule-run`, `about`, `vite` |
| Database | `db`, `migrate`, `migrate-fresh`, `seed`, `fresh`, `db-status`, `db-rollback`, `db-refresh`, `db-wipe`, `db-show`, `db-table` |
| Testing | `test`, `test-unit`, `test-feature`, `test-coverage`, `test-parallel` |
| Kualitas | `lint` (pint --test), `fix` (pint), `format`, `quality` (lint + test) |
| Cache | `optimize`, `optimize-clear`, `cache-clear`, `config-clear` |
| Docker | `up`, `up-apache`, `down`, `down-hard`, `ps`, `docker-health`, `restart`, `rebuild`, `docker-logs`, `shell`, `db-shell` |
| Docker helper | `docker-artisan`, `docker-composer`, `docker-npm`, `docker-test`, `docker-fresh` |
| Passthrough | `artisan CMD="..."`, `composer CMD="..."`, `npm CMD="..."` |
| CI tanpa PHP | `ci-image`, `ci-image-rebuild`, `ci-bootstrap`, `ci`, `ci-coverage`, `ci-lint`, `ci-build` |
| Utilitas | `clean`, `fresh-install`, `check-env`, `help` |

Contoh passthrough:

```sh
make artisan CMD="make:model Post -m"
make composer CMD="require laravel/sanctum"
make npm CMD="run build"
```

## Alur Kerja Umum

Gerbang kualitas sebelum commit:

```sh
make quality              # host (butuh PHP)
make quality IN_DOCKER=1  # bila stack Docker sedang berjalan
make ci                   # tanpa PHP di host, memakai container
```

Reset database:

- Non-destruktif (aman, data dipertahankan): `make db`
- Rollback lalu migrasi ulang + seed: `make db-refresh`
- Destruktif (hapus semua tabel lalu seed ulang): `make fresh`
- Hapus semua tabel tanpa migrasi ulang: `make db-wipe`

## Docker

`docker-compose.yml` menyediakan dua profil yang dapat berjalan bersamaan:

- **nginx**: Nginx + PHP-FPM → `make up`
- **apache**: Apache (PHP modul) → `make up-apache`

Port host (semua nilai = port lama + 114):

| Service | Port host | Variabel |
| --- | --- | --- |
| Nginx | `8208` | `NGINX_HOST_PORT` |
| Apache | `8316` | `APACHE_HOST_PORT` |
| MySQL | `3514` | `DB_HOST_PORT` |
| PHP-FPM | `9208` | `PHPFPM_HOST_PORT` |
| Vite (HMR) | `5381` | `VITE_HOST_PORT` |

Port dapat diubah tanpa menyunting file:

```sh
make up HOST_PORT=8208 DB_HOST_PORT=3514
# atau langsung: NGINX_HOST_PORT=9000 docker compose --profile nginx up -d
```

Penyiapan database saat container start dikendalikan oleh `DB_SETUP`:

- `migrate` (default, non-destruktif): `migrate --force` + seed
- `fresh` (destruktif): `migrate:fresh --seed`
- `none`: lewati

```sh
DB_SETUP=fresh make up
```

> Catatan: `.env.example` menggunakan `DB_HOST=db` (nama service compose). Bila ingin
> menjalankan artisan di host, ubah menjadi `DB_HOST=127.0.0.1` dan `DB_PORT=3514`.

## Berkas Penting

- `Makefile` — seluruh perintah pengembangan.
- `docker-nginx/` — Dockerfile PHP-FPM, konfigurasi Nginx, dan `entrypoint.sh`.
- `docker-apache/` — Dockerfile Apache (profil `apache`).
- `phpunit.xml` — konfigurasi test (SQLite in-memory, `APP_KEY` untuk testing).
