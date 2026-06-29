@extends('layouts.app')
@section('title', 'Heartbeat Monitor')
@section('page-title', 'Heartbeat Monitor')

@push('styles')
<style>
  .hm-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: .85rem; margin-bottom: 1.25rem; }
  .hm-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; padding: .9rem 1rem; cursor: pointer; transition: box-shadow .15s; }
  .hm-card:hover { box-shadow: 0 4px 14px rgba(0,0,0,.08); }
  .hm-card .lbl { font-size: .64rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .3rem; }
  .hm-card .val { font-size: 1.5rem; font-weight: 800; color: #1a3a6b; line-height: 1; }
  .hm-card .sub { font-size: .68rem; color: #94a3b8; margin-top: .25rem; }
  .hm-card.ok { border-left: 4px solid #16a34a; } .hm-card.warn { border-left: 4px solid #d97706; }
  .hm-card.bad { border-left: 4px solid #dc2626; } .hm-card.info { border-left: 4px solid #29abe2; }

  .hm-panel { background: #fff; border: 1px solid #e2e8f0; border-radius: 10px; margin-bottom: 1.25rem; overflow: hidden; }
  .hm-panel h2 { font-size: .95rem; color: #1a3a6b; padding: .85rem 1.1rem; border-bottom: 1px solid #e2e8f0; margin: 0; display: flex; justify-content: space-between; align-items: center; }
  .hm-panel h2 .muted { font-size: .68rem; color: #94a3b8; font-weight: 500; }

  table.hm { width: 100%; border-collapse: collapse; }
  table.hm th { background: #f8fafc; color: #475569; padding: .5rem .8rem; text-align: left; font-size: .64rem; text-transform: uppercase; letter-spacing: .04em; border-bottom: 1px solid #e2e8f0; }
  table.hm td { padding: .5rem .8rem; border-bottom: 1px solid #f1f5f9; font-size: .8rem; vertical-align: middle; }
  table.hm tr:last-child td { border-bottom: none; }
  table.hm .mono { font-family: monospace; font-size: .72rem; color: #64748b; }

  .badge { display: inline-flex; align-items: center; padding: .12rem .5rem; border-radius: 10px; font-size: .64rem; font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
  .b-online { background: #dcfce7; color: #166534; } .b-late { background: #fef3c7; color: #92400e; }
  .b-expired{ background: #fee2e2; color: #991b1b; } .b-never { background: #e2e8f0; color: #475569; }
  .b-ok { background: #dcfce7; color: #166534; } .b-bad { background: #fee2e2; color: #991b1b; }

  .hm-empty { padding: 1.25rem; text-align: center; color: #94a3b8; font-size: .82rem; }
  .hm-bar { display: flex; gap: .5rem; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; }
  .hm-bar .muted { font-size: .72rem; color: #94a3b8; }
  .hm-btn { padding: .4rem .8rem; border: 1px solid #e2e8f0; background: #fff; border-radius: 8px; font-size: .78rem; cursor: pointer; color: #1a3a6b; }
  .hm-btn:hover { background: #f1f5f9; }
  .hm-diag { padding: .25rem .6rem; border: 1px solid #29abe2; background: #eff6ff; color: #1a3a6b; border-radius: 7px; font-size: .68rem; font-weight: 700; cursor: pointer; }
  .hm-diag:hover { background: #dbeafe; }
  .hm-client { display:inline-block; margin-left:.35rem; padding: .25rem .55rem; border: 1px solid #cbd5e1; background:#fff; color:#475569; border-radius:7px; font-size:.68rem; font-weight:700; text-decoration:none; }
  .hm-client:hover { background:#f1f5f9; }

  .hm-tabs { display: flex; gap: .35rem; flex-wrap: wrap; }
  .hm-tab { padding: .35rem .75rem; border: 1px solid #e2e8f0; background: #fff; border-radius: 999px; font-size: .74rem; cursor: pointer; color: #475569; }
  .hm-tab.active { background: #1a3a6b; color: #fff; border-color: #1a3a6b; }
  .hm-search { padding: .4rem .7rem; border: 1px solid #e2e8f0; border-radius: 8px; font-size: .78rem; min-width: 200px; }
  .hm-pager { display: flex; gap: .4rem; align-items: center; justify-content: flex-end; padding: .6rem .8rem; font-size: .76rem; color: #64748b; }
  .hm-pager button { border: 1px solid #e2e8f0; background: #fff; border-radius: 6px; padding: .25rem .6rem; cursor: pointer; }
  .hm-pager button:disabled { opacity: .4; cursor: not-allowed; }
</style>
@endpush

@section('content')
<div class="hm-bar">
  <span class="muted">Memantau heartbeat semua instalasi klien. TTL token: <strong>{{ $report['ttl_days'] }} hari</strong> · interval: <strong>{{ (int) round($report['interval_seconds']/60) }} mnt</strong>.</span>
  <button class="hm-btn" id="hmRefresh">↻ Muat ulang</button>
  <span class="muted" id="hmUpdated"></span>
</div>

<div class="hm-cards" id="hmCards"></div>

<div class="hm-panel">
  <h2>⚠️ Perlu Perhatian <span class="muted">klien telat / token kemungkinan kedaluwarsa — klik Diagnose</span></h2>
  <div id="hmAttentionWrap"></div>
</div>

<div class="hm-panel">
  <h2>Semua Instalasi Aktif</h2>
  <div class="hm-bar" style="padding:.7rem .9rem 0;margin:0;">
    <div class="hm-tabs" id="hmTabs">
      <span class="hm-tab active" data-f="all">Semua</span>
      <span class="hm-tab" data-f="online">Online</span>
      <span class="hm-tab" data-f="late">Telat</span>
      <span class="hm-tab" data-f="expired">Expired</span>
    </div>
    <input type="text" class="hm-search" id="hmSearch" placeholder="Cari perusahaan / domain / IP / app…">
  </div>
  <div id="hmInstWrap"></div>
  <div class="hm-pager" id="hmInstPager"></div>
</div>

<div class="hm-panel">
  <h2>Log Heartbeat <span class="muted">riwayat lengkap — termasuk kegagalan + alasannya</span></h2>
  <div class="hm-bar" style="padding:.7rem .9rem 0;margin:0;">
    <div class="hm-tabs" id="hmLogTabs">
      <span class="hm-tab active" data-s="all">Semua</span>
      <span class="hm-tab" data-s="ok">Sukses</span>
      <span class="hm-tab" data-s="failed">Gagal</span>
    </div>
    <input type="text" class="hm-search" id="hmLogSearch" placeholder="Cari perusahaan / app / IP / domain / alasan…">
  </div>
  <div id="hmRecentWrap"></div>
  <div class="hm-pager" id="hmRecentPager"></div>
</div>

@include('partials.diagnose-modal')

<script>
  const HM_DATA_URL = @json(route('heartbeat.monitor.data'));
  const HM_LOGS_URL = @json(route('heartbeat.monitor.logs'));
  let hm = @json($report);
  const state = { filter: 'all', search: '', instPage: 1, perPage: 12 };
  const logs = { page: 1, status: 'all', search: '', lastPage: 1, total: 0 };

  const esc = s => (s===null||s===undefined)?'':String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  function rel(iso){ if(!iso) return '—'; const d=new Date(iso),n=new Date(); let s=Math.round((n-d)/1000); if(s<0)s=0;
    if(s<60)return s+' dtk lalu'; if(s<3600)return Math.floor(s/60)+' mnt lalu'; if(s<86400)return Math.floor(s/3600)+' jam lalu'; return Math.floor(s/86400)+' hari lalu'; }
  function fmt(iso){ if(!iso) return '—'; try{return new Date(iso).toLocaleString('id-ID');}catch(e){return iso;} }
  const healthBadge = h => { const m={online:['b-online','ONLINE'],late:['b-late','TELAT'],expired:['b-expired','EXPIRED'],never:['b-never','BELUM']};
    const [c,l]=m[h]||['b-never',(h||'?').toUpperCase()]; return `<span class="badge ${c}">${l}</span>`; };

  function renderCards() {
    const c = hm.counts || {};
    const cards = [
      { cls:'info', lbl:'Total Aktif', val:c.total||0, sub:'instalasi', f:'all' },
      { cls:'ok',   lbl:'Online', val:c.online||0, sub:'heartbeat baru', f:'online' },
      { cls:'warn', lbl:'Telat', val:c.late||0, sub:'masih berlisensi', f:'late' },
      { cls:'bad',  lbl:'Expired', val:c.expired||0, sub:'token habis → aktivasi', f:'expired' },
      { cls:'ok',   lbl:'Heartbeat 24j OK', val:hm.heartbeats_24h_ok||0, sub:'sukses' },
      { cls:(hm.heartbeats_24h_bad>0?'bad':'info'), lbl:'Heartbeat 24j Gagal', val:hm.heartbeats_24h_bad||0, sub:'gagal/ditolak' },
      { cls:(hm.unreviewed_suspicious>0?'warn':'info'), lbl:'Suspicious', val:hm.unreviewed_suspicious||0, sub:'belum direview' },
    ];
    document.getElementById('hmCards').innerHTML = cards.map(x => `
      <div class="hm-card ${x.cls}" ${x.f?`onclick="hmSetFilter('${x.f}')"`:''} title="${x.f?'Filter: '+x.f:''}">
        <div class="lbl">${esc(x.lbl)}</div><div class="val">${esc(x.val)}</div><div class="sub">${esc(x.sub)}</div>
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
      <td style="white-space:nowrap;">
        <button class="hm-diag" onclick="openDiagnose('${esc(i.hash)}')">🩺 Diagnose</button>
        ${i.client_url ? `<a class="hm-client" href="${esc(i.client_url)}" target="_blank" rel="noopener" title="Buka /license/diagnostics di klien">↗ Klien</a>` : ''}
      </td>
    </tr>`;
  }

  function filteredInst() {
    const q = state.search.trim().toLowerCase();
    return (hm.installations||[]).filter(i => {
      if (state.filter !== 'all' && i.health !== state.filter) return false;
      if (!q) return true;
      return [i.company, i.domain, i.ip_address, i.app_code, i.app_version].some(v => (v||'').toString().toLowerCase().includes(q));
    });
  }

  function renderPager(elId, total, page, per, onPage) {
    const pages = Math.max(1, Math.ceil(total / per));
    if (page > pages) page = pages;
    const el = document.getElementById(elId);
    if (total <= per) { el.innerHTML = total ? `<span>${total} baris</span>` : ''; return page; }
    el.innerHTML = `<span>${total} baris · hal ${page}/${pages}</span>
      <button ${page<=1?'disabled':''} onclick="${onPage}(${page-1})">‹</button>
      <button ${page>=pages?'disabled':''} onclick="${onPage}(${page+1})">›</button>`;
    return page;
  }

  function renderInst() {
    const rows = filteredInst();
    state.instPage = renderPager('hmInstPager', rows.length, state.instPage, state.perPage, 'hmGotoInst');
    const start = (state.instPage - 1) * state.perPage;
    const slice = rows.slice(start, start + state.perPage);
    const el = document.getElementById('hmInstWrap');
    if (!slice.length) { el.innerHTML = `<div class="hm-empty">Tidak ada instalasi yang cocok.</div>`; return; }
    el.innerHTML = `<table class="hm"><thead><tr>
      <th>Perusahaan</th><th>App</th><th>Domain / IP</th><th>Versi</th><th>Heartbeat Terakhir</th><th>Status</th><th>Berikutnya</th><th></th>
    </tr></thead><tbody>${slice.map(instRow).join('')}</tbody></table>`;
  }

  function renderAttention() {
    const rows = hm.attention || [];
    const el = document.getElementById('hmAttentionWrap');
    if (!rows.length) { el.innerHTML = `<div class="hm-empty">Tidak ada klien yang telat. Semua sehat. 🎉</div>`; return; }
    el.innerHTML = `<table class="hm"><thead><tr>
      <th>Perusahaan</th><th>App</th><th>Domain / IP</th><th>Status</th><th>Silent</th><th></th>
    </tr></thead><tbody>${rows.map(i => `<tr>
      <td>${esc(i.company)}</td>
      <td><span class="badge b-never">${esc(i.app_code)}</span></td>
      <td class="mono">${[i.domain,i.ip_address].filter(Boolean).map(esc).join('<br>')||'—'}</td>
      <td>${healthBadge(i.health)}</td>
      <td>${i.days_since!=null?esc(i.days_since)+' hari':'—'}</td>
      <td style="white-space:nowrap;">
        <button class="hm-diag" onclick="openDiagnose('${esc(i.hash)}')">🩺 Diagnose</button>
        ${i.client_url ? `<a class="hm-client" href="${esc(i.client_url)}" target="_blank" rel="noopener" title="Buka /license/diagnostics di klien">↗ Klien</a>` : ''}
      </td>
    </tr>`).join('')}</tbody></table>`;
  }

  async function loadLogs() {
    const params = new URLSearchParams({ page: logs.page, status: logs.status, search: logs.search, per_page: 20 });
    const el = document.getElementById('hmRecentWrap');
    try {
      const r = await fetch(`${HM_LOGS_URL}?${params}`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin' });
      const j = await r.json();
      const rows = j.data || [];
      const pg = j.pagination || {};
      logs.lastPage = pg.last_page || 1;
      logs.total = pg.total || 0;
      logs.page = pg.current_page || 1;

      if (!rows.length) { el.innerHTML = `<div class="hm-empty">Tidak ada log yang cocok.</div>`; }
      else {
        el.innerHTML = `<table class="hm"><thead><tr>
          <th>Waktu</th><th>Perusahaan</th><th>App</th><th>IP</th><th>Status</th><th>Keterangan</th>
        </tr></thead><tbody>${rows.map(h => `<tr>
          <td>${rel(h.at)}<div class="mono">${fmt(h.at)}</div></td>
          <td>${esc(h.company)}</td><td class="mono">${esc(h.app_code)}</td><td class="mono">${esc(h.ip_address||'—')}</td>
          <td><span class="badge ${h.ok?'b-ok':'b-bad'}">${esc((h.status||'?').toUpperCase())}</span></td>
          <td>${esc(h.reason||(h.ok?'—':''))}</td>
        </tr>`).join('')}</tbody></table>`;
      }

      const pager = document.getElementById('hmRecentPager');
      if (logs.total === 0) { pager.innerHTML = ''; }
      else {
        pager.innerHTML = `<span>${pg.from||0}–${pg.to||0} dari ${logs.total} · hal ${logs.page}/${logs.lastPage}</span>
          <button ${logs.page<=1?'disabled':''} onclick="hmLogGoto(${logs.page-1})">‹</button>
          <button ${logs.page>=logs.lastPage?'disabled':''} onclick="hmLogGoto(${logs.page+1})">›</button>`;
      }
    } catch (e) { el.innerHTML = `<div class="hm-empty">Gagal memuat log.</div>`; }
  }

  window.hmLogGoto = p => { if (p < 1 || p > logs.lastPage) return; logs.page = p; loadLogs(); };
  window.hmLogFilter = s => { logs.status = s; logs.page = 1;
    document.querySelectorAll('#hmLogTabs .hm-tab').forEach(t => t.classList.toggle('active', t.dataset.s === s));
    loadLogs(); };

  window.hmGotoInst = p => { state.instPage = p; renderInst(); };
  window.hmSetFilter = f => {
    state.filter = f; state.instPage = 1;
    document.querySelectorAll('#hmTabs .hm-tab').forEach(t => t.classList.toggle('active', t.dataset.f === f));
    renderInst();
  };

  function render() {
    renderCards(); renderAttention(); renderInst();
    document.getElementById('hmUpdated').textContent = 'Diperbarui: ' + fmt(hm.now);
  }

  async function refresh() {
    try {
      const r = await fetch(HM_DATA_URL, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin' });
      const j = await r.json();
      if (j.success) { hm = j.data; render(); }
    } catch (e) {}
    loadLogs();
  }

  document.getElementById('hmRefresh').addEventListener('click', refresh);
  document.getElementById('hmSearch').addEventListener('input', e => { state.search = e.target.value; state.instPage = 1; renderInst(); });
  document.querySelectorAll('#hmTabs .hm-tab').forEach(t => t.addEventListener('click', () => hmSetFilter(t.dataset.f)));
  document.querySelectorAll('#hmLogTabs .hm-tab').forEach(t => t.addEventListener('click', () => hmLogFilter(t.dataset.s)));
  let logSearchTimer = null;
  document.getElementById('hmLogSearch').addEventListener('input', e => {
    clearTimeout(logSearchTimer);
    logSearchTimer = setTimeout(() => { logs.search = e.target.value; logs.page = 1; loadLogs(); }, 350);
  });
  render();
  loadLogs();
  setInterval(refresh, 20000);
</script>
@endsection
