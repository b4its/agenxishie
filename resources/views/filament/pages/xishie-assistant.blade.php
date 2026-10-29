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
                        <div style="display:flex; gap:.35rem; align-items:flex-end; flex-wrap:wrap;">
                            <button type="button" class="rb-button" wire:click="regenerateLastResponse" title="Regenerate respon asisten terakhir dengan model saat ini">&#128260; Regenerate</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('markdown')" title="Unduh transkrip sesi sebagai Markdown">&#11015; MD</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('html')" title="Unduh transkrip sesi sebagai Dokumen HTML">&#11015; HTML</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('latex')" title="Unduh transkrip sesi sebagai Dokumen LaTeX (.tex)">&#11015; LaTeX</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('json')" title="Unduh transkrip sesi sebagai JSON">&#11015; JSON</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('csv')" title="Unduh transkrip sesi sebagai CSV (Spreadsheet / Excel)">&#11015; CSV</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('txt')" title="Unduh transkrip sesi sebagai Plain Text">&#11015; TXT</button>
                            <button type="button" class="rb-button" wire:click="saveSessionAsSnapshot" title="Simpan transkrip sesi ini ke versi naskah (Snapshot)">💾 Snapshot</button>
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
                                            <span class="rb-terminal__time">[{{ $msg['created_at'] ?? now()->format('H:i:s') }}]</span>
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
                                                    @click="copyContent()"
                                                    style="border:0; background:rgba(255,255,255,0.1); color:#cbd5e1; cursor:pointer; font-size:.6rem; padding:2px 6px; border-radius:3px;"
                                                    title="Salin teks ke papan klip"
                                                >
                                                    <span x-show="!copied">&#128203; Salin</span>
                                                    <span x-show="copied" style="color:#4ade80;">&#10003; Tersalin</span>
                                                </button>
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
                        <span style="border-left:1px solid #cbd5e1; height:14px; margin:0 3px;"></span>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#4338ca; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/kebaruan')" title="Eksekusi analisis kebaruan sekarang">⚡ Cek Kebaruan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#0f766e; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/keterbacaan')" title="Eksekusi audit keterbacaan sekarang">⚡ Cek Keterbacaan</button>
                        <button type="button" class="rb-button" style="font-size:.58rem; padding:1px 6px; background:#0369a1; color:#ffffff; font-weight:700;" wire:click="executeCommandDirectly('/daftar-pustaka')" title="Format pustaka sekarang">⚡ Format Pustaka</button>
                    </div>

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