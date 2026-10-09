/**
 * Every HTML comment in a template opens before it closes.
 *
 * Live check 2026-10-09: #1142 replaced the line that opened the "Primary
 * drives every component" comment in templates/settings/admin.php, and the
 * rest of that comment rendered as text on the admin page, ending in a bare
 * `-->`. Nothing failed: PHP does not read HTML comments and the browser shows
 * what it cannot parse.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec exclude A guard on template markup, not a behaviour a spec scenario names.
 */

import { describe, expect, it } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const ROOT = path.resolve(__dirname, '../..')

/** Every .php file under templates/, relative to the repository root. */
function templates(dir = 'templates') {
	return fs.readdirSync(path.join(ROOT, dir), { withFileTypes: true }).flatMap(
		(entry) => {
			const rel = path.join(dir, entry.name)
			if (entry.isDirectory()) {
				return templates(rel)
			}
			return entry.name.endsWith('.php') ? [rel] : []
		},
	)
}

/**
 * The line numbers of every `-->` that closes no open comment, and of a
 * comment still open at the end of the file.
 *
 * @param {string} source The template.
 * @return {string[]} One entry per problem.
 */
function strayMarkers(source) {
	const problems = []
	let open = null
	const marker = /<!--|-->/g
	let match
	while ((match = marker.exec(source)) !== null) {
		const line = source.slice(0, match.index).split('\n').length
		if (match[0] === '<!--') {
			if (open === null) {
				open = line
			}
		} else if (open === null) {
			problems.push('--> with no <!-- on line ' + line)
		} else {
			open = null
		}
	}
	if (open !== null) {
		problems.push('<!-- on line ' + open + ' is never closed')
	}
	return problems
}

describe('template comments', () => {
	it('finds the templates', () => {
		expect(templates()).toContain(path.join('templates', 'settings', 'admin.php'))
	})

	it.each(templates())('%s opens every comment it closes', (file) => {
		expect(strayMarkers(fs.readFileSync(path.join(ROOT, file), 'utf8'))).toEqual(
			[],
		)
	})
})
