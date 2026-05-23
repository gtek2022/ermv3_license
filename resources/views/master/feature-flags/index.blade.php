@extends('layouts.app')
@section('title', 'Feature Flags')
@section('page-title', 'Feature Flags')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a><span class="breadcrumb-sep">/</span><span>Feature Flags</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:1.5fr 1fr;gap:1.25rem;">
    <div class="card">
        <div class="card-header"><span class="card-title">All Feature Flags</span></div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Feature Key</th><th>App Scope</th><th>Enabled</th><th>Rollout %</th><th></th></tr></thead>
                    <tbody>
                        @forelse($flags as $flag)
                        <tr>
                            <td><code style="font-size:.72rem;">{{ $flag->feature_key }}</code></td>
                            <td><span class="badge badge-info">{{ $flag->app_scope }}</span></td>
                            <td>
                                <form method="POST" action="{{ route('master.flags.toggle', Hashids::encode($flag->id)) }}">
                                    @csrf
                                    <button type="submit" class="badge {{ $flag->enabled ? 'badge-success' : 'badge-secondary' }}" style="border:none;cursor:pointer;padding:.25rem .6rem;">
                                        {{ $flag->enabled ? 'ON' : 'OFF' }}
                                    </button>
                                </form>
                            </td>
                            <td style="text-align:center;">{{ $flag->rollout_percentage }}%</td>
                            <td>
                                <form method="POST" action="{{ route('master.flags.destroy', Hashids::encode($flag->id)) }}" onsubmit="return confirm('Delete flag?')">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:2rem;">No feature flags.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
            @if($flags->hasPages())<div style="padding:.75rem 1.25rem;">{{ $flags->links() }}</div>@endif
        </div>
    </div>

    <div class="card">
        <div class="card-header"><span class="card-title">New Feature Flag</span></div>
        <div class="card-body">
            <form method="POST" action="{{ route('master.flags.store') }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Feature Key *</label>
                    <input type="text" name="feature_key" class="form-control @error('feature_key') is-invalid @enderror" value="{{ old('feature_key') }}" placeholder="export_excel" required>
                    @error('feature_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">App Scope *</label>
                    <input type="text" name="app_scope" class="form-control" value="{{ old('app_scope', '*') }}" placeholder="* or ermv3">
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.2rem;">Use * for all apps</div>
                </div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
                    <div class="form-group">
                        <label class="form-label">Rollout %</label>
                        <input type="number" name="rollout_percentage" class="form-control" value="{{ old('rollout_percentage', 100) }}" min="0" max="100">
                    </div>
                    <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:.1rem;">
                        <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;">
                            <input type="checkbox" name="enabled" value="1" {{ old('enabled') ? 'checked' : '' }} style="width:15px;height:15px;accent-color:#1a3a6b;">
                            Enabled
                        </label>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Description</label>
                    <input type="text" name="description" class="form-control" value="{{ old('description') }}">
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%;">Create Flag</button>
            </form>
        </div>
    </div>
</div>
@endsection
