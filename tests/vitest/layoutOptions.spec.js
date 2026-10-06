/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The newer layout options: the navigation width, the selected entry's soft
 * style, the brand stripe's placement and the login watermark switch
 * (openspec/changes/layout-options-navigation-stripe-watermark). Each is one
 * conditional stylesheet, off unless asked for; the tests below read the
 * files and hold each to what it may and may not do.
 *
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/workplace-layout/spec.md
 * @spec openspec/changes/layout-options-navigation-stripe-watermark/specs/brand-stripe/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

/**
 * Every rule of a stylesheet: its selector and its declarations.
 *
 * @param {string} rel The path under the repository root.
 * @return {Array<{selector: string, decls: Record<string, {value: string, important: boolean}>}>} The rules.
 */
function rules(rel) {
	const found = []
	postcss.parse(read(rel)).walkRules((rule) => {
		const decls = {}
		rule.walkDecls((decl) => {
			decls[decl.prop] = {
				value: decl.value.replace(/\s+/g, ' ').trim(),
				important: decl.important === true,
			}
		})
		found.push({ selector: rule.selector.replace(/\s+/g, ' ').trim(), decls })
	})
	return found
}

/**
 * The selectors of a rule, one per line, trimmed.
 *
 * @param {string} selector The rule's selector list.
 * @return {string[]} The selectors.
 */
const split = (selector) => selector.split(',').map((s) => s.trim())

describe('navigation width', () => {
	const sheet = rules('css/navigation-width.css')

	it('carries no width of its own: every value reads the inline variable', () => {
		for (const rule of sheet) {
			for (const [prop, { value }] of Object.entries(rule.decls)) {
				expect(value, `${rule.selector} ${prop}`).toContain(
					'var(--thematiq-navigation-width)',
				)
				expect(value, `${rule.selector} ${prop}`).not.toMatch(/\d+px/)
			}
		}
	})

	it("hands the width to Nextcloud's variable, the thematiq global and the set token", () => {
		const root = sheet.find((rule) => rule.selector === ':root')
		expect(Object.keys(root.decls).sort()).toEqual([
			'--navigation-width',
			'--nldesign-nc-navigation-width',
			'--thematiq-global-navigation-width',
		])
	})

	it('says the width outright on the panel, with weight, as the component rule does', () => {
		const panel = sheet.find((rule) =>
			split(rule.selector).includes('#app-navigation-vue'),
		)
		expect(split(panel.selector)).toEqual([
			'#app-navigation-vue',
			'.app-navigation',
		])
		for (const prop of ['width', 'min-width', 'flex']) {
			expect(panel.decls[prop].important, prop).toBe(true)
		}
	})
})

describe('the selected navigation entry: soft tint with a bold label', () => {
	const sheet = rules('css/navigation-active-soft.css')

	it('tints the entry with the accent, else the primary tint, else Nextcloud', () => {
		const entry = sheet.find((rule) => 'background-color' in rule.decls)
		expect(entry.decls['background-color'].value).toBe(
			'var( --nldesign-color-accent-light, var(--nldesign-color-primary-light, var(--color-primary-element-light)) )',
		)
		expect(entry.decls['background-color'].important).toBe(true)
		for (const selector of split(entry.selector)) {
			expect(selector).toMatch(
				/\.app-navigation-entry:not\(\.app-navigation-entry--legacy\)\.active$/,
			)
		}
	})

	it('labels it in the accent text, else the active-entry token, else the primary, in 600', () => {
		const link = sheet.find((rule) => 'font-weight' in rule.decls)
		expect(link.decls['font-weight'].value).toBe('600')
		expect(link.decls.color.value).toBe(
			'var( --nldesign-color-accent-text, var( --nldesign-component-navigation-active-color, var(--nldesign-color-primary, var(--color-primary-element)) ) )',
		)
		for (const selector of split(link.selector)) {
			expect(selector).toMatch(
				/\.app-navigation-entry\.active \.app-navigation-entry-link$/,
			)
		}
	})

	it('writes no colour literal: the dark variant derives every colour from the tokens', () => {
		const withoutComments = read('css/navigation-active-soft.css').replace(
			/\/\*[\s\S]*?\*\//g,
			'',
		)
		expect(withoutComments).not.toMatch(/:\s*#[0-9a-f]{3,8}\b/i)
	})

	it('reaches AA for Zuiddrecht: the accent text on the accent tint, 7.22:1 by the set file', () => {
		const set = read('css/tokens/zuiddrecht.css')
		expect(set).toMatch(/--nldesign-color-accent-light: #FCEDEC/)
		expect(set).toMatch(/--nldesign-color-accent-text: #A30000/)
		expect(set).toMatch(/#A30000 on #FCEDEC 7\.22/)
	})
})

describe('the brand stripe placement', () => {
	const stripe = rules('css/brand-stripe.css')
	const shared = stripe.find((rule) => 'background-image' in rule.decls)
	const headerSelector = split(shared.selector).find((s) => s.includes('#header'))
	const loginSelector = split(shared.selector).find((s) =>
		s.includes('#body-login'),
	)

	it('the top bar only: takes the login card copy off, on the exact selector the shared rule uses', () => {
		const sheet = rules('css/brand-stripe-header-only.css')
		expect(sheet).toHaveLength(1)
		expect(sheet[0].selector).toBe(loginSelector)
		expect(sheet[0].decls.content).toEqual({ value: 'none', important: true })
		expect(sheet[0].decls.display).toEqual({ value: 'none', important: true })
	})

	it('the login card only: takes the top bar copy off, on the exact selector the shared rule uses', () => {
		const sheet = rules('css/brand-stripe-login-only.css')
		expect(sheet).toHaveLength(1)
		expect(sheet[0].selector).toBe(headerSelector)
		expect(sheet[0].decls.content).toEqual({ value: 'none', important: true })
		expect(sheet[0].decls.display).toEqual({ value: 'none', important: true })
	})

	it('the shared stripe rule is as it was: both places, one rule', () => {
		expect(split(shared.selector)).toHaveLength(2)
		expect(shared.decls.content).toEqual({ value: "''", important: true })
	})
})

describe('the login watermark switch', () => {
	it('off: takes the mark off the pseudo-element the layout sheet draws it on', () => {
		const layout = rules('css/workplace-layout.css')
		const mark = layout.find((rule) =>
			rule.selector.startsWith('body#body-login::before'),
		)
		const sheet = rules('css/login-watermark-off.css')
		expect(sheet).toHaveLength(1)
		expect(sheet[0].selector).toBe(mark.selector)
		expect(sheet[0].decls.content).toEqual({ value: 'none', important: true })
		expect(sheet[0].decls['background-image']).toEqual({
			value: 'none',
			important: true,
		})
	})
})

describe('the newer layout stylesheets are off unless asked for', () => {
	it('no design system lists them, so only the option loads them', () => {
		const systems = JSON.parse(read('design-systems.json'))
		const listed = systems.flatMap((system) =>
			(system.stylesheets || []).filter((sheet) =>
				/navigation-width|navigation-active-soft|brand-stripe-(header|login)-only|login-watermark-off/.test(
					sheet,
				),
			),
		)
		expect(listed).toEqual([])
	})

	it('only Zuiddrecht names a navigation width and the soft entry among the shipped sets', () => {
		const sets = JSON.parse(read('token-sets.json'))
		const naming = sets
			.filter(
				(entry) =>
					entry.layout !== undefined
					&& (entry.layout.navigation_width !== undefined
						|| entry.layout.navigation_active_style !== undefined
						|| entry.layout.brand_stripe_placement !== undefined
						|| entry.layout.login_watermark !== undefined),
			)
			.map((entry) => [entry.id, entry.layout])
		expect(naming).toEqual([
			[
				'zuiddrecht',
				{
					workplace_layout: 'light',
					brand_stripe: true,
					navigation_width: 264,
					navigation_active_style: 'soft',
				},
			],
		])
	})
})
