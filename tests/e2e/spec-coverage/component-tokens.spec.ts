/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/component-tokens/spec.md
 *
 * Proves the component token layer in the browser, two ways.
 *
 * On a live themed page, with the VNG set active: VNG ships the `--utrecht-*`
 * tokens the bridge reads, so the page shows whether the bridge carries an
 * organisation's value through to `--nldesign-component-*`.
 *
 * In an isolated document built from the stylesheets the server serves:
 * `defaults.css`, `utrecht-bridge.css` and, where a scenario names one, a
 * token file. That is how "a set that does not define X" and "no token file
 * at all" are set up without changing the instance: the browser resolves the
 * real files, in the real order, and nothing else is on the page.
 *
 * GLOBAL STATE: the live describe switches the active set to `vng` and empties
 * that set's overrides. Both are restored in afterAll (see activateTokenSet).
 */
import { test, expect, type Page } from '@playwright/test'
import {
	activateTokenSet,
	declarations,
	normaliseCss,
	openThemedPage,
	rootVars,
	servedCss,
	stripComments,
} from './_token-css'

const DEFAULTS = 'systems/nldesign/defaults.css'
const BRIDGE = 'systems/nldesign/utrecht-bridge.css'

/**
 * Resolve custom properties in a document that carries only `sheets`.
 *
 * The page must already be on the instance, so the caller can fetch the
 * served stylesheets first. setContent then replaces the document; the
 * browser cascades exactly the CSS given, in the order given.
 */
async function isolatedCascade(
	page: Page,
	sheets: string[],
	names: string[],
): Promise<Record<string, string>> {
	const styles = sheets.map((css) => `<style>${css}</style>`).join('')
	await page.setContent(
		`<!doctype html><html><head>${styles}</head><body></body></html>`,
	)
	return rootVars(page, names)
}

test.describe('component-tokens', () => {
	test.describe('isolated cascade of the served files', () => {
		test(// @e2e openspec/specs/component-tokens/spec.md#bridge-file-is-clearly-marked-as-temporary
		'utrecht-bridge.css opens with a header that marks it temporary', async ({
			page,
		}) => {
			await openThemedPage(page)
			const css = await servedCss(page, BRIDGE)
			const header = css.slice(0, css.indexOf('*/') + 2)
			expect(header).toMatch(/^\/\*/)
			expect(header).toContain('TEMPORARY')
			expect(header).toMatch(/vendor-neutral\s+prefix/)
			expect(header).toMatch(/can\s+be\s+removed/)
			expect(header).toContain('https://github.com/nl-design-system/themes')
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#bridge-maps-utrecht-tokens-to-nldesign
		'a token set defining --utrecht-button-border-radius: 4px resolves the component token to 4px', async ({
			page,
		}) => {
			await openThemedPage(page)
			const defaults = await servedCss(page, DEFAULTS)
			const bridge = await servedCss(page, BRIDGE)
			// The organisation token file, reduced to the one token the
			// scenario names. It is emitted after the design-system sheets, as
			// CssInjectionService orders it.
			const orgTokens = ':root { --utrecht-button-border-radius: 4px; }'
			const v = await isolatedCascade(
				page,
				[defaults, bridge, orgTokens],
				['--nldesign-component-button-border-radius'],
			)
			expect(v['--nldesign-component-button-border-radius']).toBe('4px')
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#bridge-falls-back-to-defaults
		'without --utrecht-button-border-radius the component token falls back to the defaults.css value', async ({
			page,
		}) => {
			await openThemedPage(page)
			const defaults = await servedCss(page, DEFAULTS)
			const bridge = await servedCss(page, BRIDGE)
			// Rijkshuisstijl is a real set on this design system that defines
			// no --utrecht-* token at all.
			const tokens = await servedCss(page, 'tokens/rijkshuisstijl.css')
			expect(declarations(tokens, '--utrecht-').size).toBe(0)

			// What defaults.css alone resolves the token to.
			const alone = await isolatedCascade(
				page,
				[defaults, tokens],
				[
					'--nldesign-component-button-border-radius',
					'--nldesign-border-radius',
				],
			)
			const bridged = await isolatedCascade(
				page,
				[defaults, bridge, tokens],
				[
					'--nldesign-component-button-border-radius',
					'--utrecht-button-border-radius',
				],
			)
			expect(bridged['--utrecht-button-border-radius']).toBe('')
			expect(bridged['--nldesign-component-button-border-radius']).toBe(
				alone['--nldesign-component-button-border-radius'],
			)
			// defaults.css points it at the brand radius.
			expect(alone['--nldesign-component-button-border-radius']).toBe(
				alone['--nldesign-border-radius'],
			)
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#component-token-defaults-reference-brand-tokens
		'the primary-action background defaults to var(--nldesign-color-primary)', async ({
			page,
		}) => {
			await openThemedPage(page)
			const defaults = await servedCss(page, DEFAULTS)
			const bridge = await servedCss(page, BRIDGE)
			const tokens = await servedCss(page, 'tokens/rijkshuisstijl.css')

			const declared = declarations(
				defaults,
				'--nldesign-component-button-primary-action-background-color',
			)
			expect(
				normaliseCss(
					declared.get(
						'--nldesign-component-button-primary-action-background-color',
					) ?? '',
				),
			).toBe('var(--nldesign-color-primary)')

			// No organisation token overrides it: Rijkshuisstijl defines no
			// --utrecht-button-primary-action-background-color.
			const v = await isolatedCascade(
				page,
				[defaults, bridge, tokens],
				[
					'--nldesign-component-button-primary-action-background-color',
					'--nldesign-color-primary',
				],
			)
			expect(v['--nldesign-color-primary'].toLowerCase()).toBe('#154273')
			expect(
				v['--nldesign-component-button-primary-action-background-color'],
			).toBe(v['--nldesign-color-primary'])
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#component-token-defaults-are-self-consistent
		'with no token file every component default resolves, and the bridge agrees with defaults.css', async ({
			page,
		}) => {
			await openThemedPage(page)
			const defaults = await servedCss(page, DEFAULTS)
			const bridge = await servedCss(page, BRIDGE)
			const declared = declarations(defaults, '--nldesign-component-')
			// 109 at the time of writing; a floor guards against parsing nothing.
			expect(declared.size).toBeGreaterThanOrEqual(100)
			const names = [...declared.keys()]

			// A brand token each default may point at, to compare against.
			const brandRefs = new Map<string, string>()
			for (const [name, value] of declared) {
				const ref = /^var\(\s*(--nldesign-(?!component-)[\w-]+)\s*\)$/.exec(
					normaliseCss(value),
				)
				if (ref !== null) brandRefs.set(name, ref[1])
			}
			expect(brandRefs.size).toBeGreaterThan(10)

			const alone = await isolatedCascade(
				page,
				[defaults],
				[...names, ...brandRefs.values()],
			)
			const bridged = await isolatedCascade(page, [defaults, bridge], names)

			const empty = names.filter(
				(n) => alone[n] === '' && declared.get(n) !== '',
			)
			expect(
				empty,
				`defaults that resolve to nothing: ${empty.join(', ')}`,
			).toEqual([])
			const drift = names.filter(
				(n) => normaliseCss(bridged[n]) !== normaliseCss(alone[n]),
			)
			expect(
				drift,
				`bridge fallbacks that disagree with defaults.css: ${drift
					.map((n) => `${n} (${alone[n]} vs ${bridged[n]})`)
					.join(', ')}`,
			).toEqual([])
			for (const [name, brand] of brandRefs) {
				expect(normaliseCss(alone[name]), `${name} follows ${brand}`).toBe(
					normaliseCss(alone[brand]),
				)
			}
		})
	})

	test.describe('on a live page with VNG active', () => {
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

		test(// @e2e openspec/specs/component-tokens/spec.md#button-component-token
		'--utrecht-button-primary-action-background-color is available as the nldesign component token', async ({
			page,
		}) => {
			await openThemedPage(page)
			const v = await rootVars(page, [
				'--utrecht-button-primary-action-background-color',
				'--nldesign-component-button-primary-action-background-color',
			])
			expect(v['--utrecht-button-primary-action-background-color']).toMatch(
				/\S/,
			)
			expect(
				v['--nldesign-component-button-primary-action-background-color'],
			).toBe(v['--utrecht-button-primary-action-background-color'])
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#heading-component-token
		'--utrecht-heading-1-font-size is available as --nldesign-component-heading-1-font-size', async ({
			page,
		}) => {
			await openThemedPage(page)
			const v = await rootVars(page, [
				'--utrecht-heading-1-font-size',
				'--nldesign-component-heading-1-font-size',
			])
			// VNG maps h1 to the 3xl step of its type scale.
			expect(v['--utrecht-heading-1-font-size']).toBe('36px')
			expect(v['--nldesign-component-heading-1-font-size']).toBe('36px')
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#button-tokens
		'button tokens exist for every property and state variant', async ({
			page,
		}) => {
			await openThemedPage(page)
			const required = [
				'background-color',
				'color',
				'border-radius',
				'border-width',
				'border-color',
				'font-family',
				'font-size',
				'padding-block',
				'padding-inline',
				'hover-background-color',
				'active-background-color',
				'disabled-background-color',
				'focus-border-color',
				'primary-action-background-color',
				'secondary-action-background-color',
			].map((p) => `--nldesign-component-button-${p}`)
			const result = await unsupported(page, required)
			expect(result.missing, 'not declared in defaults.css').toEqual([])
			expect(result.unresolved, 'resolve to nothing on the page').toEqual([])
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#form-input-tokens
		'form input tokens exist for textbox, form-field, form-select and form-fieldset', async ({
			page,
		}) => {
			await openThemedPage(page)
			const result = await unsupported(page, [
				'--nldesign-component-textbox-border-color',
				'--nldesign-component-textbox-focus-border-color',
				'--nldesign-component-textbox-hover-border-color',
				'--nldesign-component-textbox-disabled-background-color',
				'--nldesign-component-textbox-invalid-border-color',
				'--nldesign-component-form-field-label-color',
				'--nldesign-component-form-select-border-color',
				'--nldesign-component-form-select-focus-border-color',
				'--nldesign-component-form-fieldset-border-color',
			])
			expect(result.missing, 'not declared in defaults.css').toEqual([])
			expect(result.unresolved, 'resolve to nothing on the page').toEqual([])
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#typography-tokens
		'heading levels 1-6 and paragraph tokens exist', async ({ page }) => {
			await openThemedPage(page)
			const names: string[] = []
			for (const level of [1, 2, 3, 4, 5, 6]) {
				for (const p of [
					'font-size',
					'font-weight',
					'line-height',
					'color',
				]) {
					names.push(`--nldesign-component-heading-${level}-${p}`)
				}
			}
			for (const p of ['font-size', 'line-height', 'color']) {
				names.push(`--nldesign-component-paragraph-${p}`)
			}
			const result = await unsupported(page, names)
			expect(result.missing, 'not declared in defaults.css').toEqual([])
			expect(result.unresolved, 'resolve to nothing on the page').toEqual([])
		})

		test(// @e2e openspec/specs/component-tokens/spec.md#additional-component-tokens
		'link, table, badge, separator and list tokens exist', async ({ page }) => {
			await openThemedPage(page)
			const result = await unsupported(page, [
				'--nldesign-component-link-color',
				'--nldesign-component-table-border-color',
				'--nldesign-component-badge-background-color',
				'--nldesign-component-separator-border-color',
				'--nldesign-component-ordered-list-font-size',
				'--nldesign-component-unordered-list-font-size',
			])
			expect(result.missing, 'not declared in defaults.css').toEqual([])
			expect(result.unresolved, 'resolve to nothing on the page').toEqual([])
		})
	})
})

/**
 * Which of `names` lack a default in the served defaults.css, and which
 * resolve to nothing on the live page. Both lists are empty when every token
 * is supported.
 */
async function unsupported(
	page: Page,
	names: string[],
): Promise<{ missing: string[]; unresolved: string[] }> {
	const defaults = stripComments(await servedCss(page, DEFAULTS))
	const declared = declarations(defaults, '--nldesign-component-')
	const live = await rootVars(page, names)
	return {
		missing: names.filter((n) => declared.has(n) === false),
		unresolved: names.filter((n) => live[n] === ''),
	}
}
