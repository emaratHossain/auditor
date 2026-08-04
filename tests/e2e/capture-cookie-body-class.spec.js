import { test, expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync, statSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

/**
 * A page whose <body> carries a cookie-banner class.
 *
 * The overlay-hiding CSS matches on a substring of the class attribute, because
 * cookie banners name themselves a hundred different ways. WordPress's Cookie
 * Notice plugin — one of the most installed plugins there is — writes its state
 * onto the body element itself: <body class="home ... cookies-not-set">.
 *
 * `[class*="cookie" i] { display: none }` therefore hid the entire document.
 * Every measurement after it read 0, section finding fell through to equal
 * bands of zero height, and the run died on "Expected options.clip.height to be
 * greater than 0" — themelooks.com, audit 91. A page with a `consent` class
 * would have been worse: tall enough to photograph, and blank.
 *
 * The overlay rules may only ever hide something *inside* the body.
 */
function capture(html) {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-cookie-'))
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

// The body class is what Cookie Notice actually writes. The banner inside it is
// what we do want gone.
const PAGE = `<body class="home wp-singular cookies-not-set cookies-accepted">
  <div id="cookie-notice" style="position:fixed;bottom:0;height:120px;background:#111;color:#fff">
    We use cookies.
  </div>
  ${Array.from({ length: 5 }, (_, i) =>
    `<section style="height:700px"><h2>Section ${i + 1}</h2><p>Real copy in section ${i + 1}.</p></section>`
  ).join('')}
</body>`

test('a cookie-plugin class on the body does not hide the page', () => {
  const result = capture(PAGE)

  expect(result.ok, result.error ?? '').toBe(true)
  expect(result.page_height, 'the page collapsed to nothing').toBeGreaterThan(2000)
})

test('the sections of such a page are photographed, not blank', () => {
  const result = capture(PAGE)
  const desktop = result.sections.filter((s) => s.viewport === 'desktop')

  expect(desktop.length).toBeGreaterThan(0)
  for (const s of desktop) {
    expect(s.height, `${s.name} has no height`).toBeGreaterThan(0)
    expect(statSync(s.file).size, `${s.name} is an empty image`).toBeGreaterThan(1000)
  }
})

test('the cookie banner itself is still hidden', () => {
  const result = capture(PAGE)

  // The overlay CSS must keep doing its job — the banner is inside the body, so
  // scoping the rules to descendants leaves it matched.
  const copy = result.sections.map((s) => s.copy).filter(Boolean).flatMap((c) => JSON.stringify(c))
  expect(copy.join(' ')).not.toContain('We use cookies')
})

/**
 * The same zero-height dead end, reached the other way.
 *
 * Plenty of pages hide their body until their own JavaScript boots. When that
 * script is a third-party request we cut loose for missing its deadline, the
 * body stays hidden and every measurement reads 0 — the state that produced
 * "page.screenshot: Expected options.clip.height to be greater than 0" on the
 * user's screen. Whatever the cause, the person who pasted the URL gets a
 * sentence about the page, not a sentence about Playwright's argument checking.
 */
const HIDDEN_PAGE = `<body style="display:none">
  <section style="height:900px"><h2>Never shown</h2></section>
</body>`

test('a page that hides itself fails with a sentence, not a clip error', () => {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-hidden-'))
  const file = path.join(dir, 'page.html')
  writeFileSync(file, HIDDEN_PAGE)

  let out
  try {
    out = execFileSync('node', [
      'scripts/capture.mjs',
      '--url', `file://${file}`,
      '--out', path.join(dir, 'shots'),
      '--no-lighthouse',
    ], { encoding: 'utf8', timeout: 120000 })
  } catch (e) {
    out = e.stdout   // the script exits non-zero and still prints its JSON
  }

  const result = JSON.parse(out.trim())

  expect(result.ok).toBe(false)
  expect(result.error).not.toContain('clip.height')
  expect(result.error).toContain('rendered nothing we could measure')
})
