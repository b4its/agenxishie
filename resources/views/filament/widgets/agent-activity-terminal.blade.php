{{-- Retro Terminal — live activity log agen Omnigensi --}}
<div class="rb-window">
    <div class="rb-window__titlebar" style="display:flex; justify-content:space-between; align-items:center;">
        <span>OMNIGENSI AGENT TERMINAL ({{ $totalLogs }} LOGS)</span>
        <div style="display:flex; gap:.4rem; align-items:center;">
            <select class="rb-select" style="font-size:.6rem; padding:.1rem .3rem; height:auto; color:#000;" wire:model.live="projectId">
                <option value="">Semua Proyek (Live)</option>
                @foreach ($projectOptions as $pid => $ptitle)
                    <option value="{{ $pid }}">{{ \Illuminate\Support\Str::limit($ptitle, 25) }}</option>
                @endforeach
            </select>
            <button type="button" class="rb-button" style="padding:.1rem .35rem; font-size:.58rem;" wire:click="exportLogs('csv')" title="Unduh log agen sebagai CSV">&#11015; CSV</button>
            <button type="button" class="rb-button" style="padding:.1rem .35rem; font-size:.58rem;" wire:click="exportLogs('json')" title="Unduh log agen sebagai JSON">&#11015; JSON</button>
            <span class="rb-window__controls">
                <span class="rb-window__control">-</span>
                <span class="rb-window__control">&#9633;</span>
                <span class="rb-window__control">&#215;</span>
            </span>
        </div>
    </div>
    <div class="rb-window__body" style="padding:0;">
        <div class="rb-terminal" wire:poll.5s>
            @forelse ($lines as $line)
                <div class="rb-terminal__line">
                    <span class="rb-terminal__time">[{{ $line['time'] }}]</span>
                    <span class="rb-terminal__agent">{{ $line['agent'] }}</span>
                    @if ($line['project_title'])
                        <span class="rb-mono" style="color:var(--rb-muted); font-size:.65rem;">[{{ \Illuminate\Support\Str::limit($line['project_title'], 18) }}]</span>
                    @endif
                    — {{ $line['action'] }}
                    @if ($line['ttft'])
                        <span class="rb-terminal__time">({{ $line['ttft'] }}ms)</span>
                    @endif
                </div>
            @empty
                <div class="rb-terminal__line">
                    <span class="rb-terminal__time">[--:--:--]</span>
                    standby — belum ada aktivitas agen.
                </div>
            @endforelse
        </div>
    </div>
</div>
