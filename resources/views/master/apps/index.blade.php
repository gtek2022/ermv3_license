@extends('layouts.app')
@section('title', 'Apps')
@section('page-title', 'Registered Apps')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Apps</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Applications</span>
        <a href="{{ route('master.apps.create') }}" class="btn btn-primary btn-sm">+ Register App</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Code</th><th>Name</th><th>Version</th><th>Status</th><th>Features</th><th></th></tr></thead>
                <tbody>
                    @forelse($apps as $app)
                    <tr>
                        <td><code style="font-size:.72rem;">{{ $app->code }}</code></td>
                        <td style="font-weight:600;">{{ $app->name }}</td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $app->version ?? '—' }}</td>
                        <td><span class="badge {{ $app->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $app->status }}</span></td>
                        <td style="text-align:center;">{{ $app->features_count }}</td>
                        <td>
                            <div style="display:flex;gap:.35rem;">
                                <a href="{{ route('master.apps.show', Hashids::encode($app->id)) }}" class="btn btn-secondary btn-sm">View</a>
                                <form method="POST" action="{{ route('master.apps.destroy', Hashids::encode($app->id)) }}"
                                      data-confirm="Hapus app {{ $app->name }}? Semua feature ({{ $app->features_count }}) ikut terhapus. Tindakan ini diblok kalau masih ada lisensi aktif yang memakai code ini."
                                      data-confirm-type="danger"
                                      data-confirm-title="Hapus Aplikasi"
                                      data-confirm-ok="Ya, Hapus"
                                      style="margin:0;">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No apps registered.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($apps->hasPages())<div style="padding:.75rem 1.25rem;">{{ $apps->links() }}</div>@endif
    </div>
</div>
@endsection
