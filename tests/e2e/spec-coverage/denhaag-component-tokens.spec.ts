/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/denhaag-component-tokens/spec.md
 *
 * The Den Haag components a portal adopts, painted in a real browser by the
 * public bridge and a shipped set. Each test builds the page a portal sees,
 * in the order it links them: the nldesign defaults, css/public-bridge.css,
 * then the set's own file, followed by the pinned @gemeente-denhaag component
 * CSS vendored under scripts/sources/denhaag/. It then renders the component
 * and reads its computed style.
 *
 * The stylesheets are read from this checkout, so the spec never opens the
 * Nextcloud instance and changes nothing on it. tests/vitest/denhaagBridge.spec.js
 * makes the same claims by resolving the declarations; this spec is the
 * browser's word on them.
 */
import { readFileSync } from 'fs'
import { join } from 'path'
import { test, expect, type Page } from '@playwright/test'

const ROOT = join(__dirname, '..', '..', '..')
const read = (rel: string): string => readFileSync(join(ROOT, rel), 'utf8')

/** The component CSS of the pinned packages, as vendored. */
const CARD_CSS = read('scripts/sources/denhaag/card@5.1.4.css')
const STEP_MARKER_CSS = read('scripts/sources/denhaag/step-marker@3.1.3.css')

/**
 * The case card titles are NL Design System paragraphs: the card hands them
 * its colours through `--nl-paragraph-color`, which the paragraph component
 * paints. This is that component's colour rule.
 */
const PARAGRAPH_CSS = '.nl-paragraph { color: var(--nl-paragraph-color); }'

/**
 * Load a portal page: defaults, then (optionally) the bridge, then the set,
 * then the component CSS, then `body`.
 */
async function portalPage(
	page: Page,
	setId: string,
	body: string,
	withBridge = true,
): Promise<void> {
	const sheets = [
		read('css/systems/nldesign/defaults.css'),
		withBridge ? read('css/public-bridge.css') : '',
		read(`css/tokens/${setId}.css`),
		PARAGRAPH_CSS,
		CARD_CSS,
		STEP_MARKER_CSS,
	]
	const styles = sheets
		.filter((css) => css !== '')
		.map((css) => `<style>${css}</style>`)
		.join('\n')
	// The body ink is a colour no set uses, so a component colour that did
	// not resolve, and fell back to the inherited one, cannot pass for the
	// set's (tilburg's text is plain black, the browser's default).
	await page.setContent(
		`<!doctype html><html><head>${styles}<style>body { color: rgb(1, 2, 3); }</style></head><body>${body}</body></html>`,
	)
}

/** The computed `prop` of the element matching `selector`. */
async function styleOf(page: Page, selector: string, prop: string): Promise<string> {
	return page.evaluate(
		({ selector, prop }) => {
			const el = document.querySelector(selector)
			if (el === null) {
				throw new Error(`no element matches ${selector}`)
			}
			return getComputedStyle(el).getPropertyValue(prop).trim()
		},
		{ selector, prop },
	)
}

/** What colour the set's own `token` paints, read off a probe element. */
async function tokenColour(page: Page, token: string): Promise<string> {
	return page.evaluate((name) => {
		const probe = document.createElement('span')
		probe.style.color = `var(${name})`
		document.body.appendChild(probe)
		const colour = getComputedStyle(probe).color
		probe.remove()
		return colour
	}, token)
}

const CASE_CARD =
	'<div class="denhaag-case-card">'
	+ '<div class="denhaag-case-card__wrapper">'
	+ '<p class="nl-paragraph denhaag-case-card__title">Aanvraag parkeervergunning</p>'
	+ '<p class="nl-paragraph denhaag-case-card__subtitle">Ingediend op 3 november</p>'
	+ '</div></div>'

test.describe('denhaag-component-tokens', () => {
	test(
		// @e2e denhaag-component-tokens::a-set-with-only-the-semantic-layer-paints-a-case-card
		'a set with only the semantic layer paints a case card in its own colours',
		async ({ page }) => {
			// `denhaag` itself now declares Den Haag's own case card values
			// (scripts/brands/denhaag.json), so the semantic-only set of the
			// scenario is `tilburg`, which declares no --denhaag-* property.
			expect(read('css/tokens/tilburg.css')).not.toContain('--denhaag-')
			await portalPage(page, 'tilburg', CASE_CARD)

			const border = await styleOf(
				page,
				'.denhaag-case-card',
				'border-bottom-color',
			)
			const title = await styleOf(page, '.denhaag-case-card__title', 'color')
			const subtitle = await styleOf(
				page,
				'.denhaag-case-card__subtitle',
				'color',
			)
			const background = await page.evaluate(() =>
				getComputedStyle(document.documentElement)
					.getPropertyValue('--denhaag-case-card-background-color')
					.trim(),
			)

			expect(background, 'the card background resolves').not.toBe('')
			expect(title).toBe(await tokenColour(page, '--nldesign-color-text'))
			expect(subtitle).toBe(
				await tokenColour(page, '--nldesign-color-text-muted'),
			)
			expect(border).toBe(await tokenColour(page, '--nldesign-color-border'))
		},
	)

	test(
		// @e2e denhaag-component-tokens::the-primary-colour-reaches-the-current-step
		'the primary colour reaches the current step',
		async ({ page }) => {
			await portalPage(
				page,
				'tilburg',
				'<span class="denhaag-step-marker denhaag-step-marker--current">2</span>',
			)
			const marker = await styleOf(
				page,
				'.denhaag-step-marker--current',
				'background-color',
			)
			const number = await styleOf(
				page,
				'.denhaag-step-marker--current',
				'color',
			)
			expect(marker).toBe(await tokenColour(page, '--nldesign-color-primary'))
			expect(number).toBe(
				await tokenColour(page, '--nldesign-color-primary-text'),
			)
		},
	)

	test(
		// @e2e denhaag-component-tokens::rotterdam-keeps-its-process-steps
		'rotterdam keeps its process steps with the bridge loaded first',
		async ({ page }) => {
			const names = [
				...read('css/tokens/rotterdam.css').matchAll(
					/(--denhaag-process-steps-[a-z0-9-]+)\s*:/g,
				),
			].map((m) => m[1])
			expect(names.length).toBeGreaterThan(0)

			const readAll = (): Promise<Record<string, string>> =>
				page.evaluate((list) => {
					const style = getComputedStyle(document.documentElement)
					const out: Record<string, string> = {}
					for (const name of list) {
						out[name] = style.getPropertyValue(name).trim()
					}
					return out
				}, names)

			await portalPage(page, 'rotterdam', '', false)
			const alone = await readAll()
			await portalPage(page, 'rotterdam', '', true)
			const withBridge = await readAll()

			for (const name of names) {
				expect(alone[name], `${name} resolves`).not.toBe('')
			}
			expect(withBridge).toEqual(alone)
		},
	)
})
