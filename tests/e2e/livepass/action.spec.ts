import { test, expect } from '@playwright/test'
import { execFileSync } from 'child_process'

const OUT = process.env.LP_OUT ?? '/tmp'
const occ = (...a: string[]) =>
	execFileSync('docker', [
		'exec',
		'-u',
		'www-data',
		'livepass-thematiq-nextcloud-1',
		'php',
		'occ',
		...a,
	]).toString()

test.describe.configure({ mode: 'serial' })

test.skip(!!process.env.LP_SKIP_FORM, 'form done')
test('brand form with its own action colour, then the set on the instance', async ({
	page,
}) => {
	test.setTimeout(240_000)
	await page.goto('/settings/admin/theming', { waitUntil: 'domcontentloaded' })
	await page.locator('#nldesign-create-tab-colours').click()
	await page.locator('#nldesign-brand-name').fill('LP action green')
	await page.locator('#nldesign-brand-primary').fill('#154273')
	await page.locator('label[for=nldesign-brand-action-enabled]').click()
	await expect(page.locator('#nldesign-brand-action')).toBeEnabled()
	await page.locator('#nldesign-brand-action').fill('#1f883d')
	await page.waitForTimeout(800)
	const form = page.locator('#nldesign-brand-form')
	await form.screenshot({ path: `${OUT}/brand-form-action.png` })
	const samples = await page
		.locator('#nldesign-brand-preview .nldesign-brand-sample')
		.evaluateAll((els) =>
			els.map((e) => ({
				id: e.id,
				text: e.textContent?.trim(),
				bg: getComputedStyle(e).backgroundColor,
				fg: getComputedStyle(e).color,
			})),
		)
	console.log('SAMPLES', JSON.stringify(samples))
	console.log(
		'READOUT',
		await page.locator('#nldesign-brand-contrast').innerText(),
	)
	const respP = page.waitForResponse(
		(r) =>
			r.url().includes('/settings/tokensets/from-colours')
			&& r.request().method() === 'POST',
	)
	await page.locator('#nldesign-brand-save').click()
	const resp = await respP
	const body = await resp.json()
	console.log('CREATE', resp.status(), JSON.stringify(body).slice(0, 800))
	await page.waitForTimeout(1500)
	console.log(
		'RESULT',
		await page
			.locator('#nldesign-brand-result')
			.innerText()
			.catch(() => ''),
	)
	const list = occ('thematiq:theme:list')
	const id = (list.split('\n').find((l) => l.includes('LP action green')) ?? '')
		.trim()
		.split(/\s+/)[0]
	console.log('SETID', id)
	expect(id).not.toBe('')
	console.log(occ('thematiq:theme:set', id))

	for (const scheme of ['light', 'dark'] as const) {
		await page.emulateMedia({ colorScheme: scheme })
		await page.goto('/settings/admin/theming', { waitUntil: 'load' })
		await page.waitForTimeout(1500)
		const vars = await page.evaluate(() => {
			const cs = getComputedStyle(document.body)
			const btn = document.querySelector('#nldesign-brand-save') as HTMLElement
			return {
				primaryElement: cs
					.getPropertyValue('--color-primary-element')
					.trim(),
				primary: cs.getPropertyValue('--color-primary').trim(),
				nlPrimary: cs.getPropertyValue('--nldesign-color-primary').trim(),
				nlAction: cs.getPropertyValue('--nldesign-color-action').trim(),
				button: btn ? getComputedStyle(btn).backgroundColor : null,
				header: getComputedStyle(
					document.querySelector('#header') as HTMLElement,
				).backgroundColor,
			}
		})
		console.log('VARS', scheme, JSON.stringify(vars))
		// a checked checkbox: the token-set licence/ preview toggles on this page
		const cb = await page.evaluate(() => {
			const boxes = Array.from(
				document.querySelectorAll('input[type=checkbox].checkbox'),
			) as HTMLInputElement[]
			const b = boxes.find(
				(x) => x.offsetParent !== null || x.nextElementSibling,
			)
			if (!b) return null
			b.checked = true
			const l = b.nextElementSibling as HTMLElement
			return {
				id: b.id,
				before: l ? getComputedStyle(l, '::before').backgroundColor : null,
				border: l ? getComputedStyle(l, '::before').borderColor : null,
			}
		})
		console.log('CHECKBOX', scheme, JSON.stringify(cb))
		await page.screenshot({ path: `${OUT}/admin-${scheme}.png` }).catch(() => {})
		await page.goto('/apps/files/', { waitUntil: 'load' })
		await page.waitForTimeout(3000)
		const prog = await page.evaluate(() => {
			const p = document.querySelector(
				'.progress-bar, progress, [role=progressbar]',
			) as HTMLElement
			if (!p) return null
			const bar = p.querySelector(
				'.progress-bar__bar, div',
			) as HTMLElement | null
			const cs = getComputedStyle(p)
			return {
				tag: p.tagName,
				cls: p.className,
				color: cs.color,
				bg: cs.backgroundColor,
				bar: bar ? getComputedStyle(bar).backgroundColor : null,
				accent: cs.accentColor,
				pv: getComputedStyle(p, '::-webkit-progress-value').backgroundColor,
			}
		})
		console.log('PROGRESS', scheme, JSON.stringify(prog))
		const nc = await page
			.locator('.app-navigation__settings-quota, [class*=quota]')
			.first()
		await nc
			.screenshot({ path: `${OUT}/quota-${scheme}.png`, timeout: 5000 })
			.catch(() => {})
		// a checkbox drawn by nextcloud-vue: files list select-all
		const vcb = await page.evaluate(() => {
			const el = document.querySelector(
				'.checkbox-radio-switch__icon, .checkbox-content__icon',
			) as HTMLElement
			return el
				? { color: getComputedStyle(el).color, cls: el.className }
				: null
		})
		console.log('NCCHECKBOX', scheme, JSON.stringify(vcb))
	}
})
