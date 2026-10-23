<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AGEN XISHIE — Intelligent Academic &amp; Publication Workspace</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;600;700&family=Space+Grotesk:wght@500;700;800&family=Source+Serif+4:ital,wght@0,400;0,600;1,400&display=swap" rel="stylesheet">
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
            --rb-font-display: 'Space Grotesk', system-ui, sans-serif;
            --rb-font-mono: 'JetBrains Mono', monospace;
            --rb-font-serif: 'Source Serif 4', Georgia, serif;
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
            max-width: 1100px;
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
            padding: 0.5rem 0.85rem;
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
        .rb-hero {
            display: grid;
            grid-template-columns: 1.4fr 1fr;
            gap: 1.5rem;
            align-items: center;
        }
        @media (max-width: 768px) {
            .rb-hero { grid-template-columns: 1fr; }
        }
        .rb-title {
            font-size: 2.25rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--rb-primary);
            margin-bottom: 0.75rem;
            letter-spacing: -0.02em;
        }
        .rb-subtitle {
            font-size: 0.95rem;
            line-height: 1.6;
            color: #334155;
            margin-bottom: 1.25rem;
        }
        .rb-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.4rem;
            padding: 0.55rem 1.15rem;
            background: #FFFFFF;
            border: var(--rb-border);
            box-shadow: 2px 2px 0px #0F172A;
            font-family: var(--rb-font-mono);
            font-size: 0.78rem;
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
        .rb-button--secondary {
            background: var(--rb-surface);
            color: var(--rb-primary);
        }
        .rb-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }
        .rb-badge {
            display: inline-block;
            padding: 0.2rem 0.55rem;
            border: 1px solid var(--rb-ink);
            font-family: var(--rb-font-mono);
            font-size: 0.65rem;
            font-weight: 700;
            background: var(--rb-surface);
            color: var(--rb-ink);
        }
        .rb-badge--pass { background: #DCFCE7; color: #166534; }
        .rb-badge--auto { background: #FEF08A; color: #854D0E; }
        .rb-badge--hitl { background: #E0E7FF; color: #3730A3; }
        .rb-card {
            border: 1.5px solid #0F172A;
            padding: 0.85rem;
            background: #FAFAFA;
        }
        .rb-card__title {
            font-weight: 700;
            font-size: 0.85rem;
            margin-bottom: 0.35rem;
            color: var(--rb-primary);
        }
        .rb-card__body {
            font-size: 0.75rem;
            line-height: 1.5;
            color: #475569;
        }
        .rb-terminal {
            background: #0F172A;
            color: #38BDF8;
            font-family: var(--rb-font-mono);
            font-size: 0.72rem;
            padding: 0.85rem;
            border: var(--rb-border);
            line-height: 1.6;
        }
        .rb-kpi-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.75rem;
            margin: 1rem 0;
        }
        .rb-kpi-card {
            border: 1.5px solid #0F172A;
            padding: 0.65rem;
            background: var(--rb-surface);
            text-align: center;
        }
        .rb-kpi-card__label {
            font-family: var(--rb-font-mono);
            font-size: 0.6rem;
            color: #475569;
            font-weight: 700;
        }
        .rb-kpi-card__val {
            font-size: 1.35rem;
            font-weight: 800;
            color: var(--rb-primary);
            margin: 0.2rem 0;
        }
        .rb-footer {
            margin-top: auto;
            text-align: center;
            font-family: var(--rb-font-mono);
            font-size: 0.7rem;
            color: #64748B;
            padding-top: 1rem;
        }
    </style>
</head>
<body>
    <div class="rb-container">
        {{-- Main Banner Window --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#9632;] AGEN XISHIE v1.0 // INTELLIGENT ACADEMIC &amp; PUBLICATION WORKSPACE</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <div class="rb-hero">
                    <div>
                        <div style="margin-bottom: 0.5rem; display: flex; gap: 0.4rem; flex-wrap: wrap;">
                            <span class="rb-badge rb-badge--pass">PHP 8.4+ &middot; LARAVEL 13</span>
                            <span class="rb-badge rb-badge--hitl">FILAMENT 5</span>
                            <span class="rb-badge rb-badge--auto">OMNIGENSI MULTI-AGENT</span>
                            <span class="rb-badge">MYSQL 8.4 INNODB</span>
                        </div>
                        <h1 class="rb-title">AGEN XISHIE</h1>
                        <p class="rb-subtitle">
                            Platform kerja akademik cerdas yang memandu peneliti, mahasiswa, dan akademisi
                            dari tahap ideasi hingga publikasi naskah ilmiah (<strong>Jurnal, Skripsi, Tesis, Disertasi</strong>)
                            untuk target <strong>Nasional (SINTA 1-6)</strong> maupun <strong>Internasional (Scopus Q1-Q4 / WoS)</strong>.
                        </p>
                        <div style="display: flex; gap: 0.5rem; flex-wrap: wrap;">
                            <a href="/connected-projects" class="rb-button rb-button--primary" style="background:#047857;">&#128225; Hub Proyek Terhubung (:8208)</a>
                            <a href="/admin" class="rb-button rb-button--primary">&#9889; Masuk ke Admin Panel</a>
                            <a href="/admin/project-onboarding-wizard" class="rb-button rb-button--secondary">+ Mulai Proyek Baru (Wizard)</a>
                            <a href="/admin/projects" class="rb-button">Daftar Proyek</a>
                            <a href="/admin/venue-catalog" class="rb-button">&#127760; Katalog Venue Jurnal</a>
                        </div>
                    </div>
                    <div class="rb-terminal">
                        <div style="color: #4ADE80; font-weight: 700; margin-bottom: 0.4rem;">// OMNIGENSI MULTI-AGENT ENGINE</div>
                        <div>&gt; [Rule-Keeper]   :: Guideline Compliance &amp; Word Limits</div>
                        <div>&gt; [Scholar-Scout] :: Strict Citation Binding &amp; SOTA Matrix</div>
                        <div>&gt; [Academic-Scribe]:: Draft, Proposal, Final &amp; Thesis Conversion</div>
                        <div>&gt; [Peer-Critic]   :: Action Checklist, Side-by-Side Diff</div>
                        <div>&gt; [Venue-Matcher] :: Hybrid Vector Cosine (SINTA/Scopus)</div>
                        <div style="margin-top: 0.6rem; color: #FCD34D;">// CONTROL MODES: AUTO | HITL | MANUAL</div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Connected Projects / Integration Hub Status Window --}}
        <div class="rb-window" style="background:#F8FAFC; border:2px solid var(--rb-primary);">
            <div class="rb-window__titlebar" style="background:#0F172A; display:flex; justify-content:space-between; align-items:center;">
                <span>[&#128225;] INTEGRASI MODULAR &amp; STATUS PROYEK TERHUBUNG // TARGET: http://localhost:8208/</span>
                <span class="rb-badge rb-badge--pass" style="font-size:0.6rem;">ENDPOINT READY</span>
            </div>
            <div class="rb-window__body" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem;">
                <div>
                    <div style="font-size:0.95rem; font-weight:800; color:var(--rb-primary); margin-bottom:0.25rem;">
                        Target Agen Xishie &amp; Klien Modular
                    </div>
                    <p class="rb-mono" style="font-size:0.75rem; color:#475569; margin:0;">
                        Target <code>http://localhost:8208/</code> terhubung. Proyek atau website lain dapat mendaftar untuk mengonsumsi model AI, endpoint chat, audit naskah, dan verifikasi sitasi.
                    </p>
                </div>
                <div style="display:flex; gap:0.5rem; align-items:center;">
                    <a href="/connected-projects" class="rb-button rb-button--primary" style="font-size:0.75rem;">
                        &#128202; Buka Monitoring &amp; Registrasi Proyek &rarr;
                    </a>
                </div>
            </div>
        </div>

        {{-- 18 UX KPIs Bar --}}
        <div class="rb-kpi-row">
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">COMPLIANCE SCORE</div>
                <div class="rb-kpi-card__val">100%</div>
                <div class="rb-kpi-card__label">Task Success</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">CITATION DENSITY</div>
                <div class="rb-kpi-card__val">100%</div>
                <div class="rb-kpi-card__label">Strict Binding</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">REVISION VELOCITY</div>
                <div class="rb-kpi-card__val">100%</div>
                <div class="rb-kpi-card__label">Peer-Critic Diff</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">VENUE MATCH</div>
                <div class="rb-kpi-card__val">95%+</div>
                <div class="rb-kpi-card__label">SINTA &amp; Scopus</div>
            </div>
            <div class="rb-kpi-card">
                <div class="rb-kpi-card__label">EXPORT CONVERSION</div>
                <div class="rb-kpi-card__val">DOCX/MD/TEX</div>
                <div class="rb-kpi-card__label">Siap Kirim</div>
            </div>
        </div>

        {{-- Feature Highlights Grid --}}
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>FITUR UTAMA &amp; ALUR KERJA AKADEMIK</span>
            </div>
            <div class="rb-window__body">
                <div class="rb-grid">
                    <div class="rb-card">
                        <div class="rb-card__title">1. Onboarding Wizard</div>
                        <div class="rb-card__body">
                            Personalisasi 4 langkah: bidang keilmuan, jenis naskah (Jurnal/Skripsi/Tesis/Disertasi),
                            target indeks (SINTA 1-6 / Scopus Q1-Q4), preset pedoman (IMRaD / DIKTI / Custom upload PDF/DOCX),
                            serta basis kendali.
                        </div>
                    </div>
                    <div class="rb-card">
                        <div class="rb-card__title">2. Academic Workspace (3 Panel)</div>
                        <div class="rb-card__body">
                            Navigator &amp; riset (matriks literatur, upload PDF, verifikasi sitasi),
                            editor naskah terpadu dengan HITL Approval Gate, Live Guideline Auditor,
                            dan Xishie Assistant kontekstual.
                        </div>
                    </div>
                    <div class="rb-card">
                        <div class="rb-card__title">3. Smart Revision Center</div>
                        <div class="rb-card__body">
                            Dekomposisi catatan revisi reviewer/pembimbing menjadi Actionable Checklist,
                            tampilan Side-by-Side Diff (Before vs After), penerapan perbaikan instan ke naskah,
                            dan matriks Response to Reviewers otomatis.
                        </div>
                    </div>
                    <div class="rb-card">
                        <div class="rb-card__title">4. Platform Matcher &amp; Venue Catalog</div>
                        <div class="rb-card__body">
                            Pencocokan naskah semantik hybrid (FULLTEXT + Cosine Similarity) ke katalog
                            jurnal SINTA 1-6 dan Scopus Q1-Q4/WoS, estimasi APC, adaptasi naskah ke template venue,
                            dan ekspor siap kirim.
                        </div>
                    </div>
                    <div class="rb-card">
                        <div class="rb-card__title">5. Konversi Skripsi ke Jurnal</div>
                        <div class="rb-card__body">
                            Ekstraksi otomatis naskah Skripsi/Tesis/Disertasi tahap Final menjadi naskah
                            jurnal ilmiah berformat IMRaD lengkap dengan sitasi terikat dan unduhan DOCX/LaTeX/Markdown.
                        </div>
                    </div>
                    <div class="rb-card">
                        <div class="rb-card__title">6. Telemetri 18 UX KPIs</div>
                        <div class="rb-card__body">
                            Dashboard pemantau kesehatan proses penulisan berbasis Google HEART &amp; AARRR
                            Frameworks, log eksekusi agen terperinci, dan rating kepuasan CSAT 1-5.
                        </div>
                    </div>
                </div>

                <div style="margin-top: 1.5rem; text-align: center; display: flex; gap: 0.5rem; justify-content: center; flex-wrap: wrap;">
                    <a href="/admin/academic-workspace" class="rb-button rb-button--primary">&#9998; Buka Academic Workspace</a>
                    <a href="/admin/smart-revision-center" class="rb-button">&#8635; Buka Smart Revision Center</a>
                    <a href="/admin/platform-matcher" class="rb-button">&#127963; Buka Platform Matcher</a>
                    <a href="/admin/xishie-assistant" class="rb-button">&#10024; Buka Xishie Assistant</a>
                    <a href="/admin/ux-analytics" class="rb-button">&#128202; UX Analytics Dashboard</a>
                </div>
            </div>
        </div>

        <div class="rb-footer">
            AGEN XISHIE &middot; Intelligent Academic &amp; Publication Workspace &middot; {{ now()->year }}
        </div>
    </div>
</body>
</html>
