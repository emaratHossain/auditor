import { test, expect } from '@playwright/test'

/**
 * Removing a page you no longer want.
 *
 * The list only ever grew — an e2e run, a URL typed wrong, a campaign that
 * ended. Deleting is the one action here that cannot be undone, so the dialog
 * has to name the page, and Cancel has to genuinely mean nothing happened.
 */
async function addPage(page, name) {
  await page.goto('/')
  await page.getByPlaceholder('Name, e.g. Spring campaign').fill(name)
  await page.getByPlaceholder('https://example.com/landing').fill('https://example.com/delete-me')
  await page.getByRole('button', { name: 'Add page' }).click()

  const row = page.locator('li', { hasText: name })
  await expect(row).toBeVisible()

  return row
}

test('a page can be deleted, and stays deleted', async ({ page }) => {
  const name = `Delete me ${Date.now()}`
  const row = await addPage(page, name)

  await row.getByRole('button', { name: `Delete ${name}` }).click()

  const dialog = page.getByRole('dialog')
  await expect(dialog.getByRole('heading', { name: `Delete “${name}”?` })).toBeVisible()
  await expect(dialog.getByText('It has no reports yet')).toBeVisible()

  await dialog.getByRole('button', { name: 'Delete page' }).click()

  await expect(page.locator('li', { hasText: name })).toHaveCount(0)

  // Gone from the server, not just from this render.
  await page.reload()
  await expect(page.locator('li', { hasText: name })).toHaveCount(0)
})

test('cancelling leaves the page alone', async ({ page }) => {
  const name = `Keep me ${Date.now()}`
  const row = await addPage(page, name)

  await row.getByRole('button', { name: `Delete ${name}` }).click()
  await page.getByRole('dialog').getByRole('button', { name: 'Cancel' }).click()

  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.locator('li', { hasText: name })).toBeVisible()

  await page.reload()
  await expect(page.locator('li', { hasText: name })).toBeVisible()
})

test('escape cancels too', async ({ page }) => {
  const name = `Escape me ${Date.now()}`
  const row = await addPage(page, name)

  await row.getByRole('button', { name: `Delete ${name}` }).click()
  await expect(page.getByRole('dialog')).toBeVisible()

  await page.keyboard.press('Escape')

  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(page.locator('li', { hasText: name })).toBeVisible()
})

test('the dialog counts the reports that go with the page', async ({ page }) => {
  const name = `Audited then deleted ${Date.now()}`
  const row = await addPage(page, name)

  // One audit, so the dialog must say "Its report", not "Its 1 reports".
  await row.getByRole('button', { name: 'Run audit' }).click()
  const metrics = page.getByRole('dialog')
  await metrics.locator('input[name="visitors"]').fill('9000')
  await metrics.locator('input[name="bounce_rate"]').fill('55')
  await metrics.locator('input[name="conversion_rate"]').fill('2.2')
  await metrics.getByRole('button', { name: 'Run audit' }).click()
  await expect(page).toHaveURL(/\/audits\/\d+/)

  await page.goto('/')
  const again = page.locator('li', { hasText: name })
  await again.getByRole('button', { name: `Delete ${name}` }).click()

  await expect(page.getByRole('dialog').getByText('Its report and the screenshots')).toBeVisible()
})
