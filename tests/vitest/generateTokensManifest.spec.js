/**
 * The nightly token sync rewrites token-sets.json. It must keep every entry it
 * did not regenerate (the stock nextcloud set, Conduction's own sets, the
 * school examples) and every field it does not own (design_system, theming,
 * logos), or the dark-variant generator loses the backgrounds it reads from
 * the manifest. The first sync against development (#982) dropped both.
 */
import { describe, expect, it } from 'vitest'
import { mergeManifest } from '../../scripts/generate-tokens.mjs'

const existing = [
	{
		id: 'nextcloud',
		name: 'Nextcloud (Base)',
		description: 'Stock',
		design_system: 'none',
		theming: { background_color: '#00679e' },
	},
	{
		id: 'amsterdam',
		name: 'Amsterdam',
		description: 'Gemeente Amsterdam',
		design_system: 'nldesign',
		theming: { background_color: '#ffffff' },
		upstreamRef: 'old',
		upstreamVersion: '1.0.0',
	},
	{
		id: 'conduction',
		name: 'Conduction',
		description: 'Own set',
		design_system: 'nldesign',
	},
]

describe('mergeManifest', () => {
	it('keeps entries the run did not regenerate, in their original order', () => {
		const out = mergeManifest(existing, [
			{
				id: 'amsterdam',
				name: 'Amsterdam generated',
				description: 'Design tokens for Amsterdam',
				upstreamRef: 'new',
				upstreamVersion: '2.0.0',
			},
		])
		expect(out.map((e) => e.id)).toEqual([
			'nextcloud',
			'amsterdam',
			'conduction',
		])
		expect(out[0]).toEqual(existing[0])
		expect(out[2]).toEqual(existing[2])
	})

	it('keeps the fields it does not own on a regenerated entry and refreshes provenance', () => {
		const out = mergeManifest(existing, [
			{
				id: 'amsterdam',
				name: 'Amsterdam generated',
				description: 'Design tokens for Amsterdam',
				upstreamRef: 'new',
				upstreamVersion: '2.0.0',
			},
		])
		const amsterdam = out.find((e) => e.id === 'amsterdam')
		expect(amsterdam.name).toBe('Amsterdam')
		expect(amsterdam.description).toBe('Gemeente Amsterdam')
		expect(amsterdam.design_system).toBe('nldesign')
		expect(amsterdam.theming).toEqual({ background_color: '#ffffff' })
		expect(amsterdam.upstreamRef).toBe('new')
		expect(amsterdam.upstreamVersion).toBe('2.0.0')
	})

	it('appends an organisation that is new upstream', () => {
		const out = mergeManifest(existing, [
			{
				id: 'zwolle',
				name: 'Zwolle',
				description: 'Design tokens for Zwolle',
				upstreamRef: 'new',
			},
		])
		expect(out.map((e) => e.id)).toEqual([
			'nextcloud',
			'amsterdam',
			'conduction',
			'zwolle',
		])
		expect(out[3].name).toBe('Zwolle')
	})
})
