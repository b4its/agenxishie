{{-- Platform Matcher — kartu rekomendasi jurnal SINTA/Scopus — tema-aware --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        @if ($project === null)
            <div class="rb-window">
                <div class="rb-window__titlebar"><span>PLATFORM MATCHER</span></div>
                <div class="rb-window__body"><p class="rb-mono rb-muted">Belum ada proyek aktif.</p></div>
            </div>
        @else
            <div class="rb-window" style="margin-bottom:1rem;">
                <div class="rb-window__titlebar">
                    <span>[&#9632;] MANUSCRIPT PLATFORM MATCHER — {{ strtoupper($project->title) }}</span>
                    <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                </div>
                <div class="rb-window__body" style="display:flex; justify-content:space-between; align-items:center; gap:1rem; flex-wrap:wrap;">
                    <div class="rb-mono rb-soft" style="font-size:.72rem;">
                        TARGET: <strong>{{ strtoupper($project->target_scope->label()) }}</strong>
                        @if ($project->target_tier) / {{ strtoupper($project->target_tier) }} @endif
                    </div>
                    <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                        <select class="rb-select" style="font-size:.68rem; padding:.2rem .4rem;" wire:model.live="filterScope">
                            <option value="">Semua Peringkat</option>
                            <option value="SINTA">SINTA (Nasional)</option>
                            <option value="Q">Scopus (Q1-Q4)</option>
                            <option value="WoS">Web of Science</option>
                        </select>
                        <select class="rb-select" style="font-size:.68rem; padding:.2rem .4rem;" wire:model.live="sortBy">
                            <option value="match_score">Skor Match (Tertinggi)</option>
                            <option value="apc">Biaya APC (Terendah)</option>
                            <option value="journal_name">Nama Jurnal (A-Z)</option>
                        </select>
                        <button type="button" class="rb-button rb-button--primary" wire:click="runMatcher">
                            &#9889; Jalankan Venue Matcher
                        </button>
                    </div>
                </div>
            </div>

            @if ($adaptedManuscript !== null)
                <div class="rb-notice rb-notice--pass" style="margin-bottom:1rem;" x-data="{ openPreview: false }">
                    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem;">
                        <span class="rb-mono" style="font-size:.72rem;">
                            <strong>&#10003; Naskah diformat untuk: {{ $adaptedManuscript['journal_name'] ?? 'Target Venue' }}</strong>
                            &middot; Gaya: {{ strtoupper($adaptedManuscript['citation_style'] ?? 'APA') }}
                            &middot; {{ number_format((int) ($adaptedManuscript['word_count'] ?? 0)) }} kata
                        </span>
                        <button type="button" class="rb-button" style="font-size:.62rem; padding:.2rem .5rem;" x-on:click="openPreview = ! openPreview">
                            &#128065; Preview Naskah Terformat
                        </button>
                    </div>
                    <div x-show="openPreview" x-cloak style="width:100%; margin-top:.5rem;">
                        <pre class="rb-terminal" style="max-height:16rem; font-size:.66rem; white-space:pre-wrap;">{{ $adaptedManuscript['manuscript'] ?? '' }}</pre>
                    </div>
                </div>
            @endif

            {{-- Export naskah siap kirim (KPI #15 Manuscript Export Conversion) --}}
            <div class="rb-window" style="margin-bottom:1rem;">
                <div class="rb-window__titlebar">
                    <span>&#11015; EXPORT NASKAH SIAP KIRIM</span>
                    <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                </div>
                <div class="rb-window__body" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
                    <span class="rb-mono rb-muted" style="font-size:.7rem;">Naskah:</span>
                    @foreach ($exportFormats as $key => $meta)
                        <button
                            type="button"
                            class="rb-button {{ $key === 'docx' ? 'rb-button--primary' : '' }}"
                            wire:click="exportManuscript('{{ $key }}')"
                            wire:loading.attr="disabled"
                            wire:target="exportManuscript('{{ $key }}')"
                        >{{ $meta['label'] }}</button>
                    @endforeach

                    <span class="rb-mono rb-muted" style="font-size:.7rem; margin-left:auto;">Daftar Rekomendasi Jurnal:</span>
                    <button type="button" class="rb-button" wire:click="exportRecommendations('csv')" title="Unduh daftar rekomendasi sebagai CSV">&#11015; CSV</button>
                    <button type="button" class="rb-button" wire:click="exportRecommendations('markdown')" title="Unduh daftar rekomendasi sebagai Markdown">&#11015; MD</button>
                    <button type="button" class="rb-button" wire:click="exportRecommendations('json')" title="Unduh daftar rekomendasi sebagai JSON">&#11015; JSON</button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
                @forelse ($recommendations as $rec)
                    <div class="rb-window {{ $rec->is_selected ? 'rb-window--target' : '' }}" wire:key="rec-{{ $rec->id }}">
                        <div class="rb-window__titlebar">
                            <span>
                                MATCH {{ number_format((float) $rec->match_score, 1) }}%
                                @if ($rec->is_selected) &middot; &#9733; TARGET @endif
                            </span>
                            <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                        </div>
                        <div class="rb-window__body">
                            <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem;">
                                <div style="font-family:var(--rb-font-display); font-weight:800; font-size:.95rem; color:var(--rb-primary);">
                                    {{ $rec->journal_name }}
                                </div>
                                @if ($rec->is_selected)
                                    <span class="rb-badge rb-badge--pass" style="font-size:.52rem;">&#9733; TARGET</span>
                                @endif
                            </div>
                            <div class="rb-mono rb-muted" style="font-size:.68rem; margin:.3rem 0 .6rem;">
                                {{ $rec->publisher }} &middot;
                                <span class="rb-badge rb-badge--pass">{{ $rec->index_tier }}</span>
                            </div>

                            <p class="rb-mono rb-soft" style="font-size:.68rem; line-height:1.6;">
                                {{ $rec->scope_reasoning }}
                            </p>

                            <div class="rb-kpi-card" style="margin-top:.6rem;">
                                <div class="rb-kpi-card__label">APC ESTIMATE</div>
                                <div class="rb-kpi-card__value" style="font-size:1.1rem;">
                                    {{ $rec->apc_estimate !== null ? number_format((float) $rec->apc_estimate, 0, ',', '.') : 'GRATIS' }}
                                </div>
                            </div>

                            <div style="display:flex; gap:.4rem; margin-top:.7rem; flex-wrap:wrap;">
                                <button type="button" class="rb-button rb-button--primary" wire:click="adaptFormatting({{ $rec->id }})" title="Adaptasikan format naskah ke gaya jurnal ini">
                                    Adapt Formatting
                                </button>
                                <button type="button" class="rb-button" style="background:#4f46e5; color:#ffffff; font-weight:700;" wire:click="applyAdaptedToStudio({{ $rec->id }})" title="Terapkan naskah terformat venue ini langsung ke Naskah Studio">
                                    ⚡ Ke Studio
                                </button>
                                <button type="button" class="rb-button" style="background:#4338ca; color:#ffffff; font-weight:700;" wire:click="executeModelForVenueToStudio({{ $rec->id }})" title="Eksekusi model AI saat ini sesuai panduan venue dan terapkan ke Naskah Studio">
                                    ✨ Eksekusi Model Venue
                                </button>
                                @unless ($rec->is_selected)
                                    <button type="button" class="rb-button" wire:click="selectTarget({{ $rec->id }})" title="Jadikan jurnal target">&#9733; Jadikan Target</button>
                                @endunless
                                @if ($rec->submission_url)
                                    <a href="{{ $rec->submission_url }}" target="_blank" rel="noopener" class="rb-button">&#8599; Submit</a>
                                @endif
                                <button type="button" class="rb-button" style="color:var(--rb-status-alert);" wire:click="deleteRecommendation({{ $rec->id }})" wire:confirm="Hapus rekomendasi ini?">&#128465;</button>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="rb-window">
                        <div class="rb-window__titlebar"><span>BELUM ADA REKOMENDASI</span></div>
                        <div class="rb-window__body">
                            <p class="rb-mono rb-muted" style="font-size:.72rem;">
                                Tekan "Jalankan Venue Matcher" untuk mencocokkan naskah dengan katalog jurnal.
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        @endif
    </div>
</x-filament-panels::page>
