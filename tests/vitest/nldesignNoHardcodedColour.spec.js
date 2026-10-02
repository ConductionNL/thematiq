/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The nldesign system stylesheets paint no colour a token set cannot reach
 * (thematiq#769).
 *
 * openspec/specs/nl-design/spec.md, scenario "Component uses color": colours
 * MUST come from CSS variables and MUST NOT be hardcoded hex or rgb values.
 * A literal such as `background: #ffffff` on the login box or on app content
 * ignores both the active token set and dark mode.
 *
 * What counts as hardcoded: a hex colour, or an rgb()/rgba()/hsl()/hsla()
 * function, in an ordinary property's value once every var() fallback has
 * been removed. A fallback inside var() is allowed, because the token wins
 * whenever it is set. Custom property declarations (`--x: #fff`) are token
 * definitions, not component styling, and defaults.css is the file that
 * defines the token defaults, so both are out of scope. Comments are not
 * declarations, so postcss never hands them to the check.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

const HARDCODED = /#[0-9a-f]{3,8}\b|\b(rgba?|hsla?)\(/i

/**
 * The nldesign system's stylesheets, read from design-systems.json, minus the
 * token defaults.
 *
 * @return {string[]} Paths relative to the app root.
 */
function nldesignStylesheets() {
	const systems = JSON.parse(
		fs.readFileSync(path.join(root, 'design-systems.json'), 'utf8'),
	)
	const nldesign = systems.find((system) => system.id === 'nldesign')

	return nldesign.stylesheets
		.map((sheet) => `css/${sheet}.css`)
		.filter((file) => file.endsWith('/defaults.css') === false)
		.filter((file) => fs.existsSync(path.join(root, file)))
}

/**
 * Remove every var(...) call, fallback included, from a value.
 *
 * Works from the innermost call outwards, so nested fallbacks such as
 * `var(--a, var(--b, #fff))` disappear completely.
 *
 * @param {string} value A declaration value.
 * @return {string} The value with no var() left in it.
 */
export function stripVars(value) {
	let previous
	let current = value
	do {
		previous = current
		current = current.replace(/var\([^()]*\)/gi, '')
	} while (current !== previous)

	return current
}

/**
 * Every declaration in a stylesheet that hardcodes a colour.
 *
 * @param {string} css  Stylesheet source.
 * @param {string} file Name used in the report.
 * @return {string[]} `file:line property: value` per offending declaration.
 */
export function hardcodedColours(css, file) {
	const offenders = []
	postcss.parse(css).walkDecls((decl) => {
		if (decl.prop.startsWith('--')) {
			return
		}
		if (HARDCODED.test(stripVars(decl.value))) {
			offenders.push(
				`${file}:${decl.source.start.line} ${decl.prop}: ${decl.value}`,
			)
		}
	})

	return offenders
}

describe('nldesign system stylesheets use tokens for colour (#769)', () => {
	it('reads the nldesign stylesheets, without defaults.css', () => {
		const files = nldesignStylesheets()
		expect(files).toContain('css/systems/nldesign/theme.css')
		expect(files).toContain('css/systems/nldesign/element-overrides.css')
		expect(files).not.toContain('css/systems/nldesign/defaults.css')
	})

	it('flags a literal and allows a var() fallback, a token definition and a comment', () => {
		const css = `
			/* background: #ffffff */
			.a { background: #ffffff !important; }
			.b { border: 1px solid rgb(0 0 0); }
			.c { color: var(--x, var(--y, #000000)); }
			.d { --token: #123456; }
		`
		expect(hardcodedColours(css, 'fixture.css')).toEqual([
			'fixture.css:3 background: #ffffff',
			'fixture.css:4 border: 1px solid rgb(0 0 0)',
		])
	})

	it('no nldesign declaration hardcodes a colour', () => {
		const offenders = nldesignStylesheets().flatMap((file) =>
			hardcodedColours(fs.readFileSync(path.join(root, file), 'utf8'), file),
		)
		expect(offenders).toEqual([])
	})
})
