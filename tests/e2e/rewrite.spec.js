import { test, expect } from '@playwright/test'

/**
 * Drives the real report screen against a real audit.
 *
 * Self-sufficient on purpose: it creates its own page and audit through the
 * API rather than depending on the seeder, so it cannot pass or fail for
 * reasons that have nothing to do with the rewrite panel.
 */
async function auditWithCopy(request) {
  const page = await request.post('/api/pages', {
    data: { name: `Rewrite e2e ${Date.now()}`, url: 'https://example.com/rewrite-e2e' },
  })
  const pageId = (await page.json()).data.id

  const audit = await request.post(`/api/pages/${pageId}/audits`, {
    data: { visitors: 18450, bounce_rate: 64.2, conversion_rate: 1.8, cta_click_rate: 2.1 },
  })

  return (await audit.json()).data.id
}

test('it shows the real headline and rewrites it on click', async ({ page, request }) => {
  const id = await auditWithCopy(request)
  await page.goto(`/audits/${id}`)

  const hero = page.getByTestId('section-card').first()

  // The page's own words, as text — not only as a picture.
  await expect(hero.getByTestId('copy-headline')).not.toBeEmpty()

  await hero.getByRole('button', { name: /rewrite this/i }).first().click()

  const variants = hero.getByTestId('rewrite-variant')
  await expect(variants.first()).toBeVisible({ timeout: 20000 })
  expect(await variants.count()).toBeGreaterThanOrEqual(2)

  // Every version carries a reason. A rewrite with no reason is a thesaurus.
  await expect(hero.getByTestId('rewrite-reason').first()).not.toBeEmpty()
})

test('a failed live call falls back to the stored versions and says so', async ({ page, request }) => {
  const id = await auditWithCopy(request)

  // Store one first, the way the seeded demo page ships with rewrites already
  // in place. That stored row is the whole wifi insurance policy.
  await request.post(`/api/audits/${id}/rewrite`, {
    data: { section: 'Hero', element: 'headline' },
  })

  await page.route('**/api/audits/*/rewrite', (route) => route.abort('failed'))
  await page.goto(`/audits/${id}`)

  const hero = page.getByTestId('section-card').first()
  await hero.getByRole('button', { name: /rewrite this/i }).first().click()

  await expect(hero.getByTestId('rewrite-variant').first()).toBeVisible()
  await expect(hero.getByTestId('rewrite-fallback')).toBeVisible()
})

test('there is never a blank area while it thinks', async ({ page, request }) => {
  const id = await auditWithCopy(request)

  await page.route('**/api/audits/*/rewrite', async (route) => {
    await new Promise((r) => setTimeout(r, 1500))
    route.continue()
  })

  await page.goto(`/audits/${id}`)

  const hero = page.getByTestId('section-card').first()
  await hero.getByRole('button', { name: /rewrite this/i }).first().click()

  await expect(hero.getByTestId('rewrite-loading')).toBeVisible()
})
