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
                    <button type="button" class="rb-button rb-button--primary" wire:click="runMatcher">
                        &#9889; Jalankan Venue Matcher
                    </button>
                </div>
            </div>

            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(280px, 1fr)); gap:1rem;">
                @forelse ($recommendations as $rec)
                    <div class="rb-window">
                        <div class="rb-window__titlebar">
                            <span>MATCH {{ number_format((float) $rec->match_score, 1) }}%</span>
                            <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                        </div>
                        <div class="rb-window__body">
                            <div style="font-family:var(--rb-font-display); font-weight:800; font-size:.95rem; color:var(--rb-primary);">
                                {{ $rec->journal_name }}
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
                                <button type="button" class="rb-button rb-button--primary" wire:click="adaptFormatting({{ $rec->id }})">
                                    Adapt Formatting
                                </button>
                                @if ($rec->submission_url)
                                    <a href="{{ $rec->submission_url }}" target="_blank" rel="noopener" class="rb-button">&#8599; Submit</a>
                                @endif
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
