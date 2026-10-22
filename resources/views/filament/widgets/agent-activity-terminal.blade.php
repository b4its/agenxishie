{{-- Retro Terminal — live activity log agen Omnigensi --}}
<div class="rb-window">
    <div class="rb-window__titlebar">
        <span>OMNIGENSI AGENT TERMINAL</span>
        <span class="rb-window__controls">
            <span class="rb-window__control">-</span>
            <span class="rb-window__control">&#9633;</span>
            <span class="rb-window__control">&#215;</span>
        </span>
    </div>
    <div class="rb-window__body" style="padding:0;">
        <div class="rb-terminal" wire:poll.5s>
            @forelse ($lines as $line)
                <div class="rb-terminal__line">
                    <span class="rb-terminal__time">[{{ $line['time'] }}]</span>
                    <span class="rb-terminal__agent">{{ $line['agent'] }}</span>
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
