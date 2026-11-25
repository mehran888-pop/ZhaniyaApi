#!/usr/bin/env node
/**
 * zhaniya-cli — راهنمای تعاملی API زمانیا (ZhaniyaApi)
 *
 * فهرست تمام endpoint ها، متدهای HTTP، پارامترها، مسیرها و راهنمای قدم‌به‌قدم استفاده.
 *
 * استفاده:
 *   node zhaniya-cli.js list                 # فهرست همه endpoint ها
 *   node zhaniya-cli.js routes               # گروه‌بندی بر اساس مسیر
 *   node zhaniya-cli.js show <نام>           # جزئیات یک endpoint
 *   node zhaniya-cli.js search <عبارت>       # جستجو
 *   node zhaniya-cli.js guide                # راهنمای تعاملی قدم‌به‌قدم
 *   node zhaniya-cli.js curl <نام> [گزینه‌ها] # ساخت نمونه درخواست curl
 *   node zhaniya-cli.js help                 # راهنما
 */

'use strict';

const fs = require('fs');
const path = require('path');
const readline = require('readline');

// ─────────────────────────── داده‌ها ───────────────────────────

const CATALOG_PATH = path.join(__dirname, 'tools', 'api_catalog.json');

function loadCatalog() {
  if (!fs.existsSync(CATALOG_PATH)) {
    console.error('خطا: فایل tools/api_catalog.json پیدا نشد.');
    process.exit(1);
  }
  return JSON.parse(fs.readFileSync(CATALOG_PATH, 'utf8'));
}

const catalog = loadCatalog();

// ─────────────────────────── مدل‌سازی ───────────────────────────

const HTTP_INFER = [
  [/^(get|find|search|query|check|is|has)/i, 'GET'],
  [/^(post|insert|create|add|login|logon|save|write|send|print|checkout|order)/i, 'POST'],
  [/^(put|update|change|edit)/i, 'PUT'],
  [/^(delete|remove)/i, 'DELETE'],
];

function inferHttp(name) {
  for (const [re, m] of HTTP_INFER) if (re.test(name)) return m;
  return null;
}

/** ساخت نمایش مسیر برای هر action */
function buildEndpoints() {
  const out = [];
  for (const ctl of catalog.controllers) {
    const ctrlShort = ctl.name.replace(/Controller$/, '');
    const prefix = ctl.routePrefix || null;
    for (const a of ctl.actions) {
      let route;
      let routeKind; // 'attribute' | 'conventional'

      if (a.template && a.template !== a.name) {
        // RouteAttribute با متن صریح
        route = prefix ? `${prefix}/${a.template}` : a.template;
        routeKind = 'attribute';
      } else if (prefix && /RouteAttribute/.test(a.attrs.join(' '))) {
        // [Route] بدون متن + RoutePrefix روی کلاس → مسیر = prefix/action
        route = `${prefix}/${a.name}`;
        routeKind = 'attribute';
      } else if (a.template === a.name && /RouteAttribute/.test(a.attrs.join(' '))) {
        route = prefix ? `${prefix}/${a.name}` : a.name;
        routeKind = 'attribute';
      } else {
        // مسیر قراردادی (کلاسیک Web API) — در باینری رمزنگاری شده و قابل استخراج مستقیم نبود
        route = `api/${ctrlShort}/${a.name}`;
        routeKind = 'conventional';
      }

      let http = a.http && a.http.length ? a.http.slice() : [];
      let httpInferred = false;
      if (!http.length) {
        const inf = inferHttp(a.name);
        if (inf) { http = [inf]; httpInferred = true; }
      }

      out.push({
        controller: ctl.name,
        ctrlShort,
        name: a.name,
        route,
        routeKind,
        http,
        httpInferred,
        params: a.params || [],
        returns: a.returns || null,
        authorize: !!a.authorize,
        allowAnonymous: !!a.allowAnonymous,
        attrs: a.attrs || [],
        routePrefix: prefix,
        classAttrs: ctl.classAttrs || [],
      });
    }
  }
  return out;
}

const endpoints = buildEndpoints();

function findEndpoint(q) {
  const s = q.toLowerCase().replace(/^\/+|\/+$/g, '');
  // تطابق دقیق یا پسوند
  let hit = endpoints.find(e =>
    `${e.controller}/${e.name}`.toLowerCase() === s ||
    e.route.toLowerCase() === s ||
    `${e.ctrlShort}/${e.name}`.toLowerCase() === s
  );
  if (hit) return hit;
  // تطابق جزئی
  const cands = endpoints.filter(e =>
    e.name.toLowerCase().includes(s) ||
    e.route.toLowerCase().includes(s) ||
    e.controller.toLowerCase().includes(s)
  );
  return cands.length === 1 ? cands[0] : null;
}

// ─────────────────────────── قالب‌بندی ───────────────────────────

const C = {
  reset: '\x1b[0m', bold: '\x1b[1m', dim: '\x1b[2m',
  red: '\x1b[31m', green: '\x1b[32m', yellow: '\x1b[33m',
  blue: '\x1b[34m', magenta: '\x1b[35m', cyan: '\x1b[36m',
};
const NO_COLOR = process.env.NO_COLOR || !process.stdout.isTTY;
function c(col, s) { return NO_COLOR ? String(s) : C[col] + s + C.reset; }

function httpColor(m) {
  switch ((m || '').toUpperCase()) {
    case 'GET': return c('green', 'GET');
    case 'POST': return c('yellow', 'POST');
    case 'PUT': return c('blue', 'PUT');
    case 'DELETE': return c('red', 'DELETE');
    default: return m;
  }
}

function padEndW(s, n) {
  // تقریب عرض برای ترمینال (فارسی/انگلیسی)
  return String(s) + ' '.repeat(Math.max(0, n - displayWidth(s)));
}
function displayWidth(s) {
  let w = 0;
  for (const ch of String(s)) {
    const code = ch.codePointAt(0);
    if (code >= 0x0600 && code <= 0x06FF) w += 1; // فارسی: 1
    else w += 1;
  }
  return w;
}

function shortType(t) {
  if (!t) return '—';
  let s = t;
  // Nullable`1<T> → T?
  s = s.replace(/^(?:System\.)?Nullable`1<(.+)>$/, (_, i) => shortType(i) + '?');
  // Task`1<T> → T
  s = s.replace(/^(?:System\.)?Threading\.Tasks\.Task`1<(.+)>$/i, (_, i) => shortType(i));
  // IEnumerable`1<T> / IList`1<T> / List`1<T> → T[]
  s = s.replace(/^(?:System\.)?(?:Collections\.Generic\.)?(?:IEnumerable|IList|List|ICollection|IReadOnlyList)`1<(.+)>$/i, (_, i) => shortType(i) + '[]');
  s = s.replace(/^ZhaniyaApi\./g, '');
  s = s.replace(/^System\.(?:Collections\.Generic\.)?/g, '');
  s = s.replace(/^System\./g, '');
  s = s.replace(/`1</g, '<');
  return s;
}

function routeBadge(e) {
  return e.routeKind === 'attribute'
    ? c('green', '[attribute]')
    : c('yellow', '[conventional]');
}

function authBadge(e) {
  if (e.allowAnonymous) return c('cyan', 'Anonymous');
  if (e.authorize) return c('magenta', 'Authorized');
  return c('dim', '—');
}

// ─────────────────────────── دستورات ───────────────────────────

function cmdList(args) {
  const filterCtrl = args.find(a => !a.startsWith('-'));
  let list = endpoints;
  if (filterCtrl) {
    list = endpoints.filter(e =>
      e.controller.toLowerCase().includes(filterCtrl.toLowerCase()) ||
      e.ctrlShort.toLowerCase() === filterCtrl.toLowerCase()
    );
    if (!list.length) {
      console.log(c('red', `کنترلری با نام «${filterCtrl}» پیدا نشد.`));
      return;
    }
  }
  console.log(c('bold', `\nفهرست endpoint ها (${list.length} مورد)\n`));
  const header =
    ' #  ' + padEndW('Controller.Action', 44) + padEndW('HTTP', 8) + padEndW('Route', 52) + 'Auth';
  console.log(c('dim', header));
  console.log(c('dim', '─'.repeat(120)));
  list.forEach((e, i) => {
    const name = `${e.controller}.${e.name}`;
    const http = e.http.length
      ? e.http.map(httpColor).join(',')
      : c('dim', '?');
    const auth = authBadge(e);
    const mark = e.httpInferred ? c('dim', '~') : ' ';
    console.log(
      `${String(i + 1).padStart(3)}${mark} ` +
      padEndW(name, 44) +
      padEndW(http, 8) +
      padEndW(e.route + (e.routeKind === 'conventional' ? c('dim', ' *') : ''), 52) +
      auth
    );
  });
  console.log(c('dim', '\n* مسیر conventional بر اساس قرارداد Web API نمایش داده شده (قالب واقعی در باینری رمزنگاری شده است).'));
  console.log(c('dim', '~ متد HTTP از روی نام استخراج شده است.'));
  console.log(c('dim', 'جزئیات: node zhaniya-cli.js show <کنترلر/اکشن>  |  راهنما: guide\n'));
}

function cmdRoutes() {
  const groups = new Map();
  for (const e of endpoints) {
    const key = e.routeKind === 'attribute'
      ? (e.routePrefix ? `/${e.routePrefix}/…` : '/(attribute routes)')
      : '/api/{controller}/…';
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(e);
  }
  console.log(c('bold', '\nگروه‌بندی مسیرها\n'));
  for (const [key, list] of groups) {
    console.log(c('cyan', bold(`${key}  (${list.length})`)));
    for (const e of list) {
      console.log(`   ${e.http.map(httpColor).join(',').padEnd(12)} ${e.route}   ${c('dim', e.controller + '.' + e.name)}`);
    }
    console.log('');
  }
}
function bold(s) { return NO_COLOR ? s : '\x1b[1m' + s + '\x1b[0m'; }

function cmdShow(args) {
  const q = args[0];
  if (!q) { console.log(c('red', 'نام endpoint را وارد کنید. مثال: show Product/GetAll')); return; }
  const e = findEndpoint(q);
  if (!e) {
    console.log(c('red', `«${q}» پیدا نشد. با search جستجو کنید.`));
    return;
  }
  printDetail(e);
}

function printDetail(e) {
  console.log('');
  console.log(c('bold', `  ${e.controller} → ${e.name}`));
  console.log(c('dim', '  ' + '─'.repeat(70)));
  console.log(`  مسیر        : ${c('cyan', e.route)}  ${routeBadge(e)}`);
  console.log(`  متدها       : ${e.http.length ? e.http.map(httpColor).join('  ') : c('dim', '(از روی نام استخراج شده)')}${e.httpInferred ? c('dim', '  ~') : ''}`);
  console.log(`  احراز هویت  : ${authBadge(e)}` + (e.authorize ? '  (نیازمند JWT)' : ''));
  if (e.params.length) {
    console.log('  پارامترها   :');
    for (const p of e.params) {
      console.log(`      • ${c('bold', p.name)}  ${c('dim', '(' + shortType(p.type) + ')')}`);
    }
  } else {
    console.log('  پارامترها   : ' + c('dim', 'ندارد'));
  }
  console.log(`  خروجی       : ${c('green', shortType(e.returns))}`);
  if (e.attrs.length) {
    console.log(`  ویژگی‌ها    : ${c('dim', e.attrs.filter(a => !/AsyncStateMachine|CompilerGenerated/.test(a)).join(', '))}`);
  }
  console.log('');
  console.log(c('bold', '  نمونه درخواست:'));
  console.log('  ' + buildCurl(e, {}, true));
  console.log('');
}

function buildCurl(e, opts, indent) {
  const base = (opts.base || 'http://localhost:PORT').replace(/\/+$/, '');
  let url = `${base}/${e.route}`;
  const method = (e.http[0] || 'GET').toUpperCase();
  let body = '';
  let auth = '';
  if (e.authorize) auth = ` \\\n  -H 'Authorization: Bearer <JWT>'`;

  if (['POST', 'PUT', 'PATCH'].includes(method)) {
    const sample = {};
    for (const p of e.params) sample[p.name] = sampleValue(p.type);
    if (Object.keys(sample).length) {
      body = ` \\\n  -H 'Content-Type: application/json' \\\n  -d '${JSON.stringify(sample)}'`;
    }
  } else if (e.params.length) {
    // GET/DELETE → query string
    const qs = e.params
      .map(p => `${p.name}=${encodeURIComponent(String(sampleValue(p.type)))}`)
      .join('&');
    url += `?${qs}`;
  }
  const pre = indent ? '  ' : '';
  return `${pre}curl -X ${method} '${url}'${auth}${body}`;
}

function sampleValue(type) {
  const t = (type || '').toLowerCase();
  if (t.includes('string')) return 'متن';
  if (t.includes('int') || t.includes('long') || t.includes('short') || t.includes('byte') || t.includes('double') || t.includes('decimal')) return 1;
  if (t.includes('bool')) return true;
  if (t.includes('guid')) return '00000000-0000-0000-0000-000000000000';
  if (t.includes('datetime')) return '2026-01-01T00:00:00';
  if (t.includes('[]') || t.includes('list') || t.includes('ienumerable')) return [];
  return {};
}

function cmdSearch(args) {
  const q = (args.join(' ') || '').toLowerCase();
  if (!q) { console.log(c('red', 'عبارت جستجو را وارد کنید.')); return; }
  const hits = endpoints.filter(e =>
    e.name.toLowerCase().includes(q) ||
    e.controller.toLowerCase().includes(q) ||
    e.route.toLowerCase().includes(q) ||
    e.params.some(p => p.name.toLowerCase().includes(q) || (p.type || '').toLowerCase().includes(q))
  );
  if (!hits.length) { console.log(c('red', 'نتیجه‌ای پیدا نشد.')); return; }
  console.log(c('bold', `\n${hits.length} نتیجه برای «${args.join(' ')}»:\n`));
  hits.forEach((e, i) => {
    console.log(
      ` ${i + 1}. ${c('bold', e.controller + '.' + e.name)}  ` +
      `${e.http.map(httpColor).join(',')}  ${c('cyan', e.route)}`
    );
  });
  console.log('');
}

function cmdCurl(args) {
  const name = args[0];
  if (!name) { console.log(c('red', 'نام endpoint را وارد کنید. مثال: curl User/Login --base http://localhost:5000')); return; }
  const e = findEndpoint(name);
  if (!e) { console.log(c('red', `«${name}» پیدا نشد.`)); return; }
  const opts = {};
  const bi = args.indexOf('--base');
  if (bi >= 0 && args[bi + 1]) opts.base = args[bi + 1];
  console.log(buildCurl(e, opts, false));
}

// ─────────────────────── راهنمای تعاملی ───────────────────────

function ask(rl, question) {
  return new Promise(res => rl.question(question, ans => res(String(ans).trim())));
}

async function cmdGuide() {
  const rl = readline.createInterface({ input: process.stdin, output: process.stdout });
  console.log(c('bold', '\n🎓 راهنمای تعاملی API زمانیا\n'));
  console.log(c('dim', 'در هر مرحله می‌توانید شماره یا بخشی از نام را بنویسید. برای خروج: q\n'));

  try {
    // ── مرحله۱: انتخاب کنترلر ──
    const ctrls = catalog.controllers;
    console.log(c('bold', 'مرحله ۱) کنترلر مورد نظر را انتخاب کنید:'));
    ctrls.forEach((ctl, i) => {
      const n = ctl.actions.length;
      console.log(`  ${c('cyan', String(i + 1).padStart(2))}. ${padEndW(ctl.name, 34)} ${c('dim', n + ' action')}`);
    });
    let ans = await ask(rl, c('bold', '\n› انتخاب: '));
    if (/^(q|quit|exit|خروج)$/i.test(ans)) { rl.close(); return; }
    let ctlIdx = parseInt(ans, 10) - 1;
    if (!(ctlIdx >= 0 && ctlIdx < ctrls.length)) {
      const f = ctrls.findIndex(x => x.name.toLowerCase().includes(ans.toLowerCase()));
      ctlIdx = f;
    }
    if (ctlIdx < 0 || !ctrls[ctlIdx]) { console.log(c('red', 'انتخاب نامعتبر.')); rl.close(); return; }
    const ctl = ctrls[ctlIdx];
    console.log(c('green', `✓ ${ctl.name}`));

    // ── مرحله۲: انتخاب action ──
    const eps = endpoints.filter(e => e.controller === ctl.name);
    console.log(c('bold', `\nمرحله ۲) action را انتخاب کنید (${eps.length} مورد):`));
    eps.forEach((e, i) => {
      console.log(
        `  ${c('cyan', String(i + 1).padStart(2))}. ${padEndW(e.name, 30)} ` +
        `${e.http.map(httpColor).join(',').padEnd(10)} ${c('dim', e.route)}`
      );
    });
    ans = await ask(rl, c('bold', '\n› انتخاب: '));
    if (/^(q|quit|exit)$/i.test(ans)) { rl.close(); return; }
    let epIdx = parseInt(ans, 10) - 1;
    if (!(epIdx >= 0 && epIdx < eps.length)) {
      epIdx = eps.findIndex(x => x.name.toLowerCase().includes(ans.toLowerCase()));
    }
    if (epIdx < 0 || !eps[epIdx]) { console.log(c('red', 'انتخاب نامعتبر.')); rl.close(); return; }
    const ep = eps[epIdx];
    console.log(c('green', `✓ ${ep.name}`));

    // ── مرحله۳: نمایش جزئیات و راهنما ──
    console.log(c('bold', '\nمرحله ۳) جزئیات و نحوه فراخوانی:\n'));
    printDetail(ep);

    console.log(c('bold', '  نکات استفاده:'));
    if (ep.routeKind === 'conventional') {
      console.log(c('yellow', '    • این مسیر از نوع conventional است (قالب واقعی در باینری رمزنگاری شده).'));
      console.log(c('yellow', '      الگوی نمایش داده‌شده از قرارداد استاندارد Web API پیروی می‌کند.'));
    }
    if (ep.authorize) {
      console.log('    • این endpoint نیازمند توکن JWT است. ابتدا از Login توکن بگیرید:');
      const login = endpoints.find(e => e.name === 'Login');
      if (login) console.log(`      ${c('cyan', `POST ${login.route}`)} ← نام کاربری/رمز`);
      console.log(c('magenta', '    • سپس هدر: Authorization: Bearer <token>'));
    }
    if (ep.allowAnonymous) {
      console.log(c('cyan', '    • بدون احراز هویت قابل فراخوانی است.'));
    }
    if (ep.params.length) {
      console.log('    • پارامترهای ورودی را در body (POST/PUT) یا query string (GET) بفرستید.');
    }
    if (ep.httpInferred) {
      console.log(c('yellow', '    • متد HTTP از روی نام حدس زده شده؛ در صورت خطا متدهای دیگر را امتحان کنید.'));
    }
    console.log(c('dim', `\n  نمونه curl:\n${buildCurl(ep, {}, true)}`));
  } catch (err) {
    console.error(err);
  } finally {
    rl.close();
  }
}

function cmdHelp() {
  console.log(c('bold', `
zhaniya-cli — راهنمای API زمانیا (ZhaniyaApi)

دستورات:
  list [کنترلر]              فهرست همه endpoint ها (با فیلتر اختیاری کنترلر)
  routes                     گروه‌بندی مسیرها
  show <نام>                 جزئیات یک endpoint (نام: Controller/Action یا بخشی از آن)
  search <عبارت>             جستجو در نام‌ها، مسیرها و پارامترها
  guide                      راهنمای تعاملی قدم‌به‌قدم (انتخاب کنترلر → action → نحوه فراخوانی)
  curl <نام> [--base URL]    ساخت نمونه درخواست curl
  help                       نمایش این راهنما

نکات:
  • مسیرهای [attribute] دقیق و از خود DLL استخراج شده‌اند.
  • مسیرهای [conventional] بر اساس قرارداد Web API نمایش داده می‌شوند؛
    قالب واقعی مسیر در باینری این پروژه رمزنگاری شده است.
  • اکثر endpoint ها برای دسترسی نیازمند توکن JWT هستند (پس از Login).

مثال:
  node zhaniya-cli.js list
  node zhaniya-cli.js show Product/GetAll
  node zhaniya-cli.js guide
  node zhaniya-cli.js curl User/Login --base http://localhost:5000
`));
}

// ─────────────────────────── اجرای اصلی ───────────────────────────

function main() {
  const [cmd, ...args] = process.argv.slice(2);
  switch ((cmd || '').toLowerCase()) {
    case 'list': case 'ls': cmdList(args); break;
    case 'routes': cmdRoutes(); break;
    case 'show': case 'info': cmdShow(args); break;
    case 'search': case 'find': cmdSearch(args); break;
    case 'guide': case 'راهنما': cmdGuide(); break;
    case 'curl': case 'call': cmdCurl(args); break;
    case 'help': case '--help': case '-h': cmdHelp(); break;
    default:
      if (cmd) console.log(c('red', `دستور ناشناخته: ${cmd}\n`));
      cmdHelp();
  }
}

main();
