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
            <div style="display:flex;gap:.5rem;align-items:center;">
                <a href="{{ route('license.companies.edit', $hash) }}"
                    class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:.3rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                    Edit
                </a>
                <button onclick="window.__showLicensePublicKey('{{ $hash }}')"
                    class="btn btn-secondary btn-sm" style="display:flex;align-items:center;gap:.3rem;">
                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                    Public Key
                </button>
                <span class="badge {{ $bm[$license->status] ?? 'badge-secondary' }}">{{ $license->status }}</span>
            </div>
        </div>
        <div class="card-body">
            @php
                $expiresLabel = $license->expires_at
                    ? $license->expires_at->format('d M Y')
                    : '∞ Lifetime';
                $expiresStyle = $license->expires_at ? '' : 'color:#7c3aed;font-weight:600;';
            @endphp
            @foreach([['Company', $license->company?->name ?? '—'],['Label', $license->label ?? '—'],['Key', substr($license->license_key,0,12).'…'],['Activated', $license->activated_at?->format('d M Y H:i') ?? '—']] as [$label,$val])
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:100px;flex-shrink:0;">{{ $label }}</span>
                <span style="font-size:.82rem;">{{ $val }}</span>
            </div>
            @endforeach
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:100px;flex-shrink:0;">Expires</span>
                <span style="font-size:.82rem;{{ $expiresStyle }}">{{ $expiresLabel }}</span>
            </div>
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:100px;flex-shrink:0;">Max Installs</span>
                <span style="font-size:.82rem;">{{ $license->max_installations }}</span>
            </div>

            <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:1rem;padding-top:1rem;border-top:1px solid #f1f5f9;">
                @if($license->status === 'active')
                <form method="POST" action="{{ route('license.companies.suspend', $hash) }}" data-confirm="Suspend lisensi ini?" data-confirm-type="warning" data-confirm-title="Suspend Lisensi" data-confirm-ok="Ya, Suspend">@csrf<button class="btn btn-warning btn-sm">Suspend</button></form>
                <form method="POST" action="{{ route('license.companies.cancel', $hash) }}" data-confirm="Batalkan lisensi ini? Tindakan ini tidak dapat dibatalkan." data-confirm-type="danger" data-confirm-title="Batalkan Lisensi" data-confirm-ok="Ya, Batalkan">@csrf<button class="btn btn-danger btn-sm">Cancel</button></form>
                @elseif($license->status === 'suspended')
                <form method="POST" action="{{ route('license.companies.reinstate', $hash) }}">@csrf<button class="btn btn-success btn-sm">Reinstate</button></form>
                @endif
                <button type="button" class="btn btn-primary btn-sm" onclick="document.getElementById('adjustExpiryPanel').style.display='block';this.style.display='none';">⏱ Adjust Expiry</button>
            </div>

            {{-- Adjust Expiry Panel --}}
            <div id="adjustExpiryPanel" style="display:none;margin-top:1rem;padding:1rem;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.75rem;">
                    <strong style="font-size:.85rem;color:#0f172a;">Adjust License Expiry</strong>
                    <button type="button" onclick="document.getElementById('adjustExpiryPanel').style.display='none';" style="background:none;border:none;cursor:pointer;font-size:1.1rem;color:#94a3b8;">×</button>
                </div>

                <form method="POST" action="{{ route('license.companies.adjust-expiry', $hash) }}"
                      data-confirm="Yakin ubah masa berlaku license ini?"
                      data-confirm-type="info"
                      data-confirm-title="Konfirmasi Adjust Expiry"
                      data-confirm-ok="Ya, Update">
                    @csrf

                    <div style="display:flex;flex-direction:column;gap:.5rem;font-size:.78rem;">
                        <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem;background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;">
                            <input type="radio" name="mode" value="add_days" checked onchange="adjustExpToggle()" style="margin:0;">
                            <span><strong>Tambah / Kurangi hari</strong> — geser tanggal expiry. Negatif untuk kurangi.</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem;background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;">
                            <input type="radio" name="mode" value="set_date" onchange="adjustExpToggle()" style="margin:0;">
                            <span><strong>Set tanggal pasti</strong> — pilih tanggal expiry persis.</span>
                        </label>
                        <label style="display:flex;align-items:center;gap:.5rem;padding:.6rem;background:#fff;border:1.5px solid #e2e8f0;border-radius:6px;cursor:pointer;">
                            <input type="radio" name="mode" value="lifetime" onchange="adjustExpToggle()" style="margin:0;">
                            <span><strong>Convert ke Lifetime</strong> — license tidak akan pernah expired (∞).</span>
                        </label>
                    </div>

                    <div id="adjustModeAddDays" style="margin-top:.75rem;">
                        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.25rem;">Days (positif = tambah, negatif = kurangi)</label>
                        <input type="number" name="days" id="adjust_days" value="365" min="-36500" max="36500"
                               class="form-control" style="font-size:.85rem;">
                        <div style="display:flex;gap:.25rem;margin-top:.4rem;flex-wrap:wrap;">
                            @foreach([[-30,'-30'],[-7,'-7'],[7,'+7'],[30,'+30'],[90,'+90'],[365,'+365']] as [$d,$lbl])
                            <button type="button" onclick="document.getElementById('adjust_days').value={{ $d }}"
                                    class="btn btn-secondary btn-sm" style="font-size:.7rem;padding:.2rem .5rem;">{{ $lbl }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div id="adjustModeSetDate" style="margin-top:.75rem;display:none;">
                        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.25rem;">Tanggal Expires</label>
                        <input type="date" name="expires_at"
                               value="{{ $license->expires_at?->toDateString() ?? now()->addYear()->toDateString() }}"
                               class="form-control" style="font-size:.85rem;">
                    </div>

                    <div style="margin-top:.75rem;">
                        <label style="font-size:.72rem;color:#64748b;display:block;margin-bottom:.25rem;">Alasan (opsional, tercatat di audit log)</label>
                        <input type="text" name="reason" placeholder="mis. perpanjangan langganan tahunan"
                               class="form-control" style="font-size:.78rem;">
                    </div>

                    <button type="submit" class="btn btn-primary btn-sm" style="margin-top:.75rem;">Update Expiry</button>
                </form>
            </div>
            <script>
            function adjustExpToggle() {
                var modes = document.querySelectorAll('input[name="mode"]');
                var sel = '';
                modes.forEach(function (r) { if (r.checked) sel = r.value; });
                document.getElementById('adjustModeAddDays').style.display = (sel === 'add_days') ? '' : 'none';
                document.getElementById('adjustModeSetDate').style.display = (sel === 'set_date') ? '' : 'none';
            }
            </script>
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
                <button type="button" id="retrieveBtn" class="btn btn-secondary btn-sm"
                    onclick="window.__retrieveKey && window.__retrieveKey('{{ $hash }}')">Tampilkan Kunci</button>
                <div id="retrieveResult" style="display:none;margin-top:.6rem;"></div>
            </div>
            {{-- Regenerate --}}
            <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.85rem;">
                <div style="font-size:.75rem;font-weight:700;color:#991b1b;margin-bottom:.3rem;">Opsi 2 — Generate Kunci Baru</div>
                <div style="font-size:.72rem;color:#64748b;margin-bottom:.6rem;">
                    Membuat kunci baru. Kunci lama tidak berlaku lagi.
                    <strong>Aplikasi client harus diaktifkan ulang dengan kunci baru.</strong>
                </div>
                <a href="{{ route('license.companies.regenerate-confirm', $hash) }}" class="btn btn-danger btn-sm" style="white-space:nowrap;">
                    Generate Kunci Baru
                </a>
            </div>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════
     INLINE SCRIPTS — placed inside @section('content') so they render
     even when @push/@stack mechanism has issues. Functions are scoped
     to window.__* to avoid collisions.
═════════════════════════════════════════════════════════════════════ --}}
<script>
window.__retrieveKey = function(hash) {
    var btn = document.getElementById('retrieveBtn');
    var result = document.getElementById('retrieveResult');
    if (btn) { btn.disabled = true; btn.textContent = 'Memuat...'; }

    fetch('/licenses/' + hash + '/retrieve-key', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (btn) { btn.disabled = false; btn.textContent = 'Tampilkan Kunci'; }
        if (data.success) {
            window.__showLicenseKeyModal(data.key, false);
            if (result) {
                result.style.display = 'block';
                result.innerHTML = '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.5rem .75rem;font-size:.72rem;color:#166534;">✅ Kunci berhasil dipulihkan — lihat di modal.</div>';
            }
        } else {
            if (window.GToast) GToast.danger(data.message || 'Gagal memulihkan kunci.');
            else alert(data.message || 'Gagal memulihkan kunci.');
        }
    })
    .catch(function() {
        if (btn) { btn.disabled = false; btn.textContent = 'Tampilkan Kunci'; }
        if (window.GToast) GToast.danger('Gagal menghubungi server.');
        else alert('Gagal menghubungi server.');
    });
};

/**
 * Show the license key in a persistent modal with copy-to-clipboard.
 * @param {string} key  The license key to display
 * @param {boolean} isNew Whether this is a freshly-generated key (changes copy)
 */
window.__showLicenseKeyModal = function(key, isNew) {
    if (!window.GModal) {
        prompt('Kunci Lisensi (salin sekarang):', key);
        return;
    }
    var title = isNew ? '✓ Kunci Lisensi Baru' : 'Kunci Lisensi';
    var note  = isNew
        ? '<strong style="color:#dc2626;">Salin dan simpan kunci ini sekarang. Kunci tidak akan ditampilkan lagi setelah modal ditutup.</strong>'
        : 'Salin kunci ini dan simpan di tempat aman.';

    // Stash the key on window so the click handler can read it without
    // having to escape it through HTML attributes (which previously broke
    // the onclick handler when the key contained quotes).
    window.__pendingCopyKey = key;

    var msg =
        '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.85rem 1rem;margin:.5rem 0;">'
      +   '<div style="font-size:.68rem;color:#166534;font-weight:700;margin-bottom:.5rem;text-transform:uppercase;letter-spacing:.04em;">Kunci Lisensi Aplikasi</div>'
      +   '<div style="display:flex;align-items:stretch;gap:.5rem;">'
      +     '<code id="__licKeyText" style="flex:1;font-family:monospace;font-size:.95rem;color:#1a3a6b;font-weight:800;letter-spacing:.04em;background:#fff;padding:.55rem .7rem;border-radius:6px;border:1.5px dashed #86efac;cursor:text;user-select:all;display:flex;align-items:center;justify-content:center;text-align:center;min-width:0;overflow-x:auto;white-space:nowrap;">'
      +       __escapeHtml(key)
      +     '</code>'
      +     '<button type="button" id="__licCopyBtn" '
      +       'style="background:#16a34a;color:#fff;border:none;padding:.55rem .9rem;border-radius:6px;font-size:.72rem;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;">'
      +       'Copy'
      +     '</button>'
      +   '</div>'
      + '</div>'
      + '<p style="font-size:.78rem;color:#64748b;margin-top:.6rem;line-height:1.5;">' + note + '</p>';

    GModal.alert({
        type: 'success',
        title: title,
        message: msg,
        confirmText: 'Tutup',
    });

    // Wire up the Copy button after modal renders
    setTimeout(function() {
        var btn = document.getElementById('__licCopyBtn');
        if (btn) {
            btn.addEventListener('click', function() {
                window.__copyKeyToClipboard(btn, window.__pendingCopyKey || '');
            });
        }
    }, 30);
};

function __escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function(ch) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[ch];
    });
}

window.__copyKeyToClipboard = function(btn, key) {
    if (!key) return;
    var done = function() {
        var orig = btn.textContent;
        btn.textContent = '✓ Tersalin';
        btn.style.background = '#15803d';
        setTimeout(function() { btn.textContent = 'Copy'; btn.style.background = '#16a34a'; }, 2000);
    };
    if (navigator.clipboard && navigator.clipboard.writeText && window.isSecureContext) {
        navigator.clipboard.writeText(key).then(done).catch(function() {
            window.__fallbackCopy(key); done();
        });
    } else {
        window.__fallbackCopy(key); done();
    }
};

window.__fallbackCopy = function(text) {
    var ta = document.createElement('textarea');
    ta.value = text;
    ta.setAttribute('readonly', '');
    ta.style.position = 'fixed';
    ta.style.top = '0';
    ta.style.left = '0';
    ta.style.opacity = '0';
    document.body.appendChild(ta);
    ta.focus();
    ta.select();
    try { document.execCommand('copy'); } catch (e) {}
    document.body.removeChild(ta);
};

@if(session('new_license_key'))
{{-- Show the new license key in a modal on page load --}}
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(function() {
        window.__showLicenseKeyModal(@json(session('new_license_key')), true);
    }, 250);
});
@endif
</script>
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header"><span class="card-title">Licensed Apps &amp; Features</span></div>
    <div class="card-body" style="padding:0;">
        @forelse($license->licenseApps ?? [] as $la)
        @php
            // Load ALL master features for this app (active or not)
            $allMasterFeatures = \App\Models\MasterAppFeature::where('app_code', $la->app_code)->get();
            $hasFeatureRestrictions = $la->features->isNotEmpty();
            $activeCount = $la->features->where('status','active')->count();
        @endphp
        <div style="border-bottom:1px solid #f1f5f9;padding:1rem 1.25rem;">

            {{-- App header --}}
            <div style="display:flex;align-items:center;gap:.75rem;margin-bottom:1rem;flex-wrap:wrap;">
                <span class="badge badge-info" style="font-size:.75rem;padding:.25rem .65rem;">{{ $la->app_code }}</span>
                <span class="badge {{ $la->status === 'active' ? 'badge-success' : 'badge-secondary' }}">{{ $la->status }}</span>
                <span style="font-size:.75rem;color:#64748b;">Max: {{ $la->max_installations }} instalasi</span>
                @if($la->valid_until)
                <span style="font-size:.72rem;color:#94a3b8;">s/d {{ $la->valid_until->format('d M Y') }}</span>
                @endif
                @if(!$hasFeatureRestrictions)
                    <span class="badge badge-green" style="margin-left:auto;">Semua Fitur Aktif</span>
                @else
                    <span class="badge badge-yellow" style="margin-left:auto;">{{ $activeCount }} / {{ $allMasterFeatures->count() }} Fitur Aktif</span>
                @endif
            </div>

            @if($allMasterFeatures->isEmpty())
                <div style="font-size:.78rem;color:#94a3b8;padding:.5rem 0;">
                    Belum ada fitur terdaftar. Tambahkan di <a href="{{ route('master.apps.show', Hashids::encode(\App\Models\MasterApp::where('code',$la->app_code)->value('id'))) }}" style="color:#1a3a6b;">Master → Apps → {{ $la->app_code }}</a>.
                </div>
            @else
            {{-- Feature table --}}
            <div class="table-wrap">
                <table style="font-size:.78rem;">
                    <thead>
                        <tr>
                            <th>Fitur</th>
                            <th>Tipe</th>
                            <th>Status Master</th>
                            <th>Status Lisensi</th>
                            <th>Kunci FLK</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($allMasterFeatures as $feat)
                        @php
                            $licensedFeat   = $la->features->firstWhere('feature_key', $feat->feature_key);
                            $isInLicense    = (bool) $licensedFeat;
                            $licStatus      = $licensedFeat?->status ?? null;
                            $isLicActive    = $isInLicense && $licStatus === 'active';
                            $isSuspended    = $isInLicense && $licStatus === 'suspended';
                            $requiresLic    = $feat->requires_license;

                            // Row background
                            if (!$feat->is_active) {
                                $rowBg = 'background:#fef2f2;';
                            } elseif ($isLicActive) {
                                $rowBg = 'background:#f0fdf4;';
                            } elseif ($isSuspended) {
                                $rowBg = 'background:#fffbeb;';
                            } elseif (!$hasFeatureRestrictions) {
                                $rowBg = 'background:#f0fdf4;'; // all licensed
                            } else {
                                $rowBg = '';
                            }
                        @endphp
                        <tr style="{{ $rowBg }}">
                            {{-- Feature name --}}
                            <td>
                                <div style="font-weight:600;">{{ $feat->name }}</div>
                                <div style="font-size:.65rem;color:#94a3b8;font-family:monospace;">{{ $feat->feature_key }}</div>
                                @if($feat->category)
                                <span style="font-size:.58rem;background:#f1f5f9;border-radius:3px;padding:.02rem .28rem;color:#64748b;">{{ $feat->category }}</span>
                                @endif
                            </td>

                            {{-- Type --}}
                            <td>
                                @if($requiresLic)
                                    <span class="badge" style="background:#fef3c7;color:#92400e;font-size:.6rem;">🔑 Licensed</span>
                                @else
                                    <span class="badge badge-info" style="font-size:.6rem;">Free</span>
                                @endif
                            </td>

                            {{-- Master status (admin toggle) --}}
                            <td>
                                @php $masterAppId = \App\Models\MasterApp::where('code',$la->app_code)->value('id'); @endphp
                                <form method="POST" action="{{ route('master.apps.features.toggle', [Hashids::encode($masterAppId), $feat->id]) }}">
                                    @csrf
                                    <button type="submit"
                                        class="badge {{ $feat->is_active ? 'badge-success' : 'badge-secondary' }}"
                                        style="border:none;cursor:pointer;padding:.2rem .5rem;font-size:.6rem;"
                                        title="Toggle aktif/nonaktif di semua lisensi">
                                        {{ $feat->is_active ? '✓ Aktif' : '✗ Nonaktif' }}
                                    </button>
                                </form>
                            </td>

                            {{-- License status --}}
                            <td>
                                @if(!$hasFeatureRestrictions)
                                    <span class="badge badge-success" style="font-size:.6rem;">✓ Semua</span>
                                @elseif($isLicActive)
                                    <span class="badge badge-success" style="font-size:.6rem;">✓ Aktif</span>
                                @elseif($isSuspended)
                                    <span class="badge badge-warning" style="font-size:.6rem;">⏸ Suspend</span>
                                @else
                                    <span class="badge badge-secondary" style="font-size:.6rem;">✗ Tidak</span>
                                @endif
                            </td>

                            {{-- FLK key management (only for requires_license features) --}}
                            <td>
                                @if($requiresLic)
                                    @if($feat->feature_license_key_hash)
                                        <div style="display:flex;flex-direction:column;gap:.25rem;">
                                            <button onclick="window.__retrieveFLKLic({{ $feat->id }}, '{{ Hashids::encode($masterAppId) }}', event)"
                                                class="btn btn-secondary btn-sm" style="font-size:.62rem;padding:.2rem .5rem;">
                                                Lihat FLK
                                            </button>
                                            <form method="POST" action="{{ route('master.apps.features.regenerate-key', [Hashids::encode($masterAppId), $feat->id]) }}"
                                                data-confirm="Regenerate kunci FLK? Semua aktivasi lama akan dicabut." data-confirm-type="danger" data-confirm-title="Regenerate Kunci FLK" data-confirm-ok="Ya, Regenerate">
                                                @csrf
                                                <button type="submit" class="btn btn-warning btn-sm" style="font-size:.62rem;padding:.2rem .5rem;">
                                                    Regenerate
                                                </button>
                                            </form>
                                            <div id="flk-lic-{{ $feat->id }}" style="display:none;"></div>
                                            @php
                                                $actCount = \App\Models\LicenseFeatureActivation::where('feature_key',$feat->feature_key)->where('app_code',$la->app_code)->where('status','active')->count();
                                            @endphp
                                            @if($actCount > 0)
                                            <span style="font-size:.62rem;color:#166534;">{{ $actCount }} instalasi aktif</span>
                                            @endif
                                        </div>
                                    @else
                                        <span style="font-size:.65rem;color:#94a3b8;font-style:italic;">Belum ada kunci</span>
                                    @endif
                                @else
                                    <span style="font-size:.65rem;color:#94a3b8;">—</span>
                                @endif
                            </td>

                            {{-- Actions --}}
                            <td>
                                <div style="display:flex;flex-direction:column;gap:.25rem;">
                                    @if($isLicActive)
                                        {{-- Suspend from license --}}
                                        <form method="POST" action="{{ route('license.companies.features.toggle', [$hash, $licensedFeat->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-warning btn-sm" style="font-size:.62rem;padding:.2rem .5rem;white-space:nowrap;">Suspend</button>
                                        </form>
                                        {{-- Remove from license --}}
                                        <form method="POST" action="{{ route('license.companies.features.remove', [$hash, $licensedFeat->id]) }}"
                                            data-confirm="Hapus fitur ini dari lisensi?" data-confirm-type="danger" data-confirm-title="Hapus Fitur" data-confirm-ok="Ya, Hapus">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-danger btn-sm" style="font-size:.62rem;padding:.2rem .5rem;white-space:nowrap;">Hapus</button>
                                        </form>
                                    @elseif($isSuspended)
                                        {{-- Reinstate --}}
                                        <form method="POST" action="{{ route('license.companies.features.toggle', [$hash, $licensedFeat->id]) }}">
                                            @csrf
                                            <button type="submit" class="btn btn-success btn-sm" style="font-size:.62rem;padding:.2rem .5rem;white-space:nowrap;">Aktifkan</button>
                                        </form>
                                    @elseif($hasFeatureRestrictions)
                                        {{-- Add to license --}}
                                        <form method="POST" action="{{ route('license.companies.features.add', $hash) }}">
                                            @csrf
                                            <input type="hidden" name="license_app_id" value="{{ $la->id }}">
                                            <input type="hidden" name="feature_key" value="{{ $feat->feature_key }}">
                                            <button type="submit" class="btn btn-primary btn-sm" style="font-size:.62rem;padding:.2rem .5rem;white-space:nowrap;">+ Tambah</button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Quick add all unlicensed --}}
            @if($hasFeatureRestrictions)
            @php $unlicensedFeats = $allMasterFeatures->filter(fn($f) => !$la->features->contains('feature_key', $f->feature_key)); @endphp
            @if($unlicensedFeats->isNotEmpty())
            <div style="display:flex;gap:.5rem;align-items:center;flex-wrap:wrap;margin-top:.75rem;padding-top:.75rem;border-top:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;font-weight:600;">Tambah cepat:</span>
                @foreach($unlicensedFeats as $feat)
                <form method="POST" action="{{ route('license.companies.features.add', $hash) }}" style="display:inline;">
                    @csrf
                    <input type="hidden" name="license_app_id" value="{{ $la->id }}">
                    <input type="hidden" name="feature_key" value="{{ $feat->feature_key }}">
                    <button type="submit" class="btn btn-secondary btn-sm" style="font-size:.68rem;">+ {{ $feat->name }}</button>
                </form>
                @endforeach
            </div>
            @endif
            @endif
            @endif

        </div>
        @empty
        <div style="text-align:center;color:#94a3b8;padding:2rem;">No apps licensed.</div>
        @endforelse
    </div>
</div>

{{-- Active license usages — manage installation slots --}}
<div class="card" style="margin-bottom:1.25rem;">
    <div class="card-header">
        <span class="card-title">Installation Slots ({{ $activeUsages->count() }} / {{ $license->max_installations }})</span>
        @if($activeUsages->count() > 0)
        <form method="POST" action="{{ route('license.companies.revoke-all-usages', $hash) }}"
              onsubmit="return confirm('Revoke SEMUA {{ $activeUsages->count() }} usage aktif? Semua client akan kehilangan akses dan harus aktivasi ulang.');"
              style="margin:0;">
            @csrf
            <button type="submit" class="btn btn-warning btn-sm">
                Revoke Semua Usage
            </button>
        </form>
        @endif
    </div>
    <div class="card-body" style="padding:1rem;">
        @if($activeUsages->count() === 0)
        <div style="text-align:center;color:#94a3b8;padding:1.5rem;font-size:.82rem;">
            Belum ada instalasi aktif. Slot kosong — client bisa aktivasi.
        </div>
        @else
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(380px, 1fr));gap:1rem;">
            @foreach($activeUsages as $u)
            @php
                $rawMeta = $u->meta;
                if ($rawMeta instanceof \Illuminate\Database\Eloquent\Casts\ArrayObject) {
                    $m = $rawMeta->toArray();
                } elseif (is_array($rawMeta)) {
                    $m = $rawMeta;
                } elseif (is_string($rawMeta) && $rawMeta !== '') {
                    $m = json_decode($rawMeta, true) ?: [];
                } else {
                    $m = [];
                }
                // Match installation row for app_version etc.
                $inst = \App\Models\LicenseInstallation::where('license_company_id', $license->id)
                    ->where('fingerprint', $u->usage_fingerprint)
                    ->first();
            @endphp
            <div style="border:1.5px solid #e2e8f0;border-radius:10px;padding:.85rem 1rem;background:#fff;">
                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:.5rem;margin-bottom:.65rem;padding-bottom:.5rem;border-bottom:1px solid #f1f5f9;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-weight:700;color:#1a3a6b;font-size:.85rem;">
                            {{ $u->name ?? $m['hostname'] ?? 'Unknown host' }}
                        </div>
                        @if(! empty($m['domain']))
                        <div style="font-size:.7rem;color:#64748b;font-family:monospace;">{{ $m['domain'] }}</div>
                        @endif
                    </div>
                    <span class="badge badge-success" style="font-size:.6rem;flex-shrink:0;">{{ $u->status->value ?? $u->status }}</span>
                </div>

                <div style="font-size:.7rem;line-height:1.7;color:#475569;">
                    @php
                        $rows = [
                            ['App',          $u->client_type ?? $m['app'] ?? '—'],
                            ['App Version',  $m['app_version'] ?? '—'],
                            ['OS',           ($m['os'] ?? '—') . (isset($m['os_release']) ? ' '.$m['os_release'] : '')],
                            ['PHP',          $m['php_version'] ?? '—'],
                            ['Hostname',     $m['hostname'] ?? '—'],
                            ['Machine ID',   isset($m['machine_id']) && $m['machine_id'] ? $m['machine_id'] : '—'],
                            ['MAC Address',  isset($m['mac_address']) && $m['mac_address'] ? $m['mac_address'] : '—'],
                            ['Server IP',    $m['server_ip'] ?? ($u->ip ?? '—')],
                            ['Domain',       $m['domain'] ?? '—'],
                            ['Timezone',     $m['timezone'] ?? '—'],
                        ];
                    @endphp
                    @foreach($rows as [$label, $val])
                    <div style="display:flex;gap:.5rem;padding:.15rem 0;">
                        <span style="color:#94a3b8;width:100px;flex-shrink:0;font-size:.65rem;text-transform:uppercase;letter-spacing:.04em;">{{ $label }}</span>
                        <span style="font-family:monospace;color:#1e293b;word-break:break-all;font-size:.7rem;">{{ $val }}</span>
                    </div>
                    @endforeach

                    <div style="margin-top:.5rem;padding-top:.5rem;border-top:1px dashed #e2e8f0;">
                        <div style="display:flex;gap:.5rem;padding:.15rem 0;">
                            <span style="color:#94a3b8;width:100px;flex-shrink:0;font-size:.65rem;text-transform:uppercase;">Fingerprint</span>
                            <span style="font-family:monospace;color:#1e293b;word-break:break-all;font-size:.65rem;">{{ $u->usage_fingerprint }}</span>
                        </div>
                        <div style="display:flex;gap:.5rem;padding:.15rem 0;">
                            <span style="color:#94a3b8;width:100px;flex-shrink:0;font-size:.65rem;text-transform:uppercase;">User Agent</span>
                            <span style="font-family:monospace;color:#1e293b;word-break:break-all;font-size:.65rem;">{{ $u->user_agent ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <div style="display:flex;justify-content:space-between;align-items:center;margin-top:.65rem;padding-top:.5rem;border-top:1px solid #f1f5f9;font-size:.65rem;color:#94a3b8;">
                    <div>
                        Aktif sejak <strong style="color:#475569;">{{ $u->registered_at?->format('d M Y H:i') ?? '—' }}</strong>
                        <br>
                        Last seen <strong style="color:#475569;">{{ $u->last_seen_at?->diffForHumans() ?? '—' }}</strong>
                    </div>
                    <form method="POST" action="{{ route('license.companies.usage.revoke', [$hash, $u->id]) }}"
                          onsubmit="return confirm('Revoke usage di {{ $m['hostname'] ?? $u->usage_fingerprint }}? Client harus aktivasi ulang.');"
                          style="margin:0;">
                        @csrf
                        <button type="submit" class="btn btn-warning btn-sm" style="font-size:.65rem;">Revoke</button>
                    </form>
                </div>
            </div>
            @endforeach
        </div>
        @endif
    </div>
</div>

{{-- Recent heartbeats --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">Recent Heartbeats</span>
    </div>
    <div class="card-body" style="padding:0;">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Server / Host</th><th>App</th><th>IP</th><th>Versi</th><th>Status</th><th>Waktu</th></tr></thead>
                <tbody>
                    @forelse($heartbeatLogs as $hb)
                    @php
                        $inst = $license->activeInstallations->firstWhere('installation_uuid', $hb->installation_uuid);
                        $hostLabel = $inst?->hostname ?? $inst?->domain ?? $hb->domain ?? '—';
                    @endphp
                    <tr>
                        <td>
                            <div style="font-size:.8rem;font-weight:600;">{{ $hostLabel }}</div>
                            <div style="font-size:.65rem;color:#94a3b8;">{{ $hb->app_version ?? '—' }}</div>
                        </td>
                        <td><span class="badge badge-info">{{ $hb->app_code }}</span></td>
                        <td style="font-size:.75rem;color:#64748b;">{{ $hb->ip_address ?? '—' }}</td>
                        <td style="font-size:.72rem;color:#64748b;">{{ $hb->app_version ?? '—' }}</td>
                        @php
                            $hbOk = in_array($hb->status, ['success', 'verified', 'ok'], true);
                        @endphp
                        <td><span class="badge {{ $hbOk ? 'badge-success' : 'badge-danger' }}">{{ $hb->status }}</span></td>
                        <td style="font-size:.72rem;color:#94a3b8;">{{ $hb->heartbeat_at->diffForHumans() }}</td>
                    </tr>
                    @empty
                    <tr><td colspan="6" style="text-align:center;color:#94a3b8;padding:1.5rem;">Belum ada heartbeat.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

{{-- ═════════════════════════════════════════════════════════════════════
     Inline scripts (FLK + Public Key) — placed before @endsection so they
     definitely render. window.__* prefix to avoid collisions.
═════════════════════════════════════════════════════════════════════ --}}
<script>
window.__retrieveFLKLic = function(featureId, appHash, evt) {
    var btn = evt && evt.target ? evt.target : null;
    if (btn) { btn.disabled = true; btn.textContent = '...'; }

    fetch('/master/apps/' + appHash + '/features/' + featureId + '/retrieve-key', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (btn) { btn.disabled = false; btn.textContent = 'Lihat FLK'; }
        if (data.success) {
            var appLabel = data.app_name || 'aplikasi client';
            GModal.alert({
                type: 'info',
                title: 'Feature License Key (FLK)',
                message: '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.75rem 1rem;margin:.5rem 0;">'
                    + '<div style="font-size:.68rem;color:#1e40af;font-weight:700;margin-bottom:.35rem;">Feature License Key:</div>'
                    + '<div style="font-family:monospace;font-size:.9rem;color:#1a3a6b;font-weight:800;letter-spacing:.06em;word-break:break-all;">' + data.key + '</div>'
                    + '</div>'
                    + '<p style="font-size:.75rem;color:#64748b;margin-top:.5rem;">Berikan kunci ini ke client untuk aktivasi fitur di <strong>' + appLabel + '</strong>.</p>',
                confirmText: 'Tutup',
            });
        } else {
            if (window.GToast) GToast.danger(data.message || 'Gagal.');
            else alert(data.message || 'Gagal.');
        }
    })
    .catch(function() {
        if (btn) { btn.disabled = false; btn.textContent = 'Lihat FLK'; }
        if (window.GToast) GToast.danger('Gagal menghubungi server.');
    });
};

window.__showLicensePublicKey = function(hash) {
    fetch(window.location.origin + '/licenses/' + hash + '/public-key', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(function(r) { return r.json(); })
    .then(function(res) {
        if (!res.success) { if (window.GToast) GToast.danger(res.message || 'Gagal.'); return; }
        var d = res.data;
        if (!d.has_key) {
            GModal.alert({
                type: 'warning',
                title: 'Belum Ada Signing Key',
                message: '<p>' + d.message + '</p>'
                    + '<p style="margin-top:.5rem;font-size:.78rem;color:#64748b;">Buka <strong>Master → Companies → ' + res.company + '</strong> untuk generate signing key.</p>',
                confirmText: 'OK',
            });
            return;
        }
        var snippetHtml = '';
        var appCodes = Object.keys(d.app_snippets || {});
        if (appCodes.length > 1) {
            snippetHtml += '<div style="display:flex;gap:.35rem;flex-wrap:wrap;margin-bottom:.5rem;">';
            appCodes.forEach(function(code, i) {
                snippetHtml += '<button onclick="window.__switchSnippetTabLic(this, \'' + code + '\')" '
                    + 'style="padding:.2rem .6rem;border-radius:6px;font-size:.68rem;font-weight:600;cursor:pointer;border:1.5px solid #bfdbfe;'
                    + (i === 0 ? 'background:#1a3a6b;color:#fff;' : 'background:#eff6ff;color:#1e40af;') + '">'
                    + code + '</button>';
            });
            snippetHtml += '</div>';
            appCodes.forEach(function(code, i) {
                snippetHtml += '<div id="lic-snippet-' + code + '" style="display:' + (i === 0 ? 'block' : 'none') + ';">'
                    + '<div style="background:#0f172a;border-radius:8px;padding:.65rem .85rem;font-family:monospace;font-size:.72rem;color:#e2e8f0;white-space:pre-wrap;word-break:break-all;">'
                    + d.app_snippets[code] + '</div></div>';
            });
        } else {
            snippetHtml = '<div style="background:#0f172a;border-radius:8px;padding:.65rem .85rem;font-family:monospace;font-size:.72rem;color:#e2e8f0;white-space:pre-wrap;word-break:break-all;">'
                + d.env_snippet + '</div>';
        }
        GModal.alert({
            type: 'info',
            title: 'Public Key — ' + res.company,
            message:
                '<div style="text-align:left;">'
                + '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.65rem .85rem;margin-bottom:.75rem;">'
                + '<div style="font-size:.68rem;font-weight:700;color:#1e40af;margin-bottom:.35rem;">Signing Public Key (Ed25519)</div>'
                + '<div style="font-family:monospace;font-size:.8rem;color:#1a3a6b;font-weight:700;word-break:break-all;margin-bottom:.35rem;">' + d.public_key + '</div>'
                + '<div style="font-size:.62rem;color:#64748b;">KID: ' + d.kid + ' &nbsp;|&nbsp; Valid: ' + d.valid_from + ' – ' + d.valid_until + '</div>'
                + '</div>'
                + '<div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:7px;padding:.45rem .7rem;margin-bottom:.75rem;font-size:.7rem;color:#166534;">'
                + '💡 ' + d.note
                + '</div>'
                + '<div style="font-size:.68rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.4rem;">Snippet .env per Aplikasi</div>'
                + snippetHtml
                + '<div style="font-size:.62rem;color:#94a3b8;margin-top:.5rem;">Server: ' + d.server_url + ' &nbsp;|&nbsp; Issuer: ' + d.issuer + '</div>'
                + '</div>',
            confirmText: 'Tutup',
        });
    })
    .catch(function() { if (window.GToast) GToast.danger('Gagal mengambil public key.'); });
};

window.__switchSnippetTabLic = function(btn, code) {
    document.querySelectorAll('[id^="lic-snippet-"]').forEach(function(el) { el.style.display = 'none'; });
    btn.parentElement.querySelectorAll('button').forEach(function(b) { b.style.background = '#eff6ff'; b.style.color = '#1e40af'; });
    var t = document.getElementById('lic-snippet-' + code);
    if (t) t.style.display = 'block';
    btn.style.background = '#1a3a6b'; btn.style.color = '#fff';
};
</script>
@endsection

