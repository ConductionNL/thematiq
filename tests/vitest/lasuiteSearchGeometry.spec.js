/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The La Suite search field is a 34px field centred in the 64px Nextcloud 35
 * header (thematiq#932).
 *
 * Nextcloud 35 renders `.unified-search-input` as `position: relative`, a
 * child of `.unified-search-menu`, which core makes a centring flex row
 * (core/src/components/UnifiedSearch/UnifiedSearchInput.vue and
 * core/src/views/UnifiedSearch.vue). So core already centres the field, and
 * any top inset the theme adds moves it off centre by that much. The live run
 * measured a 15px inset on top of core's centring: the field sat 14.5px low.
 *
 * The height counts the borders unless the box is `border-box`: a 34px
 * `block-size` with 1px borders renders 36px.
 *
 * Resolved here from the stylesheets: the header height from shell-nc35.css,
 * the field's own declarations from element-overrides.css, laid out the way
 * a browser does for that DOM.
 *
 * @spec openspec/changes/lasuite-shell-geometry/specs/lasuite-stack/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const read = (file) =>
	fs.readFileSync(path.join(root, 'css/systems/lasuite', file), 'utf8')

/** La Suite's own field: 34px, borders included (Cunningham, measured). */
const FIELD_HEIGHT = 34

/**
 * The last value a stylesheet declares for a property on a selector.
 *
 * @param {string} css The stylesheet.
 * @param {string} selector One selector of the rule's list.
 * @return {Record<string, string>} Property to value, `!important` dropped.
 */
function declarations(css, selector) {
	const out = {}
	postcss.parse(css).walkRules((rule) => {
		if (rule.parent.type !== 'root') {
			return
		}
		if (!rule.selectors.map((s) => s.trim()).includes(selector)) {
			return
		}
		rule.walkDecls((d) => {
			out[d.prop] = d.value.replace(/\s*!important\s*$/, '').trim()
		})
	})
	return out
}

/**
 * A length in px: a plain `Npx`, `0`, or a calc() over px and the header
 * height variable.
 *
 * @param {string} value The declared value.
 * @param {number} header The header height in px.
 * @return {number} The length.
 */
function px(value, header) {
	const v = value
		.replace(/var\(\s*--header-height\s*(?:,[^()]*)?\)/g, `${header}px`)
		.replace(/^calc/, '')
		.replace(/px/g, '')
		.trim()
	if (!/^[\d\s.+\-*/()]+$/.test(v)) {
		throw new Error(`cannot resolve length "${value}"`)
	}
	return Function(`return (${v})`)()
}

/**
 * The top offset `position: relative` adds: `inset-block-start`, the first
 * value of `inset-block`, or `top`; `auto` (the default) is none.
 *
 * @param {Record<string, string>} decls The field's declarations.
 * @param {number} header The header height in px.
 * @return {number} The offset in px.
 */
function relativeOffset(decls, header) {
	const start =
		decls['inset-block-start']
		?? decls['inset-block']?.split(/\s+/)[0]
		?? decls.top
		?? 'auto'
	return start === 'auto' ? 0 : px(start, header)
}

/**
 * The rendered border-box height.
 *
 * @param {Record<string, string>} decls The field's declarations.
 * @param {number} header The header height in px.
 * @return {number} The height in px.
 */
function outerHeight(decls, header) {
	const size = px(decls['block-size'] ?? decls.height, header)
	if (decls['box-sizing'] === 'border-box') {
		return size
	}
	const border = decls.border?.match(/(\d+(?:\.\d+)?)px/)
	return size + 2 * (border ? Number(border[1]) : 0)
}

const HEADER = px(declarations(read('shell-nc35.css'), 'body')['--header-height'], 0)
const FIELD = declarations(
	read('element-overrides.css'),
	'#header .unified-search-input',
)

describe('the La Suite search field on the Nextcloud 35 header', () => {
	it('reads a 64px header from the shell layer', () => {
		expect(HEADER).toBe(64)
	})

	it(`renders ${FIELD_HEIGHT}px tall, borders included`, () => {
		expect(outerHeight(FIELD, HEADER)).toBe(FIELD_HEIGHT)
	})

	it('sits centred in the header, within 1px', () => {
		const height = outerHeight(FIELD, HEADER)
		// Core's flex row puts the field's top at the centred position; the
		// relative offset moves it from there.
		const top = (HEADER - height) / 2 + relativeOffset(FIELD, HEADER)
		const centre = top + height / 2
		expect(Math.abs(centre - HEADER / 2)).toBeLessThanOrEqual(1)
	})

	it('lets the inner field fill the box instead of overflowing the border', () => {
		const inner = declarations(
			read('element-overrides.css'),
			'#header .unified-search-input__field',
		)
		expect(inner['block-size'] ?? inner.height).toBe('100%')
	})
})
