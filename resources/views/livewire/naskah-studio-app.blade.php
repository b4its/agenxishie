<div class="flex-1 flex flex-col min-h-screen {{ $darkMode ? 'dark bg-slate-950 text-slate-100' : 'bg-slate-100 text-slate-900' }} font-sans selection:bg-blue-200 selection:text-blue-900"
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

    <style>
        .ns-btn {
            border: 1px solid #cbd5e1;
            background: #ffffff;
            color: #0f172a;
            font-size: .68rem;
            font-weight: 600;
            padding: .25rem .5rem;
            border-radius: 3px;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            line-height: 1.2;
            transition: all .12s;
        }
        .dark .ns-btn {
            background: #1e293b;
            color: #f8fafc;
            border-color: #334155;
        }
        .ns-btn:hover { background: #f1f5f9; border-color: #94a3b8; }
        .dark .ns-btn:hover { background: #334155; border-color: #64748b; }
        .ns-btn--primary { background: #1e40af; color: #ffffff; border-color: #1e40af; }
        .ns-btn--primary:hover { background: #1d4ed8; }
        .dark .ns-btn--primary { background: #2563eb; border-color: #2563eb; }
        .ns-btn--active { background: #eff6ff; color: #1d4ed8; border-color: #3b82f6; font-weight: 700; }
        .dark .ns-btn--active { background: #1e3a8a; color: #bfdbfe; border-color: #60a5fa; }
        .ns-group {
            display: flex;
            flex-direction: column;
            gap: .25rem;
            padding-right: .65rem;
            border-right: 1px solid #e2e8f0;
        }
        .dark .ns-group { border-right-color: #334155; }
        .ns-group:last-child { border-right: none; }
        .ns-group__label {
            font-family: var(--rb-font-mono, monospace);
            font-size: .55rem;
            letter-spacing: .06em;
            text-transform: uppercase;
            color: #64748b;
            font-weight: 700;
        }
        .dark .ns-group__label { color: #94a3b8; }
        .ns-group__row { display: flex; gap: .25rem; flex-wrap: wrap; align-items: center; }

        .ns-dialog-backdrop {
            position: fixed; inset: 0; z-index: 50;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(2px);
            display: flex; align-items: center; justify-content: center;
            padding: 1rem;
        }
        .ns-dialog {
            background: #ffffff;
            border: 2px solid #0f172a;
            box-shadow: 6px 6px 0px #0f172a;
            width: 100%; max-width: 540px;
            border-radius: 4px;
            overflow: hidden;
            display: flex; flex-direction: column;
            font-size: .75rem;
        }
        .dark .ns-dialog {
            background: #0f172a;
            border-color: #94a3b8;
            box-shadow: 6px 6px 0px #020617;
            color: #f8fafc;
        }
        .ns-dialog__header {
            background: #1e40af; color: #ffffff;
            padding: .6rem .85rem;
            display: flex; justify-content: space-between; align-items: center;
            font-family: var(--rb-font-mono, monospace);
            font-weight: 700; font-size: .75rem;
        }
        .dark .ns-dialog__header { background: #1e3a8a; }
        .ns-dialog__body {
            padding: 1rem;
            max-height: 70vh;
            overflow-y: auto;
            display: flex; flex-direction: column; gap: .75rem;
        }
        .ns-dialog__footer {
            padding: .6rem .85rem;
            border-top: 1px solid #e2e8f0;
            display: flex; justify-content: flex-end; gap: .5rem;
            background: #f8fafc;
        }
        .dark .ns-dialog__footer {
            background: #1e293b;
            border-top-color: #334155;
        }
    </style>

    <!-- ===================================================================== -->
    <!-- TOP UX & PRODUCT TELEMETRY BAR (18 KPIS EVERY UX DESIGNER SHOULD KNOW) -->
    <!-- ===================================================================== -->
    <header class="border-b-2 border-slate-900 bg-white dark:bg-slate-900 shadow-sm z-30 sticky top-0 px-4 py-2 flex flex-wrap items-center justify-between gap-3 text-xs">
        <!-- Brand & Document Title -->
        <div class="flex items-center space-x-3">
            <a href="{{ route('home') }}" class="font-extrabold tracking-wider font-mono px-2 py-1 bg-blue-700 text-white border-2 border-slate-900 shadow-[2px_2px_0px_#0f172a] hover:bg-blue-800 transition">
                NASKAH STUDIO
            </a>
            <input type="text" wire:model.live.debounce.400ms="docTitle" wire:change="markDirty"
                class="font-semibold text-sm border-b border-transparent hover:border-slate-400 focus:border-blue-600 focus:outline-none bg-transparent px-1 py-0.5 max-w-xs sm:max-w-md truncate"
                placeholder="Judul Naskah...">
            <span class="text-[10px] uppercase font-mono px-1.5 py-0.5 rounded border {{ $dirty ? 'border-amber-400 bg-amber-50 text-amber-900 dark:bg-amber-950/40 dark:text-amber-200' : 'border-emerald-400 bg-emerald-50 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' }}">
                {{ $dirty ? '● Belum Simpan' : '✓ Tersimpan' }}
            </span>
        </div>

        <!-- 18 UX KPIs Live Gauges & Usability Telemetry -->
        <div class="flex items-center flex-wrap gap-2 text-[11px] font-mono">
            <!-- Time on Task & Active Session Depth (Efficiency & Engagement) -->
            <div class="flex items-center space-x-1.5 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-300 dark:border-slate-700" title="Time on Task / Kedalaman Sesi Aktif">
                <span class="text-blue-600 dark:text-blue-400 font-bold">⏱️ Waktu Sesi:</span>
                <span>{{ floor($sessionSeconds / 60) }}m {{ $sessionSeconds % 60 }}s</span>
            </div>

            <!-- Guideline Compliance Meter (Task Success) -->
            <button type="button" wire:click="toggleKpiDrawer('UX')" class="flex items-center space-x-1.5 px-2 py-1 rounded border transition {{ $complianceAudit['score'] >= 85 ? 'bg-emerald-50 text-emerald-900 border-emerald-300 dark:bg-emerald-950/40 dark:text-emerald-300' : 'bg-amber-50 text-amber-900 border-amber-300 dark:bg-amber-950/40 dark:text-amber-300' }}" title="Kepatuhan Pedoman Ilmiah">
                <span>🎯 Kepatuhan:</span>
                <strong>{{ $complianceAudit['score'] }}%</strong>
                <span class="text-[10px] opacity-75">({{ $complianceAudit['grade'] }})</span>
            </button>

            <!-- Verified Citation Density (Task Quality) -->
            <div class="hidden sm:flex items-center space-x-1 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-300 dark:border-slate-700" title="Kepadatan Sitasi per 1.000 kata">
                <span>📚 Sitasi:</span>
                <span class="font-bold text-slate-900 dark:text-slate-100">
                    {{ round(($statistics['words'] > 0 ? (($elements['footnotes'] ?? 0) / max(1, $statistics['words'])) * 1000 : 0), 1) }}
                </span>
                <span class="text-[10px] text-slate-500">/1k</span>
            </div>

            <!-- Interactive CSAT / Happiness Rating (HEART - Happiness) -->
            <div class="flex items-center space-x-1 bg-amber-50 dark:bg-amber-950/30 px-2 py-1 rounded border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200">
                <span class="text-[10px] font-sans font-bold">CSAT:</span>
                <div class="flex items-center space-x-0.5">
                    @for ($s = 1; $s <= 5; $s++)
                        <button type="button" wire:click="rateCsat({{ $s }})" class="hover:scale-125 transition {{ $csatScore >= $s ? 'text-amber-500 font-bold' : 'text-slate-300 dark:text-slate-600' }}" title="Beri Rating {{ $s }}/5">
                            ★
                        </button>
                    @endfor
                </div>
                @if ($csatSubmitted)
                    <span class="text-[9px] text-emerald-600 font-bold ml-0.5">✓</span>
                @endif
            </div>

            <!-- Latency / TTFT (Performance) -->
            <div class="hidden md:flex items-center space-x-1 bg-slate-100 dark:bg-slate-800 px-2 py-1 rounded border border-slate-300 dark:border-slate-700 text-slate-600 dark:text-slate-300">
                <span class="text-emerald-500">●</span>
                <span>TTFT: 32ms</span>
            </div>

            <!-- 18 UX KPIs HUD Drawer Trigger -->
            <button type="button" wire:click="toggleKpiDrawer" class="bg-blue-600 hover:bg-blue-700 text-white font-bold px-2.5 py-1 rounded border border-blue-800 shadow-sm transition flex items-center space-x-1">
                <span>📊 18 UX KPIs</span>
            </button>

            <!-- Zen Mode Toggle -->
            <button type="button" wire:click="toggleZenMode" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Fokus Tanpa Gangguan">
                {{ $zenMode ? 'Exit Zen' : '🧘 Zen' }}
            </button>

            <!-- Dark Mode Toggle -->
            <button type="button" wire:click="toggleDarkMode" class="px-2 py-1 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-100 dark:hover:bg-slate-800 transition" title="Ganti Mode Terang/Gelap">
                {{ $darkMode ? '☀️ Terang' : '🌙 Gelap' }}
            </button>
        </div>
    </header>

    <!-- ===================================================================== -->
    <!-- RIBBON TOOLBAR (WORDCRAFT + LIBREOFFICE WRITER TABS)                   -->
    <!-- ===================================================================== -->
    <div x-show="!zenMode" class="bg-slate-50 dark:bg-slate-900 border-b-2 border-slate-900 z-20 transition-all duration-200">
        <!-- Ribbon Tab Headers -->
        <div class="flex items-center space-x-1 px-4 pt-1 border-b border-slate-200 dark:border-slate-800 text-xs font-semibold overflow-x-auto">
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
                    class="px-3 py-1.5 border-b-2 transition {{ $activeTab === $key ? 'border-blue-600 text-blue-700 dark:text-blue-400 font-bold bg-white dark:bg-slate-800' : 'border-transparent text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-slate-200' }}">
                    {{ $label }}
                </button>
            @endforeach

            <!-- Wordcraft Command Palette Trigger -->
            <button type="button" wire:click="openDialog('palette')" class="ml-auto font-mono text-[11px] px-2.5 py-1 rounded bg-slate-200 dark:bg-slate-800 hover:bg-slate-300 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-slate-700 flex items-center space-x-1">
                <span>Perintah HUD</span>
                <kbd class="px-1 py-0.2 bg-white dark:bg-slate-900 border rounded text-[9px]">Ctrl+K</kbd>
            </button>
        </div>

        <!-- Ribbon Toolbar Commands Bar -->
        <div class="p-2 px-4 flex flex-wrap items-center gap-3 text-xs bg-white dark:bg-slate-850">
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
                        <select wire:model.live="fontFamily" class="border rounded px-2 py-0.5 text-xs bg-white dark:bg-slate-800 dark:border-slate-700">
                            @foreach ($fontFamilies as $k => $fontLabel)
                                <option value="{{ $k }}">{{ $fontLabel }}</option>
                            @endforeach
                        </select>
                        <select wire:model.live="fontSize" class="border rounded px-2 py-0.5 text-xs bg-white dark:bg-slate-800 dark:border-slate-700">
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
                        <button type="button" class="ns-btn text-red-600" wire:click="clearFormatting" title="Hapus Format">✕</button>
                    </div>
                </div>

                <!-- Warna & Sorotan -->
                <div class="ns-group">
                    <span class="ns-group__label">Warna &amp; Sorotan</span>
                    <div class="ns-group__row">
                        @foreach ($colors as $color)
                            <button type="button" style="width:16px; height:16px; border-radius:2px; border:1px solid #cbd5e1; background:{{ $color }};"
                                wire:click="formatInline('color', '{{ $color }}')" title="Warna {{ $color }}"></button>
                        @endforeach
                        <span class="border-l border-slate-300 dark:border-slate-700 h-4 mx-0.5"></span>
                        @foreach ($highlights as $hl)
                            <button type="button" style="width:16px; height:16px; border-radius:2px; border:1px solid #cbd5e1; background:{{ $hl }};"
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
                        <button type="button" class="ns-btn text-emerald-700" wire:click="acceptAllRevisions">✓ Terima Semua</button>
                        <button type="button" class="ns-btn text-red-600" wire:click="rejectAllRevisions">✕ Tolak Semua</button>
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
                        <span class="font-mono font-bold text-xs">{{ $zoom }}%</span>
                        <button type="button" class="ns-btn" wire:click="openDialog('stats')">📊 Statistik Keterbacaan</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('properties')">ℹ️ Properti Dokumen</button>
                        <button type="button" class="ns-btn" wire:click="openDialog('shortcuts')">⌨️ Tombol Pintas</button>
                    </div>
                </div>
            @endif

            <!-- Quick Multi-Format Export Buttons (LibreOffice Multi-Format Engine) -->
            <div class="ml-auto flex items-center space-x-1 pl-3 border-l border-slate-300 dark:border-slate-700">
                <span class="text-[10px] uppercase font-mono font-bold text-slate-500">Ekspor:</span>
                <button type="button" wire:click="export('odt')" class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-emerald-600 hover:bg-emerald-700 text-white" title="Unduh OpenDocument (.odt)">ODT</button>
                <button type="button" wire:click="export('docx')" class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-blue-600 hover:bg-blue-700 text-white" title="Unduh Microsoft Word (.docx)">DOCX</button>
                <button type="button" wire:click="export('latex')" class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-purple-600 hover:bg-purple-700 text-white" title="Unduh LaTeX (.tex)">LaTeX</button>
                <button type="button" wire:click="export('md')" class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-slate-700 hover:bg-slate-800 text-white" title="Unduh Markdown (.md)">MD</button>
                <button type="button" wire:click="export('rtf')" class="px-2 py-0.5 rounded font-bold font-mono text-[11px] bg-amber-600 hover:bg-amber-700 text-white" title="Unduh Rich Text (.rtf)">RTF</button>
                <button type="button" wire:click="save" class="ml-2 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded border border-emerald-900 shadow-sm flex items-center space-x-1">
                    <span>💾 Simpan</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- HORIZONTAL RULER (LIBREOFFICE RULER)                                   -->
    <!-- ===================================================================== -->
    @if ($showRuler && ! $zenMode)
        <div class="bg-slate-200 dark:bg-slate-800 border-b border-slate-300 dark:border-slate-700 h-6 flex items-center px-8 font-mono text-[9px] text-slate-600 dark:text-slate-400 select-none overflow-hidden">
            <div class="w-full flex justify-between items-center relative">
                @for ($cm = 0; $cm <= 20; $cm++)
                    <div class="flex flex-col items-center">
                        <div class="h-2 w-px bg-slate-400 dark:bg-slate-600"></div>
                        <span>{{ $cm }}</span>
                    </div>
                @endfor
            </div>
        </div>
    @endif

    <!-- ===================================================================== -->
    <!-- MAIN WORKSPACE: NAVIGATOR, CANVAS, DRAWERS, ASSISTANT                 -->
    <!-- ===================================================================== -->
    <div class="flex-1 flex overflow-hidden relative">
        <!-- LEFT SIDEBAR: NAVIGATOR (LIBREOFFICE NAVIGATOR & OUTLINE) -->
        <aside x-show="!zenMode && {{ $showNavigation ? 'true' : 'false' }}"
            class="w-72 bg-white dark:bg-slate-900 border-r-2 border-slate-900 flex flex-col shrink-0 overflow-y-auto p-3 text-xs z-10">
            <div class="flex items-center justify-between pb-2 border-b border-slate-200 dark:border-slate-800 mb-3">
                <span class="font-bold uppercase tracking-wider font-mono text-slate-700 dark:text-slate-300">🧭 Navigator Dokumen</span>
                <button type="button" wire:click="$set('showNavigation', false)" class="text-slate-400 hover:text-slate-600">&times;</button>
            </div>

            <!-- Outline / Headings -->
            <div class="mb-4">
                <span class="font-bold text-[11px] uppercase tracking-wide text-blue-700 dark:text-blue-400 block mb-1">Struktur &amp; Judul ({{ $elements['headings'] ?? 0 }})</span>
                <div class="space-y-1">
                    @forelse ($outline as $item)
                        <button type="button" wire:click="selectBlock({{ $item['index'] }})"
                            class="w-full text-left truncate py-1 px-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 transition {{ $activeBlock === $item['index'] ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 font-semibold' : '' }}"
                            style="padding-left: {{ ($item['level'] - 1) * 12 + 6 }}px">
                            {{ $item['text'] ?: '(Judul Tanpa Teks)' }}
                        </button>
                    @empty
                        <span class="text-slate-400 italic text-[11px]">Belum ada judul. Sisipkan Heading untuk membentuk kerangka.</span>
                    @endforelse
                </div>
            </div>

            <!-- Statistics Summary -->
            <div class="mb-4 p-2.5 bg-slate-50 dark:bg-slate-800 rounded border border-slate-200 dark:border-slate-700 font-mono text-[11px] space-y-1">
                <div class="flex justify-between">
                    <span class="text-slate-500">Kata:</span>
                    <strong class="text-slate-900 dark:text-slate-100">{{ number_format($statistics['words'] ?? 0) }}</strong>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Karakter:</span>
                    <span>{{ number_format($statistics['characters'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Kalimat:</span>
                    <span>{{ number_format($statistics['sentences'] ?? 0) }}</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Waktu Baca:</span>
                    <span>{{ $statistics['readingTimeMinutes'] ?? 1 }} menit</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Flesch Ease:</span>
                    <span>{{ $statistics['readingEase'] ?? 0 }}</span>
                </div>
            </div>

            <!-- Version Snapshots List -->
            <div>
                <div class="flex items-center justify-between mb-1">
                    <span class="font-bold text-[11px] uppercase tracking-wide text-slate-700 dark:text-slate-300">Versi Dokumen ({{ count($snapshots) }})</span>
                    <button type="button" wire:click="openDialog('snapshots')" class="text-blue-600 hover:underline text-[10px]">+ Simpan</button>
                </div>
                <div class="space-y-1">
                    @forelse ($snapshots as $idx => $snap)
                        <div class="p-1.5 rounded border border-slate-200 dark:border-slate-800 flex items-center justify-between text-[11px]">
                            <div>
                                <span class="font-semibold block truncate max-w-[120px]">{{ $snap['label'] }}</span>
                                <span class="text-[9px] text-slate-400 font-mono">{{ $snap['at'] }}</span>
                            </div>
                            <div class="flex space-x-1">
                                <button type="button" wire:click="restoreSnapshot({{ $idx }})" class="text-blue-600 hover:underline" title="Pulihkan Versi Ini">↩</button>
                                <button type="button" wire:click="deleteSnapshot({{ $idx }})" class="text-red-500 hover:underline" title="Hapus">&times;</button>
                            </div>
                        </div>
                    @empty
                        <span class="text-slate-400 italic text-[11px]">Belum ada versi snapshot.</span>
                    @endforelse
                </div>
            </div>
        </aside>

        <!-- CENTER CANVAS: PAPER VIEW -->
        <main class="flex-1 overflow-y-auto p-4 sm:p-8 flex justify-center bg-slate-200/70 dark:bg-slate-950 transition-all duration-200">
            <div class="bg-white dark:bg-slate-900 shadow-[8px_8px_0px_#0f172a] border-2 border-slate-900 w-full max-w-4xl min-h-[900px] p-8 sm:p-14 relative flex flex-col transition-all duration-200"
                style="background-color: {{ $pageSetup['pageColor'] ?? '#ffffff' }}; font-family: {{ ($fontFamily === 'serif' ? 'var(--rb-font-serif)' : ($fontFamily === 'mono' ? 'var(--rb-font-mono)' : 'var(--rb-font-sans)')) }}; font-size: {{ $fontSize }}pt;">

                <!-- Watermark jika disetel -->
                @if (!empty($pageSetup['watermark']))
                    <div class="absolute inset-0 flex items-center justify-center pointer-events-none select-none opacity-5 text-6xl font-black uppercase transform -rotate-45 font-mono">
                        {{ $pageSetup['watermark'] }}
                    </div>
                @endif

                <!-- Document Header Canvas -->
                <div class="mb-6 pb-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between text-xs text-slate-400 font-mono">
                    <span>{{ $docTitle }}</span>
                    <span>Target: {{ $targetWordCount }} kata ({{ round((($statistics['words'] ?? 0) / max(1, $targetWordCount)) * 100) }}%)</span>
                </div>

                <!-- BLOCKS CONTAINER -->
                <div class="space-y-4 flex-1">
                    @foreach ($blocks as $index => $block)
                        @php
                            $type = $block['type'] ?? 'paragraph';
                            $isActive = $activeBlock === $index;
                        @endphp
                        <div wire:key="block-{{ $index }}"
                            wire:click="selectBlock({{ $index }})"
                            class="group relative rounded p-2 transition border {{ $isActive ? 'border-blue-500 bg-blue-50/20 dark:bg-blue-950/20' : 'border-transparent hover:border-slate-300 dark:hover:border-slate-700' }}">

                            <!-- In-Block Floating Wordcraft AI & Block Toolbar (Tampil Saat Hover atau Aktif) -->
                            <div class="absolute -top-3.5 right-2 hidden group-hover:flex items-center space-x-1 bg-slate-900 text-white rounded px-2 py-0.5 text-[10px] font-mono shadow-md z-10">
                                <button type="button" wire:click.stop="aiElaborateBlock({{ $index }})" class="hover:text-blue-300" title="Kembangkan Narasi (Wordcraft)">✨ Perluas</button>
                                <span class="opacity-40">•</span>
                                <button type="button" wire:click.stop="aiShortenBlock({{ $index }})" class="hover:text-blue-300" title="Ringkas Padat">✂️ Ringkas</button>
                                <span class="opacity-40">•</span>
                                <button type="button" wire:click.stop="aiFixTone({{ $index }}, 'akademik')" class="hover:text-blue-300" title="Sesuaikan Gaya Akademik">🎓 Akademik</button>
                                <span class="opacity-40">•</span>
                                <button type="button" wire:click.stop="moveBlock({{ $index }}, -1)" class="hover:text-slate-300" title="Pindah Ke Atas">▲</button>
                                <button type="button" wire:click.stop="moveBlock({{ $index }}, 1)" class="hover:text-slate-300" title="Pindah Ke Bawah">▼</button>
                                <button type="button" wire:click.stop="removeBlock({{ $index }})" class="hover:text-red-300 ml-1" title="Hapus Blok">&times;</button>
                            </div>

                            <!-- BLOCK TYPE: HEADING -->
                            @if ($type === 'heading')
                                @php $level = $block['level'] ?? 1; @endphp
                                @if ($level === 1)
                                    <input type="text" value="{{ $block['text'] ?? '' }}"
                                        wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="w-full font-black text-2xl sm:text-3xl text-slate-900 dark:text-slate-100 bg-transparent border-0 focus:ring-0 p-0 font-sans tracking-tight"
                                        placeholder="Judul Utama (H1)...">
                                @elseif ($level === 2)
                                    <input type="text" value="{{ $block['text'] ?? '' }}"
                                        wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="w-full font-bold text-xl sm:text-2xl text-slate-900 dark:text-slate-100 bg-transparent border-0 focus:ring-0 p-0 font-sans tracking-tight"
                                        placeholder="Sub Judul (H2)...">
                                @else
                                    <input type="text" value="{{ $block['text'] ?? '' }}"
                                        wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="w-full font-bold text-lg text-slate-900 dark:text-slate-100 bg-transparent border-0 focus:ring-0 p-0 font-sans"
                                        placeholder="Sub Sub Judul (H3)...">
                                @endif

                            <!-- BLOCK TYPE: CALLOUT (LIBREOFFICE CALLOUT BOX) -->
                            @elseif ($type === 'callout')
                                @php
                                    $cType = $block['calloutType'] ?? 'info';
                                    $themeMap = [
                                        'info' => 'bg-blue-50 border-blue-600 text-blue-950 dark:bg-blue-950/40 dark:text-blue-100',
                                        'warning' => 'bg-amber-50 border-amber-600 text-amber-950 dark:bg-amber-950/40 dark:text-amber-100',
                                        'tip' => 'bg-emerald-50 border-emerald-600 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100',
                                        'note' => 'bg-purple-50 border-purple-600 text-purple-950 dark:bg-purple-950/40 dark:text-purple-100',
                                        'success' => 'bg-emerald-50 border-emerald-600 text-emerald-950 dark:bg-emerald-950/40 dark:text-emerald-100',
                                    ];
                                @endphp
                                <div class="border-l-4 p-3 rounded-r {{ $themeMap[$cType] ?? $themeMap['info'] }}">
                                    <div class="font-bold text-xs uppercase tracking-wider mb-1 font-mono">
                                        📌 Catatan Penting ({{ strtoupper($cType) }})
                                    </div>
                                    <textarea rows="2" wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="w-full bg-transparent border-0 focus:ring-0 p-0 resize-none font-serif text-sm leading-relaxed"
                                        placeholder="Tuliskan catatan khusus di sini...">{{ $block['text'] ?? '' }}</textarea>
                                </div>

                            <!-- BLOCK TYPE: QUOTE -->
                            @elseif ($type === 'quote')
                                <div class="border-l-4 border-slate-400 pl-4 italic text-slate-700 dark:text-slate-300">
                                    <textarea rows="2" wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="w-full bg-transparent border-0 focus:ring-0 p-0 resize-none font-serif leading-relaxed"
                                        placeholder="Kutipan langsung...">{{ $block['text'] ?? '' }}</textarea>
                                </div>

                            <!-- BLOCK TYPE: TABLE (LIBREOFFICE TABLE ENGINE) -->
                            @elseif ($type === 'table')
                                <div class="overflow-x-auto my-2 border border-slate-300 dark:border-slate-700 rounded shadow-sm">
                                    <table class="w-full border-collapse text-xs">
                                        @php
                                            $rows = $block['table']['rows'] ?? [];
                                            $hasHeader = $block['table']['header'] ?? true;
                                        @endphp
                                        @foreach ($rows as $rIdx => $row)
                                            <tr class="{{ $hasHeader && $rIdx === 0 ? 'bg-slate-100 dark:bg-slate-800 font-bold border-b-2 border-slate-400' : 'border-b border-slate-200 dark:border-slate-800' }}">
                                                @foreach ($row as $cIdx => $cell)
                                                    <td class="p-2 border-r border-slate-200 dark:border-slate-800">
                                                        <input type="text" value="{{ $cell['text'] ?? '' }}"
                                                            wire:blur="updateTableCell({{ $index }}, {{ $rIdx }}, {{ $cIdx }}, $event.target.value)"
                                                            class="w-full bg-transparent border-0 focus:ring-1 focus:ring-blue-500 p-0.5 text-xs">
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </table>
                                </div>

                            <!-- BLOCK TYPE: PAGEBREAK -->
                            @elseif ($type === 'pagebreak')
                                <div class="my-6 border-b-2 border-dashed border-slate-400 text-center relative select-none">
                                    <span class="bg-white dark:bg-slate-900 px-3 text-[10px] uppercase font-mono text-slate-500 relative -top-2">Pemisah Halaman</span>
                                </div>

                            <!-- BLOCK TYPE: PARAGRAPH (DEFAULT) -->
                            @else
                                <div class="relative">
                                    @if (!empty($block['dropCap']))
                                        <div class="float-left text-5xl font-black font-serif leading-none mr-2.5 mt-1 text-blue-700 select-none">
                                            {{ mb_substr($block['text'] ?? '', 0, 1) }}
                                        </div>
                                    @endif
                                    <textarea rows="3" wire:blur="updateBlockText({{ $index }}, $event.target.value)"
                                        class="w-full bg-transparent border-0 focus:ring-0 p-0 resize-y leading-relaxed text-slate-800 dark:text-slate-200"
                                        placeholder="Mulai menulis kalimat Anda di sini...">{{ $block['text'] ?? '' }}</textarea>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>

                <!-- Empty State & Quick Insert Button -->
                <div class="mt-8 pt-4 border-t border-slate-200 dark:border-slate-800 flex justify-center">
                    <button type="button" wire:click="addBlock('paragraph')"
                        class="px-4 py-2 border-2 border-dashed border-slate-400 hover:border-blue-600 hover:text-blue-600 rounded font-bold text-xs transition flex items-center space-x-1.5">
                        <span>+ Tambah Paragraf Baru</span>
                    </button>
                </div>
            </div>
        </main>

        <!-- RIGHT DRAWER: COMMENTS & REVISIONS (LIBREOFFICE REVIEW PANEL) -->
        @if (($showComments || $showRevisions) && ! $zenMode)
            <aside class="w-80 bg-white dark:bg-slate-900 border-l-2 border-slate-900 flex flex-col shrink-0 overflow-y-auto text-xs z-15">
                @if ($showComments)
                    <div class="p-3 bg-slate-100 dark:bg-slate-800 border-b border-slate-300 dark:border-slate-700 flex justify-between items-center font-bold font-mono">
                        <span>💬 Komentar ({{ count($comments) }})</span>
                        <button type="button" wire:click="$set('showComments', false)">&times;</button>
                    </div>
                    <div class="p-3 space-y-2 border-b border-slate-200 dark:border-slate-800">
                        <div class="flex space-x-1">
                            <input type="text" class="flex-1 border rounded px-2 py-1 text-xs" placeholder="Komentar untuk blok #{{ $activeBlock + 1 }}..." wire:model="commentDraft" wire:keydown.enter="addComment">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="addComment">+</button>
                        </div>
                        @forelse ($comments as $cIdx => $c)
                            <div class="p-2 rounded border {{ !empty($c['resolved']) ? 'bg-slate-50 opacity-60 border-slate-200' : 'bg-blue-50/40 border-blue-200' }}">
                                <div class="flex justify-between text-[10px] text-slate-500 mb-1">
                                    <strong>{{ $c['author'] ?? 'Penulis' }}</strong>
                                    <span>Blok #{{ ((int)($c['blockIndex'] ?? 0)) + 1 }}</span>
                                </div>
                                <p class="mb-2 leading-normal">{{ $c['body'] ?? '' }}</p>
                                <div class="flex justify-end space-x-2 text-[10px]">
                                    <button type="button" wire:click="resolveComment({{ $cIdx }})" class="text-blue-600 hover:underline">
                                        {{ !empty($c['resolved']) ? 'Buka Kembali' : '✓ Selesai' }}
                                    </button>
                                    <button type="button" wire:click="deleteComment({{ $cIdx }})" class="text-red-500 hover:underline">Hapus</button>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-slate-400 italic py-4">Belum ada komentar.</div>
                        @endforelse
                    </div>
                @endif

                @if ($showRevisions)
                    <div class="p-3 bg-slate-100 dark:bg-slate-800 border-b border-slate-300 dark:border-slate-700 flex justify-between items-center font-bold font-mono">
                        <span>👁️ Pelacakan Revisi ({{ $pendingRevisionCount }} pending)</span>
                        <button type="button" wire:click="$set('showRevisions', false)">&times;</button>
                    </div>
                    <div class="p-3 space-y-2">
                        <div class="flex space-x-1 mb-2">
                            <button type="button" wire:click="acceptAllRevisions" class="flex-1 ns-btn ns-btn--primary justify-center text-[10px]">✓ Terima Semua</button>
                            <button type="button" wire:click="rejectAllRevisions" class="flex-1 ns-btn justify-center text-[10px]">✕ Tolak Semua</button>
                        </div>
                        @forelse ($revisions as $rIdx => $rev)
                            @if (($rev['status'] ?? '') === 'pending')
                                <div class="p-2 rounded border border-amber-300 bg-amber-50/40 text-[11px]">
                                    <div class="text-[10px] text-slate-500 mb-1">
                                        {{ $rev['author'] ?? 'Penulis' }} · Blok #{{ ((int)($rev['blockIndex'] ?? 0)) + 1 }}
                                    </div>
                                    <div class="space-y-1 mb-2">
                                        <div class="line-through text-red-600 bg-red-50 p-1 rounded">{{ $rev['before'] ?? '' }}</div>
                                        <div class="text-emerald-700 bg-emerald-50 p-1 rounded font-semibold">{{ $rev['after'] ?? '' }}</div>
                                    </div>
                                    <div class="flex justify-end space-x-2 text-[10px]">
                                        <button type="button" wire:click="acceptRevision({{ $rIdx }})" class="text-emerald-700 font-bold hover:underline">✓ Terima</button>
                                        <button type="button" wire:click="rejectRevision({{ $rIdx }})" class="text-red-600 hover:underline">✕ Tolak</button>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="text-center text-slate-400 italic py-4">Tidak ada revisi pending.</div>
                        @endforelse
                    </div>
                @endif
            </aside>
        @endif

        <!-- RIGHT SIDEBAR: AI CO-WRITER ASSISTANT -->
        <aside x-show="!zenMode && {{ $assistantOpen ? 'true' : 'false' }}"
            class="w-80 bg-white dark:bg-slate-900 border-l-2 border-slate-900 flex flex-col shrink-0 overflow-hidden text-xs z-10">
            <div class="p-3 bg-blue-700 text-white font-mono font-bold flex justify-between items-center">
                <span>🤖 XISHIE CO-WRITER</span>
                <button type="button" wire:click="closeAssistant" class="text-white hover:text-slate-200">&times;</button>
            </div>

            <!-- Assistant Tool Buttons -->
            <div class="p-2 border-b border-slate-200 dark:border-slate-800 flex flex-wrap gap-1 bg-slate-50 dark:bg-slate-800/50">
                <button type="button" wire:click="runTool('parafrase')" class="px-2 py-1 bg-white dark:bg-slate-700 border rounded font-semibold text-[10px]">/parafrase</button>
                <button type="button" wire:click="runTool('sitasi')" class="px-2 py-1 bg-white dark:bg-slate-700 border rounded font-semibold text-[10px]">/sitasi</button>
                <button type="button" wire:click="runTool('cek-pedoman')" class="px-2 py-1 bg-white dark:bg-slate-700 border rounded font-semibold text-[10px]">/cek-pedoman</button>
                <button type="button" wire:click="runTool('cek-gap')" class="px-2 py-1 bg-white dark:bg-slate-700 border rounded font-semibold text-[10px]">/cek-gap</button>
            </div>

            <!-- Messages Transcript -->
            <div class="flex-1 overflow-y-auto p-3 space-y-3">
                @forelse ($assistantLog as $log)
                    <div class="rounded p-2.5 {{ ($log['role'] ?? '') === 'user' ? 'bg-blue-50 dark:bg-blue-950/40 border border-blue-200 text-blue-950 dark:text-blue-200 ml-4' : 'bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700 mr-4' }}">
                        <strong class="block font-bold text-[10px] uppercase font-mono mb-1 text-slate-500">
                            {{ ($log['role'] ?? '') === 'user' ? 'Anda' : 'Asisten Xishie' }}
                        </strong>
                        <div class="leading-relaxed whitespace-pre-line">{{ $log['content'] ?? '' }}</div>
                    </div>
                @empty
                    <div class="text-center text-slate-400 italic py-8">
                        Ajukan pertanyaan atau minta bantuan penulisan naskah kepada asisten akademik.
                    </div>
                @endforelse
            </div>

            <!-- Chat Input -->
            <div class="p-2 border-t border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-900">
                <form wire:submit.prevent="sendAssistant" class="flex space-x-1">
                    <input type="text" wire:model="assistantInput"
                        class="flex-1 border rounded px-2.5 py-1.5 text-xs bg-slate-50 dark:bg-slate-800 dark:border-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-600"
                        placeholder="Tanya asisten atau ketik /perintah...">
                    <button type="submit" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded font-bold font-mono">
                        Kirim
                    </button>
                </form>
            </div>
        </aside>

        <!-- Floating Assistant Trigger when closed -->
        <button type="button" wire:click="openAssistant" x-show="!zenMode && !{{ $assistantOpen ? 'true' : 'false' }}"
            class="fixed bottom-10 right-6 z-30 bg-blue-700 hover:bg-blue-800 text-white border-2 border-slate-900 shadow-[4px_4px_0px_#0f172a] rounded-full p-3 transition flex items-center space-x-2 font-mono font-bold text-xs">
            <span>🤖 Asisten AI</span>
        </button>
    </div>

    <!-- ===================================================================== -->
    <!-- BOTTOM STATUS BAR (LIBREOFFICE WRITER STATUS BAR)                      -->
    <!-- ===================================================================== -->
    <footer class="bg-slate-100 dark:bg-slate-900 border-t border-slate-300 dark:border-slate-800 px-4 py-1.5 flex flex-wrap items-center justify-between text-[11px] font-mono text-slate-600 dark:text-slate-400 select-none z-20">
        <div class="flex items-center space-x-4">
            <span>Halaman 1 / 1</span>
            <span>{{ $blockCount }} blok</span>
            <span><strong>{{ number_format($wordCount) }}</strong> kata</span>
            <span>{{ number_format($charCount) }} karakter</span>
            <span>⏱️ ~{{ $statistics['readingTimeMinutes'] ?? 1 }}m baca</span>
            <span>🎤 ~{{ $statistics['speakingTimeMinutes'] ?? 1 }}m bicara</span>
        </div>
        <div class="flex items-center space-x-3">
            <span>{{ $lastSavedAt ? 'Disimpan: ' . $lastSavedAt : 'Belum disimpan' }}</span>
            <div class="flex items-center space-x-1">
                <button type="button" wire:click="zoomOut" class="hover:text-blue-600 font-bold">-</button>
                <span>{{ $zoom }}%</span>
                <button type="button" wire:click="zoomIn" class="hover:text-blue-600 font-bold">+</button>
            </div>
        </div>
    </footer>

    <!-- ===================================================================== -->
    <!-- 18 UX KPIS & PRODUCT TELEMETRY INSPECTOR (HEART + AARRR DRAWER)        -->
    <!-- ===================================================================== -->
    <div x-show="kpiDrawer" class="fixed inset-0 z-50 overflow-hidden bg-slate-900/60 backdrop-blur-sm flex justify-end transition-opacity">
        <div class="w-full max-w-2xl bg-white dark:bg-slate-900 h-full shadow-2xl border-l-4 border-slate-900 flex flex-col overflow-hidden text-xs">
            <!-- Header -->
            <div class="p-4 bg-slate-900 text-white flex justify-between items-center border-b-2 border-slate-800">
                <div>
                    <h2 class="font-extrabold text-sm tracking-wider font-mono">📊 18 UX &amp; PRODUCT METRICS INSPECTOR</h2>
                    <span class="text-[11px] opacity-75">Google HEART Framework • AARRR Pirate Funnel • Usability KPIs</span>
                </div>
                <button type="button" wire:click="toggleKpiDrawer" class="text-white hover:text-slate-300 font-bold text-lg">&times;</button>
            </div>

            <!-- Framework Tabs -->
            <div class="flex border-b border-slate-200 dark:border-slate-800 bg-slate-50 dark:bg-slate-800/50 px-4 pt-2 font-mono text-xs font-bold gap-2">
                <button type="button" wire:click="setKpiFramework('HEART')" class="px-3 py-2 border-b-2 transition {{ $kpiActiveFramework === 'HEART' ? 'border-blue-600 text-blue-600 bg-white dark:bg-slate-900' : 'border-transparent text-slate-500' }}">
                    ❤️ Google HEART
                </button>
                <button type="button" wire:click="setKpiFramework('AARRR')" class="px-3 py-2 border-b-2 transition {{ $kpiActiveFramework === 'AARRR' ? 'border-blue-600 text-blue-600 bg-white dark:bg-slate-900' : 'border-transparent text-slate-500' }}">
                    🏴‍☠️ AARRR Funnel
                </button>
                <button type="button" wire:click="setKpiFramework('UX')" class="px-3 py-2 border-b-2 transition {{ $kpiActiveFramework === 'UX' ? 'border-blue-600 text-blue-600 bg-white dark:bg-slate-900' : 'border-transparent text-slate-500' }}">
                    🎯 Usability Specific
                </button>
                <button type="button" wire:click="setKpiFramework('RESOURCES')" class="px-3 py-2 border-b-2 transition {{ $kpiActiveFramework === 'RESOURCES' ? 'border-blue-600 text-blue-600 bg-white dark:bg-slate-900' : 'border-transparent text-slate-500' }}">
                    📖 Panduan Desain UX
                </button>
            </div>

            <!-- Content Area -->
            <div class="flex-1 overflow-y-auto p-4 space-y-3">
                @if ($kpiActiveFramework === 'RESOURCES')
                    <!-- Panduan & Referensi Metrik Produk -->
                    <div class="space-y-4">
                        <div class="p-3 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 rounded">
                            <strong class="text-blue-900 dark:text-blue-300 font-bold block mb-1">Prinsip Pengukuran Perilaku Pengguna (UX Metrics That Matter)</strong>
                            <p class="text-slate-700 dark:text-slate-300 text-[11px] leading-relaxed">
                                Metrik UX bukan sekadar angka statistik, melainkan jembatan yang menghubungkan niat pengguna, respons antarmuka, dan tingkat keberhasilan tugas akademik.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 gap-2.5 font-mono text-[11px]">
                            <a href="https://lnkd.in/dWGVK2Tp" target="_blank" rel="noopener noreferrer" class="p-2.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition block">
                                <strong class="text-blue-600 block">1. Understanding Metrics (Comprehensive Guide) ↗</strong>
                                <span class="text-slate-500 text-[10px]">Panduan mendalam menyusun taksonomi metrik kuantitatif dan kualitatif.</span>
                            </a>
                            <a href="https://lnkd.in/dfbfSg8T" target="_blank" rel="noopener noreferrer" class="p-2.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition block">
                                <strong class="text-blue-600 block">2. Ultimate Guide to Product Metrics (57 Metrics) ↗</strong>
                                <span class="text-slate-500 text-[10px]">Katalog lengkap 57 metrik produk digital untuk efisiensi dan adopsi.</span>
                            </a>
                            <a href="https://lnkd.in/dAS5bdx5" target="_blank" rel="noopener noreferrer" class="p-2.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition block">
                                <strong class="text-blue-600 block">3. Product Metrics That Matter ↗</strong>
                                <span class="text-slate-500 text-[10px]">Cara membedakan vanity metrics dari actionable behavioral signals.</span>
                            </a>
                            <a href="https://lnkd.in/dvA_si5X" target="_blank" rel="noopener noreferrer" class="p-2.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition block">
                                <strong class="text-blue-600 block">4. AARRR Pirate Framework ↗</strong>
                                <span class="text-slate-500 text-[10px]">Acquisition, Activation, Retention, Referral, dan Conversion.</span>
                            </a>
                            <a href="https://lnkd.in/dSNEHVRx" target="_blank" rel="noopener noreferrer" class="p-2.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition block">
                                <strong class="text-blue-600 block">5. Google HEART Framework ↗</strong>
                                <span class="text-slate-500 text-[10px]">Happiness, Engagement, Adoption, Retention, dan Task Success.</span>
                            </a>
                            <a href="https://lnkd.in/dsMA7AFH" target="_blank" rel="noopener noreferrer" class="p-2.5 rounded border border-slate-300 dark:border-slate-700 hover:bg-slate-50 dark:hover:bg-slate-800 transition block">
                                <strong class="text-blue-600 block">6. UX Specific KPIs ↗</strong>
                                <span class="text-slate-500 text-[10px]">Task completion rate, Time-on-task, error rate, dan CSAT/SUS.</span>
                            </a>
                        </div>
                    </div>
                @else
                    <!-- Metric Cards List -->
                    @php
                        $filtered = array_filter($uxKpis, fn($m) => $kpiActiveFramework === 'UX' ? ($m['framework'] === 'UX') : ($m['framework'] === $kpiActiveFramework));
                    @endphp
                    @forelse ($filtered as $key => $metric)
                        <div class="p-3 rounded border border-slate-200 dark:border-slate-800 bg-white dark:bg-slate-850 shadow-sm flex flex-col space-y-1.5">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $metric['label'] }}</span>
                                <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold {{ $metric['healthy'] ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300' }}">
                                    {{ $metric['healthy'] ? '🟢 SEHAT' : '🟡 PERLU PERHATIAN' }}
                                </span>
                            </div>

                            <p class="text-[11px] text-slate-500 leading-normal">{{ $metric['description'] }}</p>

                            <div class="flex items-center justify-between pt-1 border-t border-slate-100 dark:border-slate-800 font-mono text-[11px]">
                                <span class="text-slate-500">Nilai Saat Ini: <strong class="text-slate-900 dark:text-slate-100">{{ $metric['value'] }} {{ $metric['unit'] }}</strong></span>
                                <span class="text-slate-400">Target: {{ $metric['target'] }} {{ $metric['unit'] }}</span>
                            </div>
                        </div>
                    @empty
                        <div class="text-center text-slate-400 italic py-8">Tidak ada metrik dalam kategori ini.</div>
                    @endforelse
                @endif
            </div>

            <!-- Footer: CSAT In-Drawer Quick Rate -->
            <div class="p-3 bg-slate-50 dark:bg-slate-800 border-t border-slate-200 dark:border-slate-700 flex items-center justify-between">
                <span class="text-[11px] font-medium text-slate-600 dark:text-slate-400">Bagaimana pengalaman menulis Anda?</span>
                <div class="flex space-x-1">
                    @for ($i = 1; $i <= 5; $i++)
                        <button type="button" wire:click="rateCsat({{ $i }})" class="px-2 py-1 bg-white dark:bg-slate-700 border rounded hover:bg-amber-100 font-bold">
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
                            <label class="block font-semibold mb-1">Teks yang dicari:</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="findQuery" placeholder="Cari..." wire:keydown.enter="findText">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Ganti dengan:</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="replaceQuery" placeholder="Ganti...">
                        </div>
                        @if ($findResult)
                            <div class="p-2 bg-blue-50 dark:bg-blue-950/40 text-blue-900 dark:text-blue-200 rounded font-mono text-xs">
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
                        <div class="grid grid-cols-2 gap-2">
                            <div class="p-3 border rounded bg-slate-50 dark:bg-slate-800">
                                <span class="text-[10px] text-slate-500 font-mono">JUMLAH KATA</span>
                                <div class="text-xl font-extrabold text-blue-700 dark:text-blue-400">{{ number_format($statistics['words'] ?? 0) }}</div>
                            </div>
                            <div class="p-3 border rounded bg-slate-50 dark:bg-slate-800">
                                <span class="text-[10px] text-slate-500 font-mono">KARAKTER</span>
                                <div class="text-xl font-extrabold text-blue-700 dark:text-blue-400">{{ number_format($statistics['characters'] ?? 0) }}</div>
                            </div>
                            <div class="p-3 border rounded bg-slate-50 dark:bg-slate-800">
                                <span class="text-[10px] text-slate-500 font-mono">FLESCH READING EASE</span>
                                <div class="text-xl font-extrabold text-emerald-600">{{ $statistics['readingEase'] ?? 0 }} / 100</div>
                            </div>
                            <div class="p-3 border rounded bg-slate-50 dark:bg-slate-800">
                                <span class="text-[10px] text-slate-500 font-mono">GRADE LEVEL</span>
                                <div class="text-xl font-extrabold text-amber-600">Kelas {{ $statistics['gradeLevel'] ?? 0 }}</div>
                            </div>
                        </div>
                        <div class="font-mono text-xs space-y-1 pt-2 border-t border-slate-200 dark:border-slate-800">
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
                        <div class="grid grid-cols-2 gap-2">
                            <div>
                                <label class="block font-semibold mb-1">Ukuran Kertas:</label>
                                <select wire:change="updatePageSetup('size', $event.target.value)" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800">
                                    <option value="A4" {{ ($pageSetup['size'] ?? '') === 'A4' ? 'selected' : '' }}>A4</option>
                                    <option value="Letter" {{ ($pageSetup['size'] ?? '') === 'Letter' ? 'selected' : '' }}>Letter</option>
                                    <option value="Legal" {{ ($pageSetup['size'] ?? '') === 'Legal' ? 'selected' : '' }}>Legal</option>
                                    <option value="A5" {{ ($pageSetup['size'] ?? '') === 'A5' ? 'selected' : '' }}>A5</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Orientasi:</label>
                                <select wire:change="updatePageSetup('orientation', $event.target.value)" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800">
                                    <option value="portrait" {{ ($pageSetup['orientation'] ?? '') === 'portrait' ? 'selected' : '' }}>Tegak (Portrait)</option>
                                    <option value="landscape" {{ ($pageSetup['orientation'] ?? '') === 'landscape' ? 'selected' : '' }}>Mendatar (Landscape)</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Margin Atas (mm):</label>
                                <input type="number" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800" wire:model="pageSetup.marginTop">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Margin Bawah (mm):</label>
                                <input type="number" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800" wire:model="pageSetup.marginBottom">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Margin Kiri (mm):</label>
                                <input type="number" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800" wire:model="pageSetup.marginLeft">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Margin Kanan (mm):</label>
                                <input type="number" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800" wire:model="pageSetup.marginRight">
                            </div>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Tanda Air (Watermark):</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="pageSetup.watermark" placeholder="cth: DRAF RAHASIA">
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
                        <p class="leading-relaxed">{{ $spellResult ?: 'Ejaan pada dokumen Anda telah diperiksa dan sesuai pedoman.' }}</p>
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
                        <div class="flex space-x-1">
                            <input type="text" class="flex-1 border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="thesaurusWord" placeholder="Masukkan kata..." wire:keydown.enter="runThesaurus">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="runThesaurus">Cari</button>
                        </div>
                        @if ($thesaurusResult)
                            <div class="p-2 border rounded bg-slate-50 dark:bg-slate-800 text-xs">
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
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block font-semibold mb-1">Jumlah Baris:</label>
                                <input type="number" min="1" max="25" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="tableRows">
                            </div>
                            <div>
                                <label class="block font-semibold mb-1">Jumlah Kolom:</label>
                                <input type="number" min="1" max="10" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="tableCols">
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
                            <label class="block font-semibold mb-1">Unggah Berkas Gambar:</label>
                            <input type="file" wire:model="imageUpload" accept="image/*" class="w-full border p-1 rounded bg-slate-50 dark:bg-slate-800">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Keterangan / Alt:</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="imageAlt" placeholder="Keterangan gambar...">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Lebar Tampilan (px):</label>
                            <input type="number" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="imageWidth" value="320">
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
                            <label class="block font-semibold mb-1">URL Tujuan:</label>
                            <input type="url" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="linkUrl" placeholder="https://example.com">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Teks Tampilan:</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="linkText" placeholder="Teks yang diklik...">
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
                            <label class="block font-semibold mb-1">Isi Catatan Kaki:</label>
                            <textarea rows="3" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="footnoteText" placeholder="Ketik keterangan catatan kaki..."></textarea>
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
                        <div class="grid grid-cols-8 gap-1.5 text-center font-serif text-lg">
                            @foreach (['©', '®', '™', '§', '¶', '±', '×', '÷', '≠', '≤', '≥', '≈', '∞', '°', 'µ', 'α', 'β', 'γ', 'π', '∑', '√', '—', '–', '•', '€', '£', '¥', '→'] as $sym)
                                <button type="button" class="ns-btn justify-center p-2 hover:scale-110" wire:click="insertSymbol('{{ $sym }}'); closeDialog()">
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
                            <label class="block font-semibold mb-1">Data CSV (Baris 1 = Header):</label>
                            <textarea rows="4" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800 font-mono text-xs" wire:model="mergeCsv" placeholder="nama,instansi&#10;Budi,Universitas Indonesia&#10;Siti,ITB"></textarea>
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Template Dokumen (Gunakan @{{nama}}):</label>
                            <textarea rows="2" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="mergeTemplate" placeholder="Yth. @{{nama}} di @{{instansi}}..."></textarea>
                        </div>
                        @if (!empty($mergePreviews))
                            <div class="p-2 border rounded bg-slate-50 dark:bg-slate-800 text-xs">
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
                        <textarea rows="3" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="commentDraft" placeholder="Tulis komentar..."></textarea>
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
                        <input type="text" class="w-full border-2 border-slate-900 rounded p-2 text-xs font-mono bg-slate-50 dark:bg-slate-800"
                            placeholder="Ketik perintah (cth: auto-correct, table, paragraph, dark mode)..."
                            wire:model.live="commandSearch" autofocus>
                        <div class="max-h-60 overflow-y-auto space-y-1 font-mono text-xs">
                            @php
                                $q = strtolower(trim($commandSearch));
                                $filteredCmds = array_filter($commands, static fn(array $c): bool => $q === '' || str_contains(strtolower($c['label']), $q) || str_contains(strtolower($c['category']), $q) || str_contains(strtolower($c['id']), $q));
                            @endphp
                            @forelse ($filteredCmds as $cmd)
                                <button type="button" class="w-full text-left p-2 rounded hover:bg-blue-50 dark:hover:bg-slate-800 flex justify-between items-center group border border-slate-200 dark:border-slate-800"
                                    wire:click="executeCommand('{{ $cmd['id'] }}'); closeDialog()">
                                    <div class="flex items-center space-x-2">
                                        <span>{{ $cmd['icon'] }}</span>
                                        <strong class="text-slate-800 dark:text-slate-200">{{ $cmd['label'] }}</strong>
                                        <span class="text-[9px] text-slate-400 bg-slate-100 dark:bg-slate-800 px-1 py-0.5 rounded">{{ $cmd['category'] }}</span>
                                    </div>
                                    @if (!empty($cmd['shortcut']))
                                        <span class="text-[9px] text-slate-400 border rounded px-1">{{ $cmd['shortcut'] }}</span>
                                    @endif
                                </button>
                            @empty
                                <div class="text-slate-400 italic py-4 text-center">Tidak ada perintah yang sesuai.</div>
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
                            <label class="block font-semibold mb-1">Judul Dokumen:</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="docTitle" wire:change="markDirty">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Penulis (Author):</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="metadata.author" wire:change="markDirty" placeholder="Nama penulis...">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Subjek / Topik:</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="metadata.subject" wire:change="markDirty" placeholder="Topik bahasan...">
                        </div>
                        <div>
                            <label class="block font-semibold mb-1">Kata Kunci (Keywords):</label>
                            <input type="text" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800" wire:model="metadata.keywords" wire:change="markDirty" placeholder="Kata kunci...">
                        </div>
                        <div class="pt-2 border-t border-slate-200 dark:border-slate-800 font-mono text-xs space-y-1 text-slate-500">
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
                        <div class="flex space-x-1 mb-2">
                            <input type="text" class="flex-1 border p-1.5 rounded bg-slate-50 dark:bg-slate-800" placeholder="Label versi baru..." wire:model="snapshotLabel">
                            <button type="button" class="ns-btn ns-btn--primary" wire:click="createSnapshot(snapshotLabel)">+ Simpan</button>
                        </div>
                        <div class="space-y-1 max-h-60 overflow-y-auto">
                            @forelse ($snapshots as $sIdx => $snap)
                                <div class="p-2 border rounded flex justify-between items-center bg-slate-50 dark:bg-slate-800">
                                    <div>
                                        <strong>{{ $snap['label'] }}</strong>
                                        <div class="text-[10px] text-slate-400 font-mono">{{ $snap['at'] }} · {{ $snap['count'] }} blok</div>
                                    </div>
                                    <div class="flex space-x-1">
                                        <button type="button" wire:click="restoreSnapshot({{ $sIdx }})" class="ns-btn text-blue-600">↩ Pulihkan</button>
                                        <button type="button" wire:click="deleteSnapshot({{ $sIdx }})" class="ns-btn text-red-600">✕</button>
                                    </div>
                                </div>
                            @empty
                                <div class="text-slate-400 italic text-center py-4">Belum ada snapshot versi tersimpan.</div>
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
                        <table class="w-full text-xs font-mono">
                            <thead class="border-b">
                                <tr class="text-left text-slate-500">
                                    <th class="p-1">Kombinasi</th>
                                    <th class="p-1">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                                <tr><td class="p-1 font-bold">Ctrl + S</td><td class="p-1">Simpan Naskah</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + Z</td><td class="p-1">Urungkan Perubahan (Undo)</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + Y</td><td class="p-1">Ulangi Perubahan (Redo)</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + K</td><td class="p-1">Pusat Perintah (Command Palette HUD)</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + F</td><td class="p-1">Cari dan Ganti Teks</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + B</td><td class="p-1">Format Huruf Tebal (Bold)</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + I</td><td class="p-1">Format Huruf Miring (Italic)</td></tr>
                                <tr><td class="p-1 font-bold">Ctrl + U</td><td class="p-1">Format Garis Bawah (Underline)</td></tr>
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
