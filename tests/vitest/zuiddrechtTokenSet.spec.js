/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Gemeente Zuiddrecht, read from the files a page loads.
 *
 * The set's header claims that every text pair reaches WCAG AA. A claim in a
 * comment is not a measurement, so these tests compute each pair from the
 * files themselves: css/tokens/zuiddrecht.css for the light scheme, and for
 * the dark scheme the generated css/tokens/dark/zuiddrecht.css with
 * css/token-overrides/zuiddrecht.css on top, in both dark scopes.
 *
 * Two surfaces are not tokens of the set and are named here instead:
 *
 *   - Nextcloud's main background, which cards and the navigation sit on:
 *     #ffffff in the light and #171717 in the dark;
 *   - the selected navigation entry, which Nextcloud 34 and later draw as a
 *     16% wash of the navigation's primary element colour over the main
 *     background, 22% while it is hovered.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

const SET_ID = 'zuiddrecht'
const ENTRY = JSON.parse(read('token-sets.json')).find((e) => e.id === SET_ID)

/** Nextcloud's own main background per scheme (core theming, not a set token). */
const MAIN_BACKGROUND = { light: '#ffffff', dark: '#171717' }

/**
 * Custom properties declared on the token file's one `:root` rule.
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
 * The declarations of a dark stylesheet, per scope: the system preference
 * (`@media`) and the explicit dark theme.
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
 * `color-mix(in srgb, colour share, base)`: what Nextcloud draws for the
 * selected navigation entry.
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

const LIGHT = rootTokens(read('css/tokens/zuiddrecht.css'))
const GENERATED = darkScopes(read('css/tokens/dark/zuiddrecht.css'))
const OVERRIDES = darkScopes(read('css/token-overrides/zuiddrecht.css'))

/** Each scheme's tokens as a page resolves them, by scheme name. */
const SCHEMES = {
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

const SURFACE = '--nldesign-component-content-surface-background-color'

/**
 * The text pairs the set names, for one scheme: `[what, foreground,
 * background, minimum]`.
 *
 * @param {Record<string, string>} t The scheme's tokens.
 * @param {string} main Nextcloud's main background in that scheme.
 * @return {Array<[string, string, string, number]>} The pairs.
 */
function pairs(t, main) {
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
			'selected navigation label on the solid fill (Nextcloud 32 and 33)',
			t['--nldesign-color-primary-text'],
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
		// The public site (openspec/changes/zuiddrecht-site-page-title-notice-surface):
		// the ink on a plain notice, and what the grey band and a boxed
		// table's header row carry: text, muted text and links.
		[
			'attention text on the attention ground',
			t['--nldesign-website-attention-color'],
			t['--nldesign-website-attention-background-color'],
			4.5,
		],
		[
			'notice text on the notice ground',
			t['--nldesign-website-notice-color'],
			t['--nldesign-website-notice-background-color'],
			4.5,
		],
		...[
			['text', '--nldesign-color-text'],
			['muted text', '--nldesign-color-text-muted'],
			['link', '--nldesign-color-link'],
			['link hover', '--nldesign-color-link-hover'],
		].map(([what, token]) => [
			what + ' on the site surface',
			t[token],
			t['--nldesign-color-surface'],
			4.5,
		]),
	]
}

describe('zuiddrecht: the manifest entry', () => {
	it('is an nldesign set with the primary, the workspace colour and both logos', () => {
		expect(ENTRY.name).toBe('Gemeente Zuiddrecht')
		expect(ENTRY.design_system).toBe('nldesign')
		expect(ENTRY.theming.primary_color.toLowerCase()).toBe(
			LIGHT['--nldesign-color-primary'].toLowerCase(),
		)
		expect(ENTRY.theming.background_color.toLowerCase()).toBe(
			LIGHT[SURFACE].toLowerCase(),
		)
		for (const logo of [ENTRY.theming.logo, ENTRY.theming.logo_dark]) {
			expect(fs.existsSync(path.join(ROOT, logo)), logo).toBe(true)
		}
	})

	it('ships the four logos, each a self-contained SVG', () => {
		for (const name of ['', '-dark', '-emblem', '-emblem-grey']) {
			const svg = read('img/logos/zuiddrecht' + name + '.svg')
			expect(svg.startsWith('<svg'), name).toBe(true)
			// Inline fills only: a logo is loaded as a background image, where
			// a <style> block or an external reference does not apply.
			expect(/<style|href=/.test(svg), name).toBe(false)
		}
	})

	it('carries the light layout, the stripe on the login card, a 264px navigation, the soft entry and the workplace bar as its layout defaults', () => {
		expect(ENTRY.layout).toEqual({
			workplace_layout: 'light',
			brand_stripe: true,
			navigation_width: 264,
			navigation_active_style: 'soft',
			brand_stripe_placement: 'login',
			header_style: 'workplace',
		})
	})
})

describe('zuiddrecht: the palette', () => {
	it('uses blue for actions and keeps red as the accent', () => {
		expect(LIGHT['--nldesign-color-primary']).toBe('#3669A5')
		expect(LIGHT['--nldesign-color-primary-hover']).toBe('#234A78')
		expect(LIGHT['--nldesign-color-link']).toBe('#3669A5')
		expect(LIGHT['--nldesign-color-button-primary-background']).toBe('#3669A5')
		expect(LIGHT['--zuiddrecht-color-red']).toBe('#CC0000')
		expect(
			LIGHT['--nldesign-component-navigation-active-background-color'],
		).toBe('#CC0000')
	})

	it("carries Nextcloud's radius scale: 8px controls, 12px containers", () => {
		expect(LIGHT['--nldesign-border-radius']).toBe('8px')
		expect(LIGHT['--nldesign-border-radius-small']).toBe('4px')
		expect(LIGHT['--nldesign-border-radius-large']).toBe('12px')
		expect(LIGHT['--nldesign-border-radius-rounded']).toBe('28px')
		expect(LIGHT['--nldesign-border-radius-pill']).toBe('100px')
	})

	it('declares the stripe as red, blue, red in the ratio 6 : 3 : 1, 5px high', () => {
		expect(
			[1, 2, 3].map((n) => LIGHT['--nldesign-brand-stripe-color-' + n]),
		).toEqual(['#CC0000', '#3669A5', '#CC0000'])
		expect(
			[1, 2, 3].map((n) => LIGHT['--nldesign-brand-stripe-ratio-' + n]),
		).toEqual(['6', '3', '1'])
		expect(LIGHT['--nldesign-brand-stripe-height']).toBe('5px')
	})
})

describe('zuiddrecht: every text pair reaches AA', () => {
	for (const [scheme, { tokens, main }] of Object.entries(SCHEMES)) {
		it('in the ' + scheme + ' scheme', () => {
			const failing = pairs(tokens, main)
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

	it('measures real pairs: a pair that fails is reported, not skipped', () => {
		// The control for the loop above. The red accent on the pale red tint
		// is a pair the design does NOT use for text, because it fails.
		expect(ratio('#CC0000', '#F7D6D6')).toBeLessThan(4.5)
		expect(ratio('#ffffff', '#3669A5')).toBeGreaterThan(5.6)
	})
})

describe('zuiddrecht: the dark workspace', () => {
	it('sets the dark page and surface in both dark scopes, and no colour in the light', () => {
		// Outside the dark scopes the file holds geometry and type only (the
		// login logo box, the workplace boards' measures): every colour there
		// goes through a token, so no colour literal of its own, and the
		// generated dark variant keeps up on its own.
		const literals = []
		postcss
			.parse(read('css/token-overrides/zuiddrecht.css'))
			.walkRules((rule) => {
				const inMedia =
					rule.parent.type === 'atrule'
					&& /prefers-color-scheme:\s*dark/.test(rule.parent.params)
				if (!inMedia && !/data-theme/.test(rule.selector)) {
					rule.walkDecls((d) => {
						// A literal as the last-resort fallback of a token is fine.
						const own = d.value.replace(/,\s*#[0-9a-f]{3,8}\s*\)/gi, ')')
						if (/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i.test(own)) {
							literals.push(
								rule.selector + ' ' + d.prop + ': ' + d.value,
							)
						}
					})
				}
			})
		expect(literals).toEqual([])

		for (const scope of [OVERRIDES.media, OVERRIDES.explicit]) {
			expect(Object.keys(scope).sort()).toEqual([
				'--color-background-plain',
				'--color-background-plain-text',
				SURFACE,
			])
			expect(scope['--color-background-plain']).toBe(scope[SURFACE])
		}
	})

	it('puts the workspace below the cards, where the generator put it above', () => {
		const pinned = OVERRIDES.explicit[SURFACE]
		expect(luminance(pinned)).toBeLessThan(luminance(MAIN_BACKGROUND.dark))
		expect(luminance(GENERATED.explicit[SURFACE])).toBeGreaterThan(
			luminance(MAIN_BACKGROUND.dark),
		)
	})

	it('outranks the generated dark file, which loads after it', () => {
		// Same scopes, one more simple selector in front: (0,2,1) against (0,1,1).
		const selectors = []
		postcss
			.parse(read('css/token-overrides/zuiddrecht.css'))
			.walkRules((rule) => {
				if (rule.nodes.some((node) => node.prop === SURFACE)) {
					selectors.push(
						...rule.selectors.map((sel) =>
							sel.replace(/\s+/g, ' ').trim(),
						),
					)
				}
			})
		expect(selectors.length).toBe(3)
		expect(selectors.every((sel) => sel.startsWith(':root body'))).toBe(true)
	})
})

describe('zuiddrecht: the logo on the login card', () => {
	/**
	 * The rule that sizes the login logo in a stylesheet: the one whose
	 * selector list names the login card's `::after`.
	 *
	 * @param {string} rel The stylesheet.
	 * @return {{selectors: Array<string>, decls: Record<string, string>}} The rule.
	 */
	function loginLogoRule(rel) {
		let found = null
		postcss.parse(read(rel)).walkRules((rule) => {
			const selectors = rule.selectors.map((sel) => sel.trim())
			if (
				found === null
				&& selectors.includes('#body-login .guest-box.login-box::after')
			) {
				const decls = {}
				rule.walkDecls((d) => {
					decls[d.prop] = d.value + (d.important ? ' !important' : '')
				})
				found = { selectors, decls }
			}
		})
		return found
	}

	const base = loginLogoRule('css/systems/nldesign/theme.css')
	const override = loginLogoRule('css/token-overrides/zuiddrecht.css')

	it('follows the NL Design rule it overrides, selector for selector', () => {
		// Same selectors and the same importance, in a file that loads later:
		// that is what makes the override win, and a selector the base rule
		// gains later shows up here.
		expect(base.decls.width).toBe('40px !important')
		expect(override.selectors).toEqual(base.selectors)
	})

	it('gives the wordmark 200 by 44 pixels, centred in the 100px above the form', () => {
		expect(override.decls).toEqual({
			top: '28px !important',
			width: '200px !important',
			height: '44px !important',
		})
		// 4545 by 970 artwork at 200px wide is 42.7px high, so 44px holds it.
		expect((200 * 970) / 4545).toBeLessThan(44)
		expect(28 + 44 + 28).toBe(100)
	})

	it('names the grey emblem as its login watermark, and the file exists', () => {
		expect(LIGHT['--nldesign-login-watermark-image']).toBe(
			"url('../../img/logos/zuiddrecht-emblem-grey.svg')",
		)
		expect(
			fs.existsSync(path.join(ROOT, 'img/logos/zuiddrecht-emblem-grey.svg')),
		).toBe(true)
	})
})

describe('zuiddrecht: the font', () => {
	const FACE = /@font-face\s*\{[^}]*\}/g

	for (const sheet of ['css/fonts.css', 'css/systems/nldesign/fonts.css']) {
		it(
			sheet + ' serves Fira Sans in 400, 500, 600 and 700, normal and italic',
			() => {
				const dir = path.dirname(sheet)
				const faces = (read(sheet).match(FACE) || []).filter((face) =>
					face.includes("'Fira Sans'"),
				)
				const served = new Set()
				for (const face of faces) {
					const weight = /font-weight:\s*(\d+)/.exec(face)[1]
					const style = /font-style:\s*(\w+)/.exec(face)[1]
					for (const url of face.matchAll(/url\('([^']+)'\)/g)) {
						expect(
							fs.existsSync(path.join(ROOT, dir, url[1])),
							url[1],
						).toBe(true)
					}
					served.add(weight + ' ' + style)
				}

				expect([...served].sort()).toEqual(
					['400', '500', '600', '700']
						.flatMap((weight) => [
							weight + ' italic',
							weight + ' normal',
						])
						.sort(),
				)
			},
		)
	}

	it('is the family the set names first', () => {
		expect(LIGHT['--nldesign-font-family'].startsWith("'Fira Sans'")).toBe(true)
	})
})

describe('zuiddrecht: what the layout options carry, and the day-close link', () => {
	const override = read('css/token-overrides/zuiddrecht.css')
	const declarations = []
	postcss.parse(override).walkDecls((d) => {
		declarations.push({
			selector: d.parent.selector || '',
			prop: d.prop,
			value: d.value + (d.important ? ' !important' : ''),
		})
	})

	it('leaves the 264px navigation and the soft entry to the layout options', () => {
		// The set's `layout` block names both, so with nothing stored the page
		// loads navigation-width.css and navigation-active-soft.css
		// (LayoutOptionsServiceTest::testZuiddrechtWearsTheNewerDefaults). A
		// literal here would overrule an administrator who picks another.
		expect(declarations.filter((d) => /\b264px\b/.test(d.value))).toEqual([])
		expect(declarations.filter((d) => /navigation-width/.test(d.prop))).toEqual(
			[],
		)
		expect(
			declarations.filter((d) =>
				/app-navigation-entry[^,]*\.active/.test(d.selector),
			),
		).toEqual([])
	})

	it('draws the navigation card link as a link: link colour, underlined', () => {
		const rule = (prop) =>
			declarations.find(
				(d) =>
					/#content #app-navigation-vue \.cn-app-nav__card-link$/.test(
						d.selector.replace(/\s+/g, ' ').trim(),
					) && d.prop === prop,
			)?.value
		expect(rule('color')).toBe('var(--nldesign-color-link) !important')
		expect(rule('text-decoration')).toBe('underline !important')
	})
})
