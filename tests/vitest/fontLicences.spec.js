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

// Derived from the generator's own tables rather than retyped, because a
// hand-maintained list here named Fira Sans, Figtree, IBM Plex Mono and Inter
// and did NOT name Source Sans 3 — which both nldesign font directories had
// shipped since the (EXAMPLE) Gemeente set. A licence gate that only checks
// the families someone remembered to list is the gate not running.
//
// Inter and Marianne live under css/systems/lasuite and css/systems/summer-breeze
// and are declared by those systems' own stylesheets, so they are not in the
// nldesign generator; they are named here with their upstream holders.
const BUNDLED = require('../../scripts/build-fonts.js')

const GENERATED_FAMILIES = BUNDLED.FAMILIES.concat(BUNDLED.NOTICE_ONLY).map(
	(family) => ({
		prefix: family.faces[0].file.replace(/latin.*$/, ''),
		holder: family.copyright
			.replace(/^[\d-]+ (by )?/, '')
			.replace(/ \(http.*$/, ''),
		spdx: family.spdx,
	}),
)

const FAMILIES = GENERATED_FAMILIES.concat([
	{ prefix: 'inter-', holder: 'The Inter Project Authors', spdx: 'OFL-1.1' },
	{ prefix: 'Inter-', holder: 'The Inter Project Authors', spdx: 'OFL-1.1' },
])

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
	return bundled(dir).filter((entry) => entry.family.spdx === 'OFL-1.1')
}

/**
 * Every font file in a directory, paired with the family that claims it.
 *
 * @param {string} dir Directory relative to the repo root.
 * @return {Array<{file: string, family: object}>} Each font with its family.
 */
function bundled(dir) {
	return fs
		.readdirSync(path.join(root, dir))
		.filter((file) => /\.(woff2?|ttf|otf)$/i.test(file))
		.map((file) => ({
			file,
			family: FAMILIES.find((f) =>
				file.toLowerCase().startsWith(f.prefix.toLowerCase()),
			),
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

	for (const dir of FONT_DIRS) {
		it(`${dir} has no font binary without a recorded copyright holder`, () => {
			const files = fs
				.readdirSync(path.join(root, dir))
				.filter((file) => /\.(woff2?|ttf|otf)$/i.test(file))
			const claimed = bundled(dir).map((entry) => entry.file)
			expect(files.length).toBeGreaterThan(0)
			expect(files.filter((file) => claimed.includes(file) === false)).toEqual(
				[],
			)
		})
	}

	it('LICENSES/ holds the Apache License 2.0 text, for Intel Clear Sans', () => {
		const text = fs.readFileSync(
			path.join(root, 'LICENSES/Apache-2.0.txt'),
			'utf8',
		)
		expect(text).toContain('Apache License')
		expect(text).toContain('Version 2.0, January 2004')
	})

	for (const dir of ['css/fonts', 'css/systems/nldesign/fonts']) {
		it(`${dir} ships an APACHE-2.0.txt naming Intel for Clear Sans`, () => {
			const text = fs.readFileSync(
				path.join(root, dir, 'APACHE-2.0.txt'),
				'utf8',
			)
			expect(text).toContain('Intel Corporation')
			expect(text).toContain('Apache License')
			expect(
				fs
					.readdirSync(path.join(root, dir))
					.filter((f) => f.startsWith('clear-sans-')).length,
			).toBeGreaterThan(0)
		})
	}

	it('REUSE.toml labels every OFL font OFL-1.1 with its holder, overriding the EUPL blanket', () => {
		// `\"` in a TOML string is a literal quote, and two holders carry one
		// (Reserved Font Name "Lato" / "Plex"), so the escape is undone before
		// the text is compared. Without this the assertion failed on the escape
		// rather than on a missing holder.
		const toml = fs
			.readFileSync(path.join(root, 'REUSE.toml'), 'utf8')
			.replace(/\\"/g, '"')
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
