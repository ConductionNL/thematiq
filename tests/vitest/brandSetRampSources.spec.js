/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The brand generator refuses a ramp step that does not say where its value
 * came from (openspec/changes/example-gemeente-theme, task 1.1a).
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync } from 'fs'
import { join } from 'path'
import { spawnSync } from 'child_process'
import { rampStepsWithoutSource } from '../../scripts/generate-brand-set.mjs'

const root = join(__dirname, '..', '..')
const brandsDir = join(root, 'scripts', 'brands')

describe('ramp steps carry their source', () => {
	it('names a step whose from is missing or empty', () => {
		const brand = {
			ramp: {
				'blue-300': { value: '#12506B', from: 'mockup' },
				'blue-400': { value: '#0B3648' },
				'gray-500': { value: '#6B6661', from: '  ' },
			},
		}
		expect(rampStepsWithoutSource(brand)).toEqual(['blue-400', 'gray-500'])
	})

	it('accepts a brand whose every step has a source', () => {
		const brand = { ramp: { 'blue-300': { value: '#12506B', from: 'mockup' } } }
		expect(rampStepsWithoutSource(brand)).toEqual([])
	})

	it('holds for every inline-palette brand file shipped', () => {
		for (const file of readdirSync(brandsDir).filter((f) =>
			f.startsWith('example-'),
		)) {
			const brand = JSON.parse(readFileSync(join(brandsDir, file), 'utf8'))
			expect(rampStepsWithoutSource(brand), file).toEqual([])
		}
	})

	it('the script exits non-zero and writes nothing for an unknown brand', () => {
		const run = spawnSync(
			process.execPath,
			[join(root, 'scripts', 'generate-brand-set.mjs'), 'no-such-brand'],
			{ encoding: 'utf8' },
		)
		expect(run.status).toBe(1)
	})
})
