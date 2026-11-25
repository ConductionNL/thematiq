/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/lasuite-stack/spec.md
 *
 * The La Suite design system bundle in a real browser: the order its five
 * layers load in, that switching away leaves none of it behind, the font
 * gate, and that the blue Cunningham base and the violet override live in
 * separate layers.
 *
 * The base-versus-override test reads the two stylesheets from this checkout
 * and never opens the instance. The others make `lasuite` the active set and
 * empty its overrides (activateTokenSet), and the font tests also set the
 * Marianne gate; everything is put back afterwards.
 */
import { readFileSync } from 'fs'
import { join } from 'path'
import { test, expect, type Page } from '@playwright/test'
import {
	activateTokenSet,
	indexOfCss,
	openThemedPage,
	stylesheetPaths,
} from './_token-css'
import { requestToken } from '../workflows/_helpers'

const ROOT = join(__dirname, '..', '..', '..')
const read = (rel: string): string => readFileSync(join(ROOT, rel), 'utf8')

/** The lasuite bundle, in the order design-systems.json declares it. */
const LAYERS = [
	'systems/lasuite/fonts',
	'systems/lasuite/defaults',
	'systems/lasuite/brand-override',
	'systems/lasuite/bridge',
	'systems/lasuite/element-overrides',
]

/** Every custom property any same-origin stylesheet on the page declares. */
async function declaredProperties(page: Page): Promise<string[]> {
	return page.evaluate(() => {
		const names = new Set<string>()
		const walk = (rules: CSSRuleList): void => {
			for (const rule of Array.from(rules)) {
				const style = (rule as CSSStyleRule).style
				if (style !== undefined) {
					for (const name of Array.from(style)) {
						if (name.startsWith('--')) names.add(name)
					}
				}
				const nested = (rule as CSSGroupingRule).cssRules
				if (nested !== undefined) walk(nested)
			}
		}
		for (const sheet of Array.from(document.styleSheets)) {
			try {
				walk(sheet.cssRules)
			} catch {
				// A cross-origin sheet cannot be read; it is not ours.
			}
		}
		return [...names]
	})
}

/** Set the Marianne acknowledgement gate; returns what it was. */
async function setMarianne(page: Page, enabled: boolean): Promise<boolean> {
	await page.goto('/settings/admin/theming')
	await page.waitForLoadState('domcontentloaded')
	const token = await requestToken(page)
	return page.evaluate(
		async ({ t, on }) => {
			const OC = (
				window as unknown as { OC: { generateUrl: (p: string) => string } }
			).OC
			const url = OC.generateUrl('/apps/thematiq/settings/marianne')
			const before = (await (
				await fetch(url, { headers: { requesttoken: t } })
			).json()) as { enabled: boolean }
			const r = await fetch(url, {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', requesttoken: t },
				body: JSON.stringify({ enabled: on }),
			})
			if (!r.ok) throw new Error(`marianne gate: HTTP ${r.status}`)
			return before.enabled
		},
		{ t: token, on: enabled },
	)
}

/** Open the themed page, recording every request made while it loads. */
async function openRecording(page: Page): Promise<string[]> {
	const requests: string[] = []
	page.on('request', (r) => requests.push(r.url()))
	await openThemedPage(page)
	await page.evaluate(() => document.fonts.ready)
	return requests
}

test.describe('lasuite-stack', () => {
	test(
		// @e2e lasuite-stack::brand-base-matches-the-published-cunningham-blue-base
		'the defaults layer alone is the blue base, the violet comes from the override',
		async ({ page }) => {
			const brand600 = async (sheets: string[]): Promise<string> => {
				const styles = sheets
					.map((file) => `<style>${read(`css/${file}.css`)}</style>`)
					.join('')
				await page.setContent(
					`<!doctype html><html><head>${styles}</head><body></body></html>`,
				)
				return page.evaluate(() =>
					getComputedStyle(document.documentElement)
						.getPropertyValue('--lasuite--globals--colors--brand-600')
						.trim()
						.toLowerCase(),
				)
			}
			expect(await brand600(['systems/lasuite/defaults'])).toBe('#0659c5')
			// The header comment names the violet to explain where it lives; no
			// declaration in this layer carries it.
			const declarations = read('css/systems/lasuite/defaults.css')
				.replace(/\/\*[\s\S]*?\*\//g, '')
				.toLowerCase()
			expect(declarations).not.toContain('#4844ad')
			expect(
				await brand600([
					'systems/lasuite/defaults',
					'systems/lasuite/brand-override',
				]),
			).toBe('#534fc2')
		},
	)

	test.describe('with lasuite active', () => {
		test.describe.configure({ mode: 'serial', timeout: 120_000 })

		let restore: (() => Promise<void>) | null = null

		test.beforeAll(async ({ browser }) => {
			restore = await activateTokenSet(browser, 'lasuite')
		})

		test.afterAll(async () => {
			if (restore !== null) {
				await restore()
			}
		})

		test(
			// @e2e lasuite-stack::bundle-resolves-and-loads-in-declared-order
			'the five layers load in order, then the set, then the overrides',
			async ({ page }) => {
				await openThemedPage(page)
				const paths = await stylesheetPaths(page)
				const positions = LAYERS.map((file) => indexOfCss(paths, file))
				for (const [i, file] of LAYERS.entries()) {
					expect(positions[i], `${file} is linked`).toBeGreaterThanOrEqual(
						0,
					)
					if (i > 0) {
						expect(
							positions[i],
							`${file} after ${LAYERS[i - 1]}`,
						).toBeGreaterThan(positions[i - 1])
					}
				}
				const set = indexOfCss(paths, 'tokens/lasuite')
				expect(set, 'tokens/lasuite is linked').toBeGreaterThan(
					positions[positions.length - 1],
				)
				const overrides = paths.findIndex((p) =>
					p.includes('custom-overrides'),
				)
				if (overrides !== -1) {
					expect(
						overrides,
						'the overrides load after the set',
					).toBeGreaterThan(set)
				}
			},
		)

		test(
			// @e2e lasuite-stack::inter-served-by-default-marianne-gated
			'with the gate off the page renders Inter and asks for no Marianne file',
			async ({ page }) => {
				const was = await setMarianne(page, false)
				try {
					const requests = await openRecording(page)
					expect(
						requests.filter((url) => /marianne/i.test(url)),
						'no Marianne request',
					).toEqual([])
					const faces = await page.evaluate(() =>
						[...document.fonts]
							.filter((f) => f.status === 'loaded')
							.map((f) => f.family.replace(/["']/g, '')),
					)
					expect(faces).toContain('Inter')
					expect(faces).not.toContain('Marianne')
				} finally {
					await setMarianne(page, was)
				}
			},
		)

		test(
			// @e2e lasuite-stack::marianne-renders-when-the-gate-is-on
			'with the gate on Marianne loads from the app itself, Inter stays the fallback',
			async ({ page }) => {
				const was = await setMarianne(page, true)
				try {
					const requests = await openRecording(page)
					const origin = new URL(page.url()).origin
					const marianne = requests.filter((url) =>
						/marianne.*\.woff2/i.test(url),
					)
					expect(
						marianne.length,
						'a Marianne file is requested',
					).toBeGreaterThan(0)
					for (const url of marianne) {
						expect(new URL(url).origin, url).toBe(origin)
						expect(new URL(url).pathname, url).toContain('/thematiq/')
					}
					const family = await page.evaluate(() =>
						getComputedStyle(document.documentElement)
							.getPropertyValue('--lasuite-font-family')
							.trim(),
					)
					const stack = family
						.split(',')
						.map((f) => f.trim().replace(/["']/g, ''))
					expect(stack[0]).toBe('Marianne')
					expect(stack).toContain('Inter')
				} finally {
					await setMarianne(page, was)
				}
			},
		)
	})

	test(
		// @e2e lasuite-stack::deactivating-lasuite-leaves-no-residue
		'after switching away from lasuite nothing of it is loaded or declared',
		async ({ browser, page }) => {
			test.setTimeout(120_000)
			const restoreLasuite = await activateTokenSet(browser, 'lasuite')
			try {
				const restoreOther = await activateTokenSet(browser, 'zuiddrecht')
				try {
					await openThemedPage(page)
					const paths = await stylesheetPaths(page)
					expect(
						paths.filter((p) => p.includes('/systems/lasuite/')),
					).toEqual([])
					const lasuite = (await declaredProperties(page)).filter((name) =>
						name.startsWith('--lasuite-'),
					)
					expect(lasuite).toEqual([])
				} finally {
					await restoreOther()
				}
			} finally {
				await restoreLasuite()
			}
		},
	)
})
