/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Helpers for the specs that make claims about token CSS: which stylesheets a
 * page carries, in which order, and what a custom property resolves to.
 *
 * Every one of those claims is observable in a browser. The order of the
 * `<link rel="stylesheet">` elements is in the DOM, a custom property's value
 * is `getComputedStyle(document.documentElement).getPropertyValue()`, and a
 * served stylesheet is one `fetch` away. These helpers exist so that a spec
 * does not need to say otherwise.
 *
 * Deliberately NOT named `*.spec.ts`, for the reason given in `_fixtures.ts`.
 */
import { expect, type Browser, type Page } from '@playwright/test'
import { adminContext } from './_fixtures'
import {
	THEMING_URL,
	appAssetUrl,
	getOverrides,
	getTokenSet,
	requestToken,
	setOverrides,
	setTokenSet,
} from '../workflows/_helpers'

/**
 * A themed page that runs no admin script.
 *
 * The admin theming page is not used to read values: js/admin.js writes
 * custom properties onto `document.documentElement.style` for its live
 * preview, so what it reports would be the preview, not the cascade.
 */
export const THEMED_PAGE = '/settings/user'

/** Open the themed page and wait until Nextcloud's `OC` global exists. */
export async function openThemedPage(page: Page): Promise<void> {
	await page.goto(THEMED_PAGE)
	await page.waitForLoadState('domcontentloaded')
	await page.waitForFunction(
		() => typeof (window as unknown as { OC?: unknown }).OC !== 'undefined',
		null,
		{ timeout: 30_000 },
	)
}

/**
 * Make `tokenSet` the instance's active set, with no admin overrides on top.
 *
 * The overrides file of a set on a design system is shared by every such set,
 * so whatever an admin pinned there would win over the token file and the
 * test would be reading the admin's value instead of the set's. It is
 * snapshotted and emptied AFTER the switch, because which overrides file the
 * API reads depends on the active set.
 *
 * Returns the function that puts both back. Call it in `afterAll` or in a
 * `finally`. If emptying the overrides fails, the set is switched back
 * before the error is rethrown, so a half-done setup leaves nothing behind.
 */
export async function activateTokenSet(
	browser: Browser,
	tokenSet: string,
): Promise<() => Promise<void>> {
	const ctx = await adminContext(browser)
	const page = await ctx.newPage()
	try {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		const previousSet = await getTokenSet(page, token)
		await setTokenSet(page, token, tokenSet)
		let previousOverrides: Record<string, string>
		try {
			previousOverrides = await getOverrides(page, token)
			await setOverrides(page, token, {})
		} catch (error) {
			await setTokenSet(page, token, previousSet)
			throw error
		}

		return async () => {
			const restoreCtx = await adminContext(browser)
			const restorePage = await restoreCtx.newPage()
			try {
				await restorePage.goto(THEMING_URL)
				await restorePage.waitForLoadState('domcontentloaded')
				const restoreToken = await requestToken(restorePage)
				// Overrides first, while the set whose file they belong to is
				// still the active one.
				await setOverrides(restorePage, restoreToken, previousOverrides)
				await setTokenSet(restorePage, restoreToken, previousSet)
				expect(await getTokenSet(restorePage, restoreToken)).toBe(
					previousSet,
				)
			} finally {
				await restoreCtx.close()
			}
		}
	} finally {
		await ctx.close()
	}
}

/** Read several custom properties off the document root, trimmed. */
export async function rootVars(
	page: Page,
	names: string[],
): Promise<Record<string, string>> {
	return page.evaluate((list) => {
		const style = getComputedStyle(document.documentElement)
		const out: Record<string, string> = {}
		for (const name of list) {
			out[name] = style.getPropertyValue(name).trim()
		}
		return out
	}, names)
}

/**
 * The pathnames of the page's stylesheet links, in document order.
 *
 * The query string is dropped: Nextcloud appends a cache-busting `?v=`, and
 * the load order is what is being asserted, not the cache key.
 */
export async function stylesheetPaths(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		Array.from(
			document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'),
		).map((link) => new URL(link.href, document.baseURI).pathname),
	)
}

/**
 * The index of the first stylesheet whose path ends in `/css/<file>.css`.
 * -1 when the page carries no such stylesheet.
 */
export function indexOfCss(paths: string[], file: string): number {
	return paths.findIndex((p) => p.endsWith(`/css/${file}.css`))
}

/** Fetch a stylesheet this app serves, e.g. `tokens/vng.css`, as text. */
export async function servedCss(page: Page, file: string): Promise<string> {
	const url = await appAssetUrl(page, 'css', file)
	const res = await page.request.get(url)
	expect(res.ok(), `${file} should be served (${url}, HTTP ${res.status()})`).toBe(
		true,
	)
	return res.text()
}

/** Remove block comments, so a declaration quoted in a comment is not read. */
export function stripComments(css: string): string {
	return css.replace(/\/\*[\s\S]*?\*\//g, '')
}

/**
 * The custom property declarations in a stylesheet whose name starts with
 * `prefix`, in source order. Comments are stripped first. A property declared
 * twice keeps its last value, as the cascade would.
 */
export function declarations(css: string, prefix: string): Map<string, string> {
	const out = new Map<string, string>()
	const escaped = prefix.replace(/[-/\\^$*+?.()|[\]{}]/g, '\\$&')
	const re = new RegExp(`(${escaped}[A-Za-z0-9_-]*)\\s*:\\s*([^;}]+)`, 'g')
	for (const match of stripComments(css).matchAll(re)) {
		out.set(match[1], match[2].trim())
	}
	return out
}

/**
 * Normalise a CSS value for comparison: lower case, one quote style, single
 * spaces, no space after a comma or inside parentheses. Browsers keep a custom
 * property's value close to how it was written, but not byte for byte: a
 * string may come back in double quotes where the file used single ones.
 */
export function normaliseCss(value: string): string {
	return value
		.toLowerCase()
		.replace(/"/g, "'")
		.replace(/\s+/g, ' ')
		.replace(/\s*,\s*/g, ',')
		.replace(/\(\s*/g, '(')
		.replace(/\s*\)/g, ')')
		.trim()
}

/** Relative WCAG 2.x luminance of a `#rgb` or `#rrggbb` colour. */
export function luminance(hex: string): number {
	let h = hex.replace('#', '').trim()
	if (h.length === 3) {
		h = h
			.split('')
			.map((c) => c + c)
			.join('')
	}
	const channel = [0, 2, 4].map((i) => {
		const v = parseInt(h.slice(i, i + 2), 16) / 255
		return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
	})
	return 0.2126 * channel[0] + 0.7152 * channel[1] + 0.0722 * channel[2]
}

/** WCAG 2.x contrast ratio of two hex colours. */
export function contrastRatio(a: string, b: string): number {
	const la = luminance(a)
	const lb = luminance(b)
	return (Math.max(la, lb) + 0.05) / (Math.min(la, lb) + 0.05)
}
