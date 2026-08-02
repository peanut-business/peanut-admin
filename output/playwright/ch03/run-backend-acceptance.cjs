#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const BASE = 'http://127.0.0.1:8000';
const OUT = path.join(__dirname, 'backend-summary.json');
const tag = `ch03_${Date.now().toString(36)}`;
const password = 'CH03Accept!260802';
const created = { admins: [], roles: [], replies: [] };
let snapshot = [];

function quote(value) { return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`; }
function db(sql) {
  const r = spawnSync('docker', ['exec', '-i', '-e', 'MYSQL_PWD=peanut_2024', 'likeadmin-mysql',
    'mysql', '--default-character-set=utf8mb4', '-h192.168.192.2', '-P3306', '-upeanut', '-N', '-B', 'peanut_admin'],
  { input: `${sql.trim().replace(/;?$/, ';')}\n`, encoding: 'utf8' });
  if (r.status !== 0) throw new Error((r.stderr || 'database failed').trim().slice(0, 500));
  return (r.stdout || '').trim();
}
function scalar(sql) { return db(sql).split(/\r?\n/)[0]?.split('\t')[0] || ''; }
function assert(value, message) { if (!value) throw new Error(message); }
function md5(value) { return crypto.createHash('md5').update(value).digest('hex'); }

async function request(method, route, body = {}, token = '', json = true) {
  const url = new URL(`${BASE}${route}`);
  const headers = { Accept: json ? 'application/json' : '*/*' };
  let payload;
  if (method === 'GET') Object.entries(body).forEach(([k, v]) => url.searchParams.set(k, String(v)));
  else { headers['content-type'] = json ? 'application/json' : 'application/xml'; payload = json ? JSON.stringify(body) : body; }
  if (token) headers.Authorization = `Bearer ${token}`;
  const response = await fetch(url, { method, headers, body: payload });
  return json ? response.json() : { status: response.status, text: await response.text() };
}
function ok(data, label) { assert(Number(data?.code) === 20000, `${label}: ${data?.msg || 'failed'}`); return data.data; }
function rejected(data, label) { assert(Number(data?.code) !== 20000, `${label}: unexpectedly succeeded`); }
function denied(data, label) { assert(Number(data?.code) === 40300, `${label}: expected 40300`); }

function createAdmin(username, root) {
  const salt = `${tag}_${root}`;
  const id = Number(scalar(`INSERT INTO pa_admin
    (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
    VALUES (${quote(username)},'CH03验收',${quote(md5(`${md5(password)}${salt}`))},${quote(salt)},'',${root},0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
    SELECT LAST_INSERT_ID()`));
  created.admins.push(id);
  return id;
}
async function login(account) {
  return ok(await request('POST', '/api/user/login', { account, password, terminal: 1 }), `${account} login`).token;
}
function snapshotConfig() {
  const rows = db("SELECT type,name,HEX(value),create_time,update_time FROM pa_config WHERE type IN ('oa_setting','open_platform') ORDER BY type,name");
  return rows.split(/\r?\n/).filter(Boolean).map((line) => {
    const [type, name, hex, createTime, updateTime] = line.split('\t');
    return { type, name, hex, createTime, updateTime };
  });
}
function configFingerprint() {
  return db("SELECT type,name,HEX(value) FROM pa_config WHERE type IN ('oa_setting','open_platform') ORDER BY type,name");
}
function restoreConfig() {
  for (const row of snapshot) {
    const valueSql = row.hex === '' ? "''" : `0x${row.hex}`;
    db(`UPDATE pa_config SET value=${valueSql},create_time=${Number(row.createTime)},update_time=${Number(row.updateTime)}
      WHERE type=${quote(row.type)} AND name=${quote(row.name)}`);
  }
}
function cleanup() {
  const adminIds = created.admins.length ? created.admins.join(',') : '0';
  const roleIds = created.roles.length ? created.roles.join(',') : '0';
  const replyIds = created.replies.length ? created.replies.join(',') : '0';
  db(`DELETE FROM pa_admin_session WHERE admin_id IN (${adminIds});
      DELETE FROM pa_operation_log WHERE admin_id IN (${adminIds});
      DELETE FROM pa_admin_role WHERE admin_id IN (${adminIds}) OR role_id IN (${roleIds});
      DELETE FROM pa_system_role_menu WHERE role_id IN (${roleIds});
      DELETE FROM pa_admin WHERE id IN (${adminIds});
      DELETE FROM pa_system_role WHERE id IN (${roleIds});
      DELETE FROM pa_official_account_reply WHERE id IN (${replyIds})`);
  restoreConfig();
  return Number(scalar(`SELECT
    (SELECT COUNT(*) FROM pa_admin WHERE id IN (${adminIds}))+
    (SELECT COUNT(*) FROM pa_system_role WHERE id IN (${roleIds}))+
    (SELECT COUNT(*) FROM pa_admin_session WHERE admin_id IN (${adminIds}))+
    (SELECT COUNT(*) FROM pa_official_account_reply WHERE id IN (${replyIds}))`)) === 0;
}
function signature(token, timestamp, nonce) {
  return crypto.createHash('sha1').update([token, timestamp, nonce].sort().join('')).digest('hex');
}
function mockPublish(menu, fail) {
  const encoded = Buffer.from(JSON.stringify(menu)).toString('base64');
  const serverRoot = path.resolve(__dirname, '../../../server');
  const code = `require ${JSON.stringify(path.join(serverRoot, 'vendor/autoload.php'))};
    $app=new think\\App(${JSON.stringify(serverRoot)});$app->initialize();
    $menu=json_decode(base64_decode(${JSON.stringify(encoded)}),true);
    $before=app\\common\\service\\ConfigService::get("oa_setting","menu","[]");
    $calls=[];
    $service=new app\\common\\service\\wechat\\OfficialAccountService(
      function($method,$url,$headers,$body) use (&$calls){$calls[]=[$method,$url,$body];
        if($method==="GET") return [200,"{\\"access_token\\":\\"mock-token\\"}"];
        return [200,${fail ? '"{\\"errcode\\":45064,\\"errmsg\\":\\"mock rejected\\"}"' : '"{\\"errcode\\":0,\\"errmsg\\":\\"ok\\"}"'}];});
    $ok=app\\adminapi\\logic\\setting\\OfficialAccountMenuLogic::saveAndPublish($menu,$service);
    $after=app\\common\\service\\ConfigService::get("oa_setting","menu","[]");
    echo json_encode(["ok"=>$ok,"unchanged"=>$before===$after,"stored"=>json_decode($after,true),"calls"=>count($calls)]);`;
  const result = spawnSync('php', ['-r', code], { encoding: 'utf8' });
  if (result.status !== 0) throw new Error((result.stderr || 'mock publish failed').trim().slice(0, 500));
  return JSON.parse(result.stdout);
}

async function main() {
  const summary = { contract: 'CH03-backend', checks: {}, cleanup: false, ok: false };
  try {
    snapshot = snapshotConfig();
    const rootName = `${tag}_root`;
    const userName = `${tag}_user`;
    createAdmin(rootName, 1);
    const userId = createAdmin(userName, 0);
    const roleId = Number(scalar(`INSERT INTO pa_system_role (name,\`desc\`,sort,create_time,update_time)
      VALUES (${quote(`${tag}_role`)},'CH03 temporary role',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()); SELECT LAST_INSERT_ID()`));
    created.roles.push(roleId);
    db(`INSERT INTO pa_admin_role (admin_id,role_id) VALUES (${userId},${roleId})`);
    const rootToken = await login(rootName);
    const userToken = await login(userName);

    const config = { name: 'CH03公众号', original_id: 'gh_ch03', qr_code: `${BASE}/storage/ch03.png`,
      app_id: 'wx_ch03', app_secret: 'secret_ch03', token: 'token_ch03', encoding_aes_key: '', encryption_type: 1 };
    ok(await request('POST', '/api/admin/setting/official-account/save', config, rootToken), 'save OA config');
    const detail = ok(await request('GET', '/api/admin/setting/official-account/config', {}, rootToken), 'read OA config');
    assert(detail.app_id === config.app_id && detail.callback_mode === 'plaintext', 'OA config mismatch');
    assert(detail.url === `${BASE}/api/wechat/official-account/callback`, 'callback URL mismatch');
    const beforeInvalid = configFingerprint();
    rejected(await request('POST', '/api/admin/setting/official-account/save', { ...config, app_id: ' ' }, rootToken), 'blank AppID');
    assert(configFingerprint() === beforeInvalid, 'invalid OA save changed config');
    const open = { app_id: 'open_ch03', app_secret: 'open_secret_ch03' };
    ok(await request('POST', '/api/admin/setting/open-platform/save', open, rootToken), 'save open platform');
    assert(ok(await request('GET', '/api/admin/setting/open-platform/config', {}, rootToken), 'read open platform').app_id === open.app_id, 'open config mismatch');
    summary.checks.atomic_config_and_open_platform = true;

    const menu = [{ name: '首页', type: 'view', url: 'https://example.com', sub_button: [] }];
    ok(await request('POST', '/api/admin/setting/official-account/menu/save', { menu }, rootToken), 'local menu save');
    assert(JSON.stringify(ok(await request('GET', '/api/admin/setting/official-account/menu', {}, rootToken), 'menu detail').menu) === JSON.stringify(menu), 'menu read mismatch');
    rejected(await request('POST', '/api/admin/setting/official-account/menu/save', { menu: [...menu, ...menu, ...menu, ...menu] }, rootToken), 'menu count');
    const failedPublish = mockPublish([{ name: '失败', type: 'click', key: 'fail', sub_button: [] }], true);
    assert(!failedPublish.ok && failedPublish.unchanged && failedPublish.calls === 2, 'failed publish overwrote local menu');
    const publishedMenu = [{ name: '发布', type: 'click', key: 'published', sub_button: [] }];
    const successfulPublish = mockPublish(publishedMenu, false);
    assert(successfulPublish.ok && JSON.stringify(successfulPublish.stored) === JSON.stringify(publishedMenu), 'successful publish not stored');
    summary.checks.menu_validation_and_publish_ordering = true;

    async function addReply(body) {
      ok(await request('POST', '/api/admin/setting/official-account/reply/add', body, rootToken), `add ${body.name}`);
      const id = Number(scalar(`SELECT id FROM pa_official_account_reply WHERE name=${quote(body.name)} ORDER BY id DESC LIMIT 1`));
      created.replies.push(id); return id;
    }
    const baseReply = { content_type: 1, status: 1, sort: 0 };
    const sub1 = await addReply({ ...baseReply, name: `${tag}_sub1`, reply_type: 1, content: 'subscribe-one' });
    const sub2 = await addReply({ ...baseReply, name: `${tag}_sub2`, reply_type: 1, content: 'subscribe-two' });
    assert(db(`SELECT status FROM pa_official_account_reply WHERE id IN (${sub1},${sub2}) ORDER BY id`) === '0\n1', 'subscribe uniqueness failed');
    ok(await request('POST', '/api/admin/setting/official-account/reply/status', { id: sub1, status: 1 }, rootToken), 'switch singleton');
    assert(db(`SELECT status FROM pa_official_account_reply WHERE id IN (${sub1},${sub2}) ORDER BY id`) === '1\n0', 'status invariant bypassed');
    await addReply({ ...baseReply, name: `${tag}_default`, reply_type: 3, content: 'default-reply' });
    await addReply({ ...baseReply, name: `${tag}_fuzzy`, reply_type: 2, keyword: 'hello', matching_type: 2, sort: 20, content: 'fuzzy-reply' });
    await addReply({ ...baseReply, name: `${tag}_exact`, reply_type: 2, keyword: 'hello', matching_type: 1, sort: 10, content: 'exact-reply' });
    rejected(await request('POST', '/api/admin/setting/official-account/reply/add', { ...baseReply, name: `${tag}_bad`, reply_type: 2, keyword: 'x', matching_type: 1, sort: -1, content: 'bad' }, rootToken), 'negative sort');
    rejected(await request('POST', '/api/admin/setting/official-account/reply/status', { id: 2147483647, status: 1 }, rootToken), 'invalid reply id');
    assert(Number(scalar("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema='peanut_admin' AND table_name='pa_official_account_reply' AND column_name='reply_num'")) === 0, 'reply_num leaked into schema');
    summary.checks.reply_transactions_and_invariants = true;

    const timestamp = '1785661000'; const nonce = 'ch03nonce'; const sig = signature(config.token, timestamp, nonce);
    const handshake = await request('GET', '/api/wechat/official-account/callback', { timestamp, nonce, signature: sig, echostr: 'verified' }, '', false);
    assert(handshake.status === 200 && handshake.text === 'verified', 'callback handshake failed');
    async function webhook(inner) {
      return request('POST', `/api/wechat/official-account/callback?timestamp=${timestamp}&nonce=${nonce}&signature=${sig}`, inner, '', false);
    }
    const envelope = (inner) => `<xml><ToUserName><![CDATA[gh]]></ToUserName><FromUserName><![CDATA[user]]></FromUserName>${inner}</xml>`;
    assert((await webhook(envelope('<MsgType><![CDATA[event]]></MsgType><Event><![CDATA[subscribe]]></Event>'))).text.includes('subscribe-one'), 'subscribe reply failed');
    assert((await webhook(envelope('<MsgType><![CDATA[text]]></MsgType><Content><![CDATA[hello]]></Content>'))).text.includes('exact-reply'), 'keyword priority failed');
    assert((await webhook(envelope('<MsgType><![CDATA[text]]></MsgType><Content><![CDATA[unknown]]></Content>'))).text.includes('default-reply'), 'default fallback failed');
    const badSignature = await request('GET', '/api/wechat/official-account/callback', { timestamp, nonce, signature: 'bad', echostr: 'x' }, '', false);
    assert(badSignature.status === 403, 'invalid signature accepted');
    summary.checks.plaintext_webhook_state_machine = true;

    denied(await request('GET', '/api/admin/setting/official-account/config', {}, userToken), 'ungranted config');
    const viewMenuId = Number(scalar("SELECT id FROM pa_system_menu WHERE perms='setting/official-account/config' LIMIT 1"));
    db(`INSERT INTO pa_system_role_menu (role_id,menu_id) VALUES (${roleId},${viewMenuId})`);
    ok(await request('GET', '/api/admin/setting/official-account/config', {}, userToken), 'granted config view');
    denied(await request('POST', '/api/admin/setting/official-account/save', config, userToken), 'save remains denied');
    assert(Number(scalar("SELECT COUNT(*) FROM pa_system_menu WHERE perms LIKE 'setting/official-account/%' OR perms LIKE 'setting/open-platform/%'")) === 13, 'permission nodes missing');
    summary.checks.permission_semantics = true;
    summary.ok = true;
  } catch (error) {
    summary.error = String(error?.message || error);
  } finally {
    try { summary.cleanup = cleanup(); } catch (error) { summary.cleanup_error = String(error?.message || error); }
    summary.ok = summary.ok && summary.cleanup;
    fs.mkdirSync(path.dirname(OUT), { recursive: true });
    fs.writeFileSync(OUT, `${JSON.stringify(summary, null, 2)}\n`);
    process.stdout.write(`${JSON.stringify(summary)}\n`);
  }
  if (!summary.ok) process.exitCode = 1;
}

main();
