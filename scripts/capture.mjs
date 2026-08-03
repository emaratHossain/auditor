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
const MAX_CTAS = 3;              // a footer with forty links is not forty rewrite targets
const CTA_MAX_WORDS = 6;         // "Start free trial" is a CTA; a paragraph is not
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

/**
 * The page's own words, section by section.
 *
 * Screenshots give pixels. You cannot rewrite a headline you never read, so the
 * rewrite feature depends entirely on this — and it happens here because the
 * browser is already open on a loaded page.
 */
async function readCopy(page, sections) {
  return page.evaluate(
    ({ sections, MAX_CTAS, CTA_MAX_WORDS }) => {
      /** A stable-enough selector to find this element again later. */
      const selectorFor = (el) => {
        if (el.id) return `#${CSS.escape(el.id)}`;
        const cls = (el.className || '').toString().trim().split(/\s+/).filter(Boolean)[0];
        const base = cls ? `${el.tagName.toLowerCase()}.${CSS.escape(cls)}` : el.tagName.toLowerCase();
        const siblings = Array.from(el.parentElement?.children ?? []);
        return `${base}:nth-child(${siblings.indexOf(el) + 1})`;
      };

      const entry = (el) => ({
        text: (el.innerText || el.textContent || '').trim().replace(/\s+/g, ' ').slice(0, 300),
        tag: el.tagName.toLowerCase(),
        selector: selectorFor(el),
      });

      /** Everything rendered between two Y offsets. */
      const within = (top, bottom) =>
        Array.from(document.body.querySelectorAll('*')).filter((el) => {
          const r = el.getBoundingClientRect();
          const y = r.top + window.scrollY;
          return y >= top - 1 && y < bottom && r.height > 0 && r.width > 0;
        });

      return sections.map(({ position, height }) => {
        const nodes = within(position, position + height);

        let headline = nodes.find(
          (el) => /^h[1-3]$/.test(el.tagName.toLowerCase()) && (el.innerText || '').trim(),
        );

        // Webflow and React marketing pages often have no h1 where you expect
        // one — the biggest words on screen are a styled div. Less precise,
        // never empty.
        if (!headline) {
          const sized = nodes
            .filter((el) => el.children.length === 0 && (el.innerText || '').trim())
            .map((el) => ({ el, size: parseFloat(getComputedStyle(el).fontSize) || 0 }))
            .sort((a, b) => b.size - a.size);
          headline = sized[0]?.el;
        }

        const after = headline ? nodes.indexOf(headline) : -1;
        const subhead = nodes
          .slice(after + 1)
          .find((el) => el.tagName.toLowerCase() === 'p' && (el.innerText || '').trim());

        const ctas = nodes
          .filter((el) => {
            const tag = el.tagName.toLowerCase();
            if (tag !== 'button' && tag !== 'a') return false;

            // The word cap applies to buttons too. Real marketing sites wrap
            // whole feature cards in a <button>, and without this a "button
            // label" comes back as 300 characters of product copy — which then
            // gets handed to the rewriter as though it were a call to action.
            const text = (el.innerText || '').trim();
            if (!text || text.split(/\s+/).length > CTA_MAX_WORDS) return false;

            if (tag === 'button') return true;

            // A link that has been styled to look like a button.
            return /btn|button|cta/i.test(el.className || '') ||
              getComputedStyle(el).display !== 'inline';
          })
          .slice(0, MAX_CTAS)
          .map(entry)
          .filter((c) => c.text !== '');

        // A real h1 often wraps a subtitle too, and innerText joins them into
        // one run-on line. The headline is the first line of it.
        const firstLine = (e) => {
          const line = e.text.split(/(?<=[.!?])\s+|\s{2,}/)[0].trim();
          return { ...e, text: line || e.text };
        };

        return {
          headline: headline ? firstLine(entry(headline)) : null,
          subhead: subhead ? entry(subhead) : null,
          ctas,
        };
      });
    },
    { sections, MAX_CTAS, CTA_MAX_WORDS },
  );
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
  const copyPerSection = await readCopy(page, sections);

  const captured = [];

  for (const [i, s] of sections.entries()) {
    const height = Math.max(120, Math.min(s.height, 2400));
    const file = path.join(args.out, `${slug(s.name)}-${i}-desktop.webp`);

    // A section below the fold is outside the viewport, and a clip without
    // fullPage is measured against the viewport rather than the page — so every
    // real landing page failed here with "clipped area is either empty or
    // outside the resulting image". Short synthetic test pages did not, because
    // all of their sections happened to fit inside the first 900px.
    //
    // With fullPage the clip is in page coordinates, which is what the measured
    // section positions already are.
    const available = Math.max(0, pageHeight - s.position);

    if (available < 40) continue;   // the page shrank under us; nothing to shoot

    await page.screenshot({
      path: file,
      type: 'webp',
      quality: 78,
      fullPage: true,
      clip: { x: 0, y: s.position, width: DESKTOP.width, height: Math.min(height, available) },
      scale: 'css',
    });

    captured.push({
      name: s.name, viewport: 'desktop', file,
      position: s.position, height, page_height: pageHeight, sort_order: i,
      copy: copyPerSection[i] ?? null,
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
    // The phone shot is the whole page, so it has no section copy of its own —
    // the desktop rows already carry every word on the page.
    copy: null,
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
