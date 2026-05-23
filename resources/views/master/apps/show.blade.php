@extends('layouts.app')
@section('title', $app->name)
@section('page-title', $app->name)
@section('breadcrumb')
    <a href="{{ route('master.apps.index') }}">Apps</a><span class="breadcrumb-sep">/</span><span>{{ $app->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1fr 1.5fr;gap:1.25rem;">
    <div class="card">
        <div class="card-header">
            <span class="card-title">App Info</span>
            <a href="{{ route('master.apps.edit', Hashids::encode($app->id)) }}" class="btn btn-secondary btn-sm">Edit</a>
        </div>
        <div class="card-body">
            @foreach([['Code','code'],['Version','version'],['Base URL','base_url'],['Status','status']] as [$label,$field])
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:80px;flex-shrink:0;">{{ $label }}</span>
                <span style="font-size:.82rem;">{{ $app->$field ?? '—' }}</span>
            </div>
            @endforeach
            @if($app->description)
            <p style="font-size:.78rem;color:#64748b;margin-top:.75rem;">{{ $app->description }}</p>
            @endif
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <span class="card-title">Features ({{ $features->count() }})</span>
        </div>
        <div class="card-body" style="padding:0;">
            {{-- Add feature form --}}
            <div style="padding:.85rem 1.25rem;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
                <form method="POST" action="{{ route('master.apps.features.store', Hashids::encode($app->id)) }}" style="display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:.5rem;align-items:end;">
                    @csrf
                    <div>
                        <label class="form-label" style="font-size:.65rem;">Feature Key</label>
                        <input type="text" name="feature_key" class="form-control" placeholder="risk_register" required style="padding:.4rem .6rem;font-size:.78rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.65rem;">Name</label>
                        <input type="text" name="name" class="form-control" placeholder="Risk Register" required style="padding:.4rem .6rem;font-size:.78rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.65rem;">Category</label>
                        <select name="category" class="form-control" style="padding:.4rem .6rem;font-size:.78rem;">
                            <option value="core">Core</option>
                            <option value="premium">Premium</option>
                            <option value="addon">Addon</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </form>
            </div>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Key</th><th>Name</th><th>Category</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                        @forelse($features as $feat)
                        <tr>
                            <td><code style="font-size:.72rem;">{{ $feat->feature_key }}</code></td>
                            <td style="font-size:.8rem;">{{ $feat->name }}</td>
                            <td><span class="badge badge-secondary">{{ $feat->category ?? '—' }}</span></td>
                            <td><span class="badge {{ $feat->is_active ? 'badge-success' : 'badge-secondary' }}">{{ $feat->is_active ? 'Yes' : 'No' }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('master.apps.features.destroy', [Hashids::encode($app->id), $feat->id]) }}" onsubmit="return confirm('Remove feature?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Remove</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;">No features yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
