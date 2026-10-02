/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/css-architecture/spec.md
 *
 * The layered cascade, proven where it exists: in a browser that loaded it.
 *
 * Each test reads one of four things the page actually got, never the PHP
 * that decided it: the ordered `<link rel="stylesheet">` list, a stylesheet as
 * the server serves it, a computed custom property, or a computed style on a
 * real element. Expected values come from the shipped files the server reads
 * (design-systems.json, css/tokens/*.css, css/systems/**), so a test fails
 * when the page and the source disagree, whichever of the two moved.
 *
 * STATE. Most tests run against `rijkshuisstijl` (an nldesign set with a
 * ribbon) with an empty overrides file and both display toggles off. That
 * state is applied once in beforeAll and the previous state is put back in
 * afterAll. Tests that need another set switch to it with withThemeState(),
 * which restores in a `finally`.
 *
 * The scenarios this file does not prove carry their own `@e2e exclude` in the
 * spec, with the reason and the artifact that covers them instead.
 */
import { test, expect, type Page } from '@playwright/test'

import {
	adminContext,
	api,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'
import {
	anonymousPage,
	applyThemeState,
	bodyVar,
	contrastRatio,
	designSystem,
	designSystems,
	layerHref,
	openLoginPage,
	parseRgb,
	PROBE_URL,
	repoFile,
	resolveColor,
	restoreThemeState,
	rootDeclarations,
	rootVar,
	servedCss,
	stripComments,
	thematiqLayers,
	tokenSetManifest,
	withThemeState,
	type ThemeSnapshot,
} from './_theme-state'

// The dark variant layer is scoped to a dark preference; pin the light one so
// every computed value below is the light cascade.
test.use({ colorScheme: 'light' })

const NLDESIGN_SET = 'rijkshuisstijl'

/** The six nldesign layers the spec names, in the order it names them. */
const NLDESIGN_FILES = [
	'fonts',
	'defaults',
	'utrecht-bridge',
	'theme',
	'overrides',
	'element-overrides',
]

/** Open the probe page and return the Thematiq layers it loaded. */
async function probeLayers(page: Page): Promise<string[]> {
	await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
	return thematiqLayers(page)
}

/** The highest index of any layer matching `pred`, or -1. */
function lastIndex(layers: string[], pred: (l: string) => boolean): number {
	let last = -1
	layers.forEach((l, i) => {
		if (pred(l)) last = i
	})
	return last
}

/** The stylesheet manifest for a token set (GET /settings/tokenset-stylesheets/{id}). */
async function stylesheetManifest(
	page: Page,
	tokenSet: string,
): Promise<{
	designSystem: string
	layers: Array<{ layer: string; kind: string; href?: string }>
}> {
	const res = await api(
		page,
		'GET',
		`/index.php/apps/thematiq/settings/tokenset-stylesheets/${tokenSet}`,
	)
	expect(res.status, `stylesheet manifest for ${tokenSet}`).toBe(200)
	return res.json
}

/** The `css/<file>` names of a manifest's design-system layers, in order. */
function manifestFiles(manifest: {
	layers: Array<{ layer: string; href?: string }>
}): string[] {
	return manifest.layers
		.filter((l) => l.layer === 'design-system')
		.map((l) => {
			const m = /\/thematiq\/css\/(.+)\.css$/.exec(
				new URL(l.href as string, 'http://x').pathname,
			)
			return m === null ? '' : m[1]
		})
}

/** Pseudo-element computed style of one element. */
async function pseudoStyle(
	page: Page,
	selector: string,
	pseudo: string,
): Promise<{ width: string; height: string; backgroundColor: string }> {
	return page
		.locator(selector)
		.first()
		.evaluate((el, p) => {
			const s = getComputedStyle(el, p)
			return {
				width: s.width,
				height: s.height,
				backgroundColor: s.backgroundColor,
			}
		}, pseudo)
}

test.describe('css-architecture: the nldesign cascade on an nldesign set', () => {
	test.describe.configure({ mode: 'serial', timeout: 60_000 })

	let snapshot: ThemeSnapshot | null = null

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		try {
			snapshot = await applyThemeState(page, {
				tokenSet: NLDESIGN_SET,
				overrides: {},
				hideSlogan: false,
				showMenuLabels: false,
			})
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		if (snapshot === null) return
		const ctx = await adminContext(browser)
		const page = await ctx.newPage()
		try {
			await restoreThemeState(page, snapshot)
		} finally {
			await ctx.close()
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#standard-css-load-order-for-nldesign-design-system
	'the nldesign bundle loads in the order design-systems.json declares it', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		const declared = designSystem('nldesign').stylesheets

		// Every design-system layer on the page, and nothing else, in the
		// declared order: the page follows the manifest, not a hard-coded list.
		expect(layers.filter((l) => l.startsWith('systems/'))).toEqual(declared)
		const first = layers.indexOf(declared[0])
		expect(first).toBeGreaterThanOrEqual(0)
		expect(layers.slice(first, first + declared.length)).toEqual(declared)
		// The resolved set's token file is its own layer straight after it.
		expect(layers[first + declared.length]).toBe(`tokens/${NLDESIGN_SET}`)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#token-set-css-loaded-after-design-system-stylesheets
	'the token set file loads after every design-system layer and before custom-overrides', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		const tokens = layers.indexOf(`tokens/${NLDESIGN_SET}`)
		const lastSystem = lastIndex(layers, (l) => l.startsWith('systems/'))
		const overrides = layers.indexOf('custom-overrides')

		expect(lastSystem).toBeGreaterThanOrEqual(0)
		expect(tokens).toBeGreaterThan(lastSystem)
		expect(overrides).toBeGreaterThan(tokens)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#custom-overrides-file-loaded
	'custom-overrides.css exists, is served and loads after layer 7', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		const elementOverrides = layers.indexOf('systems/nldesign/element-overrides')
		const overrides = layers.indexOf('custom-overrides')

		expect(elementOverrides).toBeGreaterThanOrEqual(0)
		expect(overrides).toBeGreaterThan(elementOverrides)
		// ensureExists() ran before the link was emitted: the file is there.
		const css = await servedCss(page, 'custom-overrides')
		expect(css).toContain(':root')
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#nl-design-system-files-in-correct-directory
	'the nldesign layers come from css/systems/nldesign/ and the tokens from css/tokens/', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		expect(layers.filter((l) => l.startsWith('systems/'))).toEqual(
			NLDESIGN_FILES.map((f) => `systems/nldesign/${f}`),
		)
		expect(layers).toContain(`tokens/${NLDESIGN_SET}`)
		for (const file of NLDESIGN_FILES) {
			expect(await servedCss(page, `systems/nldesign/${file}`)).not.toBe('')
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#fira-sans-font-faces-registered
	'the fonts layer registers Fira Sans 400/700 normal/italic with font-display swap', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const rules = await page.evaluate(() => {
			const sheet = [...document.styleSheets].find(
				(s) =>
					s.href !== null
					&& new URL(s.href).pathname.endsWith(
						'/thematiq/css/systems/nldesign/fonts.css',
					),
			)
			if (sheet === undefined) return null
			return [...sheet.cssRules]
				.filter((r) => r instanceof CSSFontFaceRule)
				.map((r) => {
					const s = (r as CSSFontFaceRule).style
					return {
						family: s
							.getPropertyValue('font-family')
							.replace(/['"]/g, ''),
						weight: s.getPropertyValue('font-weight'),
						style: s.getPropertyValue('font-style'),
						display: s.getPropertyValue('font-display'),
					}
				})
		})
		expect(rules, 'systems/nldesign/fonts.css must be loaded').not.toBeNull()
		expect(
			(rules ?? []).map((r) => `${r.family} ${r.weight} ${r.style}`).sort(),
		).toEqual([
			'Fira Sans 400 italic',
			'Fira Sans 400 normal',
			'Fira Sans 700 italic',
			'Fira Sans 700 normal',
		])
		expect(new Set((rules ?? []).map((r) => r.display))).toEqual(
			new Set(['swap']),
		)

		// And the browser registered them: each face is in document.fonts and
		// actually loads.
		for (const face of [
			'400 normal',
			'400 italic',
			'700 normal',
			'700 italic',
		]) {
			const [weight, style] = face.split(' ')
			const loaded = await page.evaluate(
				async ({ w, s }) => {
					const faces = await document.fonts.load(
						`${s} ${w} 16px "Fira Sans"`,
					)
					return faces.map((f) => `${f.weight} ${f.style} ${f.status}`)
				},
				{ w: weight, s: style },
			)
			expect(loaded, `Fira Sans ${face}`).toContain(
				`${weight} ${style} loaded`,
			)
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#font-file-formats-supported
	'each @font-face lists local() first, then woff2, then woff, all served from systems/nldesign/fonts/', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const faces = await page.evaluate(() => {
			const sheet = [...document.styleSheets].find(
				(s) =>
					s.href !== null
					&& new URL(s.href).pathname.endsWith(
						'/thematiq/css/systems/nldesign/fonts.css',
					),
			)
			if (sheet === undefined || sheet.href === null) return null
			const base = sheet.href
			return [...sheet.cssRules]
				.filter((r) => r instanceof CSSFontFaceRule)
				.map((r) => {
					const src = (r as CSSFontFaceRule).style.getPropertyValue('src')
					const urls = [
						...src.matchAll(
							/url\("?([^")]+)"?\)\s*format\("?([\w-]+)"?\)/g,
						),
					].map((m) => ({ url: new URL(m[1], base).href, format: m[2] }))
					return { src, urls }
				})
		})
		expect(faces, 'systems/nldesign/fonts.css must be loaded').not.toBeNull()
		expect((faces ?? []).length).toBe(4)
		for (const face of faces ?? []) {
			expect(face.src.trim().startsWith('local(')).toBe(true)
			expect(face.urls.map((u) => u.format)).toEqual(['woff2', 'woff'])
			for (const { url } of face.urls) {
				expect(new URL(url).pathname).toContain(
					'/thematiq/css/systems/nldesign/fonts/',
				)
				const res = await page.request.get(url)
				expect(res.status(), url).toBe(200)
			}
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#brand-color-tokens-defined
	'the served defaults layer declares the Rijkshuisstijl brand colours', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const decls = rootDeclarations(
			await servedCss(page, 'systems/nldesign/defaults'),
		)
		expect(decls.get('--nldesign-color-primary')).toBe('#154273')
		expect(decls.get('--nldesign-color-primary-text')).toBe('#ffffff')
		expect(decls.get('--nldesign-color-primary-hover')).toBe('#1d5499')
		expect(decls.get('--nldesign-color-primary-light')).toBe('#e8f0f8')
		expect(decls.get('--nldesign-color-primary-light-hover')).toBe('#d4e4f2')
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#status-color-tokens-defined
	'the served defaults layer declares the status colours with matching -rgb variants', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const decls = rootDeclarations(
			await servedCss(page, 'systems/nldesign/defaults'),
		)
		const expected: Record<string, string> = {
			error: '#d52b1e',
			warning: '#e17000',
			success: '#39870c',
			info: '#007bc7',
		}
		for (const [status, hex] of Object.entries(expected)) {
			expect(decls.get(`--nldesign-color-${status}`)).toBe(hex)
			const channels = [1, 3, 5]
				.map((i) => parseInt(hex.slice(i, i + 2), 16))
				.join(', ')
			expect(decls.get(`--nldesign-color-${status}-rgb`)).toBe(channels)
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#all-token-categories-defined
	'the served defaults layer declares a token for every category the spec lists', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const decls = rootDeclarations(
			await servedCss(page, 'systems/nldesign/defaults'),
		)
		const categories: Record<string, string[]> = {
			brand: ['--nldesign-color-primary'],
			status: ['--nldesign-color-error', '--nldesign-color-info'],
			background: [
				'--nldesign-color-background-hover',
				'--nldesign-color-background-dark',
				'--nldesign-color-background-darker',
				'--nldesign-color-header-background',
				'--nldesign-color-nav-background',
			],
			text: [
				'--nldesign-color-text',
				'--nldesign-color-text-muted',
				'--nldesign-color-text-light',
			],
			border: ['--nldesign-color-border'],
			focus: ['--nldesign-color-focus'],
			link: ['--nldesign-color-link'],
			button: ['--nldesign-color-button-primary-background'],
			typography: ['--nldesign-font-family'],
			radius: [
				'--nldesign-border-radius',
				'--nldesign-border-radius-small',
				'--nldesign-border-radius-large',
				'--nldesign-border-radius-rounded',
				'--nldesign-border-radius-pill',
			],
			animation: ['--nldesign-animation-quick', '--nldesign-animation-slow'],
			placeholder: [
				'--nldesign-color-placeholder-light',
				'--nldesign-color-placeholder-dark',
			],
			logo: [
				'--nldesign-color-logo-background',
				'--nldesign-size-lint',
				'--nldesign-logo-filter',
			],
		}
		for (const [category, names] of Object.entries(categories)) {
			for (const name of names) {
				expect(decls.has(name), `${category}: ${name}`).toBe(true)
			}
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#component-tokens-defined
	'the served defaults layer declares the component tokens for every listed component', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const decls = rootDeclarations(
			await servedCss(page, 'systems/nldesign/defaults'),
		)
		const names = [
			'button-background-color',
			'button-hover-background-color',
			'button-active-background-color',
			'button-disabled-background-color',
			'button-focus-border-color',
			'button-primary-action-background-color',
			'button-secondary-action-background-color',
			'textbox-background-color',
			'textbox-focus-border-color',
			'form-field-label-color',
			'form-select-border-color',
			'form-fieldset-border-color',
			'paragraph-font-size',
			'link-color',
			'table-border-color',
			'badge-background-color',
			'separator-border-color',
			'ordered-list-color',
			'unordered-list-color',
		]
		for (let h = 1; h <= 6; h++) {
			for (const prop of [
				'font-size',
				'font-weight',
				'line-height',
				'color',
			]) {
				names.push(`heading-${h}-${prop}`)
			}
		}
		for (const name of names) {
			expect(decls.has(`--nldesign-component-${name}`), name).toBe(true)
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#rijkshuisstijl-lint-tokens
	'rijkshuisstijl hangs a coloured ribbon behind the header logo', async ({
		page,
	}) => {
		const tokens = rootDeclarations(repoFile(`css/tokens/${NLDESIGN_SET}.css`))
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		await page.locator('#nextcloud').waitFor({ state: 'attached' })

		const ribbon = await pseudoStyle(page, '#nextcloud', '::before')
		expect(ribbon.width).toBe(tokens.get('--nldesign-size-lint'))
		expect(ribbon.height).toBe(tokens.get('--nldesign-size-lint-height'))
		expect(ribbon.backgroundColor).toBe(
			await resolveColor(
				page,
				tokens.get('--nldesign-color-logo-background') as string,
			),
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#token-set-only-overrides-root-scope
	'every shipped token set file is one :root block and nothing else', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const ids = tokenSetManifest().map((t) => t.id)
		expect(ids.length).toBeGreaterThan(0)
		for (const id of ids) {
			const css = stripComments(await servedCss(page, `tokens/${id}`))
			const selectors = css
				.split('{')
				.slice(0, -1)
				.map((part) => part.split('}').pop()?.trim())
			expect(selectors, `tokens/${id}.css`).toEqual([':root'])
			expect((css.match(/\}/g) ?? []).length, `tokens/${id}.css`).toBe(1)
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#utrecht-token-present-in-token-set
	'a --utrecht-* token on :root reaches the bridged --nldesign-component-* token', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		// primary-lock forces the component tokens back to the brand; it must
		// be off for the bridge to be what decides.
		expect(layers, 'precondition: primary-lock is off').not.toContain(
			'primary-lock',
		)
		await page.addStyleTag({
			content:
				':root { --utrecht-button-primary-action-background-color: #123456; }',
		})
		const bridged = await rootVar(
			page,
			'--nldesign-component-button-primary-action-background-color',
		)
		expect(await resolveColor(page, bridged)).toBe('rgb(18, 52, 86)')
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#utrecht-token-absent-fallback-to-defaults
	'without a --utrecht-* token the bridged component token falls back to the primary', async ({
		page,
	}) => {
		const tokens = repoFile(`css/tokens/${NLDESIGN_SET}.css`)
		expect(
			tokens,
			'precondition: the set declares no utrecht button token',
		).not.toMatch(/--utrecht-button/)
		const layers = await probeLayers(page)
		expect(layers).not.toContain('primary-lock')
		const bridged = await rootVar(
			page,
			'--nldesign-component-button-primary-action-background-color',
		)
		const primary = await rootVar(page, '--nldesign-color-primary')
		expect(await resolveColor(page, bridged)).toBe(
			await resolveColor(page, primary),
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#no-circular-references
	'no bridge mapping falls back to itself, and every var() fallback is a defaults token or a value', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const bridge = rootDeclarations(
			await servedCss(page, 'systems/nldesign/utrecht-bridge'),
		)
		const defaults = rootDeclarations(
			await servedCss(page, 'systems/nldesign/defaults'),
		)
		expect(bridge.size).toBeGreaterThan(0)
		for (const [name, value] of bridge) {
			const referenced = [...value.matchAll(/var\((--[\w-]+)/g)].map(
				(m) => m[1],
			)
			expect(referenced, `${name} must not reference itself`).not.toContain(
				name,
			)
			// The first var() is the --utrecht-* input; every later one is a
			// fallback and must be a token the defaults layer defines.
			for (const fallback of referenced.slice(1)) {
				expect(
					defaults.has(fallback),
					`${name} falls back to ${fallback}`,
				).toBe(true)
			}
		}
		// And the browser agrees: no bridged token computed to the
		// guaranteed-invalid (empty) value a cycle produces.
		for (const name of bridge.keys()) {
			expect(await rootVar(page, name), name).not.toBe('')
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#component-categories-bridged
	'the bridge maps --utrecht-* tokens for every component category the spec lists', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const values = [
			...rootDeclarations(
				await servedCss(page, 'systems/nldesign/utrecht-bridge'),
			).values(),
		].join(' ')
		const inputs = [
			'--utrecht-button-background-color',
			'--utrecht-button-hover-',
			'--utrecht-button-active-',
			'--utrecht-button-disabled-',
			'--utrecht-button-focus-',
			'--utrecht-button-primary-action-',
			'--utrecht-button-secondary-action-',
			'--utrecht-textbox-',
			'--utrecht-form-label-',
			'--utrecht-select-',
			'--utrecht-heading-1-',
			'--utrecht-heading-6-',
			'--utrecht-paragraph-',
			'--utrecht-link-',
			'--utrecht-table-',
			'--utrecht-badge-',
			'--utrecht-separator-',
			'--utrecht-ordered-list-',
			'--utrecht-unordered-list-',
			'--utrecht-breadcrumb-',
			'--utrecht-code-',
		]
		for (const input of inputs) {
			expect(values, input).toContain(`var(${input}`)
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#bridge-is-a-temporary-layer
	'switching the bridge stylesheet off leaves the other layers resolving', async ({
		page,
	}) => {
		await probeLayers(page)
		const read = async () => ({
			primary: await rootVar(page, '--nldesign-color-primary'),
			bodyPrimary: await bodyVar(page, '--color-primary'),
			header: await page
				.locator('#header')
				.evaluate((el) => getComputedStyle(el).backgroundColor),
			action: await resolveColor(
				page,
				await rootVar(
					page,
					'--nldesign-component-button-primary-action-background-color',
				),
			),
		})
		const before = await read()
		const disabled = await page.evaluate(() => {
			const link = [
				...document.querySelectorAll('link[rel="stylesheet"]'),
			].find((l) =>
				new URL((l as HTMLLinkElement).href).pathname.endsWith(
					'/thematiq/css/systems/nldesign/utrecht-bridge.css',
				),
			) as HTMLLinkElement | undefined
			if (link === undefined) return false
			link.disabled = true
			return true
		})
		expect(disabled, 'the bridge layer must be on the page').toBe(true)
		// With no --utrecht-* input the defaults layer already holds the same
		// fallbacks, so removing the bridge changes nothing.
		expect(await read()).toEqual(before)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#nextcloud-css-variables-overridden-on-body
	'body carries the Nextcloud primary, status and radius variables from the tokens', async ({
		page,
	}) => {
		await probeLayers(page)
		const pairs: Array<[string, string]> = [
			['--color-primary', '--nldesign-color-primary'],
			['--color-primary-text', '--nldesign-color-primary-text'],
			['--color-error', '--nldesign-color-error'],
			['--color-warning', '--nldesign-color-warning'],
			['--color-success', '--nldesign-color-success'],
			['--color-info', '--nldesign-color-info'],
		]
		for (const [nc, token] of pairs) {
			expect(await resolveColor(page, await bodyVar(page, nc)), nc).toBe(
				await resolveColor(page, await rootVar(page, token)),
			)
		}
		for (const [nc, token] of [
			['--border-radius', '--nldesign-border-radius'],
			['--border-radius-small', '--nldesign-border-radius-small'],
			['--border-radius-large', '--nldesign-border-radius-large'],
			['--border-radius-pill', '--nldesign-border-radius-pill'],
		]) {
			expect(await bodyVar(page, nc), nc).toBe(await rootVar(page, token))
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#header-styled-from-tokens
	'the header paints the header tokens and lets the ribbon hang out', async ({
		page,
	}) => {
		await probeLayers(page)
		const header = await page.locator('#header').evaluate((el) => {
			const s = getComputedStyle(el)
			return { background: s.backgroundColor, overflow: s.overflow }
		})
		expect(header.background).toBe(
			await resolveColor(
				page,
				await rootVar(page, '--nldesign-color-header-background'),
			),
		)
		expect(header.overflow).toBe('visible')
		const text = await page
			.locator('#header .header-end')
			.evaluate((el) => getComputedStyle(el).color)
		expect(text).toBe(
			await resolveColor(
				page,
				await rootVar(page, '--nldesign-color-header-text'),
			),
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#login-page-styled-with-government-branding
	'the login page hides the stock header, draws a white box with the ribbon and token buttons', async ({
		browser,
	}) => {
		const tokens = rootDeclarations(repoFile(`css/tokens/${NLDESIGN_SET}.css`))
		const anon = await anonymousPage(browser)
		try {
			const page = anon.page
			await openLoginPage(page)
			const headersShown = await page.evaluate(() =>
				[
					...document.querySelectorAll(
						'#body-login header, #body-login #header',
					),
				]
					.map((h) => getComputedStyle(h).display)
					.filter((d) => d !== 'none'),
			)
			expect(headersShown).toEqual([])

			const box = await page
				.locator('.guest-box.login-box')
				.first()
				.evaluate((el) => {
					const s = getComputedStyle(el)
					return { background: s.backgroundColor, shadow: s.boxShadow }
				})
			expect(box).toEqual({ background: 'rgb(255, 255, 255)', shadow: 'none' })

			const ribbon = await pseudoStyle(
				page,
				'.guest-box.login-box',
				'::before',
			)
			expect(ribbon.width).toBe(tokens.get('--nldesign-size-lint'))
			expect(ribbon.backgroundColor).toBe(
				await resolveColor(
					page,
					tokens.get('--nldesign-color-logo-background') as string,
				),
			)

			const button = await page
				.locator('form button[type="submit"]')
				.first()
				.evaluate((el) => ({
					background: getComputedStyle(el).backgroundColor,
					token: getComputedStyle(el)
						.getPropertyValue(
							'--nldesign-component-button-primary-action-background-color',
						)
						.trim(),
				}))
			expect(button.token).not.toBe('')
			expect(button.background).toBe(await resolveColor(page, button.token))
		} finally {
			await anon.close()
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#focus-states-for-accessibility
	'a keyboard-focused element shows a 2px solid focus-token outline offset by 2px, haloed by the token', async ({
		page,
	}) => {
		await probeLayers(page)
		await page.evaluate(() => {
			const wrap = document.createElement('div')
			wrap.id = 'e2e-focus-probe'
			wrap.style.cssText =
				'position:fixed;top:120px;left:120px;z-index:100000;padding:8px'
			wrap.innerHTML =
				'<a id="e2e-focus-a" href="#e2e-a">first</a> <a id="e2e-focus-b" href="#e2e-b">second</a>'
			document.body.appendChild(wrap)
		})
		await page.locator('#e2e-focus-a').focus()
		await page.keyboard.press('Tab')
		const outline = await page.evaluate(() => {
			const el = document.activeElement as HTMLElement
			const s = getComputedStyle(el)
			return {
				id: el.id,
				style: s.outlineStyle,
				width: s.outlineWidth,
				offset: s.outlineOffset,
				color: s.outlineColor,
				shadow: s.boxShadow,
			}
		})
		expect(outline.id).toBe('e2e-focus-b')
		expect(outline.style).toBe('solid')
		expect(outline.width).toBe('2px')
		expect(outline.offset).toBe('2px')
		// Two-tone ring (#896): the outline is the focus token made opaque, and
		// the translucent token itself is the halo around it.
		const token = await resolveColor(
			page,
			await rootVar(page, '--nldesign-color-focus'),
		)
		const [r, g, b] = parseRgb(token)
		// Normalised through a canvas: a relative colour may serialise as
		// color(srgb ...) rather than rgb(), depending on the browser version.
		const asHex = (c: string) =>
			page.evaluate((v) => {
				const ctx = document
					.createElement('canvas')
					.getContext('2d') as CanvasRenderingContext2D
				ctx.fillStyle = v
				return ctx.fillStyle
			}, c)
		expect(await asHex(outline.color)).toBe(await asHex(`rgb(${r}, ${g}, ${b})`))
		expect(outline.shadow).toContain(token)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#primary-color-variables-mapped
	':root maps the Nextcloud primary variables onto the nldesign primary tokens', async ({
		page,
	}) => {
		await probeLayers(page)
		const pairs: Array<[string, string]> = [
			['--color-primary', '--nldesign-color-primary'],
			['--color-primary-text', '--nldesign-color-primary-text'],
			['--color-primary-hover', '--nldesign-color-primary-hover'],
			['--color-primary-element', '--nldesign-color-primary'],
			['--color-primary-element-hover', '--nldesign-color-primary-hover'],
			['--color-primary-element-text', '--nldesign-color-primary-text'],
			['--color-primary-light', '--nldesign-color-primary-light'],
			['--color-primary-element-light-text', '--nldesign-color-primary'],
		]
		for (const [nc, token] of pairs) {
			expect(await resolveColor(page, await rootVar(page, nc)), nc).toBe(
				await resolveColor(page, await rootVar(page, token)),
			)
		}
		// And it does so with !important, which is what lets it beat core.
		const served = stripComments(
			await servedCss(page, 'systems/nldesign/overrides'),
		)
		expect(served).toMatch(
			/--color-primary:\s*var\(--nldesign-color-primary\)\s*!important/,
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#main-background-intentionally-not-overridden
	'no nldesign layer declares the main background variables, and overrides.css says why', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		const raw = await servedCss(page, 'systems/nldesign/overrides')
		const names = [
			'--color-main-background',
			'--color-main-background-rgb',
			'--color-main-background-translucent',
			'--color-background-plain',
		]
		for (const name of names) {
			expect(raw, `${name} carries an explaining comment`).toMatch(
				new RegExp(`/\\*\\s*${name}:\\s*intentionally not overridden`),
			)
		}
		for (const layer of layers.filter(
			(l) => l.startsWith('systems/') || l.startsWith('tokens/'),
		)) {
			const css = stripComments(await servedCss(page, layer))
			for (const name of names) {
				expect(css, `${layer} must not declare ${name}`).not.toMatch(
					new RegExp(`(^|[\\s;{])${name}\\s*:`),
				)
			}
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#dark-mode-compatibility-preserved
	'no nldesign layer declares the invert-if-dark/bright variables', async ({
		page,
	}) => {
		const layers = await probeLayers(page)
		const names = [
			'--background-invert-if-dark',
			'--background-invert-if-bright',
		]
		for (const layer of layers.filter(
			(l) => l.startsWith('systems/') || l.startsWith('tokens/'),
		)) {
			const css = stripComments(await servedCss(page, layer))
			for (const name of names) {
				expect(css, `${layer} must not declare ${name}`).not.toMatch(
					new RegExp(`(^|[\\s;{])${name}\\s*:`),
				)
			}
		}
		// Nextcloud still computes them, so they keep working.
		for (const name of names) {
			expect(await bodyVar(page, name), name).not.toBe('')
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#typography-variable-mapped
	'--font-face resolves to the nldesign font family, Fira Sans first', async ({
		page,
	}) => {
		await probeLayers(page)
		const fontFace = await rootVar(page, '--font-face')
		expect(fontFace).toBe(await rootVar(page, '--nldesign-font-family'))
		expect(fontFace.replace(/['"]/g, '').split(',')[0].trim()).toBe('Fira Sans')
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#custom-overrides-file-initially-empty
	'an empty overrides file is a valid empty :root block that changes nothing', async ({
		page,
	}) => {
		await probeLayers(page)
		const css = await servedCss(page, 'custom-overrides')
		expect(stripComments(css).replace(/\s+/g, '')).toBe(':root{}')
		expect(rootDeclarations(css).size).toBe(0)
		// Nothing moved: :root still has the mapping overrides.css gives it.
		expect(
			await resolveColor(page, await rootVar(page, '--color-primary')),
		).toBe(
			await resolveColor(
				page,
				await rootVar(page, '--nldesign-color-primary'),
			),
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#primary-text-on-primary-background
	'primary text on the primary colour reaches 4.5:1', async ({ page }) => {
		await probeLayers(page)
		const ratio = contrastRatio(
			await resolveColor(
				page,
				await rootVar(page, '--nldesign-color-primary-text'),
			),
			await resolveColor(
				page,
				await rootVar(page, '--nldesign-color-primary'),
			),
		)
		expect(ratio).toBeGreaterThanOrEqual(4.5)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#default-text-on-default-background
	'body text on white reaches 4.5:1', async ({ page }) => {
		await probeLayers(page)
		const ratio = contrastRatio(
			await resolveColor(page, await rootVar(page, '--nldesign-color-text')),
			'rgb(255, 255, 255)',
		)
		expect(ratio).toBeGreaterThanOrEqual(4.5)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#muted-text-meets-minimum-contrast
	'muted text on white reaches 4.5:1', async ({ page }) => {
		await probeLayers(page)
		const ratio = contrastRatio(
			await resolveColor(
				page,
				await rootVar(page, '--nldesign-color-text-muted'),
			),
			'rgb(255, 255, 255)',
		)
		expect(ratio).toBeGreaterThanOrEqual(4.5)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#design-system-resolved-from-token-set-metadata
	'a set without design_system resolves to the nldesign bundle', async ({
		page,
	}) => {
		const meta = tokenSetManifest().find((t) => t.id === NLDESIGN_SET)
		expect(meta?.design_system ?? 'nldesign').toBe('nldesign')
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const manifest = await stylesheetManifest(page, NLDESIGN_SET)
		expect(manifest.designSystem).toBe('nldesign')
		expect(manifestFiles(manifest)).toEqual(designSystem('nldesign').stylesheets)
		// The public capability names the same system for the active set.
		const cap = await page.evaluate(async () => {
			const res = await fetch('/ocs/v2.php/cloud/capabilities?format=json', {
				headers: { 'OCS-APIRequest': 'true' },
			})
			return (await res.json()).ocs.data.capabilities.nldesign.designSystem
		})
		expect(cap).toBe('nldesign')
	})
})

test.describe('css-architecture: design systems and layers on other sets', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	test(// @e2e openspec/specs/css-architecture/spec.md#stock-nextcloud-design-system-loads-no-stylesheets
	'the stock nextcloud set loads no design-system and no token layer', async ({
		browser,
		page,
	}) => {
		expect(designSystem('none').stylesheets).toEqual([])
		await withThemeState(browser, { tokenSet: 'nextcloud' }, async () => {
			const layers = await probeLayers(page)
			expect(layers.filter((l) => l.startsWith('systems/'))).toEqual([])
			expect(layers.filter((l) => l.startsWith('tokens/'))).toEqual([])
			const manifest = await stylesheetManifest(page, 'nextcloud')
			expect(manifest.designSystem).toBe('none')
			expect(manifestFiles(manifest)).toEqual([])
		})
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#custom-overrides-always-loaded-last
	'custom-overrides loads after every set layer, and it is the same file on two sets', async ({
		browser,
		page,
	}) => {
		const hrefs: string[] = []
		for (const set of [NLDESIGN_SET, 'amsterdam']) {
			await withThemeState(browser, { tokenSet: set }, async () => {
				const layers = await probeLayers(page)
				const overrides = layers.indexOf('custom-overrides')
				const lastSetLayer = lastIndex(
					layers,
					(l) =>
						l.startsWith('systems/')
						|| l.startsWith('tokens/')
						|| l === 'icon-contrast'
						|| l === 'error-contrast'
						|| l === 'component-scopes',
				)
				expect(lastSetLayer, set).toBeGreaterThanOrEqual(0)
				expect(overrides, set).toBeGreaterThan(lastSetLayer)
				hrefs.push(
					new URL((await layerHref(page, 'custom-overrides')) as string)
						.pathname,
				)
			})
		}
		expect(hrefs[0]).toBe(hrefs[1])
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#conditional-css-loading
	'the toggled stylesheets load after custom-overrides, for an admin and for a plain user', async ({
		browser,
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(page)
		await withThemeState(
			browser,
			{ hideSlogan: true, showMenuLabels: true },
			async () => {
				const layers = await probeLayers(page)
				const overrides = layers.findIndex((l) =>
					l.startsWith('custom-overrides'),
				)
				const slogan = layers.indexOf('hide-slogan')
				const labels = layers.indexOf('show-menu-labels')
				expect(overrides).toBeGreaterThanOrEqual(0)
				expect(slogan).toBeGreaterThan(overrides)
				expect(labels).toBeGreaterThan(slogan)
				expect(
					lastIndex(
						layers,
						(l) => l.startsWith('systems/') || l.startsWith('tokens/'),
					),
				).toBeLessThan(slogan)

				// Instance-global: a user in no admin group gets the same two.
				const user = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
				try {
					await user.page.goto(PROBE_URL, {
						waitUntil: 'domcontentloaded',
					})
					const userLayers = await thematiqLayers(user.page)
					expect(userLayers).toContain('hide-slogan')
					expect(userLayers).toContain('show-menu-labels')
				} finally {
					await user.close()
				}
			},
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#defaults-serve-as-fallback-for-incomplete-token-sets
	'a set without an error colour gets the defaults error colour on every layer that uses it', async ({
		browser,
		page,
	}) => {
		const set = 'groningen'
		expect(repoFile(`css/tokens/${set}.css`)).not.toContain(
			'--nldesign-color-error',
		)
		const fallback = rootDeclarations(
			repoFile('css/systems/nldesign/defaults.css'),
		).get('--nldesign-color-error') as string
		await withThemeState(browser, { tokenSet: set, overrides: {} }, async () => {
			const layers = await probeLayers(page)
			expect(layers).toContain(`tokens/${set}`)
			const expected = await resolveColor(page, fallback)
			expect(
				await resolveColor(
					page,
					await rootVar(page, '--nldesign-color-error'),
				),
			).toBe(expected)
			expect(
				await resolveColor(page, await bodyVar(page, '--color-error')),
			).toBe(expected)
		})
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#organization-colors-applied
	"amsterdam's primary wins over the defaults and reaches the Nextcloud variables", async ({
		browser,
		page,
	}) => {
		const primary = rootDeclarations(repoFile('css/tokens/amsterdam.css')).get(
			'--nldesign-color-primary',
		) as string
		expect(primary).toBeTruthy()
		await withThemeState(
			browser,
			{ tokenSet: 'amsterdam', overrides: {} },
			async () => {
				await probeLayers(page)
				const expected = await resolveColor(page, primary)
				expect(
					await resolveColor(
						page,
						await rootVar(page, '--nldesign-color-primary'),
					),
				).toBe(expected)
				expect(
					await resolveColor(page, await bodyVar(page, '--color-primary')),
				).toBe(expected)
				expect(
					await resolveColor(
						page,
						await bodyVar(page, '--color-primary-element'),
					),
				).toBe(expected)
			},
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#non-lint-theme-no-logo-background
	'a set without a logo background draws no ribbon and no logo filter', async ({
		browser,
		page,
	}) => {
		const set = 'amsterdam'
		const tokens = repoFile(`css/tokens/${set}.css`)
		expect(tokens).not.toContain('--nldesign-color-logo-background')
		expect(tokens).not.toContain('--nldesign-logo-filter')
		await withThemeState(browser, { tokenSet: set }, async () => {
			await probeLayers(page)
			const ribbon = await pseudoStyle(page, '#nextcloud', '::before')
			expect(ribbon.width).toBe('0px')
			expect(ribbon.backgroundColor).toBe('rgba(0, 0, 0, 0)')
			const filter = await page
				.locator('#nextcloud .logo')
				.first()
				.evaluate((el) => getComputedStyle(el).filter)
			expect(filter).toBe('none')
		})
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#custom-overrides-cascade-priority
	'a saved --color-primary override beats the token set on :root', async ({
		browser,
		page,
	}) => {
		const tokenPrimary = rootDeclarations(
			repoFile('css/tokens/amsterdam.css'),
		).get('--nldesign-color-primary') as string
		await withThemeState(
			browser,
			{ tokenSet: 'amsterdam', overrides: { '--color-primary': '#ff0000' } },
			async () => {
				await probeLayers(page)
				expect(
					await resolveColor(page, await rootVar(page, '--color-primary')),
				).toBe('rgb(255, 0, 0)')
				// The token set still says what it says; the override wins by order.
				expect(
					await resolveColor(
						page,
						await rootVar(page, '--nldesign-color-primary'),
					),
				).toBe(await resolveColor(page, tokenPrimary))
			},
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#la-suite-design-system-resolves
	'lasuite resolves to its five-file bundle followed by tokens/lasuite', async ({
		page,
	}) => {
		const expected = [
			'systems/lasuite/fonts',
			'systems/lasuite/defaults',
			'systems/lasuite/brand-override',
			'systems/lasuite/bridge',
			'systems/lasuite/element-overrides',
		]
		expect(designSystem('lasuite').stylesheets).toEqual(expected)
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const manifest = await stylesheetManifest(page, 'lasuite')
		expect(manifest.designSystem).toBe('lasuite')
		expect(manifestFiles(manifest)).toEqual(expected)
		const tokenLayer = manifest.layers.find((l) => l.layer === 'tokens')
		expect(new URL(tokenLayer?.href as string, 'http://x').pathname).toMatch(
			/\/thematiq\/css\/tokens\/lasuite\.css$/,
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#cunningham-blue-base-design-system-resolves
	'cunningham resolves to four lasuite files without brand-override and paints #1A509F', async ({
		browser,
		page,
	}) => {
		const expected = [
			'systems/lasuite/fonts',
			'systems/lasuite/defaults',
			'systems/lasuite/bridge',
			'systems/lasuite/element-overrides',
		]
		expect(designSystem('cunningham').stylesheets).toEqual(expected)
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const manifest = await stylesheetManifest(page, 'cunningham')
		expect(manifest.designSystem).toBe('cunningham')
		expect(manifestFiles(manifest)).toEqual(expected)

		await withThemeState(
			browser,
			{ tokenSet: 'cunningham', overrides: {} },
			async () => {
				const layers = await probeLayers(page)
				expect(layers).not.toContain('systems/lasuite/brand-override')
				expect(
					await resolveColor(page, await bodyVar(page, '--color-primary')),
				).toBe('rgb(26, 80, 159)')
			},
		)
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#la-suite-system-files-in-correct-directory
	'the lasuite bundle and its fonts live under css/systems/lasuite/, and only it declares --lasuite-*', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const manifest = await stylesheetManifest(page, 'lasuite')
		for (const file of manifestFiles(manifest)) {
			expect(file.startsWith('systems/lasuite/'), file).toBe(true)
		}

		const fontsUrl = await page.evaluate(() =>
			(window as any).OC.filePath(
				'thematiq',
				'css',
				'systems/lasuite/fonts.css',
			),
		)
		const fontsCss = stripComments(
			await servedCss(page, 'systems/lasuite/fonts'),
		)
		const urls = [...fontsCss.matchAll(/url\(\s*['"]?([^'")]+)['"]?\s*\)/g)].map(
			(m) => new URL(m[1], new URL(fontsUrl, page.url())).href,
		)
		expect(urls.length).toBeGreaterThan(0)
		for (const url of urls) {
			expect(new URL(url).pathname).toContain(
				'/thematiq/css/systems/lasuite/fonts/',
			)
			expect((await page.request.get(url)).status(), url).toBe(200)
		}

		// The --lasuite-* namespace is declared nowhere outside that directory.
		for (const ds of designSystems().filter(
			(d) => d.id !== 'lasuite' && d.id !== 'cunningham',
		)) {
			for (const file of ds.stylesheets) {
				const css = stripComments(await servedCss(page, file))
				expect(css, `${file} must not declare --lasuite-*`).not.toMatch(
					/(^|[\s;{])--lasuite-[\w-]*\s*:/,
				)
			}
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#cunningham-reuses-the-lasuite-directory
	'every cunningham layer is served from css/systems/lasuite/, brand-override excluded', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const files = manifestFiles(await stylesheetManifest(page, 'cunningham'))
		expect(files.length).toBe(4)
		for (const file of files) {
			expect(file.startsWith('systems/lasuite/'), file).toBe(true)
			expect(await servedCss(page, file)).not.toBe('')
		}
		expect(files).not.toContain('systems/lasuite/brand-override')
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#future-design-systems-have-separate-directories
	'every shipped design system except cunningham is served from its own css/systems/<id>/', async ({
		page,
	}) => {
		await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
		const owners = new Map<string, string>()
		for (const ds of designSystems()) {
			if (ds.id === 'cunningham') continue
			for (const file of ds.stylesheets) {
				expect(
					file.startsWith(`systems/${ds.id}/`),
					`${ds.id}: ${file}`,
				).toBe(true)
				expect(await servedCss(page, file)).not.toBe('')
				// No two systems share a file.
				expect(owners.get(file), file).toBeUndefined()
				owners.set(file, ds.id)
			}
		}
	})

	test(// @e2e openspec/specs/css-architecture/spec.md#default-themes-every-context
	'user, login, guest, public share and error pages all get the same set layers', async ({
		browser,
		page,
	}) => {
		// No admin UI sets themed_contexts (occ only), and a fresh instance does
		// not have it, so this is the default state the scenario describes.
		await withThemeState(browser, { tokenSet: NLDESIGN_SET }, async () => {
			const setLayers = (layers: string[]) =>
				layers.filter(
					(l) => l.startsWith('systems/') || l.startsWith('tokens/'),
				)

			const user = setLayers(await probeLayers(page))
			expect(user).toContain(`tokens/${NLDESIGN_SET}`)

			await page.goto('/index.php/apps/definitely-not-an-app/', {
				waitUntil: 'domcontentloaded',
			})
			expect(setLayers(await thematiqLayers(page)), 'error page').toEqual(user)

			// A public link share on a fresh folder, removed again below.
			await page.goto(PROBE_URL, { waitUntil: 'domcontentloaded' })
			const dir = `thematiq-e2e-contexts-${Date.now().toString(36)}`
			const share = await page.evaluate(async (folder) => {
				const oc = (window as any).OC
				await fetch(
					`/remote.php/dav/files/${oc.getCurrentUser().uid}/${folder}`,
					{
						method: 'MKCOL',
						headers: { requesttoken: oc.requestToken },
					},
				)
				const res = await fetch(
					'/ocs/v2.php/apps/files_sharing/api/v1/shares',
					{
						method: 'POST',
						headers: {
							requesttoken: oc.requestToken,
							'OCS-APIRequest': 'true',
							'Content-Type': 'application/json',
							Accept: 'application/json',
						},
						body: JSON.stringify({
							path: `/${folder}`,
							shareType: 3,
							permissions: 1,
						}),
					},
				)
				const json = res.ok ? await res.json() : null
				return {
					status: res.status,
					token: (json?.ocs?.data?.token ?? null) as string | null,
					id: String(json?.ocs?.data?.id ?? ''),
				}
			}, dir)

			const anon = await anonymousPage(browser)
			try {
				expect(
					share.token,
					`public share (OCS ${share.status})`,
				).toBeTruthy()

				await openLoginPage(anon.page)
				expect(setLayers(await thematiqLayers(anon.page)), 'login').toEqual(
					user,
				)

				await anon.page.goto('/index.php/unsupported', {
					waitUntil: 'domcontentloaded',
				})
				expect(setLayers(await thematiqLayers(anon.page)), 'guest').toEqual(
					user,
				)

				await anon.page.goto(`/index.php/s/${share.token}`, {
					waitUntil: 'domcontentloaded',
				})
				expect(setLayers(await thematiqLayers(anon.page)), 'public').toEqual(
					user,
				)
			} finally {
				await anon.close()
				await page.evaluate(
					async ({ id, folder }) => {
						const oc = (window as any).OC
						if (id !== '') {
							await fetch(
								`/ocs/v2.php/apps/files_sharing/api/v1/shares/${id}`,
								{
									method: 'DELETE',
									headers: {
										requesttoken: oc.requestToken,
										'OCS-APIRequest': 'true',
									},
								},
							)
						}
						await fetch(
							`/remote.php/dav/files/${oc.getCurrentUser().uid}/${folder}`,
							{
								method: 'DELETE',
								headers: { requesttoken: oc.requestToken },
							},
						)
					},
					{ id: share.id, folder: dir },
				)
			}
		})
	})
})
