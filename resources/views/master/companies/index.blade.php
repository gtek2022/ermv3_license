@extends('layouts.app')
@section('title', 'Companies')
@section('page-title', 'Companies')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Companies</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Client Companies</span>
        <a href="{{ route('master.companies.create') }}" class="btn btn-primary btn-sm">+ New Company</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Email</th><th>Status</th><th>Licenses</th><th>Created</th><th></th></tr></thead>
                <tbody>
                    @forelse($companies as $company)
                    <tr>
                        <td><code style="font-size:.72rem;">{{ $company->code }}</code></td>
                        <td style="font-weight:600;">{{ $company->name }}</td>
                        <td style="font-size:.78rem;color:#64748b;">{{ $company->email ?? '—' }}</td>
                        <td><span class="badge {{ $company->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $company->status }}</span></td>
                        <td style="text-align:center;">{{ $company->license_companies_count }}</td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $company->created_at->format('d M Y') }}</td>
                        <td>
                            <div style="display:flex;gap:.35rem;">
                                <a href="{{ route('master.companies.show', Hashids::encode($company->id)) }}" class="btn btn-secondary btn-sm">View</a>
                                <form method="POST" action="{{ route('master.companies.destroy', Hashids::encode($company->id)) }}"
                                      data-confirm="Hapus company {{ $company->name }}? Tindakan ini soft-delete — data masih bisa direstore via DB."
                                      data-confirm-type="danger"
                                      data-confirm-title="Hapus Company"
                                      data-confirm-ok="Ya, Hapus"
                                      style="margin:0;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">No companies yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($companies->hasPages())
        <div style="padding:.75rem 1.25rem;">{{ $companies->links() }}</div>
        @endif
    </div>
</div>
@endsection
