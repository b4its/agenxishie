#!/bin/sh
set -e

# Perilaku startup dapat dikontrol lewat environment variable DB_SETUP:
#   fresh  → migrate:fresh --seed (DESTRUKTIF, default untuk dev)
#   migrate→ migrate --force + seed idempoten (aman untuk data yang ada)
#   none   → lewati penyiapan database
DB_SETUP="${DB_SETUP:-fresh}"

setup_database() {
    attempt=0
    while true; do
        attempt=$((attempt + 1))

        case "$DB_SETUP" in
            fresh)
                echo "[xishie] Menyiapkan database (migrate:fresh --seed)..."
                if php artisan migrate:fresh --seed --force; then
                    echo "[xishie] Database berhasil di-refresh bersama data seeder."
                    return 0
                fi
                ;;
            migrate)
                echo "[xishie] Menyiapkan database (migrate + seed)..."
                if php artisan migrate --force && php artisan db:seed --force; then
                    echo "[xishie] Migrasi & seeder selesai."
                    return 0
                fi
                ;;
            none)
                echo "[xishie] DB_SETUP=none, penyiapan database dilewati."
                return 0
                ;;
            *)
                echo "[xishie] Nilai DB_SETUP tidak dikenal: '$DB_SETUP' (pakai: fresh|migrate|none)"
                return 1
                ;;
        esac

        if [ "$attempt" -ge 30 ]; then
            echo "[xishie] Peringatan: database belum siap setelah $attempt percobaan, lanjut tanpa penyiapan."
            return 0
        fi

        echo "[xishie] Database belum siap (percobaan ${attempt}/30), mencoba lagi dalam 2 detik..."
        sleep 2
    done
}

setup_database

echo "[xishie] Menjalankan: $*"
exec "$@"
