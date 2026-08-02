'use strict';

const fs = require('fs');
const os = require('os');
const path = require('path');

const OUTPUT_ROOT = __dirname;
const BUSINESS_DIR = path.join(OUTPUT_ROOT, 'business');
const FAILURE_DIR = path.join(OUTPUT_ROOT, 'failure');
const TARGET_TITLE = process.env.C02_UI_TITLE || '';

function resolvePlaywright() {
  const npxRoot = path.join(os.homedir(), '.npm', '_npx');
  const candidates = [];

  if (fs.existsSync(npxRoot)) {
    for (const entry of fs.readdirSync(npxRoot)) {
      const nodeModules = path.join(npxRoot, entry, 'node_modules');
      for (const packageName of ['playwright', 'playwright-core']) {
        const packagePath = path.join(nodeModules, packageName);
        if (fs.existsSync(path.join(packagePath, 'package.json'))) {
          candidates.push({
            packagePath,
            mtime: fs.statSync(packagePath).mtimeMs,
          });
        }
      }
    }
  }

  candidates.sort((left, right) => right.mtime - left.mtime);
  if (!candidates.length) {
    throw new Error('playwright not found under ~/.npm/_npx/*/node_modules');
  }
  return require(candidates[0].packagePath);
}

async function firstVisible(locators, timeout = 1000) {
  for (const locator of locators) {
    try {
      await locator.first().waitFor({ state: 'visible', timeout });
      return locator.first();
    } catch (_) {
      // Try the next stable locator candidate.
    }
  }
  return null;
}

async function requireVisible(name, locators, timeout = 2500) {
  const locator = await firstVisible(locators, timeout);
  if (!locator) throw new Error(`${name} is not visible`);
  return locator;
}

async function login(page, target) {
  await page.goto(target.url, { waitUntil: 'domcontentloaded' });

  const password = await firstVisible(
    [
      page.getByLabel(/密码|password/i),
      page.getByPlaceholder(/密码|password/i),
      page.locator('input[type="password"]'),
    ],
    1500
  );
  if (!password) return;

  if (!target.account || !target.password) {
    throw new Error(`${target.name} credentials are missing`);
  }

  const account = await requireVisible(`${target.name} account input`, [
    page.getByLabel(/账号|账户|用户名|account|username/i),
    page.getByPlaceholder(/账号|账户|用户名|account|username/i),
    page.locator('input[type="text"]').first(),
  ]);
  await account.fill(target.account);
  await password.fill(target.password);

  const submit = await requireVisible(`${target.name} login button`, [
    page.getByRole('button', { name: /登录|登 录|login|sign in/i }),
    page.locator('button[type="submit"]'),
  ]);
  await Promise.all([
    page.waitForLoadState('domcontentloaded').catch(() => {}),
    submit.click(),
  ]);

  await requireVisible(`${target.name} authenticated navigation`, [
    page.getByText('文章管理', { exact: true }),
    page.getByText(/工作台|仪表盘/),
  ], 10000);
}

async function navigateToArticles(page, target) {
  const titleInput = [
    page.getByPlaceholder('输入文章标题'),
    page.getByPlaceholder('请输入文章标题'),
  ];
  if (await firstVisible(titleInput, 800)) return;

  for (const parentName of target.parents) {
    const parent = await firstVisible(
      [page.getByText(parentName, { exact: true })],
      600
    );
    if (parent) await parent.click();
  }

  const articleMenu = await requireVisible(`${target.name} article menu`, [
    page.getByRole('menuitem', { name: '文章管理' }),
    page.getByText('文章管理', { exact: true }),
  ]);
  await articleMenu.click();
  await requireVisible(`${target.name} article title filter`, titleInput, 10000);
}

async function assertColumn(page, targetName, names) {
  const patterns = names.map((name) => new RegExp(`^${name}$`));
  await requireVisible(
    `${targetName} column ${names.join('/')}`,
    patterns.flatMap((pattern) => [
      page.getByRole('columnheader', { name: pattern }),
      page.locator('th').filter({ hasText: pattern }),
    ])
  );
}

async function locateFixtureRow(page, target) {
  if (TARGET_TITLE) {
    const titleInput = await requireVisible(`${target.name} title filter`, [
      page.getByPlaceholder('输入文章标题'),
      page.getByPlaceholder('请输入文章标题'),
    ]);
    await titleInput.fill(TARGET_TITLE);
    const search = await requireVisible(`${target.name} search button`, [
      page.getByRole('button', { name: /查询|搜索/ }),
    ]);
    await search.click();
    const fixtureRow = page
      .getByRole('row')
      .filter({ hasText: TARGET_TITLE })
      .first();
    await fixtureRow.waitFor({ state: 'visible', timeout: 10000 });
    return fixtureRow;
  }

  const row = page.locator('tbody tr').first();
  await row.waitFor({ state: 'visible', timeout: 5000 });
  return row;
}

async function acceptTarget(page, target) {
  await login(page, target);
  await navigateToArticles(page, target);

  await requireVisible(`${target.name} title filter`, [
    page.getByPlaceholder('输入文章标题'),
    page.getByPlaceholder('请输入文章标题'),
  ]);
  await requireVisible(`${target.name} category filter`, [
    page.getByText(/栏目名称/, { exact: true }),
    page.getByText(/分类/, { exact: true }),
  ]);
  await requireVisible(`${target.name} status filter`, [
    page.getByText(/文章状态/, { exact: true }),
    page.getByText(/状态/, { exact: true }),
  ]);
  await requireVisible(`${target.name} add permission entry`, [
    page.getByRole('button', { name: /发布文章|新增文章/ }),
  ]);

  await assertColumn(page, target.name, ['ID']);
  await assertColumn(page, target.name, ['封面']);
  await assertColumn(page, target.name, ['标题']);
  await assertColumn(page, target.name, ['栏目', '分类']);
  await assertColumn(page, target.name, ['浏览量', '总浏览量']);
  await assertColumn(page, target.name, ['状态']);
  await assertColumn(page, target.name, ['操作']);

  const row = await locateFixtureRow(page, target);
  await requireVisible(`${target.name} updateStatus permission button`, [
    row.getByRole('switch'),
    row.locator('[role="switch"]'),
    row.locator('.el-switch, .arco-switch'),
  ]);
  await requireVisible(`${target.name} edit permission button`, [
    row.getByRole('button', { name: '编辑' }),
    row.getByText('编辑', { exact: true }),
  ]);
  await requireVisible(`${target.name} delete permission button`, [
    row.getByRole('button', { name: '删除' }),
    row.getByText('删除', { exact: true }),
  ]);

  await page.screenshot({
    path: path.join(BUSINESS_DIR, `${target.slug}-article.png`),
    fullPage: true,
  });

  return {
    filters: 3,
    columns: 7,
    permissions: ['add', 'edit', 'delete', 'updateStatus'],
    fixture: TARGET_TITLE || null,
  };
}

async function main() {
  fs.mkdirSync(BUSINESS_DIR, { recursive: true });
  fs.mkdirSync(FAILURE_DIR, { recursive: true });

  const { chromium } = resolvePlaywright();
  const browser = await chromium.launch({ headless: true });
  const context = await browser.newContext({
    viewport: { width: 1440, height: 1000 },
  });
  const page = await context.newPage();
  await context.tracing.start({ screenshots: true, snapshots: true });

  const targets = [
    {
      name: 'LikeAdmin',
      slug: 'likeadmin',
      url: 'http://127.0.0.1:5174/admin/',
      account: process.env.LIKEADMIN_ACCOUNT,
      password: process.env.LIKEADMIN_PASSWORD,
      parents: ['应用管理', '文章资讯'],
    },
    {
      name: 'Peanut',
      slug: 'peanut',
      url: 'http://127.0.0.1:5175/',
      account: process.env.PEANUT_ACCOUNT,
      password: process.env.PEANUT_PASSWORD,
      parents: ['内容管理'],
    },
  ];

  try {
    const result = {};
    for (const target of targets) {
      result[target.slug] = await acceptTarget(page, target);
    }
    await context.tracing.stop();
    await browser.close();
    process.stdout.write(
      `${JSON.stringify({ ok: true, fixture: TARGET_TITLE || null, result })}\n`
    );
  } catch (error) {
    const message = error instanceof Error ? error.message : String(error);
    await page
      .screenshot({
        path: path.join(FAILURE_DIR, 'failure.png'),
        fullPage: true,
      })
      .catch(() => {});
    await context.tracing
      .stop({ path: path.join(FAILURE_DIR, 'trace.zip') })
      .catch(() => {});
    await browser.close().catch(() => {});
    process.stdout.write(`${JSON.stringify({ ok: false, error: message })}\n`);
    process.exitCode = 1;
  }
}

main().catch((error) => {
  const message = error instanceof Error ? error.message : String(error);
  process.stdout.write(`${JSON.stringify({ ok: false, error: message })}\n`);
  process.exitCode = 1;
});
