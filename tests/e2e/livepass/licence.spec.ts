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

test('restricted set applied before the upgrade: notice, refusal, confirmation', async ({
	page,
}) => {
	test.setTimeout(240_000)
	// The state an upgrade finds: epe applied, nobody confirmed it.
	occ('config:app:set', 'thematiq', 'token_set', '--value=epe')
	console.log(
		'REPAIR',
		occ('maintenance:repair')
			.split('\n')
			.filter((l) => l.includes('RecordRestricted') || l.includes('epe'))
			.join(' | '),
	)
	console.log(
		'AWAIT',
		occ(
			'config:app:get',
			'thematiq',
			'restricted_sets_awaiting_confirmation',
		).trim(),
	)
	await page.goto('/settings/admin/theming', { waitUntil: 'load' })
	const awaiting = page.locator('#nldesign-set-licences-awaiting')
	await expect(awaiting).toBeVisible({ timeout: 30_000 })
	console.log('AWAITING', (await awaiting.innerText()).replace(/\s+/g, ' '))
	await page
		.locator('#nldesign-set-licences')
		.screenshot({ path: `${OUT}/licence-block-awaiting.png` })
	// a second restricted set is refused until confirmed
	const r = await page.evaluate(async () => {
		const res = await fetch(OC.generateUrl('/apps/thematiq/settings/tokenset'), {
			method: 'POST',
			headers: {
				requesttoken: OC.requestToken,
				'Content-Type': 'application/json',
			},
			body: JSON.stringify({ tokenSet: 'amsterdam' }),
		})
		return { status: res.status, body: (await res.text()).slice(0, 300) }
	})
	console.log('APPLY-AMSTERDAM', JSON.stringify(r))
	// selecting it in the dropdown names the confirmation
	const sel = page.locator('#nldesign-token-set-select')
	if (await sel.count()) {
		await sel
			.selectOption('amsterdam')
			.catch((e) => console.log('SELECT-ERR', String(e).slice(0, 200)))
		await page.waitForTimeout(1500)
		const hint = page.locator('#nldesign-set-licence-hint')
		console.log(
			'HINT',
			await hint.isVisible(),
			(await hint.innerText().catch(() => '')).replace(/\s+/g, ' '),
		)
		await page.screenshot({ path: `${OUT}/licence-hint.png`, fullPage: false })
		const dlg0 = page
			.locator('[role=dialog]')
			.filter({ hasText: 'Apply token set' })
		if (await dlg0.count()) {
			await dlg0.getByRole('button', { name: 'Apply selected' }).click()
			await page.waitForTimeout(2500)
			const toasts = await page
				.locator('.toastify, .toast, [role=alert]')
				.allInnerTexts()
			console.log('APPLY-UI', JSON.stringify(toasts).slice(0, 600))
			await page.screenshot({ path: `${OUT}/licence-apply-refused.png` })
			console.log(
				'ACTIVE',
				occ('config:app:get', 'thematiq', 'token_set').trim(),
			)
			if (await dlg0.isVisible())
				await dlg0.getByRole('button', { name: 'Cancel' }).click()
		}
		await page.goto('/settings/admin/theming', { waitUntil: 'load' })
		await page.waitForTimeout(1500)
	}
	// confirm epe from the notice's link / the block
	const entry = page.locator('#nldesign-set-licence-epe')
	await entry.scrollIntoViewIfNeeded()
	await entry.screenshot({ path: `${OUT}/licence-epe-before.png` })
	await entry
		.locator('summary')
		.first()
		.click()
		.catch(() => {})
	await entry.screenshot({ path: `${OUT}/licence-epe-open.png` })
	const confirm = entry.getByRole('button', { name: /^(Confirm|Bevestigen)$/ })
	console.log('CONFIRM-DISABLED', await confirm.isDisabled())
	await entry.locator('input[type=checkbox]').check()
	console.log('CONFIRM-DISABLED-AFTER', await confirm.isDisabled())
	await confirm.click()
	await page.waitForTimeout(1500)
	const dlg = page.locator('[role=dialog]')
	if (await dlg.count()) {
		console.log(
			'DIALOG',
			(await dlg.first().innerText()).replace(/\s+/g, ' ').slice(0, 400),
		)
		await dlg.first().screenshot({ path: `${OUT}/licence-confirm-dialog.png` })
		await dlg
			.first()
			.getByRole('button', { name: /Confirm|Bevestig/ })
			.last()
			.click()
		await page.waitForTimeout(1500)
	}
	console.log(
		'ENTRY-AFTER',
		(await entry.innerText()).replace(/\s+/g, ' ').slice(0, 400),
	)
	console.log('AWAITING-AFTER', await awaiting.isVisible())
	await entry.screenshot({ path: `${OUT}/licence-epe-after.png` })
	console.log(
		'AWAIT2',
		occ(
			'config:app:get',
			'thematiq',
			'restricted_sets_awaiting_confirmation',
		).trim(),
	)
	await page.reload({ waitUntil: 'load' })
	await page.waitForTimeout(2000)
	console.log('AWAITING-RELOAD', await awaiting.isVisible())
})
