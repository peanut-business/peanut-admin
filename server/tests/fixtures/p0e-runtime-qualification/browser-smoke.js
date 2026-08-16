const required = (name) => {
  const value = process.env[name];
  if (!value) throw new Error(`missing browser environment: ${name}`);
  return value;
};
const mode = required('P0E_BROWSER_MODE');
const baseUrl = required('P0E_BROWSER_BASE_URL').replace(/\/$/, '');
const docsUrl = required('P0E_BROWSER_DOCS_URL').replace(/\/$/, '');
const outputDir = required('P0E_BROWSER_OUTPUT_DIR');
const adminEmail = required('P0E_ADMIN_INITIAL_EMAIL');
const adminPassword = required('P0E_ADMIN_INITIAL_PASSWORD');
const platformEmail = required('P0E_PLATFORM_INITIAL_EMAIL');
const platformPassword = required('P0E_PLATFORM_INITIAL_PASSWORD');
if (!['standalone', 'multi-tenant'].includes(mode)) throw new Error(`invalid mode: ${mode}`);
const screenshotPath = (label) => `${outputDir}/${mode}-${label}.png`;

const assertPage = async (url, label, minimumText = 20) => {
  const response = await page.goto(url, { waitUntil: 'networkidle' });
  if (!response || response.status() >= 400) throw new Error(`${label} returned ${response?.status()}`);
  const text = (await page.locator('body').innerText()).trim();
  if (text.length < minimumText) throw new Error(`${label} rendered insufficient content`);
  await page.screenshot({ path: screenshotPath(label), fullPage: true });
  return { url: page.url(), status: response.status(), text_length: text.length };
};

const results = {};
await page.goto(`${baseUrl}/admin/login`, { waitUntil: 'networkidle' });
if (mode === 'multi-tenant') {
  await page.locator('input').nth(0).fill(adminEmail);
}
await page.locator('input[type="password"]').fill(adminPassword);
await page.getByRole('button', { name: /登录|login/i }).click();
if (mode === 'multi-tenant') {
  const tenantTransition = await Promise.race([
    page.locator('.el-select').waitFor({ state: 'visible', timeout: 20000 }).then(() => 'select'),
    page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 }).then(() => 'navigated'),
  ]);
  if (tenantTransition === 'select') {
    await page.getByRole('button', { name: /登录|login/i }).click();
  }
}
await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 });
await page.waitForLoadState('networkidle');
if (page.url().includes('/login')) throw new Error('tenant administrator login did not leave the login page');
await page.screenshot({ path: screenshotPath('admin'), fullPage: true });
results.admin = { url: page.url(), title: await page.title() };

if (mode === 'multi-tenant') {
  await page.goto(`${baseUrl}/admin/platform/login`, { waitUntil: 'networkidle' });
  const inputs = page.locator('input');
  await inputs.nth(0).fill(platformEmail);
  await inputs.nth(1).fill(platformPassword);
  await page.getByRole('button', { name: /sign in to platform/i }).click();
  await page.waitForURL(/\/admin\/platform\/tenants/, { timeout: 20000 });
  await page.waitForLoadState('networkidle');
  await page.screenshot({ path: screenshotPath('platform'), fullPage: true });
  results.platform = { url: page.url(), title: await page.title() };
}

results.pc = await assertPage(`${baseUrl}/pc/`, 'pc');
results.h5 = await assertPage(`${baseUrl}/mobile/`, 'h5');
results.docs = await assertPage(`${docsUrl}/`, 'docs');
console.log(JSON.stringify({ schema_version: 1, mode, status: 'passed', results }));
