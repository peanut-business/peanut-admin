'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const BASE_URL = 'http://127.0.0.1:8000';
const OUT_FILE = path.join(__dirname, 'channel-permission-summary.json');
const stamp = Date.now().toString(36).slice(-6);
const password = 'M01Permission!260801';
const rootName = `m01${stamp}root`;
const userName = `m01${stamp}user`;
const roleName = `m01${stamp}role`;
const logReceiver = `m01-${stamp}@fixture.invalid`;
const configNames = ['sms_default', 'sms_aliyun', 'sms_tencent'];
const created = { adminIds: new Set(), roleIds: new Set(), logIds: new Set() };

function quote(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function db(sql) {
  const result = spawnSync(
    'docker',
    [
      'exec', '-i', '-e', 'MYSQL_PWD=peanut_2024', 'likeadmin-mysql',
      'mysql', '-h192.168.192.2', '-P3306', '-upeanut', '-N', '-B', 'peanut_admin',
    ],
    { input: `${sql.trim().replace(/;?$/, ';')}\n`, encoding: 'utf8' }
  );
  if (result.status !== 0) throw new Error((result.stderr || 'database command failed').trim());
  return (result.stdout || '').trim();
}

function scalar(sql) {
  return db(sql).split(/\r?\n/)[0]?.split('\t')[0] || '';
}

function md5(value) {
  return crypto.createHash('md5').update(value).digest('hex');
}

async function api(route, body, token = '') {
  const response = await fetch(`${BASE_URL}${route}`, {
    method: body === undefined ? 'GET' : 'POST',
    headers: {
      ...(body === undefined ? {} : { 'content-type': 'application/json' }),
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    ...(body === undefined ? {} : { body: JSON.stringify(body) }),
  });
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch {
    throw new Error(`${route} returned non-JSON response`);
  }
}

function ok(payload, label) {
  if (Number(payload?.code) !== 20000) throw new Error(`${label}: ${payload?.msg || 'request failed'}`);
  return payload.data;
}

function expectDenied(payload, label) {
  if (Number(payload?.code) !== 40300) throw new Error(`${label} was not denied: ${JSON.stringify(payload)}`);
}

function expectNotDenied(payload, label) {
  if (Number(payload?.code) === 40300) throw new Error(`${label} remained denied`);
}

function snapshotConfig(name) {
  const line = db(`SELECT id,HEX(value),create_time,update_time FROM pa_config
    WHERE type='notice' AND name=${quote(name)} LIMIT 1`);
  if (!line) return { name, exists: false };
  const [id, valueHex, createTime, updateTime] = line.split('\t');
  return {
    name,
    exists: true,
    id: Number(id),
    value_hex: valueHex,
    create_time: Number(createTime),
    update_time: Number(updateTime),
  };
}

function restoreConfig(row) {
  if (!row.exists) {
    db(`DELETE FROM pa_config WHERE type='notice' AND name=${quote(row.name)}`);
    return;
  }
  db(`UPDATE pa_config SET
    value=CONVERT(UNHEX(${quote(row.value_hex)}) USING utf8mb4),
    create_time=${row.create_time},
    update_time=${row.update_time}
    WHERE id=${row.id} AND type='notice' AND name=${quote(row.name)}`);
}

function configMatches(row) {
  const current = snapshotConfig(row.name);
  return row.exists === current.exists
    && (!row.exists || (
      row.id === current.id
      && row.value_hex === current.value_hex
      && row.create_time === current.create_time
      && row.update_time === current.update_time
    ));
}

function createAdmin(username, root) {
  const salt = 'm01perm';
  const hash = md5(`${md5(password)}${salt}`);
  const id = Number(scalar(`INSERT INTO pa_admin
    (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
    VALUES (${quote(username)},'M01权限验收',${quote(hash)},${quote(salt)},'',${root},0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
    SELECT LAST_INSERT_ID()`));
  created.adminIds.add(id);
  return id;
}

function notificationMenuIds() {
  const rows = db(`SELECT id,pid,paths,perms FROM pa_system_menu ORDER BY id`)
    .split(/\r?\n/).filter(Boolean).map((line) => {
      const [id, pid, paths, perms] = line.split('\t');
      return { id: Number(id), pid: Number(pid), paths: paths || '', perms: perms || '' };
    });
  const byId = new Map(rows.map((row) => [row.id, row]));
  const ids = new Set(rows
    .filter((row) => row.paths.startsWith('/notice/') || row.perms.startsWith('notice/'))
    .map((row) => row.id));
  for (const id of [...ids]) {
    let parent = byId.get(byId.get(id)?.pid);
    while (parent) {
      ids.add(parent.id);
      parent = byId.get(parent.pid);
    }
  }
  return [...ids].sort((a, b) => a - b);
}

function flattenMenus(menus) {
  const rows = [];
  for (const item of menus || []) {
    rows.push(item);
    rows.push(...flattenMenus(item.children || []));
  }
  return rows;
}

function cleanup(configSnapshot) {
  for (const row of configSnapshot) restoreConfig(row);

  db(`SELECT id FROM pa_notice_log WHERE receiver=${quote(logReceiver)}`)
    .split(/\r?\n/).filter(Boolean).map(Number).forEach((id) => created.logIds.add(id));
  if (created.logIds.size) db(`DELETE FROM pa_notice_log WHERE id IN (${[...created.logIds].join(',')})`);

  db(`SELECT id FROM pa_admin WHERE username IN (${quote(rootName)},${quote(userName)})`)
    .split(/\r?\n/).filter(Boolean).map(Number).forEach((id) => created.adminIds.add(id));
  if (created.adminIds.size) {
    const ids = [...created.adminIds].join(',');
    db(`DELETE FROM pa_admin_session WHERE admin_id IN (${ids});
        DELETE FROM pa_operation_log WHERE admin_id IN (${ids});
        DELETE FROM pa_admin_dept WHERE admin_id IN (${ids});
        DELETE FROM pa_admin_jobs WHERE admin_id IN (${ids});
        DELETE FROM pa_admin_role WHERE admin_id IN (${ids});
        DELETE FROM pa_admin WHERE id IN (${ids})`);
  }

  db(`SELECT id FROM pa_system_role WHERE name=${quote(roleName)}`)
    .split(/\r?\n/).filter(Boolean).map(Number).forEach((id) => created.roleIds.add(id));
  if (created.roleIds.size) {
    const ids = [...created.roleIds].join(',');
    db(`DELETE FROM pa_system_role_menu WHERE role_id IN (${ids});
        DELETE FROM pa_system_role WHERE id IN (${ids})`);
  }
}

async function main() {
  const configSnapshot = configNames.map(snapshotConfig);
  const summary = { contract: 'M01-channel-permission', checks: {}, cleanup: false, ok: false };

  try {
    const collisions = Number(scalar(`SELECT
      (SELECT COUNT(*) FROM pa_admin WHERE username IN (${quote(rootName)},${quote(userName)})) +
      (SELECT COUNT(*) FROM pa_system_role WHERE name=${quote(roleName)}) +
      (SELECT COUNT(*) FROM pa_notice_log WHERE receiver=${quote(logReceiver)})`));
    if (collisions !== 0) throw new Error('fixture natural key collision');

    const rootId = createAdmin(rootName, 1);
    const rootLogin = ok(await api('/api/user/login', { account: rootName, password, terminal: 1 }), 'root login');
    const rootToken = rootLogin.token;

    const incomplete = await api('/api/admin/notice/channel/save', {
      section: 'sms_aliyun', access_key_id: '', access_key_secret: '', sign_name: '', status: 1,
    }, rootToken);
    if (Number(incomplete.code) === 20000 || snapshotConfig('sms_aliyun').exists !== configSnapshot[1].exists) {
      throw new Error('incomplete provider configuration changed state');
    }
    summary.checks.incomplete_provider_rejected = true;

    ok(await api('/api/admin/notice/channel/save', {
      section: 'sms_aliyun', access_key_id: 'M01_FAKE_AK', access_key_secret: 'M01_FAKE_SECRET', sign_name: 'M01测试', status: 1,
    }, rootToken), 'enable aliyun');
    let channel = ok(await api('/api/admin/notice/channel/detail', undefined, rootToken), 'aliyun state');
    if (channel.sms_default !== 'aliyun' || Number(channel.sms_aliyun.status) !== 1 || Number(channel.sms_tencent.status) !== 0) {
      throw new Error('aliyun activation state mismatch');
    }

    ok(await api('/api/admin/notice/channel/save', {
      section: 'sms_tencent', secret_id: 'M01_FAKE_ID', secret_key: 'M01_FAKE_KEY', sdk_app_id: 'M01_FAKE_APP', sign_name: 'M01测试', region: 'ap-guangzhou', status: 1,
    }, rootToken), 'enable tencent');
    channel = ok(await api('/api/admin/notice/channel/detail', undefined, rootToken), 'tencent state');
    if (channel.sms_default !== 'tencent' || Number(channel.sms_tencent.status) !== 1 || Number(channel.sms_aliyun.status) !== 0) {
      throw new Error('provider switch state mismatch');
    }
    summary.checks.provider_switch = true;

    ok(await api('/api/admin/notice/channel/save', {
      section: 'sms_tencent', secret_id: 'M01_FAKE_ID', secret_key: 'M01_FAKE_KEY', sdk_app_id: 'M01_FAKE_APP', sign_name: 'M01测试', region: 'ap-guangzhou', status: 0,
    }, rootToken), 'disable tencent');
    channel = ok(await api('/api/admin/notice/channel/detail', undefined, rootToken), 'disabled provider state');
    if (channel.sms_default !== '' || Number(channel.sms_tencent.status) !== 0 || Number(channel.sms_aliyun.status) !== 0 || channel.status.sms !== false) {
      throw new Error('provider disable state mismatch');
    }
    const disabledDefault = await api('/api/admin/notice/channel/save', {
      section: 'sms_default', value: 'aliyun',
    }, rootToken);
    if (Number(disabledDefault.code) === 20000) throw new Error('disabled provider selected as default');
    summary.checks.provider_disable_and_default_rule = true;

    const menuIds = notificationMenuIds();
    if (!menuIds.length) throw new Error('notification permission nodes missing');
    const roleId = Number(scalar(`INSERT INTO pa_system_role
      (name,\`desc\`,sort,create_time,update_time) VALUES (${quote(roleName)},'M01 fixture',0,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID()`));
    created.roleIds.add(roleId);
    const userId = createAdmin(userName, 0);
    db(`INSERT INTO pa_admin_role (admin_id,role_id) VALUES (${userId},${roleId});
        INSERT INTO pa_system_role_menu (role_id,menu_id) VALUES ${menuIds.map((id) => `(${roleId},${id})`).join(',')}`);

    const sceneIdValue = Number(scalar(`SELECT id FROM pa_notice_scene WHERE code='login_code' LIMIT 1`));
    const logId = Number(scalar(`INSERT INTO pa_notice_log
      (template_id,scene_id,channel,provider,receiver,title,content,verify_code,is_verified,check_count,verified_time,status,error,extra,send_time,create_time)
      VALUES (0,${sceneIdValue},1,'fixture',${quote(logReceiver)},'M01 fixture','fixture','',0,0,0,1,'','{}',UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID()`));
    created.logIds.add(logId);

    const userLogin = ok(await api('/api/user/login', { account: userName, password, terminal: 1 }), 'ordinary admin login');
    const userToken = userLogin.token;
    const actionCalls = async () => ({
      channelDetail: await api('/api/admin/notice/channel/detail', undefined, userToken),
      channelSave: await api('/api/admin/notice/channel/save', { section: 'invalid' }, userToken),
      sceneLists: await api('/api/admin/notice/scene/lists', undefined, userToken),
      sceneDetail: await api(`/api/admin/notice/scene/detail?id=${sceneIdValue}`, undefined, userToken),
      sceneSave: await api('/api/admin/notice/scene/save', {}, userToken),
      logLists: await api('/api/admin/notice/log/lists', undefined, userToken),
      logDetail: await api(`/api/admin/notice/log/detail?id=${logId}`, undefined, userToken),
    });

    const fullInfo = ok(await api('/api/admin/login/info', undefined, userToken), 'full permission info');
    const fullMenus = flattenMenus(fullInfo.menu);
    const requiredPaths = ['/notice/channel', '/notice/template', '/notice/log'];
    const requiredActions = ['notice/channel/save', 'notice/scene/detail', 'notice/scene/save', 'notice/log/detail'];
    if (!requiredPaths.every((item) => fullMenus.some((menu) => menu.paths === item))
      || !requiredActions.every((item) => fullInfo.permissions.includes(item))) {
      throw new Error('granted menu or button permissions missing');
    }
    const grantedCalls = await actionCalls();
    Object.entries(grantedCalls).forEach(([name, payload]) => expectNotDenied(payload, `granted ${name}`));
    summary.checks.permission_granted = true;

    db(`DELETE FROM pa_system_role_menu WHERE role_id=${roleId}`);
    const deniedInfo = ok(await api('/api/admin/login/info', undefined, userToken), 'revoked permission info');
    if (flattenMenus(deniedInfo.menu).some((menu) => requiredPaths.includes(menu.paths))
      || requiredActions.some((item) => deniedInfo.permissions.includes(item))) {
      throw new Error('revoked menu or button permissions remained visible');
    }
    const deniedCalls = await actionCalls();
    Object.entries(deniedCalls).forEach(([name, payload]) => expectDenied(payload, `revoked ${name}`));
    summary.checks.permission_revoked = true;

    db(`INSERT INTO pa_system_role_menu (role_id,menu_id) VALUES ${menuIds.map((id) => `(${roleId},${id})`).join(',')}`);
    const restoredInfo = ok(await api('/api/admin/login/info', undefined, userToken), 'restored permission info');
    if (!requiredPaths.every((item) => flattenMenus(restoredInfo.menu).some((menu) => menu.paths === item))
      || !requiredActions.every((item) => restoredInfo.permissions.includes(item))) {
      throw new Error('restored menu or button permissions missing');
    }
    const restoredCalls = await actionCalls();
    Object.entries(restoredCalls).forEach(([name, payload]) => expectNotDenied(payload, `restored ${name}`));
    summary.checks.permission_restored = true;
    summary.ok = true;
  } finally {
    cleanup(configSnapshot);
    const remaining = Number(scalar(`SELECT
      (SELECT COUNT(*) FROM pa_admin WHERE username IN (${quote(rootName)},${quote(userName)})) +
      (SELECT COUNT(*) FROM pa_system_role WHERE name=${quote(roleName)}) +
      (SELECT COUNT(*) FROM pa_notice_log WHERE receiver=${quote(logReceiver)})`));
    summary.cleanup = remaining === 0 && configSnapshot.every(configMatches);
    if (!summary.cleanup) summary.ok = false;
    fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
    fs.writeFileSync(OUT_FILE, `${JSON.stringify(summary, null, 2)}\n`, 'utf8');
  }

  process.stdout.write(`${JSON.stringify(summary)}\n`);
  if (!summary.ok) process.exitCode = 1;
}

main().catch((error) => {
  process.stdout.write(`${JSON.stringify({ contract: 'M01-channel-permission', ok: false, error: error.message })}\n`);
  process.exitCode = 1;
});
