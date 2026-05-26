@extends('layouts.app')
@section('title', 'Heartbeat & Cron Setup')
@section('page-title', 'Heartbeat & Cron Setup')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a>
    <span class="breadcrumb-sep">/</span><span>Setup Heartbeat</span>
@endsection

@push('styles')
<style>
.live-banner { background: #fff; border: 2px solid #e2e8f0; border-radius: 10px; padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; margin-bottom: 1rem; }
.live-banner.healthy { border-color: #16a34a; background: #f0fdf4; }
.live-banner.warn    { border-color: #d97706; background: #fffbeb; }
.live-banner.broken  { border-color: #dc2626; background: #fef2f2; }
.live-banner.never   { border-color: #94a3b8; background: #f8fafc; }
.live-dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
.live-banner.healthy .live-dot { background: #16a34a; box-shadow: 0 0 0 4px rgba(22,163,74,.18); animation: pulse 2s infinite; }
.live-banner.warn    .live-dot { background: #d97706; box-shadow: 0 0 0 4px rgba(217,119,6,.18); }
.live-banner.broken  .live-dot { background: #dc2626; box-shadow: 0 0 0 4px rgba(220,38,38,.18); }
.live-banner.never   .live-dot { background: #94a3b8; }
@keyframes pulse { 0%,100% { box-shadow: 0 0 0 4px rgba(22,163,74,.18); } 50% { box-shadow: 0 0 0 8px rgba(22,163,74,.06); } }
.live-text { flex: 1; }
.live-text strong { display: block; font-size: 1rem; margin-bottom: .15rem; }

.stat { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: .85rem 1rem; }
.stat-label { font-size: .68rem; color: #64748b; text-transform: uppercase; letter-spacing: .04em; margin-bottom: .25rem; }
.stat-value { font-size: 1rem; font-weight: 600; color: #1a3a6b; }
.stat-sub   { font-size: .72rem; color: #94a3b8; margin-top: .15rem; }

.code-block { display: flex; gap: .5rem; align-items: stretch; margin: .75rem 0; }
.code-block code { flex: 1; background: #0f172a; color: #e2e8f0; padding: .65rem .85rem; border-radius: 6px; font-size: .76rem; word-break: break-all; }
</style>
@endpush

@section('content')
<div style="max-width: 980px; margin: 0 auto; display: flex; flex-direction: column; gap: 1.25rem;">

  {{-- ── LIVE BANNER ── --}}
  @php
    $tickStatus = $tickFreshness['status'];
    $bannerCls  = $tickStatus === 'healthy' ? 'healthy'
               : ($tickStatus === 'broken' ? 'broken'
               : ($tickStatus === 'stale' ? 'warn' : 'never'));
  @endphp
  <div class="live-banner {{ $bannerCls }}" id="liveBanner">
    <div class="live-dot"></div>
    <div class="live-text">
      <strong id="liveTitle">
        @if($tickStatus === 'healthy') ✓ Cron jalan normal
        @elseif($tickStatus === 'stale') ⚠ Cron agak telat
        @elseif($tickStatus === 'broken') ✗ Cron tidak jalan
        @else ◯ Cron belum pernah jalan
        @endif
      </strong>
      <span id="liveMessage">{{ $tickFreshness['message'] }}</span>
    </div>
    <div style="font-size: .68rem; color: #64748b;">Auto-refresh tiap 30 detik</div>
  </div>

  {{-- ── 1. SETUP CRON DI GEMILANG ── --}}
  <div class="card">
    <div class="card-header"><span class="card-title">Step 1 — Cron untuk Server Gemilang Ini</span></div>
    <div class="card-body">

      <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; margin-bottom: 1rem;">
        <div class="stat">
          <div class="stat-label">Server Type</div>
          <div class="stat-value">
            @if($capabilities['has_aapanel']) aaPanel
            @elseif($capabilities['os'] === 'Linux') Linux ({{ $capabilities['php_user'] }})
            @else {{ $capabilities['os'] }}
            @endif
          </div>
          <div class="stat-sub">PHP runs as: <code>{{ $capabilities['php_user'] }}</code></div>
        </div>
        <div class="stat">
          <div class="stat-label">Cron Daemon</div>
          <div class="stat-value">
            @if($capabilities['cron_running'])
              <span style="color:#16a34a;">● Running</span>
            @else
              <span style="color:#dc2626;">● Not running</span>
            @endif
          </div>
          <div class="stat-sub">{{ $capabilities['has_crontab'] ? 'crontab tersedia' : 'crontab tidak ada' }}</div>
        </div>
      </div>

      @if($existingEntry)
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#166534;">
          <strong>✓ Cron entry sudah terpasang</strong>
          <div style="margin-top:.35rem;font-size:.78rem;">
            Mode: <code>{{ $existingEntry['mode'] }}</code><br>
            Lokasi: <code>{{ $existingEntry['where'] }}</code>
          </div>
        </div>
        <button onclick="uninstallCron()" id="uninstallBtn" class="btn btn-danger btn-sm">Uninstall Cron Entry</button>
      @else
        <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.75rem 1rem;margin-bottom:1rem;font-size:.85rem;color:#78350f;">
          <strong>⚠ Cron belum terpasang</strong> — task scheduler tidak akan jalan otomatis.
        </div>

        @php
          $strategy = 'unsupported';
          $strategyLabel = 'Tidak ada mode tersedia';
          if ($capabilities['has_aapanel'] && $capabilities['aapanel_cron_writable'] && $capabilities['is_root']) {
            $strategy = 'aapanel'; $strategyLabel = 'aaPanel cron task';
          } elseif ($capabilities['cron_d_writable']) {
            $strategy = 'cron-d'; $strategyLabel = '/etc/cron.d/ fragment';
          } elseif ($capabilities['has_crontab']) {
            $strategy = 'user-crontab'; $strategyLabel = "User crontab ({$capabilities['php_user']})";
          }
        @endphp

        @if($strategy !== 'unsupported')
          <div style="display:flex;gap:.75rem;align-items:center;flex-wrap:wrap;">
            <button onclick="installCron()" id="installBtn" class="btn btn-success">
              ⚡ Install Cron Otomatis
            </button>
            <span style="font-size:.82rem;color:#64748b;">
              akan pakai mode: <strong>{{ $strategyLabel }}</strong>
            </span>
          </div>
          <div id="installResult"></div>
        @else
          <div style="background:#fef2f2;border:1px solid #fecaca;border-radius:8px;padding:.75rem 1rem;font-size:.85rem;color:#991b1b;">
            <strong>Tidak bisa auto-install.</strong> User <code>{{ $capabilities['php_user'] }}</code> tidak punya privilege.
            Tambahkan baris berikut secara manual via SSH:
          </div>

          <div class="code-block">
            <code id="serverCron">{{ $cronLine }}</code>
            <button onclick="copyCode('serverCron', this)" class="btn btn-primary btn-sm" style="white-space:nowrap;">Copy</button>
          </div>
        @endif
      @endif

      <h4 style="margin-top:1.25rem;margin-bottom:.5rem;">Detail Capabilities</h4>
      <table>
        <thead><tr><th>Capability</th><th>Status</th><th>Detail</th></tr></thead>
        <tbody>
          <tr><td>Running as root</td><td>{!! $capabilities['is_root'] ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-warning">NO</span>' !!}</td><td>{{ $capabilities['php_user'] }}</td></tr>
          <tr><td>aaPanel installed</td><td>{!! $capabilities['has_aapanel'] ? '<span class="badge badge-info">YES</span>' : '<span class="badge badge-secondary">NO</span>' !!}</td><td>{{ $capabilities['aapanel_cron_dir'] ?? '—' }}</td></tr>
          <tr><td>aaPanel cron writable</td><td>{!! $capabilities['aapanel_cron_writable'] ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-secondary">NO</span>' !!}</td><td>—</td></tr>
          <tr><td>/etc/cron.d/ writable</td><td>{!! $capabilities['cron_d_writable'] ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-secondary">NO</span>' !!}</td><td>—</td></tr>
          <tr><td>crontab command</td><td>{!! $capabilities['has_crontab'] ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-danger">NO</span>' !!}</td><td>—</td></tr>
          <tr><td>Cron daemon running</td><td>{!! $capabilities['cron_running'] ? '<span class="badge badge-success">YES</span>' : '<span class="badge badge-danger">NO</span>' !!}</td><td>—</td></tr>
        </tbody>
      </table>

      <h4 style="margin-top:1rem;margin-bottom:.5rem;">Test Scheduler Sekarang</h4>
      <button onclick="testScheduler()" id="testBtn" class="btn btn-success btn-sm">▶ Run schedule:run</button>
      <div id="testResult" style="margin-top:.75rem;"></div>
    </div>
  </div>

  {{-- ── 2. SNIPPET UNTUK CLIENT ── --}}
  <div class="card" style="border-left: 4px solid var(--success);">
    <div class="card-header"><span class="card-title">Step 2 — Setup Cron di Server Client (ERMv3)</span></div>
    <div class="card-body">
      <p style="font-size:.85rem;margin-bottom:.75rem;">
        Server client (ERMv3) juga butuh cron untuk heartbeat berkala ke gemilang. Berikan instruksi ini ke
        IT team client. <strong>Atau lebih mudah</strong>: minta mereka buka URL berikut untuk one-click install:
      </p>

      <div class="code-block">
        <code id="clientUrl">https://erm.&lt;client-domain&gt;/license/setup</code>
        <button onclick="copyCode('clientUrl', this)" class="btn btn-primary btn-sm" style="white-space:nowrap;">Copy</button>
      </div>

      <p style="font-size:.82rem;color:#64748b;margin-top:.5rem;">
        Halaman <code>/license/setup</code> di sisi client punya tombol <strong>⚡ Install Cron Otomatis</strong> yang sama,
        deteksi aaPanel/Ubuntu, dan monitor heartbeat live.
      </p>

      <h4 style="margin-top:1.25rem;margin-bottom:.5rem;">Snippet Cron Manual (kalau auto-install tidak bisa)</h4>
      <div class="code-block">
        <code id="clientCron">{{ $clientCronExample }}</code>
        <button onclick="copyCode('clientCron', this)" class="btn btn-primary btn-sm" style="white-space:nowrap;">Copy</button>
      </div>
      <div style="font-size:.7rem;color:#64748b;">Ganti path sesuai lokasi instalasi ERMv3 di server client.</div>
    </div>
  </div>

  {{-- ── 3. SCHEDULES ── --}}
  <div class="card">
    <div class="card-header"><span class="card-title">Schedules Terdaftar</span></div>
    <div class="card-body">
      <pre style="background:#0f172a;color:#e2e8f0;padding:.85rem 1rem;border-radius:8px;font-size:.72rem;line-height:1.55;overflow-x:auto;max-height:240px;">@foreach($scheduledCommands as $line){{ $line }}
@endforeach</pre>
    </div>
  </div>

  {{-- ── 4. REFERENCE ── --}}
  <div class="card" style="background:#f0fdf4;border-color:#bbf7d0;">
    <div class="card-body" style="font-size:.82rem;">
      <strong>📖 Panduan lengkap:</strong>
      <a href="{{ route('guide.lisensi') }}" target="_blank" rel="noopener" style="color:var(--primary);">Buka Panduan Lisensi</a>
      → section <strong>Heartbeat Setup</strong>
    </div>
  </div>
</div>

<script>
const csrf = () => document.querySelector('meta[name="csrf-token"]').content;

function copyCode(id, btn) {
  const text = document.getElementById(id).textContent.trim();
  navigator.clipboard.writeText(text).then(() => {
    const orig = btn.textContent;
    btn.textContent = '✓ Tersalin';
    setTimeout(() => { btn.textContent = orig; }, 2000);
  });
}

async function installCron() {
  const btn = document.getElementById('installBtn');
  const result = document.getElementById('installResult');
  btn.disabled = true; btn.textContent = 'Installing...';

  try {
    const r = await fetch('{{ route("system.heartbeat-setup.install") }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    const data = await r.json();

    let html = '<div style="background:' + (data.success ? '#f0fdf4' : '#fef2f2') + ';border:1px solid '
      + (data.success ? '#bbf7d0' : '#fecaca') + ';border-radius:8px;padding:.75rem 1rem;margin-top:.75rem;font-size:.85rem;color:'
      + (data.success ? '#166534' : '#991b1b') + ';">';
    html += '<strong>' + (data.success ? '✓ ' : '✗ ') + data.message + '</strong>';
    if (data.mode) html += '<div style="margin-top:.35rem;font-size:.78rem;">Mode: <code>' + data.mode + '</code></div>';
    if (data.detail) html += '<pre style="margin-top:.4rem;font-size:.7rem;max-height:120px;background:#0f172a;color:#e2e8f0;padding:.5rem .75rem;border-radius:6px;">' + JSON.stringify(data.detail, null, 2) + '</pre>';
    html += '</div>';
    result.innerHTML = html;

    if (data.success) {
      setTimeout(() => window.location.reload(), 2000);
    } else {
      btn.disabled = false; btn.textContent = '⚡ Install Cron Otomatis';
    }
  } catch (e) {
    btn.disabled = false; btn.textContent = '⚡ Install Cron Otomatis';
    result.innerHTML = '<div style="color:#991b1b;margin-top:.5rem;">Network error: ' + e.message + '</div>';
  }
}

async function uninstallCron() {
  if (! confirm('Yakin uninstall cron entry?')) return;
  const btn = document.getElementById('uninstallBtn');
  btn.disabled = true; btn.textContent = 'Removing...';

  try {
    const r = await fetch('{{ route("system.heartbeat-setup.uninstall") }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    const data = await r.json();
    if (window.GToast) GToast[data.success ? 'success' : 'danger'](data.message);
    else alert((data.success ? '✓ ' : '✗ ') + data.message);
    if (data.success) window.location.reload();
    else { btn.disabled = false; btn.textContent = 'Uninstall Cron Entry'; }
  } catch (e) {
    btn.disabled = false; btn.textContent = 'Uninstall Cron Entry';
  }
}

async function testScheduler() {
  const btn = document.getElementById('testBtn');
  const result = document.getElementById('testResult');
  btn.disabled = true; btn.textContent = 'Running...';

  try {
    const r = await fetch('{{ route("system.heartbeat-setup.test") }}', {
      method: 'POST',
      headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json' },
      credentials: 'same-origin'
    });
    const data = await r.json();
    btn.disabled = false; btn.textContent = '▶ Run schedule:run';

    let body = '<strong>' + (data.success ? '✓ ' : '✗ ') + data.message + '</strong>';
    if (data.output) body += '<pre style="margin-top:.4rem;font-size:.7rem;max-height:160px;background:#0f172a;color:#e2e8f0;padding:.5rem .75rem;border-radius:6px;">' + escapeHtml(data.output) + '</pre>';
    if (data.last_run) body += '<div style="font-size:.7rem;margin-top:.3rem;color:#64748b;">Last run: ' + data.last_run + '</div>';
    result.innerHTML = '<div style="background:' + (data.success ? '#f0fdf4' : '#fef2f2') + ';border:1px solid '
      + (data.success ? '#bbf7d0' : '#fecaca') + ';border-radius:8px;padding:.65rem .85rem;font-size:.85rem;color:'
      + (data.success ? '#166534' : '#991b1b') + ';">' + body + '</div>';
  } catch (e) {
    btn.disabled = false; btn.textContent = '▶ Run schedule:run';
  }
}

function escapeHtml(s) {
  return String(s).replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'})[c]);
}

async function pollStatus() {
  try {
    const r = await fetch('{{ route("system.heartbeat-setup.status") }}', {
      headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    });
    const res = await r.json();
    if (! res.success) return;

    const tick = res.data.tick;
    const banner = document.getElementById('liveBanner');
    const title = document.getElementById('liveTitle');
    const message = document.getElementById('liveMessage');

    banner.classList.remove('healthy', 'warn', 'broken', 'never');
    if (tick.status === 'healthy') {
      banner.classList.add('healthy');
      title.textContent = '✓ Cron jalan normal';
    } else if (tick.status === 'stale') {
      banner.classList.add('warn');
      title.textContent = '⚠ Cron agak telat';
    } else if (tick.status === 'broken') {
      banner.classList.add('broken');
      title.textContent = '✗ Cron tidak jalan';
    } else {
      banner.classList.add('never');
      title.textContent = '◯ Cron belum pernah jalan';
    }
    message.textContent = tick.message;
  } catch (e) {
    // silent
  }
}

setInterval(pollStatus, 30000);
</script>
@endsection
