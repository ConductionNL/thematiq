/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/extended-token-sets/spec.md
 *
 * Proves the extended token sets in the browser: what a complete and an
 * incomplete set do to a live page, what the generated token files the server
 * serves look like, how the API validates and lists sets, and what the admin
 * dropdown offers.
 *
 * Three scenarios carry a scenario-level exclusion in the spec: the nightly
 * sync workflow, the generator's internal name conversion and its manifest
 * write. None of them involves a Nextcloud page or API.
 *
 * GLOBAL STATE: two describes switch the active set (`utrecht`, then
 * `groningen`) and empty that set's overrides; the validation test switches
 * to `groningen` through the API. Every one restores the previous set.
 */
import { test, expect, type Page } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import {
	getTokenSet,
	offerTokenSets,
	requestToken,
	setTokenSet,
	withdrawTokenSetOffer,
} from '../workflows/_helpers'
import {
	activateTokenSet,
	declarations,
	normaliseCss,
	openThemedPage,
	rootVars,
	servedCss,
	stripComments,
} from './_token-css'
import { adminContext } from './_fixtures'
import {
	servedCss as servedLayer,
	tokenSetManifest,
	withUploadedSet,
} from './_theme-state'

const THEMING_URL = '/settings/admin/theming'
const DEFAULTS = 'systems/nldesign/defaults.css'
const TOKENS_DIR = path.resolve(__dirname, '../../../css/tokens')

type ListedSet = { id: string; name: string; description?: string }

/** GET a JSON endpoint of this app from inside the authenticated page. */
async function getJson(
	page: Page,
	route: string,
): Promise<{ status: number; body: { tokenSets: ListedSet[] } }> {
	return page.evaluate(async (r) => {
		const oc = (
			window as unknown as {
				OC: { generateUrl: (p: string) => string; requestToken: string }
			}
		).OC
		const res = await fetch(oc.generateUrl(r), {
			headers: { requesttoken: oc.requestToken },
		})
		return { status: res.status, body: await res.json() }
	}, route)
}

/** POST /settings/tokenset with any id, returning the status. */
async function postTokenSet(page: Page, id: string): Promise<number> {
	return page.evaluate(async (tokenSet) => {
		const oc = (
			window as unknown as {
				OC: { generateUrl: (p: string) => string; requestToken: string }
			}
		).OC
		const res = await fetch(oc.generateUrl('/apps/thematiq/settings/tokenset'), {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				requesttoken: oc.requestToken,
			},
			body: JSON.stringify({ tokenSet }),
		})
		return res.status
	}, id)
}

/** Read custom properties off <body>, where theme.css maps Nextcloud's own. */
async function bodyVars(
	page: Page,
	names: string[],
): Promise<Record<string, string>> {
	return page.evaluate((list) => {
		const style = getComputedStyle(document.body)
		const out: Record<string, string> = {}
		for (const name of list) out[name] = style.getPropertyValue(name).trim()
		return out
	}, names)
}

test.describe('extended-token-sets', () => {
	test(// @e2e openspec/specs/extended-token-sets/spec.md#token-generation-from-json
	'the generated groningen.css is one :root block of --nldesign-* declarations', async ({
		page,
	}) => {
		await openThemedPage(page)
		const css = await servedCss(page, 'tokens/groningen.css')
		// TokenSetConverterService records where a generated file came from in
		// its provenance block; the header text itself is the converter's own.
		expect(css).toMatch(
			/source:\s+nl-design-system\/themes proprietary\/groningen-design-tokens/,
		)
		const body = stripComments(css)
		expect(body.match(/:root\b/g) ?? []).toHaveLength(1)
		expect(body.match(/\{/g) ?? []).toHaveLength(1)
		expect(declarations(body, '--nldesign-').size).toBeGreaterThan(0)
	})

	test(// @e2e openspec/specs/extended-token-sets/spec.md#organization-specific-palette-preservation
	'the generated haarlem.css keeps the --haarlem-color-* palette beside the --nldesign-* tokens', async ({
		page,
	}) => {
		await openThemedPage(page)
		const css = await servedCss(page, 'tokens/haarlem.css')
		expect(css).toMatch(
			/source:\s+nl-design-system\/themes proprietary\/haarlem-design-tokens/,
		)
		const palette = declarations(css, '--haarlem-color-')
		expect(palette.get('--haarlem-color-oceaan-10')?.toLowerCase()).toBe(
			'#e8eef5',
		)
		expect(declarations(css, '--nldesign-').size).toBeGreaterThan(palette.size)
		// On a page the served file reaches, the palette resolves as written.
		const v = await page.evaluate((text) => {
			const style = document.createElement('style')
			style.textContent = text
			document.head.appendChild(style)
			const value = getComputedStyle(document.documentElement)
				.getPropertyValue('--haarlem-color-oceaan-10')
				.trim()
			style.remove()
			return value
		}, css)
		expect(v.toLowerCase()).toBe('#e8eef5')
	})

	test(// @e2e openspec/specs/extended-token-sets/spec.md#token-set-validation
	'setTokenSet accepts a set because its CSS file exists and refuses ids with no file', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		const before = await getTokenSet(page, token)
		try {
			// No file, and path traversal: both refused, nothing changes.
			expect(await postTokenSet(page, 'e2e-no-such-set')).toBe(400)
			expect(await postTokenSet(page, '../token-sets')).toBe(400)
			expect(await getTokenSet(page, token)).toBe(before)

			// groningen is on no allowlist (TokenSetService::SELECTABLE_SHIPPED_SETS
			// holds nextcloud and cunningham), so accepting it shows the check is
			// the file on disk, not a hardcoded array of names.
			expect(fs.existsSync(path.join(TOKENS_DIR, 'groningen.css'))).toBe(true)
			expect(await postTokenSet(page, 'groningen')).toBe(200)
			expect(await getTokenSet(page, token)).toBe('groningen')
		} finally {
			await setTokenSet(page, token, before)
		}
	})

	test(// @e2e openspec/specs/extended-token-sets/spec.md#available-token-sets-api
	'the catalogue lists every token file on disk, and the admin listing adds descriptions', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const files: string[] = fs.readdirSync(TOKENS_DIR)
		const onDisk = files
			.filter((f) => f.endsWith('.css') && f.startsWith('custom-') === false)
			.map((f) => f.replace(/\.css$/, ''))
		expect(onDisk.length).toBeGreaterThan(40)

		const catalogue = await getJson(page, '/apps/thematiq/api/token-sets')
		expect(catalogue.status).toBe(200)
		const ids = catalogue.body.tokenSets.map((s) => s.id)
		const missing = onDisk.filter((id) => ids.includes(id) === false)
		expect(
			missing,
			`files with no catalogue entry: ${missing.join(', ')}`,
		).toEqual([])
		const unnamed = catalogue.body.tokenSets.filter((s) => (s.name ?? '') === '')
		expect(unnamed.map((s) => s.id)).toEqual([])
		const amsterdam = catalogue.body.tokenSets.find((s) => s.id === 'amsterdam')
		expect(amsterdam?.name).toBe('Gemeente Amsterdam')

		const token = await requestToken(page)
		const offer = await offerTokenSets(page, token, ['amsterdam'])
		try {
			const admin = await getJson(page, '/apps/thematiq/settings/tokensets')
			expect(admin.status).toBe(200)
			const withoutDescription = admin.body.tokenSets.filter(
				(s) => (s.description ?? '') === '',
			)
			expect(withoutDescription.map((s) => s.id)).toEqual([])
			expect(
				admin.body.tokenSets.find((s) => s.id === 'amsterdam')?.description,
			).toBe('Design tokens for Gemeente Amsterdam')
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})

	test(// @e2e openspec/specs/extended-token-sets/spec.md#admin-views-token-set-dropdown
	'each dropdown option shows the display name token-sets.json gives the set', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		const offer = await offerTokenSets(page, token, ['amsterdam', 'zwolle'])
		try {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const options = await page
				.locator('#nldesign-token-set-select option')
				.evaluateAll((els) =>
					els.map((el) => ({
						id: (el as HTMLOptionElement).value,
						text: (el.textContent ?? '').trim(),
					})),
				)
			const listed = await getJson(page, '/apps/thematiq/settings/tokensets')
			const names = new Map(listed.body.tokenSets.map((s) => [s.id, s.name]))
			const wrong = options.filter((o) => names.get(o.id) !== o.text)
			expect(wrong, JSON.stringify(wrong)).toEqual([])
			// The names token-sets.json gives these two sets.
			expect(options.find((o) => o.id === 'amsterdam')?.text).toBe(
				'Gemeente Amsterdam',
			)
			expect(options.find((o) => o.id === 'zwolle')?.text).toBe(
				'Gemeente Zwolle',
			)
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})

	test.describe('with Gemeente Utrecht active', () => {
		test.describe.configure({ mode: 'serial', timeout: 90_000 })

		let restore: (() => Promise<void>) | null = null

		test.beforeAll(async ({ browser }) => {
			restore = await activateTokenSet(browser, 'utrecht')
		})

		test.afterAll(async () => {
			if (restore !== null) {
				await restore()
				restore = null
			}
		})

		test(// @e2e openspec/specs/extended-token-sets/spec.md#organization-with-complete-token-set
		'the instance renders with Utrecht brand colour, typography and border radius', async ({
			page,
		}) => {
			await openThemedPage(page)
			const file = declarations(
				await servedCss(page, 'tokens/utrecht.css'),
				'--nldesign-',
			)
			const root = await rootVars(page, [
				'--nldesign-color-primary',
				'--nldesign-font-family',
				'--nldesign-border-radius',
				'--font-face',
			])
			expect(root['--nldesign-color-primary'].toLowerCase()).toBe('#24578f')
			expect(normaliseCss(root['--nldesign-font-family'])).toBe(
				normaliseCss(file.get('--nldesign-font-family') ?? ''),
			)
			expect(root['--nldesign-font-family']).toMatch(/^['"]Fira Sans['"]/)
			expect(root['--nldesign-border-radius']).toBe('4px')
			// Nextcloud's own variables carry them to every app.
			expect(normaliseCss(root['--font-face'])).toBe(
				normaliseCss(root['--nldesign-font-family']),
			)
			const body = await bodyVars(page, [
				'--color-primary-element',
				'--border-radius',
			])
			expect(body['--color-primary-element'].toLowerCase()).toBe('#24578f')
			expect(body['--border-radius']).toBe('4px')
		})
	})

	test.describe('with a set that leaves tokens out', () => {
		test.describe.configure({ timeout: 90_000 })

		test(// @e2e openspec/specs/extended-token-sets/spec.md#organization-with-incomplete-token-set
		'the font weights an incomplete set declares apply and everything it leaves out falls back to defaults.css', async ({
			browser,
			page,
		}) => {
			// The set is the test's own. The spec's example, Groningen, used to
			// ship only its font weights; since the token sync (#1006, #1008)
			// every shipped set is complete, so none is incomplete any more.
			const css =
				':root {\n'
				+ '\t--nldesign-typography-font-weight-normal: 300;\n'
				+ '\t--nldesign-typography-font-weight-bold: 600;\n'
				+ '}\n'
			await withUploadedSet(browser, 'font weights only', css, async (set) => {
				const restore = await activateTokenSet(browser, set)
				try {
					await openThemedPage(page)
					const file = declarations(
						await servedLayer(page, `tokens/${set}`),
						'--nldesign-',
					)
					expect([...file.keys()].sort()).toEqual([
						'--nldesign-typography-font-weight-bold',
						'--nldesign-typography-font-weight-normal',
					])
					const defaults = declarations(
						await servedCss(page, DEFAULTS),
						'--nldesign-',
					)
					const v = await rootVars(page, [
						'--nldesign-typography-font-weight-normal',
						'--nldesign-typography-font-weight-bold',
						'--nldesign-color-primary',
						'--nldesign-border-radius',
					])
					expect(v['--nldesign-typography-font-weight-normal']).toBe('300')
					expect(v['--nldesign-typography-font-weight-bold']).toBe('600')
					expect(v['--nldesign-color-primary'].toLowerCase()).toBe(
						(
							defaults.get('--nldesign-color-primary') ?? ''
						).toLowerCase(),
					)
					expect(v['--nldesign-border-radius']).toBe(
						defaults.get('--nldesign-border-radius'),
					)
				} finally {
					await restore()
				}
			})
		})
	})

	test.describe('with a withheld set active', () => {
		test.describe.configure({ mode: 'serial', timeout: 90_000 })

		// A shipped token file with no token-sets.json entry: the picker offers
		// every named set and withholds this one, so while the instance runs it,
		// the survival rule is the only thing that can put it in the dropdown.
		// Read from the data, because which sets are withheld changes as the
		// token sync names them (groningen, which this test used before, has
		// been selectable on its own since #1038).
		const named = new Set(tokenSetManifest().map((s) => s.id))
		const withheld = fs
			.readdirSync(TOKENS_DIR)
			.filter((f) => f.endsWith('.css'))
			.map((f) => f.slice(0, -'.css'.length))
			.filter((id) => !named.has(id))
			.sort()[0]

		let restore: (() => Promise<void>) | null = null

		test.beforeAll(async ({ browser }) => {
			// A hook's own budget: `describe.configure({ timeout })` does not
			// reach `beforeAll`, and this one loads a page, then switches the
			// instance's set through the admin page (activateTokenSet).
			test.setTimeout(120_000)
			expect(
				withheld,
				'no withheld shipped file to exercise the rule with',
			).toBeTruthy()
			const ctx = await adminContext(browser)
			try {
				// Any page with OC.requestToken serves getJson(); the admin
				// theming page is not needed for one GET.
				const page = await ctx.newPage()
				await page.goto('/settings/user', { waitUntil: 'domcontentloaded' })
				await page.waitForFunction(
					() => typeof (window as any).OC?.requestToken === 'string',
				)
				const before = await getJson(
					page,
					'/apps/thematiq/settings/tokensets',
				)
				expect(
					before.body.tokenSets.map((s) => s.id),
					`precondition: ${withheld} is withheld while it is not running`,
				).not.toContain(withheld)
			} finally {
				await ctx.close()
			}
			restore = await activateTokenSet(browser, withheld)
		})

		test.afterAll(async () => {
			if (restore !== null) {
				await restore()
				restore = null
			}
		})

		test(// @e2e openspec/specs/extended-token-sets/spec.md#settings-page-shows-all-token-sets
		'the dropdown is the selectable list resolved at request time, with the running set offered and selected', async ({
			page,
		}) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const select = page.locator('#nldesign-token-set-select')
			await expect(select).toBeVisible()
			const options = await select.locator('option').evaluateAll((els) =>
				els.map((el) => ({
					id: (el as HTMLOptionElement).value,
					text: (el.textContent ?? '').trim(),
				})),
			)
			const listed = await getJson(page, '/apps/thematiq/settings/tokensets')
			expect(options).toEqual(
				listed.body.tokenSets.map((s) => ({ id: s.id, text: s.name })),
			)
			// Offered ONLY because the instance is running it (the precondition
			// above saw it withheld). A hardcoded list could not contain it.
			expect(options.map((o) => o.id)).toContain(withheld)
			expect(await select.inputValue()).toBe(withheld)
		})
	})
})
