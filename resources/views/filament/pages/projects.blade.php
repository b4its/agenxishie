{{-- Projects — manajemen siklus hidup proyek --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window" style="margin-bottom:1rem;">
            <div class="rb-window__titlebar">
                <span>[&#9632;] PROJECTS — {{ count($projects) }} PROYEK</span>
                <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
            </div>
            <div class="rb-window__body" style="display:flex; gap:.6rem; flex-wrap:wrap; align-items:center;">
                <input
                    type="text"
                    class="rb-input"
                    style="flex:1 1 14rem; min-width:12rem;"
                    placeholder="&#128269; Cari proyek (judul / bidang)…"
                    wire:model.live.debounce.300ms="search"
                />
                <select class="rb-select" style="width:9rem;" wire:model.live="filterMode">
                    <option value="">Semua Mode</option>
                    <option value="automation">Automation</option>
                    <option value="hitl">HITL</option>
                    <option value="manual">Manual</option>
                </select>
                <select class="rb-select" style="width:9rem;" wire:model.live="filterStage">
                    <option value="">Semua Tahap</option>
                    <option value="draft">Draft</option>
                    <option value="proposal">Proposal</option>
                    <option value="final">Final</option>
                </select>
                @if ($search !== '' || $filterMode !== '' || $filterStage !== '')
                    <button type="button" class="rb-button" wire:click="resetFilters">&#8635; Reset</button>
                @endif
                <button type="button" class="rb-button" wire:click="exportProjects('csv')" title="Ekspor daftar proyek ke berkas CSV">
                    &#128190; CSV
                </button>
                <button type="button" class="rb-button" wire:click="exportProjects('json')" title="Ekspor daftar proyek ke berkas JSON">
                    &#128196; JSON
                </button>
                <button type="button" class="rb-button rb-button--primary" wire:click="newProject">
                    &#43; Proyek Baru
                </button>
            </div>
        </div>

        @if (empty($projects))
            <div class="rb-window">
                <div class="rb-window__body">
                    <p class="rb-mono rb-muted" style="font-size:.8rem; margin:0 0 .75rem;">
                        {{ $search !== '' ? 'Tidak ada proyek yang cocok dengan pencarian.' : 'Belum ada proyek. Mulai dengan membuat proyek baru.' }}
                    </p>
                    <button type="button" class="rb-button rb-button--primary" wire:click="newProject">&#43; Buat Proyek Pertama</button>
                </div>
            </div>
        @else
            <div style="display:grid; grid-template-columns:repeat(auto-fit, minmax(300px, 1fr)); gap:1rem;">
                @foreach ($projects as $project)
                    <div class="rb-window" wire:key="project-{{ $project['id'] }}">
                        <div class="rb-window__titlebar">
                            <span title="{{ $project['title'] }}">{{ \Illuminate\Support\Str::limit(strtoupper($project['title']), 42) }}</span>
                            <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                        </div>
                        <div class="rb-window__body">
                            <div class="rb-mono rb-soft" style="font-size:.68rem; margin-bottom:.6rem;">
                                {{ $project['field'] }} &middot; {{ $project['document'] }}
                            </div>

                            <div style="display:flex; gap:.35rem; flex-wrap:wrap; margin-bottom:.75rem;">
                                <span class="rb-badge rb-badge--automation">{{ $project['stage'] }}</span>
                                <span class="rb-badge rb-badge--manual">{{ $project['control_mode'] }}</span>
                                <span class="rb-badge {{ $project['compliance'] >= 70 ? 'rb-badge--pass' : 'rb-badge--alert' }}">
                                    {{ number_format($project['compliance'], 0) }}% PATUH
                                </span>
                            </div>

                            <div class="rb-kpi-row" style="margin-bottom:.85rem; grid-template-columns:repeat(3,1fr);">
                                <div class="rb-kpi-card" style="padding:.45rem .55rem;">
                                    <div class="rb-kpi-card__label">REFERENSI</div>
                                    <div class="rb-kpi-card__value" style="font-size:1.2rem;">{{ $project['references'] }}</div>
                                </div>
                                <div class="rb-kpi-card" style="padding:.45rem .55rem;">
                                    <div class="rb-kpi-card__label">REVISI</div>
                                    <div class="rb-kpi-card__value" style="font-size:1.2rem;">{{ $project['revisions'] }}</div>
                                </div>
                                <div class="rb-kpi-card" style="padding:.45rem .55rem;">
                                    <div class="rb-kpi-card__label">JURNAL</div>
                                    <div class="rb-kpi-card__value" style="font-size:1.2rem;">{{ $project['recommendations'] }}</div>
                                </div>
                            </div>

                            <div class="rb-mono rb-muted" style="font-size:.6rem; margin-bottom:.6rem;">
                                Diperbarui: {{ $project['updated_at']?->diffForHumans() ?? '-' }}
                            </div>

                            <div style="display:flex; gap:.4rem; flex-wrap:wrap;" x-data="{ confirmDelete: false }">
                                <button type="button" class="rb-button rb-button--primary" wire:click="openWorkspace({{ $project['id'] }})">
                                    &#9654; Buka Workspace
                                </button>

                                <button type="button" class="rb-button" wire:click="duplicateProject({{ $project['id'] }})" title="Duplikasi Proyek">
                                    &#10697; Duplikasi
                                </button>

                                <template x-if="! confirmDelete">
                                    <button type="button" class="rb-button" style="color:var(--rb-status-alert);" x-on:click="confirmDelete = true">
                                        &#128465; Hapus
                                    </button>
                                </template>
                                <template x-if="confirmDelete">
                                    <span style="display:inline-flex; gap:.35rem;">
                                        <button type="button" class="rb-button rb-button--danger" wire:click="deleteProject({{ $project['id'] }})">
                                            Yakin hapus?
                                        </button>
                                        <button type="button" class="rb-button" x-on:click="confirmDelete = false">Batal</button>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
