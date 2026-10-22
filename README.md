# Xishie

Aplikasi Laravel 13 dengan PHP 8.4. Seluruh perintah pengembangan dibungkus dalam
`Makefile`, sehingga tidak perlu mengingat kombinasi perintah artisan/npm/docker.

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
make up        # nyalakan Nginx + PHP-FPM + MySQL  →  http://localhost:8094
```

Saat stack Docker berjalan, semua target bisa dieksekusi **di dalam container**
dengan menambahkan `IN_DOCKER=1`:

```sh
make test IN_DOCKER=1
make routes IN_DOCKER=1
make fresh IN_DOCKER=1
```

## Daftar Perintah

Jalankan `make` (atau `make help`) untuk melihat seluruh perintah beserta deskripsinya.

| Kategori | Perintah |
| --- | --- |
| Setup | `setup`, `env`, `install`, `key`, `build`, `storage-link` |
| Pengembangan | `serve`/`dev`, `tinker`, `routes`, `logs`, `queue`, `vite` |
| Database | `db`, `migrate`, `migrate-fresh`, `seed`, `fresh`, `db-status`, `db-rollback` |
| Testing | `test`, `test-unit`, `test-feature`, `test-coverage`, `test-parallel` |
| Kualitas | `lint` (pint --test), `fix` (pint), `quality` (lint + test) |
| Cache | `optimize`, `optimize-clear`, `cache-clear`, `config-clear` |
| Docker | `up`, `up-apache`, `down`, `ps`, `restart`, `docker-logs`, `shell`, `db-shell` |
| Docker helper | `docker-artisan`, `docker-composer`, `docker-npm`, `docker-test`, `docker-fresh` |
| CI tanpa PHP | `ci-image`, `ci-bootstrap`, `ci` |
| Utilitas | `clean`, `fresh-install`, `check-env` |

## Alur Kerja Umum

Gerbang kualitas sebelum commit:

```sh
make quality            # host (butuh PHP)
make ci                 # tanpa PHP di host, memakai container
make quality IN_DOCKER=1  # bila stack Docker sedang berjalan
```

Reset database:

- Non-destruktif (aman, data dipertahankan): `make db`
- Destruktif (hapus semua tabel lalu seed ulang): `make fresh`

## Docker

`docker-compose.yml` menyediakan dua profil:

- **nginx** (default): Nginx + PHP-FPM + MySQL → `make up`
- **apache**: Apache + PHP-FPM + MySQL → `make up-apache`

Port yang dipakai:

| Service | Host |
| --- | --- |
| Web (nginx/apache) | `8094` |
| MySQL | `3400` |
| Vite (HMR) | `5267` |

Penyiapan database saat container start dikendalikan oleh `DB_SETUP`:

- `migrate` (default, non-destruktif): `migrate --force` + seed
- `fresh` (destruktif): `migrate:fresh --seed`
- `none`: lewati

```sh
DB_SETUP=fresh make up
```

> Catatan: `.env.example` menggunakan `DB_HOST=db` (nama service compose). Bila ingin
> menjalankan artisan di host, ubah menjadi `DB_HOST=127.0.0.1` dan `DB_PORT=3400`.

## Berkas Penting

- `Makefile` — seluruh perintah pengembangan.
- `docker-nginx/` — Dockerfile PHP-FPM, konfigurasi Nginx, dan `entrypoint.sh`.
- `docker-apache/` — Dockerfile Apache (profil `apache`).
- `phpunit.xml` — konfigurasi test (SQLite in-memory, `APP_KEY` untuk testing).
