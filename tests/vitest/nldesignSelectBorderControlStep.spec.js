/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A native select's edge defaults to the control border, the same step a text
 * box takes, not the card hairline.
 *
 * @spec openspec/specs/nldesign-select-border-control-step/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')

/** The value a stylesheet assigns to `name`, whitespace collapsed. */
function valueOf(css, name) {
	const m = css.match(new RegExp(`${name}:\\s*([^;]+);`))
	return m ? m[1].replace(/\s+/g, ' ').trim() : null
}

const SELECT = '--nldesign-component-form-select-border-color'

describe('the nldesign select border', () => {
	it('defaults to the control border', () => {
		expect(valueOf(read('css/systems/nldesign/defaults.css'), SELECT)).toBe('var(--nldesign-color-border-dark)')
	})

	it('falls back to the control border in the Utrecht bridge', () => {
		expect(valueOf(read('css/systems/nldesign/utrecht-bridge.css'), SELECT)).toBe(
			'var( --utrecht-select-border-color, var(--utrecht-form-input-border-color, var(--nldesign-color-border-dark)) )',
		)
	})

	it('matches the text box default', () => {
		expect(valueOf(read('css/systems/nldesign/defaults.css'), '--nldesign-component-textbox-border-color')).toBe(
			'var(--nldesign-color-border-dark)',
		)
	})
})
