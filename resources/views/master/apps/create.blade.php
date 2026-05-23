@extends('layouts.app')
@section('title', 'Register App')
@section('page-title', 'Register App')
@section('breadcrumb')
    <a href="{{ route('master.apps.index') }}">Apps</a><span class="breadcrumb-sep">/</span><span>Create</span>
@endsection

@section('content')
<div class="card" style="max-width:560px;">
    <div class="card-header"><span class="card-title">Register New Application</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.apps.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Code *</label>
                    <input type="text" name="code" class="form-control @error('code') is-invalid @enderror" value="{{ old('code') }}" placeholder="ermv3" required>
                    @error('code')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Name *</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name') }}" required>
                    @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-control" rows="2">{{ old('description') }}</textarea>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Version</label>
                    <input type="text" name="version" class="form-control" value="{{ old('version') }}" placeholder="1.0.0">
                </div>
                <div class="form-group">
                    <label class="form-label">Base URL</label>
                    <input type="url" name="base_url" class="form-control" value="{{ old('base_url') }}" placeholder="https://">
                </div>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="{{ route('master.apps.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Register App</button>
            </div>
        </form>
    </div>
</div>
@endsection
