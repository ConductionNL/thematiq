/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-set-dropdown/spec.md
 *
 * Proves the token set dropdown in the browser.
 *
 * Four shipped sets are offered for the whole file (a group mapping to fresh,
 * empty groups, see offerTokenSets), so the dropdown has enough real options
 * to sort, search and pick from without theming the admin running it.
 *
 * Only "Token set selection triggers save" lets a save through, and it puts
 * the previous set back in `finally`. Every other test aborts
 * `POST /settings/tokenset` at the network layer, so a selection made there
 * can open the apply dialog but cannot change the instance.
 */
import { test, expect, type Page } from '@playwright/test'
import {
	getTokenSet,
	offerTokenSets,
	requestToken,
	setTokenSet,
	withdrawTokenSetOffer,
	type TokenSetOffer,
} from '../workflows/_helpers'
import { adminContext } from './_fixtures'

const THEMING_URL = '/settings/admin/theming'
const OFFERED = ['vng', 'rijkshuisstijl', 'amsterdam', 'zwolle']

type Listed = { id: string; name: string; theming?: { primary_color?: string } }

/** The selectable list, as the admin page's own API returns it. */
async function selectable(page: Page): Promise<Listed[]> {
	return page.evaluate(async () => {
		const oc = (
			window as unknown as {
				OC: { generateUrl: (p: string) => string; requestToken: string }
			}
		).OC
		const res = await fetch(
			oc.generateUrl('/apps/thematiq/settings/tokensets'),
			{
				headers: { requesttoken: oc.requestToken },
			},
		)
		return (await res.json()).tokenSets
	})
}

/** Make every save of the active set fail at the network layer. */
async function blockSaves(page: Page): Promise<string[]> {
	const blocked: string[] = []
	await page.route('**/apps/thematiq/settings/tokenset', (route) => {
		if (route.request().method() === 'POST') {
			blocked.push(route.request().postData() ?? '')
			return route.abort()
		}
		return route.continue()
	})
	return blocked
}

/** Open the theming page and close a sync dialog that may open on load. */
async function openTheming(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForSelector('#nldesign-token-set-select', { timeout: 15_000 })
	const sync = page.locator('#nldesign-theming-dialog-overlay')
	if (await sync.isVisible({ timeout: 1_500 }).catch(() => false)) {
		await sync.locator('.nldesign-dialog-cancel').first().click()
	}
}

/** Cancel the apply dialog if the last selection opened one. */
async function cancelApplyDialog(page: Page): Promise<void> {
	const dialog = page.locator('#nldesign-apply-dialog-overlay')
	if (await dialog.isVisible({ timeout: 4_000 }).catch(() => false)) {
		await dialog.locator('.nldesign-dialog-cancel').first().click()
		await expect(dialog).toBeHidden()
	}
}

/** PHP's strcasecmp, which TokenSetService sorts with: ASCII case folding. */
function strcasecmp(a: string, b: string): number {
	const fold = (s: string) => s.replace(/[A-Z]/g, (c) => c.toLowerCase())
	const x = fold(a)
	const y = fold(b)
	return x < y ? -1 : x > y ? 1 : 0
}

test.describe('token-set-dropdown', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	let offer: TokenSetOffer | null = null

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		try {
			await page.goto(THEMING_URL)
			offer = await offerTokenSets(page, await requestToken(page), OFFERED)
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		if (offer === null) return
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		try {
			await page.goto(THEMING_URL)
			await withdrawTokenSetOffer(page, await requestToken(page), offer)
			offer = null
		} finally {
			await ctx.close()
		}
	})

	test(// @e2e openspec/specs/token-set-dropdown/spec.md#dropdown-renders-with-all-token-sets
	'a native <select> lists every selectable set by name, with the active set pre-selected', async ({
		page,
	}) => {
		await openTheming(page)
		const select = page.locator('#nldesign-token-set-select')
		expect(await select.evaluate((el) => el.tagName)).toBe('SELECT')
		const options = await select.locator('option').evaluateAll((els) =>
			els.map((el) => ({
				id: (el as HTMLOptionElement).value,
				name: (el.textContent ?? '').trim(),
			})),
		)
		const listed = await selectable(page)
		expect(options).toEqual(listed.map((s) => ({ id: s.id, name: s.name })))
		for (const id of OFFERED) {
			expect(options.map((o) => o.id)).toContain(id)
		}
		const active = await getTokenSet(page, await requestToken(page))
		expect(await select.inputValue()).toBe(active)
	})

	test(// @e2e openspec/specs/token-set-dropdown/spec.md#dropdown-is-searchable-via-browser-native-behavior
	'typing on the focused dropdown jumps to the matching set, with no search widget of our own', async ({
		page,
	}) => {
		await blockSaves(page)
		await openTheming(page)
		const select = page.locator('#nldesign-token-set-select')
		// No custom search control sits beside the dropdown.
		await expect(
			page.locator(
				'.nldesign-token-set-selector input[type="search"], .nldesign-token-set-selector input[type="text"]',
			),
		).toHaveCount(0)

		const before = await select.inputValue()
		// Typed without a space: on a focused <select> a space opens the list.
		const [typed, targetId] =
			before === 'rijkshuisstijl' ? ['VNG', 'vng'] : ['Rijk', 'rijkshuisstijl']
		await select.focus()
		// The browser's own type-to-select: characters typed in quick
		// succession match the start of an option's label.
		await page.keyboard.type(typed)
		await expect(select).toHaveValue(targetId)
		await cancelApplyDialog(page)
	})

	test(// @e2e openspec/specs/token-set-dropdown/spec.md#token-set-selection-triggers-save
	'picking a set and confirming saves it with POST /settings/tokenset and shows a notification', async ({
		page,
	}) => {
		await openTheming(page)
		const token = await requestToken(page)
		const before = await getTokenSet(page, token)
		// A set the dropdown offers: one of the sets this file offers, other
		// than the active one. The admin dropdown lists only the selectable sets
		// (TokenSetService::SELECTABLE_SHIPPED_SETS, the active set, custom sets
		// and mapped sets), so a set outside the offer cannot be picked.
		const offered = await page
			.locator('#nldesign-token-set-select option')
			.evaluateAll((els) => els.map((el) => (el as HTMLOptionElement).value))
		const target = OFFERED.find((id) => id !== before && offered.includes(id))
		expect(
			target,
			`the dropdown offers one of ${OFFERED.join(', ')}`,
		).toBeTruthy()
		try {
			const saved = page.waitForRequest(
				(req) =>
					req.method() === 'POST'
					&& new URL(req.url()).pathname.endsWith(
						'/apps/thematiq/settings/tokenset',
					),
				{ timeout: 30_000 },
			)
			await page
				.locator('#nldesign-token-set-select')
				.selectOption(target as string)

			// The apply dialog opens once its preview and theming reads resolve,
			// or the save goes out directly when the preview has nothing to show.
			// Wait for whichever comes first; `isVisible()` does not wait at all.
			const dialog = page.locator('#nldesign-apply-dialog-overlay')
			const opened = await Promise.race([
				dialog
					.waitFor({ state: 'visible', timeout: 30_000 })
					.then(() => true)
					.catch(() => false),
				saved.then(() => false),
			])
			if (opened) {
				// Apply the set only: no token rows pinned, no core theming sync.
				await page.locator('#nldesign-apply-deselect-all').click()
				const sync = dialog.locator('#nldesign-apply-theming-check')
				if ((await sync.count()) > 0) await sync.uncheck()
				await dialog.locator('.nldesign-dialog-confirm').click()
			}

			const request = await saved
			expect(JSON.parse(request.postData() ?? '{}')).toEqual({
				tokenSet: target,
			})
			const response = await request.response()
			expect(response?.status()).toBe(200)

			// Nextcloud 32-34 render toasts as `.toastify`; 35 uses CSS-module
			// classes (`_toastContainer_…`, `_toast_…`). Both carry "toast" in
			// a class name, so the toast is found by that and its text.
			await expect(
				page
					.locator('.toastify, [class*="_toast_"]')
					.filter({ hasText: /Applied|Theme updated/ })
					.first(),
			).toBeVisible({ timeout: 15_000 })
			expect(await getTokenSet(page, token)).toBe(target)

			// A standalone core-theming sync dialog may follow a save that had
			// no token changes to show; it is declined, not applied.
			const syncDialog = page.locator('#nldesign-theming-dialog-overlay')
			if (await syncDialog.isVisible({ timeout: 2_000 }).catch(() => false)) {
				await syncDialog.locator('.nldesign-dialog-cancel').first().click()
			}
		} finally {
			await setTokenSet(page, await requestToken(page), before)
		}
	})

	test(// @e2e openspec/specs/token-set-dropdown/spec.md#dropdown-handles-400-entries
	'with 400 more options the dropdown lists them all and stays responsive', async ({
		page,
	}) => {
		await blockSaves(page)
		await openTheming(page)
		const select = page.locator('#nldesign-token-set-select')
		const realCount = await select.locator('option').count()

		// The instance ships about fifty sets, so the 400 are added to the real
		// element in the page. What is under test is the control: a native
		// <select> plus the page's own change handling.
		const timing = await select.evaluate((el) => {
			const s = el as HTMLSelectElement
			const started = performance.now()
			for (let i = 0; i < 400; i++) {
				const option = document.createElement('option')
				option.value = `e2e-scale-${i}`
				option.textContent = `Gemeente E2E ${String(i).padStart(3, '0')}`
				s.appendChild(option)
			}
			return performance.now() - started
		})
		await expect(select.locator('option')).toHaveCount(realCount + 400)

		// Responsive: picking the last entry is handled and the main thread
		// answers a frame promptly afterwards.
		const started = Date.now()
		await select.selectOption('e2e-scale-399')
		await expect(select).toHaveValue('e2e-scale-399')
		const frame = await page.evaluate(
			() =>
				new Promise<number>((resolve) => {
					const t0 = performance.now()
					requestAnimationFrame(() => resolve(performance.now() - t0))
				}),
		)
		expect(Date.now() - started).toBeLessThan(5_000)
		expect(frame).toBeLessThan(1_000)
		expect(timing).toBeLessThan(1_000)
		await cancelApplyDialog(page)
	})

	test(// @e2e openspec/specs/token-set-dropdown/spec.md#token-sets-appear-in-alphabetical-order
	'options are sorted by name: Gemeente Amsterdam, Gemeente Zwolle, Rijkshuisstijl, VNG', async ({
		page,
	}) => {
		await openTheming(page)
		const names = await page
			.locator('#nldesign-token-set-select option')
			.evaluateAll((els) => els.map((el) => (el.textContent ?? '').trim()))
		expect(names).toEqual([...names].sort(strcasecmp))
		const position = (name: string) => names.indexOf(name)
		const expected = [
			'Gemeente Amsterdam',
			'Gemeente Zwolle',
			'Rijkshuisstijl',
			'VNG Vereniging Nederlandse Gemeenten',
		]
		for (const name of expected) {
			expect(position(name), name).toBeGreaterThanOrEqual(0)
		}
		expect(expected.map(position)).toEqual(
			[...expected.map(position)].sort((a, b) => a - b),
		)
	})

	test(// @e2e openspec/specs/token-set-dropdown/spec.md#preview-reflects-new-selection
	'the preview takes the new set colours before any save completes', async ({
		page,
	}) => {
		await blockSaves(page)
		await openTheming(page)
		const select = page.locator('#nldesign-token-set-select')
		const before = await select.inputValue()
		const target = before === 'vng' ? 'zwolle' : 'vng'
		const listed = await selectable(page)
		const expected = (
			listed.find((s) => s.id === target)?.theming?.primary_color ?? ''
		).toLowerCase()
		expect(expected).toMatch(/^#[0-9a-f]{6}$/)

		await select.selectOption(target)
		const preview = page.locator('#nldesign-preview')
		await expect
			.poll(async () =>
				(
					await preview.evaluate((el) =>
						(el as HTMLElement).style.getPropertyValue('--prev-primary'),
					)
				)
					.trim()
					.toLowerCase(),
			)
			.toBe(expected)
		// Optimistic: no save has gone through. Every POST is aborted, and the
		// active set on the server is unchanged.
		expect(await getTokenSet(page, await requestToken(page))).toBe(before)
		await cancelApplyDialog(page)
	})
})
