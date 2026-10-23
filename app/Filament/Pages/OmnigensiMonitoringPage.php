<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Assistant\OmnigensiChatClient;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

/**
 * OmnigensiMonitoringPage: papan pemantau integrasi dengan platform Omnigensi
 * (Connected Projects & Integration Hub).
 *
 * Menampilkan status koneksi, latensi, header server target, jumlah inbound
 * handshake, dan izin model tiap proyek terhubung; serta aksi ping/ping-all
 * dan handshake API key untuk proyek ini.
 */
class OmnigensiMonitoringPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-signal';

    protected static ?string $navigationLabel = 'Omnigensi Monitoring';

    protected static ?string $title = 'Omnigensi Integration Monitor';

    protected static ?int $navigationSort = 9;

    protected string $view = 'filament.pages.omnigensi-monitoring';

    public bool $backendOnline = true;

    public string $status = '';

    /**
     * Ringkasan handshake API key proyek ini.
     *
     * @var array<string, mixed>|null
     */
    public ?array $handshake = null;

    /**
     * Daftar proyek terhubung + status (dari endpoint operator).
     *
     * @var array<int, array<string, mixed>>
     */
    public array $clients = [];

    public function mount(): void
    {
        $this->refresh();
    }

    /**
     * Muat ulang status koneksi + daftar proyek terhubung.
     */
    public function refresh(): void
    {
        $client = app(OmnigensiChatClient::class);

        if (! $client->isAvailable()) {
            $this->backendOnline = false;
            $this->status = 'Backend Omnigensi tidak terjangkau.';
            $this->clients = [];
            $this->handshake = null;

            return;
        }

        $this->backendOnline = true;

        try {
            $this->clients = $client->integrationClients();
            $this->status = count($this->clients).' proyek terhubung.';
        } catch (\Throwable $exception) {
            $this->clients = [];
            $this->status = 'Gagal memuat proyek terhubung: '.$exception->getMessage();
        }

        // Handshake API key proyek ini (jika dikonfigurasi).
        if ($client->hasIntegrationKey()) {
            try {
                $this->handshake = $client->handshake();
            } catch (\Throwable $exception) {
                $this->handshake = ['status' => 'error', 'error' => $exception->getMessage()];
            }
        } else {
            $this->handshake = null;
        }
    }

    /**
     * Probe satu proyek terhubung.
     */
    public function pingClient(string $clientId): void
    {
        try {
            $result = app(OmnigensiChatClient::class)->pingClient($clientId);

            Notification::make()
                ->title('Ping '.($result['name'] ?? 'proyek'))
                ->body(($result['status'] ?? '?').' · HTTP '.($result['status_code'] ?? '-').' · '.($result['latency_ms'] ?? '-').'ms')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Ping gagal')->body($exception->getMessage())->danger()->send();
        }

        $this->refresh();
    }

    /**
     * Probe seluruh proyek terhubung sekaligus.
     */
    public function pingAll(): void
    {
        try {
            $results = app(OmnigensiChatClient::class)->pingAll();
            $online = count(array_filter($results, static fn (array $r): bool => ($r['status'] ?? '') === 'online'));

            Notification::make()
                ->title('Ping seluruh proyek selesai')
                ->body($online.'/'.count($results).' proyek ONLINE.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Ping-all gagal')->body($exception->getMessage())->danger()->send();
        }

        $this->refresh();
    }

    /**
     * Daftarkan/pulihkan target default agen xishie.
     */
    public function seedXishie(): void
    {
        try {
            $client = app(OmnigensiChatClient::class)->seedXishie();
            $key = (string) ($client['api_key'] ?? '');

            Notification::make()
                ->title('Konfigurasi xishie dipulihkan')
                ->body($key !== '' ? 'API Key: '.substr($key, 0, 18).'… (salin dari panel Omnigensi)' : 'Target agen xishie terdaftar.')
                ->success()
                ->send();
        } catch (\Throwable $exception) {
            Notification::make()->title('Gagal memulihkan konfigurasi')->body($exception->getMessage())->danger()->send();
        }

        $this->refresh();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'baseUrl' => (string) config('omnigensi.base_url'),
            'hasApiKey' => app(OmnigensiChatClient::class)->hasIntegrationKey(),
        ];
    }
}
