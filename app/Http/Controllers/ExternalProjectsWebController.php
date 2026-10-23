<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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
        $onlineClients = $records->where('status', 'online')->count();
        $totalInboundHits = (int) $records->sum('inbound_requests_count');

        $latencies = $records->whereNotNull('last_latency_ms')->pluck('last_latency_ms');
        $avgLatencyMs = $latencies->isNotEmpty() ? (int) round($latencies->avg()) : 0;

        $clients = $records->map(function (ExternalClient $c): array {
            return [
                'id' => $c->id,
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

        return view('connected-projects', [
            'clients' => $clients,
            'totalClients' => $totalClients,
            'onlineClients' => $onlineClients,
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
            'description' => ['nullable', 'string', 'max:500'],
            'selected_models' => ['required', 'array', 'min:1'],
        ]);

        $client = $this->manager->register(
            name: $validated['name'],
            targetUrl: $validated['target_url'],
            allowedModels: $validated['selected_models'],
            description: $validated['description'] ?? null,
        );

        // Jalankan probe awal
        $this->manager->ping($client);

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
