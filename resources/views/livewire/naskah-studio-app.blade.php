<div class="flex-1 flex flex-col min-h-screen {{ $darkMode ? 'dark bg-slate-950 text-slate-100' : 'bg-slate-100 text-slate-900' }} font-sans"
    x-data="{
        shortcutsOpen: false,
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
            });
            setInterval(() => {
                $wire.tickSession();
            }, 5000);
        }
    }">

    <!-- ===================================================================== -->
    <!-- TOP UX & PRODUCT TELEMETRY BAR (18 KPIs EVERY UX DESIGNER SHOULD KNOW) -->
    <!-- ===================================================================== -->
    <header class="border-b-2 border-slate-900 bg-white dark:bg-slate-900 shadow-sm z-30 sticky top-0 px-4 py-2 flex flex-wrap items-center justify-between gap-3 text-xs">
        <!-- Brand & Document Title -->
        <div class="flex items-center space-x-3">
            <a href="{{ route('home') }}" class="font-extrabold tracking-wider font-mono px-2 py-1 bg-blue-700 text-white border-2 border-slate-900 shadow-[2px_2px_0px_#0f172a] hover:bg-blue-800 transition">
                NASKAH STUDIO
            </a>
            <input type="text" wire:model.live.debounce.400ms="docTitle"
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
                    {{ round(($statistics['words'] > 0 ? (count(array_filter($blocks, fn($b) => ($b['type'] ?? '') === 'footnote-ref')) / max(1, $statistics['words'])) * 1000 : 0), 1) }}
                </span>
                <span class="text-[10px] text-slate-500">/1k kata</span>
            </div>

            <!-- Interactive CSAT / Happiness Rating (HEART - Happiness) -->
            <div class="flex items-center space-x-1 bg-amber-50 dark:bg-amber-950/30 px-2 py-1 rounded border border-amber-300 dark:border-amber-700 text-amber-900 dark:text-amber-200">
                <span class="text-[10px] font-sans font-bold">CSAT:</span>
                <div class="flex items-center space-x-0.5">
                    @for ($s = 1; $s <= 5; $s++)
                        <button type="button" wire:click="rateCsat({{ $s }})" class="hover:scale-125 transition {{ $csatScore >= $s ? 'text-amber-500' : 'text-slate-300 dark:text-slate-600' }}" title="Beri Rating {{ $s }}/5">
                            ★
                        </button>
                    @endfor
                </div>
                @if ($csatSubmitted)
                    <span class="text-[9px] text-emerald-600 font-bold ml-1">✓ Tercatat</span>
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
        <div class="p-2 px-4 flex flex-wrap items-center gap-2 text-xs bg-white dark:bg-slate-850">
            @if ($activeTab === 'home')
                <!-- Undo / Redo / Format Painter -->
                <div class="flex items-center space-x-1 pr-2 border-r border-slate-300 dark:border-slate-700">
                    <button type="button" wire:click="undo" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40" {{ empty($undoStack) ? 'disabled' : '' }} title="Undo (Ctrl+Z)">
                        ↩
                    </button>
                    <button type="button" wire:click="redo" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 disabled:opacity-40" {{ empty($redoStack) ? 'disabled' : '' }} title="Redo (Ctrl+Y)">
                        ↪
                    </button>
                    <button type="button" wire:click="copyFormat({{ $activeBlock }})" class="p-1.5 rounded hover:bg-slate-100 dark:hover:bg-slate-800 {{ $copiedFormat ? 'bg-amber-100 text-amber-900 font-bold' : '' }}" title="Format Painter">
                        🖌️
                    </button>
                </div>

                <!-- Typography & Font Controls -->
                <div class="flex items-center space-x-1 pr-2 border-r border-slate-300 dark:border-slate-700">
                    <select wire:model.live="fontFamily" class="border rounded px-2 py-1 text-xs bg-white dark:bg-slate-800 dark:border-slate-700">
                        @foreach ($fontFamilies as $k => $fontLabel)
                            <option value="{{ $k }}">{{ $fontLabel }}</option>
                        @endforeach
                    </select>
                    <select wire:model.live="fontSize" class="border rounded px-2 py-1 text-xs bg-white dark:bg-slate-800 dark:border-slate-700">
                        @foreach ($fontSizes as $fs)
                            <option value="{{ $fs }}">{{ $fs }} pt</option>
                        @endforeach
                    </select>
                    <button type="button" wire:click="adjustFontSize(1)" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Perbesar Font">A▲</button>
                    <button type="button" wire:click="adjustFontSize(-1)" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Perkecil Font">A▼</button>
                </div>

                <!-- Inline Styling (Bold, Italic, Underline, Strikethrough, Code) -->
                <div class="flex items-center space-x-1 pr-2 border-r border-slate-300 dark:border-slate-700">
                    <button type="button" wire:click="formatInline('bold')" class="px-2 py-1 font-bold border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Tebal (Bold)">B</button>
                    <button type="button" wire:click="formatInline('italic')" class="px-2 py-1 italic border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Miring (Italic)">I</button>
                    <button type="button" wire:click="formatInline('underline')" class="px-2 py-1 underline border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Garis Bawah (Underline)">U</button>
                    <button type="button" wire:click="formatInline('strike')" class="px-2 py-1 line-through border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Coret">S</button>
                    <button type="button" wire:click="formatInline('code')" class="px-2 py-1 font-mono border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Kode">&lt;/&gt;</button>
                    <button type="button" wire:click="clearFormatting" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 text-red-600" title="Hapus Format">✕</button>
                </div>

                <!-- Alignment & Lists -->
                <div class="flex items-center space-x-1 pr-2 border-r border-slate-300 dark:border-slate-700">
                    <button type="button" wire:click="formatParagraph('align', 'left')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Rata Kiri">⫷</button>
                    <button type="button" wire:click="formatParagraph('align', 'center')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Rata Tengah">≡</button>
                    <button type="button" wire:click="formatParagraph('align', 'right')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Rata Kanan">⫸</button>
                    <button type="button" wire:click="formatParagraph('align', 'justify')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Rata Kiri Kanan">☷</button>
                    <button type="button" wire:click="applyStyle({{ $activeBlock }}, 'bullet')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Daftar Poin">• List</button>
                    <button type="button" wire:click="applyStyle({{ $activeBlock }}, 'numbered')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800" title="Daftar Angka">1. List</button>
                </div>

                <!-- LibreOffice Typographical Auto-Correct -->
                <div class="flex items-center space-x-1">
                    <button type="button" wire:click="runAutoCorrect" class="px-2.5 py-1 bg-amber-500 hover:bg-amber-600 text-slate-900 font-bold border border-slate-900 shadow-[2px_2px_0px_#0f172a] rounded flex items-center space-x-1" title="Perbaiki Typografi Otomatis (panah, kutip, simbol)">
                        <span>⚡ Auto-Correct</span>
                    </button>
                </div>
            @elseif ($activeTab === 'insert')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="addBlock('paragraph')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Paragraf</button>
                    <button type="button" wire:click="addBlock('heading')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Judul</button>
                    <button type="button" wire:click="addBlock('quote')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Kutipan</button>
                    <button type="button" wire:click="insertCallout('info')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold bg-blue-50 dark:bg-blue-950/40 text-blue-800 dark:text-blue-300">+ Callout Box</button>
                    <button type="button" wire:click="insertTable" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Tabel (3x3)</button>
                    <button type="button" wire:click="insertPageBreak" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Pemisah Halaman</button>
                    <button type="button" wire:click="toggleDropCap({{ $activeBlock }})" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">Drop Cap</button>
                    <button type="button" wire:click="openDialog('footnote')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Catatan Kaki</button>
                    <button type="button" wire:click="insertTableOfContents" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Daftar Isi</button>
                </div>
            @elseif ($activeTab === 'design')
                <div class="flex items-center space-x-2">
                    <span class="font-bold">Tema Dokumen:</span>
                    <button type="button" wire:click="applyTheme('academic')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800">Akademik</button>
                    <button type="button" wire:click="applyTheme('modern')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800">Modern</button>
                    <button type="button" wire:click="applyTheme('classic')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800">Klasik</button>
                    <button type="button" wire:click="applyTheme('minimal')" class="px-2 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800">Minimal</button>
                    <button type="button" wire:click="openDialog('pageSetup')" class="ml-4 px-2.5 py-1 bg-slate-200 dark:bg-slate-700 rounded font-semibold">Pengaturan Halaman &amp; Watermark</button>
                </div>
            @elseif ($activeTab === 'layout')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="openDialog('pageSetup')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">Ukuran Kertas ({{ $pageSetup['size'] ?? 'A4' }})</button>
                    <button type="button" wire:click="updatePageSetup('orientation', '{{ ($pageSetup['orientation'] ?? 'portrait') === 'portrait' ? 'landscape' : 'portrait' }}')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">
                        Orientasi: {{ ucfirst($pageSetup['orientation'] ?? 'portrait') }}
                    </button>
                    <button type="button" wire:click="$toggle('showRuler')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">
                        {{ $showRuler ? 'Sembunyikan Penggaris' : 'Tampilkan Penggaris' }}
                    </button>
                </div>
            @elseif ($activeTab === 'references')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="insertBibliography({{ $sourceProjectId }})" class="px-2.5 py-1 bg-blue-600 text-white font-semibold rounded hover:bg-blue-700">Sisipkan Daftar Pustaka</button>
                    <button type="button" wire:click="openDialog('footnote')" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Catatan Kaki</button>
                    <button type="button" wire:click="insertTableOfContents" class="px-2.5 py-1 border rounded hover:bg-slate-100 dark:hover:bg-slate-800 font-semibold">+ Daftar Isi Otomatis</button>
                </div>
            @elseif ($activeTab === 'review')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="openDialog('comment')" class="px-2.5 py-1 bg-emerald-600 text-white font-semibold rounded hover:bg-emerald-700">+ Tambah Komentar</button>
                    <button type="button" wire:click="$toggle('trackChanges')" class="px-2.5 py-1 border rounded {{ $trackChanges ? 'bg-amber-100 text-amber-900 border-amber-400 font-bold' : '' }}">
                        {{ $trackChanges ? '● Lacak Perubahan (Aktif)' : '○ Lacak Perubahan' }}
                    </button>
                    <button type="button" wire:click="acceptAllRevisions" class="px-2.5 py-1 border rounded text-emerald-700 hover:bg-emerald-50">Setujui Semua Revisi</button>
                    <button type="button" wire:click="rejectAllRevisions" class="px-2.5 py-1 border rounded text-red-700 hover:bg-red-50">Tolak Semua Revisi</button>
                    <button type="button" wire:click="openDialog('snapshots')" class="ml-4 px-2.5 py-1 bg-slate-200 dark:bg-slate-700 rounded font-semibold">Snapshot Versi ({{ count($snapshots) }})</button>
                </div>
            @elseif ($activeTab === 'table')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="tableInsertRow({{ $activeBlock }}, 1)" class="px-2 py-1 border rounded hover:bg-slate-100">+ Baris</button>
                    <button type="button" wire:click="tableInsertColumn({{ $activeBlock }}, 1)" class="px-2 py-1 border rounded hover:bg-slate-100">+ Kolom</button>
                    <button type="button" wire:click="tableToggleHeader({{ $activeBlock }})" class="px-2 py-1 border rounded hover:bg-slate-100">Toggle Baris Header</button>
                    <span class="border-l border-slate-300 dark:border-slate-700 h-4"></span>
                    <button type="button" wire:click="tableSumColumn({{ $activeBlock }}, 1)" class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-900 dark:text-blue-100 rounded font-semibold">Σ Hitung SUM</button>
                    <button type="button" wire:click="tableAverageColumn({{ $activeBlock }}, 1)" class="px-2 py-1 bg-blue-100 dark:bg-blue-900 text-blue-900 dark:text-blue-100 rounded font-semibold">x̄ Hitung AVERAGE</button>
                </div>
            @elseif ($activeTab === 'mailings')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="openDialog('mailMerge')" class="px-2.5 py-1 bg-purple-600 text-white font-semibold rounded hover:bg-purple-700">Konfigurasi Mail Merge CSV</button>
                    <span class="text-xs text-slate-500">Gunakan placeholder seperti @{{nama}} atau @{{instansi}}</span>
                </div>
            @elseif ($activeTab === 'view')
                <div class="flex items-center space-x-2">
                    <button type="button" wire:click="$toggle('showNavigation')" class="px-2.5 py-1 border rounded hover:bg-slate-100 font-semibold">{{ $showNavigation ? 'Sembunyikan Navigator' : 'Tampilkan Navigator' }}</button>
                    <button type="button" wire:click="$toggle('showRuler')" class="px-2.5 py-1 border rounded hover:bg-slate-100 font-semibold">{{ $showRuler ? 'Sembunyikan Penggaris' : 'Tampilkan Penggaris' }}</button>
                    <button type="button" wire:click="zoomIn" class="px-2 py-1 border rounded font-bold">Zoom +</button>
                    <button type="button" wire:click="zoomOut" class="px-2 py-1 border rounded font-bold">Zoom -</button>
                    <span class="text-xs font-mono font-bold">{{ $zoom }}%</span>
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
    <!-- MAIN WORKSPACE: NAVIGATOR, CANVAS, ASSISTANT                           -->
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
                    <span>{{ $statistics['fleschReadingEase'] ?? 0 }}</span>
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
                style="font-family: {{ ($fontFamily === 'serif' ? 'var(--rb-font-serif)' : ($fontFamily === 'mono' ? 'var(--rb-font-mono)' : 'var(--rb-font-sans)')) }}; font-size: {{ $fontSize }}pt;">

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
            class="fixed bottom-6 right-6 z-30 bg-blue-700 hover:bg-blue-800 text-white border-2 border-slate-900 shadow-[4px_4px_0px_#0f172a] rounded-full p-3 transition flex items-center space-x-2 font-mono font-bold text-xs">
            <span>🤖 Asisten AI</span>
        </button>
    </div>

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
    <!-- MODAL DIALOGS: COMMAND PALETTE, PAGE SETUP, SNAPSHOTS, FOOTNOTE        -->
    <!-- ===================================================================== -->
    @if ($dialog === 'palette')
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-start justify-center pt-20 p-4">
            <div class="bg-white dark:bg-slate-900 border-2 border-slate-900 shadow-[8px_8px_0px_#0f172a] rounded max-w-xl w-full p-4">
                <div class="flex justify-between items-center mb-3">
                    <h3 class="font-extrabold font-mono text-xs uppercase tracking-wider text-slate-900 dark:text-slate-100">Wordcraft Command Palette (HUD)</h3>
                    <button type="button" wire:click="closeDialog" class="font-bold text-slate-400 hover:text-slate-600">&times;</button>
                </div>
                <input type="text" wire:model.live="commandSearch" autofocus
                    class="w-full border-2 border-slate-900 rounded p-2 text-xs font-mono mb-3 bg-slate-50 dark:bg-slate-800"
                    placeholder="Ketik nama perintah (cth: auto-correct, paragraph, table, dark mode)...">
                <div class="max-h-64 overflow-y-auto space-y-1 text-xs font-mono">
                    @foreach ($commands as $cmd)
                        @if (empty($commandSearch) || str_contains(strtolower($cmd['label']), strtolower($commandSearch)) || str_contains(strtolower($cmd['id']), strtolower($commandSearch)))
                            <button type="button" wire:click="executeCommand('{{ $cmd['id'] }}'); closeDialog();"
                                class="w-full text-left p-2 rounded hover:bg-blue-50 dark:hover:bg-slate-800 flex justify-between items-center group">
                                <span class="font-semibold">{{ $cmd['label'] }}</span>
                                <span class="text-[10px] text-slate-400 group-hover:text-blue-600">{{ $cmd['id'] }}</span>
                            </button>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @elseif ($dialog === 'snapshots')
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border-2 border-slate-900 shadow-[8px_8px_0px_#0f172a] rounded max-w-md w-full p-5">
                <h3 class="font-extrabold font-mono text-xs uppercase tracking-wider mb-3">Simpan Snapshot Versi Dokumen</h3>
                <input type="text" wire:model="snapshotLabel" class="w-full border p-2 text-xs rounded mb-3 bg-slate-50 dark:bg-slate-800" placeholder="Label versi (cth: Draf Siap Ujian, Revisi Bab 1)...">
                <div class="flex justify-end space-x-2">
                    <button type="button" wire:click="closeDialog" class="px-3 py-1.5 border rounded text-xs">Batal</button>
                    <button type="button" wire:click="createSnapshot(snapshotLabel); closeDialog();" class="px-3 py-1.5 bg-blue-600 text-white rounded font-bold text-xs">Simpan Versi</button>
                </div>
            </div>
        </div>
    @elseif ($dialog === 'pageSetup')
        <div class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm flex items-center justify-center p-4">
            <div class="bg-white dark:bg-slate-900 border-2 border-slate-900 shadow-[8px_8px_0px_#0f172a] rounded max-w-md w-full p-5 text-xs">
                <h3 class="font-extrabold font-mono text-xs uppercase tracking-wider mb-3">Pengaturan Halaman &amp; Watermark</h3>
                <div class="space-y-3 mb-4">
                    <div>
                        <label class="block font-semibold mb-1">Ukuran Kertas:</label>
                        <select wire:change="updatePageSetup('size', $event.target.value)" class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800">
                            <option value="A4" {{ ($pageSetup['size'] ?? '') === 'A4' ? 'selected' : '' }}>A4 (210 x 297 mm)</option>
                            <option value="Letter" {{ ($pageSetup['size'] ?? '') === 'Letter' ? 'selected' : '' }}>Letter (8.5 x 11 in)</option>
                            <option value="Legal" {{ ($pageSetup['size'] ?? '') === 'Legal' ? 'selected' : '' }}>Legal (8.5 x 14 in)</option>
                            <option value="A5" {{ ($pageSetup['size'] ?? '') === 'A5' ? 'selected' : '' }}>A5 (148 x 210 mm)</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-semibold mb-1">Watermark Teks:</label>
                        <input type="text" value="{{ $pageSetup['watermark'] ?? '' }}"
                            wire:blur="updatePageSetup('watermark', $event.target.value)"
                            class="w-full border p-1.5 rounded bg-slate-50 dark:bg-slate-800"
                            placeholder="cth: DRAF RAHASIA, SAMPLE, BUKAN UNTUK DISTRIBUSI">
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="button" wire:click="closeDialog" class="px-4 py-1.5 bg-blue-600 text-white rounded font-bold text-xs">Selesai</button>
                </div>
            </div>
        </div>
    @endif
</div>
