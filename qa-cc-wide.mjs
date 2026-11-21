export default async function run(page, ui) {
  // We start on /login
  await page.locator('input[name="email"]').fill('malimov707@gmail.com');
  await page.locator('input[name="password"]').fill('Qa!Pass123');
  await page.locator('button:has-text("Kirish")').click();
  await page.waitForLoadState('networkidle');
  const afterLogin = page.url();

  if (afterLogin.includes('/login')) {
    return { problem: 'stayed on login', url: afterLogin };
  }
  if (!afterLogin.includes('/admin/control-center')) {
    await page.goto('http://127.0.0.1:8899/admin/control-center');
    await page.waitForLoadState('networkidle');
  }
  await page.waitForTimeout(1200);
  await page.screenshot({ path: '/tmp/cc-wide.png', fullPage: true });
  const hero = page.locator('.cc-hero');
  const heroCount = await hero.count();
  if (heroCount) {
    await hero.first().screenshot({ path: '/tmp/cc-hero-wide.png' });
  }
  return { url: page.url(), heroCount };
}
