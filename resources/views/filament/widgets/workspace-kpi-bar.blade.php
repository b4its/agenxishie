{{-- Workspace KPI bar — Retro Blue, tema-aware --}}
@if ($project === null)
    <div class="rb-window">
        <div class="rb-window__titlebar">
            <span>WORKSPACE TELEMETRY</span>
            <span class="rb-window__controls">
                <span class="rb-window__control">-</span>
                <span class="rb-window__control">&#9633;</span>
                <span class="rb-window__control">&#215;</span>
            </span>
        </div>
        <div class="rb-window__body">
            <p class="rb-mono rb-muted" style="font-size:.75rem;">Belum ada proyek. Mulai dari Project Onboarding Wizard.</p>
        </div>
    </div>
@else
    <div class="rb-window">
        <div class="rb-window__titlebar">
            <span>WORKSPACE TELEMETRY — {{ strtoupper($project->document_type->label()) }}</span>
            <span class="rb-window__controls">
                <span class="rb-window__control">-</span>
                <span class="rb-window__control">&#9633;</span>
                <span class="rb-window__control">&#215;</span>
            </span>
        </div>
        <div class="rb-window__body">
            <div class="rb-kpi-row">
                @foreach ($cards as $card)
                    @php
                        $tone = $card['healthy'] ? 'rb-kpi-card__value--pass' : 'rb-kpi-card__value--alert';
                    @endphp
                    <div class="rb-kpi-card">
                        <div class="rb-kpi-card__label">{{ $card['label'] }}</div>
                        <div class="rb-kpi-card__value {{ $tone }}">
                            {{ number_format((float) $card['value'], 1) }}
                        </div>
                        <div class="rb-kpi-card__unit">{{ $card['unit'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
@endif
