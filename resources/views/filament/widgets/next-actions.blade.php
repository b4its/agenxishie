{{-- Dashboard: langkah berikutnya per proyek --}}
<div class="rb-window">
    <div class="rb-window__titlebar">
        <span>[&#9889;] NEXT ACTIONS</span>
        <span class="rb-window__controls">
            <span class="rb-window__control">-</span>
            <span class="rb-window__control">&#9633;</span>
            <span class="rb-window__control">&#215;</span>
        </span>
    </div>
    <div class="rb-window__body">
        @if (empty($items))
            <p class="rb-mono rb-muted" style="font-size:.75rem; margin:0;">Belum ada proyek untuk dipandu.</p>
        @else
            @foreach ($items as $item)
                <div style="margin-bottom:1rem;" wire:key="na-{{ $item['id'] }}">
                    <div style="display:flex; align-items:center; justify-content:space-between; gap:.5rem; margin-bottom:.4rem;">
                        <div class="rb-mono" style="font-weight:700; font-size:.78rem;">
                            {{ \Illuminate\Support\Str::limit($item['title'], 46) }}
                        </div>
                        <span class="rb-badge rb-badge--automation" style="font-size:.52rem;">{{ $item['stage'] }}</span>
                    </div>
                    <ul style="margin:0; padding:0; list-style:none; display:flex; flex-direction:column; gap:.35rem;">
                        @foreach ($item['actions'] as $action)
                            @php
                                $badge = match ($action['severity']) {
                                    'alert' => 'rb-badge--alert',
                                    'warn' => 'rb-badge--hitl',
                                    'pass' => 'rb-badge--pass',
                                    default => 'rb-badge--info',
                                };
                            @endphp
                            <li style="display:flex; align-items:flex-start; gap:.5rem;">
                                <span class="rb-badge {{ $badge }}" style="font-size:.5rem; margin-top:.1rem;">{{ strtoupper($action['severity']) }}</span>
                                <span style="flex:1;">
                                    <a href="{{ $action['url'] }}" class="rb-mono" style="font-size:.72rem; font-weight:600; color:var(--rb-primary); text-decoration:none;">
                                        {{ $action['label'] }} &#8594;
                                    </a>
                                    <span class="rb-mono rb-muted" style="display:block; font-size:.62rem;">{{ $action['detail'] }}</span>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        @endif
    </div>
</div>
