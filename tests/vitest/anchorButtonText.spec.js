/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A link drawn as a button (NcButton with href or to, a core `a.button`,
 * `a[role="button"]`, a CnBannerWidget action, a CnLinkButtonWidget button,
 * a CnWeekStripWidget item card: DqMijnWerk "Termijnen deze week")
 * reads as a button: the link colour rules leave it to its button's own text
 * colour, and its text is never underlined, in any state.
 *
 * @spec openspec/changes/anchor-buttons-keep-button-text/specs/css-architecture/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { beforeAll, describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const sheets = [
	'css/systems/nldesign/theme.css',
	'css/systems/nldesign/element-overrides.css',
	'css/systems/high-contrast/theme.css',
].map((file) => ({ file, ast: postcss.parse(fs.readFileSync(path.join(root, file), 'utf8')) }))

const STATES = /:(hover|visited|focus-visible|focus|active|link)(?![\w-])/g

/**
 * The declarations of `prop` whose rule matches the element in some state:
 * a selector's state pseudo-classes are dropped before matching, so a
 * `:hover` or `:visited` rule counts as matching.
 *
 * @param {Element} element The element.
 * @param {string} prop The property.
 * @return {Array<{file: string, selector: string, value: string, important: boolean}>} The matches.
 */
function declarations(element, prop) {
	const out = []
	for (const { file, ast } of sheets) {
		ast.walkDecls(prop, (decl) => {
			const rule = decl.parent
			if (rule.type !== 'rule' || (rule.parent.type === 'atrule' && rule.parent.name !== 'media')) {
				return
			}
			const hit = rule.selectors.find((selector) => {
				try {
					return element.matches(selector.trim().replace(STATES, ''))
				} catch {
					return false
				}
			})
			if (hit) {
				out.push({ file, selector: hit.trim(), value: decl.value, important: decl.important })
			}
		})
	}
	return out
}

const BUTTONS = {
	'NcButton primary': '<a class="button-vue button-vue--size-normal button-vue--primary" href="#"><span class="button-vue__wrapper"><span class="button-vue__text">Zaak openen</span></span></a>',
	'NcButton secondary': '<a class="button-vue button-vue--size-normal button-vue--secondary" href="#"><span class="button-vue__wrapper"><span class="button-vue__text">Naar het werkbord</span></span></a>',
	'NcButton tertiary': '<a class="button-vue button-vue--size-normal button-vue--tertiary" href="#"><span class="button-vue__wrapper"><span class="button-vue__text">Annuleren</span></span></a>',
	'role button': '<a role="button" href="#">Opslaan</a>',
	'core button': '<a class="button primary" href="#">Openen</a>',
	'banner primary action': '<a class="cn-banner-widget__action cn-banner-widget__action--primary" href="#">Zaak openen</a>',
	'banner secondary action': '<a class="cn-banner-widget__action cn-banner-widget__action--secondary" href="#">Open deze zaken</a>',
	'link button widget': '<a class="cn-link-button-widget__button" href="#">Naar het portaal</a>',
	'week strip item': '<a class="cn-week-strip__item cn-week-strip__item--link" href="#"><strong class="cn-week-strip__title">Parkeervergunningen binnenstad</strong><span class="cn-week-strip__meta">2026-4837</span></a>',
}

beforeAll(() => {
	document.body.innerHTML = `
		<div id="app"><div id="content"><div class="app-content">
			${Object.values(BUTTONS).map((html, i) => `<div id="b${i}">${html}</div>`).join('')}
			<p><a id="prose-link" href="#">Documentatie</a></p>
		</div></div></div>`
})

const anchors = () => Object.keys(BUTTONS).map((name, i) => [name, document.querySelector(`#b${i} > a`)])

describe('a link drawn as a button reads as a button', () => {
	it('is never painted by a forced link colour rule', () => {
		for (const [name, anchor] of anchors()) {
			const forced = declarations(anchor, 'color')
				.filter((entry) => entry.important && /link|on-surface|nldesign-color-text/.test(entry.value))
			expect(forced, name).toEqual([])
		}
	})

	it('is never underlined, in any state', () => {
		for (const [name, anchor] of anchors()) {
			const decoration = declarations(anchor, 'text-decoration')
			expect(decoration.filter((entry) => entry.value !== 'none'), name).toEqual([])
			expect(decoration.some((entry) => entry.value === 'none' && entry.important), name).toBe(true)
		}
	})

	it('leaves a link in prose its link colour and underline', () => {
		const link = document.getElementById('prose-link')
		expect(declarations(link, 'color').some((entry) => entry.important && /link/.test(entry.value))).toBe(true)
		expect(declarations(link, 'text-decoration').some((entry) => entry.value !== 'none')).toBe(true)
	})
})
