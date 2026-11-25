import { test } from '@playwright/test'
const OUT = process.env.LP_OUT ?? '/tmp'
for (const scheme of ['light', 'dark'] as const) {
	test(`primary buttons, ${scheme}`, async ({ page }) => {
		test.setTimeout(120_000)
		await page.emulateMedia({ colorScheme: scheme })
		for (const url of [
			'/settings/admin/theming',
			'/apps/files/',
			'/settings/users',
		]) {
			await page.goto(url, { waitUntil: 'load' })
			await page.waitForTimeout(3000)
			const r = await page.evaluate(() => {
				const out: any[] = []
				document
					.querySelectorAll(
						'button.primary, input.primary, .button-vue--vue-primary, .button-vue--primary',
					)
					.forEach((b: any) => {
						out.push({
							id: b.id,
							cls: String(b.className)
								.replace(/\s+/g, ' ')
								.slice(0, 120),
							vis: b.offsetParent !== null,
							dis: !!b.disabled,
							bg: getComputedStyle(b).backgroundColor,
							fg: getComputedStyle(b).color,
							text: (b.innerText || b.value || '').trim().slice(0, 30),
						})
					})
				return out.slice(0, 8)
			})
			console.log('BTN', scheme, url, JSON.stringify(r))
			const v = page
				.locator(
					'.button-vue--vue-primary:visible, .button-vue--primary:visible, button.primary:visible',
				)
				.first()
			await v
				.screenshot({
					path: `${OUT}/btn-${scheme}-${url.replace(/\W/g, '')}.png`,
					timeout: 4000,
				})
				.catch(() => {})
		}
	})
}
