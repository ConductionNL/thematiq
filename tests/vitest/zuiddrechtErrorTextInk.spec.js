/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Error text on the page is a set's error ink, not its error fill. The boards
 * draw late deadlines ("1 dag te laat", DqDagAfsluiten) in Zuiddrecht's
 * #A30000, while the error fill behind buttons and badges stays #CC0000.
 * A set without an error ink keeps reading its error colour.
 *
 * @spec openspec/specs/error-text-ink/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (file) => fs.readFileSync(path.join(ROOT, file), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')

describe('the error text ink', () => {
	it('is what --color-text-error reads, the error colour without one', () => {
		expect(read('css/systems/nldesign/overrides.css')).toMatch(/--color-text-error:\s*var\(--nldesign-color-error-text,\s*var\(--nldesign-color-error\)\)\s*!important;/)
	})

	it('is #A30000 in the Zuiddrecht set, beside its #CC0000 fill', () => {
		const light = read('css/tokens/zuiddrecht.css')
		expect(light).toMatch(/--nldesign-color-error-text:\s*#A30000;/i)
		expect(light).toMatch(/--nldesign-color-error:\s*#CC0000;/i)
	})

	it('has a generated dark value, so the light ink stays out of dark mode', () => {
		const dark = read('css/tokens/dark/zuiddrecht.css')
		expect(dark).toMatch(/--nldesign-color-error-text:\s*#[0-9a-f]{6};/i)
		expect(dark).not.toMatch(/--nldesign-color-error-text:\s*#A30000;/i)
	})
})
