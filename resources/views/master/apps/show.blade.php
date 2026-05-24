@extends('layouts.app')
@section('title', $app->name)
@section('page-title', $app->name)
@section('breadcrumb')
    <a href="{{ route('master.apps.index') }}">Apps</a>
    <span class="breadcrumb-sep">/</span>
    <span>{{ $app->name }}</span>
@endsection

@section('content')
<div style="display:grid;grid-template-columns:280px 1fr;gap:1.25rem;align-items:start;">

    {{-- App Info --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">App Info</span>
            <a href="{{ route('master.apps.edit', Hashids::encode($app->id)) }}" class="btn btn-secondary btn-sm">Edit</a>
        </div>
        <div class="card-body">
            @foreach([['Code','code'],['Version','version'],['Status','status'],['Base URL','base_url']] as [$label,$field])
            <div style="display:flex;padding:.4rem 0;border-bottom:1px solid #f1f5f9;">
                <span style="font-size:.72rem;color:#64748b;width:75px;flex-shrink:0;">{{ $label }}</span>
                <span style="font-size:.82rem;word-break:break-all;">{{ $app->$field ?? '—' }}</span>
            </div>
            @endforeach
            @if($app->description)
            <p style="font-size:.78rem;color:#64748b;margin-top:.75rem;line-height:1.5;">{{ $app->description }}</p>
            @endif

            {{-- Active licenses using this app --}}
            @if($licenseApps->isNotEmpty())
            <div style="margin-top:1rem;padding-top:.85rem;border-top:1px solid #f1f5f9;">
                <div style="font-size:.68rem;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.05em;margin-bottom:.5rem;">
                    Lisensi Aktif ({{ $licenseApps->count() }})
                </div>
                @foreach($licenseApps as $la)
                <div style="display:flex;align-items:center;gap:.4rem;padding:.3rem 0;border-bottom:1px solid #f8fafc;">
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:.75rem;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            {{ $la->licenseCompany?->company?->name ?? '—' }}
                        </div>
                        <div style="font-size:.65rem;color:#94a3b8;">
                            {{ $la->licenseCompany?->label ?? substr($la->licenseCompany?->license_key ?? '', 0, 12) }}…
                        </div>
                    </div>
                    <span class="badge badge-success" style="font-size:.58rem;">aktif</span>
                </div>
                @endforeach
            </div>
            @else
            <div style="margin-top:.85rem;padding:.6rem;background:#f8fafc;border-radius:7px;font-size:.75rem;color:#94a3b8;text-align:center;">
                Belum ada lisensi aktif untuk app ini
            </div>
            @endif
        </div>
    </div>

    {{-- Features --}}
    <div class="card">
        <div class="card-header">
            <span class="card-title">Features ({{ $features->count() }})</span>
        </div>
        <div class="card-body" style="padding:0;">

            {{-- Add feature form --}}
            <div style="padding:.85rem 1.25rem;border-bottom:1px solid #f1f5f9;background:#f8fafc;">
                <div style="font-size:.7rem;font-weight:700;color:#475569;text-transform:uppercase;letter-spacing:.04em;margin-bottom:.5rem;">
                    Tambah Feature Baru
                </div>
                <form method="POST" action="{{ route('master.apps.features.store', Hashids::encode($app->id)) }}"
                    style="display:grid;grid-template-columns:1.5fr 1.5fr 1fr auto;gap:.5rem;align-items:end;">
                    @csrf
                    <div>
                        <label class="form-label" style="font-size:.62rem;">Feature Key *</label>
                        <input type="text" name="feature_key" class="form-control @error('feature_key') is-invalid @enderror"
                            placeholder="export_excel" required style="padding:.4rem .6rem;font-size:.78rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.62rem;">Nama *</label>
                        <input type="text" name="name" class="form-control"
                            placeholder="Export Excel" required style="padding:.4rem .6rem;font-size:.78rem;">
                    </div>
                    <div>
                        <label class="form-label" style="font-size:.62rem;">Kategori</label>
                        <select name="category" class="form-control" style="padding:.4rem .6rem;font-size:.78rem;">
                            <option value="core">Core</option>
                            <option value="premium">Premium</option>
                            <option value="addon">Addon</option>
                        </select>
                    </div>
                    <div style="display:flex;flex-direction:column;gap:.35rem;">
                        <label style="display:flex;align-items:center;gap:.35rem;font-size:.7rem;cursor:pointer;white-space:nowrap;">
                            <input type="checkbox" name="requires_license" value="1"
                                style="width:14px;height:14px;accent-color:#1a3a6b;">
                            Butuh Lisensi
                        </label>
                        <button type="submit" class="btn btn-primary btn-sm">+ Tambah</button>
                    </div>
                </form>
            </div>

            {{-- Feature list --}}
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Feature Key</th>
                            <th>Nama</th>
                            <th>Kategori</th>
                            <th>Tipe</th>
                            <th>Status</th>
                            <th>Lisensi ke...</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($features as $feat)
                        <tr>
                            <td><code style="font-size:.72rem;">{{ $feat->feature_key }}</code></td>
                            <td style="font-size:.82rem;font-weight:500;">{{ $feat->name }}</td>
                            <td>
                                @php $catColor = ['core'=>'badge-blue','premium'=>'badge-green','addon'=>'badge-secondary']; @endphp
                                <span class="badge {{ $catColor[$feat->category] ?? 'badge-secondary' }}">{{ $feat->category ?? '—' }}</span>
                            </td>
                            <td>
                                @if($feat->requires_license)
                                    <span class="badge badge-warning" style="background:#fef3c7;color:#92400e;">🔑 Licensed</span>
                                @else
                                    <span class="badge badge-info">Free</span>
                                @endif
                            </td>
                            <td>
                                {{-- Toggle active/inactive --}}
                                <form method="POST" action="{{ route('master.apps.features.toggle', [Hashids::encode($app->id), $feat->id]) }}" style="display:inline;">
                                    @csrf
                                    <button type="submit"
                                        class="badge {{ $feat->is_active ? 'badge-success' : 'badge-secondary' }}"
                                        style="border:none;cursor:pointer;padding:.25rem .6rem;font-size:.65rem;"
                                        title="Klik untuk toggle">
                                        {{ $feat->is_active ? '✓ Aktif' : '✗ Nonaktif' }}
                                    </button>
                                </form>
                            </td>
                            <td>
                                @if($feat->requires_license)
                                    {{-- Show key management for licensed features --}}
                                    <div style="display:flex;flex-direction:column;gap:.3rem;">
                                        @if($feat->feature_license_key_hash)
                                            <div style="display:flex;gap:.3rem;flex-wrap:wrap;">
                                                <button onclick="retrieveFLK({{ $feat->id }}, '{{ Hashids::encode($app->id) }}')"
                                                    class="btn btn-secondary btn-sm" style="font-size:.65rem;padding:.2rem .5rem;">
                                                    Lihat Kunci
                                                </button>
                                                <form method="POST" action="{{ route('master.apps.features.regenerate-key', [Hashids::encode($app->id), $feat->id]) }}"
                                                    data-confirm="Generate kunci baru? Semua aktivasi lama akan dicabut." data-confirm-type="danger" data-confirm-title="Regenerate Kunci" data-confirm-ok="Ya, Generate">
                                                    @csrf
                                                    <button type="submit" class="btn btn-warning btn-sm" style="font-size:.65rem;padding:.2rem .5rem;">
                                                        Regenerate
                                                    </button>
                                                </form>
                                            </div>
                                            <div id="flk-result-{{ $feat->id }}" style="display:none;"></div>
                                            {{-- Active activations count --}}
                                            @php $activationCount = \App\Models\LicenseFeatureActivation::where('feature_key', $feat->feature_key)->where('app_code', $app->code)->where('status','active')->count(); @endphp
                                            @if($activationCount > 0)
                                            <span style="font-size:.65rem;color:#166534;">{{ $activationCount }} instalasi aktif</span>
                                            @endif
                                        @else
                                            <span style="font-size:.68rem;color:#94a3b8;font-style:italic;">Kunci belum dibuat</span>
                                        @endif
                                    </div>
                                @else
                                    {{-- Free feature: show license-to buttons --}}
                                    @if($licenseApps->isEmpty())
                                        <span style="font-size:.72rem;color:#94a3b8;">—</span>
                                    @else
                                        <div style="display:flex;gap:.3rem;flex-wrap:wrap;align-items:center;">
                                            @foreach($licenseApps as $la)
                                            @php
                                                $alreadyLicensed = $la->features()->where('feature_key', $feat->feature_key)->where('status','active')->exists();
                                                $licCompanyId = $la->licenseCompany?->id;
                                                $licHash = $licCompanyId ? Hashids::encode($licCompanyId) : null;
                                            @endphp
                                            @if(! $licHash)
                                                {{-- Orphan license_app — no company; skip --}}
                                                @continue
                                            @endif
                                            @if($alreadyLicensed)
                                                <span style="display:inline-flex;align-items:center;gap:.2rem;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:5px;padding:.12rem .4rem;font-size:.62rem;color:#166534;">
                                                    ✓ {{ $la->licenseCompany?->company?->name ?? 'Lic #'.$la->id }}
                                                </span>
                                            @else
                                                <form method="POST" action="{{ route('license.companies.features.add', $licHash) }}">
                                                    @csrf
                                                    <input type="hidden" name="license_app_id" value="{{ $la->id }}">
                                                    <input type="hidden" name="feature_key" value="{{ $feat->feature_key }}">
                                                    <button type="submit" style="display:inline-flex;align-items:center;gap:.2rem;background:#eff6ff;border:1px solid #bfdbfe;border-radius:5px;padding:.12rem .4rem;font-size:.62rem;color:#1e40af;cursor:pointer;">
                                                        + {{ $la->licenseCompany?->company?->name ?? 'Lic #'.$la->id }}
                                                    </button>
                                                </form>
                                            @endif
                                            @endforeach
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <form method="POST" action="{{ route('master.apps.features.destroy', [Hashids::encode($app->id), $feat->id]) }}"
                                    data-confirm="Hapus feature ini? Semua lisensi yang menggunakan feature ini akan terpengaruh." data-confirm-type="danger" data-confirm-title="Hapus Feature" data-confirm-ok="Ya, Hapus">
                                    @csrf @method('DELETE')
                                    <button type="submit" class="btn btn-danger btn-sm">Hapus</button>
                                </form>
                            </td>
                        </tr>
                        @empty
                        <tr><td colspan="7" style="text-align:center;color:#94a3b8;padding:2rem;">Belum ada feature.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
function retrieveFLK(featureId, appHash) {
    var btn = event.target;
    btn.disabled = true; btn.textContent = '...';
    fetch(window.location.origin + '/master/apps/' + appHash + '/features/' + featureId + '/retrieve-key', {
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json',
                   'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content }
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.textContent = 'Lihat Kunci';
        if (data.success) {
            GModal.alert({
                type: 'info',
                title: 'Feature License Key (FLK)',
                message: '<div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:8px;padding:.75rem 1rem;margin:.5rem 0;">'
                    + '<div style="font-size:.68rem;color:#1e40af;font-weight:700;margin-bottom:.35rem;">Feature License Key:</div>'
                    + '<div style="font-family:monospace;font-size:.9rem;color:#1a3a6b;font-weight:800;letter-spacing:.06em;word-break:break-all;">' + data.key + '</div>'
                    + '</div>'
                    + '<p style="font-size:.75rem;color:#64748b;margin-top:.5rem;">Berikan kunci ini ke client untuk aktivasi fitur di ERMv3.</p>',
                confirmText: 'Tutup',
            });
        } else {
            GToast.danger(data.message);
        }
    })
    .catch(() => { btn.disabled = false; btn.textContent = 'Lihat Kunci'; GToast.danger('Gagal menghubungi server.'); });
}
</script>
@endpush
