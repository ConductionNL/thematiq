/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every bundled OFL font travels with its licence (#898).
 *
 * The SIL Open Font License 1.1 lets these fonts be redistributed only with
 * the copyright notice and the licence text. Two things carry them:
 *
 * - an OFL.txt next to the font files, which ships inside the app package
 *   whatever the packager leaves out (css/systems/lasuite/fonts/ already did
 *   this; see tests/Unit/LasuiteDesignStackTest.php);
 * - a REUSE.toml annotation that labels the binaries OFL-1.1, because the
 *   blanket `path = "**"` entry otherwise labels them EUPL-1.2, and
 *   LICENSES/OFL-1.1.txt for that identifier to point at.
 *
 * The expected copyright holders come from each family's upstream licence.
 */
import { describe, expect, it } from 'vitest'
import fs from 'fs'
import path from 'path'

const root = path.resolve(__dirname, '..', '..')

const FAMILIES = [
	{ prefix: 'fira-sans-', holder: 'The Mozilla Foundation and Telefonica S.A.' },
	{ prefix: 'figtree-', holder: 'The Figtree Project Authors' },
	{ prefix: 'ibm-plex-mono-', holder: 'IBM Corp.' },
	{ prefix: 'inter-', holder: 'The Inter Project Authors' },
]

const FONT_DIRS = [
	'css/fonts',
	'css/systems/nldesign/fonts',
	'css/systems/lasuite/fonts',
	'css/systems/summer-breeze/fonts',
]

/**
 * The OFL font files in a directory, matched by family prefix.
 *
 * @param {string} dir Directory relative to the repo root.
 * @return {Array<{file: string, family: object}>} Each font with its family.
 */
function ofl(dir) {
	return fs
		.readdirSync(path.join(root, dir))
		.filter((file) => /\.(woff2?|ttf|otf)$/i.test(file))
		.map((file) => ({
			file,
			family: FAMILIES.find((f) => file.toLowerCase().startsWith(f.prefix)),
		}))
		.filter((entry) => entry.family !== undefined)
}

describe('bundled OFL fonts carry their licence', () => {
	it('LICENSES/ holds the SIL Open Font License 1.1 text', () => {
		const text = fs.readFileSync(path.join(root, 'LICENSES/OFL-1.1.txt'), 'utf8')
		expect(text).toContain('SIL OPEN FONT LICENSE')
		expect(text).toContain('Version 1.1 - 26 February 2007')
	})

	for (const dir of FONT_DIRS) {
		it(`${dir} ships an OFL.txt naming the holder of every family in it`, () => {
			const fonts = ofl(dir)
			expect(fonts.length).toBeGreaterThan(0)
			const text = fs.readFileSync(path.join(root, dir, 'OFL.txt'), 'utf8')
			expect(text).toContain('SIL OPEN FONT LICENSE Version 1.1')
			for (const { family } of fonts) {
				expect(text).toContain(family.holder)
			}
		})
	}

	it('REUSE.toml labels every OFL font OFL-1.1 with its holder, overriding the EUPL blanket', () => {
		const toml = fs.readFileSync(path.join(root, 'REUSE.toml'), 'utf8')
		const blocks = toml.split('[[annotations]]').slice(1)
		for (const dir of FONT_DIRS) {
			for (const { file, family } of ofl(dir)) {
				const block = blocks.find((b) => {
					const globs = [...b.matchAll(/"([^"]+)"/g)].map((m) => m[1])
					return (
						globs.some((g) => matches(g, `${dir}/${file}`))
						&& b.includes('SPDX-License-Identifier = "OFL-1.1"')
					)
				})
				expect(
					block,
					`${dir}/${file} has no OFL-1.1 annotation`,
				).toBeDefined()
				expect(block).toContain('precedence = "override"')
				expect(block).toContain(family.holder)
			}
		}
	})
})

/**
 * A minimal REUSE glob match: `*` within one segment, `**` across segments.
 *
 * @param {string} glob The glob from REUSE.toml.
 * @param {string} file The repo-relative path.
 * @return {boolean} Whether the glob matches the path.
 */
function matches(glob, file) {
	const re = glob
		.replace(/[.+^${}()|[\]\\]/g, '\\$&')
		.split('**')
		.map((part) => part.replace(/\*/g, '[^/]*'))
		.join('.*')
	return new RegExp(`^${re}$`).test(file)
}
