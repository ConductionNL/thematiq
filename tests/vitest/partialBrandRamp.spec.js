/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A partial brand file names only the colours the organisation publishes. A
 * ramp step it leaves out is not declared, and no role-layer declaration
 * carries a VNG or Tilburg colour in its place: those are left to Nextcloud
 * (openspec/changes/token-set-coverage-next-waves, task 1.2).
 */
import { describe, expect, it } from 'vitest'
import { namesLeftToNextcloud } from '../../scripts/generate-brand-set.mjs'

const role = new Map([
	['--vng-color-blue-500', '#1a4f97'],
	['--vng-color-gray-100', '#f3f3f3'],
	['--utrecht-button-primary-action-background-color', 'var(--vng-color-blue-500)'],
	['--utrecht-page-background-color', 'var(--vng-color-gray-100)'],
	['--utrecht-link-color', '#1a4f97'],
	['--utrecht-separator-color', '#cccccc'],
	['--utrecht-focus-outline-color', 'rgba(0, 0, 0, 0.1)'],
	['--utrecht-button-primary-action-hover-background-color', 'var(--utrecht-page-background-color)'],
	['--utrecht-button-border-radius', '4px'],
])
const rampByName = new Map([['--vng-color-blue-500', '#004699']])
const rampByValue = new Map([['#1a4f97', '#004699']])

describe('namesLeftToNextcloud', () => {
	const left = namesLeftToNextcloud(role, rampByName, rampByValue)

	it('leaves out a ramp step the brand does not publish', () => {
		expect(left.has('--vng-color-gray-100')).toBe(true)
		expect(left.has('--vng-color-blue-500')).toBe(false)
	})

	it('leaves out a token that reads a missing step, directly or through another token', () => {
		expect(left.has('--utrecht-page-background-color')).toBe(true)
		expect(left.has('--utrecht-button-primary-action-hover-background-color')).toBe(true)
	})

	it('leaves out a role-layer colour literal the ramp does not replace, and any rgb() literal', () => {
		expect(left.has('--utrecht-separator-color')).toBe(true)
		expect(left.has('--utrecht-focus-outline-color')).toBe(true)
	})

	it('keeps tokens the brand colours fully, and tokens that carry no colour', () => {
		expect(left.has('--utrecht-button-primary-action-background-color')).toBe(false)
		expect(left.has('--utrecht-link-color')).toBe(false)
		expect(left.has('--utrecht-button-border-radius')).toBe(false)
	})
})
