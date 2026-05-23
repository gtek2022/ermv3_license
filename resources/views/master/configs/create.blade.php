@extends('layouts.app')
@section('title', 'New Config')
@section('page-title', 'New Config')
@section('breadcrumb')
    <a href="{{ route('master.configs.index') }}">Configs</a><span class="breadcrumb-sep">/</span><span>Create</span>
@endsection

@section('content')
<div class="card" style="max-width:600px;">
    <div class="card-header"><span class="card-title">New System Config</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.configs.store') }}">
            @csrf
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Config Key *</label>
                    <input type="text" name="config_key" class="form-control @error('config_key') is-invalid @enderror" value="{{ old('config_key') }}" placeholder="heartbeat_interval" required>
                    @error('config_key')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" class="form-control @error('category') is-invalid @enderror" value="{{ old('category') }}" list="cat-list" placeholder="licensing" required>
                    <datalist id="cat-list">
                        @foreach($categories as $cat)<option value="{{ $cat }}">@endforeach
                        <option value="licensing"><option value="system"><option value="security"><option value="ui">
                    </datalist>
                    @error('category')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="config_type" class="form-control">
                        @foreach(['string','integer','boolean','json','encrypted'] as $t)
                        <option value="{{ $t }}" {{ old('config_type') === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Value</label>
                    <input type="text" name="config_value" class="form-control" value="{{ old('config_value') }}">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description') }}">
            </div>
            <div style="display:flex;gap:1.5rem;margin-bottom:1rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;">
                    <input type="checkbox" name="is_encrypted" value="1" {{ old('is_encrypted') ? 'checked' : '' }} style="width:15px;height:15px;accent-color:#1a3a6b;">
                    Encrypt value
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;">
                    <input type="checkbox" name="is_public" value="1" {{ old('is_public') ? 'checked' : '' }} style="width:15px;height:15px;accent-color:#1a3a6b;">
                    Public (readable by client apps)
                </label>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="{{ route('master.configs.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create Config</button>
            </div>
        </form>
    </div>
</div>
@endsection
