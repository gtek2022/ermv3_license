@extends('layouts.app')
@section('title', 'System Configs')
@section('page-title', 'System Configs')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Configs</span>
@endsection

@section('content')
<div class="card">
    <div class="card-header">
        <div style="display:flex;gap:.75rem;align-items:center;flex:1;">
            <span class="card-title">System Configs</span>
            <form method="GET" style="display:flex;gap:.5rem;margin-left:auto;">
                <select name="category" class="form-control" style="width:160px;padding:.35rem .6rem;font-size:.78rem;" onchange="this.form.submit()">
                    <option value="">All Categories</option>
                    @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>{{ ucfirst($cat) }}</option>
                    @endforeach
                </select>
                <input type="text" name="search" class="form-control" style="width:200px;padding:.35rem .6rem;font-size:.78rem;" placeholder="Search key..." value="{{ request('search') }}">
                <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            </form>
        </div>
        <a href="{{ route('master.configs.create') }}" class="btn btn-primary btn-sm">+ New Config</a>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Key</th><th>Category</th><th>Value</th><th>Type</th><th>Public</th><th>Updated</th><th></th></tr></thead>
                <tbody>
                    @forelse($configs as $config)
                    <tr>
                        <td><code style="font-size:.72rem;">{{ $config->config_key }}</code></td>
                        <td><span class="badge badge-info">{{ $config->category }}</span></td>
                        <td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;font-size:.78rem;">
                            @if($config->is_encrypted)
                                <span style="color:#94a3b8;font-style:italic;">••••••••</span>
                            @else
                                {{ $config->config_value }}
                            @endif
                        </td>
                        <td><span class="badge badge-secondary">{{ $config->config_type }}</span></td>
                        <td>
                            @if($config->is_public)
                                <span class="badge badge-success">Yes</span>
                            @else
                                <span class="badge badge-secondary">No</span>
                            @endif
                        </td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $config->updated_at->format('d M Y') }}</td>
                        <td>
                            <div style="display:flex;gap:.35rem;">
                                @if(str_starts_with($config->config_key, 'system.signing.'))
                                    <span style="display:inline-flex;align-items:center;gap:.3rem;font-size:.7rem;color:#94a3b8;padding:.3rem .6rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:7px;">
                                        <svg width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                        Auto-managed
                                    </span>
                                @else
                                    <a href="{{ route('master.configs.edit', Hashids::encode($config->id)) }}" class="btn btn-secondary btn-sm">Edit</a>
                                    <a href="{{ route('master.configs.history', Hashids::encode($config->id)) }}" class="btn btn-secondary btn-sm">History</a>
                                @endif
                            </div>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">No configs yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($configs->hasPages())
        <div style="padding:.75rem 1.25rem;">{{ $configs->links() }}</div>
        @endif
    </div>
</div>
@endsection
