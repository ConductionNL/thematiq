/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * On the nldesign bundle the active app label takes the header's own text
 * colour, not the brand primary (#944).
 *
 * openspec/specs/menu-labels/spec.md, "Active state visible on all
 * backgrounds": the font-weight difference is the only active marker, and
 * the label's text colour comes from the header text colour token. The
 * nldesign element-overrides painted the active label `--color-primary`, so
 * on a set with a coloured header the active name sat in the primary on the
 * header colour: Utrecht's #24578F on its #cc0000 header is about 1.3:1.
 *
 * The browser half is tests/e2e/spec-coverage/menu-labels.spec.ts ("on a
 * white and on a coloured header ..."); this file reads the stylesheet, so it
 * runs without a server.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

const ACTIVE_LABEL =
	'#header nav.app-menu .app-menu-entry--active .app-menu-entry__label'
const LABEL = '#header nav.app-menu .app-menu-entry__label'

/**
 * The last `color` declaration of the rules whose selector list holds `selector`.
 *
 * @param {string} file The stylesheet, relative to the repo root.
 * @param {string} selector The exact selector.
 * @return {import('postcss').Declaration|undefined} The declaration.
 */
function lastColour(file, selector) {
	const css = postcss.parse(fs.readFileSync(path.join(root, file), 'utf8'))
	let found
	css.walkRules((rule) => {
		if (rule.selectors.map((s) => s.trim()).includes(selector)) {
			rule.walkDecls('color', (d) => {
				found = d
			})
		}
	})
	return found
}

describe('nldesign active menu label colour', () => {
	const file = 'css/systems/nldesign/element-overrides.css'

	it('takes the header text colour, the same as every other label', () => {
		const active = lastColour(file, ACTIVE_LABEL)
		const other = lastColour(file, LABEL)
		expect(active, `${ACTIVE_LABEL} sets a colour`).toBeDefined()
		expect(active.important).toBe(true)
		expect(active.value).toContain('--nldesign-color-header-text')
		expect(active.value).not.toContain('--color-primary')
		expect(active.value.replace(/\s+/g, '')).toBe(
			other.value.replace(/\s+/g, ''),
		)
	})
})
