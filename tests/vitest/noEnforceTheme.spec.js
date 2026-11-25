/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Each user's theme choice is core's (dark-mode spec, "No enforce_theme usage
 * in the codebase"): the app never forces a theme on anyone, so neither
 * `enforce_theme` nor `setEnabledThemes` occurs in its source.
 *
 * @spec openspec/specs/dark-mode/spec.md
 */
import { describe, expect, it } from 'vitest'
import { readFileSync, readdirSync, statSync, existsSync } from 'fs'
import { join, relative } from 'path'

const root = join(__dirname, '..', '..')

/** The app's own source directories: what runs, not what documents it. */
const SOURCE_DIRS = ['lib', 'js', 'src', 'appinfo', 'templates', 'css']

/** Every file under `dir`, skipping build output and dependencies. */
function filesUnder(dir) {
	const out = []
	for (const name of readdirSync(dir)) {
		if (name === 'node_modules' || name === 'vendor') continue
		const path = join(dir, name)
		if (statSync(path).isDirectory()) {
			out.push(...filesUnder(path))
		} else if (/\.(php|js|mjs|vue|ts|css|xml)$/.test(name)) {
			out.push(path)
		}
	}
	return out
}

describe('the app never forces a theme', () => {
	it('has no enforce_theme or setEnabledThemes in its source', () => {
		const hits = []
		for (const dir of SOURCE_DIRS) {
			const abs = join(root, dir)
			if (!existsSync(abs)) continue
			for (const file of filesUnder(abs)) {
				const text = readFileSync(file, 'utf8')
				for (const needle of ['enforce_theme', 'setEnabledThemes']) {
					if (text.includes(needle)) hits.push(`${relative(root, file)}: ${needle}`)
				}
			}
		}
		expect(hits).toEqual([])
	})

	it('scans real source, so an empty result means something', () => {
		expect(filesUnder(join(root, 'lib')).length).toBeGreaterThan(50)
	})
})
