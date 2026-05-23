@extends('layouts.app')
@section('title', 'Edit App')
@section('page-title', 'Edit App')
@section('breadcrumb')
    <a href="{{ route('master.apps.index') }}">Apps</a>
    <span class="breadcrumb-sep">/</span>
    <a href="{{ route('master.apps.show', Hashids::encode($app->id)) }}">{{ $app->name }}</a>
    <span class="breadcrumb-sep">/</span><span>Edit</span>
@endsection

@section('content')
<div class="card" style="max-width:560px;">
    <div class="card-header"><span class="card-title">Edit App</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.apps.update', Hashids::encode($app->id)) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Code *</label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code', $app->code) }}" required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control" value="{{ old('name', $app->name) }}" required>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description', $app->description) }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Version</label>
                    <input type="text" name="version" class="form-control" value="{{ old('version', $app->version) }}">
                </div>
                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        @foreach(['active','inactive','deprecated'] as $s)
                        <option value="{{ $s }}" {{ old('status', $app->status) === $s ? 'selected' : '' }}>{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Base URL</label>
                    <input type="url" name="base_url" class="form-control" value="{{ old('base_url', $app->base_url) }}">
                </div>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="{{ route('master.apps.show', Hashids::encode($app->id)) }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
