{{-- Tabel metrik per framework (HEART / AARRR) --}}
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
            <p class="rb-mono" style="font-size:.7rem;">Belum ada data telemetri.</p>
        @else
            <table style="width:100%; border-collapse:collapse;" class="rb-mono">
                <tbody>
                    @foreach ($rows as $row)
                        <tr style="border-bottom:1px solid #e2e8f0;">
                            <td style="padding:.35rem 0; font-size:.7rem;">{{ $row['label'] }}</td>
                            <td style="padding:.35rem 0; text-align:right; font-size:.75rem; font-weight:700;">
                                <span class="{{ $row['healthy'] ? 'rb-badge rb-badge--pass' : 'rb-badge rb-badge--alert' }}">
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
