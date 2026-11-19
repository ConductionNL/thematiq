/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A partial brand's semantic layer is completed only with the organisation's
 * own values or Nextcloud's, never with a shade nobody chose
 * (openspec/changes/token-set-coverage-next-waves, task 1.2).
 */
import { describe, expect, it } from 'vitest'
import { completeSemanticLayer, keptValue, toHex } from '../../scripts/lib/partial-semantic.mjs'

const published = new Set(['#24578f', '#666666', '#ffffff'])

describe('toHex', () => {
	it('reads hex and the space-separated hsl() the packages publish', () => {
		expect(toHex('#FFF')).toBe('#ffffff')
		expect(toHex('hsl(211 60% 35%)')).toBe('#24578f')
		expect(toHex('transparent')).toBeNull()
	})
})

describe('keptValue', () => {
	it('keeps a value the set assigns to the role', () => {
		expect(keptValue({ action: 'applied', target: '--nldesign-color-nav-background', value: '#f2f2f2' }, undefined, published, new Map())).toBe('#f2f2f2')
	})

	it('keeps Nextcloud defaults and Nextcloud-style derivations from a published colour', () => {
		expect(keptValue({ action: 'adapted', reason: 'nextcloud-default-used', value: '#a65600' }, undefined, published, new Map())).toBe('#a65600')
		expect(keptValue({ action: 'adapted', reason: 'derived-from-brand', source: '--nldesign-color-primary', value: '#e9eef4' }, undefined, published, new Map())).toBe('#e9eef4')
	})

	it('keeps a palette pick only when the organisation publishes it, else the rule default', () => {
		const rule = { fallback: { kind: 'ramp', default: '#696969' } }
		expect(keptValue({ action: 'adapted', reason: 'derived-from-brand', source: '(brand ramp)', value: '#666666' }, rule, published, new Map())).toBe('#666666')
		expect(keptValue({ action: 'adapted', reason: 'derived-from-brand', source: '(brand ramp)', value: '#7a7a7a' }, rule, published, new Map())).toBe('#696969')
	})

	it('refuses a published pick that is far lighter or darker than the role, keeping Nextcloud\'s default', () => {
		const rule = { fallback: { kind: 'ramp', default: '#e0e1e2' } }
		const small = new Set(['#2b2b2b', '#f3f5f9'])
		expect(keptValue({ action: 'adapted', reason: 'derived-from-brand', source: '(brand ramp)', value: '#2b2b2b' }, rule, small, new Map())).toBe('#e0e1e2')
		expect(keptValue({ action: 'adapted', reason: 'derived-from-brand', source: '(brand ramp)', value: '#f3f5f9' }, rule, small, new Map())).toBe('#f3f5f9')
	})

	it('replaces a contrast-adjusted shade with the rule fallback', () => {
		const rule = { fallback: { kind: 'derive', from: '--nldesign-color-header-background', transform: 'onColor', args: { light: '#ffffff', dark: '#000000' } } }
		const chosen = new Map([['--nldesign-color-header-background', '#24578F']])
		expect(keptValue({ action: 'adapted', reason: 'contrast-adjusted', value: '#0d1e32' }, rule, published, chosen)).toBe('#ffffff')
	})

	it('answers a colour role given no colour with the rule default', () => {
		const rule = { fallback: { kind: 'ramp', default: '#e0e1e2' } }
		expect(keptValue({ action: 'applied', target: '--nldesign-color-background-dark', value: 'transparent' }, rule, published, new Map())).toBe('#e0e1e2')
	})
})

describe('completeSemanticLayer', () => {
	it('adds only tokens the brand does not name', () => {
		const added = completeSemanticLayer({
			report: [
				{ action: 'applied', target: '--nldesign-color-primary', value: '#000000' },
				{ action: 'adapted', reason: 'nextcloud-default-used', target: '--nldesign-color-warning', value: '#a65600' },
				{ action: 'kept', target: '--utrecht-x', value: '1' },
			],
			rules: [],
			declared: new Set(['--nldesign-color-primary']),
			declaredValues: new Map([['--nldesign-color-primary', '#24578f']]),
			published,
		})
		expect(added).toEqual([{ name: '--nldesign-color-warning', value: '#a65600' }])
	})
})
