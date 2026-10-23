{{-- Dashboard: ringkasan seluruh proyek --}}
<div class="rb-window">
    <div class="rb-window__titlebar">
        <span>[&#9632;] PROJECTS OVERVIEW — {{ $total }} PROYEK</span>
        <span class="rb-window__controls">
            <span class="rb-window__control">-</span>
            <span class="rb-window__control">&#9633;</span>
            <span class="rb-window__control">&#215;</span>
        </span>
    </div>
    <div class="rb-window__body">
        @if (empty($rows))
            <p class="rb-mono rb-muted" style="font-size:.75rem; margin:0;">
                Belum ada proyek. Mulai dari <a href="/admin/project-onboarding-wizard" style="color:var(--rb-primary);">Project Onboarding Wizard</a>.
            </p>
        @else
            <div style="overflow:auto;">
                <table class="rb-table">
                    <thead>
                        <tr>
                            <th>PROYEK</th>
                            <th style="text-align:center;">TAHAP</th>
                            <th style="text-align:center;">MODE</th>
                            <th style="text-align:center;">PATUH</th>
                            <th style="text-align:center;">REF</th>
                            <th style="text-align:center;">DIPERBARUI</th>
                            <th style="text-align:center;">AKSI</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="ov-{{ $row['id'] }}">
                                <td title="{{ $row['title'] }}">{{ \Illuminate\Support\Str::limit($row['title'], 42) }}</td>
                                <td style="text-align:center;"><span class="rb-badge rb-badge--automation" style="font-size:.55rem;">{{ $row['stage'] }}</span></td>
                                <td style="text-align:center;"><span class="rb-badge rb-badge--manual" style="font-size:.55rem;">{{ $row['control_mode'] }}</span></td>
                                <td style="text-align:center;">
                                    <span class="rb-badge {{ $row['compliance'] >= 70 ? 'rb-badge--pass' : 'rb-badge--alert' }}" style="font-size:.55rem;">
                                        {{ number_format($row['compliance'], 0) }}%
                                    </span>
                                </td>
                                <td style="text-align:center;">{{ $row['references'] }}</td>
                                <td style="text-align:center;" class="rb-muted">{{ $row['updated_at']?->diffForHumans(short: true) ?? '-' }}</td>
                                <td style="text-align:center;">
                                    <a href="{{ $row['url'] }}" class="rb-button" style="padding:.1rem .5rem; font-size:.6rem; text-decoration:none;">Buka &#8594;</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
