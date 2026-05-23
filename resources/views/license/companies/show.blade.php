@extends('layouts.app')
@section('title', 'License Detail')
@section('page-title', 'License Detail')
@section('breadcrumb')
    <a href="{{ route('license.companies.index') }}">Licenses</a>
    <span class="breadcrumb-sep">/</span><span>{{ $license->label ?? substr($license->license_key, 0, 12) }}…</span>
@endsection

@section('content')
@php
    $bm = ['active'=>'badge-success','suspended'=>'badge-warning','cancelled'=>'badge-danger','expired'=>'badge-danger'];
    $hash = Hashids::encode($license->id);
@endphp

<div style="display:grid;grid-template-columns:1fr 1fr;gap:1.25rem;margin-bottom:1.25rem;">
    {{-- License info --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">License Info</span>
            <span class="badge {{ $bm[$license->status] ?? 'badge-secondary' }}">{{ $license->status }}</span>
        </div>
        <div class="card-body">
            @foreach([['Company', $license->company?->name ?? '—'],['Label', $license->label ?? '—'],['Key', substr($license->license_key,0,12).'…'],['Activated', $license->activated_at?->format('d M Y H:i') ?? '—'],['Expires', $license->expires_at?->format('d M Y') ?? '∞'],['Max Installs', $license->max_installations]] as [$label,$val])
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:100px;flex-shrink:0;">{{ $label }}</span>
                <span style="font-size:.82rem;">{{ $val }}</span>
            </div>
            @endforeach

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                @if($license->status === 'active')
                <form method="POST" action="{{ route('license.companies.suspend', $hash) }}" onsubmit="return confirm('Suspend?')">@csrf<button class="btn btn-warning btn-sm">Suspend</button></form>
                <form method="POST" action="{{ route('license.companies.cancel', $hash) }}" onsubmit="return confirm('Cancel? Cannot be undone.')">@csrf<button class="btn btn-danger btn-sm">Cancel</button></form>
                @elseif($license->status === 'suspended')
                <form method="POST" action="{{ route('license.companies.reinstate', $hash) }}">@csrf<button class="btn btn-success btn-sm">Reinstate</button></form>
                @endif
                <form method="POST" action="{{ route('license.companies.renew', $hash) }}" style="display:flex;gap:.4rem;align-items:center;">
                    @csrf
                    <input type="number" name="days" value="365" min="1" max="3650" style="width:70px;padding:.3rem .5rem;border:1.5px solid #e2e8f0;border-radius:7px;font-size:.75rem;">
                    <button class="btn btn-success btn-sm">Renew</button>
                </form>
            </div>
        </div>
    </div>

    {{-- Policy --}}
    <div class="card">
        <div class="card-header"><span class="card-title">Heartbeat Policy Override</span></div>
        <div class="card-body">
            <p style="font-size:.78rem;color:#64748b;margin-bottom:1rem;">Override server defaults for this license. Leave at defaults to use global config.</p>
            <form method="POST" action="{{ route('license.companies.policy.update', $hash) }}">
                @csrf
                <div class="form-group">
                    <label class="form-label">Failure Tolerance</label>
                    <input type="number" name="heartbeat_tolerance" class="form-control" value="3" min="1" max="20">
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.2rem;">Failures before warning banner</div>
                </div>
                <div class="form-group">
                    <label class="form-label">Warning Days</label>
                    <input type="number" name="warning_days" class="form-control" value="3" min="1" max="30">
                    <div style="font-size:.68rem;color:#94a3b8;margin-top:.2rem;">Days until lockout modal</div>
                </div>
                <button type="submit" class="btn btn-primary btn-sm">Save Policy</button>
            </form>
        </div>
    </div>

    {{-- License Key Recovery --}}
    <div class="card" style="border-color:#fde68a;">
        <div class="card-header" style="background:#fffbeb;">
            <span class="card-title" style="color:#92400e;">🔑 Kunci Lisensi Lupa?</span>
        </div>
        <div class="card-body">
            <p style="font-size:.78rem;color:#64748b;margin-bottom:1rem;">
                Jika kunci lisensi hilang atau lupa, gunakan salah satu opsi berikut.
            </p>
            {{-- Retrieve --}}
            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:.85rem;margin-bottom:.75rem;">
                <div style="font-size:.75rem;font-weight:700;color:#1a3a6b;margin-bottom:.3rem;">Opsi 1 — Tampilkan Kunci Lama</div>
                <div style="font-size:.72rem;color:#64748b;margin-bottom:.6rem;">Memulihkan kunci asli dari enkripsi. Hanya berhasil jika APP_KEY tidak pernah berubah.</div>
                <button onclick="retrieveKey('{{ $hash }}')" id="retrieveBtn" class="btn btn-secondary btn-sm">Tampilkan Kunci</button>
                <div id="retrieveResult" style="display:none;margin-top:.6rem;"></div>
            </div>
            {{-- Regenerate --}}
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.85rem;">
                <div style="font-size:.75rem;font-weight:700;color:#991b1b;margin-bottom:.3rem;">Opsi 2 — Generate Kunci Baru</div>
                <div style="font-size:.72rem;color:#64748b;margin-bottom:.6rem;">
                    Membuat kunci baru. Kunci lama tidak berlaku lagi.
                    <strong>ERMv3 harus diaktifkan ulang dengan kunci baru.</strong>
                </div>
                <form method="POST" action="{{ route('license.companies.regenerate-key', $hash) }}"
                    onsubmit="return confirm('Generate kunci baru? Kunci lama tidak berlaku lagi dan ERMv3 harus diaktifkan ulang.')">
                    @csrf
                    <div style="display:flex;gap:.5rem;align-items:center;">
                        <input type="text" name="reason" class="form-control"
                            placeholder="Alasan (opsional)" style="font-size:.78rem;padding:.35rem .6rem;">
                        <button type="submit" class="btn btn-danger btn-sm" style="white-space:nowrap;">Generate Kunci Baru</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script>
function retrieveKey(hash) {
    var btn = document.getElementById('retrieveBtn');
    var result = document.getElementById('retrieveResult');
    btn.disabled = true;
    btn.textContent = 'Memuat...';

    fetch('/licenses/' + hash + '/retrieve-key', {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = 'Tampilkan Kunci';
        result.style.display = 'block';
        if (data.success) {
            result.innerHTML = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.6rem .85rem;">'
                + '<div style="font-size:.68rem;color:#166534;font-weight:700;margin-bottom:.25rem;">✅ Kunci Berhasil Dipulihkan</div>'
                + '<div style="font-family:monospace;font-size:.88rem;color:#1a3a6b;font-weight:700;letter-spacing:.05em;">' + data.key + '</div>'
                + '<div style="font-size:.68rem;color:#64748b;margin-top:.3rem;">Salin kunci ini sekarang. Halaman ini tidak menyimpan kunci secara permanen.</div>'
                + '</div>';
        } else {
            result.innerHTML = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.6rem .85rem;font-size:.78rem;color:#991b1b;">'
                + '❌ ' + data.message + '</div>';
        }
    })
    .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Tampilkan Kunci';
        result.style.display = 'block';
        result.innerHTML = '<div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.6rem .85rem;font-size:.78rem;color:#991b1b;">Gagal menghubungi server.</div>';
    });
}
</script>
@endpush
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><span class="card-title">Licensed Apps ({{ $license->licenseApps?->count() ?? 0 }})</span></div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>App</th><th>Status</th><th>Max Installs</th><th>Valid Until</th></tr></thead>
                <tbody>
                    @forelse($license->licenseApps ?? [] as $la)
                    <tr>
                        <td><span class="badge badge-info">{{ $la->app_code }}</span></td>
                        <td><span class="badge {{ $la->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $la->status }}</span></td>
                        <td style="text-align:center;">{{ $la->max_installations }}</td>
                        <td style="font-size:.75rem;">{{ $la->valid_until?->format('d M Y') ?? '∞' }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="4" style="text-align:center;color:#94a3b8;padding:1.5rem;">No apps licensed.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- Recent heartbeats --}}
<div class="card">
    <div class="card-header"><span class="card-title">Recent Heartbeats</span></div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>UUID</th><th>App</th><th>IP</th><th>Status</th><th>Time</th></tr></thead>
                <tbody>
                    @forelse($heartbeatLogs as $hb)
                    <tr>
                        <td style="font-family:monospace;font-size:.7rem;">{{ substr($hb->installation_uuid,0,16) }}…</td>
                        <td><span class="badge badge-info">{{ $hb->app_code }}</span></td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $hb->ip_address ?? '—' }}</td>
                        <td><span class="badge {{ $hb->status === 'verified' ? 'badge-success' : 'badge-danger' }}">{{ $hb->status }}</span></td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $hb->heartbeat_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="5" style="text-align:center;color:#94a3b8;padding:1.5rem;">No heartbeats yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
