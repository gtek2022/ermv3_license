@extends('layouts.app')
@section('title', 'Edit User')
@section('page-title', 'Edit User')
@section('breadcrumb')
    <a href="{{ route('users.index') }}">Users</a><span class="breadcrumb-sep">/</span><span>Edit</span>
@endsection

@section('content')
<div class="card" style="max-width:480px;">
    <div class="card-header"><span class="card-title">Edit User</span></div>
    <div class="card-body">
        <form method="POST" action="{{ route('users.update', Hashids::encode($user->id)) }}">
            @csrf @method('PUT')
            <div class="form-group">
                <label class="form-label">Name *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror" value="{{ old('name', $user->name) }}" required>
                @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Email *</label>
                <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" value="{{ old('email', $user->email) }}" required>
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                <div class="form-group">
                    <label class="form-label">Role *</label>
                    <select name="role" class="form-control">
                        @foreach(['admin'=>'Admin','viewer'=>'Viewer','super_admin'=>'Super Admin'] as $val=>$label)
                        <option value="{{ $val }}" {{ old('role', $user->role) === $val ? 'selected' : '' }}>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group" style="display:flex;align-items:flex-end;padding-bottom:.1rem;">
                    <label style="display:flex;align-items:center;gap:.5rem;cursor:pointer;font-size:.82rem;">
                        <input type="checkbox" name="is_active" value="1" {{ old('is_active', $user->is_active) ? 'checked' : '' }} style="width:15px;height:15px;accent-color:#1a3a6b;">
                        Active
                    </label>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Password <span style="font-weight:400;color:#94a3b8;">(leave blank to keep)</span></label>
                <input type="password" name="password" class="form-control @error('password') is-invalid @enderror">
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="form-group">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="password_confirmation" class="form-control">
            </div>
            <div style="display:flex;gap:.75rem;justify-content:flex-end;">
                <a href="{{ route('users.index') }}" class="btn btn-secondary">Cancel</a>
                <button type="submit" class="btn btn-primary">Save Changes</button>
            </div>
        </form>
    </div>
</div>
@endsection
