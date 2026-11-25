/**
 * Unit tests for js/lib/uploadResult.js, the model of the TqSetUploaden upload result.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { describe, expect, it } from 'vitest'

import uploadResult from '../../js/lib/uploadResult.js'

const label = (code, reasons) => reasons[code] || 'label:' + code

describe('uploadResult.notices', () => {
	it('gives one row per token, from skipped, errors and the conversion report', () => {
		const rows = uploadResult.notices({
			skipped: [{ path: 'color.other', reason: 'unmapped-path' }, '--zd-legacy-shadow'],
			errors: [{ path: 'color.brand.p3', reason: 'unsupported-color-space' }],
			report: [
				{ source: 'color.applied', action: 'applied', reason: 'x' },
				{ source: 'color.accent.vivid', action: 'adapted', reason: 'out-of-gamut-clipped' },
				{ source: 'color.other', action: 'skipped', reason: 'unmapped-path' },
				{ source: '--page-width', action: 'skipped', reason: 'layout' },
			],
			reasons: { layout: 'Page width was not applied.' },
		}, label)

		expect(rows).toEqual([
			{ token: 'color.other', reason: 'label:unmapped-path' },
			{ token: '--zd-legacy-shadow', reason: 'label:unmapped-path' },
			{ token: 'color.brand.p3', reason: 'label:unsupported-color-space' },
			{ token: 'color.accent.vivid', reason: 'label:out-of-gamut-clipped' },
			{ token: '--page-width', reason: 'Page width was not applied.' },
		])
	})

	it('is empty for a response without notices', () => {
		expect(uploadResult.notices({ imported: 3 }, label)).toEqual([])
		expect(uploadResult.notices(null, label)).toEqual([])
	})
})

describe('uploadResult.stored', () => {
	it('carries what the board draws for a stored upload', () => {
		const model = uploadResult.stored({
			imported: 312,
			skipped: [{ path: 'a', reason: 'unmapped-path' }],
			darkValues: 6,
			version: '3.0.0',
			warnings: [{ pair: 'text on primary', ratio: 3.5 }, { kind: 'incomplete', missing: ['x'] }],
			importWarnings: [
				{ path: 'color.old', message: 'Use color.new', token: '--nldesign-color-old' },
				{ path: 'typography.body', message: null },
			],
		}, { name: 'Zuiddrecht', fileName: 'zd.tokens.json', label })

		expect(model).toMatchObject({
			state: 'stored',
			name: 'Zuiddrecht',
			fileName: 'zd.tokens.json',
			version: '3.0.0',
			imported: 312,
			skipped: 1,
			darkValues: 6,
			contrast: 1,
		})
		expect(model.deprecations).toHaveLength(2)
		expect(model.adoptable).toEqual([{ path: 'color.old', message: 'Use color.new', token: '--nldesign-color-old' }])
	})

	it('has no file name for a paste and no version when the file declares none', () => {
		const model = uploadResult.stored({ imported: 1 }, { name: 'Paste', label })
		expect(model.fileName).toBe('')
		expect(model.version).toBe('')
		expect(model.contrast).toBe(0)
		expect(model.notices).toEqual([])
	})
})

describe('uploadResult.refused', () => {
	it('carries the status, the file, the error and the tokens the response names', () => {
		const model = uploadResult.refused({
			error: 'Property --nldesign-link-color contains a forbidden value.',
			skipped: [{ path: 'color.x', reason: 'unmapped-path' }],
		}, { status: 422, fileName: 'promo.css', label })

		expect(model).toEqual({
			state: 'refused',
			status: 422,
			fileName: 'promo.css',
			error: 'Property --nldesign-link-color contains a forbidden value.',
			notices: [{ token: 'color.x', reason: 'label:unmapped-path' }],
		})
	})
})

describe('uploadResult.missing', () => {
	it('counts the tokens an incomplete set leaves to the design system', () => {
		expect(uploadResult.missing({ warnings: [{ pair: 'a' }, { kind: 'incomplete', missing: ['a', 'b', 'c'] }] })).toBe(3)
	})

	it('is 0 for a complete set or no set', () => {
		expect(uploadResult.missing({ warnings: [] })).toBe(0)
		expect(uploadResult.missing(undefined)).toBe(0)
	})
})
