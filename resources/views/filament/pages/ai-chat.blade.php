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
                        <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">MODEL</div>
                        <select class="rb-select" wire:model="selectedModel" style="width:100%;">
                            @forelse ($models as $key => $label)
                                <option value="{{ $key }}">{{ $label }}</option>
                            @empty
                                <option value="">— tidak ada model —</option>
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
                    <div style="display:flex; gap:.4rem;">
                        <button type="button" class="rb-button" wire:click="refreshModels" wire:loading.attr="disabled">
                            &#8635; Muat Model
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
                <div class="rb-terminal" style="max-height:30rem; min-height:12rem; padding:.9rem;">
                    @forelse ($messages as $msg)
                        @if ($msg['role'] === 'user')
                            <div style="margin-bottom:.9rem;">
                                <div class="rb-terminal__agent" style="font-weight:700;">&#128100; YOU</div>
                                <div style="white-space:pre-wrap;">{{ $msg['content'] }}</div>
                            </div>
                        @else
                            <div style="margin-bottom:.9rem;">
                                <div style="font-weight:700; color:var(--rb-terminal-fg);">
                                    &#129302; {{ $msg['model'] ?? 'ASSISTANT' }}
                                    @if (! empty($msg['latency_ms']))
                                        <span class="rb-terminal__time">({{ $msg['latency_ms'] }}ms)</span>
                                    @endif
                                </div>
                                <div style="white-space:pre-wrap; {{ str_starts_with($msg['content'], '[Gagal]') ? 'color:var(--rb-status-alert);' : '' }}">{{ $msg['content'] }}</div>
                            </div>
                        @endif
                    @empty
                        <div class="rb-terminal__line">
                            <span class="rb-terminal__time">[--:--:--]</span>
                            standby — pilih model lalu ketik pesan.
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
