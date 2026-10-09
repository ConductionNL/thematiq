/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The two conditional stylesheets behind the layout options.
 *
 * css/workplace-layout.css gives any theme a light top bar. It may be turned
 * on for a set that was drawn with a coloured one, in the light and in the
 * dark, so it must take its colours from the scheme and write none itself.
 *
 * css/brand-stripe.css draws three bands from tokens. The stops are computed
 * in CSS from unitless ratios; the test resolves the same arithmetic from the
 * stylesheet's own expressions, with Zuiddrecht's tokens and with none.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/workplace-layout/spec.md
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/brand-stripe/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

/**
 * Every rule of a stylesheet as `{selectors, decls}`.
 *
 * @param {string} rel The stylesheet, relative to the repository.
 * @return {Array<{selectors: Array<string>, decls: Record<string, string>}>} The rules.
 */
function rulesOf(rel) {
	const rules = []
	postcss.parse(read(rel)).walkRules((rule) => {
		const decls = {}
		rule.walkDecls((d) => {
			decls[d.prop] = (d.value + (d.important ? ' !important' : '')).replace(
				/\s+/g,
				' ',
			)
		})
		rules.push({ selectors: rule.selectors.map((s) => s.trim()), decls })
	})
	return rules
}

/**
 * Resolve `var()` and `calc()` in a value the way a browser would, for the
 * unitless arithmetic the stripe uses.
 *
 * @param {string} value The declared value.
 * @param {Record<string, string>} vars The custom properties in scope.
 * @return {string} The value with every `var()` substituted.
 */
function substitute(value, vars) {
	let out = value
	for (let pass = 0; pass < 20 && out.includes('var('); pass++) {
		out = out.replace(
			/var\(\s*(--[\w-]+)\s*(?:,\s*([^()]*(?:\([^()]*\)[^()]*)*))?\)/g,
			(_, name, fallback) => {
				if (vars[name] !== undefined) {
					return vars[name]
				}
				if (fallback === undefined) {
					throw new Error('unresolved ' + name)
				}
				return fallback.trim()
			},
		)
	}
	return out
}

/**
 * Evaluate a `calc()` expression that ends in `* 100%`, as a percentage.
 *
 * @param {string} expression The substituted value.
 * @return {number} The percentage.
 */
function percentage(expression) {
	const arithmetic = expression.replace(/calc/g, '').replace(/100%/, '100')
	if (/^[\d\s+\-*/().]+$/.test(arithmetic) === false) {
		throw new Error('not arithmetic: ' + arithmetic)
	}
	return Number(Function('"use strict"; return (' + arithmetic + ')')())
}

describe('workplace layout: the light top bar', () => {
	const rules = rulesOf('css/workplace-layout.css')

	it('starts with the rule on the top bar, which leaves the login page alone, then the login watermark', () => {
		expect(rules[1].selectors).toEqual(['body#body-login::before'])
		for (const selector of rules[0].selectors) {
			expect(selector).toMatch(
				/^:where\(body:not\(#body-login\)\) (header)?#header$/,
			)
		}
	})

	it('writes no colour literal anywhere in the sheet', () => {
		// The sheet may be turned on for any set, in the light and in the dark,
		// so every colour comes from the scheme or from a token.
		for (const rule of rules) {
			for (const [prop, value] of Object.entries(rule.decls)) {
				expect(
					/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i.test(value),
					rule.selectors.join(', ') + ' { ' + prop + ': ' + value + ' }',
				).toBe(false)
			}
		}
	})

	it("paints the bar with the scheme's main background and writes no colour of its own", () => {
		const decls = rules[0].decls
		expect(decls['background-color']).toBe(
			'var( --nldesign-component-header-background-color, var(--color-main-background) ) !important',
		)
		for (const [prop, value] of Object.entries(decls)) {
			expect(
				/#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i.test(value),
				prop + ': ' + value,
			).toBe(false)
		}
	})

	it('hands every header variable a design system reads the main text and background', () => {
		const decls = rules[0].decls
		// Nextcloud's own header rules.
		expect(decls['--color-background-plain-text']).toBe(
			'var( --nldesign-component-header-color, var(--color-main-text) )',
		)
		expect(decls['--background-image-invert-if-bright']).toBe(
			'var(--background-invert-if-bright)',
		)
		// The NL Design sheets.
		expect(decls['--nldesign-color-header-background']).toBe(
			'var(--color-main-background)',
		)
		expect(decls['--nldesign-color-header-text']).toBe('var(--color-main-text)')
		expect(decls['--nldesign-header-icon-filter']).toBe(
			'var(--background-invert-if-bright)',
		)
	})

	it('reads only header tokens the NL Design sheets really read', () => {
		// The control for the test above: a token nothing reads would make the
		// redeclaration decoration.
		const sheets = ['theme.css', 'element-overrides.css']
			.map((file) => read('css/systems/nldesign/' + file))
			.join('\n')
		for (const token of [
			'--nldesign-color-header-background',
			'--nldesign-color-header-text',
			'--nldesign-header-icon-filter',
		]) {
			expect(
				sheets.includes('var(' + token)
					|| sheets.includes(token + ',')
					|| sheets.includes(token + ')'),
				token,
			).toBe(true)
		}
	})
})

describe('workplace layout: the login watermark', () => {
	const watermark = rulesOf('css/workplace-layout.css')[1].decls

	it('draws nothing for a set that names no image', () => {
		expect(watermark['background-image']).toBe(
			'var(--nldesign-login-watermark-image, none)',
		)
	})

	it('is faint, fixed in the bottom corner, and takes no pointer events', () => {
		expect(watermark.content).toBe("''")
		expect(watermark.opacity).toBe(
			'var(--nldesign-login-watermark-opacity, 0.07)',
		)
		expect(watermark.position).toBe('fixed')
		expect(watermark['inset-inline-end']).toBeDefined()
		expect(watermark.bottom).toBeDefined()
		expect(watermark['pointer-events']).toBe('none')
	})

	it('is drawn as NcLogin draws it: 720px high, 90px and 120px past the corner', () => {
		// 720 / 90 / 120 on a 1440 by 900 window: the height is min(720px, 80vmin),
		// the offsets are 12.5% and 16.67% of it.
		expect(watermark['--thematiq-watermark-height']).toContain(
			'min(720px, 80vmin)',
		)
		expect(watermark.height).toBe('var(--thematiq-watermark-height)')
		expect(watermark['inset-inline-end']).toBe(
			'calc(var(--thematiq-watermark-height) * -0.125)',
		)
		expect(watermark.bottom).toBe(
			'calc(var(--thematiq-watermark-height) * -0.1667)',
		)
	})

	it('is named by Zuiddrecht and its four demo schools only among the shipped sets', () => {
		const dir = path.join(ROOT, 'css/tokens')
		const namers = fs
			.readdirSync(dir)
			.filter((file) => file.endsWith('.css'))
			.filter((file) =>
				fs
					.readFileSync(path.join(dir, file), 'utf8')
					.includes('--nldesign-login-watermark-image'),
			)
		expect(namers.sort()).toEqual([
			'esdoornveen.css',
			'vaartveld.css',
			'warmtepompacademie.css',
			'wilgenboom.css',
			'zuiddrecht.css',
		])
	})
})

/**
 * Every stylesheet a design system ships, as `[name, rules]`.
 *
 * @return {Array<[string, Array<{selectors: Array<string>, decls: Record<string, string>}>]>} The sheets.
 */
function systemSheets() {
	const sheets = []
	const systems = path.join(ROOT, 'css/systems')
	for (const system of fs.readdirSync(systems)) {
		for (const file of fs.readdirSync(path.join(systems, system))) {
			if (file.endsWith('.css') === true) {
				const name = system + '/' + file
				sheets.push([name, rulesOf('css/systems/' + name)])
			}
		}
	}
	return sheets
}

/**
 * Specificity of a selector as [ids, classes, elements]. `:where()` counts
 * for nothing.
 *
 * @param {string} selector A selector.
 * @return {Array<number>} The three counts.
 */
function specificity(selector) {
	const bare = selector.replace(/:where\([^()]*(?:\([^()]*\)[^()]*)*\)/g, '')
	const ids = (bare.match(/#[\w-]+/g) || []).length
	const classes = (bare.match(/\.[\w-]+|\[[^\]]*\]/g) || []).length
	const elements = (bare.match(/(^|[\s>+~])[a-z][\w-]*|::[\w-]+/g) || []).length
	return [ids, classes, elements]
}

describe('brand stripe', () => {
	const rules = rulesOf('css/brand-stripe.css')
	const stripe = rules[0].decls
	const HEADER = ':where(body:not(#body-login)) #header::after'
	const LOGIN = "#body-login div[class*='login-box__wrapper']::before"

	it('is one shared rule for the top bar and the login card, plus where each one sits', () => {
		expect(rules.length).toBe(4)
		expect([...rules[0].selectors].sort()).toEqual([LOGIN, HEADER].sort())
		expect(rules[1]).toEqual({ selectors: [HEADER], decls: { bottom: '0' } })
		expect(rules[2]).toEqual({ selectors: [LOGIN], decls: { top: '0' } })
		expect(stripe['pointer-events']).toBe('none')
		expect(stripe.position).toBe('absolute')
		expect(stripe.height).toBe('var(--nldesign-brand-stripe-height, 4px)')
	})

	it('hands the same tokens to the component library, with the same fallbacks', () => {
		// CnBrandStripe in nextcloud-vue reads `--cn-brand-stripe-*` and knows
		// no theme. Without this rule a portal header drew one primary band
		// next to a top bar in three.
		expect(rules[3].selectors).toEqual([':root'])
		const handed = rules[3].decls
		for (const n of [1, 2, 3]) {
			expect(handed['--cn-brand-stripe-ratio-' + n]).toBe(
				'var(--nldesign-brand-stripe-ratio-' + n + ', 1)',
			)
			expect(handed['--cn-brand-stripe-color-' + n]).toContain(
				'--nldesign-brand-stripe-color-' + n,
			)
		}
		expect(handed['--cn-brand-stripe-height']).toBe(stripe.height)
		// The motif and its dark-band variant, with no fallback, so a set that
		// names no image keeps the component's three bands
		// (openspec/changes/brand-motif-on-portals).
		expect(handed['--cn-brand-stripe-image']).toBe(
			'var(--nldesign-brand-stripe-image)',
		)
		expect(handed['--cn-brand-stripe-image-inverse'].replace(/\s+/g, ' ')).toBe(
			'var( --nldesign-brand-stripe-image-inverse, var(--nldesign-brand-stripe-image) )',
		)
		expect(Object.keys(handed).length).toBe(9)
	})

	it('is really drawn: content and display are declared, and important', () => {
		// The defect this guards: the stripe once declared a plain
		// `content: ''`. The NL Design and La Suite sheets switch the header's
		// pseudo-elements off with `!important`, so the stripe computed its
		// height and its gradient and was never rendered.
		expect(stripe.content).toBe("'' !important")
		expect(stripe.display).toBe('block !important')
	})

	it('outranks every design system rule that switches the header pseudo-element off', () => {
		const resets = []
		for (const [name, sheet] of systemSheets()) {
			for (const rule of sheet) {
				if (/none/.test(rule.decls.content || '') === false) {
					continue
				}
				for (const selector of rule.selectors) {
					if (/#header::after$/.test(selector)) {
						resets.push({ from: name, selector })
					}
				}
			}
		}

		// The control: the resets exist. Without them this test proves nothing.
		expect(resets.map((reset) => reset.from).sort()).toEqual([
			'lasuite/element-overrides.css',
			'nldesign/element-overrides.css',
		])
		// Equal specificity is enough: this sheet loads after theirs.
		expect(specificity(HEADER)).toEqual([1, 0, 1])
		for (const reset of resets) {
			expect(specificity(reset.selector), reset.selector).toEqual([1, 0, 1])
		}
	})

	it('uses a login pseudo-element no design system stylesheet claims', () => {
		// The login card's own two pseudo-elements carry the ribbon and the
		// logo, so the stripe uses the inner wrapper's `::before`.
		const claimed = []
		for (const [name, sheet] of systemSheets()) {
			for (const rule of sheet) {
				if (
					rule.selectors.some((selector) =>
						/login-box__wrapper[^\s]*::?(before|after)$/.test(selector),
					)
				) {
					claimed.push(name)
				}
			}
		}
		expect(claimed).toEqual([])
	})

	/**
	 * The two stops and the three colours, for a set's tokens.
	 *
	 * @param {Record<string, string>} tokens The set's stripe tokens.
	 * @return {{stops: Array<number>, colours: Array<string>, height: string}} What the stripe resolves to.
	 */
	function resolve(tokens) {
		const vars = { ...tokens }
		for (const [prop, value] of Object.entries(stripe)) {
			if (prop.startsWith('--thematiq-stripe-')) {
				vars[prop] = substitute(value, vars)
			}
		}
		return {
			stops: [
				percentage(vars['--thematiq-stripe-stop-1']),
				percentage(vars['--thematiq-stripe-stop-2']),
			],
			colours: [1, 2, 3].map((n) => vars['--thematiq-stripe-color-' + n]),
			height: substitute(stripe.height, vars),
		}
	}

	it('draws Zuiddrecht as red to 60%, blue to 90%, red to the end, 5px high', () => {
		const tokens = {}
		postcss
			.parse(read('css/tokens/zuiddrecht.css'))
			.walkDecls(/^--nldesign-brand-stripe-/, (d) => {
				tokens[d.prop] = d.value.trim()
			})
		expect(Object.keys(tokens).length).toBe(7)

		const resolved = resolve(tokens)
		expect(resolved.stops[0]).toBeCloseTo(60, 6)
		expect(resolved.stops[1]).toBeCloseTo(90, 6)
		expect(resolved.colours).toEqual(['#CC0000', '#3669A5', '#CC0000'])
		expect(resolved.height).toBe('5px')
	})

	it('draws three equal bands in the primary colours, 4px high, for a set with no stripe tokens', () => {
		const resolved = resolve({
			'--color-primary-element': 'PRIMARY',
			'--color-primary-element-hover': 'HOVER',
		})
		expect(resolved.stops[0]).toBeCloseTo(100 / 3, 6)
		expect(resolved.stops[1]).toBeCloseTo(200 / 3, 6)
		expect(resolved.colours).toEqual(['PRIMARY', 'HOVER', 'PRIMARY'])
		expect(resolved.height).toBe('4px')
	})

	it('gives each band a hard edge: both neighbours share the stop', () => {
		const gradient = stripe['background-image']
		expect(gradient).toContain(
			'var(--thematiq-stripe-color-1) 0 var(--thematiq-stripe-stop-1)',
		)
		expect(gradient).toContain(
			'var(--thematiq-stripe-color-2) var(--thematiq-stripe-stop-1) var(--thematiq-stripe-stop-2)',
		)
		expect(gradient).toContain(
			'var(--thematiq-stripe-color-3) var(--thematiq-stripe-stop-2) 100%',
		)
	})
})

describe('the brand stripe: a motif of its own', () => {
	/**
	 * @spec openspec/changes/school-token-sets/specs/school-token-sets/spec.md#requirement-a-set-may-draw-its-motif-in-the-brand-stripe
	 */
	it('draws a set image in place of the three bands, and the bands without one', () => {
		const sheet = postcss.parse(read('css/brand-stripe.css'))
		let image = null
		sheet.walkDecls('background-image', (d) => {
			image = d.value.replace(/\s+/g, ' ').trim()
		})
		expect(
			image.startsWith('var( --nldesign-brand-stripe-image, linear-gradient('),
		).toBe(true)
	})
})

describe('the layout stylesheets are off unless asked for', () => {
	it('no design system lists them, so only the option loads them', () => {
		const systems = JSON.parse(read('design-systems.json'))
		const listed = systems.flatMap((system) =>
			(system.stylesheets || []).filter((sheet) =>
				/workplace-layout|brand-stripe|navigation-width|navigation-active-soft|login-watermark-off/.test(
					sheet,
				),
			),
		)
		expect(listed).toEqual([])
	})

	it('only Zuiddrecht and its four demo schools carry layout defaults among the shipped sets', () => {
		const carriers = JSON.parse(read('token-sets.json'))
			.filter((entry) => entry.layout !== undefined)
			.map((entry) => entry.id)
		expect(carriers).toEqual([
			'zuiddrecht',
			'wilgenboom',
			'vaartveld',
			'esdoornveen',
			'warmtepompacademie',
		])
	})
})

/**
 * The rules of a sheet whose selector list contains `needle`.
 *
 * @param {Array<{selectors: Array<string>, decls: Record<string, string>}>} rules The sheet.
 * @param {string} needle Part of a selector.
 * @return {Array<{selectors: Array<string>, decls: Record<string, string>}>} The matches.
 */
function withSelector(rules, needle) {
	// Prettier breaks a long selector over several lines; compare it flat.
	return rules.filter((rule) =>
		rule.selectors.some((selector) =>
			selector.replace(/\s+/g, ' ').includes(needle),
		),
	)
}

describe('workplace layout: the login page and the guest pages', () => {
	// @spec openspec/changes/workplace-layout-core-pages/specs/workplace-layout/spec.md
	const rules = rulesOf('css/workplace-layout.css')

	it("shows Nextcloud's own guest logo above the card, 56px high, with the set's logo in it", () => {
		const [logo] = withSelector(rules, '#header.header-guest .logo')
		expect(logo.decls.display).toBe('block !important')
		expect(logo.decls.height).toBe('56px !important')
		expect(logo.decls['background-image']).toBe(
			'var(--nldesign-logo-url, var(--image-logo)) !important',
		)
		const [header] = withSelector(rules, 'body#body-login #header.header-guest')
		expect(header.decls.display).toBe('block !important')
	})

	it('retires the in-card stamp and the room the NL Design sheet keeps for it', () => {
		const [stamp] = withSelector(rules, '.guest-box.login-box::after')
		expect(stamp.decls.display).toBe('none !important')
		const [wrapper] = withSelector(rules, "div[class*='login-box__wrapper']")
		expect(wrapper.decls['padding-top']).toBe(
			'var(--nldesign-brand-stripe-height, 0px) !important',
		)
	})

	it('draws the card 420px wide with a hairline, the container radius and the card shadow colour', () => {
		const [card] = rules.filter(
			(rule) =>
				rule.selectors.join() === 'body#body-login .guest-box.login-box',
		)
		expect(card.decls.width).toBe('min(420px, 100%) !important')
		expect(card.decls.border).toBe(
			'1px solid var(--nldesign-component-login-box-border-color, var(--color-border)) !important',
		)
		expect(card.decls['border-radius']).toBe(
			'var( --nldesign-component-login-box-border-radius, var(--border-radius-container-large) ) !important',
		)
		expect(card.decls['box-shadow']).toBe(
			'0 2px 12px var( --nldesign-login-card-shadow-color, var(--nldesign-component-content-card-shadow-color, transparent) ) !important',
		)
	})

	it('titles the card at 24px and sizes the controls at 44px with a 1px edge', () => {
		const [title] = withSelector(rules, '.login-form__headline')
		expect(title.decls['font-size']).toBe('24px !important')
		const [body] = rules.filter(
			(rule) => rule.selectors.join() === 'body#body-login',
		)
		expect(body.decls['--default-clickable-area']).toBe('44px')
		const [input] = withSelector(rules, '.input-field__input')
		expect(input.decls['border-width']).toBe('1px !important')
	})

	it('paints the guest action button label with the login button label colour', () => {
		// The NL Design link rule `a:not(#header a)…` scores (1,2,4); these
		// selectors must outrank it or the label stays link-blue on blue.
		const [action] = withSelector(
			rules,
			'.body-login-container a.button.primary',
		)
		expect(action.decls.color).toBe(
			'var( --nldesign-component-login-button-color, var(--color-primary-element-text) ) !important',
		)
		for (const selector of action.selectors) {
			const [ids, classes] = specificity(selector)
			expect(ids >= 1 && classes >= 3, selector).toBe(true)
		}
	})

	it('paints the two text actions under the form in the link colour, through the on-surface opt-out', () => {
		const [actions] = rules.filter(
			(rule) =>
				rule.decls['--nldesign-color-on-surface'] !== undefined
				&& rule.selectors.some((s) =>
					s.includes('.button-vue--vue-tertiary'),
				),
		)
		expect(actions.decls['--nldesign-color-on-surface']).toBe(
			'var( --nldesign-color-link, var(--color-primary-element) )',
		)
		expect(actions.decls.color).toBe(
			'var(--nldesign-color-link, var(--color-primary-element)) !important',
		)
		// The control: the NL Design text rule really reads the opt-out.
		expect(read('css/systems/nldesign/element-overrides.css')).toContain(
			'var(--nldesign-color-on-surface, var(--nldesign-color-text)) !important',
		)
	})

	it('takes the plate from under the footer line and mutes it', () => {
		const [footer] = withSelector(rules, 'body#body-login footer.guest-box')
		expect(footer.decls.background).toBe('transparent !important')
		const [line] = withSelector(rules, 'footer.guest-box p.info a.entity-name')
		expect(line.decls.color).toBe('var(--color-text-maxcontrast) !important')
		for (const selector of line.selectors.filter((s) =>
			s.endsWith(' a.entity-name'),
		)) {
			const [ids, classes] = specificity(selector)
			expect(ids >= 1 && classes >= 3, selector).toBe(true)
		}
	})
})

describe('workplace layout: the top bar', () => {
	// @spec openspec/changes/workplace-layout-core-pages/specs/workplace-layout/spec.md
	const rules = rulesOf('css/workplace-layout.css')

	it('is 68px high through the variable Nextcloud lays the page out from', () => {
		const [body] = rules.filter(
			(rule) => rule.selectors.join() === 'body:not(#body-login)',
		)
		expect(body.decls['--header-height']).toBe('68px')
	})

	it('draws the app grid button as a 40px square on the workspace colour', () => {
		const [waffle] = withSelector(rules, '.app-menu__waffle')
		expect(waffle.decls['--button-size']).toBe('40px')
		expect(waffle.decls.width).toBe('40px !important')
		expect(waffle.decls.height).toBe('40px !important')
		expect(waffle.decls['border-radius']).toBe(
			'var(--border-radius-element) !important',
		)
		expect(waffle.decls['background-color']).toContain(
			'--nldesign-component-content-surface-background-color',
		)
	})

	it('draws the search field as a 44px pill on the workspace colour with a muted regular label', () => {
		const [search] = withSelector(rules, '.unified-search__button')
		expect(search.decls.height).toBe('44px !important')
		expect(search.decls['border-radius']).toBe('22px !important')
		expect(search.decls.border).toBe('1px solid var(--color-border) !important')
		expect(search.decls['font-weight']).toBe('400 !important')
		expect(search.decls.color).toBe('var(--color-text-maxcontrast) !important')
	})

	it("keeps the dashboard's panel row transparent on the workspace", () => {
		const [row] = withSelector(rules, '#content.app-dashboard .panels')
		expect(row.decls.background).toBe('transparent !important')
	})
})

describe('workplace layout: the standard apps as cards on the surface', () => {
	// @spec openspec/changes/workplace-layout-standard-apps/specs/workplace-layout/spec.md
	const rules = rulesOf('css/workplace-layout.css')

	it("draws the dashboard's panels as cards with the card title size", () => {
		const [panel] = rules.filter(
			(rule) =>
				rule.selectors.join()
				=== ':where(body:not(#body-login)) #content.app-dashboard .panel',
		)
		expect(panel.decls['border-radius']).toBe(
			'var(--border-radius-container-large) !important',
		)
		expect(panel.decls.border).toBe('1px solid var(--color-border) !important')
		expect(panel.decls['box-shadow']).toContain(
			'--nldesign-component-content-card-shadow-color',
		)
		const [title] = withSelector(
			rules,
			'#content.app-dashboard .panel > .panel--header > h2',
		)
		expect(title.decls['font-size']).toBe(
			'var(--nldesign-component-heading-3-font-size) !important',
		)
	})

	it('draws the Files list as one card, its rows and README on the card, its header labels 14px muted', () => {
		const [list] = rules.filter(
			(rule) =>
				rule.selectors.join()
				=== ':where(body:not(#body-login)) #app-content-vue .files-list',
		)
		expect(list.decls.background).toBe('var(--color-main-background) !important')
		expect(list.decls.border).toBe('1px solid var(--color-border) !important')
		expect(list.decls['border-radius']).toBe(
			'var(--border-radius-container-large) !important',
		)
		const [rows] = withSelector(rules, '.files-list .files-list__thead')
		const headRows = withSelector(
			rules,
			'#app-content-vue .files-list .files-list__tfoot',
		)
		expect(headRows[0].decls['background-color']).toBe(
			'var(--color-main-background) !important',
		)
		const [labels] = withSelector(rules, '.files-list .files-list__thead th')
		expect(labels.decls['font-size']).toBe('14px !important')
		expect(labels.decls.color).toBe('var(--color-text-maxcontrast) !important')
		const [readme] = withSelector(
			rules,
			'.files-list #rich-workspace .text-editor__main',
		)
		expect(readme.decls['background-color']).toBe(
			'var(--color-main-background) !important',
		)
		expect(rows).toBeDefined()
	})

	it('draws the settings sections as cards and the theme picker select 44px high', () => {
		const [section] = withSelector(
			rules,
			'#content.app-settings #app-content-vue .section',
		)
		expect(section.decls.background).toBe(
			'var(--color-main-background) !important',
		)
		expect(section.decls.border).toBe('1px solid var(--color-border) !important')
		expect(section.decls['border-radius']).toBe(
			'var(--border-radius-container-large) !important',
		)
		const [select] = withSelector(rules, '#nldesign-token-set-select')
		expect(select.decls.height).toBe('44px !important')
	})
})
