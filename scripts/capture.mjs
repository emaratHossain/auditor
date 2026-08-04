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
import lighthouse from 'lighthouse';
import { mkdir } from 'node:fs/promises';
import path from 'node:path';

const MAX_SECTIONS = 6;          // cost and waiting time stay bounded
const LONG_EDGE = 1568;          // a full-size image can cost ~3x as many image tokens
const MAX_CTAS = 3;              // a footer with forty links is not forty rewrite targets
const CTA_MAX_WORDS = 6;         // "Start free trial" is a CTA; a paragraph is not
const LH_PORT = 9222;            // the debugging port Lighthouse attaches to
const LH_TIMEOUT_MS = 90000;     // it is allowed to fail; it is not allowed to hang
const WORST_CHECKS = 3;
const NAV_TIMEOUT_MS = 45000;        // getting a document at all
const DOM_READY_BUDGET_MS = 12000;   // then carry on whether or not it fires
const THIRD_PARTY_TIMEOUT_MS = 8000; // a cross-origin request that misses this is dropped
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

/**
 * Give every third-party request a deadline.
 *
 * A single dead CDN request poisons everything downstream: DOMContentLoaded
 * never fires, and document.fonts.ready never resolves, so even page.screenshot
 * hangs. arraytics.com does this with a popup widget while serving its own HTML
 * in under two seconds.
 *
 * Only cross-origin requests are policed, and only by time — a slow-but-alive
 * CDN bundle still loads, because plenty of real sites render their hero from
 * one. What gets cut loose is the request that is never coming back.
 */
async function deadlineThirdParties(page, pageUrl) {
  const ownHost = new URL(pageUrl).host;

  await page.route('**/*', async (route) => {
    const url = route.request().url();

    let host;
    try { host = new URL(url).host; } catch { return route.continue(); }

    if (host === ownHost) return route.continue();

    try {
      const response = await route.fetch({ timeout: THIRD_PARTY_TIMEOUT_MS });
      await route.fulfill({ response });
    } catch {
      // Never coming back. Let the parser and the font loader move on.
      await route.abort().catch(() => {});
    }
  });
}

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
    /** Cut at a word boundary. "…to grow your re" reads like a bug. */
    const trim = (s, max = 42) => {
      const clean = s.replace(/\s+/g, ' ').trim();
      if (clean.length <= max) return clean;
      const cut = clean.slice(0, max);
      const space = cut.lastIndexOf(' ');
      return (space > 12 ? cut.slice(0, space) : cut).replace(/[,.;:—-]$/, '');
    };

    const nodes = Array.from(document.querySelectorAll('section, main > div, [class*="section" i], header, footer'));
    const seen = [];
    for (const el of nodes) {
      const r = el.getBoundingClientRect();
      const top = Math.round(r.top + window.scrollY);
      const height = Math.round(r.height);
      if (height < 200 || r.width < 200) continue;              // too small to be a section

      // A fixed 120px gap is not enough on a 14,000px page: stripe.com produced
      // two cards with the same name and the same screenshot, one percent apart,
      // which reads as a broken report. Scale the gap with the page, and reject
      // a repeated heading outright — the same words twice is the same section.
      const minGap = Math.max(160, Math.round(document.body.scrollHeight * 0.02));
      if (seen.some((s) => Math.abs(s.position - top) < minGap)) continue;

      const heading = el.querySelector('h1, h2, h3')?.innerText?.trim();
      const name = trim(heading || el.id || el.getAttribute('aria-label') || '');

      if (name && seen.some((s) => s.name === name)) continue;

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

/**
 * Lighthouse, against the browser we already have open.
 *
 * It is allowed to fail. A timeout or a crash returns null, capture still
 * succeeds, and the score falls back to the old estimates labelled as such —
 * an audit must never die because a Lighthouse run hung.
 *
 * Note on words: Lighthouse calls its individual checks "audits". In this
 * codebase an audit is a row in the audits table, so these are checks.
 */
async function runLighthouse(url) {
  let timer;

  try {
    const result = await Promise.race([
      lighthouse(url, {
        port: LH_PORT,
        output: 'json',
        logLevel: 'silent',
        onlyCategories: ['performance', 'accessibility', 'best-practices', 'seo'],
      }),
      new Promise((_, reject) => {
        timer = setTimeout(() => reject(new Error('Lighthouse timed out')), LH_TIMEOUT_MS);
      }),
    ]);

    const cats = result?.lhr?.categories;
    if (!cats) return null;

    const pct = (c) => (c?.score == null ? null : Math.round(c.score * 100));

    const worst = Object.values(result.lhr.audits ?? {})
      .filter((a) => typeof a.score === 'number' && a.score < 0.9 && a.title)
      .sort((a, b) => a.score - b.score)
      .slice(0, WORST_CHECKS)
      .map((a) => ({ id: a.id, title: a.title, score: Math.round(a.score * 100) }));

    return {
      performance: pct(cats.performance),
      accessibility: pct(cats.accessibility),
      best_practices: pct(cats['best-practices']),
      seo: pct(cats.seo),
      worst_checks: worst,
    };
  } catch {
    return null;
  } finally {
    clearTimeout(timer);
  }
}

/**
 * Open a page without letting one dead resource sink the whole audit.
 *
 * Three levels, each weaker than the last, because real landing pages fail in
 * all three ways:
 *
 *   'load' waits for every image, font and iframe. stripe.com measured 31.8s.
 *   'domcontentloaded' never fires at all if a blocking third-party script in
 *   the head hangs — arraytics.com does exactly this with a popup widget, and
 *   the page renders perfectly while the event never comes.
 *
 * So we wait only for the navigation to commit, which means we have a document,
 * then give DOMContentLoaded a short budget and carry on regardless. The scroll
 * pass and fonts.ready below are what actually make the screenshot correct.
 */
async function openPage(page, url) {
  await deadlineThirdParties(page, url);

  const response = await page.goto(url, { waitUntil: 'commit', timeout: NAV_TIMEOUT_MS });

  const status = response?.status() ?? 0;
  if (status >= 400) {
    throw new Error(`That address returned ${status}.`);
  }

  // Best effort. A page that never fires it is still worth photographing.
  await page.waitForLoadState('domcontentloaded', { timeout: DOM_READY_BUDGET_MS }).catch(() => {});

  // The real readiness signal for a screenshot is simply: is there a body yet.
  // If a blocking script stalled the parser before it ever reached one, there
  // is genuinely nothing rendered to photograph, and that deserves a sentence
  // rather than a null-property crash three functions later.
  // locator().waitFor, not waitForSelector: the latter blocks on Playwright's
  // internal navigation barrier, which on a page whose load never settles took
  // 9.3 seconds here against 7 milliseconds for the locator. Same question,
  // two orders of magnitude apart.
  try {
    await page.locator('body').waitFor({ state: 'attached', timeout: DOM_READY_BUDGET_MS });
  } catch {
    throw new Error('That page never rendered anything we could photograph — a script on it is not responding.');
  }

  return response;
}

const skipLighthouse = process.argv.includes('--no-lighthouse');

const browser = await chromium.launch({
  args: ['--no-sandbox', `--remote-debugging-port=${LH_PORT}`],
});

try {
  await mkdir(args.out, { recursive: true });

  const ctx = await browser.newContext({ viewport: DESKTOP, deviceScaleFactor: 1 });
  const page = await ctx.newPage();

  const started = Date.now();
  await openPage(page, args.url);
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
  await openPage(mpage, args.url);
  await hideOverlays(mpage);
  await mpage.waitForTimeout(500);

  const mobileFile = path.join(args.out, 'page-mobile.webp');
  const mobileHeight = await mpage.evaluate(() => document.body.scrollHeight);

  // Asked for one image taller than it can allocate, Chromium writes zero bytes
  // and raises nothing. The audit then survived capture, survived correlation,
  // and died at the AI call with "Unable to process input image (400)" — minutes
  // and one browser launch after the mistake. stripe.com's phone layout is over
  // 20,000px; plenty of marketing pages are.
  //
  // The clamp costs nothing that matters. This shot exists so the model can judge
  // the phone layout, and that judgement is made at the top of the page — nobody
  // reads the 18,000th pixel. mobileHeight below still reports the real height,
  // so the scroll-depth numbers are unaffected.
  const MOBILE_SHOT_LIMIT = 12_000;

  await mpage.screenshot({
    path: mobileFile,
    type: 'webp',
    quality: 78,
    fullPage: true,
    clip: { x: 0, y: 0, width: MOBILE.width, height: Math.min(mobileHeight, MOBILE_SHOT_LIMIT) },
    scale: 'css',
  });

  captured.push({
    name: sections[0]?.name ?? 'Whole page',
    viewport: 'mobile', file: mobileFile,
    position: 0, height: mobileHeight, page_height: mobileHeight, sort_order: 0,
    // The phone shot is the whole page, so it has no section copy of its own —
    // the desktop rows already carry every word on the page.
    copy: null,
  });

  // After the screenshots, so a slow Lighthouse run never costs us the pictures.
  const lighthouseScores = skipLighthouse ? null : await runLighthouse(args.url);

  console.log(JSON.stringify({
    ok: true, how, load_ms: loadMs, page_height: pageHeight,
    long_edge: LONG_EDGE, lighthouse: lighthouseScores, sections: captured,
  }));
} catch (err) {
  console.log(JSON.stringify({ ok: false, error: String(err?.message ?? err) }));
  process.exitCode = 1;
} finally {
  await browser.close();
}
