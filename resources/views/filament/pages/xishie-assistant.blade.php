{{-- Xishie Assistant — konsol chat kontekstual dengan palet slash command & model switcher --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#10024;] XISHIE ASSISTANT — {{ $project?->title ? strtoupper(\Illuminate\Support\Str::limit($project->title, 40)) : 'NO PROJECT' }} [MODEL: {{ strtoupper($currentModel) }}]</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
            </div>
            <div style="background:#0f172a; color:#94a3b8; font-size:.62rem; padding:.25rem .75rem; border-bottom:1px solid rgba(255,255,255,0.08); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.4rem;">
                <span style="font-family:monospace; display:inline-flex; align-items:center; gap:5px;">
                    <span style="color:#38bdf8;">● SESI:</span> {{ count($log) }} pesan
                    <span style="color:#64748b;">|</span>
                    <span style="color:#fde047;">MODEL:</span> {{ $currentModel }}
                    <span style="color:#64748b;">|</span>
                    <span style="color:#34d399;">FOKUS:</span> {{ strtoupper($assistantScope) }}
                    <span style="color:#64748b;">|</span>
                    <span style="color:#a78bfa;">GENERASI:</span> {{ number_format($this->getTotalWordsGenerated()) }} kata
                </span>
                <div style="display:flex; align-items:center; gap:8px;">
                    <span style="font-family:monospace; color:#94a3b8;">
                        PERSONA: {{ strtoupper(str_replace('_', ' ', $currentPersona)) }}
                    </span>
                    <span style="color:#64748b;">|</span>
                    <div style="display:inline-flex; align-items:center; gap:3px;">
                        <input
                            type="text"
                            placeholder="Cari transkrip..."
                            wire:model.live.debounce.150ms="transcriptSearch"
                            style="font-size:.6rem; padding:1px 5px; height:18px; width:100px; border-radius:3px; border:1px solid #334155; background:#1e293b; color:#f8fafc;"
                        />
                        @if ($transcriptSearch !== '')
                            <button type="button" wire:click="$set('transcriptSearch', '')" style="border:0; background:none; color:#94a3b8; cursor:pointer; font-size:.6rem;" title="Reset pencarian transkrip">✕</button>
                        @endif
                        <button
                            type="button"
                            wire:click="toggleOnlyStarred"
                            style="border:1px solid {{ $onlyStarred ? '#eab308' : '#334155' }}; background:{{ $onlyStarred ? '#eab308' : '#1e293b' }}; color:{{ $onlyStarred ? '#78350f' : '#fde047' }}; cursor:pointer; font-size:.6rem; font-weight:700; padding:1px 5px; height:18px; border-radius:3px; display:inline-flex; align-items:center; gap:2px;"
                            title="{{ $onlyStarred ? 'Tampilkan semua pesan transkrip' : 'Saring hanya pesan berbintang / penting' }}"
                        >
                            <span>★</span>{{ count($starredMessages) > 0 ? count($starredMessages) : '' }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="rb-window__body">
                @if ($project === null)
                    <div class="rb-notice rb-notice--alert">
                        <div class="rb-notice__title">[&#9888;] BELUM ADA PROYEK</div>
                        <p class="rb-mono rb-soft" style="font-size:.72rem; margin:.35rem 0;">
                            Buat proyek terlebih dahulu melalui <a href="/admin/project-onboarding-wizard" style="color:var(--rb-primary);">Project Onboarding Wizard</a>.
                        </p>
                    </div>
                @else
                    {{-- Bar Kontrol: Proyek, Model AI, & Aksi Ekspor --}}
                    <div style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-bottom:.85rem;">
                        <div style="flex:1 1 16rem; min-width:13rem;">
                            <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">KONTEKS PROYEK</div>
                            <select class="rb-select" wire:change="switchProject($event.target.value)" style="width:100%;">
                                @foreach ($projects as $p)
                                    <option value="{{ $p->id }}" @selected($p->id === $project->id)>{{ \Illuminate\Support\Str::limit($p->title, 50) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="flex:1 1 14rem; min-width:12rem;">
                            <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">MODEL AI ASSISTANT</div>
                            <select class="rb-select" wire:change="setAssistantModel($event.target.value)" style="width:100%;">
                                @foreach ($availableModels as $m)
                                    <option value="{{ $m['id'] }}" @selected($m['id'] === $currentModel)>
                                        {{ $m['name'] }} ({{ $m['provider'] }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div style="flex:1 1 14rem; min-width:12rem;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.3rem;">
                                <span class="rb-kpi-card__label">📄 KONTEKS NASKAH</span>
                                @if ($activeDocumentId)
                                    <a href="{{ route('filament.admin.pages.naskah-studio', ['document' => $activeDocumentId]) }}" target="_blank" style="font-size:.58rem; color:#1d4ed8; font-weight:700; text-decoration:none;" title="Buka dokumen ini langsung di Naskah Studio">↗ Buka Studio</a>
                                @endif
                            </div>
                            <select class="rb-select" wire:change="selectContextDocument($event.target.value)" style="width:100%;">
                                <option value="">(Semua / Keseluruhan Proyek)</option>
                                @foreach ($documents as $d)
                                    <option value="{{ $d->id }}" @selected($d->id === $activeDocumentId)>
                                        {{ \Illuminate\Support\Str::limit($d->title, 35) }} ({{ number_format($d->word_count) }} kata)
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display:flex; gap:.35rem; align-items:flex-end; flex-wrap:wrap;">
                            <button type="button" class="rb-button" wire:click="regenerateLastResponse" title="Regenerate respon asisten terakhir dengan model saat ini">&#128260; Regenerate</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('markdown')" title="Unduh transkrip sesi sebagai Markdown">&#11015; MD</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('html')" title="Unduh transkrip sesi sebagai Dokumen HTML">&#11015; HTML</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('latex')" title="Unduh transkrip sesi sebagai Dokumen LaTeX (.tex)">&#11015; LaTeX</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('json')" title="Unduh transkrip sesi sebagai JSON">&#11015; JSON</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('csv')" title="Unduh transkrip sesi sebagai CSV (Spreadsheet / Excel)">&#11015; CSV</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('rtf')" title="Unduh transkrip sesi sebagai Rich Text (.rtf Word/LibreOffice)">&#11015; RTF</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('txt')" title="Unduh transkrip sesi sebagai Plain Text">&#11015; TXT</button>
                            <button type="button" class="rb-button" x-data="{ copied: false }" @click="navigator.clipboard.writeText(document.querySelector('.rb-terminal')?.innerText || ''); copied = true; setTimeout(() => copied = false, 2000)" title="Salin seluruh isi percakapan ke papan klip">
                                <span x-text="copied ? '✓ Tersalin!' : '📋 Salin Transkrip'">📋 Salin Transkrip</span>
                            </button>
                            <button type="button" class="rb-button" style="background:#1d4ed8; color:#ffffff; font-weight:700;" wire:click="exportTranscriptToDocument" title="Konversi sesi percakapan menjadi Dokumen Baru di Naskah Studio">📝 Buat Naskah Studio</button>
                            <button type="button" class="rb-button" wire:click="saveSessionAsSnapshot" title="Simpan transkrip sesi ini ke versi naskah (Snapshot)">💾 Snapshot</button>
                            <button type="button" class="rb-button" style="background:#4338ca; color:#ffffff; font-weight:700;" wire:click="generateSessionSummary" title="Sintesis dan rangkum seluruh sesi diskusi akademik">📌 Rangkum Sesi</button>
                            @if (!empty($lastClearedLog))
                                <button type="button" class="rb-button" style="background:#0f766e; color:#ffffff; font-weight:700;" wire:click="restoreClearedLog" title="Pulihkan riwayat percakapan yang baru dibersihkan">↩️ Pulihkan ({{ count($lastClearedLog) }})</button>
                            @endif
                            <button type="button" class="rb-button" wire:click="clearLog" title="Bersihkan riwayat percakapan">&#128465; Bersihkan</button>
                        </div>
                    </div>

                    {{-- Bar Lingkup & Persona Akademik --}}
                    <div style="display:flex; flex-wrap:wrap; justify-content:space-between; gap:.4rem; align-items:center; margin-bottom:.85rem; padding:.35rem .6rem; background:rgba(67,56,202,0.04); border-radius:4px; border:1px solid rgba(67,56,202,0.12);">
                        <div style="display:flex; flex-wrap:wrap; gap:.3rem; align-items:center;">
                            <span class="rb-mono" style="font-size:.6rem; color:#4338ca; font-weight:700; text-transform:uppercase;">PERSONA AKADEMIK:</span>
                            @foreach ($this->getPersonas() as $pKey => $pLabel)
                                <button
                                    type="button"
                                    wire:click="setPersona('{{ $pKey }}')"
                                    style="border:1px solid {{ $currentPersona === $pKey ? 'var(--rb-primary, #4338ca)' : '#cbd5e1' }};
                                           background:{{ $currentPersona === $pKey ? 'var(--rb-primary, #4338ca)' : '#ffffff' }};
                                           color:{{ $currentPersona === $pKey ? '#ffffff' : '#334155' }};
                                           font-size:.58rem; font-weight:600; padding:2px 7px; border-radius:3px; cursor:pointer;"
                                    title="Atur gaya respon asisten ke {{ $pLabel }}"
                                >
                                    @if($pKey === 'academic_senior') 🎓 @elseif($pKey === 'critical_reviewer') 🔍 @elseif($pKey === 'proofreader') ✍️ @elseif($pKey === 'methodologist') 📐 @else 💡 @endif
                                    {{ $pLabel }}
                                </button>
                            @endforeach
                        </div>
                        <div style="display:flex; gap:.25rem; align-items:center;">
                            <span class="rb-mono" style="font-size:.58rem; color:#64748b; font-weight:700;">FOKUS:</span>
                            @foreach (['project' => 'Proyek', 'manuscript' => 'Naskah', 'literature' => 'Literatur'] as $scKey => $scLabel)
                                <button
                                    type="button"
                                    wire:click="setScope('{{ $scKey }}')"
                                    style="border:1px solid {{ $assistantScope === $scKey ? '#0f766e' : '#cbd5e1' }};
                                           background:{{ $assistantScope === $scKey ? '#0f766e' : '#ffffff' }};
                                           color:{{ $assistantScope === $scKey ? '#ffffff' : '#475569' }};
                                           font-size:.55rem; font-weight:700; padding:1px 6px; border-radius:3px; cursor:pointer;"
                                >{{ $scLabel }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Palet Slash Command Berdasarkan Kategori --}}
                    <div style="margin-bottom:.85rem; background:rgba(0,0,0,0.02); padding:.6rem; border-radius:4px; border:1px solid rgba(0,0,0,0.06);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.4rem; flex-wrap:wrap; gap:.4rem;">
                            <div class="rb-kpi-card__label" style="margin-bottom:0;">PALET SLASH COMMAND (26 PERINTAH AKADEMIK)</div>
                            <div style="display:flex; gap:.25rem; flex-wrap:wrap; align-items:center;">
                                @foreach ($categories as $catKey => $catLabel)
                                    <button
                                        type="button"
                                        wire:click="setCommandCategory('{{ $catKey }}')"
                                        style="border:1px solid {{ $activeCategory === $catKey ? 'var(--rb-primary, #4338ca)' : '#cbd5e1' }};
                                               background:{{ $activeCategory === $catKey ? 'var(--rb-primary, #4338ca)' : '#f8fafc' }};
                                               color:{{ $activeCategory === $catKey ? '#ffffff' : '#475569' }};
                                               font-size:.62rem; font-weight:700; padding:2px 7px; border-radius:3px; cursor:pointer;"
                                    >{{ $catLabel }}</button>
                                @endforeach
                                <input
                                    type="text"
                                    class="rb-input"
                                    placeholder="Cari..."
                                    wire:model.live.debounce.150ms="commandSearch"
                                    style="font-size:.62rem; padding:2px 6px; height:22px; width:90px; border-radius:3px; border:1px solid #cbd5e1;"
                                />
                            </div>
                        </div>
                        <div style="display:flex; flex-wrap:wrap; gap:.3rem;">
                            @foreach ($filteredCommands as $cmd)
                                <button
                                    type="button"
                                    class="rb-button"
                                    style="padding:.2rem .55rem; font-size:.62rem;"
                                    title="{{ $cmd['description'] }}"
                                    wire:click="insertCommand('{{ $cmd['name'] }}')"
                                >{{ $cmd['name'] }}</button>
                            @endforeach
                        </div>
                    </div>

                    {{-- Bar Header & Tombol Buka Katalog Templat Prompt Akademik --}}
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.65rem; padding:.3rem .55rem; background:#f1f5f9; border-radius:4px; border:1px solid #e2e8f0;">
                        <div style="display:flex; align-items:center; gap:.4rem;">
                            <span style="font-size:.65rem; font-weight:800; color:#334155; text-transform:uppercase;">📚 KATALOG TEMPLAT PROMPT RISET</span>
                            <span style="font-size:.58rem; background:#e2e8f0; color:#475569; padding:1px 5px; border-radius:3px;">{{ count($promptTemplates) }} templat</span>
                        </div>
                        <button
                            type="button"
                            wire:click="togglePromptTemplates"
                            style="font-size:.6rem; font-weight:700; color:#4338ca; background:none; border:none; cursor:pointer;"
                        >
                            {{ $showPromptTemplates ? '▲ Sembunyikan' : '▼ Buka Katalog' }}
                        </button>
                    </div>

                    @if ($showPromptTemplates)
                        <div style="margin-bottom:.85rem; background:#f8fafc; padding:.65rem; border-radius:4px; border:1px solid #cbd5e1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.5rem; flex-wrap:wrap; gap:.3rem;">
                                <div style="display:flex; gap:.25rem; flex-wrap:wrap;">
                                    @foreach (['all' => 'Semua', 'Struktur & Bab' => 'Struktur', 'Sitasi & Literatur' => 'Literatur', 'Audit & Validasi' => 'Audit', 'Gaya & Format' => 'Format'] as $tCatKey => $tCatLabel)
                                        <button
                                            type="button"
                                            wire:click="setTemplateCategoryFilter('{{ $tCatKey }}')"
                                            style="border:1px solid {{ $templateCategoryFilter === $tCatKey ? '#0284c7' : '#cbd5e1' }};
                                                   background:{{ $templateCategoryFilter === $tCatKey ? '#0284c7' : '#ffffff' }};
                                                   color:{{ $templateCategoryFilter === $tCatKey ? '#ffffff' : '#334155' }};
                                                   font-size:.58rem; font-weight:700; padding:1px 6px; border-radius:3px; cursor:pointer;"
                                        >{{ $tCatLabel }}</button>
                                    @endforeach
                                </div>
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:.5rem;">
                                @foreach ($promptTemplates as $tKey => $tpl)
                                    <div style="background:#ffffff; border:1px solid #e2e8f0; border-radius:4px; padding:.5rem; display:flex; flex-direction:column; justify-content:space-between;">
                                        <div>
                                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.2rem;">
                                                <span style="font-size:.65rem; font-weight:700; color:#1e293b;">{{ $tpl['title'] }}</span>
                                                <span style="font-size:.52rem; background:#e0f2fe; color:#0369a1; padding:1px 4px; border-radius:3px; font-weight:600;">{{ $tpl['category'] }}</span>
                                            </div>
                                            <p style="font-size:.58rem; color:#64748b; margin-bottom:.4rem; line-height:1.3;">{{ $tpl['description'] }}</p>
                                        </div>
                                        <div style="display:flex; gap:.3rem; justify-content:flex-end;">
                                            <button
                                                type="button"
                                                class="rb-button"
                                                style="font-size:.58rem; padding:1px 6px;"
                                                wire:click="applyPromptTemplate('{{ $tKey }}', false)"
                                                title="Salin templat ke kolom pesan input"
                                            >📝 Ke Prompt</button>
                                            <button
                                                type="button"
                                                class="rb-button"
                                                style="font-size:.58rem; padding:1px 6px; background:#4338ca; color:#ffffff; font-weight:700;"
                                                wire:click="applyPromptTemplate('{{ $tKey }}', true)"
                                                title="Eksekusi langsung dengan model aktif"
                                            >⚡ Eksekusi</button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Bar Header & Drawer Pencarian Referensi Proyek --}}
                    <div data-citation-action="insertCitationKey" style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.65rem; padding:.3rem .55rem; background:#f0fdfa; border-radius:4px; border:1px solid #ccfbf1;">
                        <div style="display:flex; align-items:center; gap:.4rem;">
                            <span style="font-size:.65rem; font-weight:800; color:#0f766e; text-transform:uppercase;">🔍 PUSTAKA REFERENSI PROYEK</span>
                            <span style="font-size:.58rem; background:#ccfbf1; color:#0f766e; padding:1px 5px; border-radius:3px; font-weight:600;">{{ count($searchedReferences) }} rujukan</span>
                        </div>
                        <button
                            type="button"
                            wire:click="toggleReferenceDrawer"
                            style="font-size:.6rem; font-weight:700; color:#0f766e; background:none; border:none; cursor:pointer;"
                        >
                            {{ $showReferenceDrawer ? '▲ Sembunyikan' : '▼ Buka Pencarian' }}
                        </button>
                    </div>

                    @if ($showReferenceDrawer)
                        <div style="margin-bottom:.85rem; background:#f0fdfa; padding:.65rem; border-radius:4px; border:1px solid #99f6e4;">
                            <div style="margin-bottom:.5rem;">
                                <input
                                    type="text"
                                    class="rb-input"
                                    placeholder="Cari rujukan (judul, penulis, kunci sitasi, atau nama jurnal)..."
                                    wire:model.live.debounce.150ms="referenceSearchQuery"
                                    style="width:100%; font-size:.65rem; padding:4px 8px; border-radius:3px; border:1px solid #5eead4; background:#ffffff;"
                                />
                            </div>
                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(280px, 1fr)); gap:.4rem; max-height:14rem; overflow-y:auto;">
                                @forelse ($searchedReferences as $ref)
                                    <div style="background:#ffffff; border:1px solid #ccfbf1; border-radius:4px; padding:.45rem; display:flex; justify-content:space-between; align-items:center; gap:.4rem;">
                                        <div style="overflow:hidden;">
                                            <div style="font-size:.63rem; font-weight:700; color:#0f766e; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">{{ $ref['title'] }}</div>
                                            <div style="font-size:.55rem; color:#64748b;">
                                                <span style="font-weight:600; color:#0d9488;">{{ $ref['citation_key'] }}</span> &bull; {{ $ref['authors'] }} ({{ $ref['year'] ?? 'n.d.' }})
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="rb-button"
                                            style="font-size:.58rem; padding:2px 7px; background:#0f766e; color:#ffffff; white-space:nowrap;"
                                            wire:click="insertCitationKey('{{ $ref['citation_key'] }}')"
                                            title="Sisipkan tag kunci sitasi ke dalam pesan input"
                                        >+ Sisipkan</button>
                                    </div>
                                @empty
                                    <div style="font-size:.6rem; color:#64748b; padding:.5rem; grid-column:1/-1;">
                                        Ketik kata kunci pencarian di atas untuk menemukan referensi proyek.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    @endif

                    {{-- Transkrip Percakapan --}}
                    <div
                        class="rb-terminal"
                        style="max-height:30rem; min-height:12rem; padding:.9rem;"
                        x-data="{ scrollEnd() { this.$el.scrollTop = this.$el.scrollHeight; } }"
                        x-on:assistant-updated.window="scrollEnd()"
                        wire:key="asst-{{ count($log) }}"
                        x-init="scrollEnd()"
                    >
                        @forelse ($log as $msg)
                            @php
                                $isStarred = in_array($loop->index, $starredMessages, true);
                                $matchesFilter = ($transcriptSearch === '' || stripos((string)$msg['content'], $transcriptSearch) !== false) && (! $onlyStarred || $isStarred);
                            @endphp
                            @if ($matchesFilter)
                                @if ($msg['role'] === 'user')
                                    <div style="margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px dashed rgba(255,255,255,0.1);">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                            <div class="rb-terminal__agent" style="font-weight:700; color:#93c5fd;">&#128100; PENELITI</div>
                                            <div style="display:flex; gap:4px; align-items:center;">
                                                <span class="rb-terminal__time">[{{ $msg['created_at'] ?? now()->format('H:i:s') }}]</span>
                                                <button type="button" wire:click="deleteMessage({{ $loop->index }})" style="border:0; background:none; color:#fca5a5; cursor:pointer; font-size:.65rem; padding:0 3px;" title="Hapus pertanyaan ini">🗑️</button>
                                            </div>
                                        </div>
                                        <div style="white-space:pre-wrap; color:#f8fafc;">{{ $msg['content'] }}</div>
                                    </div>
                                @else
                                    <div style="margin-bottom:1rem; padding:8px 10px; border-radius:4px; border:{{ $isStarred ? '1px solid rgba(234,179,8,0.5)' : '1px dashed rgba(255,255,255,0.1)' }}; background:{{ $isStarred ? 'rgba(234,179,8,0.06)' : 'transparent' }};" x-data="{ copied: false, copyContent() { navigator.clipboard.writeText({{ json_encode($msg['content']) }}); this.copied = true; setTimeout(() => this.copied = false, 2000); } }">
                                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; flex-wrap:wrap; gap:4px;">
                                            <div style="font-weight:700; color:#38bdf8; display:inline-flex; align-items:center; gap:6px;">
                                                &#10024; XISHIE ASSISTANT
                                                <span style="font-size:.6rem; padding:1px 5px; background:rgba(56,189,248,0.15); border-radius:3px; color:#7dd3fc; font-weight:normal;">
                                                    {{ $msg['model'] ?? $currentModel }}
                                                </span>
                                                @if(!empty($msg['is_regenerated']))
                                                    <span style="font-size:.58rem; padding:1px 4px; background:rgba(234,179,8,0.2); border-radius:3px; color:#fde047;">regenerated</span>
                                                @endif
                                                @if($isStarred)
                                                    <span style="font-size:.58rem; padding:1px 5px; background:rgba(234,179,8,0.25); border-radius:3px; color:#fde047; font-weight:700;">★ penting</span>
                                                @endif
                                                <span style="font-size:.58rem; padding:1px 5px; background:rgba(255,255,255,0.08); border-radius:3px; color:#cbd5e1;">
                                                    {{ str_word_count((string) $msg['content']) }} kata
                                                </span>
                                                @if (!empty($msg['data']['verified']))
                                                    <span style="font-size:.58rem; padding:1px 5px; background:rgba(34,197,94,0.2); border-radius:3px; color:#86efac; font-weight:700;" title="{{ count($msg['data']['verified']) }} sitasi valid di pustaka">
                                                        ✓ {{ count($msg['data']['verified']) }} Valid
                                                    </span>
                                                @endif
                                                @if (!empty($msg['data']['unmatched']))
                                                    <span style="font-size:.58rem; padding:1px 5px; background:rgba(239,68,68,0.2); border-radius:3px; color:#fca5a5; font-weight:700;" title="{{ count($msg['data']['unmatched']) }} sitasi belum terdaftar">
                                                        ⚠️ {{ count($msg['data']['unmatched']) }} Cek
                                                    </span>
                                                @endif
                                            </div>
                                            <div style="display:flex; gap:4px; align-items:center;">
                                                <span class="rb-terminal__time" style="font-size:.6rem;">[{{ $msg['created_at'] ?? now()->format('H:i:s') }}]</span>
                                                <button
                                                    type="button"
                                                    wire:click="toggleStarMessage({{ $loop->index }})"
                                                    style="border:0; background:none; color:{{ $isStarred ? '#fde047' : '#94a3b8' }}; cursor:pointer; font-size:.75rem; padding:0 3px;"
                                                    title="{{ $isStarred ? 'Hapus tanda penting (bintang)' : 'Tandai jawaban penting (bintang)' }}"
                                                >{{ $isStarred ? '★' : '☆' }}</button>
                                                <button
                                                    type="button"
                                                    wire:click="deleteMessage({{ $loop->index }})"
                                                    style="border:0; background:rgba(239,68,68,0.15); color:#fca5a5; cursor:pointer; font-size:.62rem; padding:2px 6px; border-radius:3px;"
                                                    title="Hapus jawaban ini dari transkrip"
                                                >🗑️</button>
                                                @unless (str_starts_with($msg['content'], '[Gagal]'))
                                                    <button
                                                        type="button"
                                                        class="rb-terminal__time"
                                                        style="border:0; background:rgba(234,179,8,0.2); color:#fde047; cursor:pointer; font-size:.62rem; font-weight:700; padding:2px 6px; border-radius:3px;"
                                                        title="Regenerate respon ini dengan model aktif"
                                                        wire:click="regenerateMessage({{ $loop->index }})"
                                                    >🔄 Regenerate</button>
                                                    <button
                                                        type="button"
                                                        class="rb-terminal__time"
                                                        style="border:0; background:rgba(99,102,241,0.25); color:#a5b4fc; cursor:pointer; font-size:.62rem; font-weight:700; padding:2px 6px; border-radius:3px;"
                                                        title="Terapkan rekomendasi ini langsung ke Naskah Studio"
                                                        wire:click="applyToNaskahStudio({{ $loop->index }})"
                                                    >⚡ Ke Naskah Studio</button>
                                                    <button
                                                        type="button"
                                                        class="rb-terminal__time"
                                                        x-data="{ copied: false }"
                                                        @click="navigator.clipboard.writeText(@js($msg['content'])); copied = true; setTimeout(() => copied = false, 2000)"
                                                        style="border:0; background:rgba(16,185,129,0.2); color:#6ee7b7; cursor:pointer; font-size:.62rem; font-weight:700; padding:2px 6px; border-radius:3px;"
                                                        title="Salin teks respon ini ke papan klip (clipboard)"
                                                    >
                                                        <span x-text="copied ? '✓ Tersalin!' : '📋 Salin'">📋 Salin</span>
                                                    </button>
                                                @endunless
                                            </div>
                                        </div>
                                        <div class="rb-chat-md {{ str_starts_with($msg['content'], '[Gagal]') ? 'rb-chat-md--error' : '' }}">
                                            {!! $this->markdownToHtml($msg['content']) !!}
                                        </div>
                                    </div>
                                @endif
                            @endif
                        @empty
                            <div class="rb-terminal__line">
                                <span class="rb-terminal__time">[--:--:--]</span>
                                standby — pilih salah satu dari 26 slash command di atas atau ketik pertanyaan langsung.
                            </div>
                        @endforelse

                        <div wire:loading wire:target="send,executeCurrentModelToStudio,regenerateLastResponse" class="rb-terminal__line">
                            <span class="rb-terminal__time">[...]</span> Xishie (Model: {{ $currentModel }}) sedang memproses respon akademik...
                        </div>
                    </div>

                    {{-- Rekomendasi Cepat Berdasarkan Tahap Proyek Aktif --}}
                    @if (!empty($stagePrompts))
                        <div style="display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.6rem; align-items:center; background:rgba(79,70,229,0.05); padding:.3rem .5rem; border-radius:4px; border:1px solid rgba(79,70,229,0.12);">
                            <span class="rb-mono" style="font-size:.58rem; color:#4338ca; font-weight:700;">TAHAP {{ strtoupper($project?->current_stage?->value ?? $project?->stage?->value ?? 'DRAFT') }}:</span>
                            @foreach ($stagePrompts as $sp)
                                <button
                                    type="button"
                                    class="rb-button"
                                    style="font-size:.58rem; padding:1px 6px; background:#4338ca; color:#ffffff; font-weight:600;"
                                    wire:click="executeCommandDirectly('{{ $sp['command'] }}')"
                                    title="Jalankan perintah {{ $sp['command'] }}"
                                >
                                    {{ $sp['label'] }}
                                </button>
                            @endforeach
                        </div>
                    @endif

                    {{-- Rekomendasi Cepat Umum --}}
                    <div style="display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.4rem; align-items:center;">
                        <span class="rb-mono" style="font-size:.58rem; color:#64748b; font-weight:700;">PROMPT CEPAT:</span>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/abstrak')">📝 /abstrak</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/pendahuluan')">📑 /pendahuluan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/metodologi')">🔬 /metodologi</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/pembahasan')">💬 /pembahasan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/kesimpulan')">🏁 /kesimpulan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/kebaruan')">💡 /kebaruan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/daftar-pustaka')">📚 /daftar-pustaka</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/tabel-analisis')">📊 /tabel-analisis</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/rumus')">π /rumus</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/keterbacaan')">📈 /keterbacaan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 5px;" wire:click="insertCommand('/tesaurus ')">📚 /tesaurus</button>
                        <span style="border-left:1px solid #cbd5e1; height:14px; margin:0 3px;"></span>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#4338ca; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/kebaruan')" title="Eksekusi analisis kebaruan sekarang">⚡ Cek Kebaruan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#0f766e; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/keterbacaan')" title="Eksekusi audit keterbacaan sekarang">⚡ Cek Keterbacaan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#0369a1; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/daftar-pustaka')" title="Format pustaka sekarang">⚡ Format Pustaka</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#059669; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/sitasi audit')" title="Eksekusi audit integritas sitasi & deteksi halusinasi">⚡ Audit Sitasi</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#7c3aed; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/tesaurus')" title="Buka indeks tesaurus akademik">⚡ Tesaurus</button>
                    </div>

                    {{-- Riwayat Prompt Sesi --}}
                    @if (!empty($promptHistory))
                        <div style="display:flex; align-items:center; gap:.35rem; flex-wrap:wrap; margin-top:.4rem; padding:.25rem .45rem; background:#f1f5f9; border-radius:4px; border:1px solid #e2e8f0;">
                            <span style="font-size:.58rem; font-weight:700; color:#475569; display:inline-flex; align-items:center; gap:2px;">
                                🕒 Riwayat Prompt:
                            </span>
                            @foreach (array_slice($promptHistory, 0, 5) as $idx => $hPrompt)
                                <span style="display:inline-flex; align-items:center; background:#ffffff; border:1px solid #cbd5e1; border-radius:3px; padding:1px 5px; gap:3px;">
                                    <button type="button" style="background:none; border:none; font-size:.58rem; color:#1e293b; max-width:180px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; cursor:pointer; padding:0;" wire:click="selectPromptFromHistory('{{ addslashes($hPrompt) }}')" title="{{ $hPrompt }}">
                                        {{ Str::limit($hPrompt, 28) }}
                                    </button>
                                    <button type="button" wire:click="removePromptFromHistory({{ $idx }})" style="background:none; border:none; font-size:.55rem; color:#94a3b8; cursor:pointer; padding:0 2px;" title="Hapus prompt ini">×</button>
                                </span>
                            @endforeach
                            <button type="button" wire:click="clearPromptHistory" style="background:none; border:none; font-size:.58rem; color:#94a3b8; cursor:pointer; padding:0 3px;" title="Bersihkan riwayat prompt">✕</button>
                        </div>
                    @endif

                    {{-- Form Input --}}
                    <form wire:submit="send" style="display:flex; gap:.5rem; margin-top:.4rem; align-items:flex-start;">
                        <textarea
                            class="rb-textarea"
                            rows="2"
                            style="flex:1;"
                            placeholder="Ketik pertanyaan atau /pendahuluan, /abstrak, /metodologi, /pembahasan, /sitasi, /poles…"
                            wire:model="message"
                            wire:keydown.ctrl.enter="send"
                        ></textarea>
                        <button type="submit" class="rb-button rb-button--primary" style="min-height:2.6rem;" wire:loading.attr="disabled">
                            Kirim &#10148;
                        </button>
                        <button
                            type="button"
                            class="rb-button"
                            style="min-height:2.6rem; border-color:#10b981; color:#10b981; font-weight:700;"
                            wire:click="executeCurrentModelToStudio"
                            wire:loading.attr="disabled"
                            wire:target="executeCurrentModelToStudio"
                            title="Eksekusi instruksi dengan model {{ $currentModel }} dan terapkan langsung ke Naskah Studio"
                        >
                            <span wire:loading.remove wire:target="executeCurrentModelToStudio">&#9889; Eksekusi ke Studio</span>
                            <span wire:loading wire:target="executeCurrentModelToStudio">Mengeksekusi…</span>
                        </button>
                    </form>
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-top:.35rem; flex-wrap:wrap; gap:.4rem;">
                        <div class="rb-mono rb-muted" style="font-size:.6rem;">
                            Ctrl+Enter mengirim &middot; Jawaban mengacu pada konteks naskah &amp; dirender sebagai GFM.
                            @if (!empty($message))
                                <span style="margin-left:6px; color:#4338ca; font-weight:700;">({{ strlen($message) }} kar, {{ str_word_count($message) }} kata)</span>
                                <button type="button" wire:click="$set('message', '')" style="background:none; border:none; color:#ef4444; font-size:.58rem; cursor:pointer; margin-left:4px; font-weight:600;" title="Kosongkan kolom pesan">✕ Reset</button>
                            @endif
                        </div>
                        <div style="font-size:.62rem; color:var(--rb-primary); font-family:monospace; font-weight:600;">
                            &#129302; Model Aktif: {{ $currentModel }}
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>