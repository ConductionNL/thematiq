#!/usr/bin/env node

/**
 * Build the Nextcloud variable inventory from a Nextcloud release.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * WHY THIS EXISTS
 *
 * "thematiq supports every Nextcloud variable" was a claim nobody could check,
 * because nothing recorded what "every" is. This script reads it from the
 * release an instance actually serves and writes it down:
 *
 *   scripts/mapping/nextcloud-variables.json  one entry per custom property
 *   scripts/mapping/nextcloud-variables.sha   one hash per entry, for the drift check
 *
 * INPUTS (local files only, no network)
 *
 *   --nextcloud <dir>  a built Nextcloud release (its core/, apps/ and dist/), or
 *                      NEXTCLOUD_DIR. The version is read from <dir>/version.php.
 *   theming            scripts/inventory/sources/nextcloud-<version>/theming/{id}.css,
 *                      the four stylesheets the theming app serves at
 *                      /apps/theming/theme/{id}.css on a stock instance.
 *   library            node_modules/@conduction/nextcloud-vue, the version the
 *                      lockfile pins, or CONDUCTION_LIBRARY_DIR.
 *   config             scripts/inventory/config.json: owner prefixes, forced classes.
 *
 * USAGE
 *
 *   npm run inventory:extract -- --nextcloud /path/to/nextcloud   write the files
 *   npm run inventory:extract -- --nextcloud /path --check        compare, write nothing
 *
 * --check exits 1 and names the first differing entry when the committed
 * inventory is not what this script produces. Without a release directory it
 * exits 2: a drift check that cannot read its input has not passed.
 *
 * @spec openspec/changes/nc-variable-inventory/specs/nextcloud-variable-inventory/spec.md
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import {
	THEMES,
	CLASSES,
	classify,
	keep,
	ownerOfFile,
	ownerOfVariable,
	parseThemeStylesheet,
	scanSource,
	serialise,
} from './scan.mjs'
import { hash } from './guard.mjs'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')
const OUTPUT = path.join(root, 'scripts/mapping/nextcloud-variables.json')
const DIGESTS = path.join(root, 'scripts/mapping/nextcloud-variables.sha')
const LIBRARY = process.env.CONDUCTION_LIBRARY_DIR ?? path.join(root, 'node_modules/@conduction/nextcloud-vue')
const LIBRARY_FILES = ['dist/nextcloud-vue.css', 'dist/nextcloud-vue.cjs.js']

/**
 * Read a command-line option's value.
 *
 * @param {string} name Option name without dashes.
 * @return {string|null} The value, or null.
 */
function option(name) {
	const at = process.argv.indexOf(`--${name}`)
	return at === -1 ? null : (process.argv[at + 1] ?? null)
}

/**
 * Every CSS and JavaScript file of a release, sorted, relative to its root.
 *
 * @param {string} dir Release root.
 * @return {string[]} Relative paths with forward slashes.
 */
function releaseFiles(dir) {
	const out = []
	const walk = (rel) => {
		for (const entry of fs.readdirSync(path.join(dir, rel), { withFileTypes: true })) {
			const child = rel === '' ? entry.name : `${rel}/${entry.name}`
			if (entry.isDirectory() === true) {
				if (['l10n', 'tests', 'vendor', '3rdparty', 'node_modules'].includes(entry.name) === false) {
					walk(child)
				}
			} else if (/\.(css|js|mjs)$/.test(entry.name) === true) {
				out.push(child)
			}
		}
	}
	for (const top of ['core', 'apps', 'dist']) {
		if (fs.existsSync(path.join(dir, top)) === true) {
			walk(top)
		}
	}
	return out.sort()
}

/**
 * The release's four-part version from version.php.
 *
 * @param {string} dir Release root.
 * @return {string} e.g. 34.0.0.12
 */
function releaseVersion(dir) {
	const php = fs.readFileSync(path.join(dir, 'version.php'), 'utf8')
	const match = php.match(/\$OC_Version\s*=\s*(?:array\(|\[)([\d,\s]+)/)
	if (match === null) {
		throw new Error(`no $OC_Version in ${dir}/version.php`)
	}
	return match[1].split(',').map((part) => part.trim()).filter((part) => part !== '').join('.')
}

/**
 * Build the inventory object.
 *
 * @param {string} nextcloudDir Release root.
 * @return {object} The inventory.
 */
export function buildInventory(nextcloudDir) {
	const config = JSON.parse(fs.readFileSync(path.join(root, 'scripts/inventory/config.json'), 'utf8'))
	const version = releaseVersion(nextcloudDir)
	const themingDir = path.join(root, `scripts/inventory/sources/nextcloud-${version}/theming`)
	const library = JSON.parse(fs.readFileSync(path.join(LIBRARY, 'package.json'), 'utf8'))

	const facts = new Map()
	const fact = (name) => {
		if (facts.has(name) === false) {
			facts.set(name, {
				name,
				theme: false,
				declared: false,
				read: false,
				runtime: false,
				conduction: false,
				selectors: [],
				values: [],
				owners: [],
				stock: {},
			})
		}
		return facts.get(name)
	}

	for (const theme of THEMES) {
		const file = path.join(themingDir, `${theme}.css`)
		if (fs.existsSync(file) === false) {
			throw new Error(`missing theming stylesheet ${path.relative(root, file)}`)
		}
		const { selector, values } = parseThemeStylesheet(fs.readFileSync(file, 'utf8'))
		for (const [name, value] of values) {
			const entry = fact(name)
			entry.theme = true
			entry.stock[theme] = value
			if (entry.selectors.includes(selector) === false) {
				entry.selectors.push(selector)
			}
		}
	}

	const scan = (text, owner, conductionOnly) => {
		const { declared, read, runtime } = scanSource(text)
		const seen = new Set()
		const touch = (name) => {
			if (conductionOnly === true && name.startsWith('--cn-') === false) {
				return null
			}
			const entry = fact(name)
			if (conductionOnly === true) {
				entry.conduction = true
			}
			if (seen.has(name) === false) {
				entry.owners.push(owner)
				seen.add(name)
			}
			return entry
		}
		for (const [name, where] of declared) {
			const entry = touch(name)
			if (entry === null) {
				continue
			}
			entry.declared = true
			for (const selector of where.selectors) {
				if (entry.selectors.includes(selector) === false) {
					entry.selectors.push(selector)
				}
			}
			for (const value of where.values) {
				if (entry.values.includes(value) === false) {
					entry.values.push(value)
				}
			}
		}
		for (const name of read) {
			const entry = touch(name)
			if (entry !== null) {
				entry.read = true
			}
		}
		for (const name of runtime) {
			const entry = touch(name)
			if (entry !== null) {
				entry.runtime = true
			}
		}
	}

	for (const file of releaseFiles(nextcloudDir)) {
		scan(fs.readFileSync(path.join(nextcloudDir, file), 'utf8'), ownerOfFile(file), false)
	}
	for (const file of LIBRARY_FILES) {
		scan(fs.readFileSync(path.join(LIBRARY, file), 'utf8'), 'conduction', true)
	}

	const prefixes = Object.entries(config.ownerPrefixes)
	const variables = {}
	const counts = Object.fromEntries(CLASSES.map((name) => [name, 0]))
	for (const name of [...facts.keys()].sort()) {
		const entry = facts.get(name)
		const klass = classify(entry, config.forceClass)
		counts[klass] += 1
		const owner = klass === 'theme' ? 'theming' : ownerOfVariable(name, entry.owners, prefixes)
		const record = { class: klass, owner }
		record.selectors = keep(entry.selectors)
		if (klass === 'theme') {
			record.stock = Object.fromEntries(THEMES.filter((theme) => entry.stock[theme] !== undefined).map((theme) => [theme, entry.stock[theme]]))
		} else if (entry.values.length > 0) {
			record.stock = { default: entry.values[0] }
		}
		record.declared = entry.declared || entry.theme
		record.read = entry.read
		variables[name] = record
	}

	return {
		$comment: [
			'GENERATED by scripts/inventory/extract-nextcloud-variables.mjs. Do not edit by hand:',
			'the drift check compares every entry with nextcloud-variables.sha and with a fresh extraction.',
			'What thematiq does with each entry lives in variable-status.json, which IS edited by hand.',
		],
		nextcloud: version,
		library: { name: library.name, version: library.version },
		counts,
		variables,
	}
}

/**
 * One line per entry: name, tab, the first 16 hex characters of its SHA-256.
 *
 * @param {object} inventory The inventory.
 * @return {string} The digest file text.
 */
export function digestLines(inventory) {
	const lines = [`@source\t${hash({ nextcloud: inventory.nextcloud, library: inventory.library, counts: inventory.counts })}`]
	for (const [name, entry] of Object.entries(inventory.variables)) {
		lines.push(`${name}\t${hash(entry)}`)
	}
	return `${lines.join('\n')}\n`
}

/**
 * The first entry where two inventories differ.
 *
 * @param {object} expected The fresh extraction.
 * @param {object} actual   The committed file.
 * @return {string|null} A description, or null when identical.
 */
export function firstDifference(expected, actual) {
	for (const key of ['nextcloud', 'library', 'counts']) {
		if (JSON.stringify(expected[key]) !== JSON.stringify(actual[key])) {
			return `${key}: expected ${JSON.stringify(expected[key])}, found ${JSON.stringify(actual[key])}`
		}
	}
	const names = [...new Set([...Object.keys(expected.variables), ...Object.keys(actual.variables ?? {})])].sort()
	for (const name of names) {
		const want = JSON.stringify(expected.variables[name])
		const got = JSON.stringify(actual.variables?.[name])
		if (want !== got) {
			return `${name}: expected ${want ?? 'no entry'}, found ${got ?? 'no entry'}`
		}
	}
	return null
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
	const dir = option('nextcloud') ?? process.env.NEXTCLOUD_DIR ?? null
	if (dir === null || fs.existsSync(path.join(dir, 'version.php')) === false) {
		console.error('No Nextcloud release directory: pass --nextcloud <dir> or set NEXTCLOUD_DIR (see docs/reference/nextcloud-variable-inventory.md).')
		process.exit(2)
	}
	const inventory = buildInventory(dir)
	if (process.argv.includes('--check') === true) {
		const committed = JSON.parse(fs.readFileSync(OUTPUT, 'utf8'))
		const difference = firstDifference(inventory, committed)
		if (difference !== null || fs.readFileSync(OUTPUT, 'utf8') !== serialise(inventory)) {
			console.error(`nextcloud-variables.json is not what the extractor produces for Nextcloud ${inventory.nextcloud}. First difference: ${difference ?? 'formatting only'}`)
			process.exit(1)
		}
		console.log(`nextcloud-variables.json matches the extraction from Nextcloud ${inventory.nextcloud} (${Object.keys(inventory.variables).length} entries).`)
	} else {
		fs.writeFileSync(OUTPUT, serialise(inventory))
		fs.writeFileSync(DIGESTS, digestLines(inventory))
		console.log(`Wrote ${Object.keys(inventory.variables).length} entries for Nextcloud ${inventory.nextcloud}: ${JSON.stringify(inventory.counts)}`)
	}
}
