import { test, expect } from '@playwright/test'

/**
 * The demo path itself. If this passes, the thing you show on Sunday works.
 */
test('paste a URL, fill in the numbers, read the report', async ({ page }) => {
  await page.goto('/')

  await expect(
    page.getByRole('heading', { name: 'Paste a landing page. Find out where visitors leave, and why.' }),
  ).toBeVisible()

  const name = `Spring campaign ${Date.now()}`
  await page.getByPlaceholder('Name, e.g. Spring campaign').fill(name)
  await page.getByPlaceholder('https://example.com/landing').fill('https://example.com/spring')
  await page.getByRole('button', { name: 'Add page' }).click()

  const row = page.locator('li', { hasText: name })
  await expect(row).toBeVisible()

  await row.getByRole('button', { name: 'Run audit' }).click()

  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('heading', { name: `Run an audit on ${name}` })).toBeVisible()

  // Only the three required numbers — the optional ones stay blank on purpose.
  await dialog.locator('input[name="visitors"]').fill('12400')
  await dialog.locator('input[name="bounce_rate"]').fill('62')
  await dialog.locator('input[name="conversion_rate"]').fill('1.8')
  await dialog.getByRole('button', { name: 'Run audit' }).click()

  await expect(page).toHaveURL(/\/audits\/\d+/)

  // The score, and the fixes that matter most, right at the top.
  await expect(page.getByTestId('score-dial')).toBeVisible({ timeout: 30_000 })
  await expect(page.getByRole('heading', { name: 'Fix these first' })).toBeVisible()

  // Every fix must carry a number as evidence — the whole point of the product.
  const evidence = page.locator('dt', { hasText: 'Evidence' }).first()
  await expect(evidence).toBeVisible()
  const firstFix = await page.locator('ol > li').first().innerText()
  expect(firstFix).toMatch(/\d/)

  // The picture beside the words.
  await expect(page.getByRole('heading', { name: /section by section/i })).toBeVisible()
  await expect(page.locator('img').first()).toBeVisible()

  // The score can be taken apart.
  await page.getByRole('button', { name: /how this score is built/i }).click()
  await expect(page.getByText('Main button effectiveness')).toBeVisible()
  // The caveat is gone when Lighthouse measured it, so assert the label that
  // is always there and always says which of the two this number is.
  await expect(page.getByTestId('category-Accessibility')).toContainText(/measured|estimated/i)
})

test('a blank address is refused with the message under the field', async ({ page }) => {
  await page.goto('/')
  await page.getByPlaceholder('Name, e.g. Spring campaign').fill('No address')
  await page.getByRole('button', { name: 'Add page' }).click()

  await expect(page.getByText(/paste the web address/i)).toBeVisible()
})

test('the pages screen says so when there is nothing yet', async ({ page }) => {
  await page.goto('/')
  await expect(page.getByRole('heading', { name: 'Your pages' })).toBeVisible()
  // Either a list or a stated empty state — never a blank white area.
  const rows = await page.locator('li').count()
  const empty = await page.getByText('No pages yet').count()
  expect(rows + empty).toBeGreaterThan(0)
})
