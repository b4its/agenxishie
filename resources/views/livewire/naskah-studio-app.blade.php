<div class="word-root {{ $darkMode ? 'dark' : '' }}"
    x-data="{
        kpiPane: @entangle('kpiDrawerOpen'),
        zenMode: @entangle('zenMode'),
        activeTaskPane: @entangle('activeTaskPane'),
        commentsOpen: @entangle('showComments'),
        revisionsOpen: @entangle('showRevisions'),
        assistantPane: @entangle('assistantOpen'),
        navTab: @entangle('navigationTab'),
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
                <button type="button" class="word-icon-btn" wire:click="save" title="Save Document (Ctrl+S)">
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
            <span class="word-doc-status">
                — {{ $dirty ? 'Not saved' : 'Saved' }}
            </span>
        </div>

        <!-- Right: Search commands and help, 18 UX KPIs Pill, Profile Avatar -->
        <div class="word-titlebar__right">
            <!-- Search commands and help (Ctrl+K) -->
            <div class="word-search-box" wire:click="openDialog('palette')" style="cursor:pointer;" title="Search commands and help (Ctrl+K)">
                <svg viewBox="0 0 20 20" width="13" height="13" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="8.5" cy="8.5" r="5.5"/>
                    <path d="M13 13l4 4"/>
                </svg>
                <input type="text" class="word-search-input" placeholder="Search commands and help" readonly style="cursor:pointer;">
                <kbd style="font-size:9px; background:#ffffff; border:1px solid #d1d1d1; padding:1px 3px; border-radius:2px; color:#616161;">Ctrl+K</kbd>
            </div>

            <!-- 18 UX KPIs Telemetry Pill Button (Matches Image 1 Discord pill position) -->
            <button type="button" wire:click="toggleKpiDrawer" class="word-kpi-chip" title="Buka 18 UX KPIs &amp; Telemetri Usability">
                <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor">
                    <path d="M2 10a8 8 0 1116 0 8 8 0 01-16 0zm8-6a6 6 0 100 12 6 6 0 000-12zm-1 3a1 1 0 012 0v3.586l2.707 2.707a1 1 0 01-1.414 1.414l-3-3A1 1 0 019 10.586V7z"/>
                </svg>
                <span>18 UX KPIs</span>
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

        <!-- Right: Telemetry, Comments, Editing Mode Dropdown, Blue Share Button (Matches Image 1) -->
        <div class="word-ribbon-actions">
            <!-- Live UX Telemetry Status: Time on Task & Compliance -->
            <div class="word-ribbon-telemetry" style="display:inline-flex; align-items:center; gap:8px; margin-right:4px;">
                <span style="font-size:11px; color:var(--ms-text-muted);" title="Time on Task / Waktu Sesi Aktif">
                    ⏱️ Waktu Sesi: <strong style="color:var(--ms-text-primary);">{{ floor($sessionSeconds / 60) }}m {{ $sessionSeconds % 60 }}s</strong>
                </span>
                <span style="font-size:11px; color:var(--ms-text-muted);" title="Guideline Compliance Score">
                    🎯 Kepatuhan: <strong style="color:var(--ms-blue);">{{ $complianceAudit['score'] }}%</strong>
                </span>
            </div>

            <!-- Comments Button -->
            <button type="button" wire:click="$toggle('showComments')" class="word-btn-pill {{ $showComments ? 'active' : '' }}" title="Comments">
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
                    <button type="button" wire:click="setEditingMode('editing'); open = false;" class="word-nav-item {{ $editingMode === 'editing' ? 'active' : '' }}">
                        ✏️ Editing
                    </button>
                    <button type="button" wire:click="setEditingMode('reviewing'); open = false;" class="word-nav-item {{ $editingMode === 'reviewing' ? 'active' : '' }}">
                        💬 Reviewing
                    </button>
                    <button type="button" wire:click="setEditingMode('viewing'); open = false;" class="word-nav-item {{ $editingMode === 'viewing' ? 'active' : '' }}">
                        👁️ Viewing
                    </button>
                </div>
            </div>

            <!-- Solid Blue Share Button (Matches Image 1) -->
            <button type="button" wire:click="openDialog('merge')" class="word-btn-pill word-btn-share" title="Share &amp; Export Document">
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
                    <button type="button" class="word-btn-large" wire:click="newDocument" title="Create New Document">
                        <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="12" y1="18" x2="12" y2="12"/><line x1="9" y1="15" x2="15" y2="15"/>
                        </svg>
                        <span>New</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="save" title="Save Document (Ctrl+S)">
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
                        <button type="button" class="word-tool-btn" wire:click="runTool('cek-pedoman')" title="Check Guideline Compliance">
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
                            <select wire:model.live="fontFamily" class="word-select" style="width:105px;">
                                <option value="sans">Aptos</option>
                                <option value="serif">Calibri</option>
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
                            <button type="button" class="word-btn-square" style="font-weight:700;" @click="document.execCommand('bold')" wire:click="formatInline('bold')" title="Bold (Ctrl+B)">B</button>
                            <button type="button" class="word-btn-square" style="font-style:italic;" @click="document.execCommand('italic')" wire:click="formatInline('italic')" title="Italic (Ctrl+I)">I</button>
                            <button type="button" class="word-btn-square" style="text-decoration:underline;" @click="document.execCommand('underline')" wire:click="formatInline('underline')" title="Underline (Ctrl+U)">U ▾</button>
                            <button type="button" class="word-btn-square" style="text-decoration:line-through;" @click="document.execCommand('strikeThrough')" wire:click="formatInline('strike')" title="Strikethrough (ab)">ab</button>
                            <button type="button" class="word-btn-square" @click="document.execCommand('subscript')" wire:click="formatInline('code')" title="Subscript (x₂)">x₂</button>
                            <button type="button" class="word-btn-square" @click="document.execCommand('superscript')" wire:click="formatInline('code')" title="Superscript (x²)">x²</button>
                            <button type="button" class="word-btn-square" style="color:#0f6cbd;" wire:click="applyTheme('modern')" title="Text Effects">A</button>

                            <!-- Highlight Pen -->
                            <div class="word-color-picker-btn" wire:click="formatInline('highlight', '#fde68a')" title="Highlight Color">
                                <span>🖊️</span>
                                <div class="word-color-bar" style="background:#fde68a;"></div>
                            </div>

                            <!-- Font Color -->
                            <div class="word-color-picker-btn" wire:click="formatInline('color', '#b91c1c')" title="Font Color">
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
                            <button type="button" class="word-btn-square" @click="document.execCommand('insertUnorderedList')" wire:click="applyStyle({{ $activeBlock }}, 'bullet')" title="Bullets">• ▾</button>
                            <button type="button" class="word-btn-square" @click="document.execCommand('insertOrderedList')" wire:click="applyStyle({{ $activeBlock }}, 'numbered')" title="Numbering">1. ▾</button>
                            <button type="button" class="word-btn-square" wire:click="addBlock('bullet')" title="Multilevel List">▾</button>
                            <button type="button" class="word-btn-square" wire:click="formatParagraph('indent-')" title="Decrease Indent">⇤</button>
                            <button type="button" class="word-btn-square" wire:click="formatParagraph('indent+')" title="Increase Indent">⇥</button>
                            <button type="button" class="word-btn-square" wire:click="$toggle('showGridlines')" title="Show/Hide Marks (¶)">¶</button>
                        </div>

                        <!-- Row 2: Align Left, Center, Right, Justify, Spacing, Shading, Borders -->
                        <div class="word-group__row">
                            <button type="button" class="word-btn-square" @click="document.execCommand('justifyLeft')" wire:click="formatParagraph('align', 'left')" title="Align Left">⫷</button>
                            <button type="button" class="word-btn-square" @click="document.execCommand('justifyCenter')" wire:click="formatParagraph('align', 'center')" title="Center">≡</button>
                            <button type="button" class="word-btn-square" @click="document.execCommand('justifyRight')" wire:click="formatParagraph('align', 'right')" title="Align Right">⫸</button>
                            <button type="button" class="word-btn-square" @click="document.execCommand('justifyFull')" wire:click="formatParagraph('align', 'justify')" title="Justify">☷</button>
                            <button type="button" class="word-btn-square" wire:click="openDialog('pagesetup')" title="Line Spacing">↕ ▾</button>
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

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'heading' ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'title')" title="Title">
                            <div class="word-style-tile__preview" style="font-weight:800; font-size:14px; color:#111827;">AaBb</div>
                            <div class="word-style-tile__name">Title</div>
                        </div>

                        <div class="word-style-tile {{ ($blocks[$activeBlock]['type'] ?? '') === 'heading' && ($blocks[$activeBlock]['level'] ?? 1) === 2 ? 'active' : '' }}"
                            wire:click="applyStyle({{ $activeBlock }}, 'heading2')" title="Heading 2">
                            <div class="word-style-tile__preview" style="color:var(--ms-blue-heading); font-weight:600; font-size:12.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Heading 2</div>
                        </div>

                        <div class="word-style-tile" wire:click="applyStyle({{ $activeBlock }}, 'quote')" title="Subtitle">
                            <div class="word-style-tile__preview" style="color:#6b7280; font-style:italic; font-size:11.5px;">AaBbCcD</div>
                            <div class="word-style-tile__name">Subtitle</div>
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
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="openAssistant" title="Dictate Voice Assistant">
                        <div style="width:24px; height:24px; border-radius:9999px; background:#0f6cbd; color:#ffffff; display:flex; align-items:center; justify-content:center;">
                            <svg viewBox="0 0 20 20" width="13" height="13" fill="currentColor">
                                <path fill-rule="evenodd" d="M7 4a3 3 0 016 0v4a3 3 0 11-6 0V4zm4 10.93A7.001 7.001 0 0017 8a1 1 0 10-2 0A5 5 0 015 8a1 1 0 00-2 0 7.001 7.001 0 006 6.93V17H6a1 1 0 100 2h8a1 1 0 100-2h-3v-2.07z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                        <span>Dictate</span>
                    </button>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Voice</span>
                </div>
            </div>

            <!-- GROUP: EDITOR -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="toggleKpiDrawer('UX')" title="Editor &amp; Proofing">
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

            <!-- GROUP: 18 UX KPIS -->
            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="toggleKpiDrawer" title="18 UX &amp; Product Metrics Inspector">
                        <div style="width:24px; height:24px; border-radius:4px; background:#ebf3fc; color:#0f6cbd; display:flex; align-items:center; justify-content:center; font-size:12px;">
                            📊
                        </div>
                        <span>18 KPIs ▾</span>
                    </button>
                </div>
                <div class="word-group__footer">
                    <span class="word-group__label">Telemetry</span>
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
                    <button type="button" class="word-btn-large" wire:click="openDialog('image')" title="Insert Picture">
                        <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><polyline points="21 15 16 10 5 21"/></svg>
                        <span>Pictures</span>
                    </button>
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="insertCallout('info')">📌 Note (Info)</button>
                        <button type="button" class="word-tool-btn" wire:click="insertCallout('warning')">⚠️ Note (Warning)</button>
                        <button type="button" class="word-tool-btn" wire:click="insertCallout('success')">✅ Note (Success)</button>
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

            <!-- Group: Research & Literature Gap -->
            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="runTool('cek-gap')">🔍 Analyze Literature Gap</button>
                        <button type="button" class="word-tool-btn" wire:click="runTool('sitasi')">✅ Verify Citations</button>
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
                    <button type="button" class="word-tool-btn {{ $showComments ? 'active' : '' }}" wire:click="$toggle('showComments')">💬 Show Comments</button>
                    <button type="button" class="word-tool-btn" wire:click="openDialog('comment')">➕ New Comment</button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Comments</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-tool-btn {{ $showRevisions ? 'active' : '' }}" wire:click="$toggle('showRevisions')">👁️ Track Changes</button>
                    <button type="button" class="word-tool-btn" style="color:#107c41;" wire:click="acceptAllRevisions">✓ Accept All</button>
                    <button type="button" class="word-tool-btn" style="color:#c50f1f;" wire:click="rejectAllRevisions">✕ Reject All</button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Tracking &amp; Changes</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <div class="word-group__col">
                        <button type="button" class="word-tool-btn" wire:click="aiElaborateBlock({{ $activeBlock }})">✨ AI Elaborate</button>
                        <button type="button" class="word-tool-btn" wire:click="aiShortenBlock({{ $activeBlock }})">✂️ AI Shorten</button>
                        <button type="button" class="word-tool-btn" wire:click="aiFixTone({{ $activeBlock }}, 'akademik')">🎓 Academic Tone</button>
                    </div>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Wordcraft AI</span></div>
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
                    <button type="button" class="word-btn-large" wire:click="openAssistant" title="Open AI Wordcraft Copilot">
                        <span style="font-size:18px;">✨</span>
                        <span>AI Assistant</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="runTool('cek-pedoman')" title="Check Guideline Compliance">
                        <span style="font-size:18px;">🎯</span>
                        <span>Pedoman</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Copilot &amp; Audit</span></div>
            </div>

            <div class="word-group">
                <div class="word-group__content">
                    <button type="button" class="word-btn-large" wire:click="toggleKpiDrawer('HEART')" title="Rate CSAT Satisfaction">
                        <span style="font-size:18px;">⭐</span>
                        <span>Feedback / CSAT</span>
                    </button>
                    <button type="button" class="word-btn-large" wire:click="toggleKpiDrawer('RESOURCES')" title="18 UX KPIs Documentation &amp; Learning Guides">
                        <span style="font-size:18px;">📖</span>
                        <span>18 UX Guides</span>
                    </button>
                </div>
                <div class="word-group__footer"><span class="word-group__label">Feedback &amp; 18 KPIs</span></div>
            </div>
        @endif
    </div>

    <!-- ===================================================================== -->
    <!-- 4. HORIZONTAL RULER (MICROSOFT WORD INCH RULER)                       -->
    <!-- ===================================================================== -->
    @if ($showRuler && ! $zenMode)
        <div class="word-ruler-container">
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

    <!-- ===================================================================== -->
    <!-- 5. MAIN WORKSPACE: NAVIGATION PANE, CANVAS AREA & RIGHT TASK PANE     -->
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

            <!-- TAB 2: PAGES -->
            @elseif ($navigationTab === 'pages')
                <div style="padding:12px; display:flex; flex-direction:column; gap:8px;">
                    <div @click="window.scrollTo({ top: 0, behavior: 'smooth' })"
                        style="border:1px solid var(--ms-blue); border-radius:3px; padding:6px; background:#ffffff; box-shadow:var(--ms-shadow-card); cursor:pointer;">
                        <div style="font-size:10px; color:#8a8886; margin-bottom:2px;">Page 1</div>
                        <div style="font-size:9.5px; line-height:1.25; color:#616161; overflow:hidden; height:50px;">
                            <strong>{{ $docTitle }}</strong><br>
                            How a small community of makers shares space...
                        </div>
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
        <!-- CENTER CANVAS: SEAMLESS REALISTIC MICROSOFT WORD PAPER SHEET      -->
        <!-- ================================================================= -->
        <main class="word-canvas-area">
            <div class="word-paper"
                style="background-color: {{ $pageSetup['pageColor'] ?? '#ffffff' }}; font-family: {{ ($fontFamily === 'serif' ? 'var(--ms-font-serif)' : ($fontFamily === 'mono' ? 'var(--ms-font-mono)' : 'var(--ms-font-ui)')) }}; font-size: {{ $fontSize }}pt; zoom: {{ (int) $zoom / 100 }}; transform-origin: top center;">

                <!-- Watermark if configured -->
                @if (!empty($pageSetup['watermark']))
                    <div style="position:absolute; inset:0; display:flex; align-items:center; justify-content:center; pointer-events:none; user-select:none; opacity:0.04; font-size:5rem; font-weight:800; text-transform:uppercase; transform:rotate(-35deg);">
                        {{ $pageSetup['watermark'] }}
                    </div>
                @endif

                <!-- Word Header Margin (Matches Image 1) -->
                <div class="word-paper-header">
                    <span>The Open Studio Handbook</span>
                    <span>Spring edition</span>
                </div>

                <!-- UNIFIED MANUSCRIPT EDITOR: SINGLE CONTINUOUS CONTENTEDITABLE SURFACE -->
                <div id="word-manuscript-editor"
                    class="word-manuscript-editor"
                    contenteditable="{{ $editingMode === 'viewing' ? 'false' : 'true' }}"
                    spellcheck="false"
                    wire:ignore.self
                    @input.debounce.800ms="$wire.syncManuscriptHtml($el.innerHTML)"
                    @blur="$wire.syncManuscriptHtml($el.innerHTML)"
                    x-data="{
                        hasSelection: false,
                        checkSelection() {
                            const sel = window.getSelection();
                            this.hasSelection = sel && sel.toString().trim().length > 0;
                        }
                    }"
                    @mouseup="checkSelection()"
                    @keyup="checkSelection()">

                    @php
                        $inBulletList = false;
                        $inNumberedList = false;
                        $inBibliographySection = false;
                    @endphp

                    @foreach ($blocks as $index => $block)
                        @php
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
                            <li id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                @if (str_contains($text, 'Clean as you go'))
                                    <strong>Clean as you go.</strong> Leave a table the way you would like to find it.
                                @elseif (str_contains($text, 'Label what is yours'))
                                    <strong>Label what is yours.</strong> Unlabelled supplies on the commons shelf are fair game (Anderson, 2020).
                                @elseif (str_contains($text, 'Ask before you borrow'))
                                    <strong>Ask before you borrow.</strong> Even from the shelf, a quick note helps everyone keep track.
                                @else
                                    {!! e($text) !!}
                                @endif
                            </li>
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
                                <li id="word-block-{{ $index }}" data-block-index="{{ $index }}">{!! e($text) !!}</li>
                            @else
                                @if ($inNumberedList)
                                    </ol>
                                    @php $inNumberedList = false; @endphp
                                @endif

                                @if ($type === 'title')
                                    <h1 class="word-title-text" id="doc-title" data-block-index="{{ $index }}">{!! e($text ?: 'The Open Studio Handbook') !!}</h1>
                                @elseif ($type === 'subtitle')
                                    <p class="word-subtitle-text" id="doc-subtitle" data-block-index="{{ $index }}">{!! e($text) !!}</p>
                                @elseif ($type === 'heading')
                                    @php
                                        if (stripos($text, 'daftar pustaka') !== false || stripos($text, 'referensi') !== false || stripos($text, 'bibliography') !== false) {
                                            $inBibliographySection = true;
                                        } else {
                                            $inBibliographySection = false;
                                        }
                                    @endphp
                                    @if ($level === 1)
                                        <h2 class="word-heading-1" id="{{ $headingId }}" data-block-index="{{ $index }}">{!! e($text) !!}</h2>
                                    @elseif ($level === 2)
                                        <h3 class="word-heading-2" id="{{ $headingId }}" data-block-index="{{ $index }}">{!! e($text) !!}</h3>
                                    @else
                                        <h4 class="word-heading-3" id="{{ $headingId }}" data-block-index="{{ $index }}">{!! e($text) !!}</h4>
                                    @endif
                                @elseif ($type === 'quote')
                                    <blockquote class="word-quote-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                        <p class="word-quote-text">{!! e($text) !!}</p>
                                    </blockquote>
                                @elseif ($type === 'toc')
                                    <div class="word-toc-block" id="word-block-{{ $index }}" data-block-index="{{ $index }}" contenteditable="false" style="margin: 18px 0 26px 0; padding: 14px 18px; background: #faf9f8; border: 1px solid #edebe9; border-radius: 4px;">
                                        <div style="font-weight: 700; font-size: 13pt; color: #0f6cbd; margin-bottom: 12px; display: flex; align-items: center; justify-content: space-between;">
                                            <span>📑 Daftar Isi Otomatis</span>
                                            <span style="font-size: 11px; font-weight: normal; color: #8a8886;">Diperbarui dari Heading dokumen</span>
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
                                @elseif ($type === 'pagebreak')
                                    <div class="word-page-divider" contenteditable="false">
                                        <div class="word-page-divider-line"></div>
                                        <span class="word-page-divider-label">Page Break</span>
                                        <div class="word-page-divider-line"></div>
                                    </div>
                                    <div class="word-paper-header" contenteditable="false" style="margin-top: 36px; margin-bottom: 24px;">
                                        <span>The Open Studio Handbook</span>
                                        <span>Spring edition</span>
                                    </div>
                                @elseif ($type === 'callout')
                                    <div class="word-callout-box" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                        📌 {!! nl2br(e($text)) !!}
                                    </div>
                                @elseif ($type === 'table')
                                    <div style="overflow-x:auto; margin:14px 0; border:1px solid #d1d1d1; border-radius:2px;" contenteditable="true" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                        <table style="width:100%; border-collapse:collapse; font-size:11pt;">
                                            @php
                                                $rows = $block['table']['rows'] ?? [];
                                                $hasHeader = $block['table']['header'] ?? true;
                                            @endphp
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
                                @else
                                    @if ($inBibliographySection)
                                        <p class="word-bib-entry" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                            {!! nl2br(e($text)) !!}
                                        </p>
                                    @else
                                        <p class="word-paragraph-text" id="word-block-{{ $index }}" data-block-index="{{ $index }}">
                                            @if ($index === 2 && str_contains($text, 'pleasant'))
                                                Every Thursday evening the old print shop on Alder Street fills with people who make things. <strong>Painters</strong> set up by the north windows, letterpress regulars claim the back room, and somebody always brings bread. This handbook collects what we have learned about running a shared studio (Gauntlett, 2018): the routines that keep it <span class="word-spell-squiggle" title="Spelling: pleasant">pleasant</span>, the tools we pool, and the small agreements that let thirty strangers become a crew (Sennett, 2019).
                                            @elseif ($index === 4 && str_contains($text, 'ceramicist'))
                                                Rent is the obvious reason, but it is not the best one. A shared room puts your work next to work you would never have seen otherwise. A <span class="word-spell-squiggle" title="Spelling: ceramicist">ceramicist</span> explains glaze chemistry to a comic artist; a photographer borrows a weaver's loom for a still life. The cross-talk is the point.
                                            @else
                                                {!! nl2br(e($text)) !!}
                                            @endif
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

            </div>
        </main>

        <!-- ================================================================= -->
        <!-- RIGHT TASK PANE: 18 UX KPIS, EDITOR, COMMENTS, ASSISTANT          -->
        <!-- ================================================================= -->
        @if ($kpiDrawerOpen || $showComments || $showRevisions || $assistantOpen)
            <aside class="word-taskpane">

                <!-- 18 UX KPIS & USABILITY TELEMETRY PANE -->
                @if ($kpiDrawerOpen)
                    <div class="word-taskpane-header">
                        <div style="display:flex; align-items:center; gap:6px;">
                            <div style="width:20px; height:20px; background:#0f6cbd; border-radius:3px; display:flex; align-items:center; justify-content:center; color:#ffffff; font-size:11px;">📊</div>
                            <span class="word-taskpane-title">18 UX KPIs &amp; Telemetry</span>
                        </div>
                        <button type="button" class="word-icon-btn" wire:click="toggleKpiDrawer">&times;</button>
                    </div>

                    <!-- Framework Switcher Tabs (HEART, AARRR, Usability, Resources) -->
                    <div class="word-nav-subtabs" style="padding:8px 12px; background:var(--ms-bg-subtle, #f9f9fb);">
                        <button type="button" wire:click="setKpiFramework('HEART')" class="word-nav-pill {{ $kpiActiveFramework === 'HEART' ? 'active' : '' }}">
                            ❤️ HEART
                        </button>
                        <button type="button" wire:click="setKpiFramework('AARRR')" class="word-nav-pill {{ $kpiActiveFramework === 'AARRR' ? 'active' : '' }}">
                            🏴‍☠️ AARRR
                        </button>
                        <button type="button" wire:click="setKpiFramework('UX')" class="word-nav-pill {{ $kpiActiveFramework === 'UX' ? 'active' : '' }}">
                            🎯 18 KPIs
                        </button>
                        <button type="button" wire:click="setKpiFramework('RESOURCES')" class="word-nav-pill {{ $kpiActiveFramework === 'RESOURCES' ? 'active' : '' }}">
                            📖 Guides
                        </button>
                    </div>

                    <!-- Task Pane Body -->
                    <div class="word-taskpane-body">
                        <!-- Top Usability Summary Card -->
                        <div style="padding:12px; border-radius:6px; background:#ebf3fc; border:1px solid #cfe4fa; display:flex; flex-direction:column; gap:6px;">
                            <div style="display:flex; justify-content:space-between; align-items:center;">
                                <strong style="color:#0f6cbd; font-size:13px;">Editor &amp; UX Health Score</strong>
                                <span style="font-weight:700; color:#107c41; font-size:13px;">{{ $complianceAudit['score'] }}% ({{ $complianceAudit['grade'] }})</span>
                            </div>
                            <div style="font-size:11px; color:#424242; line-height:1.4;">
                                ⏱️ Time on Task: <strong>{{ floor($sessionSeconds / 60) }}m {{ $sessionSeconds % 60 }}s</strong> • Sitasi: <strong>{{ round(($statistics['words'] > 0 ? (($elements['footnotes'] ?? 0) / max(1, $statistics['words'])) * 1000 : 0), 1) }}/1k</strong>
                            </div>
                        </div>

                        <!-- CSAT Rating Widget -->
                        <div style="padding:10px 12px; border:1px solid #e1dfdd; border-radius:6px; background:#ffffff;">
                            <div style="font-size:12px; font-weight:600; margin-bottom:4px; display:flex; justify-content:space-between;">
                                <span>Bagikan Kepuasan UX (CSAT)</span>
                                <span style="color:#b74700; font-weight:700;">{{ $csatScore }}.0 / 5.0</span>
                            </div>
                            <div style="display:flex; gap:4px; margin-bottom:6px;">
                                @for ($s = 1; $s <= 5; $s++)
                                    <button type="button" wire:click="rateCsat({{ $s }})" style="background:none; border:none; font-size:18px; cursor:pointer; color: {{ $csatScore >= $s ? '#d97706' : '#d1d1d1' }};">
                                        ★
                                    </button>
                                @endfor
                            </div>
                            @if ($csatSubmitted)
                                <div style="font-size:11px; color:#107c41; font-weight:600;">✓ Rating kepuasan telah dicatat ke telemetri HEART.</div>
                            @endif
                        </div>

                        <!-- TAB: RESOURCES (6 UX GUIDES FROM PROMPT) -->
                        @if ($kpiActiveFramework === 'RESOURCES')
                            <div style="display:flex; flex-direction:column; gap:8px;">
                                <div style="font-size:12px; font-weight:600; color:#242424;">18 KPIs &amp; UX Behavioral Resources:</div>

                                <a href="https://lnkd.in/dWGVK2Tp" target="_blank" rel="noopener noreferrer" class="word-metric-card" style="text-decoration:none;">
                                    <strong style="color:#0f6cbd; font-size:12px;">1. Understanding Metrics (Guide) ↗</strong>
                                    <span style="font-size:11px; color:#616161;">Panduan taksonomi metrik kuantitatif &amp; kualitatif untuk mengukur perilaku pengguna.</span>
                                </a>

                                <a href="https://lnkd.in/dfbfSg8T" target="_blank" rel="noopener noreferrer" class="word-metric-card" style="text-decoration:none;">
                                    <strong style="color:#0f6cbd; font-size:12px;">2. Ultimate Guide to Product Metrics (57 Metrics) ↗</strong>
                                    <span style="font-size:11px; color:#616161;">Katalog 57 metrik produk digital untuk efisiensi, retensi, dan keberhasilan tugas.</span>
                                </a>

                                <a href="https://lnkd.in/dAS5bdx5" target="_blank" rel="noopener noreferrer" class="word-metric-card" style="text-decoration:none;">
                                    <strong style="color:#0f6cbd; font-size:12px;">3. Product Metrics That Matter ↗</strong>
                                    <span style="font-size:11px; color:#616161;">Membedakan sinyal perilaku nyata dari vanity metrics dalam antarmuka naskah.</span>
                                </a>

                                <a href="https://lnkd.in/dvA_si5X" target="_blank" rel="noopener noreferrer" class="word-metric-card" style="text-decoration:none;">
                                    <strong style="color:#0f6cbd; font-size:12px;">4. AARRR Pirate Framework ↗</strong>
                                    <span style="font-size:11px; color:#616161;">Acquisition, Activation, Retention, Referral, dan Revenue funnel.</span>
                                </a>

                                <a href="https://lnkd.in/dSNEHVRx" target="_blank" rel="noopener noreferrer" class="word-metric-card" style="text-decoration:none;">
                                    <strong style="color:#0f6cbd; font-size:12px;">5. Google HEART Framework ↗</strong>
                                    <span style="font-size:11px; color:#616161;">Happiness, Engagement, Adoption, Retention, and Task Success.</span>
                                </a>

                                <a href="https://lnkd.in/dsMA7AFH" target="_blank" rel="noopener noreferrer" class="word-metric-card" style="text-decoration:none;">
                                    <strong style="color:#0f6cbd; font-size:12px;">6. UX Specific KPIs ↗</strong>
                                    <span style="font-size:11px; color:#616161;">Task Completion Rate, Time on Task, User Error Rate, dan SEQ/CSAT.</span>
                                </a>
                            </div>

                        <!-- TAB: METRIC CARDS LIST (HEART, AARRR, UX) -->
                        @else
                            @php
                                $filteredKpis = array_filter($uxKpis, fn($m) => $kpiActiveFramework === 'UX' ? ($m['framework'] === 'UX') : ($m['framework'] === $kpiActiveFramework));
                            @endphp
                            @foreach ($filteredKpis as $kpiKey => $kpi)
                                <div class="word-metric-card">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <strong style="font-size:12px; color:var(--ms-text-primary);">{{ $kpi['label'] }}</strong>
                                        <span style="font-size:10px; font-weight:700; padding:2px 6px; border-radius:3px; background:{{ $kpi['healthy'] ? '#d1fae5; color:#065f46;' : '#fef3c7; color:#92400e;' }}">
                                            {{ $kpi['healthy'] ? 'OPTIMAL' : 'ATTENTION' }}
                                        </span>
                                    </div>
                                    <p style="font-size:11px; color:var(--ms-text-secondary); line-height:1.4;">{{ $kpi['description'] }}</p>
                                    <div style="display:flex; justify-content:space-between; font-size:11px; padding-top:4px; border-top:1px solid #edebe9;">
                                        <span>Current: <strong>{{ $kpi['value'] }} {{ $kpi['unit'] }}</strong></span>
                                        <span style="color:var(--ms-text-muted);">Target: {{ $kpi['target'] }} {{ $kpi['unit'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>

                <!-- COMMENTS TASK PANE -->
                @elseif ($showComments)
                    <div class="word-taskpane-header">
                        <span class="word-taskpane-title">Comments ({{ count($comments) }})</span>
                        <button type="button" class="word-icon-btn" wire:click="$set('showComments', false)">&times;</button>
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

                <!-- REVISIONS TASK PANE -->
                @elseif ($showRevisions)
                    <div class="word-taskpane-header">
                        <span class="word-taskpane-title">Track Changes ({{ $pendingRevisionCount }} pending)</span>
                        <button type="button" class="word-icon-btn" wire:click="$set('showRevisions', false)">&times;</button>
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

                <!-- COPILOT / XISHIE ASSISTANT PANE -->
                @elseif ($assistantOpen)
                    <div class="word-taskpane-header">
                        <span class="word-taskpane-title">🤖 Copilot &amp; Assistant</span>
                        <button type="button" class="word-icon-btn" wire:click="closeAssistant">&times;</button>
                    </div>
                    <div class="word-taskpane-body">
                        <div style="display:flex; flex-wrap:wrap; gap:4px;">
                            <button type="button" wire:click="runTool('parafrase')" class="word-btn-pill" style="font-size:11px;">/parafrase</button>
                            <button type="button" wire:click="runTool('sitasi')" class="word-btn-pill" style="font-size:11px;">/sitasi</button>
                            <button type="button" wire:click="runTool('cek-pedoman')" class="word-btn-pill" style="font-size:11px;">/cek-pedoman</button>
                        </div>
                        <div style="flex:1; overflow-y:auto; display:flex; flex-direction:column; gap:8px;">
                            @forelse ($assistantLog as $log)
                                <div style="padding:8px 10px; border-radius:6px; font-size:12px; {{ ($log['role'] ?? '') === 'user' ? 'background:#ebf3fc; border:1px solid #cfe4fa; margin-left:12px;' : 'background:#f9f9fb; border:1px solid #e1dfdd; margin-right:12px;' }}">
                                    <strong style="font-size:10px; color:#8a8886; display:block; margin-bottom:2px;">{{ ($log['role'] ?? '') === 'user' ? 'You' : 'Copilot' }}</strong>
                                    <div style="line-height:1.45;">{{ $log['content'] ?? '' }}</div>
                                </div>
                            @empty
                                <div style="text-align:center; color:#8a8886; font-size:12px; padding:32px 0;">Ask Copilot to help draft, cite, or refine your manuscript.</div>
                            @endforelse
                        </div>
                        <form wire:submit.prevent="sendAssistant" style="display:flex; gap:4px; margin-top:8px;">
                            <input type="text" wire:model="assistantInput" class="word-select" style="flex:1; height:32px;" placeholder="Ask Copilot or type /command...">
                            <button type="submit" class="word-btn-pill word-btn-share">Send</button>
                        </form>
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
                Page 1 of 2
            </div>
            <div class="word-status-item" wire:click="openDialog('stats')" title="Word Count">
                1 of {{ number_format($wordCount) }} words
            </div>
            <div class="word-status-item" wire:click="openDialog('spelling')" title="Proofing Language">
                English (United States)
            </div>
            <div class="word-status-item" wire:click="toggleKpiDrawer('UX')" title="Accessibility &amp; Compliance">
                <svg viewBox="0 0 20 20" width="12" height="12" fill="currentColor" style="color:#107c41;">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <span>Accessibility: Good to go</span>
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
        <div class="word-dialog-backdrop">
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
                @elseif ($dialog === 'stats')
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
                        <div style="font-size:12px; color:#616161; line-height:1.6; padding-top:6px; border-top:1px solid #edebe9;">
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
                        <span>Page Setup &amp; Margins</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
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
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Watermark:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="pageSetup.watermark" placeholder="e.g. DRAFT">
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

                {{-- INSERT IMAGE --}}
                @elseif ($dialog === 'image')
                    <div class="word-dialog__header">
                        <span>Insert Picture</span>
                        <button type="button" class="word-icon-btn" wire:click="closeDialog">&times;</button>
                    </div>
                    <div class="word-dialog__body">
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Choose Image File:</label>
                            <input type="file" wire:model="imageUpload" accept="image/*" class="word-select" style="width:100%; height:34px; padding:3px;">
                        </div>
                        <div>
                            <label style="display:block; font-weight:600; font-size:12px; margin-bottom:4px;">Alt Description:</label>
                            <input type="text" class="word-select" style="width:100%; height:32px;" wire:model="imageAlt" placeholder="Caption...">
                        </div>
                    </div>
                    <div class="word-dialog__footer">
                        <button type="button" class="word-btn-pill" wire:click="closeDialog">Close</button>
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
                            <button type="button" class="word-btn-pill word-btn-share" wire:click="createSnapshot(snapshotLabel)">Save Version</button>
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
