/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The e2e retry policy (#181): strict in CI, one retry on a developer box.
 */
import { describe, expect, it } from 'vitest'

import { retriesFor } from '../e2e/instance-load'

describe('retriesFor', () => {
	it('never retries in CI, whatever PW_RETRIES says', () => {
		expect(retriesFor({ CI: 'true', PW_RETRIES: '3' })).toBe(0)
		expect(retriesFor({ GITHUB_ACTIONS: 'true' })).toBe(0)
	})

	it('retries once on a developer box by default', () => {
		expect(retriesFor({})).toBe(1)
	})

	it('takes PW_RETRIES off CI, and ignores a value that is not a count', () => {
		expect(retriesFor({ PW_RETRIES: '0' })).toBe(0)
		expect(retriesFor({ PW_RETRIES: '2' })).toBe(2)
		expect(retriesFor({ PW_RETRIES: 'many' })).toBe(1)
		expect(retriesFor({ PW_RETRIES: '-1' })).toBe(1)
	})
})
