<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Integration\ExternalClientManager;
use App\Models\ExternalClient;
use Illuminate\Console\Command;

class MonitorExternalProjectsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'xishie:ping-clients {--client= : ID atau slug spesifik klien}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Jalankan probe berkala ke seluruh proyek target eksternal (misal http://localhost:8208/)';

    public function handle(ExternalClientManager $manager): int
    {
        $specific = $this->option('client');

        $query = ExternalClient::query()->where('is_active', true);
        if ($specific !== null) {
            $query->where(fn ($q) => $q->where('id', $specific)->orWhere('client_slug', $specific));
        }

        $clients = $query->get();

        if ($clients->isEmpty()) {
            $this->warn('Tidak ada klien eksternal aktif yang terdaftar.');

            return self::SUCCESS;
        }

        $this->info("Memulai probe koneksi ke {$clients->count()} proyek target...");

        $rows = [];
        foreach ($clients as $client) {
            $result = $manager->ping($client);

            $rows[] = [
                'ID' => $client->id,
                'Proyek' => $client->name,
                'Target URL' => $client->target_url,
                'Status' => strtoupper($result['status']),
                'HTTP' => $result['status_code'] ?? '—',
                'Latensi' => ($result['latency_ms'] ?? 0).'ms',
                'Server' => $result['server'] ?? '—',
                'Uptime' => $client->getUptimePercentage().'%',
            ];
        }

        $this->table(['ID', 'Proyek', 'Target URL', 'Status', 'HTTP', 'Latensi', 'Server', 'Uptime'], $rows);

        $onlineCount = count(array_filter($rows, static fn ($r): bool => $r['Status'] === 'ONLINE'));
        $this->info("Probe selesai: {$onlineCount}/{$clients->count()} proyek merespon aktif.");

        return self::SUCCESS;
    }
}
