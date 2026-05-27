@extends('layouts.app')
@section('title', 'Dashboard')
@section('page-title', 'Dashboard')

@push('styles')
<style>
    /* ── Dashboard-specific UI polish ─────────────────────────────── */
    .dash-section-title {
        font-size: .68rem;
        text-transform: uppercase;
        letter-spacing: .08em;
        color: #64748b;
        font-weight: 700;
        margin: 0 0 .65rem .15rem;
        display: flex;
        align-items: center;
        gap: .4rem;
    }
    .dash-section-title svg { width: 14px; height: 14px; }

    .stat-card-link {
        text-decoration: none;
        color: inherit;
        transition: all .15s;
    }
    .stat-card-link:hover .stat-card {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px -8px rgba(15,23,42,.18);
        border-color: #cbd5e1;
    }
    .stat-card { transition: all .15s; }

    .stat-trend {
        font-size: .65rem;
        color: #64748b;
        margin-top: .25rem;
        display: flex; align-items: center; gap: .25rem;
    }
    .stat-trend.danger { color: #dc2626; }
    .stat-trend.warning { color: #d97706; }
    .stat-trend.success { color: #16a34a; }

    /* Two-column main grid */
    .dash-grid {
        display: grid;
        grid-template-columns: 1.4fr 1fr;
        gap: 1.25rem;
    }
    @media (max-width: 1100px) {
        .dash-grid { grid-template-columns: 1fr; }
    }

    /* ── Renewal cards (replaces flat table) ── */
    .renewal-list { display: flex; flex-direction: column; }
    .renewal-row {
        display: flex;
        gap: .9rem;
        padding: .85rem 1.25rem;
        border-bottom: 1px solid #e2e8f0;
        align-items: center;
        transition: background .12s;
    }
    .renewal-row:last-child { border-bottom: 0; }
    .renewal-row:hover { background: #f8fafc; }

    .renewal-icon {
        width: 38px; height: 38px;
        border-radius: 10px;
        display: flex; align-items: center; justify-content: center;
        flex-shrink: 0;
        font-weight: 700;
        font-size: .9rem;
    }
    .renewal-icon-expired  { background: #fee2e2; color: #991b1b; }
    .renewal-icon-critical { background: #fef2f2; color: #dc2626; }
    .renewal-icon-warning  { background: #fef3c7; color: #92400e; }
    .renewal-icon-soon     { background: #dbeafe; color: #1e40af; }
    .renewal-icon-safe     { background: #dcfce7; color: #166534; }

    .renewal-meta { flex: 1; min-width: 0; }
    .renewal-name {
        font-weight: 600;
        font-size: .82rem;
        color: #0f172a;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .renewal-sub {
        font-size: .7rem;
        color: #64748b;
        margin-top: .15rem;
    }

    .renewal-deadline {
        text-align: right;
        flex-shrink: 0;
    }
    .renewal-days {
        font-size: 1.05rem;
        font-weight: 800;
        line-height: 1;
        font-variant-numeric: tabular-nums;
    }
    .renewal-days-label {
        font-size: .62rem;
        color: #64748b;
        text-transform: uppercase;
        letter-spacing: .06em;
        margin-top: .2rem;
    }
    .renewal-days.expired  { color: #991b1b; }
    .renewal-days.critical { color: #dc2626; }
    .renewal-days.warning  { color: #d97706; }
    .renewal-days.soon     { color: #2563eb; }
    .renewal-days.safe     { color: #16a34a; }

    /* ── Activity feed ── */
    .activity-list { padding: .25rem 0; }
    .activity-row {
        display: flex;
        gap: .75rem;
        padding: .65rem 1.25rem;
        font-size: .76rem;
        border-bottom: 1px solid #f1f5f9;
        align-items: center;
    }
    .activity-row:last-child { border-bottom: 0; }
    .activity-dot {
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #16a34a;
        flex-shrink: 0;
    }
    .activity-dot.fail { background: #dc2626; }
    .activity-text { flex: 1; min-width: 0; color: #334155; }
    .activity-text strong { color: #0f172a; }
    .activity-time { color: #94a3b8; font-size: .68rem; flex-shrink: 0; }

    /* Heartbeat row striping */
    .hb-stale-row { background: #fef2f2 !important; }
</style>
@endpush

@section('content')

{{-- ──────────────── STAT CARDS ──────────────── --}}
<div class="stat-grid">
    <a href="{{ route('master.companies.index') }}" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon stat-icon-blue">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ $stats['total_companies'] }}</div>
                <div class="stat-label">Companies</div>
                <div class="stat-trend">{{ $stats['total_licenses'] }} licenses total</div>
            </div>
        </div>
    </a>

    <a href="{{ route('license.companies.index') }}" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon stat-icon-green">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ $stats['active_licenses'] }}</div>
                <div class="stat-label">Active Licenses</div>
                @if($stats['expiring_in_30'] > 0)
                    <div class="stat-trend warning">⚠ {{ $stats['expiring_in_30'] }} expiring in 30 days</div>
                @else
                    <div class="stat-trend success">all healthy</div>
                @endif
            </div>
        </div>
    </a>

    <a href="{{ route('license.companies.index') }}" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon stat-icon-red">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ $stats['expired_licenses'] }}</div>
                <div class="stat-label">Expired Licenses</div>
                @if($stats['suspended_licenses'] > 0)
                    <div class="stat-trend danger">+ {{ $stats['suspended_licenses'] }} suspended</div>
                @else
                    <div class="stat-trend">no suspended</div>
                @endif
            </div>
        </div>
    </a>

    <a href="{{ route('license.installations.index') }}" class="stat-card-link">
        <div class="stat-card">
            <div class="stat-icon stat-icon-yellow">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="8" rx="2"/><rect x="2" y="14" width="20" height="8" rx="2"/></svg>
            </div>
            <div>
                <div class="stat-value">{{ $stats['active_installations'] }}</div>
                <div class="stat-label">Active Installations</div>
                <div class="stat-trend success">{{ number_format($stats['heartbeats_last_24h']) }} heartbeats / 24h</div>
            </div>
        </div>
    </a>

    @if($stats['unreviewed_suspicious'] > 0)
    <a href="{{ route('license.installations.index') }}" class="stat-card-link">
        <div class="stat-card" style="border-color:#fecaca;background:#fffbfb;">
            <div class="stat-icon stat-icon-red">
                <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            </div>
            <div>
                <div class="stat-value" style="color:#dc2626;">{{ $stats['unreviewed_suspicious'] }}</div>
                <div class="stat-label">Suspicious Events</div>
                <div class="stat-trend danger">awaiting review</div>
            </div>
        </div>
    </a>
    @endif
</div>

{{-- ──────────────── MAIN GRID ──────────────── --}}
<div class="dash-grid">

    {{-- LEFT COLUMN: Heartbeats --}}
    <div>
        <h3 class="dash-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
            Live Heartbeats
        </h3>
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
                <div style="padding:.6rem 1rem;background:linear-gradient(90deg,#f8fafc,#fff);border-bottom:1px solid #e2e8f0;font-size:.7rem;color:#64748b;display:flex;justify-content:space-between;align-items:center;">
                    <span>Interval: <strong style="color:#0f172a;">{{ $intervalLabel }}</strong> — countdown live</span>
                    <span style="display:inline-flex;align-items:center;gap:.3rem;color:#16a34a;">
                        <span style="width:6px;height:6px;border-radius:50%;background:#16a34a;animation:pulse 2s ease-in-out infinite;"></span>
                        {{ $stats['active_installations'] }} aktif
                    </span>
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
                            <tr class="{{ $isStale ? 'hb-stale-row' : '' }}">
                                <td style="font-size:.75rem;font-weight:500;">
                                    {{ $inst->domain ?? $inst->hostname ?? '—' }}
                                    @if($inst->domain && $inst->hostname && $inst->domain !== $inst->hostname)
                                        <div style="font-size:.65rem;color:#94a3b8;font-weight:400;">{{ $inst->hostname }}</div>
                                    @endif
                                </td>
                                <td><span class="badge badge-info">{{ $inst->app_code }}</span></td>
                                <td style="font-size:.72rem;color:#64748b;">{{ $inst->last_heartbeat_at?->diffForHumans() ?? '—' }}</td>
                                <td
                                    class="hb-countdown"
                                    data-next="{{ $inst->next_expected_at?->toIso8601String() }}"
                                    data-interval="{{ $heartbeatInterval ?? 3600 }}"
                                    style="font-size:.72rem;color:{{ $countdownColor }};font-weight:600;font-family:ui-monospace,monospace;"
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
                            <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2.5rem 1rem;">
                                <div style="font-size:1.5rem;margin-bottom:.4rem;">📡</div>
                                <div style="font-size:.78rem;">No heartbeats yet.</div>
                                <div style="font-size:.7rem;margin-top:.25rem;">Activate a license to see heartbeat data here.</div>
                            </td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Recent activity feed --}}
        @if($recentActivity->count() > 0)
        <h3 class="dash-section-title" style="margin-top:1.5rem;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            Activity
        </h3>
        <div class="card">
            <div class="card-body" style="padding:0;">
                <div class="activity-list">
                    @foreach($recentActivity as $act)
                    <div class="activity-row">
                        <span class="activity-dot {{ $act->status === 'success' ? '' : 'fail' }}"></span>
                        <div class="activity-text">
                            Heartbeat from
                            <strong>{{ optional($act->licenseCompany)->company?->name ?? $act->app_code ?? '—' }}</strong>
                            @if($act->app_code)
                                <span class="badge badge-info" style="font-size:.6rem;padding:.1rem .35rem;margin-left:.25rem;vertical-align:middle;">{{ $act->app_code }}</span>
                            @endif
                            @if($act->domain)
                                <span style="color:#94a3b8;font-size:.7rem;">· {{ $act->domain }}</span>
                            @endif
                            @if($act->status !== 'success')
                                <span style="color:#dc2626;">— {{ $act->failure_reason ?? 'failed' }}</span>
                            @endif
                            <span style="color:#94a3b8;"> ({{ $act->ip_address ?? '—' }})</span>
                        </div>
                        <div class="activity-time">{{ $act->heartbeat_at?->diffForHumans() ?? '—' }}</div>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>

    {{-- RIGHT COLUMN: Renewals + Suspicious --}}
    <div>
        <h3 class="dash-section-title">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            Upcoming Renewals
        </h3>
        <div class="card">
            <div class="card-header">
                <span class="card-title">Next 5 Expiring</span>
                <a href="{{ route('license.companies.index') }}" class="btn btn-secondary btn-sm">All Licenses</a>
            </div>
            <div class="card-body" style="padding:0;">
                @forelse($upcomingRenewals as $lic)
                @php
                    $u = $lic->urgency;
                    $iconText = match ($u) {
                        'expired'  => '⏱',
                        'critical' => '!',
                        'warning'  => '!',
                        'soon'     => '⏳',
                        default    => '✓',
                    };
                    $daysAbs   = abs($lic->days_left);
                    $daysLabel = $u === 'expired' ? 'expired ' . $daysAbs . 'd ago' : ($lic->days_left === 0 ? 'due today' : $lic->days_left . ' days left');
                @endphp
                <a href="{{ route('license.companies.show', \Vinkla\Hashids\Facades\Hashids::encode($lic->id)) }}"
                   style="text-decoration:none;color:inherit;display:block;">
                    <div class="renewal-row">
                        <div class="renewal-icon renewal-icon-{{ $u }}">{{ $iconText }}</div>
                        <div class="renewal-meta">
                            <div class="renewal-name">{{ $lic->company?->name ?? '—' }}</div>
                            <div class="renewal-sub">
                                {{ $lic->label ?? 'No label' }}
                                · expires {{ $lic->expires_at->format('d M Y') }}
                            </div>
                        </div>
                        <div class="renewal-deadline">
                            <div class="renewal-days {{ $u }}">
                                @if($u === 'expired')
                                    -{{ $daysAbs }}
                                @elseif($lic->days_left === 0)
                                    0
                                @else
                                    {{ $lic->days_left }}
                                @endif
                            </div>
                            <div class="renewal-days-label">
                                @if($u === 'expired')
                                    days overdue
                                @elseif($lic->days_left === 0)
                                    due today
                                @else
                                    days left
                                @endif
                            </div>
                        </div>
                    </div>
                </a>
                @empty
                <div style="text-align:center;color:#94a3b8;padding:2.5rem 1rem;">
                    <div style="font-size:1.5rem;margin-bottom:.4rem;">📋</div>
                    <div style="font-size:.78rem;">No licenses with expiry date.</div>
                </div>
                @endforelse
            </div>
        </div>

        {{-- Suspicious events panel --}}
        @if($suspiciousEvents->count() > 0)
        <h3 class="dash-section-title" style="margin-top:1.5rem;color:#991b1b;">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
            Suspicious Events
        </h3>
        <div class="card" style="border-color:#fecaca;">
            <div class="card-header" style="background:#fef2f2;gap:.4rem;">
                <span class="card-title" style="color:#991b1b;">Unreviewed</span>
                <div style="display:flex;gap:.4rem;">
                    <a href="{{ route('license.installations.suspicious.index') }}" class="btn btn-secondary btn-sm">View All</a>
                    <form method="POST" action="{{ route('license.installations.suspicious.ignore-all-global') }}"
                          data-confirm="Tandai SEMUA suspicious event yang belum ditinjau sebagai reviewed? Card merah ini akan hilang. Data tetap tersimpan untuk audit."
                          data-confirm-type="info"
                          data-confirm-title="Ignore All Suspicious"
                          data-confirm-ok="Ya, Ignore Semua"
                          style="margin:0;">
                        @csrf
                        <button class="btn btn-danger btn-sm" type="submit">Ignore All</button>
                    </form>
                </div>
            </div>
            <div class="card-body" style="padding:0;">
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Event</th><th>App</th><th>Severity</th><th>Time</th></tr></thead>
                        <tbody>
                            @foreach($suspiciousEvents as $ev)
                            <tr>
                                <td style="font-size:.72rem;">{{ $ev->event_type }}</td>
                                <td><span class="badge badge-info">{{ $ev->app_code ?? '—' }}</span></td>
                                <td><span class="badge {{ $ev->severity === 'critical' ? 'badge-danger' : 'badge-warning' }}">{{ $ev->severity }}</span></td>
                                <td style="font-size:.7rem;color:#64748b;">{{ $ev->occurred_at->diffForHumans() }}</td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>
</div>

{{-- Live tickers --}}
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

<style>
@keyframes pulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: .5; transform: scale(1.4); }
}
</style>

@endsection
