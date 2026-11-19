/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A theme that makes placeholders opaque must leave Nextcloud's own hiding
 * alone: a text field or text area with its label inside the field shows its
 * placeholder only on focus. A blanket `::placeholder { opacity: 1 !important }`
 * drew the label over the placeholder, and every layer above it (this app's
 * counter-rule, nextcloud-vue's board look) had to answer with
 * `opacity: 0 !important`. Reads the shipped CSS and asks a real DOM which
 * fields the opaque rule reaches.
 *
 * @spec openspec/specs/floating-label-hides-placeholder/spec.md#requirement-a-resting-floating-label-hides-the-placeholder
 */

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'
import { JSDOM } from 'jsdom'

const ROOT = path.resolve(__dirname, '..', '..')

/**
 * Every top-level `selector { body }` pair of a stylesheet, in source order.
 *
 * @param {string} css The stylesheet.
 * @return {Array<{selector: string, body: string}>} The rules.
 */
function rulesOf(css) {
	const plain = css.replace(/\/\*[\s\S]*?\*\//g, '')
	return [...plain.matchAll(/([^{}]+)\{([^{}]*)\}/g)].map((m) => ({
		// Prettier wraps a long selector over several lines; compare it as one.
		selector: m[1].trim().replace(/\s+/g, ' '),
		body: m[2],
	}))
}

/**
 * A selector list split at its top-level commas only.
 *
 * @param {string} list The selector list.
 * @return {string[]} The selectors.
 */
function splitList(list) {
	const out = ['']
	let depth = 0
	for (const ch of list) {
		depth += ch === '(' ? 1 : ch === ')' ? -1 : 0
		if (ch === ',' && depth === 0) {
			out.push('')
		} else {
			out[out.length - 1] += ch
		}
	}
	return out.map((s) => s.trim())
}

const doc = new JSDOM(`<body>
	<div class="input-field"><input id="inside" class="input-field__input"></div>
	<div class="input-field input-field--label-outside"><input id="outside" class="input-field__input"></div>
	<textarea id="area-inside" class="textarea__input"></textarea>
	<textarea id="area-outside" class="textarea__input textarea__input--label-outside"></textarea>
	<input id="plain">
</body>`).window.document

describe.each([
	'css/systems/nldesign/theme.css',
	'css/systems/high-contrast/element-overrides.css',
])('%s', (file) => {
	const rules = rulesOf(fs.readFileSync(path.join(ROOT, file), 'utf8'))
	const opaque = rules.filter(
		(r) =>
			r.selector.endsWith('::placeholder')
			&& /opacity:\s*1\s*!important/.test(r.body),
	)

	/**
	 * Whether the opaque placeholder rule reaches the field.
	 *
	 * @param {string} id The field id.
	 * @return {boolean} Reached.
	 */
	function opaqueOn(id) {
		const el = doc.getElementById(id)
		return opaque.some((r) =>
			splitList(r.selector)
				.map((s) => s.replace(/::placeholder$/, '') || '*')
				.some((s) => el.matches(s)),
		)
	}

	it('makes placeholders opaque', () => {
		expect(opaque.length).toBeGreaterThan(0)
		expect(opaqueOn('plain')).toBe(true)
		expect(opaqueOn('outside')).toBe(true)
		expect(opaqueOn('area-outside')).toBe(true)
	})

	it('leaves the placeholder of a field with its label inside hidden at rest', () => {
		expect(opaqueOn('inside')).toBe(false)
		expect(opaqueOn('area-inside')).toBe(false)
	})

	it('shows that placeholder opaque once the field has focus', () => {
		for (const id of ['inside', 'area-inside']) {
			doc.getElementById(id).focus()
			expect(opaqueOn(id), id).toBe(true)
			doc.getElementById(id).blur()
		}
	})

	it('needs no counter-rule that hides a placeholder with !important', () => {
		const hidden = rules.filter(
			(r) =>
				r.selector.endsWith('::placeholder')
				&& /opacity:\s*0\s*!important/.test(r.body),
		)
		expect(hidden).toEqual([])
	})
})
