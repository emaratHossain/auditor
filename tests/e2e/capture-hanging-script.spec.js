import { test, expect } from '@playwright/test'
import { execFile } from 'node:child_process'
import { createServer } from 'node:http'
import { mkdtempSync } from 'node:fs'
import { tmpdir } from 'node:os'
import path from 'node:path'
import { promisify } from 'node:util'

const run = promisify(execFile)

/**
 * A real landing page usually loads a handful of third-party scripts — chat
 * widgets, ad pixels, popup tools. When one of them hangs, a blocking <script>
 * in the head stops DOMContentLoaded from ever firing, even though the page
 * itself has rendered perfectly well.
 *
 * arraytics.com does exactly this (app.poper.ai/share/poper.js). Capture must
 * photograph what rendered rather than wait forever for a script that is never
 * coming.
 */
function serveWithHangingScript() {
  const server = createServer((req, res) => {
    if (req.url.startsWith('/hangs-forever.js')) {
      // Accept the connection and then never answer, exactly like a dead CDN.
      return
    }

    // The script sits after the content, which is the shape arraytics.com has:
    // the page renders completely, and DOMContentLoaded still never fires
    // because the parser is stuck waiting on a script that is never coming.
    res.writeHead(200, { 'Content-Type': 'text/html' })
    res.end(`<html><head></head><body>
      <section style="min-height:400px;width:1000px">
        <h1>The page still rendered</h1>
        <p>Even though a script is hanging.</p>
        <a href="/go" class="btn">Get started</a>
      </section>
      <section style="min-height:400px;width:1000px"><h2>Second</h2><p>More.</p></section>
      <script src="/hangs-forever.js"></script>
    </body></html>`)
  })

  return new Promise((resolve) => {
    server.listen(0, '127.0.0.1', () => {
      resolve({
        url: `http://127.0.0.1:${server.address().port}/`,
        close: () => server.close(),
      })
    })
  })
}

test('a hanging third-party script does not stop the capture', async () => {
  test.setTimeout(180000)

  const site = await serveWithHangingScript()

  try {
    const { stdout } = await run('node', [
      'scripts/capture.mjs',
      '--url', site.url,
      '--out', path.join(mkdtempSync(path.join(tmpdir(), 'dropsense-hang-')), 'shots'),
      '--no-lighthouse',
    ], { encoding: 'utf8', timeout: 150000, maxBuffer: 10 * 1024 * 1024 })

    const result = JSON.parse(stdout.trim())

    expect(result.ok).toBe(true)

    const first = result.sections.find((s) => s.viewport === 'desktop')
    expect(first.copy.headline.text).toBe('The page still rendered')
    expect(first.copy.ctas[0].text).toBe('Get started')
  } finally {
    site.close()
  }
})
