<div class="word-root {{ $darkMode ? 'dark' : '' }}"
    style="position: relative;"
    x-data="wordStudioApp({
        wire: $wire,
        zenMode: @entangle('zenMode'),
        activeTaskPane: @entangle('activeTaskPane'),
        commentsOpen: @entangle('showComments'),
        revisionsOpen: @entangle('showRevisions'),
        assistantPane: @entangle('assistantOpen'),
        navTab: @entangle('navigationTab'),
        activeBlock: @entangle('activeBlock')
    })">

    <!-- ===================================================================== -->
    <!-- FLOATING SELECTION MINI-TOOLBAR (MICROSOFT WORD 365 BUBBLE MENU)      -->
    <!-- ===================================================================== -->
    <div x-cloak
        x-show="showMiniToolbar"
        @click.outside="showMiniToolbar = false"
        class="word-mini-toolbar"
        :style="`top: ${toolbarTop}px; left: ${toolbarLeft}px;`"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 transform translate-y-2 scale-95"
        x-transition:enter-end="opacity-100 transform translate-y-0 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 transform translate-y-0 scale-100"
        x-transition:leave-end="opacity-0 transform translate-y-2 scale-95">

        <!-- Bold, Italic, Underline, Strike -->
        <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.bold }" @mousedown.prevent="format('bold')" title="Huruf Tebal (Ctrl+B)">
            <strong>B</strong>
        </button>
        <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.italic }" @mousedown.prevent="format('italic')" title="Huruf Miring (Ctrl+I)">
            <em>I</em>
        </button>
        <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.underline }" @mousedown.prevent="format('underline')" title="Garis Bawah (Ctrl+U)">
            <u>U</u>
        </button>
        <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.strike }" @mousedown.prevent="format('strikeThrough')" title="Coret (Strikethrough)">
            <s>S</s>
        </button>

        <div class="word-mini-divider"></div>

        <!-- Highlight & Color -->
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('hiliteColor', '#fde68a')" title="Stabilo Kuning">
            <span style="background:#fde68a; padding:1px 3px; border-radius:2px; font-weight:700; font-size:10px;">ab</span>
        </button>
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('foreColor', '#0f6cbd')" title="Warna Biru Word">
            <span style="color:#0f6cbd; font-weight:700; font-size:11px;">A</span>
        </button>

        <div class="word-mini-divider"></div>

        <!-- Alignment -->
        <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.align === 'left' }" @mousedown.prevent="applyAlignment('left')" title="Rata Kiri">
            <svg viewBox="0 0 16 16" width="11" height="11" fill="currentColor">
                <path d="M2 3h12v1.5H2zm0 3.5h8v1.5H2zm0 3.5h12v1.5H2zm0 3.5h8V15H2z"/>
            </svg>
        </button>
        <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.align === 'center' }" @mousedown.prevent="applyAlignment('center')" title="Rata Tengah">
            <svg viewBox="0 0 16 16" width="11" height="11" fill="currentColor">
                <path d="M2 3h12v1.5H2zm2 3.5h8v1.5H4zm-2 3.5h12v1.5H2zm2 3.5h8V15H4z"/>
            </svg>
        </button>

        <div class="word-mini-divider"></div>

        <!-- Xishie Copilot Quick Action Dropdown -->
        <div style="position:relative;">
            <button type="button" @click="copilotMenuOpen = !copilotMenuOpen" class="word-mini-btn word-mini-copilot-btn" title="Xishie Assistant AI Actions">
                <span>✨ AI</span>
            </button>
            <div x-show="copilotMenuOpen" @click.outside="copilotMenuOpen = false" class="word-mini-dropdown" style="width: 210px;">
                <button type="button" @mousedown.prevent @click="runAi('/poles')" class="word-mini-dropdown-item">
                    <span>🪄 Poles Kalimat Terpilih</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/parafrase')" class="word-mini-dropdown-item">
                    <span>🔄 Parafrase Anti-Plagiasi</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/kembangkan')" class="word-mini-dropdown-item">
                    <span>✍️ Elaborasi &amp; Kembangkan</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/ringkaskan')" class="word-mini-dropdown-item">
                    <span>✂️ Padatkan / Buat Ringkas</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/perbaiki-ejaan')" class="word-mini-dropdown-item">
                    <span>🔤 Perbaiki Ejaan &amp; PUEBI</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/terjemahkan')" class="word-mini-dropdown-item">
                    <span>🌐 Terjemahkan Akademik</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/sitasi')" class="word-mini-dropdown-item">
                    <span>📚 Rekomendasikan Sitasi</span>
                </button>
                <button type="button" @mousedown.prevent @click="runAi('/cek-pedoman')" class="word-mini-dropdown-item">
                    <span>🎯 Cek Kepatuhan Pedoman</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- RIGHT-CLICK CONTEXT MENU (MICROSOFT WORD & LIBREOFFICE WRITER MENU)   -->
    <!-- ===================================================================== -->
    <div x-cloak
        x-show="showContextMenu"
        @click.outside="closeContextMenu()"
        @keydown.window.escape="closeContextMenu()"
        class="word-context-menu"
        :style="`top: ${contextMenuY}px; left: ${contextMenuX}px;`"
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 transform scale-95"
        x-transition:enter-end="opacity-100 transform scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="opacity-100 transform scale-100"
        x-transition:leave-end="opacity-0 transform scale-95">

        <!-- Mini-Toolbar Strip at Top -->
        <div class="word-context-minibar">
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.bold }" @mousedown.prevent="format('bold')" title="Huruf Tebal (Ctrl+B)"><strong>B</strong></button>
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.italic }" @mousedown.prevent="format('italic')" title="Huruf Miring (Ctrl+I)"><em>I</em></button>
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.underline }" @mousedown.prevent="format('underline')" title="Garis Bawah (Ctrl+U)"><u>U</u></button>
            <button type="button" class="word-mini-btn" :class="{ 'active': currentFormat.strike }" @mousedown.prevent="format('strikeThrough')" title="Coret Tengah (Ctrl+D)"><s>S</s></button>
            <span class="word-context-sep"></span>
            <button type="button" class="word-mini-btn" @mousedown.prevent="applyTextColor('#dc2626')" title="Warna Font"><span style="color:#dc2626; font-weight:700;">A</span></button>
            <button type="button" class="word-mini-btn" @mousedown.prevent="applyHighlight('#fef08a')" title="Sorotan Teks"><span style="background:#fef08a; padding:1px 3px; border-radius:2px; font-weight:700;">✎</span></button>
            <button type="button" class="word-mini-btn" @mousedown.prevent="clearDirectFormatting()" title="Hapus Format Langsung (Ctrl+Space)">T<sub>x</sub></button>
        </div>

        <!-- Clipboard Group -->
        <div class="word-context-group">
            <button type="button" class="word-context-item" @mousedown.prevent="cut(); closeContextMenu()">
                <span class="word-context-label">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.294a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z"/></svg>
                    Potong (Cut)
                </span>
                <span class="word-context-shortcut">Ctrl+X</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="copy(); closeContextMenu()">
                <span class="word-context-label">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path d="M8 3a1 1 0 011-1h2a1 1 0 110 2H9a1 1 0 01-1-1z"/><path d="M6 3a2 2 0 00-2 2v11a2 2 0 002 2h8a2 2 0 002-2V5a2 2 0 00-2-2 3 3 0 01-3 3H9a3 3 0 01-3-3z"/></svg>
                    Salin (Copy)
                </span>
                <span class="word-context-shortcut">Ctrl+C</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="paste(); closeContextMenu()">
                <span class="word-context-label">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path d="M9 2a1 1 0 000 2h2a1 1 0 100-2H9z"/><path fill-rule="evenodd" d="M4 5a2 2 0 012-2 3 3 0 003 3h2a3 3 0 003-3 2 2 0 012 2v11a2 2 0 01-2 2H6a2 2 0 01-2-2V5zm3 4a1 1 0 000 2h6a1 1 0 100-2H7zm0 4a1 1 0 100 2h6a1 1 0 100-2H7z" clip-rule="evenodd"/></svg>
                    Tempel (Paste)
                </span>
                <span class="word-context-shortcut">Ctrl+V</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="copyCurrentFormat(); closeContextMenu()">
                <span class="word-context-label">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path fill-rule="evenodd" d="M4 2a2 2 0 00-2 2v11a3 3 0 106 0V4a2 2 0 00-2-2H4zm1 14a1 1 0 100-2 1 1 0 000 2zm5-1.757l4.9-4.9a2 2 0 000-2.828L13.485 5.1a2 2 0 00-2.828 0L10 5.757v8.486zM16 18H9.071l6-6H16a2 2 0 012 2v2a2 2 0 01-2 2z" clip-rule="evenodd"/></svg>
                    Salin Format (Format Painter)
                </span>
                <span class="word-context-shortcut">Ctrl+Shift+C</span>
            </button>
        </div>

        <!-- Table Tools Contextual Group (ketika klik kanan di dalam sel tabel) -->
        <template x-if="contextIsTable">
            <div class="word-context-group">
                <div class="word-context-divider"></div>
                <div style="padding: 2px 9px 4px; font-size: 10px; font-weight: 700; color: #0f6cbd; text-transform: uppercase; letter-spacing: 0.05em;">
                    Alat Tabel (Table Tools)
                </div>
                <button type="button" class="word-context-item" @mousedown.prevent="insertTableRow(true); closeContextMenu()">
                    <span class="word-context-label">➕ Sisipkan Baris di Bawah</span>
                    <span class="word-context-shortcut">Row Below</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="insertTableRow(false); closeContextMenu()">
                    <span class="word-context-label">➕ Sisipkan Baris di Atas</span>
                    <span class="word-context-shortcut">Row Above</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="insertTableColumn(true); closeContextMenu()">
                    <span class="word-context-label">➕ Sisipkan Kolom di Kanan</span>
                    <span class="word-context-shortcut">Col Right</span>
                </button>
                <button type="button" class="word-context-item" @mousedown.prevent="insertTableColumn(false); closeContextMenu()">
                    <span class="word-context-label">➕ Sisipkan Kolom di Kiri</span>
                    <span class="word-context-shortcut">Col Left</span>
                </button>
                <button type="button" class="word-context-item" style="color:#dc2626;" @mousedown.prevent="deleteTableRow(); closeContextMenu()">
                    <span class="word-context-label">🗑️ Hapus Baris Ini</span>
                    <span class="word-context-shortcut">Del Row</span>
                </button>
                <button type="button" class="word-context-item" style="color:#dc2626;" @mousedown.prevent="deleteTableColumn(); closeContextMenu()">
                    <span class="word-context-label">🗑️ Hapus Kolom Ini</span>
                    <span class="word-context-shortcut">Del Col</span>
                </button>
                <button type="button" class="word-context-item" style="color:#dc2626;" @mousedown.prevent="deleteTable(); closeContextMenu()">
                    <span class="word-context-label">✕ Hapus Seluruh Tabel</span>
                    <span class="word-context-shortcut">Del Table</span>
                </button>
            </div>
        </template>

        <div class="word-context-divider"></div>

        <!-- Styles Group -->
        <div class="word-context-group">
            <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('normal'); closeContextMenu()">
                <span class="word-context-label">¶ Paragraf Normal</span>
                <span class="word-context-shortcut">Ctrl+Alt+0</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('heading1'); closeContextMenu()">
                <span class="word-context-label" style="font-weight:700;">H1 Judul 1 (Heading 1)</span>
                <span class="word-context-shortcut">Ctrl+Alt+1</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('heading2'); closeContextMenu()">
                <span class="word-context-label" style="font-weight:600;">H2 Judul 2 (Heading 2)</span>
                <span class="word-context-shortcut">Ctrl+Alt+2</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('bullet'); closeContextMenu()">
                <span class="word-context-label">• Daftar Butir (Bullets)</span>
                <span class="word-context-shortcut">Ctrl+Shift+L</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="applyBlockStyle('numbered'); closeContextMenu()">
                <span class="word-context-label">1. Daftar Bernomor (Numbered)</span>
                <span class="word-context-shortcut">1. + Spasi</span>
            </button>
        </div>

        <div class="word-context-divider"></div>

        <!-- Insert Group -->
        <div class="word-context-group">
            <button type="button" class="word-context-item" @mousedown.prevent="insertPageBreak(); closeContextMenu()">
                <span class="word-context-label">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"/></svg>
                    Pemisah Halaman (Page Break)
                </span>
                <span class="word-context-shortcut">Ctrl+Enter</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="insertTable(3, 3); closeContextMenu()">
                <span class="word-context-label">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor"><path fill-rule="evenodd" d="M5 4a3 3 0 00-3 3v6a3 3 0 003 3h10a3 3 0 003-3V7a3 3 0 00-3-3H5zm-1 9v-1h5v2H5a1 1 0 01-1-1zm7 1v-2h5v1a1 1 0 01-1 1h-4zm5-4h-5V8h5v2zM9 8v2H4V8a1 1 0 011-1h4z" clip-rule="evenodd"/></svg>
                    Sisipkan Tabel 3×3
                </span>
                <span class="word-context-shortcut">Word Grid</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="insertHorizontalRule(); closeContextMenu()">
                <span class="word-context-label">— Garis Pembatas Horisontal</span>
                <span class="word-context-shortcut">--- + Enter</span>
            </button>
        </div>

        <div class="word-context-divider"></div>

        <!-- Xishie Assistant AI Copilot Group -->
        <div class="word-context-group">
            <div style="padding: 2px 9px 4px; font-size: 10px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.05em;">
                Xishie Assistant AI Copilot
            </div>
            <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/poles'); closeContextMenu()">
                <span class="word-context-label">
                    <span class="word-context-copilot-badge">🪄 /poles</span>
                    <span>Poles Gaya Bahasa & Diksi</span>
                </span>
                <span class="word-context-shortcut">AI</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/parafrase'); closeContextMenu()">
                <span class="word-context-label">
                    <span class="word-context-copilot-badge">🔄 /parafrase</span>
                    <span>Parafrase Kalimat Terpilih</span>
                </span>
                <span class="word-context-shortcut">AI</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/puebi'); closeContextMenu()">
                <span class="word-context-label">
                    <span class="word-context-copilot-badge">✍️ /puebi</span>
                    <span>Koreksi Ejaan & PUEBI</span>
                </span>
                <span class="word-context-shortcut">AI</span>
            </button>
            <button type="button" class="word-context-item" @mousedown.prevent="triggerContextAssistant('/ringkas'); closeContextMenu()">
                <span class="word-context-label">
                    <span class="word-context-copilot-badge">📋 /ringkas</span>
                    <span>Ringkas Teks Dokumen</span>
                </span>
                <span class="word-context-shortcut">AI</span>
            </button>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- FLOATING IN-CANVAS COPILOT BUBBLE (MICROSOFT WORD 365 COPILOT INLINE) -->
    <!-- ===================================================================== -->
    <div x-cloak
        x-show="showBlockCopilot && !showMiniToolbar"
        @click.outside="showBlockCopilot = false"
        class="word-in-canvas-copilot"
        :style="`top: ${blockCopilotTop}px; left: ${blockCopilotLeft}px;`"
        x-data="{ blockCopilotMenu: false }"
        title="Xishie Assistant AI Copilot">
        <div style="position:relative; display:inline-flex; align-items:center;">
            <button type="button" class="word-block-copilot-btn" @click="blockCopilotMenu = !blockCopilotMenu">
                <span style="font-size:11px;">✨</span>
                <span>Copilot ▾</span>
            </button>
            <div x-show="blockCopilotMenu" @click.outside="blockCopilotMenu = false" class="word-mini-dropdown" style="width: 220px; top: 28px; right: 0;">
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/poles')" class="word-mini-dropdown-item">
                    <span>🪄 Poles Gaya Bahasa</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/parafrase')" class="word-mini-dropdown-item">
                    <span>🔄 Parafrase Anti-Plagiasi</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/kembangkan')" class="word-mini-dropdown-item">
                    <span>✍️ Kembangkan Paragraf</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/ringkaskan')" class="word-mini-dropdown-item">
                    <span>✂️ Padatkan / Ringkas</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/perbaiki-ejaan')" class="word-mini-dropdown-item">
                    <span>🔤 Koreksi Ejaan PUEBI</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/terjemahkan')" class="word-mini-dropdown-item">
                    <span>🌐 Terjemahan Akademik</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/rumus')" class="word-mini-dropdown-item">
                    <span>π Rekomendasi Rumus LaTeX</span>
                </button>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; runBlockAi('/cek-pedoman')" class="word-mini-dropdown-item">
                    <span>🎯 Audit Kepatuhan Kaidah</span>
                </button>
                <div style="height:1px; background:#e1dfdd; margin:2px 0;"></div>
                <button type="button" @mousedown.prevent @click="blockCopilotMenu = false; showBlockCopilot = false; $wire.openDialog('execute_model')" class="word-mini-dropdown-item" style="color:#4f46e5; font-weight:700;">
                    <span>⚡ Eksekusi Model AI Sekarang...</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; showBlockCopilot = false; $wire.openAssistant()" class="word-mini-dropdown-item" style="color:#0f6cbd; font-weight:600;">
                    <span>💬 Buka di Xishie Assistant...</span>
                </button>
            </div>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 1. TOP TITLEBAR (MICROSOFT WORD 365 TOP BAR)                          -->
    <!-- ===================================================================== -->
    <header class="word-titlebar">
        <!-- Left: Brand, AutoSave Switch, Quick Access Save, Undo, Redo -->
        <div class="word-titlebar__left">
            <!-- Brand Badge -->
            <a href="{{ route('home') }}" style="text-decoration:none; display:inline-flex; align-items:center; gap:6px; margin-right:4px;" title="NASKAH STUDIO — Beranda">
                <div style="width:22px; height:22px; background:#0f6cbd; border-radius:3px; display:flex; align-items:center; justify-content:center; color:#ffffff; font-weight:800; font-size:12px; font-family:var(--ms-font-ui);">
                    W
                </div>
                <span style="font-size:12px; font-weight:700; color:var(--ms-text-primary); letter-spacing:-0.01em;">NASKAH STUDIO</span>
            </a>

            <!-- AutoSave Toggle Switch (Matches Image 1) -->
            <div class="word-autosave" wire:click="toggleAutoSave" title="Toggle Microsoft Word AutoSave">
                <div class="word-switch {{ $autoSave ? 'active' : '' }}">
                    <div class="word-switch__thumb"></div>
                </div>
                <span>AutoSave</span>
            </div>

            <!-- Quick Access Toolbar (Save, Undo, Redo, Down Arrow) -->
            <div class="word-quick-actions">
                <button type="button" class="word-icon-btn" @click="saveDocument()" title="Save Document (Ctrl+S)">
                    <svg viewBox="0 0 20 20" width="15" height="15" fill="currentColor">
                        <path d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L15 2.586A2 2 0 0013.586 2H4zm1 2h8v3H5V4zm0 6h10v6H5v-6z"/>
                    </svg>
                </button>
                <button type="button" class="word-icon-btn" @click="undo()" title="Undo (Ctrl+Z)">
                    <svg viewBox="0 0 20 20" width="15" height="15" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v1a1 1 0 11-2 0v-1a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" class="word-icon-btn" @click="redo()" title="Redo (Ctrl+Y)">
                    <svg viewBox="0 0 20 20" width="15" height="15" fill="currentColor">
                        <path fill-rule="evenodd" d="M12.293 3.293a1 1 0 011.414 0l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414-1.414L14.586 9H9a5 5 0 00-5 5v1a1 1 0 11-2 0v-1a7 7 0 017-7h5.586l-2.293-2.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" class="word-icon-btn" wire:click="loadSampleHandbook" title="Reset / Reload Sample Handbook (Word)">
                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor">
                        <path d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"/>
                    </svg>
                </button>
            </div>
        </div>

        <!-- Center: Document Title & Saved Indicator (Matches Image 1) -->
        <div class="word-titlebar__center">
            <input type="text" wire:model.live.debounce.400ms="docTitle" wire:change="markDirty"
                class="word-doc-title-input"
                placeholder="The Open Studio Handbook">
            <span class="word-doc-status" style="display:inline-flex; align-items:center; gap:4px; font-size:11px;">
                <span x-show="savingState === 'saving'" style="color:#0f6cbd; font-weight:600; display:inline-flex; align-items:center; gap:4px;">
                    <svg class="animate-spin" viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10" stroke-opacity="0.25"></circle><path d="M12 2a10 10 0 0110 10" stroke-linecap="round"></path></svg>
                    Menyimpan...
                </span>
                <span x-show="savingState === 'saved' && !@js($dirty)" style="color:#107c41; font-weight:600;">✓ Tersimpan otomatis</span>
                <span x-show="savingState === 'unsaved' || (savingState === 'saved' && @js($dirty))" style="color:#d83b01; font-weight:600;">● Perubahan belum disimpan</span>
            </span>
        </div>

        <!-- Right: Search commands, Xishie Assistant Copilot, Profile Avatar -->
        <div class="word-titlebar__right">
            <!-- Search commands and help (Ctrl+K) -->
            <div class="word-search-box" wire:click="openDialog('palette')" style="cursor:pointer;" title="Search commands and help (Ctrl+K)">
                <svg viewBox="0 0 20 20" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="8.5" cy="8.5" r="5.5"/>
                    <path d="M13 13l4 4"/>
                </svg>
                <input type="text" class="word-search-input" placeholder="Search (Ctrl+K)" readonly style="cursor:pointer;">
                <kbd style="font-size:9px; background:#ffffff; border:1px solid #d1d1d1; padding:1px 3px; border-radius:2px; color:#616161;">Ctrl+K</kbd>
            </div>

            <!-- Eksekusi Model Sekarang Trigger Button -->
            <button type="button" wire:click="openDialog('execute_model')" class="word-assistant-pill-btn" style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#ffffff; font-weight:700;" title="Eksekusi Model AI Sekarang ke Naskah (Alt+X)">
                <span>⚡</span>
                <span>Eksekusi Model</span>
            </button>

            <!-- Active Model Badge -->
            <span class="badge" style="background:#eef2ff; color:#4338ca; border:1px solid #c7d2fe; font-size:.65rem; padding:.2rem .5rem; border-radius:4px; font-weight:700; display:inline-flex; align-items:center; gap:4px;" title="Model AI Aktif yang digunakan untuk eksekusi ke naskah">
                <span>🤖</span> {{ $this->getCurrentAssistantModel() }}
            </span>

            <!-- Xishie Assistant Topbar Trigger Button -->
            <button type="button" wire:click="openAssistant" class="word-assistant-pill-btn {{ $assistantOpen ? 'active' : '' }}" title="Buka Xishie Assistant (AI Copilot Penulisan Ilmiah)">
                <span class="word-assistant-pill-icon">✨</span>
                <span>Xishie Assistant</span>
            </button>

            <!-- User Avatar Circle WU (Matches Image 1) -->
            <div class="word-avatar" title="Akun Penulis (WU)">
                WU
            </div>
        </div>
    </header>

    <!-- ===================================================================== -->
    <!-- 2. RIBBON TABS ROW: FILE, HOME, INSERT... + COMMENTS, EDITING, SHARE  -->
    <!-- ===================================================================== -->
    <div x-show="!zenMode" class="word-ribbon-tabs-row">
        <!-- Left: Ribbon Tabs (File, Home, Insert, Draw, Design, Layout, References, Mailings, Review, View, Help) -->
        <div class="word-ribbon-tabs">
            @php
                $msTabs = [
                    'file' => 'File',
                    'home' => 'Home',
                    'insert' => 'Insert',
                    'draw' => 'Draw',
                    'design' => 'Design',
                    'layout' => 'Layout',
                    'references' => 'References',
                    'mailings' => 'Mailings',
                    'review' => 'Review',
                    'view' => 'View',
                    'table' => 'Table',
                    'help' => 'Help',
                ];
            @endphp

            @foreach ($msTabs as $tabKey => $tabLabel)
                <button type="button" wire:click="$set('activeTab', '{{ $tabKey }}')"
                    class="word-tab {{ $activeTab === $tabKey ? 'active' : '' }}">
                    {{ $tabLabel }}
                </button>
            @endforeach
        </div>

        <!-- Right: Copilot, Comments, Editing Mode Dropdown, Blue Share Button (Matches Word 365) -->
        <div class="word-ribbon-actions">
            <!-- Xishie Assistant Copilot Button -->
            <button type="button" wire:click="toggleTaskPane('assistant')" class="word-btn-pill {{ $assistantOpen ? 'active' : '' }}" style="background: linear-gradient(135deg, #eef2ff 0%, #f3e8ff 100%); border: 1px solid #c7d2fe; color: #4338ca; font-weight: 700; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(99,102,241,0.12);" title="Xishie Assistant (AI Copilot Penulisan Ilmiah)">
                <span style="font-size: 13px;">✨</span>
                <span>Copilot</span>
            </button>

            <!-- Comments Button -->
            <button type="button" wire:click="toggleTaskPane('comments')" class="word-btn-pill {{ $showComments ? 'active' : '' }}" title="Comments">
                <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor">
                    <path fill-rule="evenodd" d="M18 10c0 3.866-3.582 7-8 7a8.841 8.841 0 01-4.083-.98L2 17l1.338-3.123C2.493 12.767 2 11.434 2 10c0-3.866 3.582-7 8-7s8 3.134 8 7zM7 9H5v2h2V9zm8 0h-2v2h2V9zM9 9h2v2H9V9z" clip-rule="evenodd"/>
                </svg>
                <span>Comments</span>
                @if (count($comments) > 0)
                    <span style="background:#0f6cbd; color:#ffffff; font-size:10px; border-radius:9999px; padding:0 4px; font-weight:700;">{{ count($comments) }}</span>
                @endif
            </button>

            <!-- Editing Mode Dropdown -->
            <div style="position:relative;" x-data="{ open: false }">
                <button type="button" @click="open = !open" class="word-btn-pill" title="Editing Mode">
                    <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                        <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                    </svg>
                    <span>{{ ucfirst($editingMode) }} ▾</span>
                </button>
                <div x-show="open" @click.outside="open = false" style="position:absolute; right:0; top:28px; background:#ffffff; border:1px solid #e1dfdd; border-radius:6px; box-shadow:0 8px 24px rgba(0,0,0,0.12); width:170px; z-index:100; padding:4px 0; font-size:12px;">
                    <button type="button" @click="open = false" wire:click="setEditingMode('editing')" class="word-nav-item {{ $editingMode === 'editing' ? 'active' : '' }}">
                        ✏️ Editing
                    </button>
                    <button type="button" @click="open = false" wire:click="setEditingMode('reviewing')" class="word-nav-item {{ $editingMode === 'reviewing' ? 'active' : '' }}">
                        💬 Reviewing
                    </button>
                    <button type="button" @click="open = false" wire:click="setEditingMode('viewing')" class="word-nav-item {{ $editingMode === 'viewing' ? 'active' : '' }}">
                        👁️ Viewing
                    </button>
                </div>
            </div>

            <!-- Solid Blue Share Button (Matches Image 1) -->
            <button type="button" wire:click="openDialog('share')" class="word-btn-pill word-btn-share" title="Share &amp; Export Document">
                <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                    <path d="M15 8a3 3 0 10-2.977-2.63l-4.94 2.47a3 3 0 100 4.319l4.94 2.47a3 3 0 10.895-1.789l-4.94-2.47a3.027 3.027 0 000-.74l4.94-2.47C13.456 7.68 14.19 8 15 8z"/>
                </svg>
                <span>Share</span>
            </button>
        </div>
    </div>

    <!-- ===================================================================== -->
    <!-- 3. RIBBON TOOLBAR BODY (MICROSOFT WORD 365 COMMANDS)                  -->
    <!-- ===================================================================== -->
    <div x-show="!zenMode" class="word-ribbon-body">
        @if ($activeTab === 'file')
            <!-- TAB: FILE -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="newDocument" title="Create New Document (1 Page)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        <span>New</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="loadSampleHandbook" title="Load Sample Multi-Page Handbook">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/>
                        </svg>
                        <span>Sample</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="duplicateDocument" title="Duplicate Current Document">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                            <path d="M5 15H4a2 2 0 01-2-2V4a2 2 0 012-2h9a2 2 0 012 2v1"></path>
                        </svg>
                        <span>Duplicate</span>
                    </button>
                    <button type="button" class="word-btn-large" @click="saveDocument()" title="Save Document (Ctrl+S)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M19 21H5a2 2 0 01-2-2V5a2 2 0 012-2h11l5 5v11a2 2 0 01-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/>
                        </svg>
                        <span>Save</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('snapshots')" title="Version History &amp; Snapshots">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 14 14"/>
                        </svg>
                        <span>History</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('properties')" title="Document Metadata &amp; Properties">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <span>Properties</span>
                    </button>
                    <button type="button" class="word-btn-large" @click="window.print()" title="Print Document (Ctrl+P)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <polyline points="6 9 6 2 18 2 18 9"></polyline>
                            <path d="M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"></path>
                            <rect x="6" y="14" width="12" height="8"></rect>
                        </svg>
                        <span>Print</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Document</span></div>
            </div>

            <!-- Group: Export Direct -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="export('docx')" title="Download Microsoft Word .docx">
                        <div style="font-weight:800; font-size:12px; color:#0f6cbd;">DOCX</div>
                        <span>Word</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="export('odt')" title="Download OpenDocument .odt">
                        <div style="font-weight:800; font-size:12px; color:#107c41;">ODT</div>
                        <span>OpenDoc</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="export('latex')" title="Download LaTeX archive">
                        <div style="font-weight:800; font-size:12px; color:#7c3aed;">TeX</div>
                        <span>LaTeX</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="export('md')">📄 Markdown</button>
                        <button type="button" class="word-tool-btn" wire:click="export('rtf')">📑 Rich Text</button>
                        <button type="button" class="word-tool-btn" wire:click="export('html')">🌐 HTML Web</button>
                        <button type="button" class="word-tool-btn" wire:click="export('json')">📦 JSON Data</button>
                        <button type="button" class="word-tool-btn" wire:click="export('bibtex')">📚 BibTeX (.bib)</button>
                        <button type="button" class="word-tool-btn" wire:click="export('txt')">📝 Plain Text</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Export &amp; Share</span></div>
            </div>

            <!-- Group: Templates -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="loadSampleHandbook" title="Reset to The Open Studio Handbook">
                            📖 Reload Handbook
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="runTool('/cek-pedoman')" title="Check Guideline Compliance">
                            🎯 Check DIKTI / Pedoman
                        </button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Templates</span></div>
            </div>

        @elseif ($activeTab === 'home')
            <!-- GROUP: CLIPBOARD -->
            <div class="word-group">
                <div class="word-group__content">
                    <!-- Big Paste Button -->
                    <button type="button" class="word-btn-large" @mousedown.prevent="paste()" title="Paste from Clipboard (Ctrl+V)">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            <path d="M9 14l2 2 4-4"/>
                        </svg>
                        <span>Paste</span>
                    </button>

                    <!-- Cut, Copy, Format Painter -->
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" @mousedown.prevent="cut()" title="Cut (Ctrl+X)">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.294a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" clip-rule="evenodd"/>
                            </svg>
                            <span>Cut</span>
                        </button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="copy()" title="Copy (Ctrl+C)">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                                <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z"/>
                                <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z"/>
                            </svg>
                            <span>Copy</span>
                        </button>
                        <button type="button" class="word-tool-btn" :class="{ 'active': copiedFormat !== null || @js((bool)$copiedFormat) }" @mousedown.prevent="copyCurrentFormat()" title="Format Painter (Ctrl+Shift+C)">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                                <path d="M13.586 3.586a2 2 0 112.828 2.828l-.793.793-2.828-2.828.793-.793zM11.379 5.793L3 14.172V17h2.828l8.38-8.379-2.83-2.828z"/>
                            </svg>
                            <span>Format</span>
                        </button>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Clipboard</span>
                </div>
            </div>

            <!-- GROUP: FONT -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <!-- Row 1: Font Family, Size, Grow, Shrink, Case, Clear -->
                        <div class="word-group__row">
                            <select class="word-select" style="width:115px;" @mousedown="saveSelection()" @change="applyFontFamily($event.target.value)" :value="currentFormat.fontFamily || '{{ $styleMeta['fontFamily'] ?? 'serif' }}'">
                                <option value="sans" {{ ($styleMeta['fontFamily'] ?? '') === 'sans' ? 'selected' : '' }}>Aptos</option>
                                <option value="calibri" {{ ($styleMeta['fontFamily'] ?? '') === 'calibri' ? 'selected' : '' }}>Calibri</option>
                                <option value="times" {{ ($styleMeta['fontFamily'] ?? '') === 'times' ? 'selected' : '' }}>Times New Roman</option>
                                <option value="arial" {{ ($styleMeta['fontFamily'] ?? '') === 'arial' ? 'selected' : '' }}>Arial</option>
                                <option value="serif" {{ ($styleMeta['fontFamily'] ?? 'serif') === 'serif' ? 'selected' : '' }}>Source Serif 4</option>
                                <option value="mono" {{ ($styleMeta['fontFamily'] ?? '') === 'mono' ? 'selected' : '' }}>JetBrains Mono</option>
                            </select>
                            <select class="word-select" style="width:48px;" @mousedown="saveSelection()" @change="applyFontSize($event.target.value)" :value="currentFormat.fontSize || {{ $styleMeta['fontSize'] ?? 16 }}">
                                @foreach ([10, 11, 12, 14, 16, 18, 20, 24, 28, 32] as $fs)
                                    <option value="{{ $fs }}" {{ ($styleMeta['fontSize'] ?? 16) == $fs ? 'selected' : '' }}>{{ $fs }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="word-btn-square" @mousedown.prevent="applyFontSize(Math.min(36, (currentFormat.fontSize || 16) + 1))" title="Increase Font Size (Ctrl+])">A<sup>▲</sup></button>
                            <button type="button" class="word-btn-square" @mousedown.prevent="applyFontSize(Math.max(8, (currentFormat.fontSize || 16) - 1))" title="Decrease Font Size (Ctrl+[)">A<sup>▼</sup></button>
                            <div style="position:relative; display:inline-block;" x-data="{ openCase: false }">
                                <button type="button" class="word-btn-square" @click="openCase = !openCase" title="Change Case (Aa ▾)">Aa</button>
                                <div x-show="openCase" @click.outside="openCase = false" class="word-mini-dropdown" style="width: 165px; top: 26px; left: 0;">
                                    <button type="button" @click="openCase = false" wire:click="transformActiveBlockCase('sentence')" class="word-mini-dropdown-item">Sentence case</button>
                                    <button type="button" @click="openCase = false" wire:click="transformActiveBlockCase('lower')" class="word-mini-dropdown-item">lowercase</button>
                                    <button type="button" @click="openCase = false" wire:click="transformActiveBlockCase('upper')" class="word-mini-dropdown-item">UPPERCASE</button>
                                    <button type="button" @click="openCase = false" wire:click="transformActiveBlockCase('title')" class="word-mini-dropdown-item">Capitalize Each Word</button>
                                </div>
                            </div>
                            <button type="button" class="word-btn-square" @mousedown.prevent="clearCurrentFormat()" title="Clear All Formatting (Ctrl+Space)">✕</button>
                        </div>

                        <!-- Row 2: B, I, U, Strike, Sub, Super, Glow, Highlight, Color -->
                        <div class="word-group__row">
                            <button type="button" class="word-btn-square" style="font-weight:700;" :class="{ 'active': currentFormat.bold }" @mousedown.prevent="format('bold')" title="Bold (Ctrl+B)">B</button>
                            <button type="button" class="word-btn-square" style="font-style:italic;" :class="{ 'active': currentFormat.italic }" @mousedown.prevent="format('italic')" title="Italic (Ctrl+I)">I</button>
                            <button type="button" class="word-btn-square" style="text-decoration:underline;" :class="{ 'active': currentFormat.underline }" @mousedown.prevent="format('underline')" title="Underline (Ctrl+U)">U ▾</button>
                            <button type="button" class="word-btn-square" style="text-decoration:line-through;" :class="{ 'active': currentFormat.strike }" @mousedown.prevent="format('strikeThrough')" title="Strikethrough (ab)">ab</button>
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.subscript }" @mousedown.prevent="format('subscript')" title="Subscript (x₂)">x₂</button>
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.superscript }" @mousedown.prevent="format('superscript')" title="Superscript (x²)">x²</button>
                            <button type="button" class="word-btn-square" style="color:#0f6cbd;" wire:click="applyTheme('modern')" title="Text Effects">A</button>

                            <!-- Highlight Pen -->
                            <div class="word-color-picker-btn" @mousedown.prevent="format('hiliteColor', '#fef08a')" title="Highlight Color">
                                <span>🖊️</span>
                                <div class="word-color-bar" style="background:#fde68a;"></div>
                            </div>

                            <!-- Font Color -->
                            <div class="word-color-picker-btn" @mousedown.prevent="format('foreColor', '#b91c1c')" title="Font Color">
                                <span style="font-weight:700; line-height:1;">A</span>
                                <div class="word-color-bar" style="background:#b91c1c;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Font</span>
                </div>
            </div>

            <!-- GROUP: PARAGRAPH -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <!-- Row 1: Bullets, Numbering, Multilevel, Indent-, Indent+, Marks -->
                        <div class="word-group__row">
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.isBullet }" @mousedown.prevent="format('insertUnorderedList')" title="Bullets">• ▾</button>
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.isNumbered }" @mousedown.prevent="format('insertOrderedList')" title="Numbering">1. ▾</button>
                            <button type="button" class="word-btn-square" wire:click="addBlock('bullet')" title="Multilevel List">▾</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent="applyIndent(-1)" title="Decrease Indent (Shift+Tab)">⇤</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent="applyIndent(1)" title="Increase Indent (Tab)">⇥</button>
                            <button type="button" class="word-btn-square" wire:click="$toggle('showGridlines')" title="Show/Hide Marks (¶)">¶</button>
                        </div>

                        <!-- Row 2: Align Left, Center, Right, Justify, Spacing, Shading, Borders -->
                        <div class="word-group__row">
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.align === 'left' }" @mousedown.prevent="applyAlignment('left')" title="Align Left (Ctrl+L)">⫷</button>
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.align === 'center' }" @mousedown.prevent="applyAlignment('center')" title="Center (Ctrl+E)">≡</button>
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.align === 'right' }" @mousedown.prevent="applyAlignment('right')" title="Align Right (Ctrl+R)">⫸</button>
                            <button type="button" class="word-btn-square" :class="{ 'active': currentFormat.align === 'justify' }" @mousedown.prevent="applyAlignment('justify')" title="Justify (Ctrl+J)">☷</button>
                            <div style="position:relative;" x-data="{ openSpacing: false }">
                                <button type="button" class="word-btn-square" @click="openSpacing = !openSpacing" title="Line Spacing (1.0, 1.15, 1.5, 2.0)">↕ ▾</button>
                                <div x-show="openSpacing" @click.outside="openSpacing = false" style="position:absolute; left:0; top:24px; background:#ffffff; border:1px solid #e1dfdd; border-radius:4px; box-shadow:0 4px 16px rgba(0,0,0,0.12); width:135px; z-index:100; padding:4px 0; font-size:11.5px;">
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('1.0')" class="word-nav-item {{ (string)($pageSetup['lineSpacing'] ?? '1.5') === '1' || ($pageSetup['lineSpacing'] ?? '') == 1.0 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">1.0 Single</button>
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('1.15')" class="word-nav-item {{ ($pageSetup['lineSpacing'] ?? '') == 1.15 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">1.15 Normal</button>
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('1.5')" class="word-nav-item {{ ($pageSetup['lineSpacing'] ?? '') == 1.5 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">1.5 Academic (DIKTI)</button>
                                    <button type="button" @click="openSpacing = false" @mousedown.prevent="applyLineSpacing('2.0')" class="word-nav-item {{ ($pageSetup['lineSpacing'] ?? '') == 2.0 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">2.0 Double</button>
                                </div>
                            </div>
                            <button type="button" class="word-btn-square" wire:click="updatePageSetup('pageColor', '#fdfbf7')" title="Shading">🪣</button>
                            <button type="button" class="word-btn-square" wire:click="openDialog('table')" title="Borders">⊞ ▾</button>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Paragraph</span>
                </div>
            </div>

            <!-- GROUP: STYLES GALLERY (PREVIEW CARDS) -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-styles-gallery">
                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'normal' }"
                            @mousedown.prevent="applyBlockStyle('normal')" title="Normal (Ctrl+Alt+0)">
                            <div class="word-style-tile__preview" style="font-family:var(--ms-font-ui); font-size:12px;">AaBbCcDd</div>
                            <div class="word-style-tile__name">Normal</div>
                        </div>

                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'nospacing' }"
                            @mousedown.prevent="applyBlockStyle('normal')" title="No Spacing">
                            <div class="word-style-tile__preview" style="font-family:var(--ms-font-ui); font-size:11px; letter-spacing:-0.5px;">AaBbCcDd</div>
                            <div class="word-style-tile__name">No Spacing</div>
                        </div>

                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'heading1' }"
                            @mousedown.prevent="applyBlockStyle('heading1')" title="Heading 1 (Ctrl+Alt+1)">
                            <div class="word-style-tile__preview" style="color:var(--ms-blue-dark); font-weight:700; font-size:13px;">AaBbC</div>
                            <div class="word-style-tile__name">Heading 1</div>
                        </div>

                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'title' }"
                            @mousedown.prevent="applyBlockStyle('title')" title="Title">
                            <div class="word-style-tile__preview" style="font-weight:800; font-size:14px; color:#111827;">AaBb</div>
                            <div class="word-style-tile__name">Title</div>
                        </div>

                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'heading2' }"
                            @mousedown.prevent="applyBlockStyle('heading2')" title="Heading 2 (Ctrl+Alt+2)">
                            <div class="word-style-tile__preview" style="color:var(--ms-blue-heading); font-weight:600; font-size:12.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Heading 2</div>
                        </div>

                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'subtitle' }"
                            @mousedown.prevent="applyBlockStyle('subtitle')" title="Subtitle">
                            <div class="word-style-tile__preview" style="color:#6b7280; font-size:11.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Subtitle</div>
                        </div>

                        <div class="word-style-tile" :class="{ 'active': currentFormat.style === 'quote' }"
                            @mousedown.prevent="applyBlockStyle('quote')" title="Quote">
                            <div class="word-style-tile__preview" style="color:#6b7280; font-style:italic; font-size:11.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Quote</div>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Styles</span>
                </div>
            </div>

            <!-- GROUP: EDITING -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="openDialog('find')" title="Find (Ctrl+F)">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor"><path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd"/></svg>
                            <span>Find</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('find')" title="Replace">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor"><path d="M4 2a1 1 0 011 1v2.101a7.002 7.002 0 0111.601 2.566 1 1 0 11-1.885.666A5.002 5.002 0 005.999 7H9a1 1 0 010 2H4a1 1 0 01-1-1V3a1 1 0 011-1zm.008 9.057a1 1 0 011.276.61A5.002 5.002 0 0014.001 13H11a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0v-2.101a7.002 7.002 0 01-11.601-2.566 1 1 0 01.61-1.276z"/></svg>
                            <span>Replace</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('palette')" title="Select">
                            <span>Select ▾</span>
                        </button>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Editing</span>
                </div>
            </div>

            <!-- GROUP: VOICE -->
            <div class="word-group" x-data="{
                listening: false,
                recognition: null,
                init() {
                    const SpeechRec = window.SpeechRecognition || window.webkitSpeechRecognition;
                    if (SpeechRec) {
                        this.recognition = new SpeechRec();
                        this.recognition.continuous = true;
                        this.recognition.interimResults = true;
                        this.recognition.lang = 'id-ID';
                        this.recognition.onresult = (event) => {
                            let transcript = '';
                            for (let i = event.resultIndex; i < event.results.length; i++) {
                                if (event.results[i].isFinal) {
                                    transcript += event.results[i][0].transcript + ' ';
                                }
                            }
                            if (transcript) {
                                document.execCommand('insertText', false, transcript);
                                syncAllPages();
                            }
                        };
                        this.recognition.onerror = () => {
                            this.listening = false;
                        };
                        this.recognition.onend = () => {
                            this.listening = false;
                        };
                    }
                },
                toggle() {
                    if (!this.recognition) {
                        $wire.openAssistant();
                        return;
                    }
                    if (this.listening) {
                        this.recognition.stop();
                        this.listening = false;
                    } else {
                        try {
                            this.recognition.start();
                            this.listening = true;
                        } catch (err) {
                            $wire.openAssistant();
                        }
                    }
                }
            }">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" :class="{ 'active': listening }" @click="toggle()" :title="listening ? 'Mendengarkan ucapan... (Klik untuk berhenti)' : 'Dictate / Dikte Suara (Web Speech API / AI)'">
                        <div style="width:24px; height:24px; border-radius:9999px; background:#0f6cbd; color:#ffffff; display:flex; align-items:center; justify-content:center; transition:all 0.2s;" :style="listening ? 'background:#dc2626; animation:pulse 1.2s infinite;' : ''">
                            <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor">
                                <path fill-rule="evenodd" d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100-2h-3v-2.07z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span x-text="listening ? 'Mendengar' : 'Dictate'">Dictate</span>
                    </button>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Voice</span>
                </div>
            </div>

            <!-- GROUP: EDITOR -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openAssistant" title="Microsoft Editor &amp; Xishie Proofing">
                        <div style="position:relative; width:24px; height:24px; display:flex; align-items:center; justify-content:center; color:#0f6cbd;">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span style="position:absolute; top:1px; right:1px; width:6px; height:6px; background:#107c41; border-radius:9999px;"></span>
                        </div>
                        <span>Editor</span>
                    </button>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Editor</span>
                </div>
            </div>

            <!-- GROUP: XISHIE ASSISTANT (COPILOT) -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large word-btn-assistant-highlight" wire:click="openAssistant" title="Buka Xishie Assistant (AI Copilot Penulisan Ilmiah)">
                        <div class="word-copilot-icon-badge">
                            ✨
                        </div>
                        <span style="font-weight:700; color:#4338ca;">Xishie AI</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="runTool('/poles')" title="Poles Teks Akademik">
                            <span>🪄 Poles Teks</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="runTool('/parafrase')" title="Parafrase Paragraf">
                            <span>🔄 Parafrase</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="runTool('/cek-pedoman')" title="Audit Kepatuhan Pedoman">
                            <span>📋 Pedoman</span>
                        </button>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Copilot</span>
                </div>
            </div>

            <!-- GROUP: MODEL AI PRESETS (AKADEMIK) -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%); border-color: #a5b4fc;" title="Eksekusi model AI saat ini ke naskah">
                        <span style="font-size:18px;">⚡</span>
                        <span style="font-weight:700; color:#4338ca;">Model AI</span>
                    </button>
                    <div class="word-group__col">
                        <div class="word-group__row">
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('polish')" title="Poles ragam bahasa akademik">✨ Poles</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('expand')" title="Kembangkan argumen ilmiah">📖 Kembangkan</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('novelty')" title="Formulasi kebaruan riset">💡 Kebaruan</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('abstract')" title="Susun draf abstrak akademik">📝 Abstrak</button>
                        </div>
                        <div class="word-group__row">
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('methodology')" title="Susun metodologi riset">📐 Metode</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('discussion')" title="Sintesis pembahasan kritis naskah">💬 Bahas</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('conclusion')" title="Formulasi kesimpulan &amp; saran">🏁 Simpulan</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('readability')" title="Audit keterbacaan naskah">📈 Baca</button>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Generasi AI</span>
                </div>
            </div>

        <!-- TAB: INSERT -->
        @elseif ($activeTab === 'insert')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="insertCoverPage('skripsi')" title="Insert Academic Cover Page at Beginning">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="2" width="16" height="20" rx="2"/><line x1="8" y1="6" x2="16" y2="6"/><line x1="8" y1="10" x2="16" y2="10"/><line x1="8" y1="14" x2="12" y2="14"/></svg>
                        <span>Cover Page</span>
                    </button>
                    <button type="button" class="word-btn-large" @mousedown.prevent="insertPageBreak()" title="Insert Blank Page">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span>Blank Page</span>
                    </button>
                    <button type="button" class="word-btn-large" @mousedown.prevent="insertPageBreak()" title="Insert Page Break (Ctrl+Enter)">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v6h12V8z"/><path d="M6 16v6a2 2 0 002 2h12a2 2 0 002-2v-6H6z"/><line x1="2" y1="12" x2="22" y2="12" stroke-dasharray="2 2"/></svg>
                        <span>Page Break</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Pages</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('table')" title="Insert Custom Table">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M3 15h18M9 3v18M15 3v18"/></svg>
                        <span>Table ▾</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTable(3, 3)" title="Quick 3x3 Table">⊞ 3x3 Quick</button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTableRow(true)" title="Add Row Below (Tab pada baris terakhir)">➕ Row</button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTableColumn(true)" title="Add Column Right">➕ Col</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Tables</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('image')" title="Insert Picture (Upload or URL)">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span>Pictures</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="insertSampleDiagram('flowchart')" title="Sisipkan Diagram Alur Metodologi">📊 Flowchart</button>
                        <button type="button" class="word-tool-btn" wire:click="insertSampleDiagram('piechart')" title="Sisipkan Diagram Lingkaran">🥧 Pie Chart</button>
                        <button type="button" class="word-tool-btn" wire:click="insertCallout('info')" title="Sisipkan Kotak Catatan Khusus">📌 Callout Note</button>
                        <button type="button" class="word-tool-btn" wire:click="insertCallout('important')" title="Kotak Catatan Penting">⚡ Important</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Illustrations</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="openDialog('link')">🔗 Hyperlink</button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('footnote')">⚓ Footnote</button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertHorizontalRule()" title="Horizontal Line (---)">➖ Horizontal Line</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Links &amp; Notes</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="openDialog('pagesetup')">📄 Header &amp; Footer</button>
                        <button type="button" class="word-tool-btn" wire:click="insertPageNumberField"># Page Number</button>
                        <button type="button" class="word-tool-btn" wire:click="insertDate">📅 Date &amp; Time</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Header &amp; Footer</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('equation')" title="Insert LaTeX Equation (Alt+=)">
                        <span style="font-size:20px; font-weight:700; line-height:1; font-family:'Cambria Math', serif;">π</span>
                        <span>Equation</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('symbols')" title="Insert Special Character">
                        <span style="font-size:20px; font-weight:700; line-height:1;">Ω</span>
                        <span>Symbol</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="insertTableOfContents" title="Insert Table of Contents">
                        <span style="font-size:18px;">📑</span>
                        <span>TOC</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Symbols</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%); border-color: #a5b4fc;" title="Eksekusi model AI saat ini ke naskah">
                        <span style="font-size:18px;">⚡</span>
                        <span style="font-weight:700; color:#4338ca;">Model AI</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="executeModelPreset('table')" title="Sintesis matriks tabel data akademik menggunakan model aktif">📊 Tabel AI</button>
                        <button type="button" class="word-tool-btn" wire:click="executeModelPreset('equation')" title="Formulasikan rumus LaTeX menggunakan model aktif">∑ Rumus AI</button>
                        <button type="button" class="word-tool-btn" wire:click="runTool('/matriks-literatur')" title="Sintesis matriks perbandingan literatur (SOTA)">📚 Matriks AI</button>
                        <button type="button" class="word-tool-btn" wire:click="executeModelPreset('novelty')" title="Formulasikan matriks kebaruan riset">💡 Kebaruan AI</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Generasi AI</span></div>
            </div>

        <!-- TAB: DRAW -->
        @elseif ($activeTab === 'draw')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large active" title="Select / Cursor Mode">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3l7 18 3-7 7-3L3 3z"/></svg>
                        <span>Select</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="formatInline('highlight', '#bfdbfe')" title="Blue Pen Drawing">
                        <div style="width:20px; height:20px; background:#0f6cbd; border-radius:9999px;"></div>
                        <span>Blue Pen</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="formatInline('highlight', '#fecaca')" title="Red Pen Drawing">
                        <div style="width:20px; height:20px; background:#dc2626; border-radius:9999px;"></div>
                        <span>Red Pen</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="formatInline('highlight', '#fef08a')" title="Yellow Highlighter">
                        <div style="width:20px; height:20px; background:#eab308; border-radius:9999px;"></div>
                        <span>Highlighter</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="formatInline('highlight', null)" title="Eraser">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M20 20H7L3 16C2 15 2 13 3 12L13 2C14 1 16 1 17 2L22 7C23 8 23 10 22 11L13 20"/></svg>
                        <span>Eraser</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Drawing Tools</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="insertCallout('sketch')">🎨 Drawing Sketch Note</button>
                        <button type="button" class="word-tool-btn" wire:click="clearFormatting">✕ Clear All Ink</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Convert &amp; Canvas</span></div>
            </div>

        <!-- TAB: DESIGN -->
        @elseif ($activeTab === 'design')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="applyTheme('modern')" title="Office Modern Theme">
                        <div style="width:20px; height:20px; border-radius:3px; background:#0f6cbd;"></div>
                        <span>Modern</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="applyTheme('classic')" title="Classic Academic Theme">
                        <div style="width:20px; height:20px; border-radius:3px; background:#1e3a8a;"></div>
                        <span>Academic</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="applyTheme('serif')" title="Editorial Serif Theme">
                        <div style="width:20px; height:20px; border-radius:3px; background:#7c2d12;"></div>
                        <span>Editorial</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Document Themes</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="setWatermark('DRAFT')">💧 Watermark: DRAFT</button>
                        <button type="button" class="word-tool-btn" wire:click="setWatermark('CONFIDENTIAL')">🔒 Watermark: CONFIDENTIAL</button>
                        <button type="button" class="word-tool-btn" wire:click="setWatermark('')">✕ Clear Watermark</button>
                    </div>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="updatePageSetup('pageColor', '#ffffff')">⚪ White Page</button>
                        <button type="button" class="word-tool-btn" wire:click="updatePageSetup('pageColor', '#fdfbf7')">📜 Cream / Sepia</button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('pagesetup')">🔲 Page Borders</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Page Background</span></div>
            </div>

        <!-- TAB: LAYOUT -->
        @elseif ($activeTab === 'layout')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('pagesetup')" title="Margins Setup">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="4" y="4" width="16" height="16" rx="1"/><line x1="8" y1="4" x2="8" y2="20"/><line x1="16" y1="4" x2="16" y2="20"/><line x1="4" y1="8" x2="20" y2="8"/><line x1="4" y1="16" x2="20" y2="16"/></svg>
                        <span>Margins ▾</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="setMarginsPreset('dikti')" title="Batas Margin Standar DIKTI (4-4-3-3 cm: Kiri 4, Atas 4, Kanan 3, Bawah 3)">
                        <span style="font-size:18px;">📐</span>
                        <span>DIKTI 4-4-3-3</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="setOrientation('{{ ($pageSetup['orientation'] ?? 'portrait') === 'portrait' ? 'landscape' : 'portrait' }}')" title="Page Orientation">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="6" width="18" height="12" rx="2"/></svg>
                        <span>{{ ucfirst($pageSetup['orientation'] ?? 'portrait') }}</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('pagesetup')" title="Paper Size">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/></svg>
                        <span>Size: {{ $pageSetup['size'] ?? 'A4' }}</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Page Setup</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="formatParagraph('indent-')">⇤ Decrease Indent</button>
                        <button type="button" class="word-tool-btn" wire:click="formatParagraph('indent+')">⇥ Increase Indent</button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertPageBreak()" title="Pemisah Halaman (Ctrl+Enter)">✂️ Insert Break</button>
                        <button type="button" class="word-tool-btn {{ !empty($pageSetup['lineNumbers']) ? 'active' : '' }}" wire:click="toggleLineNumbers" title="Toggle Document Line Numbers for Peer Review">#️⃣ Line Numbers: {{ !empty($pageSetup['lineNumbers']) ? 'On' : 'Off' }}</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Paragraph</span></div>
            </div>

        <!-- TAB: REFERENCES (ACUAN DAFTAR PUSTAKA & DAFTAR ISI) -->
        @elseif ($activeTab === 'references')
            <!-- Group: Table of Contents -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="insertTableOfContents" title="Generate Automatic Table of Contents based on Headings">
                        <span style="font-size:20px;">📑</span>
                        <span>Table of Contents</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Table of Contents</span></div>
            </div>

            <!-- Group: Footnotes & Endnotes -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('footnote')" title="Insert Footnote (Catatan Kaki)">
                        <span style="font-size:18px;">⚓</span>
                        <span>Footnote</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('endnote')" title="Insert Endnote (Catatan Akhir)">
                        <span style="font-size:18px;">📖</span>
                        <span>Endnote</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Footnotes &amp; Endnotes</span></div>
            </div>

            <!-- Group: Citations & Bibliography -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <div class="word-group__row">
                            <button type="button" class="word-tool-btn" wire:click="insertCitation" title="Insert Academic Citation into Manuscript">
                                📚 Insert Citation
                            </button>
                            <button type="button" class="word-tool-btn" wire:click="setNavigationTab('pustaka')" title="Buka panel daftar referensi riset terverifikasi">
                                📖 Pustaka Riset
                            </button>
                            <button type="button" class="word-tool-btn" wire:click="openDialog('sources')" title="Manage Sources">
                                🗂️ Manage Sources
                            </button>
                        </div>
                        <div class="word-group__row" style="align-items: center; gap: 6px;">
                            <span style="font-size: 11px; color: var(--ms-text-secondary);">Style:</span>
                            <select wire:change="setCitationStyle($event.target.value)" class="word-select" style="width: 95px; height: 22px; font-size: 11px;">
                                <option value="APA" {{ ($citationStyle ?? 'APA') === 'APA' ? 'selected' : '' }}>APA (7th)</option>
                                <option value="IEEE" {{ ($citationStyle ?? 'APA') === 'IEEE' ? 'selected' : '' }}>IEEE</option>
                                <option value="Harvard" {{ ($citationStyle ?? 'APA') === 'Harvard' ? 'selected' : '' }}>Harvard</option>
                                <option value="Chicago" {{ ($citationStyle ?? 'APA') === 'Chicago' ? 'selected' : '' }}>Chicago</option>
                                <option value="MLA" {{ ($citationStyle ?? 'APA') === 'MLA' ? 'selected' : '' }}>MLA (9th)</option>
                            </select>
                            <button type="button" class="word-tool-btn" wire:click="insertBibliography" title="Generate Bibliography / Daftar Pustaka Section at End of Document">
                                📜 Bibliography
                            </button>
                            <button type="button" class="word-tool-btn" wire:click="updateBibliography" title="Update Bibliography and Re-format with Active Style">
                                🔄 Update Bib
                            </button>
                            <button type="button" class="word-tool-btn" wire:click="auditCitations" title="Audit Citations Integrity and Detect Potential Hallucinations">
                                🔍 Audit Citations
                            </button>
                            <button type="button" class="word-tool-btn" wire:click="runTool('/daftar-pustaka')" title="Format Daftar Pustaka Standar Kaidah Ilmiah (/daftar-pustaka)">
                                📚 Format Pustaka
                            </button>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Citations &amp; Bibliography</span></div>
            </div>

            <!-- Group: Captions & Cross-Reference -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="openDialog('crossref')" title="Sisipkan Rujukan Silang ke Persamaan / Gambar / Tabel">🔗 Cross-Reference</button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('equation')" title="Sisipkan Persamaan Matematika">π Persamaan</button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('image')" title="Sisipkan Gambar Ilmiah">🖼️ Gambar</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Captions &amp; Cross-Ref</span></div>
            </div>

            <!-- Group: Research & Literature Gap -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="runTool('/cek-gap')">🔬 /cek-gap Research Gap</button>
                        <button type="button" class="word-tool-btn" wire:click="runTool('/matriks-literatur')">📊 /matriks Sintesis SOTA</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Research &amp; Validation</span></div>
            </div>

        <!-- TAB: MAILINGS -->
        @elseif ($activeTab === 'mailings')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('merge')" title="Start Mail Merge with CSV">
                        <span style="font-size:20px;">✉️</span>
                        <span>Start Merge</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Start Mail Merge</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="openDialog('merge')">📂 Select CSV Data Source</button>
                        <button type="button" class="word-tool-btn" wire:click="insertMerge">➕ Insert Merge Field</button>
                        <button type="button" class="word-tool-btn" wire:click="previewMerge">👁️ Preview Results</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Write &amp; Insert Fields</span></div>
            </div>

        <!-- TAB: REVIEW -->
        @elseif ($activeTab === 'review')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="runSpellCheck">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        <span>Spelling</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('thesaurus')">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 19.5A2.5 2.5 0 016.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 014 19.5v-15A2.5 2.5 0 016.5 2z"/></svg>
                        <span>Thesaurus</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('stats')">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
                        <span>Word Count</span>
                    </button>
                    <div style="position:relative; display:inline-block;" x-data="{ openLang: false }">
                        <button type="button" class="word-btn-large" @click="openLang = !openLang" title="Proofing Language (PUEBI / English)">
                            <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="10"/><line x1="2" y1="12" x2="22" y2="12"/><path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>
                            <span>{{ strtoupper($proofingLanguage ?? 'id') }}</span>
                        </button>
                        <div x-show="openLang" @click.outside="openLang = false" class="word-mini-dropdown" style="width: 175px; top: 48px; left: 0;">
                            <button type="button" @click="openLang = false" wire:click="setProofingLanguage('id')" class="word-mini-dropdown-item {{ ($proofingLanguage ?? 'id') === 'id' ? 'active' : '' }}">🇮🇩 Indonesia (PUEBI)</button>
                            <button type="button" @click="openLang = false" wire:click="setProofingLanguage('en-us')" class="word-mini-dropdown-item {{ ($proofingLanguage ?? 'id') === 'en-us' ? 'active' : '' }}">🇺🇸 English (US Academic)</button>
                            <button type="button" @click="openLang = false" wire:click="setProofingLanguage('en-gb')" class="word-mini-dropdown-item {{ ($proofingLanguage ?? 'id') === 'en-gb' ? 'active' : '' }}">🇬🇧 English (GB Academic)</button>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Proofing</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-tool-btn {{ $showComments ? 'active' : '' }}" wire:click="toggleTaskPane('comments')">💬 Show Comments</button>
                    <button type="button" class="word-tool-btn" wire:click="openDialog('comment')">➕ New Comment</button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Comments</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-tool-btn {{ $showRevisions ? 'active' : '' }}" wire:click="toggleTaskPane('revisions')">👁️ Track Changes</button>
                    <button type="button" class="word-tool-btn" style="color:#107c41;" wire:click="acceptAllRevisions">✓ Accept All</button>
                    <button type="button" class="word-tool-btn" style="color:#c50f1f;" wire:click="rejectAllRevisions">✕ Reject All</button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Tracking &amp; Changes</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('execute_model')" style="background: linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%); border-color: #a5b4fc;" title="Eksekusi model AI saat ini ke naskah">
                        <span style="font-size:18px;">⚡</span>
                        <span style="font-weight:700; color:#4338ca;">Eksekusi Model</span>
                    </button>
                    <div class="word-group__col">
                        <div class="word-group__row">
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('polish')" title="Poles ragam bahasa akademik">✨ Poles Cepat</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('expand')" title="Kembangkan argumen ilmiah">📖 Kembangkan</button>
                            <button type="button" class="word-tool-btn" wire:click="executeModelPreset('novelty')" title="Formulasi kebaruan riset (novelty)">💡 Kebaruan</button>
                        </div>
                        <div class="word-group__row">
                            <button type="button" class="word-tool-btn" wire:click="runTool('/cek-pedoman')" title="Audit kepatuhan pedoman naskah">📜 Cek Pedoman</button>
                            <button type="button" class="word-tool-btn" wire:click="runTool('/cek-gap')" title="Audit celah riset dan gap literatur">🔬 Cek Gap</button>
                            <button type="button" class="word-tool-btn" wire:click="runTool('/keterbacaan')" title="Audit indeks keterbacaan naskah">📈 Keterbacaan</button>
                            <button type="button" class="word-tool-btn" wire:click="auditCitations" title="Audit kepatuhan dan integritas sitasi naskah">🔍 Audit Sitasi</button>
                            <button type="button" class="word-tool-btn" wire:click="runTool('/format-paragraf')" title="Audit &amp; rapikan format paragraf naskah">📐 Format Paragraf</button>
                        </div>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Audit Asisten &amp; Model AI</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large word-btn-assistant-highlight" wire:click="openAssistant" title="Buka Xishie Assistant Copilot">
                        <div class="word-copilot-icon-badge">✨</div>
                        <span style="font-weight:700; color:#4338ca;">Copilot</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="aiElaborateBlock({{ $activeBlock }})">✨ AI Elaborate</button>
                        <button type="button" class="word-tool-btn" wire:click="aiShortenBlock({{ $activeBlock }})">✂️ AI Shorten</button>
                        <button type="button" class="word-tool-btn" wire:click="aiFixTone({{ $activeBlock }}, 'akademik')">🎓 Academic Tone</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Xishie AI</span></div>
            </div>

        <!-- TAB: VIEW -->
        @elseif ($activeTab === 'view')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large {{ $viewMode === 'read' ? 'active' : '' }}" wire:click="$set('viewMode', 'read')">
                        <span style="font-size:18px;">📖</span>
                        <span>Read Mode</span>
                    </button>
                    <button type="button" class="word-btn-large {{ $viewMode === 'print' ? 'active' : '' }}" wire:click="$set('viewMode', 'print')">
                        <span style="font-size:18px;">📄</span>
                        <span>Print Layout</span>
                    </button>
                    <button type="button" class="word-btn-large {{ $viewMode === 'web' ? 'active' : '' }}" wire:click="$set('viewMode', 'web')">
                        <span style="font-size:18px;">🌐</span>
                        <span>Web Layout</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Views</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn {{ $showRuler ? 'active' : '' }}" wire:click="$toggle('showRuler')">📏 Ruler</button>
                        <button type="button" class="word-tool-btn {{ $showGridlines ? 'active' : '' }}" wire:click="$toggle('showGridlines')">▦ Gridlines</button>
                        <button type="button" class="word-tool-btn {{ $showNavigation ? 'active' : '' }}" wire:click="$toggle('showNavigation')">🧭 Navigation Pane</button>
                        <button type="button" class="word-tool-btn {{ !empty($pageSetup['lineNumbers']) ? 'active' : '' }}" wire:click="toggleLineNumbers" title="Toggle Document Line Numbers">#️⃣ Line Numbers</button>
                        <button type="button" class="word-tool-btn {{ $zenMode ? 'active' : '' }}" wire:click="toggleZenMode" title="Toggle Zen Focus Mode">🧘 Zen Mode</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Show</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="setZoomLevel('100')">
                        <span style="font-weight:700; font-size:13px;">100%</span>
                        <span>Normal</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="zoomIn">🔍 Zoom In (+)</button>
                        <button type="button" class="word-tool-btn" wire:click="zoomOut">🔍 Zoom Out (-)</button>
                        <button type="button" class="word-tool-btn" wire:click="setZoomLevel('125')">Page Width (125%)</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Zoom</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="toggleZenMode" title="Focus Mode / Zen">
                        <span style="font-size:18px;">🧘</span>
                        <span>Focus</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="toggleDarkMode" title="Dark / Light Theme">
                        <span style="font-size:18px;">{{ $darkMode ? '☀️' : '🌙' }}</span>
                        <span>{{ $darkMode ? 'Light' : 'Dark' }}</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Immersive</span></div>
            </div>

        <!-- TAB: HELP -->
        @elseif ($activeTab === 'help')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('shortcuts')" title="Keyboard Shortcuts HUD">
                        <span style="font-size:18px;">⌨️</span>
                        <span>Shortcuts</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('palette')" title="Search All Commands (Ctrl+K)">
                        <span style="font-size:18px;">⚡</span>
                        <span>Commands</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Help &amp; Shortcuts</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large word-btn-assistant-highlight" wire:click="openAssistant" title="Buka Xishie Assistant Penulisan Ilmiah">
                        <div class="word-copilot-icon-badge">✨</div>
                        <span style="font-weight:700; color:#4338ca;">Asisten AI</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="runTool('/cek-pedoman')" title="Audit Kepatuhan Pedoman Naskah">
                            <span>🎯 Cek Pedoman</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('stats')" title="Statistik &amp; Skor Keterbacaan">
                            <span>📊 Statistik</span>
                        </button>
                    </div>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="runTool('/cek-gap')" title="Audit Research Gap">
                            <span>🔬 Cek Gap</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="executeModelPreset('novelty')" title="Formulasi Kebaruan Riset">
                            <span>💡 Kebaruan</span>
                        </button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Asisten &amp; Audit</span></div>
            </div>

        <!-- TAB: TABLE TOOLS (ALAT TABEL MICROSOFT WORD & LIBREOFFICE) -->
        @elseif ($activeTab === 'table')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" @mousedown.prevent="insertTable(3, 3)" title="Sisipkan Tabel Baru 3×3">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <rect x="3" y="3" width="18" height="18" rx="2"/>
                            <path d="M3 9h18M3 15h18M9 3v18M15 3v18"/>
                        </svg>
                        <span>New Table</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="openDialog('table')" title="Konfigurasi Ukuran Grid Tabel">
                        <span style="font-size:18px;">⊞</span>
                        <span>Custom Grid</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Insert Table</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTableRow(true)" title="Sisipkan Baris di Bawah">
                            ➕ Row Below
                        </button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTableRow(false)" title="Sisipkan Baris di Atas">
                            ➕ Row Above
                        </button>
                    </div>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTableColumn(true)" title="Sisipkan Kolom di Kanan">
                            ➕ Column Right
                        </button>
                        <button type="button" class="word-tool-btn" @mousedown.prevent="insertTableColumn(false)" title="Sisipkan Kolom di Kiri">
                            ➕ Column Left
                        </button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Rows &amp; Columns</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="tableToggleHeader({{ $activeBlock }})" title="Ganti Header Baris">
                            🏷️ Header Row
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableCalculateColumn({{ $activeBlock }}, 0, 'sum')" title="Hitung Total Kolom Angka">
                            Σ Sum (Jumlah)
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableCalculateColumn({{ $activeBlock }}, 0, 'avg')" title="Hitung Rata-rata Kolom Angka">
                            μ Average (Rata-rata)
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableCalculateColumn({{ $activeBlock }}, 0, 'median')" title="Cari Nilai Median / Nilai Tengah Kolom">
                            ~ Median (Nilai Tengah)
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableCalculateColumn({{ $activeBlock }}, 0, 'min')" title="Cari Nilai Minimum Kolom">
                            ↓ Min (Minimum)
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableCalculateColumn({{ $activeBlock }}, 0, 'max')" title="Cari Nilai Maksimum Kolom">
                            ↑ Max (Maksimum)
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableCalculateColumn({{ $activeBlock }}, 0, 'count')" title="Hitung Jumlah Entri Numerik Kolom">
                            # Total Data (Count)
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableFormatApa({{ $activeBlock }})" title="Format Tabel Kaidah APA 7th (3 Garis Horizontal)">
                            🏛️ Format APA
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableSetStyle({{ $activeBlock }}, 'grid')" title="Format Grid Penuh">
                            ▦ Grid
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableSetStyle({{ $activeBlock }}, 'striped')" title="Format Baris Belang Zebra">
                            ☰ Zebra
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="tableSetStyle({{ $activeBlock }}, 'minimal')" title="Format Minimalis">
                            ― Minimal
                        </button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Formulas &amp; Data</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" style="color:#c50f1f;" @mousedown.prevent="deleteTableRow()" title="Hapus Baris Terpilih">
                            🗑️ Delete Row
                        </button>
                        <button type="button" class="word-tool-btn" style="color:#c50f1f;" @mousedown.prevent="deleteTableColumn()" title="Hapus Kolom Terpilih">
                            🗑️ Delete Column
                        </button>
                        <button type="button" class="word-tool-btn" style="color:#c50f1f;" @mousedown.prevent="deleteTable()" title="Hapus Seluruh Tabel">
                            ✕ Delete Table
                        </button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Delete</span></div>
            </div>
        @endif
    </div>

    <!-- ===================================================================== -->
    <!-- 4. MAIN WORKSPACE: NAVIGATION PANE, CANVAS AREA & RIGHT TASK PANE     -->
    <!-- ===================================================================== -->
    <div class="word-workspace">

        <!-- ================================================================= -->
        <!-- LEFT SIDEBAR: NAVIGATION PANE (EXACT MATCH FOR IMAGE 1)           -->
        <!-- ================================================================= -->
        <aside x-show="!zenMode && {{ $showNavigation ? 'true' : 'false' }}" class="word-nav-pane">
            <div class="word-nav-header">
                <span>Navigation</span>
                <button type="button" class="word-icon-btn" wire:click="$set('showNavigation', false)" style="width:20px; height:20px;" title="Collapse Navigation">
                    <svg viewBox="0 0 16 16" width="12" height="12" fill="none" stroke="currentColor" stroke-width="1.4">
                        <rect x="2" y="2" width="12" height="12" rx="2"/>
                        <line x1="6" y1="2" x2="6" y2="14"/>
                    </svg>
                </button>
            </div>

            <!-- "Search document" Search Box -->
            <div class="word-nav-search">
                <div style="position:relative;">
                    <svg viewBox="0 0 20 20" width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute; left:8px; top:8px; color:#8a8886;">
                        <circle cx="8.5" cy="8.5" r="5.5"/>
                        <path d="M13 13l4 4"/>
                    </svg>
                    <input type="text" wire:model.live.debounce.300ms="navigationSearch" class="word-nav-search-input" placeholder="Search document">
                </div>
            </div>

            <!-- Sub-tabs: Headings, Pages, Pustaka, Versi, Results -->
            <div class="word-nav-subtabs" style="display:flex; gap:3px; padding:0 8px 6px; overflow-x:auto;">
                <button type="button" wire:click="setNavigationTab('headings')" class="word-nav-pill {{ $navigationTab === 'headings' ? 'active' : '' }}" title="Struktur Bab &amp; Heading">
                    Judul
                </button>
                <button type="button" wire:click="setNavigationTab('pages')" class="word-nav-pill {{ $navigationTab === 'pages' ? 'active' : '' }}" title="Daftar Lembar Halaman">
                    Lembar
                </button>
                <button type="button" wire:click="setNavigationTab('pustaka')" class="word-nav-pill {{ $navigationTab === 'pustaka' ? 'active' : '' }}" title="Daftar Referensi Proyek">
                    Pustaka ({{ count($projectReferences) }})
                </button>
                <button type="button" wire:click="setNavigationTab('versi')" class="word-nav-pill {{ $navigationTab === 'versi' ? 'active' : '' }}" title="Riwayat Versi &amp; Snapshot">
                    Versi ({{ count($snapshots) }})
                </button>
                <button type="button" wire:click="setNavigationTab('notes')" class="word-nav-pill {{ $navigationTab === 'notes' ? 'active' : '' }}" title="Catatan Riset &amp; Scratchpad Penulis">
                    Catatan
                </button>
                <button type="button" wire:click="setNavigationTab('results')" class="word-nav-pill {{ $navigationTab === 'results' ? 'active' : '' }}" title="Pencarian Teks">
                    Cari
                </button>
            </div>

            <!-- TAB 1: HEADINGS TREE (NAVIGASI DOKUMEN & ACUAN DAFTAR PUSTAKA) -->
            @if ($navigationTab === 'headings')
                <div class="word-nav-tree">
                    @forelse ($outline as $item)
                        @php
                            $matchesSearch = empty($navigationSearch) || str_contains(strtolower($item['text']), strtolower($navigationSearch));
                            $isBibHeading = stripos($item['text'], 'daftar pustaka') !== false || stripos($item['text'], 'referensi') !== false || stripos($item['text'], 'bibliography') !== false;
                        @endphp
                        @if ($matchesSearch)
                            <div style="display: flex; align-items: center; justify-content: space-between; gap: 4px; padding-right: 6px;">
                                <button type="button" wire:click="selectBlock({{ $item['index'] }})"
                                    @click="
                                        const target = document.getElementById('heading-{{ $item['index'] }}') || document.getElementById('word-block-{{ $item['index'] }}');
                                        if (target) {
                                            target.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                            target.classList.add('word-nav-target');
                                            setTimeout(() => target.classList.remove('word-nav-target'), 1500);
                                        }
                                    "
                                    class="word-nav-item {{ $activeBlock === $item['index'] ? 'active' : '' }}"
                                    style="flex: 1; text-align: left; padding-left: {{ $item['level'] === 1 ? '12px' : '26px' }}; font-weight: {{ $item['level'] === 1 ? '600' : '400' }}; font-size: 12px; display: flex; align-items: center; justify-content: space-between;">
                                    <span>
                                        {{ $item['text'] ?: '(Untitled Heading)' }}
                                        @if (!empty($item['word_count']))
                                            <span style="font-size: 10px; color: #64748b; font-weight: normal; margin-left: 4px;">({{ number_format($item['word_count']) }} kata)</span>
                                        @endif
                                    </span>
                                    @if ($isBibHeading)
                                        <span style="font-size: 10px; color: #0f6cbd; background: #ebf3fc; padding: 1px 6px; border-radius: 9999px; font-weight: 600;">Acuan</span>
                                    @endif
                                </button>
                                <div style="display: inline-flex; align-items: center; gap: 1px; flex-shrink: 0;">
                                    <button type="button" wire:click="moveSection({{ $item['index'] }}, 'up')" title="Pindahkan Bagian ke Atas" style="background:transparent; border:none; padding:1px 3px; font-size:9px; cursor:pointer; color:#64748b;" onmouseover="this.style.color='#0f6cbd'" onmouseout="this.style.color='#64748b'">&#9650;</button>
                                    <button type="button" wire:click="moveSection({{ $item['index'] }}, 'down')" title="Pindahkan Bagian ke Bawah" style="background:transparent; border:none; padding:1px 3px; font-size:9px; cursor:pointer; color:#64748b;" onmouseover="this.style.color='#0f6cbd'" onmouseout="this.style.color='#64748b'">&#9660;</button>
                                    <button type="button" wire:click="promoteHeading({{ $item['index'] }})" title="Tingkatkan Level Judul (Promote)" style="background:transparent; border:none; padding:1px 3px; font-size:9px; cursor:pointer; color:#64748b;" onmouseover="this.style.color='#0f6cbd'" onmouseout="this.style.color='#64748b'">&#9664;</button>
                                    <button type="button" wire:click="demoteHeading({{ $item['index'] }})" title="Turunkan Level Judul (Demote)" style="background:transparent; border:none; padding:1px 3px; font-size:9px; cursor:pointer; color:#64748b;" onmouseover="this.style.color='#0f6cbd'" onmouseout="this.style.color='#64748b'">&#9654;</button>
                                </div>
                            </div>
                        @endif
                    @empty
                        <div style="padding:16px 12px; color:var(--ms-text-muted); font-size:12px; text-align:center;">
                            No headings yet.
                        </div>
                    @endforelse
                </div>

            <!-- TAB 2: PAGES (THUMBNAIL PDF VIEWER & MICROSOFT WORD MULTI-PAGE) -->
            @elseif ($navigationTab === 'pages')
                <div style="padding:12px; display:flex; flex-direction:column; gap:12px;">
                    @foreach ($paginatedPages as $p)
                        <div @click="document.getElementById('word-page-{{ $p['pageNumber'] }}')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"
                            class="word-page-thumb"
                            style="border: 1px solid #d1d1d1; border-radius: 4px; padding: 10px 12px; background: #ffffff; box-shadow: 0 1px 3px rgba(0,0,0,0.06); cursor: pointer; transition: all 0.15s ease;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:6px;">
                                <span style="font-size:11px; font-weight:700; color:#0f6cbd;">Halaman {{ $p['pageNumber'] }}</span>
                                <span style="font-size:10px; color:#8a8886;">{{ count($p['blocks']) }} elemen</span>
                            </div>
                            <div style="font-size:10px; line-height:1.35; color:#616161; overflow:hidden; max-height:48px; border-left:2px solid #0f6cbd; padding-left:6px;">
                                @php
                                    $firstBlockText = '';
                                    foreach ($p['blocks'] as $bItem) {
                                        $txt = trim((string) ($bItem['block']['text'] ?? ''));
                                        if ($txt !== '') {
                                            $firstBlockText = $txt;
                                            break;
                                        }
                                    }
                                @endphp
                                {{ Str::limit($firstBlockText ?: '(Halaman ' . $p['pageNumber'] . ')', 70) }}
                            </div>
                        </div>
                    @endforeach
                </div>

            <!-- TAB: PUSTAKA (PROJECT REFERENCES) -->
            @elseif ($navigationTab === 'pustaka')
                <div style="padding:8px 10px; display:flex; flex-direction:column; gap:6px; overflow-y:auto; max-height:calc(100vh - 280px);">
                    <div style="margin-bottom:2px;">
                        <input type="text" wire:model.live.debounce.250ms="referenceSearchQuery" placeholder="Cari pustaka (judul, penulis, kunci)..." class="word-select" style="width:100%; height:auto; font-size:10.5px; padding:4px 6px;" />
                    </div>
                    @forelse ($projectReferences as $pRef)
                        <div style="border:1px solid #e2e8f0; border-radius:4px; padding:6px 8px; background:#ffffff; font-size:11px;">
                            <div style="font-weight:700; color:#1e293b; margin-bottom:2px;">{{ Str::limit($pRef->title, 45) }}</div>
                            <div style="font-size:10px; color:#64748b; margin-bottom:4px;">{{ $pRef->authors ?: 'Penulis' }} ({{ $pRef->year ?: 'n.d.' }})</div>
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <span style="font-size:9.5px; background:#f1f5f9; padding:1px 5px; border-radius:3px; color:#475569;">{{ strtoupper($pRef->venue_tier ?? 'Q1') }}</span>
                                <button type="button" wire:click="insertReferenceCitation({{ $pRef->id }})" class="word-tool-btn" style="color:#4338ca; font-weight:700;" title="Sisipkan Sitasi In-text ke Naskah">+ Sisip Sitasi</button>
                            </div>
                        </div>
                    @empty
                        <div style="padding:16px 12px; color:var(--ms-text-muted); font-size:11.5px; text-align:center;">
                            Belum ada referensi proyek terdaftar.
                        </div>
                    @endforelse
                </div>

            <!-- TAB: VERSI (SNAPSHOTS) -->
            @elseif ($navigationTab === 'versi')
                <div style="padding:8px 10px; display:flex; flex-direction:column; gap:6px; overflow-y:auto; max-height:calc(100vh - 280px);">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                        <span style="font-size:11px; font-weight:700; color:#334155;">Riwayat Snapshot</span>
                        <button type="button" wire:click="createSnapshot('Manual Snapshot')" class="word-tool-btn" style="color:#0f766e; font-weight:700;">+ Simpan Versi</button>
                    </div>
                    @forelse ($snapshots as $sIdx => $snap)
                        <div style="border:1px solid #e2e8f0; border-radius:4px; padding:6px 8px; background:#ffffff; font-size:11px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                <strong style="color:#1e293b;">{{ $snap['label'] ?? ('Versi #'.($sIdx + 1)) }}</strong>
                                <span style="font-size:9.5px; color:#64748b;">{{ $snap['created_at'] ?? '' }}</span>
                            </div>
                            <div style="font-size:10px; color:#64748b; margin-bottom:4px;">{{ $snap['words'] ?? 0 }} kata · {{ $snap['blocks_count'] ?? count($snap['blocks'] ?? []) }} blok</div>
                            <div style="display:flex; justify-content:flex-end; gap:4px;">
                                <button type="button" wire:click="restoreSnapshot({{ $sIdx }})" class="word-tool-btn" style="color:#4338ca; font-weight:700;">Pulihkan</button>
                                <button type="button" wire:click="deleteSnapshot({{ $sIdx }})" class="word-tool-btn" style="color:#dc2626;">Hapus</button>
                            </div>
                        </div>
                    @empty
                        <div style="padding:16px 12px; color:var(--ms-text-muted); font-size:11.5px; text-align:center;">
                            Belum ada snapshot tersimpan.
                        </div>
                    @endforelse
                </div>

            <!-- TAB: CATATAN (SCRATCHPAD) -->
            @elseif ($navigationTab === 'notes')
                <div style="padding:10px; display:flex; flex-direction:column; gap:8px;">
                    <div style="display:flex; justify-content:space-between; align-items:center;">
                        <span style="font-size:11px; font-weight:700; color:#334155;">📝 Catatan Riset / Scratchpad</span>
                        <span style="font-size:10px; color:#64748b;">{{ str_word_count($scratchpadNotes) }} kata</span>
                    </div>
                    <textarea
                        wire:model.live.debounce.400ms="scratchpadNotes"
                        placeholder="Tulis ide cepat, kutipan sementara, atau catatan reviewer di sini..."
                        style="width:100%; min-height:180px; font-size:11px; padding:6px; border:1px solid #cbd5e1; border-radius:4px; font-family:monospace; resize:vertical; background:#f8fafc; color:#1e293b; line-height:1.4;"
                    ></textarea>
                    <div style="display:flex; gap:6px;">
                        <button type="button" class="word-tool-btn" style="flex:1; background:#2563eb; color:#ffffff; font-weight:700; padding:4px 8px;" wire:click="insertNotesToManuscript" title="Sisipkan seluruh isi catatan ke naskah">
                            ⬇️ Ke Naskah
                        </button>
                        <button type="button" class="word-tool-btn" style="color:#dc2626;" wire:click="clearScratchpadNotes" title="Bersihkan catatan">
                            ✕ Bersihkan
                        </button>
                    </div>
                </div>

            <!-- TAB 3: RESULTS -->
            @else
                @if (!empty($navigationSearch))
                    <div class="word-nav-tree">
                        @php
                            $matchCount = 0;
                        @endphp
                        @foreach ($blocks as $bIdx => $b)
                            @php
                                $bText = $b['text'] ?? '';
                            @endphp
                            @if ($bText && stripos($bText, $navigationSearch) !== false)
                                @php $matchCount++; @endphp
                                <button type="button" wire:click="selectBlock({{ $bIdx }})"
                                    @click="document.getElementById('word-block-{{ $bIdx }}')?.scrollIntoView({ behavior: 'smooth', block: 'center' })"
                                    class="word-nav-item" style="font-size:11px; padding:6px 12px; line-height:1.3; text-align:left;">
                                    <div style="font-weight:600; color:var(--ms-blue); font-size:10px;">Block #{{ $bIdx + 1 }} ({{ ucfirst($b['type'] ?? 'paragraph') }})</div>
                                    <div style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ Str::limit($bText, 60) }}</div>
                                </button>
                            @endif
                        @endforeach
                        @if ($matchCount === 0)
                            <div style="padding:12px; font-size:12px; color:var(--ms-text-muted);">No matches found for "{{ $navigationSearch }}".</div>
                        @endif
                    </div>
                @else
                    <div style="padding:12px; font-size:12px; color:var(--ms-text-muted);">
                        Type in the search box above to search document text.
                    </div>
                @endif
            @endif

            <!-- Navigation Footer Quick Stats -->
            <div style="padding:8px 12px; border-top:1px solid var(--ms-border-subtle); background:var(--ms-bg-subtle, #f9f9fb); font-size:11px; color:var(--ms-text-secondary); display:flex; flex-direction:column; gap:3px;">
                <div style="display:flex; justify-content:space-between;">
                    <span>Words:</span>
                    <strong style="color:var(--ms-text-primary);">{{ number_format($statistics['words'] ?? 0) }}</strong>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Characters:</span>
                    <span>{{ number_format($statistics['characters'] ?? 0) }}</span>
                </div>
                <div style="display:flex; justify-content:space-between;">
                    <span>Reading Ease:</span>
                    <span>{{ $statistics['readingEase'] ?? 0 }} / 100</span>
                </div>
            </div>
        </aside>

        <!-- ================================================================= -->
        <!-- CENTER CANVAS: SEPARATE PAGES (PDF VIEWER & MICROSOFT WORD STYLE) -->
        <!-- ================================================================= -->
        <main class="word-canvas-area" style="zoom: {{ (int) $zoom / 100 }};"
            @contextmenu="openContextMenu($event)"
            @scroll.passive="
                const sheets = $el.querySelectorAll('.word-paper-sheet');
                const scrollPos = $el.scrollTop + 180;
                sheets.forEach((sheet) => {
                    if (sheet.offsetTop <= scrollPos) {
                        const p = parseInt(sheet.getAttribute('data-page-number') || '1', 10);
                        if (!isNaN(p)) { activePage = p; }
                    }
                });
                showMiniToolbar = false;
                showBlockCopilot = false;
                showContextMenu = false;
            ">

            <!-- Horizontal Ruler Aligned Directly Above Canvas Sheet -->
            @if ($showRuler && ! $zenMode)
                <div class="word-canvas-ruler-wrapper">
                    <div class="word-ruler">
                        <!-- Left Indent Marker (First Line + Hanging + Left Box) -->
                        <div class="word-indent-marker-left" title="First Line &amp; Left Indent">
                            <svg viewBox="0 0 10 10" width="7" height="7" fill="#616161"><polygon points="0,0 10,0 5,8"/></svg>
                            <svg viewBox="0 0 10 10" width="7" height="7" fill="#616161"><polygon points="5,0 10,8 0,8"/></svg>
                            <div style="width:7px; height:3px; background:#616161;"></div>
                        </div>

                        <!-- Ruler Inches Markings (1 to 7 inches) -->
                        <div class="word-ruler__numbers">
                            @for ($inch = 1; $inch <= 7; $inch++)
                                <div class="word-ruler__inch">
                                    <span class="word-ruler__num">{{ $inch }}</span>
                                    <div class="word-ruler__tick-quarter"></div>
                                    <div class="word-ruler__tick-half"></div>
                                    <div class="word-ruler__tick-three-quarter"></div>
                                    <div class="word-ruler__tick-major"></div>
                                </div>
                            @endfor
                        </div>

                        <!-- Right Margin Indent Marker -->
                        <div class="word-indent-marker-right" title="Right Margin Indent">
                            <svg viewBox="0 0 10 10" width="7" height="7" fill="#616161"><polygon points="5,0 10,8 0,8"/></svg>
                        </div>
                    </div>
                </div>
            @endif

            <div id="word-manuscript-editor"
                class="word-manuscript-container"
                wire:key="manuscript-canvas-{{ $pageCount }}"
                style="width: 100%; display: flex; flex-direction: column; align-items: center;">

                {{-- Review Hasil Generasi Model Bar --}}
                @if ($isReviewingModelOutput)
                    <div class="word-model-review-banner" style="width: 100%; max-width: 820px; margin: 0 auto 16px auto; background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%); border: 2px solid #6366f1; border-radius: 8px; box-shadow: 0 6px 20px rgba(99,102,241,0.22); padding: 12px 18px; display: flex; align-items: center; justify-content: space-between; gap: 14px; position: sticky; top: 12px; z-index: 50;">
                        <div style="display: flex; align-items: center; gap: 12px; flex: 1; min-width: 0;">
                            <div style="width: 36px; height: 36px; border-radius: 9px; background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color: #ffffff; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; box-shadow: 0 2px 8px rgba(79,70,229,0.3);">
                                ✨
                            </div>
                            <div style="min-width: 0;">
                                <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                    <strong style="font-size: 13.5px; color: #1e1b4b;">Tinjau Hasil Generasi: {{ $currentAssistantModel }}</strong>
                                    <span style="font-size: 10.5px; background: #e0e7ff; color: #4338ca; padding: 2px 7px; border-radius: 4px; font-weight: 700;">Versi #{{ $modelGenerationCount }}</span>
                                    <span style="font-size: 11px; color: #6366f1; font-weight: 600;">({{ count($pendingReviewBlockIndices) }} blok ditambahkan ke naskah)</span>
                                </div>
                                <div style="font-size: 11.5px; color: #475569; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 520px; margin-top: 3px;">
                                    Instruksi: &ldquo;<strong>{{ $lastExecutedModelPrompt }}</strong>&rdquo;
                                </div>
                            </div>
                        </div>
                        <div style="display: flex; align-items: center; gap: 8px; flex-shrink: 0;">
                            <button type="button" wire:click="acceptModelOutput" class="word-btn-pill" style="background: #16a34a; color: #ffffff; border: 1px solid #15803d; font-weight: 700; height: 32px; padding: 0 14px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(22,163,74,0.3);" title="Terima hasil ini dan terapkan secara permanen ke naskah">
                                <span>✓</span> Terima &amp; Terapkan
                            </button>
                            <button type="button" wire:click="regenerateCurrentModel" wire:loading.attr="disabled" wire:target="regenerateCurrentModel" class="word-btn-pill" style="background: #ffffff; color: #4338ca; border: 1.5px solid #a5b4fc; font-weight: 700; height: 32px; padding: 0 14px; font-size: 12px; display: inline-flex; align-items: center; gap: 5px; box-shadow: 0 1px 3px rgba(0,0,0,0.06);" title="Belum sesuai? Generate ulang draf alternatif dari model saat ini">
                                <span>🔄</span> Generate Ulang
                            </button>
                            <button type="button" wire:click="discardModelOutput" class="word-btn-pill" style="background: #ffffff; color: #dc2626; border: 1px solid #fca5a5; font-weight: 600; height: 32px; padding: 0 12px; font-size: 12px;" title="Tolak hasil generasi ini dan kembalikan naskah ke kondisi semula">
                                ✕ Tolak
                            </button>
                        </div>
                    </div>
                @endif

                @foreach ($paginatedPages as $pageData)
                    @php
                        $pageNumber = $pageData['pageNumber'];
                        $isFirstPage = $pageData['isFirstPage'];
                        $pageBlocks = $pageData['blocks'];
                        $inBulletList = false;
                        $inNumberedList = false;
                        $inBibliographySection = false;

                        $fontFamilyCss = match ($fontFamily) {
                            'calibri' => 'var(--ms-font-calibri)',
                            'times' => 'var(--ms-font-times)',
                            'arial' => 'var(--ms-font-arial)',
                            'serif' => 'var(--ms-font-serif)',
                            'mono' => 'var(--ms-font-mono)',
                            default => 'var(--ms-font-aptos)',
                        };
                        $padTop = max(12, min(50, (float)($pageSetup['marginTop'] ?? 25.4)));
                        $padRight = max(12, min(50, (float)($pageSetup['marginRight'] ?? 25.4)));
                        $padBottom = max(12, min(50, (float)($pageSetup['marginBottom'] ?? 25.4)));
                        $padLeft = max(12, min(50, (float)($pageSetup['marginLeft'] ?? 25.4)));
                    @endphp

                    <div class="word-paper-sheet {{ ($pageSetup['orientation'] ?? 'portrait') === 'landscape' ? 'landscape word-paper-sheet--landscape' : '' }}"
                        wire:key="page-sheet-{{ $pageNumber }}-v{{ $editorRevision }}"
                        id="word-page-{{ $pageNumber }}"
                        data-page-number="{{ $pageNumber }}"
                        style="background-color: {{ $pageSetup['pageColor'] ?? '#ffffff' }}; font-family: {{ $fontFamilyCss }}; font-size: {{ $fontSize }}pt; padding: {{ $padTop }}mm {{ $padRight }}mm {{ $padBottom }}mm {{ $padLeft }}mm;">

                        <!-- Watermark if configured -->
                        @if (!empty($pageSetup['watermark']))
                            <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; user-select:none; opacity:0.04; font-size:5rem; font-weight:800; text-transform:uppercase; transform:rotate(-35deg);">
                                {{ $pageSetup['watermark'] }}
                            </div>
                        @endif

                        <!-- Header Halaman (Sesuai Konsep Microsoft Word & Gambar 1) -->
                        <!-- Header Halaman (Sesuai Konsep Microsoft Word & Bebas Tabrakan) -->
                        <div class="word-sheet-header" contenteditable="false" style="display:flex; justify-content:space-between; align-items:center; min-height:28px; border-bottom:1px solid rgba(0,0,0,0.08); padding-bottom:8px; margin-bottom:22px; position:relative; z-index:15;">
                            <!-- Sisi Kiri: Judul Dokumen -->
                            <div style="display:flex; align-items:center; gap:8px; min-width:0; flex:1;">
                                <span class="word-sheet-title" style="font-weight:600; color:#475569; font-size:11.5px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:280px;" title="{{ $docTitle }}">
                                    {{ $docTitle }}
                                </span>
                            </div>

                            <!-- Sisi Kanan: Aksi Halaman & Nomor Halaman -->
                            <div style="display:flex; align-items:center; gap:6px; flex-shrink:0;" x-data="{ pageMenuOpen: false }" @click.outside="pageMenuOpen = false">
                                <!-- Tombol Eksekusi Model Halaman -->
                                <button type="button"
                                    wire:click="executeModelToPage({{ $pageNumber }})"
                                    class="word-tool-btn"
                                    style="font-size:10.5px; padding:2px 8px; height:22px; background:linear-gradient(135deg, rgba(99,102,241,0.12) 0%, rgba(168,85,247,0.12) 100%); color:#4f46e5; border:1px solid #c7d2fe; border-radius:4px; font-weight:700; display:inline-flex; align-items:center; gap:4px;"
                                    title="Eksekusi model AI saat ini ke Halaman {{ $pageNumber }}">
                                    <span>⚡</span> Model Hal. {{ $pageNumber }}
                                </button>

                                <!-- Dropdown Opsi & Layout Halaman -->
                                <div style="position:relative;">
                                    <button type="button"
                                        @click="pageMenuOpen = !pageMenuOpen"
                                        class="word-tool-btn"
                                        style="font-size:10.5px; padding:2px 8px; height:22px; background:#f8fafc; color:#334155; border:1px solid #cbd5e1; border-radius:4px; font-weight:600; display:inline-flex; align-items:center; gap:4px;"
                                        title="Opsi tata letak dan paragraf Halaman {{ $pageNumber }}">
                                        <span>⚙️</span> Opsi Hal. {{ $pageNumber }} <span style="font-size:8px;">▼</span>
                                    </button>

                                    <!-- Floating Dropdown Menu -->
                                    <div x-show="pageMenuOpen"
                                         x-transition:enter="transition ease-out duration-100"
                                         x-transition:enter-start="opacity-0 scale-95"
                                         x-transition:enter-end="opacity-100 scale-100"
                                         x-transition:leave="transition ease-in duration-75"
                                         x-transition:leave-start="opacity-100 scale-100"
                                         x-transition:leave-end="opacity-0 scale-95"
                                         style="position:absolute; right:0; top:26px; z-index:100; min-width:220px; background:#ffffff; border:1px solid #cbd5e1; border-radius:6px; box-shadow:0 10px 25px -5px rgba(0,0,0,0.15), 0 8px 10px -6px rgba(0,0,0,0.1); padding:5px; display:flex; flex-direction:column; gap:2px;"
                                         contenteditable="false">
                                        <div style="font-size:9.5px; font-weight:700; color:#64748b; padding:4px 8px; border-bottom:1px solid #f1f5f9; text-transform:uppercase; letter-spacing:0.5px;">
                                            Alat Halaman {{ $pageNumber }}
                                        </div>
                                        <button type="button"
                                            wire:click="openParagraphCustomizer({{ $pageNumber }}, 0); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>⚙️</span> Kustomisasi Paragraf
                                        </button>
                                        <button type="button"
                                            wire:click="insertCustomParagraphOnPage({{ $pageNumber }}, 'Paragraf baru pada halaman {{ $pageNumber }}.'); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>➕</span> Tambah Paragraf Baru
                                        </button>
                                        <div style="height:1px; background:#f1f5f9; margin:2px 0;"></div>
                                        <button type="button"
                                            wire:click="applyParagraphPresetToPage({{ $pageNumber }}, 'academic_standard'); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>⚡</span> Format APA 1.5 (Scopus)
                                        </button>
                                        <button type="button"
                                            wire:click="applyParagraphPresetToPage({{ $pageNumber }}, 'journal_ieee'); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>⚡</span> Format IEEE / ACM (1.0)
                                        </button>
                                        <button type="button"
                                            wire:click="cleanPageParagraphSpaces({{ $pageNumber }}); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>🧹</span> Rapikan Spasi Berlebih
                                        </button>
                                        <div style="height:1px; background:#f1f5f9; margin:2px 0;"></div>
                                        <button type="button"
                                            wire:click="setPageParagraphColumns({{ $pageNumber }}, {{ ($pageOverrides[$pageNumber]['columns'] ?? 1) === 1 ? 2 : 1 }}); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>📰</span> {{ ($pageOverrides[$pageNumber]['columns'] ?? 1) === 2 ? 'Ubah ke 1 Kolom' : 'Ubah ke 2 Kolom' }}
                                        </button>
                                        <button type="button"
                                            wire:click="togglePageParagraphLineNumbers({{ $pageNumber }}); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>🔢</span> {{ !empty($pageOverrides[$pageNumber]['lineNumbers']) ? 'Sembunyikan No. Baris' : 'Tampilkan No. Baris' }}
                                        </button>
                                        <button type="button"
                                            wire:click="setPageParagraphAlignment({{ $pageNumber }}, 'justify'); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>≡</span> Rata Penuh (Justify)
                                        </button>
                                        <button type="button"
                                            wire:click="setPageParagraphAlignment({{ $pageNumber }}, 'left'); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>=</span> Rata Kiri (Left)
                                        </button>
                                        <button type="button"
                                            wire:click="setPageParagraphLineSpacing({{ $pageNumber }}, 1.5); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>↕</span> Atur Spasi 1.5
                                        </button>
                                        <button type="button"
                                            wire:click="setPageParagraphLineSpacing({{ $pageNumber }}, 1.0); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>↕</span> Atur Spasi 1.0
                                        </button>
                                        <button type="button"
                                            wire:click="batchIndentPageParagraphs({{ $pageNumber }}, 'first-line'); pageMenuOpen = false;"
                                            class="word-mini-dropdown-item"
                                            style="padding:5px 8px; font-size:11px; text-align:left; border:none; background:none; cursor:pointer; border-radius:4px; display:flex; align-items:center; gap:6px;">
                                            <span>📐</span> Indent Baris Pertama (1.27cm)
                                        </button>
                                    </div>
                                </div>

                                <!-- Label / Nomor Halaman -->
                                <div style="display:flex; align-items:center; padding-left:4px;">
                                    @if ($isFirstPage)
                                        <span class="word-sheet-edition" style="font-size:10px; color:#64748b; font-weight:600; white-space:nowrap;">Halaman 1</span>
                                    @else
                                        <span class="word-sheet-page-top" title="Halaman {{ $pageNumber }}" style="font-size:10.5px; font-weight:700; color:#334155; white-space:nowrap;">Hal. {{ $pageNumber }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Konten Lembar Halaman (Continuous Flow Inside Page) -->
                        @php
                            $pageCols = (int) ($pageOverrides[$pageNumber]['columns'] ?? $pageSetup['columns'] ?? 1);
                            $colStyle = $pageCols > 1 ? "column-count: {$pageCols}; column-gap: 1.5rem;" : '';
                            $pageLineSpacing = (float) ($pageOverrides[$pageNumber]['lineSpacing'] ?? $pageSetup['lineSpacing'] ?? 1.5);
                        @endphp
                        <div class="word-sheet-content"
                            wire:key="page-content-{{ $pageNumber }}-v{{ $editorRevision }}"
                            wire:ignore
                            id="word-page-content-{{ $pageNumber }}"
                            style="line-height: {{ $pageLineSpacing }}; {{ $colStyle }}"
                            contenteditable="{{ $editingMode === 'viewing' ? 'false' : 'true' }}"
                            spellcheck="false"
                            @keydown="handleContentKeydown($event)"
                            @keydown.ctrl.enter.prevent="insertPageBreak()"
                            @keydown.meta.enter.prevent="insertPageBreak()"
                            @paste="handlePaste($event)"
                            @dragover.prevent
                            @drop="handleDrop($event)"
                            @click="updateActiveBlock($event)"
                            @focusin="updateActiveBlock($event)"
                            @keyup="updateActiveBlock($event)"
                            @input="handleContentInput($event)"
                            @blur="syncAllPages()">

                            @foreach ($pageBlocks as $item)
                                @php
                                    $index = $item['index'];
                                    $block = $item['block'];
                                    $type = $block['type'] ?? 'paragraph';
                                    $level = $block['level'] ?? 1;
                                    $headingId = 'heading-' . $index;
                                    $text = $block['text'] ?? '';
                                @endphp

                                @if ($type === 'bullet')
                                    @if (!$inBulletList)
                                        <ul class="word-bullet-list">
                                        @php $inBulletList = true; @endphp
                                    @endif
                                    <li id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text !!}</li>
                                @else
                                    @if ($inBulletList)
                                        </ul>
                                        @php $inBulletList = false; @endphp
                                    @endif

                                    @if ($type === 'numbered')
                                        @if (!$inNumberedList)
                                            <ol class="word-numbered-list">
                                            @php $inNumberedList = true; @endphp
                                        @endif
                                        <li id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text !!}</li>
                                    @else
                                        @if ($inNumberedList)
                                            </ol>
                                            @php $inNumberedList = false; @endphp
                                        @endif

                                        @if ($type === 'title')
                                            <h1 class="word-title-text" id="doc-title" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text ?: 'The Open Studio Handbook' !!}</h1>
                                        @elseif ($type === 'subtitle')
                                            <p class="word-subtitle-text" id="doc-subtitle" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text !!}</p>
                                        @elseif ($type === 'heading')
                                            @php
                                                if (stripos($text, 'daftar pustaka') !== false || stripos($text, 'referensi') !== false || stripos($text, 'bibliography') !== false) {
                                                    $inBibliographySection = true;
                                                } else {
                                                    $inBibliographySection = false;
                                                }
                                            @endphp
                                            @if ($level === 1)
                                                <h2 class="word-heading-1" id="{{ $headingId }}" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text !!}</h2>
                                            @elseif ($level === 2)
                                                <h3 class="word-heading-2" id="{{ $headingId }}" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text !!}</h3>
                                            @else
                                                <h4 class="word-heading-3" id="{{ $headingId }}" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">{!! $text !!}</h4>
                                            @endif
                                        @elseif ($type === 'quote')
                                            <blockquote class="word-quote-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="text-align: {{ $block['align'] ?? 'left' }};">
                                                <p class="word-quote-text">{!! $text !!}</p>
                                            </blockquote>
                                        @elseif ($type === 'toc')
                                            <div class="word-toc-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}" contenteditable="false" style="margin: 18px 0 26px 0; padding: 14px 18px; background: #faf9f8; border: 1px solid #edebe9; border-radius: 4px; position:relative;">
                                                <div style="font-weight: 700; font-size: 13pt; color: #0f6cbd; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                                                    <span>📑 Daftar Isi Otomatis</span>
                                                    <div style="display:flex; align-items:center; gap:6px;">
                                                        <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px;" wire:click="insertTableOfContents" title="Perbarui Daftar Isi">🔄 Perbarui</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px; color:#d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Daftar Isi">🗑️</button>
                                                    </div>
                                                </div>
                                                <div style="display: flex; flex-direction: column; gap: 6px;">
                                                    @php
                                                        $tocRows = $block['table']['rows'] ?? [];
                                                    @endphp
                                                    @foreach ($tocRows as $tIdx => $tRow)
                                                        @if ($tIdx > 0)
                                                            <div style="display: flex; align-items: baseline; font-size: 11pt; color: #242424;">
                                                                <span style="white-space: nowrap;">{{ $tRow[0]['text'] ?? '' }}</span>
                                                                <span style="flex: 1; border-bottom: 1px dotted #a19f9d; margin: 0 8px; transform: translateY(-3px);"></span>
                                                                <span style="font-size: 10pt; color: #616161;">{{ $tRow[1]['text'] === '…' ? (1 + floor($tIdx / 4)) : ($tRow[1]['text'] ?? '1') }}</span>
                                                            </div>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            </div>
                                        @elseif ($type === 'callout')
                                            @php
                                                $cType = $block['calloutType'] ?? 'info';
                                                $cIcon = match ($cType) {
                                                    'warning' => '⚠️',
                                                    'tip' => '💡',
                                                    'theorem' => '📐',
                                                    'note' => 'ℹ️',
                                                    'sketch' => '🎨',
                                                    'success' => '✅',
                                                    'important' => '⚡',
                                                    'caution' => '🛑',
                                                    default => '📌',
                                                };
                                                $cBorder = match ($cType) {
                                                    'warning' => '#d97706',
                                                    'tip' => '#16a34a',
                                                    'theorem' => '#9333ea',
                                                    'note' => '#0284c7',
                                                    'sketch' => '#ec4899',
                                                    'success' => '#16a34a',
                                                    'important' => '#7c3aed',
                                                    'caution' => '#dc2626',
                                                    default => '#0f6cbd',
                                                };
                                                $cBg = match ($cType) {
                                                    'warning' => 'rgba(217, 119, 6, 0.05)',
                                                    'tip' => 'rgba(22, 163, 74, 0.05)',
                                                    'theorem' => 'rgba(147, 51, 234, 0.05)',
                                                    'note' => 'rgba(2, 132, 199, 0.05)',
                                                    'sketch' => 'rgba(236, 72, 153, 0.05)',
                                                    'success' => 'rgba(22, 163, 74, 0.05)',
                                                    'important' => 'rgba(124, 58, 237, 0.05)',
                                                    'caution' => 'rgba(220, 38, 38, 0.05)',
                                                    default => 'rgba(15, 108, 189, 0.05)',
                                                };
                                            @endphp
                                            <div class="word-callout-box"
                                                id="word-block-{{ $index }}"
                                                data-block-index="{{ $index }}"
                                                data-callout-type="{{ $cType }}"
                                                style="border-left: 4px solid {{ $cBorder }}; background: {{ $cBg }}; padding: 10px 14px; border-radius: 0 4px 4px 0; margin: 14px 0; font-size: 11pt; line-height: 1.5; position: relative;">
                                                <div class="word-callout-actions" contenteditable="false" style="position: absolute; right: 6px; top: 6px; display: flex; gap: 4px; z-index: 10;">
                                                    <button type="button" class="word-tool-btn" style="padding: 1px 6px; font-size: 10px; height: 20px; color: #d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Catatan Sorotan">🗑️</button>
                                                </div>
                                                <div style="display:flex; align-items:flex-start; gap:8px;">
                                                    <span style="font-size: 14pt; line-height: 1; user-select: none;">{{ $cIcon }}</span>
                                                    <div style="flex:1;">{!! nl2br(e($text)) !!}</div>
                                                </div>
                                            </div>
                                        @elseif ($type === 'table')
                                            <div style="overflow-x:auto; margin:14px 0; border:1px solid #d1d1d1; border-radius:2px; position:relative;" class="word-table-wrapper" contenteditable="true" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                                @php
                                                    $rows = $block['table']['rows'] ?? [];
                                                    $hasHeader = $block['table']['header'] ?? true;
                                                @endphp
                                                <div class="word-table-actions" contenteditable="false" style="position:absolute; right:6px; top:6px; z-index:10; display:flex; gap:4px; background:rgba(255,255,255,0.92); padding:2px 4px; border-radius:3px; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                                                    <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px; color:#4f46e5; font-weight:700;" wire:click="executeModelOnTable({{ $index }})" title="Eksekusi model AI saat ini untuk melengkapi data tabel">⚡ Model Tabel</button>
                                                    <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px;" wire:click="tableInsertRow({{ $index }}, {{ count($rows) }})" title="Tambah Baris Bawah">➕ Baris</button>
                                                    <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px;" wire:click="tableInsertColumn({{ $index }}, {{ count($rows[0] ?? []) }})" title="Tambah Kolom Kanan">➕ Kolom</button>
                                                    <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px;" wire:click="tableToggleHeader({{ $index }})" title="Toggle Baris Header">🏷️ Header</button>
                                                    <button type="button" class="word-tool-btn" style="padding:1px 6px; font-size:10px; height:20px; color:#d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Tabel">🗑️</button>
                                                </div>
                                                <table style="width:100%; border-collapse:collapse; font-size:11pt;">
                                                    @foreach ($rows as $rIdx => $row)
                                                        <tr style="{{ $hasHeader && $rIdx === 0 ? 'background:#f3f2f1; font-weight:600; border-bottom:1px solid #a19f9d;' : 'border-bottom:1px solid #e1dfdd;' }}">
                                                            @foreach ($row as $cIdx => $cell)
                                                                <td style="padding:6px 10px; border-right:1px solid #e1dfdd;">
                                                                    {{ $cell['text'] ?? '' }}
                                                                </td>
                                                            @endforeach
                                                        </tr>
                                                    @endforeach
                                                </table>
                                            </div>
                                        @elseif ($type === 'equation')
                                            @php
                                                $latex = (string) ($block['equation']['latex'] ?? $block['latex'] ?? $block['text'] ?? 'E = mc^2');
                                                $caption = (string) ($block['equation']['caption'] ?? $block['caption'] ?? '');
                                                $display = (string) ($block['equation']['display'] ?? 'block');
                                            @endphp
                                            <div class="word-equation-block"
                                                id="word-block-{{ $index }}"
                                                data-block-index="{{ $index }}"
                                                data-latex="{{ $latex }}"
                                                data-caption="{{ $caption }}"
                                                data-display="{{ $display }}"
                                                contenteditable="false"
                                                @dblclick="$wire.editEquation({{ $index }})"
                                                style="user-select:none; margin: 16px 0; padding: 12px 18px; background: #fbfcfe; border: 1px solid #e1dfdd; border-radius: 4px; display: flex; align-items: center; justify-content: center; position: relative; cursor: pointer;">
                                                <div class="word-equation-actions" style="position: absolute; right: 8px; top: 8px; display: flex; gap: 4px;">
                                                    <button type="button" class="word-tool-btn" style="padding: 2px 8px; font-size: 11px; height: 22px; background: #ffffff; border: 1px solid #d1d1d1; border-radius: 3px; cursor: pointer; color: #4f46e5; font-weight: 700;" wire:click="executeModelOnEquation({{ $index }})" title="Eksekusi model AI saat ini untuk menjabarkan rumus ini">
                                                        ⚡ Jabarkan Model
                                                    </button>
                                                    <button type="button" class="word-tool-btn" style="padding: 2px 8px; font-size: 11px; height: 22px; background: #ffffff; border: 1px solid #d1d1d1; border-radius: 3px; cursor: pointer;" wire:click="editEquation({{ $index }})" title="Edit Rumus LaTeX">
                                                        ✏️ Edit Rumus
                                                    </button>
                                                    <button type="button" class="word-tool-btn" style="padding: 2px 6px; font-size: 11px; height: 22px; background: #ffffff; border: 1px solid #d1d1d1; border-radius: 3px; cursor: pointer;" @click.stop="navigator.clipboard?.writeText('{{ addslashes($latex) }}'); $wire.notify('LaTeX Disalin', 'Sintaks rumus disalin.', 'info')" title="Salin Sintaks LaTeX">
                                                        📋 Salin
                                                    </button>
                                                    <button type="button" class="word-tool-btn" style="padding: 2px 6px; font-size: 11px; height: 22px; background: #ffffff; border: 1px solid #d1d1d1; border-radius: 3px; cursor: pointer; color: #d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Rumus">
                                                        🗑️
                                                    </button>
                                                </div>
                                                <div class="word-equation-render" style="font-family: 'Cambria Math', 'KaTeX_Math', 'Times New Roman', serif; font-size: 1.18rem; text-align: center; color: #242424; margin: auto; padding: 4px 50px 4px 10px; overflow-x: auto;">
                                                    <span class="latex-math-render" data-latex="{{ $latex }}">{{ $latex }}</span>
                                                </div>
                                                @if ($caption !== '')
                                                    <div class="word-equation-caption" style="position: absolute; right: 16px; font-size: 11pt; font-weight: 600; color: #616161;">
                                                        ({{ trim($caption, '()') }})
                                                    </div>
                                                @endif
                                            </div>
                                        @elseif ($type === 'image')
                                            @php
                                                $src = (string) ($block['image']['src'] ?? $block['src'] ?? '');
                                                $alt = (string) ($block['image']['alt'] ?? $block['alt'] ?? 'Gambar naskah');
                                                $caption = (string) ($block['image']['caption'] ?? $block['caption'] ?? $alt);
                                                $width = (int) ($block['image']['width'] ?? 480);
                                                $align = (string) ($block['align'] ?? 'center');
                                            @endphp
                                            <figure class="word-image-figure"
                                                id="word-block-{{ $index }}"
                                                data-block-index="{{ $index }}"
                                                data-caption="{{ $caption }}"
                                                data-width="{{ $width }}"
                                                data-align="{{ $align }}"
                                                contenteditable="false"
                                                @dblclick="$wire.editImage({{ $index }})"
                                                style="margin: 18px 0; display: flex; flex-direction: column; align-items: {{ $align === 'left' ? 'flex-start' : ($align === 'right' ? 'flex-end' : 'center') }}; text-align: {{ $align }}; position: relative; cursor: pointer;">
                                                <div style="position: relative; display: inline-block; max-width: 100%;">
                                                    <div class="word-image-actions" style="position: absolute; right: 6px; top: 6px; display: flex; gap: 4px; background: rgba(255,255,255,0.95); padding: 3px 6px; border-radius: 4px; box-shadow: 0 1px 4px rgba(0,0,0,0.15); z-index:10;">
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 5px; font-size: 10px; height: 20px; {{ $width === 320 ? 'background:#e0e7ff; font-weight:700;' : '' }}" wire:click="setImageWidth({{ $index }}, 320)" title="Ukuran Kecil (320px)">320</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 5px; font-size: 10px; height: 20px; {{ $width === 480 ? 'background:#e0e7ff; font-weight:700;' : '' }}" wire:click="setImageWidth({{ $index }}, 480)" title="Ukuran Sedang (480px)">480</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 5px; font-size: 10px; height: 20px; {{ $width === 640 ? 'background:#e0e7ff; font-weight:700;' : '' }}" wire:click="setImageWidth({{ $index }}, 640)" title="Ukuran Lebar (640px)">640</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 5px; font-size: 10px; height: 20px;" wire:click="setImageAlign({{ $index }}, 'left')" title="Rata Kiri">⬅️</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 5px; font-size: 10px; height: 20px;" wire:click="setImageAlign({{ $index }}, 'center')" title="Rata Tengah">↔️</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 5px; font-size: 10px; height: 20px;" wire:click="setImageAlign({{ $index }}, 'right')" title="Rata Kanan">➡️</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 6px; font-size: 10px; height: 20px;" wire:click="editImage({{ $index }})" title="Ganti / Edit Gambar">🖼️ Edit</button>
                                                        <button type="button" class="word-tool-btn" style="padding: 1px 6px; font-size: 10px; height: 20px; color: #d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Gambar">🗑️</button>
                                                    </div>
                                                    @if ($src !== '')
                                                        <img src="{{ $src }}"
                                                            alt="{{ $alt }}"
                                                            style="max-width: 100%; width: {{ $width }}px; height: auto; border: 1px solid #edebe9; border-radius: 3px; display: block; box-shadow: 0 2px 6px rgba(0,0,0,0.06);">
                                                    @else
                                                        <div style="width: {{ $width }}px; height: 160px; background: #f3f2f1; border: 2px dashed #c8c6c4; display: flex; align-items: center; justify-content: center; color: #605e5c; font-size: 13px;">
                                                            📷 Gambar Belum Dipilih
                                                        </div>
                                                    @endif
                                                </div>
                                                @if ($caption !== '')
                                                    <figcaption style="margin-top: 6px; font-size: 10pt; font-style: italic; color: #616161; max-width: {{ $width }}px;">
                                                        {{ $caption }}
                                                    </figcaption>
                                                @endif
                                            </figure>
                                        @elseif ($type === 'footnote-ref')
                                            <div class="word-footnote-block"
                                                id="word-block-{{ $index }}"
                                                data-block-index="{{ $index }}"
                                                contenteditable="false"
                                                style="margin: 14px 0 6px 0; padding-top: 6px; border-top: 1px solid #cbd5e1; font-size: 9.5pt; color: #475569; display: flex; align-items: baseline; gap: 8px;">
                                                <span style="font-size: 9pt; font-weight: 700; color: #0f6cbd;">⚓ Catatan Kaki:</span>
                                                <span style="flex: 1; font-style: italic;">{!! e($text) !!}</span>
                                                <button type="button" class="word-tool-btn" style="padding: 1px 6px; font-size: 10px; color: #d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Catatan Kaki">🗑️</button>
                                            </div>
                                        @elseif ($type === 'endnote-ref')
                                            <div class="word-endnote-block"
                                                id="word-block-{{ $index }}"
                                                data-block-index="{{ $index }}"
                                                contenteditable="false"
                                                style="margin: 8px 0; padding: 6px 10px; background: #f8fafc; border-left: 3px solid #64748b; border-radius: 3px; font-size: 9.5pt; color: #334155; display: flex; align-items: baseline; gap: 8px;">
                                                <span style="font-size: 9pt; font-weight: 700; color: #475569;">📑 Catatan Akhir:</span>
                                                <span style="flex: 1;">{!! e($text) !!}</span>
                                                <button type="button" class="word-tool-btn" style="padding: 1px 6px; font-size: 10px; color: #d13438;" wire:click="deleteBlock({{ $index }})" title="Hapus Catatan Akhir">🗑️</button>
                                            </div>
                                        @else
                                            @if ($inBibliographySection)
                                                <p class="word-bib-entry" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="{{ $this->blockInlineStyle($block) }}">
                                                    {!! $text !!}
                                                </p>
                                            @else
                                                @php
                                                    $pIdxOnPage = 0;
                                                    foreach ($this->getPageParagraphs($pageNumber) as $pItem) {
                                                        if ($pItem['globalIndex'] === $index) {
                                                            $pIdxOnPage = $pItem['pageIndex'];
                                                            break;
                                                        }
                                                    }
                                                @endphp
                                                <div class="word-para-item-wrapper" style="position:relative;" x-data="{ hovered: false }" @mouseenter="hovered = true" @mouseleave="hovered = false">
                                                    @if (!empty($pageOverrides[$pageNumber]['lineNumbers']) || !empty($pageSetup['lineNumbers']))
                                                        <span class="ns-line-number" contenteditable="false" style="position:absolute; left:-28px; width:22px; text-align:right; font-family:var(--rb-font-mono, monospace); font-size:10px; color:#94a3b8; user-select:none; pointer-events:none;">{{ $index + 1 }}</span>
                                                    @endif
                                                    <p class="word-paragraph-text {{ !empty($block['dropCap']) ? 'word-drop-cap' : '' }}" id="word-block-{{ $index }}" data-block-index="{{ $index }}" style="{{ $this->blockInlineStyle($block) }}">
                                                        {!! $text !!}
                                                    </p>
                                                    <div x-show="hovered" contenteditable="false" style="position:absolute; right:4px; top:-12px; z-index:20; display:flex; gap:3px; background:#ffffff; border:1px solid #cbd5e1; border-radius:4px; padding:2px 4px; box-shadow:0 2px 5px rgba(0,0,0,0.1);">
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="openParagraphCustomizer({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Kustomisasi paragraf ini">⚙️</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="togglePageParagraphFirstLineIndent({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Indentasi Baris Pertama (1.27cm)">📐</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="highlightPageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Sorot Warna Kuning">🖍️</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="togglePageParagraphDropCap({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Huruf kapital awal membesar (Drop Cap)">🔤</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="movePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }}, -1)" title="Geser naik">⬆️</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="movePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }}, 1)" title="Geser turun">⬇️</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="duplicatePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Duplikat paragraf">📄</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="splitPageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Pecah paragraf">✂️</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="paraphrasePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Parafrase kalimat ragam akademik">🔄</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px;" wire:click="polishPageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Poles bahasa akademik">✨</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px; color:#4f46e5; font-weight:bold;" wire:click="executeModelOnParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Eksekusi model AI saat ini pada paragraf ini">⚡</button>
                                                        <button type="button" class="word-tool-btn" style="padding:1px 5px; font-size:10px; height:18px; color:#d13438;" wire:click="deletePageParagraph({{ $pageNumber }}, {{ $pIdxOnPage }})" title="Hapus paragraf">🗑️</button>
                                                    </div>
                                                </div>
                                            @endif
                                        @endif
                                    @endif
                                @endif
                            @endforeach

                            @if ($inBulletList)
                                </ul>
                            @endif
                            @if ($inNumberedList)
                                </ol>
                            @endif
                        </div>

                        <!-- Footer Halaman (Sesuai Konsep Microsoft Word & Gambar 1) -->
                        <div class="word-sheet-footer" contenteditable="false">
                            @if ($isFirstPage)
                                <!-- Awal BAB: Nomor Halaman di Bawah Tengah (Sesuai Gambar 1 Microsoft Word) -->
                                <span class="word-sheet-page-bottom" title="Halaman {{ $pageNumber }}">{{ $pageNumber }}</span>
                            @else
                                <span style="font-size: 10px; color: #8a8886;">{{ $docTitle }}</span>
                            @endif
                        </div>

                    </div>
                @endforeach

            </div>
        </main>

        <!-- ================================================================= -->
        <!-- RIGHT TASK PANE: XISHIE ASSISTANT, COMMENTS & REVISIONS            -->
        <!-- ================================================================= -->
        @if ($assistantOpen || $showComments || $showRevisions)
            <aside class="word-taskpane">

                <!-- 1. XISHIE ASSISTANT TASK PANE (PRIMARY AI COPILOT) -->
                @if ($assistantOpen)
                    <div class="word-assistant-header">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div class="word-copilot-icon-badge" style="width:22px; height:22px; font-size:12px;">✨</div>
                            <div>
                                <span class="word-taskpane-title" style="font-weight:700; color:var(--ms-text-primary);">Xishie Assistant</span>
                                <div style="font-size:10px; color:var(--ms-text-muted);">AI Co-pilot Penulisan Ilmiah</div>
                            </div>
                        </div>
                        <div style="display:flex; align-items:center; gap:4px;">
                            @if (!empty($assistantLog))
                                <button type="button" class="word-icon-btn" wire:click="clearAssistantLog" title="Bersihkan Riwayat Chat">
                                    <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor">
                                        <path fill-rule="evenodd" d="M9 2a1 1 0 00-.894.553L7.382 4H4a1 1 0 000 2v10a2 2 0 002 2h8a2 2 0 002-2V6a1 1 0 100-2h-3.382l-.724-1.447A1 1 0 0011 2H9zM7 8a1 1 0 012 0v6a1 1 0 11-2 0V8zm5-1a1 1 0 00-1 1v6a1 1 0 102 0V8a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                    </svg>
                                </button>
                            @endif
                            <button type="button" class="word-icon-btn" wire:click="closeAssistant" title="Tutup Asisten">&times;</button>
                        </div>
                    </div>

                    <!-- Context Bar -->
                    <div class="word-assistant-context">
                        <span style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap; max-width:210px;">
                            📄 {{ Str::limit($docTitle, 22) }}
                        </span>
                        <span style="font-size:10px; color:#4338ca; font-weight:600; background:#e0e7ff; padding:1px 6px; border-radius:9999px;">
                            Blok #{{ $activeBlock + 1 }} ({{ ucfirst($blocks[$activeBlock]['type'] ?? 'paragraf') }})
                        </span>
                    </div>

                    <!-- Model Selector & Target Bar -->
                    <div class="word-assistant-model-bar" style="display:flex; align-items:center; justify-content:space-between; padding:4px 8px; background:#f1f5f9; border-bottom:1px solid #e2e8f0; font-size:11px; gap:6px;">
                        <div style="display:flex; align-items:center; gap:4px; flex:1; min-width:0;">
                            <span style="font-weight:700; color:#334155; font-size:10px; text-transform:uppercase; letter-spacing:0.5px;">Model:</span>
                            <select wire:model.live="currentAssistantModel" class="word-select" style="font-size:10.5px; height:24px; padding:0 4px; flex:1; border:1px solid #cbd5e1; border-radius:4px; background:#ffffff; color:#1e293b; font-weight:600;" title="Pilih model AI saat ini">
                                @foreach ($this->getAvailableAssistantModels() as $availModel)
                                    <option value="{{ $availModel['id'] }}">
                                        {{ $availModel['name'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <span style="font-size:9.5px; color:#0f766e; background:#ccfbf1; font-weight:700; padding:2px 6px; border-radius:9999px; white-space:nowrap;" title="Status Model Aktif">
                            ● Aktif
                        </span>
                    </div>

                    <!-- Quick Model Execution Bar -->
                    <div class="word-assistant-execute-box" style="padding:6px 8px; background:linear-gradient(135deg, #eef2ff 0%, #faf5ff 100%); border-bottom:1px solid #c7d2fe;">
                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:4px;">
                            <span style="font-weight:700; font-size:10.5px; color:#4338ca; display:inline-flex; align-items:center; gap:4px;">
                                <span>⚡</span> Eksekusi Model Sekarang
                            </span>
                            <select wire:model="executeModelTarget" style="font-size:9.5px; height:20px; padding:0 3px; border-radius:3px; border:1px solid #a5b4fc; background:#ffffff; color:#312e81;">
                                <option value="current_page">Halaman Saat Ini</option>
                                <option value="active_block">Ganti Blok Aktif</option>
                                <option value="append">Akhir Naskah</option>
                            </select>
                        </div>
                        <div style="display:flex; gap:4px;">
                            <input type="text" wire:model="executeModelPrompt" wire:keydown.enter="submitExecuteModel" placeholder="Instruksi ke model (mis. Kembangkan hipotesis riset)..." style="flex:1; height:26px; font-size:11px; padding:0 6px; border:1px solid #c7d2fe; border-radius:4px; background:#ffffff;" />
                            <button type="button" wire:click="submitExecuteModel" wire:loading.attr="disabled" wire:target="submitExecuteModel,executeCurrentModel" style="height:26px; padding:0 8px; font-size:10.5px; font-weight:700; background:#4338ca; color:#ffffff; border:none; border-radius:4px; cursor:pointer;" title="Eksekusi model sekarang dan terapkan langsung ke kanvas naskah">
                                Eksekusi
                            </button>
                        </div>
                    </div>

                    {{-- Review Panel di Panel Asisten --}}
                    @if ($isReviewingModelOutput)
                        <div class="word-assistant-review-panel" style="padding:8px 10px; background:linear-gradient(135deg, #eef2ff 0%, #fdf4ff 100%); border-bottom:1px solid #c7d2fe; font-size:11px;">
                            <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:5px;">
                                <span style="font-weight:700; color:#312e81; display:inline-flex; align-items:center; gap:4px;">
                                    <span>🔍</span> Review Hasil Model
                                </span>
                                <span style="font-size:9.5px; background:#e0e7ff; color:#4338ca; padding:1px 6px; border-radius:3px; font-weight:700;">
                                    Versi #{{ $modelGenerationCount }}
                                </span>
                            </div>
                            <div style="font-size:10.5px; color:#475569; margin-bottom:7px; line-height:1.4;">
                                Draf &ldquo;<em>{{ Str::limit($lastExecutedModelPrompt, 38) }}</em>&rdquo; sedang aktif di naskah.
                            </div>
                            <div style="display:flex; gap:4px;">
                                <button type="button" wire:click="acceptModelOutput" style="flex:1; height:26px; font-size:10px; font-weight:700; background:#16a34a; color:#ffffff; border:none; border-radius:4px; cursor:pointer;" title="Terima hasil ini">
                                    ✓ Terima
                                </button>
                                <button type="button" wire:click="regenerateCurrentModel" wire:loading.attr="disabled" wire:target="regenerateCurrentModel" style="flex:1.2; height:26px; font-size:10px; font-weight:700; background:#ffffff; color:#4338ca; border:1px solid #c7d2fe; border-radius:4px; cursor:pointer;" title="Generate ulang draf alternatif">
                                    🔄 Generate Ulang
                                </button>
                                <button type="button" wire:click="discardModelOutput" style="height:26px; padding:0 8px; font-size:10px; font-weight:600; background:#ffffff; color:#dc2626; border:1px solid #fecaca; border-radius:4px; cursor:pointer;" title="Tolak hasil ini">
                                    ✕ Tolak
                                </button>
                            </div>
                        </div>
                    @endif

                    <!-- Palet Slash Command Berdasarkan Kategori (26 Perintah Ilmiah) -->
                    <div style="padding:.4rem .6rem; background:#f8fafc; border-bottom:1px solid #e2e8f0;">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.35rem; flex-wrap:wrap; gap:.3rem;">
                            <span style="font-size:.62rem; font-weight:700; color:#475569; text-transform:uppercase;">PERINTAH (26 SLASH COMMAND):</span>
                            <input
                                type="text"
                                wire:model.live.debounce.150ms="drawerCommandSearch"
                                placeholder="Cari..."
                                style="font-size:.62rem; padding:1px 5px; height:20px; width:80px; border:1px solid #cbd5e1; border-radius:3px; background:#fff;"
                            />
                        </div>
                        <div style="display:flex; gap:.25rem; flex-wrap:wrap; margin-bottom:.35rem;">
                            @foreach ($drawerCategories as $dcKey => $dcLabel)
                                <button
                                    type="button"
                                    wire:click="setDrawerCommandCategory('{{ $dcKey }}')"
                                    style="border:1px solid {{ ($drawerCommandCategory ?? 'all') === $dcKey ? '#4338ca' : '#cbd5e1' }};
                                           background:{{ ($drawerCommandCategory ?? 'all') === $dcKey ? '#4338ca' : '#ffffff' }};
                                           color:{{ ($drawerCommandCategory ?? 'all') === $dcKey ? '#ffffff' : '#475569' }};
                                           font-size:.58rem; font-weight:700; padding:1px 5px; border-radius:3px; cursor:pointer;"
                                >{{ $dcLabel }}</button>
                            @endforeach
                        </div>
                        <div class="word-assistant-tools-bar" style="max-height:85px; overflow-y:auto;">
                            @foreach ($drawerCommands as $dCmd)
                                <button
                                    type="button"
                                    class="word-assistant-tool-chip"
                                    wire:click="runTool('{{ $dCmd['name'] }}')"
                                    title="{{ $dCmd['description'] }}"
                                    style="font-size:.6rem; padding:2px 5px;"
                                >{{ $dCmd['name'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    <!-- Conversation Stream -->
                    <div class="word-assistant-chat" data-delete-action="deleteAssistantMessage" x-init="$watch('$el.children.length', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $nextTick(() => $el.scrollTop = $el.scrollHeight);">
                        @if (empty($assistantLog))
                            <!-- Empty State with Interactive Prompts (Word 365 Copilot Style Grid) -->
                            <div class="word-assistant-empty">
                                <div style="width:48px; height:48px; border-radius:12px; background:linear-gradient(135deg, #eef2ff 0%, #ede9fe 100%); display:flex; align-items:center; justify-content:center; font-size:24px; box-shadow:0 2px 8px rgba(99,102,241,0.15);">
                                    ✨
                                </div>
                                <div style="font-weight:700; font-size:14px; color:var(--ms-text-primary);">
                                    Halo! Saya Xishie Assistant
                                </div>
                                <p style="font-size:11.5px; color:var(--ms-text-muted); line-height:1.45; margin:0 4px;">
                                    Asisten cerdas Anda untuk menelaah naskah, memoles gaya bahasa akademik, parafrase anti-plagiasi, menyusun matriks literatur, dan memastikan kepatuhan pedoman jurnal.
                                </p>

                                <div class="word-assistant-grid">
                                    <div class="word-assistant-card" wire:click="runTool('/poles')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">🪄</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Poles Teks</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Sempurnakan gaya bahasa akademik</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/parafrase')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">🔄</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Parafrase</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Tulis ulang kalimat bebas plagiasi</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/cek-pedoman')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">🎯</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Cek Pedoman</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Audit struktur IMRAD &amp; kaidah</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/sitasi')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">📚</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Cari Sitasi</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Rekomendasi rujukan ilmiah valid</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/matriks-literatur')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">📊</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Matriks SOTA</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Tabel sintesis literatur komparatif</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/cek-gap')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">🔬</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Cek Gap</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Uji kebaruan &amp; rumusan masalah</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/rumus')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">π</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Rumus LaTeX</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Model matematika &amp; persamaan</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/kembangkan')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">✍️</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Kembangkan</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Elaborasi poin jadi paragraf utuh</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/abstrak')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">📄</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Rancang Abstrak</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Draf abstrak terstruktur IMRAD</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/tabel-analisis')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">📊</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Tabel Analisis</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Tabel evaluasi komparasi kinerja</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/pendahuluan')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">📖</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Bab Pendahuluan</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Latar belakang, urgensi &amp; gap</span>
                                    </div>
                                    <div class="word-assistant-card" wire:click="runTool('/kesimpulan')">
                                        <div style="display:flex; align-items:center; gap:6px;">
                                            <span style="font-size:14px;">🏁</span>
                                            <strong style="font-size:11.5px; color:#4338ca;">Bab Kesimpulan</strong>
                                        </div>
                                        <span style="font-size:10px; color:var(--ms-text-muted);">Sintesis temuan &amp; limitasi riset</span>
                                    </div>
                                </div>
                            </div>
                        @else
                            @foreach ($assistantLog as $logIndex => $log)
                                @if (($log['role'] ?? '') === 'user')
                                    <!-- User Message Bubble -->
                                    <div class="word-assistant-bubble-user">
                                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; gap:8px;">
                                            <strong style="font-size:10.5px; color:#0f6cbd;">Anda</strong>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                @if (!empty($log['command']) && $log['command'] !== 'freeform' && $log['command'] !== 'chat')
                                                    <span style="font-size:9.5px; background:#cfe4fa; color:#0f6cbd; padding:1px 5px; border-radius:4px; font-weight:700;">{{ $log['command'] }}</span>
                                                @endif
                                                <button type="button" wire:click="deleteAssistantMessage({{ $logIndex }})" style="border:none; background:none; color:#ef4444; cursor:pointer; font-size:11px; padding:0 2px;" title="Hapus pesan ini">🗑️</button>
                                            </div>
                                        </div>
                                        <div>{{ $log['content'] ?? '' }}</div>
                                    </div>
                                @else
                                    <!-- Assistant Message Bubble -->
                                    <div class="word-assistant-bubble-bot">
                                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:6px;">
                                            <div style="display:flex; align-items:center; gap:5px;">
                                                <span style="font-size:12px;">✨</span>
                                                <strong style="font-size:11px; color:#4338ca;">Xishie Assistant</strong>
                                                @if (!empty($log['model']))
                                                    <span style="font-size:9.5px; background:#ede9fe; color:#6d28d9; padding:1px 5px; border-radius:3px; font-family:monospace; font-weight:600;">{{ $log['model'] }}</span>
                                                @endif
                                                @if (!empty($log['data']['verified']))
                                                    <span style="font-size:9.5px; padding:1px 5px; background:#dcfce7; border-radius:3px; color:#15803d; font-weight:700;" title="{{ count($log['data']['verified']) }} sitasi valid di pustaka">
                                                        ✓ {{ count($log['data']['verified']) }} Valid
                                                    </span>
                                                @endif
                                                @if (!empty($log['data']['unmatched']))
                                                    <span style="font-size:9.5px; padding:1px 5px; background:#fee2e2; border-radius:3px; color:#dc2626; font-weight:700;" title="{{ count($log['data']['unmatched']) }} sitasi belum terdaftar">
                                                        ⚠️ {{ count($log['data']['unmatched']) }} Cek
                                                    </span>
                                                @endif
                                            </div>
                                            <div style="display:flex; align-items:center; gap:4px;">
                                                <span style="font-size:9.5px; color:var(--ms-text-muted); background:#f1f5f9; padding:1px 5px; border-radius:3px;">AI Copilot</span>
                                                <button type="button" wire:click="deleteAssistantMessage({{ $logIndex }})" style="border:none; background:none; color:#ef4444; cursor:pointer; font-size:11px; padding:0 2px;" title="Hapus pesan ini">🗑️</button>
                                            </div>
                                        </div>
                                        <div class="word-assistant-rendered-markdown" style="line-height:1.55; margin-bottom:10px; font-size:12px;">
                                            {!! \Illuminate\Support\Str::markdown($log['content'] ?? '') !!}
                                        </div>

                                        <!-- In-Document Action Buttons -->
                                        @if ($isReviewingModelOutput && ($log['applied'] ?? false))
                                            <div style="margin-top:6px; padding:6px 8px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px; font-size:11px;">
                                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:5px;">
                                                    <span style="font-weight:700; color:#15803d; display:inline-flex; align-items:center; gap:4px;">
                                                        <span>✨</span> Diterapkan ke Kanvas Naskah
                                                    </span>
                                                    <span style="font-size:9.5px; background:#dcfce7; color:#166534; padding:1px 5px; border-radius:3px; font-weight:700;">
                                                        Versi #{{ $modelGenerationCount }} · Review Aktif
                                                    </span>
                                                </div>
                                                <div style="display:flex; gap:4px;">
                                                    <button type="button" wire:click="acceptModelOutput" style="flex:1; height:24px; font-size:10px; font-weight:700; background:#16a34a; color:#ffffff; border:none; border-radius:4px; cursor:pointer;" title="Terima hasil ini dan simpan permanen ke naskah">
                                                        ✓ Terima &amp; Simpan
                                                    </button>
                                                    <button type="button" wire:click="regenerateCurrentModel" wire:loading.attr="disabled" wire:target="regenerateCurrentModel" style="flex:1.2; height:24px; font-size:10px; font-weight:700; background:#ffffff; color:#4338ca; border:1px solid #c7d2fe; border-radius:4px; cursor:pointer;" title="Generate ulang variasi baru jika belum sesuai">
                                                        🔄 Generate Ulang
                                                    </button>
                                                    <button type="button" wire:click="discardModelOutput" style="height:24px; padding:0 8px; font-size:10px; font-weight:600; background:#ffffff; color:#dc2626; border:1px solid #fecaca; border-radius:4px; cursor:pointer;" title="Tolak dan kembalikan naskah ke kondisi awal">
                                                        ✕ Tolak
                                                    </button>
                                                </div>
                                            </div>
                                        @else
                                            <div style="display:flex; flex-wrap:wrap; gap:4px; border-top:1px solid #f1f5f9; padding-top:8px;">
                                                <button type="button" @click="$wire.applyAssistantToCurrentPage({{ $logIndex }}, typeof activePage !== 'undefined' ? activePage : null)" class="word-assistant-act-btn" style="background:#0f6cbd; color:#ffffff; font-weight:700; border-color:#0f6cbd;" title="Eksekusi rekomendasi model ini langsung ke halaman naskah saat ini">
                                                    <span>⚡ Terapkan ke Halaman Ini</span>
                                                </button>
                                                <button type="button" wire:click="insertAssistantResponse({{ $logIndex }})" class="word-assistant-act-btn" title="Sisipkan teks ini sebagai paragraf baru di naskah">
                                                    <span>➕ Sisipkan ke Naskah</span>
                                                </button>
                                                <button type="button" wire:click="replaceActiveBlockWithAssistant({{ $logIndex }})" class="word-assistant-act-btn" title="Ganti blok aktif #{{ $activeBlock + 1 }} dengan teks ini">
                                                    <span>🔄 Ganti Blok Aktif</span>
                                                </button>
                                                <button type="button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(@js($log['content'])); copied = true; setTimeout(() => copied = false, 2000);" class="word-assistant-act-btn" title="Salin teks ke clipboard">
                                                    <span x-show="!copied">📋 Salin</span>
                                                    <span x-show="copied" style="color:#107c41;">✓ Tersalin!</span>
                                                </button>
                                                <button type="button" wire:click="deleteAssistantMessage({{ $logIndex }})" class="word-assistant-act-btn" style="color:#dc2626;" title="Hapus respon ini dari riwayat">
                                                    <span>🗑️ Hapus</span>
                                                </button>
                                            </div>
                                        @endif

                                        <!-- Quick Follow-up Chips -->
                                        <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:6px; border-top:1px dashed #f1f5f9; padding-top:6px;">
                                            <button type="button" wire:click="sendAssistantWithText('Jelaskan rekomendasi ini secara lebih mendalam dengan rincian operasional.')" class="word-assistant-chip-tiny" title="Minta penjelasan lebih mendalam">
                                                💬 Jelaskan Detail
                                            </button>
                                            <button type="button" wire:click="sendAssistantWithText('Buatkan alternatif formulasi lain untuk kalimat/bagian ini.')" class="word-assistant-chip-tiny" title="Minta alternatif lain">
                                                🔄 Alternatif Lain
                                            </button>
                                            <button type="button" wire:click="sendAssistantWithText('Buat formulasi kalimat yang lebih padat dan ringkas.')" class="word-assistant-chip-tiny" title="Minta versi lebih padat">
                                                ✂️ Lebih Ringkas
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif

                        <!-- Loading Indicator (Hidden until active request) -->
                        <div wire:loading.flex wire:target="sendAssistant,runTool,sendAssistantWithText,submitExecuteModel,executeCurrentModel" class="word-assistant-loading" style="display: none;">
                            <span class="word-assistant-spinner"></span>
                            <span style="font-weight: 500;">Xishie Assistant sedang menelaah naskah Anda...</span>
                        </div>
                    </div>

                    <!-- Input Form -->
                    <div class="word-assistant-input-area">
                        <form wire:submit.prevent="sendAssistant" style="display:flex; gap:6px;">
                            <input type="text" wire:model="assistantInput" class="word-select" style="flex:1; height:34px; padding:0 10px; font-size:12px;" placeholder="Tanya asisten atau ketik /poles, /sitasi... (Enter)">
                            <button type="submit" class="word-btn-pill word-btn-share" style="height:34px; padding:0 12px; font-weight:600;" wire:loading.attr="disabled" wire:target="sendAssistant,runTool,sendAssistantWithText,submitExecuteModel,executeCurrentModel">
                                Kirim
                            </button>
                        </form>
                        <div style="font-size:10px; color:var(--ms-text-muted); display:flex; justify-content:space-between;">
                            <span>Tekan <strong>Enter</strong> untuk mengirim</span>
                            <span>Perintah: /poles, /parafrase, /sitasi, /cek-pedoman</span>
                        </div>
                    </div>

                <!-- 2. COMMENTS TASK PANE -->
                @elseif ($showComments)
                    <div class="word-taskpane-header">
                        <span class="word-taskpane-title">Comments ({{ count($comments) }})</span>
                        <button type="button" class="word-icon-btn" wire:click="closeTaskPane">&times;</button>
                    </div>
                    <div class="word-taskpane-body">
                        <div style="display:flex; gap:6px;">
                            <input type="text" class="word-select" style="flex:1; height:32px;" placeholder="Comment on block #{{ $activeBlock + 1 }}..." wire:model="commentDraft" wire:keydown.enter="addComment">
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="addComment">Post</button>
                        </div>
                        @forelse ($comments as $cIdx => $c)
                            <div style="padding:10px; border-radius:6px; border:1px solid #e1dfdd; background:#ffffff; box-shadow:var(--ms-shadow-card);">
                                <div style="display:flex; justify-content:space-between; font-size:11px; color:#8a8886; margin-bottom:4px;">
                                    <strong>{{ $c['author'] ?? 'Reviewer' }}</strong>
                                    <span>Block #{{ ((int)($c['blockIndex'] ?? 0)) + 1 }}</span>
                                </div>
                                <p style="font-size:12px; line-height:1.45; margin-bottom:8px;">{{ $c['body'] ?? '' }}</p>
                                <div style="display:flex; justify-content:flex-end; gap:8px;">
                                    <button type="button" wire:click="resolveComment({{ $cIdx }})" class="word-tool-btn" style="color:#0f6cbd;">
                                        {{ !empty($c['resolved']) ? 'Reopen' : '✓ Resolve' }}
                                    </button>
                                    <button type="button" wire:click="deleteComment({{ $cIdx }})" class="word-tool-btn" style="color:#c50f1f;">Delete</button>
                                </div>
                            </div>
                        @empty
                            <div style="text-align:center; color:#8a8886; font-size:12px; padding:24px 0;">No comments yet.</div>
                        @endforelse
                    </div>

                <!-- 3. REVISIONS TASK PANE -->
                @elseif ($showRevisions)
                    <div class="word-taskpane-header">
                        <span class="word-taskpane-title">Track Changes ({{ $pendingRevisionCount }} pending)</span>
                        <button type="button" class="word-icon-btn" wire:click="closeTaskPane">&times;</button>
                    </div>
                    <div class="word-taskpane-body">
                        <div style="display:flex; gap:6px; margin-bottom:8px;">
                            <button type="button" wire:click="acceptAllRevisions" class="word-btn-pill word-btn-share" style="flex:1; justify-content:center;">✓ Accept All</button>
                            <button type="button" wire:click="rejectAllRevisions" class="word-btn-pill" style="flex:1; justify-content:center; color:#c50f1f;">✕ Reject All</button>
                        </div>
                        @forelse ($revisions as $rIdx => $rev)
                            @if (($rev['status'] ?? '') === 'pending')
                                <div style="padding:10px; border-radius:6px; border:1px solid #fde68a; background:#fffbeb; font-size:12px;">
                                    <div style="font-size:10px; color:#8a8886; margin-bottom:4px;">{{ $rev['author'] ?? 'Author' }} · Block #{{ ((int)($rev['blockIndex'] ?? 0)) + 1 }}</div>
                                    <div style="text-decoration:line-through; color:#c50f1f; margin-bottom:2px;">{{ $rev['before'] ?? '' }}</div>
                                    <div style="color:#107c41; font-weight:600; margin-bottom:6px;">{{ $rev['after'] ?? '' }}</div>
                                    <div style="display:flex; justify-content:flex-end; gap:6px;">
                                        <button type="button" wire:click="acceptRevision({{ $rIdx }})" class="word-tool-btn" style="color:#107c41;">✓ Accept</button>
                                        <button type="button" wire:click="rejectRevision({{ $rIdx }})" class="word-tool-btn" style="color:#c50f1f;">✕ Reject</button>
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div style="text-align:center; color:#8a8886; font-size:12px; padding:24px 0;">No pending revisions.</div>
                        @endforelse
                    </div>
                @endif

            </aside>
        @endif

    </div>

    <!-- ===================================================================== -->
    <!-- 6. BOTTOM STATUS BAR (EXACT MATCH FOR IMAGE 1)                        -->
    <!-- ===================================================================== -->
    <footer class="word-statusbar">
        <!-- Left: Page X of Y, Words, Proofing Language -->
        <div class="word-statusbar__left">
            <div class="word-status-item" wire:click="openDialog('stats')" title="Lompat ke halaman">
                <span>Halaman <span x-text="activePage || 1">1</span> dari {{ $pageCount ?? 1 }}</span>
                <span class="sr-only">Page 1 of {{ $pageCount ?? 1 }}</span>
            </div>
            <div class="word-status-item" wire:click="openDialog('stats')" title="Word Count">
                <span x-text="(liveWordCount || {{ $wordCount }}) + ' kata'">{{ number_format($wordCount) }} kata</span>
            </div>
            @if ($targetWordCount > 0)
                <div class="word-status-item" wire:click="openDialog('stats')" title="Target Word Count" style="color:#0f6cbd; font-weight:700;">
                    🎯 {{ number_format($statistics['words'] ?? $wordCount) }}/{{ number_format($targetWordCount) }} kata ({{ $this->getTargetWordProgress() }}%)
                </div>
            @endif
            <div class="word-status-item" style="display:inline-flex; align-items:center; gap:4px;" title="Model AI Aktif">
                <span>🤖</span> {{ $this->getCurrentAssistantModel() }}
            </div>
            <div class="word-status-item" style="display:inline-flex; align-items:center; gap:4px;" title="Format Margin Halaman (DIKTI 4-4-3-3)">
                <span>📄</span> {{ $pageSetup['size'] ?? 'A4' }} ({{ ($pageSetup['marginTop'] ?? 25) == 40 ? 'DIKTI 4-4-3-3' : 'Normal' }})
            </div>
            <div class="word-status-item" wire:click="openDialog('spelling')" title="Proofing Language">
                {{ $proofingLanguage ?? 'Indonesia (PUEBI)' }}
            </div>
            <div class="word-status-item" wire:click="openDialog('stats')" title="Status Aksesibilitas &amp; Statistik Dokumen (Waktu Sesi: {{ floor($sessionSeconds / 60) }}m, Kepatuhan: {{ $complianceAudit['score'] }}%)">
                <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor" style="color:#107c41;">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Accessibility: Good to go</span>
                <span class="sr-only" style="position:absolute; width:1px; height:1px; padding:0; margin:-1px; overflow:hidden; clip:rect(0,0,0,0); border:0;">Waktu Sesi: {{ floor($sessionSeconds / 60) }}m {{ $sessionSeconds % 60 }}s | Kepatuhan: {{ $complianceAudit['score'] }}%</span>
            </div>
        </div>

        <!-- Right: Focus Mode, View Switchers, Interactive Zoom Slider -->
        <div class="word-statusbar__right">
            <!-- Focus Button -->
            <button type="button" class="word-status-item" wire:click="toggleZenMode" style="border:none; background:transparent;" title="Focus Mode / Zen">
                <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                    <path d="M3 4a1 1 0 011-1h4a1 1 0 010 2H5v3a1 1 0 01-2 0V4zm14 0a1 1 0 00-1-1h-4a1 1 0 100 2h3v3a1 1 0 102 0V4zm-1 12h-3a1 1 0 100 2h4a1 1 0 001-1v-4a1 1 0 10-2 0v3zM4 16v-3a1 1 0 10-2 0v4a1 1 0 001 1h4a1 1 0 100-2H4z"/>
                </svg>
                <span>Focus</span>
            </button>

            <!-- View Mode Switchers -->
            <div class="word-view-switchers">
                <button type="button" class="word-view-btn {{ $viewMode === 'read' ? 'active' : '' }}" wire:click="$set('viewMode', 'read')" title="Read Mode">
                    <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor"><path d="M9 4.804A7.968 7.968 0 005.5 4c-1.255 0-2.443.29-3.5.804v10A7.969 7.969 0 015.5 14c1.669 0 3.218.51 4.5 1.385A7.962 7.962 0 0114.5 14c1.255 0 2.443.29-3.5.804v-10A7.968 7.968 0 0014.5 4c-1.255 0-2.443.29-3.5.804V12a1 1 0 11-2 0V4.804z"/></svg>
                </button>
                <button type="button" class="word-view-btn {{ $viewMode === 'print' ? 'active' : '' }}" wire:click="$set('viewMode', 'print')" title="Print Layout">
                    <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor"><path fill-rule="evenodd" d="M4 4a2 2 0 012-2h8a2 2 0 012 2v12a2 2 0 01-2 2H6a2 2 0 01-2-2V4zm2 0v12h8V4H6z" clip-rule="evenodd"/></svg>
                </button>
                <button type="button" class="word-view-btn {{ $viewMode === 'web' ? 'active' : '' }}" wire:click="$set('viewMode', 'web')" title="Web Layout">
                    <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor"><path fill-rule="evenodd" d="M4.083 9h11.834a8 8 0 010 2H4.083a8 8 0 010-2zM10 2a8 8 0 100 16 8 8 0 000-16z" clip-rule="evenodd"/></svg>
                </button>
            </div>

            <!-- Interactive Zoom Slider -->
            <div class="word-zoom-control">
                <button type="button" class="word-icon-btn" wire:click="zoomOut" style="width:18px; height:18px; font-size:12px;" title="Zoom Out">-</button>
                <input type="range" min="50" max="200" step="10" wire:model.live="zoom" class="word-zoom-slider" title="Zoom Slider: {{ $zoom }}%">
                <button type="button" class="word-icon-btn" wire:click="zoomIn" style="width:18px; height:18px; font-size:12px;" title="Zoom In">+</button>
                <span class="word-status-item" wire:click="setZoomLevel('100')" style="font-size:11px; font-weight:600; min-width:34px;">
                    {{ $zoom }}%
                </span>
            </div>
        </div>
    </footer>

    <!-- ===================================================================== -->
    <!-- 7. FLUENT WORD MODAL DIALOGS                                          -->
    <!-- ===================================================================== -->
    @if ($dialog !== '')
        <div class="word-dialog-backdrop" wire:click.self="closeDialog" @keydown.escape.window="$wire.closeDialog()">
            <div class="word-dialog">

                {{-- EKSEKUSI MODEL AI SEKARANG --}}
                @if ($dialog === 'execute_model')
                    <div class="word-dialog__header" style="background:linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); color:#ffffff;">
                        <span style="font-weight:700; font-size:13px; display:inline-flex; align-items:center; gap:6px;">⚡ Eksekusi Model AI ke Naskah Studio</span>
                        <button type="button" class="word-icon-btn" style="color:#ffffff;" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="padding:14px; display:flex; flex-direction:column; gap:12px;">
                        <div style="font-size:11.5px; color:#475569; line-height:1.45;">
                            Jalankan instruksi menggunakan model AI aktif dan terapkan hasilnya secara langsung ke kanvas naskah.
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:4px;">Model AI Aktif:</label>
                                <select wire:model="currentAssistantModel" class="word-select" style="width:100%; height:30px; font-size:11px;">
                                    @foreach ($this->getAvailableAssistantModels() as $availModel)
                                        <option value="{{ $availModel['id'] }}">
                                            {{ $availModel['name'] }} ({{ $availModel['provider'] }})
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:4px;">Target Penempatan:</label>
                                <select wire:model="executeModelTarget" class="word-select" style="width:100%; height:30px; font-size:11px;">
                                    <option value="current_page">Halaman Saat Ini (Hal. {{ $this->getCurrentPageNumber() }})</option>
                                    <option value="active_block">Ganti Blok Aktif (#{{ $activeBlock + 1 }})</option>
                                    <option value="new_page">Halaman Baru (+ Pemisah Halaman)</option>
                                    <option value="append">Akhir Dokumen (Append)</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label style="display:block; font-size:11px; font-weight:700; color:#334155; margin-bottom:4px;">Instruksi Pembuatan Konten (Prompt):</label>
                            <textarea wire:model="executeModelPrompt" rows="3" placeholder="Misalnya: Buatkan daftar pustaka, kembangkan bab metodologi, atau buatkan tabel..." class="word-select" style="width:100%; height:auto; font-size:11.5px; padding:6px; resize:vertical; font-family:inherit;"></textarea>
                            <div style="display:flex; flex-wrap:wrap; gap:4px; margin-top:5px;">
                                <button type="button" class="word-assistant-tool-chip" style="font-size:10px; padding:2px 6px;" wire:click="$set('executeModelPrompt', 'Buatkan daftar pustaka')">📚 Buatkan Daftar Pustaka</button>
                                <button type="button" class="word-assistant-tool-chip" style="font-size:10px; padding:2px 6px;" wire:click="$set('executeModelPrompt', 'Buatkan tabel sintesis literatur')">📊 Buatkan Tabel</button>
                                <button type="button" class="word-assistant-tool-chip" style="font-size:10px; padding:2px 6px;" wire:click="$set('executeModelPrompt', 'Buatkan rumus regresi linear LaTeX')">π Buatkan Rumus</button>
                                <button type="button" class="word-assistant-tool-chip" style="font-size:10px; padding:2px 6px;" wire:click="$set('executeModelPrompt', 'Buatkan draf abstrak penelitian')">📝 Buatkan Abstrak</button>
                                <button type="button" class="word-assistant-tool-chip" style="font-size:10px; padding:2px 6px;" wire:click="$set('executeModelPrompt', 'Buatkan bab kesimpulan dan saran')">🏁 Buatkan Kesimpulan</button>
                            </div>
                        </div>
                        <div style="display:flex; justify-content:flex-end; gap:6px;">
                            <button type="button" class="word-dialog__btn" wire:click="closeDialog">Batal</button>
                            <button type="button" class="word-dialog__btn word-dialog__btn--primary" style="background:#4f46e5; border-color:#4338ca;" wire:click="submitExecuteModel" wire:loading.attr="disabled" wire:target="submitExecuteModel,executeCurrentModel">
                                <span wire:loading.remove wire:target="submitExecuteModel,executeCurrentModel">⚡ Eksekusi &amp; Terapkan</span>
                                <span wire:loading wire:target="submitExecuteModel,executeCurrentModel">Mengeksekusi…</span>
                            </button>
                        </div>
                    </div>

                {{-- FIND & REPLACE --}}
                @elseif ($dialog === 'find')
                    <div class="word-dialog__header">
                        <span>Find and Replace</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Find what:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="findQuery" placeholder="Search query..." wire:keydown.enter="findText">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Replace with:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="replaceQuery" placeholder="Replacement...">
                        </div>
                        @if ($findResult)
                            <div style="padding:8px 12px; background:#ebf3fc; border-radius:4px; font-size:12px; color:#0f6cbd;">
                                {{ $findResult }}
                            </div>
                        @endif
                    </div>
                    <div class="word-dialog__footer" style="display:flex; justify-content:space-between; gap:6px;">
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="word-btn-pill" wire:click="findText">Find All</button>
                            <button type="button" class="word-btn-pill" wire:click="findPrevious" title="Find previous match">⏮ Prev</button>
                            <button type="button" class="word-btn-pill" wire:click="findNext" title="Find next match">Find Next ⏭</button>
                        </div>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="word-btn-pill" wire:click="replaceCurrent" title="Replace current match in active block">Replace</button>
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="replaceAll">Replace All</button>
                        </div>
                    </div>

                {{-- DOCUMENT STATS --}}
                @elseif (in_array($dialog, ['stats', 'statistics', 'wordcount'], true))
                    <div class="word-dialog__header">
                        <span>Word Count &amp; Readability Statistics</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div class="word-metric-card">
                                <span style="font-size:11px; color:#8a8886;">WORDS</span>
                                <strong style="font-size:20px; color:#0f6cbd;" x-text="liveWordCount || {{ $wordCount }}">{{ number_format($statistics['words'] ?? $wordCount) }}</strong>
                            </div>
                            <div class="word-metric-card">
                                <span style="font-size:11px; color:#8a8886;">CHARACTERS</span>
                                <strong style="font-size:20px; color:#0f6cbd;" x-text="liveCharCount || {{ $charCount }}">{{ number_format($statistics['characters'] ?? $charCount) }}</strong>
                            </div>
                            <div class="word-metric-card">
                                <span style="font-size:11px; color:#8a8886;">FLESCH READING EASE</span>
                                <strong style="font-size:20px; color:#107c41;">{{ $statistics['readingEase'] ?? 0 }} / 100</strong>
                            </div>
                            <div class="word-metric-card">
                                <span style="font-size:11px; color:#8a8886;">READING TIME</span>
                                <strong style="font-size:20px; color:#d83b01;">~{{ $statistics['readingTimeMinutes'] ?? 1 }} min</strong>
                            </div>
                        </div>
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-top:8px;">
                            <div style="padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; font-size:11px;">
                                <div style="color:#64748b;">Rumus Matematika:</div>
                                <strong style="font-size:14px; color:#0f6cbd;">{{ count(array_filter($blocks, static fn ($b) => ($b['type'] ?? '') === 'equation')) }}</strong>
                            </div>
                            <div style="padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; font-size:11px;">
                                <div style="color:#64748b;">Gambar &amp; Diagram:</div>
                                <strong style="font-size:14px; color:#107c41;">{{ count(array_filter($blocks, static fn ($b) => ($b['type'] ?? '') === 'image')) }}</strong>
                            </div>
                            <div style="padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; font-size:11px;">
                                <div style="color:#64748b;">Tabel Data:</div>
                                <strong style="font-size:14px; color:#7c3aed;">{{ count(array_filter($blocks, static fn ($b) => ($b['type'] ?? '') === 'table')) }}</strong>
                            </div>
                            <div style="padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; font-size:11px;">
                                <div style="color:#64748b;">Catatan Kaki:</div>
                                <strong style="font-size:14px; color:#d97706;">{{ count(array_filter($blocks, static fn ($b) => ($b['type'] ?? '') === 'footnote-ref')) }}</strong>
                            </div>
                        </div>

                        {{-- Target Word Count Box --}}
                        <div style="margin-top:8px; padding:8px 10px; background:#f0fdf4; border:1px solid #bbf7d0; border-radius:4px;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <span style="font-size:11px; font-weight:700; color:#166534;">🎯 TARGET KATA DOKUMEN:</span>
                                <span style="font-size:11px; font-weight:700; color:{{ ($targetWordCount > 0 && ($statistics['words'] ?? $wordCount) >= $targetWordCount) ? '#16a34a' : '#0f6cbd' }};">
                                    @if ($targetWordCount > 0)
                                        {{ number_format($statistics['words'] ?? $wordCount) }} / {{ number_format($targetWordCount) }} kata ({{ $this->getTargetWordProgress() }}%)
                                    @else
                                        Belum diatur
                                    @endif
                                </span>
                            </div>
                            @if ($targetWordCount > 0)
                                <div style="width:100%; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; margin-bottom:6px;">
                                    <div style="width:{{ min(100, $this->getTargetWordProgress() ?? 0) }}%; height:100%; background:{{ ($statistics['words'] ?? $wordCount) >= $targetWordCount ? '#16a34a' : '#0f6cbd' }}; transition:width 0.3s;"></div>
                                </div>
                                <div style="font-size:10px; color:#64748b; margin-bottom:6px;">
                                    @if (($statistics['words'] ?? $wordCount) >= $targetWordCount)
                                        <span style="color:#16a34a; font-weight:700;">✅ Target kata tercapai! (+{{ number_format(($statistics['words'] ?? $wordCount) - $targetWordCount) }} kata lebih)</span>
                                    @else
                                        <span>Sisa {{ number_format($targetWordCount - ($statistics['words'] ?? $wordCount)) }} kata lagi untuk mencapai target.</span>
                                    @endif
                                </div>
                            @endif
                            <div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center;">
                                <span style="font-size:10px; color:#475569; font-weight:600;">Preset:</span>
                                <button type="button" class="word-tool-btn" style="font-size:10px; padding:1px 6px; height:20px;" wire:click="setTargetWordCount(500)">500 (Abstrak)</button>
                                <button type="button" class="word-tool-btn" style="font-size:10px; padding:1px 6px; height:20px;" wire:click="setTargetWordCount(2000)">2.000 (Bab 1)</button>
                                <button type="button" class="word-tool-btn" style="font-size:10px; padding:1px 6px; height:20px;" wire:click="setTargetWordCount(5000)">5.000 (Artikel)</button>
                                <button type="button" class="word-tool-btn" style="font-size:10px; padding:1px 6px; height:20px;" wire:click="setTargetWordCount(10000)">10.000 (Proposal)</button>
                                <button type="button" class="word-tool-btn" style="font-size:10px; padding:1px 6px; height:20px;" wire:click="setTargetWordCount(25000)">25.000 (Tesis)</button>
                                <button type="button" class="word-tool-btn" style="font-size:10px; padding:1px 6px; height:20px; color:#ef4444;" wire:click="setTargetWordCount(0)">✕ Reset</button>
                            </div>
                        </div>
                        <div style="font-size:12px; color:#616161; line-height:1.6; padding-top:6px; margin-top:8px; border-top:1px solid #edebe9;">
                            <div>• Sentences: <strong>{{ $statistics['sentences'] ?? 0 }}</strong></div>
                            <div>• Paragraphs: <strong>{{ $statistics['paragraphs'] ?? count($blocks) }}</strong></div>
                            <div>• Speaking Time: <strong>~{{ $statistics['speakingTimeMinutes'] ?? 1 }} min</strong></div>
                            <div>• Academic Compliance: <strong style="color:#107c41;">{{ $complianceAudit['score'] ?? 85 }}% ({{ $complianceAudit['grade'] ?? 'Siap Publikasi' }})</strong></div>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="closeDialog">Close</button>
                    </div>

                {{-- PARAGRAPH CUSTOMIZER PER HALAMAN --}}
                @elseif ($dialog === 'paragraphCustomizer')
                    <div class="word-dialog__header">
                        <span>⚙️ Kustomisasi Paragraf Halaman {{ $customParaPage }}</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="display:flex; flex-direction:column; gap:12px; max-height:75vh; overflow-y:auto;">
                        <!-- Preset Cepat Kaidah Akademik -->
                        <div style="background:rgba(15,108,189,0.04); border:1px solid rgba(15,108,189,0.15); border-radius:4px; padding:8px 10px;">
                            <label style="display:block; font-weight:700; font-size:11px; color:#0f6cbd; margin-bottom:6px;">⚡ Terapkan Preset Kaidah Akademik:</label>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="word-tool-btn" wire:click="loadPresetIntoCustomizer('academic_standard')" style="padding:2px 8px; font-size:11px;">APA 1.5</button>
                                <button type="button" class="word-tool-btn" wire:click="loadPresetIntoCustomizer('journal_ieee')" style="padding:2px 8px; font-size:11px;">IEEE / ACM 1.0</button>
                                <button type="button" class="word-tool-btn" wire:click="loadPresetIntoCustomizer('thesis_dikti')" style="padding:2px 8px; font-size:11px;">Skripsi DIKTI 2.0</button>
                                <button type="button" class="word-tool-btn" wire:click="loadPresetIntoCustomizer('abstract_block')" style="padding:2px 8px; font-size:11px;">Abstrak Block</button>
                                <button type="button" class="word-tool-btn" wire:click="loadPresetIntoCustomizer('block_quote')" style="padding:2px 8px; font-size:11px;">Kutipan Langsung</button>
                            </div>
                        </div>

                        @php
                            $pageParas = $this->getPageParagraphs($customParaPage);
                        @endphp
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Pilih Paragraf di Halaman {{ $customParaPage }}:</label>
                            <select wire:change="selectPageParagraphForCustomizer($event.target.value)" class="word-select" style="width:100%; height:32px;">
                                @forelse ($pageParas as $p)
                                    <option value="{{ $p['pageIndex'] }}" {{ $customParaIndex === $p['pageIndex'] ? 'selected' : '' }}>
                                        Paragraf #{{ $p['pageIndex'] + 1 }} ({{ ucfirst($p['block']['type'] ?? 'paragraph') }}): {{ Str::limit(strip_tags($p['block']['text'] ?? ''), 55) }}
                                    </option>
                                @empty
                                    <option value="0">(Belum ada paragraf di halaman ini)</option>
                                @endforelse
                            </select>
                        </div>

                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Sunting Isi Teks Paragraf:</label>
                            <textarea wire:model="customParaText" class="word-textarea" rows="3" style="width:100%; font-size:12px; line-height:1.4; padding:6px; border:1px solid #d1d1d1; border-radius:3px;"></textarea>
                        </div>

                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Perataan Teks (Alignment):</label>
                            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:6px;">
                                <button type="button" class="word-tool-btn {{ $customParaAlign === 'left' ? 'active' : '' }}" wire:click="$set('customParaAlign', 'left')" style="justify-content:center; padding:6px;">⬅️ Kiri</button>
                                <button type="button" class="word-tool-btn {{ $customParaAlign === 'center' ? 'active' : '' }}" wire:click="$set('customParaAlign', 'center')" style="justify-content:center; padding:6px;">↔️ Tengah</button>
                                <button type="button" class="word-tool-btn {{ $customParaAlign === 'right' ? 'active' : '' }}" wire:click="$set('customParaAlign', 'right')" style="justify-content:center; padding:6px;">➡️ Kanan</button>
                                <button type="button" class="word-tool-btn {{ $customParaAlign === 'justify' ? 'active' : '' }}" wire:click="$set('customParaAlign', 'justify')" style="justify-content:center; padding:6px;">📶 Justify</button>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Jarak Baris:</label>
                                <select wire:model="customParaLineSpacing" class="word-select" style="width:100%; height:30px; font-size:11px;">
                                    <option value="1.0">1.0 (Single)</option>
                                    <option value="1.15">1.15</option>
                                    <option value="1.5">1.5 (Academic 1.5)</option>
                                    <option value="2.0">2.0 (Double)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Spasi Sebelum (pt):</label>
                                <input type="number" min="0" max="72" step="2" wire:model="customParaSpacingBefore" class="word-input" style="width:100%; height:30px; font-size:11px;">
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Spasi Sesudah (pt):</label>
                                <input type="number" min="0" max="72" step="2" wire:model="customParaSpacingAfter" class="word-input" style="width:100%; height:30px; font-size:11px;">
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Indentasi Kiri (Level):</label>
                                <input type="number" min="0" max="8" wire:model="customParaIndent" class="word-input" style="width:100%; height:30px; font-size:11px;">
                            </div>
                            <div style="display:flex; align-items:center; margin-top:18px;">
                                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" wire:model="customParaFirstLineIndent">
                                    Paragraf Menjorok (1.27cm)
                                </label>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px;">
                            <div style="display:flex; align-items:center;">
                                <label style="display:flex; align-items:center; gap:6px; font-size:11px; font-weight:600; cursor:pointer;">
                                    <input type="checkbox" wire:model="customParaDropCap">
                                    Gaya Drop Cap (Huruf Besar)
                                </label>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Tipe Blok:</label>
                                <select wire:model="customParaType" class="word-select" style="width:100%; height:30px; font-size:11px;">
                                    <option value="paragraph">Paragraf Standar</option>
                                    <option value="quote">Kutipan (Blockquote)</option>
                                    <option value="callout">Kotak Catatan (Callout)</option>
                                    <option value="bullet">Daftar Poin (Bullet)</option>
                                    <option value="numbered">Daftar Bernomor (Numbered)</option>
                                </select>
                            </div>
                        </div>

                        <!-- Warna Latar & Garis Tepi -->
                        <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:8px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Warna Latar (Shading):</label>
                                <select wire:model="customParaBgColor" class="word-select" style="width:100%; height:30px; font-size:11px;">
                                    <option value="">(Transparan)</option>
                                    <option value="#f8fafc">Abu-abu Lembut (#f8fafc)</option>
                                    <option value="#eff6ff">Biru Muda (#eff6ff)</option>
                                    <option value="#f0fdf4">Hijau Muda (#f0fdf4)</option>
                                    <option value="#fefce8">Kuning Lembut (#fefce8)</option>
                                    <option value="#faf5ff">Ungu Muda (#faf5ff)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Aksen Garis (Border):</label>
                                <select wire:model="customParaBorderColor" class="word-select" style="width:100%; height:30px; font-size:11px;">
                                    <option value="">(Tanpa Garis)</option>
                                    <option value="#0f6cbd">Biru Royal (#0f6cbd)</option>
                                    <option value="#16a34a">Hijau Zamrud (#16a34a)</option>
                                    <option value="#d97706">Emas / Amber (#d97706)</option>
                                    <option value="#dc2626">Merah (#dc2626)</option>
                                    <option value="#64748b">Abu-abu (#64748b)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:11px; margin-bottom:4px;">Indentasi Kanan (Level):</label>
                                <input type="number" min="0" max="8" wire:model="customParaRightIndent" class="word-input" style="width:100%; height:30px; font-size:11px;">
                            </div>
                        </div>

                        <!-- Cari & Ganti Khusus Halaman -->
                        <div style="background:#f1f5f9; border:1px solid #cbd5e1; border-radius:4px; padding:8px 10px;">
                            <label style="display:block; font-weight:700; font-size:11px; color:#334155; margin-bottom:6px;">🔍 Cari &amp; Ganti Teks Khusus Halaman {{ $customParaPage }}:</label>
                            <div style="display:grid; grid-template-columns:1fr 1fr auto; gap:6px; align-items:center;">
                                <input type="text" wire:model="pageSearchQuery" placeholder="Kata dicari..." class="word-input" style="height:28px; font-size:11px;">
                                <input type="text" wire:model="pageReplaceQuery" placeholder="Pengganti..." class="word-input" style="height:28px; font-size:11px;">
                                <button type="button" class="word-tool-btn" wire:click="findAndReplaceOnPage({{ $customParaPage }})" style="padding:4px 10px; height:28px; background:#0f6cbd; color:#ffffff; font-weight:600; font-size:11px;">Ganti</button>
                            </div>
                        </div>

                        <!-- Alat Manipulasi Paragraf -->
                        <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px; padding:8px 10px; display:flex; flex-direction:column; gap:6px;">
                            <label style="display:block; font-weight:700; font-size:11px; color:#475569;">🛠️ Alat Manipulasi Paragraf:</label>
                            <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                <button type="button" class="word-tool-btn" wire:click="movePageParagraph({{ $customParaPage }}, {{ $customParaIndex }}, -1)" style="padding:2px 8px; font-size:11px;" title="Geser naik posisi paragraf pada halaman ini">⬆️ Geser Naik</button>
                                <button type="button" class="word-tool-btn" wire:click="movePageParagraph({{ $customParaPage }}, {{ $customParaIndex }}, 1)" style="padding:2px 8px; font-size:11px;" title="Geser turun posisi paragraf pada halaman ini">⬇️ Geser Turun</button>
                                <button type="button" class="word-tool-btn" wire:click="duplicatePageParagraph({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:11px;" title="Duplikasi paragraf ini">📄 Duplikat</button>
                                <button type="button" class="word-tool-btn" wire:click="splitPageParagraph({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:11px;" title="Pecah paragraf panjang menjadi dua alinea">✂️ Pecah Paragraf</button>
                                <button type="button" class="word-tool-btn" wire:click="mergePageParagraphs({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:11px;" title="Gabungkan dengan paragraf berikutnya">🔗 Gabung Berikutnya</button>
                                <button type="button" class="word-tool-btn" wire:click="cleanPageParagraphSpaces({{ $customParaPage }})" style="padding:2px 8px; font-size:11px;" title="Rapikan spasi berlebih pada halaman ini">🧹 Rapikan Spasi</button>
                                <button type="button" class="word-tool-btn" wire:click="transformPageParagraphCase({{ $customParaPage }}, 'sentence')" style="padding:2px 8px; font-size:11px;" title="Ubah ke Sentence Case">🔤 Sentence Case</button>
                                <button type="button" class="word-tool-btn" wire:click="deletePageParagraph({{ $customParaPage }}, {{ $customParaIndex }})" style="padding:2px 8px; font-size:11px; color:#d13438;" wire:confirm="Hapus paragraf ini dari halaman?" title="Hapus paragraf ini">🗑️ Hapus</button>
                            </div>
                        </div>

                        <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:10px; border-top:1px solid #edebe9; padding-top:10px; flex-wrap:wrap;">
                            <button type="button" class="word-tool-btn" wire:click="closeDialog" style="padding:6px 12px;">Batal</button>
                            <button type="button" class="word-tool-btn" wire:click="applyParagraphCustomizer(true)" style="padding:6px 12px; background:#f3f2f1; font-weight:600;" title="Terapkan perataan, line spacing, dan indentasi ke seluruh paragraf di halaman ini">
                                Terapkan ke Semua Paragraf Hal. {{ $customParaPage }}
                            </button>
                            <button type="button" class="word-tool-btn" wire:click="applyParagraphCustomizer(false)" style="padding:6px 14px; background:#0f6cbd; color:#ffffff; font-weight:700;">
                                Simpan Paragraf Ini
                            </button>
                        </div>
                    </div>

                {{-- PAGE SETUP --}}
                @elseif ($dialog === 'pagesetup')
                    <div class="word-dialog__header">
                        <span>Page Setup, Margins &amp; Spacing</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="display:flex; flex-direction:column; gap:12px;">
                        <!-- Paper Size & Orientation -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Paper Size:</label>
                                <select wire:change="setPaperSize($event.target.value)" class="word-select" style="width:100%; height:32px;">
                                    <option value="A4" {{ ($pageSetup['size'] ?? '') === 'A4' ? 'selected' : '' }}>A4 (210 x 297 mm)</option>
                                    <option value="Letter" {{ ($pageSetup['size'] ?? '') === 'Letter' ? 'selected' : '' }}>Letter (8.5 x 11 in)</option>
                                    <option value="Legal" {{ ($pageSetup['size'] ?? '') === 'Legal' ? 'selected' : '' }}>Legal (8.5 x 14 in)</option>
                                    <option value="B5" {{ ($pageSetup['size'] ?? '') === 'B5' ? 'selected' : '' }}>B5 (Academic Book)</option>
                                    <option value="A5" {{ ($pageSetup['size'] ?? '') === 'A5' ? 'selected' : '' }}>A5</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Orientation:</label>
                                <select wire:change="setOrientation($event.target.value)" class="word-select" style="width:100%; height:32px;">
                                    <option value="portrait" {{ ($pageSetup['orientation'] ?? '') === 'portrait' ? 'selected' : '' }}>Portrait</option>
                                    <option value="landscape" {{ ($pageSetup['orientation'] ?? '') === 'landscape' ? 'selected' : '' }}>Landscape</option>
                                </select>
                            </div>
                        </div>

                        <!-- Margins Presets -->
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:6px;">Margin Presets:</label>
                            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:6px;">
                                <button type="button" class="word-tool-btn" wire:click="setMarginsPreset('normal')" style="justify-content:center; text-align:center; padding:6px 4px; font-size:11px;">
                                    Normal (1 in)
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="setMarginsPreset('dikti')" style="justify-content:center; text-align:center; padding:6px 4px; font-size:11px; font-weight:700; color:#0f6cbd;">
                                    DIKTI (4-4-3-3)
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="setMarginsPreset('narrow')" style="justify-content:center; text-align:center; padding:6px 4px; font-size:11px;">
                                    Narrow (12.7mm)
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="setMarginsPreset('moderate')" style="justify-content:center; text-align:center; padding:6px 4px; font-size:11px;">
                                    Moderate
                                </button>
                            </div>
                        </div>

                        <!-- Direct Margins (Top, Bottom, Left, Right in mm) -->
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Custom Margins (mm):</label>
                            <div style="display:grid; grid-template-columns:repeat(4, 1fr); gap:6px;">
                                <div>
                                    <span style="font-size:10px; color:var(--ms-text-muted);">Top (Atas):</span>
                                    <input type="number" min="10" max="60" class="word-select" style="width:100%; height:28px;" wire:model="pageSetup.marginTop">
                                </div>
                                <div>
                                    <span style="font-size:10px; color:var(--ms-text-muted);">Bottom (Bawah):</span>
                                    <input type="number" min="10" max="60" class="word-select" style="width:100%; height:28px;" wire:model="pageSetup.marginBottom">
                                </div>
                                <div>
                                    <span style="font-size:10px; color:var(--ms-text-muted);">Left (Kiri):</span>
                                    <input type="number" min="10" max="60" class="word-select" style="width:100%; height:28px;" wire:model="pageSetup.marginLeft">
                                </div>
                                <div>
                                    <span style="font-size:10px; color:var(--ms-text-muted);">Right (Kanan):</span>
                                    <input type="number" min="10" max="60" class="word-select" style="width:100%; height:28px;" wire:model="pageSetup.marginRight">
                                </div>
                            </div>
                        </div>

                        <!-- Line Spacing & Watermark -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Line Spacing (Jarak Baris):</label>
                                <select wire:change="setLineSpacing($event.target.value)" class="word-select" style="width:100%; height:32px;">
                                    <option value="1.0" {{ ($pageSetup['lineSpacing'] ?? 1.5) == 1.0 ? 'selected' : '' }}>1.0 (Single)</option>
                                    <option value="1.15" {{ ($pageSetup['lineSpacing'] ?? 1.5) == 1.15 ? 'selected' : '' }}>1.15 (Normal Word)</option>
                                    <option value="1.5" {{ ($pageSetup['lineSpacing'] ?? 1.5) == 1.5 ? 'selected' : '' }}>1.5 (Academic / DIKTI)</option>
                                    <option value="2.0" {{ ($pageSetup['lineSpacing'] ?? 1.5) == 2.0 ? 'selected' : '' }}>2.0 (Double)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Watermark:</label>
                                <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="pageSetup.watermark" placeholder="e.g. DRAFT / RAHASIA">
                            </div>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="applyPageSetup">Apply</button>
                    </div>

                {{-- SPELL CHECK --}}
                @elseif ($dialog === 'spelling')
                    <div class="word-dialog__header">
                        <span>Spelling and Grammar Check</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <p style="font-size:13px; line-height:1.6;">{{ $spellResult ?: 'Spelling and grammar check is complete. Your manuscript complies with standard rules.' }}</p>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="closeDialog">Close</button>
                    </div>

                {{-- THESAURUS --}}
                @elseif ($dialog === 'thesaurus')
                    <div class="word-dialog__header">
                        <span>Thesaurus</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="display:flex; gap:6px;">
                            <input type="text" class="word-select" style="flex:1; height:32px;" wire:model="thesaurusWord" placeholder="Look up word..." wire:keydown.enter="runThesaurus">
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="runThesaurus">Look Up</button>
                        </div>
                        @if ($thesaurusResult)
                            <div style="padding:10px; border:1px solid #e1dfdd; border-radius:4px; font-size:12px; background:#f9f9fb;">
                                <div>{{ $thesaurusResult }}</div>
                                @if (!empty($thesaurusSynonyms))
                                    <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:4px; align-items:center;">
                                        <span style="font-size:11px; color:#605e5c; font-weight:600; width:100%;">Apply to active block:</span>
                                        @foreach ($thesaurusSynonyms as $syn)
                                            <button type="button" class="word-btn-pill" style="font-size:11px; padding:3px 8px; background:#eff6fc; color:#0f6cbd; border:1px solid #c7e0f4; font-weight:700;" wire:click="applyThesaurusSynonym('{{ $syn }}')" title="Apply synonym '{{ $syn }}' to active block">
                                                ✓ {{ $syn }}
                                            </button>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Close</button>
                    </div>

                {{-- INSERT TABLE --}}
                @elseif ($dialog === 'table')
                    <div class="word-dialog__header">
                        <span>Insert Table</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Number of columns:</label>
                                <input type="number" min="1" max="10" class="word-select" style="width:100%; height:32px;" wire:model="tableCols">
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Number of rows:</label>
                                <input type="number" min="1" max="25" class="word-select" style="width:100%; height:32px;" wire:model="tableRows">
                            </div>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Cancel</button>
                        <button type="button" class="word-btn-pill word-btn-share" @click="insertTable($wire.tableRows || 3, $wire.tableCols || 3); $wire.closeDialog()">OK</button>
                    </div>

                {{-- INSERT / EDIT EQUATION (LATEX) --}}
                @elseif ($dialog === 'equation')
                    <div class="word-dialog__header">
                        <span style="display:flex; align-items:center; gap:6px;">
                            <span style="font-family:'Cambria Math', serif; font-size:16px; font-weight:700;">π</span>
                            <span>{{ $editingEquationIndex !== null ? 'Edit Rumus LaTeX' : 'Insert Equation (Rumus Matematika / LaTeX)' }}</span>
                        </span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="display:flex; flex-direction:column; gap:12px;">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:6px; color:var(--ms-text-primary);">Pilih Templat Cepat (Presets):</label>
                            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('quadratic')">Rumus ABC</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('pythagoras')">Pythagoras</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('gaussian')">Distribusi Normal</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('integral')">Integral Tentu</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('matrix')">Matriks 2x2</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('euler')">Identitas Euler (e^iπ+1=0)</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('regression')">Regresi Berganda</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('einstein')">Massa-Energi (E=mc²)</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('taylor')">Deret Taylor</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('limit')">Limit</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('summation')">Sigma Deret</button>
                                <button type="button" class="word-tool-btn" style="font-size:11px; padding:3px 8px;" wire:click="setEquationPreset('fourier')">Transformasi Fourier</button>
                            </div>
                        </div>

                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px; color:var(--ms-text-primary);">Sintaks Rumus LaTeX:</label>
                            <textarea rows="3" class="word-select" style="width:100%; height:auto; font-family:'JetBrains Mono', monospace; font-size:12px; line-height:1.4;" wire:model.live.debounce.300ms="equationLatex" placeholder="Contoh: x = \frac{-b \pm \sqrt{b^2 - 4ac}}{2a}"></textarea>
                            <span style="font-size:10px; color:var(--ms-text-muted); margin-top:2px; display:block;">Dukungan penuh sintaks LaTeX: pecahan (\frac), integral (\int), akar (\sqrt), matriks, huruf Yunani (\alpha, \beta, \pi).</span>
                        </div>

                        <!-- Palet Simbol Matematika Cepat Berdasarkan Kategori -->
                        <div style="display:flex; flex-direction:column; gap:6px;">
                            <label style="display:block; font-weight:600; font-size:11px; color:var(--ms-text-secondary);">Karakter &amp; Operator Cepat (Klik untuk Sisipkan):</label>

                            <!-- Baris 1: Operator & Relasi Dasar -->
                            <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap; background:#faf9f8; padding:5px; border:1px solid #e1dfdd; border-radius:3px;">
                                <span style="font-size:10px; font-weight:700; color:#64748b; margin-right:4px;">OPERATOR:</span>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\pm')">±</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\times')">×</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\div')">÷</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\neq')">≠</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\approx')">≈</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\le')">≤</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\ge')">≥</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\infty')">∞</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\propto')">∝</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\cdot')">·</button>
                            </div>

                            <!-- Baris 2: Struktur & Kalkulus -->
                            <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap; background:#faf9f8; padding:5px; border:1px solid #e1dfdd; border-radius:3px;">
                                <span style="font-size:10px; font-weight:700; color:#64748b; margin-right:4px;">KALKULUS:</span>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\frac{a}{b}')">a/b</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\sqrt{x}')">√x</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\int')">∫</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\sum')">∑</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\prod')">∏</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\lim_{x \\to 0}')">lim</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\partial')">∂</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\nabla')">∇</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\to')">→</button>
                            </div>

                            <!-- Baris 3: Huruf Yunani & Matriks -->
                            <div style="display:flex; align-items:center; gap:4px; flex-wrap:wrap; background:#faf9f8; padding:5px; border:1px solid #e1dfdd; border-radius:3px;">
                                <span style="font-size:10px; font-weight:700; color:#64748b; margin-right:4px;">YUNANI/MATRIKS:</span>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\alpha')">α</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\beta')">β</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\theta')">θ</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\lambda')">λ</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\mu')">μ</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\pi')">π</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\sigma')">σ</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\Delta')">Δ</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\begin{bmatrix} a & b \\\\ c & d \\end{bmatrix}')">[Matrix]</button>
                                <button type="button" class="word-tool-btn" style="min-width:24px; height:22px; font-size:11px; font-weight:600;" wire:click="appendEquationSymbol('\\vec{v}')">v⃗</button>
                            </div>
                        </div>

                        <!-- Live Preview Box with Real-time Client-Side KaTeX update -->
                        <div x-data="{
                            previewLatex: @entangle('equationLatex'),
                            updateMath() {
                                if (this.$refs.mathBox && typeof window.katex !== 'undefined' && this.previewLatex) {
                                    try {
                                        window.katex.render(this.previewLatex, this.$refs.mathBox, { throwOnError: false, displayMode: true });
                                        return;
                                    } catch(e) {}
                                }
                            }
                        }" x-init="$nextTick(() => updateMath()); $watch('previewLatex', () => $nextTick(() => updateMath()))"
                        style="background:#f8fafc; border:1px solid #cbd5e1; border-radius:4px; padding:12px; text-align:center;">
                            <div style="font-size:11px; font-weight:600; color:#64748b; margin-bottom:6px; text-transform:uppercase; letter-spacing:0.5px;">Pratinjau Rumus (Live Math)</div>
                            <div x-ref="mathBox" style="font-family:'Cambria Math', 'KaTeX_Math', 'Times New Roman', serif; font-size:1.25rem; color:#0f172a; min-height:36px; display:flex; align-items:center; justify-content:center;">
                                <span class="latex-math-render" data-latex="{{ $equationLatex }}">{{ $equationLatex ?: 'E = mc^2' }}</span>
                            </div>
                        </div>

                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px; color:var(--ms-text-primary);">Nomor / Keterangan Persamaan:</label>
                                <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="equationCaption" placeholder="Misal: (1) atau Eq. 2.4">
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px; color:var(--ms-text-primary);">Tipe Tampilan:</label>
                                <select class="word-select" style="width:100%; height:32px;" wire:model="equationDisplay">
                                    <option value="block">Blok Mandiri (Tengah)</option>
                                    <option value="inline">Inline (Sejajar Teks)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="word-dialog__footer" style="display:flex; justify-content:space-between;">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Batal</button>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="word-btn-pill" wire:click="insertInlineEquation" title="Sisipkan sebagai rumus sebaris ($)">
                                Sisipkan Inline ($)
                            </button>
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="insertEquation">
                                {{ $editingEquationIndex !== null ? 'Perbarui Rumus' : 'Sisipkan Blok Persamaan' }}
                            </button>
                        </div>
                    </div>

                {{-- INSERT / EDIT IMAGE --}}
                @elseif ($dialog === 'image')
                    <div class="word-dialog__header">
                        <span>{{ $editingImageIndex !== null ? 'Edit Gambar & Diagram Ilmiah' : 'Insert Picture & Academic Diagram' }}</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="display:flex; flex-direction:column; gap:14px;">
                        <!-- Opsi 1: Unggah Berkas -->
                        <div style="padding:10px 12px; border:1px solid #e1dfdd; border-radius:4px; background:#faf9f8;">
                            <label style="display:block; font-weight:700; font-size:12px; margin-bottom:4px; color:#0f6cbd;">1. Unggah dari Komputer:</label>
                            <input type="file" wire:model="imageUpload" accept="image/*" class="word-select" style="width:100%; height:34px; padding:3px; background:#fff;">
                            <span style="font-size:10px; color:#616161; margin-top:3px; display:block;">Mendukung berkas JPG, PNG, WebP, GIF, SVG.</span>
                        </div>

                        <!-- Opsi 2: Sisipkan via URL -->
                        <div style="padding:10px 12px; border:1px solid #e1dfdd; border-radius:4px; background:#faf9f8;">
                            <label style="display:block; font-weight:700; font-size:12px; margin-bottom:4px; color:#0f6cbd;">2. Gambar dari Tautan URL / Data URI:</label>
                            <div style="display:flex; gap:6px;">
                                <input type="url" class="word-select" style="flex:1; height:32px; background:#fff;" wire:model="imageUrl" placeholder="https://contoh.com/gambar-grafik.png">
                                <button type="button" class="word-btn-pill word-btn-share" style="height:32px; font-size:11px;" wire:click="insertImageUrl">
                                    {{ $editingImageIndex !== null ? 'Terapkan URL' : 'Sisipkan URL' }}
                                </button>
                            </div>
                        </div>

                        <!-- Opsi 3: Diagram Cepat Siap Pakai -->
                        <div style="padding:10px 12px; border:1px solid #e1dfdd; border-radius:4px; background:#faf9f8;">
                            <label style="display:block; font-weight:700; font-size:12px; margin-bottom:6px; color:#0f6cbd;">3. Diagram Akademik Cepat (1-Klik):</label>
                            <div style="display:flex; flex-wrap:wrap; gap:8px;">
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('flowchart')">
                                    📊 Flowchart Metodologi
                                </button>
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('barchart')">
                                    📈 Grafik Komparasi Model
                                </button>
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('mindmap')">
                                    🧠 Kerangka Konseptual
                                </button>
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('venn')">
                                    ⭕ Diagram Venn
                                </button>
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('timeline')">
                                    ⏱️ Timeline Riset
                                </button>
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('piechart')">
                                    🥧 Pie Chart Demografi
                                </button>
                                <button type="button" class="word-tool-btn" style="padding:4px 8px; font-size:11px;" wire:click="insertSampleDiagram('architecture')">
                                    🏛️ Arsitektur Pipeline
                                </button>
                            </div>
                        </div>

                        <!-- Pengaturan Ukuran & Keterangan Gambar -->
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Keterangan Gambar (Caption):</label>
                                <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="imageCaption" placeholder="Gambar 1: Deskripsi gambar...">
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Lebar Tampilan:</label>
                                <select class="word-select" style="width:100%; height:32px;" wire:model="imageWidth">
                                    <option value="320">Kecil (320 px)</option>
                                    <option value="480">Sedang (480 px - Standar)</option>
                                    <option value="600">Lebar (600 px)</option>
                                    <option value="720">Penuh (720 px)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="word-dialog__footer" style="display:flex; justify-content:space-between;">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Tutup</button>
                        @if ($editingImageIndex !== null)
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="updateImageProperties">Simpan Perubahan</button>
                        @endif
                    </div>

                {{-- CROSS-REFERENCE DIALOG --}}
                @elseif ($dialog === 'crossref')
                    @php
                        $caps = $this->getManuscriptCaptions();
                    @endphp
                    <div class="word-dialog__header">
                        <span>Cross-Reference (Rujukan Silang Akademik)</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="display:flex; flex-direction:column; gap:12px; max-height:420px; overflow-y:auto;">
                        <div style="font-size:12px; color:var(--ms-text-secondary);">
                            Pilih elemen dokumen untuk disisipkan sebagai rujukan silang ke posisi kursor / blok aktif:
                        </div>

                        <!-- Equations -->
                        <div>
                            <label style="display:block; font-weight:700; font-size:12px; color:#0f6cbd; margin-bottom:4px;">Persamaan Matematika (Equations):</label>
                            @if (empty($caps['equations']))
                                <div style="font-size:11px; color:#8a8886; font-style:italic; padding:6px; background:#f9f9fb; border-radius:3px;">Belum ada rumus persamaan dalam naskah.</div>
                            @else
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    @foreach ($caps['equations'] as $eq)
                                        <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">
                                            <span style="font-family:'Cambria Math', monospace; font-size:12px;">Persamaan ({{ trim($eq['caption'], '()') }}): <span style="color:#64748b;">{{ \Illuminate\Support\Str::limit($eq['preview'], 35) }}</span></span>
                                            <button type="button" class="word-btn-pill word-btn-share" style="padding:2px 8px; font-size:11px; height:24px;" wire:click="insertCrossReference('equation', '{{ trim($eq['caption'], '()') }}')">Sisipkan</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Figures / Images -->
                        <div>
                            <label style="display:block; font-weight:700; font-size:12px; color:#0f6cbd; margin-bottom:4px;">Gambar &amp; Diagram (Figures):</label>
                            @if (empty($caps['images']))
                                <div style="font-size:11px; color:#8a8886; font-style:italic; padding:6px; background:#f9f9fb; border-radius:3px;">Belum ada gambar dalam naskah.</div>
                            @else
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    @foreach ($caps['images'] as $img)
                                        <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">
                                            <span style="font-size:12px;">{{ \Illuminate\Support\Str::limit($img['caption'], 45) }}</span>
                                            <button type="button" class="word-btn-pill word-btn-share" style="padding:2px 8px; font-size:11px; height:24px;" wire:click="insertCrossReference('image', '{{ $img['caption'] }}')">Sisipkan</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Tables -->
                        <div>
                            <label style="display:block; font-weight:700; font-size:12px; color:#0f6cbd; margin-bottom:4px;">Tabel Data:</label>
                            @if (empty($caps['tables']))
                                <div style="font-size:11px; color:#8a8886; font-style:italic; padding:6px; background:#f9f9fb; border-radius:3px;">Belum ada tabel dalam naskah.</div>
                            @else
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    @foreach ($caps['tables'] as $tb)
                                        <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">
                                            <span style="font-size:12px;">{{ $tb['caption'] }}</span>
                                            <button type="button" class="word-btn-pill word-btn-share" style="padding:2px 8px; font-size:11px; height:24px;" wire:click="insertCrossReference('table', '{{ $tb['caption'] }}')">Sisipkan</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <!-- Headings / Sections -->
                        <div>
                            <label style="display:block; font-weight:700; font-size:12px; color:#0f6cbd; margin-bottom:4px;">Bagian &amp; Bab Dokumen (Headings):</label>
                            @if (empty($caps['headings']))
                                <div style="font-size:11px; color:#8a8886; font-style:italic; padding:6px; background:#f9f9fb; border-radius:3px;">Belum ada judul/bab dalam naskah.</div>
                            @else
                                <div style="display:flex; flex-direction:column; gap:4px;">
                                    @foreach ($caps['headings'] as $hd)
                                        <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 10px; background:#f8fafc; border:1px solid #e2e8f0; border-radius:4px;">
                                            <span style="font-size:12px;">H{{ $hd['level'] }}: {{ \Illuminate\Support\Str::limit($hd['caption'], 45) }}</span>
                                            <button type="button" class="word-btn-pill word-btn-share" style="padding:2px 8px; font-size:11px; height:24px;" wire:click="insertCrossReference('heading', '{{ $hd['caption'] }}')">Sisipkan</button>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- INSERT LINK --}}
                @elseif ($dialog === 'link')
                    <div class="word-dialog__header">
                        <span>Insert Hyperlink</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Address (URL):</label>
                            <input type="url" class="word-select" style="width:100%; height:32px;" wire:model="linkUrl" placeholder="https://">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Text to display:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="linkText" placeholder="Link text...">
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Cancel</button>
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="insertLink">OK</button>
                    </div>

                {{-- FOOTNOTE --}}
                @elseif ($dialog === 'footnote')
                    <div class="word-dialog__header">
                        <span>Insert Footnote</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Footnote text:</label>
                            <textarea rows="3" class="word-select" style="width:100%; height:auto;" wire:model="footnoteText" placeholder="Type footnote..."></textarea>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Cancel</button>
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="insertFootnote">Insert</button>
                    </div>

                {{-- ENDNOTE --}}
                @elseif ($dialog === 'endnote')
                    <div class="word-dialog__header">
                        <span>Insert Endnote</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Endnote text (placed before references):</label>
                            <textarea rows="3" class="word-select" style="width:100%; height:auto;" wire:model="endnoteText" placeholder="Type endnote..."></textarea>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Cancel</button>
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="insertEndnote">Insert</button>
                    </div>

                {{-- SOURCES MANAGER (CITATION MANAGER) --}}
                @elseif ($dialog === 'sources')
                    <div class="word-dialog__header">
                        <span>Source Manager &amp; Citations</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="font-size:12px; color:var(--ms-text-secondary); margin-bottom:10px;">
                            Pilih sumber pustaka untuk disisipkan sebagai acuan sitasi naskah (Gaya: <strong>{{ $citationStyle ?? 'APA' }}</strong>):
                        </div>
                        <div style="display:flex; flex-direction:column; gap:8px;">
                            @if (isset($projectReferences) && $projectReferences->isNotEmpty())
                                @foreach ($projectReferences as $pRef)
                                    <div style="padding:10px 12px; border:1px solid #e1dfdd; border-radius:4px; display:flex; justify-content:space-between; align-items:center; background:#faf9f8;">
                                        <div>
                                            <div style="font-weight:600; font-size:12px; color:#0f6cbd;">{{ $pRef->authors[0]['name'] ?? ($pRef->citation_key ?? 'Penulis') }} ({{ $pRef->year ?? '-' }})</div>
                                            <div style="font-size:11px; color:#424242;">{{ $pRef->title }}</div>
                                            <div style="font-size:10px; color:#8a8886;">{{ $pRef->publication ?? $pRef->doi }}</div>
                                        </div>
                                        <button type="button" class="word-tool-btn" style="color:#0f6cbd; border-color:#0f6cbd; font-weight:600; font-size:11px;" wire:click="insertReferenceCitation({{ $pRef->id }})">
                                            + Sisipkan Sitasi
                                        </button>
                                    </div>
                                @endforeach
                            @else
                                @php
                                    $sampleSources = [
                                        ['author' => 'Anderson, C.', 'year' => '2020', 'title' => 'Makers: The New Industrial Revolution and Shared Tool Collectives', 'publisher' => 'Crown Business Publishing', 'key' => '(Anderson, 2020)'],
                                        ['author' => 'Gauntlett, D.', 'year' => '2018', 'title' => 'Making is Connecting: The Social Power of Shared Craft Studios', 'publisher' => 'Polity Press', 'key' => '(Gauntlett, 2018)'],
                                        ['author' => 'Kafai, Y. B., & Peppler, K. A.', 'year' => '2021', 'title' => 'Youth, Technology, and DIY Authoring in Collaborative Workshops', 'publisher' => 'MIT Press', 'key' => '(Kafai & Peppler, 2021)'],
                                        ['author' => 'Sennett, R.', 'year' => '2019', 'title' => 'The Craftsman: Material Culture, Guild Cooperation, and Studio Ethics', 'publisher' => 'Yale University Press', 'key' => '(Sennett, 2019)'],
                                    ];
                                @endphp
                                @foreach ($sampleSources as $src)
                                    <div style="padding:10px 12px; border:1px solid #e1dfdd; border-radius:4px; display:flex; justify-content:space-between; align-items:center; background:#faf9f8;">
                                        <div>
                                            <div style="font-weight:600; font-size:12px; color:#0f6cbd;">{{ $src['author'] }} ({{ $src['year'] }})</div>
                                            <div style="font-size:11px; color:#424242;">{{ $src['title'] }}</div>
                                            <div style="font-size:10px; color:#8a8886;">{{ $src['publisher'] }}</div>
                                        </div>
                                        <button type="button" class="word-tool-btn" style="color:#0f6cbd; border-color:#0f6cbd; font-weight:600; font-size:11px;" wire:click="insertCitation('{{ $src['key'] }}')">
                                            + Sisipkan Sitasi
                                        </button>
                                    </div>
                                @endforeach
                            @endif
                        </div>
                    </div>
                    <div class="word-dialog__footer" style="display:flex; justify-content:space-between;">
                        <button type="button" class="word-tool-btn" wire:click="insertBibliography">
                            📜 Buat Daftar Pustaka Lengkap
                        </button>
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- SYMBOLS --}}
                @elseif ($dialog === 'symbols')
                    <div class="word-dialog__header">
                        <span>Insert Symbol</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="display:grid; grid-template-columns:repeat(8, 1fr); gap:4px; font-size:16px; text-align:center;">
                            @foreach (['©', '®', '™', '§', '¶', '±', '×', '÷', '≠', '≤', '≥', '≈', '∞', '°', 'µ', 'α', 'β', 'γ', 'π', '∑', '√', '—', '–', '•', '€', '£', '¥', '→'] as $sym)
                                <button type="button" class="word-btn-square" style="width:100%; height:32px;" wire:click="insertSymbol('{{ $sym }}')">
                                    {{ $sym }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Close</button>
                    </div>

                {{-- SHARE & EXPORT DIALOG --}}
                @elseif ($dialog === 'share')
                    <div class="word-dialog__header">
                        <div style="display:flex; align-items:center; gap:8px;">
                            <div style="width:24px; height:24px; border-radius:4px; background:#0f6cbd; color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:12px; font-weight:700;">W</div>
                            <span style="font-weight:700; font-size:14px;">Share — {{ $docTitle }}</span>
                        </div>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body" style="display:flex; flex-direction:column; gap:16px;">
                        <!-- Share Link Box -->
                        <div x-data="{ copied: false }">
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:6px; color:var(--ms-text-primary);">Tautan Bagikan Dokumen (Share Link):</label>
                            <div style="display:flex; gap:6px;">
                                <input type="text" readonly :value="window.location.href" class="word-select" style="flex:1; height:34px; padding:0 8px; font-family:var(--ms-font-mono); font-size:11.5px; background:var(--ms-bg-subtle, #f3f2f1);" id="share-link-input">
                                <button type="button" class="word-btn-pill word-btn-share"
                                    @click="
                                        navigator.clipboard.writeText(window.location.href);
                                        copied = true;
                                        setTimeout(() => copied = false, 2500);
                                    "
                                    style="min-width:90px; height:34px;">
                                    <span x-show="!copied">📋 Salin Tautan</span>
                                    <span x-show="copied" style="color:#ffffff;">✓ Tersalin!</span>
                                </button>
                            </div>
                            <div style="font-size:11px; color:var(--ms-text-muted); margin-top:4px;">
                                Siapa pun yang memiliki tautan dapat mengakses naskah ini.
                            </div>
                        </div>

                        <!-- Direct Export & Download Options -->
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:8px; color:var(--ms-text-primary);">Ekspor &amp; Unduh Cepat:</label>
                            <div style="display:grid; grid-template-columns:repeat(3, 1fr); gap:8px;">
                                <button type="button" class="word-tool-btn" wire:click="export('docx')" style="justify-content:center; padding:10px 8px; flex-direction:column; gap:4px; height:auto; border:1px solid var(--ms-border);">
                                    <span style="font-weight:800; color:#0f6cbd; font-size:14px;">DOCX</span>
                                    <span style="font-size:11px;">Microsoft Word</span>
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="export('pdf')" style="justify-content:center; padding:10px 8px; flex-direction:column; gap:4px; height:auto; border:1px solid var(--ms-border);">
                                    <span style="font-weight:800; color:#c50f1f; font-size:14px;">PDF</span>
                                    <span style="font-size:11px;">Dokumen Cetak</span>
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="export('odt')" style="justify-content:center; padding:10px 8px; flex-direction:column; gap:4px; height:auto; border:1px solid var(--ms-border);">
                                    <span style="font-weight:800; color:#107c41; font-size:14px;">ODT</span>
                                    <span style="font-size:11px;">LibreOffice Writer</span>
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="export('latex')" style="justify-content:center; padding:10px 8px; flex-direction:column; gap:4px; height:auto; border:1px solid var(--ms-border);">
                                    <span style="font-weight:800; color:#7c2d12; font-size:14px;">LaTeX</span>
                                    <span style="font-size:11px;">Artikel Ilmiah</span>
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="export('md')" style="justify-content:center; padding:10px 8px; flex-direction:column; gap:4px; height:auto; border:1px solid var(--ms-border);">
                                    <span style="font-weight:800; color:#475569; font-size:14px;">MD</span>
                                    <span style="font-size:11px;">Markdown</span>
                                </button>
                                <button type="button" class="word-tool-btn" wire:click="export('rtf')" style="justify-content:center; padding:10px 8px; flex-direction:column; gap:4px; height:auto; border:1px solid var(--ms-border);">
                                    <span style="font-weight:800; color:#0891b2; font-size:14px;">RTF</span>
                                    <span style="font-size:11px;">Rich Text Format</span>
                                </button>
                            </div>
                        </div>

                        <!-- Collaborators List -->
                        <div style="border-top:1px solid var(--ms-border-subtle); padding-top:12px;">
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:8px; color:var(--ms-text-primary);">Kolaborator &amp; Penulis:</label>
                            <div style="display:flex; flex-direction:column; gap:6px;">
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 8px; background:var(--ms-bg-subtle, #f9f9fb); border-radius:4px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div class="word-avatar" style="width:24px; height:24px; font-size:11px;">WU</div>
                                        <div>
                                            <div style="font-size:12px; font-weight:600; color:var(--ms-text-primary);">Penulis (Anda)</div>
                                            <div style="font-size:10px; color:var(--ms-text-muted);">Pemilik Dokumen</div>
                                        </div>
                                    </div>
                                    <span style="font-size:11px; color:#107c41; font-weight:600;">Aktif</span>
                                </div>
                                <div style="display:flex; align-items:center; justify-content:space-between; padding:6px 8px; background:var(--ms-bg-subtle, #f9f9fb); border-radius:4px;">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <div style="width:24px; height:24px; border-radius:9999px; background:#0f6cbd; color:#ffffff; display:flex; align-items:center; justify-content:center; font-size:11px; font-weight:700;">AI</div>
                                        <div>
                                            <div style="font-size:12px; font-weight:600; color:var(--ms-text-primary);">Xishie Wordcraft Assistant</div>
                                            <div style="font-size:10px; color:var(--ms-text-muted);">Copilot &amp; Proofreader</div>
                                        </div>
                                    </div>
                                    <span style="font-size:11px; color:#0f6cbd; font-weight:600;">Tersedia</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Tutup</button>
                    </div>

                {{-- MAIL MERGE CSV --}}
                @elseif ($dialog === 'merge')
                    <div class="word-dialog__header">
                        <span>Mail Merge (CSV)</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">CSV Data (Row 1 = Headers):</label>
                            <textarea rows="4" class="word-select" style="width:100%; height:auto; font-family:var(--ms-font-mono); font-size:11px;" wire:model.live="mergeCsv" placeholder="name,organization&#10;Alice,Alder Studios&#10;Bob,Makers Guild"></textarea>
                        </div>
                        @php $detectedHeaders = $this->getMergeHeaders(); @endphp
                        @if (!empty($detectedHeaders))
                            <div style="display:flex; flex-wrap:wrap; gap:4px; align-items:center; margin:6px 0;">
                                <span style="font-size:11px; color:#475569; font-weight:600;">Insert Tags:</span>
                                @foreach ($detectedHeaders as $h)
                                    <button type="button" class="word-tool-btn" style="font-size:10.5px; padding:1px 6px; height:20px;" wire:click="insertMergeField('{{ $h }}')">+ &#123;&#123;{{ $h }}&#125;&#125;</button>
                                @endforeach
                            </div>
                        @endif
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Template (Use @{{name}}):</label>
                            <textarea rows="2" class="word-select" style="width:100%; height:auto;" wire:model="mergeTemplate" placeholder="Dear @{{name}} at @{{organization}}..."></textarea>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="previewMerge">Preview</button>
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="insertMerge">Insert into Document</button>
                    </div>

                {{-- COMMENT --}}
                @elseif ($dialog === 'comment')
                    <div class="word-dialog__header">
                        <span>New Comment on Block #{{ $activeBlock + 1 }}</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <textarea rows="3" class="word-select" style="width:100%; height:auto;" wire:model="commentDraft" placeholder="Type your comment here..."></textarea>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Cancel</button>
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="postCommentAndClose">Post</button>
                    </div>

                {{-- COMMAND PALETTE HUD (CTRL+K) --}}
                @elseif ($dialog === 'palette')
                    <div class="word-dialog__header">
                        <span>Word Commands &amp; Actions (Ctrl+K)</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <input type="text" class="word-select" style="width:100%; height:34px; padding:0 10px;"
                            placeholder="Type a command (e.g., auto-correct, table, heading, dark mode)..."
                            wire:model.live="commandSearch" autofocus>
                        <div style="max-height:260px; overflow-y:auto; display:flex; flex-direction:column; gap:2px;">
                            @php
                                $q = strtolower(trim($commandSearch));
                                $filteredCmds = array_filter($commands, static fn(array $c): bool => $q === '' || str_contains(strtolower($c['label']), $q) || str_contains(strtolower($c['category']), $q) || str_contains(strtolower($c['id']), $q));
                            @endphp
                            @forelse ($filteredCmds as $cmd)
                                <button type="button" class="word-nav-item" style="justify-content:space-between;"
                                    wire:click="executeCommand('{{ $cmd['id'] }}')">
                                    <div style="display:flex; align-items:center; gap:8px;">
                                        <span>{{ $cmd['icon'] }}</span>
                                        <span style="font-weight:600;">{{ $cmd['label'] }}</span>
                                    </div>
                                    @if (!empty($cmd['shortcut']))
                                        <kbd style="padding:1px 5px; border:1px solid #d1d1d1; border-radius:3px; font-size:10px;">{{ $cmd['shortcut'] }}</kbd>
                                    @endif
                                </button>
                            @empty
                                <div style="color:#8a8886; font-size:12px; text-align:center; padding:16px;">No commands found.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Close</button>
                    </div>

                {{-- PROPERTIES --}}
                @elseif ($dialog === 'properties')
                    <div class="word-dialog__header">
                        <span>Document Properties</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Title:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="docTitle" wire:change="markDirty">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Author:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="metadata.author" wire:change="markDirty">
                        </div>
                        <div style="font-size:11px; color:#616161; line-height:1.6; padding-top:6px; border-top:1px solid #edebe9;">
                            <div>• Words: <strong>{{ number_format($wordCount) }}</strong></div>
                            <div>• Characters: <strong>{{ number_format($charCount) }}</strong></div>
                            <div>• Blocks: <strong>{{ $blockCount }}</strong></div>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="saveProperties">Save Properties</button>
                    </div>

                {{-- SNAPSHOTS --}}
                @elseif ($dialog === 'snapshots')
                    <div class="word-dialog__header">
                        <span>Version History &amp; Snapshots</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="display:flex; gap:6px;">
                            <input type="text" class="word-select" style="flex:1; height:32px;" placeholder="Snapshot label..." wire:model="snapshotLabel">
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="createSnapshot">Save Version</button>
                        </div>
                        <div style="max-height:240px; overflow-y:auto; display:flex; flex-direction:column; gap:4px;">
                            @forelse ($snapshots as $sIdx => $snap)
                                <div style="padding:8px 10px; border:1px solid #e1dfdd; border-radius:4px; display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <strong style="font-size:12px;">{{ $snap['label'] }}</strong>
                                        <div style="font-size:10px; color:#8a8886;">{{ $snap['at'] }} · {{ $snap['count'] }} blocks</div>
                                    </div>
                                    <div style="display:flex; gap:4px;">
                                        <button type="button" wire:click="compareSnapshot({{ $sIdx }})" class="word-tool-btn" style="color:#0f766e;" title="Compare version difference">Diff</button>
                                        <button type="button" wire:click="branchSnapshotToDocument({{ $sIdx }})" class="word-tool-btn" style="color:#0284c7;" title="Branch snapshot into new document">Branch</button>
                                        <button type="button" wire:click="restoreSnapshot({{ $sIdx }})" class="word-tool-btn" style="color:#0f6cbd;">Restore</button>
                                        <button type="button" wire:click="deleteSnapshot({{ $sIdx }})" class="word-tool-btn" style="color:#c50f1f;">✕</button>
                                    </div>
                                </div>
                            @empty
                                <div style="color:#8a8886; font-size:12px; text-align:center; padding:16px;">No version snapshots saved.</div>
                            @endforelse
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Close</button>
                    </div>

                {{-- SNAPSHOT DIFF --}}
                @elseif ($dialog === 'snapshot_diff' && $snapshotDiffResult)
                    <div class="word-dialog__header">
                        <span>Version Comparison: {{ $snapshotDiffResult['snapshot_label'] }} vs Active Manuscript</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div style="background:#eff6ff; border:1px solid #bfdbfe; padding:8px 12px; border-radius:4px; margin-bottom:10px; font-size:12px; color:#1e40af;">
                            <strong>{{ $snapshotDiffResult['summary'] }}</strong>
                        </div>
                        @if (!empty($snapshotDiffResult['new_headings']))
                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:6px 10px; border-radius:4px; margin-bottom:8px; font-size:11.5px; color:#166534;">
                                <strong>+ New Headings Added:</strong> {{ implode(', ', $snapshotDiffResult['new_headings']) }}
                            </div>
                        @endif
                        @if (!empty($snapshotDiffResult['removed_headings']))
                            <div style="background:#fef2f2; border:1px solid #fecaca; padding:6px 10px; border-radius:4px; margin-bottom:8px; font-size:11.5px; color:#991b1b;">
                                <strong>- Headings Removed:</strong> {{ implode(', ', $snapshotDiffResult['removed_headings']) }}
                            </div>
                        @endif
                        <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                            <div style="background:#f9f9fb; border:1px solid #e1dfdd; padding:10px; border-radius:4px;">
                                <div style="font-weight:700; font-size:12px; color:#475569; margin-bottom:4px;">Snapshot ({{ $snapshotDiffResult['snapshot_label'] }})</div>
                                <div style="font-size:11px; color:#64748b; margin-bottom:6px;">{{ $snapshotDiffResult['snapshot_at'] }}</div>
                                <div style="font-size:12px;">Words: <strong>{{ number_format($snapshotDiffResult['snapshot_words']) }}</strong></div>
                                <div style="font-size:12px;">Blocks: <strong>{{ $snapshotDiffResult['snapshot_blocks'] }}</strong></div>
                            </div>
                            <div style="background:#f0fdf4; border:1px solid #bbf7d0; padding:10px; border-radius:4px;">
                                <div style="font-weight:700; font-size:12px; color:#15803d; margin-bottom:4px;">Current Active Version</div>
                                <div style="font-size:11px; color:#166534; margin-bottom:6px;">Live Editor Session</div>
                                <div style="font-size:12px;">Words: <strong>{{ number_format($snapshotDiffResult['current_words']) }}</strong> ({{ $snapshotDiffResult['delta_words'] >= 0 ? '+'.$snapshotDiffResult['delta_words'] : $snapshotDiffResult['delta_words'] }})</div>
                                <div style="font-size:12px;">Blocks: <strong>{{ $snapshotDiffResult['current_blocks'] }}</strong> ({{ $snapshotDiffResult['delta_blocks'] >= 0 ? '+'.$snapshotDiffResult['delta_blocks'] : $snapshotDiffResult['delta_blocks'] }})</div>
                            </div>
                        </div>
                    </div>
                    <div class="word-dialog__footer" style="display:flex; justify-content:space-between;">
                        <button type="button" class="word-btn-pill" wire:click="openDialog('snapshots')">&larr; Back to Versions</button>
                        <div style="display:flex; gap:6px;">
                            <button type="button" class="word-btn-pill" wire:click="branchSnapshotToDocument({{ $snapshotDiffResult['snapshot_index'] }})">Branch New Document</button>
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="restoreSnapshot({{ $snapshotDiffResult['snapshot_index'] }})">Restore This Version</button>
                        </div>
                    </div>

                {{-- SHORTCUTS --}}
                @elseif ($dialog === 'shortcuts')
                    <div class="word-dialog__header">
                        <span>Keyboard Shortcuts</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <table style="width:100%; font-size:12px; border-collapse:collapse;">
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Alt + I</td><td>Open / Close Xishie Assistant AI Copilot</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + S</td><td>Save document</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + P</td><td>Print document / Pratinjau Cetak</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Z</td><td>Undo change</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Y</td><td>Redo change</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Shift + F3</td><td>Ubah Huruf Besar/Kecil (lowercase &rarr; Capitalize &rarr; UPPERCASE)</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + K</td><td>Search commands &amp; actions (Command Palette)</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + F / H</td><td>Find and Replace</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Enter</td><td>Insert Page Break (Pemisah Lembar Halaman Fisik)</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Tab / Backspace</td><td>Indentasi Baris Pertama 1.27cm &amp; Reset Indentasi</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Space / M</td><td>Hapus Format Langsung (Clear Direct Formatting)</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Scroll Wheel</td><td>Zoom Kanvas Lembar Halaman (50% &ndash; 200%)</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Klik Kanan</td><td>Menu Konteks Naskah &amp; Alat Tabel Lengkap</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + B / I / U</td><td>Tebal / Miring / Garis Bawah</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + [ / ]</td><td>Decrease / Increase Font Size</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Shift + C / V</td><td>Copy / Apply Format (Format Painter)</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Alt + 1 / 2 / 3</td><td>Apply Heading 1 / 2 / 3 Style</td></tr>
                            <tr><td style="padding:4px 0; font-weight:600;">Ctrl + Alt + 0</td><td>Apply Normal Text Style</td></tr>
                        </table>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="closeDialog">Close</button>
                    </div>
                @endif

            </div>
        </div>
    @endif

    {{-- Floating Assistant FAB Button (Word 365 Copilot Style) --}}
    @if (!$assistantOpen)
        <button type="button" class="word-assistant-fab" wire:click="openAssistant" title="Buka Xishie Assistant (AI Copilot Penulisan Ilmiah)">
            <span class="word-fab-dot"></span>
            <span>✨ Xishie Assistant</span>
        </button>
    @endif

    @assets
        <script src="{{ asset('js/word-studio.js') }}"></script>
    @endassets

</div>
