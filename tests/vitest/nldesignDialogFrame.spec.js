/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Under nldesign a dialog keeps the frame the boards draw: a corner half again
 * the brand radius (12px on an 8px set) and a lifting shadow, both open to a
 * set's own dialog tokens.
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')

/** The sheet without comments. */
function sheet(file) {
	return fs.readFileSync(path.join(ROOT, file), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')
}

/** The declaration blocks whose selector list holds `selector`. */
function blocksWith(css, selector) {
	const out = []
	const re = /([^{}]+)\{([^{}]*)\}/g
	let m
	while ((m = re.exec(css)) !== null) {
		if (m[1].split(',').map((s) => s.trim()).includes(selector)) {
			out.push(m[2])
		}
	}
	return out
}

describe('the nldesign dialog frame', () => {
	for (const file of ['css/systems/nldesign/theme.css', 'css/systems/nldesign/element-overrides.css']) {
		it(`${file} gives the dialog half again the brand radius`, () => {
			const radius = blocksWith(sheet(file), '.modal-container')
				.filter((b) => b.includes('border-radius'))
				.join('\n')
			expect(radius).toMatch(
				/--nldesign-component-dialog-border-radius,\s*calc\(var\(--nldesign-border-radius\) \* 1\.5\)/,
			)
		})
	}

	it('keeps the dialog shadow, which a set can change with its dialog token', () => {
		const blocks = blocksWith(sheet('css/systems/nldesign/theme.css'), '.modal-container')
		expect(blocks.some((b) => /box-shadow:\s*none/.test(b))).toBe(false)
		expect(blocks.join('\n')).toMatch(
			/box-shadow:\s*var\(\s*--nldesign-component-dialog-box-shadow,\s*0 16px 48px rgba\(27, 28, 29, 0\.28\)\s*\)\s*!important/,
		)
	})
})
