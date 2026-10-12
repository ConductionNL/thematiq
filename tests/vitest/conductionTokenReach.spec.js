/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Each Conduction component token has a recorded reach, and the record is
 * proved against the inventory and the design system rather than believed.
 *
 * The plan read "no shipped set declares the 33 `--nldesign-cn-*` tokens" as
 * a gap to close by declaring them. Measured, six of them already follow the
 * set through a Nextcloud variable the set drives, and two of those six carry
 * a success, warning and error variant that one set-level value would
 * flatten. The others hold a literal size or are read with a fallback at the
 * place of use. `scripts/mapping/conduction-token-reach.json` holds that
 * measurement, one entry per token, and this file keeps it true.
 *
 * @spec openspec/changes/conduction-tokens-recorded-reach/specs/component-tokens/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const json = (rel) => JSON.parse(read(rel))
const stripComments = (css) => css.replace(/\/\*[\s\S]*?\*\//g, '')

const REACH_FILE = 'scripts/mapping/conduction-token-reach.json'
const REACHES = ['follows', 'literal', 'use-site']

const internal = json('scripts/mapping/internal-tokens.json').tokens
const conduction = Object.keys(internal)
	.filter((token) => internal[token].owner === 'cn')
	.sort()
const reach = () => json(REACH_FILE).tokens
const theme = stripComments(read('css/systems/nldesign/theme.css'))
const defaults = stripComments(read('css/systems/nldesign/defaults.css'))

/**
 * Every stylesheet under a directory, recursively.
 *
 * @param {string} dir Directory relative to the app root.
 * @return {string[]} Paths relative to the app root.
 */
const stylesheets = (dir) => {
	const found = []
	for (const entry of fs.readdirSync(path.join(ROOT, dir), {
		withFileTypes: true,
	})) {
		const rel = `${dir}/${entry.name}`
		if (entry.isDirectory() === true) {
			found.push(...stylesheets(rel))
		} else if (entry.name.endsWith('.css') === true) {
			found.push(rel)
		}
	}
	return found
}

/**
 * Whether a stylesheet declares a custom property.
 *
 * @param {string} css The stylesheet, comments removed.
 * @param {string} name The property name.
 * @return {boolean} True when the name is declared.
 */
const declares = (css, name) => new RegExp(`(^|[\\s;{])${name}\\s*:`).test(css)

describe('the recorded reach of the Conduction component tokens', () => {
	it('covers every Conduction token of the inventory and nothing else', () => {
		expect(conduction).toHaveLength(33)
		expect(Object.keys(reach()).sort()).toEqual(conduction)
	})

	it('gives each token one of the three reaches', () => {
		for (const token of conduction) {
			expect(REACHES, token).toContain(reach()[token].reach)
		}
	})

	it('proves a token that follows the set: its stock value reads a Nextcloud variable the design system maps from a set token', () => {
		const following = conduction.filter(
			(token) => reach()[token].reach === 'follows',
		)
		expect(following.length).toBeGreaterThan(0)
		for (const token of following) {
			const { through, driver } = reach()[token]
			expect(internal[token].stock, token).toContain(`var(${through}`)
			// The variable reads the driver directly, or after an optional
			// token in front of it: `var(--nldesign-color-action, var(driver))`
			// for the primary element, which a set's action colour may move.
			expect(theme, token).toMatch(
				new RegExp(
					`${through}:\\s*var\\(\\s*(?:--[\\w-]+\\s*,\\s*var\\(\\s*)?${driver}\\s*\\)`,
				),
			)
			expect(declares(defaults, driver), `${token}: ${driver}`).toBe(true)
		}
	})

	it('records as following every token whose stock value reads a mapped Nextcloud variable', () => {
		for (const token of conduction) {
			const stock = internal[token].stock ?? ''
			if (stock.includes('var(--') === true) {
				expect(reach()[token].reach, token).toBe('follows')
			}
		}
	})

	it('proves a literal token: its stock value reads no variable at all', () => {
		for (const token of conduction) {
			if (reach()[token].reach !== 'literal') {
				continue
			}
			expect(typeof internal[token].stock, token).toBe('string')
			expect(internal[token].stock, token).not.toContain('var(')
		}
	})

	it('proves a use-site token: the library declares it nowhere, so only its reader holds a fallback', () => {
		for (const token of conduction) {
			if (reach()[token].reach !== 'use-site') {
				continue
			}
			expect(internal[token].stock, token).toBeUndefined()
			expect(internal[token].mode, token).toBe('body')
			expect(reach()[token].note, token).toMatch(/\S/)
		}
	})

	it('marks a following token with variants exactly when the library declares it on more than one selector', () => {
		for (const token of conduction) {
			const entry = reach()[token]
			const several = (internal[token].selectors ?? []).length > 1
			expect(entry.variants === true, token).toBe(
				entry.reach === 'follows' && several,
			)
		}
	})

	it('keeps a token with variants out of every shipped stylesheet, where one value would replace all of them', () => {
		const withVariants = conduction.filter(
			(token) => reach()[token].variants === true,
		)
		expect(withVariants).toEqual([
			'--nldesign-cn-forms-status',
			'--nldesign-cn-kpi-accent',
		])
		for (const file of stylesheets('css')) {
			const css = stripComments(read(file))
			for (const token of withVariants) {
				expect(declares(css, token), `${file} declares ${token}`).toBe(false)
			}
		}
	})
})
