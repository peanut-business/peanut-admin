'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');

const SCRIPT_DIR = __dirname;
const REPOSITORY_ROOT = path.resolve(SCRIPT_DIR, '../../..');
const DEFAULT_FIXTURE = path.join(SCRIPT_DIR, 'fixture.json');

const HELP = `Peanut Admin MT05 concentrated browser acceptance harness

Usage:
  node output/playwright/mt05/run-multitenant-acceptance.cjs [options]

Preparation-only modes (do not start a browser):
  --help                    Show this help
  --dry-run                 Validate arguments and print the redacted execution plan
  --contract-check          Check repository API/selector contracts from fixture.json

Execution options:
  --base-url URL            Multi-tenant Admin Web URL
  --standalone-base-url URL Standalone Admin Web URL (separate deployment of same release)
  --api-base-url URL        Backend URL used by browser-context API calls
  --operator-email VALUE    PlatformOperator email (required for a real run)
  --operator-password VALUE PlatformOperator password (required for a real run)
  --owner-email VALUE       Fixture owner email; default is derived from run-id
  --owner-password VALUE    Fixture owner password (required for a real run)
  --module-key VALUE        Deployed TenantModule key (required for a real run)
  --module-config JSON      TenantModule JSON object; defaults to fixture.json
  --run-id VALUE            Unique lowercase run id; default uses UTC time and PID
  --output-dir PATH         Artifact directory; default output/playwright/mt05/runs/<run-id>
  --fixture PATH            Fixture/contract JSON; default output/playwright/mt05/fixture.json
  --headed                  Run Chromium headed (headless by default)
  --timeout-ms NUMBER       Per-operation timeout; default 15000
  --playwright-module PATH  Explicit playwright or playwright-core package directory

No service is started, no dependency is installed, and no fixture Tenant is deleted.
The final Tenant remains suspended so the denial assertions stay auditable.
`;

function fail(message) {
  throw new Error(message);
}

function parseArguments(argv) {
  const options = { headed: false, dryRun: false, contractCheck: false };
  const valueOptions = new Map([
    ['--base-url', 'baseUrl'],
    ['--standalone-base-url', 'standaloneBaseUrl'],
    ['--api-base-url', 'apiBaseUrl'],
    ['--operator-email', 'operatorEmail'],
    ['--operator-password', 'operatorPassword'],
    ['--owner-email', 'ownerEmail'],
    ['--owner-password', 'ownerPassword'],
    ['--module-key', 'moduleKey'],
    ['--module-config', 'moduleConfigRaw'],
    ['--run-id', 'runId'],
    ['--output-dir', 'outputDir'],
    ['--fixture', 'fixturePath'],
    ['--timeout-ms', 'timeoutRaw'],
    ['--playwright-module', 'playwrightModule'],
  ]);

  for (let index = 0; index < argv.length; index += 1) {
    const argument = argv[index];
    if (argument === '--help' || argument === '-h') options.help = true;
    else if (argument === '--dry-run') options.dryRun = true;
    else if (argument === '--contract-check') options.contractCheck = true;
    else if (argument === '--headed') options.headed = true;
    else if (valueOptions.has(argument)) {
      const value = argv[index + 1];
      if (value === undefined || value.startsWith('--')) fail(`${argument} requires a value`);
      options[valueOptions.get(argument)] = value;
      index += 1;
    } else fail(`unknown option: ${argument}`);
  }
  return options;
}

function readFixture(fixturePath) {
  let parsed;
  try {
    parsed = JSON.parse(fs.readFileSync(fixturePath, 'utf8'));
  } catch (error) {
    fail(`fixture is unreadable: ${error.message}`);
  }
  if (parsed.schema_version !== 1) fail('fixture schema_version must be 1');
  return parsed;
}

function normalizeUrl(value, name) {
  let url;
  try {
    url = new URL(value);
  } catch (_) {
    fail(`${name} must be an absolute http(s) URL`);
  }
  if (!['http:', 'https:'].includes(url.protocol)) fail(`${name} must use http(s)`);
  url.hash = '';
  url.search = '';
  if (!url.pathname.endsWith('/')) url.pathname += '/';
  return url.toString();
}

function normalizeAdminWebUrl(value, name) {
  const normalized = normalizeUrl(value, name);
  if (!new URL(normalized).pathname.endsWith('/admin/')) {
    fail(`${name} must include the deployed /admin/ base path`);
  }
  return normalized;
}

function defaultRunId() {
  return `mt05-${new Date().toISOString().replace(/[-:]/g, '').replace(/\..+/, '').toLowerCase()}-${process.pid}`;
}

function buildConfiguration(cli) {
  const fixturePath = path.resolve(cli.fixturePath || DEFAULT_FIXTURE);
  const fixture = readFixture(fixturePath);
  const runId = cli.runId || defaultRunId();
  if (!/^[a-z0-9][a-z0-9-]{2,63}$/.test(runId)) {
    fail('--run-id must match ^[a-z0-9][a-z0-9-]{2,63}$');
  }
  const timeout = Number(cli.timeoutRaw || 15000);
  if (!Number.isSafeInteger(timeout) || timeout < 1000 || timeout > 120000) {
    fail('--timeout-ms must be an integer between 1000 and 120000');
  }
  let moduleConfig = fixture.module?.config || {};
  if (cli.moduleConfigRaw !== undefined) {
    try {
      moduleConfig = JSON.parse(cli.moduleConfigRaw);
    } catch (_) {
      fail('--module-config must be valid JSON');
    }
  }
  if (!moduleConfig || Array.isArray(moduleConfig) || typeof moduleConfig !== 'object') {
    fail('--module-config must be a JSON object');
  }
  const ownerEmail = cli.ownerEmail || `${runId}@mt05.invalid`;
  if (!ownerEmail.includes('@')) fail('--owner-email must look like an email address');

  return {
    fixture,
    fixturePath,
    baseUrl: normalizeAdminWebUrl(cli.baseUrl || fixture.deployment.multi_tenant_base_url, '--base-url'),
    standaloneBaseUrl: normalizeAdminWebUrl(
      cli.standaloneBaseUrl || fixture.deployment.standalone_base_url,
      '--standalone-base-url'
    ),
    apiBaseUrl: normalizeUrl(cli.apiBaseUrl || fixture.deployment.api_base_url, '--api-base-url'),
    operatorEmail: cli.operatorEmail || process.env.MT05_OPERATOR_EMAIL || '',
    operatorPassword: cli.operatorPassword || process.env.MT05_OPERATOR_PASSWORD || '',
    ownerEmail,
    ownerPassword: cli.ownerPassword || process.env.MT05_OWNER_PASSWORD || '',
    moduleKey: cli.moduleKey || fixture.module?.key || '',
    moduleConfig,
    runId,
    timeout,
    headed: cli.headed,
    playwrightModule: cli.playwrightModule || process.env.MT05_PLAYWRIGHT_MODULE || '',
    outputDir: path.resolve(cli.outputDir || path.join(SCRIPT_DIR, 'runs', runId)),
  };
}

function redactedPlan(config) {
  return {
    mode: 'dry-run',
    browser_started: false,
    services_started: false,
    dependencies_installed: false,
    run_id: config.runId,
    urls: {
      multi_tenant: config.baseUrl,
      standalone: config.standaloneBaseUrl,
      api: config.apiBaseUrl,
    },
    output_dir: config.outputDir,
    credentials: {
      platform_operator_email: config.operatorEmail || '<required for real run>',
      platform_operator_password: config.operatorPassword ? '<provided>' : '<required for real run>',
      owner_email: config.ownerEmail,
      owner_password: config.ownerPassword ? '<provided>' : '<required for real run>',
    },
    tenant_module: {
      key: config.moduleKey || '<required for real run>',
      config_keys: Object.keys(config.moduleConfig).sort(),
    },
    matrix: [
      'PlatformOperator browser login',
      'provision primary Tenant and first owner',
      'activate primary Tenant',
      'enable/configure deployed TenantModule',
      'provision and activate switch target for the same owner Account',
      'owner browser login and Tenant selection',
      'configure representative tenant role and Article permissions',
      'owner TenantMember writes and reads a Tenant-first Article',
      'switch Tenant and prove old token revocation',
      'suspend selected Tenant',
      'reject tenant-targeted new login and old-session business write',
      'Standalone hides Tenant selection and platform route',
    ],
  };
}

function contractCheck(config) {
  const results = [];
  for (const [relativePath, needles] of Object.entries(config.fixture.contracts || {})) {
    const absolutePath = path.join(REPOSITORY_ROOT, relativePath);
    if (!fs.existsSync(absolutePath)) fail(`contract file is missing: ${relativePath}`);
    const source = fs.readFileSync(absolutePath, 'utf8');
    const missing = needles.filter((needle) => !source.includes(needle));
    results.push({ file: relativePath, checked: needles.length, missing });
  }
  const failures = results.filter((result) => result.missing.length > 0);
  const requiredApiEnvironment = config.fixture.required_api_environment;
  if (!Array.isArray(requiredApiEnvironment)
      || requiredApiEnvironment.length === 0
      || requiredApiEnvironment.some((name) => typeof name !== 'string' || name === '')) {
    fail('fixture required_api_environment must be a non-empty string list');
  }
  process.stdout.write(`${JSON.stringify({
    ok: failures.length === 0,
    mode: 'contract-check',
    required_api_environment: requiredApiEnvironment,
    results,
  }, null, 2)}\n`);
  if (failures.length) process.exitCode = 1;
}

function resolvePlaywright(explicitPath) {
  const candidates = [];
  if (explicitPath) candidates.push(path.resolve(explicitPath));
  candidates.push('playwright', 'playwright-core');
  const npxRoot = path.join(os.homedir(), '.npm', '_npx');
  if (fs.existsSync(npxRoot)) {
    for (const entry of fs.readdirSync(npxRoot)) {
      for (const packageName of ['playwright', 'playwright-core']) {
        const packagePath = path.join(npxRoot, entry, 'node_modules', packageName);
        if (fs.existsSync(path.join(packagePath, 'package.json'))) candidates.push(packagePath);
      }
    }
  }
  for (const candidate of candidates) {
    try {
      const resolved = require(candidate);
      if (resolved.chromium) return resolved;
    } catch (_) {
      // Continue through already-installed candidates. This harness never installs dependencies.
    }
  }
  fail('Playwright is not installed/resolvable; pass --playwright-module or prepare it before the one authorized run');
}

function appUrl(baseUrl, route) {
  return new URL(route.replace(/^\//, ''), baseUrl).toString();
}

function apiUrl(config, route) {
  return new URL(route.replace(/^\//, ''), config.apiBaseUrl).toString();
}

async function responseBody(response) {
  const text = await response.text();
  try {
    return JSON.parse(text);
  } catch (_) {
    fail(`${response.url()} returned non-JSON status ${response.status()}`);
  }
}

async function requestJson(page, config, method, route, token, payload, expectedCode = 20000) {
  const response = await page.request.fetch(apiUrl(config, route), {
    method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      'X-Request-Id': `${config.runId}-${route.replace(/[^a-z0-9]+/gi, '-').slice(-48)}`,
    },
    ...(payload === undefined ? {} : { data: payload }),
  });
  const body = await responseBody(response);
  if (body.code !== expectedCode) {
    fail(`${method} ${route} expected code ${expectedCode}, got ${body.code}: ${body.msg || 'no message'}`);
  }
  return body.data;
}

async function requestTenantSession(page, config, method, route, token, payload) {
  const response = await page.request.fetch(apiUrl(config, route), {
    method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      'X-Request-Id': `${config.runId}-${route.replace(/[^a-z0-9]+/gi, '-').slice(-48)}`,
    },
    ...(payload === undefined ? {} : { data: payload }),
  });
  const body = await responseBody(response);
  if (!response.ok() || response.status() !== 200) {
    fail(`${method} ${route} expected HTTP 200, got ${response.status()}`);
  }
  if (!body || typeof body !== 'object' || !body.data || typeof body.data !== 'object') {
    fail(`${method} ${route} omitted the Tenant session data envelope`);
  }
  if (!body.meta || typeof body.meta.request_id !== 'string' || body.meta.request_id === '') {
    fail(`${method} ${route} omitted the Tenant session request identity`);
  }
  return body.data;
}

async function expectRejected(page, config, method, route, token, payload, name) {
  const response = await page.request.fetch(apiUrl(config, route), {
    method,
    headers: {
      Accept: 'application/json',
      ...(token ? { Authorization: `Bearer ${token}` } : {}),
      'X-Request-Id': `${config.runId}-rejection-${name.replace(/[^a-z0-9]+/gi, '-').slice(0, 32)}`,
    },
    ...(payload === undefined ? {} : { data: payload }),
  });
  const body = await responseBody(response);
  if (body.code === 20000) fail(`${name} unexpectedly succeeded`);
  return { code: body.code, message: body.msg || '', http_status: response.status() };
}

async function platformBrowserLogin(page, config) {
  await page.goto(appUrl(config.baseUrl, 'platform/login'), { waitUntil: 'domcontentloaded' });
  await page.getByLabel('Email', { exact: true }).fill(config.operatorEmail);
  await page.getByLabel('Password', { exact: true }).fill(config.operatorPassword);
  await Promise.all([
    page.waitForResponse((response) => response.url().includes('/api/platform/session/login')),
    page.getByRole('button', { name: 'Sign in to platform', exact: true }).click(),
  ]);
  await page.getByRole('heading', { name: 'Tenant lifecycle', exact: true }).waitFor();
  const token = await page.evaluate(() => localStorage.getItem('platform_token'));
  if (!token) fail('PlatformOperator browser login did not persist a platform token');
  return token;
}

async function provisionTenantThroughUi(page, config, code, name, initialPassword) {
  await page.getByRole('button', { name: 'Provision Tenant', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Provision Tenant', exact: true });
  await dialog.getByLabel('Tenant code', { exact: true }).fill(code);
  await dialog.getByLabel('Tenant name', { exact: true }).fill(name);
  await dialog.getByLabel('Owner email', { exact: true }).fill(config.ownerEmail);
  await dialog.getByLabel('Owner display name', { exact: true }).fill(`MT05 Owner ${config.runId}`);
  if (initialPassword) await dialog.getByLabel('Initial password', { exact: true }).fill(initialPassword);
  const responsePromise = page.waitForResponse((response) => response.url().includes('/api/platform/tenants/provision'));
  await dialog.getByRole('button', { name: 'Provision', exact: true }).click();
  const body = await responseBody(await responsePromise);
  if (body.code !== 20000) fail(`Tenant ${code} provisioning failed: ${body.msg || body.code}`);
  await page.getByRole('row').filter({ hasText: code }).waitFor();
}

async function tenantRecord(page, config, platformToken, code) {
  const result = await requestJson(page, config, 'GET', '/api/platform/tenants?page=1&page_size=100', platformToken);
  const tenant = result.lists.find((candidate) => candidate.code === code);
  if (!tenant) fail(`Tenant ${code} is absent from platform list`);
  return tenant;
}

async function activateTenantThroughUi(page, config, code) {
  const row = page.getByRole('row').filter({ hasText: code });
  await row.getByRole('button', { name: 'Activate', exact: true }).click();
  const messageBox = page.locator('.el-message-box').filter({ hasText: `activate` });
  await messageBox.locator('input').fill(`${config.runId} acceptance activation`);
  const responsePromise = page.waitForResponse((response) => response.url().includes('/api/platform/tenants/activate'));
  await messageBox.locator('.el-button--primary').click();
  const body = await responseBody(await responsePromise);
  if (body.code !== 20000) fail(`Tenant ${code} activation failed: ${body.msg || body.code}`);
  await row.getByText('active', { exact: true }).waitFor();
}

async function enableModuleThroughUi(page, config, code) {
  const row = page.getByRole('row').filter({ hasText: code });
  await row.getByRole('button', { name: 'Enable', exact: true }).click();
  const dialog = page.getByRole('dialog', { name: 'Enable Tenant Module', exact: true });
  await dialog.getByLabel('Module key', { exact: true }).fill(config.moduleKey);
  await dialog.getByLabel('JSON config', { exact: true }).fill(JSON.stringify(config.moduleConfig));
  await dialog.getByLabel('Change reason', { exact: true }).fill(`${config.runId} representative module configuration`);
  const responsePromise = page.waitForResponse((response) => response.url().includes('/api/platform/tenants/modules/enable'));
  await dialog.getByRole('button', { name: 'Enable', exact: true }).click();
  const body = await responseBody(await responsePromise);
  if (body.code !== 20000) fail(`TenantModule enable failed: ${body.msg || body.code}`);
  await dialog.waitFor({ state: 'hidden' });
}

async function ownerBrowserLogin(page, config, primaryTenantId, primaryTenantName) {
  await page.goto(appUrl(config.baseUrl, 'login'), { waitUntil: 'domcontentloaded' });
  await page.getByPlaceholder(/账号|账户|用户名|account|username/i).fill(config.ownerEmail);
  await page.getByPlaceholder(/密码|password/i).fill(config.ownerPassword);
  const login = page.getByRole('button', { name: /登录|login/i });
  await login.click();
  const selector = page.locator('.login-form .el-select');
  await selector.waitFor({ state: 'visible' });
  await selector.click();
  await page
    .locator('.el-select-dropdown:visible .el-select-dropdown__item')
    .filter({ hasText: primaryTenantName })
    .click();
  const responsePromise = page.waitForResponse((response) => response.url().includes('/api/tenant/session/select'));
  await login.click();
  const body = await responseBody(await responsePromise);
  if (body.data?.state !== 'authenticated') fail('owner Tenant selection did not authenticate');
  if (Number(body.data.context?.tenant_id) !== Number(primaryTenantId)) {
    fail('owner browser selection established the wrong Tenant');
  }
  await page.waitForURL((url) => !url.pathname.endsWith('/login'));
  const token = await page.evaluate(() => localStorage.getItem('token'));
  if (!token || !token.startsWith('pa_tat_')) fail('owner browser login did not persist a Tenant access token');
  return token;
}

function flattenMenus(items, result = []) {
  for (const item of items || []) {
    result.push(item);
    flattenMenus(item.children || [], result);
  }
  return result;
}

async function configureRepresentativeRole(page, config, token) {
  const menus = await requestJson(page, config, 'GET', '/api/admin/menu/lists', token);
  const articleMenuIds = flattenMenus(menus)
    .filter((menu) => String(menu.perms || '').startsWith('article.article/'))
    .map((menu) => Number(menu.id));
  if (!articleMenuIds.length) fail('Article permission menu contracts are absent');
  const roleName = `MT05 Article ${config.runId}`.slice(0, 50);
  await requestJson(page, config, 'POST', '/api/admin/role/add', token, {
    name: roleName,
    desc: 'MT05 representative Tenant role',
    sort: 5,
    menu_id: articleMenuIds,
  });
  const roles = await requestJson(page, config, 'GET', '/api/admin/role/lists?page_no=1&page_size=100', token);
  const role = roles.lists.find((candidate) => candidate.name === roleName);
  if (!role) fail('representative role was not readable after creation');
  return { role_id: role.id, permission_menu_ids: articleMenuIds };
}

async function writeRepresentativeArticle(page, config, token, suffix) {
  const categoryName = `MT05 ${suffix} category ${config.runId}`.slice(0, 90);
  await requestJson(page, config, 'POST', '/api/admin/article.articleCate/add', token, {
    name: categoryName,
    sort: 1,
    is_show: 1,
  });
  const categories = await requestJson(page, config, 'GET', '/api/admin/article.articleCate/lists?page_no=1&page_size=100', token);
  const category = categories.lists.find((candidate) => candidate.name === categoryName);
  if (!category) fail(`${suffix} Article category was not readable after creation`);
  const title = `MT05 ${suffix} article ${config.runId}`.slice(0, 255);
  await requestJson(page, config, 'POST', '/api/admin/article.article/add', token, {
    title,
    cid: category.id,
    is_show: 1,
    content: `<p>${config.runId}</p>`,
    abstract: 'MT05 Tenant-first representative business fixture',
    author: 'MT05 harness',
    click_virtual: 0,
    sort: 0,
  });
  const articles = await requestJson(page, config, 'GET', `/api/admin/article.article/lists?page_no=1&page_size=100&title=${encodeURIComponent(title)}`, token);
  const article = articles.lists.find((candidate) => candidate.title === title);
  if (!article) fail(`${suffix} Article was not readable after creation`);
  return { category_id: category.id, article_id: article.id, title };
}

async function switchTenant(page, config, oldToken, targetTenantId) {
  const selection = await requestTenantSession(
    page, config, 'POST', '/api/tenant/session/switch', oldToken, {}
  );
  const target = selection.tenants.find((tenant) => Number(tenant.tenant_id) === Number(targetTenantId));
  if (!target) fail('switch challenge omitted the target Tenant');
  const authenticated = await requestTenantSession(page, config, 'POST', '/api/tenant/session/select', '', {
    challenge_token: selection.challenge_token,
    tenant_id: targetTenantId,
  });
  if (authenticated.state !== 'authenticated') fail('Tenant switch did not authenticate');
  await page.evaluate((token) => localStorage.setItem('token', token), authenticated.access_token);
  return authenticated.access_token;
}

async function suspendTenant(page, config, platformToken, tenant) {
  return requestJson(page, config, 'POST', '/api/platform/tenants/suspend', platformToken, {
    tenant_id: tenant.id,
    expected_revision: tenant.revision,
    change_reason: `${config.runId} suspension denial gate`,
  });
}

async function assertStandalone(page, config) {
  await page.goto(appUrl(config.standaloneBaseUrl, 'login'), { waitUntil: 'domcontentloaded' });
  await page.getByPlaceholder(/账号|账户|用户名|account|username/i).waitFor();
  if (await page.locator('.login-form .el-select').count()) fail('Standalone login exposed Tenant selection');
  await page.goto(appUrl(config.standaloneBaseUrl, 'platform/login'), { waitUntil: 'domcontentloaded' });
  if (await page.getByRole('heading', { name: 'Instance Platform', exact: true }).count()) {
    fail('Standalone deployment exposed the platform entry');
  }
}

async function execute(config) {
  for (const [name, value] of [
    ['--operator-email', config.operatorEmail],
    ['--operator-password', config.operatorPassword],
    ['--owner-password', config.ownerPassword],
    ['--module-key', config.moduleKey],
  ]) if (!value) fail(`${name} is required for a real run`);
  if (config.baseUrl === config.standaloneBaseUrl) {
    fail('--base-url and --standalone-base-url must identify separately configured deployments');
  }

  if (fs.existsSync(config.outputDir)) fail(`--output-dir already exists: ${config.outputDir}`);
  fs.mkdirSync(config.outputDir, { recursive: true });
  const { chromium } = resolvePlaywright(config.playwrightModule);
  const browser = await chromium.launch({ headless: !config.headed });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  context.setDefaultTimeout(config.timeout);
  const page = await context.newPage();
  await context.tracing.start({ screenshots: true, snapshots: true, sources: true });
  const summary = { ok: false, run_id: config.runId, started_at: new Date().toISOString(), assertions: {} };
  const primaryCode = `${config.runId}-a`.slice(0, 64);
  const secondaryCode = `${config.runId}-b`.slice(0, 64);

  try {
    const platformToken = await platformBrowserLogin(page, config);
    summary.assertions.platform_operator_login = true;

    await provisionTenantThroughUi(page, config, primaryCode, `MT05 Primary ${config.runId}`, config.ownerPassword);
    await activateTenantThroughUi(page, config, primaryCode);
    await enableModuleThroughUi(page, config, primaryCode);
    await provisionTenantThroughUi(page, config, secondaryCode, `MT05 Switch ${config.runId}`, '');
    await activateTenantThroughUi(page, config, secondaryCode);
    const primary = await tenantRecord(page, config, platformToken, primaryCode);
    let secondary = await tenantRecord(page, config, platformToken, secondaryCode);
    summary.assertions.platform_governance = { primary_tenant_id: primary.id, secondary_tenant_id: secondary.id, module_key: config.moduleKey };
    await page.screenshot({ path: path.join(config.outputDir, 'platform-tenants.png'), fullPage: true });

    const primaryToken = await ownerBrowserLogin(
      page,
      config,
      primary.id,
      `MT05 Primary ${config.runId}`
    );
    summary.assertions.owner_login_and_selection = true;
    summary.assertions.representative_role = await configureRepresentativeRole(page, config, primaryToken);
    summary.assertions.primary_article = await writeRepresentativeArticle(page, config, primaryToken, 'primary');

    const secondaryToken = await switchTenant(page, config, primaryToken, secondary.id);
    summary.assertions.old_token_rejection = await expectRejected(
      page, config, 'GET', '/api/admin/article.article/lists?page_no=1&page_size=1', primaryToken, undefined, 'old-token-after-switch'
    );
    summary.assertions.secondary_article = await writeRepresentativeArticle(page, config, secondaryToken, 'secondary');

    secondary = await tenantRecord(page, config, platformToken, secondaryCode);
    await suspendTenant(page, config, platformToken, secondary);
    summary.assertions.suspended_new_login_rejection = await expectRejected(
      page, config, 'POST', '/api/tenant/session/login', '',
      { email: config.ownerEmail, password: config.ownerPassword, tenant_code: secondaryCode },
      'suspended-tenant-new-login'
    );
    summary.assertions.suspended_old_session_write_rejection = await expectRejected(
      page, config, 'POST', '/api/admin/article.articleCate/add', secondaryToken,
      { name: `denied ${config.runId}`.slice(0, 90), sort: 0, is_show: 1 },
      'suspended-tenant-old-session-write'
    );

    await assertStandalone(page, config);
    summary.assertions.standalone_hidden_control_plane = true;
    await page.screenshot({ path: path.join(config.outputDir, 'standalone-platform-hidden.png'), fullPage: true });
    summary.ok = true;
  } catch (error) {
    summary.error = error instanceof Error ? error.message : String(error);
    await page.screenshot({ path: path.join(config.outputDir, 'failure.png'), fullPage: true }).catch(() => {});
    process.exitCode = 1;
  } finally {
    summary.finished_at = new Date().toISOString();
    fs.writeFileSync(path.join(config.outputDir, 'summary.json'), `${JSON.stringify(summary, null, 2)}\n`);
    await context.tracing.stop({ path: path.join(config.outputDir, 'trace.zip') }).catch(() => {});
    await browser.close().catch(() => {});
  }
  process.stdout.write(`${JSON.stringify(summary)}\n`);
}

async function main() {
  const cli = parseArguments(process.argv.slice(2));
  if (cli.help) {
    process.stdout.write(HELP);
    return;
  }
  const config = buildConfiguration(cli);
  if (cli.contractCheck) {
    contractCheck(config);
    return;
  }
  if (cli.dryRun) {
    process.stdout.write(`${JSON.stringify(redactedPlan(config), null, 2)}\n`);
    return;
  }
  await execute(config);
}

main().catch((error) => {
  process.stderr.write(`MT05 harness error: ${error instanceof Error ? error.message : String(error)}\n`);
  process.exitCode = 1;
});
