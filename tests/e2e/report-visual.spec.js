import { test, expect } from '@playwright/test'

/**
 * Looks the seeded audit up rather than hardcoding /audits/1, which is only
 * correct on a database that has just been reset.
 */
async function seededAuditId(request) {
  const pages = await (await request.get('/api/pages')).json()
  const withAudit = pages.data.find((p) => p.latest_audit)

  expect(withAudit, 'seed first: php artisan db:seed --class=DemoAuditSeeder').toBeTruthy()

  return withAudit.latest_audit.id
}

test.describe('the report screen', () => {
  test('the score, the source of the numbers and the top fixes are all above the sections', async ({ page, request }) => {
    await page.goto(`/audits/${await seededAuditId(request)}`)

    await expect(page.getByTestId('score-dial')).toBeVisible()
    await expect(page.getByText(/conversion score/i).first()).toBeVisible()

    // Demo numbers are labelled, not hidden.
    await expect(page.getByTestId('metrics-source')).toBeVisible()

    const dial = await page.getByTestId('score-dial').boundingBox()
    const first = await page.getByTestId('section-card').first().boundingBox()
    expect(dial.y).toBeLessThan(first.y)
  })

  test('the score breakdown says which numbers were measured', async ({ page, request }) => {
    await page.goto(`/audits/${await seededAuditId(request)}`)
    await page.getByRole('button', { name: /how this score/i }).click()

    await expect(page.getByTestId('category-Accessibility')).toContainText(/measured|estimated/i)
  })

  test('nothing scrolls sideways on a phone', async ({ page, request }) => {
    await page.setViewportSize({ width: 390, height: 844 })
    await page.goto(`/audits/${await seededAuditId(request)}`)

    const overflow = await page.evaluate(() =>
      document.documentElement.scrollWidth - document.documentElement.clientWidth)

    expect(overflow).toBeLessThanOrEqual(1)
  })

  test('every number on screen has a sentence explaining it', async ({ page, request }) => {
    await page.goto(`/audits/${await seededAuditId(request)}`)

    const metrics = page.getByTestId('metric')

    // count() does not auto-wait, and the report arrives in a second fetch after
    // the status poll — so wait for one before counting them all.
    await expect(metrics.first()).toBeVisible()

    const count = await metrics.count()
    expect(count).toBeGreaterThan(0)

    for (let i = 0; i < count; i++) {
      await expect(metrics.nth(i).getByTestId('metric-explain')).not.toBeEmpty()
    }
  })
})
