#!/usr/bin/env node
/**
 * Opens a browser window at Google Analytics and waits while YOU sign in.
 *
 * The session is saved to a local profile folder, so the capture script can
 * reuse it. Your password is typed by you, into Google, in a normal browser
 * window — nothing else ever sees it.
 *
 *   node scripts/ga4-login.mjs
 */
import { chromium } from 'playwright';
import path from 'node:path';

const PROFILE = path.resolve('storage/app/browser-profile');

const context = await chromium.launchPersistentContext(PROFILE, {
  headless: false,
  viewport: { width: 1440, height: 900 },
  args: ['--start-maximized'],
});

const page = context.pages()[0] ?? (await context.newPage());
await page.goto('https://analytics.google.com/analytics/web/', { waitUntil: 'domcontentloaded' });

console.log(`
────────────────────────────────────────────────────────────────
  A browser window has opened at Google Analytics.

  1. Sign in with the Google account that has your GA4 property.

  2. OPTIONAL BUT RECOMMENDED — add Google's public demo property,
     so the documentation contains no private client data:
     https://analytics.google.com/analytics/web/demoAccount

  3. Once you can see a GA4 report, come back here and press Ctrl+C.
     The session is saved as you go; nothing else is needed.
────────────────────────────────────────────────────────────────
`);

// Keep the window open until the user stops the script.
await new Promise(() => {});
