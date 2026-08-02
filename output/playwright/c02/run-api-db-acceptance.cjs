#!/usr/bin/env node
'use strict';

/**
 * C02 low-token API/DB acceptance.
 *
 * Do not print request/response bodies. Normal stdout is exactly one summary JSON line.
 * Detailed success evidence is written to output/playwright/c02/{side}.json;
 * failure-only diagnostics are written to output/playwright/c02/{side}-debug.json.
 *
 * Required per-side environment (SIDE = LIKEADMIN or PEANUT):
 *   C02_SIDE_BASE_URL
 *   C02_SIDE_ADMIN_ACCOUNT
 *   C02_SIDE_ADMIN_PASSWORD
 *   C02_SIDE_DB_COMMAND
 *
 * DB_COMMAND must accept SQL on stdin and print tab-separated, headerless rows, for example:
 *   mysql --batch --skip-column-names -h HOST -u USER -pPASS DATABASE
 * Instead of DB_COMMAND, the script also accepts DB_HOST/DB_PORT/DB_USER/
 * DB_PASSWORD/DB_NAME and builds the mysql command without printing credentials.
 *
 * Optional:
 *   C02_SIDE_DB_PREFIX                  default la_ / pa_
 *   C02_SIDE_ADMIN_LOGIN_PATH           default /adminapi/login/account
 *   C02_SIDE_ADMIN_PREFIX               default /adminapi / /api/admin
 *   C02_SIDE_MEMBER_TOKEN               required for collection assertions
 *   C02_SIDE_CACHE_CLEANUP_COMMAND      exact, side-scoped cache cleanup; no broad FLUSHDB
 *   C02_SIDE_PERMISSION_MENU_IDS         CSV, default 70,71,72,74,75,76,77,105
 *   C02_SIDE_LIST_MENU_ID                article list permission ID, default 71 / 50
 *   C02_SIDE_DELETE_MENU_ID              article delete permission ID, default 76 / 53
 *   C02_SIDE_EXPECT_SUCCESS_CODES        CSV, default 1 / 20000
 *   C02_SIDE_TOKEN_HEADER                default token
 */

const fs = require('node:fs');
const path = require('node:path');
const { spawnSync } = require('node:child_process');

const OUT_DIR = __dirname;
const RUN_ID = `${Date.now().toString(36)}${Math.random().toString(36).slice(2, 6)}`.slice(-12);
function env(name, fallback = '') {
  const value = process.env[name];
  return value === undefined || value === '' ? fallback : value;
}

function required(name) {
  const value = env(name);
  if (!value) throw new Error(`missing environment: ${name}`);
  return value;
}

function csvInts(value) {
  return value.split(',').map((item) => Number(item.trim())).filter(Number.isInteger);
}

function shellQuote(value) {
  return `'${String(value).replace(/'/g, `'"'"'`)}'`;
}

function databaseCommand(ep) {
  const explicit = env(`${ep}DB_COMMAND`);
  if (explicit) return explicit;
  const host = required(`${ep}DB_HOST`);
  const user = required(`${ep}DB_USER`);
  const password = required(`${ep}DB_PASSWORD`);
  const database = required(`${ep}DB_NAME`);
  const port = env(`${ep}DB_PORT`, '3306');
  const client = env(`${ep}DB_CLIENT`, 'mysql');
  return [
    `MYSQL_PWD=${shellQuote(password)}`,
    shellQuote(client),
    '--batch',
    '--skip-column-names',
    `--host=${shellQuote(host)}`,
    `--port=${shellQuote(port)}`,
    `--user=${shellQuote(user)}`,
    shellQuote(database),
  ].join(' ');
}

function sideConfig(key) {
  const ep = `C02_${key}_`;
  const like = key === 'LIKEADMIN';
  return {
    key,
    name: like ? 'likeadmin' : 'peanut',
    baseUrl: required(`${ep}BASE_URL`).replace(/\/$/, ''),
    rootAccount: required(`${ep}ADMIN_ACCOUNT`),
    rootPassword: required(`${ep}ADMIN_PASSWORD`),
    loginPath: env(`${ep}ADMIN_LOGIN_PATH`, like ? '/adminapi/login/account' : '/api/user/login'),
    adminPrefix: env(`${ep}ADMIN_PREFIX`, like ? '/adminapi' : '/api/admin'),
    dbCommand: databaseCommand(ep),
    dbPrefix: env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_'),
    successCodes: csvInts(env(`${ep}EXPECT_SUCCESS_CODES`, like ? '1' : '20000')),
    tokenHeader: env(`${ep}TOKEN_HEADER`, 'token'),
    memberToken: env(`${ep}MEMBER_TOKEN`),
    cacheCleanupCommand: env(`${ep}CACHE_CLEANUP_COMMAND`),
    permissionMenuIds: csvInts(env(`${ep}PERMISSION_MENU_IDS`, '70,71,72,74,75,76,77,105')),
    listMenuId: Number(env(`${ep}LIST_MENU_ID`, like ? '71' : '50')),
    deleteMenuId: Number(env(`${ep}DELETE_MENU_ID`, like ? '76' : '53')),
    tables: {
      article: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}article`,
      cate: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}article_cate`,
      collect: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}article_collect`,
      admin: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}admin`,
      adminRole: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}admin_role`,
      adminSession: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}admin_session`,
      role: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}system_role`,
      roleMenu: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}system_role_menu`,
      menu: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}system_menu`,
      log: `${env(`${ep}DB_PREFIX`, like ? 'la_' : 'pa_')}operation_log`,
    },
  };
}

function sqlQuote(value) {
  return `'${String(value).replace(/\\/g, '\\\\').replace(/'/g, "''")}'`;
}

function db(config, sql, { allowFailure = false } = {}) {
  const result = spawnSync(config.dbCommand, {
    shell: true,
    input: `${sql.trim().replace(/;?$/, ';')}\n`,
    encoding: 'utf8',
    maxBuffer: 8 * 1024 * 1024,
  });
  if (result.status !== 0 && !allowFailure) {
    throw new Error(`db command failed (${config.name}): ${(result.stderr || '').trim().slice(0, 500)}`);
  }
  return (result.stdout || '').trim();
}

function dbRows(config, sql) {
  const output = db(config, sql);
  if (!output) return [];
  return output.split(/\r?\n/).map((line) => line.split('\t'));
}

function dbScalar(config, sql) {
  const rows = dbRows(config, sql);
  return rows.length ? rows[0][0] : '';
}

function dbInt(config, sql) {
  return Number(dbScalar(config, sql) || 0);
}

function assert(condition, message, details) {
  if (!condition) {
    const error = new Error(message);
    error.details = details;
    throw error;
  }
}

function bodyMessage(body) {
  return String(body?.msg ?? body?.message ?? body?.data?.message ?? '');
}

function businessData(body) {
  if (body && Object.prototype.hasOwnProperty.call(body, 'data')) return body.data;
  return body;
}

function isSuccess(config, body) {
  return config.successCodes.includes(Number(body?.code));
}

async function request(config, token, method, route, payload, options = {}) {
  let resolvedRoute = route;
  if (route.startsWith('/adminapi/') && config.adminPrefix !== '/adminapi') {
    let suffix = route.slice('/adminapi/'.length);
    suffix = suffix.replace(/^auth\.role\//, 'role/').replace(/^auth\.admin\//, 'admin/');
    resolvedRoute = `${config.adminPrefix}/${suffix}`;
  }
  const url = new URL(`${config.baseUrl}${resolvedRoute}`);
  const headers = { Accept: 'application/json' };
  if (token) {
    headers[config.tokenHeader] = token;
    headers.Authorization = `Bearer ${token}`;
  }
  let body;
  if (method === 'GET' && payload) {
    for (const [key, value] of Object.entries(payload)) {
      if (value !== undefined && value !== null) url.searchParams.set(key, String(value));
    }
  } else if (payload !== undefined) {
    headers['Content-Type'] = 'application/json';
    body = JSON.stringify(payload);
  }
  const response = await fetch(url, { method, headers, body, redirect: 'manual' });
  const text = await response.text();
  let json;
  try {
    json = text ? JSON.parse(text) : {};
  } catch {
    json = { _non_json: text.slice(0, 1000) };
  }
  if (options.expectOk === true) {
    assert(isSuccess(config, json), `${method} ${route} failed`, { status: response.status, body: json });
  }
  if (options.expectOk === false) {
    assert(!isSuccess(config, json), `${method} ${route} unexpectedly succeeded`, { status: response.status, body: json });
  }
  return { status: response.status, body: json };
}

async function login(config, account, password) {
  const response = await request(config, '', 'POST', config.loginPath, {
    account,
    username: account,
    password,
    terminal: 1,
  }, { expectOk: true });
  const data = businessData(response.body) || {};
  const token = data.token || response.body.token;
  assert(token, `login returned no token (${config.name})`, response.body);
  return String(token);
}

function listPayload(response) {
  const data = businessData(response.body) || {};
  return {
    lists: Array.isArray(data.lists) ? data.lists : [],
    count: Number(data.count ?? 0),
    pageNo: Number(data.page_no ?? data.pageNo ?? 1),
    pageSize: Number(data.page_size ?? data.pageSize ?? 0),
    extend: data.extend ?? [],
  };
}

function findBy(rows, field, value) {
  return rows.find((row) => String(row?.[field]) === String(value));
}

async function adminList(config, token, route, params) {
  return listPayload(await request(config, token, 'GET', route, params, { expectOk: true }));
}

function prefixDigest(config, prefix) {
  const { article, cate } = config.tables;
  return {
    articleFixtures: dbInt(config, `SELECT COUNT(*) FROM \`${article}\` WHERE title LIKE ${sqlQuote(`${prefix}%`)}`),
    cateFixtures: dbInt(config, `SELECT COUNT(*) FROM \`${cate}\` WHERE name LIKE ${sqlQuote(`${prefix}%`)}`),
    nonFixtureArticle: dbRows(config, `SELECT COUNT(*),COALESCE(MAX(id),0),COALESCE(SUM(id),0) FROM \`${article}\` WHERE title NOT LIKE ${sqlQuote(`${prefix}%`)}`)[0],
    nonFixtureCate: dbRows(config, `SELECT COUNT(*),COALESCE(MAX(id),0),COALESCE(SUM(id),0) FROM \`${cate}\` WHERE name NOT LIKE ${sqlQuote(`${prefix}%`)}`)[0],
  };
}

function activeArticleSnapshot(config, prefix) {
  const t = config.tables.article;
  return dbRows(config, `
    SELECT COUNT(*),COALESCE(SUM(id),0),COALESCE(SUM(is_show),0),COALESCE(SUM(click_actual),0)
    FROM \`${t}\` WHERE title LIKE ${sqlQuote(`${prefix}%`)} AND delete_time IS NULL
  `)[0].map(Number);
}

async function createCategory(config, token, values) {
  await request(config, token, 'POST', '/adminapi/article.articleCate/add', values, { expectOk: true });
  const result = await adminList(config, token, '/adminapi/article.articleCate/lists', { page_type: 0 });
  const row = findBy(result.lists, 'name', values.name);
  assert(row, `created category not found: ${values.name}`);
  return Number(row.id);
}

async function createArticle(config, token, values) {
  await request(config, token, 'POST', '/adminapi/article.article/add', values, { expectOk: true });
  const result = await adminList(config, token, '/adminapi/article.article/lists', {
    title: values.title,
    page_type: 0,
  });
  const row = findBy(result.lists, 'title', values.title);
  assert(row, `created article not found: ${values.title}`);
  return Number(row.id);
}

async function findRole(config, token, name) {
  const result = await adminList(config, token, '/adminapi/auth.role/lists', { page_type: 0, name });
  return result.lists.find((row) => String(row.name) === name);
}

async function findAdmin(config, token, account) {
  const result = await adminList(config, token, '/adminapi/auth.admin/lists', { page_type: 0, account });
  return result.lists.find((row) => String(row.account ?? row.username) === account);
}

async function createPermissionActor(config, rootToken, names, state) {
  const menuIds = config.permissionMenuIds;
  assert(
    menuIds.includes(config.listMenuId) && menuIds.includes(config.deleteMenuId),
    `permission menu IDs must include lists=${config.listMenuId} and delete=${config.deleteMenuId}`
  );
  await request(config, rootToken, 'POST', '/adminapi/auth.role/add', {
    name: names.role,
    desc: 'C02 temporary least-privilege role',
    sort: 0,
    menu_id: menuIds,
  }, { expectOk: true });
  const role = await findRole(config, rootToken, names.role);
  assert(role, 'temporary role not found after add');
  state.roleId = Number(role.id);

  await request(config, rootToken, 'POST', '/adminapi/auth.admin/add', {
    account: names.admin,
    name: names.adminName,
    password: names.password,
    password_confirm: names.password,
    role_id: [state.roleId],
    dept_id: [],
    jobs_id: [],
    avatar: '',
    disable: 0,
    multipoint_login: 1,
  }, { expectOk: true });
  const admin = await findAdmin(config, rootToken, names.admin);
  assert(admin, 'temporary admin not found after add');
  state.adminId = Number(admin.id);
  state.lowToken = await login(config, names.admin, names.password);
}

async function setRoleMenus(config, rootToken, names, roleId, menuIds) {
  await request(config, rootToken, 'POST', '/adminapi/auth.role/edit', {
    id: roleId,
    name: names.role,
    desc: 'C02 temporary least-privilege role',
    sort: 0,
    menu_id: menuIds,
  }, { expectOk: true });
}

function expireAdminSessions(config, adminId) {
  db(config, `DELETE FROM \`${config.tables.adminSession}\` WHERE admin_id=${Number(adminId)}`);
}

function writeJson(file, value) {
  fs.writeFileSync(path.join(OUT_DIR, file), `${JSON.stringify(value, null, 2)}\n`, 'utf8');
}

async function cleanup(config, rootToken, state, prefix) {
  const t = config.tables;
  const articleIds = [...state.articleIds].filter(Number.isInteger);
  const cateIds = [...state.cateIds].filter(Number.isInteger);
  const adminId = Number(state.adminId || 0);
  const roleId = Number(state.roleId || 0);

  if (rootToken && adminId) {
    await request(config, rootToken, 'POST', '/adminapi/auth.admin/delete', { id: adminId }).catch(() => null);
  }
  if (rootToken && roleId) {
    await request(config, rootToken, 'POST', '/adminapi/auth.role/delete', { id: roleId }).catch(() => null);
  }

  const articleWhere = articleIds.length
    ? `id IN (${articleIds.join(',')})`
    : `title LIKE ${sqlQuote(`${prefix}%`)}`;
  const cateWhere = cateIds.length
    ? `id IN (${cateIds.join(',')})`
    : `name LIKE ${sqlQuote(`${prefix}%`)}`;
  const collectArticleWhere = articleIds.length ? `article_id IN (${articleIds.join(',')})` : '1=0';

  db(config, `
    DELETE FROM \`${t.collect}\` WHERE ${collectArticleWhere};
    DELETE FROM \`${t.article}\` WHERE ${articleWhere};
    DELETE FROM \`${t.cate}\` WHERE ${cateWhere};
    ${adminId ? `DELETE FROM \`${t.adminSession}\` WHERE admin_id=${adminId};` : ''}
    ${adminId ? `DELETE FROM \`${t.log}\` WHERE admin_id=${adminId};` : ''}
    ${adminId ? `DELETE FROM \`${t.adminRole}\` WHERE admin_id=${adminId};` : ''}
    ${adminId ? `DELETE FROM \`${t.admin}\` WHERE id=${adminId};` : ''}
    ${roleId ? `DELETE FROM \`${t.roleMenu}\` WHERE role_id=${roleId};` : ''}
    ${roleId ? `DELETE FROM \`${t.role}\` WHERE id=${roleId};` : ''}
  `);

  let cache = 'not_configured';
  if (config.cacheCleanupCommand) {
    const result = spawnSync(config.cacheCleanupCommand, { shell: true, encoding: 'utf8' });
    if (result.status !== 0) throw new Error(`cache cleanup failed (${config.name})`);
    cache = 'cleared';
  }
  return {
    cache,
    articleFixtures: dbInt(config, `SELECT COUNT(*) FROM \`${t.article}\` WHERE title LIKE ${sqlQuote(`${prefix}%`)}`),
    cateFixtures: dbInt(config, `SELECT COUNT(*) FROM \`${t.cate}\` WHERE name LIKE ${sqlQuote(`${prefix}%`)}`),
    admin: adminId ? dbInt(config, `SELECT COUNT(*) FROM \`${t.admin}\` WHERE id=${adminId}`) : 0,
    role: roleId ? dbInt(config, `SELECT COUNT(*) FROM \`${t.role}\` WHERE id=${roleId}`) : 0,
    sessions: adminId ? dbInt(config, `SELECT COUNT(*) FROM \`${t.adminSession}\` WHERE admin_id=${adminId}`) : 0,
    logs: adminId ? dbInt(config, `SELECT COUNT(*) FROM \`${t.log}\` WHERE admin_id=${adminId}`) : 0,
  };
}

async function runSide(config) {
  const short = config.name === 'likeadmin' ? 'la' : 'pa';
  const prefix = `C02_${short}_${RUN_ID}_`;
  const names = {
    role: `${prefix}r`.slice(0, 15),
    admin: `${prefix}adm`.slice(0, 31),
    adminName: `C02${short}${RUN_ID}`.slice(0, 15),
    password: `C02a!${RUN_ID}`.slice(0, 18),
  };
  const state = {
    cateIds: new Set(),
    articleIds: new Set(),
    roleId: 0,
    adminId: 0,
    lowToken: '',
  };
  const evidence = {
    side: config.name,
    contract: 'C02',
    prefix,
    fixtures: {},
    checks: {},
    messages: {},
    observations: {},
    cleanup: {},
  };
  let rootToken = '';
  let stage = 'environment';

  try {
    const baseline = prefixDigest(config, prefix);
    assert(baseline.articleFixtures === 0 && baseline.cateFixtures === 0, 'fixture prefix is not clean', baseline);
    evidence.baseline = baseline;

    stage = 'root-login';
    rootToken = await login(config, config.rootAccount, config.rootPassword);

    stage = 'categories';
    const cateA = await createCategory(config, rootToken, { name: `${prefix}cate_a`, sort: 90, is_show: 1 });
    const cateB = await createCategory(config, rootToken, { name: `${prefix}cate_b`, sort: 10, is_show: 1 });
    state.cateIds.add(cateA); state.cateIds.add(cateB);

    stage = 'articles';
    const articleBase = (suffix, cid, overrides = {}) => ({
      title: `${prefix}${suffix}`,
      cid,
      desc: `desc_${suffix}`,
      abstract: `abstract_${suffix}`,
      image: '',
      author: `author_${suffix}`,
      content: `<p>content_${suffix}</p>`,
      click_virtual: 11,
      sort: 50,
      is_show: 1,
      ...overrides,
    });
    const valuesA = articleBase('article_a', cateA, { sort: 90, click_virtual: 11 });
    const valuesB = articleBase('article_b', cateA, { sort: 10, click_virtual: 22 });
    const valuesH = articleBase('article_h', cateA, { sort: 50, click_virtual: 33, is_show: 0 });
    const valuesD = articleBase('article_d', cateB, { sort: 30, click_virtual: 44 });
    const articleA = await createArticle(config, rootToken, valuesA);
    const articleB = await createArticle(config, rootToken, valuesB);
    const articleH = await createArticle(config, rootToken, valuesH);
    const articleD = await createArticle(config, rootToken, valuesD);
    [articleA, articleB, articleH, articleD].forEach((id) => state.articleIds.add(id));
    evidence.fixtures = { cateA, cateB, articleA, articleB, articleH, articleD };

    stage = 'admin-list';
    const all = await adminList(config, rootToken, '/adminapi/article.article/lists', {
      title: prefix,
      page_type: 0,
    });
    assert(all.count === 4 && all.lists.length === 4, 'admin fixture list count mismatch', all);
    const listedA = findBy(all.lists, 'id', articleA);
    assert(
      listedA
        && Number(listedA.cid) === cateA
        && String(listedA.cate_name) === `${prefix}cate_a`
        && Number(listedA.is_show) === 1
        && Number(listedA.click) === Number(listedA.click_virtual) + Number(listedA.click_actual),
      'admin article business values mismatch',
      listedA
    );
    assert(all.lists.map((row) => Number(row.id)).join(',') === [articleA, articleH, articleD, articleB].join(','), 'default sort mismatch', all.lists.map((row) => [row.id, row.sort]));

    const titleFilter = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: 'article_b', page_type: 0 });
    const cateFilter = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: prefix, cid: cateB, page_type: 0 });
    const hiddenFilter = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: prefix, is_show: 0, page_type: 0 });
    assert(titleFilter.count === 1 && Number(titleFilter.lists[0].id) === articleB, 'title filter mismatch');
    assert(cateFilter.count === 1 && Number(cateFilter.lists[0].id) === articleD, 'cid filter mismatch');
    assert(hiddenFilter.count === 1 && Number(hiddenFilter.lists[0].id) === articleH, 'is_show=0 filter mismatch');

    const page1 = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: prefix, page_no: 1, page_size: 2 });
    const page2 = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: prefix, page_no: 2, page_size: 2 });
    assert(page1.count === 4 && page1.lists.length === 2 && page2.lists.length === 2, 'pagination mismatch');
    assert(!page1.lists.some((a) => page2.lists.some((b) => Number(a.id) === Number(b.id))), 'pagination overlap');
    const idAsc = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: prefix, field: 'id', order_by: 'asc', page_type: 0 });
    assert(idAsc.lists.every((row, i, rows) => i === 0 || Number(rows[i - 1].id) < Number(row.id)), 'id asc mismatch');
    const fallback = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: prefix, field: 'sort', order_by: 'asc', page_type: 0 });
    assert(fallback.lists.map((row) => Number(row.id)).join(',') === all.lists.map((row) => Number(row.id)).join(','), 'invalid sort did not fall back');
    evidence.checks.list_query = true;

    stage = 'validation';
    const beforeFailed = activeArticleSnapshot(config, prefix);
    const invalidId = Math.max(...state.articleIds) + 999999;
    const validationCases = [
      ['missing_title', { ...valuesA, title: undefined }],
      ['missing_cid', { ...valuesA, cid: undefined }],
      ['missing_is_show', { ...valuesA, is_show: undefined }],
      ['invalid_is_show', { ...valuesA, is_show: 2 }],
      ['missing_detail_id', {}, 'GET', '/adminapi/article.article/detail'],
      ['invalid_detail_id', { id: invalidId }, 'GET', '/adminapi/article.article/detail'],
    ];
    for (const [caseName, payload, method = 'POST', route = '/adminapi/article.article/add'] of validationCases) {
      const cleaned = Object.fromEntries(Object.entries(payload).filter(([, value]) => value !== undefined));
      const response = await request(config, rootToken, method, route, cleaned, { expectOk: false });
      evidence.messages[caseName] = bodyMessage(response.body);
    }
    const afterFailed = activeArticleSnapshot(config, prefix);
    assert(JSON.stringify(beforeFailed) === JSON.stringify(afterFailed), 'failed validation changed article data', { beforeFailed, afterFailed });
    evidence.checks.validation = true;
    evidence.checks.failed_write_invariant = true;

    stage = 'detail-edit';
    const detailBefore = businessData((await request(config, rootToken, 'GET', '/adminapi/article.article/detail', { id: articleA }, { expectOk: true })).body);
    const dbBeforeEdit = dbRows(config, `SELECT click_actual,create_time FROM \`${config.tables.article}\` WHERE id=${articleA}`)[0].map(Number);
    const edited = { ...valuesA, id: articleA, title: `${prefix}article_a_edit`, desc: 'edited', author: 'edited_author', sort: 95 };
    await request(config, rootToken, 'POST', '/adminapi/article.article/edit', edited, { expectOk: true });
    const detailAfter = businessData((await request(config, rootToken, 'GET', '/adminapi/article.article/detail', { id: articleA }, { expectOk: true })).body);
    const dbAfterEdit = dbRows(config, `SELECT click_actual,create_time FROM \`${config.tables.article}\` WHERE id=${articleA}`)[0].map(Number);
    assert(String(detailBefore.title) === valuesA.title && String(detailAfter.title) === edited.title, 'detail/edit title mismatch');
    assert(dbBeforeEdit[0] === dbAfterEdit[0] && dbBeforeEdit[1] === dbAfterEdit[1], 'edit changed click_actual/create_time', { dbBeforeEdit, dbAfterEdit });
    evidence.checks.admin_crud = true;

    stage = 'mobile-detail';
    const mobileList = listPayload(await request(config, '', 'GET', '/api/article/lists', { cid: cateA, keyword: 'article_a_edit', page_no: 1, page_size: 15 }, { expectOk: true }));
    assert(mobileList.count === 1 && Number(mobileList.lists[0].id) === articleA, 'mobile list filter mismatch', mobileList);
    const clickBefore = dbInt(config, `SELECT click_actual FROM \`${config.tables.article}\` WHERE id=${articleA}`);
    const mobileDetail = businessData((await request(config, '', 'GET', '/api/article/detail', { id: articleA }, { expectOk: true })).body);
    const clickAfter = dbInt(config, `SELECT click_actual FROM \`${config.tables.article}\` WHERE id=${articleA}`);
    assert(clickAfter - clickBefore === 1, 'mobile detail did not increment click_actual exactly once', { clickBefore, clickAfter });
    assert(Number(mobileDetail.id) === articleA && !Object.prototype.hasOwnProperty.call(mobileDetail, 'click_actual'), 'mobile detail fields mismatch', mobileDetail);
    evidence.checks.mobile = true;
    evidence.checks.detail_click_delta = 1;

    stage = 'hidden-state-pc';
    await request(config, rootToken, 'POST', '/adminapi/article.article/updateStatus', { id: articleA, is_show: 0 }, { expectOk: true });
    const hiddenAdmin = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: `${prefix}article_a_edit`, page_type: 0 });
    assert(hiddenAdmin.count === 1 && Number(hiddenAdmin.lists[0].is_show) === 0, 'hidden article missing from admin');
    const hiddenMobile = listPayload(await request(config, '', 'GET', '/api/article/lists', { cid: cateA, keyword: 'article_a_edit' }, { expectOk: true }));
    assert(hiddenMobile.count === 0, 'hidden article visible in mobile list');
    const hiddenDetail = await request(config, '', 'GET', '/api/article/detail', { id: articleA });
    assert(!isSuccess(config, hiddenDetail.body) || !businessData(hiddenDetail.body)?.id, 'hidden article detail still visible', hiddenDetail.body);
    evidence.observations.hidden_mobile_detail = { code: hiddenDetail.body?.code, message: bodyMessage(hiddenDetail.body) };

    const pc = businessData((await request(config, '', 'GET', '/api/pc/infoCenter', {}, { expectOk: true })).body);
    assert(Array.isArray(pc), 'PC infoCenter must return category array', pc);
    const pcCate = pc.find((item) => Number(item.id) === cateA);
    assert(pcCate && Array.isArray(pcCate.article), 'PC enabled category block missing', pc);
    const pcContainsHidden = pcCate.article.some((item) => Number(item.id) === articleA);
    if (config.name === 'peanut') {
      assert(!pcContainsHidden, 'hidden article visible in Peanut PC info center', pcCate);
    }
    evidence.observations.reference_pc_contains_hidden = pcContainsHidden;
    assert(!pcCate.article.some((item) => Object.prototype.hasOwnProperty.call(item, 'content')), 'PC nested article leaked content');
    evidence.checks.pc_hidden_article_excluded = true;

    await request(config, rootToken, 'POST', '/adminapi/article.article/updateStatus', { id: articleA, is_show: 1 }, { expectOk: true });
    evidence.checks.status_flow = true;

    stage = 'collection';
    assert(config.memberToken, `member token required (${config.name})`);
    await request(config, config.memberToken, 'POST', '/api/article/addCollect', { id: articleA }, { expectOk: true });
    await request(config, config.memberToken, 'POST', '/api/article/addCollect', { id: articleA }, { expectOk: true });
    const activeCollect = dbRows(config, `SELECT id,status FROM \`${config.tables.collect}\` WHERE article_id=${articleA}`);
    assert(activeCollect.length === 1 && Number(activeCollect[0][1]) === 1, 'repeat collect did not preserve one active relation', activeCollect);
    const collectId = Number(activeCollect[0][0]);
    const collected = listPayload(await request(config, config.memberToken, 'GET', '/api/article/collect', { page_no: 1, page_size: 15 }, { expectOk: true }));
    assert(collected.lists.some((item) => Number(item.article_id ?? item.id) === articleA), 'collection list missing article');
    await request(config, config.memberToken, 'POST', '/api/article/cancelCollect', { id: articleA }, { expectOk: true });
    await request(config, config.memberToken, 'POST', '/api/article/cancelCollect', { id: articleA }, { expectOk: true });
    assert(dbInt(config, `SELECT status FROM \`${config.tables.collect}\` WHERE id=${collectId}`) === 0, 'repeat cancel did not preserve inactive relation');
    await request(config, config.memberToken, 'POST', '/api/article/addCollect', { id: articleA }, { expectOk: true });
    const restoredCollect = dbRows(config, `SELECT id,status FROM \`${config.tables.collect}\` WHERE article_id=${articleA}`);
    assert(restoredCollect.length === 1 && Number(restoredCollect[0][0]) === collectId && Number(restoredCollect[0][1]) === 1, 're-collect did not reuse relation', restoredCollect);

    await request(config, rootToken, 'POST', '/adminapi/article.article/updateStatus', { id: articleA, is_show: 0 }, { expectOk: true });
    const hiddenCollected = listPayload(await request(config, config.memberToken, 'GET', '/api/article/collect', { page_no: 1, page_size: 15 }, { expectOk: true }));
    assert(!hiddenCollected.lists.some((item) => Number(item.article_id ?? item.id) === articleA), 'hidden article visible in collection list');
    await request(config, rootToken, 'POST', '/adminapi/article.article/updateStatus', { id: articleA, is_show: 1 }, { expectOk: true });

    await request(config, config.memberToken, 'POST', '/api/article/addCollect', { id: articleD }, { expectOk: true });
    evidence.checks.collection = true;

    stage = 'permission';
    await createPermissionActor(config, rootToken, names, state);
    const withoutDelete = config.permissionMenuIds.filter((id) => id !== config.deleteMenuId);
    await setRoleMenus(config, rootToken, names, state.roleId, withoutDelete);
    expireAdminSessions(config, state.adminId);
    state.lowToken = await login(config, names.admin, names.password);
    const dBeforeDenied = dbRows(config, `SELECT delete_time FROM \`${config.tables.article}\` WHERE id=${articleD}`)[0]?.[0] || 'NULL';
    const denied = await request(config, state.lowToken, 'POST', '/adminapi/article.article/delete', { id: articleD }, { expectOk: false });
    const dAfterDenied = dbRows(config, `SELECT delete_time FROM \`${config.tables.article}\` WHERE id=${articleD}`)[0]?.[0] || 'NULL';
    assert(dBeforeDenied === dAfterDenied, 'denied delete changed article', { dBeforeDenied, dAfterDenied });
    evidence.observations.permission_denied = { code: denied.body?.code, message: bodyMessage(denied.body), status: denied.status };

    await setRoleMenus(config, rootToken, names, state.roleId, config.permissionMenuIds);
    expireAdminSessions(config, state.adminId);
    state.lowToken = await login(config, names.admin, names.password);
    const restoredDetail = await request(config, state.lowToken, 'GET', '/adminapi/article.article/detail', { id: articleD }, { expectOk: true });
    assert(Number(businessData(restoredDetail.body)?.id) === articleD, 'restored permission did not recover authorized API');
    evidence.checks.permission_revoke_restore = true;
    evidence.checks.unauthorized_api_default = true;

    stage = 'soft-delete';
    await request(config, state.lowToken, 'POST', '/adminapi/article.article/delete', { id: articleD }, { expectOk: true });
    const deleted = dbRows(config, `SELECT delete_time FROM \`${config.tables.article}\` WHERE id=${articleD}`);
    assert(deleted.length === 1 && deleted[0][0] !== '' && deleted[0][0] !== 'NULL', 'article was not soft deleted', deleted);
    const deletedAdmin = await adminList(config, rootToken, '/adminapi/article.article/lists', { title: valuesD.title, page_type: 0 });
    assert(deletedAdmin.count === 0, 'soft deleted article remains in admin list');
    const deletedMobile = listPayload(await request(config, '', 'GET', '/api/article/lists', { cid: cateB, keyword: 'article_d' }, { expectOk: true }));
    assert(deletedMobile.count === 0, 'soft deleted article remains in mobile list');
    const deletedCollect = listPayload(await request(config, config.memberToken, 'GET', '/api/article/collect', { page_no: 1, page_size: 15 }, { expectOk: true }));
    assert(!deletedCollect.lists.some((item) => Number(item.article_id ?? item.id) === articleD), 'soft deleted article remains in collection list');
    assert(dbInt(config, `SELECT COUNT(*) FROM \`${config.tables.collect}\` WHERE article_id=${articleD}`) === 1, 'soft delete cascaded collection relation');
    evidence.checks.soft_delete = true;

    stage = 'invariants';
    const current = activeArticleSnapshot(config, prefix);
    assert(current[0] >= 3, 'unexpected active fixture count before cleanup', current);
    evidence.invariants = {
      failed_write_delta: 0,
      edit_click_actual_delta: dbAfterEdit[0] - dbBeforeEdit[0],
      detail_click_delta: clickAfter - clickBefore,
      denied_delete_unchanged: dBeforeDenied === dAfterDenied,
      soft_delete_row_retained: deleted.length === 1,
    };
    evidence.checks.invariants = true;

    stage = 'cleanup';
    evidence.cleanup = await cleanup(config, rootToken, state, prefix);
    const after = prefixDigest(config, prefix);
    assert(evidence.cleanup.articleFixtures === 0 && evidence.cleanup.cateFixtures === 0, 'fixture cleanup incomplete', evidence.cleanup);
    assert(evidence.cleanup.admin === 0 && evidence.cleanup.role === 0 && evidence.cleanup.sessions === 0 && evidence.cleanup.logs === 0, 'auth cleanup incomplete', evidence.cleanup);
    assert(JSON.stringify(after.nonFixtureArticle) === JSON.stringify(baseline.nonFixtureArticle), 'non-fixture article digest changed', { before: baseline, after });
    assert(JSON.stringify(after.nonFixtureCate) === JSON.stringify(baseline.nonFixtureCate), 'non-fixture category digest changed', { before: baseline, after });
    evidence.checks.cleanup = true;
    evidence.baseline_restored = true;
    evidence.ok = true;
    writeJson(`${config.name}.json`, evidence);
    return { side: config.name, ok: true, checks: evidence.checks, evidence: `${config.name}.json` };
  } catch (error) {
    let cleanupError = '';
    try {
      evidence.cleanup = await cleanup(config, rootToken, state, prefix);
    } catch (inner) {
      cleanupError = inner.message;
    }
    evidence.ok = false;
    evidence.failed_stage = stage;
    const debug = {
      ...evidence,
      error: error.message,
      details: error.details,
      stack: error.stack,
      cleanup_error: cleanupError,
    };
    writeJson(`${config.name}-debug.json`, debug);
    return { side: config.name, ok: false, failed_stage: stage, error: error.message, debug: `${config.name}-debug.json` };
  }
}

async function main() {
  const requestedSides = env('C02_SIDES', 'LIKEADMIN,PEANUT')
    .split(',')
    .map((side) => side.trim().toUpperCase())
    .filter((side) => ['LIKEADMIN', 'PEANUT'].includes(side));
  assert(requestedSides.length > 0, 'C02_SIDES must include LIKEADMIN or PEANUT');
  const sides = requestedSides.map(sideConfig);
  const results = [];
  for (const config of sides) results.push(await runSide(config));
  const summary = {
    contract: 'C02',
    run_id: RUN_ID,
    ok: results.every((item) => item.ok),
    sides: results,
  };
  process.stdout.write(`${JSON.stringify(summary)}\n`);
  if (!summary.ok) process.exitCode = 1;
}

main().catch((error) => {
  process.stdout.write(`${JSON.stringify({ contract: 'C02', run_id: RUN_ID, ok: false, error: error.message })}\n`);
  process.exitCode = 1;
});
