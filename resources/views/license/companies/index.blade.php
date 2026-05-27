@extends('layouts.app')
@section('title', 'Licenses')
@section('page-title', 'Licenses')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Licenses</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">License Bundles</span>
        <a href="{{ route('license.companies.create') }}" class="btn btn-primary btn-sm">+ New License</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Company</th>
                        <th>Label</th>
                        <th>Status</th>
                        <th>Apps</th>
                        <th>Activated</th>
                        <th>Installations</th>
                        <th>Expires</th>
                        <th>Created</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($licenses as $lic)
                    @php
                        $badgeMap  = ['active'=>'badge-success','suspended'=>'badge-warning','cancelled'=>'badge-danger','expired'=>'badge-danger'];
                        $badge     = $badgeMap[$lic->status] ?? 'badge-secondary';
                        $activated = $lic->total_installations_count > 0;
                        $hash      = Hashids::encode($lic->id);
                    @endphp
                    <tr>
                        <td style="font-weight:600;">{{ $lic->company?->name ?? '—' }}</td>
                        <td style="font-size:.78rem;color:#64748b;">{{ $lic->label ?? '—' }}</td>
                        <td><span class="badge {{ $badge }}">{{ $lic->status }}</span></td>
                        <td style="text-align:center;">{{ $lic->licenseApps?->count() ?? 0 }}</td>

                        {{-- Activated column --}}
                        <td style="text-align:center;">
                            @if($activated)
                                <span class="badge badge-success" title="Sudah pernah diaktifkan di {{ $lic->total_installations_count }} instalasi">
                                    ✓ Ya
                                </span>
                            @else
                                <span class="badge badge-secondary" title="Belum pernah diaktifkan di client manapun">
                                    Belum
                                </span>
                            @endif
                        </td>

                        <td style="text-align:center;">
                            @if($lic->active_installations_count > 0)
                                <span class="badge badge-info">{{ $lic->active_installations_count }} aktif</span>
                            @else
                                <span style="color:#94a3b8;font-size:.75rem;">0</span>
                            @endif
                        </td>

                        <td>
                            @if($lic->expires_at)
                                <span class="{{ $lic->expires_at->isPast() ? 'badge badge-danger' : ($lic->expires_at->diffInDays(now()) <= 30 ? 'badge badge-warning' : '') }}" style="font-size:.72rem;">
                                    {{ $lic->expires_at->format('d M Y') }}
                                </span>
                            @else
                                <span style="font-size:.7rem;color:#7c3aed;font-weight:600;" title="Lifetime — never expires">∞ Lifetime</span>
                            @endif
                        </td>

                        <td style="font-size:.72rem;color:#94a3b8;">{{ $lic->created_at->format('d M Y') }}</td>

                        <td>
                            <div style="display:flex;gap:.35rem;align-items:center;">
                                <a href="{{ route('license.companies.show', $hash) }}" class="btn btn-secondary btn-sm">View</a>
                                <a href="{{ route('license.companies.edit', $hash) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <a href="{{ route('license.companies.delete-confirm', $hash) }}" class="btn btn-danger btn-sm">Hapus</a>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="9" style="text-align:center;color:#94a3b8;padding:2rem;">No licenses yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($licenses->hasPages())
        <div style="padding:.75rem 1.25rem;">{{ $licenses->links() }}</div>
        @endif
    </div>
</div>
@endsection
