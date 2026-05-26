@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@section('content')
{{-- Stat cards --}}
<div class="stat-grid">
    <div class="stat-card">
        <div class="stat-icon stat-icon-blue">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        </div>
        <div>
            <div class="stat-value">{{ $stats['total_companies'] }}</div>
            <div class="stat-label">Companies</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active_licenses'] }}</div>
            <div class="stat-label">Active Licenses</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-red">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
        </div>
        <div>
            <div class="stat-value">{{ $stats['expired_licenses'] }}</div>
            <div class="stat-label">Expired Licenses</div>
        </div>
    </div>
    <div class="stat-card">
        <div class="stat-icon stat-icon-green">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg>
        </div>
        <div>
            <div class="stat-value">{{ $stats['active_installations'] }}</div>
            <div class="stat-label">Active Installations</div>
        </div>
    </div>
    @if($stats['unreviewed_suspicious'] > 0)
    <div class="stat-card" style="border-color:#fecaca;">
        <div class="stat-icon stat-icon-red">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        </div>
        <div>
            <div class="stat-value" style="color:#dc2626;">{{ $stats['unreviewed_suspicious'] }}</div>
            <div class="stat-label">Suspicious Events</div>
        </div>
    </div>
    @endif
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;">
    {{-- Recent heartbeats --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Recent Heartbeats</span>
            <a href="{{ route('license.installations.index') }}" class="btn btn-secondary btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:0;">
            @php
                $intervalMin = (int) round(($heartbeatInterval ?? 3600) / 60);
                $intervalLabel = $intervalMin >= 60
                    ? round($intervalMin / 60, 1) . ' jam'
                    : $intervalMin . ' menit';
            @endphp
            <div style="padding:.6rem 1rem;background:#f8fafc;border-bottom:1px solid #e2e8f0;font-size:.72rem;color:#64748b;">
                Heartbeat interval: <strong>{{ $intervalLabel }}</strong>
                — countdown menunjukkan waktu sampai heartbeat berikutnya yang diharapkan.
            </div>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Host</th>
                            <th>App</th>
                            <th>Last Seen</th>
                            <th>Next Expected</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentHeartbeats as $inst)
                        @php
                            $secs = $inst->seconds_until_next;
                            $isStale = $inst->is_stale ?? false;
                            // Format countdown for human display
                            if ($secs === null) {
                                $countdownText = '—';
                                $countdownColor = '#94a3b8';
                            } elseif ($secs > 0) {
                                $mins = (int) round($secs / 60);
                                $countdownText = $mins >= 60
                                    ? 'in ' . round($mins / 60, 1) . ' jam'
                                    : 'in ' . $mins . ' menit';
                                $countdownColor = '#16a34a';
                            } elseif ($isStale) {
                                $countdownText = 'overdue ' . abs((int) round($secs / 60)) . ' menit';
                                $countdownColor = '#dc2626';
                            } else {
                                $countdownText = 'due now';
                                $countdownColor = '#d97706';
                            }
                        @endphp
                        <tr @if($isStale) style="background:#fef2f2;" @endif>
                            <td style="font-size:.75rem;">{{ $inst->hostname ?? $inst->domain ?? '—' }}</td>
                            <td><span class="badge badge-info">{{ $inst->app_code }}</span></td>
                            <td style="font-size:.72rem;color:#64748b;">{{ $inst->last_heartbeat_at?->diffForHumans() ?? '—' }}</td>
                            <td
                                class="hb-countdown"
                                data-next="{{ $inst->next_expected_at?->toIso8601String() }}"
                                data-interval="{{ $heartbeatInterval ?? 3600 }}"
                                style="font-size:.72rem;color:{{ $countdownColor }};font-weight:500;font-family:ui-monospace,monospace;"
                            >
                                {{ $countdownText }}
                            </td>
                            <td>
                                @if($isStale)
                                    <span class="badge badge-danger">stale</span>
                                @else
                                    <span class="badge {{ $inst->status === 'active' ? 'badge-success' : 'badge-danger' }}">{{ $inst->status }}</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;">No heartbeats yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Expiring soon --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Expiring Soon</span>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Company</th><th>Label</th><th>Expires</th></tr></thead>
                    <tbody>
                        @forelse($expiringSoon as $lic)
                        <tr>
                            <td style="font-size:.78rem;font-weight:600;">{{ $lic->company?->name ?? '—' }}</td>
                            <td style="font-size:.75rem;color:#64748b;">{{ $lic->label ?? '—' }}</td>
                            <td>
                                <span class="badge {{ $lic->expires_at->diffInDays(now()) <= 7 ? 'badge-danger' : 'badge-warning' }}">
                                    {{ $lic->expires_at->format('d M Y') }}
                                </span>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="3" style="text-align:center;color:#94a3b8;padding:1.5rem;">No licenses expiring soon.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

@if($suspiciousEvents->count())
<div class="card" style="margin-top:1.25rem;border-color:#fecaca;">
    <div class="card-header" style="background:#fef2f2;">
        <span class="card-title" style="color:#991b1b;">⚠ Unreviewed Suspicious Events</span>
        <a href="{{ route('license.installations.index') }}" class="btn btn-danger btn-sm">View All</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Event</th><th>App</th><th>IP</th><th>Severity</th><th>Time</th></tr></thead>
                <tbody>
                    @foreach($suspiciousEvents as $ev)
                    <tr>
                        <td style="font-size:.75rem;">{{ $ev->event_type }}</td>
                        <td><span class="badge badge-info">{{ $ev->app_code ?? '—' }}</span></td>
                        <td style="font-size:.72rem;color:#64748b;">{{ $ev->ip_address ?? '—' }}</td>
                        <td><span class="badge {{ $ev->severity === 'critical' ? 'badge-danger' : 'badge-warning' }}">{{ $ev->severity }}</span></td>
                        <td style="font-size:.72rem;color:#64748b;">{{ $ev->occurred_at->diffForHumans() }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</div>
@endif

{{-- Live countdown ticker for heartbeats — updates every second --}}
<script>
(function () {
    const cells = document.querySelectorAll('.hb-countdown');
    if (cells.length === 0) return;

    function fmt(secs) {
        const abs = Math.abs(secs);
        if (abs < 60)   return secs + 's';
        if (abs < 3600) return Math.round(secs / 60) + 'm';
        return (secs / 3600).toFixed(1) + 'h';
    }

    function tick() {
        const now = Date.now();
        cells.forEach(cell => {
            const next     = cell.dataset.next ? Date.parse(cell.dataset.next) : null;
            const interval = parseInt(cell.dataset.interval || '3600', 10);
            if (! next || isNaN(next)) return;

            const diffSec = Math.floor((next - now) / 1000);
            const stale   = diffSec < -(interval * 0.5);

            if (diffSec > 0) {
                cell.textContent = 'in ' + fmt(diffSec);
                cell.style.color = '#16a34a';
            } else if (stale) {
                cell.textContent = 'overdue ' + fmt(diffSec).replace('-', '');
                cell.style.color = '#dc2626';
            } else {
                cell.textContent = 'due now';
                cell.style.color = '#d97706';
            }
        });
    }

    tick();
    setInterval(tick, 1000);
})();
</script>
@endsection
