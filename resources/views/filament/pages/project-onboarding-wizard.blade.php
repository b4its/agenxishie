{{-- Project Onboarding Wizard — Retro Blue 4 langkah --}}
<x-filament-panels::page>
    <div class="rb-canvas" style="padding:1rem;">
        <div class="rb-window">
            <div class="rb-window__titlebar">
                <span>[&#9632;] AGEN XISHIE // PROJECT ONBOARDING — STEP {{ $step }} / 4</span>
                <span class="rb-window__controls">
                    <span class="rb-window__control">-</span>
                    <span class="rb-window__control">&#9633;</span>
                    <span class="rb-window__control">&#215;</span>
                </span>
            </div>
            <div class="rb-window__body">
                <div class="rb-stepper" style="margin-bottom:1.25rem;">
                    @foreach ([1 => 'JUDUL & BIDANG', 2 => 'TOPIK & TARGET', 3 => 'PANDUAN', 4 => 'BASIS KENDALI'] as $index => $label)
                        <span class="rb-stepper__node {{ $step === $index ? 'rb-stepper__node--active' : ($step > $index ? 'rb-stepper__node--done' : 'rb-stepper__node--locked') }}">
                            {{ $index }}. {{ $label }}
                        </span>
                        @if (! $loop->last)
                            <span class="rb-stepper__arrow">&#8594;</span>
                        @endif
                    @endforeach
                </div>

                @if ($step === 1)
                    <div style="margin-bottom:1rem; padding:.6rem; border:var(--rb-border-soft); background:var(--rb-blueprint);">
                        <div class="rb-mono" style="font-size:.65rem; font-weight:bold; margin-bottom:.4rem;">⚡ TEMPLATE &amp; INSPIRASI TOPIK CEPAT:</div>
                        <div style="display:flex; gap:.35rem; flex-wrap:wrap;">
                            <button type="button" class="rb-button" style="font-size:.62rem; padding:.2rem .45rem;" wire:click="applyTemplate('ai_iot')">IoT &amp; AI (Scopus Q2)</button>
                            <button type="button" class="rb-button" style="font-size:.62rem; padding:.2rem .45rem;" wire:click="applyTemplate('nlp_health')">NLP Biomedis (Scopus Q1)</button>
                            <button type="button" class="rb-button" style="font-size:.62rem; padding:.2rem .45rem;" wire:click="applyTemplate('cyber_security')">Keamanan Cloud (SINTA 2)</button>
                            <button type="button" class="rb-button" style="font-size:.62rem; padding:.2rem .45rem;" wire:click="applyTemplate('social_education')">AI Pendidikan (Tesis SINTA 2)</button>
                        </div>
                    </div>
                @endif

                <form wire:submit="createProject">
                    {{ $this->form }}

                    <div style="display:flex; gap:.5rem; margin-top:1.25rem; flex-wrap:wrap;">
                        @if ($step > 1)
                            <button type="button" class="rb-button" wire:click="previousStep">&#8592; Kembali</button>
                        @endif

                        @if ($step < 4)
                            <button type="button" class="rb-button rb-button--primary" wire:click="nextStep">Lanjut &#8594;</button>
                        @else
                            <button type="submit" class="rb-button rb-button--approve">&#9889; RUN XISHIE AUTO-PILOT</button>
                        @endif
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-filament-panels::page>
