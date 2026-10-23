<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Integration\Enums\ConnectionStatus;
use App\Domain\Integration\ExternalClientManager;
use App\Models\ExternalClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Controller Web Publik untuk Halaman Proyek Terhubung & Integrasi Modular.
 * Dapat diakses langsung di http://localhost:8208/connected-projects
 * tanpa kewajiban login panel, memungkinkan registrasi dan monitoring seketika.
 */
class ExternalProjectsWebController extends Controller
{
    public function __construct(
        private readonly ExternalClientManager $manager,
    ) {}

    /**
     * Tampilkan halaman monitoring dan registrasi proyek terhubung.
     */
    public function index(): View
    {
        // Pastikan target default agen xishie terdaftar
        if (ExternalClient::count() === 0) {
            $default = $this->manager->register(
                name: 'Agen Xishie (Lokal)',
                targetUrl: 'http://localhost:8208/',
                allowedModels: ['*'],
                description: 'Target integrasi lokal agen xishie untuk sinkronisasi naskah dan orkestrasi model.',
            );
            $this->manager->ping($default);
        }

        $records = ExternalClient::query()
            ->with(['pingLogs' => fn ($q) => $q->latest('probed_at')->limit(8)])
            ->orderByDesc('created_at')
            ->get();

        $totalClients = $records->count();
        $connectedClients = $records->filter(fn (ExternalClient $c): bool => $c->connection_state === ConnectionStatus::CONNECTED)->count();
        $unreachableClients = $records->filter(fn (ExternalClient $c): bool => $c->connection_state === ConnectionStatus::UNREACHABLE)->count();
        $onlineClients = $records->where('status', 'online')->count();
        $totalInboundHits = (int) $records->sum('inbound_requests_count');

        $latencies = $records->whereNotNull('last_latency_ms')->pluck('last_latency_ms');
        $avgLatencyMs = $latencies->isNotEmpty() ? (int) round($latencies->avg()) : 0;

        $clients = $records->map(function (ExternalClient $c): array {
            $state = $c->connection_state;

            return [
                'id' => $c->id,
                'name' => $c->name,
                'client_slug' => $c->client_slug,
                'api_key' => $c->api_key,
                'target_url' => $c->target_url,
                'handshake_endpoint' => $this->manager->resolveHandshakeEndpoint($c),
                'description' => $c->description,
                'allowed_models' => $c->allowed_models ?? ['*'],
                'status' => $c->status,
                'connection_status' => $state->value,
                'connection_label' => $state->label(),
                'connection_short' => $state->shortLabel(),
                'connection_tone' => $state->tone(),
                'connection_description' => $state->description(),
                'target_app_name' => $c->target_app_name,
                'target_framework' => $c->target_framework,
                'target_version' => $c->target_version,
                'last_handshake_at' => $c->last_handshake_at?->diffForHumans() ?? 'Belum pernah',
                'last_handshake_message' => $c->last_handshake_message,
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

        return view('connected-projects', [
            'clients' => $clients,
            'totalClients' => $totalClients,
            'onlineClients' => $onlineClients,
            'connectedClients' => $connectedClients,
            'unreachableClients' => $unreachableClients,
            'avgLatencyMs' => $avgLatencyMs,
            'totalInboundHits' => $totalInboundHits,
            'availableModels' => $this->manager->getAvailableModels(),
        ]);
    }

    /**
     * Daftarkan proyek target eksternal baru dari formulir web.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:3', 'max:128'],
            'target_url' => ['required', 'string', 'max:255'],
            'webhook_url' => ['nullable', 'string', 'max:255'],
            'webhook_secret' => ['nullable', 'string', 'max:64'],
            'description' => ['nullable', 'string', 'max:500'],
            'selected_models' => ['required', 'array', 'min:1'],
        ]);

        $client = $this->manager->register(
            name: $validated['name'],
            targetUrl: $validated['target_url'],
            allowedModels: $validated['selected_models'],
            description: $validated['description'] ?? null,
            webhookUrl: $validated['webhook_url'] ?? null,
            webhookSecret: $validated['webhook_secret'] ?? null,
        );

        // Jalankan handshake end-to-end untuk verifikasi awal
        $this->manager->handshake($client);

        return redirect()->route('connected-projects.index')
            ->with('status_message', "Proyek '{$client->name}' berhasil didaftarkan!")
            ->with('new_api_key', $client->api_key)
            ->with('new_client_name', $client->name);
    }

    /**
     * Jalankan probe status koneksi (ping) ke proyek target.
     */
    public function ping(ExternalClient $client): RedirectResponse
    {
        $result = $this->manager->ping($client);
        $statusUpper = strtoupper($result['status']);
        $code = $result['status_code'] !== null ? "HTTP {$result['status_code']}" : 'NO-RESPONSE';
        $latency = "{$result['latency_ms']}ms";

        $msg = "Probe '{$client->name}': {$statusUpper} ({$code} &middot; {$latency})";

        return redirect()->route('connected-projects.index')->with('status_message', $msg);
    }

    /**
     * Jalankan handshake konektivitas mendalam dengan target proyek.
     */
    public function handshake(ExternalClient $client): RedirectResponse
    {
        $result = $this->manager->handshake($client);
        $stateLabel = $result['state']->label();
        $code = $result['status_code'] !== null ? "HTTP {$result['status_code']}" : 'NO-RESPONSE';
        $latency = "{$result['latency_ms']}ms";

        $msg = "Handshake '{$client->name}': {$stateLabel} ({$code} &middot; {$latency}) — {$result['message']}";

        return redirect()->route('connected-projects.index')
            ->with('status_message', $msg)
            ->with('status_tone', $result['state']->tone());
    }

    /**
     * Handshake seluruh proyek aktif sekaligus.
     */
    public function handshakeAll(): RedirectResponse
    {
        $results = $this->manager->handshakeAll();
        $connected = count(array_filter($results, static fn (array $r): bool => $r['state'] === ConnectionStatus::CONNECTED));
        $total = count($results);

        return redirect()->route('connected-projects.index')
            ->with('status_message', "Handshake seluruh proyek selesai: {$connected}/{$total} TERHUBUNG.");
    }

    /**
     * Probe seluruh proyek target sekaligus.
     */
    public function pingAll(): RedirectResponse
    {
        $results = $this->manager->pingAll();
        $online = count(array_filter($results, static fn (array $r): bool => $r['status'] === 'online'));
        $total = count($results);

        return redirect()->route('connected-projects.index')
            ->with('status_message', "Probe seluruh proyek selesai: {$online}/{$total} ONLINE.");
    }

    /**
     * Regenerasi kunci API proyek.
     */
    public function regenerateKey(ExternalClient $client): RedirectResponse
    {
        $newKey = $this->manager->regenerateApiKey($client);

        return redirect()->route('connected-projects.index')
            ->with('status_message', "API Key baru diterbitkan untuk '{$client->name}'.")
            ->with('new_api_key', $newKey)
            ->with('new_client_name', $client->name);
    }

    /**
     * Ubah status aktif/nonaktif proyek.
     */
    public function toggle(ExternalClient $client): RedirectResponse
    {
        $client->is_active = ! $client->is_active;
        $client->save();

        $state = $client->is_active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('connected-projects.index')
            ->with('status_message', "Proyek '{$client->name}' telah {$state}.");
    }

    /**
     * Jalankan uji eksekusi chat inbound secara simulasi.
     */
    public function testInbound(ExternalClient $client): RedirectResponse
    {
        try {
            $result = $this->manager->handleChat(
                client: $client,
                prompt: 'Verifikasi pipeline integrasi modular untuk agen xishie.',
                model: 'AcademicScribeAgent',
            );

            return redirect()->route('connected-projects.index')
                ->with('status_message', "Simulasi inbound '{$client->name}' berhasil dieksekusi!")
                ->with('test_result', $result);
        } catch (\Throwable $e) {
            return redirect()->route('connected-projects.index')
                ->with('status_message', 'Simulasi gagal: '.$e->getMessage());
        }
    }

    /**
     * Jalankan uji pengiriman webhook ke proyek target.
     */
    public function testWebhook(ExternalClient $client): RedirectResponse
    {
        $result = $this->manager->sendWebhook(
            client: $client,
            event: 'ping.test',
            payload: [
                'message' => "Uji coba pengiriman webhook dari Xishie Hub ke {$client->name}.",
                'triggered_at' => now()->toIso8601String(),
            ],
        );

        $status = strtoupper($result['status']);
        $msg = "Uji Webhook '{$client->name}': {$status}";
        if ($result['status'] === 'skipped') {
            $msg .= ' (URL Webhook belum diisi)';
        } elseif ($result['status'] === 'delivered') {
            $msg .= " (HTTP {$result['status_code']} · {$result['latency_ms']}ms)";
        } else {
            $msg .= ' (Gagal: '.($result['error'] ?? 'Unreachable').')';
        }

        return redirect()->route('connected-projects.index')->with('status_message', $msg);
    }

    /**
     * Hapus pendaftaran proyek.
     */
    public function destroy(ExternalClient $client): RedirectResponse
    {
        $name = $client->name;
        $client->delete();

        return redirect()->route('connected-projects.index')
            ->with('status_message', "Proyek '{$name}' telah dihapus dari registri.");
    }
}
