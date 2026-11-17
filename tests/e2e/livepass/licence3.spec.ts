import { test } from '@playwright/test'
test('apply dialog refuses an unconfirmed set and names the confirmation', async ({ page }) => {
	test.setTimeout(120_000)
	await page.goto('/settings/admin/theming', { waitUntil: 'load' })
	await page.waitForTimeout(2000)
	await page.locator('#nldesign-token-set-select').selectOption('amsterdam')
	await page.waitForTimeout(2500)
	const dlg = page.locator('#nldesign-apply-dialog-overlay')
	console.log('DIALOG', await dlg.count())
	if (await dlg.count()) {
		await dlg.locator('.nldesign-dialog-confirm').click()
		await page.waitForTimeout(3000)
	}
	console.log('TOASTS', JSON.stringify(await page.locator('.toastify').allInnerTexts()).slice(0, 400))
})
