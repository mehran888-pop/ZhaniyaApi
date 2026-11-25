#!/usr/bin/env node
/**
 * zhaniya-ui — رابط گرافیکی وب برای API زمانیا
 * بدون هیچ وابستگی خارجی؛ فقط Node.js
 *
 * اجرا:  node zhaniya-cli.js ui [--port 8080]
 * یا:    node zhaniya-ui.js [--port 8080]
 */

'use strict';

const http = require('http');

function buildEndpoints() {
  // استفاده از منطق مشترک در zhaniya-cli.js
  return require('./zhaniya-cli.js').buildEndpoints();
}

function start(args) {
  let port = parseInt(process.env.PORT || '8080', 10);
  const pi = (args || []).indexOf('--port');
  if (pi >= 0 && args[pi + 1]) port = parseInt(args[pi + 1], 10);

  const endpoints = buildEndpoints();

  const server = http.createServer((req, res) => {
    const url = (req.url || '/').split('?')[0];
    if (url === '/api/endpoints') {
      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      res.end(JSON.stringify({ endpoints }));
      return;
    }
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(HTML);
  });

  server.listen(port, '0.0.0.0', () => {
    console.log(`\n  🌐 رابط گرافیکی زمانیا آماده است:`);
    console.log(`     http://localhost:${port}\n`);
    console.log(`  برای توقف: Ctrl+C\n`);
  });
  server.on('error', (e) => {
    if (e.code === 'EADDRINUSE') {
      console.error(`پورت ${port} اشغال است. با --port پورت دیگری بدهید.`);
      process.exit(1);
    }
    throw e;
  });
  return server;
}

if (require.main === module) start(process.argv.slice(2));

// ───────────────────────── HTML تک‌پارچه ─────────────────────────

const HTML = `<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>راهنمای API زمانیا</title>
<style>
  :root {
    --bg: #0f1420; --panel: #171d2e; --panel2: #1e2740; --line: #2a3554;
    --txt: #e8edf9; --muted: #8fa0c5; --accent: #4f8cff; --accent2: #7c5cff;
    --green: #2ecc8f; --yellow: #f5b942; --red: #ff6b6b; --cyan: #38c8e0;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body {
    font-family: Vazirmatn, Tahoma, "Segoe UI", system-ui, sans-serif;
    background: var(--bg); color: var(--txt); min-height: 100vh;
  }
  header {
    background: linear-gradient(135deg, #141b30, #1a2342);
    border-bottom: 1px solid var(--line);
    padding: 14px 22px; display: flex; align-items: center; gap: 16px;
    position: sticky; top: 0; z-index: 10;
  }
  header h1 { font-size: 19px; font-weight: 800; }
  header h1 span { color: var(--accent); }
  .search {
    flex: 1; max-width: 480px; background: var(--panel2);
    border: 1px solid var(--line); border-radius: 10px;
    padding: 9px 14px; color: var(--txt); font-size: 14px; outline: none;
  }
  .search:focus { border-color: var(--accent); }
  .btn {
    background: var(--accent); color: #fff; border: 0; border-radius: 9px;
    padding: 9px 16px; font-size: 13.5px; cursor: pointer; font-family: inherit;
    font-weight: 700;
  }
  .btn:hover { filter: brightness(1.1); }
  .btn.ghost { background: var(--panel2); border: 1px solid var(--line); color: var(--txt); }
  .layout { display: flex; min-height: calc(100vh - 60px); }
  aside {
    width: 250px; background: var(--panel); border-left: 1px solid var(--line);
    padding: 14px 10px; overflow-y: auto; flex-shrink: 0;
  }
  aside h2 { font-size: 12px; color: var(--muted); margin: 6px 8px 10px; letter-spacing: .5px; }
  .ctl {
    padding: 9px 12px; border-radius: 9px; cursor: pointer; font-size: 13.5px;
    display: flex; justify-content: space-between; align-items: center;
    color: var(--muted); margin-bottom: 2px;
  }
  .ctl:hover { background: var(--panel2); color: var(--txt); }
  .ctl.active { background: rgba(79,140,255,.16); color: var(--accent); font-weight: 700; }
  .ctl .n { background: var(--panel2); border-radius: 20px; padding: 1px 8px; font-size: 11px; }
  .ctl.active .n { background: rgba(79,140,255,.25); }
  main { flex: 1; padding: 18px 22px; overflow-x: hidden; }
  .stats { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
  .stat {
    background: var(--panel); border: 1px solid var(--line); border-radius: 11px;
    padding: 10px 16px; font-size: 13px; color: var(--muted);
  }
  .stat b { color: var(--txt); font-size: 17px; margin-left: 6px; }
  .cards { display: grid; grid-template-columns: repeat(auto-fill, minmax(330px, 1fr)); gap: 12px; }
  .card {
    background: var(--panel); border: 1px solid var(--line); border-radius: 13px;
    padding: 14px 16px; cursor: pointer; transition: .15s;
    display: flex; flex-direction: column; gap: 8px;
  }
  .card:hover { border-color: var(--accent); transform: translateY(-2px); }
  .card .top { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  .m {
    font-size: 11px; font-weight: 800; padding: 2px 9px; border-radius: 6px;
    letter-spacing: .5px; color: #06131a;
  }
  .m.GET { background: var(--green); } .m.POST { background: var(--yellow); }
  .m.PUT { background: var(--cyan); } .m.DELETE { background: var(--red); color: #fff; }
  .m.? { background: var(--muted); }
  .card .name { font-size: 14.5px; font-weight: 700; }
  .card .route {
    font-family: Consolas, monospace; font-size: 12.5px; color: var(--cyan);
    direction: ltr; text-align: left; background: var(--panel2);
    padding: 6px 10px; border-radius: 7px; overflow: hidden; text-overflow: ellipsis;
  }
  .tags { display: flex; gap: 6px; flex-wrap: wrap; }
  .tag { font-size: 10.5px; padding: 2px 8px; border-radius: 20px; border: 1px solid var(--line); color: var(--muted); }
  .tag.attr { color: var(--green); border-color: rgba(46,204,143,.4); }
  .tag.conv { color: var(--yellow); border-color: rgba(245,185,66,.4); }
  .tag.auth { color: var(--accent2); border-color: rgba(124,92,255,.5); }
  .tag.anon { color: var(--cyan); border-color: rgba(56,200,224,.4); }
  .empty { color: var(--muted); text-align: center; padding: 60px 0; font-size: 15px; }

  /* مودال جزئیات */
  .overlay {
    position: fixed; inset: 0; background: rgba(5,8,16,.72); z-index: 50;
    display: none; align-items: center; justify-content: center; padding: 20px;
  }
  .overlay.show { display: flex; }
  .modal {
    background: var(--panel); border: 1px solid var(--line); border-radius: 16px;
    width: 100%; max-width: 720px; max-height: 88vh; overflow-y: auto; padding: 24px 26px;
  }
  .modal h3 { font-size: 18px; margin-bottom: 4px; }
  .modal .sub { color: var(--muted); font-size: 13px; margin-bottom: 16px; }
  .row { display: flex; gap: 10px; margin-bottom: 10px; font-size: 14px; align-items: baseline; }
  .row .lbl { color: var(--muted); min-width: 90px; font-size: 13px; }
  .row .val { flex: 1; }
  .code {
    background: #0b0f1a; border: 1px solid var(--line); border-radius: 10px;
    padding: 13px 15px; font-family: Consolas, monospace; font-size: 12.5px;
    direction: ltr; text-align: left; color: #9fd0ff; white-space: pre-wrap;
    word-break: break-all; position: relative; margin-top: 6px;
  }
  .copy {
    position: absolute; top: 8px; left: 8px; background: var(--panel2);
    border: 1px solid var(--line); color: var(--txt); border-radius: 7px;
    padding: 4px 10px; font-size: 11px; cursor: pointer; font-family: inherit;
  }
  table.params { width: 100%; border-collapse: collapse; font-size: 13.5px; margin-top: 4px; }
  table.params th {
    text-align: right; color: var(--muted); font-size: 12px; padding: 6px 8px;
    border-bottom: 1px solid var(--line);
  }
  table.params td { padding: 7px 8px; border-bottom: 1px dashed var(--line); }
  table.params td.ty { direction: ltr; text-align: left; color: var(--cyan); font-family: Consolas, monospace; font-size: 12.5px; }
  .tip {
    background: rgba(79,140,255,.09); border: 1px solid rgba(79,140,255,.3);
    border-radius: 10px; padding: 11px 14px; font-size: 13px; margin-top: 14px;
    color: #cfe1ff; line-height: 1.9;
  }
  .close {
    float: left; background: none; border: 0; color: var(--muted);
    font-size: 22px; cursor: pointer; line-height: 1;
  }
  footer { text-align: center; color: var(--muted); font-size: 12px; padding: 20px; }
  @media (max-width: 800px) { aside { display: none; } }
</style>
</head>
<body>
<header>
  <h1>⚡ راهنمای <span>API زمانیا</span></h1>
  <input id="q" class="search" placeholder="جستجو: نام اکشن، کنترلر، مسیر یا پارامتر…">
  <button class="btn ghost" onclick="resetFilter()">همه</button>
</header>

<div class="layout">
  <aside>
    <h2>کنترلرها</h2>
    <div id="ctlList"></div>
  </aside>
  <main>
    <div class="stats" id="stats"></div>
    <div class="cards" id="cards"></div>
    <div class="empty" id="empty" style="display:none">نتیجه‌ای پیدا نشد 🔍</div>
    <footer>برای جزئیات، روی هر کارت کلیک کنید · مسیرهای <b style="color:var(--green)">attribute</b> دقیق و <b style="color:var(--yellow)">conventional</b> بر اساس قرارداد Web API هستند</footer>
  </main>
</div>

<!-- مودال جزئیات -->
<div class="overlay" id="ov" onclick="if(event.target===this)closeModal()">
  <div class="modal" id="modal"></div>
</div>

<script>
let ALL = [];
let filterCtl = null;

const httpColor = m => ({GET:'var(--green)',POST:'var(--yellow)',PUT:'var(--cyan)',DELETE:'var(--red)'}[m] || 'var(--muted)');

function shortType(t) {
  if (!t) return '—';
  let s = t;
  s = s.replace(/^(?:System\\.)?Nullable\`1<(.+)>$/i, (m,i) => shortType(i) + '?');
  s = s.replace(/^(?:System\\.)?Threading\\.Tasks\\.Task\`1<(.+)>$/i, (m,i) => shortType(i));
  s = s.replace(/^(?:System\\.)?(?:Collections\\.Generic\\.)?(?:IEnumerable|IList|List|ICollection|IReadOnlyList)\`1<(.+)>$/i, (m,i) => shortType(i) + '[]');
  s = s.replace(/ZhaniyaApi\\./g, '');
  s = s.replace(/System\\.(?:Collections\\.Generic\\.)?/g, '');
  s = s.replace(/System\\./g, '');
  s = s.replace(/\`1</g, '<');
  return s;
}

function sampleValue(type) {
  const t = (type||'').toLowerCase();
  if (t.includes('string')) return 'متن';
  if (/(int|long|short|byte|double|decimal)/.test(t)) return 1;
  if (t.includes('bool')) return true;
  if (t.includes('guid')) return '00000000-0000-0000-0000-000000000000';
  if (t.includes('datetime')) return '2026-01-01T00:00:00';
  return {};
}

function buildCurl(e) {
  const base = 'http://localhost:PORT';
  let url = base + '/' + e.route;
  const method = (e.http[0] || 'GET').toUpperCase();
  let auth = e.authorize ? " \\\\\\n  -H 'Authorization: Bearer <JWT>'" : '';
  let body = '';
  if (['POST','PUT','PATCH'].includes(method)) {
    const s = {};
    e.params.forEach(p => s[p.name] = sampleValue(p.type));
    if (Object.keys(s).length)
      body = " \\\\\\n  -H 'Content-Type: application/json' \\\\\\n  -d '" + JSON.stringify(s) + "'";
  } else if (e.params.length) {
    url += '?' + e.params.map(p => p.name + '=' + encodeURIComponent(String(sampleValue(p.type)))).join('&');
  }
  return 'curl -X ' + method + " '" + url + "'" + auth + body;
}

function init(eps) {
  ALL = eps;
  renderStats(); renderCtl(); renderCards();
  document.getElementById('q').addEventListener('input', renderCards);
}

function resetFilter() {
  filterCtl = null;
  document.getElementById('q').value = '';
  renderCtl(); renderCards();
}

function renderStats() {
  const attr = ALL.filter(e => e.routeKind === 'attribute').length;
  const auth = ALL.filter(e => e.authorize).length;
  document.getElementById('stats').innerHTML =
    '<div class="stat"><b>' + ALL.length + '</b> endpoint</div>' +
    '<div class="stat"><b>' + new Set(ALL.map(e=>e.controller)).size + '</b> کنترلر</div>' +
    '<div class="stat"><b style="color:var(--green)">' + attr + '</b> مسیر attribute دقیق</div>' +
    '<div class="stat"><b style="color:var(--accent2)">' + auth + '</b> نیازمند JWT</div>';
}

function renderCtl() {
  const counts = {};
  ALL.forEach(e => counts[e.controller] = (counts[e.controller]||0)+1);
  let h = '<div class="ctl ' + (!filterCtl?'active':'') + '" onclick="filterCtl=null;renderCtl();renderCards()">' +
          '<span>همه</span><span class="n">' + ALL.length + '</span></div>';
  Object.keys(counts).forEach(c => {
    h += '<div class="ctl ' + (filterCtl===c?'active':'') + '" onclick="filterCtl=\\'' + c + '\\';renderCtl();renderCards()">' +
         '<span>' + c.replace('Controller','') + '</span><span class="n">' + counts[c] + '</span></div>';
  });
  document.getElementById('ctlList').innerHTML = h;
}

function renderCards() {
  const q = (document.getElementById('q').value||'').toLowerCase().trim();
  let list = ALL;
  if (filterCtl) list = list.filter(e => e.controller === filterCtl);
  if (q) list = list.filter(e =>
    e.name.toLowerCase().includes(q) || e.controller.toLowerCase().includes(q) ||
    e.route.toLowerCase().includes(q) ||
    e.params.some(p => p.name.toLowerCase().includes(q) || (p.type||'').toLowerCase().includes(q))
  );
  const box = document.getElementById('cards');
  document.getElementById('empty').style.display = list.length ? 'none' : 'block';
  box.innerHTML = list.map((e, i) => {
    const idx = ALL.indexOf(e);
    const ms = (e.http.length ? e.http : ['?']).map(m =>
      '<span class="m ' + m + '">' + m + '</span>').join('');
    let tags = '';
    tags += e.routeKind === 'attribute'
      ? '<span class="tag attr">attribute ✓</span>'
      : '<span class="tag conv">conventional *</span>';
    if (e.authorize) tags += '<span class="tag auth">JWT</span>';
    if (e.allowAnonymous) tags += '<span class="tag anon">Anonymous</span>';
    return '<div class="card" onclick="show(' + idx + ')">' +
      '<div class="top">' + ms + '<span class="name">' + e.controller.replace('Controller','') + ' → ' + e.name + '</span></div>' +
      '<div class="route">' + e.route + '</div>' +
      '<div class="tags">' + tags + '</div>' +
    '</div>';
  }).join('');
}

function show(i) {
  const e = ALL[i];
  const ms = (e.http.length ? e.http : ['(از روی نام)']).map(m =>
    '<span class="m ' + m + '">' + m + '</span>').join(' ');
  let params = '<div style="color:var(--muted);font-size:13.5px">ندارد</div>';
  if (e.params.length) {
    params = '<table class="params"><tr><th>نام</th><th>نوع</th></tr>' +
      e.params.map(p => '<tr><td>' + p.name + '</td><td class="ty">' + shortType(p.type) + '</td></tr>').join('') +
      '</table>';
  }
  let tips = '';
  if (e.routeKind === 'conventional')
    tips += '• مسیر از نوع <b>conventional</b> است (قالب واقعی در باینری رمزنگاری شده و الگو از قرارداد Web API پیروی می‌کند).<br>';
  if (e.authorize) {
    tips += '• نیازمند <b>توکن JWT</b> است — ابتدا از مسیر Login توکن بگیرید و هدر Authorization: Bearer بفرستید.<br>';
    const login = ALL.find(x => x.name === 'Login');
    if (login) tips += '• مسیر ورود: <b style="direction:ltr;display:inline-block">POST ' + login.route + '</b><br>';
  }
  if (e.allowAnonymous) tips += '• بدون احراز هویت قابل فراخوانی است.<br>';
  if (e.params.length) tips += '• پارامترهای POST/PUT در body با فرمت JSON و پارامترهای GET در query string ارسال شوند.<br>';
  if (e.httpInferred) tips += '• متد HTTP از روی نام حدس زده شده (در DLL attribute ندارد).<br>';

  document.getElementById('modal').innerHTML =
    '<button class="close" onclick="closeModal()">×</button>' +
    '<h3>' + e.controller + ' → ' + e.name + '</h3>' +
    '<div class="sub">' + (e.routeKind==='attribute' ? 'مسیر attribute (دقیق)' : 'مسیر قراردادی') + '</div>' +
    '<div class="row"><span class="lbl">مسیر</span><span class="val" style="direction:ltr;text-align:left;font-family:Consolas,monospace;color:var(--cyan)">' + e.route + '</span></div>' +
    '<div class="row"><span class="lbl">متدها</span><span class="val">' + ms + '</span></div>' +
    '<div class="row"><span class="lbl">احراز هویت</span><span class="val">' +
      (e.authorize ? '<b style="color:var(--accent2)">نیازمند JWT</b>' : e.allowAnonymous ? '<b style="color:var(--cyan)">Anonymous</b>' : '—') + '</span></div>' +
    '<div class="row"><span class="lbl">خروجی</span><span class="val" style="direction:ltr;text-align:left;font-family:Consolas,monospace;font-size:13px;color:var(--green)">' + shortType(e.returns) + '</span></div>' +
    '<div class="row"><span class="lbl">پارامترها</span><span class="val"></span></div>' + params +
    '<div style="margin-top:16px;font-size:13.5px;font-weight:700;color:var(--muted)">نمونه درخواست</div>' +
    '<div class="code"><button class="copy" onclick="copyCurl(this)">کپی</button><span id="curlTxt"></span></div>' +
    (tips ? '<div class="tip">💡 <b>راهنما:</b><br>' + tips + '</div>' : '');
  document.getElementById('curlTxt').textContent = buildCurl(e);
  document.getElementById('ov').classList.add('show');
}

function copyCurl(btn) {
  const txt = document.getElementById('curlTxt').textContent;
  navigator.clipboard.writeText(txt).then(() => {
    btn.textContent = 'شد ✓';
    setTimeout(() => btn.textContent = 'کپی', 1500);
  });
}

function closeModal() { document.getElementById('ov').classList.remove('show'); }
document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

fetch('/api/endpoints').then(r => r.json()).then(d => init(d.endpoints));
</script>
</body>
</html>`;

module.exports = { start };
