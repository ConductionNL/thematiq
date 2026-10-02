/**
 * js/lib/multiBrandSource.js answers exactly as lib/Service/MultiBrandSource.php for the
 * three fixtures: tests/Unit/fixtures/multi-brand/expected.json is what PHP returns
 * (checked by TokenSetConverterMultiBrandTest::testParityFixtureMatchesPhp).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/authoring-multi-brand-token-source/tasks.md#task-2.4
 */

import { describe, expect, it } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'
import multiBrand from '../../js/lib/multiBrandSource.js'

const DIR = path.resolve(__dirname, '../Unit/fixtures/multi-brand')
const expected = JSON.parse(fs.readFileSync(path.join(DIR, 'expected.json'), 'utf8'))

describe('multiBrandSource parity with PHP', () => {
	it.each(Object.keys(expected))('%s', (file) => {
		const content = fs.readFileSync(path.join(DIR, file), 'utf8')
		const brands = multiBrand.detectBrands(content)
		const cuts = {}
		brands.forEach((brand) => {
			cuts[brand.key] = multiBrand.cut(content, brand.key)
		})

		expect({ brands, cuts }).toEqual(expected[file])
	})

	it('refuses a brand the source does not have', () => {
		expect(() =>
			multiBrand.cut(
				fs.readFileSync(path.join(DIR, 'two-brand-classes.css'), 'utf8'),
				'west',
			),
		).toThrow('unknown brand: west')
	})
})
