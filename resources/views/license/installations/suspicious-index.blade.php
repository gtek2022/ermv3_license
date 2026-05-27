@extends('layouts.app')
@section('title', 'Suspicious Events')
@section('page-title', 'Suspicious Events')
@section('breadcrumb')
    <a href="{{ route('license.installations.index') }}">Installations</a>
    <span class="breadcrumb-sep">/</span><span>Suspicious Events</span>
@endsection

@section('content')

<div class="card" style="margin-bottom:1rem;">
    <div class="card-body" style="display:flex;gap:1rem;align-items:center;flex-wrap:wrap;">
        <form method="GET" style="display:flex;gap:.5rem;align-items:center;flex:1;flex-wrap:wrap;">
            <select name="reviewed" class="form-control" style="width:auto;">
                <option value="">All status</option>
                <option value="0" {{ request('reviewed') === '0' ? 'selected' : '' }}>Unreviewed</option>
                <option value="1" {{ request('reviewed') === '1' ? 'selected' : '' }}>Reviewed</option>
            </select>
            <select name="event_type" class="form-control" style="width:auto;">
                <option value="">All event types</option>
                @foreach(['fingerprint_mismatch','invalid_key','replay_attack','seat_limit_exceeded','revoked_attempt','blacklisted'] as $t)
                <option value="{{ $t }}" {{ request('event_type') === $t ? 'selected' : '' }}>{{ $t }}</option>
                @endforeach
            </select>
            <select name="severity" class="form-control" style="width:auto;">
                <option value="">All severity</option>
                <option value="info"     {{ request('severity') === 'info' ? 'selected' : '' }}>info</option>
                <option value="warning"  {{ request('severity') === 'warning' ? 'selected' : '' }}>warning</option>
                <option value="critical" {{ request('severity') === 'critical' ? 'selected' : '' }}>critical</option>
            </select>
            <button class="btn btn-primary btn-sm">Filter</button>
            @if(request()->hasAny(['reviewed','event_type','severity']))
            <a href="{{ route('license.installations.suspicious.index') }}" class="btn btn-secondary btn-sm">Reset</a>
            @endif
        </form>

        @php
            $unreviewedTotal = \App\Models\LicenseLogsSuspicious::where('is_reviewed', false)->count();
        @endphp
        @if($unreviewedTotal > 0)
        <form method="POST" action="{{ route('license.installations.suspicious.ignore-all-global') }}"
              data-confirm="Tandai SEMUA {{ $unreviewedTotal }} suspicious event yang belum ditinjau sebagai reviewed? Data tetap tersimpan untuk audit."
              data-confirm-type="info"
              data-confirm-title="Ignore All Suspicious"
              data-confirm-ok="Ya, Ignore Semua"
              style="margin:0;">
            @csrf
            <button class="btn btn-danger btn-sm" type="submit">Ignore All ({{ $unreviewedTotal }})</button>
        </form>
        @endif
    </div>
</div>

<div class="card">
    <div class="card-header">
        <span class="card-title">All Suspicious Events ({{ $events->total() }})</span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Event Type</th>
                        <th>Severity</th>
                        <th>Status</th>
                        <th>App</th>
                        <th>IP / Domain</th>
                        <th>Fingerprint</th>
                        <th>When</th>
                        <th style="text-align:right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($events as $ev)
                    <tr style="{{ $ev->is_reviewed ? 'opacity:.55;' : '' }}">
                        <td style="font-size:.78rem;font-weight:500;">{{ $ev->event_type }}</td>
                        <td><span class="badge {{ $ev->severity === 'critical' ? 'badge-danger' : ($ev->severity === 'info' ? 'badge-info' : 'badge-warning') }}">{{ $ev->severity }}</span></td>
                        <td>
                            @if($ev->is_reviewed)
                                <span class="badge badge-secondary">reviewed</span>
                            @else
                                <span class="badge badge-warning">unreviewed</span>
                            @endif
                        </td>
                        <td><span class="badge badge-info">{{ $ev->app_code ?? '—' }}</span></td>
                        <td style="font-size:.72rem;color:#64748b;">
                            {{ $ev->ip_address ?? '—' }}<br>
                            <small>{{ $ev->domain ?? '—' }}</small>
                        </td>
                        <td style="font-family:monospace;font-size:.7rem;color:#64748b;">
                            {{ $ev->received_fingerprint ? substr($ev->received_fingerprint, 0, 16) . '…' : '—' }}
                        </td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $ev->occurred_at->diffForHumans() }}</td>
                        <td style="text-align:right;">
                            @if(!$ev->is_reviewed && $ev->installation_id)
                                @php $instHash = \Vinkla\Hashids\Facades\Hashids::encode($ev->installation_id); @endphp
                                <form method="POST" action="{{ route('license.installations.suspicious.review', [$instHash, $ev->id]) }}" style="margin:0;">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm" type="submit">Ignore</button>
                                </form>
                            @elseif(!$ev->is_reviewed)
                                <span style="font-size:.66rem;color:#94a3b8;">orphan event<br>(use Ignore All)</span>
                            @endif
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No suspicious events match the filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($events->hasPages())
        <div style="padding:.75rem 1.25rem;">{{ $events->links() }}</div>
        @endif
    </div>
</div>

@endsection
