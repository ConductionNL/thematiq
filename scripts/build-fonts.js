#!/usr/bin/env node

/**
 * Self-hosted webfont layer — generator and drift check.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * One table, two stylesheets. `FAMILIES` below is the only place that says
 * which typefaces thematiq self-hosts, under which licence, from which upstream
 * package, and in which weights; this script writes
 * `css/systems/nldesign/fonts.css` (the layer the nldesign design system links
 * on a Nextcloud instance) and `css/fonts.css` (the layer portaliq links for a
 * public portal) from it.
 *
 * WHY THIS IS A TABLE AND NOT A STRING. The former version of this script held
 * one hard-coded Fira Sans stylesheet and wrote it over `css/fonts.css`. Source
 * Sans 3 was later added to that file by hand for the (EXAMPLE) Gemeente set,
 * so `npm run build` would have silently deleted it, and
 * `css/systems/nldesign/fonts.css` — the file that actually matters on an
 * instance — was not written by any generator at all, just kept identical by
 * hand. `--check` now fails on either kind of drift.
 *
 * WHY A FAMILY IS HERE OR IS NOT. A family is listed only when we may
 * redistribute the bytes: SIL OFL 1.1 or Apache 2.0, with the upstream
 * copyright notice recorded and a REUSE.toml entry for the binaries. A set that
 * names a family we may NOT redistribute (Avenir, DIN, Bolder, Karbon, Sofia
 * Pro, greycliff-cf, neue-haas-grotesk, RijksoverheidSans, Calibri, a Monotype
 * `W01` webfont) gets a `font` block in `token-sets.json` saying so, and the
 * admin UI asks for an upload. Shipping a lookalike under the brand's name
 * would be worse than falling back, because nobody would ever find out.
 *
 * Usage:
 *   node scripts/build-fonts.js            # write both stylesheets
 *   node scripts/build-fonts.js --check    # exit 1 when either has drifted
 */

'use strict'

const fs = require('fs')
const path = require('path')

const REPO_ROOT = path.resolve(__dirname, '..')

/**
 * The output stylesheets, and where each resolves `url()` from.
 *
 * Both directories carry their own copy of the binaries, as `css/fonts/` and
 * `css/systems/nldesign/fonts/` already did for Fira Sans, so neither layer can
 * break the other by moving a file.
 */
const OUTPUTS = [
	{
		css: 'css/systems/nldesign/fonts.css',
		fontsDir: 'css/systems/nldesign/fonts',
	},
	{ css: 'css/fonts.css', fontsDir: 'css/fonts' },
]

/**
 * Every typeface thematiq self-hosts.
 *
 * Which shipped set names which family is NOT repeated here, because it would
 * be a second copy of a fact the stylesheets already hold.
 * tests/Unit/Service/TokenSetFontAuditTest.php reads the real
 * `--nldesign-font-family` of every shipped set, resolves its var() chain and
 * fails when a set names a family no linked stylesheet serves and
 * `token-sets.json` declares nothing about it.
 */
const FAMILIES = [
	{
		family: 'Fira Sans',
		spdx: 'OFL-1.1',
		copyright:
			'2012-2015 The Mozilla Foundation and Telefonica S.A. (https://github.com/mozilla/Fira)',
		upstream: '@fontsource/fira-sans',
		note: 'The NL Design System default, and the open-source stand-in for RijksoverheidSansWebText.',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Fira Sans',
				file: 'fira-sans-latin-400-normal',
				formats: ['woff2', 'woff'],
			},
			{
				weight: 400,
				style: 'italic',
				local: 'Fira Sans Italic',
				file: 'fira-sans-latin-400-italic',
				formats: ['woff2', 'woff'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Fira Sans Bold',
				file: 'fira-sans-latin-700-normal',
				formats: ['woff2', 'woff'],
			},
			{
				weight: 700,
				style: 'italic',
				local: 'Fira Sans Bold Italic',
				file: 'fira-sans-latin-700-italic',
				formats: ['woff2', 'woff'],
			},
		],
	},
	{
		family: 'Source Sans 3',
		spdx: 'OFL-1.1',
		copyright:
			"2010-2020 Adobe (http://www.adobe.com/), with Reserved Font Name 'Source'",
		upstream: '@fontsource/source-sans-3 5.3.0',
		note: 'Named by the (EXAMPLE) Gemeente set and by nora.',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Source Sans 3',
				file: 'source-sans-3-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 600,
				style: 'normal',
				local: 'Source Sans 3 SemiBold',
				file: 'source-sans-3-latin-600-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Source Sans 3 Bold',
				file: 'source-sans-3-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'Source Sans Pro',
		spdx: 'OFL-1.1',
		copyright:
			"2010-2020 Adobe (http://www.adobe.com/), with Reserved Font Name 'Source'",
		upstream: '@fontsource/source-sans-pro 5.3.0',
		note: 'Source Sans 3 is the newer release of the same design; three sets name the older name, so the older name is served.',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Source Sans Pro',
				file: 'source-sans-pro-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 600,
				style: 'normal',
				local: 'Source Sans Pro SemiBold',
				file: 'source-sans-pro-latin-600-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Source Sans Pro Bold',
				file: 'source-sans-pro-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'Open Sans',
		spdx: 'OFL-1.1',
		copyright:
			'2020 The Open Sans Project Authors (https://github.com/googlefonts/opensans)',
		upstream: '@fontsource/open-sans 5.3.0',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Open Sans',
				file: 'open-sans-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 600,
				style: 'normal',
				local: 'Open Sans SemiBold',
				file: 'open-sans-latin-600-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Open Sans Bold',
				file: 'open-sans-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'Lato',
		spdx: 'OFL-1.1',
		copyright:
			'2010-2011 by tyPoland Lukasz Dziedzic (team@latofonts.com) with Reserved Font Name "Lato"',
		upstream: '@fontsource/lato 5.3.0',
		note: 'Upstream ships no 600 cut, so 600 is left to the browser to synthesise from 400/700.',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Lato',
				file: 'lato-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Lato Bold',
				file: 'lato-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'Roboto',
		spdx: 'OFL-1.1',
		copyright:
			'2011 The Roboto Project Authors (https://github.com/googlefonts/roboto-classic)',
		upstream: '@fontsource/roboto 5.3.0',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Roboto',
				file: 'roboto-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 600,
				style: 'normal',
				local: 'Roboto SemiBold',
				file: 'roboto-latin-600-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Roboto Bold',
				file: 'roboto-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'IBM Plex Sans',
		spdx: 'OFL-1.1',
		copyright:
			'2017 IBM Corp. with Reserved Font Name "Plex" (https://github.com/IBM/plex)',
		upstream: '@fontsource/ibm-plex-sans 5.3.0',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'IBM Plex Sans',
				file: 'ibm-plex-sans-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 600,
				style: 'normal',
				local: 'IBM Plex Sans SemiBold',
				file: 'ibm-plex-sans-latin-600-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'IBM Plex Sans Bold',
				file: 'ibm-plex-sans-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'Clear Sans',
		spdx: 'Apache-2.0',
		copyright: '2012 Intel Corporation (https://github.com/intel/clear-sans)',
		upstream: '@fontsource/clear-sans 5.3.0',
		note: 'Apache 2.0, not OFL, so its licence text travels in LICENSES/Apache-2.0.txt rather than in the OFL notice.',
		faces: [
			{
				weight: 400,
				style: 'normal',
				local: 'Clear Sans',
				file: 'clear-sans-latin-400-normal',
				formats: ['woff2'],
			},
			{
				weight: 700,
				style: 'normal',
				local: 'Clear Sans Bold',
				file: 'clear-sans-latin-700-normal',
				formats: ['woff2'],
			},
		],
	},
	{
		family: 'Figtree',
		spdx: 'OFL-1.1',
		copyright:
			'2022 The Figtree Project Authors (https://github.com/erikdkennedy/figtree)',
		upstream: '@fontsource/figtree',
		note: 'One variable file covering 300-900, which is why this family declares a weight RANGE rather than three cuts.',
		faces: [
			{
				weight: '300 900',
				style: 'normal',
				local: 'Figtree',
				file: 'figtree-latin-variable',
				formats: ['woff2'],
			},
		],
	},
]

/**
 * Bundled typefaces whose `@font-face` lives in another stylesheet, listed here
 * so the licence notice beside the binaries still names them.
 *
 * IBM Plex Mono is declared by `css/fonts-conduction.css`, which is a layer no
 * design system links today. The bytes ship either way, so the OFL notice has
 * to name the holder either way — generating the notice from the emitting table
 * alone dropped IBM Plex Mono from `css/fonts/OFL.txt`, which is exactly the
 * failure this table prevents.
 */
const NOTICE_ONLY = [
	{
		family: 'IBM Plex Mono',
		spdx: 'OFL-1.1',
		copyright:
			'2017 IBM Corp. with Reserved Font Name "Plex" (https://github.com/IBM/plex)',
		upstream: '@fontsource/ibm-plex-mono',
		faces: [{ file: 'ibm-plex-mono-latin-400', formats: ['woff2'] }],
	},
]

/**
 * The notice file each licence's bundled families are named in, beside the
 * binaries. The licence text inside each file is preserved; only the copyright
 * lines above it are generated.
 */
const NOTICES = {
	'OFL-1.1': 'OFL.txt',
	'Apache-2.0': 'APACHE-2.0.txt',
}

/** Render one @font-face block. */
function renderFace(family, face) {
	const sources = [`local('${face.local}')`].concat(
		face.formats.map(
			(format) =>
				`url('${family.dir}/${face.file}.${format}') format('${format}')`,
		),
	)

	return [
		'@font-face {',
		`\tfont-family: '${family.family}';`,
		'\tsrc:',
		sources.map((source) => `\t\t${source}`).join(',\n') + ';',
		`\tfont-weight: ${face.weight};`,
		`\tfont-style: ${face.style};`,
		'\tfont-display: swap;',
		'}',
	].join('\n')
}

/** Render one family's comment header plus its faces. */
function renderFamily(family, dir) {
	const header = [
		'/*',
		` * ${family.family} — ${family.spdx}.`,
		` * Copyright ${family.copyright}.`,
		` * From ${family.upstream}, latin subset.`,
	]
	if (family.note !== undefined) {
		header.push(` * ${family.note}`)
	}
	header.push(' */')

	const withDir = Object.assign({}, family, { dir })

	return [header.join('\n')]
		.concat(family.faces.map((face) => renderFace(withDir, face)))
		.join('\n\n')
}

/** Render a whole stylesheet for one output. */
function renderStylesheet(output) {
	const dir =
		path.basename(output.fontsDir) === 'fonts' ? 'fonts' : output.fontsDir
	const banner = [
		'/**',
		' * Self-hosted webfonts.',
		' *',
		' * GENERATED by scripts/build-fonts.js from its FAMILIES table — do not edit by',
		' * hand. Add a family there (with its licence and copyright, plus a REUSE.toml',
		' * entry for the binaries) and re-run `npm run build:fonts`.',
		' *',
		' * SELF-HOSTED, NOT LINKED TO GOOGLE. A government page must not make its',
		' * visitors announce themselves to a third party to read it, and a page that',
		' * only renders correctly when an external host answers is a page with an outage',
		' * nobody owns.',
		' *',
		' * `font-display: swap` throughout, so text is readable while a face loads.',
		' *',
		' * SPDX-License-Identifier: EUPL-1.2',
		' * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>',
		' */',
	].join('\n')

	return (
		[banner]
			.concat(FAMILIES.map((family) => renderFamily(family, dir)))
			.join('\n\n') + '\n'
	)
}

/** Every binary the table names, relative to one output's font directory. */
function expectedBinaries() {
	const files = []
	for (const family of FAMILIES) {
		for (const face of family.faces) {
			for (const format of face.formats) {
				files.push(`${face.file}.${format}`)
			}
		}
	}

	return files
}

/**
 * The notice an OFL (or Apache) font directory must carry beside the binaries.
 *
 * The SIL OFL lets these files be redistributed only WITH the copyright notice
 * and the licence text, so the notice travels next to the fonts rather than
 * only in REUSE.toml — a packager that strips metadata still ships it. Only the
 * notice lines are generated: the licence body already in the file is preserved
 * byte for byte, because a generator must never be the thing that edits a
 * licence text.
 *
 * Before this was generated, `css/fonts/OFL.txt` named Fira Sans, Figtree and
 * IBM Plex Mono and the nldesign one named only Fira Sans, while both
 * directories had shipped Source Sans 3 since the (EXAMPLE) Gemeente set. The
 * hand-maintained list in tests/vitest/fontLicences.spec.js did not name Source
 * Sans 3 either, so nothing failed.
 *
 * @param {string} dir     The font directory, relative to the repo root.
 * @param {string} spdx    The licence identifier to collect families for.
 * @param {string} notice  The file name of the notice in that directory.
 *
 * @return {string|null} The rendered notice, or null when the file has no
 *                       preservable licence body to append to.
 */
function renderNotice(dir, spdx, notice) {
	const target = path.join(REPO_ROOT, dir, notice)
	let current = null
	try {
		current = fs.readFileSync(target, 'utf8')
	} catch {
		return null
	}

	const marker = 'licensed under the'
	const index = current.indexOf(marker)
	if (index === -1) {
		return null
	}

	const present = fs.readdirSync(path.join(REPO_ROOT, dir))
	const lines = []
	for (const family of FAMILIES.concat(NOTICE_ONLY)) {
		if (family.spdx !== spdx) {
			continue
		}
		const ships = family.faces.some((face) =>
			face.formats.some((format) =>
				present.includes(`${face.file}.${format}`),
			),
		)
		if (ships === false) {
			continue
		}
		lines.push(`${family.family}: Copyright ${family.copyright}`)
	}

	return lines.join('\n') + '\n\n' + current.slice(index)
}

/**
 * Font binaries in a directory that no table claims.
 *
 * A notice generated from a table can only name what the table knows, so the
 * table has to be complete. This reports the gap from the other side: any
 * woff/woff2/ttf/otf file in a font directory that neither FAMILIES nor
 * NOTICE_ONLY accounts for, which is a binary shipping with no recorded
 * copyright holder.
 *
 * @param {string} dir The font directory, relative to the repo root.
 *
 * @return {Array<string>} The unclaimed file names.
 */
function unclaimedBinaries(dir) {
	const claimed = new Set()
	for (const family of FAMILIES.concat(NOTICE_ONLY)) {
		for (const face of family.faces) {
			for (const format of face.formats) {
				claimed.add(`${face.file}.${format}`)
			}
		}
	}

	return fs
		.readdirSync(path.join(REPO_ROOT, dir))
		.filter((file) => /\.(woff2?|ttf|otf)$/i.test(file))
		.filter((file) => claimed.has(file) === false)
}

function main() {
	const check = process.argv.slice(2).includes('--check')
	const problems = []

	for (const output of OUTPUTS) {
		const target = path.join(REPO_ROOT, output.css)
		const rendered = renderStylesheet(output)

		if (check === true) {
			let current = null
			try {
				current = fs.readFileSync(target, 'utf8')
			} catch {
				problems.push(`${output.css} is missing`)
				continue
			}
			if (current !== rendered) {
				problems.push(
					`${output.css} has drifted from scripts/build-fonts.js — run \`npm run build:fonts\``,
				)
			}
		} else {
			fs.writeFileSync(target, rendered)
			console.log(`✓ Wrote ${output.css}`)
		}

		for (const file of expectedBinaries()) {
			const binary = path.join(REPO_ROOT, output.fontsDir, file)
			if (fs.existsSync(binary) === false) {
				problems.push(`${output.fontsDir}/${file} is missing`)
			}
		}

		for (const file of unclaimedBinaries(output.fontsDir)) {
			problems.push(
				`${output.fontsDir}/${file} is a font binary no table in scripts/build-fonts.js claims, so its copyright holder is recorded nowhere`,
			)
		}

		for (const [spdx, file] of Object.entries(NOTICES)) {
			const notice = renderNotice(output.fontsDir, spdx, file)
			if (notice === null) {
				problems.push(
					`${output.fontsDir}/${file} is missing or carries no licence text`,
				)
				continue
			}
			if (check === true) {
				if (
					fs.readFileSync(
						path.join(REPO_ROOT, output.fontsDir, file),
						'utf8',
					) !== notice
				) {
					problems.push(
						`${output.fontsDir}/${file} does not name every bundled ${spdx} family — run \`npm run build:fonts\``,
					)
				}
				continue
			}
			fs.writeFileSync(path.join(REPO_ROOT, output.fontsDir, file), notice)
			console.log(`✓ Wrote ${output.fontsDir}/${file}`)
		}
	}

	if (problems.length > 0) {
		console.error('[build:fonts] ' + problems.length + ' problem(s):')
		for (const problem of problems) {
			console.error('  - ' + problem)
		}
		process.exit(1)
	}

	console.log(
		`[build:fonts] ${FAMILIES.length} self-hosted families, ${expectedBinaries().length} files per layer — ${check === true ? 'no drift' : 'written'}.`,
	)
}

module.exports = {
	FAMILIES,
	NOTICE_ONLY,
	OUTPUTS,
	renderStylesheet,
	expectedBinaries,
}

if (require.main === module) {
	main()
}
