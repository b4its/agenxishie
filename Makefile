# ==============================================================================
# Xishie — Laravel Application Makefile
# ==============================================================================
# Semua perintah harian ada di sini. Secara default perintah dijalankan di HOST.
# Gunakan `make <target> ARTISAN=docker` (atau `make docker-*`) untuk menjalankan
# perintah di dalam container PHP (php-fpm), sehingga tidak perlu PHP di host.
#
#   make            # tampilkan daftar perintah (sama dengan: make help)
#   make help
#   make setup      # instalasi lengkap dari nol (dependencies, env, key, db)
#   make dev        # jalankan server + queue + vite sekaligus
#   make test       # jalankan seluruh test
#   make quality    # pint + test (gerbang kualitas sebelum commit)
# ==============================================================================

# --- Konfigurasi dasar ---------------------------------------------------------
APP_NAME        ?= xishie
COMPOSE         ?= docker compose
DOCKER_EXEC     ?= $(COMPOSE) exec -T php-fpm
HOST_PORT       ?= 8094
APP_URL         ?= http://localhost:$(HOST_PORT)

# Binari default (host). Jalankan di container dengan: `make <target> IN_DOCKER=1`.
IN_DOCKER       ?= 0
ifeq ($(IN_DOCKER),1)
ARTISAN         ?= $(DOCKER_EXEC) php artisan
PHP             ?= $(DOCKER_EXEC) php
COMPOSER        ?= $(DOCKER_EXEC) composer
NPM             ?= $(DOCKER_EXEC) npm
PINT            ?= $(DOCKER_EXEC) ./vendor/bin/pint
else
ARTISAN         ?= php artisan
PHP             ?= php
COMPOSER        ?= composer
NPM             ?= npm
PINT            ?= ./vendor/bin/pint
endif

# Nama service di docker-compose.yml
PHP_SERVICE     ?= php-fpm
DB_SERVICE      ?= db
NGINX_SERVICE   ?= nginx

# Flags
PHPUNIT_FLAGS   ?=
PINT_FLAGS      ?= --dirty

.DEFAULT_GOAL := help
SHELL         := /bin/bash
.SHELLFLAGS   := -eu -o pipefail -c

# Beri pesan yang jelas bila PHP tidak tersedia di host dan IN_DOCKER tidak aktif.
.PHONY: require-php
require-php:
ifeq ($(IN_DOCKER),0)
	@command -v $(firstword $(ARTISAN)) >/dev/null 2>&1 || { \
		echo "✗ PHP/artisan tidak ditemukan di host."; \
		echo "  Jalankan lewat container: make <target> IN_DOCKER=1"; \
		echo "  atau gerbang CI tanpa PHP: make ci"; \
		exit 1; \
	}
endif

# ------------------------------------------------------------------------------
# Help (default)
# ------------------------------------------------------------------------------
.PHONY: help
help: ## Tampilkan daftar perintah yang tersedia
	@printf "\n\033[1;36m%s\033[0m\n" "Xishie — perintah tersedia:"
	@printf "  \033[2mJalankan lewat container dengan menambahkan: IN_DOCKER=1\033[0m\n\n"
	@awk 'BEGIN {FS = ":.*##"} /^[a-zA-Z0-9_-]+:.*##/ { printf "  \033[1;32m%-26s\033[0m %s\n", $$1, $$2 }' $(MAKEFILE_LIST)
	@printf "\n"

# ------------------------------------------------------------------------------
# Setup & Instalasi
# ------------------------------------------------------------------------------
.PHONY: setup
setup: env install key db ## Setup lengkap dari nol (env, install, key, migrate+seed, build)

.PHONY: env
env: ## Salin .env.example ke .env bila belum ada
	@if [ -f .env ]; then \
		echo "✓ .env sudah ada, dilewati."; \
	else \
		cp .env.example .env && echo "✓ .env dibuat dari .env.example"; \
	fi

.PHONY: install
install: ## Install dependencies (composer + npm)
	@echo "→ composer install"
	$(COMPOSER) install --no-interaction --prefer-dist
	@echo "→ npm install"
	$(NPM) install
	@echo "✓ Dependencies terpasang."

.PHONY: build
build: node_modules ## Build aset frontend (vite production)
	$(NPM) run build

# Pastikan dependency frontend terpasang sebelum build/dev.
# (package-lock.json bersifat opsional, jadi tidak dijadikan prasyarat.)
node_modules:
	@if [ -d node_modules ]; then \
		echo "✓ node_modules sudah ada, dilewati."; \
	else \
		echo "→ node_modules belum ada, menjalankan npm install"; \
		$(NPM) install; \
	fi
	@touch node_modules

.PHONY: key
key: require-php ## Generate APP_KEY
	@if grep -q "^APP_KEY=.\+" .env 2>/dev/null; then \
		echo "✓ APP_KEY sudah ada, dilewati."; \
	else \
		$(ARTISAN) key:generate --ansi; \
	fi

.PHONY: storage-link
storage-link: require-php ## Buat symlink public/storage
	$(ARTISAN) storage:link

# ------------------------------------------------------------------------------
# Development
# ------------------------------------------------------------------------------
.PHONY: serve
serve: ## Jalankan dev server beserta queue + vite (concurrently)
	$(COMPOSER) dev

.PHONY: dev
dev: serve ## Alias untuk `serve`

.PHONY: tinker
tinker: require-php ## Masuk ke REPL Tinker
	$(ARTISAN) tinker

.PHONY: routes
routes: require-php ## Daftar route terdaftar
	$(ARTISAN) route:list

.PHONY: logs
logs: require-php ## Tail aplikasi log
	$(ARTISAN) pail

.PHONY: queue
queue: require-php ## Jalankan queue worker (dev)
	$(ARTISAN) queue:listen --tries=1

.PHONY: vite
vite: ## Jalankan vite dev server
	$(NPM) run dev

# ------------------------------------------------------------------------------
# Database
# ------------------------------------------------------------------------------
.PHONY: db
db: require-php ## Siapkan database: migrate + seed
	$(ARTISAN) migrate --force --ansi
	$(ARTISAN) db:seed --force --ansi

.PHONY: migrate
migrate: require-php ## Jalankan migrasi yang tertunda
	$(ARTISAN) migrate --ansi

.PHONY: migrate-fresh
migrate-fresh: require-php ## Hapus semua tabel lalu migrasi ulang (DESTRUKTIF)
	$(ARTISAN) migrate:fresh --ansi

.PHONY: seed
seed: require-php ## Jalankan database seeder
	$(ARTISAN) db:seed --ansi

.PHONY: fresh
fresh: require-php ## migrate:fresh + seed (DESTRUKTIF)
	$(ARTISAN) migrate:fresh --seed --ansi

.PHONY: db-status
db-status: require-php ## Status migrasi
	$(ARTISAN) migrate:status

.PHONY: db-rollback
db-rollback: require-php ## Rollback migrasi terakhir
	$(ARTISAN) migrate:rollback --ansi

# ------------------------------------------------------------------------------
# Testing
# ------------------------------------------------------------------------------
.PHONY: test
test: require-php ## Jalankan seluruh test (Pest/PHPUnit)
	@$(ARTISAN) config:clear --ansi >/dev/null
	$(ARTISAN) test $(PHPUNIT_FLAGS)

.PHONY: test-unit
test-unit: require-php ## Test hanya suite Unit
	$(ARTISAN) test --testsuite=Unit $(PHPUNIT_FLAGS)

.PHONY: test-feature
test-feature: require-php ## Test hanya suite Feature
	$(ARTISAN) test --testsuite=Feature $(PHPUNIT_FLAGS)

.PHONY: test-coverage
test-coverage: require-php ## Test dengan laporan coverage
	$(ARTISAN) test --coverage $(PHPUNIT_FLAGS)

.PHONY: test-parallel
test-parallel: require-php ## Test secara paralel
	$(ARTISAN) test --parallel $(PHPUNIT_FLAGS)

# ------------------------------------------------------------------------------
# Kualitas Kode
# ------------------------------------------------------------------------------
.PHONY: lint
lint: ## Periksa gaya kode dengan Pint (tanpa mengubah file)
	$(PINT) --test $(PINT_FLAGS)

.PHONY: fix
fix: ## Format kode dengan Pint (mengubah file)
	$(PINT) $(PINT_FLAGS)

.PHONY: format
format: fix ## Alias untuk `fix`

.PHONY: quality
quality: lint test ## Gerbang kualitas: lint + test

# ------------------------------------------------------------------------------
# Cache & Optimasi
# ------------------------------------------------------------------------------
.PHONY: optimize
optimize: require-php ## Cache config, route, view, dan event (produksi)
	$(ARTISAN) optimize

.PHONY: optimize-clear
optimize-clear: require-php ## Bersihkan seluruh cache optimasi
	$(ARTISAN) optimize:clear

.PHONY: cache-clear
cache-clear: require-php ## Bersihkan cache (config, route, view, cache, event)
	$(ARTISAN) optimize:clear --ansi

.PHONY: config-clear
config-clear: require-php ## Hapus cache config
	$(ARTISAN) config:clear

# ------------------------------------------------------------------------------
# Docker
# ------------------------------------------------------------------------------
# Mode penyiapan DB saat container start: fresh | migrate | none
DB_SETUP        ?= migrate

.PHONY: up
up: ## Nyalakan stack Nginx + PHP-FPM + MySQL (background)
	DB_SETUP=$(DB_SETUP) $(COMPOSE) --profile nginx up -d --build
	@echo "✓ Aplikasi: $(APP_URL)"
	@echo "  Mode DB : $(DB_SETUP) (ubah dengan DB_SETUP=fresh|migrate|none)"

.PHONY: up-apache
up-apache: ## Nyalakan stack Apache + MySQL (background)
	DB_SETUP=$(DB_SETUP) $(COMPOSE) --profile apache up -d --build

.PHONY: down
down: ## Matikan seluruh container
	$(COMPOSE) --profile nginx --profile apache down

.PHONY: ps
ps: ## Status container
	$(COMPOSE) --profile nginx --profile apache ps

.PHONY: restart
restart: ## Restart seluruh container
	$(COMPOSE) restart

.PHONY: docker-logs
docker-logs: ## Tail log seluruh container
	$(COMPOSE) logs -f --tail=100

.PHONY: shell
shell: ## Masuk ke shell container PHP (php-fpm)
	$(COMPOSE) exec $(PHP_SERVICE) bash

.PHONY: db-shell
db-shell: ## Masuk ke konsol MySQL
	$(COMPOSE) exec $(DB_SERVICE) mysql -uroot

# Target Docker meng-override binari host agar dijalankan di dalam container.
.PHONY: docker-artisan docker-composer docker-npm docker-test
docker-artisan: ## Jalankan artisan di container (ARGS="...")
	$(DOCKER_EXEC) php artisan $(ARGS)

docker-composer: ## Jalankan composer di container (ARGS="...")
	$(DOCKER_EXEC) composer $(ARGS)

docker-npm: ## Jalankan npm di container (ARGS="...")
	$(DOCKER_EXEC) npm $(ARGS)

docker-test: ## Jalankan test di container
	$(DOCKER_EXEC) php artisan config:clear >/dev/null
	$(DOCKER_EXEC) php artisan test $(PHPUNIT_FLAGS)

.PHONY: docker-fresh
docker-fresh: ## migrate:fresh --seed di container (DESTRUKTIF)
	$(DOCKER_EXEC) php artisan migrate:fresh --seed --force

# ------------------------------------------------------------------------------
# Git & Utilitas
# ------------------------------------------------------------------------------
.PHONY: clean
clean: require-php ## Bersihkan cache framework & file sementara
	$(ARTISAN) optimize:clear --ansi || true
	rm -rf public/build public/hot
	@echo "✓ Bersih."

.PHONY: fresh-install
fresh-install: clean ## Bersihkan total lalu setup ulang
	rm -rf vendor node_modules
	$(MAKE) setup

.PHONY: check-env
check-env: ## Verifikasi tools yang dibutuhkan
	@echo "PHP     : $$(command -v $(PHP) || echo '✗ tidak ditemukan')"
	@echo "Composer: $$(command -v $(COMPOSER) || echo '✗ tidak ditemukan')"
	@echo "Node    : $$(command -v node || echo '✗ tidak ditemukan')"
	@echo "Docker  : $$(command -v docker || echo '✗ tidak ditemukan')"
	@echo "Make    : $$(command -v make || echo '✗ tidak ditemukan')"

# ------------------------------------------------------------------------------
# Rootless ringan untuk CI / tanpa PHP di host
# ------------------------------------------------------------------------------
# Membangun (sekali) image turunan berisi ekstensi + tool yang dibutuhkan,
# lalu menjalankan seluruh gerbang kualitas di dalamnya (SQLite in-memory).
# Tidak butuh docker-compose dan tidak butuh PHP di host.
CI_IMAGE_NAME   ?= xishie/ci
CI_IMAGE_TAG    ?= php8.4
CI_IMAGE        ?= $(CI_IMAGE_NAME):$(CI_IMAGE_TAG)
CI_RUN_FLAGS    ?= --rm -v "$(CURDIR)":/app -w /app \
	-e COMPOSER_ALLOW_SUPERUSER=1 \
	-e APP_ENV=testing \
	-e DB_CONNECTION=sqlite \
	-e DB_DATABASE=:memory:
CI_RUN          ?= docker run $(CI_RUN_FLAGS) $(CI_IMAGE)

.PHONY: ci-image
ci-image: ## Bangun image CI (php:8.4-cli + pdo_sqlite, unzip, git, composer)
	@if docker image inspect $(CI_IMAGE) >/dev/null 2>&1; then \
		echo "✓ Image $(CI_IMAGE) sudah ada, dilewati."; \
	else \
		echo "→ Membangun image $(CI_IMAGE)"; \
		printf '%s\n' \
			'FROM php:8.4-cli' \
			'RUN apt-get update && apt-get install -y --no-install-recommends \' \
			'        git unzip libsqlite3-dev \' \
			'    && docker-php-ext-install pdo_sqlite \' \
			'    && apt-get clean && rm -rf /var/lib/apt/lists/*' \
			'COPY --from=composer:2 /usr/bin/composer /usr/bin/composer' \
			| docker build -t $(CI_IMAGE) - ; \
	fi

.PHONY: ci-bootstrap
ci-bootstrap: ci-image ## Install dependency Composer di image CI
	@echo "→ composer install (dalam $(CI_IMAGE))"
	$(CI_RUN) composer install --no-interaction --prefer-dist

.PHONY: ci
ci: ci-bootstrap ## Gerbang CI di container: install + pint + test (tanpa PHP di host)
	@# .env dibutuhkan phpdotenv agar boot tidak memunculkan warning saat test.
	@[ -f .env ] || cp .env.example .env
	@echo "→ pint --test"
	$(CI_RUN) ./vendor/bin/pint --test
	@echo "→ php artisan test"
	$(CI_RUN) php artisan test $(PHPUNIT_FLAGS)
	@echo "✓ CI lulus."

.PHONY: ci-build
ci-build: ## Build aset frontend di host (butuh Node)
	$(NPM) run build
