/**
 * The browser mirror of TokenValueValidator: the same cases as
 * tests/Unit/Service/TokenValueValidatorTest.php, from the same fixture, and splitAlpha().
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/token-editor-ui/spec.md#requirement-the-server-checks-each-value-against-its-token-type
 */

import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
import { resolve } from 'node:path'
import { describe, expect, it } from 'vitest'

const require = createRequire(resolve('tests/vitest/tokenValueGrammar.spec.js'))
const transforms = require('../../js/lib/tokenTransforms.js')
const cases = JSON.parse(
	readFileSync(resolve('tests/Unit/fixtures/token-value-grammar.json'), 'utf8'),
)

describe('isValidTokenValue', () => {
	it.each(cases)('%s %s is %s', (type, value, accepted) => {
		expect(transforms.isValidTokenValue(type, value)).toBe(accepted)
	})
})

describe('splitAlpha', () => {
	it('splits an 8-digit hex into the picker colour and a percentage', () => {
		expect(transforms.splitAlpha('#15427380')).toEqual({
			hex: '#154273',
			alpha: 50,
		})
	})

	it('reads an opaque 6-digit hex as 100', () => {
		expect(transforms.splitAlpha('#154273')).toEqual({
			hex: '#154273',
			alpha: 100,
		})
	})

	it('reads short hex and rgba()', () => {
		expect(transforms.splitAlpha('#fff8')).toEqual({ hex: '#ffffff', alpha: 53 })
		expect(transforms.splitAlpha('rgba(21, 66, 115, 0.25)')).toEqual({
			hex: '#154273',
			alpha: 25,
		})
	})

	it('returns null for what it cannot read', () => {
		expect(transforms.splitAlpha('var(--x)')).toBeNull()
	})

	it('joins a colour and a percentage back', () => {
		expect(transforms.joinAlpha('#154273', 50)).toBe('#15427380')
		expect(transforms.joinAlpha('#154273', 100)).toBe('#154273')
	})
})
