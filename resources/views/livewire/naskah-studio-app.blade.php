<div class="word-root {{ $darkMode ? 'dark' : '' }}"
    style="position: relative;"
    x-data="{
        zenMode: @entangle('zenMode'),
        activeTaskPane: @entangle('activeTaskPane'),
        commentsOpen: @entangle('showComments'),
        revisionsOpen: @entangle('showRevisions'),
        assistantPane: @entangle('assistantOpen'),
        navTab: @entangle('navigationTab'),
        activeBlock: @entangle('activeBlock'),
        activePage: 1,
        showMiniToolbar: false,
        toolbarTop: 0,
        toolbarLeft: 0,
        selectedText: '',
        copilotMenuOpen: false,
        showBlockCopilot: false,
        blockCopilotTop: 0,
        blockCopilotLeft: 0,
        syncAllPages() {
            const pageEls = document.querySelectorAll('.word-sheet-content');
            const pageHtmls = [];
            pageEls.forEach(el => pageHtmls.push(el.innerHTML));
            if (pageHtmls.length > 0) {
                $wire.syncManuscriptPages(pageHtmls);
            }
        },
        syncPages() {
            this.syncAllPages();
        },
        updateActiveBlock(event) {
            const target = event.target;
            if (!target) return;
            const blockEl = target.closest('[data-block-index]');
            if (blockEl) {
                const idx = parseInt(blockEl.getAttribute('data-block-index'), 10);
                if (!isNaN(idx) && this.activeBlock !== idx) {
                    this.activeBlock = idx;
                    $wire.selectBlock(idx);
                }
                try {
                    const rect = blockEl.getBoundingClientRect();
                    const rootRect = $el.getBoundingClientRect();
                    const maxLeft = (this.assistantPane || this.commentsOpen || this.revisionsOpen) ? (rootRect.width - 380 - 100) : (rootRect.width - 120);
                    this.blockCopilotTop = Math.max(10, (rect.top - rootRect.top) - 10);
                    this.blockCopilotLeft = Math.min(maxLeft, Math.max(10, (rect.right - rootRect.left) - 96));
                    this.showBlockCopilot = true;
                } catch (e) {
                    this.showBlockCopilot = false;
                }
            } else {
                this.showBlockCopilot = false;
            }
        },
        runBlockAi(tool) {
            this.showBlockCopilot = false;
            $wire.runTool(tool);
        },
        checkSelection() {
            const sel = window.getSelection();
            if (!sel || sel.isCollapsed) {
                this.showMiniToolbar = false;
                this.copilotMenuOpen = false;
                return;
            }
            const text = sel.toString().trim();
            if (!text) {
                this.showMiniToolbar = false;
                this.copilotMenuOpen = false;
                return;
            }
            const anchor = sel.anchorNode;
            if (!anchor || !anchor.parentElement || !anchor.parentElement.closest('.word-sheet-content')) {
                this.showMiniToolbar = false;
                this.copilotMenuOpen = false;
                return;
            }
            try {
                const range = sel.getRangeAt(0);
                const rect = range.getBoundingClientRect();
                const rootRect = $el.getBoundingClientRect();
                if (rect.width > 0 && rect.height > 0) {
                    this.selectedText = text;
                    this.toolbarTop = Math.max(10, (rect.top - rootRect.top) - 44);
                    this.toolbarLeft = Math.max(10, (rect.left - rootRect.left) + (rect.width / 2) - 150);
                    this.showMiniToolbar = true;

                    const blockEl = anchor.parentElement ? anchor.parentElement.closest('[data-block-index]') : null;
                    if (blockEl) {
                        const idx = parseInt(blockEl.getAttribute('data-block-index'), 10);
                        if (!isNaN(idx) && this.activeBlock !== idx) {
                            this.activeBlock = idx;
                            $wire.selectBlock(idx);
                        }
                    }
                }
            } catch (err) {
                this.showMiniToolbar = false;
            }
        },
        format(cmd, val = null) {
            document.execCommand(cmd, false, val);
            this.syncAllPages();
        },
        runAi(tool) {
            const textToUse = this.selectedText;
            this.showMiniToolbar = false;
            this.copilotMenuOpen = false;
            $wire.runTool(tool, textToUse);
        },
        handlePaste(e) {
            const items = (e.clipboardData || e.originalEvent?.clipboardData)?.items;
            if (!items) return;
            for (let i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    const blob = items[i].getAsFile();
                    if (!blob) continue;
                    e.preventDefault();
                    const reader = new FileReader();
                    reader.onload = (event) => {
                        const dataUrl = event.target.result;
                        $wire.insertImageUrl(dataUrl, 'Gambar dari Tangkapan Layar (Clipboard)', 480, 'center');
                    };
                    reader.readAsDataURL(blob);
                    return;
                }
            }
        },
        handleDrop(e) {
            const files = e.dataTransfer?.files;
            if (!files || files.length === 0) return;
            const file = files[0];
            if (file.type.indexOf('image') !== -1) {
                e.preventDefault();
                const reader = new FileReader();
                reader.onload = (event) => {
                    const dataUrl = event.target.result;
                    const fileName = file.name.replace(/\.[^/.]+$/, '');
                    $wire.insertImageUrl(dataUrl, 'Gambar: ' + fileName, 480, 'center');
                };
                reader.readAsDataURL(file);
            }
        },
        init() {
            window.addEventListener('keydown', (e) => {
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                    e.preventDefault();
                    $wire.openDialog('palette');
                }
                if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') {
                    e.preventDefault();
                    const pageEls = document.querySelectorAll('.word-sheet-content');
                    const pageHtmls = [];
                    pageEls.forEach(el => pageHtmls.push(el.innerHTML));
                    $wire.save(pageHtmls);
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
                if (e.altKey && (e.key === '=' || e.key === '+')) {
                    e.preventDefault();
                    $wire.openDialog('equation');
                }
            });

            document.addEventListener('selectionchange', () => {
                clearTimeout(this._selTimer);
                this._selTimer = setTimeout(() => this.checkSelection(), 120);
            });

            setInterval(() => {
                $wire.tickSession();
            }, 5000);
        }
    }">

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
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('bold')" title="Huruf Tebal (Ctrl+B)">
            <strong>B</strong>
        </button>
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('italic')" title="Huruf Miring (Ctrl+I)">
            <em>I</em>
        </button>
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('underline')" title="Garis Bawah (Ctrl+U)">
            <u>U</u>
        </button>
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('strikeThrough')" title="Coret (Strikethrough)">
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
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('justifyLeft')" title="Rata Kiri">
            <svg viewBox="0 0 16 16" width="11" height="11" fill="currentColor">
                <path d="M2 3h12v1.5H2zm0 3.5h8v1.5H2zm0 3.5h12v1.5H2zm0 3.5h8V15H2z"/>
            </svg>
        </button>
        <button type="button" class="word-mini-btn" @mousedown.prevent="format('justifyCenter')" title="Rata Tengah">
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
                <button type="button" @click="runAi('/poles')" class="word-mini-dropdown-item">
                    <span>🪄 Poles Kalimat Terpilih</span>
                </button>
                <button type="button" @click="runAi('/parafrase')" class="word-mini-dropdown-item">
                    <span>🔄 Parafrase Anti-Plagiasi</span>
                </button>
                <button type="button" @click="runAi('/kembangkan')" class="word-mini-dropdown-item">
                    <span>✍️ Elaborasi &amp; Kembangkan</span>
                </button>
                <button type="button" @click="runAi('/ringkaskan')" class="word-mini-dropdown-item">
                    <span>✂️ Padatkan / Buat Ringkas</span>
                </button>
                <button type="button" @click="runAi('/perbaiki-ejaan')" class="word-mini-dropdown-item">
                    <span>🔤 Perbaiki Ejaan &amp; PUEBI</span>
                </button>
                <button type="button" @click="runAi('/terjemahkan')" class="word-mini-dropdown-item">
                    <span>🌐 Terjemahkan Akademik</span>
                </button>
                <button type="button" @click="runAi('/sitasi')" class="word-mini-dropdown-item">
                    <span>📚 Rekomendasikan Sitasi</span>
                </button>
                <button type="button" @click="runAi('/cek-pedoman')" class="word-mini-dropdown-item">
                    <span>🎯 Cek Kepatuhan Pedoman</span>
                </button>
            </div>
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
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/poles')" class="word-mini-dropdown-item">
                    <span>🪄 Poles Gaya Bahasa</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/parafrase')" class="word-mini-dropdown-item">
                    <span>🔄 Parafrase Anti-Plagiasi</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/kembangkan')" class="word-mini-dropdown-item">
                    <span>✍️ Kembangkan Paragraf</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/ringkaskan')" class="word-mini-dropdown-item">
                    <span>✂️ Padatkan / Ringkas</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/perbaiki-ejaan')" class="word-mini-dropdown-item">
                    <span>🔤 Koreksi Ejaan PUEBI</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/terjemahkan')" class="word-mini-dropdown-item">
                    <span>🌐 Terjemahan Akademik</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/rumus')" class="word-mini-dropdown-item">
                    <span>π Rekomendasi Rumus LaTeX</span>
                </button>
                <button type="button" @click="blockCopilotMenu = false; runBlockAi('/cek-pedoman')" class="word-mini-dropdown-item">
                    <span>🎯 Audit Kepatuhan Kaidah</span>
                </button>
                <div style="height:1px; background:#e1dfdd; margin:2px 0;"></div>
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
                <button type="button" class="word-icon-btn" @click="const pageEls = document.querySelectorAll('.word-sheet-content'); const pageHtmls = []; pageEls.forEach(el => pageHtmls.push(el.innerHTML)); $wire.save(pageHtmls);" title="Save Document (Ctrl+S)">
                    <svg viewBox="0 0 20 20" width="15" height="15" fill="currentColor">
                        <path d="M4 2a2 2 0 00-2 2v12a2 2 0 002 2h12a2 2 0 002-2V6.414A2 2 0 0017.414 5L15 2.586A2 2 0 0013.586 2H4zm1 2h8v3H5V4zm0 6h10v6H5v-6z"/>
                    </svg>
                </button>
                <button type="button" class="word-icon-btn" wire:click="undo" {{ empty($undoStack) ? 'disabled' : '' }} title="Undo (Ctrl+Z)">
                    <svg viewBox="0 0 20 20" width="15" height="15" fill="currentColor">
                        <path fill-rule="evenodd" d="M7.707 3.293a1 1 0 010 1.414L5.414 7H11a7 7 0 017 7v1a1 1 0 11-2 0v-1a5 5 0 00-5-5H5.414l2.293 2.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"/>
                    </svg>
                </button>
                <button type="button" class="word-icon-btn" wire:click="redo" {{ empty($redoStack) ? 'disabled' : '' }} title="Redo (Ctrl+Y)">
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
                @if ($dirty)
                    <span style="color:#d83b01; font-weight:600;">● Perubahan belum disimpan</span>
                @else
                    <span style="color:#107c41; font-weight:600;">✓ Tersimpan otomatis</span>
                @endif
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

        <!-- Right: Comments, Editing Mode Dropdown, Blue Share Button (Matches Word 365) -->
        <div class="word-ribbon-actions">
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
                    <button type="button" class="word-btn-large" @click="const pageEls = document.querySelectorAll('.word-sheet-content'); const pageHtmls = []; pageEls.forEach(el => pageHtmls.push(el.innerHTML)); $wire.save(pageHtmls);" title="Save Document (Ctrl+S)">
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
                    <button type="button" class="word-btn-large" wire:click="runAutoCorrect" title="Paste / Auto-Correct">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            <path d="M9 14l2 2 4-4"/>
                        </svg>
                        <span>Paste ▾</span>
                    </button>

                    <!-- Cut, Copy, Format Painter -->
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="removeBlock({{ $activeBlock }})" title="Cut">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.5 13a3.5 3.5 0 01-.369-6.98 4 4 0 117.753-1.977A4.5 4.5 0 1113.5 13H11V9.413l1.293 1.294a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V13H5.5z" clip-rule="evenodd"/>
                            </svg>
                            <span>Cut</span>
                        </button>
                        <button type="button" class="word-tool-btn" wire:click="addBlock('paragraph')" title="Copy">
                            <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor">
                                <path d="M7 9a2 2 0 012-2h6a2 2 0 012 2v6a2 2 0 01-2 2H9a2 2 0 01-2-2V9z"/>
                                <path d="M5 3a2 2 0 00-2 2v6a2 2 0 002 2V5h8a2 2 0 00-2-2H5z"/>
                            </svg>
                            <span>Copy</span>
                        </button>
                        <button type="button" class="word-tool-btn {{ $copiedFormat ? 'active' : '' }}" wire:click="copyFormat({{ $activeBlock }})" title="Format Painter">
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
                            <select wire:model.live="fontFamily" class="word-select" style="width:115px;">
                                <option value="sans">Aptos</option>
                                <option value="calibri">Calibri</option>
                                <option value="times">Times New Roman</option>
                                <option value="arial">Arial</option>
                                <option value="serif">Source Serif 4</option>
                                <option value="mono">JetBrains Mono</option>
                            </select>
                            <select wire:model.live="fontSize" class="word-select" style="width:48px;">
                                @foreach ([10, 11, 12, 14, 16, 18, 20, 24, 28, 32] as $fs)
                                    <option value="{{ $fs }}">{{ $fs }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="word-btn-square" wire:click="adjustFontSize(1)" title="Increase Font Size (A^)">A<sup>▲</sup></button>
                            <button type="button" class="word-btn-square" wire:click="adjustFontSize(-1)" title="Decrease Font Size (Av)">A<sup>▼</sup></button>
                            <button type="button" class="word-btn-square" wire:click="runAutoCorrect" title="Change Case (Aa ▾)">Aa</button>
                            <button type="button" class="word-btn-square" wire:click="clearFormatting" title="Clear All Formatting">✕</button>
                        </div>

                        <!-- Row 2: B, I, U, Strike, Sub, Super, Glow, Highlight, Color -->
                        <div class="word-group__row">
                            <button type="button" class="word-btn-square" style="font-weight:700;" @mousedown.prevent @click="document.execCommand('bold'); syncAllPages();" title="Bold (Ctrl+B)">B</button>
                            <button type="button" class="word-btn-square" style="font-style:italic;" @mousedown.prevent @click="document.execCommand('italic'); syncAllPages();" title="Italic (Ctrl+I)">I</button>
                            <button type="button" class="word-btn-square" style="text-decoration:underline;" @mousedown.prevent @click="document.execCommand('underline'); syncAllPages();" title="Underline (Ctrl+U)">U ▾</button>
                            <button type="button" class="word-btn-square" style="text-decoration:line-through;" @mousedown.prevent @click="document.execCommand('strikeThrough'); syncAllPages();" title="Strikethrough (ab)">ab</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('subscript'); syncAllPages();" title="Subscript (x₂)">x₂</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('superscript'); syncAllPages();" title="Superscript (x²)">x²</button>
                            <button type="button" class="word-btn-square" style="color:#0f6cbd;" wire:click="applyTheme('modern')" title="Text Effects">A</button>

                            <!-- Highlight Pen -->
                            <div class="word-color-picker-btn" @mousedown.prevent @click="document.execCommand('hiliteColor', false, '#fef08a'); syncAllPages();" title="Highlight Color">
                                <span>🖊️</span>
                                <div class="word-color-bar" style="background:#fde68a;"></div>
                            </div>

                            <!-- Font Color -->
                            <div class="word-color-picker-btn" @mousedown.prevent @click="document.execCommand('foreColor', false, '#b91c1c'); syncAllPages();" title="Font Color">
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
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('insertUnorderedList'); syncAllPages();" title="Bullets">• ▾</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('insertOrderedList'); syncAllPages();" title="Numbering">1. ▾</button>
                            <button type="button" class="word-btn-square" wire:click="addBlock('bullet')" title="Multilevel List">▾</button>
                            <button type="button" class="word-btn-square" wire:click="formatParagraph('indent-')" title="Decrease Indent">⇤</button>
                            <button type="button" class="word-btn-square" wire:click="formatParagraph('indent+')" title="Increase Indent">⇥</button>
                            <button type="button" class="word-btn-square" wire:click="$toggle('showGridlines')" title="Show/Hide Marks (¶)">¶</button>
                        </div>

                        <!-- Row 2: Align Left, Center, Right, Justify, Spacing, Shading, Borders -->
                        <div class="word-group__row">
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('justifyLeft'); syncAllPages();" title="Align Left">⫷</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('justifyCenter'); syncAllPages();" title="Center">≡</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('justifyRight'); syncAllPages();" title="Align Right">⫸</button>
                            <button type="button" class="word-btn-square" @mousedown.prevent @click="document.execCommand('justifyFull'); syncAllPages();" title="Justify">☷</button>
                            <div style="position:relative;" x-data="{ openSpacing: false }">
                                <button type="button" class="word-btn-square" @click="openSpacing = !openSpacing" title="Line Spacing (1.0, 1.15, 1.5, 2.0)">↕ ▾</button>
                                <div x-show="openSpacing" @click.outside="openSpacing = false" style="position:absolute; left:0; top:24px; background:#ffffff; border:1px solid #e1dfdd; border-radius:4px; box-shadow:0 4px 16px rgba(0,0,0,0.12); width:135px; z-index:100; padding:4px 0; font-size:11.5px;">
                                    <button type="button" @click="openSpacing = false" wire:click="setLineSpacing('1.0')" class="word-nav-item {{ (string)($pageSetup['lineSpacing'] ?? '1.5') === '1' || ($pageSetup['lineSpacing'] ?? '') == 1.0 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">1.0 Single</button>
                                    <button type="button" @click="openSpacing = false" wire:click="setLineSpacing('1.15')" class="word-nav-item {{ ($pageSetup['lineSpacing'] ?? '') == 1.15 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">1.15 Normal</button>
                                    <button type="button" @click="openSpacing = false" wire:click="setLineSpacing('1.5')" class="word-nav-item {{ ($pageSetup['lineSpacing'] ?? '') == 1.5 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">1.5 Academic (DIKTI)</button>
                                    <button type="button" @click="openSpacing = false" wire:click="setLineSpacing('2.0')" class="word-nav-item {{ ($pageSetup['lineSpacing'] ?? '') == 2.0 ? 'active' : '' }}" style="padding:4px 10px; font-size:11px;">2.0 Double</button>
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
                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'paragraph' ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'normal')" title="Normal">
                            <div class="word-style-tile__preview" style="font-family:var(--ms-font-ui); font-size:12px;">AaBbCcDd</div>
                            <div class="word-style-tile__name">Normal</div>
                        </div>

                        <div class="word-style-tile" wire:click="applyStyle({{ $activeBlock }}, 'normal')" title="No Spacing">
                            <div class="word-style-tile__preview" style="font-family:var(--ms-font-ui); font-size:11px; letter-spacing:-0.5px;">AaBbCcDd</div>
                            <div class="word-style-tile__name">No Spacing</div>
                        </div>

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'heading' && ($blocks[$activeBlock]['level'] ?? 1) === 1 ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'heading1')" title="Heading 1">
                            <div class="word-style-tile__preview" style="color:var(--ms-blue-dark); font-weight:700; font-size:13px;">AaBbC</div>
                            <div class="word-style-tile__name">Heading 1</div>
                        </div>

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'title' ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'title')" title="Title">
                            <div class="word-style-tile__preview" style="font-weight:800; font-size:14px; color:#111827;">AaBb</div>
                            <div class="word-style-tile__name">Title</div>
                        </div>

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'heading' && ($blocks[$activeBlock]['level'] ?? 1) === 2 ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'heading2')" title="Heading 2">
                            <div class="word-style-tile__preview" style="color:var(--ms-blue-heading); font-weight:600; font-size:12.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Heading 2</div>
                        </div>

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'subtitle' ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'subtitle')" title="Subtitle">
                            <div class="word-style-tile__preview" style="color:#6b7280; font-size:11.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Subtitle</div>
                        </div>

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'quote' ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'quote')" title="Quote">
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

        <!-- TAB: INSERT -->
        @elseif ($activeTab === 'insert')
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="addBlock('paragraph')" title="Insert Blank Page">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                        <span>Blank Page</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="insertPageBreak" title="Insert Page Break">
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
                        <button type="button" class="word-tool-btn" wire:click="insertTable" title="Quick 3x3 Table">⊞ 3x3 Quick</button>
                        <button type="button" class="word-tool-btn" wire:click="tableInsertRow({{ $activeBlock }}, 1)" title="Add Row Below">➕ Row</button>
                        <button type="button" class="word-tool-btn" wire:click="tableInsertColumn({{ $activeBlock }}, 1)" title="Add Column">➕ Col</button>
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
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Illustrations</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="openDialog('link')">🔗 Hyperlink</button>
                        <button type="button" class="word-tool-btn" wire:click="openDialog('footnote')">⚓ Footnote</button>
                        <button type="button" class="word-tool-btn" wire:click="insertHorizontalRule">➖ Horizontal Line</button>
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
                        <button type="button" class="word-tool-btn" wire:click="updatePageSetup('watermark', 'DRAFT')">💧 Watermark: DRAFT</button>
                        <button type="button" class="word-tool-btn" wire:click="updatePageSetup('watermark', 'CONFIDENTIAL')">🔒 Watermark: CONFIDENTIAL</button>
                        <button type="button" class="word-tool-btn" wire:click="updatePageSetup('watermark', '')">✕ Clear Watermark</button>
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
                    <button type="button" class="word-btn-large" wire:click="updatePageSetup('orientation', '{{ ($pageSetup['orientation'] ?? 'portrait') === 'portrait' ? 'landscape' : 'portrait' }}')" title="Page Orientation">
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
                        <button type="button" class="word-tool-btn" wire:click="insertPageBreak">✂️ Insert Break</button>
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

            <!-- Group: Footnotes -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openDialog('footnote')" title="Insert Footnote (Catatan Kaki)">
                        <span style="font-size:18px;">⚓</span>
                        <span>Insert Footnote</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Footnotes</span></div>
            </div>

            <!-- Group: Citations & Bibliography -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <div class="word-group__row">
                            <button type="button" class="word-tool-btn" wire:click="insertCitation" title="Insert Academic Citation into Manuscript">
                                📚 Insert Citation
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
                </div>
                <div class="word-group__footer"><span class="word-group__label">Asisten &amp; Audit</span></div>
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

            <!-- Sub-tabs: Headings, Pages, Results -->
            <div class="word-nav-subtabs">
                <button type="button" wire:click="setNavigationTab('headings')" class="word-nav-pill {{ $navigationTab === 'headings' ? 'active' : '' }}">
                    Headings
                </button>
                <button type="button" wire:click="setNavigationTab('pages')" class="word-nav-pill {{ $navigationTab === 'pages' ? 'active' : '' }}">
                    Pages
                </button>
                <button type="button" wire:click="setNavigationTab('results')" class="word-nav-pill {{ $navigationTab === 'results' ? 'active' : '' }}">
                    Results
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
                                style="padding-left: {{ $item['level'] === 1 ? '12px' : '26px' }}; font-weight: {{ $item['level'] === 1 ? '600' : '400' }}; font-size: 12px; display: flex; align-items: center; justify-content: space-between;">
                                <span>{{ $item['text'] ?: '(Untitled Heading)' }}</span>
                                @if ($isBibHeading)
                                    <span style="font-size: 10px; color: #0f6cbd; background: #ebf3fc; padding: 1px 6px; border-radius: 9999px; font-weight: 600;">Acuan</span>
                                @endif
                            </button>
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
                x-data="{
                    syncAllPages() {
                        const pageEls = document.querySelectorAll('.word-sheet-content');
                        const pageHtmls = [];
                        pageEls.forEach(el => pageHtmls.push(el.innerHTML));
                        if (pageHtmls.length > 0) {
                            $wire.syncManuscriptPages(pageHtmls);
                        }
                    }
                }"
                style="width: 100%; display: flex; flex-direction: column; align-items: center;">

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

                    <div class="word-paper-sheet"
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
                        <div class="word-sheet-header" contenteditable="false">
                            <span class="word-sheet-title">{{ $docTitle }}</span>
                            @if ($isFirstPage)
                                <span class="word-sheet-edition">Spring edition</span>
                            @else
                                <!-- BAB Selanjutnya: Nomor Halaman di Kanan Atas (Sesuai Gambar 1) -->
                                <span class="word-sheet-page-top" title="Halaman {{ $pageNumber }}">{{ $pageNumber }}</span>
                            @endif
                        </div>

                        <!-- Konten Lembar Halaman (Continuous Flow Inside Page) -->
                        <div class="word-sheet-content"
                            wire:key="page-content-{{ $pageNumber }}-v{{ $editorRevision }}"
                            wire:ignore
                            id="word-page-content-{{ $pageNumber }}"
                            style="line-height: {{ $pageSetup['lineSpacing'] ?? '1.5' }};"
                            contenteditable="{{ $editingMode === 'viewing' ? 'false' : 'true' }}"
                            spellcheck="false"
                            @keydown.ctrl.enter.prevent="$wire.insertPageBreak()"
                            @keydown.meta.enter.prevent="$wire.insertPageBreak()"
                            @paste="handlePaste($event)"
                            @dragover.prevent
                            @drop="handleDrop($event)"
                            @click="updateActiveBlock($event)"
                            @focusin="updateActiveBlock($event)"
                            @keyup="updateActiveBlock($event)"
                            @input.debounce.800ms="syncAllPages()"
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
                                    <li id="word-block-{{ $index }}" data-block-index="{{ $index }}">{!! $text !!}</li>
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
                                        <li id="word-block-{{ $index }}" data-block-index="{{ $index }}">{!! $text !!}</li>
                                    @else
                                        @if ($inNumberedList)
                                            </ol>
                                            @php $inNumberedList = false; @endphp
                                        @endif

                                        @if ($type === 'title')
                                            <h1 class="word-title-text" id="doc-title" data-block-index="{{ $index }}">{!! $text ?: 'The Open Studio Handbook' !!}</h1>
                                        @elseif ($type === 'subtitle')
                                            <p class="word-subtitle-text" id="doc-subtitle" data-block-index="{{ $index }}">{!! $text !!}</p>
                                        @elseif ($type === 'heading')
                                            @php
                                                if (stripos($text, 'daftar pustaka') !== false || stripos($text, 'referensi') !== false || stripos($text, 'bibliography') !== false) {
                                                    $inBibliographySection = true;
                                                } else {
                                                    $inBibliographySection = false;
                                                }
                                            @endphp
                                            @if ($level === 1)
                                                <h2 class="word-heading-1" id="{{ $headingId }}" data-block-index="{{ $index }}">{!! $text !!}</h2>
                                            @elseif ($level === 2)
                                                <h3 class="word-heading-2" id="{{ $headingId }}" data-block-index="{{ $index }}">{!! $text !!}</h3>
                                            @else
                                                <h4 class="word-heading-3" id="{{ $headingId }}" data-block-index="{{ $index }}">{!! $text !!}</h4>
                                            @endif
                                        @elseif ($type === 'quote')
                                            <blockquote class="word-quote-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
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
                                                    default => '📌',
                                                };
                                                $cBorder = match ($cType) {
                                                    'warning' => '#d97706',
                                                    'tip' => '#16a34a',
                                                    'theorem' => '#9333ea',
                                                    'note' => '#0284c7',
                                                    default => '#0f6cbd',
                                                };
                                                $cBg = match ($cType) {
                                                    'warning' => 'rgba(217, 119, 6, 0.05)',
                                                    'tip' => 'rgba(22, 163, 74, 0.05)',
                                                    'theorem' => 'rgba(147, 51, 234, 0.05)',
                                                    'note' => 'rgba(2, 132, 199, 0.05)',
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
                                        @else
                                            @if ($inBibliographySection)
                                                <p class="word-bib-entry" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                                    {!! $text !!}
                                                </p>
                                            @else
                                                <p class="word-paragraph-text" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                                    {!! $text !!}
                                                </p>
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

                    <!-- Quick Command Chips Bar -->
                    <div class="word-assistant-tools-bar">
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/poles')" title="Poles kalimat/paragraf dengan gaya penulisan akademik">
                            🪄 /poles
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/parafrase')" title="Parafrase kalimat untuk menghindari plagiasi">
                            🔄 /parafrase
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/cek-pedoman')" title="Audit kepatuhan naskah terhadap kaidah ilmiah &amp; pedoman">
                            🎯 /cek-pedoman
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/sitasi')" title="Cari rekomendasi sitasi dan referensi ilmiah yang valid">
                            📚 /sitasi
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/ringkas')" title="Ringkasan eksekutif progres dan statistik naskah">
                            📝 /ringkas
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/cek-gap')" title="Identifikasi research gap dan kebaruan penelitian">
                            🔬 /cek-gap
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/matriks-literatur')" title="Bangun tabel matriks sintesis literatur (SOTA)">
                            📊 /matriks-literatur
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/cari-referensi')" title="Cari rekomendasi referensi akademik relevan">
                            🔍 /cari-referensi
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/rumus')" title="Rekomendasi rumus matematika &amp; LaTeX">
                            π /rumus
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/gambar')" title="Panduan diagram alur &amp; penyisipan gambar">
                            🖼️ /gambar
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/abstrak')" title="Susun draf abstrak akademik terstruktur IMRAD">
                            📄 /abstrak
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/metodologi')" title="Rancang struktur bab metodologi riset">
                            📐 /metode
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/tabel-analisis')" title="Buat tabel evaluasi komparasi kinerja">
                            📊 /tabel-analisis
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/terjemahkan')" title="Konversi terjemahan bahasa akademik formal">
                            🌐 /terjemahkan
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/puebi')" title="Periksa ejaan baku dan kaidah bahasa PUEBI/KBBI">
                            🔤 /puebi
                        </button>
                        <button type="button" class="word-assistant-tool-chip" wire:click="runTool('/kembangkan')" title="Elaborasi dan kembangkan poin menjadi teks utuh">
                            ✍️ /kembangkan
                        </button>
                    </div>

                    <!-- Conversation Stream -->
                    <div class="word-assistant-chat" x-init="$watch('$el.children.length', () => $nextTick(() => $el.scrollTop = $el.scrollHeight)); $nextTick(() => $el.scrollTop = $el.scrollHeight);">
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
                                </div>
                            </div>
                        @else
                            @foreach ($assistantLog as $logIndex => $log)
                                @if (($log['role'] ?? '') === 'user')
                                    <!-- User Message Bubble -->
                                    <div class="word-assistant-bubble-user">
                                        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:3px; gap:8px;">
                                            <strong style="font-size:10.5px; color:#0f6cbd;">Anda</strong>
                                            @if (!empty($log['command']) && $log['command'] !== 'freeform' && $log['command'] !== 'chat')
                                                <span style="font-size:9.5px; background:#cfe4fa; color:#0f6cbd; padding:1px 5px; border-radius:4px; font-weight:700;">{{ $log['command'] }}</span>
                                            @endif
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
                                            </div>
                                            <span style="font-size:9.5px; color:var(--ms-text-muted); background:#f1f5f9; padding:1px 5px; border-radius:3px;">AI Copilot</span>
                                        </div>
                                        <div class="word-assistant-rendered-markdown" style="line-height:1.55; margin-bottom:10px; font-size:12px;">
                                            {!! \Illuminate\Support\Str::markdown($log['content'] ?? '') !!}
                                        </div>

                                        <!-- In-Document Action Buttons -->
                                        <div style="display:flex; flex-wrap:wrap; gap:4px; border-top:1px solid #f1f5f9; padding-top:8px;">
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
                                        </div>

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
                        <div wire:loading.flex wire:target="sendAssistant,runTool,sendAssistantWithText" class="word-assistant-loading" style="display: none;">
                            <span class="word-assistant-spinner"></span>
                            <span style="font-weight: 500;">Xishie Assistant sedang menelaah naskah Anda...</span>
                        </div>
                    </div>

                    <!-- Input Form -->
                    <div class="word-assistant-input-area">
                        <form wire:submit.prevent="sendAssistant" style="display:flex; gap:6px;">
                            <input type="text" wire:model="assistantInput" class="word-select" style="flex:1; height:34px; padding:0 10px; font-size:12px;" placeholder="Tanya asisten atau ketik /poles, /sitasi... (Enter)">
                            <button type="submit" class="word-btn-pill word-btn-share" style="height:34px; padding:0 12px; font-weight:600;" wire:loading.attr="disabled" wire:target="sendAssistant,runTool,sendAssistantWithText">
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
            <div class="word-status-item" wire:click="openDialog('stats')" title="Jump to page">
                <span x-text="'Page ' + activePage + ' of {{ $pageCount ?? 1 }}'">Page 1 of {{ $pageCount ?? 1 }}</span>
            </div>
            <div class="word-status-item" wire:click="openDialog('stats')" title="Word Count">
                1 of {{ number_format($wordCount) }} words
            </div>
            <div class="word-status-item" wire:click="openDialog('spelling')" title="Proofing Language">
                English (United States)
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

                {{-- FIND & REPLACE --}}
                @if ($dialog === 'find')
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
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="findText">Find Next</button>
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="replaceAll">Replace All</button>
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
                                <strong style="font-size:20px; color:#0f6cbd;">{{ number_format($statistics['words'] ?? 0) }}</strong>
                            </div>
                            <div class="word-metric-card">
                                <span style="font-size:11px; color:#8a8886;">CHARACTERS</span>
                                <strong style="font-size:20px; color:#0f6cbd;">{{ number_format($statistics['characters'] ?? 0) }}</strong>
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
                        <div style="font-size:12px; color:#616161; line-height:1.6; padding-top:6px; margin-top:8px; border-top:1px solid #edebe9;">
                            <div>• Sentences: <strong>{{ $statistics['sentences'] ?? 0 }}</strong></div>
                            <div>• Paragraphs: <strong>{{ $statistics['paragraphs'] ?? 0 }}</strong></div>
                            <div>• Speaking Time: <strong>~{{ $statistics['speakingTimeMinutes'] ?? 1 }} min</strong></div>
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="closeDialog">Close</button>
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
                                <select wire:change="updatePageSetup('size', $event.target.value)" class="word-select" style="width:100%; height:32px;">
                                    <option value="A4" {{ ($pageSetup['size'] ?? '') === 'A4' ? 'selected' : '' }}>A4 (210 x 297 mm)</option>
                                    <option value="Letter" {{ ($pageSetup['size'] ?? '') === 'Letter' ? 'selected' : '' }}>Letter (8.5 x 11 in)</option>
                                    <option value="Legal" {{ ($pageSetup['size'] ?? '') === 'Legal' ? 'selected' : '' }}>Legal (8.5 x 14 in)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Orientation:</label>
                                <select wire:change="updatePageSetup('orientation', $event.target.value)" class="word-select" style="width:100%; height:32px;">
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
                                {{ $thesaurusResult }}
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
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="insertTable">OK</button>
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
                            <textarea rows="4" class="word-select" style="width:100%; height:auto; font-family:var(--ms-font-mono); font-size:11px;" wire:model="mergeCsv" placeholder="name,organization&#10;Alice,Alder Studios&#10;Bob,Makers Guild"></textarea>
                        </div>
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

                {{-- SHORTCUTS --}}
                @elseif ($dialog === 'shortcuts')
                    <div class="word-dialog__header">
                        <span>Keyboard Shortcuts</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <table style="width:100%; font-size:12px; border-collapse:collapse;">
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + S</td><td>Save document</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Z</td><td>Undo change</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + Y</td><td>Redo change</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + K</td><td>Search commands &amp; actions</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + F</td><td>Find and Replace</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + B</td><td>Bold</td></tr>
                            <tr style="border-bottom:1px solid #edebe9;"><td style="padding:4px 0; font-weight:600;">Ctrl + I</td><td>Italic</td></tr>
                            <tr><td style="padding:4px 0; font-weight:600;">Ctrl + U</td><td>Underline</td></tr>
                        </table>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill word-btn-share" wire:click="closeDialog">Close</button>
                    </div>
                @endif

            </div>
        </div>
    @endif

</div>
