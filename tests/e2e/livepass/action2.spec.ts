import { test } from '@playwright/test'
const OUT = process.env.LP_OUT ?? '/tmp'
for (const scheme of ['light', 'dark'] as const) {
	test(`action colour on the instance, ${scheme}`, async ({ page }) => {
		test.setTimeout(120_000)
		await page.emulateMedia({ colorScheme: scheme })
		await page.goto('/settings/admin/theming', { waitUntil: 'load' })
		await page.waitForTimeout(1500)
		const vars = await page.evaluate(() => {
			const cs = getComputedStyle(document.body)
			const btns = Array.from(
				document.querySelectorAll(
					'button.primary, .button-vue--vue-primary, .button-vue--primary',
				),
			) as HTMLElement[]
			const vis = btns.filter((b) => b.offsetParent !== null).slice(0, 3)
			return {
				primaryElement: cs
					.getPropertyValue('--color-primary-element')
					.trim(),
				nlAction: cs.getPropertyValue('--nldesign-color-action').trim(),
				buttons: vis.map((b) => ({
					id: b.id || b.className.slice(0, 40),
					bg: getComputedStyle(b).backgroundColor,
					fg: getComputedStyle(b).color,
				})),
				header: getComputedStyle(
					document.querySelector('#header') as HTMLElement,
				).backgroundColor,
				mainBg: cs.getPropertyValue('--color-main-background').trim(),
			}
		})
		console.log('VARS', scheme, JSON.stringify(vars))
		const cb = await page.evaluate(() => {
			const b = Array.from(
				document.querySelectorAll('input[type=checkbox].checkbox'),
			).find(
				(x) => (x.nextElementSibling as HTMLElement)?.offsetParent !== null,
			) as HTMLInputElement
			if (!b) return null
			b.checked = true
			const l = b.nextElementSibling as HTMLElement
			return {
				id: b.id,
				before: getComputedStyle(l, '::before').backgroundColor,
			}
		})
		console.log('CHECKBOX', scheme, JSON.stringify(cb))
		const btn = page
			.locator('button.primary:visible, .button-vue--vue-primary:visible')
			.first()
		await btn.scrollIntoViewIfNeeded().catch(() => {})
		await btn
			.screenshot({ path: `${OUT}/main-button-${scheme}.png`, timeout: 5000 })
			.catch(() => {})
		await page.goto('/apps/files/', { waitUntil: 'load' })
		await page.waitForTimeout(4000)
		const prog = await page.evaluate(() => {
			const p = document.querySelector(
				'progress, .progress-bar, [role=progressbar]',
			) as HTMLElement
			if (!p) return null
			const cs = getComputedStyle(p)
			return {
				tag: p.tagName,
				cls: String(p.className).slice(0, 80),
				color: cs.color,
				accent: cs.accentColor,
				value: getComputedStyle(p, '::-webkit-progress-value')
					.backgroundColor,
			}
		})
		console.log('PROGRESS', scheme, JSON.stringify(prog))
		const vcb = await page.evaluate(() => {
			const el = document.querySelector(
				'.checkbox-radio-switch__icon, .checkbox-content__icon',
			) as HTMLElement
			return el ? { color: getComputedStyle(el).color } : null
		})
		console.log('NCCHECKBOX', scheme, JSON.stringify(vcb))
		await page
			.locator('[class*=quota]')
			.first()
			.screenshot({ path: `${OUT}/quota-${scheme}.png`, timeout: 5000 })
			.catch(() => {})
	})
}
