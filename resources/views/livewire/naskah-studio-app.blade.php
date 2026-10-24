<div class="ns-root {{ $darkMode ? 'dark' : '' }}"
    x-data="{
        kpiDrawer: @entangle('kpiDrawerOpen'),
        zenMode: @entangle('zenMode'),
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    $wire.openDialog('palette');
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    $wire.save();
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'z') {
                    e.preventDefault();
                    $wire.undo();
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'y') {
                    e.preventDefault();
                    $wire.redo();
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'f') {
                    e.preventDefault();
                    $wire.openDialog('find');
                }
            });
            setInterval(() => {
                $wire.tickSession();
            }, 5000);
        }
    }">

    <!-- ===================================================================== -->
    <!-- TOP UX & PRODUCT TELEMETRY BAR (18 KPIS EVERY UX DESIGNER SHOULD KNOW) -->
    <!-- ===================================================================== -->
    <header class="ns-topbar">
        <!-- Brand & Document Title -->
        <div class="ns-topbar__left">
            <a href="{{ route('home') }}" class="ns-brand" title="Kembali ke Beranda">
                NASKAH STUDIO
            </a>
            <input type="text" wire:model.live.debounce.400ms="docTitle" wire:change="markDirty"
                class="ns-title-input"
                placeholder="Judul Naskah...">
            <span class="ns-badge {{ $dirty ? 'ns-badge--dirty' : 'ns-badge--saved' }}">
                {{ $dirty ? '● Belum Simpan' : '✓ Tersimpan' }}
            </span>
        </div>

        <!-- 18 UX KPIs Live Gauges & Usability Telemetry -->
        <div class="ns-telemetry-strip">
            <!-- Time on Task & Active Session Depth (Efficiency & Engagement) -->
            <div class="ns-kpi-pill" title="Time on Task / Kedalaman Sesi Aktif">
                <span style="color:var(--rb-primary); font-weight:700;">⏱️ Waktu Sesi:</span>
                <span>{{ floor($sessionSeconds / 60) }}m {{ $sessionSeconds % 60 }}s</span>
            </div>

            <!-- Guideline Compliance Meter (Task Success) -->
            <button type="button" wire:click="toggleKpiDrawer('UX')" class="ns-kpi-pill ns-kpi-pill--btn" title="Kepatuhan Pedoman Ilmiah">
                <span>🎯 Kepatuhan:</span>
                <strong>{{ $complianceAudit['score'] }}%</strong>
                <span style="opacity:0.75;">({{ $complianceAudit['grade'] }})</span>
            </button>

            <!-- Verified Citation Density (Task Quality) -->
            <div class="ns-kpi-pill" title="Kepadatan Sitasi per 1.000 kata">
                <span>📚 Sitasi:</span>
                <strong style="color:var(--rb-ink);">
                    {{ round(($statistics['words'] > 0 ? (($elements['footnotes'] ?? 0) / max(1, $statistics['words'])) * 1000 : 0), 1) }}
                </strong>
                <span style="color:var(--rb-ink-muted);">/1k</span>
            </div>

            <!-- Interactive CSAT / Happiness Rating (HEART - Happiness) -->
            <div class="ns-csat-widget" title="Beri rating kepuasan UX menulis (CSAT / Single Ease Question)">
                <span style="font-weight:700;">CSAT:</span>
                <div style="display:flex; align-items:center;">
                    @for ($s = 1; $s <= 5; $s++)
                        <button type="button" wire:click="rateCsat({{ $s }})" class="ns-csat-star" style="color: {{ $csatScore >= $s ? '#d97706' : '#cbd5e1' }};" title="Beri Rating {{ $s }}/5">
                            ★
                        </button>
                    @endfor
                </div>
                @if ($csatSubmitted)
                    <span style="color:#059669; font-weight:700; margin-left:2px;">✓</span>
                @endif
            </div>

            <!-- Latency / TTFT (Performance) -->
            <div class="ns-kpi-pill" title="System Response Latency / TTFT">
                <span style="color:#10b981;">●</span>
                <span>TTFT: 32ms</span>
            </div>

            <!-- 18 UX KPIs HUD Drawer Trigger -->
            <button type="button" wire:click="toggleKpiDrawer" class="ns-kpi-hud-trigger" title="Buka Inspector 18 UX &amp; Product Metrics">
                <span>📊 18 UX KPIs</span>
            </button>

            <!-- Zen Mode Toggle -->
            <button type="button" wire:click="toggleZenMode" class="ns-btn" title="Fokus Tanpa Gangguan (Zen Mode)">
                {{ $zenMode ? 'Exit Zen' : '🧘 Zen' }}
            </button>

            <!-- Dark Mode Toggle -->
            <button type="button" wire:click="toggleDarkMode" class="ns-btn" title="Ganti Mode Terang/Gelap">
                {{ $darkMode ? '☀️ Terang' : '🌙 Gelap' }}
            </button>
        </div>
    </header>

    <!-- ===================================================================== -->
    <!-- RIBBON TOOLBAR (WORDCRAFT + LIBREOFFICE WRITER TABS)                   -->
    <!-- ===================================================================== -->
    <div x-show="!zenMode" class="ns-ribbon-container">
        <!-- Ribbon Tab Headers -->
        <div class="ns-ribbon-tabs">
            @php
                $tabs = [
                    'home' => 'Home',
                    'insert' => 'Sisipkan',
                    'design' => 'Desain',
                    'layout' => 'Tata Letak',
                    'references' => 'Referensi',
                    'review' => 'Peninjauan',
                    'table' => 'Tabel',
                    'mailings' => 'Surat Menyurat',
                    'view' => 'Tampilan',
                ];
            @endphp
            @foreach ($tabs as $key => $label)
                <button type="button" wire:click="$set('activeTab', '{{ $key }}')"
                    class="ns-ribbon-tab {{ $activeTab === $key ? 'active' : '' }}">
                    {{ $label }}
                </button>
            @endforeach

            <!-- Wordcraft Command Palette Trigger -->
            <button type="button" wire:click="openDialog('palette')" class="ns-btn font-mono" style="margin-left:auto;" title="Buka Command Palette HUD (Ctrl+K)">
                <span>⚡ Perintah HUD</span>
                <kbd style="padding: 1px 4px; background: var(--rb-surface-muted); border: 1px solid var(--rb-canvas-grid); border-radius: 2px; font-size: 9px;">Ctrl+K</kbd>
            </button>
        </div>

        <!-- Ribbon Toolbar Commands Bar -->
        <div class="ns-ribbon-body">
            @if ($activeTab === 'home')
                <!-- Clipboard & Undo/Redo/Format Painter -->
                <div class="ns-group">
                    <span class="ns-group__label">Papan Klip</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="undo" {{ empty($undoStack) ? 'disabled' : '' }} title="Urungkan (Ctrl+Z)">↩ Undo</button>
                        <button type="button" class="ns-btn" wire:click="redo" {{ empty($redoStack) ? 'disabled' : '' }} title="Ulangi (Ctrl+Y)">↪ Redo</button>
                        <button type="button" class="ns-btn {{ $copiedFormat ? 'ns-btn--active' : '' }}" wire:click="copyFormat({{ $activeBlock }})" title="Format Painter">🖌️ Kuas Format</button>
                    </div>
                </div>

                <!-- Font & Ukuran -->
                <div class="ns-group">
                    <span class="ns-group__label">Font &amp; Tipografi</span>
                    <div class="ns-group__row">
                        <select wire:model.live="fontFamily" class="ns-btn" style="padding-right:1rem;">
                            @foreach ($fontFamilies as $k => $fontLabel)
                                <option value="{{ $k }}">{{ $fontLabel }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="fontSize" class="ns-btn" style="padding-right:1rem;">
                            @foreach ($fontSizes as $fs)
                                <option value="{{ $fs }}">{{ $fs }} pt</option>
                            @endforeach
                        </select>
                        <button type="button" class="ns-btn" wire:click="adjustFontSize(1)" title="Perbesar Font">A▲</button>
                        <button type="button" class="ns-btn" wire:click="adjustFontSize(-1)" title="Perkecil Font">A▼</button>
                    </div>
                </div>

                <!-- Format Inline -->
                <div class="ns-group">
                    <span class="ns-group__label">Format Karakter</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn font-bold" wire:click="formatInline('bold')" title="Tebal (Ctrl+B)">B</button>
                        <button type="button" class="ns-btn italic" wire:click="formatInline('italic')" title="Miring (Ctrl+I)">I</button>
                        <button type="button" class="ns-btn underline" wire:click="formatInline('underline')" title="Garis Bawah (Ctrl+U)">U</button>
                        <button type="button" class="ns-btn line-through" wire:click="formatInline('strike')" title="Coret">S</button>
                        <button type="button" class="ns-btn font-mono" wire:click="formatInline('code')" title="Kode Inline">&lt;/&gt;</button>
                        <button type="button" class="ns-btn" style="color:#dc2626;" wire:click="clearFormatting" title="Hapus Format">✕</button>
                    </div>
                </div>

                <!-- Warna & Sorotan -->
                <div class="ns-group">
                    <span class="ns-group__label">Warna &amp; Sorotan</span>
                    <div class="ns-group__row">
                        @foreach ($colors as $color)
                            <button type="button" style="width:16px; height:16px; border-radius:2px; border:1px solid #cbd5e1; background:{{ $color }}; cursor:pointer;"
                                wire:click="formatInline('color', '{{ $color }}')" title="Warna {{ $color }}"></button>
                        @endforeach
                        <span style="border-left:1px solid var(--rb-canvas-grid); height:14px; margin:0 2px;"></span>
                        @foreach ($highlights as $hl)
                            <button type="button" style="width:16px; height:16px; border-radius:2px; border:1px solid #cbd5e1; background:{{ $hl }}; cursor:pointer;"
                                wire:click="formatInline('highlight', '{{ $hl }}')" title="Sorot {{ $hl }}"></button>
                        @endforeach
                    </div>
                </div>

                <!-- Paragraf & Perataan -->
                <div class="ns-group">
                    <span class="ns-group__label">Paragraf</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="formatParagraph('align', 'left')" title="Rata Kiri">⫷</button>
                        <button type="button" class="ns-btn" wire:click="formatParagraph('align', 'center')" title="Rata Tengah">≡</button>
                        <button type="button" class="ns-btn" wire:click="formatParagraph('align', 'right')" title="Rata Kanan">⫸</button>
                        <button type="button" class="ns-btn" wire:click="formatParagraph('align', 'justify')" title="Rata Kiri Kanan">☷</button>
                        <button type="button" class="ns-btn" wire:click="formatParagraph('indent-')" title="Kurangi Indentasi">⇤</button>
                        <button type="button" class="ns-btn" wire:click="formatParagraph('indent+')" title="Tambah Indentasi">⇥</button>
                    </div>
                </div>

                <!-- Galeri Gaya & Auto-Correct -->
                <div class="ns-group">
                    <span class="ns-group__label">Gaya &amp; Alat Sunting</span>
                    <div class="ns-group__row">
                        @foreach ($stylesGallery as $styleKey => $styleLabel)
                            <button type="button" class="ns-btn" wire:click="applyStyle({{ $activeBlock }}, '{{ $styleKey }}')">{{ $styleLabel }}</button>
                        @endforeach
                        <button type="button" class="ns-btn" wire:click="openDialog('find')">🔍 Cari &amp; Ganti</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="runAutoCorrect" title="LibreOffice Typographical Auto-Correct">⚡ Auto-Correct</button>
                    </div>
                </div>

            @elseif ($activeTab === 'insert')
                <div class="ns-group">
                    <span class="ns-group__label">Blok Konten</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="addBlock('heading')">+ Judul</button>
                        <button type="button" class="ns-btn" wire:click="addBlock('paragraph')">+ Paragraf</button>
                        <button type="button" class="ns-btn" wire:click="addBlock('quote')">“ Kutipan</button>
                        <button type="button" class="ns-btn" wire:click="insertCallout('info')">📌 Callout</button>
                        <button type="button" class="ns-btn" wire:click="addBlock('bullet')">• Poin</button>
                        <button type="button" class="ns-btn" wire:click="addBlock('numbered')">1. Nomor</button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Tabel &amp; Media</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="openDialog('table')">⊞ Sisipkan Tabel</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('image')">🖼️ Sisipkan Gambar</button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Tautan &amp; Catatan</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="openDialog('link')">🔗 Tautan</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('footnote')">⚓ Catatan Kaki</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('symbols')">Ω Simbol Khusus</button>
                        <button type="button" class="ns-btn" wire:click="toggleDropCap({{ $activeBlock }})">Drop Cap</button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Pemisah &amp; Otomasi</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="insertPageBreak">✂️ Pemisah Halaman</button>
                        <button type="button" class="ns-btn" wire:click="insertHorizontalRule">― Garis Pemisah</button>
                        <button type="button" class="ns-btn" wire:click="insertDate">📅 Tanggal</button>
                        <button type="button" class="ns-btn" wire:click="insertTableOfContents">📑 Daftar Isi</button>
                        <button type="button" class="ns-btn" wire:click="insertBibliography({{ $sourceProjectId }})">📚 Daftar Pustaka</button>
                    </div>
                </div>

            @elseif ($activeTab === 'design')
                <div class="ns-group">
                    <span class="ns-group__label">Tema Desain</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn {{ ($styleMeta['theme'] ?? '') === 'academic' ? 'ns-btn--active' : '' }}" wire:click="applyTheme('academic')">Akademik</button>
                        <button type="button" class="ns-btn {{ ($styleMeta['theme'] ?? '') === 'modern' ? 'ns-btn--active' : '' }}" wire:click="applyTheme('modern')">Modern</button>
                        <button type="button" class="ns-btn {{ ($styleMeta['theme'] ?? '') === 'classic' ? 'ns-btn--active' : '' }}" wire:click="applyTheme('classic')">Klasik</button>
                        <button type="button" class="ns-btn {{ ($styleMeta['theme'] ?? '') === 'code' ? 'ns-btn--active' : '' }}" wire:click="applyTheme('code')">Teknis</button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Warna Kertas</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#ffffff')">Putih</button>
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#fdfbf7')">Gading</button>
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('pageColor', '#fcf6e9')">Sepia</button>
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('pageBorder', {{ !($pageSetup['pageBorder'] ?? false) ? 'true' : 'false' }})">
                            Bingkai: {{ !empty($pageSetup['pageBorder']) ? 'Aktif' : 'Nonaktif' }}
                        </button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Tanda Air (Watermark)</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', 'DRAF')">DRAF</button>
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', 'RAHASIA')">RAHASIA</button>
                        <button type="button" class="ns-btn" wire:click="updatePageSetup('watermark', '')">Hapus Tanda Air</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('pagesetup')">⚙️ Pengaturan Halaman</button>
                    </div>
                </div>

            @elseif ($activeTab === 'layout')
                <div class="ns-group">
                    <span class="ns-group__label">Kertas &amp; Orientasi</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn font-semibold" wire:click="openDialog('pagesetup')">Ukuran: {{ $pageSetup['size'] ?? 'A4' }}</button>
                        <button type="button" class="ns-btn font-semibold" wire:click="updatePageSetup('orientation', '{{ ($pageSetup['orientation'] ?? 'portrait') === 'portrait' ? 'landscape' : 'portrait' }}')">
                            Orientasi: {{ ucfirst($pageSetup['orientation'] ?? 'portrait') }}
                        </button>
                        <button type="button" class="ns-btn" wire:click="$toggle('showRuler')">
                            {{ $showRuler ? 'Sembunyikan Penggaris' : 'Tampilkan Penggaris' }}
                        </button>
                    </div>
                </div>

            @elseif ($activeTab === 'references')
                <div class="ns-group">
                    <span class="ns-group__label">Sitasi &amp; Pustaka</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="insertBibliography({{ $sourceProjectId }})">📚 Sisipkan Daftar Pustaka</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('footnote')">⚓ Catatan Kaki</button>
                        <button type="button" class="ns-btn" wire:click="insertTableOfContents">📑 Daftar Isi</button>
                    </div>
                </div>

            @elseif ($activeTab === 'review')
                <div class="ns-group">
                    <span class="ns-group__label">Komentar &amp; Revisi</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn {{ $showComments ? 'ns-btn--active' : '' }}" wire:click="$toggle('showComments')">💬 Panel Komentar ({{ count($comments) }})</button>
                        <button type="button" class="ns-btn {{ $showRevisions ? 'ns-btn--active' : '' }}" wire:click="$toggle('showRevisions')">👁️ Lacak Revisi ({{ $pendingRevisionCount }} pending)</button>
                        <button type="button" class="ns-btn" style="color:#059669;" wire:click="acceptAllRevisions">✓ Terima Semua</button>
                        <button type="button" class="ns-btn" style="color:#dc2626;" wire:click="rejectAllRevisions">✕ Tolak Semua</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('snapshots')">📁 Versi Dokumen ({{ count($snapshots) }})</button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Bahasa &amp; Ejaan</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="runSpellCheck">✓ Periksa Ejaan</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('thesaurus')">📖 Tesaurus Kata</button>
                    </div>
                </div>

            @elseif ($activeTab === 'table')
                <div class="ns-group">
                    <span class="ns-group__label">Struktur Tabel</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="tableInsertRow({{ $activeBlock }}, 1)">+ Baris Bawah</button>
                        <button type="button" class="ns-btn" wire:click="tableInsertColumn({{ $activeBlock }}, 1)">+ Kolom Kanan</button>
                        <button type="button" class="ns-btn" wire:click="tableToggleHeader({{ $activeBlock }})">Header Baris</button>
                    </div>
                </div>
                <div class="ns-group">
                    <span class="ns-group__label">Kalkulasi Formula (LibreOffice Table)</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="tableSumColumn({{ $activeBlock }}, 1)">Σ Hitung SUM</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="tableAverageColumn({{ $activeBlock }}, 1)">x̄ Hitung AVERAGE</button>
                    </div>
                </div>

            @elseif ($activeTab === 'mailings')
                <div class="ns-group">
                    <span class="ns-group__label">Mail Merge</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="openDialog('merge')">✉️ Konfigurasi Mail Merge CSV</button>
                    </div>
                </div>

            @elseif ($activeTab === 'view')
                <div class="ns-group">
                    <span class="ns-group__label">Navigasi &amp; Tampilan</span>
                    <div class="ns-group__row">
                        <button type="button" class="ns-btn" wire:click="$toggle('showNavigation')">{{ $showNavigation ? 'Sembunyikan Navigator' : 'Tampilkan Navigator' }}</button>
                        <button type="button" class="ns-btn" wire:click="$toggle('showRuler')">{{ $showRuler ? 'Sembunyikan Penggaris' : 'Tampilkan Penggaris' }}</button>
                        <button type="button" class="ns-btn" wire:click="zoomIn">Zoom +</button>
                        <button type="button" class="ns-btn" wire:click="zoomOut">Zoom -</button>
                        <span class="font-mono font-bold" style="font-size:0.75rem;">{{ $zoom }}%</span>
                        <button type="button" class="ns-btn" wire:click="openDialog('stats')">📊 Statistik Keterbacaan</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('properties')">ℹ️ Properti Dokumen</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('shortcuts')">⌨️ Tombol Pintas</button>
                    </div>
                </div>
            @endif

            <!-- Quick Multi-Format Export Buttons (LibreOffice Multi-Format Engine) -->
            <div style="margin-left:auto; display:flex; align-items:center; gap:0.25rem; padding-left:0.75rem; border-left:1px solid var(--rb-canvas-grid); flex-shrink:0;">
                <span class="ns-group__label" style="margin-right:2px;">Ekspor:</span>
                <button type="button" wire:click="export('odt')" class="ns-btn font-bold font-mono" style="background:#059669; color:#fff; border-color:#059669;" title="Unduh OpenDocument (.odt)">ODT</button>
                <button type="button" wire:click="export('docx')" class="ns-btn font-bold font-mono" style="background:#2563eb; color:#fff; border-color:#2563eb;" title="Unduh Microsoft Word (.docx)">DOCX</button>
                <button type="button" wire:click="export('latex')" class="ns-btn font-bold font-mono" style="background:#7c3aed; color:#fff; border-color:#7c3aed;" title="Unduh LaTeX (.tex)">LaTeX</button>
                <button type="button" wire:click="export('md')" class="ns-btn font-bold font-mono" style="background:#334155; color:#fff; border-color:#334155;" title="Unduh Markdown (.md)">MD</button>
                <button type="button" wire:click="export('rtf')" class="ns-btn font-bold font-mono" style="background:#d97706; color:#fff; border-color:#d97706;" title="Unduh Rich Text (.rtf)">RTF</button>
                <button type="button" wire:click="save" class="ns-btn ns-btn--primary font-bold" style="padding:0.3rem 0.75rem; margin-left:0.25rem;">
                    <span>💾 Simpan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- HORIZONTAL RULER (LIBREOFFICE RULER)                                   -->
    <!-- ===================================================================== -->
    @if ($showRuler && ! $zenMode)
        <div class="ns-ruler">
            <div class="ns-ruler__ticks">
                @for ($cm = 0; $cm <= 20; $cm++)
                    <div class="ns-ruler__tick">
                        <div class="ns-ruler__bar"></div>
                        <span>{{ $cm }}</span>
                    </div>
                @endfor
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MAIN WORKSPACE: NAVIGATOR, CANVAS, DRAWERS, ASSISTANT                 -->
    <!-- ===================================================================== -->
    <div class="ns-workspace">
        <!-- LEFT SIDEBAR: NAVIGATOR (LIBREOFFICE NAVIGATOR & OUTLINE) -->
        <aside x-show="!zenMode && {{ $showNavigation ? 'true' : 'false' }}" class="ns-navigator">
            <div class="ns-sidebar-header">
                <span>🧭 Navigator Dokumen</span>
                <button type="button" wire:click="$set('showNavigation', false)" style="background:none; border:none; color:var(--rb-ink-muted); font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>

            <!-- Outline / Headings -->
            <div style="margin-bottom:1rem;">
                <span style="font-weight:700; font-size:0.68rem; text-transform:uppercase; color:var(--rb-primary); display:block; margin-bottom:0.35rem; font-family:var(--rb-font-mono);">
                    Struktur &amp; Judul ({{ $elements['headings'] ?? 0 }})
                </span>
                <div style="display:flex; flex-direction:column; gap:0.2rem;">
                    @forelse ($outline as $item)
                        <button type="button" wire:click="selectBlock({{ $item['index'] }})"
                            class="ns-btn" style="text-align:left; justify-content:flex-start; width:100%; border-color:transparent; padding:0.25rem 0.5rem; {{ $activeBlock === $item['index'] ? 'background:var(--rb-accent-soft); font-weight:700; color:var(--rb-primary);' : '' }}"
                            style="padding-left: {{ ($item['level'] - 1) * 12 + 6 }}px">
                            {{ $item['text'] ?: '(Judul Tanpa Teks)' }}
                        </button>
                    @empty
                        <span style="color:var(--rb-ink-muted); font-style:italic; font-size:0.68rem;">Belum ada judul. Sisipkan Heading untuk membentuk kerangka.</span>
                    @endforelse
                </div>
            </div>

            <!-- Statistics Summary Box -->
            <div style="margin-bottom:1rem; padding:0.65rem; background:var(--rb-surface-soft); border:1px solid var(--rb-canvas-grid); border-radius:3px; font-family:var(--rb-font-mono); font-size:0.68rem; display:flex; flex-direction:column; gap:0.25rem;">
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--rb-ink-muted);">Kata:</span>
                    <strong style="color:var(--rb-ink);">{{ number_format($statistics['words'] ?? 0) }}</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--rb-ink-muted);">Karakter:</span>
                    <span>{{ number_format($statistics['characters'] ?? 0) }}</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--rb-ink-muted);">Kalimat:</span>
                    <span>{{ number_format($statistics['sentences'] ?? 0) }}</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--rb-ink-muted);">Waktu Baca:</span>
                    <span>{{ $statistics['readingTimeMinutes'] ?? 1 }} menit</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span style="color:var(--rb-ink-muted);">Flesch Ease:</span>
                    <span>{{ $statistics['readingEase'] ?? 0 }}</span>
                </div>
            </div>

            <!-- Version Snapshots List -->
            <div>
                <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:0.35rem;">
                    <span style="font-weight:700; font-size:0.68rem; text-transform:uppercase; color:var(--rb-ink); font-family:var(--rb-font-mono);">
                        Versi Dokumen ({{ count($snapshots) }})
                    </span>
                    <button type="button" wire:click="openDialog('snapshots')" class="ns-btn font-mono" style="font-size:0.65rem; padding:0.15rem 0.35rem;">+ Simpan</button>
                </div>
                <div style="display:flex; flex-direction:column; gap:0.25rem;">
                    @forelse ($snapshots as $idx => $snap)
                        <div style="padding:0.4rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; display:flex; align-items:center; justify-content:space-between; font-size:0.68rem; background:var(--rb-surface-soft);">
                            <div>
                                <span style="font-weight:600; display:block; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $snap['label'] }}</span>
                                <span style="font-size:0.6rem; color:var(--rb-ink-muted); font-family:var(--rb-font-mono);">{{ $snap['at'] }}</span>
                            </div>
                            <div style="display:flex; gap:0.25rem;">
                                <button type="button" wire:click="restoreSnapshot({{ $idx }})" class="ns-btn" style="padding:0.1rem 0.3rem;" title="Pulihkan Versi Ini">↩</button>
                                <button type="button" wire:click="deleteSnapshot({{ $idx }})" class="ns-btn" style="padding:0.1rem 0.3rem; color:#dc2626;" title="Hapus">&times;</button>
                            </div>
                        </div>
                    @empty
                        <span style="color:var(--rb-ink-muted); font-style:italic; font-size:0.68rem;">Belum ada versi snapshot.</span>
                    @endforelse
                </div>
            </div>
        </aside>

        <!-- CENTER CANVAS: PAPER VIEW -->
        <main class="ns-canvas-area">
            <div class="ns-paper"
                style="background-color: {{ $pageSetup['pageColor'] ?? '#ffffff' }}; font-family: {{ ($fontFamily === 'serif' ? 'var(--rb-font-serif)' : ($fontFamily === 'mono' ? 'var(--rb-font-mono)' : 'var(--rb-font-sans)')) }}; font-size: {{ $fontSize }}pt;">

                <!-- Watermark jika disetel -->
                @if (!empty($pageSetup['watermark']))
                    <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; user-select:none; opacity:0.06; font-size:4rem; font-weight:900; text-transform:uppercase; transform:rotate(-45deg); font-family:var(--rb-font-mono);">
                        {{ $pageSetup['watermark'] }}
                    </div>
                @endif

                <!-- Document Header Canvas -->
                <div class="ns-paper-header">
                    <span style="font-weight:700;">{{ $docTitle }}</span>
                    <span>Target: {{ $targetWordCount }} kata ({{ round((($statistics['words'] ?? 0) / max(1, $targetWordCount)) * 100) }}%)</span>
                </div>

                <!-- BLOCKS CONTAINER -->
                <div style="display:flex; flex-direction:column; gap:0.75rem; flex:1;">
                    @foreach ($blocks as $index => $block)
                        @php
                            $type = $block['type'] ?? 'paragraph';
                            $isActive = $activeBlock === $index;
                        @endphp
                        <div wire:key="block-{{ $index }}"
                            wire:click="selectBlock({{ $index }})"
                            class="ns-block {{ $isActive ? 'active' : '' }}">

                            <!-- In-Block Floating Wordcraft AI & Block Toolbar -->
                            <div class="ns-block__toolbar">
                                <button type="button" wire:click.stop="aiElaborateBlock({{ $index }})" title="Kembangkan Narasi (Wordcraft)">✨ Perluas</button>
                                <span style="opacity:0.4;">•</span>
                                <button type="button" wire:click.stop="aiShortenBlock({{ $index }})" title="Ringkas Padat">✂️ Ringkas</button>
                                <span style="opacity:0.4;">•</span>
                                <button type="button" wire:click.stop="aiFixTone({{ $index }}, 'akademik')" title="Sesuaikan Gaya Akademik">🎓 Akademik</button>
                                <span style="opacity:0.4;">•</span>
                                <button type="button" wire:click.stop="moveBlock({{ $index }}, -1)" title="Pindah Ke Atas">▲</button>
                                <button type="button" wire:click.stop="moveBlock({{ $index }}, 1)" title="Pindah Ke Bawah">▼</button>
                                <button type="button" wire:click.stop="removeBlock({{ $index }})" style="color:#f87171; margin-left:2px;" title="Hapus Blok">&times;</button>
                            </div>

                            <!-- BLOCK TYPE: HEADING -->
                            @if ($type === 'heading')
                                @php $level = $block['level'] ?? 1; @endphp
                                <input type="text" value="{{ $block['text'] ?? '' }}"
                                    wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                    class="ns-input-heading {{ $level === 1 ? 'ns-input-heading--h1' : ($level === 2 ? 'ns-input-heading--h2' : 'ns-input-heading--h3') }}"
                                    placeholder="Judul (H{{ $level }})...">

                            <!-- BLOCK TYPE: CALLOUT (LIBREOFFICE CALLOUT BOX) -->
                            @elseif ($type === 'callout')
                                @php
                                    $cType = $block['calloutType'] ?? 'info';
                                    $borderColors = [
                                        'info' => '#2563eb',
                                        'warning' => '#d97706',
                                        'tip' => '#059669',
                                        'note' => '#7c3aed',
                                        'success' => '#059669',
                                    ];
                                    $bgColors = [
                                        'info' => 'rgba(37,99,235,0.06)',
                                        'warning' => 'rgba(217,119,6,0.06)',
                                        'tip' => 'rgba(5,150,105,0.06)',
                                        'note' => 'rgba(124,58,237,0.06)',
                                        'success' => 'rgba(5,150,105,0.06)',
                                    ];
                                @endphp
                                <div style="border-left:4px solid {{ $borderColors[$cType] ?? '#2563eb' }}; background:{{ $bgColors[$cType] ?? 'rgba(37,99,235,0.06)' }}; padding:0.75rem; border-radius:0 4px 4px 0;">
                                    <div style="font-weight:700; font-size:0.65rem; text-transform:uppercase; margin-bottom:0.25rem; font-family:var(--rb-font-mono); color:{{ $borderColors[$cType] ?? '#2563eb' }};">
                                        📌 Catatan Penting ({{ strtoupper($cType) }})
                                    </div>
                                    <textarea rows="2" wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="ns-textarea-p" style="font-family:var(--rb-font-serif); font-size:0.9rem;"
                                        placeholder="Tuliskan catatan khusus di sini...">{{ $block['text'] ?? '' }}</textarea>
                                </div>

                            <!-- BLOCK TYPE: QUOTE -->
                            @elseif ($type === 'quote')
                                <div style="border-left:4px solid var(--rb-ink-muted); padding-left:1rem; font-style:italic;">
                                    <textarea rows="2" wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="ns-textarea-p" style="font-family:var(--rb-font-serif);"
                                        placeholder="Kutipan langsung...">{{ $block['text'] ?? '' }}</textarea>
                                </div>

                            <!-- BLOCK TYPE: TABLE (LIBREOFFICE TABLE ENGINE) -->
                            @elseif ($type === 'table')
                                <div style="overflow-x:auto; margin:0.5rem 0; border:1px solid var(--rb-canvas-grid); border-radius:3px;">
                                    <table style="width:100%; border-collapse:collapse; font-size:0.75rem;">
                                        @php
                                            $rows = $block['table']['rows'] ?? [];
                                            $hasHeader = $block['table']['header'] ?? true;
                                        @endphp
                                        @foreach ($rows as $rIdx => $row)
                                            <tr style="{{ $hasHeader && $rIdx === 0 ? 'background:var(--rb-surface-soft); font-weight:700; border-bottom:2px solid var(--rb-canvas-grid);' : 'border-bottom:1px solid var(--rb-canvas-grid);' }}">
                                                @foreach ($row as $cIdx => $cell)
                                                    <td style="padding:0.4rem; border-right:1px solid var(--rb-canvas-grid);">
                                                        <input type="text" value="{{ $cell['text'] ?? '' }}"
                                                            wire:blur="updateTableCell({{ $index }}, {{ $rIdx }}, {{ $cIdx }}, $event.target.value)"
                                                            style="width:100%; box-sizing:border-box; background:transparent; border:none; outline:none; font-size:0.75rem; padding:0.2rem;">
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>

                            <!-- BLOCK TYPE: PAGEBREAK -->
                            @elseif ($type === 'pagebreak')
                                <div style="margin:1.5rem 0; border-bottom:2px dashed var(--rb-canvas-grid); text-align:center; position:relative; user-select:none;">
                                    <span style="background:var(--rb-surface); padding:0 0.75rem; font-size:0.62rem; text-transform:uppercase; font-family:var(--rb-font-mono); color:var(--rb-ink-muted); position:relative; top:-8px;">
                                        ✂️ Pemisah Halaman
                                    </span>
                                </div>

                            <!-- BLOCK TYPE: PARAGRAPH (DEFAULT) -->
                            @else
                                <div style="position:relative;">
                                    @if (!empty($block['dropCap']))
                                        <div style="float:left; font-size:3.5rem; font-weight:900; font-family:var(--rb-font-serif); line-height:0.85; margin-right:0.6rem; margin-top:0.2rem; color:var(--rb-primary); user-select:none;">
                                            {{ mb_substr($block['text'] ?? '', 0, 1) }}
                                        </div>
                                    @endif
                                    <textarea rows="3" wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="ns-textarea-p"
                                        placeholder="Mulai menulis kalimat Anda di sini...">{{ $block['text'] ?? '' }}</textarea>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Empty State & Quick Insert Button -->
                <div style="margin-top:2rem; padding-top:1rem; border-top:1px solid var(--rb-canvas-grid); display:flex; justify-content:center;">
                    <button type="button" wire:click="addBlock('paragraph')"
                        class="ns-btn font-bold font-mono" style="padding:0.5rem 1rem; border:2px dashed var(--rb-ink-muted);">
                        <span>+ Tambah Paragraf Baru</span>
                    </button>
                </div>
            </div>
        </main>

        <!-- RIGHT SIDEBAR: COMMENTS & REVISIONS (LIBREOFFICE REVIEW PANEL) -->
        @if (($showComments || $showRevisions) && ! $zenMode)
            <aside class="ns-sidebar-right">
                @if ($showComments)
                    <div class="ns-sidebar-header" style="padding:0.65rem 0.85rem; background:var(--rb-surface-soft); margin-bottom:0;">
                        <span>💬 Komentar ({{ count($comments) }})</span>
                        <button type="button" wire:click="$set('showComments', false)" style="background:none; border:none; font-size:1.1rem; cursor:pointer;">&times;</button>
                    </div>
                    <div style="padding:0.75rem; display:flex; flex-direction:column; gap:0.5rem; border-bottom:1px solid var(--rb-canvas-grid);">
                        <div style="display:flex; gap:0.35rem;">
                            <input type="text" class="ns-btn" style="flex:1;" placeholder="Komentar blok #{{ $activeBlock + 1 }}..." wire:model="commentDraft" wire:keydown.enter="addComment">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="addComment">+</button>
                        </div>
                        @forelse ($comments as $cIdx => $c)
                            <div style="padding:0.5rem; border-radius:3px; border:1px solid {{ !empty($c['resolved']) ? 'var(--rb-canvas-grid)' : '#93c5fd' }}; background:{{ !empty($c['resolved']) ? 'var(--rb-surface-soft); opacity:0.6;' : 'rgba(37,99,235,0.05);' }}">
                                <div style="display:flex; justify-content:space-between; font-size:0.62rem; color:var(--rb-ink-muted); margin-bottom:0.25rem;">
                                    <strong>{{ $c['author'] ?? 'Penulis' }}</strong>
                                    <span>Blok #{{ ((int)($c['blockIndex'] ?? 0)) + 1 }}</span>
                                </div>
                                <p style="margin-bottom:0.4rem; line-height:1.4;">{{ $c['body'] ?? '' }}</p>
                                <div style="display:flex; justify-content:flex-end; gap:0.5rem; font-size:0.62rem;">
                                    <button type="button" wire:click="resolveComment({{ $cIdx }})" class="ns-btn" style="padding:0.1rem 0.35rem; color:var(--rb-primary);">
                                        {{ !empty($c['resolved']) ? 'Buka Kembali' : '✓ Selesai' }}
                                    </button>
                                    <button type="button" wire:click="deleteComment({{ $cIdx }})" class="ns-btn" style="padding:0.1rem 0.35rem; color:#dc2626;">Hapus</button>
                                </div>
                            </div>
                        @empty
                            <div style="text-align:center; color:var(--rb-ink-muted); font-style:italic; padding:1rem 0;">Belum ada komentar.</div>
                        @endforelse
                    </div>
                @endif

                @if ($showRevisions)
                    <div class="ns-sidebar-header" style="padding:0.65rem 0.85rem; background:var(--rb-surface-soft); margin-bottom:0;">
                        <span>👁️ Pelacakan Revisi ({{ $pendingRevisionCount }} pending)</span>
                        <button type="button" wire:click="$set('showRevisions', false)" style="background:none; border:none; font-size:1.1rem; cursor:pointer;">&times;</button>
                    </div>
                    <div style="padding:0.75rem; display:flex; flex-direction:column; gap:0.5rem;">
                        <div style="display:flex; gap:0.35rem; margin-bottom:0.5rem;">
                            <button type="button" wire:click="acceptAllRevisions" class="ns-btn ns-btn--primary" style="flex:1; justify-content:center;">✓ Terima Semua</button>
                            <button type="button" wire:click="rejectAllRevisions" class="ns-btn" style="flex:1; justify-content:center; color:#dc2626;">✕ Tolak Semua</button>
                        </div>
                        @forelse ($revisions as $rIdx => $rev)
                            @if (($rev['status'] ?? '') === 'pending')
                                <div style="padding:0.5rem; border-radius:3px; border:1px solid #f59e0b; background:rgba(245,158,11,0.06); font-size:0.68rem;">
                                    <div style="font-size:0.62rem; color:var(--rb-ink-muted); margin-bottom:0.25rem;">
                                        {{ $rev['author'] ?? 'Penulis' }} · Blok #{{ ((int)($rev['blockIndex'] ?? 0)) + 1 }}
                                    </div>
                                    <div style="display:flex; flex-direction:column; gap:0.25rem; margin-bottom:0.4rem;">
                                        <div style="text-decoration:line-through; color:#dc2626; background:#fee2e2; padding:0.25rem; border-radius:2px;">{{ $rev['before'] ?? '' }}</div>
                                        <div style="color:#059669; background:#d1fae5; padding:0.25rem; border-radius:2px; font-weight:600;">{{ $rev['after'] ?? '' }}</div>
                                    </div>
                                    <div style="display:flex; justify-content:flex-end; gap:0.5rem; font-size:0.62rem;">
                                        <button type="button" wire:click="acceptRevision({{ $rIdx }})" class="ns-btn" style="color:#059669; font-weight:700;">✓ Terima</button>
                                        <button type="button" wire:click="rejectRevision({{ $rIdx }})" class="ns-btn" style="color:#dc2626;">✕ Tolak</button>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div style="text-align:center; color:var(--rb-ink-muted); font-style:italic; padding:1rem 0;">Tidak ada revisi pending.</div>
                        @endforelse
                    </div>
                @endif
            </aside>
        @endif

        <!-- RIGHT SIDEBAR: AI CO-WRITER ASSISTANT -->
        <aside x-show="!zenMode && {{ $assistantOpen ? 'true' : 'false' }}" class="ns-sidebar-right">
            <div class="ns-sidebar-header" style="padding:0.65rem 0.85rem; background:var(--rb-primary); color:#ffffff; margin-bottom:0;">
                <span style="color:#ffffff;">🤖 XISHIE CO-WRITER</span>
                <button type="button" wire:click="closeAssistant" style="background:none; border:none; color:#ffffff; font-size:1.1rem; cursor:pointer;">&times;</button>
            </div>

            <!-- Assistant Tool Buttons -->
            <div style="padding:0.5rem; border-bottom:1px solid var(--rb-canvas-grid); display:flex; flex-wrap:wrap; gap:0.25rem; background:var(--rb-surface-soft);">
                <button type="button" wire:click="runTool('parafrase')" class="ns-btn font-mono" style="font-size:0.62rem;">/parafrase</button>
                <button type="button" wire:click="runTool('sitasi')" class="ns-btn font-mono" style="font-size:0.62rem;">/sitasi</button>
                <button type="button" wire:click="runTool('cek-pedoman')" class="ns-btn font-mono" style="font-size:0.62rem;">/cek-pedoman</button>
                <button type="button" wire:click="runTool('cek-gap')" class="ns-btn font-mono" style="font-size:0.62rem;">/cek-gap</button>
            </div>

            <!-- Messages Transcript -->
            <div style="flex:1; overflow-y:auto; padding:0.75rem; display:flex; flex-direction:column; gap:0.65rem;">
                @forelse ($assistantLog as $log)
                    <div style="padding:0.65rem; border-radius:3px; border:1px solid; {{ ($log['role'] ?? '') === 'user' ? 'background:var(--rb-accent-soft); border-color:#93c5fd; margin-left:1rem;' : 'background:var(--rb-surface-soft); border-color:var(--rb-canvas-grid); margin-right:1rem;' }}">
                        <strong style="display:block; font-size:0.62rem; text-transform:uppercase; font-family:var(--rb-font-mono); margin-bottom:0.25rem; color:var(--rb-ink-muted);">
                            {{ ($log['role'] ?? '') === 'user' ? 'Anda' : 'Asisten Xishie' }}
                        </strong>
                        <div style="line-height:1.5; white-space:pre-line;">{{ $log['content'] ?? '' }}</div>
                    </div>
                @empty
                    <div style="text-align:center; color:var(--rb-ink-muted); font-style:italic; padding:2rem 0;">
                        Ajukan pertanyaan atau minta bantuan penulisan naskah kepada asisten akademik.
                    </div>
                @endforelse
            </div>

            <!-- Chat Input -->
            <div style="padding:0.5rem; border-top:1px solid var(--rb-canvas-grid); background:var(--rb-surface);">
                <form wire:submit.prevent="sendAssistant" style="display:flex; gap:0.25rem;">
                    <input type="text" wire:model="assistantInput"
                        class="ns-btn" style="flex:1; font-size:0.75rem;"
                        placeholder="Tanya asisten atau ketik /perintah...">
                    <button type="submit" class="ns-btn ns-btn--primary font-mono font-bold">
                        Kirim
                    </button>
                </form>
            </div>
        </aside>

        <!-- Floating Assistant Trigger when closed -->
        <button type="button" wire:click="openAssistant" x-show="!zenMode && !{{ $assistantOpen ? 'true' : 'false' }}"
            class="ns-btn ns-btn--primary font-mono font-bold"
            style="position:fixed; bottom:2.5rem; right:1.5rem; z-index:30; padding:0.65rem 1rem; border-radius:24px; box-shadow:var(--rb-shadow); border:var(--rb-border);">
            <span>🤖 Asisten AI</span>
        </button>
    </div>

    <!-- ===================================================================== -->
    <!-- BOTTOM STATUS BAR (LIBREOFFICE WRITER STATUS BAR)                      -->
    <!-- ===================================================================== -->
    <footer class="ns-statusbar">
        <div style="display:flex; align-items:center; gap:1rem;">
            <span>Halaman 1 / 1</span>
            <span>{{ $blockCount }} blok</span>
            <span><strong>{{ number_format($wordCount) }}</strong> kata</span>
            <span>{{ number_format($charCount) }} karakter</span>
            <span>⏱️ ~{{ $statistics['readingTimeMinutes'] ?? 1 }}m baca</span>
            <span>🎤 ~{{ $statistics['speakingTimeMinutes'] ?? 1 }}m bicara</span>
        </div>
        <div style="display:flex; align-items:center; gap:0.75rem;">
            <span>{{ $lastSavedAt ? 'Disimpan: ' . $lastSavedAt : 'Belum disimpan' }}</span>
            <div style="display:flex; align-items:center; gap:0.25rem;">
                <button type="button" wire:click="zoomOut" class="ns-btn" style="padding:0.1rem 0.35rem;">-</button>
                <span style="font-weight:700;">{{ $zoom }}%</span>
                <button type="button" wire:click="zoomIn" class="ns-btn" style="padding:0.1rem 0.35rem;">+</button>
            </div>
        </div>
    </footer>

    <!-- ===================================================================== -->
    <!-- 18 UX KPIS & PRODUCT TELEMETRY INSPECTOR (HEART + AARRR DRAWER)        -->
    <!-- ===================================================================== -->
    <div x-show="kpiDrawer" class="ns-dialog-backdrop">
        <div class="ns-drawer-kpi">
            <!-- Header -->
            <div class="ns-dialog__header" style="padding:0.75rem 1rem;">
                <div>
                    <h2 style="font-weight:800; font-size:0.85rem; font-family:var(--rb-font-mono); letter-spacing:0.04em;">📊 18 UX &amp; PRODUCT METRICS INSPECTOR</h2>
                    <span style="font-size:0.68rem; opacity:0.8;">Google HEART Framework • AARRR Pirate Funnel • Usability KPIs</span>
                </div>
                <button type="button" wire:click="toggleKpiDrawer" style="font-size:1.4rem;">&times;</button>
            </div>

            <!-- Framework Tabs -->
            <div class="ns-ribbon-tabs" style="background:var(--rb-surface-soft);">
                <button type="button" wire:click="setKpiFramework('HEART')" class="ns-ribbon-tab {{ $kpiActiveFramework === 'HEART' ? 'active' : '' }}">
                    ❤️ Google HEART
                </button>
                <button type="button" wire:click="setKpiFramework('AARRR')" class="ns-ribbon-tab {{ $kpiActiveFramework === 'AARRR' ? 'active' : '' }}">
                    🏴‍☠️ AARRR Funnel
                </button>
                <button type="button" wire:click="setKpiFramework('UX')" class="ns-ribbon-tab {{ $kpiActiveFramework === 'UX' ? 'active' : '' }}">
                    🎯 Usability Specific
                </button>
                <button type="button" wire:click="setKpiFramework('RESOURCES')" class="ns-ribbon-tab {{ $kpiActiveFramework === 'RESOURCES' ? 'active' : '' }}">
                    📖 Panduan Desain UX
                </button>
            </div>

            <!-- Content Area -->
            <div class="ns-dialog__body" style="flex:1; max-height:none; padding:1rem;">
                @if ($kpiActiveFramework === 'RESOURCES')
                    <!-- Panduan & Referensi Metrik Produk -->
                    <div style="display:flex; flex-direction:column; gap:0.75rem;">
                        <div style="padding:0.85rem; background:var(--rb-accent-soft); border:1px solid #93c5fd; border-radius:3px;">
                            <strong style="color:var(--rb-primary); display:block; margin-bottom:0.35rem; font-family:var(--rb-font-mono);">Prinsip Pengukuran Perilaku Pengguna (UX Metrics That Matter)</strong>
                            <p style="font-size:0.72rem; line-height:1.5;">
                                Metrik UX bukan sekadar angka statistik, melainkan jembatan yang menghubungkan niat pengguna, respons antarmuka, dan tingkat keberhasilan tugas akademik sesuai kaidah 18 KPIs every UX designer should know.
                            </p>
                        </div>

                        <div style="display:flex; flex-direction:column; gap:0.5rem; font-family:var(--rb-font-mono); font-size:0.72rem;">
                            <a href="https://lnkd.in/dWGVK2Tp" target="_blank" rel="noopener noreferrer" style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; text-decoration:none; color:inherit; background:var(--rb-surface-soft); display:block;">
                                <strong style="color:var(--rb-primary); display:block;">1. Understanding Metrics (Comprehensive Guide) ↗</strong>
                                <span style="color:var(--rb-ink-muted); font-size:0.65rem;">Panduan mendalam menyusun taksonomi metrik kuantitatif dan kualitatif.</span>
                            </a>
                            <a href="https://lnkd.in/dfbfSg8T" target="_blank" rel="noopener noreferrer" style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; text-decoration:none; color:inherit; background:var(--rb-surface-soft); display:block;">
                                <strong style="color:var(--rb-primary); display:block;">2. Ultimate Guide to Product Metrics (57 Metrics) ↗</strong>
                                <span style="color:var(--rb-ink-muted); font-size:0.65rem;">Katalog lengkap 57 metrik produk digital untuk efisiensi dan adopsi.</span>
                            </a>
                            <a href="https://lnkd.in/dAS5bdx5" target="_blank" rel="noopener noreferrer" style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; text-decoration:none; color:inherit; background:var(--rb-surface-soft); display:block;">
                                <strong style="color:var(--rb-primary); display:block;">3. Product Metrics That Matter ↗</strong>
                                <span style="color:var(--rb-ink-muted); font-size:0.65rem;">Cara membedakan vanity metrics dari actionable behavioral signals.</span>
                            </a>
                            <a href="https://lnkd.in/dvA_si5X" target="_blank" rel="noopener noreferrer" style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; text-decoration:none; color:inherit; background:var(--rb-surface-soft); display:block;">
                                <strong style="color:var(--rb-primary); display:block;">4. AARRR Pirate Framework ↗</strong>
                                <span style="color:var(--rb-ink-muted); font-size:0.65rem;">Acquisition, Activation, Retention, Referral, dan Conversion.</span>
                            </a>
                            <a href="https://lnkd.in/dSNEHVRx" target="_blank" rel="noopener noreferrer" style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; text-decoration:none; color:inherit; background:var(--rb-surface-soft); display:block;">
                                <strong style="color:var(--rb-primary); display:block;">5. Google HEART Framework ↗</strong>
                                <span style="color:var(--rb-ink-muted); font-size:0.65rem;">Happiness, Engagement, Adoption, Retention, dan Task Success.</span>
                            </a>
                            <a href="https://lnkd.in/dsMA7AFH" target="_blank" rel="noopener noreferrer" style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; text-decoration:none; color:inherit; background:var(--rb-surface-soft); display:block;">
                                <strong style="color:var(--rb-primary); display:block;">6. UX Specific KPIs ↗</strong>
                                <span style="color:var(--rb-ink-muted); font-size:0.65rem;">Task completion rate, Time-on-task, error rate, dan CSAT/SUS.</span>
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Metric Cards List -->
                    @php
                        $filtered = array_filter($uxKpis, fn($m) => $kpiActiveFramework === 'UX' ? ($m['framework'] === 'UX') : ($m['framework'] === $kpiActiveFramework));
                    @endphp
                    @forelse ($filtered as $key => $metric)
                        <div style="padding:0.75rem; border-radius:3px; border:1px solid var(--rb-canvas-grid); background:var(--rb-surface-soft); display:flex; flex-direction:column; gap:0.35rem;">
                            <div style="display:flex; align-items:center; justify-content:space-between;">
                                <strong style="color:var(--rb-ink); font-size:0.75rem;">{{ $metric['label'] }}</strong>
                                <span class="ns-badge {{ $metric['healthy'] ? 'ns-badge--saved' : 'ns-badge--dirty' }}">
                                    {{ $metric['healthy'] ? '🟢 SEHAT' : '🟡 PERLU PERHATIAN' }}
                                </span>
                            </div>

                            <p style="font-size:0.68rem; color:var(--rb-ink-muted); line-height:1.4;">{{ $metric['description'] }}</p>

                            <div style="display:flex; align-items:center; justify-content:space-between; padding-top:0.35rem; border-top:1px solid var(--rb-canvas-grid); font-family:var(--rb-font-mono); font-size:0.68rem;">
                                <span style="color:var(--rb-ink-muted);">Nilai: <strong style="color:var(--rb-ink);">{{ $metric['value'] }} {{ $metric['unit'] }}</strong></span>
                                <span style="color:var(--rb-ink-muted);">Target: {{ $metric['target'] }} {{ $metric['unit'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div style="text-align:center; color:var(--rb-ink-muted); font-style:italic; padding:2rem 0;">Tidak ada metrik dalam kategori ini.</div>
                    @endforelse
                @endif
            </div>

            <!-- Footer: CSAT In-Drawer Quick Rate -->
            <div class="ns-dialog__footer" style="align-items:center; justify-content:space-between;">
                <span style="font-size:0.68rem; color:var(--rb-ink-muted);">Bagaimana pengalaman menulis Anda?</span>
                <div style="display:flex; gap:0.25rem;">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="rateCsat({{ $i }})" class="ns-btn font-bold font-mono">
                            {{ $i }} ⭐
                        </button>
                    @endfor
                </div>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- ALL 16 MODAL DIALOGS (LIBREOFFICE WRITER & WORDCRAFT FULL SUITE)       -->
    <!-- ===================================================================== -->
    @if ($dialog !== '')
        <div class="ns-dialog-backdrop">
            <div class="ns-dialog">

                {{-- DIALOG: CARI & GANTI (FIND & REPLACE) --}}
                @if ($dialog === 'find')
                    <div class="ns-dialog__header">
                        <span>🔍 Cari dan Ganti Teks</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Teks yang dicari:</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="findQuery" placeholder="Cari..." wire:keydown.enter="findText">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Ganti dengan:</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="replaceQuery" placeholder="Ganti...">
                        </div>
                        @if ($findResult)
                            <div style="padding:0.5rem; background:var(--rb-accent-soft); border:1px solid #93c5fd; border-radius:3px; font-family:var(--rb-font-mono); font-size:0.68rem;">
                                {{ $findResult }}
                            </div>
                        @endif
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="findText">Cari</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="replaceAll">Ganti Semua</button>
                    </div>

                {{-- DIALOG: STATISTIK & KETERBACAAN --}}
                @elseif ($dialog === 'stats')
                    <div class="ns-dialog__header">
                        <span>📊 Statistik Dokumen &amp; Skor Keterbacaan</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                            <div style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; background:var(--rb-surface-soft);">
                                <span style="font-size:0.6rem; color:var(--rb-ink-muted); font-family:var(--rb-font-mono); text-transform:uppercase;">JUMLAH KATA</span>
                                <div style="font-size:1.25rem; font-weight:800; color:var(--rb-primary);">{{ number_format($statistics['words'] ?? 0) }}</div>
                            </div>
                            <div style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; background:var(--rb-surface-soft);">
                                <span style="font-size:0.6rem; color:var(--rb-ink-muted); font-family:var(--rb-font-mono); text-transform:uppercase;">KARAKTER</span>
                                <div style="font-size:1.25rem; font-weight:800; color:var(--rb-primary);">{{ number_format($statistics['characters'] ?? 0) }}</div>
                            </div>
                            <div style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; background:var(--rb-surface-soft);">
                                <span style="font-size:0.6rem; color:var(--rb-ink-muted); font-family:var(--rb-font-mono); text-transform:uppercase;">FLESCH READING EASE</span>
                                <div style="font-size:1.25rem; font-weight:800; color:#059669;">{{ $statistics['readingEase'] ?? 0 }} / 100</div>
                            </div>
                            <div style="padding:0.65rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; background:var(--rb-surface-soft);">
                                <span style="font-size:0.6rem; color:var(--rb-ink-muted); font-family:var(--rb-font-mono); text-transform:uppercase;">GRADE LEVEL</span>
                                <div style="font-size:1.25rem; font-weight:800; color:#d97706;">Kelas {{ $statistics['gradeLevel'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div style="font-family:var(--rb-font-mono); font-size:0.68rem; display:flex; flex-direction:column; gap:0.25rem; padding-top:0.5rem; border-top:1px solid var(--rb-canvas-grid);">
                            <div>• Kalimat: {{ $statistics['sentences'] ?? 0 }}</div>
                            <div>• Paragraf: {{ $statistics['paragraphs'] ?? 0 }}</div>
                            <div>• Estimasi Waktu Baca: ~{{ $statistics['readingTimeMinutes'] ?? 1 }} menit</div>
                            <div>• Estimasi Waktu Bicara: ~{{ $statistics['speakingTimeMinutes'] ?? 1 }} menit</div>
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: PAGE SETUP & MARGINS --}}
                @elseif ($dialog === 'pagesetup')
                    <div class="ns-dialog__header">
                        <span>⚙️ Tata Letak Kertas &amp; Margin</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.5rem;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Ukuran Kertas:</label>
                                <select wire:change="updatePageSetup('size', $event.target.value)" class="ns-btn" style="width:100%;">
                                    <option value="A4" {{ ($pageSetup['size'] ?? '') === 'A4' ? 'selected' : '' }}>A4</option>
                                    <option value="Letter" {{ ($pageSetup['size'] ?? '') === 'Letter' ? 'selected' : '' }}>Letter</option>
                                    <option value="Legal" {{ ($pageSetup['size'] ?? '') === 'Legal' ? 'selected' : '' }}>Legal</option>
                                    <option value="A5" {{ ($pageSetup['size'] ?? '') === 'A5' ? 'selected' : '' }}>A5</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Orientasi:</label>
                                <select wire:change="updatePageSetup('orientation', $event.target.value)" class="ns-btn" style="width:100%;">
                                    <option value="portrait" {{ ($pageSetup['orientation'] ?? '') === 'portrait' ? 'selected' : '' }}>Tegak (Portrait)</option>
                                    <option value="landscape" {{ ($pageSetup['orientation'] ?? '') === 'landscape' ? 'selected' : '' }}>Mendatar (Landscape)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Margin Atas (mm):</label>
                                <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginTop">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Margin Bawah (mm):</label>
                                <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginBottom">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Margin Kiri (mm):</label>
                                <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginLeft">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Margin Kanan (mm):</label>
                                <input type="number" class="ns-btn" style="width:100%;" wire:model="pageSetup.marginRight">
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Tanda Air (Watermark):</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="pageSetup.watermark" placeholder="cth: DRAF RAHASIA">
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="markDirty; closeDialog()">Terapkan</button>
                    </div>

                {{-- DIALOG: SPELL CHECK --}}
                @elseif ($dialog === 'spelling')
                    <div class="ns-dialog__header">
                        <span>✓ Pemeriksaan Ejaan</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <p style="line-height:1.6;">{{ $spellResult ?: 'Ejaan pada dokumen Anda telah diperiksa dan sesuai pedoman.' }}</p>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: THESAURUS --}}
                @elseif ($dialog === 'thesaurus')
                    <div class="ns-dialog__header">
                        <span>📖 Tesaurus Kata Akademik</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div style="display:flex; gap:0.35rem;">
                            <input type="text" class="ns-btn" style="flex:1;" wire:model="thesaurusWord" placeholder="Masukkan kata..." wire:keydown.enter="runThesaurus">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="runThesaurus">Cari</button>
                        </div>
                        @if ($thesaurusResult)
                            <div style="padding:0.5rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; background:var(--rb-surface-soft); font-size:0.72rem;">
                                {{ $thesaurusResult }}
                            </div>
                        @endif
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: SISIPKAN TABEL --}}
                @elseif ($dialog === 'table')
                    <div class="ns-dialog__header">
                        <span>⊞ Sisipkan Tabel Baru</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem;">
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Jumlah Baris:</label>
                                <input type="number" min="1" max="25" class="ns-btn" style="width:100%;" wire:model="tableRows">
                            </div>
                            <div>
                                <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Jumlah Kolom:</label>
                                <input type="number" min="1" max="10" class="ns-btn" style="width:100%;" wire:model="tableCols">
                            </div>
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="insertTable; closeDialog()">Sisipkan</button>
                    </div>

                {{-- DIALOG: SISIPKAN GAMBAR --}}
                @elseif ($dialog === 'image')
                    <div class="ns-dialog__header">
                        <span>🖼️ Sisipkan Gambar</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Unggah Berkas Gambar:</label>
                            <input type="file" wire:model="imageUpload" accept="image/*" class="ns-btn" style="width:100%;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Keterangan / Alt:</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="imageAlt" placeholder="Keterangan gambar...">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Lebar Tampilan (px):</label>
                            <input type="number" class="ns-btn" style="width:100%;" wire:model="imageWidth" value="320">
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: SISIPKAN TAUTAN --}}
                @elseif ($dialog === 'link')
                    <div class="ns-dialog__header">
                        <span>🔗 Sisipkan Tautan (Hyperlink)</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">URL Tujuan:</label>
                            <input type="url" class="ns-btn" style="width:100%;" wire:model="linkUrl" placeholder="https://example.com">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Teks Tampilan:</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="linkText" placeholder="Teks yang diklik...">
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="insertLink">Sisipkan</button>
                    </div>

                {{-- DIALOG: CATATAN KAKI (FOOTNOTE) --}}
                @elseif ($dialog === 'footnote')
                    <div class="ns-dialog__header">
                        <span>⚓ Sisipkan Catatan Kaki</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Isi Catatan Kaki:</label>
                            <textarea rows="3" class="ns-btn" style="width:100%; font-family:var(--rb-font-serif);" wire:model="footnoteText" placeholder="Ketik keterangan catatan kaki..."></textarea>
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="insertFootnote">Sisipkan</button>
                    </div>

                {{-- DIALOG: SIMBOL KHUSUS --}}
                @elseif ($dialog === 'symbols')
                    <div class="ns-dialog__header">
                        <span>Ω Simbol Khusus Tipografi</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div style="display:grid; grid-template-columns:repeat(8, 1fr); gap:0.35rem; font-family:var(--rb-font-serif); font-size:1.15rem; text-align:center;">
                            @foreach (['©', '®', '™', '§', '¶', '±', '×', '÷', '≠', '≤', '≥', '≈', '∞', '°', 'µ', 'α', 'β', 'γ', 'π', '∑', '√', '—', '–', '•', '€', '£', '¥', '→'] as $sym)
                                <button type="button" class="ns-btn" style="justify-content:center; padding:0.4rem;" wire:click="insertSymbol('{{ $sym }}'); closeDialog()">
                                    {{ $sym }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: MAIL MERGE CSV --}}
                @elseif ($dialog === 'merge')
                    <div class="ns-dialog__header">
                        <span>✉️ Konfigurasi Mail Merge CSV</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Data CSV (Baris 1 = Header):</label>
                            <textarea rows="4" class="ns-btn" style="width:100%; font-family:var(--rb-font-mono); font-size:0.68rem;" wire:model="mergeCsv" placeholder="nama,instansi&#10;Budi,Universitas Indonesia&#10;Siti,ITB"></textarea>
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Template Dokumen (Gunakan @{{nama}}):</label>
                            <textarea rows="2" class="ns-btn" style="width:100%;" wire:model="mergeTemplate" placeholder="Yth. @{{nama}} di @{{instansi}}..."></textarea>
                        </div>
                        @if (!empty($mergePreviews))
                            <div style="padding:0.5rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; background:var(--rb-surface-soft); font-size:0.68rem;">
                                <strong>Pratinjau Rekaman 1:</strong>
                                <div>{{ $mergePreviews[0]['text'] ?? '' }}</div>
                            </div>
                        @endif
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="previewMerge">Pratinjau</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="insertMerge">Sisipkan ke Naskah</button>
                    </div>

                {{-- DIALOG: TAMBAH KOMENTAR --}}
                @elseif ($dialog === 'comment')
                    <div class="ns-dialog__header">
                        <span>💬 Tambah Komentar pada Blok #{{ $activeBlock + 1 }}</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <textarea rows="3" class="ns-btn" style="width:100%;" wire:model="commentDraft" placeholder="Tulis komentar..."></textarea>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Batal</button>
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="addComment; closeDialog()">Simpan</button>
                    </div>

                {{-- DIALOG: COMMAND PALETTE HUD (WORDCRAFT) --}}
                @elseif ($dialog === 'palette')
                    <div class="ns-dialog__header">
                        <span>⚡ Wordcraft Command Palette (HUD)</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <input type="text" class="ns-btn" style="width:100%; font-family:var(--rb-font-mono); font-size:0.75rem; padding:0.5rem;"
                            placeholder="Ketik perintah (cth: auto-correct, table, paragraph, dark mode)..."
                            wire:model.live="commandSearch" autofocus>
                        <div style="max-height:240px; overflow-y:auto; display:flex; flex-direction:column; gap:0.25rem; font-family:var(--rb-font-mono); font-size:0.72rem;">
                            @php
                                $q = strtolower(trim($commandSearch));
                                $filteredCmds = array_filter($commands, static fn(array $c): bool => $q === '' || str_contains(strtolower($c['label']), $q) || str_contains(strtolower($c['category']), $q) || str_contains(strtolower($c['id']), $q));
                            @endphp
                            @forelse ($filteredCmds as $cmd)
                                <button type="button" class="ns-btn" style="justify-content:space-between; width:100%; padding:0.4rem 0.6rem;"
                                    wire:click="executeCommand('{{ $cmd['id'] }}'); closeDialog()">
                                    <div style="display:flex; align-items:center; gap:0.5rem;">
                                        <span>{{ $cmd['icon'] }}</span>
                                        <strong>{{ $cmd['label'] }}</strong>
                                        <span class="ns-badge" style="font-size:0.58rem; background:var(--rb-surface-soft);">{{ $cmd['category'] }}</span>
                                    </div>
                                    @if (!empty($cmd['shortcut']))
                                        <kbd style="padding:1px 4px; border:1px solid var(--rb-canvas-grid); border-radius:2px; font-size:0.6rem;">{{ $cmd['shortcut'] }}</kbd>
                                    @endif
                                </button>
                            @empty
                                <div style="color:var(--rb-ink-muted); font-style:italic; padding:1rem 0; text-align:center;">Tidak ada perintah yang sesuai.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: PROPERTI DOKUMEN & METADATA --}}
                @elseif ($dialog === 'properties')
                    <div class="ns-dialog__header">
                        <span>ℹ️ Properti Dokumen &amp; Metadata</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Judul Dokumen:</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="docTitle" wire:change="markDirty">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Penulis (Author):</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="metadata.author" wire:change="markDirty" placeholder="Nama penulis...">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Subjek / Topik:</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="metadata.subject" wire:change="markDirty" placeholder="Topik bahasan...">
                        </div>
                        <div>
                            <label style="display:block; font-weight:700; margin-bottom:0.25rem;">Kata Kunci (Keywords):</label>
                            <input type="text" class="ns-btn" style="width:100%;" wire:model="metadata.keywords" wire:change="markDirty" placeholder="Kata kunci...">
                        </div>
                        <div style="padding-top:0.5rem; border-top:1px solid var(--rb-canvas-grid); font-family:var(--rb-font-mono); font-size:0.68rem; color:var(--rb-ink-muted); display:flex; flex-direction:column; gap:0.25rem;">
                            <div>• Total Kata: {{ number_format($wordCount) }}</div>
                            <div>• Total Karakter: {{ number_format($charCount) }}</div>
                            <div>• Total Blok: {{ $blockCount }}</div>
                            <div>• Terakhir Disimpan: {{ $lastSavedAt ?: 'Belum disimpan' }}</div>
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="save; closeDialog()">Simpan Properti</button>
                    </div>

                {{-- DIALOG: SNAPSHOTS (VERSI DOKUMEN) --}}
                @elseif ($dialog === 'snapshots')
                    <div class="ns-dialog__header">
                        <span>📁 Riwayat Versi &amp; Snapshot Naskah</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <div style="display:flex; gap:0.35rem; margin-bottom:0.5rem;">
                            <input type="text" class="ns-btn" style="flex:1;" placeholder="Label versi baru..." wire:model="snapshotLabel">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="createSnapshot(snapshotLabel)">+ Simpan</button>
                        </div>
                        <div style="max-height:240px; overflow-y:auto; display:flex; flex-direction:column; gap:0.35rem;">
                            @forelse ($snapshots as $sIdx => $snap)
                                <div style="padding:0.5rem; border:1px solid var(--rb-canvas-grid); border-radius:3px; display:flex; justify-content:space-between; align-items:center; background:var(--rb-surface-soft);">
                                    <div>
                                        <strong>{{ $snap['label'] }}</strong>
                                        <div style="font-size:0.62rem; color:var(--rb-ink-muted); font-family:var(--rb-font-mono);">{{ $snap['at'] }} · {{ $snap['count'] }} blok</div>
                                    </div>
                                    <div style="display:flex; gap:0.25rem;">
                                        <button type="button" wire:click="restoreSnapshot({{ $sIdx }})" class="ns-btn" style="color:var(--rb-primary);">↩ Pulihkan</button>
                                        <button type="button" wire:click="deleteSnapshot({{ $sIdx }})" class="ns-btn" style="color:#dc2626;">✕</button>
                                    </div>
                                </div>
                            @empty
                                <div style="color:var(--rb-ink-muted); font-style:italic; text-align:center; padding:1.5rem 0;">Belum ada snapshot versi tersimpan.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- DIALOG: KEYBOARD SHORTCUTS --}}
                @elseif ($dialog === 'shortcuts')
                    <div class="ns-dialog__header">
                        <span>⌨️ Tombol Pintas Papan Ketik (Keyboard Shortcuts)</span>
                        <button type="button" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="ns-dialog__body">
                        <table style="width:100%; font-size:0.72rem; font-family:var(--rb-font-mono); border-collapse:collapse;">
                            <thead style="border-bottom:1px solid var(--rb-canvas-grid);">
                                <tr style="text-align:left; color:var(--rb-ink-muted);">
                                    <th style="padding:0.35rem;">Kombinasi</th>
                                    <th style="padding:0.35rem;">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + S</td><td style="padding:0.35rem;">Simpan Naskah</td></tr>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + Z</td><td style="padding:0.35rem;">Urungkan Perubahan (Undo)</td></tr>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + Y</td><td style="padding:0.35rem;">Ulangi Perubahan (Redo)</td></tr>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + K</td><td style="padding:0.35rem;">Pusat Perintah (Command Palette HUD)</td></tr>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + F</td><td style="padding:0.35rem;">Cari dan Ganti Teks</td></tr>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + B</td><td style="padding:0.35rem;">Format Huruf Tebal (Bold)</td></tr>
                                <tr style="border-bottom:1px solid var(--rb-canvas-grid);"><td style="padding:0.35rem; font-weight:700;">Ctrl + I</td><td style="padding:0.35rem;">Format Huruf Miring (Italic)</td></tr>
                                <tr><td style="padding:0.35rem; font-weight:700;">Ctrl + U</td><td style="padding:0.35rem;">Format Garis Bawah (Underline)</td></tr>
                            </tbody>
                        </table>
                    </div>
                    <div class="ns-dialog__footer">
                        <button type="button" class="ns-btn ns-btn--primary" wire:click="closeDialog">Tutup</button>
                    </div>
                @endif

            </div>
        </div>
    @endif
</div>
