/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/dark-mode/spec.md
 *
 * Summer Breeze in a real browser (thematiq#1022): with the summer-breeze set
 * active, the colours the page resolves for body text, the primary button,
 * links and a text input reach AA in light mode and in both dark scopes (the
 * auto theme on a dark OS, and the explicit dark theme), and the focus ring
 * reaches 3:1.
 *
 * Every test switches the instance to summer-breeze through withThemeState(),
 * which puts the previous set back in a finally. That is a state change: run
 * this file only against a disposable instance, never the shared :8080 one
 * (tests/e2e/shared-instance.ts refuses that unless it is named twice).
 */
import { test, expect, type Page } from '@playwright/test'

import {
	PROBE_URL,
	designSystem,
	thematiqLayers,
	withThemeState,
} from './_theme-state'

/** The explicit theme attributes Nextcloud writes on <body> server-side. */
type Scope = 'light' | 'auto-dark' | 'explicit-dark'

/** Put the probe page in one scope and wait for the style recalculation. */
async function enterScope(page: Page, scope: Scope): Promise<void> {
	await page.emulateMedia({
		colorScheme: scope === 'auto-dark' ? 'dark' : 'light',
	})
	await page.evaluate((s) => {
		const body = document.body
		for (const name of [...body.getAttributeNames()]) {
			if (name.startsWith('data-theme-')) body.removeAttribute(name)
		}
		if (s === 'explicit-dark') {
			body.setAttribute('data-themes', 'dark')
			body.setAttribute('data-theme-dark', '')
		} else {
			// The auto theme: no explicit choice, so the media query decides.
			body.setAttribute('data-themes', '')
		}
	}, scope)
	await page.evaluate(
		() =>
			new Promise<void>((r) =>
				requestAnimationFrame(() => requestAnimationFrame(() => r())),
			),
	)
}

/**
 * Any CSS colour (hex, rgb(), color(srgb ...), a var() chain) as the opaque
 * [r, g, b, a] the browser paints, read back off a 1x1 canvas so every
 * serialisation compares the same way.
 */
async function paint(page: Page, value: string): Promise<number[]> {
	return page.evaluate((v) => {
		const probe = document.createElement('span')
		probe.style.setProperty('color', v, 'important')
		document.body.appendChild(probe)
		const resolved = getComputedStyle(probe).color
		probe.remove()
		const canvas = document.createElement('canvas')
		canvas.width = 1
		canvas.height = 1
		const ctx = canvas.getContext('2d') as CanvasRenderingContext2D
		ctx.fillStyle = resolved
		ctx.fillRect(0, 0, 1, 1)
		const [r, g, b, a] = ctx.getImageData(0, 0, 1, 1).data
		return [r, g, b, a / 255]
	}, value)
}

/** WCAG relative luminance of an opaque colour. */
function luminance([r, g, b]: number[]): number {
	const lin = (c: number) => {
		const s = c / 255
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
	}
	return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)
}

/** WCAG contrast of a (possibly translucent) foreground over a background. */
function ratio(fg: number[], bg: number[]): number {
	const a = fg[3] ?? 1
	const front = [0, 1, 2].map((i) => fg[i] * a + bg[i] * (1 - a))
	const [hi, lo] = [luminance(front), luminance(bg)].sort((x, y) => y - x)
	return (hi + 0.05) / (lo + 0.05)
}

/** The value a custom property computes to on <body>. */
async function bodyValue(page: Page, name: string): Promise<string> {
	return page.evaluate(
		(n) => getComputedStyle(document.body).getPropertyValue(n).trim(),
		name,
	)
}

/** A text input appended to the page, with its painted border and fill. */
async function inputColours(
	page: Page,
): Promise<{ border: string; fill: string }> {
	return page.evaluate(() => {
		let input = document.getElementById('e2e-summer-input') as HTMLInputElement
		if (input === null) {
			input = document.createElement('input')
			input.type = 'text'
			input.id = 'e2e-summer-input'
			input.style.cssText = 'position:fixed;top:80px;left:80px;z-index:100000'
			document.body.appendChild(input)
		}
		const s = getComputedStyle(input)
		const fill =
			s.backgroundColor === 'rgba(0, 0, 0, 0)'
				? getComputedStyle(document.body).getPropertyValue(
						'--color-main-background',
					)
				: s.backgroundColor
		return { border: s.borderTopColor, fill }
	})
}

/** Open the probe page with the summer-breeze set active. */
async function openProbe(page: Page): Promise<void> {
	await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
	const layers = await thematiqLayers(page)
	for (const sheet of designSystem('summer-breeze').stylesheets) {
		expect(layers, `${sheet} is loaded`).toContain(sheet)
	}
	expect(layers).toContain('tokens/summer-breeze')
}

/** The pairs every scope must clear, measured on what the page resolves. */
async function expectLegible(page: Page, scope: Scope): Promise<void> {
	const main = await paint(page, await bodyValue(page, '--color-main-background'))
	const pairs: Array<[string, string, string, number]> = [
		['body text', '--color-main-text', '--color-main-background', 4.5],
		[
			'primary button label',
			'--color-primary-element-text',
			'--color-primary-element',
			4.5,
		],
		[
			'secondary button label',
			'--color-primary-element-light-text',
			'--color-primary-element-light',
			4.5,
		],
		['link', '--color-primary-element', '--color-main-background', 4.5],
		['error text', '--color-error-text', '--color-main-background', 4.5],
	]
	for (const [name, fg, bg, threshold] of pairs) {
		const background = await paint(page, await bodyValue(page, bg))
		const foreground = await paint(page, await bodyValue(page, fg))
		const r = ratio(foreground, background)
		expect(r, `${scope}: ${name} ${r.toFixed(2)}:1`).toBeGreaterThanOrEqual(
			threshold,
		)
	}

	const input = await inputColours(page)
	const border = ratio(await paint(page, input.border), await paint(page, input.fill))
	expect(border, `${scope}: input border ${border.toFixed(2)}:1`).toBeGreaterThanOrEqual(3)

	if (scope === 'light') {
		expect(luminance(main)).toBeGreaterThan(0.8)
	} else {
		expect(luminance(main), `${scope}: the main background is dark`).toBeLessThan(0.05)
	}
}

test.describe('summer-breeze', () => {
	test(// @e2e openspec/specs/dark-mode/spec.md#text-buttons-links-and-status-colours-reach-aa-in-light-mode
	'with summer-breeze active, text, buttons, links and inputs reach AA in light mode', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { tokenSet: 'summer-breeze' }, async () => {
			await openProbe(page)
			await enterScope(page, 'light')
			await expectLegible(page, 'light')
		})
	})

	test(// @e2e openspec/specs/dark-mode/spec.md#both-dark-scopes-remap-every-colour-token-and-stay-legible
	'with summer-breeze active, the auto dark theme and the explicit dark theme are dark and legible', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { tokenSet: 'summer-breeze' }, async () => {
			await openProbe(page)
			const surfaces: Record<string, string> = {}
			for (const scope of ['auto-dark', 'explicit-dark'] as Scope[]) {
				await enterScope(page, scope)
				await expect
					.poll(
						async () =>
							luminance(
								await paint(page, await bodyValue(page, '--summer-color-surface')),
							),
						{ message: `${scope}: --summer-color-surface turns dark`, timeout: 10_000 },
					)
					.toBeLessThan(0.05)
				await expectLegible(page, scope)
				surfaces[scope] = await bodyValue(page, '--summer-color-surface')
			}
			// One generated palette, two scopes: they must agree.
			expect(surfaces['auto-dark']).toBe(surfaces['explicit-dark'])
		})
	})

	test(// @e2e openspec/specs/dark-mode/spec.md#the-summer-breeze-focus-ring-reaches-31
	'with summer-breeze active, the focus outline is the focus colour made opaque and reaches 3:1, light and dark', async ({
		browser,
		page,
	}) => {
		await withThemeState(browser, { tokenSet: 'summer-breeze' }, async () => {
			await openProbe(page)
			await page.evaluate(() => {
				const wrap = document.createElement('div')
				wrap.id = 'e2e-summer-focus'
				wrap.style.cssText =
					'position:fixed;top:120px;left:120px;z-index:100000;padding:8px'
				wrap.innerHTML =
					'<a id="e2e-summer-a" href="#a">first</a> <a id="e2e-summer-b" href="#b">second</a>'
				document.body.appendChild(wrap)
			})
			for (const scope of ['light', 'auto-dark', 'explicit-dark'] as Scope[]) {
				await enterScope(page, scope)
				await page.locator('#e2e-summer-a').focus()
				await page.keyboard.press('Tab')
				const ring = await page.evaluate(() => {
					const el = document.activeElement as HTMLElement
					const s = getComputedStyle(el)
					return {
						id: el.id,
						style: s.outlineStyle,
						width: s.outlineWidth,
						color: s.outlineColor,
						shadow: s.boxShadow,
					}
				})
				expect(ring.id).toBe('e2e-summer-b')
				expect(ring.style).toBe('solid')
				expect(ring.width).toBe('2px')

				const token = await paint(page, await bodyValue(page, '--summer-color-focus'))
				const outline = await paint(page, ring.color)
				expect(outline[3], `${scope}: the outline is opaque`).toBe(1)
				expect(outline.slice(0, 3)).toEqual(token.slice(0, 3))
				expect(token[3], `${scope}: the focus token stays translucent`).toBeLessThan(1)
				expect(ring.shadow, `${scope}: the translucent token is the halo`).not.toBe('none')

				const page_ = await paint(
					page,
					await bodyValue(page, '--summer-color-background-plain'),
				)
				const r = ratio(outline, page_)
				expect(r, `${scope}: focus outline ${r.toFixed(2)}:1`).toBeGreaterThanOrEqual(3)
			}
		})
	})
})
