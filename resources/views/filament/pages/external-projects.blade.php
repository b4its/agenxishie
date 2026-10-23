{{-- Hub Proyek Eksternal & Integrasi Modular --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;" @if ($autoRefresh) wire:poll.10s="loadClients" @endif>

        {{-- Window Utama: Kontrol & Monitoring Hub --}}
        <div class="rb-window" style="margin-bottom:1.25rem;">
            <div class="rb-window__titlebar">
                <span>[&#9679;] HUB PROYEK EKSTERNAL &amp; INTEGRASI MODULAR</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <p class="rb-mono rb-soft" style="font-size:.73rem; margin-bottom:1rem; line-height:1.5;">
                    Pusat registrasi dan pemantau konektivitas modular untuk aplikasi Laravel lain (misal <strong>agen xishie</strong> di <code>http://localhost:8208/</code>)
                    atau website eksternal yang ingin mengonsumsi endpoint, pipeline, dan model agen AI Xishie.
                </p>

                {{-- Row Indikator KPI --}}
                <div class="rb-kpi-row" style="margin-bottom:1rem;">
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">TOTAL PROYEK TERDAFTAR</div>
                        <div class="rb-kpi-card__value">{{ $totalClients }}</div>
                        <div class="rb-kpi-card__unit">Klien Modular</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">STATUS KONEKSI</div>
                        <div class="rb-kpi-card__value {{ $onlineClients > 0 ? 'rb-kpi-card__value--pass' : ($totalClients > 0 ? 'rb-kpi-card__value--alert' : '') }}">
                            {{ $onlineClients }} / {{ $totalClients }}
                        </div>
                        <div class="rb-kpi-card__unit">Proyek ONLINE</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">RATA-RATA LATENSI</div>
                        <div class="rb-kpi-card__value {{ $avgLatencyMs < 200 && $avgLatencyMs > 0 ? 'rb-kpi-card__value--pass' : '' }}">
                            {{ $avgLatencyMs > 0 ? $avgLatencyMs.'ms' : '—' }}
                        </div>
                        <div class="rb-kpi-card__unit">Waktu Tanggap Probe</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">TOTAL INBOUND HITS</div>
                        <div class="rb-kpi-card__value rb-kpi-card__value--pass">{{ $totalInboundHits }}</div>
                        <div class="rb-kpi-card__unit">Permintaan Masuk API</div>
                    </div>
                </div>

                {{-- Bar Tombol Aksi Cepat --}}
                <div style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:center;">
                    <button type="button" class="rb-button rb-button--primary" wire:click="openRegisterModal" wire:loading.attr="disabled">
                        &#43; Daftarkan Proyek Target
                    </button>
                    <button type="button" class="rb-button" wire:click="seedAgenXishie" wire:loading.attr="disabled" title="Daftarkan agen xishie di http://localhost:8208/">
                        &#127793; Hubungkan Agen Xishie (:8208)
                    </button>
                    <button type="button" class="rb-button" wire:click="pingAll" wire:loading.attr="disabled">
                        &#128225; Probe Semua Proyek
                    </button>
                    <button type="button" class="rb-button" wire:click="loadClients" wire:loading.attr="disabled">
                        &#8635; Segarkan Data
                    </button>
                    <label class="rb-mono" style="font-size:.68rem; margin-left:auto; display:flex; align-items:center; gap:.4rem; cursor:pointer;">
                        <input type="checkbox" wire:model.live="autoRefresh" /> Auto-Refresh (10s)
                    </label>
                </div>
            </div>
        </div>

        {{-- Banner Notifikasi API Key Baru --}}
        @if ($newApiKey)
            <div class="rb-notice rb-notice--approve" style="margin-bottom:1.25rem;">
                <div class="rb-notice__title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>[&#10003;] KUNCI API AKTIF DITERBITKAN: {{ $newClientName }}</span>
                    <button type="button" wire:click="dismissNewKey" class="rb-button" style="padding:.1rem .4rem; font-size:.65rem;">&times; Tutup</button>
                </div>
                <p class="rb-mono" style="font-size:.72rem; margin:.4rem 0;">
                    Simpan API Key ini di file <code>.env</code> proyek target (misal <code>XISHIE_API_KEY={{ $newApiKey }}</code>).
                    Kunci ini digunakan pada header <code>X-API-Key</code> atau <code>Authorization: Bearer &lt;KEY&gt;</code> untuk mengakses model &amp; endpoint Xishie.
                </p>
                <div style="display:flex; gap:.5rem; align-items:center; margin-top:.4rem;">
                    <input type="text" readonly value="{{ $newApiKey }}" class="rb-input" style="font-family:var(--rb-font-mono); font-weight:700; color:var(--rb-primary);" id="copyApiKeyInput" />
                    <button type="button" class="rb-button rb-button--primary" onclick="navigator.clipboard.writeText(document.getElementById('copyApiKeyInput').value); alert('API Key disalin ke clipboard!');">
                        Salin Kunci
                    </button>
                </div>
            </div>
        @endif

        {{-- Box Hasil Uji Simulasi Inbound --}}
        @if ($testResult)
            <div class="rb-notice rb-notice--info" style="margin-bottom:1.25rem;">
                <div class="rb-notice__title" style="display:flex; justify-content:space-between; align-items:center;">
                    <span>[&#9889;] HASIL SIMULASI INBOUND DARI: {{ $testResult['client'] ?? 'Klien' }}</span>
                    <button type="button" wire:click="dismissTestResult" class="rb-button" style="padding:.1rem .4rem; font-size:.65rem;">&times; Tutup</button>
                </div>
                <div class="rb-mono" style="font-size:.7rem; margin-top:.4rem;">
                    <div>Model: <strong>{{ $testResult['model'] ?? 'AcademicScribeAgent' }}</strong> &middot; Eksekusi: {{ $testResult['executed_at'] ?? 'now' }}</div>
                    <div style="background:var(--rb-surface-inset); padding:.6rem; border:var(--rb-border-soft); margin-top:.4rem; border-radius:4px; white-space:pre-wrap;">{{ $testResult['response'] ?? 'Tidak ada data respon' }}</div>
                </div>
            </div>
        @endif

        {{-- Drawer / Modal Registrasi Proyek --}}
        @if ($showRegisterModal)
            <div class="rb-window" style="margin-bottom:1.25rem; border:2px solid var(--rb-primary);">
                <div class="rb-window__titlebar" style="background:var(--rb-primary);">
                    <span>[&#43;] FORMULIR REGISTRASI PROYEK EKSTERNAL / TARGET BARU</span>
                    <button type="button" wire:click="closeRegisterModal" style="background:none; border:none; color:#fff; cursor:pointer; font-weight:bold;">&times;</button>
                </div>
                <div class="rb-window__body">
                    <form wire:submit.prevent="registerProject">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1rem;">
                            <div>
                                <label class="rb-mono" style="font-size:.7rem; font-weight:bold; display:block; margin-bottom:.3rem;">
                                    NAMA PROYEK / WEBSITE *
                                </label>
                                <input type="text" wire:model="name" class="rb-input" placeholder="Misal: Agen Xishie Prod, Portal Jurnal Kampus" required />
                                @error('name') <span class="rb-mono" style="color:var(--rb-status-alert); font-size:.65rem;">{{ $message }}</span> @enderror
                            </div>
                            <div>
                                <label class="rb-mono" style="font-size:.7rem; font-weight:bold; display:block; margin-bottom:.3rem;">
                                    URL TARGET MONITORING &amp; PROBE *
                                </label>
                                <input type="text" wire:model="targetUrl" class="rb-input" placeholder="http://localhost:8208/ atau https://target.domain" required />
                                @error('targetUrl') <span class="rb-mono" style="color:var(--rb-status-alert); font-size:.65rem;">{{ $message }}</span> @enderror
                            </div>
                        </div>

                        <div style="margin-bottom:1rem;">
                            <label class="rb-mono" style="font-size:.7rem; font-weight:bold; display:block; margin-bottom:.3rem;">
                                DESKRIPSI / TUJUAN INTEGRASI
                            </label>
                            <input type="text" wire:model="description" class="rb-input" placeholder="Keterangan singkat peran atau arsitektur proyek pemanggil..." />
                        </div>

                        <div style="margin-bottom:1rem;">
                            <label class="rb-mono" style="font-size:.7rem; font-weight:bold; display:block; margin-bottom:.3rem;">
                                IZIN MODEL &amp; PIPELINE YANG DIBUKA UNTUK PROYEK INI
                            </label>
                            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:.5rem; background:var(--rb-surface-inset); padding:.6rem; border:var(--rb-border-soft); border-radius:4px;">
                                <label class="rb-mono" style="font-size:.68rem; display:flex; align-items:center; gap:.4rem; cursor:pointer;">
                                    <input type="checkbox" wire:model="selectedModels" value="*" />
                                    <strong>* (Semua Model &amp; Endpoint)</strong>
                                </label>
                                @foreach ($availableModels as $modelKey => $meta)
                                    <label class="rb-mono" style="font-size:.68rem; display:flex; align-items:center; gap:.4rem; cursor:pointer;">
                                        <input type="checkbox" wire:model="selectedModels" value="{{ $modelKey }}" />
                                        <span>{{ $meta['name'] }}</span>
                                    </label>
                                @endforeach
                            </div>
                            @error('selectedModels') <span class="rb-mono" style="color:var(--rb-status-alert); font-size:.65rem;">{{ $message }}</span> @enderror
                        </div>

                        <div style="display:flex; gap:.6rem; justify-content:flex-end;">
                            <button type="button" class="rb-button" wire:click="closeRegisterModal">Batal</button>
                            <button type="submit" class="rb-button rb-button--primary">Daftarkan &amp; Terbitkan Kunci API</button>
                        </div>
                    </form>
                </div>
            </div>
        @endif

        {{-- Window Tabel Proyek Terhubung --}}
        <div class="rb-window" style="margin-bottom:1.25rem;">
            <div class="rb-window__titlebar">
                <span>[&#9679;] STATUS PROYEK TERHUBUNG &amp; HASIL PROBE KONEKTIVITAS</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body" style="padding:0;">
                @if (empty($clients))
                    <div style="padding:2rem; text-align:center;">
                        <p class="rb-mono rb-muted" style="font-size:.78rem; margin-bottom:1rem;">
                            Belum ada proyek atau website eksternal yang terdaftar dalam registry integrasi.
                        </p>
                        <button type="button" class="rb-button rb-button--primary" wire:click="seedAgenXishie">
                            &#127793; Daftarkan Proyek Agen Xishie (:8208) Sekarang
                        </button>
                    </div>
                @else
                    <div style="overflow-x:auto;">
                        <table class="rb-table" style="width:100%; border-collapse:collapse;">
                            <thead>
                                <tr>
                                    <th>PROYEK</th>
                                    <th>URL TARGET</th>
                                    <th style="text-align:center;">STATUS</th>
                                    <th style="text-align:center;">HTTP</th>
                                    <th style="text-align:center;">LATENSI</th>
                                    <th>SERVER</th>
                                    <th style="text-align:center;">PROBE TERAKHIR</th>
                                    <th style="text-align:center;">INBOUND HITS</th>
                                    <th>IZIN MODEL</th>
                                    <th style="text-align:center;">AKSI</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($clients as $client)
                                    @php
                                        $st = (string) ($client['status'] ?? 'unknown');
                                        $badgeClass = match ($st) {
                                            'online' => 'rb-badge--pass',
                                            'degraded' => 'rb-badge--hitl',
                                            'timeout' => 'rb-badge--hitl',
                                            'offline' => 'rb-badge--alert',
                                            default => 'rb-badge--manual',
                                        };
                                    @endphp
                                    <tr style="{{ ! $client['is_active'] ? 'opacity:0.55;' : '' }}">
                                        <td>
                                            <div style="display:flex; align-items:center; gap:.4rem;">
                                                <strong>{{ $client['name'] }}</strong>
                                                @if (! $client['is_active'])
                                                    <span class="rb-badge rb-badge--manual" style="font-size:.55rem;">NONAKTIF</span>
                                                @endif
                                            </div>
                                            <div class="rb-mono rb-muted" style="font-size:.62rem;">
                                                Slug: <code>{{ $client['client_slug'] }}</code> &middot; {{ $client['created_at'] }}
                                            </div>
                                        </td>
                                        <td class="rb-mono" style="font-size:.68rem;">
                                            <a href="{{ $client['target_url'] }}" target="_blank" rel="noopener noreferrer" style="color:var(--rb-primary); text-decoration:underline;">
                                                {{ $client['target_url'] }}
                                            </a>
                                            @if ($client['last_error_message'])
                                                <div class="rb-mono" style="color:var(--rb-status-alert); font-size:.58rem; margin-top:.15rem;" title="{{ $client['last_error_message'] }}">
                                                    Err: {{ \Illuminate\Support\Str::limit($client['last_error_message'], 32) }}
                                                </div>
                                            @endif
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="rb-badge {{ $badgeClass }}" style="font-size:.62rem; font-weight:bold;">
                                                {{ strtoupper($st) }}
                                            </span>
                                        </td>
                                        <td style="text-align:center; font-family:var(--rb-font-mono); font-size:.68rem;">
                                            {{ $client['last_status_code'] ?? '—' }}
                                        </td>
                                        <td style="text-align:center; font-family:var(--rb-font-mono); font-size:.68rem;">
                                            {{ $client['last_latency_ms'] !== null ? $client['last_latency_ms'].'ms' : '—' }}
                                        </td>
                                        <td class="rb-mono" style="font-size:.62rem;">
                                            {{ \Illuminate\Support\Str::limit((string) ($client['target_server'] ?? '—'), 22) }}
                                        </td>
                                        <td style="text-align:center; font-family:var(--rb-font-mono); font-size:.62rem;">
                                            {{ $client['last_ping_at'] }}
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="rb-badge rb-badge--pass" style="font-size:.65rem; font-weight:bold;">
                                                {{ $client['inbound_requests_count'] }} hits
                                            </span>
                                            @if ($client['last_inbound_endpoint'] !== '—')
                                                <div class="rb-mono rb-muted" style="font-size:.55rem; margin-top:.15rem;">
                                                    {{ $client['last_inbound_endpoint'] }}
                                                </div>
                                            @endif
                                        </td>
                                        <td>
                                            @if (in_array('*', (array) $client['allowed_models'], true))
                                                <span class="rb-tag" style="font-size:.58rem;">ALL MODELS (*)</span>
                                            @else
                                                <div style="display:flex; flex-wrap:wrap; gap:.25rem; max-width:220px;">
                                                    @foreach ((array) $client['allowed_models'] as $m)
                                                        <span class="rb-tag" style="font-size:.55rem;">{{ $m }}</span>
                                                    @endforeach
                                                </div>
                                            @endif
                                        </td>
                                        <td style="text-align:center; white-space:nowrap;">
                                            <div style="display:inline-flex; gap:.3rem;">
                                                <button type="button" class="rb-button" style="padding:.15rem .45rem; font-size:.6rem;" wire:click="pingClient({{ $client['id'] }})" wire:loading.attr="disabled" title="Probe status koneksi target sekarang">
                                                    &#128225; Ping
                                                </button>
                                                <button type="button" class="rb-button" style="padding:.15rem .45rem; font-size:.6rem;" wire:click="testInboundChat({{ $client['id'] }})" wire:loading.attr="disabled" title="Uji eksekusi model inbound secara simulasi">
                                                    &#9889; Uji
                                                </button>
                                                <button type="button" class="rb-button" style="padding:.15rem .45rem; font-size:.6rem;" wire:click="regenerateKey({{ $client['id'] }})" wire:confirm="Regenerasi API key untuk {{ $client['name'] }}? Kunci lama akan hangus." title="Regenerasi Kunci API">
                                                    &#128273; Key
                                                </button>
                                                <button type="button" class="rb-button" style="padding:.15rem .45rem; font-size:.6rem;" wire:click="toggleActive({{ $client['id'] }})" title="{{ $client['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }}">
                                                    {{ $client['is_active'] ? '&#9208;' : '&#9654;' }}
                                                </button>
                                                <button type="button" class="rb-button rb-button--danger" style="padding:.15rem .45rem; font-size:.6rem;" wire:click="deleteClient({{ $client['id'] }})" wire:confirm="Hapus pendaftaran proyek {{ $client['name'] }}?" title="Hapus proyek">
                                                    &#128465;
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Window Dokumentasi Integrasi & Referensi API --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[i] PANDUAN PENGGUNAAN API MODULAR OLEH PROYEK LAIN</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <p class="rb-mono rb-soft" style="font-size:.72rem; margin-bottom:.75rem;">
                    Proyek eksternal seperti <strong>agen xishie</strong> dapat memanggil endpoint modular Xishie menggunakan header <code>X-API-Key: &lt;KUNCI_API&gt;</code>:
                </p>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-bottom:1rem;">
                    <div style="background:var(--rb-surface-inset); padding:.75rem; border:var(--rb-border-soft); border-radius:4px;">
                        <div class="rb-mono" style="font-weight:bold; font-size:.72rem; color:var(--rb-primary); margin-bottom:.3rem;">
                            1. HANDSHAKE / PROBE KONEKSI
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.3rem;">GET /api/v1/external/ping</code>
                        <p class="rb-mono rb-muted" style="font-size:.65rem; margin:0;">
                            Memverifikasi status koneksi antar-aplikasi, identitas klien, dan hak akses model.
                        </p>
                    </div>

                    <div style="background:var(--rb-surface-inset); padding:.75rem; border:var(--rb-border-soft); border-radius:4px;">
                        <div class="rb-mono" style="font-weight:bold; font-size:.72rem; color:var(--rb-primary); margin-bottom:.3rem;">
                            2. KATALOG MODEL &amp; AGEN
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.3rem;">GET /api/v1/external/models</code>
                        <p class="rb-mono rb-muted" style="font-size:.65rem; margin:0;">
                            Mengambil daftar agen aktif (Scribe, RuleKeeper, ScholarScout, VenueMatcher, PeerCritic).
                        </p>
                    </div>

                    <div style="background:var(--rb-surface-inset); padding:.75rem; border:var(--rb-border-soft); border-radius:4px;">
                        <div class="rb-mono" style="font-weight:bold; font-size:.72rem; color:var(--rb-primary); margin-bottom:.3rem;">
                            3. INFERENSI AGEN &amp; CHAT
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.3rem;">POST /api/v1/external/chat</code>
                        <p class="rb-mono rb-muted" style="font-size:.65rem; margin:0;">
                            Body JSON: <code>{"prompt": "...", "model": "AcademicScribeAgent"}</code>
                        </p>
                    </div>

                    <div style="background:var(--rb-surface-inset); padding:.75rem; border:var(--rb-border-soft); border-radius:4px;">
                        <div class="rb-mono" style="font-weight:bold; font-size:.72rem; color:var(--rb-primary); margin-bottom:.3rem;">
                            4. AUDIT NASKAH &amp; KEPATUHAN
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.3rem;">POST /api/v1/external/audit</code>
                        <p class="rb-mono rb-muted" style="font-size:.65rem; margin:0;">
                            Body JSON: <code>{"content": "Teks naskah...", "style": "IEEE"}</code>
                        </p>
                    </div>
                </div>

                {{-- Contoh Cuplikan Kode Laravel Client --}}
                <div style="background:var(--rb-surface-inset); padding:.75rem; border:var(--rb-border-soft); border-radius:4px;">
                    <div class="rb-mono" style="font-weight:bold; font-size:.7rem; margin-bottom:.4rem;">
                        Contoh Pemanggilan dari Laravel Agen Xishie:
                    </div>
                    <pre class="rb-mono" style="font-size:.65rem; line-height:1.4; margin:0; overflow-x:auto;"><code>use Illuminate\Support\Facades\Http;

$response = Http::withHeaders([
    'X-API-Key' => config('services.xishie.api_key'),
])->post('http://localhost:8208/api/v1/external/chat', [
    'prompt' => 'Tuliskan abstrak mengenai pemanfaatan AI pada penulisan ilmiah...',
    'model'  => 'AcademicScribeAgent',
]);

$data = $response->json();</code></pre>
                </div>
            </div>
        </div>

    </div>
</x-filament-panels::page>
