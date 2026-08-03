import { test, expect } from '@playwright/test'
import { execFileSync } from 'node:child_process'
import { mkdtempSync, writeFileSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'

/**
 * The crawl is driven through the real script against a real local page,
 * because what it has to survive is real markup — not a mock of it.
 */
function capture(html) {
  const dir = mkdtempSync(path.join(tmpdir(), 'dropsense-'))
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

const SECTION = (inner) =>
  `<section style="min-height:400px;width:1000px">${inner}</section>`

test('it reads the headline, the subhead and the buttons', () => {
  const result = capture(`<body>
    ${SECTION(`
      <h1>Ship faster than your competitors</h1>
      <p>One dashboard for the whole team.</p>
      <a href="/signup" class="btn">Start free trial</a>
    `)}
    ${SECTION('<h2>Pricing</h2><p>Simple plans.</p><button>Buy now</button>')}
  </body>`)

  expect(result.ok).toBe(true)

  const first = result.sections.find((s) => s.viewport === 'desktop')
  expect(first.copy.headline.text).toBe('Ship faster than your competitors')
  expect(first.copy.subhead.text).toBe('One dashboard for the whole team.')
  expect(first.copy.ctas[0].text).toBe('Start free trial')
  expect(first.copy.ctas[0].selector).toBeTruthy()
})

test('a page with no heading still yields a headline', () => {
  const result = capture(`<body>${SECTION(`
    <div style="font-size:48px">The biggest words on the screen</div>
    <div style="font-size:14px">Much smaller words.</div>
    <a href="/x" class="button">Go</a>
  `)}${SECTION('<h2>Second</h2><p>x</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')
  expect(first.copy.headline.text).toBe('The biggest words on the screen')
})

test('a footer with many links does not become many rewrite targets', () => {
  const links = Array.from({ length: 30 }, (_, i) => `<a href="/l${i}">Link ${i}</a>`).join('')
  const result = capture(`<body>${SECTION(`<h2>Hero</h2><p>x</p>${links}`)}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')
  expect(first.copy.ctas.length).toBeLessThanOrEqual(3)
})

/**
 * Found by running the real thing against stripe.com: marketing sites wrap
 * whole feature cards in a <button>, and an unchecked button became a
 * 300-character "button label" on its way to the rewriter.
 */
test('a card wrapped in a button is not mistaken for a button label', () => {
  const result = capture(`<body>${SECTION(`
    <h1>Real headline</h1>
    <p>Real subhead.</p>
    <button>Accept and optimize payments globally, online and in person, with a single integration that scales</button>
    <button>Get started</button>
  `)}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')

  expect(first.copy.ctas.map((c) => c.text)).toEqual(['Get started'])
})

test('an empty button never reaches the rewriter', () => {
  const result = capture(`<body>${SECTION(`
    <h1>Real headline</h1>
    <p>Real subhead.</p>
    <button></button>
    <a href="/go" class="btn">Go now</a>
  `)}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')

  expect(first.copy.ctas.every((c) => c.text.trim() !== '')).toBe(true)
})

/** A real h1 often wraps its subtitle, and innerText runs them together. */
test('the headline is the first line, not the whole block', () => {
  const result = capture(`<body>${SECTION(`
    <h1>Ship faster than your competitors. Accept payments in every country you sell in.</h1>
    <p>Real subhead.</p>
  `)}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  const first = result.sections.find((s) => s.viewport === 'desktop')

  expect(first.copy.headline.text).toBe('Ship faster than your competitors.')
})

test('the mobile shot carries no copy of its own', () => {
  const result = capture(`<body>${SECTION('<h1>Hi</h1><p>x</p>')}${SECTION('<h2>Two</h2><p>y</p>')}</body>`)

  expect(result.sections.find((s) => s.viewport === 'mobile').copy).toBeNull()
})
