<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>HUB PROYEK TERHUBUNG &amp; INTEGRASI MODULAR — AGEN XISHIE</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Space+Grotesk:wght@500;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --rb-ink: #0F172A;
            --rb-primary: #1E40AF;
            --rb-secondary: #2563EB;
            --rb-surface: #E0F2FE;
            --rb-blueprint: #F0F6FF;
            --rb-border: 2px solid #0F172A;
            --rb-shadow: 4px 4px 0px #0F172A;
            --rb-status-pass: #059669;
            --rb-status-alert: #DC2626;
            --rb-status-hitl: #D97706;
            --rb-font-display: 'Space Grotesk', system-ui, sans-serif;
            --rb-font-mono: 'JetBrains Mono', monospace;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            background-color: var(--rb-blueprint);
            background-image:
                linear-gradient(to right, rgba(15, 23, 42, 0.08) 1px, transparent 1px),
                linear-gradient(to bottom, rgba(15, 23, 42, 0.08) 1px, transparent 1px);
            background-size: 24px 24px;
            color: var(--rb-ink);
            font-family: var(--rb-font-display);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            padding: 1.5rem;
        }
        .rb-container {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }
        .rb-window {
            background: #FFFFFF;
            border: var(--rb-border);
            box-shadow: var(--rb-shadow);
            margin-bottom: 1.5rem;
        }
        .rb-window__titlebar {
            background: var(--rb-primary);
            color: #FFFFFF;
            padding: 0.55rem 0.85rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: var(--rb-font-mono);
            font-size: 0.75rem;
            letter-spacing: 0.06em;
            font-weight: 700;
        }
        .rb-window__controls {
            display: flex;
            gap: 0.35rem;
        }
        .rb-window__control {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.1rem;
            height: 1.1rem;
            border: 1px solid #FFFFFF;
            background: rgba(255, 255, 255, 0.15);
            font-size: 0.7rem;
            line-height: 1;
        }
        .rb-window__body {
            padding: 1.25rem;
        }
        .rb-kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .rb-kpi-card {
            border: var(--rb-border);
            padding: 0.85rem;
            background: var(--rb-surface);
            box-shadow: 2px 2px 0px #0F172A;
        }
        .rb-kpi-card__label {
            font-family: var(--rb-font-mono);
            font-size: 0.65rem;
            color: #475569;
            font-weight: 700;
        }
        .rb-kpi-card__val {
            font-size: 1.6rem;
            font-weight: 800;
            color: var(--rb-primary);
            margin: 0.2rem 0;
        }
        .rb-kpi-card__unit {
            font-family: var(--rb-font-mono);
            font-size: 0.62rem;
            color: #64748B;
        }
        .rb-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.45rem 0.85rem;
            background: #FFFFFF;
            border: var(--rb-border);
            box-shadow: 2px 2px 0px #0F172A;
            font-family: var(--rb-font-mono);
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            color: var(--rb-ink);
            cursor: pointer;
            transition: transform 0.1s ease, box-shadow 0.1s ease;
        }
        .rb-button:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0px #0F172A;
        }
        .rb-button:active {
            transform: translate(1px, 1px);
            box-shadow: 1px 1px 0px #0F172A;
        }
        .rb-button--primary {
            background: var(--rb-primary);
            color: #FFFFFF;
        }
        .rb-button--danger {
            background: #FEF2F2;
            color: var(--rb-status-alert);
            border-color: var(--rb-status-alert);
        }
        .rb-input, .rb-select {
            width: 100%;
            padding: 0.45rem 0.65rem;
            border: var(--rb-border);
            font-family: var(--rb-font-mono);
            font-size: 0.75rem;
            background: #FFFFFF;
            margin-top: 0.25rem;
        }
        .rb-input:focus, .rb-select:focus {
            outline: none;
            border-color: var(--rb-secondary);
            box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.2);
        }
        .rb-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        .rb-table th {
            background: var(--rb-primary);
            color: #FFFFFF;
            font-family: var(--rb-font-mono);
            font-size: 0.65rem;
            text-align: left;
            padding: 0.6rem 0.75rem;
            border-right: 1px solid rgba(255, 255, 255, 0.2);
        }
        .rb-table td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #CBD5E1;
            border-right: 1px solid #E2E8F0;
        }
        .rb-table tr:hover {
            background: #F8FAFC;
        }
        .rb-badge {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border: 1px solid var(--rb-ink);
            font-family: var(--rb-font-mono);
            font-size: 0.62rem;
            font-weight: 700;
        }
        .rb-badge--pass { background: #DCFCE7; color: #166534; }
        .rb-badge--alert { background: #FEE2E2; color: #991B1B; }
        .rb-badge--hitl { background: #FEF3C7; color: #92400E; }
        .rb-badge--manual { background: #F1F5F9; color: #475569; }
        .rb-notice {
            padding: 0.85rem;
            border: var(--rb-border);
            box-shadow: 2px 2px 0px #0F172A;
            margin-bottom: 1.25rem;
            font-family: var(--rb-font-mono);
            font-size: 0.75rem;
        }
        .rb-notice--approve { background: #ECFDF5; border-color: #047857; color: #065F46; }
        .rb-notice--info { background: #EFF6FF; border-color: #2563EB; color: #1E40AF; }
        .rb-notice--danger { background: #FEF2F2; border-color: #DC2626; color: #991B1B; }
        .rb-conn {
            display: inline-flex; align-items: center; gap: .4rem;
            padding: .2rem .55rem; border: 1px solid var(--rb-ink);
            font-family: var(--rb-font-mono); font-size: .62rem; font-weight: 700;
            white-space: nowrap;
        }
        .rb-conn__dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
        .rb-conn--pass { background: #DCFCE7; color: #166534; }
        .rb-conn--pass .rb-conn__dot { background: #16A34A; box-shadow: 0 0 0 2px rgba(22,163,74,.25); }
        .rb-conn--hitl { background: #FEF3C7; color: #92400E; }
        .rb-conn--hitl .rb-conn__dot { background: #D97706; }
        .rb-conn--alert { background: #FEE2E2; color: #991B1B; }
        .rb-conn--alert .rb-conn__dot { background: #DC2626; }
        .rb-conn--manual { background: #F1F5F9; color: #475569; }
        .rb-conn--manual .rb-conn__dot { background: #94A3B8; }
        .rb-mono { font-family: var(--rb-font-mono); }
        .rb-muted { color: #64748B; }
    </style>
</head>
<body>
    <div class="rb-container">

        {{-- Navigasi Topbar --}}
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:.5rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="rb-badge rb-badge--pass" style="font-size:.7rem;">LIVE SYSTEM</span>
                <span class="rb-mono" style="font-size:.75rem; font-weight:700;">AGEN XISHIE // MODULAR INTEGRATION HUB</span>
            </div>
            <div style="display:flex; gap:.5rem;">
                <a href="/" class="rb-button">&larr; Beranda</a>
                <a href="/admin" class="rb-button rb-button--primary">&#9889; Admin Panel</a>
                <a href="/admin/connected-projects" class="rb-button">&#128187; Filament Monitor</a>
            </div>
        </div>

        {{-- Flash Pesan / Alert --}}
        @if (session('status_message'))
            @php
                $tone = session('status_tone');
                $flashClass = match ($tone) {
                    'pass' => 'rb-notice--approve',
                    'alert' => 'rb-notice--danger',
                    default => 'rb-notice--info',
                };
            @endphp
            <div class="rb-notice {{ $flashClass }}">
                [i] {!! session('status_message') !!}
            </div>
        @endif

        {{-- Banner API Key Baru --}}
        @if (session('new_api_key'))
            <div class="rb-notice rb-notice--approve">
                <div style="font-weight:700; margin-bottom:.35rem;">
                    [&#10003;] KUNCI API AKTIF UNTUK: {{ session('new_client_name') }}
                </div>
                <p style="margin-bottom:.4rem; font-size:.7rem;">
                    Gunakan header <code>X-API-Key: {{ session('new_api_key') }}</code> untuk mengakses model &amp; endpoint Xishie dari proyek luar:
                </p>
                <div style="display:flex; gap:.5rem; align-items:center;">
                    <input type="text" readonly value="{{ session('new_api_key') }}" class="rb-input" id="apiKeyWebInput" style="font-weight:bold; color:var(--rb-primary); margin:0;" />
                    <button type="button" class="rb-button rb-button--primary" onclick="navigator.clipboard.writeText(document.getElementById('apiKeyWebInput').value); alert('API Key disalin!');">
                        Salin Kunci
                    </button>
                </div>
            </div>
        @endif

        {{-- Box Hasil Uji Inbound --}}
        @if (session('test_result'))
            @php $res = session('test_result'); @endphp
            <div class="rb-notice rb-notice--info">
                <div style="font-weight:700; margin-bottom:.35rem;">
                    [&#9889;] HASIL EKSEKUSI INBOUND CHAT DARI: {{ $res['client'] ?? 'Klien' }}
                </div>
                <div style="font-size:.7rem;">
                    Model: <strong>{{ $res['model'] ?? 'AcademicScribeAgent' }}</strong> &middot; Eksekusi: {{ $res['executed_at'] ?? 'now' }}
                    <pre style="background:#fff; padding:.5rem; border:1px solid #CBD5E1; margin-top:.4rem; white-space:pre-wrap;">{{ $res['response'] ?? 'Respon OK' }}</pre>
                </div>
            </div>
        @endif

        {{-- Window Utama: Kontrol & Monitoring Hub --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#9679;] HUB PROYEK EKSTERNAL &amp; INTEGRASI MODULAR (MONITORING TARGET: http://localhost:8208/)</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <p class="rb-mono" style="font-size:.75rem; margin-bottom:1rem; line-height:1.5;">
                    Pusat pendaftaran dan pemantauan konektivitas antar-proyek. Memungkinkan aplikasi Laravel <strong>agen xishie</strong>
                    (misal di <code>http://localhost:8208/</code>) atau website lain untuk register, mendapatkan kunci API,
                    dan mengonsumsi endpoint model Xishie secara modular.
                </p>

                {{-- Row Indikator KPI --}}
                <div class="rb-kpi-row">
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">TOTAL PROYEK TERDAFTAR</div>
                        <div class="rb-kpi-card__val">{{ $totalClients }}</div>
                        <div class="rb-kpi-card__unit">Klien Modular</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">PROYEK TERHUBUNG</div>
                        <div class="rb-kpi-card__val" style="color:{{ $connectedClients > 0 ? 'var(--rb-status-pass)' : 'var(--rb-status-alert)' }};">
                            {{ $connectedClients }} / {{ $totalClients }}
                        </div>
                        <div class="rb-kpi-card__unit">Handshake Terautentikasi</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">TIDAK TERJANGKAU</div>
                        <div class="rb-kpi-card__val" style="color:{{ $unreachableClients > 0 ? 'var(--rb-status-alert)' : 'var(--rb-status-pass)' }};">
                            {{ $unreachableClients }}
                        </div>
                        <div class="rb-kpi-card__unit">Target Offline</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">RATA-RATA LATENSI</div>
                        <div class="rb-kpi-card__val" style="color:{{ $avgLatencyMs < 200 && $avgLatencyMs > 0 ? 'var(--rb-status-pass)' : 'var(--rb-ink)' }};">
                            {{ $avgLatencyMs > 0 ? $avgLatencyMs.'ms' : '—' }}
                        </div>
                        <div class="rb-kpi-card__unit">Waktu Tanggap Probe</div>
                    </div>
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">TOTAL INBOUND HITS</div>
                        <div class="rb-kpi-card__val" style="color:var(--rb-status-pass);">{{ $totalInboundHits }}</div>
                        <div class="rb-kpi-card__unit">Permintaan Masuk API</div>
                    </div>
                </div>

                {{-- Bar Tombol Aksi Cepat --}}
                <div style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:center;">
                    <form action="{{ route('connected-projects.handshake-all') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="rb-button rb-button--primary">&#128279; Handshake Semua Proyek</button>
                    </form>
                    <form action="{{ route('connected-projects.ping-all') }}" method="POST" style="display:inline;">
                        @csrf
                        <button type="submit" class="rb-button">&#128225; Probe HTTP Saja</button>
                    </form>
                    <a href="{{ route('connected-projects.index') }}" class="rb-button">&#8635; Segarkan Data</a>
                    <button type="button" class="rb-button" onclick="document.getElementById('registerFormWindow').scrollIntoView({behavior:'smooth'});">&#43; Daftarkan Proyek Baru</button>
                    <label class="rb-mono" style="font-size:.68rem; margin-left:auto; display:flex; align-items:center; gap:.4rem; cursor:pointer;">
                        <input type="checkbox" id="autoRefreshCheckbox" onchange="toggleAutoRefresh(this.checked);" /> Auto-Refresh (10s)
                    </label>
                </div>
            </div>
        </div>

        {{-- Legenda Arti Status Koneksi --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[i] ARTI STATUS KONEKSI &amp; LANGKAH PENANGANANNYA</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(260px, 1fr)); gap:.75rem;">
                    @foreach (\App\Domain\Integration\Enums\ConnectionStatus::cases() as $state)
                        <div style="border:1px solid #CBD5E1; padding:.6rem; background:#FFFFFF;">
                            <span class="rb-conn rb-conn--{{ $state->tone() }}" style="margin-bottom:.35rem;">
                                <span class="rb-conn__dot"></span>{{ $state->shortLabel() }}
                            </span>
                            <div class="rb-mono" style="font-size:.68rem; font-weight:700; margin-top:.35rem;">{{ $state->label() }}</div>
                            <p class="rb-mono rb-muted" style="font-size:.6rem; margin:.2rem 0 0;">{{ $state->description() }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Window Tabel Proyek Terhubung --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#9679;] DAFTAR PROYEK TARGET &amp; STATUS KONEKTIVITAS REAL-TIME</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body" style="padding:0;">
                <div style="overflow-x:auto;">
                    <table class="rb-table">
                        <thead>
                            <tr>
                                <th>PROYEK</th>
                                <th>URL TARGET</th>
                                <th style="text-align:center;">STATUS KONEKSI</th>
                                <th style="text-align:center;">STATUS &amp; RIWAYAT</th>
                                <th style="text-align:center;">UPTIME</th>
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
                            @forelse ($clients as $client)
                                @php
                                    $st = (string) ($client['status'] ?? 'unknown');
                                    $badgeClass = match ($st) {
                                        'online' => 'rb-badge--pass',
                                        'degraded' => 'rb-badge--hitl',
                                        'timeout' => 'rb-badge--hitl',
                                        'offline' => 'rb-badge--alert',
                                        default => 'rb-badge--manual',
                                    };
                                    $uptime = (float) ($client['uptime_percentage'] ?? 100.0);
                                @endphp
                                <tr style="{{ ! $client['is_active'] ? 'opacity:0.55;' : '' }}">
                                    <td>
                                        <div style="display:flex; align-items:center; gap:.35rem;">
                                            <strong>{{ $client['name'] }}</strong>
                                            @if (! $client['is_active'])
                                                <span class="rb-badge rb-badge--manual" style="font-size:.52rem;">NONAKTIF</span>
                                            @endif
                                        </div>
                                        <div class="rb-mono rb-muted" style="font-size:.62rem;">
                                            Slug: <code>{{ $client['client_slug'] }}</code>
                                        </div>
                                    </td>
                                    <td class="rb-mono" style="font-size:.68rem;">
                                        <a href="{{ $client['target_url'] }}" target="_blank" rel="noopener noreferrer" style="color:var(--rb-primary); text-decoration:underline;">
                                            {{ $client['target_url'] }}
                                        </a>
                                        @if ($client['last_error_message'])
                                            <div class="rb-mono" style="color:var(--rb-status-alert); font-size:.58rem;" title="{{ $client['last_error_message'] }}">
                                                Err: {{ \Illuminate\Support\Str::limit($client['last_error_message'], 32) }}
                                            </div>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="rb-conn rb-conn--{{ $client['connection_tone'] }}" title="{{ $client['connection_description'] }}">
                                            <span class="rb-conn__dot"></span>{{ $client['connection_short'] }}
                                        </span>
                                        <div class="rb-mono rb-muted" style="font-size:.55rem; margin-top:.25rem;">
                                            {{ $client['last_handshake_at'] }}
                                        </div>
                                        @if ($client['target_app_name'] || $client['target_version'])
                                            <div class="rb-mono" style="font-size:.55rem; margin-top:.15rem; color:var(--rb-primary);" title="Identitas aplikasi target terdeteksi dari handshake">
                                                {{ $client['target_app_name'] ?? '—' }}
                                                @if ($client['target_version']) v{{ $client['target_version'] }} @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td style="text-align:center;">
                                        <span class="rb-badge {{ $badgeClass }}" style="font-size:.62rem; font-weight:bold;">
                                            {{ strtoupper($st) }}
                                        </span>
                                        @if (! empty($client['recent_pings']))
                                            <div style="display:flex; gap:3px; justify-content:center; align-items:center; margin-top:4px;" title="Riwayat probe koneksi terbaru">
                                                @foreach ($client['recent_pings'] as $ping)
                                                    @php
                                                        $dotBg = match ($ping['status']) {
                                                            'online' => 'var(--rb-status-pass)',
                                                            'offline' => 'var(--rb-status-alert)',
                                                            default => 'var(--rb-status-hitl)',
                                                        };
                                                    @endphp
                                                    <span style="display:inline-block; width:7px; height:7px; border-radius:2px; background:{{ $dotBg }};" title="{{ $ping['probed_at'] }} &middot; {{ $ping['latency_ms'] ?? 0 }}ms &middot; {{ strtoupper($ping['status']) }}"></span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td style="text-align:center; font-family:var(--rb-font-mono); font-size:.68rem; font-weight:bold; color:{{ $uptime >= 90 ? 'var(--rb-status-pass)' : ($uptime >= 70 ? 'var(--rb-status-hitl)' : 'var(--rb-status-alert)') }};">
                                        {{ $uptime }}%
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
                                            <span class="rb-badge rb-badge--manual" style="font-size:.55rem;">ALL MODELS (*)</span>
                                        @else
                                            <div style="display:flex; flex-wrap:wrap; gap:.25rem; max-width:220px;">
                                                @foreach ((array) $client['allowed_models'] as $m)
                                                    <span class="rb-badge rb-badge--manual" style="font-size:.52rem;">{{ $m }}</span>
                                                @endforeach
                                            </div>
                                        @endif
                                    </td>
                                    <td style="text-align:center; white-space:nowrap;">
                                        <div style="display:inline-flex; gap:.25rem;">
                                            {{-- Ping --}}
                                            <form action="{{ route('connected-projects.ping', $client['id']) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rb-button" style="padding:.12rem .4rem; font-size:.58rem;" title="Probe status target sekarang">&#128225; Ping</button>
                                            </form>

                                            {{-- Handshake --}}
                                            <form action="{{ route('connected-projects.handshake', $client['id']) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rb-button" style="padding:.12rem .4rem; font-size:.58rem;" title="Handshake konektivitas mendalam">&#129309; Handshake</button>
                                            </form>

                                            {{-- Webhook Test --}}
                                            <form action="{{ route('connected-projects.test-webhook', $client['id']) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rb-button" style="padding:.12rem .4rem; font-size:.58rem;" title="Uji kirim Webhook">&#128279; Hook</button>
                                            </form>

                                            {{-- Handshake end-to-end --}}
                                            <form action="{{ route('connected-projects.handshake', $client['id']) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rb-button rb-button--primary" style="padding:.12rem .4rem; font-size:.58rem;" title="Verifikasi koneksi end-to-end (API key + kontrak respons)">&#128279; Handshake</button>
                                            </form>

                                            {{-- Uji Inbound --}}
                                            <form action="{{ route('connected-projects.test-inbound', $client['id']) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rb-button" style="padding:.12rem .4rem; font-size:.58rem;" title="Uji eksekusi model inbound">&#9889; Uji</button>
                                            </form>

                                            {{-- Regen Key --}}
                                            <form action="{{ route('connected-projects.regenerate-key', $client['id']) }}" method="POST" onsubmit="return confirm('Regenerasi API Key untuk {{ $client['name'] }}?');">
                                                @csrf
                                                <button type="submit" class="rb-button" style="padding:.12rem .4rem; font-size:.58rem;" title="Regenerasi API Key">&#128273; Key</button>
                                            </form>

                                            {{-- Toggle Active --}}
                                            <form action="{{ route('connected-projects.toggle', $client['id']) }}" method="POST">
                                                @csrf
                                                <button type="submit" class="rb-button" style="padding:.12rem .4rem; font-size:.58rem;" title="{{ $client['is_active'] ? 'Nonaktifkan' : 'Aktifkan' }}">{{ $client['is_active'] ? '⏸' : '▶' }}</button>
                                            </form>

                                            {{-- Delete --}}
                                            <form action="{{ route('connected-projects.destroy', $client['id']) }}" method="POST" onsubmit="return confirm('Hapus proyek {{ $client['name'] }}?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="rb-button rb-button--danger" style="padding:.12rem .4rem; font-size:.58rem;" title="Hapus">&#128465;</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="12" style="text-align:center; padding:1.5rem;" class="rb-mono rb-muted">Belum ada proyek target terdaftar.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Window Form Registrasi Proyek --}}
        <div class="rb-window" id="registerFormWindow">
            <div class="rb-window__titlebar">
                <span>[&#43;] FORMULIR REGISTRASI PROYEK TARGET / WEBSITE EKSTERNAL BARU</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <form action="{{ route('connected-projects.store') }}" method="POST">
                    @csrf
                    <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-bottom:1rem;">
                        <div>
                            <label class="rb-mono" style="font-size:.7rem; font-weight:700;">NAMA PROYEK / WEBSITE *</label>
                            <input type="text" name="name" class="rb-input" placeholder="Misal: Agen Xishie Produksi, Portal Jurnal Kampus" required value="{{ old('name') }}" />
                            @error('name') <span class="rb-mono" style="color:var(--rb-status-alert); font-size:.65rem;">{{ $message }}</span> @enderror
                        </div>
                        <div>
                            <label class="rb-mono" style="font-size:.7rem; font-weight:700;">URL TARGET MONITORING &amp; PROBE *</label>
                            <input type="text" name="target_url" class="rb-input" placeholder="http://localhost:8208/ atau https://target.domain" required value="{{ old('target_url', 'http://localhost:8208/') }}" />
                            @error('target_url') <span class="rb-mono" style="color:var(--rb-status-alert); font-size:.65rem;">{{ $message }}</span> @enderror
                        </div>
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label class="rb-mono" style="font-size:.7rem; font-weight:700;">DESKRIPSI / TUJUAN INTEGRASI</label>
                        <input type="text" name="description" class="rb-input" placeholder="Keterangan singkat arsitektur atau tujuan integrasi..." value="{{ old('description') }}" />
                    </div>

                    <div style="margin-bottom:1rem;">
                        <label class="rb-mono" style="font-size:.7rem; font-weight:700; display:block; margin-bottom:.3rem;">
                            IZIN MODEL &amp; PIPELINE YANG DIBUKA UNTUK PROYEK INI
                        </label>
                        <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(240px, 1fr)); gap:.5rem; background:var(--rb-surface); padding:.75rem; border:var(--rb-border);">
                            <label class="rb-mono" style="font-size:.7rem; display:flex; align-items:center; gap:.4rem; cursor:pointer;">
                                <input type="checkbox" name="selected_models[]" value="*" checked />
                                <strong>* (Semua Model &amp; Endpoint)</strong>
                            </label>
                            @foreach ($availableModels as $modelKey => $meta)
                                <label class="rb-mono" style="font-size:.7rem; display:flex; align-items:center; gap:.4rem; cursor:pointer;">
                                    <input type="checkbox" name="selected_models[]" value="{{ $modelKey }}" />
                                    <span>{{ $meta['name'] }}</span>
                                </label>
                            @endforeach
                        </div>
                        @error('selected_models') <span class="rb-mono" style="color:var(--rb-status-alert); font-size:.65rem;">{{ $message }}</span> @enderror
                    </div>

                    <div style="display:flex; justify-content:flex-end;">
                        <button type="submit" class="rb-button rb-button--primary">
                            &#43; Daftarkan Proyek &amp; Terbitkan Kunci API
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Window Dokumentasi Integrasi & Cuplikan Kode --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[i] PANDUAN INTEGRASI MODULAR (CARA MEMANGGIL MODEL DARI PROYEK LAIN)</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <p class="rb-mono" style="font-size:.72rem; margin-bottom:.75rem;">
                    Proyek pemanggil (seperti <strong>agen xishie</strong>) dapat mengirim HTTP Request ke endpoint modular Xishie dengan menyertakan header <code>X-API-Key: &lt;KUNCI_API&gt;</code>:
                </p>

                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem; margin-bottom:1rem;">
                    <div style="background:var(--rb-surface); padding:.75rem; border:1px solid #CBD5E1;">
                        <div class="rb-mono" style="font-weight:700; font-size:.7rem; color:var(--rb-primary); margin-bottom:.25rem;">
                            1. PROBE &amp; HANDSHAKE
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.25rem;">GET /api/v1/external/ping</code>
                        <p class="rb-mono rb-muted" style="font-size:.62rem; margin:0;">Cek apakah kedua proyek terhubung aktif.</p>
                    </div>

                    <div style="background:var(--rb-surface); padding:.75rem; border:1px solid #CBD5E1;">
                        <div class="rb-mono" style="font-weight:700; font-size:.7rem; color:var(--rb-primary); margin-bottom:.25rem;">
                            2. KATALOG MODEL
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.25rem;">GET /api/v1/external/models</code>
                        <p class="rb-mono rb-muted" style="font-size:.62rem; margin:0;">Daftar model yang diizinkan untuk kunci API ini.</p>
                    </div>

                    <div style="background:var(--rb-surface); padding:.75rem; border:1px solid #CBD5E1;">
                        <div class="rb-mono" style="font-weight:700; font-size:.7rem; color:var(--rb-primary); margin-bottom:.25rem;">
                            3. INFERENSI MODEL &amp; CHAT
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.25rem;">POST /api/v1/external/chat</code>
                        <p class="rb-mono rb-muted" style="font-size:.62rem; margin:0;">Kirim prompt ke AcademicScribeAgent / model LLM.</p>
                    </div>

                    <div style="background:var(--rb-surface); padding:.75rem; border:1px solid #CBD5E1;">
                        <div class="rb-mono" style="font-weight:700; font-size:.7rem; color:var(--rb-primary); margin-bottom:.25rem;">
                            4. AUDIT NASKAH &amp; IMRaD
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.25rem;">POST /api/v1/external/audit</code>
                        <p class="rb-mono rb-muted" style="font-size:.62rem; margin:0;">Audit kepatuhan struktur, kata, dan referensi.</p>
                    </div>

                    <div style="background:#FFFBEB; padding:.75rem; border:1px solid #D97706;">
                        <div class="rb-mono" style="font-weight:700; font-size:.7rem; color:#92400E; margin-bottom:.25rem;">
                            5. DAFTAR MANDIRI (SELF-REGISTER)
                        </div>
                        <code class="rb-mono" style="font-size:.65rem; display:block; margin-bottom:.25rem;">POST /api/v1/external/register</code>
                        <p class="rb-mono rb-muted" style="font-size:.62rem; margin:0;">
                            Proyek lain mendaftarkan diri &amp; menerima API key otomatis. Wajib header
                            <code>X-Registration-Secret</code>.
                        </p>
                    </div>
                </div>

                <div style="background:#0F172A; color:#38BDF8; padding:.85rem; border:var(--rb-border); font-family:var(--rb-font-mono); font-size:.68rem; line-height:1.5;">
                    <div style="color:#4ADE80; font-weight:700; margin-bottom:.35rem;">// CONTOH PEMANGGILAN DARI LARAVEL (AGEN XISHIE)</div>
                    <div>use Illuminate\Support\Facades\Http;</div>
                    <br>
                    <div>$response = Http::withHeaders([</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'X-API-Key' =&gt; config('services.xishie.api_key'),</div>
                    <div>])-&gt;post('http://localhost:8208/api/v1/external/chat', [</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'prompt' =&gt; 'Tuliskan kajian literatur mengenai NLP pada penulisan ilmiah...',</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'model'  =&gt; 'AcademicScribeAgent',</div>
                    <div>]);</div>
                    <br>
                    <div>$data = $response-&gt;json(); // Kembalian respon ilmiah terstruktur dari Xishie</div>
                </div>

                <div style="background:#0F172A; color:#38BDF8; padding:.85rem; border:var(--rb-border); font-family:var(--rb-font-mono); font-size:.68rem; line-height:1.5; margin-top:1rem;">
                    <div style="color:#FBBF24; font-weight:700; margin-bottom:.35rem;">// CONTOH DAFTAR MANDIRI DARI PROYEK LAIN (SELF-REGISTER)</div>
                    <div>$registration = Http::withHeaders([</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'X-Registration-Secret' =&gt; env('XISHIE_REGISTRATION_SECRET'),</div>
                    <div>])-&gt;post('http://localhost:8208/api/v1/external/register', [</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'name' =&gt; 'Agen Xishie (Laravel)',</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'target_url' =&gt; config('app.url'),</div>
                    <div>&nbsp;&nbsp;&nbsp;&nbsp;'allowed_models' =&gt; ['*'],</div>
                    <div>]);</div>
                    <br>
                    <div>// Simpan $registration-&gt;json('client.api_key') ke config proyek Anda,</div>
                    <div>// lalu status koneksi akan tampil dan terpantau di halaman ini.</div>
                </div>
            </div>
        </div>

    </div>

    <script>
        let refreshTimer = null;
        function toggleAutoRefresh(enabled) {
            if (enabled) {
                localStorage.setItem('xishie_auto_refresh', '1');
                refreshTimer = setTimeout(() => { window.location.reload(); }, 10000);
            } else {
                localStorage.removeItem('xishie_auto_refresh');
                if (refreshTimer) clearTimeout(refreshTimer);
            }
        }
        if (localStorage.getItem('xishie_auto_refresh') === '1') {
            document.getElementById('autoRefreshCheckbox').checked = true;
            refreshTimer = setTimeout(() => { window.location.reload(); }, 10000);
        }
    </script>
</body>
</html>
