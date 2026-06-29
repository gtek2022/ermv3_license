{{-- Shared License Diagnose modal. Include once per page, then call
     window.openDiagnose('<installation-hash>') from any button. --}}
<div id="dgmOverlay" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.55);z-index:1000;align-items:flex-start;justify-content:center;padding:4vh 1rem;overflow-y:auto;">
  <div style="background:#fff;border-radius:14px;max-width:680px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3);">
    <div style="display:flex;justify-content:space-between;align-items:center;padding:1rem 1.25rem;border-bottom:1px solid #e2e8f0;">
      <strong style="font-size:1rem;color:#1a3a6b;">🩺 Diagnosa Lisensi</strong>
      <button onclick="window.dgmClose()" style="border:none;background:#f1f5f9;border-radius:8px;width:30px;height:30px;cursor:pointer;font-size:1rem;color:#475569;">×</button>
    </div>
    <div id="dgmBody" style="padding:1.25rem;">
      <div id="dgmLoading" style="text-align:center;color:#94a3b8;padding:1.5rem;font-size:.85rem;">Menganalisa…</div>
      <div id="dgmContent" style="display:none;"></div>
    </div>
  </div>
</div>

<style>
  .dgm-vd { border-radius:10px;padding:1rem 1.1rem;margin-bottom:1rem; }
  .dgm-vd.success { background:#f0fdf4;border:2px solid #16a34a; }
  .dgm-vd.warning { background:#fffbeb;border:2px solid #d97706; }
  .dgm-vd.danger  { background:#fef2f2;border:2px solid #dc2626; }
  .dgm-vd .t { font-size:1rem;font-weight:800;color:#1e293b;margin-bottom:.25rem; }
  .dgm-vd .m { font-size:.85rem;color:#334155; }
  .dgm-vd .h { font-size:.78rem;margin-top:.5rem;padding:.5rem .7rem;background:rgba(255,255,255,.65);border-radius:6px;color:#475569; }
  .dgm-meta { font-size:.75rem;color:#64748b;margin-bottom:1rem;display:flex;flex-wrap:wrap;gap:.35rem 1rem; }
  .dgm-chk { display:flex;gap:.6rem;padding:.55rem 0;border-bottom:1px dashed #eef2f7; }
  .dgm-chk:last-child { border-bottom:none; }
  .dgm-ic { width:20px;height:20px;border-radius:50%;flex-shrink:0;color:#fff;font-weight:800;font-size:.72rem;display:flex;align-items:center;justify-content:center; }
  .dgm-ic.pass{background:#16a34a;} .dgm-ic.warn{background:#d97706;} .dgm-ic.fail{background:#dc2626;}
  .dgm-chk .l { font-weight:700;font-size:.82rem;color:#1a3a6b; }
  .dgm-chk .mm { font-size:.78rem;color:#475569;margin-top:.1rem; }
  .dgm-chk .hh { font-size:.73rem;color:#92400e;margin-top:.3rem; }
</style>

<script>
(function () {
  const DIAG_BASE = @json(url('/heartbeat-monitor/diagnose'));
  const esc = s => (s===null||s===undefined)?'':String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');

  window.dgmClose = function () { document.getElementById('dgmOverlay').style.display = 'none'; };

  window.openDiagnose = async function (hash) {
    const ov = document.getElementById('dgmOverlay');
    const loading = document.getElementById('dgmLoading');
    const content = document.getElementById('dgmContent');
    ov.style.display = 'flex';
    loading.style.display = 'block';
    content.style.display = 'none';
    content.innerHTML = '';
    try {
      const r = await fetch(`${DIAG_BASE}/${hash}`, { headers:{'Accept':'application/json','X-Requested-With':'XMLHttpRequest'}, credentials:'same-origin' });
      const j = await r.json();
      if (!j.success) throw new Error(j.message || 'Gagal memuat diagnosa');
      content.innerHTML = renderDiag(j.data);
    } catch (e) {
      content.innerHTML = `<div class="dgm-vd danger"><div class="t">Gagal memuat</div><div class="m">${esc(e.message)}</div></div>`;
    }
    loading.style.display = 'none';
    content.style.display = 'block';
  };

  function renderDiag(d) {
    const v = d.verdict || {};
    const inst = d.installation || {};
    const icon = { pass:'✓', warn:'!', fail:'✕' };
    const meta = [
      ['Perusahaan', inst.company], ['App', inst.app_code],
      ['Domain', inst.domain], ['IP', inst.ip_address],
      ['Versi', inst.app_version], ['Heartbeat terakhir', inst.last_heartbeat ? new Date(inst.last_heartbeat).toLocaleString('id-ID') : '—'],
    ].filter(x => x[1]).map(x => `<span><strong>${esc(x[0])}:</strong> ${esc(x[1])}</span>`).join('');
    const checks = (d.checks||[]).map(c => `
      <div class="dgm-chk">
        <div class="dgm-ic ${c.status}">${icon[c.status]||'?'}</div>
        <div>
          <div class="l">${esc(c.label)}</div>
          <div class="mm">${esc(c.message)}</div>
          ${c.hint ? `<div class="hh">💡 ${esc(c.hint)}</div>` : ''}
        </div>
      </div>`).join('');
    return `
      <div class="dgm-vd ${esc(v.level||'warning')}">
        <div class="t">${esc(v.title||'Hasil diagnosa')}</div>
        <div class="m">${esc(v.message||'')}</div>
        ${v.hint ? `<div class="h">💡 ${esc(v.hint)}</div>` : ''}
      </div>
      <div class="dgm-meta">${meta}</div>
      <div>${checks}</div>`;
  }

  document.getElementById('dgmOverlay').addEventListener('click', function (e) {
    if (e.target === this) window.dgmClose();
  });
})();
</script>
