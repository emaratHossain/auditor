import { test, expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync, statSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

/**
 * A phone screenshot of a very long page.
 *
 * `fullPage: true` with no clip asks Chromium for one image the height of the
 * whole document. Past its limit — a little over 16,000px — it writes a file of
 * zero bytes and raises nothing. The audit then died at the AI call with
 * "Unable to process input image (400)", several minutes and one browser launch
 * after the actual mistake. This happened on stripe.com, whose phone layout runs
 * past 20,000px, and it will happen on any long marketing page.
 *
 * The desktop sections have clamped their height since they were written. The
 * phone shot is the one that did not.
 */
function capture(html) {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-tall-'))
  const file = path.join(dir, 'page.html')
  writeFileSync(file, html)

  const out = execFileSync('node', [
    'scripts/capture.mjs',
    '--url', `file://${file}`,
    '--out', path.join(dir, 'shots'),
    '--no-lighthouse',
  ], { encoding: 'utf8', timeout: 120000 })

  return JSON.parse(out.trim())
}

// 40 blocks of 600px is 24,000px on a 390px-wide phone — comfortably past the limit.
const TALL_PAGE = `<body>
  <h1>Tall page</h1>
  ${Array.from({ length: 40 }, (_, i) =>
    `<section style="height:600px"><h2>Block ${i + 1}</h2><p>Some copy in block ${i + 1}.</p></section>`
  ).join('')}
</body>`

test('a page too tall for one screenshot still yields a usable phone image', () => {
  const result = capture(TALL_PAGE)
  const mobile = result.sections.find((s) => s.viewport === 'mobile')

  expect(mobile, 'the phone shot must still be captured').toBeTruthy()
  expect(statSync(mobile.file).size, 'a zero-byte image is what the AI call rejects').toBeGreaterThan(1000)
})

test('the real page height is still reported even when the image is clamped', () => {
  const result = capture(TALL_PAGE)
  const mobile = result.sections.find((s) => s.viewport === 'mobile')

  // The scroll depth rules read this, so clamping the picture must not shorten
  // the page the numbers describe.
  expect(mobile.page_height).toBeGreaterThan(16_384)
})
