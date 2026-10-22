{{-- Omnigensi Integration Monitor — status koneksi & proyek terhubung --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">

        {{-- Status backend + handshake --}}
        <div class="rb-window" style="margin-bottom:1rem;">
            <div class="rb-window__titlebar">
                <span>[&#9679;] KONEKSI OMNIGENSI — {{ $baseUrl }}</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
            </div>
            <div class="rb-window__body">
                @if (! $backendOnline)
                    <div class="rb-notice rb-notice--alert" style="margin-bottom:1rem;">
                        <div class="rb-notice__title">[&#9888;] BACKEND OMNIGENSI TIDAK TERJANGKAU</div>
                        <p class="rb-mono rb-soft" style="font-size:.72rem; margin:.35rem 0;">
                            Tidak dapat menghubungi <strong>{{ $baseUrl }}</strong>. Jalankan stack omnigensi
                            dan pastikan <code>OMNIGENSI_BASE_URL</code> dapat dijangkau dari container.
                        </p>
                    </div>
                @endif

                <div class="rb-kpi-row" style="margin-bottom:1rem;">
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">STATUS BACKEND</div>
                        <div class="rb-kpi-card__value {{ $backendOnline ? 'rb-kpi-card__value--pass' : 'rb-kpi-card__value--alert' }}">
                            {{ $backendOnline ? 'ONLINE' : 'OFFLINE' }}
                        </div>
                        <div class="rb-kpi-card__unit">{{ count($clients) }} proyek terhubung</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">API KEY PROYEK</div>
                        <div class="rb-kpi-card__value {{ $hasApiKey ? 'rb-kpi-card__value--pass' : 'rb-kpi-card__value--alert' }}">
                            {{ $hasApiKey ? 'TERPASANG' : 'BELUM' }}
                        </div>
                        <div class="rb-kpi-card__unit">{{ $handshake['status'] ?? '—' }}</div>
                    </div>
                    @if ($handshake !== null && isset($handshake['available_models']))
                        <div class="rb-kpi-card">
                            <div class="rb-kpi-card__label">MODEL TERSEDIA</div>
                            <div class="rb-kpi-card__value rb-kpi-card__value--pass">{{ count($handshake['available_models']) }}</div>
                            <div class="rb-kpi-card__unit">via API Key</div>
                        </div>
                    @endif
                </div>

                @if ($handshake !== null && isset($handshake['client_name']))
                    <div class="rb-mono rb-soft" style="font-size:.7rem; margin-bottom:.5rem;">
                        Client: <strong>{{ $handshake['client_name'] }}</strong>
                        · Versi Omnigensi: {{ $handshake['omnigensi_version'] ?? '—' }}
                        · Izin model: {{ implode(', ', (array) ($handshake['allowed_models'] ?? ['*'])) }}
                    </div>
                @endif

                <div style="display:flex; gap:.5rem; flex-wrap:wrap;">
                    <button type="button" class="rb-button rb-button--primary" wire:click="refresh" wire:loading.attr="disabled">
                        &#8635; Muat Ulang
                    </button>
                    <button type="button" class="rb-button" wire:click="pingAll" wire:loading.attr="disabled">
                        &#128225; Ping All
                    </button>
                    <button type="button" class="rb-button" wire:click="seedXishie" wire:loading.attr="disabled">
                        &#127793; Seed Xishie
                    </button>
                </div>
                @if ($status !== '')
                    <div class="rb-mono rb-muted" style="font-size:.68rem; margin-top:.5rem;">{{ $status }}</div>
                @endif
            </div>
        </div>

        {{-- Tabel proyek terhubung --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>CONNECTED PROJECTS</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
            </div>
            <div class="rb-window__body">
                @if (empty($clients))
                    <p class="rb-mono rb-muted" style="font-size:.72rem;">Belum ada proyek terhubung (atau backend tidak terjangkau).</p>
                @else
                    <div style="overflow:auto;">
                        <table class="rb-table">
                            <thead>
                                <tr>
                                    <th>PROYEK</th>
                                    <th>TARGET</th>
                                    <th style="text-align:center;">STATUS</th>
                                    <th style="text-align:center;">HTTP</th>
                                    <th style="text-align:center;">LATENSI</th>
                                    <th style="text-align:center;">INBOUND</th>
                                    <th>SERVER</th>
                                    <th style="text-align:center;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clients as $client)
                                    @php
                                        $st = (string) ($client['status'] ?? 'unknown');
                                        $badge = match ($st) {
                                            'online' => 'rb-badge--pass',
                                            'degraded' => 'rb-badge--hitl',
                                            'offline' => 'rb-badge--alert',
                                            default => 'rb-badge--manual',
                                        };
                                    @endphp
                                    <tr>
                                        <td>
                                            <strong>{{ $client['name'] ?? '—' }}</strong>
                                            @if (! empty($client['is_active'])) @else <span class="rb-muted">(nonaktif)</span> @endif
                                        </td>
                                        <td class="rb-mono" style="font-size:.6rem;">{{ $client['target_url'] ?? '—' }}</td>
                                        <td style="text-align:center;"><span class="rb-badge {{ $badge }}">{{ strtoupper($st) }}</span></td>
                                        <td style="text-align:center;">{{ $client['last_status_code'] ?? '—' }}</td>
                                        <td style="text-align:center;">{{ isset($client['last_latency_ms']) ? $client['last_latency_ms'].'ms' : '—' }}</td>
                                        <td style="text-align:center;">{{ $client['inbound_requests_count'] ?? 0 }}</td>
                                        <td class="rb-mono" style="font-size:.58rem;">{{ \Illuminate\Support\Str::limit((string) ($client['target_server'] ?? '—'), 28) }}</td>
                                        <td style="text-align:center;">
                                            <button type="button" class="rb-button" style="padding:.12rem .45rem; font-size:.58rem;" wire:click="pingClient('{{ $client['id'] }}')" wire:loading.attr="disabled">PING</button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
