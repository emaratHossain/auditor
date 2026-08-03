import { test, expect } from '@playwright/test'
import { execFile } from 'node:child_process'
import { createServer } from 'node:http'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { promisify } from 'node:util'

const run = promisify(execFile)

/**
 * Lighthouse is served over HTTP, never file://.
 *
 * Its performance metrics are meaningless on a file:// URL and some checks
 * refuse to run at all, so a passing test there would prove nothing about the
 * thing we actually ship.
 */
function serve(html) {
  const server = createServer((_req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/html' })
    res.end(html)
  })

  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => {
      resolve({ url: `http://127.0.0.1:${server.address().port}/`, close: () => server.close() })
    })
  })
}

/**
 * Async on purpose. execFileSync would block this process's event loop, and the
 * fixture server lives in this same process — so the subprocess's request could
 * never be answered and every capture would fail for a reason that has nothing
 * to do with capture.
 */
async function capture(url, extraArgs = []) {
  const { stdout } = await run('node', [
    'scripts/capture.mjs',
    '--url', url,
    '--out', path.join(mkdtempSync(path.join(tmpdir(), 'dropsense-lh-')), 'shots'),
    ...extraArgs,
  ], { encoding: 'utf8', timeout: 180000, maxBuffer: 10 * 1024 * 1024 })

  return JSON.parse(stdout.trim())
}

// Deliberately imperfect: no lang attribute, no alt text, faint tiny type.
const IMPERFECT = `<html><body>
  <section style="min-height:400px;width:1000px">
    <h1 style="color:#bbb;font-size:9px">Faint heading</h1>
    <img src="data:image/gif;base64,R0lGODlhAQABAAAAACw=">
    <a href="/x" class="btn">Go</a>
  </section>
  <section style="min-height:400px;width:1000px"><h2>Two</h2><p>y</p></section>
</body></html>`

test('capture returns real Lighthouse scores from the same visit', async () => {
  test.setTimeout(180000)
  const site = await serve(IMPERFECT)

  try {
    const result = await capture(site.url)

    expect(result.ok).toBe(true)
    expect(result.lighthouse).not.toBeNull()
    expect(typeof result.lighthouse.performance).toBe('number')
    expect(typeof result.lighthouse.accessibility).toBe('number')
    expect(result.lighthouse.accessibility).toBeLessThan(100)   // that page has real problems
    expect(Array.isArray(result.lighthouse.worst_checks)).toBe(true)
  } finally {
    site.close()
  }
})

test('capture still succeeds when Lighthouse is switched off', async () => {
  const site = await serve('<body><section style="min-height:400px;width:1000px"><h1>Hi</h1><p>x</p></section><section style="min-height:400px;width:1000px"><h2>Two</h2></section></body>')

  try {
    const result = await capture(site.url, ['--no-lighthouse'])

    // An audit must never die because a Lighthouse run did.
    expect(result.ok).toBe(true)
    expect(result.sections.length).toBeGreaterThan(0)
    expect(result.lighthouse).toBeNull()
  } finally {
    site.close()
  }
})
