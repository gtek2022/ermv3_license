@extends('layouts.app')
@section('title', 'Setup Heartbeat & Scheduler')
@section('page-title', 'Setup Heartbeat & Scheduler')
@section('breadcrumb')
    <a href="{{ route('dashboard') }}">Home</a>
    <span class="breadcrumb-sep">/</span><span>Setup Heartbeat</span>
@endsection

@section('content')
<div style="max-width:900px;margin:0 auto;display:flex;flex-direction:column;gap:1.25rem;">

    {{-- ── Intro ── --}}
    <div class="card">
        <div class="card-body">
            <h3 style="margin-top:0;color:var(--primary);font-size:1.1rem;">Setup Cron & Heartbeat — Quick Start</h3>
            <p style="font-size:.85rem;color:var(--text-muted);">
                Halaman ini bantu setup cron Laravel di <strong>server Gemilang</strong> (sini) dan kasih
                snippet untuk pasang di server <strong>client (ERMv3)</strong>.
            </p>
        </div>
    </div>

    {{-- ── 1. Server Gemilang Setup ── --}}
    <div class="card" style="border-left:4px solid var(--primary-mid);">
        <div class="card-header">
            <span class="card-title">Step 1 — Setup Cron di Server Ini (Gemilang)</span>
        </div>
        <div class="card-body">
            <p style="font-size:.85rem;margin-bottom:.75rem;">
                Server Gemilang perlu cron untuk task-task berkala (cek expirations, notifications, key rotation hint).
            </p>

            <div style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:.85rem;margin-bottom:1rem;">
                <div style="font-size:.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:.4rem;">Tambahkan ke crontab</div>
                <div style="display:flex;gap:.5rem;align-items:stretch;">
                    <code id="serverCron" style="flex:1;background:#0f172a;color:#e2e8f0;padding:.65rem .85rem;border-radius:6px;font-size:.78rem;word-break:break-all;font-family:monospace;">{{ $serverCronLine }}</code>
                    <button onclick="copyCode('serverCron', this)" class="btn btn-primary btn-sm" style="white-space:nowrap;">Copy</button>
                </div>
            </div>

            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:8px;padding:.65rem .85rem;font-size:.78rem;color:#78350f;margin-bottom:1rem;">
                <strong>Cara pasang:</strong> SSH ke server → jalankan <code style="background:#fff;padding:.1rem .35rem;border-radius:3px;">crontab -e</code> → paste baris di atas → save (Ctrl+X, Y, Enter di nano).
            </div>

            <h4 style="margin-bottom:.5rem;">Status di Server Gemilang Sekarang</h4>
            <table>
                <thead><tr><th>Cek</th><th>Status</th><th>Detail</th></tr></thead>
                <tbody>
                    @foreach($checks as $k => $c)
                    <tr>
                        <td>{{ $c['label'] }}</td>
                        <td>
                            @if($c['ok'])
                                <span class="badge badge-success">✓ OK</span>
                            @else
                                <span class="badge badge-danger">✗ Fail</span>
                            @endif
                        </td>
                        <td style="font-family:monospace;font-size:.72rem;color:var(--text-muted);word-break:break-all;">{{ $c['value'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>

            <h4 style="margin-top:1.25rem;margin-bottom:.5rem;">Test Scheduler Sekarang</h4>
            <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem;">
                Klik tombol di bawah untuk jalankan <code>php artisan schedule:run</code> manual dan verifikasi semuanya jalan.
            </p>
            <button onclick="testScheduler()" id="testBtn" class="btn btn-success btn-sm">▶ Test Scheduler</button>
            <div id="testResult" style="display:none;margin-top:.75rem;padding:.65rem .85rem;border-radius:8px;font-size:.78rem;"></div>

            @if($lastRun)
            <div style="font-size:.72rem;color:var(--text-muted);margin-top:.6rem;">
                Last cron run: <strong>{{ $lastRun->diffForHumans() }}</strong> ({{ $lastRun->format('d M Y H:i:s') }})
            </div>
            @endif

            <h4 style="margin-top:1.25rem;margin-bottom:.5rem;">Schedule Terdaftar</h4>
            <pre style="background:#0f172a;color:#e2e8f0;padding:.85rem 1rem;border-radius:8px;font-size:.72rem;line-height:1.55;overflow-x:auto;max-height:240px;">@foreach($scheduledCommands as $line){{ $line }}
@endforeach</pre>
        </div>
    </div>

    {{-- ── 2. Client Setup ── --}}
    <div class="card" style="border-left:4px solid var(--success);">
        <div class="card-header">
            <span class="card-title">Step 2 — Setup Cron di Server Client (ERMv3)</span>
        </div>
        <div class="card-body">
            <p style="font-size:.85rem;margin-bottom:.75rem;">
                Setiap server yang menjalankan ERMv3 (atau aplikasi client lain) perlu cron untuk heartbeat berkala
                ke gemilang. Tanpa cron → heartbeat tidak jalan → setelah grace period habis, aplikasi locked.
            </p>

            <div style="background:#f8fafc;border:1px solid var(--border);border-radius:8px;padding:.85rem;margin-bottom:1rem;">
                <div style="font-size:.7rem;color:var(--text-muted);font-weight:700;text-transform:uppercase;margin-bottom:.4rem;">Snippet cron untuk client</div>
                <div style="display:flex;gap:.5rem;align-items:stretch;">
                    <code id="clientCron" style="flex:1;background:#0f172a;color:#e2e8f0;padding:.65rem .85rem;border-radius:6px;font-size:.78rem;word-break:break-all;font-family:monospace;">{{ $genericCronLine }}</code>
                    <button onclick="copyCode('clientCron', this)" class="btn btn-primary btn-sm" style="white-space:nowrap;">Copy</button>
                </div>
                <div style="font-size:.7rem;color:var(--text-muted);margin-top:.45rem;">
                    Ganti <code style="background:#fff;padding:.05rem .3rem;border-radius:3px;">/www/wwwroot/erm.client.com</code> dengan path aktual di server client.
                </div>
            </div>

            <h4 style="margin-bottom:.5rem;">Instruksi untuk Client (Copy-Paste ke Email)</h4>
            <div style="background:#fff;border:1.5px dashed #cbd5e1;border-radius:8px;padding:1rem;font-size:.82rem;line-height:1.65;">
                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.65rem;">
                    <strong>Setup Heartbeat ERMv3</strong>
                    <button onclick="copyInstruction(this)" id="copyInstr" class="btn btn-secondary btn-sm" style="font-size:.7rem;">Copy semua</button>
                </div>
                <div id="instructionText">
                    <p>Halo Tim IT,</p>
                    <p>Setelah aktivasi lisensi ERMv3 selesai, mohon setup cron untuk heartbeat berkala:</p>
                    <ol style="margin-left:1.25rem;">
                        <li>SSH ke server tempat ERMv3 di-install</li>
                        <li>Jalankan <code>crontab -e</code></li>
                        <li>Tambah baris berikut (ganti path sesuai lokasi ERMv3):
                            <pre style="background:#f1f5f9;padding:.5rem .75rem;border-radius:6px;font-size:.75rem;margin:.4rem 0;font-family:monospace;">{{ $genericCronLine }}</pre>
                        </li>
                        <li>Save (di nano: Ctrl+X, Y, Enter)</li>
                        <li>Verifikasi: <code>php artisan schedule:list</code> harus menampilkan <code>license:heartbeat</code></li>
                        <li>Tunggu 1 jam, atau test manual: <code>php artisan license:heartbeat</code></li>
                    </ol>
                    <p style="margin-top:.75rem;color:var(--text-muted);font-size:.78rem;">
                        <strong>Tanpa cron ini</strong>: aplikasi tetap berjalan ~7 hari (grace period), tapi setelah itu otomatis locked sampai heartbeat sukses.
                    </p>
                </div>
            </div>

            <h4 style="margin-top:1.25rem;margin-bottom:.5rem;">Yang Otomatis di ERMv3</h4>
            <ul style="font-size:.82rem;line-height:1.7;color:var(--text);">
                <li>✓ Backend scheduler — sudah ter-register oleh package <code>laravel-licensing-client</code> (jadwal default tiap jam)</li>
                <li>✓ Frontend banner & lockout modal — sudah ter-include via <code>@include('components.license-banner')</code> di <code>layouts/app.blade.php</code></li>
                <li>✓ JavaScript poller — auto polling tiap 5 menit ke <code>/license/heartbeat-status</code></li>
                <li>✓ Endpoint backend — <code>GET /license/heartbeat-status</code> & <code>POST /license/heartbeat-ping</code></li>
            </ul>

            <p style="font-size:.82rem;color:var(--text);margin-top:.75rem;">
                <strong>Yang HARUS dilakukan client</strong>: tambahkan baris cron di atas. Selebihnya zero config.
            </p>
        </div>
    </div>

    {{-- ── 3. Tuning ── --}}
    <div class="card" style="border-left:4px solid var(--warning);">
        <div class="card-header">
            <span class="card-title">Step 3 — Tuning Heartbeat Behavior (Opsional)</span>
        </div>
        <div class="card-body">
            <p style="font-size:.85rem;margin-bottom:.75rem;">
                Default values bisa di-override per license atau global.
            </p>

            <h4>Override Global (Semua Client)</h4>
            <p style="font-size:.78rem;color:var(--text-muted);margin-bottom:.5rem;">
                <a href="{{ route('master.configs.index') }}" style="color:var(--primary);">Master Data → Configs</a> → tambah/edit key:
            </p>
            <table>
                <thead><tr><th>Config Key</th><th>Default</th><th>Penjelasan</th></tr></thead>
                <tbody>
                    <tr><td><code>licensing.heartbeat_interval</code></td><td>3600</td><td>Detik antar heartbeat</td></tr>
                    <tr><td><code>licensing.heartbeat_retry_limit</code></td><td>3</td><td>Berapa kali fail sebelum warning</td></tr>
                    <tr><td><code>licensing.warning_days</code></td><td>3</td><td>Hari warning sebelum lockout</td></tr>
                    <tr><td><code>licensing.grace_period_days</code></td><td>7</td><td>Hari toleransi server unreachable</td></tr>
                    <tr><td><code>licensing.timeout</code></td><td>30</td><td>HTTP timeout (detik)</td></tr>
                </tbody>
            </table>

            <h4 style="margin-top:1rem;">Override per License</h4>
            <p style="font-size:.78rem;color:var(--text-muted);">
                Buka <a href="{{ route('license.companies.index') }}" style="color:var(--primary);">Licenses</a> → klik license → section "Heartbeat Policy Override".
            </p>
        </div>
    </div>

    {{-- ── 4. Reference ── --}}
    <div class="card" style="background:#f0fdf4;border-color:#bbf7d0;">
        <div class="card-body" style="font-size:.82rem;">
            <strong>📖 Panduan lengkap:</strong>
            <a href="{{ route('guide.lisensi') }}" target="_blank" rel="noopener" style="color:var(--primary);">Buka Panduan Lisensi</a>
            (section <strong>Heartbeat Setup</strong> &amp; <strong>Tuning Heartbeat &amp; Policy</strong>)
        </div>
    </div>

</div>

<script>
function copyCode(id, btn) {
    const text = document.getElementById(id).textContent.trim();
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Tersalin';
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-success');
        setTimeout(() => {
            btn.textContent = orig;
            btn.classList.add('btn-primary');
            btn.classList.remove('btn-success');
        }, 2000);
    });
}

function copyInstruction(btn) {
    const text = document.getElementById('instructionText').innerText;
    navigator.clipboard.writeText(text).then(() => {
        const orig = btn.textContent;
        btn.textContent = '✓ Tersalin';
        setTimeout(() => { btn.textContent = orig; }, 2000);
    });
}

function testScheduler() {
    const btn    = document.getElementById('testBtn');
    const result = document.getElementById('testResult');
    btn.disabled = true;
    btn.textContent = 'Testing...';
    result.style.display = 'none';

    fetch('{{ route("system.heartbeat-setup.test") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json',
        },
        credentials: 'same-origin'
    })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        btn.textContent = '▶ Test Scheduler';
        result.style.display = 'block';
        if (data.success) {
            result.style.background = '#f0fdf4';
            result.style.border = '1px solid #bbf7d0';
            result.style.color = '#166534';
            result.innerHTML = '<strong>✓ ' + data.message + '</strong>'
                + '<div style="font-family:monospace;margin-top:.4rem;font-size:.7rem;white-space:pre-wrap;">' + (data.output || '(no output)') + '</div>'
                + '<div style="margin-top:.4rem;font-size:.7rem;color:#15803d;">Last run: ' + data.last_run + '</div>';
        } else {
            result.style.background = '#fef2f2';
            result.style.border = '1px solid #fecaca';
            result.style.color = '#991b1b';
            result.textContent = '✗ ' + data.message;
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.textContent = '▶ Test Scheduler';
        result.style.display = 'block';
        result.style.background = '#fef2f2';
        result.style.border = '1px solid #fecaca';
        result.style.color = '#991b1b';
        result.textContent = '✗ Network error: ' + err.message;
    });
}
</script>
@endsection
