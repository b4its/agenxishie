<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>18 UX &amp; PRODUCT METRICS DASHBOARD — AGEN XISHIE (STANDALONE)</title>
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
        .rb-window__body {
            padding: 1.25rem;
        }
        .rb-kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
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
            text-transform: uppercase;
        }
        .rb-kpi-card__val {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--rb-primary);
            margin: 0.2rem 0;
            line-height: 1.1;
        }
        .rb-kpi-card__unit {
            font-family: var(--rb-font-mono);
            font-size: 0.65rem;
            color: #64748B;
        }
        .rb-button {
            display: inline-flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.45rem 0.85rem;
            border: var(--rb-border);
            background: #FFFFFF;
            color: var(--rb-ink);
            box-shadow: 2px 2px 0px #0F172A;
            font-family: var(--rb-font-mono);
            font-size: 0.75rem;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.1s ease;
        }
        .rb-button:hover {
            transform: translate(-1px, -1px);
            box-shadow: 3px 3px 0px #0F172A;
        }
        .rb-button--primary {
            background: var(--rb-primary);
            color: #FFFFFF;
        }
        .rb-badge {
            display: inline-block;
            padding: 0.15rem 0.45rem;
            border: 1px solid var(--rb-ink);
            font-family: var(--rb-font-mono);
            font-size: 0.62rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .rb-badge--pass { background: #DCFCE7; color: #166534; }
        .rb-badge--alert { background: #FEE2E2; color: #991B1B; }
        .rb-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.75rem;
        }
        .rb-table th {
            background: #F1F5F9;
            border: 1px solid #CBD5E1;
            padding: 0.55rem 0.75rem;
            text-align: left;
            font-family: var(--rb-font-mono);
            font-size: 0.68rem;
        }
        .rb-table td {
            border: 1px solid #E2E8F0;
            padding: 0.55rem 0.75rem;
            vertical-align: middle;
        }
        .rb-table tr:hover {
            background: #F8FAFC;
        }
    </style>
</head>
<body>
    <div class="rb-container">
        <!-- Navigation Header -->
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem; flex-wrap:wrap; gap:.5rem;">
            <div style="display:flex; align-items:center; gap:.5rem;">
                <span class="rb-badge rb-badge--pass">STANDALONE SUITE</span>
                <span style="font-family:var(--rb-font-mono); font-size:.8rem; font-weight:700;">
                    AGEN XISHIE // 18 UX &amp; PRODUCT METRICS DASHBOARD
                </span>
            </div>
            <div style="display:flex; gap:.5rem;">
                <a href="/" class="rb-button">&larr; Beranda</a>
                <a href="/studio" class="rb-button rb-button--primary" style="background:#1d4ed8;">&#9997; Naskah Studio</a>
                <a href="/connected-projects" class="rb-button">&#128225; Hub Proyek</a>
                <a href="/admin" class="rb-button rb-button--primary">&#9889; Admin Panel</a>
            </div>
        </div>

        <!-- Project Selector Bar -->
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>PILIH PROYEK AKADEMIK UNTUK AUDIT METRIK</span>
                <span class="rb-badge rb-badge--pass">LIVE DATA</span>
            </div>
            <div class="rb-window__body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div>
                    <label style="font-family:var(--rb-font-mono); font-size:.7rem; font-weight:700; display:block; margin-bottom:.3rem;">
                        Proyek Aktif Saat Ini:
                    </label>
                    <form method="GET" action="{{ route('studio.analytics') }}" style="display:flex; gap:.5rem;">
                        <select name="project" onchange="this.form.submit()" style="padding:.4rem .6rem; border:2px solid #0f172a; font-family:var(--rb-font-mono); font-size:.75rem; min-width:280px;">
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}" {{ ($project && $project->id === $p->id) ? 'selected' : '' }}>
                                    #{{ $p->id }} — {{ $p->title }} ({{ strtoupper($p->document_type instanceof \BackedEnum ? $p->document_type->value : (string) $p->document_type) }})
                                </option>
                            @endforeach
                        </select>
                        <noscript><button type="submit" class="rb-button">Terapkan</button></noscript>
                    </form>
                </div>
                <div style="font-family:var(--rb-font-mono); font-size:.72rem;">
                    <div>Mode Kerja: <strong>{{ strtoupper($project?->control_mode instanceof \BackedEnum ? $project->control_mode->value : (string) ($project?->control_mode ?? 'hitl')) }}</strong></div>
                    <div style="color:#64748b;">Target Venue: {{ $project?->target_venue ?? 'SINTA / SCOPUS' }}</div>
                </div>
            </div>
        </div>

        <!-- KPI Executive Summary Cards -->
        <div class="rb-kpi-row">
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">SKOR KESEHATAN UX TOTAL</div>
                <div class="rb-kpi-card__val">{{ $healthScore }}%</div>
                <div class="rb-kpi-card__unit">{{ $healthyCount }} dari {{ $totalCount }} Metrik Memenuhi Standar</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">GOOGLE HEART HEALTH</div>
                <div class="rb-kpi-card__val" style="color:#059669;">
                    {{ count(array_filter($summary['heart'] ?? [], fn($m) => !empty($m['healthy']))) }} / {{ count($summary['heart'] ?? []) }}
                </div>
                <div class="rb-kpi-card__unit">Happiness, Engagement, Adoption, Retention</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">AARRR PIRATE FUNNEL</div>
                <div class="rb-kpi-card__val" style="color:#2563EB;">
                    {{ count(array_filter($summary['aarrr'] ?? [], fn($m) => !empty($m['healthy']))) }} / {{ count($summary['aarrr'] ?? []) }}
                </div>
                <div class="rb-kpi-card__unit">Acquisition, Retention, Referral, Conversion</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">USABILITY SPECIFIC METRICS</div>
                <div class="rb-kpi-card__val" style="color:#D97706;">
                    {{ count(array_filter($summary['ux'] ?? [], fn($m) => !empty($m['healthy']))) }} / {{ count($summary['ux'] ?? []) }}
                </div>
                <div class="rb-kpi-card__unit">Task Success, Velocity, Quality, Error, TTFT</div>
            </div>
        </div>

        <!-- 18 KPIs Full Metrics Table -->
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>TAKSONOMI 18 METRIK UX &amp; PRODUK (HEART + AARRR + USABILITY)</span>
                <span>TOTAL: 18 METRIK</span>
            </div>
            <div class="rb-window__body" style="overflow-x:auto;">
                <table class="rb-table">
                    <thead>
                        <tr>
                            <th>Metrik</th>
                            <th>Kategori</th>
                            <th>Kerangka</th>
                            <th>Nilai Aktual</th>
                            <th>Target</th>
                            <th>Status Mutu</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($summary['all'] ?? [] as $key => $metric)
                            <tr>
                                <td>
                                    <strong>{{ $metric['label'] }}</strong>
                                    <div style="font-size:.65rem; color:#64748b; font-family:var(--rb-font-mono);">{{ $key }}</div>
                                </td>
                                <td>{{ $metric['category'] }}</td>
                                <td>
                                    <span class="rb-badge" style="background:#F1F5F9;">{{ $metric['framework'] }}</span>
                                </td>
                                <td style="font-family:var(--rb-font-mono); font-weight:700;">
                                    {{ $metric['value'] }} {{ $metric['unit'] }}
                                </td>
                                <td style="font-family:var(--rb-font-mono); color:#64748b;">
                                    {{ $metric['target'] }} {{ $metric['unit'] }}
                                </td>
                                <td>
                                    @if ($metric['healthy'])
                                        <span class="rb-badge rb-badge--pass">🟢 SEHAT</span>
                                    @else
                                        <span class="rb-badge rb-badge--alert">🟡 ATTENTION</span>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" style="text-align:center; color:#94a3b8; padding:2rem;">Belum ada proyek terpilih.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Curated UX Metrics Design Guides & Articles -->
        <div class="rb-window">
            <div class="rb-window__titlebar" style="background:#0F172A;">
                <span>PANDUAN DESAIN &amp; PENGUKURAN METRIK PERILAKU PENGGUNA (6 SUMBER UTAMA)</span>
                <span class="rb-badge rb-badge--pass">REFERENSI DESAIN</span>
            </div>
            <div class="rb-window__body">
                <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
                    <a href="https://lnkd.in/dWGVK2Tp" target="_blank" rel="noopener noreferrer" style="border:1px solid #CBD5E1; padding:.75rem; text-decoration:none; color:inherit; background:#F8FAFC;">
                        <strong style="color:var(--rb-primary); display:block; margin-bottom:.2rem;">1. Understanding Metrics (Comprehensive Guide) ↗</strong>
                        <p style="font-size:.7rem; color:#64748b;">Panduan menyusun hierarki metrik behavioral, attitudinal, dan operasional.</p>
                    </a>
                    <a href="https://lnkd.in/dfbfSg8T" target="_blank" rel="noopener noreferrer" style="border:1px solid #CBD5E1; padding:.75rem; text-decoration:none; color:inherit; background:#F8FAFC;">
                        <strong style="color:var(--rb-primary); display:block; margin-bottom:.2rem;">2. Ultimate Guide to Product Metrics (57 Metrics) ↗</strong>
                        <p style="font-size:.7rem; color:#64748b;">Katalog komprehensif 57 indikator keberhasilan produk digital &amp; adopsi.</p>
                    </a>
                    <a href="https://lnkd.in/dAS5bdx5" target="_blank" rel="noopener noreferrer" style="border:1px solid #CBD5E1; padding:.75rem; text-decoration:none; color:inherit; background:#F8FAFC;">
                        <strong style="color:var(--rb-primary); display:block; margin-bottom:.2rem;">3. Product Metrics That Matter ↗</strong>
                        <p style="font-size:.7rem; color:#64748b;">Strategi memisahkan metrik kesombongan (*vanity*) dari sinyal nilai nyata (*impact*).</p>
                    </a>
                    <a href="https://lnkd.in/dvA_si5X" target="_blank" rel="noopener noreferrer" style="border:1px solid #CBD5E1; padding:.75rem; text-decoration:none; color:inherit; background:#F8FAFC;">
                        <strong style="color:var(--rb-primary); display:block; margin-bottom:.2rem;">4. AARRR Pirate Framework ↗</strong>
                        <p style="font-size:.7rem; color:#64748b;">Pengukuran corong Akuisisi, Aktivasi, Retensi, Rujukan, dan Pendapatan.</p>
                    </a>
                    <a href="https://lnkd.in/dSNEHVRx" target="_blank" rel="noopener noreferrer" style="border:1px solid #CBD5E1; padding:.75rem; text-decoration:none; color:inherit; background:#F8FAFC;">
                        <strong style="color:var(--rb-primary); display:block; margin-bottom:.2rem;">5. Google HEART Framework ↗</strong>
                        <p style="font-size:.7rem; color:#64748b;">Happiness, Engagement, Adoption, Retention, dan Task Success berskala besar.</p>
                    </a>
                    <a href="https://lnkd.in/dsMA7AFH" target="_blank" rel="noopener noreferrer" style="border:1px solid #CBD5E1; padding:.75rem; text-decoration:none; color:inherit; background:#F8FAFC;">
                        <strong style="color:var(--rb-primary); display:block; margin-bottom:.2rem;">6. UX Specific KPIs ↗</strong>
                        <p style="font-size:.7rem; color:#64748b;">Kalkulasi kuantitatif Task Success Rate, Time-on-Task, Error Rate, dan CSAT/SUS.</p>
                    </a>
                </div>
            </div>
        </div>

        <!-- Telemetry Audit Activity Log -->
        <div class="rb-window">
            <div class="rb-window__titlebar" style="background:#334155;">
                <span>LOG AKTIVITAS TELEMETRI &amp; AUDIT UX TERKINI</span>
                <span class="rb-badge rb-badge--pass">AUDIT TRAIL</span>
            </div>
            <div class="rb-window__body" style="overflow-x:auto;">
                <table class="rb-table">
                    <thead>
                        <tr>
                            <th>Waktu Rekam</th>
                            <th>Kunci Metrik</th>
                            <th>Kategori</th>
                            <th>Nilai</th>
                            <th>Metadata</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($recentTelemetries as $tel)
                            <tr>
                                <td style="font-family:var(--rb-font-mono); font-size:.7rem;">
                                    {{ $tel->recorded_at ? $tel->recorded_at->format('Y-m-d H:i:s') : '-' }}
                                </td>
                                <td>
                                    <strong>{{ $tel->metric_key->label() ?? $tel->metric_key->value }}</strong>
                                </td>
                                <td>
                                    <span class="rb-badge">{{ $tel->metric_category->label() }}</span>
                                </td>
                                <td style="font-family:var(--rb-font-mono); font-weight:700;">
                                    {{ $tel->metric_value }}
                                </td>
                                <td style="font-family:var(--rb-font-mono); font-size:.65rem; color:#64748b;">
                                    {{ json_encode($tel->metadata) }}
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" style="text-align:center; color:#94a3b8; padding:1.5rem;">Belum ada log telemetri tersimpan.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</body>
</html>
