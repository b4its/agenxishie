{{-- Venue Catalog — jelajahi katalog jurnal SINTA/Scopus/WoS --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window" style="margin-bottom:1rem;">
            <div class="rb-window__titlebar">
                <span>[&#9673;] VENUE CATALOG — {{ count($venues) }}/{{ $totalVenues }} JURNAL</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
            </div>
            <div class="rb-window__body" style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:flex-end;">
                <div style="flex:1 1 14rem; min-width:12rem;">
                    <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">PROYEK TARGET</div>
                    <select class="rb-select" wire:model.live="projectId">
                        <option value="">-- Pilih Proyek --</option>
                        @foreach ($projectOptions as $pId => $pTitle)
                            <option value="{{ $pId }}">{{ \Illuminate\Support\Str::limit($pTitle, 36) }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="flex:1 1 14rem; min-width:12rem;">
                    <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">CARI</div>
                    <input type="text" class="rb-input" placeholder="&#128269; Nama jurnal / penerbit / ruang lingkup…" wire:model.live.debounce.300ms="search" />
                </div>
                <div style="width:9rem;">
                    <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">CAKUPAN</div>
                    <select class="rb-select" wire:model.live="scope">
                        <option value="">Semua</option>
                        @foreach ($scopeOptions as $val => $label)
                            <option value="{{ $val }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="width:9rem;">
                    <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">PERINGKAT</div>
                    <select class="rb-select" wire:model.live="tier">
                        <option value="">Semua</option>
                        @foreach ($tierOptions as $tierOpt)
                            <option value="{{ $tierOpt }}">{{ $tierOpt }}</option>
                        @endforeach
                    </select>
                </div>
                <div style="width:10.5rem;">
                    <div class="rb-kpi-card__label" style="margin-bottom:.3rem;">URUTKAN</div>
                    <select class="rb-select" wire:model.live="sortBy">
                        <option value="name">Nama Jurnal (A-Z)</option>
                        <option value="apc_asc">Biaya APC Terendah</option>
                        <option value="review_asc">Review Tercepat</option>
                    </select>
                </div>
                <button type="button" class="rb-button" wire:click="resetFilters">&#8635; Reset</button>
                <button type="button" class="rb-button" wire:click="exportCatalog('csv')" title="Unduh hasil filter katalog jurnal sebagai file CSV">&#128229; Ekspor CSV</button>
                <button type="button" class="rb-button" wire:click="exportCatalog('markdown')" title="Unduh hasil filter katalog jurnal sebagai Markdown">&#128229; Ekspor MD</button>
                <button type="button" class="rb-button" wire:click="exportCatalog('html')" title="Unduh hasil filter katalog jurnal sebagai HTML">&#128229; Ekspor HTML</button>
                <button type="button" class="rb-button" wire:click="exportCatalog('json')" title="Unduh hasil filter katalog jurnal sebagai JSON">&#128229; Ekspor JSON</button>
            </div>
        </div>

        @if (empty($venues))
            <div class="rb-window">
                <div class="rb-window__body">
                    <p class="rb-mono rb-muted" style="font-size:.8rem; margin:0;">Tidak ada jurnal yang cocok dengan filter.</p>
                </div>
            </div>
        @else
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1rem;">
                @foreach ($venues as $venue)
                    <div class="rb-window" wire:key="venue-{{ $venue['id'] }}">
                        <div class="rb-window__titlebar">
                            <span title="{{ $venue['journal_name'] }}">{{ \Illuminate\Support\Str::limit(strtoupper($venue['journal_name']), 38) }}</span>
                            <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                        </div>
                        <div class="rb-window__body">
                            <div style="display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.55rem;">
                                <span class="rb-badge {{ $venue['scope_type'] === 'scopus' ? 'rb-badge--automation' : 'rb-badge--hitl' }}">
                                    {{ strtoupper($venue['scope_type']) }}
                                </span>
                                <span class="rb-badge rb-badge--pass">{{ $venue['index_tier'] }}</span>
                                @if ($venue['field_of_study'])
                                    <span class="rb-badge rb-badge--manual">{{ strtoupper($venue['field_of_study']) }}</span>
                                @endif
                            </div>

                            <div class="rb-mono rb-soft" style="font-size:.66rem; margin-bottom:.5rem;">
                                {{ $venue['publisher'] ?? 'Penerbit tidak diketahui' }}
                            </div>

                            <p class="rb-mono rb-soft" style="font-size:.66rem; line-height:1.55; margin:0 0 .6rem;">
                                {{ \Illuminate\Support\Str::limit($venue['aims_and_scope'], 180) }}
                            </p>

                            <div class="rb-kpi-row" style="grid-template-columns:1fr 1fr; gap:.5rem; margin-bottom:.6rem;">
                                <div class="rb-kpi-card" style="padding:.4rem .5rem;">
                                    <div class="rb-kpi-card__label">APC</div>
                                    <div class="rb-kpi-card__value" style="font-size:.95rem;">
                                        {{ $venue['apc'] !== null && $venue['apc'] > 0 ? 'Rp '.number_format($venue['apc'], 0, ',', '.') : 'GRATIS' }}
                                    </div>
                                </div>
                                <div class="rb-kpi-card" style="padding:.4rem .5rem;">
                                    <div class="rb-kpi-card__label">REVIEW</div>
                                    <div class="rb-kpi-card__value" style="font-size:.95rem;">
                                        {{ $venue['review_time_days'] !== null ? $venue['review_time_days'].' hari' : '—' }}
                                    </div>
                                </div>
                            </div>

                            <div style="display:flex; gap:.35rem; align-items:center; flex-wrap:wrap;">
                                @if ($venue['submission_url'])
                                    <a href="{{ $venue['submission_url'] }}" target="_blank" rel="noopener" class="rb-button" style="font-size:.62rem;">
                                        &#8599; Situs Submission
                                    </a>
                                @endif

                                @if ($projectId !== null)
                                    @if ($selectedVenueId === $venue['id'])
                                        <span class="rb-badge rb-badge--pass" style="font-size:.62rem;">&#10003; TARGET AKTIF</span>
                                    @else
                                        <button
                                            type="button"
                                            class="rb-button rb-button--primary"
                                            style="font-size:.62rem; padding:.25rem .5rem;"
                                            wire:click="setAsProjectTarget({{ $venue['id'] }})"
                                            title="Tetapkan jurnal ini sebagai target proyek aktif"
                                        >
                                            &#9733; Jadikan Target
                                        </button>
                                    @endif
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
