import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext({ viewport: { width: 1440, height: 900 } });
const page = await context.newPage();

// 1. Login
await page.goto('http://127.0.0.1:8899/login');
await page.fill('input[name="email"]', 'malimov707@gmail.com');
await page.fill('input[name="password"]', 'mashhurbek707');
await Promise.all([
  page.waitForNavigation({ waitUntil: 'networkidle' }),
  page.click('button[type="submit"]'),
]);

// Wait for redirect to complete
await page.waitForLoadState('networkidle');
console.log('After login URL:', page.url());

// If login stayed on login page, dump debug
if (page.url().includes('/login')) {
  const html = await page.content();
  console.log('STILL ON LOGIN. Errors on page:', html.includes('invalid') || html.includes('x-nima uchun') ? 'has-errors' : 'no-error-text');
}

// Navigate directly if redirected to dashboard but not control center
if (!page.url().includes('/admin/control-center')) {
  await page.goto('http://127.0.0.1:8899/admin/control-center');
  await page.waitForLoadState('networkidle');
}
console.log('Final URL:', page.url());

// Wait for hero image
await page.waitForTimeout(1000);

// Full page screenshot
await page.screenshot({ path: '/tmp/cc-final.png', fullPage: true });
console.log('Saved screenshot /tmp/cc-final.png');

// Hero-only closeup
const hero = await page.$('.cc-hero');
if (hero) {
  await hero.screenshot({ path: '/tmp/cc-hero.png' });
  console.log('Saved hero screenshot /tmp/cc-hero.png');
} else {
  console.log('HERO NOT FOUND');
}

await browser.close();
