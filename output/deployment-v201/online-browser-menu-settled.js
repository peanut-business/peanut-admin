async (page) => {
  const env = process.env;
  await page.goto(`${env.PA_SHARED_ADMIN_URL}/admin/login`, { waitUntil: 'networkidle' });
  await page.locator('input').nth(0).fill(env.PA_SHARED_EMAIL);
  await page.locator('input[type="password"]').fill(env.PA_SHARED_PASSWORD);
  await page.getByRole('button', { name: /登录|login/i }).first().click();
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 20000 });
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(1000);
  const userMenu = page.getByRole('button', { name: '打开用户菜单' });
  await userMenu.click();
  await page.waitForTimeout(1000);
  const menu = page.locator('.el-dropdown-menu:visible, [role="menu"]:visible').first();
  const box = await menu.boundingBox();
  const style = await menu.evaluate((node) => {
    const computed = window.getComputedStyle(node);
    return { opacity: computed.opacity, visibility: computed.visibility, display: computed.display, text: node.textContent?.trim() || '' };
  });
  const screenshot = `${env.PA_BROWSER_OUTPUT_DIR}/shared-admin-menu-settled.png`;
  await page.screenshot({ path: screenshot, fullPage: true });
  return { url: page.url(), menu_box: box, menu_style: style, screenshot };
}
