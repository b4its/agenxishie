{{-- Xishie Assistant — konsol chat kontekstual dengan palet slash command --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#10024;] XISHIE ASSISTANT — {{ $project?->title ? strtoupper(\Illuminate\Support\Str::limit($project->title, 40)) : 'NO PROJECT' }}</span>
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
                    {{-- Proyek + aksi --}}
                    <div style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-bottom:.85rem;">
                        <div style="flex:1 1 18rem; min-width:14rem;">
                            <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">KONTEKS PROYEK</div>
                            <select class="rb-select" wire:change="switchProject($event.target.value)" style="width:100%;">
                                @foreach ($projects as $p)
                                    <option value="{{ $p->id }}" @selected($p->id === $project->id)>{{ \Illuminate\Support\Str::limit($p->title, 50) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div style="display:flex; gap:.35rem; align-items:flex-end;">
                            <button type="button" class="rb-button" wire:click="exportTranscript('markdown')" title="Unduh transkrip sesi sebagai Markdown">&#11015; MD</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('json')" title="Unduh transkrip sesi sebagai JSON">&#11015; JSON</button>
                            <button type="button" class="rb-button" wire:click="exportTranscript('txt')" title="Unduh transkrip sesi sebagai Plain Text">&#11015; TXT</button>
                            <button type="button" class="rb-button" wire:click="clearLog" title="Bersihkan riwayat percakapan">&#128465; Bersihkan</button>
                        </div>
                    </div>

                    {{-- Palet slash command --}}
                    <div class="rb-kpi-card__label" style="margin-bottom:.4rem;">SLASH COMMAND</div>
                    <div style="display:flex; flex-wrap:wrap; gap:.35rem; margin-bottom:.85rem;">
                        @foreach ($commands as $cmd)
                            <button
                                type="button"
                                class="rb-button"
                                style="padding:.2rem .55rem; font-size:.62rem;"
                                title="{{ $cmd['description'] }}"
                                wire:click="insertCommand('{{ $cmd['name'] }}')"
                            >{{ $cmd['name'] }}</button>
                        @endforeach
                    </div>

                    {{-- Transkrip --}}
                    <div
                        class="rb-terminal"
                        style="max-height:28rem; min-height:10rem; padding:.9rem;"
                        x-data="{ scrollEnd() { this.$el.scrollTop = this.$el.scrollHeight; } }"
                        x-on:assistant-updated.window="scrollEnd()"
                        wire:key="asst-{{ count($log) }}"
                        x-init="scrollEnd()"
                    >
                        @forelse ($log as $msg)
                            @if ($msg['role'] === 'user')
                                <div style="margin-bottom:.9rem;">
                                    <div class="rb-terminal__agent" style="font-weight:700;">&#128100; YOU</div>
                                    <div style="white-space:pre-wrap;">{{ $msg['content'] }}</div>
                                </div>
                            @else
                                <div style="margin-bottom:.9rem;">
                                    <div style="display:flex; justify-content:space-between; align-items:center;">
                                        <div style="font-weight:700; color:var(--rb-terminal-fg);">&#10024; XISHIE</div>
                                        @unless (str_starts_with($msg['content'], '[Gagal]'))
                                            <button
                                                type="button"
                                                class="rb-terminal__time"
                                                style="border:0; background:rgba(99,102,241,0.1); color:#4338ca; cursor:pointer; font-size:.62rem; font-weight:700; padding:2px 6px; border-radius:3px;"
                                                title="Terapkan rekomendasi ini langsung ke Naskah Studio"
                                                wire:click="applyToNaskahStudio({{ $loop->index }})"
                                            >⚡ Ke Naskah Studio</button>
                                        @endunless
                                    </div>
                                    <div class="rb-chat-md {{ str_starts_with($msg['content'], '[Gagal]') ? 'rb-chat-md--error' : '' }}">
                                        {!! $this->markdownToHtml($msg['content']) !!}
                                    </div>
                                </div>
                            @endif
                        @empty
                            <div class="rb-terminal__line">
                                <span class="rb-terminal__time">[--:--:--]</span>
                                standby — pilih slash command atau ketik pertanyaan.
                            </div>
                        @endforelse

                        <div wire:loading wire:target="send" class="rb-terminal__line">
                            <span class="rb-terminal__time">[...]</span> Xishie menyusun jawaban...
                        </div>
                    </div>

                    {{-- Input --}}
                    <form wire:submit="send" style="display:flex; gap:.5rem; margin-top:.75rem; align-items:flex-start;">
                        <textarea
                            class="rb-textarea"
                            rows="2"
                            style="flex:1;"
                            placeholder="Ketik pertanyaan atau /sitasi, /parafrase, /cek-gap, /cek-pedoman…"
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
                            title="Eksekusi instruksi dengan model saat ini dan terapkan langsung ke Naskah Studio"
                        >
                            <span wire:loading.remove wire:target="executeCurrentModelToStudio">&#9889; Eksekusi ke Studio</span>
                            <span wire:loading wire:target="executeCurrentModelToStudio">Mengeksekusi…</span>
                        </button>
                    </form>
                    <div class="rb-mono rb-muted" style="font-size:.6rem; margin-top:.35rem;">
                        Ctrl+Enter mengirim · jawaban memakai konteks proyek &amp; dirender sebagai Markdown.
                    </div>
                @endif
            </div>
        </div>
    </div>
</x-filament-panels::page>
