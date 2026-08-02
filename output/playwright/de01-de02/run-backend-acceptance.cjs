#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');
const BASE = 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, 'backend-summary.json');
const tag = `de_${Date.now().toString(36)}`;
const password = 'DEAccept!260802';
const fixtures = { admins: [], roles: [] };
let pageRows = [], tabRows = [], styleRow = null;

const q = (v) => `'${String(v).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
function db(sql) {
  const r = spawnSync('docker', ['exec', '-i', '-e', 'MYSQL_PWD=peanut_2024', 'likeadmin-mysql', 'mysql',
    '--default-character-set=utf8mb4', '-h192.168.192.2', '-P3306', '-upeanut', '-N', '-B', 'peanut_admin'],
  { input: `${sql.trim().replace(/;?$/, ';')}\n`, encoding: 'utf8' });
  if (r.status !== 0) throw new Error((r.stderr || 'database failed').trim().slice(0, 500));
  return (r.stdout || '').trim();
}
const scalar = (sql) => db(sql).split(/\r?\n/)[0]?.split('\t')[0] || '';
const md5 = (v) => crypto.createHash('md5').update(v).digest('hex');
function assert(v, m) { if (!v) throw new Error(m); }
async function api(method, route, body = {}, token = '') {
  const url = new URL(`${BASE}${route}`); const headers = { Accept: 'application/json' }; let payload;
  if (method === 'GET') Object.entries(body).forEach(([k, v]) => url.searchParams.set(k, String(v)));
  else { headers['content-type'] = 'application/json'; payload = JSON.stringify(body); }
  if (token) headers.Authorization = `Bearer ${token}`;
  return (await fetch(url, { method, headers, body: payload })).json();
}
function ok(v, label) { assert(Number(v?.code) === 20000, `${label}: ${v?.msg || 'failed'}`); return v.data; }
function reject(v, label) { assert(Number(v?.code) !== 20000, `${label}: unexpectedly succeeded`); }
function denied(v, label) { assert(Number(v?.code) === 40300, `${label}: expected 40300`); }

function createAdmin(name, root) {
  const salt = `${tag}_${root}`;
  const id = Number(scalar(`INSERT INTO pa_admin
    (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
    VALUES (${q(name)},'DE验收',${q(md5(`${md5(password)}${salt}`))},${q(salt)},'',${root},0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()); SELECT LAST_INSERT_ID()`));
  fixtures.admins.push(id); return id;
}
async function login(name) { return ok(await api('POST', '/api/user/login', { account: name, password, terminal: 1 }), `${name} login`).token; }
function rows(sql, fields) {
  const out = db(sql); return out.split(/\r?\n/).filter(Boolean).map((line) => Object.fromEntries(fields.map((f, i) => [f, line.split('\t')[i] ?? ''])));
}
function snapshot() {
  pageRows = rows('SELECT id,type,HEX(name),HEX(data),HEX(meta),create_time,update_time FROM pa_decorate_page ORDER BY id', ['id','type','name','data','meta','create','update']);
  tabRows = rows('SELECT id,position,HEX(name),HEX(selected),HEX(unselected),HEX(link),is_show,create_time,update_time FROM pa_decorate_tabbar ORDER BY id', ['id','position','name','selected','unselected','link','show','create','update']);
  styleRow = rows("SELECT id,HEX(value),create_time,update_time FROM pa_config WHERE type='tabbar' AND name='style'", ['id','value','create','update'])[0];
}
const hex = (v) => v === '' ? "''" : `0x${v}`;
function restore() {
  const admins = fixtures.admins.length ? fixtures.admins.join(',') : '0';
  const roles = fixtures.roles.length ? fixtures.roles.join(',') : '0';
  const pageValues = pageRows.map((r) => `(${r.id},${r.type},${hex(r.name)},${hex(r.data)},${hex(r.meta)},${r.create},${r.update})`).join(',');
  const tabValues = tabRows.map((r) => `(${r.id},${r.position},${hex(r.name)},${hex(r.selected)},${hex(r.unselected)},${hex(r.link)},${r.show},${r.create},${r.update})`).join(',');
  db(`DELETE FROM pa_admin_session WHERE admin_id IN (${admins}); DELETE FROM pa_operation_log WHERE admin_id IN (${admins});
    DELETE FROM pa_admin_role WHERE admin_id IN (${admins}) OR role_id IN (${roles}); DELETE FROM pa_system_role_menu WHERE role_id IN (${roles});
    DELETE FROM pa_admin WHERE id IN (${admins}); DELETE FROM pa_system_role WHERE id IN (${roles});
    DELETE FROM pa_decorate_page; INSERT INTO pa_decorate_page (id,type,name,data,meta,create_time,update_time) VALUES ${pageValues};
    DELETE FROM pa_decorate_tabbar; INSERT INTO pa_decorate_tabbar (id,position,name,selected,unselected,link,is_show,create_time,update_time) VALUES ${tabValues};
    UPDATE pa_config SET value=${hex(styleRow.value)},create_time=${styleRow.create},update_time=${styleRow.update} WHERE id=${styleRow.id}`);
  db(`ALTER TABLE pa_decorate_page AUTO_INCREMENT=${Math.max(...pageRows.map((r) => Number(r.id))) + 1}; ALTER TABLE pa_decorate_tabbar AUTO_INCREMENT=${Math.max(...tabRows.map((r) => Number(r.id))) + 1}`);
  return Number(scalar(`SELECT (SELECT COUNT(*) FROM pa_admin WHERE id IN (${admins}))+(SELECT COUNT(*) FROM pa_system_role WHERE id IN (${roles}))`)) === 0;
}
function fingerprint() {
  return db("SELECT 'p',id,type,HEX(data),HEX(meta) FROM pa_decorate_page UNION ALL SELECT 't',id,position,HEX(link),is_show FROM pa_decorate_tabbar UNION ALL SELECT 'c',id,0,HEX(value),0 FROM pa_config WHERE type='tabbar' AND name='style' ORDER BY 1,2");
}

async function main() {
  const summary = { contract: 'DE01-DE02-backend', checks: {}, cleanup: false, ok: false };
  try {
    snapshot();
    const rootName = `${tag}_root`, userName = `${tag}_user`;
    createAdmin(rootName, 1); const userId = createAdmin(userName, 0);
    const roleId = Number(scalar(`INSERT INTO pa_system_role (name,\`desc\`,sort,create_time,update_time) VALUES (${q(`${tag}_role`)},'DE role',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()); SELECT LAST_INSERT_ID()`));
    fixtures.roles.push(roleId); db(`INSERT INTO pa_admin_role (admin_id,role_id) VALUES (${userId},${roleId})`);
    const root = await login(rootName), user = await login(userName);

    const mobile = ok(await api('GET', '/api/admin/decoration/mobile/page/lists', {}, root), 'mobile lists');
    const pc = ok(await api('GET', '/api/admin/decoration/pc/page/lists', {}, root), 'pc lists');
    assert(mobile.length === 4 && pc.length === 1, 'standard page set missing');
    const homeInfo = mobile.find((p) => Number(p.type) === 1), themeInfo = mobile.find((p) => Number(p.type) === 5);
    const home = ok(await api('GET', '/api/admin/decoration/mobile/page/detail', { id: homeInfo.id }, root), 'home detail');
    const banner = home.data.find((c) => c.name === 'banner');
    banner.content.data[0].name = '即时生效'; banner.content.data[0].image = `${BASE}/storage/de-banner.png`;
    ok(await api('POST', '/api/admin/decoration/mobile/page/save', { id: home.id, type: 1, data: home.data, meta: home.meta }, root), 'home save');
    const consumedHome = ok(await api('GET', '/api/decoration/mobile', { type: 1 }), 'home consume');
    assert(consumedHome.data.find((c) => c.name === 'banner').content.data[0].name === '即时生效', 'mobile save not consumed');
    assert(scalar(`SELECT JSON_UNQUOTE(JSON_EXTRACT(data,'$[1].content.data[0].image')) FROM pa_decorate_page WHERE id=${home.id}`) === 'storage/de-banner.png', 'image was not stored as relative URI');
    const beforeInvalid = fingerprint();
    reject(await api('POST', '/api/admin/decoration/mobile/page/save', { id: home.id, type: 1, data: home.data.filter((c) => c.name !== 'news'), meta: home.meta }, root), 'missing component');
    reject(await api('POST', '/api/admin/decoration/mobile/page/save', { id: home.id, type: 4, data: home.data, meta: home.meta }, root), 'cross-domain type');
    assert(fingerprint() === beforeInvalid, 'invalid mobile save changed online state');
    summary.checks.mobile_schema_uri_and_immediate_consumption = true;

    const theme = ok(await api('GET', '/api/admin/decoration/mobile/page/detail', { id: themeInfo.id }, root), 'theme detail');
    theme.data = { themeColorId: 7, topTextColor: 'black', navigationBarColor: '#112233', themeColor1: '#223344', themeColor2: '#334455', buttonColor: 'white' };
    ok(await api('POST', '/api/admin/decoration/mobile/page/save', { id: theme.id, type: 5, data: theme.data, meta: [] }, root), 'custom theme');
    const themeBeforeInvalid = fingerprint();
    reject(await api('POST', '/api/admin/decoration/mobile/page/save', { id: theme.id, type: 5, data: { ...theme.data, themeColorId: 3 }, meta: [] }, root), 'mutated preset theme');
    assert(fingerprint() === themeBeforeInvalid, 'invalid theme changed state');
    assert(ok(await api('GET', '/api/index/config'), 'global config').theme.data.themeColor1 === '#223344', 'theme not consumed');
    summary.checks.theme_presets_and_consumption = true;

    const pcPage = ok(await api('GET', '/api/admin/decoration/pc/page/detail', { id: pc[0].id }, root), 'pc detail');
    pcPage.data[0].content.data[0].name = 'PC即时生效';
    ok(await api('POST', '/api/admin/decoration/pc/page/save', { id: pcPage.id, type: 4, data: pcPage.data, meta: [] }, root), 'pc save');
    assert(ok(await api('GET', '/api/decoration/pc'), 'pc consume').data[0].content.data[0].name === 'PC即时生效', 'PC save not consumed');
    summary.checks.pc_schema_and_immediate_consumption = true;

    const tabbar = ok(await api('GET', '/api/admin/decoration/tabbar/detail', {}, root), 'tabbar detail');
    tabbar.list[1].name = '新资讯';
    ok(await api('POST', '/api/admin/decoration/tabbar/save', tabbar, root), 'tabbar save');
    assert(ok(await api('GET', '/api/decoration/tabbar'), 'tabbar consume').list[1].name === '新资讯', 'tabbar not consumed');
    const tabBeforeInvalid = fingerprint();
    const badFirst = structuredClone(tabbar); badFirst.list[0].link.target = 'profile';
    reject(await api('POST', '/api/admin/decoration/tabbar/save', badFirst, root), 'first tab changed');
    const hidden = structuredClone(tabbar); hidden.list[1].is_show = 0; hidden.list[2].is_show = 0;
    reject(await api('POST', '/api/admin/decoration/tabbar/save', hidden, root), 'visible tab count');
    assert(fingerprint() === tabBeforeInvalid, 'invalid tabbar save changed state');
    summary.checks.tabbar_transaction_and_first_item_invariant = true;

    denied(await api('GET', '/api/admin/decoration/mobile/page/lists', {}, user), 'ungranted mobile');
    const mobilePerms = db("SELECT id FROM pa_system_menu WHERE perms IN ('decoration/mobile/page/lists','decoration/mobile/page/detail') ORDER BY id").split(/\r?\n/).map(Number);
    db(`INSERT INTO pa_system_role_menu (role_id,menu_id) VALUES ${mobilePerms.map((id) => `(${roleId},${id})`).join(',')}`);
    ok(await api('GET', '/api/admin/decoration/mobile/page/lists', {}, user), 'granted mobile');
    denied(await api('GET', '/api/admin/decoration/pc/page/lists', {}, user), 'PC domain isolated');
    assert(Number(scalar("SELECT COUNT(*) FROM pa_system_menu WHERE perms LIKE 'decoration/%'")) === 9, 'decoration permission nodes missing');
    summary.checks.permission_domains = true;
    summary.ok = true;
  } catch (error) { summary.error = String(error?.message || error); }
  finally {
    try { summary.cleanup = restore(); } catch (error) { summary.cleanup_error = String(error?.message || error); }
    summary.ok = summary.ok && summary.cleanup;
    fs.mkdirSync(path.dirname(OUT), { recursive: true }); fs.writeFileSync(OUT, `${JSON.stringify(summary, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(summary)}\n`);
  }
  if (!summary.ok) process.exitCode = 1;
}
main();
