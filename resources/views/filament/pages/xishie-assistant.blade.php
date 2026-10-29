{{-- Xishie Assistant — konsol chat kontekstual dengan palet slash command & model switcher --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#10024;] XISHIE ASSISTANT — {{ $project?->title ? strtoupper(\Illuminate\Support\Str::limit($project->title, 40)) : 'NO PROJECT' }} [MODEL: {{ strtoupper($currentModel) }}]</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
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
                            <button type="button" class="rb-button" wire:click="exportTranscript('json')" title="Unduh transkrip sesi sebagai JSON">&#11015; JSON</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('txt')" title="Unduh transkrip sesi sebagai Plain Text">&#11015; TXT</button>
                            <button type="button" class="rb-button" wire:click="clearLog" title="Bersihkan riwayat percakapan">&#128465; Bersihkan</button>
                        </div>
                    </div>

                    {{-- Palet Slash Command Berdasarkan Kategori --}}
                    <div style="margin-bottom:.85rem; background:rgba(0,0,0,0.02); padding:.6rem; border-radius:4px; border:1px solid rgba(0,0,0,0.06);">
                        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:.4rem; flex-wrap:wrap; gap:.4rem;">
                            <div class="rb-kpi-card__label" style="margin-bottom:0;">PALET SLASH COMMAND (26 PERINTAH AKADEMIK)</div>
                            <div style="display:flex; gap:.25rem; flex-wrap:wrap;">
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
                            @if ($msg['role'] === 'user')
                                <div style="margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px dashed rgba(255,255,255,0.1);">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:2px;">
                                        <div class="rb-terminal__agent" style="font-weight:700; color:#93c5fd;">&#128100; PENELITI</div>
                                        <span class="rb-terminal__time">[{{ $msg['created_at'] ?? now()->format('H:i:s') }}]</span>
                                    </div>
                                    <div style="white-space:pre-wrap; color:#f8fafc;">{{ $msg['content'] }}</div>
                                </div>
                            @else
                                <div style="margin-bottom:1rem; padding-bottom:.5rem; border-bottom:1px dashed rgba(255,255,255,0.1);" x-data="{ copied: false, copyContent() { navigator.clipboard.writeText({{ json_encode($msg['content']) }}); this.copied = true; setTimeout(() => this.copied = false, 2000); } }">
                                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px; flex-wrap:wrap; gap:4px;">
                                        <div style="font-weight:700; color:#38bdf8; display:inline-flex; align-items:center; gap:6px;">
                                            &#10024; XISHIE ASSISTANT
                                            <span style="font-size:.6rem; padding:1px 5px; background:rgba(56,189,248,0.15); border-radius:3px; color:#7dd3fc; font-weight:normal;">
                                                {{ $msg['model'] ?? $currentModel }}
                                            </span>
                                            @if(!empty($msg['is_regenerated']))
                                                <span style="font-size:.58rem; padding:1px 4px; background:rgba(234,179,8,0.2); border-radius:3px; color:#fde047;">regenerated</span>
                                            @endif
                                        </div>
                                        <div style="display:flex; gap:4px; align-items:center;">
                                            <span class="rb-terminal__time" style="font-size:.6rem;">[{{ $msg['created_at'] ?? now()->format('H:i:s') }}]</span>
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

                    {{-- Form Input --}}
                    <form wire:submit="send" style="display:flex; gap:.5rem; margin-top:.75rem; align-items:flex-start;">
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