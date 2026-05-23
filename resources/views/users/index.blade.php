@extends('layouts.app')
@section('title', 'Users')
@section('page-title', 'Users')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Users</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <span class="card-title">Admin Users</span>
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm">+ New User</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead>
                <tbody>
                    @forelse($users as $user)
                    <tr>
                        <td style="font-weight:600;">{{ $user->name }}</td>
                        <td style="font-size:.78rem;">{{ $user->email }}</td>
                        <td>
                            @php $roleMap = ['super_admin'=>'badge-danger','admin'=>'badge-info','viewer'=>'badge-secondary']; @endphp
                            <span class="badge {{ $roleMap[$user->role] ?? 'badge-secondary' }}">{{ str_replace('_',' ', $user->role) }}</span>
                        </td>
                        <td><span class="badge {{ $user->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span></td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $user->created_at->format('d M Y') }}</td>
                        <td>
                            <div style="display:flex;gap:.35rem;">
                                <a href="{{ route('users.edit', Hashids::encode($user->id)) }}" class="btn btn-secondary btn-sm">Edit</a>
                                @if($user->id !== auth()->id())
                                <form method="POST" action="{{ route('users.destroy', Hashids::encode($user->id)) }}" data-confirm="Hapus user ini?" data-confirm-type="danger" data-confirm-title="Hapus User" data-confirm-ok="Ya, Hapus">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:2rem;">No users found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($users->hasPages())
        <div style="padding:.75rem 1.25rem;">{{ $users->links() }}</div>
        @endif
    </div>
</div>
@endsection
