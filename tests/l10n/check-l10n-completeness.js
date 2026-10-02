#!/usr/bin/env node
/**
 * l10n locale completeness check.
 *
 * check-l10n.js (the sibling extraction-drift guard) only asserts that every
 * t()/n() source string used in the frontend has an entry in l10n/en.json —
 * it says nothing about the OTHER locale files. Nextcloud silently falls
 * back to the English source string for any key missing from a locale file,
 * so a locale can drift further and further behind en.json (new admin-panel
 * strings ship, translators never get a key to translate) without any CI
 * signal. This script closes that gap: every key present in l10n/en.json
 * MUST also be present with a non-empty value (untranslated is fine, MISSING
 * or EMPTY is not) in
 * every other l10n/*.json file, so translators always have the full set of
 * keys to work from and no shipped string is silently English-only forever.
 *
 * It is intentionally dependency-free (pure Node, no build, no npm install)
 * so CI can run it in a bare node container.
 *
 * Modes:
 *   (default)  check only — exit non-zero if any locale is missing a key
 *              present in en.json.
 *   --write    backfill — add every missing key to each locale file with the
 *              English source as a placeholder value (source === English,
 *              same convention check-l10n.js --write uses for en.json). This
 *              guarantees structural completeness immediately; the
 *              placeholder is a real (if untranslated) value, which is the
 *              same runtime behaviour Nextcloud already has for a missing
 *              key, but it now shows up for translators instead of being
 *              invisible.
 *
 * Exit codes:
 *   0  every locale has every en.json key (or --write made it so)
 *   1  one or more locales are missing one or more keys (hard failure)
 *
 * Env:
 *   L10N_DIR  override the l10n directory (default: l10n)
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/l10n-completeness/spec.md#requirement-the-l10n-check-tool-catches-cross-locale-gaps
 */

'use strict'

const fs = require('fs')
const path = require('path')

const ROOT = process.cwd()
const WRITE = process.argv.includes('--write')
const l10nDir = path.join(ROOT, process.env.L10N_DIR || 'l10n')
const enFile = path.join(l10nDir, 'en.json')

function readJson (p) {
	return JSON.parse(fs.readFileSync(p, 'utf8'))
}

if (!fs.existsSync(enFile)) {
	console.error(`l10n-completeness-check: en.json not found: ${enFile}`)
	process.exit(2)
}

const enData = readJson(enFile)
const enTranslations = enData.translations || {}
const enKeys = Object.keys(enTranslations)

const localeFiles = fs.readdirSync(l10nDir)
	.filter((f) => f.endsWith('.json') && f !== 'en.json')
	.sort()

if (localeFiles.length === 0) {
	console.log('l10n-completeness-check: no locale files besides en.json — nothing to check')
	process.exit(0)
}

const report = []
let totalMissing = 0

for (const file of localeFiles) {
	const filePath = path.join(l10nDir, file)
	const data = readJson(filePath)
	const translations = data.translations || {}
	// A key with an empty value is as invisible to the user as a missing one:
	// Nextcloud shows nothing, not even the English source. Both count.
	const missingKeys = enKeys.filter((k) => !Object.prototype.hasOwnProperty.call(translations, k)
		|| typeof translations[k] !== 'string'
		|| translations[k] === '')

	if (missingKeys.length === 0) {
		continue
	}

	totalMissing += missingKeys.length
	report.push({ file, filePath, data, translations, missingKeys })
}

console.log(`l10n-completeness-check: ${enKeys.length} keys in en.json, `
	+ `checked ${localeFiles.length} locale file(s), `
	+ `${report.length} locale(s) with missing keys (${totalMissing} missing key instance(s) total)`)

if (report.length === 0) {
	console.log('l10n-completeness-check: OK — every locale file has every en.json key')
	process.exit(0)
}

if (WRITE) {
	for (const { file, filePath, data, translations, missingKeys } of report) {
		const appended = { ...translations }
		for (const key of missingKeys) {
			appended[key] = enTranslations[key]
		}
		data.translations = appended
		fs.writeFileSync(filePath, JSON.stringify(data, null, 4) + '\n')
		console.log(`l10n-completeness-check: WROTE ${missingKeys.length} missing key(s) into ${file} `
			+ '(placeholder value === English source). Review the diff and translate as needed.')
	}
	process.exit(0)
}

console.error(`\nl10n-completeness-check: FAIL — ${report.length} locale file(s) are missing keys present in en.json:`)
for (const { file, missingKeys } of report) {
	console.error(`  • ${file}: missing ${missingKeys.length} key(s)`)
	for (const key of missingKeys.slice(0, 5)) {
		console.error(`      ${JSON.stringify(key)}`)
	}
	if (missingKeys.length > 5) {
		console.error(`      … +${missingKeys.length - 5} more`)
	}
}
console.error('\nRun `node tests/l10n/check-l10n-completeness.js --write` to backfill the missing keys '
	+ '(English placeholder value), then translate the placeholders as needed.')
process.exit(1)
