import { test } from '@playwright/test'
const OUT = process.env.LP_OUT ?? '/tmp'
test('after the confirmation', async ({ page }) => {
	test.setTimeout(120_000)
	await page.goto('/settings/admin/theming', { waitUntil: 'load' })
	await page.waitForTimeout(3000)
	console.log('AWAITING-VISIBLE', await page.locator('#nldesign-set-licences-awaiting').isVisible())
	const entry = page.locator('#nldesign-set-licence-epe')
	console.log('SUMMARY', (await entry.locator('summary').innerText()).replace(/\s+/g, ' '))
	await entry.locator('summary').click()
	await page.waitForTimeout(800)
	const txt = (await entry.innerText()).replace(/\s+/g, ' ')
	console.log('ENTRY-TAIL', txt.slice(-300))
	const withdraw = entry.getByRole('button', { name: 'Withdraw confirmation' })
	console.log('WITHDRAW', await withdraw.count())
	await entry.locator('summary').screenshot({ path: `${OUT}/licence-epe-confirmed.png` })
	if (await withdraw.count()) {
		await withdraw.scrollIntoViewIfNeeded()
		await withdraw.screenshot({ path: `${OUT}/licence-epe-withdraw.png` })
		await withdraw.click()
		await page.waitForTimeout(2000)
		const after = (await entry.innerText()).replace(/\s+/g, ' ')
		console.log('WITHDRAW-WHILE-APPLIED', after.slice(-200))
		const toasts = await page.locator('.toastify').allInnerTexts()
		console.log('TOASTS', JSON.stringify(toasts).slice(0, 300))
	}
})
