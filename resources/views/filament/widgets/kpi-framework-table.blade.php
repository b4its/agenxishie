{{-- Tabel metrik per framework (HEART / AARRR) — tema-aware --}}
<div class="rb-window">
    <div class="rb-window__titlebar" style="display:flex; justify-content:space-between; align-items:center;">
        <span>{{ $title }}</span>
        <div style="display:flex; gap:.4rem; align-items:center;">
            @if (! empty($projectOptions))
                <select class="rb-select" style="font-size:.6rem; padding:.1rem .3rem; height:auto; color:#000;" wire:model.live="projectId">
                    @foreach ($projectOptions as $pid => $ptitle)
                        <option value="{{ $pid }}">{{ \Illuminate\Support\Str::limit($ptitle, 20) }}</option>
                    @endforeach
                </select>
            @endif
            <span class="rb-window__controls">
                <span class="rb-window__control">-</span>
                <span class="rb-window__control">&#9633;</span>
                <span class="rb-window__control">&#215;</span>
            </span>
        </div>
    </div>
    <div class="rb-window__body">
        @if ($rows === [])
            <p class="rb-mono rb-muted" style="font-size:.72rem;">Belum ada data telemetri.</p>
        @else
            <table class="rb-table">
                <tbody>
                    @foreach ($rows as $row)
                        <tr>
                            <td>{{ $row['label'] }}</td>
                            <td style="text-align:right;">
                                <span class="rb-badge {{ $row['healthy'] ? 'rb-badge--pass' : 'rb-badge--alert' }}">
                                    {{ number_format((float) $row['value'], 1) }} {{ $row['unit'] }}
                                </span>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
