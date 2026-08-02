#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const BASE_URL = 'http://127.0.0.1:8000';
const OUT_FILE = path.join(__dirname, 'api-db-summary.json');
const suffix = `${Date.now().toString(36)}${crypto.randomBytes(2).toString('hex')}`.slice(-9);
const password = 'CH02Accept!260802';
const names = { root: `ch02_${suffix}_root`, admin: `ch02_${suffix}_admin`, role: `ch02_${suffix}_role` };
const created = { admins: new Set(), roles: new Set() };
const configNames = ['app_id', 'app_secret', 'name', 'original_id', 'qr_code'];

function quote(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function db(sql) {
  const result = spawnSync('docker', [
    'exec', '-i', '-e', 'MYSQL_PWD=peanut_2024', 'likeadmin-mysql',
    'mysql', '--default-character-set=utf8mb4', '-h192.168.192.2', '-P3306',
    '-upeanut', '-N', '-B', 'peanut_admin',
  ], { input: `${sql.trim().replace(/;?$/, ';')}\n`, encoding: 'utf8' });
  if (result.status !== 0) throw new Error((result.stderr || 'database command failed').trim().slice(0, 500));
  return (result.stdout || '').trim();
}

function scalar(sql) { return db(sql).split(/\r?\n/)[0]?.split('\t')[0] || ''; }
function md5(value) { return crypto.createHash('md5').update(value).digest('hex'); }
function assert(value, message) { if (!value) throw new Error(message); }

async function api(method, route, body = {}, token = '') {
  const url = new URL(`${BASE_URL}${route}`);
  const headers = { Accept: 'application/json' };
  let payload;
  if (method === 'GET') {
    for (const [key, value] of Object.entries(body)) url.searchParams.set(key, String(value));
  } else {
    headers['content-type'] = 'application/json';
    payload = JSON.stringify(body);
  }
  if (token) headers.Authorization = `Bearer ${token}`;
  return (await fetch(url, { method, headers, body: payload })).json();
}

function ok(payload, label) {
  if (Number(payload?.code) !== 20000) throw new Error(`${label}: ${payload?.msg || 'request failed'}`);
  return payload.data;
}
function denied(payload, label) { assert(Number(payload?.code) === 40300, `${label}: expected 40300`); }
function rejected(payload, label) { assert(Number(payload?.code) !== 20000, `${label}: unexpectedly succeeded`); }

async function login(account) {
  const result = ok(await api('POST', '/api/user/login', { account, password, terminal: 1 }), `${account} login`);
  assert(result?.token, `${account} login returned no token`);
  return result.token;
}

function createAdmin(username, root) {
  const salt = `ch02${root}salt`;
  const hash = md5(`${md5(password)}${salt}`);
  const id = Number(scalar(`INSERT INTO pa_admin
    (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
    VALUES (${quote(username)},'CH02验收',${quote(hash)},${quote(salt)},'',${root},0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
    SELECT LAST_INSERT_ID()`));
  created.admins.add(id);
  return id;
}

function configSnapshot() {
  return db(`SELECT name,HEX(COALESCE(value,'')),update_time FROM pa_config
    WHERE type='mnp_setting' AND name IN (${configNames.map(quote).join(',')}) ORDER BY name`);
}

function forceDefaults() {
  db(`START TRANSACTION;
    ${configNames.map((name) => `INSERT INTO pa_config (type,name,value,create_time,update_time)
      VALUES ('mnp_setting',${quote(name)},'',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
      ON DUPLICATE KEY UPDATE value='',update_time=UNIX_TIMESTAMP()`).join(';')};
    DELETE FROM pa_config WHERE type='channel' AND name IN ('wechat_mini_status','wechat_mini_appid','wechat_mini_secret');
    COMMIT`);
}

function cleanup() {
  const adminIds = [...created.admins];
  const roleIds = [...created.roles];
  const adminIn = adminIds.length ? adminIds.join(',') : '0';
  const roleIn = roleIds.length ? roleIds.join(',') : '0';
  db(`DELETE FROM pa_admin_session WHERE admin_id IN (${adminIn});
    DELETE FROM pa_operation_log WHERE admin_id IN (${adminIn});
    DELETE FROM pa_admin_dept WHERE admin_id IN (${adminIn});
    DELETE FROM pa_admin_jobs WHERE admin_id IN (${adminIn});
    DELETE FROM pa_admin_role WHERE admin_id IN (${adminIn}) OR role_id IN (${roleIn});
    DELETE FROM pa_system_role_menu WHERE role_id IN (${roleIn});
    DELETE FROM pa_admin WHERE id IN (${adminIn});
    DELETE FROM pa_system_role WHERE id IN (${roleIn})`);
  forceDefaults();
  const fixtures = Number(scalar(`SELECT
    (SELECT COUNT(*) FROM pa_admin WHERE id IN (${adminIn})) +
    (SELECT COUNT(*) FROM pa_system_role WHERE id IN (${roleIn})) +
    (SELECT COUNT(*) FROM pa_admin_session WHERE admin_id IN (${adminIn})) +
    (SELECT COUNT(*) FROM pa_admin_role WHERE admin_id IN (${adminIn}) OR role_id IN (${roleIn})) +
    (SELECT COUNT(*) FROM pa_system_role_menu WHERE role_id IN (${roleIn}))`));
  const defaultCount = Number(scalar(`SELECT COUNT(*) FROM pa_config WHERE type='mnp_setting'
    AND name IN (${configNames.map(quote).join(',')}) AND COALESCE(value,'')=''`));
  return {
    fixtures,
    defaults: defaultCount === configNames.length,
  };
}

async function main() {
  const summary = { contract: 'CH02', checks: {}, cleanup: {}, ok: false };
  let stage = 'setup';
  try {
    const collisions = Number(scalar(`SELECT
      (SELECT COUNT(*) FROM pa_admin WHERE username IN (${quote(names.root)},${quote(names.admin)})) +
      (SELECT COUNT(*) FROM pa_system_role WHERE name=${quote(names.role)})`));
    assert(collisions === 0, 'fixture collision');

    const rootId = createAdmin(names.root, 1);
    const roleId = Number(scalar(`INSERT INTO pa_system_role (name,\`desc\`,sort,create_time,update_time)
      VALUES (${quote(names.role)},'CH02 temporary role',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP()); SELECT LAST_INSERT_ID()`));
    created.roles.add(roleId);
    const adminId = createAdmin(names.admin, 0);
    db(`INSERT INTO pa_admin_role (admin_id,role_id) VALUES (${adminId},${roleId})`);

    const rootToken = await login(names.root);
    const adminToken = await login(names.admin);
    stage = 'save-read';
    const savedBody = {
      name: 'CH02 小程序', original_id: 'gh_ch02',
      qr_code: 'http://127.0.0.1:8000/storage/uploads/images/ch02.png',
      app_id: 'wx_ch02_app', app_secret: 'ch02_secret',
    };
    ok(await api('POST', '/api/admin/setting/mini-program/save', savedBody, rootToken), 'root save');
    const detail = ok(await api('GET', '/api/admin/setting/mini-program/config', {}, rootToken), 'root read');
    assert(detail.name === savedBody.name && detail.original_id === savedBody.original_id, 'profile mismatch');
    assert(detail.app_id === savedBody.app_id && detail.app_secret === savedBody.app_secret, 'credential mismatch');
    assert(detail.qr_code === savedBody.qr_code, 'QR absolute URL mismatch');
    assert(scalar("SELECT value FROM pa_config WHERE type='mnp_setting' AND name='qr_code'") === 'storage/uploads/images/ch02.png', 'QR URI was not normalized');
    const expectedDomains = {
      request_domain: 'https://127.0.0.1:8000', socket_domain: 'wss://127.0.0.1:8000',
      upload_file_domain: 'https://127.0.0.1:8000', download_file_domain: 'https://127.0.0.1:8000',
      udp_domain: 'udp://127.0.0.1:8000', business_domain: '127.0.0.1:8000',
    };
    for (const [key, value] of Object.entries(expectedDomains)) assert(detail[key] === value, `${key} mismatch`);
    summary.checks.profile_qr_credentials_and_domains = true;

    stage = 'validation';
    const invalidCases = [
      ['blank app id', { ...savedBody, app_id: '   ' }],
      ['blank secret', { ...savedBody, app_secret: '   ' }],
      ['long name', { ...savedBody, name: '长'.repeat(101) }],
      ['long QR', { ...savedBody, qr_code: `https://example.com/${'x'.repeat(260)}` }],
    ];
    for (const [label, body] of invalidCases) {
      const before = configSnapshot();
      rejected(await api('POST', '/api/admin/setting/mini-program/save', body, rootToken), label);
      assert(configSnapshot() === before, `${label} changed configuration`);
    }
    summary.checks.validation_and_atomic_invariant = true;

    stage = 'permission';
    denied(await api('GET', '/api/admin/setting/mini-program/config', {}, adminToken), 'ungranted read');
    denied(await api('POST', '/api/admin/setting/mini-program/save', savedBody, adminToken), 'ungranted save');
    summary.checks.permission_default_deny = true;
    const menuIds = db(`SELECT id FROM pa_system_menu WHERE is_disable=0
      AND perms IN ('setting/mini-program/config','setting/mini-program/save') ORDER BY perms`)
      .split(/\r?\n/).filter(Boolean).map(Number);
    assert(menuIds.length === 2, 'permission nodes missing');
    db(`INSERT INTO pa_system_role_menu (role_id,menu_id) VALUES ${menuIds.map((id) => `(${roleId},${id})`).join(',')}`);
    ok(await api('GET', '/api/admin/setting/mini-program/config', {}, adminToken), 'granted read');
    ok(await api('POST', '/api/admin/setting/mini-program/save', savedBody, adminToken), 'granted save');
    summary.checks.permission_grant = true;
    db(`DELETE FROM pa_system_role_menu WHERE role_id=${roleId}`);
    denied(await api('GET', '/api/admin/setting/mini-program/config', {}, adminToken), 'revoked read');
    denied(await api('POST', '/api/admin/setting/mini-program/save', savedBody, adminToken), 'revoked save');
    summary.checks.permission_revoke = true;
    assert(Number(scalar("SELECT COUNT(*) FROM pa_config WHERE type='channel' AND name IN ('wechat_mini_status','wechat_mini_appid','wechat_mini_secret')")) === 0, 'legacy mini-program fields remain');
    summary.checks.single_configuration_model = true;
    summary.ok = true;
  } catch (error) {
    summary.stage = stage;
    summary.error = error.message;
  } finally {
    summary.cleanup = cleanup();
    summary.checks.cleanup = summary.cleanup.fixtures === 0 && summary.cleanup.defaults;
    if (!summary.checks.cleanup) summary.ok = false;
    fs.writeFileSync(OUT_FILE, `${JSON.stringify(summary, null, 2)}\n`, 'utf8');
  }
  process.stdout.write(`${JSON.stringify(summary)}\n`);
  if (!summary.ok) process.exitCode = 1;
}

main();
