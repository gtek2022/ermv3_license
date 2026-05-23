@extends('layouts.app')
@section('title', $company->name)
@section('page-title', $company->name)
@section('breadcrumb')
    <a href="{{ route('master.companies.index') }}">Companies</a>
    <span class="breadcrumb-sep">/</span><span>{{ $company->name }}</span>
@endsection

@section('content')
@php $companyHash = Hashids::encode($company->id); @endphp
<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">Company Info</span>
            <div style="display:flex;gap:.5rem;">
                <a href="{{ route('master.companies.edit', $companyHash) }}" class="btn btn-secondary btn-sm">Edit</a>
                <span class="badge {{ $company->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $company->status }}</span>
            </div>
        </div>
        <div class="card-body">
            @foreach([['Code','code'],['Email','email'],['Phone','phone'],['City','city'],['Country','country'],['Website','website']] as [$label,$field])
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:90px;flex-shrink:0;">{{ $label }}</span>
                <span style="font-size:.82rem;">{{ $company->$field ?? '—' }}</span>
            </div>
            @endforeach
            @if($company->notes)
            <div style="margin-top:.75rem;font-size:.78rem;color:#64748b;">{{ $company->notes }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">License Bundles ({{ $licenses->count() }})</span>
            <a href="{{ route('license.companies.create') }}" class="btn btn-primary btn-sm">+ New License</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Label</th><th>Status</th><th>Apps</th><th>Expires</th><th></th></tr></thead>
                    <tbody>
                        @forelse($licenses as $lic)
                        @php $bm=['active'=>'badge-success','suspended'=>'badge-warning','cancelled'=>'badge-danger','expired'=>'badge-danger']; @endphp
                        <tr>
                            <td style="font-size:.78rem;">{{ $lic->label ?? '—' }}</td>
                            <td><span class="badge {{ $bm[$lic->status] ?? 'badge-secondary' }}">{{ $lic->status }}</span></td>
                            <td style="text-align:center;">{{ $lic->licenseApps?->count() ?? 0 }}</td>
                            <td style="font-size:.72rem;">{{ $lic->expires_at?->format('d M Y') ?? '∞' }}</td>
                            <td><a href="{{ route('license.companies.show', Hashids::encode($lic->id)) }}" class="btn btn-secondary btn-sm">View</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;">No licenses.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
