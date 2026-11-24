/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The token sync fills a converted set with the role layer's component
 * tokens, re-pointed at the colours its upstream publishes and nothing else
 * (openspec/changes/archive/2026-11-22-token-set-coverage-next-waves, task 3.2).
 */
import { describe, expect, it } from 'vitest'
import {
	halfPairs,
	partialRoleFill,
	rampFromReport,
	withPartialRoleFill,
	FILL_HEADING,
} from '../../scripts/lib/partial-role-fill.mjs'

const role = new Map([
	['--vng-color-white', '#ffffff'],
	['--vng-color-blue-500', '#1a4f97'],
	['--vng-color-gray-100', '#f3f3f3'],
	['--utrecht-button-background-color', 'var(--vng-color-blue-500)'],
	['--utrecht-button-color', '#ffffff'],
	['--utrecht-page-background-color', 'var(--vng-color-gray-100)'],
	['--utrecht-card-color', 'var(--vng-color-blue-500)'],
	['--utrecht-card-background-color', 'var(--vng-color-gray-100)'],
	['--utrecht-button-border-radius', '4px'],
	['--utrecht-document-font-family', 'Avenir, sans-serif'],
	['--denhaag-case-card-title-color', 'var(--vng-color-blue-500)'],
	['--utrecht-link-color', 'var(--vng-color-blue-500)'],
])

const report = [
	{ action: 'applied', target: '--nldesign-color-primary', value: '#008937' },
	{
		action: 'adapted',
		target: '--nldesign-color-error',
		value: '#d70d0d',
		reason: 'nextcloud-default-used',
	},
	{
		action: 'applied',
		target: 'manifest:theming.background_color',
		value: '#ffffff',
	},
]

describe('rampFromReport', () => {
	it('re-points a step only for a role the converter applied from upstream', () => {
		const ramp = rampFromReport(report)
		expect(ramp['blue-500']).toEqual({
			value: '#008937',
			from: '--nldesign-color-primary',
		})
		expect(ramp.white.value).toBe('#ffffff')
		expect(ramp['red-300']).toBeUndefined()
	})
})

describe('partialRoleFill', () => {
	const ramp = rampFromReport(report)

	it('fills the role layer in the upstream colours, and its geometry', () => {
		const fill = new Map(partialRoleFill(new Map(), ramp, role))
		expect(fill.get('--vng-color-blue-500')).toBe('#008937')
		expect(fill.get('--utrecht-button-background-color')).toBe(
			'var(--vng-color-blue-500)',
		)
		expect(fill.get('--utrecht-button-color')).toBe('#ffffff')
		expect(fill.get('--utrecht-button-border-radius')).toBe('4px')
	})

	it('leaves out an unpublished step, what reads it, the role typeface and the Den Haag tokens', () => {
		const fill = new Map(partialRoleFill(new Map(), ramp, role))
		expect(fill.has('--vng-color-gray-100')).toBe(false)
		expect(fill.has('--utrecht-page-background-color')).toBe(false)
		expect(fill.has('--utrecht-document-font-family')).toBe(false)
		expect(fill.has('--denhaag-case-card-title-color')).toBe(false)
	})

	it('leaves out a foreground whose background stays with the bridge', () => {
		const fill = new Map(partialRoleFill(new Map(), ramp, role))
		expect(fill.has('--utrecht-card-color')).toBe(false)
		expect(fill.has('--utrecht-link-color')).toBe(true)
	})

	it('never overrides what the set declares itself', () => {
		const fill = new Map(
			partialRoleFill(
				new Map([['--utrecht-button-color', '#000000']]),
				ramp,
				role,
			),
		)
		expect(fill.has('--utrecht-button-color')).toBe(false)
	})
})

describe('halfPairs', () => {
	it('names a filled foreground over a background that is not filled', () => {
		const left = new Set(['--utrecht-card-background-color'])
		expect(halfPairs(role, new Map(), left)).toContain('--utrecht-card-color')
	})
})

describe('withPartialRoleFill', () => {
	const css =
		':root {\n\t--nldesign-color-primary: #008937;\n\n\t/* 4. Provenance\n\t */\n}\n'

	it('writes the fill before the provenance, inside the one :root block', () => {
		const { css: out, count } = withPartialRoleFill(css, report, role)
		expect(count).toBeGreaterThan(0)
		expect(out.indexOf(FILL_HEADING)).toBeLessThan(
			out.indexOf('/* 4. Provenance'),
		)
		expect(out.match(/\{/g)).toHaveLength(1)
	})

	it('replaces a fill it wrote before instead of doubling it', () => {
		const once = withPartialRoleFill(css, report, role).css
		expect(withPartialRoleFill(once, report, role).css).toBe(once)
	})
})
