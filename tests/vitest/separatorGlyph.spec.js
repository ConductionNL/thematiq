/**
 * Fira Sans has no glyph for U+2E31 WORD SEPARATOR MIDDLE DOT, which Deck
 * writes in its card panel ("Modified: ... ⸱ Created: ..."). Every Fira face
 * has a sibling face with the same weight and style that holds only that
 * character, so the dot is drawn instead of an empty box.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/css-architecture/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')

const facesOf = (rel) => {
	const faces = []
	postcss
		.parse(fs.readFileSync(path.join(ROOT, rel), 'utf8'))
		.walkAtRules('font-face', (rule) => {
			const face = {}
			rule.walkDecls((decl) => {
				face[decl.prop] = decl.value
			})
			if (/Fira Sans/.test(face['font-family'])) {
				faces.push(face)
			}
		})
	return faces
}

describe.each([
	['css/systems/nldesign/fonts.css', 'css/systems/nldesign/fonts'],
	['css/fonts.css', 'css/fonts'],
])('%s', (sheet, dir) => {
	const faces = facesOf(sheet)
	const main = faces.filter((face) => face['unicode-range'] === undefined)
	const separator = faces.filter((face) => face['unicode-range'] === 'U+2E31')

	it('gives every Fira face a separator face with the same weight and style', () => {
		expect(main.length).toBeGreaterThan(0)
		expect(
			separator.map((face) => `${face['font-weight']} ${face['font-style']}`),
		).toEqual(main.map((face) => `${face['font-weight']} ${face['font-style']}`))
	})

	it('loads the separator from a file that ships beside the sheet', () => {
		for (const face of separator) {
			expect(face.src).toBe("url('fonts/fira-sans-separator.otf') format('opentype')")
		}
		expect(fs.existsSync(path.join(ROOT, dir, 'fira-sans-separator.otf'))).toBe(
			true,
		)
	})
})
