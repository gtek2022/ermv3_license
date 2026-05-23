@extends('layouts.app')
@section('title', 'Konfirmasi Generate Kunci Baru')
@section('page-title', 'Konfirmasi Generate Kunci Baru')
@section('breadcrumb')
    <a href="{{ route('license.companies.index') }}">Licenses</a>
    <span class="breadcrumb-sep">/</span>
    <a href="{{ route('license.companies.show', $hash) }}">{{ $licenseCompany->label ?? substr($licenseCompany->license_key, 0, 12) }}…</a>
    <span class="breadcrumb-sep">/</span><span>Generate Kunci Baru</span>
@endsection

@section('content')
<div style="max-width:560px;margin:0 auto;">
    <div class="card" style="border-color:#fecaca;">
        <div class="card-header" style="background:#fef2f2;">
            <span class="card-title" style="color:#991b1b;">⚠ Generate Kunci Baru</span>
        </div>
        <div class="card-body">
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.85rem;margin-bottom:1.25rem;">
                <div style="font-size:.85rem;color:#92400e;font-weight:700;margin-bottom:.5rem;">
                    Tindakan ini tidak dapat dibatalkan
                </div>
                <ul style="font-size:.78rem;color:#78350f;line-height:1.7;margin-left:1.1rem;">
                    <li>Kunci lisensi lama akan <strong>langsung tidak berlaku</strong>.</li>
                    <li>Aplikasi ERMv3 yang sudah aktif akan kehilangan akses sampai diaktivasi ulang.</li>
                    <li>Anda harus memberikan kunci baru ke client untuk aktivasi ulang.</li>
                    <li>Kunci baru hanya akan ditampilkan satu kali — segera disalin dan disimpan.</li>
                </ul>
            </div>

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.85rem;margin-bottom:1.25rem;">
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:120px;flex-shrink:0;">Company</span>
                    <span style="font-size:.82rem;font-weight:600;">{{ $licenseCompany->company?->name ?? '—' }}</span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:120px;flex-shrink:0;">Label</span>
                    <span style="font-size:.82rem;">{{ $licenseCompany->label ?? '—' }}</span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:120px;flex-shrink:0;">Kunci Sekarang</span>
                    <span style="font-size:.78rem;font-family:monospace;color:#475569;">
                        {{ substr($licenseCompany->license_key, 0, 12) }}…{{ substr($licenseCompany->license_key, -4) }}
                    </span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:120px;flex-shrink:0;">Status</span>
                    <span class="badge badge-{{ $licenseCompany->status === 'active' ? 'success' : 'secondary' }}">{{ $licenseCompany->status }}</span>
                </div>
            </div>

            <form method="POST" action="{{ route('license.companies.regenerate-key', $hash) }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Alasan (opsional)</label>
                    <input type="text" name="reason" class="form-control"
                        placeholder="Mis. Kunci lama hilang / dicurigai bocor"
                        autofocus>
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.2rem;">Disimpan di audit log untuk keperluan tracing.</div>
                </div>

                <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:1.5rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                    <a href="{{ route('license.companies.show', $hash) }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-danger">Ya, Generate Kunci Baru</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
