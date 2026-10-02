/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/vng-token-set/spec.md
 *
 * Proves the VNG token set in the browser. The value scenarios make VNG the
 * active set, open a themed page and read what the cascade resolved with
 * getComputedStyle(document.documentElement). Expected values come from
 * css/tokens/vng.css, and where a scenario is about the file itself the test
 * reads the file the server actually serves.
 *
 * GLOBAL STATE: the value scenarios switch the active set to `vng` and empty
 * the admin overrides of that set, so an admin's pinned value cannot stand in
 * for the set's own. Both are restored in afterAll (see activateTokenSet).
 */
import { test, expect } from '@playwright/test'
import {
	offerTokenSets,
	requestToken,
	withdrawTokenSetOffer,
} from '../workflows/_helpers'
import {
	activateTokenSet,
	contrastRatio,
	declarations,
	indexOfCss,
	normaliseCss,
	openThemedPage,
	rootVars,
	servedCss,
	stripComments,
	stylesheetPaths,
} from './_token-css'

const THEMING_URL = '/settings/admin/theming'

const HEX = /^#[0-9a-f]{6}$/

test.describe('vng-token-set', () => {
	// The manifest and dropdown scenarios do not need VNG active; they run
	// first, outside the describe that switches the set.
	test(// @e2e openspec/specs/vng-token-set/spec.md#vng-appears-in-admin-dropdown
	'VNG token set appears as a selectable option in the admin dropdown', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		// A shipped brand is offered only once it is selectable: see
		// openspec/specs/token-sets/spec.md, "Only Fully Functional Brands Are
		// Selectable". VNG is not on the allowlist today, so the scenario's
		// GIVEN is established through a group mapping, which the same
		// requirement names as one of the paths that keep a set selectable.
		const token = await requestToken(page)
		const offer = await offerTokenSets(page, token, ['vng'])
		try {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const select = page.locator('#nldesign-token-set-select')
			await expect(select).toBeVisible()
			// VNG option must exist in the dropdown
			const vngOption = select.locator('option[value="vng"]')
			await expect(vngOption).toBeAttached()
			await expect(vngOption).toHaveText(/VNG/)
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})

	test(// @e2e openspec/specs/vng-token-set/spec.md#vng-appears-in-manifest
	'TokenSetService lists VNG with its manifest name and a description', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		// GET /settings/tokensets is TokenSetService's selectable listing, built
		// from token-sets.json. VNG is in it once it is offered.
		const offer = await offerTokenSets(page, token, ['vng'])
		try {
			const sets = await page.evaluate(async (t) => {
				const oc = (
					window as unknown as {
						OC: { generateUrl: (p: string) => string }
					}
				).OC
				const r = await fetch(
					oc.generateUrl('/apps/thematiq/settings/tokensets'),
					{
						headers: { requesttoken: t },
					},
				)
				return {
					status: r.status,
					body: (await r.json()) as {
						tokenSets: {
							id: string
							name: string
							description: string
						}[]
					},
				}
			}, token)
			expect(sets.status).toBe(200)
			const vng = sets.body.tokenSets.find((s) => s.id === 'vng')
			expect(vng, 'vng must be listed').toBeDefined()
			expect(vng?.name).toBe('VNG Vereniging Nederlandse Gemeenten')
			// The manifest's own description (token-sets.json), not the
			// "Design tokens for <Name>" fallback the service builds for a set
			// the manifest does not know.
			expect(vng?.description ?? '').toContain(
				'Vereniging Nederlandse Gemeenten',
			)
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})

	test.describe('with VNG active', () => {
		test.describe.configure({ mode: 'serial', timeout: 90_000 })

		let restore: (() => Promise<void>) | null = null

		test.beforeAll(async ({ browser }) => {
			restore = await activateTokenSet(browser, 'vng')
		})

		test.afterAll(async () => {
			if (restore !== null) {
				await restore()
				restore = null
			}
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#vng-token-file-exists-and-loads
		'the page loads css/tokens/vng.css and every --nldesign-* token resolves to its VNG value', async ({
			page,
		}) => {
			await openThemedPage(page)
			const paths = await stylesheetPaths(page)
			expect(
				indexOfCss(paths, 'tokens/vng'),
				`tokens/vng.css must be linked; page carries ${paths.join(', ')}`,
			).toBeGreaterThanOrEqual(0)

			const declared = declarations(
				await servedCss(page, 'tokens/vng.css'),
				'--nldesign-',
			)
			// 47 at the time of writing; a floor guards against parsing nothing.
			expect(declared.size).toBeGreaterThanOrEqual(40)

			const live = await rootVars(page, [...declared.keys()])
			const mismatches: string[] = []
			for (const [name, value] of declared) {
				if (name === '--nldesign-logo-url') {
					// The logo layer re-declares it as an absolute URL right after
					// the token file (CssInjectionService::logoUrlLayer()).
					expect(live[name]).toContain('vng.svg')
					continue
				}
				if (normaliseCss(live[name]) !== normaliseCss(value)) {
					mismatches.push(`${name}: file "${value}", page "${live[name]}"`)
				}
			}
			expect(mismatches, mismatches.join('\n')).toEqual([])
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#vng-palette-tokens-are-preserved
		'the full --vng-color-* palette resolves to literal hex values', async ({
			page,
		}) => {
			await openThemedPage(page)
			const declared = declarations(
				await servedCss(page, 'tokens/vng.css'),
				'--vng-color-',
			)
			for (const family of [
				'blue',
				'red',
				'green',
				'orange',
				'pink',
				'gray',
			]) {
				const names = [...declared.keys()].filter((n) =>
					n.startsWith(`--vng-color-${family}-`),
				)
				expect(names.length, `${family} shades declared`).toBeGreaterThan(0)
			}
			// Resolved hex in the file, not var() references to tilburg tokens.
			for (const [name, value] of declared) {
				expect(value, `${name} in the file`).toMatch(HEX)
			}
			const live = await rootVars(page, [...declared.keys()])
			for (const [name, value] of declared) {
				expect(live[name].toLowerCase(), `${name} on the page`).toBe(
					value.toLowerCase(),
				)
			}
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#primary-colors-use-vng-blue
		'primary colour tokens resolve to VNG blue', async ({ page }) => {
			await openThemedPage(page)
			const v = await rootVars(page, [
				'--nldesign-color-primary',
				'--nldesign-color-primary-hover',
				'--nldesign-color-primary-text',
				'--nldesign-color-primary-light',
				'--vng-color-blue-100',
				'--vng-color-blue-200',
			])
			expect(v['--nldesign-color-primary'].toLowerCase()).toBe('#003865')
			expect(v['--nldesign-color-primary-hover'].toLowerCase()).toBe('#026596')
			expect(v['--nldesign-color-primary-text'].toLowerCase()).toBe('#ffffff')
			// A light blue from the palette (vng.css uses blue-100, #e6f6ff).
			expect([
				v['--vng-color-blue-100'].toLowerCase(),
				v['--vng-color-blue-200'].toLowerCase(),
			]).toContain(v['--nldesign-color-primary-light'].toLowerCase())
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#status-colors-use-vng-palette
		'status colour tokens resolve to the VNG palette', async ({ page }) => {
			await openThemedPage(page)
			const v = await rootVars(page, [
				'--nldesign-color-error',
				'--nldesign-color-success',
				'--nldesign-color-warning',
				'--vng-color-red-400',
				'--vng-color-green-400',
				'--vng-color-orange-400',
			])
			expect(v['--nldesign-color-error'].toLowerCase()).toBe('#bf1a12')
			expect(v['--nldesign-color-success'].toLowerCase()).toBe('#01745a')
			expect(v['--nldesign-color-warning'].toLowerCase()).toBe('#d45f01')
			expect(v['--nldesign-color-error']).toBe(v['--vng-color-red-400'])
			expect(v['--nldesign-color-success']).toBe(v['--vng-color-green-400'])
			expect(v['--nldesign-color-warning']).toBe(v['--vng-color-orange-400'])
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#text-colors-use-vng-values
		'text colour tokens resolve to VNG black-txt and a VNG gray', async ({
			page,
		}) => {
			await openThemedPage(page)
			const grays = [
				'950',
				'900',
				'800',
				'700',
				'600',
				'500',
				'400',
				'300',
				'200',
				'100',
				'50',
			].map((n) => `--vng-color-gray-${n}`)
			const v = await rootVars(page, [
				'--nldesign-color-text',
				'--nldesign-color-text-muted',
				'--vng-color-black-txt',
				...grays,
			])
			expect(v['--nldesign-color-text'].toLowerCase()).toBe('#333333')
			expect(v['--nldesign-color-text']).toBe(v['--vng-color-black-txt'])
			expect(grays.map((g) => v[g].toLowerCase())).toContain(
				v['--nldesign-color-text-muted'].toLowerCase(),
			)
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#font-family-is-set-to-avenir
		'the font token puts Avenir first with a sans-serif fallback', async ({
			page,
		}) => {
			await openThemedPage(page)
			const family = (await rootVars(page, ['--nldesign-font-family']))[
				'--nldesign-font-family'
			]
			const stack = family
				.split(',')
				.map((f) => f.trim().replace(/^['"]|['"]$/g, ''))
			expect(stack[0]).toBe('Avenir')
			expect(stack[stack.length - 1]).toBe('sans-serif')
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#font-sizes-follow-vng-scale
		'heading and body font sizes resolve from the VNG typography scale', async ({
			page,
		}) => {
			await openThemedPage(page)
			const scale: Record<string, string> = {
				sm: '14px',
				md: '16px',
				lg: '20px',
				xl: '24px',
				'2xl': '32px',
				'3xl': '36px',
				'4xl': '48px',
			}
			const scaleNames = Object.keys(scale).map(
				(k) => `--tilburg-typography-font-size-${k}`,
			)
			const headings = [1, 2, 3, 4, 5, 6].map(
				(n) => `--utrecht-heading-${n}-font-size`,
			)
			const v = await rootVars(page, [
				...scaleNames,
				...headings,
				'--utrecht-document-font-size',
			])
			for (const [k, px] of Object.entries(scale)) {
				expect(v[`--tilburg-typography-font-size-${k}`], k).toBe(px)
			}
			const values = Object.values(scale)
			for (const h of headings) {
				expect(values, h).toContain(v[h])
			}
			// Body text is the md step; h1 the 3xl step, as vng.css maps them.
			expect(v['--utrecht-document-font-size']).toBe('16px')
			expect(v['--utrecht-heading-1-font-size']).toBe('36px')
			expect(v['--utrecht-heading-6-font-size']).toBe('14px')
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#spacing-tokens-are-defined
		'the VNG spacing scale is defined and component padding resolves from it', async ({
			page,
		}) => {
			await openThemedPage(page)
			const declared = declarations(
				await servedCss(page, 'tokens/vng.css'),
				'--tilburg-space-',
			)
			expect(declared.size).toBeGreaterThanOrEqual(20)
			const live = await rootVars(page, [
				...declared.keys(),
				'--utrecht-textarea-padding-block-start',
				'--utrecht-textarea-padding-inline-start',
			])
			for (const [name, value] of declared) {
				expect(normaliseCss(live[name]), name).toBe(normaliseCss(value))
			}
			expect(live['--tilburg-space-row-snail']).toBe('8px')
			expect(live['--tilburg-space-row-rat']).toBe('16px')
			expect(live['--utrecht-textarea-padding-block-start']).toBe(
				live['--tilburg-space-block-snail'],
			)
			expect(live['--utrecht-textarea-padding-inline-start']).toBe(
				live['--tilburg-space-inline-snail'],
			)
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#border-radius-uses-vng-values
		'the border radius token is VNG border-radius-md (8px)', async ({
			page,
		}) => {
			await openThemedPage(page)
			const v = await rootVars(page, [
				'--nldesign-border-radius',
				'--tilburg-border-radius-md',
			])
			expect(v['--nldesign-border-radius']).toBe('8px')
			expect(v['--nldesign-border-radius']).toBe(
				v['--tilburg-border-radius-md'],
			)
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#header-is-white-with-vng-dark-text
		'the header is white with VNG dark text, at WCAG AA contrast', async ({
			page,
		}) => {
			await openThemedPage(page)
			const v = await rootVars(page, [
				'--nldesign-color-header-background',
				'--nldesign-color-header-text',
			])
			const bg = v['--nldesign-color-header-background'].toLowerCase()
			const fg = v['--nldesign-color-header-text'].toLowerCase()
			expect(bg).toBe('#ffffff')
			expect(fg).toBe('#333333')
			expect(contrastRatio(bg, fg)).toBeGreaterThanOrEqual(4.5)
		})

		test(// @e2e openspec/specs/vng-token-set/spec.md#utrecht-component-tokens-flow-through-the-bridge
		'VNG supplies --utrecht-* tokens in one :root block and the bridge carries them', async ({
			page,
		}) => {
			await openThemedPage(page)
			const css = stripComments(await servedCss(page, 'tokens/vng.css'))
			expect(css.match(/:root\b/g) ?? []).toHaveLength(1)
			expect(css.match(/\{/g) ?? []).toHaveLength(1)
			expect(declarations(css, '--utrecht-').size).toBeGreaterThanOrEqual(80)

			const v = await rootVars(page, [
				'--utrecht-button-border-radius',
				'--nldesign-component-button-border-radius',
			])
			expect(v['--utrecht-button-border-radius']).toBe('8px')
			expect(v['--nldesign-component-button-border-radius']).toBe('8px')
		})
	})
})
