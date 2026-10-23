{{-- Smart Revision Center — checklist, diff, response matrix — tema-aware --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        @if ($project === null)
            <div class="rb-window">
                <div class="rb-window__titlebar"><span>REVISION CENTER</span></div>
                <div class="rb-window__body"><p class="rb-mono rb-muted">Belum ada proyek aktif.</p></div>
            </div>
        @else
            <div class="rb-window" style="margin-bottom:1rem;">
                <div class="rb-window__titlebar">
                    <span>[&#9632;] SMART REVISION CENTER — {{ strtoupper($project->title) }}</span>
                    <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                </div>
                <div class="rb-window__body">
                    {{-- Input multi-sumber: unggah dokumen komentar reviewer (PDF/DOCX) --}}
                    <div style="display:flex; gap:.5rem; align-items:center; margin-bottom:.85rem; flex-wrap:wrap;">
                        <label class="rb-button" style="cursor:pointer;">
                            &#8679; Unggah Dokumen Komentar (PDF/DOCX)
                            <input type="file" accept=".pdf,.docx,application/pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document" wire:model="feedbackUpload" style="display:none;" />
                        </label>
                        <span wire:loading wire:target="feedbackUpload" class="rb-mono rb-muted" style="font-size:.68rem;">
                            Mengekstrak teks...
                        </span>
                        @if ($feedbackImportStatus !== '')
                            <span class="rb-mono {{ str_starts_with($feedbackImportStatus, 'OK') ? 'rb-text-pass' : 'rb-text-alert' }}" style="font-size:.68rem;">
                                {{ $feedbackImportStatus }}
                            </span>
                        @endif
                    </div>

                    <form wire:submit="analyseFeedback">
                        {{ $this->form }}
                        <div style="margin-top:.85rem;">
                            <button type="submit" class="rb-button rb-button--primary">Analisis &amp; Buat Checklist</button>
                        </div>
                    </form>
                </div>
            </div>

            @foreach ($revisions as $revision)
                <div class="rb-window" style="margin-bottom:1rem;">
                    <div class="rb-window__titlebar">
                        <span>REVISI #{{ $revision->id }} — {{ strtoupper($revision->reviewer_role) }} [{{ strtoupper($revision->status->label()) }}]</span>
                        <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                    </div>
                    <div class="rb-window__body">
                        <div class="rb-kpi-row" style="margin-bottom:.85rem;">
                            <div class="rb-kpi-card">
                                <div class="rb-kpi-card__label">RESOLUSI</div>
                                <div class="rb-kpi-card__value {{ $revision->resolutionPercentage() >= 100 ? 'rb-kpi-card__value--pass' : 'rb-kpi-card__value--alert' }}">
                                    {{ number_format($revision->resolutionPercentage(), 0) }}
                                </div>
                                <div class="rb-kpi-card__unit">%</div>
                            </div>
                            <div class="rb-kpi-card">
                                <div class="rb-kpi-card__label">BUTIR TUGAS</div>
                                <div class="rb-kpi-card__value">{{ count((array) $revision->action_items) }}</div>
                                <div class="rb-kpi-card__unit">items</div>
                            </div>
                        </div>

                        <div class="rb-kpi-card__label" style="display:flex; align-items:center; justify-content:space-between; gap:.5rem; flex-wrap:wrap;">
                            <span>ACTIONABLE CHECKLIST</span>
                            <span style="display:flex; gap:.35rem;">
                                <button type="button" class="rb-button" style="padding:.15rem .5rem; font-size:.6rem;" wire:click="reanalyseRevision({{ $revision->id }})" wire:loading.attr="disabled">&#8635; Analisis Ulang</button>
                                <button type="button" class="rb-button rb-button--approve" style="padding:.15rem .5rem; font-size:.6rem;" wire:click="markAllResolved({{ $revision->id }})" wire:loading.attr="disabled">&#10003; Semua Selesai</button>
                                <button type="button" class="rb-button rb-button--danger" style="padding:.15rem .5rem; font-size:.6rem;" wire:click="deleteRevision({{ $revision->id }})" wire:confirm="Hapus revisi ini?" wire:loading.attr="disabled">&#128465; Hapus</button>
                            </span>
                        </div>
                        <ul class="rb-mono" style="font-size:.72rem; line-height:1.9; margin:.4rem 0 1rem; padding-left:.2rem;">
                            @foreach ((array) $revision->action_items as $item)
                                <li style="display:flex; align-items:center; gap:.5rem; margin-bottom:.35rem;">
                                    @if (($item['status'] ?? '') === 'resolved')
                                        <button
                                            type="button"
                                            class="rb-badge rb-badge--pass"
                                            style="cursor:pointer; border:none;"
                                            wire:click="toggleItemStatus({{ $revision->id }}, {{ (int) ($item['id'] ?? 0) }})"
                                            title="Klik untuk membuka kembali"
                                        >&#10003; SELESAI</button>
                                    @else
                                        <button
                                            type="button"
                                            class="rb-badge rb-badge--alert"
                                            style="cursor:pointer; border:none;"
                                            wire:click="toggleItemStatus({{ $revision->id }}, {{ (int) ($item['id'] ?? 0) }})"
                                            title="Klik untuk menandai selesai"
                                        >&#9675; PENDING</button>
                                    @endif
                                    <span style="flex:1;">[{{ $item['section_key'] ?? '-' }}] {{ $item['task'] ?? '' }}</span>
                                    <button
                                        type="button"
                                        class="rb-button"
                                        style="padding:.1rem .45rem; font-size:.6rem;"
                                        wire:click="toggleItemStatus({{ $revision->id }}, {{ (int) ($item['id'] ?? 0) }})"
                                    >
                                        {{ ($item['status'] ?? '') === 'resolved' ? '↺ Buka' : '✓ Selesai' }}
                                    </button>
                                </li>
                            @endforeach
                        </ul>

                        <div class="rb-kpi-card__label">SIDE-BY-SIDE DIFF</div>
                        @foreach ((array) $revision->diff_payload as $diff)
                            <div class="rb-diff" style="margin:.5rem 0;">
                                <div class="rb-diff__pane rb-diff__pane--before">
                                    <div class="rb-diff__header">BEFORE — {{ $diff['section_key'] ?? '-' }}</div>
                                    {{ $diff['before'] ?: '(kosong)' }}
                                </div>
                                <div class="rb-diff__pane rb-diff__pane--after">
                                    <div class="rb-diff__header" style="display:flex; justify-content:space-between; align-items:center;">
                                        <span>AFTER — {{ $diff['section_key'] ?? '-' }}</span>
                                        <button
                                            type="button"
                                            class="rb-button rb-button--primary"
                                            style="padding:.1rem .45rem; font-size:.58rem;"
                                            wire:click="applyRevisionDiff({{ $revision->id }}, '{{ $diff['section_key'] ?? '' }}')"
                                            wire:loading.attr="disabled"
                                            title="Terapkan perbaikan ini langsung ke naskah"
                                        >&#10003; Terapkan Perbaikan</button>
                                    </div>
                                    {{ $diff['after'] ?? '' }}
                                </div>
                            </div>
                        @endforeach

                        <div class="rb-kpi-card__label" style="margin-top:1rem; display:flex; align-items:center; justify-content:space-between; gap:.5rem;">
                            <span>MATRIKS RESPONSE TO REVIEWERS</span>
                            <span style="display:flex; gap:.3rem;">
                                <button
                                    type="button"
                                    class="rb-button"
                                    style="padding:.15rem .5rem; font-size:.6rem;"
                                    wire:click="downloadResponseMatrix({{ $revision->id }}, 'markdown')"
                                    wire:loading.attr="disabled"
                                    title="Unduh matriks sebagai Markdown"
                                >&#11015; MD</button>
                                <button
                                    type="button"
                                    class="rb-button"
                                    style="padding:.15rem .5rem; font-size:.6rem;"
                                    wire:click="downloadResponseMatrix({{ $revision->id }}, 'csv')"
                                    wire:loading.attr="disabled"
                                    title="Unduh matriks sebagai CSV"
                                >&#11015; CSV</button>
                            </span>
                        </div>
                        <pre class="rb-terminal" style="max-height:none; margin-top:.5rem;">{{ $revision->response_matrix_doc }}</pre>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
