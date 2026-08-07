#!/usr/bin/env node
/**
 * Turns a rendered HTML file into a PDF, using the Chromium already installed
 * for screenshots. Cheaper than adding a second headless-browser dependency.
 *
 *   node scripts/pdf.mjs --in report.html --out report.pdf
 */
import { chromium } from 'playwright';
import { pathToFileURL } from 'node:url';

const args = Object.fromEntries(
  process.argv.slice(2).reduce((acc, cur, i, arr) => {
    if (cur.startsWith('--')) acc.push([cur.slice(2), arr[i + 1]]);
    return acc;
  }, [])
);

if (!args.in || !args.out) {
  console.error('Usage: pdf.mjs --in <html> --out <pdf>');
  process.exit(2);
}

const browser = await chromium.launch();
try {
  const page = await browser.newPage();
  await page.goto(pathToFileURL(args.in).href, { waitUntil: 'load' });
  await page.emulateMedia({ media: 'print' });
  await page.pdf({
    path: args.out,
    format: 'A4',
    printBackground: true,
    margin: { top: '14mm', bottom: '14mm', left: '12mm', right: '12mm' },
  });
  console.log(JSON.stringify({ ok: true }));
} catch (err) {
  console.log(JSON.stringify({ ok: false, error: String(err?.message ?? err) }));
  process.exitCode = 1;
} finally {
  await browser.close();
}
