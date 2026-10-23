<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Integration\ExternalClientManager;
use App\Models\ExternalClient;
use Illuminate\Database\Seeder;

/**
 * Seeder untuk menghubungkan target default agen xishie (http://localhost:8208/)
 * dan website portal integrasi modular.
 */
class ExternalClientSeeder extends Seeder
{
    public function run(): void
    {
        $manager = app(ExternalClientManager::class);

        $client = ExternalClient::query()->firstOrCreate(
            ['target_url' => 'http://localhost:8208/'],
            [
                'name' => 'Agen Xishie (Lokal)',
                'client_slug' => 'agen-xishie-lokal',
                'api_key' => 'xsk_agen_xishie_master_demo_key',
                'description' => 'Target integrasi lokal agen xishie untuk sinkronisasi naskah dan orkestrasi model.',
                'allowed_models' => ['*'],
                'status' => 'unknown',
                'is_active' => true,
            ],
        );

        // Jalankan probe awal
        $manager->ping($client);
    }
}
