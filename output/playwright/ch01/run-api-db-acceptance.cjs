#!/usr/bin/env node
'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const BASE_URL = 'http://127.0.0.1:8000';
const OUT_FILE = path.join(__dirname, 'api-db-summary.json');
const runId = `${Date.now().toString(36)}${crypto.randomBytes(2).toString('hex')}`.slice(-10);
const prefix = `ch01_${runId}`;
const password = 'CH01Accept!260802';
const names = {
  root: `${prefix}_root`,
  admin: `${prefix}_admin`,
  role: `${prefix}_role`,
};
const created = { adminIds: new Set(), roleIds: new Set() };

function quote(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function db(sql) {
  const result = spawnSync(
    'docker',
    [
      'exec', '-i', '-e', 'MYSQL_PWD=peanut_2024', 'likeadmin-mysql',
      'mysql', '--default-character-set=utf8mb4', '-h192.168.192.2', '-P3306',
      '-upeanut', '-N', '-B', 'peanut_admin',
    ],
    { input: `${sql.trim().replace(/;?$/, ';')}\n`, encoding: 'utf8' }
  );
  if (result.status !== 0) {
    throw new Error((result.stderr || 'database command failed').trim().slice(0, 500));
  }
  return (result.stdout || '').trim();
}

function scalar(sql) {
  return db(sql).split(/\r?\n/)[0]?.split('\t')[0] || '';
}

function md5(value) {
  return crypto.createHash('md5').update(value).digest('hex');
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

async function request(method, route, payload = {}, token = '') {
  const url = new URL(`${BASE_URL}${route}`);
  const headers = { Accept: 'application/json' };
  if (token) headers.Authorization = `Bearer ${token}`;
  let body;
  if (method === 'GET') {
    for (const [key, value] of Object.entries(payload)) url.searchParams.set(key, String(value));
  } else {
    headers['content-type'] = 'application/json';
    body = JSON.stringify(payload);
  }
  const response = await fetch(url, { method, headers, body });
  return response.json();
}

function data(payload, message) {
  if (Number(payload?.code) !== 20000) {
    throw new Error(`${message}: ${payload?.msg || 'request failed'}`);
  }
  return payload.data;
}

function rejected(payload, message) {
  if (Number(payload?.code) === 20000) {
    throw new Error(`${message}: unexpectedly succeeded`);
  }
}

function denied(payload, message) {
  if (Number(payload?.code) !== 40300) {
    throw new Error(`${message}: expected 40300, got ${payload?.code}`);
  }
}

async function login(account) {
  const result = data(await request('POST', '/api/user/login', {
    account,
    password,
    terminal: 1,
  }), `${account} login`);
  assert(result?.token, `${account} login returned no token`);
  return result.token;
}

function configSnapshot() {
  return db(`SELECT name,HEX(COALESCE(value,'')),update_time
    FROM pa_config
    WHERE type='web_page' AND name IN ('status','page_status','page_url')
    ORDER BY name`);
}

function forceDefaults() {
  db(`START TRANSACTION;
    INSERT INTO pa_config (type,name,value,create_time,update_time)
    VALUES ('web_page','status','1',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
    ON DUPLICATE KEY UPDATE value='1',update_time=UNIX_TIMESTAMP();
    INSERT INTO pa_config (type,name,value,create_time,update_time)
    VALUES ('web_page','page_status','0',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
    ON DUPLICATE KEY UPDATE value='0',update_time=UNIX_TIMESTAMP();
    INSERT INTO pa_config (type,name,value,create_time,update_time)
    VALUES ('web_page','page_url','',UNIX_TIMESTAMP(),UNIX_TIMESTAMP())
    ON DUPLICATE KEY UPDATE value='',update_time=UNIX_TIMESTAMP();
    COMMIT`);
}

function cleanup() {
  const adminIds = [...created.adminIds].filter(Number.isInteger);
  const roleIds = [...created.roleIds].filter(Number.isInteger);
  if (adminIds.length || roleIds.length) {
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
  }
  forceDefaults();

  const adminIn = adminIds.length ? adminIds.join(',') : '0';
  const roleIn = roleIds.length ? roleIds.join(',') : '0';
  const counts = db(`SELECT
    (SELECT COUNT(*) FROM pa_admin WHERE id IN (${adminIn})),
    (SELECT COUNT(*) FROM pa_system_role WHERE id IN (${roleIn})),
    (SELECT COUNT(*) FROM pa_admin_role WHERE admin_id IN (${adminIn}) OR role_id IN (${roleIn})),
    (SELECT COUNT(*) FROM pa_system_role_menu WHERE role_id IN (${roleIn})),
    (SELECT COUNT(*) FROM pa_admin_session WHERE admin_id IN (${adminIn})),
    (SELECT COUNT(*) FROM pa_operation_log WHERE admin_id IN (${adminIn})),
    (SELECT COUNT(*) FROM pa_admin_dept WHERE admin_id IN (${adminIn})),
    (SELECT COUNT(*) FROM pa_admin_jobs WHERE admin_id IN (${adminIn}))`)
    .split('\t').map(Number);
  const defaults = db(`SELECT name,value FROM pa_config
    WHERE type='web_page' AND name IN ('status','page_status','page_url') ORDER BY name`);
  return {
    fixtures: counts.reduce((sum, count) => sum + count, 0),
    defaults: defaults === 'page_status\t0\npage_url\t\nstatus\t1',
  };
}

async function main() {
  const summary = { contract: 'CH01', checks: {}, cleanup: {}, ok: false };
  let stage = 'setup';
  try {
    const collisions = Number(scalar(`SELECT
      (SELECT COUNT(*) FROM pa_admin WHERE username IN (${quote(names.root)},${quote(names.admin)})) +
      (SELECT COUNT(*) FROM pa_system_role WHERE name=${quote(names.role)})`));
    assert(collisions === 0, 'fixture natural key collision');

    const rootSalt = 'ch01rootsalt';
    const rootHash = md5(`${md5(password)}${rootSalt}`);
    const rootId = Number(scalar(`INSERT INTO pa_admin
      (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
      VALUES (${quote(names.root)},'CH01 root',${quote(rootHash)},${quote(rootSalt)},'',1,0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID()`));
    created.adminIds.add(rootId);

    const roleId = Number(scalar(`INSERT INTO pa_system_role (name,\`desc\`,sort,create_time,update_time)
      VALUES (${quote(names.role)},'CH01 temporary least-privilege role',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID()`));
    created.roleIds.add(roleId);

    const adminSalt = 'ch01admsalt';
    const adminHash = md5(`${md5(password)}${adminSalt}`);
    const adminId = Number(scalar(`INSERT INTO pa_admin
      (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
      VALUES (${quote(names.admin)},'CH01 admin',${quote(adminHash)},${quote(adminSalt)},'',0,0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID()`));
    created.adminIds.add(adminId);
    db(`INSERT INTO pa_admin_role (admin_id,role_id) VALUES (${adminId},${roleId})`);

    const rootToken = await login(names.root);
    const adminToken = await login(names.admin);

    stage = 'management-read';
    const initial = data(await request('GET', '/api/admin/setting/web-page/config', {}, rootToken), 'management read');
    assert([0, 1].includes(Number(initial.status)), 'management status invalid');
    assert([0, 1].includes(Number(initial.page_status)), 'management page_status invalid');
    assert(String(initial.url).endsWith('/mobile'), 'derived mobile URL invalid');
    summary.checks.management_read = true;

    stage = 'validation';
    data(await request('POST', '/api/admin/setting/web-page/save', {
      status: 0,
      page_status: 1,
      page_url: 'https://example.com/ch01-baseline',
    }, rootToken), 'baseline save');
    const invalidCases = [
      ['status enum', { status: 2, page_status: 0, page_url: '' }],
      ['page_status enum', { status: 1, page_status: -1, page_url: '' }],
      ['empty redirect', { status: 0, page_status: 1, page_url: '' }],
      ['non-http redirect', { status: 0, page_status: 1, page_url: 'ftp://example.com/ch01' }],
    ];
    for (const [label, body] of invalidCases) {
      const before = configSnapshot();
      rejected(await request('POST', '/api/admin/setting/web-page/save', body, rootToken), label);
      assert(configSnapshot() === before, `${label} changed config values or update_time`);
    }
    summary.checks.strict_validation_and_invariant = true;

    stage = 'public-consumption';
    const managed = data(await request('GET', '/api/admin/setting/web-page/config', {}, rootToken), 'management re-read');
    const globalConfig = data(await request('GET', '/api/index/config'), 'public config');
    const publicWebPage = globalConfig?.web_page;
    assert(publicWebPage, 'public web_page config missing');
    for (const key of ['status', 'page_status', 'page_url', 'url']) {
      assert(String(publicWebPage[key]) === String(managed[key]), `public/admin ${key} mismatch`);
    }
    summary.checks.public_consistency = true;

    stage = 'permission-deny';
    const beforeDenied = configSnapshot();
    denied(await request('GET', '/api/admin/setting/web-page/config', {}, adminToken), 'ungranted read');
    denied(await request('POST', '/api/admin/setting/web-page/save', {
      status: 1, page_status: 0, page_url: '',
    }, adminToken), 'ungranted save');
    assert(configSnapshot() === beforeDenied, 'ungranted actor changed config');
    summary.checks.permission_default_deny = true;

    stage = 'permission-grant';
    const menuRows = db(`SELECT id,perms FROM pa_system_menu
      WHERE is_disable=0 AND perms IN ('setting/web-page/config','setting/web-page/save') ORDER BY perms`)
      .split(/\r?\n/).filter(Boolean).map((line) => line.split('\t'));
    assert(menuRows.length === 2, 'CH01 permission nodes missing');
    const menuIds = menuRows.map(([id]) => Number(id));
    db(`INSERT INTO pa_system_role_menu (role_id,menu_id) VALUES
      (${roleId},${menuIds[0]}),(${roleId},${menuIds[1]})`);
    data(await request('GET', '/api/admin/setting/web-page/config', {}, adminToken), 'granted read');
    data(await request('POST', '/api/admin/setting/web-page/save', {
      status: 0,
      page_status: 1,
      page_url: 'https://example.com/ch01-authorized',
    }, adminToken), 'granted save');
    const authorized = data(await request('GET', '/api/admin/setting/web-page/config', {}, adminToken), 'granted re-read');
    assert(Number(authorized.status) === 0 && Number(authorized.page_status) === 1
      && authorized.page_url === 'https://example.com/ch01-authorized', 'granted save mismatch');
    summary.checks.permission_grant = true;

    stage = 'permission-revoke';
    db(`DELETE FROM pa_system_role_menu WHERE role_id=${roleId}`);
    const beforeRevoked = configSnapshot();
    denied(await request('GET', '/api/admin/setting/web-page/config', {}, adminToken), 'revoked read');
    denied(await request('POST', '/api/admin/setting/web-page/save', {
      status: 1, page_status: 0, page_url: '',
    }, adminToken), 'revoked save');
    assert(configSnapshot() === beforeRevoked, 'revoked actor changed config');
    summary.checks.permission_revoke = true;
    summary.ok = true;
  } catch (error) {
    summary.failed_stage = stage;
    summary.error = error.message;
  } finally {
    try {
      summary.cleanup = cleanup();
      summary.checks.cleanup = summary.cleanup.fixtures === 0 && summary.cleanup.defaults;
      if (!summary.checks.cleanup) summary.ok = false;
    } catch (error) {
      summary.cleanup = { error: error.message };
      summary.ok = false;
    }
    fs.writeFileSync(OUT_FILE, `${JSON.stringify(summary, null, 2)}\n`, 'utf8');
  }

  process.stdout.write(`${JSON.stringify(summary)}\n`);
  if (!summary.ok) process.exitCode = 1;
}

main().catch((error) => {
  process.stdout.write(`${JSON.stringify({ contract: 'CH01', ok: false, error: error.message })}\n`);
  process.exitCode = 1;
});
