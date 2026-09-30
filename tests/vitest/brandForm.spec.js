/**
 * The browser half of the simple brand form derives exactly what the server stores:
 * every case in tests/Unit/fixtures/brand-form-parity.json (written from PHP, and checked
 * against PHP by BrandFormServiceTest::testParityFixtureMatchesPhp) must come out the same here.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/simple-brand-form/spec.md#requirement-the-derived-set-is-complete
 */

import { createRequire } from 'node:module'
import { readFileSync } from 'node:fs'
import { describe, expect, it } from 'vitest'

const require = createRequire(import.meta.url)
const fixture = JSON.parse(
	readFileSync(new URL('../Unit/fixtures/brand-form-parity.json', import.meta.url), 'utf8'),
)

describe('brandForm.derive', () => {
	it('matches the PHP derivation for every fixture case', () => {
		const brandForm = require('../../js/lib/brandForm.js')

		expect(fixture.cases.length).toBeGreaterThan(2)
		fixture.cases.forEach((c) => {
			expect(brandForm.derive(fixture.inputs, c.primary, c.background)).toEqual(c.expected)
		})
	})

	it('keeps the token order of the rules file', () => {
		const brandForm = require('../../js/lib/brandForm.js')
		const result = brandForm.derive(fixture.inputs, '#c8102e', '#ffffff')

		expect(Object.keys(result.declarations)).toEqual(Object.keys(fixture.inputs.rules))
	})

	it('refuses a colour that is not hex', () => {
		const brandForm = require('../../js/lib/brandForm.js')

		expect(brandForm.derive(fixture.inputs, 'red', '#ffffff')).toBeNull()
	})
})
