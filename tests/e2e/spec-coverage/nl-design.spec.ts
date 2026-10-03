/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/nl-design/spec.md
 *
 * Proves the nl-design delta spec in the browser with a municipality set
 * active: Gemeente Amsterdam, which defines its own primary colours and
 * leaves other tokens, such as the favourite colour, to defaults.css.
 * Expected values come from css/tokens/amsterdam.css and the served
 * defaults.css.
 *
 * "Component uses color" carries a scenario-level exclusion in the spec: the
 * rule is not met on development (#769), so a test could only fail.
 *
 * GLOBAL STATE: the active set is switched to `amsterdam` and that set's
 * overrides are emptied. Both are restored in afterAll (see activateTokenSet).
 */
import { test, expect, type Page } from '@playwright/test'
import {
	activateTokenSet,
	declarations,
	indexOfCss,
	openThemedPage,
	rootVars,
	servedCss,
	stylesheetPaths,
} from './_token-css'

/** Amsterdam's primary, from css/tokens/amsterdam.css. */
const AMSTERDAM_PRIMARY = '#004699'

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

test.describe('nl-design', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	let restore: (() => Promise<void>) | null = null

	test.beforeAll(async ({ browser }) => {
		restore = await activateTokenSet(browser, 'amsterdam')
	})

	test.afterAll(async () => {
		if (restore !== null) {
			await restore()
			restore = null
		}
	})

	test(// @e2e openspec/specs/nl-design/spec.md#custom-municipality-theme
	'with Amsterdam selected, two different apps both render Amsterdam primary', async ({
		page,
	}) => {
		for (const route of ['/settings/user', '/apps/files/']) {
			await page.goto(route)
			await page.waitForLoadState('domcontentloaded')
			const paths = await stylesheetPaths(page)
			expect(
				indexOfCss(paths, 'tokens/amsterdam'),
				`${route} links tokens/amsterdam.css`,
			).toBeGreaterThanOrEqual(0)
			const v = await bodyVars(page, [
				'--nldesign-color-primary',
				'--color-primary-element',
				'--color-primary',
			])
			expect(v['--nldesign-color-primary'].toLowerCase(), route).toBe(
				AMSTERDAM_PRIMARY,
			)
			// Nextcloud's own variables follow the municipality, which is what
			// every app paints with.
			expect(v['--color-primary-element'].toLowerCase(), route).toBe(
				AMSTERDAM_PRIMARY,
			)
			expect(v['--color-primary'].toLowerCase(), route).toBe(AMSTERDAM_PRIMARY)
		}
	})

	test(// @e2e openspec/specs/nl-design/spec.md#incomplete-token-set-renders-correctly
	'defined tokens take Amsterdam values and undefined ones fall back to defaults.css', async ({
		page,
	}) => {
		await openThemedPage(page)
		const amsterdam = declarations(
			await servedCss(page, 'tokens/amsterdam.css'),
			'--nldesign-',
		)
		const defaults = declarations(
			await servedCss(page, 'systems/nldesign/defaults.css'),
			'--nldesign-',
		)
		// The GIVEN: Amsterdam defines the primary but not the favourite colour.
		expect(amsterdam.get('--nldesign-color-primary')?.toLowerCase()).toBe(
			AMSTERDAM_PRIMARY,
		)
		expect(amsterdam.has('--nldesign-color-favorite')).toBe(false)
		const favoriteDefault = defaults.get('--nldesign-color-favorite') ?? ''
		expect(favoriteDefault).toMatch(/^#[0-9a-fA-F]{6}$/)

		const v = await rootVars(page, [
			'--nldesign-color-primary',
			'--nldesign-color-favorite',
		])
		expect(v['--nldesign-color-primary'].toLowerCase()).toBe(AMSTERDAM_PRIMARY)
		expect(v['--nldesign-color-favorite'].toLowerCase()).toBe(
			favoriteDefault.toLowerCase(),
		)
	})

	test(// @e2e openspec/specs/nl-design/spec.md#component-uses-component-level-token
	'a Nextcloud primary button takes its styling from component tokens that resolve to Amsterdam', async ({
		page,
	}) => {
		await openThemedPage(page)
		// A button with the class Nextcloud's own primary button carries. It
		// picks up the component-scopes rule for that class like any other.
		const v = await page.evaluate(() => {
			const button = document.createElement('button')
			button.className = 'button-vue button-vue--vue-primary'
			button.textContent = 'e2e'
			document.body.appendChild(button)
			const own = getComputedStyle(button)
			const root = getComputedStyle(document.documentElement)
			const read = (s: CSSStyleDeclaration, n: string) =>
				s.getPropertyValue(n).trim()
			const out = {
				buttonPrimary: read(own, '--color-primary-element'),
				buttonRadius: read(own, '--border-radius-element'),
				componentPrimary: read(
					root,
					'--nldesign-component-button-primary-action-background-color',
				),
				componentRadius: read(
					root,
					'--nldesign-component-button-border-radius',
				),
			}
			button.remove()
			return out
		})
		// The button reads the component tokens...
		expect(v.buttonPrimary).toBe(v.componentPrimary)
		expect(v.buttonRadius).toBe(v.componentRadius)
		// ...and they resolve to the organisation's value: Amsterdam's primary,
		// not the Rijkshuisstijl #154273 of defaults.css.
		expect(v.componentPrimary.toLowerCase()).toBe(AMSTERDAM_PRIMARY)
	})
})
