@extends('layouts.app')
@section('title', 'Edit Config')
@section('page-title', 'Edit Config')
@section('breadcrumb')
    <a href="{{ route('master.configs.index') }}">Configs</a><span class="breadcrumb-sep">/</span><span>Edit</span>
@endsection

@section('content')
<div class="card" style="max-width:600px;">
    <div class="card-header">
        <span class="card-title"><code>{{ $config->config_key }}</code></span>
        <a href="{{ route('master.configs.history', Hashids::encode($config->id)) }}" class="btn btn-secondary btn-sm">History</a>
    </div>
    <div class="card-body">
        <form method="POST" action="{{ route('master.configs.update', Hashids::encode($config->id)) }}">
            @csrf @method('PUT')
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Category *</label>
                    <input type="text" name="category" class="form-control" value="{{ old('category', $config->category) }}" list="cat-list" required>
                    <datalist id="cat-list">
                        @foreach($categories as $cat)<option value="{{ $cat }}">@endforeach
                    </datalist>
                </div>
                <div class="form-group">
                    <label class="form-label">Type *</label>
                    <select name="config_type" class="form-control">
                        @foreach(['string','integer','boolean','json','encrypted'] as $t)
                        <option value="{{ $t }}" {{ old('config_type', $config->config_type) === $t ? 'selected' : '' }}>{{ ucfirst($t) }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Value @if($config->is_encrypted)<span style="color:#94a3b8;font-weight:400;">(leave blank to keep current encrypted value)</span>@endif</label>
                <input type="{{ $config->is_encrypted ? 'password' : 'text' }}" name="config_value" class="form-control" value="{{ $config->is_encrypted ? '' : old('config_value', $config->config_value) }}" placeholder="{{ $config->is_encrypted ? '••••••••' : '' }}">
            </div>
            <div class="form-group">
                <label class="form-label">Description</label>
                <input type="text" name="description" class="form-control" value="{{ old('description', $config->description) }}">
            </div>
            <div class="form-group">
                <label class="form-label">Change Reason</label>
                <input type="text" name="change_reason" class="form-control" placeholder="Optional — recorded in history">
            </div>
            <div style="display:flex;gap:1.5rem;margin-bottom:1rem;">
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;">
                    <input type="checkbox" name="is_encrypted" value="1" {{ old('is_encrypted', $config->is_encrypted) ? 'checked' : '' }} style="width:15px;height:15px;accent-color:#1a3a6b;">
                    Encrypt value
                </label>
                <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;">
                    <input type="checkbox" name="is_public" value="1" {{ old('is_public', $config->is_public) ? 'checked' : '' }} style="width:15px;height:15px;accent-color:#1a3a6b;">
                    Public
                </label>
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="{{ route('master.configs.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
