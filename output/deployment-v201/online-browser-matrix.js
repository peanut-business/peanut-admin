async (page) => {
const required = (name) => {
  const value = process.env[name];
  if (!value) throw new Error(`missing environment: ${name}`);
  return value;
};

const outputDir = required('PA_BROWSER_OUTPUT_DIR');
const sharedAdminUrl = required('PA_SHARED_ADMIN_URL').replace(/\/$/, '');
const tenantAUrl = required('PA_TENANT_A_URL').replace(/\/$/, '');
const tenantBUrl = required('PA_TENANT_B_URL').replace(/\/$/, '');
const sharedEmail = required('PA_SHARED_EMAIL');
const sharedPassword = required('PA_SHARED_PASSWORD');
const tenantAEmail = required('PA_TENANT_A_EMAIL');
const tenantBEmail = required('PA_TENANT_B_EMAIL');
const tenantPassword = required('PA_TENANT_PASSWORD');

const results = [];
const failedRequests = [];
const failedResponses = [];
page.on('requestfailed', (request) => failedRequests.push({ url: request.url(), error: request.failure()?.errorText || 'unknown' }));
page.on('response', (response) => {
  if (response.status() >= 400) failedResponses.push({ url: response.url(), status: response.status() });
});

const visibleCount = async (selector) => page.locator(selector).evaluateAll((nodes) => nodes.filter((node) => {
  const style = window.getComputedStyle(node);
  const rect = node.getBoundingClientRect();
  return style.display !== 'none' && style.visibility !== 'hidden' && rect.width > 0 && rect.height > 0;
}).length);

const checkEntry = async (label, baseUrl, email, password) => {
  failedRequests.length = 0;
  failedResponses.length = 0;
  await page.goto(`${baseUrl}/admin/login`, { waitUntil: 'networkidle' });
  const inputs = page.locator('input');
  await inputs.nth(0).fill(email);
  await page.locator('input[type="password"]').fill(password);
  await page.getByRole('button', { name: /登录|login/i }).first().click();

  const tenantSelector = page.locator('.login-form .el-select');
  if (await tenantSelector.isVisible().catch(() => false)) {
    await tenantSelector.click();
    await page.locator('.el-select-dropdown:visible .el-select-dropdown__item').first().click();
    await page.getByRole('button', { name: /登录|login/i }).first().click();
  }
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 });
  await page.waitForLoadState('networkidle');
  await page.waitForFunction(() => [...document.querySelectorAll('.el-loading-mask, .el-skeleton, .el-loading-spinner, [aria-busy="true"]')].every((node) => {
    const style = window.getComputedStyle(node);
    const rect = node.getBoundingClientRect();
    return style.display === 'none' || style.visibility === 'hidden' || rect.width === 0 || rect.height === 0;
  }), { timeout: 20000 });

  const userMenu = page.getByRole('button', { name: '打开用户菜单' });
  const userMenuVisible = await userMenu.isVisible().catch(() => false);
  if (!userMenuVisible) throw new Error(`${label}: user menu button is not visible`);
  await userMenu.click();
  await page.waitForTimeout(1000);
  const menuVisible = await visibleCount('.el-dropdown-menu, [role="menu"]');
  if (menuVisible < 1) throw new Error(`${label}: user menu did not open`);

  const images = await page.locator('img').evaluateAll((nodes) => nodes.map((node) => ({
    src: node.currentSrc || node.getAttribute('src') || '',
    complete: node.complete,
    naturalWidth: node.naturalWidth,
    alt: node.getAttribute('alt') || '',
  })));
  const brokenImages = images.filter((image) => image.complete && image.naturalWidth === 0);
  const loading = await visibleCount('.el-loading-mask, .el-skeleton, .el-loading-spinner, [aria-busy="true"]');
  const bodyText = (await page.locator('body').innerText()).trim();
  const screenshot = `${outputDir}/${label}.png`;
  await page.screenshot({ path: screenshot, fullPage: true });
  const applicationOrigins = [sharedAdminUrl, tenantAUrl, tenantBUrl].map((url) => url.replace(/\/$/, ''));
  const isApplicationUrl = (url) => applicationOrigins.some((origin) => url.startsWith(`${origin}/`));
  const productFailedRequests = failedRequests.filter((request) => isApplicationUrl(request.url));
  const externalFailedRequests = failedRequests.filter((request) => !isApplicationUrl(request.url));
  const productErrorResponses = failedResponses.filter((response) => isApplicationUrl(response.url));
  const externalErrorResponses = failedResponses.filter((response) => !isApplicationUrl(response.url));
  results.push({
    label,
    url: page.url(),
    title: await page.title(),
    body_text_length: bodyText.length,
    user_menu_visible: userMenuVisible,
    user_menu_opened: menuVisible > 0,
    images,
    broken_images: brokenImages,
    visible_loading: loading,
    failed_requests: productFailedRequests,
    external_failed_requests: externalFailedRequests,
    error_responses: productErrorResponses,
    external_error_responses: externalErrorResponses,
    screenshot,
  });
};

await checkEntry('shared-admin', sharedAdminUrl, sharedEmail, sharedPassword);
await checkEntry('tenant-a', tenantAUrl, tenantAEmail, tenantPassword);
await checkEntry('tenant-b', tenantBUrl, tenantBEmail, tenantPassword);

const failures = results.flatMap((result) => [
  ...(result.broken_images.length ? [`${result.label}: broken images`] : []),
  ...(result.visible_loading ? [`${result.label}: visible loading state`] : []),
  ...(result.failed_requests.length ? [`${result.label}: failed requests`] : []),
  ...(result.error_responses.length ? [`${result.label}: HTTP error responses`] : []),
  ...(!result.user_menu_visible || !result.user_menu_opened ? [`${result.label}: user menu unavailable`] : []),
]);
const summary = { schema_version: 1, status: failures.length ? 'failed' : 'passed', failures, results };
if (failures.length) throw new Error(failures.join('; '));
return summary;
}
