import { defineConfig, devices } from '@playwright/test'

/**
 * Drives the real app in a real browser. The audit runs synchronously here
 * (QUEUE_CONNECTION=sync) so a test does not need a separate worker process.
 */
export default defineConfig({
  testDir: './tests/e2e',
  timeout: 60_000,
  fullyParallel: false,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: process.env.E2E_URL ?? 'http://127.0.0.1:8901',
    trace: 'retain-on-failure',
  },

  /*
   * Starts its own server, with the stub drivers forced on.
   *
   * These specs exercise the report screen, not the browser automation — the
   * capture scripts are driven directly by their own specs. Left to inherit
   * .env, switching CAPTURE_DRIVER to playwright made every audit these tests
   * create launch a real browser against a real site, and they timed out.
   *
   * Set E2E_URL to point at a server you started yourself instead.
   */
  webServer: process.env.E2E_URL ? undefined : {
    command: 'php artisan serve --port=8901',
    url: 'http://127.0.0.1:8901/api/pages',
    reuseExistingServer: true,
    timeout: 60_000,
    env: {
      QUEUE_CONNECTION: 'sync',
      CAPTURE_DRIVER: 'stub',
      AI_DRIVER: 'stub',
      AI_REWRITE_DRIVER: 'stub',
    },
  },
  projects: [
    { name: 'desktop', use: { ...devices['Desktop Chrome'] } },
    { name: 'phone', use: { viewport: { width: 390, height: 844 } } },
  ],
})
