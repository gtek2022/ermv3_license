@extends('layouts.app')
@section('title', 'Installation Detail')
@section('page-title', 'Installation Detail')
@section('breadcrumb')
    <a href="{{ route('license.installations.index') }}">Installations</a>
    <span class="breadcrumb-sep">/</span><span>{{ $installation->hostname ?? $installation->domain ?? 'Detail' }}</span>
@endsection

@section('content')
@php $hash = Hashids::encode($installation->id); @endphp

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Installation Info</span>
            <span class="badge {{ $installation->status === 'active' ? 'badge-success' : ($installation->status === 'blacklisted' ? 'badge-danger' : 'badge-warning') }}">{{ $installation->status }}</span>
        </div>
        <div class="card-body">
            @foreach([['UUID',$installation->installation_uuid],['Fingerprint',substr($installation->fingerprint,0,20).'…'],['Hostname',$installation->hostname ?? '—'],['Domain',$installation->domain ?? '—'],['IP',$installation->ip_address ?? '—'],['App Version',$installation->app_version ?? '—'],['App Code',$installation->app_code],['Violations',$installation->violation_counter],['First Verified',$installation->first_verified_at?->format('d M Y H:i') ?? 'Never'],['Last Heartbeat',$installation->last_heartbeat_at?->format('d M Y H:i') ?? 'Never']] as [$label,$val])
            <div style="display:flex;padding:.35rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.7rem;color:#64748b;width:110px;flex-shrink:0;">{{ $label }}</span>
                <span style="font-size:.78rem;font-family:{{ in_array($label,['UUID','Fingerprint']) ? 'monospace' : 'inherit' }};">{{ $val }}</span>
            </div>
            @endforeach

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                @if($installation->status === 'active')
                <form method="POST" action="{{ route('license.installations.revoke', $hash) }}" data-confirm="Revoke instalasi ini? Perangkat tidak bisa menggunakan lisensi sampai diaktifkan ulang." data-confirm-type="warning" data-confirm-title="Revoke Instalasi" data-confirm-ok="Ya, Revoke">
                    @csrf
                    <input type="hidden" name="reason" value="Revoked by admin">
                    <button class="btn btn-warning btn-sm">Revoke</button>
                </form>
                <form method="POST" action="{{ route('license.installations.blacklist', $hash) }}" data-confirm="Blacklist fingerprint ini? Perangkat akan ditolak secara permanen." data-confirm-type="danger" data-confirm-title="Blacklist Perangkat" data-confirm-ok="Ya, Blacklist">
                    @csrf
                    <input type="hidden" name="reason" value="Blacklisted by admin">
                    <button class="btn btn-danger btn-sm">Blacklist</button>
                </form>
                @endif
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">Suspicious Events ({{ $suspicious->count() }})</span></div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Event</th><th>Severity</th><th>Time</th><th></th></tr></thead>
                    <tbody>
                        @forelse($suspicious as $ev)
                        <tr>
                            <td style="font-size:.75rem;">{{ $ev->event_type }}</td>
                            <td><span class="badge {{ $ev->severity === 'critical' ? 'badge-danger' : 'badge-warning' }}">{{ $ev->severity }}</span></td>
                            <td style="font-size:.72rem;color:#94a3b8;">{{ $ev->occurred_at->diffForHumans() }}</td>
                            <td>
                                @if(!$ev->is_reviewed)
                                <form method="POST" action="{{ route('license.installations.suspicious.review', [$hash, $ev->id]) }}">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm">Review</button>
                                </form>
                                @else
                                <span style="font-size:.7rem;color:#94a3b8;">Reviewed</span>
                                @endif
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No suspicious events.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header"><span class="card-title">Heartbeat History (last 30)</span></div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Status</th><th>IP</th><th>Version</th><th>Config Ver</th><th>Violations</th><th>Time</th></tr></thead>
                <tbody>
                    @forelse($heartbeats as $hb)
                    <tr>
                        <td><span class="badge {{ $hb->status === 'verified' ? 'badge-success' : 'badge-danger' }}">{{ $hb->status }}</span></td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $hb->ip_address ?? '—' }}</td>
                        <td style="font-size:.75rem;">{{ $hb->app_version ?? '—' }}</td>
                        <td style="font-size:.72rem;color:#64748b;">{{ $hb->config_version ?? '—' }}</td>
                        <td style="text-align:center;">{{ $hb->violation_counter }}</td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $hb->heartbeat_at->format('d M Y H:i') }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:1.5rem;">No heartbeats yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
