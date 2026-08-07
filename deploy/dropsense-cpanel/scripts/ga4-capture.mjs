#!/usr/bin/env node
/**
 * Walks the Google Analytics reports that hold the numbers this tool needs,
 * and photographs each screen into docs/features/evidence/.
 *
 * Reuses the browser profile created by ga4-login.mjs, so it needs no password.
 *
 *   node scripts/ga4-capture.mjs            # visible, so you can watch
 *   node scripts/ga4-capture.mjs --headless
 */
import { chromium } from 'playwright';
import path from 'node:path';
import { mkdir } from 'node:fs/promises';

const PROFILE = path.resolve('storage/app/browser-profile');
const OUT = path.resolve('docs/features/evidence');
const headless = process.argv.includes('--headless');

await mkdir(OUT, { recursive: true });

const context = await chromium.launchPersistentContext(PROFILE, {
  headless,
  viewport: { width: 1440, height: 900 },
});

const page = context.pages()[0] ?? (await context.newPage());
const shots = [];

async function shot(name, note) {
  const file = path.join(OUT, `ga4-${name}.png`);
  await page.screenshot({ path: file });
  shots.push({ name, note, file: path.relative(process.cwd(), file) });
  console.log(`  captured ${name} — ${note}`);
}

/** Click something by its visible text, tolerantly. */
async function clickText(text, { timeout = 15000 } = {}) {
  const target = page.getByText(text, { exact: false }).first();
  await target.waitFor({ state: 'visible', timeout });
  await target.click();
  await page.waitForTimeout(2500);
}

try {
  await page.goto('https://analytics.google.com/analytics/web/', { waitUntil: 'domcontentloaded' });
  await page.waitForTimeout(6000);

  const url = page.url();
  if (url.includes('accounts.google.com') || url.includes('signin')) {
    console.log(JSON.stringify({ ok: false, error: 'Not signed in. Run: node scripts/ga4-login.mjs' }));
    process.exitCode = 1;
  } else {
    await shot('01-home', 'Where you land after signing in — the property picker is top-left.');

    // Reports → Engagement → Pages and screens
    try {
      await clickText('Reports');
      await shot('02-reports', 'The Reports section in the left-hand menu.');
    } catch { console.log('  (could not find Reports in the menu)'); }

    try {
      await clickText('Engagement');
      await page.waitForTimeout(1500);
      await shot('03-engagement', 'Engagement expands to show Pages and screens.');
      await clickText('Pages and screens');
      await page.waitForTimeout(4000);
      await shot('04-pages-and-screens', 'Views and Active users per page. Bounce rate is NOT here yet.');
    } catch { console.log('  (could not reach Pages and screens)'); }

    console.log(JSON.stringify({ ok: true, shots }, null, 2));
  }
} catch (err) {
  console.log(JSON.stringify({ ok: false, error: String(err?.message ?? err) }));
  process.exitCode = 1;
} finally {
  await context.close();
}
