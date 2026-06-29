@extends('layouts.app')
@section('title', 'Heartbeat Monitor')
@section('page-title', 'Heartbeat Monitor')

@push('styles')
<style>
  .hm-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .85rem; margin-bottom: 1.25rem; }
  .hm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: .9rem 1rem; }
  .hm-card .lbl { font-size: .64rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .3rem; }
  .hm-card .val { font-size: 1.5rem; font-weight: 800; color: #1a3a6b; line-height: 1; }
  .hm-card .sub { font-size: .68rem; color: #94a3b8; margin-top: .25rem; }
  .hm-card.ok { border-left: 4px solid #16a34a; }
  .hm-card.warn { border-left: 4px solid #d97706; }
  .hm-card.bad { border-left: 4px solid #dc2626; }
  .hm-card.info { border-left: 4px solid #29abe2; }

  .hm-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 1.25rem; overflow: hidden; }
  .hm-panel h2 { font-size: .95rem; color: #1a3a6b; padding: .85rem 1.1rem; border-bottom: 1px solid #e2e8f0; margin: 0; display: flex; justify-content: space-between; align-items: center; }
  .hm-panel h2 .muted { font-size: .68rem; color: #94a3b8; font-weight: 500; }

  table.hm { width: 100%; border-collapse: collapse; }
  table.hm th { background: #f8fafc; color: #475569; padding: .5rem .8rem; text-align: left; font-size: .64rem; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e2e8f0; }
  table.hm td { padding: .5rem .8rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; vertical-align: middle; }
  table.hm tr:last-child td { border-bottom: none; }
  table.hm .mono { font-family: monospace; font-size: .72rem; color: #64748b; }

  .badge { display: inline-flex; align-items: center; padding: .12rem .5rem; border-radius: 10px; font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
  .b-online { background: #dcfce7; color: #166534; }
  .b-late   { background: #fef3c7; color: #92400e; }
  .b-expired{ background: #fee2e2; color: #991b1b; }
  .b-never  { background: #e2e8f0; color: #475569; }
  .b-ok     { background: #dcfce7; color: #166534; }
  .b-bad    { background: #fee2e2; color: #991b1b; }

  .hm-empty { padding: 1.25rem; text-align: center; color: #94a3b8; font-size: .82rem; }
  .hm-bar { display: flex; gap: .5rem; align-items: center; margin-bottom: 1rem; }
  .hm-bar .muted { font-size: .72rem; color: #94a3b8; }
  .hm-refresh { margin-left: auto; padding: .4rem .8rem; border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; font-size: .78rem; cursor: pointer; color: #1a3a6b; }
</style>
@endpush

@section('content')
<div class="hm-bar">
  <span class="muted">Memantau heartbeat semua instalasi klien. TTL token: <strong>{{ $report['ttl_days'] }} hari</strong> · interval: <strong>{{ (int) round($report['interval_seconds']/60) }} mnt</strong>.</span>
  <button class="hm-refresh" id="hmRefresh">↻ Muat ulang</button>
  <span class="muted" id="hmUpdated"></span>
</div>

<div class="hm-cards" id="hmCards"></div>

<div class="hm-panel">
  <h2>Perlu Perhatian <span class="muted">klien telat / token kemungkinan sudah kedaluwarsa</span></h2>
  <div id="hmAttentionWrap"></div>
</div>

<div class="hm-panel">
  <h2>Semua Instalasi Aktif</h2>
  <div id="hmInstWrap"></div>
</div>

<div class="hm-panel">
  <h2>Log Heartbeat Terbaru <span class="muted">termasuk kegagalan + alasannya</span></h2>
  <div id="hmRecentWrap"></div>
</div>

<script>
  const HM_DATA_URL = @json(route('heartbeat.monitor.data'));
  let hm = @json($report);

  const esc = s => (s === null || s === undefined) ? '' : String(s)
    .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  function rel(iso) {
    if (!iso) return '—';
    const d = new Date(iso), now = new Date();
    let s = Math.round((now - d) / 1000);
    if (s < 0) s = 0;
    if (s < 60) return s + ' dtk lalu';
    if (s < 3600) return Math.floor(s/60) + ' mnt lalu';
    if (s < 86400) return Math.floor(s/3600) + ' jam lalu';
    return Math.floor(s/86400) + ' hari lalu';
  }
  function fmt(iso){ if(!iso) return '—'; try { return new Date(iso).toLocaleString('id-ID'); } catch(e){ return iso; } }

  const healthBadge = h => {
    const map = { online:['b-online','ONLINE'], late:['b-late','TELAT'], expired:['b-expired','EXPIRED'], never:['b-never','BELUM'] };
    const [cls,lbl] = map[h] || ['b-never', (h||'?').toUpperCase()];
    return `<span class="badge ${cls}">${lbl}</span>`;
  };

  function renderCards() {
    const c = hm.counts || {};
    const cards = [
      { cls:'info', lbl:'Total Aktif', val:c.total||0, sub:'instalasi terlisensi' },
      { cls:'ok',   lbl:'Online',      val:c.online||0, sub:'heartbeat baru saja' },
      { cls:'warn', lbl:'Telat',       val:c.late||0,   sub:'masih dalam masa token' },
      { cls:'bad',  lbl:'Expired',     val:c.expired||0,sub:'token kemungkinan habis → aktivasi' },
      { cls:'ok',   lbl:'Heartbeat 24j (OK)', val:hm.heartbeats_24h_ok||0, sub:'sukses' },
      { cls:(hm.heartbeats_24h_bad>0?'bad':'info'), lbl:'Heartbeat 24j (Gagal)', val:hm.heartbeats_24h_bad||0, sub:'gagal/ditolak' },
      { cls:(hm.unreviewed_suspicious>0?'warn':'info'), lbl:'Suspicious', val:hm.unreviewed_suspicious||0, sub:'belum direview' },
    ];
    document.getElementById('hmCards').innerHTML = cards.map(x => `
      <div class="hm-card ${x.cls}">
        <div class="lbl">${esc(x.lbl)}</div>
        <div class="val">${esc(x.val)}</div>
        <div class="sub">${esc(x.sub)}</div>
      </div>`).join('');
  }

  function instRow(i) {
    const loc = [i.domain, i.ip_address].filter(Boolean).map(esc).join('<br>') || '—';
    let nextTxt = '—';
    if (i.health === 'expired') nextTxt = `silent ${i.days_since} hari`;
    else if (i.next_in_secs != null) nextTxt = i.next_in_secs > 0 ? `~${Math.round(i.next_in_secs/60)} mnt lagi` : 'terlambat';
    return `<tr>
      <td>${esc(i.company)}</td>
      <td><span class="badge b-never">${esc(i.app_code)}</span></td>
      <td class="mono">${loc}</td>
      <td class="mono">${esc(i.app_version||'—')}</td>
      <td>${rel(i.last_heartbeat)}<div class="mono">${fmt(i.last_heartbeat)}</div></td>
      <td>${healthBadge(i.health)}</td>
      <td class="mono">${esc(nextTxt)}</td>
    </tr>`;
  }

  function renderTable(elId, rows, emptyMsg) {
    const el = document.getElementById(elId);
    if (!rows || !rows.length) { el.innerHTML = `<div class="hm-empty">${esc(emptyMsg)}</div>`; return; }
    el.innerHTML = `<table class="hm"><thead><tr>
        <th>Perusahaan</th><th>App</th><th>Domain / IP</th><th>Versi</th><th>Heartbeat Terakhir</th><th>Status</th><th>Berikutnya</th>
      </tr></thead><tbody>${rows.map(instRow).join('')}</tbody></table>`;
  }

  function renderRecent() {
    const el = document.getElementById('hmRecentWrap');
    const rows = hm.recent || [];
    if (!rows.length) { el.innerHTML = `<div class="hm-empty">Belum ada log heartbeat.</div>`; return; }
    el.innerHTML = `<table class="hm"><thead><tr>
        <th>Waktu</th><th>Perusahaan</th><th>App</th><th>IP</th><th>Status</th><th>Keterangan</th>
      </tr></thead><tbody>${rows.map(h => `<tr>
        <td>${rel(h.at)}<div class="mono">${fmt(h.at)}</div></td>
        <td>${esc(h.company)}</td>
        <td class="mono">${esc(h.app_code)}</td>
        <td class="mono">${esc(h.ip_address||'—')}</td>
        <td><span class="badge ${h.ok?'b-ok':'b-bad'}">${esc((h.status||'?').toUpperCase())}</span></td>
        <td>${esc(h.reason||(h.ok?'—':''))}</td>
      </tr>`).join('')}</tbody></table>`;
  }

  function render() {
    renderCards();
    renderTable('hmAttentionWrap', hm.attention, 'Tidak ada klien yang telat. Semua sehat. 🎉');
    renderTable('hmInstWrap', hm.installations, 'Belum ada instalasi aktif.');
    renderRecent();
    document.getElementById('hmUpdated').textContent = 'Diperbarui: ' + fmt(hm.now);
  }

  async function refresh() {
    try {
      const r = await fetch(HM_DATA_URL, { headers: { 'Accept':'application/json', 'X-Requested-With':'XMLHttpRequest' }, credentials:'same-origin' });
      const j = await r.json();
      if (j.success) { hm = j.data; render(); }
    } catch (e) { /* keep last render */ }
  }

  document.getElementById('hmRefresh').addEventListener('click', refresh);
  render();
  setInterval(refresh, 20000);
</script>
@endsection
