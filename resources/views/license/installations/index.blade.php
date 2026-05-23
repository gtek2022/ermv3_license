@extends('layouts.app')
@section('title', 'Installations')
@section('page-title', 'Installations')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Installations</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Device Installations</span>
        <form method="GET" style="display:flex;gap:.5rem;">
            <input type="text" name="search" class="form-control" style="width:200px;padding:.35rem .6rem;font-size:.78rem;" placeholder="Host / IP / UUID..." value="{{ request('search') }}">
            <select name="status" class="form-control" style="width:130px;padding:.35rem .6rem;font-size:.78rem;" onchange="this.form.submit()">
                <option value="">All Status</option>
                @foreach(['active','revoked','blacklisted','suspended'] as $s)
                <option value="{{ $s }}" {{ request('status') === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
        </form>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Host</th><th>App</th><th>IP</th><th>Version</th><th>Status</th><th>Violations</th><th>Last Heartbeat</th><th></th></tr></thead>
                <tbody>
                    @forelse($installations as $inst)
                    @php $badgeMap = ['active'=>'badge-success','revoked'=>'badge-danger','blacklisted'=>'badge-danger','suspended'=>'badge-warning']; @endphp
                    <tr>
                        <td>
                            <div style="font-weight:600;font-size:.8rem;">{{ $inst->hostname ?? $inst->domain ?? '—' }}</div>
                            <div style="font-size:.68rem;color:#94a3b8;font-family:monospace;">{{ substr($inst->installation_uuid, 0, 16) }}…</div>
                        </td>
                        <td><span class="badge badge-info">{{ $inst->app_code }}</span></td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $inst->ip_address ?? '—' }}</td>
                        <td style="font-size:.75rem;">{{ $inst->app_version ?? '—' }}</td>
                        <td><span class="badge {{ $badgeMap[$inst->status] ?? 'badge-secondary' }}">{{ $inst->status }}</span></td>
                        <td style="text-align:center;">
                            @if($inst->violation_counter > 0)
                                <span class="badge badge-warning">{{ $inst->violation_counter }}</span>
                            @else
                                <span style="color:#94a3b8;">0</span>
                            @endif
                        </td>
                        <td style="font-size:.72rem;color:#64748b;">{{ $inst->last_heartbeat_at?->diffForHumans() ?? 'Never' }}</td>
                        <td>
                            <a href="{{ route('license.installations.show', Hashids::encode($inst->id)) }}" class="btn btn-secondary btn-sm">View</a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="8" style="text-align:center;color:#94a3b8;padding:2rem;">No installations found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($installations->hasPages())
        <div style="padding:.75rem 1.25rem;">{{ $installations->links() }}</div>
        @endif
    </div>
</div>
@endsection
