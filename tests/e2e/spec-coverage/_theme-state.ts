/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the specs that prove the stylesheet cascade in a real
 * browser: put the instance in a known theming state, read what the page
 * actually loaded, and resolve the values the cascade produced.
 *
 * Deliberately NOT named `*.spec.ts` (see _fixtures.ts for why).
 *
 * EVERY STATE CHANGE IS UNDONE. `applyThemeState()` returns the state it found,
 * and `restoreThemeState()` puts exactly that back. Callers run the restore in
 * a `finally` or an `afterAll`, so a failing assertion never leaves the shared
 * instance on another token set, with a toggle on, or with an emptied
 * overrides file.
 */
import { expect, type Browser, type Page } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'

import { adminContext, E2E_CUSTOM_SET_PREFIX } from './_fixtures'
import {
	getOverrides,
	getTokenSet,
	requestToken,
	setMenuLabels,
	setOverrides,
	setSlogan,
	setTokenSet,
} from '../workflows/_helpers'

/** The repository root, for reading the shipped manifests the server reads too. */
export const REPO_ROOT = path.resolve(__dirname, '../../..')

/** A page the admin can always open that carries `OC` and the full cascade. */
export const PROBE_URL = '/settings/user'

/** One design system from design-systems.json. */
export type DesignSystem = { id: string; stylesheets: string[] }

/** One entry of token-sets.json. */
export type TokenSetMeta = { id: string; design_system?: string }

/** design-systems.json, as shipped. */
export function designSystems(): DesignSystem[] {
	return JSON.parse(
		fs.readFileSync(path.join(REPO_ROOT, 'design-systems.json'), 'utf8'),
	)
}

/** One design system by id; fails the test when it is not shipped. */
export function designSystem(id: string): DesignSystem {
	const found = designSystems().find((d) => d.id === id)
	expect(found, `design-systems.json must ship "${id}"`).toBeTruthy()
	return found as DesignSystem
}

/** token-sets.json, as shipped. */
export function tokenSetManifest(): TokenSetMeta[] {
	return JSON.parse(
		fs.readFileSync(path.join(REPO_ROOT, 'token-sets.json'), 'utf8'),
	)
}

/** A file under the repo, read the way the server reads it. */
export function repoFile(relative: string): string {
	return fs.readFileSync(path.join(REPO_ROOT, relative), 'utf8')
}

/** The theming state these specs change and restore. */
export type ThemeState = {
	tokenSet: string
	hideSlogan: boolean
	showMenuLabels: boolean
}

/** What `applyThemeState()` changes; anything left out is left alone. */
export type ThemeChange = Partial<ThemeState> & {
	/** Replace the overrides file of the target set (`{}` empties it). */
	overrides?: Record<string, string>
}

/** What `restoreThemeState()` needs to put the instance back. */
export type ThemeSnapshot = ThemeState & {
	/** Overrides of the target set before they were replaced, or null when untouched. */
	overrides: Record<string, string> | null
}

/**
 * Read the two display toggles from the public capability.
 *
 * The capability reports `hide_slogan === '1'` and `show_menu_labels === '1'`
 * (lib/Capabilities.php), so it is a strict read of the stored value, not of
 * whatever the admin page happens to render.
 */
export async function readToggles(
	page: Page,
): Promise<{ hideSlogan: boolean; showMenuLabels: boolean }> {
	const cap = await page.evaluate(async () => {
		const res = await fetch('/ocs/v2.php/cloud/capabilities?format=json', {
			headers: { 'OCS-APIRequest': 'true' },
		})
		return (await res.json()).ocs.data.capabilities.nldesign
	})
	expect(cap, 'the nldesign capability must be published').toBeTruthy()
	return { hideSlogan: cap.hideSlogan, showMenuLabels: cap.showMenuLabels }
}

/** Make sure the page has `OC` (an authenticated Nextcloud page) loaded. */
async function ensureOcPage(page: Page): Promise<void> {
	const hasOc = await page
		.evaluate(() => typeof (window as any).OC?.requestToken === 'string')
		.catch(() => false)
	if (hasOc === false) {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
	}
}

/**
 * Put the instance in a theming state and return what it was before.
 *
 * Order matters: the token set first, because the overrides endpoint writes
 * the file of the ACTIVE set (a set on no design system has a file of its own,
 * see CustomOverridesService::fileFor), then the overrides, then the toggles.
 */
export async function applyThemeState(
	page: Page,
	change: ThemeChange,
): Promise<ThemeSnapshot> {
	await ensureOcPage(page)
	const token = await requestToken(page)
	const toggles = await readToggles(page)
	const snapshot: ThemeSnapshot = {
		tokenSet: await getTokenSet(page, token),
		hideSlogan: toggles.hideSlogan,
		showMenuLabels: toggles.showMenuLabels,
		overrides: null,
	}

	if (change.tokenSet !== undefined) {
		await setTokenSet(page, token, change.tokenSet)
	}
	if (change.overrides !== undefined) {
		snapshot.overrides = await getOverrides(page, token)
		await setOverrides(page, token, change.overrides)
	}
	if (change.hideSlogan !== undefined) {
		await setSlogan(page, token, change.hideSlogan)
	}
	if (change.showMenuLabels !== undefined) {
		await setMenuLabels(page, token, change.showMenuLabels)
	}

	return snapshot
}

/** Undo `applyThemeState()`, in reverse order. */
export async function restoreThemeState(
	page: Page,
	snapshot: ThemeSnapshot,
): Promise<void> {
	await ensureOcPage(page)
	const token = await requestToken(page)
	await setMenuLabels(page, token, snapshot.showMenuLabels)
	await setSlogan(page, token, snapshot.hideSlogan)
	// The overrides belong to the set that was active while they were replaced,
	// so they go back BEFORE the set is switched back.
	if (snapshot.overrides !== null) {
		await setOverrides(page, token, snapshot.overrides)
	}
	await setTokenSet(page, token, snapshot.tokenSet)
}

/**
 * Run `fn` with the instance in a theming state, and always put it back.
 *
 * Uses its own admin context so the caller's page keeps whatever it had open.
 */
export async function withThemeState(
	browser: Browser,
	change: ThemeChange,
	fn: () => Promise<void>,
): Promise<void> {
	const ctx = await adminContext(browser)
	const admin = await ctx.newPage()
	try {
		const snapshot = await applyThemeState(admin, change)
		try {
			await fn()
		} finally {
			await restoreThemeState(admin, snapshot)
		}
	} finally {
		await ctx.close()
	}
}

/**
 * Upload `css` as a raw custom token set, run `fn` with its id, and always
 * delete the set again.
 *
 * For scenarios about a set that leaves tokens out. Every shipped set is
 * complete since the token sync (#1006, #1008), so such a set has to be the
 * test's own; a raw upload is stored as written, without the converter that
 * would fill in what it leaves out. It has no design_system, so it resolves to
 * the nldesign bundle. The name starts with "E2E", so the id starts with
 * E2E_CUSTOM_SET_PREFIX and removeE2eCustomSets() also clears a set that a
 * dying test left behind.
 *
 * Uses its own admin context, like withThemeState(). Nest withThemeState()
 * inside `fn`, so the previous set is active again before this one is deleted.
 */
export async function withUploadedSet(
	browser: Browser,
	label: string,
	css: string,
	fn: (id: string) => Promise<void>,
): Promise<void> {
	const ctx = await adminContext(browser)
	const admin = await ctx.newPage()
	try {
		await ensureOcPage(admin)
		const token = await requestToken(admin)
		const call = (method: string, url: string, body?: unknown) =>
			admin.evaluate(
				async ({ m, u, b, t }) => {
					const r = await fetch((window as any).OC.generateUrl(u), {
						method: m,
						headers: {
							'Content-Type': 'application/json',
							requesttoken: t,
						},
						body: b === undefined ? undefined : JSON.stringify(b),
					})
					let json: any = null
					try {
						json = await r.json()
					} catch {
						json = null
					}
					return { status: r.status, json }
				},
				{ m: method, u: url, b: body, t: token },
			)

		const res = await call('POST', '/apps/thematiq/settings/tokensets/upload', {
			name: `E2E ${label} ${Date.now()}`,
			raw: true,
			content: css,
		})
		expect(res.status, JSON.stringify(res.json)).toBe(200)
		const id = String(res.json?.id ?? '')
		expect(id.startsWith(E2E_CUSTOM_SET_PREFIX), `uploaded set id ${id}`).toBe(
			true,
		)
		try {
			await fn(id)
		} finally {
			await call(
				'DELETE',
				`/apps/thematiq/settings/tokensets/custom/${encodeURIComponent(id)}`,
			)
		}
	} finally {
		await ctx.close()
	}
}

/** An anonymous context: no session, so `/login` is the real login page. */
export async function anonymousPage(
	browser: Browser,
): Promise<{ page: Page; close: () => Promise<void> }> {
	const ctx = await browser.newContext({
		storageState: { cookies: [], origins: [] },
	})
	const page = await ctx.newPage()
	return { page, close: () => ctx.close() }
}

/** Open the real login page in an anonymous page and wait for the form. */
export async function openLoginPage(page: Page): Promise<void> {
	await page.goto('/index.php/login', { waitUntil: 'domcontentloaded' })
	await page
		.locator('input[name="user"]')
		.waitFor({ state: 'visible', timeout: 30_000 })
}

/**
 * A Thematiq stylesheet path, as its name under `css/` without the extension,
 * or null for any other stylesheet.
 *
 * Shipped layers are static files (`/apps/thematiq/css/<name>.css`); the files
 * an admin writes at runtime live in app data and are served by route
 * (`/index.php/apps/thematiq/runtime/css/<name>.css`, RuntimeFileController,
 * #811). Both are the same layer to the cascade, so both map to `<name>`.
 */
export function thematiqLayerName(pathname: string): string | null {
	const match = /\/thematiq\/(?:runtime\/)?css\/(.+)\.css$/.exec(pathname)
	return match === null ? null : match[1]
}

/**
 * The Thematiq stylesheets a page loaded, in document order, as their path
 * under `css/` without the extension: `systems/nldesign/fonts`,
 * `tokens/rijkshuisstijl`, `custom-overrides`, `hide-slogan`, ...
 *
 * These are the names `CssInjectionService` emits, so a list read here
 * compares directly with design-systems.json. Runtime files (custom-overrides,
 * custom-css, uploaded sets) are listed under the same names.
 */
export async function thematiqLayers(page: Page): Promise<string[]> {
	const hrefs = await page.evaluate(() =>
		[...document.querySelectorAll('link[rel="stylesheet"]')].map(
			(l) => (l as HTMLLinkElement).href,
		),
	)
	const layers: string[] = []
	for (const href of hrefs) {
		const name = thematiqLayerName(new URL(href).pathname)
		if (name !== null) layers.push(name)
	}
	return layers
}

/** The absolute href of one loaded Thematiq layer, or null when not loaded. */
export async function layerHref(page: Page, layer: string): Promise<string | null> {
	const hrefs = await page.evaluate(() =>
		[...document.querySelectorAll('link[rel="stylesheet"]')].map(
			(l) => (l as HTMLLinkElement).href,
		),
	)
	return (
		hrefs.find((href) => thematiqLayerName(new URL(href).pathname) === layer)
		?? null
	)
}

/**
 * The stylesheets thematiq writes at runtime, by their name under `css/`.
 * Mirrors the css patterns of lib/Service/RuntimeFile/RuntimeFileNames.php.
 * A shipped token set is a static file; an uploaded one (`custom-*`) is not.
 */
const RUNTIME_CSS =
	/^(custom-overrides(-[a-z0-9-]+)?|custom-css|tokens\/(dark\/)?custom-[a-z0-9-]+)$/

/**
 * Fetch a stylesheet as the server serves it to this page.
 *
 * A shipped file's URL comes from `OC.filePath()`, the platform's own
 * resolver, so it is right under both `apps/` and `custom_apps/` (see
 * appAssetUrl in _helpers). A runtime file is served by the
 * `runtimeFile#serve` route from app data, never from the app folder.
 */
export async function servedCss(page: Page, file: string): Promise<string> {
	const url = await page.evaluate(
		({ f, runtime }) =>
			runtime
				? (window as any).OC.generateUrl(
						'/apps/thematiq/runtime/css/' + f + '.css',
					)
				: (window as any).OC.filePath('thematiq', 'css', f + '.css'),
		{ f: file, runtime: RUNTIME_CSS.test(file) },
	)
	const res = await page.request.get(url)
	expect(res.status(), `css/${file}.css must be served (${url})`).toBe(200)
	return res.text()
}

/** CSS with every comment removed. */
export function stripComments(css: string): string {
	return css.replace(/\/\*[\s\S]*?\*\//g, '')
}

/**
 * Every custom property declared in the `:root` blocks of a stylesheet, with
 * whitespace collapsed (`var(\n --x\n)` reads as `var(--x)`).
 */
export function rootDeclarations(css: string): Map<string, string> {
	const decls = new Map<string, string>()
	const body = stripComments(css)
	const blockRe = /:root\s*\{([^}]*)\}/g
	let block: RegExpExecArray | null
	while ((block = blockRe.exec(body)) !== null) {
		for (const part of block[1].split(';')) {
			const colon = part.indexOf(':')
			if (colon === -1) continue
			const name = part.slice(0, colon).trim()
			if (!name.startsWith('--')) continue
			const value = part
				.slice(colon + 1)
				.replace(/\s+/g, ' ')
				.replace(/\(\s+/g, '(')
				.replace(/\s+\)/g, ')')
				.trim()
			decls.set(name, value)
		}
	}
	return decls
}

/** The computed value of a custom property on `:root`. */
export async function rootVar(page: Page, name: string): Promise<string> {
	return page.evaluate(
		(n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(),
		name,
	)
}

/** The computed value of a custom property on `body`. */
export async function bodyVar(page: Page, name: string): Promise<string> {
	return page.evaluate(
		(n) => getComputedStyle(document.body).getPropertyValue(n).trim(),
		name,
	)
}

/**
 * Resolve any CSS colour (hex, rgba(), a `var()` chain) to the `rgb()` /
 * `rgba()` string the browser computes for it, so two spellings of one colour
 * compare equal. The probe's inline `!important` outranks every author rule.
 */
export async function resolveColor(page: Page, value: string): Promise<string> {
	return page.evaluate((v) => {
		const probe = document.createElement('span')
		probe.style.setProperty('color', v, 'important')
		document.body.appendChild(probe)
		const resolved = getComputedStyle(probe).color
		probe.remove()
		return resolved
	}, value)
}

/** `rgb(a)` string to channels; alpha defaults to 1. */
export function parseRgb(value: string): [number, number, number, number] {
	const m =
		/rgba?\(\s*([\d.]+)[,\s]+([\d.]+)[,\s]+([\d.]+)(?:[,\s/]+([\d.]+))?\s*\)/.exec(
			value,
		)
	expect(m, `"${value}" must be an rgb() colour`).not.toBeNull()
	const [, r, g, b, a] = m as RegExpExecArray
	return [Number(r), Number(g), Number(b), a === undefined ? 1 : Number(a)]
}

/** WCAG 2.x relative luminance of an opaque colour. */
function luminance([r, g, b]: [number, number, number, number]): number {
	const lin = (c: number) => {
		const s = c / 255
		return s <= 0.03928 ? s / 12.92 : ((s + 0.055) / 1.055) ** 2.4
	}
	return 0.2126 * lin(r) + 0.7152 * lin(g) + 0.0722 * lin(b)
}

/**
 * WCAG 2.x contrast ratio of a foreground over a background.
 *
 * A translucent foreground is composited over the background first, which is
 * what the eye sees.
 */
export function contrastRatio(fg: string, bg: string): number {
	const back = parseRgb(bg)
	const front = parseRgb(fg)
	const a = front[3]
	const composite: [number, number, number, number] = [
		front[0] * a + back[0] * (1 - a),
		front[1] * a + back[1] * (1 - a),
		front[2] * a + back[2] * (1 - a),
		1,
	]
	const l1 = luminance(composite)
	const l2 = luminance(back)
	return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05)
}
