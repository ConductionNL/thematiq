/**
 * Guard: every translation call asks for the `thematiq` domain.
 *
 * The app id moved from `nldesign` to `thematiq` in b7b65de4, and the l10n
 * catalogues register under `thematiq`. Calls left on the old domain find no
 * catalogue and show the English key in every language (#660: 43 strings on
 * the admin page and the preview banner). Prettier splits long calls over
 * several lines, so a single-line grep misses most of them: this scans whole
 * files.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */

import { describe, expect, it } from 'vitest'
import fs from 'fs'
import path from 'path'

const ROOT = path.resolve(__dirname, '../..')

/** Every file under a directory with one of the extensions, recursively. */
function filesUnder(dir, extensions) {
	const abs = path.join(ROOT, dir)
	if (fs.existsSync(abs) === false) {
		return []
	}
	return fs.readdirSync(abs, { withFileTypes: true }).flatMap((entry) => {
		const rel = path.join(dir, entry.name)
		if (entry.isDirectory()) {
			return filesUnder(rel, extensions)
		}
		return extensions.includes(path.extname(entry.name)) ? [rel] : []
	})
}

/** JS translation calls: t(), n(), translate(), translatePlural(). */
const JS_OLD_DOMAIN = /\b(?:t|n|translate|translatePlural)\(\s*['"]nldesign['"]/g

/** PHP lookups of a catalogue by app id. */
const PHP_OLD_DOMAIN = /(?:getL10N|->get|L10N::get)\(\s*(?:appId:\s*|app:\s*)?['"]nldesign['"]/g

/** The offending calls in a set of files, as `file:line`. */
function offenders(files, pattern) {
	return files.flatMap((file) => {
		const src = fs.readFileSync(path.join(ROOT, file), 'utf8')
		return [...src.matchAll(pattern)].map(
			(m) => file + ':' + src.slice(0, m.index).split('\n').length,
		)
	})
}

describe('translation domain', () => {
	it('has no JS translation call on the old nldesign domain', () => {
		const files = [
			...filesUnder('js', ['.js']),
			...filesUnder('src', ['.js', '.ts', '.vue']),
		]
		expect(files.length).toBeGreaterThan(0)
		expect(offenders(files, JS_OLD_DOMAIN)).toEqual([])
	})

	it('has no PHP catalogue lookup on the old nldesign domain', () => {
		const files = [
			...filesUnder('lib', ['.php']),
			...filesUnder('templates', ['.php']),
		]
		expect(files.length).toBeGreaterThan(0)
		expect(offenders(files, PHP_OLD_DOMAIN)).toEqual([])
	})
})
