/**
 * The button icons the Tq boards draw (css/admin-page.css, data-thematiq-icon):
 * every icon a button names has a rule with its own MDI path, and the buttons
 * the Theming page template draws name an icon that exists.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-each-tool-is-the-page-or-panel-its-board-draws-req-aset-103
 */

import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { describe, expect, it } from 'vitest'

const root = join(__dirname, '..', '..')
const css = readFileSync(join(root, 'css', 'admin-page.css'), 'utf8')
const defined = new Set(
	[...css.matchAll(/\[data-thematiq-icon='([a-z-]+)'\] \{\n\t--thematiq-button-icon: url\("data:image\/svg\+xml,[^"]+%3Cpath d='M[^']+'/g)].map(
		(m) => m[1],
	),
)

function used(text, pattern) {
	return [...text.matchAll(pattern)].map((m) => m[1])
}

describe('button icons', () => {
	it('defines every icon once, with a path', () => {
		const names = used(css, /\[data-thematiq-icon='([a-z-]+)'\] \{/g)
		expect(new Set(names).size).toBe(names.length)
		expect([...defined].sort()).toEqual([...names].sort())
		expect(defined.has('download')).toBe(true)
		expect(defined.has('chevron-down')).toBe(true)
	})

	it('names only defined icons in the template and the panel scripts', () => {
		const template = readFileSync(join(root, 'templates', 'settings', 'admin.php'), 'utf8')
		const scripts = [
			'admin.js',
			'admin-panels.js',
			'admin-bundle-panel.js',
			'admin-contrast-panel.js',
			'admin-own-sets-panel.js',
			'admin-own-tokens-panel.js',
			'admin-fonts-panel.js',
			'admin-log-panel.js',
			'admin-brands-panel.js',
			'admin-switches-panel.js',
			'admin-layout-panel.js',
			'admin-components-panel.js',
			'admin-motion-panel.js',
			'admin-workspace-icons.js',
		].map((file) => readFileSync(join(root, 'js', file), 'utf8')).join('\n')
		const names = [
			...used(template, /data-thematiq-icon="([a-z-]+)"/g),
			...used(scripts, /data-thematiq-icon="([a-z-]+)"/g),
			...used(scripts, /thematiqIcon = '([a-z-]+)'/g),
			...used(scripts, /'(download|upload)' : '(?:download|upload)'/g),
			...used(scripts, /,\s*'([a-z-]+)',?\s*\)/g).filter((n) => defined.has(n)),
		]
		expect(names.length).toBeGreaterThan(15)
		names.forEach((name) => expect(defined.has(name), name).toBe(true))
	})
})
