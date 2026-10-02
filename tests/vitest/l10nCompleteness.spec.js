/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The cross-locale completeness check has to fail on a missing OR empty key,
 * and the CI-required `npm run test:l10n` has to run it. A completeness script
 * that no gate invokes is the same as no script.
 *
 * @spec openspec/specs/l10n-completeness/spec.md
 */

import { spawnSync } from 'node:child_process'
import fs from 'node:fs'
import os from 'node:os'
import path from 'node:path'
import { afterEach, describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const SCRIPT = path.join(ROOT, 'tests/l10n/check-l10n-completeness.js')

const dirs = []

function fixture(locales) {
	const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'l10n-completeness-'))
	dirs.push(dir)
	for (const [name, translations] of Object.entries(locales)) {
		fs.writeFileSync(
			path.join(dir, name + '.json'),
			JSON.stringify({
				translations,
				pluralForm: 'nplurals=2; plural=(n != 1);',
			}),
		)
	}
	return dir
}

function run(dir) {
	return spawnSync(process.execPath, [SCRIPT], {
		cwd: ROOT,
		env: { ...process.env, L10N_DIR: dir },
		encoding: 'utf8',
	})
}

afterEach(() => {
	while (dirs.length > 0) {
		fs.rmSync(dirs.pop(), { recursive: true, force: true })
	}
})

describe('check-l10n-completeness.js', () => {
	it('fails and names the key and locale when a locale lacks an en.json key', () => {
		const dir = fixture({
			en: { 'Search apps': 'Search apps', Save: 'Save' },
			nl: { Save: 'Opslaan' },
		})
		const result = run(dir)
		expect(result.status).toBe(1)
		expect(result.stderr).toContain('nl.json')
		expect(result.stderr).toContain('"Search apps"')
	})

	it('fails when a locale carries the key with an empty value', () => {
		const dir = fixture({
			en: { 'Search apps': 'Search apps' },
			nl: { 'Search apps': '' },
		})
		const result = run(dir)
		expect(result.status).toBe(1)
		expect(result.stderr).toContain('"Search apps"')
	})

	it('passes and reports the locale count when every locale is complete', () => {
		const dir = fixture({
			en: { 'Search apps': 'Search apps' },
			nl: { 'Search apps': 'Apps zoeken' },
			de: { 'Search apps': 'Search apps' },
		})
		const result = run(dir)
		expect(result.status).toBe(0)
		expect(result.stdout).toContain('checked 2 locale file(s)')
	})
})

describe('npm run test:l10n', () => {
	it('runs the cross-locale completeness check, so CI catches a gap', () => {
		const pkg = JSON.parse(
			fs.readFileSync(path.join(ROOT, 'package.json'), 'utf8'),
		)
		expect(pkg.scripts['test:l10n']).toContain('check-l10n-completeness.js')
	})
})

describe('l10n/nl.json', () => {
	it('translates the app-theming dropdown strings into Dutch', () => {
		const nl = JSON.parse(
			fs.readFileSync(path.join(ROOT, 'l10n/nl.json'), 'utf8'),
		)
		for (const key of [
			'{themed} of {total} apps themed',
			'Search apps',
			'Search apps…',
		]) {
			expect(nl.translations[key]).toBeTruthy()
			expect(nl.translations[key]).not.toBe(key)
		}
		expect(nl.translations['{themed} of {total} apps themed']).toContain(
			'{themed}',
		)
		expect(nl.translations['{themed} of {total} apps themed']).toContain(
			'{total}',
		)
	})
})
