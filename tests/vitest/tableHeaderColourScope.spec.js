/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The table header colour paints the header cells only. Redirecting
 * `--color-text-maxcontrast` across the whole table gave every muted line in
 * every table cell the header's colour, in every set that sets the token
 * (and the body ink where the defaults set it).
 *
 * @spec openspec/specs/table-header-colour-scope/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const css = fs.readFileSync(path.join(ROOT, 'css/component-scopes.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')

/**
 * The bodies of every rule whose selector list includes exactly this selector.
 *
 * @param {string} selector The selector.
 * @return {string[]} The bodies.
 */
const bodiesOf = (selector) => {
	const out = []
	const re = /([^{}]+)\{([^{}]*)\}/g
	let m
	while ((m = re.exec(css))) {
		if (m[1].split(',').map((s) => s.trim()).includes(selector)) {
			out.push(m[2])
		}
	}
	return out
}

describe('the table header colour scope', () => {
	it('no longer redirects the muted text colour across the whole table', () => {
		for (const body of bodiesOf('table')) {
			expect(body).not.toMatch(/--color-text-maxcontrast\s*:/)
		}
	})

	it('paints the header cells from the token, inheriting without one', () => {
		expect(bodiesOf('th').join('\n')).toMatch(/color:\s*var\(--nldesign-component-table-header-color,\s*inherit\)/)
	})
})
