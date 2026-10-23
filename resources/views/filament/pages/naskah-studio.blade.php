{{-- Naskah Studio — editor naskah bergaya word processor (ribbon, panel navigasi, kanvas halaman, status bar) --}}
<x-filament-panels::page>
    <style>
        .ns-shell { display:flex; flex-direction:column; gap:.75rem; }
        .ns-ribbon { border:var(--rb-border); background:var(--rb-surface); box-shadow:var(--rb-shadow-sm); }
        .ns-ribbon__tabs { display:flex; gap:.15rem; flex-wrap:wrap; padding:.3rem .4rem 0; border-bottom:var(--rb-border-soft); background:var(--rb-surface-soft); }
        .ns-ribbon__tab { border:1px solid transparent; border-bottom:none; background:transparent; color:var(--rb-ink-soft); font-family:var(--rb-font-mono); font-size:.68rem; font-weight:700; padding:.3rem .6rem; cursor:pointer; border-radius:2px 2px 0 0; }
        .ns-ribbon__tab:hover { background:var(--rb-surface); color:var(--rb-ink); }
        .ns-ribbon__tab--active { background:var(--rb-surface); color:var(--rb-primary); border-color:var(--rb-ink); }
        .ns-ribbon__body { display:flex; gap:.9rem; flex-wrap:wrap; align-items:stretch; padding:.55rem .7rem; }
        .ns-group { display:flex; flex-direction:column; gap:.3rem; padding-right:.9rem; border-right:1px solid var(--rb-border-color, #cbd5e1); }
        .ns-group:last-child { border-right:none; }
        .ns-group__label { font-family:var(--rb-font-mono); font-size:.55rem; letter-spacing:.05em; text-transform:uppercase; color:var(--rb-ink-muted); }
        .ns-group__row { display:flex; gap:.25rem; flex-wrap:wrap; align-items:center; }
        .ns-tool { border:1px solid var(--rb-ink-soft); background:var(--rb-surface); color:var(--rb-ink); font-family:var(--rb-font-mono); font-size:.6rem; font-weight:700; padding:.2rem .45rem; cursor:pointer; min-width:1.6rem; text-align:center; }
        .ns-tool:hover { background:var(--rb-accent-soft); }
        .ns-tool--active { background:var(--rb-primary); color:var(--rb-on-primary); border-color:var(--rb-primary); }

        .ns-workspace { display:grid; grid-template-columns: 232px minmax(0,1fr); gap:.75rem; align-items:start; }
        @media (max-width: 1024px) { .ns-workspace { grid-template-columns: 1fr; } }

        .ns-nav { border:var(--rb-border); background:var(--rb-surface); box-shadow:var(--rb-shadow-sm); position:sticky; top:.5rem; max-height:70vh; overflow:auto; }
        .ns-nav__title { font-family:var(--rb-font-mono); font-size:.6rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; padding:.45rem .6rem; border-bottom:var(--rb-border-soft); background:var(--rb-surface-soft); color:var(--rb-primary); }
        .ns-nav__item { display:block; width:100%; text-align:left; border:none; background:transparent; padding:.32rem .6rem; font-family:var(--rb-font-display); font-size:.72rem; color:var(--rb-ink-soft); cursor:pointer; border-left:3px solid transparent; }
        .ns-nav__item:hover { background:var(--rb-surface-soft); color:var(--rb-ink); }
        .ns-nav__item--l2 { padding-left:1.15rem; font-size:.68rem; }
        .ns-nav__item--l3 { padding-left:1.7rem; font-size:.64rem; }
        .ns-nav__empty { padding:.6rem; font-family:var(--rb-font-mono); font-size:.62rem; color:var(--rb-ink-muted); }

        .ns-canvas { background:var(--rb-canvas-grid); border:var(--rb-border); box-shadow:var(--rb-shadow); padding:1.25rem 1rem; }
        .ns-page { background:#ffffff; color:#0f172a; max-width:820px; margin:0 auto; padding:2.5rem 2.75rem; box-shadow:0 1px 3px rgba(15,23,42,.18), 0 10px 24px rgba(15,23,42,.08); border:1px solid #e2e8f0; }
        .ns-page__title-input { width:100%; border:none; outline:none; font-family:var(--rb-font-display); font-size:1.45rem; font-weight:800; color:#0f172a; padding:.1rem 0 .5rem; border-bottom:2px solid #e2e8f0; margin-bottom:1.25rem; background:transparent; }
        .ns-block { position:relative; margin-bottom:.35rem; border:1px dashed transparent; border-radius:3px; padding:.1rem .25rem; }
        .ns-block:hover { border-color:#cbd5e1; }
        .ns-block:focus-within { border-color:#2563eb; background:#f8fbff; }
        .ns-block__gutter { position:absolute; right:.15rem; top:.1rem; display:none; gap:.15rem; }
        .ns-block:focus-within .ns-block__gutter, .ns-block:hover .ns-block__gutter { display:flex; }
        .ns-block__btn { border:1px solid #cbd5e1; background:#fff; color:#475569; font-size:.55rem; line-height:1; padding:.1rem .25rem; cursor:pointer; }
        .ns-block__btn:hover { background:#eff6ff; color:#1e40af; }
        .ns-block textarea { width:100%; border:none; resize:none; outline:none; background:transparent; font-family:var(--rb-font-serif); font-size:1rem; line-height:1.75; color:#0f172a; overflow:hidden; }
        .ns-block--heading textarea { font-family:var(--rb-font-display); font-weight:700; color:#1e40af; }
        .ns-block--h1 textarea { font-size:1.35rem; }
        .ns-block--h2 textarea { font-size:1.15rem; }
        .ns-block--h3 textarea { font-size:1rem; }
        .ns-block--quote { border-left:3px solid #1e40af; margin-left:0; padding-left:.9rem; }
        .ns-block--quote textarea { font-style:italic; color:#334155; }
        .ns-block--bullet::before { content:"•"; position:absolute; left:-.1rem; color:#1e40af; font-weight:700; }
        .ns-block--bullet textarea, .ns-block--numbered textarea { padding-left:.9rem; }
        .ns-page--serif textarea { font-family:var(--rb-font-serif); }
        .ns-page--sans textarea { font-family:var(--rb-font-display); }
        .ns-page--mono textarea { font-family:var(--rb-font-mono); font-size:.9rem; }

        .ns-statusbar { display:flex; gap:1rem; flex-wrap:wrap; align-items:center; border:var(--rb-border); background:var(--rb-surface); box-shadow:var(--rb-shadow-sm); padding:.35rem .7rem; font-family:var(--rb-font-mono); font-size:.62rem; color:var(--rb-ink-muted); }
        .ns-statusbar__spacer { flex:1; }

        .ns-source { border:var(--rb-border); background:var(--rb-surface); box-shadow:var(--rb-shadow-sm); }
        .ns-source__body { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,1fr)); gap:.9rem; padding:.75rem; }
        .ns-source__card { border:1px solid var(--rb-border-color,#cbd5e1); padding:.6rem; background:var(--rb-surface-soft); }
        .ns-source__card h4 { font-family:var(--rb-font-mono); font-size:.62rem; font-weight:700; letter-spacing:.04em; text-transform:uppercase; color:var(--rb-primary); margin:0 0 .45rem; }

        /* Xishie Assistant mengapung di kanan bawah */
        .ns-fab { position:fixed; right:1.5rem; bottom:1.5rem; z-index:60; display:inline-flex; align-items:center; gap:.5rem; padding:.7rem 1.05rem; border-radius:999px; background:var(--rb-primary); color:var(--rb-on-primary); border:2px solid var(--rb-ink); box-shadow:0 6px 18px rgba(15,23,42,.35); font-family:var(--rb-font-mono); font-size:.72rem; font-weight:700; cursor:pointer; }
        .ns-fab:hover { background:var(--rb-primary-strong); transform:translateY(-1px); }
        .ns-fab__dot { width:9px; height:9px; border-radius:50%; background:#4ade80; box-shadow:0 0 0 3px rgba(74,222,128,.3); }

        .ns-assistant { position:fixed; right:1.5rem; bottom:5.4rem; z-index:61; width:min(380px, calc(100vw - 2rem)); max-height:min(72vh, 640px); display:flex; flex-direction:column; border:var(--rb-border); background:var(--rb-surface); box-shadow:0 12px 32px rgba(15,23,42,.4); }
        .ns-assistant__titlebar { display:flex; justify-content:space-between; align-items:center; background:var(--rb-primary); color:var(--rb-on-primary); padding:.45rem .65rem; font-family:var(--rb-font-mono); font-size:.68rem; font-weight:700; }
        .ns-assistant__body { padding:.6rem .65rem; overflow:auto; }
        .ns-tools { display:grid; grid-template-columns:1fr 1fr; gap:.3rem; }
        .ns-tool-card { display:flex; align-items:flex-start; gap:.35rem; border:1px solid var(--rb-border-color,#cbd5e1); background:var(--rb-surface-soft); padding:.35rem .4rem; cursor:pointer; text-align:left; font-family:var(--rb-font-mono); font-size:.58rem; color:var(--rb-ink-soft); }
        .ns-tool-card:hover { border-color:var(--rb-primary); }
        .ns-tool-card--on { background:var(--rb-accent-soft); border-color:var(--rb-primary); color:var(--rb-primary); font-weight:700; }
        .ns-tool-card__check { width:.85rem; height:.85rem; flex:0 0 auto; border:1px solid var(--rb-ink-soft); display:inline-flex; align-items:center; justify-content:center; font-size:.55rem; background:#fff; }
        .ns-tool-card--on .ns-tool-card__check { background:var(--rb-primary); color:#fff; border-color:var(--rb-primary); }
        .ns-chatlog { max-height:14rem; overflow:auto; margin-top:.5rem; display:flex; flex-direction:column; gap:.35rem; }
        .ns-msg { font-family:var(--rb-font-mono); font-size:.62rem; line-height:1.55; padding:.4rem .5rem; border:1px solid var(--rb-border-color,#cbd5e1); }
        .ns-msg--user { background:var(--rb-surface-muted); }
        .ns-msg--assistant { background:var(--rb-surface-inset); border-left:3px solid var(--rb-primary); white-space:pre-wrap; }
        .ns-msg__role { display:block; font-weight:700; font-size:.55rem; letter-spacing:.05em; text-transform:uppercase; color:var(--rb-primary); margin-bottom:.15rem; }
    </style>

    <div class="ns-shell">

        {{-- ================= RIBBON ================= --}}
        <div class="ns-ribbon">
            <div class="ns-ribbon__tabs">
                @foreach ($ribbonTabs as $key => $label)
                    <button type="button" class="ns-ribbon__tab @if($activeTab === $key) ns-ribbon__tab--active @endif" wire:click="$set('activeTab', '{{ $key }}')">{{ $label }}</button>
                @endforeach
            </div>

            <div class="ns-ribbon__body">
                @if ($activeTab === 'home')
                    <div class="ns-group">
                        <span class="ns-group__label">Berkas</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-tool" wire:click="newDocument" title="Dokumen baru">&#128196; Baru</button>
                            <button type="button" class="ns-tool" wire:click="save" wire:loading.attr="disabled" wire:target="save" title="Simpan naskah">&#128190; Simpan</button>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Gaya</span>
                        <div class="ns-group__row">
                            @foreach ($stylesGallery as $styleKey => $styleLabel)
                                <button type="button" class="ns-tool" wire:click="addBlock('{{ $styleKey === 'quote' ? 'quote' : ($styleKey === 'bullet' ? 'bullet' : ($styleKey === 'numbered' ? 'numbered' : ($styleKey === 'normal' ? 'paragraph' : 'heading'))) }}')" title="Tambah blok {{ $styleLabel }}">+ {{ $styleLabel }}</button>
                            @endforeach
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Paragraf</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-tool" wire:click="addBlock('paragraph')" title="Tambah paragraf">+ Paragraf</button>
                            <button type="button" class="ns-tool" wire:click="addBlock('quote')" title="Tambah kutipan">&ldquo; Kutipan</button>
                        </div>
                    </div>
                @elseif ($activeTab === 'insert')
                    <div class="ns-group">
                        <span class="ns-group__label">Sisipkan Blok</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-tool" wire:click="addBlock('heading')">+ Judul</button>
                            <button type="button" class="ns-tool" wire:click="addBlock('paragraph')">+ Paragraf</button>
                            <button type="button" class="ns-tool" wire:click="addBlock('bullet')">+ Daftar Poin</button>
                            <button type="button" class="ns-tool" wire:click="addBlock('numbered')">+ Daftar Nomor</button>
                            <button type="button" class="ns-tool" wire:click="addBlock('quote')">+ Kutipan</button>
                        </div>
                    </div>
                @elseif ($activeTab === 'layout')
                    <div class="ns-group">
                        <span class="ns-group__label">Tipografi</span>
                        <div class="ns-group__row">
                            <select class="rb-select" style="width:auto; font-size:.65rem;" wire:model.live="fontFamily">
                                <option value="serif">Serif (akademik)</option>
                                <option value="sans">Sans-serif</option>
                                <option value="mono">Monospace</option>
                            </select>
                            <select class="rb-select" style="width:auto; font-size:.65rem;" wire:model.live="fontSize">
                                <option value="14">14 pt</option>
                                <option value="16">16 pt</option>
                                <option value="18">18 pt</option>
                            </select>
                        </div>
                    </div>
                    <div class="ns-group">
                        <span class="ns-group__label">Zoom</span>
                        <div class="ns-group__row">
                            @foreach (['75','100','125','150'] as $z)
                                <button type="button" class="ns-tool @if($zoom === $z) ns-tool--active @endif" wire:click="$set('zoom','{{ $z }}')">{{ $z }}%</button>
                            @endforeach
                        </div>
                    </div>
                @elseif ($activeTab === 'references')
                    <div class="ns-group">
                        <span class="ns-group__label">Referensi Proyek</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-tool" wire:click="toggleTool('/sitasi'); $set('assistantOpen', true)">&#128279; Sitasi</button>
                            <button type="button" class="ns-tool" wire:click="toggleTool('/cari-referensi'); $set('assistantOpen', true)">&#128269; Cari Referensi</button>
                            <button type="button" class="ns-tool" wire:click="toggleTool('/matriks-literatur'); $set('assistantOpen', true)">&#128202; Matriks Literatur</button>
                        </div>
                    </div>
                @elseif ($activeTab === 'review')
                    <div class="ns-group">
                        <span class="ns-group__label">Tinjauan</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-tool" wire:click="toggleTool('/cek-pedoman'); $set('assistantOpen', true)">&#128269; Cek Pedoman</button>
                            <button type="button" class="ns-tool" wire:click="toggleTool('/cek-gap'); $set('assistantOpen', true)">&#128300; Cek Gap</button>
                            <button type="button" class="ns-tool" wire:click="toggleTool('/ringkas'); $set('assistantOpen', true)">&#128221; Ringkas</button>
                        </div>
                    </div>
                @elseif ($activeTab === 'view')
                    <div class="ns-group">
                        <span class="ns-group__label">Panel &amp; Tampilan</span>
                        <div class="ns-group__row">
                            <button type="button" class="ns-tool @if($showNavigation) ns-tool--active @endif" wire:click="$toggle('showNavigation')">&#9776; Navigasi</button>
                            <button type="button" class="ns-tool" wire:click="$toggle('assistantOpen')">&#10024; Asisten</button>
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= SUMBER DOKUMEN ================= --}}
        <div class="ns-source">
            <div class="ns-source__body">
                <div class="ns-source__card">
                    <h4>&#128196; Dari Dokumen Lokal</h4>
                    <input type="file" wire:model="upload" accept=".docx,.pdf,.txt,.md,.markdown" class="rb-input" style="font-size:.68rem;" />
                    <div wire:loading wire:target="upload" class="rb-mono" style="font-size:.6rem; color:var(--rb-ink-muted); margin-top:.3rem;">Mengekstrak teks…</div>
                    <p class="rb-mono" style="font-size:.58rem; color:var(--rb-ink-muted); margin:.35rem 0 0;">DOCX, PDF, TXT, atau Markdown. Teks diekstrak menjadi blok naskah.</p>
                </div>

                <div class="ns-source__card">
                    <h4>&#128218; Dari Proyek</h4>
                    <div style="display:flex; gap:.3rem; flex-wrap:wrap;">
                        <select class="rb-select" style="flex:1; min-width:120px; font-size:.66rem;" wire:model="sourceProjectId">
                            <option value="">— Pilih proyek —</option>
                            @foreach ($projects as $p)
                                <option value="{{ $p->id }}">{{ $p->title }}</option>
                            @endforeach
                        </select>
                        <select class="rb-select" style="width:auto; font-size:.66rem;" wire:model="sourceProjectStage">
                            @foreach ($stageOptions as $val => $label)
                                <option value="{{ $val }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="button" class="rb-button rb-button--primary" style="margin-top:.45rem; font-size:.62rem;" wire:click="loadFromProject" wire:loading.attr="disabled" wire:target="loadFromProject">Muat Naskah Proyek</button>
                    <p class="rb-mono" style="font-size:.58rem; color:var(--rb-ink-muted); margin:.35rem 0 0;">Mengambil sub-bab tahap proyek sebagai blok naskah.</p>
                </div>

                @if ($documents->isNotEmpty())
                    <div class="ns-source__card">
                        <h4>&#128193; Dokumen Tersimpan</h4>
                        <div style="display:flex; flex-direction:column; gap:.2rem;">
                            @foreach ($documents as $doc)
                                <button type="button" class="ns-nav__item" style="border-left:none; padding:.25rem .2rem;" wire:click="openDocument({{ $doc->id }})">
                                    {{ $doc->title }}
                                    <span class="rb-mono" style="color:var(--rb-ink-muted); font-size:.55rem;">· {{ $doc->word_count }} kata</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        </div>

        {{-- ================= WORKSPACE ================= --}}
        <div class="ns-workspace" style="@if(! $showNavigation) grid-template-columns: 1fr; @endif">
            @if ($showNavigation)
                <aside class="ns-nav">
                    <div class="ns-nav__title">Navigasi Judul</div>
                    @forelse ($outline as $node)
                        <button type="button" class="ns-nav__item @if($node['level'] === 2) ns-nav__item--l2 @elseif($node['level'] >= 3) ns-nav__item--l3 @endif"
                                x-on:click="document.getElementById('ns-block-{{ $node['index'] }}')?.scrollIntoView({behavior:'smooth', block:'center'})">
                            {{ $node['text'] }}
                        </button>
                    @empty
                        <div class="ns-nav__empty">Belum ada judul. Tambah blok Heading untuk mengisi navigasi.</div>
                    @endforelse
                </aside>
            @endif

            <div class="ns-canvas">
                <div class="ns-page ns-page--{{ $fontFamily }}" style="font-size:{{ $fontSize }}px; transform-origin: top center; zoom: {{ (int) $zoom / 100 }};"
                     wire:key="ns-page-{{ $documentId ?? 'new' }}">
                    <input type="text" class="ns-page__title-input" wire:model="docTitle" wire:change="markDirty" placeholder="Judul dokumen" aria-label="Judul dokumen" />

                    @forelse ($blocks as $index => $block)
                        @php
                            $type = $block['type'] ?? 'paragraph';
                            $level = (int) ($block['level'] ?? 1);
                            $classes = 'ns-block ns-block--'.$type;
                            if ($type === 'heading') { $classes .= ' ns-block--h'.max(1, min(3, $level)); }
                            $rows = max(1, (int) ceil(mb_strlen((string) ($block['text'] ?? '')) / 78) + 1);
                        @endphp
                        <div class="{{ $classes }}" id="ns-block-{{ $index }}" wire:key="ns-block-{{ $index }}-{{ $type }}-{{ $level }}">
                            <div class="ns-block__gutter">
                                <button type="button" class="ns-block__btn" wire:click="moveBlock({{ $index }}, -1)" title="Naikkan" @disabled($index === 0)>&#9650;</button>
                                <button type="button" class="ns-block__btn" wire:click="moveBlock({{ $index }}, 1)" title="Turunkan" @disabled($index === $blockCount - 1)>&#9660;</button>
                                <button type="button" class="ns-block__btn" wire:click="applyStyle({{ $index }}, 'heading1')" title="Jadikan Heading 1">H1</button>
                                <button type="button" class="ns-block__btn" wire:click="applyStyle({{ $index }}, 'paragraph')" title="Jadikan Paragraf">P</button>
                                <button type="button" class="ns-block__btn" wire:click="removeBlock({{ $index }})" title="Hapus blok">&times;</button>
                            </div>
                            <textarea
                                rows="{{ $rows }}"
                                wire:model="blocks.{{ $index }}.text"
                                wire:change="markDirty"
                                placeholder="{{ $type === 'heading' ? 'Judul bagian…' : 'Tulis isi…' }}"
                                aria-label="Blok naskah {{ $index + 1 }} ({{ $type }})"
                            >{{ $block['text'] ?? '' }}</textarea>
                        </div>
                    @empty
                        <p class="rb-mono" style="color:var(--rb-ink-muted); font-size:.8rem;">Naskah masih kosong. Muat dari berkas/proyek atau tambah blok lewat ribbon.</p>
                    @endforelse
                </div>
            </div>
        </div>

        {{-- ================= STATUS BAR ================= --}}
        <div class="ns-statusbar">
            <span>{{ $blockCount }} blok</span>
            <span>{{ number_format($wordCount) }} kata</span>
            <span>{{ number_format($charCount) }} karakter</span>
            <span class="ns-statusbar__spacer"></span>
            <span>Halaman 1 dari 1</span>
            <span>Sumber: {{ $sourceType === 'project' ? 'Proyek · '.strtoupper((string) $sourceStage) : ($sourceFilename ?? 'Lokal') }}</span>
            @if ($dirty)
                <span class="rb-badge rb-badge--alert" style="font-size:.55rem;">&#9679; Belum disimpan</span>
            @elseif ($lastSavedAt)
                <span class="rb-badge rb-badge--pass" style="font-size:.55rem;">&#10003; Tersimpan {{ $lastSavedAt }}</span>
            @endif
            <span>Zoom {{ $zoom }}%</span>
        </div>

        {{-- ================= XISHIE ASSISTANT (mengapung) ================= --}}
        @if ($assistantOpen)
            <div class="ns-assistant">
                <div class="ns-assistant__titlebar">
                    <span>&#10024; XISHIE ASSISTANT · TOOLS</span>
                    <button type="button" style="background:transparent; border:none; color:inherit; cursor:pointer; font-size:.9rem;" wire:click="closeAssistant" aria-label="Tutup asisten">&times;</button>
                </div>
                <div class="ns-assistant__body">
                    <p class="rb-mono" style="font-size:.58rem; color:var(--rb-ink-muted); margin:0 0 .45rem;">
                        Pilih tools untuk mengaktifkan, lalu jalankan. {{ count($activeTools) }} tool aktif.
                    </p>

                    <div class="ns-tools">
                        @foreach ($assistantTools as $tool)
                            @php $on = in_array($tool['name'], $activeTools, true); @endphp
                            <button type="button" class="ns-tool-card @if($on) ns-tool-card--on @endif"
                                    wire:click="toggleTool('{{ $tool['name'] }}')"
                                    title="{{ $tool['description'] }}">
                                <span class="ns-tool-card__check">@if($on)&#10003;@endif</span>
                                <span>{{ $tool['label'] }}</span>
                            </button>
                        @endforeach
                    </div>

                    @if (count($activeTools) > 0)
                        <div style="display:flex; gap:.3rem; flex-wrap:wrap; margin-top:.45rem;">
                            @foreach ($activeTools as $activeTool)
                                <button type="button" class="rb-button rb-button--primary" style="font-size:.58rem; padding:.15rem .45rem;"
                                        wire:click="runTool('{{ $activeTool }}')" wire:loading.attr="disabled" wire:target="runTool">
                                    &#9889; Jalankan {{ str_replace('-', ' ', ltrim($activeTool, '/')) }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    <div style="display:flex; gap:.3rem; margin-top:.55rem;">
                        <input type="text" class="rb-input" style="flex:1; font-size:.66rem;" placeholder="Tanya Xishie tentang naskah ini…" wire:model="assistantInput" wire:keydown.enter="sendAssistant" />
                        <button type="button" class="rb-button rb-button--primary" style="font-size:.6rem;" wire:click="sendAssistant" wire:loading.attr="disabled" wire:target="sendAssistant">Kirim</button>
                    </div>

                    <div class="ns-chatlog">
                        @forelse ($assistantLog as $msg)
                            <div class="ns-msg ns-msg--{{ $msg['role'] === 'user' ? 'user' : 'assistant' }}">
                                @php
                                    $cmdBits = explode(' ', $msg['content'], 2);
                                    $body = trim($cmdBits[1] ?? '');
                                    $isCommandInput = $msg['role'] === 'user' && str_starts_with((string) $msg['content'], '/');
                                @endphp
                                <span class="ns-msg__role">{{ $msg['role'] === 'user' ? 'Anda' : 'Xishie' }}@if(!empty($msg['command'])) · {{ ltrim($msg['command'], '/') }}@endif</span>
                                @if ($isCommandInput)
                                    {!! $body !== '' ? e($body) : '<em>dijalankan</em>' !!}
                                @else
                                    {{ $msg['content'] }}
                                @endif
                            </div>
                        @empty
                            <p class="rb-mono" style="font-size:.58rem; color:var(--rb-ink-muted); margin:0;">Belum ada percakapan. Pilih tool atau ajukan pertanyaan.</p>
                        @endforelse
                        @if ($assistantBusy)
                            <div class="ns-msg ns-msg--assistant"><span class="ns-msg__role">Xishie</span>Memproses…</div>
                        @endif
                    </div>
                </div>
            </div>
        @endif

        <button type="button" class="ns-fab" wire:click="openAssistant" aria-label="Buka Xishie Assistant">
            <span class="ns-fab__dot"></span>
            <span>&#10024; Xishie Assistant</span>
        </button>
    </div>
</x-filament-panels::page>
