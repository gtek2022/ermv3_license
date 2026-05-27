@extends('layouts.app')
@section('title', 'Edit License Info')
@section('page-title', 'Edit License Info')

@php $hash = Hashids::encode($license->id); @endphp

@section('breadcrumb')
    <a href="{{ route('license.companies.index') }}">Licenses</a>
    <span class="breadcrumb-sep">/</span>
    <a href="{{ route('license.companies.show', $hash) }}">{{ $license->label ?? substr($license->license_key, 0, 12) }}…</a>
    <span class="breadcrumb-sep">/</span><span>Edit</span>
@endsection

@section('content')

<div class="card" style="max-width:720px;">
    <div class="card-header">
        <span class="card-title">Edit License Info</span>
    </div>

    <div class="card-body">
        <div class="callout callout-info" style="background:#eff6ff;border-left:4px solid #29abe2;padding:.75rem 1rem;margin-bottom:1.25rem;border-radius:6px;font-size:.82rem;color:#1e40af;">
            <strong>ℹ️ Yang bisa diedit di sini:</strong> hanya field kosmetik dan limit instalasi.
            <ul style="margin:.4rem 0 0 1.2rem;font-size:.78rem;">
                <li>Untuk ubah <strong>masa berlaku</strong> → pakai tombol <em>Adjust Expiry</em> di halaman detail</li>
                <li>Untuk ubah <strong>status</strong> (suspend/cancel) → pakai tombol di halaman detail</li>
                <li>Untuk ubah <strong>kunci lisensi</strong> → pakai <em>Generate Kunci Baru</em> (akan revoke semua install)</li>
                <li>Untuk pindah ke company lain → tidak diperbolehkan, hapus + buat license baru</li>
            </ul>
        </div>

        <form method="POST" action="{{ route('license.companies.update', $hash) }}">
            @csrf @method('PUT')

            {{-- Read-only info --}}
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.85rem 1rem;margin-bottom:1.25rem;">
                <div style="display:grid;grid-template-columns:120px 1fr;gap:.4rem .75rem;font-size:.78rem;">
                    <span style="color:#64748b;">Company:</span>
                    <strong>{{ $license->company?->name ?? '—' }}</strong>

                    <span style="color:#64748b;">License Key:</span>
                    <code style="font-family:monospace;color:#1a3a6b;">{{ substr($license->license_key, 0, 12) }}…</code>

                    <span style="color:#64748b;">Status:</span>
                    <span class="badge {{ $license->status === 'active' ? 'badge-success' : ($license->status === 'suspended' ? 'badge-warning' : 'badge-danger') }}">{{ $license->status }}</span>

                    <span style="color:#64748b;">Expires:</span>
                    <span>
                        @if($license->expires_at)
                            {{ $license->expires_at->format('d M Y') }}
                        @else
                            <span style="color:#7c3aed;font-weight:600;">∞ Lifetime</span>
                        @endif
                    </span>

                    <span style="color:#64748b;">Active Installs:</span>
                    <span>{{ $license->activeInstallations->count() }} / {{ $license->max_installations }}</span>
                </div>
            </div>

            {{-- Editable fields --}}
            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Label</label>
                <input type="text" name="label" class="form-control @error('label') is-invalid @enderror"
                       value="{{ old('label', $license->label) }}"
                       placeholder="mis. PDS Kencana — Lifetime"
                       maxlength="255">
                @error('label')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small style="font-size:.7rem;color:#94a3b8;">
                    Label kosmetik untuk identifikasi internal. <strong>Tidak berpengaruh ke aplikasi client</strong> —
                    label tidak dikirim via API, tidak masuk fingerprint, tidak dipakai di heartbeat.
                </small>
            </div>

            <div class="form-group" style="margin-bottom:1rem;">
                <label class="form-label">Max Installations *</label>
                <input type="number" name="max_installations"
                       class="form-control @error('max_installations') is-invalid @enderror"
                       value="{{ old('max_installations', $license->max_installations) }}"
                       min="1" max="100" required>
                @error('max_installations')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small style="font-size:.7rem;color:#94a3b8;">
                    Berapa server berbeda yang boleh aktivasi pakai kunci ini.
                    @if($license->activeInstallations->count() > 0)
                        Saat ini ada <strong>{{ $license->activeInstallations->count() }}</strong> install aktif.
                        Kalau diturunkan di bawah angka ini, install yang ada masih jalan,
                        tapi aktivasi baru akan ditolak.
                    @endif
                </small>
            </div>

            <div class="form-group" style="margin-bottom:1.25rem;">
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="4" class="form-control @error('notes') is-invalid @enderror"
                          placeholder="Catatan internal — kontrak, cara hubungi client, dll.">{{ old('notes', $license->notes) }}</textarea>
                @error('notes')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <small style="font-size:.7rem;color:#94a3b8;">
                    Catatan internal admin gemilang saja, tidak dikirim ke client.
                </small>
            </div>

            <div style="display:flex;gap:.5rem;justify-content:flex-end;padding-top:1rem;border-top:1px solid #f1f5f9;">
                <a href="{{ route('license.companies.show', $hash) }}" class="btn btn-secondary">Batal</a>
                <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
            </div>
        </form>
    </div>
</div>

@endsection
