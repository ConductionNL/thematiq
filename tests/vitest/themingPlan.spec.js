/**
 * Unit tests for js/lib/themingPlan.js: what syncing Nextcloud's own theming
 * to a token set offers, as the apply dialog shows it and admin.js posts it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-js-test-coverage/spec.md
 */

import { describe, expect, it } from 'vitest'
import themingPlan from '../../js/lib/themingPlan.js'

const { computeThemingPlan } = themingPlan

const t = (app, text, params) =>
	params === undefined
		? text
		: Object.keys(params).reduce(
				(acc, key) => acc.replace('{' + key + '}', params[key]),
				text,
			)

const plan = (set, current) => computeThemingPlan(set, current, t)
const NONE = { mode: 'none', diffs: [], payload: null }

describe('computeThemingPlan without input', () => {
	it('offers nothing without a set or without the current theming', () => {
		expect(plan(null, {})).toEqual(NONE)
		expect(plan({ theming: {} }, null)).toEqual(NONE)
	})

	it('offers nothing for a design-system set that declares no theming', () => {
		expect(
			plan({ design_system: 'nldesign' }, { primary_color: '#000' }),
		).toEqual(NONE)
		expect(plan({}, { primary_color: '#000' })).toEqual(NONE)
	})
})

describe('computeThemingPlan for a stock-based set', () => {
	it('resets everything core has synced, to the defaults core reports', () => {
		const result = plan(
			{ design_system: 'none' },
			{
				primary_color: '#123456',
				background_color: '#654321',
				default_primary_color: '#0082c9',
				default_background_color: '#0082ca',
				has_custom_logo: true,
				has_custom_background: true,
			},
		)
		expect(result).toEqual({
			mode: 'reset',
			payload: null,
			diffs: [
				{
					label: 'Primary color',
					key: 'primary_color',
					kind: 'color',
					current: '#123456',
					proposed: '#0082c9',
					proposedNote: 'Nextcloud default',
				},
				{
					label: 'Background color',
					key: 'background_color',
					kind: 'color',
					current: '#654321',
					proposed: '#0082ca',
					proposedNote: 'Nextcloud default',
				},
				{
					label: 'Logo',
					key: 'logo',
					kind: 'text',
					current: '(custom logo)',
					proposed: 'Nextcloud logo',
				},
				{
					label: 'Background image',
					key: 'background',
					kind: 'text',
					current: '(custom)',
					proposed: 'Nextcloud default',
				},
			],
		})
	})

	it('falls back to the stock blue when core reports no default', () => {
		const result = plan(
			{ design_system: 'none', theming: { captured: false } },
			{ primary_color: '#123456', background_color: '#654321' },
		)
		expect(result.diffs.map((d) => d.proposed)).toEqual(['#00679e', '#00679e'])
	})

	it('offers nothing when core is already stock', () => {
		expect(plan({ design_system: 'none' }, { has_custom_logo: false })).toEqual(
			NONE,
		)
	})

	it('matches a stock-based set that captured its branding, like any other theme', () => {
		const result = plan(
			{
				design_system: 'none',
				theming: { captured: true, primary_color: '#ABCDEF' },
			},
			{ primary_color: '#000000' },
		)
		expect(result.mode).toBe('match')
		expect(result.payload).toEqual({ primary_color: '#ABCDEF' })
	})
})

describe('computeThemingPlan for a set with theming', () => {
	it('offers the colours that differ, ignoring case, with a note for a translucent original', () => {
		const result = plan(
			{
				theming: {
					primary_color: '#AABBCC',
					primary_color_original: 'rgba(170, 187, 204, 0.5)',
					background_color: '#FFFFFF',
				},
			},
			{ primary_color: '#000000', background_color: '#ffffff' },
		)
		expect(result).toEqual({
			mode: 'match',
			payload: { primary_color: '#AABBCC' },
			diffs: [
				{
					label: 'Primary color',
					key: 'primary_color',
					kind: 'color',
					current: '#000000',
					proposed: '#AABBCC',
					proposedNote:
						"The set says rgba(170, 187, 204, 0.5). Nextcloud's own theming has no transparency. It gets this colour instead.",
				},
			],
		})
	})

	it('offers a background colour against a core that has none, with no note for an opaque one', () => {
		const result = plan({ theming: { background_color: '#112233' } }, {})
		expect(result.diffs).toEqual([
			{
				label: 'Background color',
				key: 'background_color',
				kind: 'color',
				current: undefined,
				proposed: '#112233',
				proposedNote: '',
			},
		])
		expect(result.payload).toEqual({ background_color: '#112233' })
	})

	it('offers every image slot the set fills and core does not already hold', () => {
		const result = plan(
			{
				theming: {
					logo: 'img/logos/a.svg',
					logoheader: 'img/logos/b.svg',
					favicon: 'img/logos/c.png',
					background: 'img/backgrounds/d.jpg',
				},
			},
			{
				has_custom_logo: true,
				synced_logo: 'img/logos/old.svg',
				has_custom_logoheader: true,
			},
		)
		expect(result.diffs).toEqual([
			{
				label: 'Logo',
				key: 'logo',
				kind: 'text',
				current: '(custom logo)',
				proposed: 'a.svg',
			},
			{
				label: 'Navigation bar logo',
				key: 'logoheader',
				kind: 'text',
				current: '(custom)',
				proposed: 'b.svg',
			},
			{
				label: 'Favicon',
				key: 'favicon',
				kind: 'text',
				current: '(default)',
				proposed: 'c.png',
			},
			{
				label: 'Background image',
				key: 'background',
				kind: 'text',
				current: '(default)',
				proposed: 'd.jpg',
			},
		])
		expect(result.payload).toEqual({
			logo: 'img/logos/a.svg',
			logoheader: 'img/logos/b.svg',
			favicon: 'img/logos/c.png',
			background: 'img/backgrounds/d.jpg',
		})
	})

	it('skips an image slot that already holds the file Thematiq last synced', () => {
		const set = {
			theming: {
				logo: 'img/logos/a.svg',
				favicon: 'img/logos/c.png',
				background: 'img/backgrounds/d.jpg',
			},
		}
		const current = {
			has_custom_logo: true,
			synced_logo: 'img/logos/a.svg',
			has_custom_favicon: true,
			synced_favicon: 'img/logos/c.png',
			has_custom_background: true,
			synced_background: 'img/backgrounds/d.jpg',
		}
		expect(plan(set, current)).toEqual(NONE)
		// A synced path without a custom image in the slot is not a match.
		expect(
			plan(set, { synced_logo: 'img/logos/a.svg' }).diffs.map((d) => d.key),
		).toEqual(['logo', 'favicon', 'background'])
		expect(
			plan(set, { has_custom_background: true, synced_background: 'x' })
				.diffs[2].current,
		).toBe('(custom)')
	})

	it('offers removing the background image, and sends the mode with the payload', () => {
		const result = plan(
			{ theming: { background_mode: 'color' } },
			{ has_custom_background: true },
		)
		expect(result).toEqual({
			mode: 'match',
			payload: { background_mode: 'color' },
			diffs: [
				{
					label: 'Background image',
					key: 'background_mode',
					kind: 'text',
					current: '(custom)',
					proposed: 'Removed (plain color)',
				},
			],
		})
		expect(
			plan(
				{ theming: { background_mode: 'color' } },
				{ background_mime: 'backgroundColor' },
			),
		).toEqual(NONE)
		expect(
			plan({ theming: { background_mode: 'color' } }, {}).diffs[0].current,
		).toBe('(default)')
	})

	it("offers Nextcloud's own background image back when the page shows another state", () => {
		const removed = plan(
			{ theming: { background_mode: 'default' } },
			{ background_mime: 'backgroundColor' },
		)
		expect(removed.diffs).toEqual([
			{
				label: 'Background image',
				key: 'background_mode',
				kind: 'text',
				current: 'Removed (plain color)',
				proposed: 'Nextcloud default',
			},
		])
		expect(removed.payload).toEqual({ background_mode: 'default' })

		const custom = plan(
			{ theming: { background_mode: 'default' } },
			{ has_custom_background: true },
		)
		expect(custom.diffs[0].current).toBe('(custom)')

		expect(plan({ theming: { background_mode: 'default' } }, {})).toEqual(NONE)
	})

	it('sends the image mode only alongside another difference', () => {
		expect(plan({ theming: { background_mode: 'image' } }, {})).toEqual(NONE)
		const result = plan(
			{ theming: { background_mode: 'image', primary_color: '#111111' } },
			{ primary_color: '#222222' },
		)
		expect(result.payload).toEqual({
			primary_color: '#111111',
			background_mode: 'image',
		})
	})

	it('does not send an unknown background mode', () => {
		const result = plan(
			{ theming: { background_mode: 'tiles', primary_color: '#111111' } },
			{},
		)
		expect(result.payload).toEqual({ primary_color: '#111111' })
	})

	it('lists the differences in a fixed order', () => {
		const result = plan(
			{
				theming: {
					primary_color: '#111111',
					background_color: '#222222',
					logo: 'l/a.svg',
					logoheader: 'l/b.svg',
					favicon: 'l/c.png',
					background: 'b/d.jpg',
					background_mode: 'color',
				},
			},
			{},
		)
		expect(result.diffs.map((d) => d.key)).toEqual([
			'primary_color',
			'background_color',
			'logo',
			'logoheader',
			'favicon',
			'background',
			'background_mode',
		])
		expect(Object.keys(result.payload)).toEqual([
			'primary_color',
			'background_color',
			'logo',
			'logoheader',
			'favicon',
			'background',
			'background_mode',
		])
	})
})
