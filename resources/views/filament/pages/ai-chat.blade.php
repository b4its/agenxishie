{{-- AI Chat — konsol Omnigensi dengan picker model & tema light/dark --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#9632;] AI CHAT // OMNIGENSI — {{ $this->selectedModel !== '' ? strtoupper(str_replace('|', ' / ', $this->selectedModel)) : 'NO MODEL' }}</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
            </div>
            <div class="rb-window__body">
                @if (! $backendOnline)
                    <div class="rb-notice rb-notice--alert" style="margin-bottom:1rem;">
                        <div class="rb-notice__title">[&#9888;] BACKEND OMNIGENSI TIDAK TERJANGKAU</div>
                        <p class="rb-mono rb-soft" style="font-size:.72rem; margin:.35rem 0;">
                            Tidak dapat menghubungi <strong>{{ $baseUrl }}</strong>. Jalankan stack omnigensi
                            (<code>make up</code> di <code>~/programming/python/omnigensi</code>) dan pastikan
                            <code>OMNIGENSI_BASE_URL</code> dapat dijangkau dari container.
                        </p>
                    </div>
                @endif

                {{-- Kontrol model & parameter --}}
                <div style="display:flex; flex-wrap:wrap; gap:.6rem; align-items:flex-end; margin-bottom:1rem;">
                    <div style="flex:1 1 22rem; min-width:16rem;">
                        <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">
                            MODEL
                            @if ($backendOnline)
                                <span class="rb-muted" style="font-weight:400; text-transform:none;">({{ $modelCounts['shown'] }}/{{ $modelCounts['total'] }} tampil)</span>
                            @endif
                        </div>

                        {{-- Search input untuk menyaring model --}}
                        <div style="position:relative; margin-bottom:.35rem;">
                            <input
                                type="text"
                                class="rb-input"
                                placeholder="&#128269; Cari model… (mis. claude, deepseek, 128k)"
                                wire:model.live.debounce.300ms="modelSearch"
                                style="padding-right:2rem;"
                            />
                            @if ($modelSearch !== '')
                                <button
                                    type="button"
                                    wire:click="clearModelSearch"
                                    title="Hapus pencarian"
                                    style="position:absolute; right:.4rem; top:50%; transform:translateY(-50%); border:0; background:transparent; cursor:pointer; color:var(--rb-ink-muted); font-size:1rem; line-height:1;"
                                >&#215;</button>
                            @endif
                        </div>

                        <select class="rb-select" wire:model="selectedModel" style="width:100%;">
                            @forelse ($models as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @empty
                                <option value="">
                                    {{ $modelSearch !== '' ? '— tidak ada model cocok —' : '— tidak ada model —' }}
                                </option>
                            @endforelse
                        </select>
                    </div>
                    <div style="width:8rem;">
                        <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">TEMPERATURE</div>
                        <input type="number" class="rb-input" min="0" max="2" step="0.1" wire:model="temperature" />
                    </div>
                    <div style="width:8rem;">
                        <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">MAX TOKENS</div>
                        <input type="number" class="rb-input" min="1" max="32000" step="1" wire:model="maxTokens" />
                    </div>
                    <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                        <button type="button" class="rb-button" wire:click="refreshModels" wire:loading.attr="disabled">
                            &#8635; Muat Model
                        </button>
                        <button type="button" class="rb-button" wire:click="exportChat" title="Unduh transkrip percakapan sebagai Markdown">
                            &#11015; Unduh Transkrip
                        </button>
                        <button type="button" class="rb-button" wire:click="clearChat" wire:loading.attr="disabled">
                            &#128465; Bersihkan
                        </button>
                    </div>
                </div>

                @if ($status !== '')
                    <div class="rb-mono rb-muted" style="font-size:.68rem; margin-bottom:.5rem;">{{ $status }}</div>
                @endif

                {{-- Transkrip --}}
                <div
                    class="rb-terminal"
                    style="max-height:30rem; min-height:12rem; padding:.9rem;"
                    x-data="{ scrollEnd() { this.$el.scrollTop = this.$el.scrollHeight; } }"
                    x-on:chat-updated.window="scrollEnd()"
                    wire:key="transcript-{{ count($messages) }}"
                    x-init="scrollEnd()"
                >
                    @forelse ($messages as $msg)
                        @if ($msg['role'] === 'user')
                            <div
                                style="margin-bottom:.9rem;"
                                x-data="{ copied: false, copyText(el) {
                                    const sel = document.createRange(); sel.selectNodeContents(el);
                                    const text = sel.toString();
                                    const done = () => { copied = true; setTimeout(() => copied = false, 1500); };
                                    if (navigator.clipboard && window.isSecureContext) {
                                        navigator.clipboard.writeText(text).then(done).catch(done);
                                    } else {
                                        const ta = document.createElement('textarea');
                                        ta.value = text; document.body.appendChild(ta); ta.select();
                                        try { document.execCommand('copy'); } catch (e) {}
                                        document.body.removeChild(ta); done();
                                    }
                                } }"
                            >
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem;">
                                    <div class="rb-terminal__agent" style="font-weight:700;">&#128100; YOU</div>
                                    <button
                                        type="button"
                                        class="rb-terminal__time"
                                        style="border:0; background:transparent; cursor:pointer; font-size:.62rem;"
                                        title="Salin pesan"
                                        x-on:click="copyText($refs.usermsg)"
                                        x-text="copied ? '✓ tersalin' : '⧉ salin'"
                                    >&#10697; salin</button>
                                </div>
                                <div x-ref="usermsg" style="white-space:pre-wrap;">{{ $msg['content'] }}</div>
                            </div>
                        @else
                            <div
                                style="margin-bottom:.9rem;"
                                x-data="{ copied: false, copiedMd: false, copyRaw(text, flag) {
                                    const done = () => { this[flag] = true; setTimeout(() => this[flag] = false, 1500); };
                                    if (navigator.clipboard && window.isSecureContext) {
                                        navigator.clipboard.writeText(text).then(done).catch(done);
                                    } else {
                                        const ta = document.createElement('textarea');
                                        ta.value = text; document.body.appendChild(ta); ta.select();
                                        try { document.execCommand('copy'); } catch (e) {}
                                        document.body.removeChild(ta); done();
                                    }
                                } }"
                            >
                                <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem;">
                                    <div style="font-weight:700; color:var(--rb-terminal-fg);">
                                        &#129302; {{ $msg['model'] ?? 'ASSISTANT' }}
                                        @if (! empty($msg['latency_ms']))
                                            <span class="rb-terminal__time">({{ $msg['latency_ms'] }}ms)</span>
                                        @endif
                                    </div>
                                    @unless (str_starts_with($msg['content'], '[Gagal]'))
                                        <span style="display:inline-flex; gap:.5rem; align-items:center;">
                                            <button
                                                type="button"
                                                class="rb-terminal__time"
                                                style="border:0; background:transparent; cursor:pointer; font-size:.62rem;"
                                                title="Salin teks (tanpa format)"
                                                x-on:click="copyRaw($refs.body.innerText, 'copied')"
                                                x-text="copied ? '✓ tersalin' : '⧉ salin teks'"
                                            >&#10697; salin teks</button>
                                            <button
                                                type="button"
                                                class="rb-terminal__time"
                                                style="border:0; background:transparent; cursor:pointer; font-size:.62rem;"
                                                title="Salin Markdown mentah"
                                                x-on:click="copyRaw(@js($msg['content']), 'copiedMd')"
                                                x-text="copiedMd ? '✓ .md' : '{ } .md'"
                                            >{ } .md</button>
                                        </span>
                                    @endunless
                                </div>
                                <div x-ref="body" class="rb-chat-md {{ str_starts_with($msg['content'], '[Gagal]') ? 'rb-chat-md--error' : '' }}">
                                    {!! $this->markdownToHtml($msg['content']) !!}
                                </div>
                            </div>
                        @endif
                    @empty
                        <div class="rb-terminal__line">
                            <span class="rb-terminal__time">[--:--:--]</span> standby — pilih model lalu ketik pesan.
                        </div>
                    @endforelse

                    <div wire:loading wire:target="send" class="rb-terminal__line">

                        <span class="rb-terminal__time">[...]</span> model sedang menyusun jawaban...
                    </div>
                </div>

                {{-- Input --}}
                <form wire:submit="send" style="display:flex; gap:.5rem; margin-top:.75rem; align-items:flex-start;">
                    <textarea
                        class="rb-textarea"
                        rows="2"
                        style="flex:1;"
                        placeholder="Tanya apa saja ke model {{ $this->selectedModel !== '' ? str_replace('|', ' / ', $this->selectedModel) : 'terpilih' }}..."
                        wire:model="draft"
                        wire:keydown.ctrl.enter="send"
                    ></textarea>
                    <button
                        type="submit"
                        class="rb-button rb-button--primary"
                        style="height:100%; min-height:2.6rem;"
                        @disabled(! $backendOnline)
                        wire:loading.attr="disabled"
                    >Kirim &#10148;</button>
                </form>
                <div class="rb-mono rb-muted" style="font-size:.6rem; margin-top:.35rem;">
                    Ctrl+Enter untuk mengirim · model & pemilih disediakan backend Omnigensi.
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>
