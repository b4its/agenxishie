<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Integration\ExternalClientManager;
use App\Models\ExternalClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * ExternalProjectsPage: Halaman manajemen & monitoring proyek eksternal.
 *
 * Mengelola pendaftaran proyek/website eksternal (mis. http://localhost:8208/ atau agen xishie),
 * generasi API key, monitoring berkala probe target (status, latency, HTTP code),
 * serta metrik inbound hits untuk konsumsi model Xishie.
 */
class ExternalProjectsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static ?string $navigationLabel = 'Connected Projects';

    protected static ?string $slug = 'connected-projects';

    protected static ?string $title = 'Hub Proyek Eksternal & Integrasi Modular';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.external-projects';

    /**
     * @var array<int, array<string, mixed>>
     */
    public array $clients = [];

    public int $totalClients = 0;

    public int $onlineClients = 0;

    public int $avgLatencyMs = 0;

    public int $totalInboundHits = 0;

    public bool $autoRefresh = false;

    public string $statusMessage = '';

    // Properti Form Registrasi
    public bool $showRegisterModal = false;

    public string $name = '';

    public string $targetUrl = 'http://localhost:8208/';

    public string $webhookUrl = '';

    public string $webhookSecret = '';

    public string $description = '';

    /**
     * @var array<int, string>
     */
    public array $selectedModels = ['*'];

    // Banner Kunci Baru
    public ?string $newApiKey = null;

    public ?string $newClientName = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $testResult = null;

    public ?int $selectedClientId = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $clientDiagnostics = null;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $clientSnippet = null;

    public function mount(): void
    {
        if (ExternalClient::count() === 0) {
            $this->seedAgenXishie();
        } else {
            $this->loadClients();
        }
    }

    /**
     * Muat ulang data klien dan rekap statistik.
     */
    public function loadClients(): void
    {
        $records = ExternalClient::query()
            ->orderByDesc('created_at')
            ->get();

        $this->totalClients = $records->count();
        $this->onlineClients = $records->where('status', 'online')->count();
        $this->totalInboundHits = (int) $records->sum('inbound_requests_count');

        $latencies = $records->whereNotNull('last_latency_ms')->pluck('last_latency_ms');
        $this->avgLatencyMs = $latencies->isNotEmpty() ? (int) round($latencies->avg()) : 0;

        $this->clients = $records->map(static function (ExternalClient $c): array {
            $state = $c->connection_state;

            return [
                'id' => $c->id,
                'connection_status' => $state->value,
                'connection_label' => $state->label(),
                'connection_short' => $state->shortLabel(),
                'connection_tone' => $state->tone(),
                'connection_description' => $state->description(),
                'target_app_name' => $c->target_app_name,
                'target_version' => $c->target_version,
                'last_handshake_at' => $c->last_handshake_at?->diffForHumans() ?? 'Belum pernah',
                'last_handshake_message' => $c->last_handshake_message,
                'name' => $c->name,
                'client_slug' => $c->client_slug,
                'api_key' => $c->api_key,
                'target_url' => $c->target_url,
                'description' => $c->description,
                'allowed_models' => $c->allowed_models ?? ['*'],
                'status' => $c->status,
                'last_status_code' => $c->last_status_code,
                'last_latency_ms' => $c->last_latency_ms,
                'target_server' => $c->target_server,
                'last_ping_at' => $c->last_ping_at?->diffForHumans() ?? 'Belum pernah',
                'last_error_message' => $c->last_error_message,
                'inbound_requests_count' => $c->inbound_requests_count,
                'last_inbound_at' => $c->last_inbound_at?->diffForHumans() ?? '—',
                'last_inbound_endpoint' => $c->last_inbound_endpoint ?? '—',
                'uptime_percentage' => $c->getUptimePercentage(),
                'recent_pings' => $c->getRecentPings(8),
                'is_active' => $c->is_active,
                'created_at' => $c->created_at?->format('d M Y H:i'),
            ];
        })->all();
    }

    public function openRegisterModal(): void
    {
        $this->name = '';
        $this->targetUrl = 'http://localhost:8208/';
        $this->webhookUrl = '';
        $this->webhookSecret = '';
        $this->description = '';
        $this->selectedModels = ['*'];
        $this->showRegisterModal = true;
    }

    public function closeRegisterModal(): void
    {
        $this->showRegisterModal = false;
    }

    /**
     * Daftarkan proyek target Agen Xishie di localhost:8208 secara instan.
     */
    public function seedAgenXishie(): void
    {
        $existing = ExternalClient::query()
            ->where('target_url', 'http://localhost:8208/')
            ->orWhere('name', 'Agen Xishie')
            ->first();

        $manager = app(ExternalClientManager::class);

        if ($existing !== null) {
            $manager->ping($existing);
            Notification::make()
                ->title('Proyek Agen Xishie Sudah Terdaftar')
                ->body("Target {$existing->target_url} telah diprobe ulang (Status: {$existing->status}).")
                ->info()
                ->send();
        } else {
            $client = $manager->register(
                name: 'Agen Xishie (Lokal)',
                targetUrl: 'http://localhost:8208/',
                allowedModels: ['*'],
                description: 'Koneksi modular lokal agen xishie untuk konsumsi model dan sinkronisasi naskah.',
            );

            $this->newApiKey = $client->api_key;
            $this->newClientName = $client->name;

            $manager->ping($client);

            Notification::make()
                ->title('Agen Xishie Berhasil Didaftarkan!')
                ->body("Target http://localhost:8208/ terhubung. Kunci API: {$client->api_key}")
                ->success()
                ->send();
        }

        $this->loadClients();
    }

    /**
     * Daftarkan proyek dari input form.
     */
    public function registerProject(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'min:3', 'max:128'],
            'targetUrl' => ['required', 'string', 'max:255'],
            'webhookUrl' => ['nullable', 'string', 'max:255'],
            'webhookSecret' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'selectedModels' => ['required', 'array', 'min:1'],
        ]);

        $manager = app(ExternalClientManager::class);

        $client = $manager->register(
            name: $this->name,
            targetUrl: $this->targetUrl,
            allowedModels: $this->selectedModels,
            description: $this->description,
            webhookUrl: $this->webhookUrl !== '' ? $this->webhookUrl : null,
            webhookSecret: $this->webhookSecret !== '' ? $this->webhookSecret : null,
        );

        $manager->ping($client);

        $this->newApiKey = $client->api_key;
        $this->newClientName = $client->name;
        $this->showRegisterModal = false;

        Notification::make()
            ->title('Proyek Berhasil Didaftarkan!')
            ->body("Klien '{$client->name}' siap mengonsumsi endpoint Xishie.")
            ->success()
            ->send();

        $this->loadClients();
    }

    /**
     * Probe konektivitas satu proyek.
     */
    public function pingClient(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $manager = app(ExternalClientManager::class);
        $result = $manager->ping($client);

        $statusUpper = strtoupper($result['status']);
        $code = $result['status_code'] !== null ? "HTTP {$result['status_code']}" : 'NO-RESPONSE';
        $latency = "{$result['latency_ms']}ms";

        $notif = Notification::make()->title("Probe {$client->name}: {$statusUpper}");

        if ($result['status'] === 'online') {
            $notif->body("{$code} · Latensi {$latency} · Server: ".($result['server'] ?? 'Aktif'))->success();
        } elseif ($result['status'] === 'timeout') {
            $notif->body("Koneksi timeout setelah 3s ({$result['error']})")->warning();
        } else {
            $notif->body('Target offline atau gagal: '.($result['error'] ?? 'Unreachable'))->danger();
        }

        $notif->send();
        $this->loadClients();
    }

    /**
     * Handshake konektivitas mendalam dengan target proyek.
     */
    public function handshakeClient(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $manager = app(ExternalClientManager::class);
        $result = $manager->handshake($client);

        $state = $result['state'];
        $title = "Handshake {$client->name}: {$state->label()}";

        $notif = Notification::make()->title($title);

        if ($state->isHealthy()) {
            $notif->body("HTTP {$result['status_code']} · Latensi {$result['latency_ms']}ms · {$result['message']}")->success();
        } elseif ($state->isReachable()) {
            $notif->body("HTTP {$result['status_code']} · Latensi {$result['latency_ms']}ms · {$result['message']}")->warning();
        } else {
            $notif->body($result['message'] ?? 'Target tidak dapat dijangkau')->danger();
        }

        $notif->send();
        $this->loadClients();
    }

    /**
     * Probe seluruh proyek terhubung.
     */
    public function pingAll(): void
    {
        $manager = app(ExternalClientManager::class);
        $results = $manager->pingAll();

        $online = count(array_filter($results, static fn (array $r): bool => $r['status'] === 'online'));
        $total = count($results);

        Notification::make()
            ->title('Probe Seluruh Proyek Selesai')
            ->body("{$online}/{$total} Proyek Merespon Aktif (ONLINE).")
            ->success()
            ->send();

        $this->loadClients();
    }

    /**
     * Regenerasi API key klien.
     */
    public function regenerateKey(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $manager = app(ExternalClientManager::class);
        $newKey = $manager->regenerateApiKey($client);

        $this->newApiKey = $newKey;
        $this->newClientName = $client->name;

        Notification::make()
            ->title('API Key Diregenerasi!')
            ->body("Kunci baru telah diterbitkan untuk {$client->name}.")
            ->success()
            ->send();

        $this->loadClients();
    }

    /**
     * Ubah status aktif/nonaktif proyek.
     */
    public function toggleActive(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $client->is_active = ! $client->is_active;
        $client->save();

        $state = $client->is_active ? 'diaktifkan' : 'dinonaktifkan';

        Notification::make()
            ->title("Proyek {$state}")
            ->body("Klien {$client->name} sekarang {$state}.")
            ->info()
            ->send();

        $this->loadClients();
    }

    /**
     * Hapus klien terdaftar.
     */
    public function deleteClient(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $clientName = $client->name;
        $client->delete();

        Notification::make()
            ->title('Klien Dihapus')
            ->body("Klien '{$clientName}' telah dihapus dari registri.")
            ->warning()
            ->send();

        $this->loadClients();
    }

    /**
     * Uji simulasi konsumsi inbound chat API secara modular.
     */
    public function testInboundChat(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $manager = app(ExternalClientManager::class);

        try {
            $this->testResult = $manager->handleChat(
                client: $client,
                prompt: 'Verifikasi pipeline integrasi modular untuk agen xishie.',
                model: 'AcademicScribeAgent',
            );

            Notification::make()
                ->title('Simulasi Inbound Berhasil!')
                ->body('Model AcademicScribeAgent merespon dan hit counter tercatat.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()
                ->title('Uji Inbound Gagal')
                ->body($exception->getMessage())
                ->danger()
                ->send();
        }

        $this->loadClients();
    }

    /**
     * Uji pengiriman webhook callback ke klien.
     */
    public function testWebhook(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $manager = app(ExternalClientManager::class);
        $result = $manager->sendWebhook(
            client: $client,
            event: 'ping.test',
            payload: [
                'message' => "Uji coba webhook dari Xishie ke {$client->name}.",
                'triggered_at' => now()->toIso8601String(),
            ],
        );

        if ($result['status'] === 'skipped') {
            Notification::make()->title('Webhook URL Belum Dikonfigurasi')->warning()->send();
        } elseif ($result['status'] === 'delivered') {
            Notification::make()
                ->title('Webhook Terkirim!')
                ->body("HTTP {$result['status_code']} · Latensi {$result['latency_ms']}ms (Signature SHA256 verified)")
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Webhook Gagal Terkirim')
                ->body((string) ($result['error'] ?? 'Target tidak dapat dijangkau'))
                ->danger()
                ->send();
        }

        $this->loadClients();
    }

    public function dismissNewKey(): void
    {
        $this->newApiKey = null;
        $this->newClientName = null;
    }

    public function dismissTestResult(): void
    {
        $this->testResult = null;
    }

    public function showDiagnostics(int $clientId): void
    {
        $client = ExternalClient::with(['pingLogs' => fn ($q) => $q->latest('probed_at')->limit(15)])->find($clientId);
        if ($client === null) {
            return;
        }

        $this->selectedClientId = $clientId;
        $this->clientDiagnostics = [
            'id' => $client->id,
            'name' => $client->name,
            'target_url' => $client->target_url,
            'status' => $client->status,
            'uptime' => $client->getUptimePercentage(),
            'logs' => $client->pingLogs->map(fn ($l) => [
                'status' => $l->status,
                'status_code' => $l->status_code,
                'latency_ms' => $l->latency_ms,
                'server' => $l->target_server,
                'error' => $l->error_message,
                'time' => $l->probed_at?->format('H:i:s d/m') ?? '—',
            ])->all(),
        ];
    }

    public function closeDiagnostics(): void
    {
        $this->clientDiagnostics = null;
    }

    public function showSnippet(int $clientId): void
    {
        $client = ExternalClient::find($clientId);
        if ($client === null) {
            return;
        }

        $this->selectedClientId = $clientId;
        $this->clientSnippet = [
            'client_name' => $client->name,
            'api_key' => $client->api_key,
            'base_url' => url('/api/v1/external'),
        ];
    }

    public function closeSnippet(): void
    {
        $this->clientSnippet = null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $manager = app(ExternalClientManager::class);

        return [
            'availableModels' => $manager->getAvailableModels(),
        ];
    }
}
