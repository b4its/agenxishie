{{-- Academic Workspace — 3 panel Retro Blue --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        @if ($project === null)
            <div class="rb-window">
                <div class="rb-window__titlebar"><span>WORKSPACE</span></div>
                <div class="rb-window__body">
                    <p class="rb-mono">Belum ada proyek. Jalankan Project Onboarding Wizard terlebih dahulu.</p>
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
                            @endphp
                            <span class="rb-stepper__node {{ $class }}">{{ $loop->iteration }}. {{ strtoupper($node['label']) }} {!! $mark !!}</span>
                            @if (! $loop->last)
                                <span class="rb-stepper__arrow">&#8594;</span>
                            @endif
                        @endforeach
                    </div>

                    <div style="display:flex; gap:.5rem; align-items:center;">
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
            <div style="display:grid; grid-template-columns: 1fr 1.8fr 1fr; gap:1rem;">
                {{-- LEFT: Navigator & Riset --}}
                <div class="rb-window">
                    <div class="rb-window__titlebar">
                        <span>NAVIGATOR &amp; RISET</span>
                        <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                    </div>
                    <div class="rb-window__body">
                        <div class="rb-kpi-card__label" style="margin-bottom:.4rem;">STRUKTUR BAB</div>
                        <ul class="rb-mono" style="font-size:.72rem; line-height:1.8; margin-bottom:1rem;">
                            @forelse ($sections as $section)
                                <li>&#9656; {{ $section['heading'] ?? '(tanpa judul)' }}</li>
                            @empty
                                <li>&#9675; Belum ada naskah — jalankan pipeline.</li>
                            @endforelse
                        </ul>

                        <div class="rb-kpi-card__label">FITUR RISET</div>
                        <div style="display:flex; flex-direction:column; gap:.4rem; margin-top:.4rem;">
                            <button type="button" class="rb-button" wire:click="fetchReferences">+ Cari Paper (SINTA/DOI)</button>

                            <label class="rb-button" style="cursor:pointer; justify-content:center;">
                                &#8679; Upload PDF Referensi
                                <input type="file" accept="application/pdf" wire:model="referenceUpload" style="display:none;" />
                            </label>

                            <div wire:loading wire:target="referenceUpload" class="rb-mono" style="font-size:.68rem;">
                                Mengimpor &amp; mengekstrak PDF...
                            </div>

                            @if ($importStatus !== '')
                                <div class="rb-mono" style="font-size:.68rem; color:{{ str_starts_with($importStatus, 'OK') ? '#047857' : '#b91c1c' }};">
                                    {{ $importStatus }}
                                </div>
                            @endif

                            <div class="rb-mono" style="font-size:.7rem;">Matriks Literatur: {{ $references->count() }} entri</div>
                        </div>

                        <div class="rb-kpi-card__label" style="margin-top:1rem;">STATUS PIPA</div>
                        <div style="display:flex; flex-direction:column; gap:.4rem; margin-top:.4rem;">
                            <button type="button" class="rb-button rb-button--primary" wire:click="runPipeline">&#9889; Jalankan Pipeline</button>
                            <button type="button" class="rb-button" wire:click="advanceStage">Tahap Berikutnya &#8594;</button>
                        </div>
                    </div>
                </div>

                {{-- CENTER: Editor + HITL Gate --}}
                <div class="rb-window">
                    <div class="rb-window__titlebar">
                        <span>EDITOR NASKAH — {{ strtoupper($stageLabel) }}</span>
                        <span class="rb-window__controls"><span class="rb-window__control">-</span><span class="rb-window__control">&#9633;</span><span class="rb-window__control">&#215;</span></span>
                    </div>
                    <div class="rb-window__body">
                        @if ($pendingGate !== null)
                            <div class="rb-approval-gate" style="margin-bottom:1rem;">
                                <div class="rb-approval-gate__title">[&#129309;] HITL APPROVAL GATE</div>
                                <p class="rb-mono" style="font-size:.72rem; margin:.4rem 0;">
                                    {{ $pendingGate['agent'] }} menyelesaikan: {{ $pendingGate['section'] ?? 'sub-bab' }}.
                                    Tinjau sebelum dilanjutkan.
                                </p>
                                <div style="display:flex; gap:.4rem;">
                                    <button type="button" class="rb-button rb-button--approve" wire:click="approvePendingSection">&#10003; Terima</button>
                                    <button type="button" class="rb-button" wire:click="requestRegeneration">&#8635; Revisi</button>
                                    <button type="button" class="rb-button" x-on:click="$dispatch('open-manual-edit')">&#9998; Edit Manual</button>
                                </div>
                            </div>
                        @endif

                        {{-- Manual Edit modal: sunting naskah langsung lalu simpan --}}
                        <div
                            x-data="{ open: false, draft: '' }"
                            x-on:open-manual-edit.window="open = true; draft = $el.dataset.content || ''"
                            data-content="{{ $stage?->compile() }}"
                        >
                            <template x-if="open">
                                <div style="position:fixed; inset:0; background:rgba(15,23,42,.55); z-index:50; display:flex; align-items:center; justify-content:center; padding:1rem;">
                                    <div class="rb-window" style="width:min(760px, 96vw);">
                                        <div class="rb-window__titlebar">
                                            <span>[&#9998;] EDIT NASKAH MANUAL — {{ strtoupper($stageLabel) }}</span>
                                            <span class="rb-window__controls">
                                                <span class="rb-window__control" style="cursor:pointer;" x-on:click="open = false">&#215;</span>
                                            </span>
                                        </div>
                                        <div class="rb-window__body">
                                            <p class="rb-mono" style="font-size:.7rem; margin-bottom:.5rem;">
                                                Tulis/sunting naskah. Gunakan penanda "## Judul Sub-bab" untuk memisahkan bagian.
                                            </p>
                                            <textarea
                                                x-model="draft"
                                                rows="14"
                                                class="rb-mono"
                                                style="width:100%; border:2px solid #0f172a; padding:.6rem; font-size:.78rem; line-height:1.6;"
                                            ></textarea>
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

                        <div class="rb-editor">
                            @forelse ($sections as $section)
                                <h3>{{ $section['heading'] ?? '' }}</h3>
                                <p>{{ $section['content'] ?? '' }}</p>
                            @empty
                                <p class="rb-mono" style="font-size:.75rem;">Naskah kosong. Mode Manual: tulis langsung, atau jalankan pipeline.</p>
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

                        <div style="margin-top:.6rem; max-height:10rem; overflow-y:auto;" class="rb-mono">
                            @foreach ($agentLogs as $log)
                                <div style="font-size:.66rem; line-height:1.6;">
                                    <span style="color:#64748b;">[{{ $log->created_at?->format('H:i:s') }}]</span>
                                    <strong>{{ $log->agent_name }}</strong> — {{ $log->action }}
                                </div>
                            @endforeach
                        </div>

                        <form wire:submit="sendAssistantMessage" style="margin-top:.75rem; display:flex; gap:.35rem;">
                            <input type="text" class="rb-mono" style="flex:1; border:1.5px solid #0f172a; padding:.35rem; font-size:.72rem;" placeholder="Tanya Xishie..." wire:model="assistantMessage" />
                            <button type="submit" class="rb-button rb-button--primary">Kirim</button>
                        </form>

                        <div style="margin-top:.75rem;">
                            @foreach (array_slice($assistantLog, -4) as $chat)
                                <div class="rb-mono" style="font-size:.68rem; margin-bottom:.35rem;">
                                    <strong>{{ $chat['role'] === 'user' ? 'YOU' : 'XISHIE' }}:</strong> {{ $chat['content'] }}
                                </div>
                            @endforeach
                        </div>

                        <div class="rb-kpi-card__label" style="margin-top:1rem;">REKOMENDASI JURNAL</div>
                        <ul class="rb-mono" style="font-size:.68rem; line-height:1.7;">
                            @forelse ($recommendations as $rec)
                                <li>{{ $loop->iteration }}. {{ $rec->journal_name }} ({{ number_format((float) $rec->match_score, 1) }}%)</li>
                            @empty
                                <li>&#9675; Belum ada — tekan Match Venue.</li>
                            @endforelse
                        </ul>
                        <button type="button" class="rb-button" style="margin-top:.4rem;" wire:click="matchVenues">Cocokkan Venue</button>

                        <div class="rb-kpi-card__label" style="margin-top:1rem;">KEPUASAN (CSAT 1-5)</div>
                        <div style="display:flex; gap:.3rem; margin-top:.35rem;">
                            @for ($star = 1; $star <= 5; $star++)
                                <button
                                    type="button"
                                    class="rb-button"
                                    style="padding:.15rem .45rem;"
                                    wire:click="rateSatisfaction({{ $star }})"
                                    title="Beri rating {{ $star }}"
                                >{{ $star }}</button>
                            @endfor
                        </div>
                        <div class="rb-mono" style="font-size:.65rem; margin-top:.25rem;">
                            CSAT saat ini: {{ number_format((float) ($metrics['user_satisfaction_score']['value'] ?? 0), 1) }} / 5
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-panels::page>
