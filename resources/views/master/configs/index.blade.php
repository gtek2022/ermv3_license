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
                                <a href="{{ route('master.configs.edit', Hashids::encode($config->id)) }}" class="btn btn-secondary btn-sm">Edit</a>
                                <a href="{{ route('master.configs.history', Hashids::encode($config->id)) }}" class="btn btn-secondary btn-sm">History</a>
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
