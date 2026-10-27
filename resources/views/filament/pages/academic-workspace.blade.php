{{-- Academic Workspace — 3 panel Retro Blue, tema-aware --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        @if ($project === null)
            <div class="rb-window">
                <div class="rb-window__titlebar">
                    <span>[&#9632;] WORKSPACE — EDITOR NASKAH</span>
                    <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                </div>
                <div class="rb-window__body" style="text-align:center; padding:1.75rem 1rem;">
                    <p class="rb-mono rb-soft" style="font-size:.9rem; margin:0 0 .4rem;">
                        Belum ada proyek untuk disunting.
                    </p>
                    <p class="rb-mono rb-muted" style="font-size:.72rem; margin:0 0 1rem;">
                        Editor naskah membutuhkan satu proyek aktif. Buat proyek baru lewat wizard, atau pilih proyek yang sudah ada.
                    </p>
                    <div style="display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap; margin-bottom:1rem;">
                        <a href="{{ \App\Filament\Pages\ProjectOnboardingWizard::getUrl() }}" class="rb-button rb-button--primary">&#43; Buat Proyek Baru (Wizard)</a>
                        <a href="{{ \App\Filament\Pages\ProjectsPage::getUrl() }}" class="rb-button">&#128193; Daftar Proyek</a>
                    </div>

                    @if (! empty($projects) && count($projects) > 0)
                        <div style="max-width:420px; margin:0 auto; text-align:left;">
                            <label class="rb-mono" style="font-size:.68rem; font-weight:700;">ATAU PILIH PROYEK</label>
                            <div style="display:flex; flex-direction:column; gap:.35rem; margin-top:.35rem;">
                                @foreach ($projects as $pick)
                                    <button type="button" class="rb-button" style="justify-content:flex-start; width:100%;" wire:click="switchProject({{ $pick->id }})">
                                        {{ $pick->title }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @else
            {{-- Header: stepper + mode toggle + target badge --}}
            <div class="rb-window" style="margin-bottom:1rem;">
                <div class="rb-window__titlebar">
                    <span>[&#9632;] AGEN XISHIE v1.0 // WORKSPACE — {{ strtoupper($project->title) }}</span>
                    <span class="rb-window__controls">
                        <span class="rb-window__control">-</span>
                        <span class="rb-window__control">&#9633;</span>
                        <span class="rb-window__control">&#215;</span>
                    </span>
                </div>
                <div class="rb-window__body" style="display:flex; flex-wrap:wrap; gap:1rem; align-items:center; justify-content:space-between;">
                    <div class="rb-stepper">
                        @foreach ($progression as $node)
                            @php
                                $class = match ($node['status']) {
                                    'completed' => 'rb-stepper__node--done',
                                    'in_progress', 'waiting_approval' => 'rb-stepper__node--active',
                                    default => 'rb-stepper__node--locked',
                                };
                                $mark = match ($node['status']) {
                                    'completed' => '&#10003;',
                                    'waiting_approval' => '&#9719;',
                                    'in_progress' => '&#9679;',
                                    default => '&#9675;',
                                };
                                $isCurrent = ($node['stage'] === $project->current_stage->value);
                                $isClickable = ($node['status'] !== 'locked');
                            @endphp
                            <button
                                type="button"
                                class="rb-stepper__node {{ $class }}"
                                style="border:none; background:transparent; font-family:inherit; {{ $isClickable ? 'cursor:pointer;' : 'cursor:not-allowed;' }} {{ $isCurrent ? 'box-shadow: 0 0 0 2px var(--rb-accent); border-radius:2px;' : '' }}"
                                @if ($isClickable) wire:click="selectStage('{{ $node['stage'] }}')" title="Beralih ke tahap {{ $node['label'] }}" @else title="Tahap terkunci" disabled @endif
                            >
                                {{ $loop->iteration }}. {{ strtoupper($node['label']) }} {!! $mark !!}
                            </button>
                            @if (! $loop->last)
                                <span class="rb-stepper__arrow">&#8594;</span>
                            @endif
                        @endforeach
                    </div>

                    <div style="display:flex; gap:.5rem; align-items:center; flex-wrap:wrap;">
                        @if ($projects->count() > 1)
                            <select
                                class="rb-select"
                                style="width:auto; min-width:11rem; font-size:.68rem;"
                                wire:change="switchProject($event.target.value)"
                                title="Pindah proyek"
                            >
                                @foreach ($projects as $p)
                                    <option value="{{ $p->id }}" @selected($p->id === $project->id)>{{ \Illuminate\Support\Str::limit($p->title, 40) }}</option>
                                @endforeach
                            </select>
                        @endif
                        <span class="rb-badge rb-badge--manual">TARGET: {{ strtoupper($project->target_tier ?? $project->target_scope->label()) }}</span>
                        <div class="rb-toggle">
                            @foreach (\App\Domain\Workspace\Enums\ControlMode::cases() as $mode)
                                <button
                                    type="button"
                                    class="rb-toggle__item {{ $project->control_mode === $mode ? 'rb-toggle__item--active' : '' }}"
                                    wire:click="switchControlMode('{{ $mode->value }}')"
                                >{{ $mode->shortLabel() }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            {{-- KPI bar --}}
            <div class="rb-kpi-row" style="margin-bottom:1rem;">
                @foreach ([
                    'guideline_compliance_score' => 'COMPLIANCE',
                    'verified_citation_density' => 'CITATION DENSITY',
                    'revision_resolution_velocity' => 'REVISIONS',
                    'journal_match_confidence_score' => 'JOURNAL MATCH',
                    'agent_hallucination_rate' => 'HALLUCINATION',
                ] as $key => $label)
                    @php $m = $metrics[$key] ?? null; @endphp
                    @if ($m)
                        <div class="rb-kpi-card">
                            <div class="rb-kpi-card__label">{{ $label }}</div>
                            <div class="rb-kpi-card__value {{ $m['healthy'] ? 'rb-kpi-card__value--pass' : 'rb-kpi-card__value--alert' }}">
                                {{ number_format((float) $m['value'], 1) }}
                            </div>
                            <div class="rb-kpi-card__unit">{{ $m['unit'] }}</div>
                        </div>
                    @endif
                @endforeach
            </div>

            {{-- 3 Panel --}}
            <div class="rb-workspace-grid">
                {{-- LEFT: Navigator & Riset --}}
                <div class="rb-window">
                    <div class="rb-window__titlebar">
                        <span>NAVIGATOR &amp; RISET</span>
                        <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                    </div>
                    <div class="rb-window__body">
                        <div class="rb-kpi-card__label" style="margin-bottom:.4rem;">STRUKTUR BAB</div>
                        <ul class="rb-mono" style="font-size:.72rem; line-height:1.8; margin-bottom:1rem; padding-left:.2rem;">
                            @forelse ($sections as $section)
                                <li>&#9656; {{ $section['heading'] ?? '(tanpa judul)' }}</li>
                            @empty
                                <li class="rb-muted">&#9675; Belum ada naskah — jalankan pipeline.</li>
                            @endforelse
                        </ul>

                        <div class="rb-kpi-card__label">FITUR RISET</div>
                        <div style="display:flex; flex-direction:column; gap:.4rem; margin-top:.4rem;" x-data="{ openManualRef: false, refTitle: '', refAuthors: '', refYear: {{ now()->year }}, refDoi: '', refJournal: '' }">
                            <button type="button" class="rb-button" wire:click="fetchReferences">+ Cari Paper (SINTA/DOI)</button>

                            <button type="button" class="rb-button" x-on:click="openManualRef = ! openManualRef">+ Input Referensi Manual</button>

                            <div x-show="openManualRef" x-cloak style="border:var(--rb-border-soft); padding:.5rem; background:var(--rb-blueprint); margin:.3rem 0;">
                                <div class="rb-mono" style="font-size:.65rem; font-weight:bold; margin-bottom:.3rem;">INPUT REFERENSI MANUAL</div>
                                <input type="text" x-model="refTitle" placeholder="Judul Paper" class="rb-input" style="width:100%; font-size:.65rem; margin-bottom:.3rem;" />
                                <input type="text" x-model="refAuthors" placeholder="Penulis (pisahkan koma)" class="rb-input" style="width:100%; font-size:.65rem; margin-bottom:.3rem;" />
                                <div style="display:flex; gap:.3rem; margin-bottom:.3rem;">
                                    <input type="number" x-model="refYear" placeholder="Tahun" class="rb-input" style="width:4.5rem; font-size:.65rem;" />
                                    <input type="text" x-model="refJournal" placeholder="Jurnal / Konferensi" class="rb-input" style="flex:1; font-size:.65rem;" />
                                </div>
                                <input type="text" x-model="refDoi" placeholder="DOI (opsional, misal 10.1109/...)" class="rb-input" style="width:100%; font-size:.65rem; margin-bottom:.4rem;" />
                                <div style="display:flex; gap:.3rem; justify-content:flex-end;">
                                    <button type="button" class="rb-button" style="font-size:.6rem; padding:.15rem .4rem;" x-on:click="openManualRef = false">Batal</button>
                                    <button
                                        type="button"
                                        class="rb-button rb-button--primary"
                                        style="font-size:.6rem; padding:.15rem .4rem;"
                                        x-on:click="$wire.addManualReference(refTitle, refAuthors, parseInt(refYear) || {{ now()->year }}, refJournal, refDoi); openManualRef = false; refTitle = ''; refAuthors = '';"
                                    >Simpan</button>
                                </div>
                            </div>

                            <label class="rb-button" style="cursor:pointer;">
                                &#8679; Upload PDF / BibTeX (.bib)
                                <input type="file" accept=".pdf,.bib,application/pdf,text/plain" wire:model="referenceUpload" style="display:none;" />
                            </label>

                            <div x-data="{ openBibtex: false, bibtexCode: '' }">
                                <button type="button" class="rb-button" style="width:100%;" x-on:click="openBibtex = ! openBibtex">+ Tempel Teks BibTeX</button>
                                <div x-show="openBibtex" x-cloak style="border:var(--rb-border-soft); padding:.5rem; background:var(--rb-blueprint); margin-top:.3rem;">
                                    <div class="rb-mono" style="font-size:.65rem; font-weight:bold; margin-bottom:.3rem;">TEMPEL TEKS BIBTEX</div>
                                    <textarea x-model="bibtexCode" placeholder="@article{key, title={...}, author={...}, year={2024}}" rows="4" class="rb-textarea" style="font-size:.65rem;"></textarea>
                                    <div style="display:flex; gap:.3rem; justify-content:flex-end; margin-top:.3rem;">
                                        <button type="button" class="rb-button" style="font-size:.6rem; padding:.15rem .4rem;" x-on:click="openBibtex = false">Batal</button>
                                        <button
                                            type="button"
                                            class="rb-button rb-button--primary"
                                            style="font-size:.6rem; padding:.15rem .4rem;"
                                            x-on:click="$wire.importBibtexText(bibtexCode); openBibtex = false; bibtexCode = '';"
                                        >Impor</button>
                                    </div>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="rb-button"
                                wire:click="exportReferencesBibtex"
                                title="Unduh seluruh daftar pustaka proyek sebagai file BibTeX (.bib)"
                            >
                                &#11015; Unduh Pustaka (.bib)
                            </button>

                            <div wire:loading wire:target="referenceUpload" class="rb-mono rb-muted" style="font-size:.68rem;">
                                Mengimpor referensi...
                            </div>

                            @if ($importStatus !== '')
                                <div class="rb-mono {{ str_starts_with($importStatus, 'OK') ? 'rb-text-pass' : 'rb-text-alert' }}" style="font-size:.68rem;">
                                    {{ $importStatus }}
                                </div>
                            @endif

                            <div class="rb-mono rb-muted" style="font-size:.7rem;">Matriks Literatur: {{ $references->count() }} entri</div>
                        </div>

                        {{-- TAB: Tabel Matriks Literatur (SOTA) --}}
                        <div class="rb-kpi-card__label" style="margin-top:1rem; display:flex; justify-content:space-between; align-items:center;">
                            <span>TABEL MATRIKS LITERATUR</span>
                            <span style="display:flex; gap:.25rem;">
                                <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="exportLiteratureMatrix('docx')" title="Unduh tabel SOTA sebagai Word DOCX">&#11015; DOCX</button>
                                <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="exportLiteratureMatrix('csv')" title="Unduh tabel SOTA sebagai CSV">&#11015; CSV</button>
                                <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="exportLiteratureMatrix('markdown')" title="Unduh tabel SOTA sebagai Markdown">&#11015; MD</button>
                                <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="exportLiteratureMatrix('json')" title="Unduh tabel SOTA sebagai JSON">&#11015; JSON</button>
                            </span>
                        </div>
                        <div style="max-height:13rem; overflow:auto; margin-top:.4rem;">
                            @if (empty($literatureMatrix))
                                <div class="rb-mono rb-muted" style="font-size:.66rem;">
                                    Belum ada entri. Cari paper atau unggah PDF referensi.
                                </div>
                            @else
                                <table class="rb-table">
                                    <thead>
                                        <tr>
                                            <th>PENULIS</th>
                                            <th style="text-align:center;">THN</th>
                                            <th>METODE</th>
                                            <th style="text-align:center;">STATUS</th>
                                            <th style="text-align:center;">AKSI</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($literatureMatrix as $row)
                                            <tr wire:key="ref-{{ $row['id'] }}">
                                                <td title="{{ $row['citation_key'] }}">{{ \Illuminate\Support\Str::limit((string) $row['authors'], 20) }}</td>
                                                <td style="text-align:center;">{{ $row['year'] ?? '-' }}</td>
                                                <td>{{ \Illuminate\Support\Str::limit((string) ($row['method'] ?? '-'), 16) }}</td>
                                                <td style="text-align:center;">
                                                    @if (! empty($row['is_verified']))
                                                        <span class="rb-badge rb-badge--pass" style="font-size:.5rem;">&#10003;</span>
                                                    @else
                                                        <span class="rb-badge rb-badge--manual" style="font-size:.5rem;">?</span>
                                                    @endif
                                                </td>
                                                <td style="text-align:center; white-space:nowrap;">
                                                    <button
                                                        type="button"
                                                        class="rb-button"
                                                        style="padding:0 .35rem; font-size:.55rem;"
                                                        title="{{ ! empty($row['is_verified']) ? 'Batalkan verifikasi' : 'Verifikasi referensi' }}"
                                                        wire:click="toggleReferenceVerified({{ $row['id'] }})"
                                                    >{{ ! empty($row['is_verified']) ? 'unverify' : 'verify' }}</button>
                                                    <button
                                                        type="button"
                                                        class="rb-button"
                                                        style="padding:0 .35rem; font-size:.55rem; color:var(--rb-status-alert);"
                                                        title="Hapus referensi"
                                                        wire:click="deleteReference({{ $row['id'] }})"
                                                        wire:confirm="Hapus referensi ini?"
                                                    >&#10005;</button>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>

                        <div class="rb-kpi-card__label" style="margin-top:1rem;">STATUS PIPA</div>
                        <div style="display:flex; flex-direction:column; gap:.4rem; margin-top:.4rem;">
                            <button type="button" class="rb-button rb-button--primary" wire:click="runPipeline">&#9889; Jalankan Pipeline</button>
                            <button type="button" class="rb-button" wire:click="runMultiAgentGraph">&#129513; Jalankan Multi-Agent Graph</button>
                            <button type="button" class="rb-button" wire:click="advanceStage">Tahap Berikutnya &#8594;</button>
                            <a href="{{ \App\Filament\Pages\NaskahStudioPage::getUrl(['project' => $project->id, 'stage' => $stage->value]) }}" class="rb-button" style="text-align:center; text-decoration:none;" title="Buka dan sunting naskah di WYSIWYG Naskah Studio">&#128394; Buka di Naskah Studio</a>
                        </div>

                        {{-- Export naskah siap kirim (KPI #15) --}}
                        <div class="rb-kpi-card__label" style="margin-top:1rem;">EXPORT NASKAH</div>
                        <div style="display:flex; flex-wrap:wrap; gap:.35rem; margin-top:.4rem;">
                            @foreach ($exportFormats as $key => $meta)
                                <button
                                    type="button"
                                    class="rb-button {{ $key === 'docx' ? 'rb-button--primary' : '' }}"
                                    style="padding:.25rem .5rem; font-size:.62rem;"
                                    wire:click="exportManuscript('{{ $key }}')"
                                    wire:loading.attr="disabled"
                                    wire:target="exportManuscript('{{ $key }}')"
                                >{{ $meta['label'] }}</button>
                            @endforeach
                        </div>
                    </div>
                </div>

                {{-- CENTER: Editor + HITL Gate --}}
                <div class="rb-window">
                    <div class="rb-window__titlebar">
                        <span>EDITOR NASKAH — {{ strtoupper($stageLabel) }}</span>
                        <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                    </div>
                    {{-- Toolbar editor: susun draf lengkap / edit manual / ekstrak jurnal --}}
                    <div style="display:flex; gap:.4rem; flex-wrap:wrap; padding:.5rem .75rem; border-bottom:var(--rb-border-soft); align-items:center;">
                        <button type="button" class="rb-button rb-button--primary" wire:click="generateFullDraft" wire:loading.attr="disabled" wire:target="generateFullDraft">
                            <span wire:loading.remove wire:target="generateFullDraft">&#9889; Susun Draf Lengkap</span>
                            <span wire:loading wire:target="generateFullDraft">Menyusun…</span>
                        </button>
                        <button type="button" class="rb-button" x-on:click="$dispatch('open-manual-edit')" wire:loading.attr="disabled">&#9998; Edit Manual</button>
                        <button
                            type="button"
                            class="rb-button"
                            style="border-color:var(--rb-primary); color:var(--rb-primary);"
                            wire:click="applyCurrentStageToNaskahStudio"
                            wire:loading.attr="disabled"
                            wire:target="applyCurrentStageToNaskahStudio"
                            title="Terapkan dan sinkronkan draf tahap ini ke Naskah Studio"
                        >
                            <span wire:loading.remove wire:target="applyCurrentStageToNaskahStudio">&#9889; Terapkan ke Naskah Studio</span>
                            <span wire:loading wire:target="applyCurrentStageToNaskahStudio">Menerapkan…</span>
                        </button>
                        <button
                            type="button"
                            class="rb-button"
                            style="border-color:#10b981; color:#10b981;"
                            wire:click="executeModelToNaskahStudio"
                            wire:loading.attr="disabled"
                            wire:target="executeModelToNaskahStudio"
                            title="Eksekusi model AI saat ini untuk memperkaya dan menyinkronkan draf ke Naskah Studio"
                        >
                            <span wire:loading.remove wire:target="executeModelToNaskahStudio">&#9889; Eksekusi Model ke Studio</span>
                            <span wire:loading wire:target="executeModelToNaskahStudio">Mengeksekusi…</span>
                        </button>

                        @if ($project->document_type->isThesisFamily())
                            <button
                                type="button"
                                class="rb-button"
                                style="border-color:var(--rb-primary); color:var(--rb-primary);"
                                wire:click="extractJournalManuscript"
                                wire:loading.attr="disabled"
                                wire:target="extractJournalManuscript"
                                title="Ekstrak naskah jurnal IMRaD dari bab skripsi/tesis (PRD KPI #16)"
                            >
                                <span wire:loading.remove wire:target="extractJournalManuscript">&#9889; Ekstrak Naskah Jurnal (IMRaD)</span>
                                <span wire:loading wire:target="extractJournalManuscript">Mengekstrak…</span>
                            </button>
                        @endif

                        @if ($sections !== [])
                            <button
                                type="button"
                                class="rb-button"
                                style="color:var(--rb-status-alert); margin-left:auto; font-size:.65rem;"
                                wire:click="resetStageContent"
                                wire:confirm="Yakin ingin mengosongkan draf naskah tahap ini untuk disusun ulang dari awal?"
                                title="Kosongkan draf tahap aktif untuk susun ulang"
                            >
                                &#128465; Bersihkan Draf
                            </button>
                        @endif

                        @if ($extractedJournalManuscript !== null)
                            <div class="rb-notice rb-notice--pass" style="width:100%; margin-top:.35rem; padding:.35rem .6rem; display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.5rem;">
                                <span class="rb-mono" style="font-size:.66rem;">
                                    <strong>&#10003; Naskah Jurnal IMRaD Tersedia</strong> ({{ number_format((int) ($extractedJournalManuscript['word_count'] ?? 0)) }} kata)
                                </span>
                                <span style="display:flex; gap:.3rem;">
                                    <button type="button" class="rb-button rb-button--primary" style="padding:.1rem .4rem; font-size:.58rem;" wire:click="exportJournalManuscript('docx')">DOCX</button>
                                    <button type="button" class="rb-button" style="padding:.1rem .4rem; font-size:.58rem;" wire:click="exportJournalManuscript('odt')">ODT</button>
                                    <button type="button" class="rb-button" style="padding:.1rem .4rem; font-size:.58rem;" wire:click="exportJournalManuscript('markdown')">MD</button>
                                    <button type="button" class="rb-button" style="padding:.1rem .4rem; font-size:.58rem;" wire:click="exportJournalManuscript('latex')">LaTeX</button>
                                </span>
                            </div>
                        @endif
                    </div>
                    <div class="rb-window__body">
                        @if ($pendingGate !== null)
                            <div class="rb-notice rb-notice--warn" style="margin-bottom:1rem;">
                                <div class="rb-notice__title">[&#129309;] HITL APPROVAL GATE</div>
                                <p class="rb-mono rb-soft" style="font-size:.72rem; margin:.4rem 0;">
                                    {{ $pendingGate['agent'] }} menyelesaikan: {{ $pendingGate['section'] ?? 'sub-bab' }}.
                                    Tinjau sebelum dilanjutkan.
                                </p>
                                <div style="display:flex; gap:.4rem; flex-wrap:wrap;">
                                    <button type="button" class="rb-button rb-button--approve" wire:click="approvePendingSection">&#10003; Terima</button>
                                    <button type="button" class="rb-button" wire:click="requestRegeneration">&#8635; Revisi</button>
                                    <button type="button" class="rb-button" x-on:click="$dispatch('open-manual-edit')">&#9998; Edit Manual</button>
                                </div>
                            </div>
                        @endif

                        {{-- Manual Edit modal --}}
                        <div
                            x-data="{ open: false, draft: '' }"
                            x-on:open-manual-edit.window="open = true; draft = $el.dataset.content || ''"
                            data-content="{{ $stage?->compile() }}"
                        >
                            <template x-if="open">
                                <div style="position:fixed; inset:0; background:color-mix(in srgb, var(--rb-ink) 55%, transparent); z-index:50; display:flex; align-items:center; justify-content:center; padding:1rem;">
                                    <div class="rb-window" style="width:min(760px, 96vw);">
                                        <div class="rb-window__titlebar">
                                            <span>[&#9998;] EDIT NASKAH MANUAL — {{ strtoupper($stageLabel) }}</span>
                                            <span class="rb-window__controls">
                                                <span class="rb-window__control" style="cursor:pointer;" x-on:click="open = false">&#215;</span>
                                            </span>
                                        </div>
                                        <div class="rb-window__body">
                                            <p class="rb-mono rb-muted" style="font-size:.7rem; margin-bottom:.5rem;">
                                                Tulis/sunting naskah. Gunakan penanda "## Judul Sub-bab" untuk memisahkan bagian.
                                            </p>
                                            <textarea x-model="draft" rows="14" class="rb-textarea"></textarea>
                                            <div style="display:flex; gap:.5rem; margin-top:.75rem; justify-content:flex-end;">
                                                <button type="button" class="rb-button" x-on:click="open = false">Batal</button>
                                                <button
                                                    type="button"
                                                    class="rb-button rb-button--approve"
                                                    x-on:click="$wire.saveManualEditText(draft); open = false;"
                                                >&#10003; Simpan</button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>

                        {{-- LIVE GUIDELINE AUDITOR --}}
                        @if (! empty($violationSections))
                            <div class="rb-notice rb-notice--alert" style="margin-bottom:1rem;">
                                <div class="rb-notice__title">[&#9888;] LIVE GUIDELINE AUDITOR — {{ count($violationSections) }} SUB-BAB BERMASALAH</div>
                                <ul class="rb-mono" style="font-size:.66rem; line-height:1.7; margin:.4rem 0 0 .8rem;">
                                    @foreach ($violationSections as $flagged)
                                        <li style="display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:.25rem;">
                                            <span>
                                                <strong>{{ $flagged['heading'] }}</strong> —
                                                {{ implode('; ', $flagged['issues']) }}
                                            </span>
                                            <button
                                                type="button"
                                                class="rb-button rb-button--primary"
                                                style="padding:0 .4rem; font-size:.55rem; white-space:nowrap;"
                                                wire:click="regenerateSingleSection('{{ $flagged['key'] }}')"
                                                wire:loading.attr="disabled"
                                                title="Tulis ulang sub-bab ini secara spesifik agar memenuhi pedoman"
                                            >&#9889; Tulis Ulang</button>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif

                        @php
                            $editorSectionCount = count($editorSections);
                        @endphp

                        {{-- Status bar editor: jumlah sub-bab, total kata, status simpan --}}
                        @if ($editorSectionCount > 0)
                            <div style="display:flex; align-items:center; gap:.6rem; flex-wrap:wrap; margin-bottom:.65rem; font-family:var(--rb-font-mono); font-size:.66rem;">
                                <span class="rb-badge rb-badge--info" style="font-size:.58rem;">{{ $editorSectionCount }} SUB-BAB</span>
                                <span class="rb-badge rb-badge--manual" style="font-size:.58rem;">
                                    {{ number_format((int) collect($editorSections)->sum(fn ($s) => str_word_count(strip_tags((string) ($s['content'] ?? ''))))) }} KATA
                                </span>
                                @if ($editorDirty)
                                    <span class="rb-badge rb-badge--alert" style="font-size:.58rem;">&#9679; BELUM DISIMPAN</span>
                                @else
                                    <span class="rb-badge rb-badge--pass" style="font-size:.58rem;">&#10003; TERSIMPAN</span>
                                @endif
                                <span class="rb-text-muted" style="margin-left:auto;">Simpan terakhir: {{ $editorSavedAt ?? '—' }}</span>
                                <button type="button" class="rb-button rb-button--primary" style="padding:.1rem .5rem; font-size:.6rem;" wire:click="saveEditor" wire:loading.attr="disabled" wire:target="saveEditor">
                                    <span wire:loading.remove wire:target="saveEditor">&#128190; Simpan Naskah</span>
                                    <span wire:loading wire:target="saveEditor">Menyimpan…</span>
                                </button>
                                <button type="button" class="rb-button" style="padding:.1rem .5rem; font-size:.6rem;" wire:click="addEditorSection" title="Tambah sub-bab baru di akhir">&#43; Sub-bab</button>
                            </div>
                        @endif

                        <div class="rb-editor">
                            @forelse ($editorSections as $index => $section)
                                @php
                                    $flagged = collect($violationSections)->firstWhere('key', $section['key'] ?? null);
                                    $sectionWords = str_word_count(strip_tags((string) ($section['content'] ?? '')));
                                @endphp
                                <div style="border:1px solid #CBD5E1; background:#FFFFFF; padding:.6rem .7rem; margin-bottom:.75rem;">
                                    <div style="display:flex; align-items:center; gap:.4rem; flex-wrap:wrap; margin-bottom:.4rem;">
                                        <input
                                            type="text"
                                            class="rb-input"
                                            style="font-family:var(--rb-font-display); font-weight:700; font-size:.85rem; flex:1 1 220px; margin:0;"
                                            wire:model="editorSections.{{ $index }}.heading"
                                            placeholder="Judul sub-bab"
                                            aria-label="Judul sub-bab {{ $index + 1 }}"
                                        />
                                        @if ($flagged)
                                            <span class="rb-badge rb-badge--alert" style="font-size:.55rem;" title="{{ implode('; ', $flagged['issues']) }}">&#9888; MELANGGAR</span>
                                        @endif
                                        <span class="rb-badge rb-badge--manual" style="font-size:.54rem;">{{ $sectionWords }} kata</span>
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="moveEditorSection({{ $index }}, -1)" title="Naikkan urutan" @disabled($index === 0)>&#9650;</button>
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="moveEditorSection({{ $index }}, 1)" title="Turunkan urutan" @disabled($index === $editorSectionCount - 1)>&#9660;</button>
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="duplicateEditorSection({{ $index }})" title="Duplikasi sub-bab ini">&#10697; Duplikasi</button>
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="customSectionParagraphs({{ $index }}, { first_line_indent: true })" title="Kustomisasi format paragraf menjorok">&#8677; Format Paragraf</button>
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="cleanSectionParagraphs({{ $index }})" title="Rapikan spasi berlebih dan tanda baca">&#129529; Rapikan Spasi</button>
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="transformSectionParagraphsCase({{ $index }}, 'sentence')" title="Ubah huruf ke kalimat (Sentence Case)">&#128292; Sentence Case</button>
                                        @if (! empty($section['key']))
                                            <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem;" wire:click="regenerateSingleSection('{{ $section['key'] }}')" wire:loading.attr="disabled" title="Tulis ulang sub-bab ini dengan agen">&#8635; Tulis Ulang</button>
                                        @endif
                                        <button type="button" class="rb-button" style="padding:0 .35rem; font-size:.55rem; color:var(--rb-status-alert);" wire:click="removeEditorSection({{ $index }})" wire:confirm="Hapus sub-bab ini dari naskah?" title="Hapus sub-bab">&#128465;</button>
                                    </div>
                                    <textarea
                                        class="rb-textarea"
                                        style="width:100%; font-size:.85rem; line-height:1.7; min-height:140px;"
                                        wire:model="editorSections.{{ $index }}.content"
                                        wire:change="markEditorDirty"
                                        placeholder="Tulis isi sub-bab di sini…"
                                        aria-label="Isi sub-bab {{ $section['heading'] ?? ($index + 1) }}"
                                    >{{ $section['content'] ?? '' }}</textarea>
                                </div>
                            @empty
                                <div style="text-align:center; padding:1.5rem 1rem;">
                                    <p class="rb-mono rb-soft" style="font-size:.82rem; margin:0 0 .35rem;">
                                        Naskah tahap <strong>{{ $stageLabel }}</strong> belum disusun.
                                    </p>
                                    <p class="rb-mono rb-muted" style="font-size:.7rem; margin:0 0 .9rem;">
                                        Susun seluruh sub-bab secara otomatis, atau tulis manual dari sub-bab kosong.
                                    </p>
                                    <div style="display:flex; gap:.5rem; justify-content:center; flex-wrap:wrap;">
                                        <button type="button" class="rb-button rb-button--primary" wire:click="generateFullDraft" wire:loading.attr="disabled" wire:target="generateFullDraft">
                                            <span wire:loading.remove wire:target="generateFullDraft">&#9889; Susun Draf Lengkap Sekarang</span>
                                            <span wire:loading wire:target="generateFullDraft">Menyusun naskah… (mohon tunggu)</span>
                                        </button>
                                        <button type="button" class="rb-button" wire:click="addEditorSection">&#43; Tulis Manual</button>
                                    </div>
                                </div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- RIGHT: Xishie Assistant + Rekomendasi --}}
                <div class="rb-window">
                    <div class="rb-window__titlebar">
                        <span>XISHIE ASSISTANT</span>
                        <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                    </div>
                    <div class="rb-window__body">
                        <span class="rb-badge rb-badge--{{ $project->control_mode->value === 'automation' ? 'automation' : ($project->control_mode->value === 'hitl' ? 'hitl' : 'manual') }}">
                            OMNIGENSI: {{ $project->control_mode->shortLabel() }}
                        </span>

                        <div class="rb-terminal" style="margin-top:.6rem; max-height:10rem;">
                            @foreach ($agentLogs as $log)
                                <div class="rb-terminal__line" style="font-size:.66rem;">
                                    <span class="rb-terminal__time">[{{ $log->created_at?->format('H:i:s') }}]</span>
                                    <span class="rb-terminal__agent">{{ $log->agent_name }}</span> — {{ $log->action }}
                                </div>
                            @endforeach
                        </div>

                        <form wire:submit="sendAssistantMessage" style="margin-top:.75rem; display:flex; gap:.35rem;">
                            <input type="text" class="rb-input" style="flex:1;" placeholder="Tanya Xishie..." wire:model="assistantMessage" />
                            <button type="submit" class="rb-button rb-button--primary">Kirim</button>
                        </form>

                        <div style="display:flex; gap:.35rem; margin-top:.5rem; flex-wrap:wrap;">
                            <button type="button" class="rb-button" wire:click="auditGuideline">&#128269; Audit Pedoman</button>
                            <button type="button" class="rb-button" wire:click="runPipeline">&#9889; Jalankan Agen</button>
                        </div>

                        <div style="margin-top:.75rem;">
                            @foreach (array_slice($assistantLog, -4) as $chat)
                                <div class="rb-mono" style="font-size:.68rem; margin-bottom:.35rem;">
                                    <strong>{{ $chat['role'] === 'user' ? 'YOU' : 'XISHIE' }}:</strong> {{ $chat['content'] }}
                                </div>
                            @endforeach
                        </div>

                        <div class="rb-kpi-card__label" style="margin-top:1rem;">REKOMENDASI JURNAL</div>
                        <ul class="rb-mono" style="font-size:.68rem; line-height:1.7; padding-left:.2rem;">
                            @forelse ($recommendations as $rec)
                                <li>{{ $loop->iteration }}. {{ $rec->journal_name }} ({{ number_format((float) $rec->match_score, 1) }}%)</li>
                            @empty
                                <li class="rb-muted">&#9675; Belum ada — tekan Match Venue.</li>
                            @endforelse
                        </ul>
                        <button type="button" class="rb-button" style="margin-top:.4rem;" wire:click="matchVenues">Cocokkan Venue</button>

                        <div class="rb-kpi-card__label" style="margin-top:1rem;">KEPUASAN (CSAT 1-5)</div>
                        <div style="display:flex; gap:.3rem; margin-top:.35rem;">
                            @for ($star = 1; $star <= 5; $star++)
                                <button
                                    type="button"
                                    class="rb-button"
                                    style="padding:.15rem .5rem;"
                                    wire:click="rateSatisfaction({{ $star }})"
                                    title="Beri rating {{ $star }}"
                                >{{ $star }}</button>
                            @endfor
                        </div>
                        <div class="rb-mono rb-muted" style="font-size:.65rem; margin-top:.25rem;">
                            CSAT saat ini: {{ number_format((float) ($metrics['user_satisfaction_score']['value'] ?? 0), 1) }} / 5
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
