/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-sets/spec.md
 *
 * Browser proof of the token-sets spec: discovery and manifest metadata as the
 * admin list and the public catalogue return them, the active-set endpoints,
 * the stylesheet stack a set puts on the page, the preview, the selectable
 * allowlist, and the vocabulary audit's findings as they reach the admin page.
 *
 * Expected values are read from the checkout the suite runs from
 * (token-sets.json, design-systems.json, css/tokens/*.css, the allow-list
 * fixture and the audit script's required-token list), never typed in, so a
 * regenerated set does not turn a correct test red.
 *
 * Scenarios with no browser-reachable GIVEN (a corrupt shipped manifest, the
 * PHP constructor, the PHPUnit gate itself) stay excluded in the spec, each
 * naming the test that proves it.
 *
 * STATE. Every test that switches the active set, offers a set through a
 * throwaway group mapping, uploads a custom set, or writes custom CSS or
 * overrides puts it back in `finally`.
 */
import * as fs from 'fs'
import * as path from 'path'
import { test, expect, type Page, type Route } from '@playwright/test'
import {
	getTokenSet,
	offerTokenSets,
	requestToken,
	setTokenSet,
	withdrawTokenSetOffer,
} from '../workflows/_helpers'
import {
	activeTokenSet,
	adminContext,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'

const THEMING_URL = '/settings/admin/theming'
const REPO = path.resolve(__dirname, '../../..')

// ---------------------------------------------------------------------------
// The checkout, as the server reads it
// ---------------------------------------------------------------------------

type ManifestEntry = {
	id: string
	name: string
	description: string
	design_system?: string
	theming?: Record<string, string>
}

function readRepoFile(relative: string): string {
	return fs.readFileSync(path.join(REPO, relative), 'utf8')
}

const MANIFEST: ManifestEntry[] = JSON.parse(readRepoFile('token-sets.json'))
const DESIGN_SYSTEMS: Array<{ id: string; stylesheets: string[] }> = JSON.parse(
	readRepoFile('design-systems.json'),
)
/** Every shipped token set id: one per css/tokens/*.css file. */
const TOKEN_FILES: string[] = fs
	.readdirSync(path.join(REPO, 'css/tokens'))
	.filter((f: string) => f.endsWith('.css'))
	.map((f: string) => f.slice(0, -4))
	.sort()
/** The known-incomplete sets, the fixture both audit gates read. */
const ALLOWLIST: string[] = JSON.parse(
	readRepoFile('tests/Unit/fixtures/token-set-vocabulary-allowlist.json'),
).sets
/** The required vocabulary, from the audit script (asserted equal to the PHP constant by PHPUnit). */
const REQUIRED_TOKENS: string[] = (() => {
	const block = readRepoFile('scripts/audit-token-sets.mjs').match(
		/const REQUIRED_TOKENS = \[([\s\S]*?)\]/,
	)
	if (block === null)
		throw new Error('REQUIRED_TOKENS not found in scripts/audit-token-sets.mjs')
	return [...block[1].matchAll(/'(--nldesign-[a-z0-9-]+)'/g)].map((m) => m[1])
})()
/**
 * The shipped sets the admin picker offers, derived the way
 * `TokenSetSelectionPolicy::selectable()` derives it: every id named in
 * `token-sets.json` that the vocabulary allow-list does not record incomplete.
 *
 * Read from the data rather than from a PHP constant, because the constant is
 * gone: `SELECTABLE_SHIPPED_SETS = ['nextcloud', 'cunningham']` held two of 59
 * shipped sets, so an administrator could not choose any municipality at all,
 * and it stayed that way after its own stated exit condition was met because
 * nothing failed when it went stale.
 */
const SELECTABLE_SHIPPED_SETS: string[] = MANIFEST.map((e) => e.id).filter(
	(id) => !ALLOWLIST.includes(id),
)

/**
 * Shipped token files the picker withholds: one with no `token-sets.json`
 * entry. In this repository that is `css/tokens/conduction.css`, the shared
 * role layer `scripts/generate-brand-set.mjs` copies into a brand set.
 *
 * These are what the "always selectable" rules below are exercised with, since
 * every named set the audit passes is now offered anyway and a set that is
 * offered regardless proves nothing about a survival rule.
 */
const UNNAMED_TOKEN_FILES: string[] = TOKEN_FILES.filter(
	(id) => !MANIFEST.some((e) => e.id === id),
)

function manifestEntry(id: string): ManifestEntry {
	const entry = MANIFEST.find((e) => e.id === id)
	if (entry === undefined) throw new Error(`token-sets.json has no entry "${id}"`)
	return entry
}

function designSystemOf(id: string): string {
	return MANIFEST.find((e) => e.id === id)?.design_system ?? 'nldesign'
}

/** A token file's text with comments removed, as the audit reads it. */
function tokenCss(id: string): string {
	return readRepoFile(`css/tokens/${id}.css`).replace(/\/\*[\s\S]*?\*\//g, '')
}

/**
 * CssParserService::parseDeclarations(), ported: a `;` at paren depth 0 or a
 * `}` ends a declaration, strings and comments are skipped, and the first
 * `--name:` in each chunk is the declaration. Only `--nldesign-*` names are
 * kept, as the vocabulary audit keeps them.
 */
function nldesignDeclarations(css: string): Record<string, string> {
	const out: Record<string, string> = {}
	const collect = (chunk: string) => {
		const m = chunk.match(/(--[\w-]+)\s*:\s*([\s\S]*)$/)
		if (m === null) return
		const value = m[2]
			.trim()
			.replace(/\s*!\s*important\s*$/i, '')
			.trim()
		if (value !== '' && m[1].startsWith('--nldesign-')) out[m[1]] = value
	}
	let buffer = ''
	let depth = 0
	let quote: string | null = null
	for (let i = 0; i < css.length; i++) {
		const c = css[i]
		if (quote !== null) {
			buffer += c
			if (c === '\\' && i + 1 < css.length) {
				buffer += css[++i]
			} else if (c === quote) {
				quote = null
			}
			continue
		}
		if (c === '/' && css[i + 1] === '*') {
			const end = css.indexOf('*/', i + 2)
			if (end === -1) break
			i = end + 1
			continue
		}
		if (c === '"' || c === "'") {
			quote = c
			buffer += c
		} else if (c === '(') {
			depth++
			buffer += c
		} else if (c === ')') {
			depth = Math.max(0, depth - 1)
			buffer += c
		} else if ((c === ';' && depth === 0) || c === '}') {
			collect(buffer)
			buffer = ''
			if (c === '}') depth = 0
		} else {
			buffer += c
		}
	}
	return out
}

/** PHP strcasecmp: ASCII-only case folding, then byte order. */
function strcasecmp(a: string, b: string): number {
	const fold = (s: string) => s.replace(/[A-Z]/g, (c) => c.toLowerCase())
	const x = fold(a)
	const y = fold(b)
	return x < y ? -1 : x > y ? 1 : 0
}

/** A shipped set that is complete: audited, not on the allow-list. */
const COMPLETE_SET = (() => {
	const id = TOKEN_FILES.find(
		(s) =>
			designSystemOf(s) === 'nldesign'
			&& MANIFEST.some((e) => e.id === s)
			&& !ALLOWLIST.includes(s),
	)
	if (id === undefined) throw new Error('no complete nldesign set in the checkout')
	return id
})()

/** A set that ships a CSS file and no manifest entry (css/tokens/conduction.css). */
const SET_WITHOUT_MANIFEST = (() => {
	const id = TOKEN_FILES.find((s) => !MANIFEST.some((e) => e.id === s))
	if (id === undefined) throw new Error('every token file has a manifest entry')
	return id
})()

// ---------------------------------------------------------------------------
// Browser helpers
// ---------------------------------------------------------------------------

async function openSettings(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await expect(page.locator('#nldesign-token-set-select')).toBeVisible({
		timeout: 30_000,
	})
	// Every call from this page reads OC.requestToken: wait for it.
	await page.waitForFunction(
		() => typeof (window as any).OC?.requestToken === 'string',
		null,
		{ timeout: 30_000 },
	)
}

/** A request from inside the authenticated page, with status and parsed body. */
async function call(
	page: Page,
	method: string,
	appPath: string,
	body?: unknown,
): Promise<{ status: number; json: any }> {
	return page.evaluate(
		async ({ m, p, b }) => {
			const OC = (
				window as unknown as {
					OC: { generateUrl: (u: string) => string; requestToken: string }
				}
			).OC
			const headers: Record<string, string> = { requesttoken: OC.requestToken }
			if (b !== undefined) headers['Content-Type'] = 'application/json'
			const r = await fetch(OC.generateUrl(p), {
				method: m,
				headers,
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
		{ m: method, p: appPath, b: body },
	)
}

async function adminList(page: Page): Promise<any[]> {
	const res = await call(page, 'GET', '/apps/thematiq/settings/tokensets')
	expect(res.status).toBe(200)
	return res.json.tokenSets
}

async function publicCatalogue(page: Page): Promise<any[]> {
	const res = await call(page, 'GET', '/apps/thematiq/api/token-sets')
	expect(res.status).toBe(200)
	return res.json.tokenSets
}

async function layerManifest(page: Page, id: string): Promise<any> {
	const res = await call(
		page,
		'GET',
		`/apps/thematiq/settings/tokenset-stylesheets/${id}`,
	)
	expect(res.status, `layer manifest for ${id}`).toBe(200)
	return res.json
}

/** The `css/...` path of each file layer, e.g. `systems/nldesign/fonts`. */
function fileLayers(manifest: any): string[] {
	return manifest.layers
		.filter((l: any) => l.kind === 'file' && typeof l.href === 'string')
		.map((l: any) => {
			const m = (l.href as string).match(/\/css\/(.+)\.css(\?|$)/)
			return m === null ? l.href : m[1]
		})
}

/** The served text of one of this app's files. */
async function servedFile(
	page: Page,
	relative: string,
): Promise<{ status: number; text: string }> {
	return page.evaluate(async (rel) => {
		const url = (
			window as unknown as { OC: { linkTo: (a: string, f: string) => string } }
		).OC.linkTo('thematiq', rel)
		const r = await fetch(url, { cache: 'no-store' })
		return { status: r.status, text: await r.text() }
	}, relative)
}

async function cssVar(page: Page, name: string): Promise<string> {
	return page.evaluate(
		(n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(),
		name,
	)
}

/** The `css/...` path of every app stylesheet link on the page, in order. */
async function pageStylesheets(page: Page): Promise<string[]> {
	return page.evaluate(() =>
		Array.from(document.querySelectorAll('link[rel="stylesheet"]'))
			.map((l) => (l as HTMLLinkElement).href)
			.map((href) => {
				const m = href.match(/\/thematiq\/css\/(.+)\.css(\?|$)/)
				return m === null ? null : m[1]
			})
			.filter((x): x is string => x !== null),
	)
}

/** Run `body` with `setId` as the instance's active set, then put the old one back. */
async function withActiveSet(
	page: Page,
	setId: string,
	body: () => Promise<void>,
): Promise<void> {
	const token = await requestToken(page)
	const previous = await getTokenSet(page, token)
	await setTokenSet(page, token, setId)
	try {
		await body()
	} finally {
		await setTokenSet(page, token, previous)
	}
}

/** Run `body` with `sets` offered in the admin list, then withdraw the offer. */
async function withOffered(
	page: Page,
	sets: string[],
	body: () => Promise<void>,
): Promise<void> {
	const token = await requestToken(page)
	const offer = await offerTokenSets(page, token, sets)
	try {
		await body()
	} finally {
		await withdrawTokenSetOffer(page, token, offer)
	}
}

/** Upload a raw custom set, run `body` with its id, delete it afterwards. */
async function withCustomSet(
	page: Page,
	name: string,
	css: string,
	body: (id: string) => Promise<void>,
): Promise<void> {
	const res = await call(
		page,
		'POST',
		'/apps/thematiq/settings/tokensets/upload',
		{
			name,
			raw: true,
			content: css,
		},
	)
	expect(res.status, JSON.stringify(res.json)).toBe(200)
	const id: string = res.json.id
	try {
		await body(id)
	} finally {
		await call(
			page,
			'DELETE',
			`/apps/thematiq/settings/tokensets/custom/${encodeURIComponent(id)}`,
		)
	}
}

/** The vocabulary warning a set carries in the admin list, or undefined. */
function incompleteWarning(entry: any): any {
	return (entry?.warnings ?? []).find((w: any) => w && w.kind === 'incomplete')
}

test.describe('token-sets', () => {
	// -----------------------------------------------------------------------
	// Requirement: Filesystem-Based Discovery
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#token-sets-discovered-from-filesystem
	test('every css/tokens file becomes an entry with id, name, description and design system', async ({
		page,
	}) => {
		await openSettings(page)
		const catalogue = await publicCatalogue(page)
		const shippedIds = catalogue
			.map((s) => s.id)
			.filter((id: string) => !id.startsWith('custom-'))
		expect([...shippedIds].sort()).toEqual(TOKEN_FILES)
		for (const entry of catalogue) {
			expect(typeof entry.name, entry.id).toBe('string')
			expect(typeof entry.design_system, entry.id).toBe('string')
		}
		// The admin list carries the description; offer a manifest set and the
		// one without a manifest entry.
		await withOffered(page, [COMPLETE_SET, SET_WITHOUT_MANIFEST], async () => {
			const list = await adminList(page)
			for (const id of [COMPLETE_SET, SET_WITHOUT_MANIFEST]) {
				const entry = list.find((s) => s.id === id)
				expect(entry, id).toBeTruthy()
				expect(entry.name.length, id).toBeGreaterThan(0)
				expect(entry.description.length, id).toBeGreaterThan(0)
				expect(entry.design_system.length, id).toBeGreaterThan(0)
			}
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#metadata-merged-from-manifest
	test('a shipped set carries its manifest name, description, design system and theming', async ({
		page,
	}) => {
		const shipped = manifestEntry('amsterdam')
		await openSettings(page)
		await withOffered(page, ['amsterdam'], async () => {
			const entry = (await adminList(page)).find((s) => s.id === 'amsterdam')
			expect(entry.name).toBe(shipped.name)
			expect(entry.name).toBe('Gemeente Amsterdam')
			expect(entry.description).toBe(shipped.description)
			expect(entry.design_system).toBe(shipped.design_system ?? 'nldesign')
			expect(entry.theming).toEqual(shipped.theming)
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#custom-set-metadata-merged-from-appconfig-manifest
	test('an uploaded set carries its own name, description and theming and is marked custom', async ({
		page,
	}) => {
		await openSettings(page)
		const name = 'E2E Gemeente Voorbeeld ' + Date.now()
		await withCustomSet(
			page,
			name,
			':root { --nldesign-color-primary: #007bc7; }',
			async (id) => {
				expect(id.startsWith('custom-')).toBe(true)
				const entry = (await adminList(page)).find((s) => s.id === id)
				expect(entry.name).toBe(name)
				expect(entry.description).toBe('Custom token set: ' + name)
				expect(entry.theming).toEqual({ primary_color: '#007bc7' })
				expect(entry.custom).toBe(true)
			},
		)
	})

	// @e2e openspec/specs/token-sets/spec.md#css-file-exists-without-manifest-entry
	test('a token file without a manifest entry gets an id-derived name and the defaults', async ({
		page,
	}) => {
		const id = SET_WITHOUT_MANIFEST
		const expectedName = id
			.split('-')
			.map((w) => w.charAt(0).toUpperCase() + w.slice(1))
			.join(' ')
		await openSettings(page)
		await withOffered(page, [id], async () => {
			const entry = (await adminList(page)).find((s) => s.id === id)
			expect(entry, `${id} is still returned`).toBeTruthy()
			expect(entry.name).toBe(expectedName)
			expect(entry.description).toBe('Design tokens for ' + expectedName)
			expect(entry.design_system).toBe('nldesign')
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#token-sets-sorted-alphabetically
	test('shipped and uploaded sets are sorted by name, case-insensitively, as one list', async ({
		page,
	}) => {
		await openSettings(page)
		// A lower-case name in the middle of the alphabet, so a case-sensitive
		// or group-by-group sort would put it in the wrong place.
		await withCustomSet(
			page,
			'm e2e sorteer ' + Date.now(),
			':root { --nldesign-color-primary: #007bc7; }',
			async (id) => {
				const names = (await publicCatalogue(page)).map((s) => s.name)
				expect(names).toEqual([...names].sort(strcasecmp))
				const ids = (await publicCatalogue(page)).map((s) => s.id)
				expect(ids).toContain(id)
			},
		)
	})

	// -----------------------------------------------------------------------
	// Requirement: Token Set Manifest Structure
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#manifest-entry-with-full-metadata
	test('every manifest entry has the required fields, and the served entry is complete', async ({
		page,
	}) => {
		const systemIds = DESIGN_SYSTEMS.map((d) => d.id)
		for (const entry of MANIFEST) {
			expect(entry.id, 'kebab-case id').toMatch(/^[a-z0-9]+(-[a-z0-9]+)*$/)
			expect(TOKEN_FILES, `${entry.id} matches a CSS file`).toContain(entry.id)
			expect(typeof entry.name).toBe('string')
			expect(typeof entry.description).toBe('string')
			if (entry.design_system !== undefined) {
				expect(systemIds, `${entry.id} design_system`).toContain(
					entry.design_system,
				)
			}
			for (const [key, value] of Object.entries(entry.theming ?? {})) {
				expect([
					'primary_color',
					'background_color',
					'logo',
					'background',
					'logo_dark',
				]).toContain(key)
				if (key.endsWith('_color'))
					expect(value, `${entry.id} ${key}`).toMatch(/^#[0-9a-fA-F]{6}$/)
				if (key === 'logo_dark') expect(value).toMatch(/^img\/logos\//)
			}
		}
		// What the server makes of an entry without design_system: it is an
		// nldesign set, so every served entry carries one.
		await openSettings(page)
		for (const entry of await publicCatalogue(page)) {
			expect(systemIds, `${entry.id} served design_system`).toContain(
				entry.design_system,
			)
		}
	})

	// @e2e openspec/specs/token-sets/spec.md#dark-logo-metadata-passed-through
	test('logo_dark is passed through unchanged and absent where a set has none', async ({
		page,
	}) => {
		const withDark = MANIFEST.find(
			(e) => e.theming?.logo_dark !== undefined,
		) as ManifestEntry
		await openSettings(page)
		await withOffered(page, [withDark.id, 'amsterdam'], async () => {
			const list = await adminList(page)
			expect(list.find((s) => s.id === withDark.id).theming.logo_dark).toBe(
				withDark.theming?.logo_dark,
			)
			const without = list.find((s) => s.id === 'amsterdam')
			expect('logo_dark' in without.theming).toBe(false)
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#dark-logo-consumed-by-the-generated-dark-variant
	test('the generated dark file overrides the logo url in its dark blocks only', async ({
		page,
	}) => {
		const withDark = MANIFEST.find(
			(e) => e.theming?.logo_dark !== undefined,
		) as ManifestEntry
		const dark = withDark.theming?.logo_dark as string
		await openSettings(page)
		const darkCss = await servedFile(page, `css/tokens/dark/${withDark.id}.css`)
		expect(darkCss.status).toBe(200)
		const overrides = [
			...darkCss.text.matchAll(/--nldesign-logo-url:\s*url\('([^']+)'\)/g),
		]
		expect(overrides.length, 'one override per dark scope').toBeGreaterThan(0)
		for (const m of overrides) expect(m[1].endsWith(dark)).toBe(true)
		expect(darkCss.text).toMatch(/prefers-color-scheme:\s*dark/)

		const lightCss = await servedFile(page, `css/tokens/${withDark.id}.css`)
		expect(lightCss.status).toBe(200)
		expect(lightCss.text).not.toContain(dark)
	})

	// -----------------------------------------------------------------------
	// Requirement: Active Token Set Storage
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#token-set-persisted-via-api
	test('POST /settings/tokenset stores the set and answers status and id', async ({
		page,
	}) => {
		await openSettings(page)
		await withActiveSet(page, 'nextcloud', async () => {
			const res = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{ tokenSet: 'utrecht' },
			)
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', tokenSet: 'utrecht' })
			expect(await getTokenSet(page, await requestToken(page))).toBe('utrecht')
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-retrieved-via-api
	test('the public capability answers the stored set', async ({ page }) => {
		await openSettings(page)
		await withActiveSet(page, 'amsterdam', async () => {
			// GET /settings/tokenset was removed (#664, #873): scripts read
			// the capability.
			expect(await activeTokenSet(page)).toEqual({ tokenSet: 'amsterdam' })
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-read-during-boot
	test('the stored set is loaded as the token layer on the next page', async ({
		page,
	}) => {
		await openSettings(page)
		await withActiveSet(page, 'amsterdam', async () => {
			await openSettings(page)
			const sheets = await pageStylesheets(page)
			expect(sheets).toContain('tokens/amsterdam')
			const designSheets =
				DESIGN_SYSTEMS.find((d) => d.id === 'nldesign')?.stylesheets ?? []
			for (const s of designSheets) expect(sheets, s).toContain(s)
			const lastDesign = Math.max(
				...designSheets.map((s) => sheets.indexOf(s)),
			)
			expect(sheets.indexOf('tokens/amsterdam')).toBeGreaterThan(lastDesign)
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Token Set Validation
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#valid-token-set-selected
	test('an id with a CSS file is accepted and stored', async ({ page }) => {
		await openSettings(page)
		expect((await servedFile(page, 'css/tokens/utrecht.css')).status).toBe(200)
		await withActiveSet(page, 'nextcloud', async () => {
			const res = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{ tokenSet: 'utrecht' },
			)
			expect(res.status).toBe(200)
			expect(await getTokenSet(page, await requestToken(page))).toBe('utrecht')
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#invalid-token-set-rejected
	test('an id without a CSS file is refused and nothing is stored', async ({
		page,
	}) => {
		await openSettings(page)
		const token = await requestToken(page)
		const before = await getTokenSet(page, token)
		const res = await call(page, 'POST', '/apps/thematiq/settings/tokenset', {
			tokenSet: 'nonexistent',
		})
		expect(res.status).toBe(400)
		expect(res.json).toEqual({ error: 'Invalid token set' })
		expect(await getTokenSet(page, token)).toBe(before)
	})

	// @e2e openspec/specs/token-sets/spec.md#path-traversal-with-forward-slash-prevented
	test('an id with a slash is refused even when it names a real file', async ({
		page,
	}) => {
		await openSettings(page)
		const token = await requestToken(page)
		const before = await getTokenSet(page, token)
		// css/tokens/dark/amsterdam.css exists, so only the slash check refuses it.
		expect(
			(await servedFile(page, 'css/tokens/dark/amsterdam.css')).status,
		).toBe(200)
		for (const id of ['dark/amsterdam', '../../etc/passwd']) {
			const res = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{ tokenSet: id },
			)
			expect(res.status, id).toBe(400)
			expect(res.json).toEqual({ error: 'Invalid token set' })
		}
		expect(await getTokenSet(page, token)).toBe(before)
	})

	// @e2e openspec/specs/token-sets/spec.md#path-traversal-with-dot-dot-prevented
	test('an id with .. is refused', async ({ page }) => {
		await openSettings(page)
		const token = await requestToken(page)
		const before = await getTokenSet(page, token)
		for (const id of ['..%2F..%2Fetc%2Fpasswd', '..', 'amsterdam..']) {
			const res = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{ tokenSet: id },
			)
			expect(res.status, id).toBe(400)
			expect(res.json).toEqual({ error: 'Invalid token set' })
		}
		expect(await getTokenSet(page, token)).toBe(before)
	})

	// @e2e openspec/specs/token-sets/spec.md#validation-checks-actual-file-existence
	test('validity is the CSS file existing, not a manifest entry', async ({
		page,
	}) => {
		await openSettings(page)
		await withActiveSet(page, 'nextcloud', async () => {
			// No manifest entry, but a file: accepted.
			const res = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{
					tokenSet: SET_WITHOUT_MANIFEST,
				},
			)
			expect(res.status).toBe(200)
			// Passes the traversal checks, but no file: refused.
			const missing = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{
					tokenSet: 'no-such-file-e2e',
				},
			)
			expect(missing.status).toBe(400)
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Token Set CSS Structure
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#complete-token-set
	test('a complete set overrides primary and primary-text on the live page', async ({
		page,
	}) => {
		const declared = nldesignDeclarations(tokenCss('rijkshuisstijl'))
		expect(declared['--nldesign-color-primary']).toBeDefined()
		expect(declared['--nldesign-color-primary-text']).toBeDefined()
		await openSettings(page)
		await withActiveSet(page, 'rijkshuisstijl', async () => {
			await openSettings(page)
			const sheets = await pageStylesheets(page)
			expect(sheets).toContain('systems/nldesign/defaults')
			expect(sheets.indexOf('tokens/rijkshuisstijl')).toBeGreaterThan(
				sheets.indexOf('systems/nldesign/defaults'),
			)
			expect(
				(await cssVar(page, '--nldesign-color-primary')).toLowerCase(),
			).toBe(declared['--nldesign-color-primary'].toLowerCase())
			expect(
				(await cssVar(page, '--nldesign-color-primary-text')).toLowerCase(),
			).toBe(declared['--nldesign-color-primary-text'].toLowerCase())
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#incomplete-token-set-partial-overrides
	test('a set with only primary tokens falls back to defaults.css for the rest', async ({
		page,
	}) => {
		// Tokens defaults.css declares as a literal and no other nldesign stack
		// file declares, so their live value can only come from defaults.css.
		const stack = (
			DESIGN_SYSTEMS.find((d) => d.id === 'nldesign')?.stylesheets ?? []
		).filter((s) => s !== 'systems/nldesign/defaults')
		const others = stack.map((s) =>
			nldesignDeclarations(readRepoFile(`css/${s}.css`)),
		)
		const defaults = nldesignDeclarations(
			readRepoFile('css/systems/nldesign/defaults.css').replace(
				/\/\*[\s\S]*?\*\//g,
				'',
			),
		)
		const fallbacks = REQUIRED_TOKENS.filter(
			(t) =>
				!t.startsWith('--nldesign-color-primary')
				&& /^#[0-9a-fA-F]{3,6}$/.test(defaults[t] ?? '')
				&& others.every((o) => o[t] === undefined),
		)
		expect(fallbacks.length, 'defaults.css literals to check').toBeGreaterThan(3)

		await openSettings(page)
		await withCustomSet(
			page,
			'E2E alleen primair ' + Date.now(),
			':root { --nldesign-color-primary: #8a2be2; --nldesign-color-primary-text: #ffffff; }',
			async (id) => {
				const errors: string[] = []
				page.on('pageerror', (e) => errors.push(e.message))
				await withActiveSet(page, id, async () => {
					await openSettings(page)
					expect(await cssVar(page, '--nldesign-color-primary')).toBe(
						'#8a2be2',
					)
					for (const t of fallbacks) {
						expect((await cssVar(page, t)).toLowerCase(), t).toBe(
							defaults[t].toLowerCase(),
						)
					}
					for (const t of REQUIRED_TOKENS) {
						expect(await cssVar(page, t), `${t} resolves`).not.toBe('')
					}
				})
				expect(errors).toEqual([])
			},
		)
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-with-logo
	test('a set with a logo shows it in the header and on the login page', async ({
		page,
		browser,
	}) => {
		expect(
			nldesignDeclarations(tokenCss('amsterdam'))['--nldesign-logo-url'],
		).toContain('amsterdam.svg')
		await openSettings(page)
		await withActiveSet(page, 'amsterdam', async () => {
			await openSettings(page)
			const header = await page.evaluate(() => {
				const el = document.querySelector(
					'#nextcloud .logo',
				) as HTMLElement | null
				if (el === null) return null
				const root = getComputedStyle(document.documentElement)
				return {
					image: getComputedStyle(el).backgroundImage,
					width: getComputedStyle(el).width,
					widthToken: root
						.getPropertyValue('--nldesign-logo-width')
						.trim(),
				}
			})
			expect(header, 'the header logo element exists').not.toBeNull()
			expect(header?.image).toContain('amsterdam.svg')
			expect(header?.width).toBe(header?.widthToken || '62px')

			const anonymous = await browser.newContext({
				storageState: { cookies: [], origins: [] },
			})
			try {
				const login = await anonymous.newPage()
				await login.goto('/index.php/login', {
					waitUntil: 'domcontentloaded',
				})
				await login
					.locator('#body-login .guest-box')
					.first()
					.waitFor({ timeout: 30_000 })
				const image = await login.evaluate(() => {
					const box = document.querySelector(
						'#body-login .guest-box',
					) as HTMLElement
					return getComputedStyle(box, '::after').backgroundImage
				})
				expect(image).toContain('amsterdam.svg')
			} finally {
				await anonymous.close()
			}
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-with-lintribbon
	test('a set with lint tokens paints a ribbon of those dimensions behind the logo', async ({
		page,
	}) => {
		const declared = nldesignDeclarations(tokenCss('rijkshuisstijl'))
		const width = declared['--nldesign-size-lint']
		const height = declared['--nldesign-size-lint-height']
		const colour = declared['--nldesign-color-logo-background']
		expect(
			width && height && colour,
			'rijkshuisstijl declares the lint tokens',
		).toBeTruthy()
		await openSettings(page)
		await withActiveSet(page, 'rijkshuisstijl', async () => {
			await openSettings(page)
			const ribbon = await page.evaluate(() => {
				const el = document.querySelector('#nextcloud') as HTMLElement | null
				if (el === null) return null
				const s = getComputedStyle(el, '::before')
				return {
					width: s.width,
					height: s.height,
					background: s.backgroundColor,
				}
			})
			expect(ribbon).not.toBeNull()
			expect(ribbon?.width).toBe(width)
			expect(ribbon?.height).toBe(height)
			const probe = await page.evaluate((c) => {
				const el = document.createElement('div')
				el.style.backgroundColor = c
				document.body.appendChild(el)
				const v = getComputedStyle(el).backgroundColor
				el.remove()
				return v
			}, colour)
			expect(ribbon?.background).toBe(probe)
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Token Sets API Endpoints
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#list-all-available-token-sets
	test('GET /settings/tokensets lists entries with id, name, description, design system and theming', async ({
		page,
	}) => {
		await openSettings(page)
		const list = await adminList(page)
		expect(list.length).toBeGreaterThan(0)
		for (const entry of list) {
			for (const key of ['id', 'name', 'description', 'design_system']) {
				expect(typeof entry[key], `${entry.id} ${key}`).toBe('string')
			}
			const shipped = MANIFEST.find((e) => e.id === entry.id)
			if (shipped?.theming !== undefined) {
				expect(entry.theming, `${entry.id} theming`).toBeTruthy()
			}
		}
		for (const id of SELECTABLE_SHIPPED_SETS) {
			expect(list.map((s) => s.id)).toContain(id)
		}
	})

	// @e2e openspec/specs/token-sets/spec.md#get-current-token-set
	test('the capability answers the id the dropdown shows', async ({ page }) => {
		await openSettings(page)
		const { tokenSet } = await activeTokenSet(page)
		expect(typeof tokenSet).toBe('string')
		expect(tokenSet).toBe(
			await page.locator('#nldesign-token-set-select').inputValue(),
		)
	})

	// @e2e openspec/specs/token-sets/spec.md#set-active-token-set
	test('POST /settings/tokenset switches the active set', async ({ page }) => {
		await openSettings(page)
		await withActiveSet(page, 'nextcloud', async () => {
			const res = await call(
				page,
				'POST',
				'/apps/thematiq/settings/tokenset',
				{ tokenSet: 'denhaag' },
			)
			expect(res.json).toEqual({ status: 'ok', tokenSet: 'denhaag' })
			expect(await getTokenSet(page, await requestToken(page))).toBe('denhaag')
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#set-invalid-token-set-returns-error
	test('POST /settings/tokenset with an unknown set answers 400', async ({
		page,
	}) => {
		await openSettings(page)
		const res = await call(page, 'POST', '/apps/thematiq/settings/tokenset', {
			tokenSet: 'nonexistent',
		})
		expect(res.status).toBe(400)
		expect(res.json).toEqual({ error: 'Invalid token set' })
	})

	// -----------------------------------------------------------------------
	// Requirement: Token Set Count and Coverage
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#all-required-token-sets-present
	test('the required sets are served and there are at least 40', async ({
		page,
	}) => {
		await openSettings(page)
		const ids = (await publicCatalogue(page)).map((s) => s.id)
		for (const id of [
			'rijkshuisstijl',
			'amsterdam',
			'utrecht',
			'rotterdam',
			'denhaag',
			'nextcloud',
			'lasuite',
		]) {
			expect(ids, id).toContain(id)
			expect((await servedFile(page, `css/tokens/${id}.css`)).status, id).toBe(
				200,
			)
		}
		expect(
			ids.filter((id: string) => !id.startsWith('custom-')).length,
		).toBeGreaterThanOrEqual(40)
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-count-matches-manifest
	test('every manifest entry has a file; a file without one gets an auto name', async ({
		page,
	}) => {
		await openSettings(page)
		const catalogue = await publicCatalogue(page)
		const ids = catalogue.map((s) => s.id)
		for (const entry of MANIFEST) expect(ids, entry.id).toContain(entry.id)
		const orphan = catalogue.find((s) => s.id === SET_WITHOUT_MANIFEST)
		expect(orphan.name).toBe(
			SET_WITHOUT_MANIFEST.split('-')
				.map((w) => w.charAt(0).toUpperCase() + w.slice(1))
				.join(' '),
		)
	})

	// @e2e openspec/specs/token-sets/spec.md#token-sets-include-major-dutch-municipalities
	test('the major municipalities and government organisations are served', async ({
		page,
	}) => {
		await openSettings(page)
		const ids = (await publicCatalogue(page)).map((s) => s.id)
		for (const id of [
			'amsterdam',
			'rotterdam',
			'denhaag',
			'utrecht',
			'groningen',
			'nijmegen',
			'leiden',
			'tilburg',
			'zwolle',
			'haarlem',
			'rijkshuisstijl',
			'duo',
			'vng',
		]) {
			expect(ids, id).toContain(id)
		}
	})

	// @e2e openspec/specs/token-sets/spec.md#la-suite-set-manifest-entry
	test('lasuite is a lasuite set with the violet primary, white background and no logo', async ({
		page,
	}) => {
		await openSettings(page)
		const entry = (await publicCatalogue(page)).find((s) => s.id === 'lasuite')
		expect(entry.design_system).toBe('lasuite')
		expect(entry.theming.primary_color).toBe('#4844AD')
		expect(entry.theming.background_color).toBe('#FFFFFF')
		expect('logo' in entry.theming).toBe(false)
		expect(manifestEntry('lasuite').theming?.logo).toBeUndefined()
	})

	// @e2e openspec/specs/token-sets/spec.md#la-suite-token-set-file-is-a-standard-layer-3-set
	test('lasuite.css declares only --nldesign-* on :root and loads after its bundle', async ({
		page,
	}) => {
		await openSettings(page)
		const served = await servedFile(page, 'css/tokens/lasuite.css')
		expect(served.status).toBe(200)
		const css = served.text.replace(/\/\*[\s\S]*?\*\//g, '')
		expect(css.trim()).toMatch(/^:root\s*\{[^{}]*\}$/)
		const names = [...css.matchAll(/(--[\w-]+)\s*:/g)].map((m) => m[1])
		expect(names.length).toBeGreaterThan(0)
		for (const n of names) expect(n.startsWith('--nldesign-'), n).toBe(true)

		const layers = fileLayers(await layerManifest(page, 'lasuite'))
		const bundle =
			DESIGN_SYSTEMS.find((d) => d.id === 'lasuite')?.stylesheets ?? []
		expect(layers.slice(0, bundle.length)).toEqual(bundle)
		expect(layers.indexOf('tokens/lasuite')).toBeGreaterThan(bundle.length - 1)

		// The set is complete (thematiq#1006): it declares every required token
		// itself, so none of them depends on the bundle.
		const declared = Object.keys(nldesignDeclarations(css))
		for (const t of REQUIRED_TOKENS) expect(declared, t).toContain(t)

		// A token the set leaves out and the bridge declares on :root still
		// resolves: the bridge's focus and component tokens.
		const bridge = readRepoFile('css/systems/lasuite/bridge.css').replace(
			/\/\*[\s\S]*?\*\//g,
			'',
		)
		const onRoot = [...bridge.matchAll(/(?:^|\})\s*:root\s*\{([^{}]*)\}/g)]
			.map((m) => m[1])
			.join('\n')
		const leftOut = Object.keys(nldesignDeclarations(onRoot)).filter(
			(t) => !declared.includes(t),
		)
		expect(leftOut.length).toBeGreaterThan(0)
		await withActiveSet(page, 'lasuite', async () => {
			await openSettings(page)
			for (const t of leftOut) expect(await cssVar(page, t), t).not.toBe('')
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#cunningham-blue-base-set-manifest-entry-optional-sibling
	test('cunningham is a cunningham set with the blue primary, no logo, and its own bundle', async ({
		page,
	}) => {
		await openSettings(page)
		const entry = (await publicCatalogue(page)).find(
			(s) => s.id === 'cunningham',
		)
		expect(entry.design_system).toBe('cunningham')
		expect(entry.theming.primary_color).toBe('#1A509F')
		// The set's own --nldesign-color-background-dark, not pure white,
		// so core theming matches what the stylesheet paints (585ceb76).
		expect(entry.theming.background_color).toBe('#E1E2E5')
		expect('logo' in entry.theming).toBe(false)

		const served = await servedFile(page, 'css/tokens/cunningham.css')
		expect(served.status).toBe(200)
		const names = [
			...served.text
				.replace(/\/\*[\s\S]*?\*\//g, '')
				.matchAll(/(--[\w-]+)\s*:/g),
		].map((m) => m[1])
		// The semantic layer, plus the Den Haag component tokens #899 gave
		// every set (openspec/changes/denhaag-component-tokens: a set's own
		// `--denhaag-*` or `--nl-data-badge-*` value wins over the bridge).
		for (const n of names) {
			expect(/^--(nldesign|denhaag|nl-data-badge)-/.test(n), n).toBe(true)
		}
		expect(names.some((n) => n.startsWith('--nldesign-'))).toBe(true)

		const cunningham = fileLayers(await layerManifest(page, 'cunningham'))
		const bundle =
			DESIGN_SYSTEMS.find((d) => d.id === 'cunningham')?.stylesheets ?? []
		expect(cunningham.slice(0, bundle.length)).toEqual(bundle)
		// lasuite's stack does not pick up anything of cunningham's.
		const lasuite = fileLayers(await layerManifest(page, 'lasuite'))
		expect(lasuite.some((l) => l.includes('cunningham'))).toBe(false)
	})

	// -----------------------------------------------------------------------
	// Requirement: Design System Association
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#token-set-with-nldesign-design-system
	test('an nldesign set loads the nldesign stack in order, then its tokens', async ({
		page,
	}) => {
		const bundle =
			DESIGN_SYSTEMS.find((d) => d.id === 'nldesign')?.stylesheets ?? []
		expect(bundle.length).toBeGreaterThan(0)
		await openSettings(page)
		const layers = fileLayers(await layerManifest(page, 'amsterdam'))
		expect(layers.slice(0, bundle.length)).toEqual(bundle)
		expect(layers[bundle.length]).toBe('tokens/amsterdam')

		await withActiveSet(page, 'amsterdam', async () => {
			await openSettings(page)
			const sheets = await pageStylesheets(page)
			const positions = [...bundle, 'tokens/amsterdam'].map((s) =>
				sheets.indexOf(s),
			)
			expect(positions.every((p) => p >= 0)).toBe(true)
			expect([...positions].sort((a, b) => a - b)).toEqual(positions)
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-with-none-design-system-stock-nextcloud
	test('the stock set loads no design-system sheet and no token file', async ({
		page,
	}) => {
		expect(manifestEntry('nextcloud').design_system).toBe('none')
		await openSettings(page)
		const manifest = await layerManifest(page, 'nextcloud')
		expect(manifest.designSystem).toBe('none')
		const layers = fileLayers(manifest)
		expect(layers.some((l) => l.startsWith('systems/'))).toBe(false)
		expect(layers).not.toContain('tokens/nextcloud')

		await withActiveSet(page, 'nextcloud', async () => {
			await openSettings(page)
			const sheets = await pageStylesheets(page)
			expect(sheets.some((s) => s.startsWith('systems/'))).toBe(false)
			expect(sheets).not.toContain('tokens/nextcloud')
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#default-design-system-for-token-sets-without-manifest-entry
	test('a set without a manifest entry is an nldesign set and gets the nldesign stack', async ({
		page,
	}) => {
		const bundle =
			DESIGN_SYSTEMS.find((d) => d.id === 'nldesign')?.stylesheets ?? []
		await openSettings(page)
		await withOffered(page, [SET_WITHOUT_MANIFEST], async () => {
			const entry = (await adminList(page)).find(
				(s) => s.id === SET_WITHOUT_MANIFEST,
			)
			expect(entry.design_system).toBe('nldesign')
		})
		const manifest = await layerManifest(page, SET_WITHOUT_MANIFEST)
		expect(manifest.designSystem).toBe('nldesign')
		expect(fileLayers(manifest).slice(0, bundle.length)).toEqual(bundle)
	})

	// -----------------------------------------------------------------------
	// Requirement: Token Set Preview
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#valid-token-set-preview
	test('the preview of a known set answers its id and resolved values', async ({
		page,
	}) => {
		await openSettings(page)
		const res = await call(
			page,
			'GET',
			'/apps/thematiq/settings/tokenset-preview/amsterdam',
		)
		expect(res.status).toBe(200)
		expect(res.json.tokenSetId).toBe('amsterdam')
		const names = Object.keys(res.json.resolved)
		expect(names.length).toBeGreaterThan(0)
		for (const n of names) expect(n.startsWith('--'), n).toBe(true)
	})

	// @e2e openspec/specs/token-sets/spec.md#invalid-token-set-preview-returns-404
	test('the preview of an unknown set answers 404', async ({ page }) => {
		await openSettings(page)
		const res = await call(
			page,
			'GET',
			'/apps/thematiq/settings/tokenset-preview/nonexistent',
		)
		expect(res.status).toBe(404)
		expect(res.json).toEqual({ error: 'Token set not found' })
	})

	// @e2e openspec/specs/token-sets/spec.md#preview-used-by-apply-dialog
	test('choosing a set fetches its preview and lists what differs in the apply dialog', async ({
		page,
	}) => {
		await openSettings(page)
		const token = await requestToken(page)
		const previous = await getTokenSet(page, token)
		const target = previous === 'amsterdam' ? 'utrecht' : 'amsterdam'
		await withOffered(page, [target], async () => {
			try {
				await openSettings(page)
				// What admin.js will compare: the preview, with the set's saved
				// overrides on top and the primary family left to theming sync.
				const expected = await page.evaluate(async (id) => {
					const OC = (
						window as unknown as {
							OC: {
								generateUrl: (u: string) => string
								requestToken: string
							}
						}
					).OC
					const h = { headers: { requesttoken: OC.requestToken } }
					const preview = await (
						await fetch(
							OC.generateUrl(
								'/apps/thematiq/settings/tokenset-preview/' + id,
							),
							h,
						)
					).json()
					const saved =
						(
							await (
								await fetch(
									OC.generateUrl(
										'/apps/thematiq/settings/overrides',
									)
										+ '?tokenSet='
										+ id,
									h,
								)
							).json()
						).overrides || {}
					const root = getComputedStyle(document.documentElement)
					return Object.keys(preview.resolved)
						.filter((n) => n.indexOf('--color-primary') !== 0)
						.filter((n) => {
							const next = String(
								saved[n] ?? preview.resolved[n],
							).trim()
							return (
								next !== ''
								&& root.getPropertyValue(n).trim() !== next
							)
						})
						.sort()
				}, target)
				expect(
					expected.length,
					'the target set differs from this page',
				).toBeGreaterThan(0)

				const previewCall = page.waitForRequest((r) =>
					r.url().includes('/settings/tokenset-preview/' + target),
				)
				await page.locator('#nldesign-token-set-select').selectOption(target)
				await previewCall
				const dialog = page.locator('#nldesign-apply-dialog-overlay')
				await expect(dialog).toBeVisible({ timeout: 15_000 })
				const shown = await dialog
					.locator('.nldesign-apply-check')
					.evaluateAll((els) =>
						els
							.map((e) => (e as HTMLElement).dataset.token ?? '')
							.sort(),
					)
				expect(shown).toEqual(expected)
				await dialog.locator('.nldesign-dialog-cancel').click()
				await expect(dialog).toHaveCount(0)
				expect(await getTokenSet(page, token)).toBe(previous)
			} finally {
				await setTokenSet(page, token, previous)
			}
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Route Configuration
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/token-sets/spec.md#list-token-sets-route
	test('GET /settings/tokensets reaches getAvailableTokenSets', async ({
		page,
	}) => {
		await openSettings(page)
		const res = await call(page, 'GET', '/apps/thematiq/settings/tokensets')
		expect(res.status).toBe(200)
		expect(Array.isArray(res.json.tokenSets)).toBe(true)
	})

	// @e2e openspec/specs/token-sets/spec.md#no-separate-read-route-for-the-active-token-set
	test('GET /settings/tokenset is not a route: only the POST answers there', async ({
		page,
	}) => {
		await openSettings(page)
		const res = await call(page, 'GET', '/apps/thematiq/settings/tokenset')
		// The path exists for POST only, so the router refuses the verb.
		expect(res.status).toBe(405)
		expect(res.json?.tokenSet).toBeUndefined()
	})

	// @e2e openspec/specs/token-sets/spec.md#set-active-token-set-route
	test('POST /settings/tokenset reaches setTokenSet', async ({ page }) => {
		await openSettings(page)
		const res = await call(page, 'POST', '/apps/thematiq/settings/tokenset', {
			tokenSet: 'no-such-set',
		})
		expect(res.status).toBe(400)
		expect(res.json).toEqual({ error: 'Invalid token set' })
	})

	// @e2e openspec/specs/token-sets/spec.md#token-set-preview-route
	test('GET /settings/tokenset-preview/{id} reaches getTokenSetPreview', async ({
		page,
	}) => {
		await openSettings(page)
		const res = await call(
			page,
			'GET',
			'/apps/thematiq/settings/tokenset-preview/utrecht',
		)
		expect(res.status).toBe(200)
		expect(res.json.tokenSetId).toBe('utrecht')
	})

	// -----------------------------------------------------------------------
	// Requirement: Only Fully Functional Brands Are Selectable
	// -----------------------------------------------------------------------

	/** The sets the instance's group mapping already points at. */
	async function mappedSets(page: Page): Promise<string[]> {
		const res = await call(page, 'GET', '/apps/thematiq/settings/group-theming')
		return (res.json?.mapping ?? []).map((m: any) => m.tokenSet)
	}

	// @e2e openspec/specs/token-sets/spec.md#every-named-shipped-set-the-audit-passes-is-offered
	// @e2e openspec/specs/token-sets/spec.md#a-set-that-declares-no-component-tokens-is-still-offered
	test('on a stock instance the dropdown offers every named set the audit passes', async ({
		page,
	}) => {
		await openSettings(page)
		await withActiveSet(page, 'nextcloud', async () => {
			await openSettings(page)
			const mapped = await mappedSets(page)
			const expected = [
				...new Set([...SELECTABLE_SHIPPED_SETS, 'nextcloud', ...mapped]),
			]
				.filter((id) => TOKEN_FILES.includes(id))
				.sort()
			const shippedInList = (await adminList(page))
				.map((s) => s.id)
				.filter((id: string) => !id.startsWith('custom-'))
				.sort()
			expect(shippedInList).toEqual(expected)
			const options = (
				await page
					.locator('#nldesign-token-set-select option')
					.evaluateAll((els) =>
						els.map((e) => (e as HTMLOptionElement).value),
					)
			)
				.filter((id) => !id.startsWith('custom-'))
				.sort()
			expect(options).toEqual(expected)

			// A named set that declares none of the --utrecht-* names the bridge
			// reads is offered all the same.
			const noComponentTokens = expected.find(
				(id) =>
					id !== 'nextcloud'
					&& designSystemOf(id) === 'nldesign'
					&& !/--utrecht-/.test(tokenCss(id)),
			)
			expect(
				noComponentTokens,
				'a named nldesign set without --utrecht-* tokens',
			).toBeTruthy()
			expect(options).toContain(noComponentTokens)

			// The catalogue and the preview still answer for a set not offered.
			const hidden = TOKEN_FILES.find((id) => !expected.includes(id)) as string
			expect((await publicCatalogue(page)).map((s) => s.id)).toContain(hidden)
			expect(
				(
					await call(
						page,
						'GET',
						`/apps/thematiq/settings/tokenset-preview/${hidden}`,
					)
				).status,
			).toBe(200)
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#the-active-set-is-always-selectable
	test('the active set is offered and selected even when the picker withholds it', async ({
		page,
	}) => {
		await openSettings(page)
		const mapped = await mappedSets(page)
		// A shipped file the picker withholds, so the survival rule is what
		// puts it back rather than the rule that offers every named set.
		const target = UNNAMED_TOKEN_FILES.find(
			(id) => !mapped.includes(id),
		) as string
		expect(
			target,
			'no withheld shipped file to exercise the rule with',
		).toBeTruthy()
		await withActiveSet(page, target, async () => {
			await openSettings(page)
			expect((await adminList(page)).map((s) => s.id)).toContain(target)
			await expect(page.locator('#nldesign-token-set-select')).toHaveValue(
				target,
			)
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#a-set-a-group-mapping-points-at-is-always-selectable
	test('a set a group mapping points at is offered, and only while it does', async ({
		page,
	}) => {
		await openSettings(page)
		const token = await requestToken(page)
		const active = await getTokenSet(page, token)
		const mapped = await mappedSets(page)
		const target = UNNAMED_TOKEN_FILES.find(
			(id) => !mapped.includes(id) && id !== active,
		) as string
		expect(
			target,
			'no withheld shipped file to exercise the rule with',
		).toBeTruthy()
		expect((await adminList(page)).map((s) => s.id)).not.toContain(target)
		await withOffered(page, [target], async () => {
			expect((await adminList(page)).map((s) => s.id)).toContain(target)
		})
		expect((await adminList(page)).map((s) => s.id)).not.toContain(target)
	})

	// @e2e openspec/specs/token-sets/spec.md#an-imported-set-is-always-selectable
	test('an uploaded set is offered without any mapping', async ({ page }) => {
		await openSettings(page)
		await withCustomSet(
			page,
			'E2E import ' + Date.now(),
			':root { --nldesign-color-primary: #007bc7; }',
			async (id) => {
				expect(await mappedSets(page)).not.toContain(id)
				expect((await adminList(page)).map((s) => s.id)).toContain(id)
				await openSettings(page)
				await expect(
					page.locator(`#nldesign-token-set-select option[value="${id}"]`),
				).toHaveCount(1)
			},
		)
	})

	// -----------------------------------------------------------------------
	// Requirement: Shipped Token Set Vocabulary Completeness
	//
	// The audit's verdict reaches the admin page as a `kind: 'incomplete'`
	// entry on a set's `warnings`. Each test offers the sets it reads, picked
	// from the checkout by the property the scenario describes.
	// -----------------------------------------------------------------------

	/** The admin-list entries for `ids`, offered for the duration of `body`. */
	async function withEntries(
		page: Page,
		ids: string[],
		body: (byId: Record<string, any>) => Promise<void>,
	) {
		await withOffered(page, ids, async () => {
			const list = await adminList(page)
			const byId: Record<string, any> = {}
			for (const id of ids) byId[id] = list.find((s) => s.id === id)
			await body(byId)
		})
	}

	// @e2e openspec/specs/token-sets/spec.md#a-set-that-declares-the-full-required-vocabulary-is-complete
	test('a set that declares the whole vocabulary carries no vocabulary finding', async ({
		page,
	}) => {
		const declared = Object.keys(nldesignDeclarations(tokenCss(COMPLETE_SET)))
		for (const t of REQUIRED_TOKENS)
			expect(declared, `${COMPLETE_SET} declares ${t}`).toContain(t)
		await openSettings(page)
		await withEntries(page, [COMPLETE_SET], async (byId) => {
			expect(incompleteWarning(byId[COMPLETE_SET])).toBeUndefined()
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#a-complete-catalogue-stays-quiet
	test('with every shipped set complete, no set carries a vocabulary finding and no badge shows', async ({
		page,
	}) => {
		// The GIVEN holds since #1006 / #1008: the allow-list is empty.
		expect(ALLOWLIST).toEqual([])
		await openSettings(page)
		const list = await adminList(page)
		expect(list.length).toBeGreaterThan(20)
		for (const entry of list) {
			expect(
				incompleteWarning(entry),
				`${entry.id} carries no vocabulary entry`,
			).toBeUndefined()
		}
		await expect(page.getByText('Incomplete set', { exact: true })).toBeHidden()
	})

	// @e2e openspec/specs/token-sets/spec.md#a-set-whose-design-system-reads-no-nldesign-name-is-not-auditable
	test('sets of systems that read no --nldesign-* name are not audited; bridged systems are', async ({
		page,
	}) => {
		// Summer Breeze reads a vocabulary of its own, never an --nldesign-* name.
		const summer =
			DESIGN_SYSTEMS.find((d) => d.id === 'summer-breeze')?.stylesheets ?? []
		for (const s of summer)
			expect(readRepoFile(`css/${s}.css`)).not.toMatch(/--nldesign-/)

		// The bridged systems DO read the vocabulary, so their sets are audited
		// like any nldesign set.
		for (const system of ['high-contrast', 'lasuite']) {
			const sheets =
				DESIGN_SYSTEMS.find((d) => d.id === system)?.stylesheets ?? []
			expect(sheets.length, `${system} has stylesheets`).toBeGreaterThan(0)
			expect(
				sheets.some((s) =>
					/var\(\s*--nldesign-/.test(readRepoFile(`css/${s}.css`)),
				),
				`${system} reads the --nldesign-* vocabulary`,
			).toBe(true)
		}

		// The contract since every shipped set was completed (#1006, #1008):
		// the allow-list of known-incomplete sets is empty, so no set is exempt
		// from the audit. This test used to pick its bridged subjects FROM the
		// allow-list and expect an incomplete warning on them; with the list
		// empty it found none and failed on `bridged.length > 0`.
		expect(ALLOWLIST).toEqual([])

		// Live: audited and complete, so none of them carries the warning. That
		// an incomplete set DOES carry it is held by the PHPUnit gate
		// (TokenSetVocabularyTest::testAuditDistinguishesCompleteFromIncompleteSets).
		const ids = [
			'summer-breeze',
			'nextcloud',
			'hoog-contrast',
			'lasuite',
			'cunningham',
		]
		await openSettings(page)
		await withEntries(page, ids, async (byId) => {
			for (const id of ids) {
				expect(byId[id], `${id} is listed`).toBeTruthy()
				expect(
					incompleteWarning(byId[id]),
					`${id} carries no incomplete warning`,
				).toBeUndefined()
			}
		})
	})

	// @e2e openspec/specs/token-sets/spec.md#a-complete-summer-breeze-set-raises-no-incomplete-warning-in-the-admin-dropdown
	test('summer-breeze is audited against its own vocabulary and carries no incomplete warning', async ({
		page,
	}) => {
		// The audit reads the same files the server reads: every --summer-*
		// name the system's stylesheets read, and do not declare themselves,
		// is declared by its token file. A name a stylesheet declares (the
		// focus ring's --summer-focus-ring-color) is a local, not a token, and
		// the server's audit leaves it out too (TokenSetVocabularyAuditService:
		// "read through var() and no such layer declares").
		const reads = new Set<string>()
		const local = new Set<string>()
		for (const s of DESIGN_SYSTEMS.find((d) => d.id === 'summer-breeze')
			?.stylesheets ?? []) {
			const css = readRepoFile(`css/${s}.css`).replace(/\/\*[\s\S]*?\*\//g, '')
			for (const m of css.matchAll(/var\(\s*(--summer-[A-Za-z0-9_-]+)/g))
				reads.add(m[1])
			for (const m of css.matchAll(/(--summer-[A-Za-z0-9_-]+)\s*:/g))
				local.add(m[1])
		}
		for (const name of local) reads.delete(name)
		expect(reads.size).toBeGreaterThan(10)
		const declared = tokenCss('summer-breeze')
		for (const name of reads)
			expect(declared, `summer-breeze declares ${name}`).toMatch(
				new RegExp(`${name}\\s*:`),
			)
		await openSettings(page)
		await withEntries(page, ['summer-breeze'], async (byId) => {
			expect(byId['summer-breeze'], 'summer-breeze is listed').toBeTruthy()
			expect(incompleteWarning(byId['summer-breeze'])).toBeUndefined()
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Incomplete Sets Are Surfaced In The Admin Dropdown
	// -----------------------------------------------------------------------

	/**
	 * Select `setId` in the dropdown with the token preview stubbed to one
	 * certain difference, so the apply dialog always opens and nothing is
	 * saved until it is confirmed; cancel puts the selection back.
	 */
	async function selectIntoApplyDialog(
		page: Page,
		setId: string,
		body: () => Promise<void>,
	) {
		// withOffered calls the API from this page, so it must be an
		// authenticated page with OC loaded first, not about:blank.
		await openSettings(page)
		await withOffered(page, [setId], async () => {
			const token = await requestToken(page)
			const previous = await getTokenSet(page, token)
			try {
				// selectOption only fires `change` when the value changes.
				if (previous === setId) await setTokenSet(page, token, 'nextcloud')
				await openSettings(page)
				await page.route(
					/\/apps\/thematiq\/settings\/tokenset-preview\//,
					(route: Route) =>
						route.fulfill({
							json: {
								tokenSetId: setId,
								resolved: { '--thematiq-e2e-probe': '#010203' },
							},
						}),
				)
				await page.locator('#nldesign-token-set-select').selectOption(setId)
				await body()
			} finally {
				await page.unrouteAll({ behavior: 'ignoreErrors' })
				await setTokenSet(page, token, previous)
			}
		})
	}

	// @e2e openspec/specs/token-sets/spec.md#the-custom-set-list-badge-has-three-states-ranked
	test('the custom-set list badge ranks incomplete over contrast over OK', async ({
		page,
	}) => {
		// The server never sends an incomplete finding for an upload today (the
		// spec says so), so the list response is stubbed to hold all three
		// states and the badge logic is what is under test.
		await page.route(
			/\/apps\/thematiq\/settings\/tokensets\/custom(\?.*)?$/,
			(route: Route) =>
				route.fulfill({
					json: {
						sets: [
							{
								id: 'custom-e2e-a',
								name: 'E2E both',
								warnings: [
									{
										pair: 'a vs b',
										ratio: 2,
										threshold: 4.5,
										level: 'AA',
									},
									{
										kind: 'incomplete',
										missing: ['--nldesign-color-text'],
										foreign: [],
										primaryMismatch: false,
										declaredPrimary: null,
										cssPrimary: null,
									},
								],
							},
							{
								id: 'custom-e2e-b',
								name: 'E2E contrast',
								warnings: [
									{
										pair: 'a vs b',
										ratio: 2,
										threshold: 4.5,
										level: 'AA',
									},
								],
							},
							{ id: 'custom-e2e-c', name: 'E2E clean', warnings: [] },
						],
					},
				}),
		)
		try {
			await openSettings(page)
			const row = (name: string) =>
				page
					.locator('#nldesign-custom-set-list .nldesign-custom-set-row', {
						hasText: name,
					})
					.locator('.nldesign-badge')
			await expect(row('E2E both')).toHaveText('Incomplete set')
			expect(await row('E2E both').getAttribute('title')).toContain(
				'--nldesign-color-text',
			)
			await expect(row('E2E contrast')).toHaveText('Contrast warning')
			await expect(row('E2E clean')).toHaveText('WCAG AA OK')
		} finally {
			await page.unrouteAll({ behavior: 'ignoreErrors' })
		}
	})

	// -----------------------------------------------------------------------
	// Non-admin access
	// -----------------------------------------------------------------------

	test.describe('as a non-admin', () => {
		let nonAdmin: { page: Page; close: () => Promise<void> }

		test.beforeAll(async ({ browser }) => {
			// Setup budget: provisioning plus a full form login on a cold instance.
			test.setTimeout(180_000)
			const ctx = await adminContext(browser)
			const adminPage = await ctx.newPage()
			await adminPage.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
			await ensureNonAdminUser(adminPage)
			await ctx.close()
			nonAdmin = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		})

		test.afterAll(async () => {
			await nonAdmin?.close()
		})

		// @e2e openspec/specs/token-sets/spec.md#non-admin-access-denied
		test('every token set endpoint refuses a non-admin and admits the admin', async ({
			page,
		}) => {
			// GET /settings/tokenset is gone (#664); the POST is the only
			// route on that path.
			const calls: Array<[string, string, unknown]> = [
				['GET', '/apps/thematiq/settings/tokensets', undefined],
				[
					'POST',
					'/apps/thematiq/settings/tokenset',
					{ tokenSet: 'nextcloud' },
				],
			]
			await openSettings(page)
			const token = await requestToken(page)
			const before = await getTokenSet(page, token)
			for (const [method, url, body] of calls) {
				expect(
					(await call(nonAdmin.page, method, url, body)).status,
					`${method} ${url}`,
				).toBe(403)
			}
			expect(await getTokenSet(page, token)).toBe(before)
			for (const [method, url] of calls.slice(0, 1)) {
				expect(
					(await call(page, method, url)).status,
					`admin ${method} ${url}`,
				).toBe(200)
			}
		})
	})
})
