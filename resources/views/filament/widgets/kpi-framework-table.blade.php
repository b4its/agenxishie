{{-- Tabel metrik per framework (HEART / AARRR) — tema-aware --}}
<div class="rb-window">
    <div class="rb-window__titlebar">
        <span>{{ $title }}</span>
        <span class="rb-window__controls">
            <span class="rb-window__control">-</span>
            <span class="rb-window__control">&#9633;</span>
            <span class="rb-window__control">&#215;</span>
        </span>
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
