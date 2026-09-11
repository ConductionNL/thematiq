#!/usr/bin/env node
/**
 * Convert an NL Design System theme into a Nextcloud token set, from the CLI.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * A thin front end over `js/lib/tokenConverter.js` — it reads files, prints a
 * report and optionally writes; every conversion decision lives in the module
 * and in `scripts/mapping/nlds-to-nextcloud.json`. This is the runtime the
 * nightly sync workflow can use, because it needs no PHP and no Nextcloud.
 *
 * Usage:
 *   node scripts/convert-nlds-theme.mjs <input> --slug zwolle --name "Gemeente Zwolle"
 *        [--write] [--report report.json] [--source design-tokens.css] [--quiet]
 *
 *   <input>            A built theme CSS, a Style Dictionary tokens.json, or an
 *                      existing css/tokens/*.css to re-convert (add-only).
 *   --slug             Token set id and brand prefix. Required.
 *   --name             Display name for the manifest entry. Defaults to the slug.
 *   --source           Provenance label. Defaults to the input's file name.
 *   --write            Write css/tokens/<slug>.css, img/logos/<slug>.<ext> when
 *                      the theme carries its logo inline, and update
 *                      token-sets.json. Without it nothing is touched and the
 *                      report is printed.
 *   --report <path>    Also write the full report as JSON.
 *   --quiet            Only print the summary line.
 *
 * Exit codes: 0 converted, 1 usage or conversion error.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'node:fs'
import { createHash } from 'node:crypto'
import { dirname, join, basename, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)
const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const converter = require(join(repoRoot, 'js/lib/tokenConverter.js'))

const LABEL = 'convert:theme'

/**
 * Parse `--flag value` / `--flag` arguments plus one positional input path.
 *
 * @param {Array<string>} argv Raw arguments.
 * @return {Object} The parsed options.
 */
function parseArgs(argv) {
	const options = { input: null, slug: null, name: null, source: null, write: false, report: null, quiet: false }

	for (let index = 0; index < argv.length; index++) {
		const arg = argv[index]

		if (arg === '--write') {
			options.write = true
			continue
		}

		if (arg === '--quiet') {
			options.quiet = true
			continue
		}

		if (arg === '--slug' || arg === '--name' || arg === '--report' || arg === '--source') {
			const value = argv[index + 1]
			if (value === undefined || value.startsWith('--')) {
				throw new Error(`${arg} needs a value.`)
			}

			options[arg === '--report' ? 'report' : arg.slice(2)] = value
			index++
			continue
		}

		if (arg.startsWith('--')) {
			throw new Error(`Unknown option ${arg}.`)
		}

		if (options.input !== null) {
			throw new Error('Only one input file can be converted at a time.')
		}

		options.input = arg
	}

	return options
}

/**
 * Load the mapping table, its SHA-256 and the app's own token vocabulary.
 *
 * The hash is computed over the raw file bytes, which is exactly what
 * `TokenSetConverterService::table()` does, so a set converted by either
 * runtime carries the same provenance line.
 *
 * @return {Object} `{table, tableHash, vocabulary}`.
 */
function loadContext() {
	const tablePath = join(repoRoot, 'scripts/mapping/nlds-to-nextcloud.json')
	const raw = readFileSync(tablePath, 'utf8')

	return {
		table: JSON.parse(raw),
		tableHash: createHash('sha256').update(raw).digest('hex'),
		vocabulary: converter.vocabularyFrom([
			readFileSync(join(repoRoot, 'css/systems/nldesign/defaults.css'), 'utf8'),
			readFileSync(join(repoRoot, 'css/systems/nldesign/utrecht-bridge.css'), 'utf8'),
		]),
	}
}

/**
 * Font families the instance has. The CLI cannot see Nextcloud's uploaded
 * fonts, so it reports on the bundled ones only — a `font-not-bundled` note
 * from the CLI means "not bundled", not "the admin has not uploaded it".
 *
 * @return {Array<string>} Family names.
 */
function availableFonts() {
	return []
}

/**
 * Print the report grouped by reason, the way the admin panel groups it.
 *
 * @param {Array<Object>} report The conversion report.
 * @return {void}
 */
function printReport(report) {
	const groups = new Map()

	for (const entry of report) {
		if (entry.action === 'applied') {
			continue
		}

		const reason = entry.reason || 'unmapped'
		if (groups.has(reason) === false) {
			groups.set(reason, [])
		}

		groups.get(reason).push(entry)
	}

	const applied = report.filter((entry) => entry.action === 'applied')
	if (applied.length > 0) {
		console.log(`\n[${LABEL}] applied — taken from the theme as-is`)
		for (const entry of applied) {
			console.log(`    ${entry.target.padEnd(44)} ${entry.value}`)
			console.log(`      from ${entry.source}`)
		}
	}

	for (const [reason, entries] of [...groups.entries()].sort()) {
		console.log(`\n[${LABEL}] ${reason} (${entries.length})`)
		for (const entry of entries.slice(0, 12)) {
			const target = (entry.target === '' ? '(not emitted)' : entry.target)
			console.log(`    ${entry.action.padEnd(8)} ${String(entry.source).padEnd(46)} ${target}`)
		}

		if (entries.length > 12) {
			console.log(`    … and ${entries.length - 12} more`)
		}
	}
}

/**
 * Write the token set file and merge its entry into `token-sets.json`.
 *
 * The manifest is rewritten with two-tab indentation and a trailing newline,
 * matching the committed file so `--write` produces a reviewable diff rather
 * than a reformat of all 47 entries.
 *
 * @param {string} slug The set slug.
 * @param {string} css The emitted CSS.
 * @param {Object} manifestEntry The manifest entry.
 * @param {Object|null} logoAsset The decoded logo, when the theme carried one inline.
 * @return {Array<string>} The paths written.
 */
function writeOutputs(slug, css, manifestEntry, logoAsset) {
	const written = []
	const cssPath = join(repoRoot, 'css/tokens', `${slug}.css`)
	writeFileSync(cssPath, css, 'utf8')
	written.push(cssPath)

	// The logo the converter lifted out of the theme. Written before the
	// manifest entry that names it, because theming sync validates the file
	// EXISTS before it hands the path to Nextcloud (theming-sync spec).
	// latin1: `contents` is a byte string, not text — see base64ToBytes().
	if (logoAsset !== null && logoAsset !== undefined) {
		const logoPath = join(repoRoot, logoAsset.path)
		mkdirSync(dirname(logoPath), { recursive: true })
		writeFileSync(logoPath, Buffer.from(logoAsset.contents, 'latin1'))
		written.push(logoPath)
	}

	const manifestPath = join(repoRoot, 'token-sets.json')
	const manifest = JSON.parse(readFileSync(manifestPath, 'utf8'))
	const index = manifest.findIndex((entry) => entry.id === slug)

	if (index === -1) {
		manifest.push(manifestEntry)
		manifest.sort((left, right) => String(left.id).localeCompare(String(right.id)))
	} else {
		// Preserve anything the committed entry carries that the converter does
		// not produce — a hand-picked logo, an upstreamRef from the sync — and
		// let the converted values win where they overlap.
		manifest[index] = { ...manifest[index], ...manifestEntry, theming: { ...manifest[index].theming, ...manifestEntry.theming } }
	}

	writeFileSync(manifestPath, `${JSON.stringify(manifest, null, '\t')}\n`, 'utf8')
	written.push(manifestPath)

	return written
}

/**
 * Entry point.
 *
 * @return {void}
 */
function main() {
	let options

	try {
		options = parseArgs(process.argv.slice(2))
	} catch (error) {
		console.error(`[${LABEL}] ${error.message}`)
		process.exit(1)
	}

	if (options.input === null || options.slug === null) {
		console.error(`[${LABEL}] usage: node scripts/convert-nlds-theme.mjs <input> --slug <slug> [--name "Name"] [--write] [--report out.json]`)
		process.exit(1)
	}

	const inputPath = resolve(options.input)
	if (existsSync(inputPath) === false) {
		console.error(`[${LABEL}] no such file: ${inputPath}`)
		process.exit(1)
	}

	const context = loadContext()
	let result

	try {
		result = converter.convert(readFileSync(inputPath, 'utf8'), {
			slug: options.slug,
			displayName: (options.name === null ? options.slug : options.name),
			sourceName: (options.source === null ? basename(inputPath) : options.source),
			table: context.table,
			tableHash: context.tableHash,
			vocabulary: context.vocabulary,
			fonts: availableFonts(),
		})
	} catch (error) {
		console.error(`[${LABEL}] ${error.message}`)
		process.exit(1)
	}

	const counts = result.counts

	if (options.quiet === false) {
		printReport(result.report)
	}

	console.log(
		`\n[${LABEL}] ${options.slug}: input ${result.inputKind}, `
		+ `${counts.applied} applied, ${counts.adapted} adapted, `
		+ `${counts.kept} kept, ${counts.skipped} skipped.`
	)

	if (options.report !== null) {
		writeFileSync(resolve(options.report), `${JSON.stringify(result.report, null, '\t')}\n`, 'utf8')
		console.log(`[${LABEL}] report written to ${resolve(options.report)}`)
	}

	if (options.write === false) {
		console.log(`[${LABEL}] dry run — nothing written. Pass --write to update css/tokens/ and token-sets.json.`)

		return
	}

	const written = writeOutputs(options.slug, result.css, result.manifestEntry, result.logoAsset)
	for (const path of written) {
		console.log(`[${LABEL}] wrote ${path}`)
	}

	console.log(
		`[${LABEL}] dark variant NOT regenerated — run`
		+ ' `php scripts/generate-dark-variants.php --force` (needs PHP).'
	)
}

main()
