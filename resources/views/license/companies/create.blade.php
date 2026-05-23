@extends('layouts.app')
@section('title', 'New License')
@section('page-title', 'New License')
@section('breadcrumb')
    <a href="{{ route('license.companies.index') }}">Licenses</a><span class="breadcrumb-sep">/</span><span>Create</span>
@endsection

@section('content')
<div class="card" style="max-width:680px;">
    <div class="card-header"><span class="card-title">Create License Bundle</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('license.companies.store') }}">
            @csrf
            <div class="form-group">
                <label class="form-label">Company *</label>
                <select name="company_id" class="form-control @error('company_id') is-invalid @enderror" required>
                    <option value="">— Select Company —</option>
                    @foreach($companies as $id => $name)
                    <option value="{{ $id }}" {{ old('company_id') == $id ? 'selected' : '' }}>{{ $name }}</option>
                    @endforeach
                </select>
                @error('company_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Label</label>
                <input type="text" name="label" class="form-control" value="{{ old('label') }}" placeholder="e.g. Production License 2026">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Validity (days) *</label>
                    <input type="number" name="days" class="form-control @error('days') is-invalid @enderror" value="{{ old('days', 365) }}" min="1" max="3650" required>
                    @error('days')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>
                <div class="form-group">
                    <label class="form-label">Max Installations *</label>
                    <input type="number" name="max_installations" class="form-control" value="{{ old('max_installations', 1) }}" min="1" max="100" required>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Licensed Apps *</label>
                <div style="background:#f8fafc;border:1.5px solid #e2e8f0;border-radius:8px;padding:.75rem;">
                    @foreach($apps as $app)
                    <label style="display:flex;align-items:center;gap:.5rem;padding:.35rem 0;cursor:pointer;">
                        <input type="checkbox" name="app_codes[]" value="{{ $app->code }}"
                            {{ in_array($app->code, old('app_codes', [])) ? 'checked' : '' }}
                            style="width:15px;height:15px;accent-color:#1a3a6b;">
                        <span style="font-size:.82rem;font-weight:600;">{{ $app->name }}</span>
                        <span class="badge badge-info" style="margin-left:.25rem;">{{ $app->code }}</span>
                    </label>
                    @endforeach
                </div>
                @error('app_codes')<div class="invalid-feedback" style="display:block;">{{ $message }}</div>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">Notes</label>
                <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
            </div>

            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="{{ route('license.companies.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Create License</button>
            </div>
        </form>
    </div>
</div>
@endsection
