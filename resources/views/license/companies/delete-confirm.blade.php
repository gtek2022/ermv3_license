@extends('layouts.app')
@section('title', 'Konfirmasi Hapus Lisensi')
@section('page-title', 'Konfirmasi Hapus Lisensi')
@section('breadcrumb')
    <a href="{{ route('license.companies.index') }}">Licenses</a>
    <span class="breadcrumb-sep">/</span>
    <a href="{{ route('license.companies.show', $hash) }}">{{ $licenseCompany->label ?? substr($licenseCompany->license_key, 0, 12) }}…</a>
    <span class="breadcrumb-sep">/</span><span>Hapus Lisensi</span>
@endsection

@section('content')
<div style="max-width:600px;margin:0 auto;">
    <div class="card" style="border-color:#fecaca;">
        <div class="card-header" style="background:#fef2f2;">
            <span class="card-title" style="color:#991b1b;">⚠ Hapus Lisensi</span>
        </div>
        <div class="card-body">
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.85rem;margin-bottom:1.25rem;">
                <div style="font-size:.85rem;color:#92400e;font-weight:700;margin-bottom:.5rem;">
                    Tindakan ini akan:
                </div>
                <ul style="font-size:.78rem;color:#78350f;line-height:1.7;margin-left:1.1rem;">
                    <li>Menandai lisensi sebagai <strong>terhapus (soft delete)</strong> — data masih tersimpan di database tapi tidak aktif lagi.</li>
                    <li>Auto-revoke semua <strong>usage aktif</strong> di package — semua instalasi client akan kehilangan akses.</li>
                    <li>Client (ERMv3, PDS, dll) yang aktif akan <strong>langsung berhenti berfungsi</strong> setelah heartbeat berikutnya.</li>
                    <li>Tidak bisa di-undelete dari UI — perlu intervensi database.</li>
                </ul>
            </div>

            {{-- License info --}}
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.85rem;margin-bottom:1rem;">
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:140px;flex-shrink:0;">Company</span>
                    <span style="font-size:.82rem;font-weight:600;">{{ $licenseCompany->company?->name ?? '—' }}</span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:140px;flex-shrink:0;">Label</span>
                    <span style="font-size:.82rem;">{{ $licenseCompany->label ?? '—' }}</span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:140px;flex-shrink:0;">Kunci</span>
                    <span style="font-size:.78rem;font-family:monospace;color:#475569;">
                        {{ substr($licenseCompany->license_key, 0, 12) }}…{{ substr($licenseCompany->license_key, -4) }}
                    </span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:140px;flex-shrink:0;">Status</span>
                    <span class="badge badge-{{ $licenseCompany->status === 'active' ? 'success' : 'secondary' }}">{{ $licenseCompany->status }}</span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:140px;flex-shrink:0;">Apps</span>
                    <span style="font-size:.82rem;">{{ $licenseCompany->licenseApps?->count() ?? 0 }} aplikasi</span>
                </div>
                <div style="display:flex;padding:.3rem 0;">
                    <span style="font-size:.72rem;color:#64748b;width:140px;flex-shrink:0;">Usage Aktif</span>
                    <span style="font-size:.82rem;font-weight:600;color:{{ $activeUsages->count() > 0 ? '#d97706' : '#16a34a' }};">
                        {{ $activeUsages->count() }} dari {{ $licenseCompany->max_installations }} slot
                    </span>
                </div>
            </div>

            {{-- Active usages list --}}
            @if($activeUsages->count() > 0)
            <div style="background:#fff;border:1.5px solid #fde68a;border-radius:8px;padding:.75rem;margin-bottom:1rem;">
                <div style="font-size:.78rem;font-weight:700;color:#92400e;margin-bottom:.5rem;">
                    {{ $activeUsages->count() }} Usage Aktif yang Akan Di-Revoke:
                </div>
                <div style="font-size:.7rem;color:#64748b;">
                    @foreach($activeUsages as $u)
                    <div style="padding:.3rem 0;border-bottom:1px dashed #fde68a;font-family:monospace;">
                        <span style="color:#1a3a6b;font-weight:600;">FP:</span> {{ substr($u->usage_fingerprint, 0, 16) }}…
                        <span style="margin-left:.5rem;color:#94a3b8;">last seen: {{ $u->last_seen_at?->diffForHumans() ?? '—' }}</span>
                    </div>
                    @endforeach
                </div>
            </div>
            @endif

            <form method="POST" action="{{ route('license.companies.destroy', $hash) }}">
                @csrf @method('DELETE')

                <div style="background:#fef2f2;border:1.5px solid #fecaca;border-radius:8px;padding:.75rem;margin-bottom:1rem;">
                    <label style="display:flex;gap:.5rem;align-items:flex-start;cursor:pointer;font-size:.82rem;color:#991b1b;">
                        <input type="checkbox" required style="margin-top:.2rem;flex-shrink:0;">
                        <span>Saya paham bahwa lisensi <strong>{{ $licenseCompany->label ?? $licenseCompany->company?->name }}</strong> akan di-soft delete dan {{ $activeUsages->count() }} usage aktif akan di-revoke. Aplikasi client akan kehilangan akses.</span>
                    </label>
                </div>

                <div style="display:flex;gap:.5rem;justify-content:flex-end;padding-top:1rem;border-top:1px solid #f1f5f9;">
                    <a href="{{ route('license.companies.show', $hash) }}" class="btn btn-secondary">Batal</a>
                    <button type="submit" class="btn btn-danger">Ya, Hapus Lisensi</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
