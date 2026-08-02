#!/usr/bin/env node
/**
 * Photographs a landing page, section by section.
 *
 * Takes a URL and an output directory, prints JSON describing what it captured.
 * Laravel stays in charge; this only drives the browser.
 *
 *   node scripts/capture.mjs --url https://example.com --out storage/app/public/screenshots/12
 */
import { chromium } from 'playwright';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const MAX_SECTIONS = 6;          // cost and waiting time stay bounded
const LONG_EDGE = 1568;          // a full-size image can cost ~3x as many image tokens
const DESKTOP = { width: 1440, height: 900 };
const MOBILE = { width: 390, height: 844 };

const args = Object.fromEntries(
  process.argv.slice(2).reduce((acc, cur, i, arr) => {
    if (cur.startsWith('--')) acc.push([cur.slice(2), arr[i + 1]]);
    return acc;
  }, [])
);

if (!args.url || !args.out) {
  console.error('Usage: capture.mjs --url <url> --out <dir> [--selectors "a,b,c"]');
  process.exit(2);
}

const slug = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') || 'section';

/** Hide the things that teach the AI nothing about the page underneath. */
async function hideOverlays(page) {
  await page.addStyleTag({
    content: `
      [id*="cookie" i], [class*="cookie" i], [id*="consent" i], [class*="consent" i],
      [aria-label*="cookie" i], [class*="gdpr" i], [id*="onetrust" i], #CybotCookiebotDialog,
      [class*="newsletter-popup" i], [class*="modal-backdrop" i] { display: none !important; }
      html { scroll-behavior: auto !important; }
      *, *::before, *::after { animation: none !important; transition: none !important; }
    `,
  });
}

/** Three levels, in order: the user's selectors, then landmarks, then equal bands. */
async function findSections(page, selectors) {
  if (selectors?.length) {
    const found = await page.evaluate((sels) => {
      const out = [];
      for (const sel of sels) {
        const el = document.querySelector(sel);
        if (!el) continue;
        const r = el.getBoundingClientRect();
        out.push({ name: sel, position: Math.round(r.top + window.scrollY), height: Math.round(r.height) });
      }
      return out;
    }, selectors);
    if (found.length) return { sections: found, how: 'selectors' };
  }

  const landmarks = await page.evaluate(() => {
    const nodes = Array.from(document.querySelectorAll('section, main > div, [class*="section" i], header, footer'));
    const seen = [];
    for (const el of nodes) {
      const r = el.getBoundingClientRect();
      const top = Math.round(r.top + window.scrollY);
      const height = Math.round(r.height);
      if (height < 200 || r.width < 200) continue;              // too small to be a section
      if (seen.some((s) => Math.abs(s.position - top) < 120)) continue;  // nested duplicate
      const heading = el.querySelector('h1, h2, h3')?.innerText?.trim();
      const name = (heading || el.id || el.getAttribute('aria-label') || '').slice(0, 40);
      seen.push({ name: name || `Section ${seen.length + 1}`, position: top, height });
    }
    return seen.sort((a, b) => a.position - b.position);
  });

  if (landmarks.length >= 2) return { sections: landmarks.slice(0, MAX_SECTIONS), how: 'landmarks' };

  const pageHeight = await page.evaluate(() => document.body.scrollHeight);
  const band = Math.round(pageHeight / MAX_SECTIONS);
  return {
    sections: Array.from({ length: MAX_SECTIONS }, (_, i) => ({
      name: `Part ${i + 1} of the page`,
      position: i * band,
      height: band,
    })),
    how: 'bands',
  };
}

const browser = await chromium.launch({ args: ['--no-sandbox'] });

try {
  await mkdir(args.out, { recursive: true });

  const ctx = await browser.newContext({ viewport: DESKTOP, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  const started = Date.now();
  await page.goto(args.url, { waitUntil: 'load', timeout: 45000 });
  const loadMs = Date.now() - started;

  await hideOverlays(page);
  await page.evaluate(async () => {
    // Scroll the whole page once so lazy images actually load, then go back up.
    await new Promise((resolve) => {
      let y = 0;
      const step = () => {
        window.scrollTo(0, (y += 400));
        if (y < document.body.scrollHeight) setTimeout(step, 40);
        else { window.scrollTo(0, 0); resolve(); }
      };
      step();
    });
  });
  await page.waitForTimeout(600);
  try { await page.evaluate(() => document.fonts.ready); } catch {}

  const pageHeight = await page.evaluate(() => document.body.scrollHeight);
  const selectors = args.selectors ? args.selectors.split(',').map((s) => s.trim()).filter(Boolean) : [];
  const { sections, how } = await findSections(page, selectors);

  const captured = [];

  for (const [i, s] of sections.entries()) {
    const height = Math.max(120, Math.min(s.height, 2400));
    const file = path.join(args.out, `${slug(s.name)}-${i}-desktop.webp`);

    await page.screenshot({
      path: file,
      type: 'webp',
      quality: 78,
      clip: { x: 0, y: s.position, width: DESKTOP.width, height: Math.min(height, pageHeight - s.position || height) },
      scale: 'css',
    });

    captured.push({
      name: s.name, viewport: 'desktop', file,
      position: s.position, height, page_height: pageHeight, sort_order: i,
    });
  }

  // One full-page phone shot. Mobile is usually where the conversion is lost.
  const mctx = await browser.newContext({ viewport: MOBILE, deviceScaleFactor: 1, isMobile: true, hasTouch: true });
  const mpage = await mctx.newPage();
  await mpage.goto(args.url, { waitUntil: 'load', timeout: 45000 });
  await hideOverlays(mpage);
  await mpage.waitForTimeout(500);

  const mobileFile = path.join(args.out, 'page-mobile.webp');
  await mpage.screenshot({ path: mobileFile, type: 'webp', quality: 78, fullPage: true });
  const mobileHeight = await mpage.evaluate(() => document.body.scrollHeight);

  captured.push({
    name: sections[0]?.name ?? 'Whole page',
    viewport: 'mobile', file: mobileFile,
    position: 0, height: mobileHeight, page_height: mobileHeight, sort_order: 0,
  });

  console.log(JSON.stringify({
    ok: true, how, load_ms: loadMs, page_height: pageHeight,
    long_edge: LONG_EDGE, sections: captured,
  }));
} catch (err) {
  console.log(JSON.stringify({ ok: false, error: String(err?.message ?? err) }));
  process.exitCode = 1;
} finally {
  await browser.close();
}
