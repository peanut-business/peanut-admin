'use strict';

const crypto = require('node:crypto');
const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const BASE_URL = 'http://127.0.0.1:8000';
const OUT_FILE = path.join(__dirname, 'api-db-summary.json');
const stamp = Date.now().toString(36).slice(-6);
const prefix = `m01${stamp}`;
const password = 'M01Accept!260801';
const adminName = `${prefix}root`;
const bindAccount = `${prefix}bind`;
const mobileSeed = crypto.randomInt(10_000_000, 99_999_980);
const mobile = (offset) => `139${mobileSeed + offset}`;
const phones = {
  login: mobile(0),
  bind: mobile(1),
  change: mobile(2),
  expired: mobile(3),
  wrong: mobile(4),
  limited: mobile(5),
};
const created = {
  adminIds: new Set(),
  memberIds: new Set(),
  logIds: new Set(),
};

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
  if (result.status !== 0) {
    throw new Error((result.stderr || 'database command failed').trim());
  }
  return (result.stdout || '').trim();
}

function scalar(sql) {
  return db(sql).split(/\r?\n/)[0]?.split('\t')[0] || '';
}

function md5(value) {
  return crypto.createHash('md5').update(value).digest('hex');
}

async function request(route, body = {}, token = '') {
  const response = await fetch(`${BASE_URL}${route}`, {
    method: 'POST',
    headers: {
      'content-type': 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
    },
    body: JSON.stringify(body),
  });
  const payload = await response.json();
  return payload;
}

async function get(route, token = '') {
  const response = await fetch(`${BASE_URL}${route}`, {
    headers: token ? { Authorization: `Bearer ${token}` } : {},
  });
  return response.json();
}

function ok(payload, message) {
  if (Number(payload?.code) !== 20000) {
    throw new Error(`${message}: ${payload?.msg || 'request failed'}`);
  }
  return payload.data;
}

function failed(payload, contains, message) {
  if (Number(payload?.code) === 20000 || !String(payload?.msg || '').includes(contains)) {
    throw new Error(`${message}: ${JSON.stringify(payload)}`);
  }
}

function sceneId(code) {
  return Number(scalar(`SELECT id FROM pa_notice_scene WHERE code=${quote(code)} LIMIT 1`));
}

function addSuccessfulCode(scene, receiver, code, sendTime = Math.floor(Date.now() / 1000)) {
  const id = Number(scalar(`INSERT INTO pa_notice_log
    (template_id,scene_id,channel,provider,receiver,title,content,verify_code,is_verified,check_count,verified_time,status,error,extra,send_time,create_time)
    VALUES
    (0,${sceneId(scene)},1,'fixture',${quote(receiver)},'M01 fixture','code',${quote(code)},0,0,0,1,'','{}',${sendTime},${sendTime});
    SELECT LAST_INSERT_ID()`));
  created.logIds.add(id);
  return id;
}

function cleanup(originalScene) {
  if (originalScene) {
    db(`UPDATE pa_notice_scene SET
      sms_template_id=CONVERT(UNHEX(${quote(originalScene.sms_template_id_hex)}) USING utf8mb4),
      sms_content=CONVERT(UNHEX(${quote(originalScene.sms_content_hex)}) USING utf8mb4),
      sms_status=${originalScene.sms_status},
      update_time=${originalScene.update_time}
      WHERE id=${originalScene.id}`);
  }

  const receivers = Object.values(phones).map(quote).join(',');
  db(`SELECT id FROM pa_member WHERE account=${quote(bindAccount)} OR mobile IN (${receivers})`)
    .split(/\r?\n/).filter(Boolean).map(Number).forEach((id) => created.memberIds.add(id));
  if (created.memberIds.size) {
    const ids = [...created.memberIds].join(',');
    db(`DELETE FROM pa_article_collect WHERE member_id IN (${ids});
        DELETE FROM pa_member_balance_log WHERE member_id IN (${ids});
        DELETE FROM pa_member WHERE id IN (${ids})`);
  }

  db(`SELECT id FROM pa_admin WHERE username=${quote(adminName)}`)
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

  db(`SELECT id FROM pa_notice_log WHERE receiver IN (${receivers})`)
    .split(/\r?\n/).filter(Boolean).map(Number).forEach((id) => created.logIds.add(id));
  if (created.logIds.size) {
    db(`DELETE FROM pa_notice_log WHERE id IN (${[...created.logIds].join(',')})`);
  }
}

async function main() {
  const sceneSnapshot = db(`SELECT id,HEX(COALESCE(sms_template_id,'')),HEX(COALESCE(sms_content,'')),sms_status,update_time
    FROM pa_notice_scene WHERE code='login_code' LIMIT 1`);
  const [sceneIdValue, templateHex, contentHex, sceneStatus, sceneUpdateTime] = sceneSnapshot.split('\t');
  const originalScene = sceneIdValue ? {
    id: Number(sceneIdValue),
    sms_template_id_hex: templateHex,
    sms_content_hex: contentHex,
    sms_status: Number(sceneStatus),
    update_time: Number(sceneUpdateTime),
  } : null;

  const summary = {
    contract: 'M01',
    checks: {},
    cleanup: false,
    ok: false,
  };

  try {
    if (!originalScene) throw new Error('login scene missing before acceptance');
    const receivers = Object.values(phones).map(quote).join(',');
    const collisions = Number(scalar(`SELECT
      (SELECT COUNT(*) FROM pa_admin WHERE username=${quote(adminName)}) +
      (SELECT COUNT(*) FROM pa_member WHERE account=${quote(bindAccount)} OR mobile IN (${receivers})) +
      (SELECT COUNT(*) FROM pa_notice_log WHERE receiver IN (${receivers}))`));
    if (collisions !== 0) throw new Error('fixture natural key collision');

    const salt = 'm01salt1';
    const adminHash = md5(`${md5(password)}${salt}`);
    const adminId = Number(scalar(`INSERT INTO pa_admin
      (username,nickname,password,salt,avatar,root,disable,login_time,login_ip,multipoint_login,create_time,update_time)
      VALUES (${quote(adminName)},'M01验收',${quote(adminHash)},${quote(salt)},'',1,0,0,'',1,UNIX_TIMESTAMP(),UNIX_TIMESTAMP());
      SELECT LAST_INSERT_ID()`));
    created.adminIds.add(adminId);

    const adminLogin = ok(await request('/api/user/login', {
      account: adminName,
      password,
      terminal: 1,
    }), 'admin login');
    const adminToken = adminLogin.token;

    const scenes = ok(await get('/api/admin/notice/scene/lists', adminToken), 'scene list');
    if (scenes.total !== 4) throw new Error('fixed scene count must be four');
    const sceneCodes = scenes.list.map((item) => item.code).sort();
    const expectedCodes = ['bind_mobile', 'change_mobile', 'login_code', 'reset_password'];
    if (JSON.stringify(sceneCodes) !== JSON.stringify(expectedCodes)) throw new Error('fixed scene codes mismatch');
    const loginScene = scenes.list.find((item) => item.code === 'login_code');
    if (!loginScene) throw new Error('login scene missing');
    const sceneDetail = ok(await get(`/api/admin/notice/scene/detail?id=${loginScene.id}`, adminToken), 'scene detail');
    if (sceneDetail.code !== 'login_code' || JSON.stringify(sceneDetail.variables) !== JSON.stringify(['code'])) {
      throw new Error('login scene detail mismatch');
    }
    ok(await request('/api/admin/notice/scene/save', {
      id: loginScene.id,
      sms_template_id: 'M01_TEMPLATE',
      sms_content: '验证码 ${code}',
      sms_status: 1,
    }, adminToken), 'scene save');
    const savedScene = ok(await get(`/api/admin/notice/scene/detail?id=${loginScene.id}`, adminToken), 'saved scene detail');
    if (savedScene.sms_template_id !== 'M01_TEMPLATE' || savedScene.sms_content !== '验证码 ${code}' || Number(savedScene.sms_status) !== 1) {
      throw new Error('scene save did not persist all editable fields');
    }
    summary.checks.scene_management = true;

    addSuccessfulCode('login_code', phones.limited, '7777');
    const countBeforeLimit = Number(scalar(`SELECT COUNT(*) FROM pa_notice_log WHERE receiver=${quote(phones.limited)}`));
    const limited = await request('/api/sms/sendCode', { scene: 'login_code', mobile: phones.limited });
    failed(limited, '1分钟', 'send limit');
    const countAfterLimit = Number(scalar(`SELECT COUNT(*) FROM pa_notice_log WHERE receiver=${quote(phones.limited)}`));
    if (countAfterLimit !== countBeforeLimit) throw new Error('rate limit created an extra log');
    summary.checks.send_limit = true;

    const loginLogId = addSuccessfulCode('login_code', phones.login, '1234');
    const loginResult = ok(await request('/api/login/mobile', {
      mobile: phones.login,
      code: '1234',
    }), 'mobile login');
    if (!loginResult.token || loginResult.mobile !== phones.login) throw new Error('mobile login result invalid');
    created.memberIds.add(Number(scalar(`SELECT id FROM pa_member WHERE mobile=${quote(phones.login)} LIMIT 1`)));
    const reused = await request('/api/login/mobile', { mobile: phones.login, code: '1234' });
    failed(reused, '已使用', 'code single use');
    summary.checks.mobile_login_single_use = true;

    ok(await request('/api/login/register', { account: bindAccount, password }), 'bind user register');
    const bindMemberId = Number(scalar(`SELECT id FROM pa_member WHERE account=${quote(bindAccount)} LIMIT 1`));
    created.memberIds.add(bindMemberId);
    const bindLogin = ok(await request('/api/login/account', { account: bindAccount, password }), 'bind user login');
    const bindLogId = addSuccessfulCode('bind_mobile', phones.bind, '2345');
    ok(await request('/api/user/bindMobile', { mobile: phones.bind, code: '2345' }, bindLogin.token), 'bind mobile');
    const changeLogId = addSuccessfulCode('change_mobile', phones.change, '3456');
    ok(await request('/api/user/bindMobile', { mobile: phones.change, code: '3456' }, bindLogin.token), 'change mobile');
    summary.checks.bind_and_change_mobile = true;

    const resetLogId = addSuccessfulCode('reset_password', phones.change, '4567');
    ok(await request('/api/login/resetPassword', {
      mobile: phones.change,
      code: '4567',
      password: `${password}New`,
    }), 'reset password');
    ok(await request('/api/login/account', {
      account: bindAccount,
      password: `${password}New`,
    }), 'login with reset password');
    summary.checks.reset_password = true;

    const expiredLogId = addSuccessfulCode('login_code', phones.expired, '5678', Math.floor(Date.now() / 1000) - 301);
    const expired = await request('/api/login/mobile', { mobile: phones.expired, code: '5678' });
    failed(expired, '过期', 'expired code');
    const wrongLogId = addSuccessfulCode('login_code', phones.wrong, '6789');
    const wrong = await request('/api/login/mobile', { mobile: phones.wrong, code: '0000' });
    failed(wrong, '不正确', 'wrong code');
    const expiredChecks = Number(scalar(`SELECT check_count FROM pa_notice_log WHERE id=${expiredLogId}`));
    const wrongChecks = Number(scalar(`SELECT check_count FROM pa_notice_log WHERE id=${wrongLogId}`));
    if (expiredChecks !== 1 || wrongChecks !== 1) throw new Error('invalid verification count mismatch');
    summary.checks.expiry_and_check_count = true;

    const persisted = db(`SELECT
      (SELECT COUNT(*) FROM pa_member WHERE mobile=${quote(phones.login)}) AS login_member,
      (SELECT COUNT(*) FROM pa_member WHERE id=${bindMemberId} AND mobile=${quote(phones.change)}) AS changed_member,
      (SELECT SUM(is_verified) FROM pa_notice_log WHERE id IN (${loginLogId},${bindLogId},${changeLogId},${resetLogId})) AS verified_logs`)
      .split('\t').map(Number);
    if (persisted[0] !== 1 || persisted[1] !== 1 || persisted[2] !== 4) {
      throw new Error('member or verification state did not persist');
    }

    const logs = ok(await get(`/api/admin/notice/log/lists?scene_id=${loginScene.id}&receiver=${encodeURIComponent(phones.login)}`, adminToken), 'log list');
    const loginLog = logs.list.find((item) => Number(item.id) === loginLogId);
    if (!loginLog || loginLog.scene_code !== 'login_code' || loginLog.receiver !== phones.login || Number(loginLog.status) !== 1 || Number(loginLog.is_verified) !== 1) {
      throw new Error('scene log list fixture mismatch');
    }
    const logDetail = ok(await get(`/api/admin/notice/log/detail?id=${loginLogId}`, adminToken), 'log detail');
    if (Number(logDetail.id) !== loginLogId || logDetail.receiver !== phones.login || logDetail.scene_code !== 'login_code') {
      throw new Error('scene log detail fixture mismatch');
    }
    summary.checks.log_query = true;
    summary.ok = true;
  } finally {
    cleanup(originalScene);
    const receivers = Object.values(phones).map(quote).join(',');
    const remaining = Number(scalar(`SELECT
      (SELECT COUNT(*) FROM pa_notice_log WHERE receiver IN (${receivers})) +
      (SELECT COUNT(*) FROM pa_member WHERE account=${quote(bindAccount)} OR mobile IN (${receivers})) +
      (SELECT COUNT(*) FROM pa_admin WHERE username=${quote(adminName)})`));
    const restoredScene = db(`SELECT id,HEX(COALESCE(sms_template_id,'')),HEX(COALESCE(sms_content,'')),sms_status,update_time
      FROM pa_notice_scene WHERE code='login_code' LIMIT 1`).split('\t');
    const sceneRestored = originalScene !== null
      && Number(restoredScene[0]) === originalScene.id
      && restoredScene[1] === originalScene.sms_template_id_hex
      && restoredScene[2] === originalScene.sms_content_hex
      && Number(restoredScene[3]) === originalScene.sms_status
      && Number(restoredScene[4]) === originalScene.update_time;
    summary.cleanup = remaining === 0 && sceneRestored;
    if (!summary.cleanup) summary.ok = false;
    fs.mkdirSync(path.dirname(OUT_FILE), { recursive: true });
    fs.writeFileSync(OUT_FILE, `${JSON.stringify(summary, null, 2)}\n`, 'utf8');
  }

  process.stdout.write(`${JSON.stringify(summary)}\n`);
  if (!summary.ok) process.exitCode = 1;
}

main().catch((error) => {
  process.stdout.write(`${JSON.stringify({ contract: 'M01', ok: false, error: error.message })}\n`);
  process.exitCode = 1;
});
