/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The four school sets, read from the files a page loads.
 *
 * wilgenboom, vaartveld, esdoornveen and warmtepompacademie are built the way
 * zuiddrecht is, and are measured the way tests/vitest/zuiddrechtTokenSet.spec.js
 * measures it: every text pair is computed from css/tokens/<set>.css for the
 * light scheme, and for the dark scheme from the generated
 * css/tokens/dark/<set>.css with css/token-overrides/<set>.css on top, in both
 * dark scopes. The expected values are the designs' own
 * (openspec/changes/school-token-sets).
 *
 * @spec openspec/changes/school-token-sets/specs/school-token-sets/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const MANIFEST = JSON.parse(read('token-sets.json'))

/** Nextcloud's own main background per scheme (core theming, not a set token). */
const MAIN_BACKGROUND = { light: '#ffffff', dark: '#171717' }
const SURFACE = '--nldesign-component-content-surface-background-color'

/**
 * What each design fixes, per set. Colours, faces and website radii are the
 * designs' (school-design/<set>/project/Main.dc.html and LqTokens.dc.html).
 */
const SETS = {
	wilgenboom: {
		name: 'Basisschool De Wilgenboom',
		primary: '#2F6B4A',
		hover: '#1F4A33',
		light: '#E8F1EB',
		accent: '#F5B82E',
		accentText: '#6B4A00',
		badge: '#1A1A1A',
		family: 'Lexend',
		weights: { Lexend: ['400', '500', '600', '700'] },
		heading: null,
		website: ['10px', '14px'],
		stripe: false,
		stripeHeight: '14px',
		legacyLabel: '#1A1A1A',
	},
	vaartveld: {
		name: 'Vaartveld College',
		primary: '#1F4FD8',
		hover: '#14338F',
		light: '#E8EEFC',
		accent: '#1FB5A8',
		accentText: '#0B6259',
		badge: '#1A1A1A',
		family: 'Red Hat Text',
		weights: {
			'Red Hat Text': ['400', '500', '600', '700'],
			'Red Hat Display': ['600', '700', '800'],
		},
		heading: 'Red Hat Display',
		website: ['6px', '10px'],
		stripe: false,
		stripeHeight: '9px',
		legacyLabel: '#1A1A1A',
	},
	esdoornveen: {
		name: 'Esdoornveen, mbo college',
		primary: '#5B2E91',
		hover: '#43206E',
		light: '#F0EAF7',
		accent: '#C2255C',
		accentText: '#9C1C49',
		badge: '#ffffff',
		family: 'IBM Plex Sans',
		weights: {
			'IBM Plex Sans': ['400', '500', '600', '700'],
			'IBM Plex Mono': ['400', '500'],
		},
		heading: null,
		website: ['4px', '8px'],
		stripe: true,
		stripeHeight: '6px',
		legacyLabel: null,
	},
	warmtepompacademie: {
		name: 'Warmtepompacademie',
		primary: '#0B6E7A',
		hover: '#084F58',
		light: '#E3F1F3',
		accent: '#C2410C',
		accentText: '#9A3412',
		badge: '#ffffff',
		family: 'Barlow',
		weights: {
			Barlow: ['400', '500', '600', '700'],
			'Barlow Semi Condensed': ['600', '700'],
		},
		heading: 'Barlow Semi Condensed',
		website: ['3px', '6px'],
		stripe: false,
		stripeHeight: '5px',
		legacyLabel: null,
	},
}

/**
 * Custom properties declared on a stylesheet's top-level `:root` rule.
 *
 * @param {string} css The stylesheet.
 * @return {Record<string, string>} Property to raw value.
 */
function rootTokens(css) {
	const tokens = {}
	postcss.parse(css).walkRules((rule) => {
		if (rule.parent.type === 'root' && rule.selector.trim() === ':root') {
			rule.walkDecls(/^--/, (d) => {
				tokens[d.prop] = d.value.trim()
			})
		}
	})
	return tokens
}

/**
 * The custom properties of a dark stylesheet, per scope.
 *
 * @param {string} css A stylesheet that uses the two dark scopes.
 * @return {{media: Record<string, string>, explicit: Record<string, string>}} Each scope's declarations.
 */
function darkScopes(css) {
	const media = {}
	const explicit = {}
	postcss.parse(css).walkRules((rule) => {
		const inMedia =
			rule.parent.type === 'atrule'
			&& /prefers-color-scheme:\s*dark/.test(rule.parent.params)
		const isExplicit =
			rule.parent.type === 'root' && /data-themes\*=/.test(rule.selector)
		if (!inMedia && !isExplicit) {
			return
		}
		rule.walkDecls(/^--/, (d) => {
			;(inMedia ? media : explicit)[d.prop] = d.value
				.replace(/\s*!important$/, '')
				.trim()
		})
	})
	return { media, explicit }
}

/**
 * A `#rrggbb` colour as three channels.
 *
 * @param {string} hex The colour.
 * @return {Array<number>} Red, green and blue, 0 to 255.
 */
function channels(hex) {
	const match = /^#([0-9a-f]{6})$/i.exec(hex.trim())
	if (match === null) {
		throw new Error('not a #rrggbb colour: ' + hex)
	}
	return [0, 2, 4].map((at) => parseInt(match[1].slice(at, at + 2), 16))
}

/**
 * WCAG relative luminance.
 *
 * @param {string} hex The colour.
 * @return {number} The luminance, 0 to 1.
 */
function luminance(hex) {
	const [r, g, b] = channels(hex).map((value) => {
		const c = value / 255
		return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4
	})
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * WCAG contrast ratio of two opaque colours.
 *
 * @param {string} first One colour.
 * @param {string} second The other.
 * @return {number} The ratio, 1 to 21.
 */
function ratio(first, second) {
	const [hi, lo] = [luminance(first), luminance(second)].sort((a, b) => b - a)
	return (hi + 0.05) / (lo + 0.05)
}

/**
 * `color-mix(in srgb, colour share, base)`: the selected navigation wash.
 *
 * @param {string} colour The colour mixed in.
 * @param {string} base The surface under it.
 * @param {number} share The share of `colour`, 0 to 1.
 * @return {string} The resulting `#rrggbb`.
 */
function mix(colour, base, share) {
	const top = channels(colour)
	const under = channels(base)
	return (
		'#'
		+ top
			.map((value, index) =>
				Math.round(value * share + under[index] * (1 - share))
					.toString(16)
					.padStart(2, '0'),
			)
			.join('')
	)
}

/**
 * The legacy (solid) selected entry's label colour a set's overrides file
 * sets, or null when it sets none.
 *
 * @param {string} css The overrides file.
 * @return {string|null} The label colour.
 */
function legacyLabel(css) {
	let found = null
	postcss.parse(css).walkDecls('--color-primary-element-text', (d) => {
		found = d.value.trim()
	})
	return found
}

/**
 * The text pairs one scheme must carry: `[what, foreground, background, minimum]`.
 *
 * @param {Record<string, string>} t The scheme's tokens.
 * @param {string} main Nextcloud's main background in that scheme.
 * @param {string} solidLabel The label on the solid selected entry.
 * @return {Array<[string, string, string, number]>} The pairs.
 */
function pairs(t, main, solidLabel) {
	const active = t['--nldesign-component-navigation-active-background-color']
	const text = [
		['text', '--nldesign-color-text'],
		['muted text', '--nldesign-color-text-muted'],
		['link', '--nldesign-color-link'],
		['link hover', '--nldesign-color-link-hover'],
		['error text', '--nldesign-color-error'],
		['success text', '--nldesign-color-success'],
		['warning text', '--nldesign-color-warning'],
		['info text', '--nldesign-color-info'],
	]

	return [
		...text.map(([what, token]) => [what + ' on a card', t[token], main, 4.5]),
		...text.map(([what, token]) => [
			what + ' on the workspace',
			t[token],
			t[SURFACE],
			4.5,
		]),
		[
			'primary button label',
			t['--nldesign-color-button-primary-text'],
			t['--nldesign-color-button-primary-background'],
			4.5,
		],
		[
			'primary text on the primary',
			t['--nldesign-color-primary-text'],
			t['--nldesign-color-primary'],
			4.5,
		],
		[
			'primary on the primary-light wash',
			t['--nldesign-color-primary'],
			t['--nldesign-color-primary-light'],
			4.5,
		],
		[
			'header text',
			t['--nldesign-color-header-text'],
			t['--nldesign-color-header-background'],
			4.5,
		],
		[
			'selected navigation label on its wash (Nextcloud 34+)',
			t['--nldesign-component-navigation-active-color'],
			mix(active, main, 0.16),
			4.5,
		],
		[
			'selected navigation label on its hovered wash (Nextcloud 34+)',
			t['--nldesign-component-navigation-active-color'],
			mix(active, main, 0.22),
			4.5,
		],
		[
			'label on the solid selected entry (settings, legacy, Nextcloud 32 and 33)',
			solidLabel,
			active,
			4.5,
		],
		[
			'navigation badge number',
			t['--nldesign-component-navigation-badge-color'],
			t['--nldesign-component-navigation-badge-background-color'],
			4.5,
		],
		...['info', 'success', 'warning', 'error'].map((kind) => [
			kind + ' pill label on its tint',
			t['--nldesign-component-status-badge-' + kind + '-color'],
			t['--nldesign-component-status-badge-' + kind + '-background-color'],
			4.5,
		]),
		[
			'control border against a card (3:1, WCAG 1.4.11)',
			t['--nldesign-color-border-dark'],
			main,
			3,
		],
	]
}

for (const [id, want] of Object.entries(SETS)) {
	const entry = MANIFEST.find((e) => e.id === id)
	const LIGHT = rootTokens(read('css/tokens/' + id + '.css'))
	const OVERRIDES_CSS = read('css/token-overrides/' + id + '.css')
	const GENERATED = darkScopes(read('css/tokens/dark/' + id + '.css'))
	const OVERRIDES = darkScopes(OVERRIDES_CSS)
	const label = legacyLabel(OVERRIDES_CSS)

	const schemes = {
		light: { tokens: LIGHT, main: MAIN_BACKGROUND.light },
		'dark (system preference)': {
			tokens: { ...LIGHT, ...GENERATED.media, ...OVERRIDES.media },
			main: MAIN_BACKGROUND.dark,
		},
		'dark (explicit theme)': {
			tokens: { ...LIGHT, ...GENERATED.explicit, ...OVERRIDES.explicit },
			main: MAIN_BACKGROUND.dark,
		},
	}

	describe(id + ': the manifest entry', () => {
		it('is an nldesign set with the primary, the workspace colour and both logos', () => {
			expect(entry.name).toBe(want.name)
			expect(entry.design_system).toBe('nldesign')
			expect(entry.theming.primary_color).toBe(want.primary)
			expect(entry.theming.primary_color).toBe(
				LIGHT['--nldesign-color-primary'],
			)
			expect(entry.theming.background_color).toBe(LIGHT[SURFACE])
			expect(entry.theming.logo).toBe('img/logos/' + id + '.svg')
			expect(entry.theming.logo_dark).toBe('img/logos/' + id + '-dark.svg')
		})

		it('ships its four logos, each a self-contained SVG', () => {
			for (const name of ['', '-dark', '-emblem', '-emblem-grey']) {
				const svg = read('img/logos/' + id + name + '.svg')
				expect(svg.startsWith('<svg'), name).toBe(true)
				expect(/<style|href=/.test(svg), name).toBe(false)
			}
		})

		it('wears the light layout, and the stripe where the motif fits inside the top bar', () => {
			const layout = { workplace_layout: 'light' }
			if (want.stripe === true) {
				layout.brand_stripe = true
			}
			expect(entry.layout).toEqual(layout)
		})
	})

	describe(id + ': the palette', () => {
		it('uses the main colour for actions and keeps the accent for marks', () => {
			expect(LIGHT['--nldesign-color-primary']).toBe(want.primary)
			expect(LIGHT['--nldesign-color-primary-hover']).toBe(want.hover)
			expect(LIGHT['--nldesign-color-primary-light']).toBe(want.light)
			expect(LIGHT['--nldesign-color-link']).toBe(want.primary)
			expect(LIGHT['--nldesign-color-button-primary-background']).toBe(
				want.primary,
			)
			expect(LIGHT['--' + id + '-color-accent']).toBe(want.accent)
			expect(
				LIGHT['--nldesign-component-navigation-active-background-color'],
			).toBe(want.accent)
			expect(LIGHT['--nldesign-component-navigation-active-color']).toBe(
				want.accentText,
			)
			expect(
				LIGHT['--nldesign-component-navigation-badge-background-color'],
			).toBe(want.accent)
			expect(
				LIGHT['--nldesign-component-navigation-badge-color'].toLowerCase(),
			).toBe(want.badge.toLowerCase())
		})

		it("keeps Nextcloud's radius scale in the workplace and names the website's own", () => {
			expect(LIGHT['--nldesign-border-radius']).toBe('8px')
			expect(LIGHT['--nldesign-border-radius-small']).toBe('4px')
			expect(LIGHT['--nldesign-border-radius-large']).toBe('12px')
			expect([
				LIGHT['--nldesign-website-border-radius'],
				LIGHT['--nldesign-website-border-radius-large'],
			]).toEqual(want.website)
		})

		it('draws its motif as the stripe image, at the motif height', () => {
			expect(LIGHT['--nldesign-brand-stripe-height']).toBe(want.stripeHeight)
			const image = LIGHT['--nldesign-brand-stripe-image']
			expect(image).toMatch(/^(linear-gradient\(|url\("data:image\/svg\+xml,)/)
			for (const n of [1, 2, 3]) {
				expect(LIGHT['--nldesign-brand-stripe-color-' + n]).toMatch(
					/^#[0-9A-F]{6}$/,
				)
			}
		})
	})

	describe(id + ': every text pair reaches AA', () => {
		for (const [scheme, { tokens, main }] of Object.entries(schemes)) {
			it('in the ' + scheme + ' scheme', () => {
				const solid = label ?? tokens['--nldesign-color-primary-text']
				const failing = pairs(tokens, main, solid)
					.map(([what, fg, bg, minimum]) => ({
						what,
						fg,
						bg,
						minimum,
						measured: Math.floor(ratio(fg, bg) * 100) / 100,
					}))
					.filter((pair) => pair.measured < pair.minimum)

				expect(failing).toEqual([])
			})
		}
	})

	describe(id + ': the overrides', () => {
		it('sets the dark page and surface in both dark scopes', () => {
			for (const scope of [OVERRIDES.media, OVERRIDES.explicit]) {
				expect(scope['--color-background-plain']).toBe('#121315')
				expect(scope[SURFACE]).toBe('#121315')
				expect(luminance(scope[SURFACE])).toBeLessThan(
					luminance(MAIN_BACKGROUND.dark),
				)
			}
		})

		it('gives the wordmark 200 by 44 pixels on the login card', () => {
			const decls = {}
			postcss.parse(OVERRIDES_CSS).walkRules((rule) => {
				if (
					rule.selectors
						.map((s) => s.trim())
						.includes('#body-login .guest-box.login-box::after')
				) {
					rule.walkDecls((d) => {
						decls[d.prop] = d.value + (d.important ? ' !important' : '')
					})
				}
			})
			expect(decls).toEqual({
				top: '28px !important',
				width: '200px !important',
				height: '44px !important',
			})
		})

		it('writes ink on the solid accent only where white would fail', () => {
			expect(label?.toLowerCase() ?? null).toBe(
				want.legacyLabel?.toLowerCase() ?? null,
			)
			if (label === null) {
				expect(ratio('#ffffff', want.accent)).toBeGreaterThanOrEqual(4.5)
			} else {
				expect(ratio('#ffffff', want.accent)).toBeLessThan(3)
			}
		})

		it('names its grey emblem as the login watermark, and the file exists', () => {
			expect(LIGHT['--nldesign-login-watermark-image']).toBe(
				"url('../../img/logos/" + id + "-emblem-grey.svg')",
			)
			expect(
				fs.existsSync(
					path.join(ROOT, 'img/logos/' + id + '-emblem-grey.svg'),
				),
			).toBe(true)
		})
	})

	describe(id + ': the typefaces', () => {
		const FACE = /@font-face\s*\{[^}]*\}/g

		for (const sheet of ['css/fonts.css', 'css/systems/nldesign/fonts.css']) {
			it(
				sheet
					+ ' serves every weight the design uses, from files that exist',
				() => {
					const dir = path.dirname(sheet)
					for (const [family, weights] of Object.entries(want.weights)) {
						const served = new Set()
						for (const face of read(sheet).match(FACE) || []) {
							if (!face.includes("'" + family + "'")) {
								continue
							}
							for (const url of face.matchAll(/url\('([^']+)'\)/g)) {
								expect(
									fs.existsSync(path.join(ROOT, dir, url[1])),
									url[1],
								).toBe(true)
							}
							served.add(/font-weight:\s*(\d+)/.exec(face)[1])
						}
						expect([...served].sort(), family).toEqual(weights)
					}
				},
			)
		}

		it('names its text face first, and its heading face where it has one', () => {
			expect(
				LIGHT['--nldesign-font-family'].startsWith("'" + want.family + "'"),
			).toBe(true)
			const heading = LIGHT['--nldesign-component-heading-font-family']
			if (want.heading === null) {
				expect(heading).toBeUndefined()
			} else {
				expect(heading.startsWith("'" + want.heading + "'")).toBe(true)
			}
		})
	})
}

describe('the school sets leave the example sets alone', () => {
	it('keeps the four example education sets listed under their own ids', () => {
		const ids = MANIFEST.map((e) => e.id)
		for (const id of [
			'example-basisschool',
			'example-voortgezet',
			'example-college',
			'example-opleider',
		]) {
			expect(ids, id).toContain(id)
			expect(
				fs.existsSync(path.join(ROOT, 'css/tokens/' + id + '.css')),
				id,
			).toBe(true)
		}
	})
})

describe('the control: a pair that fails is reported, not skipped', () => {
	it('measures white on the two light accents as failing', () => {
		expect(ratio('#ffffff', '#F5B82E')).toBeLessThan(3)
		expect(ratio('#ffffff', '#1FB5A8')).toBeLessThan(3)
		expect(ratio('#ffffff', '#2F6B4A')).toBeGreaterThan(6.3)
	})
})

describe('the public bridge hands the website its own corners and heading face', () => {
	const BRIDGE = rootTokens(read('css/public-bridge.css'))
	const flat = (value) => value.replace(/\s+/g, ' ').trim()

	it('maps the control and card roles onto the two website radii, with no fallback', () => {
		for (const role of [
			'--utrecht-button-border-radius',
			'--utrecht-textbox-border-radius',
			'--utrecht-textarea-border-radius',
			'--utrecht-select-border-radius',
			'--utrecht-form-control-border-radius',
		]) {
			expect(flat(BRIDGE[role]), role).toBe(
				'var(--nldesign-website-border-radius)',
			)
		}
		for (const role of [
			'--utrecht-border-radius-md',
			'--utrecht-alert-border-radius',
		]) {
			expect(flat(BRIDGE[role]), role).toBe(
				'var(--nldesign-website-border-radius-large)',
			)
		}
	})

	it('reads the website radii nowhere an instance page loads', () => {
		const readers = []
		const walk = (dir) => {
			for (const entry of fs.readdirSync(path.join(ROOT, dir), {
				withFileTypes: true,
			})) {
				const rel = dir + '/' + entry.name
				if (entry.isDirectory()) {
					walk(rel)
				} else if (
					rel.endsWith('.css')
					&& !rel.startsWith('css/tokens/')
					&& read(rel).includes('var(--nldesign-website-border-radius')
				) {
					readers.push(rel)
				}
			}
		}
		walk('css')
		expect(readers).toEqual(['css/public-bridge.css'])
	})

	it('gives every heading level the set heading face before its text face', () => {
		for (const role of [
			'--utrecht-heading-font-family',
			'--utrecht-heading-1-font-family',
			'--utrecht-heading-2-font-family',
			'--utrecht-heading-3-font-family',
		]) {
			expect(flat(BRIDGE[role]), role).toBe(
				'var( --nldesign-component-heading-font-family, var(--nldesign-font-family, system-ui, sans-serif) )',
			)
		}
	})
})
