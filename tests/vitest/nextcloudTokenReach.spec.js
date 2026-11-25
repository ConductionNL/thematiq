/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Each Nextcloud component and slot token has a recorded reach, and the record
 * is proved against the inventory and the design system rather than believed.
 * `scripts/mapping/nextcloud-token-reach.json` holds the measurement, one entry
 * per token; this file keeps it true.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'
import { render } from '../../scripts/inventory/generate-nextcloud-token-reach.mjs'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const stripComments = (css) => css.replace(/\/\*[\s\S]*?\*\//g, '')

const REACH_FILE = 'scripts/mapping/nextcloud-token-reach.json'
const REACHES = ['follows', 'literal', 'use-site', 'unmapped']

const internal = JSON.parse(read('scripts/mapping/internal-tokens.json')).tokens
const nextcloud = Object.keys(internal)
	.filter((token) => internal[token].owner !== 'cn')
	.sort()
const record = JSON.parse(read(REACH_FILE))
const reach = record.tokens
const theme = stripComments(read('css/systems/nldesign/theme.css'))
const defaults = stripComments(read('css/systems/nldesign/defaults.css'))
const internalVariables = new Set(
	Object.values(internal).map((token) => token.variable),
)

const variablesIn = (value) =>
	[...value.matchAll(/var\(\s*(--[\w-]+)/g)].map((match) => match[1])
const mapsFromSetToken = (variable) =>
	new RegExp(
		`${variable}:\\s*var\\(\\s*(?:--[\\w-]+\\s*,\\s*var\\(\\s*)?--nldesign-[\\w-]+\\s*\\)`,
	).test(theme)
const isInherited = (token) => reach[token].inherits !== undefined

describe('the recorded reach of the Nextcloud internal tokens', () => {
	it('covers every Nextcloud component and slot token of the inventory and nothing else', () => {
		expect(nextcloud.length).toBeGreaterThan(400)
		expect(Object.keys(reach).sort()).toEqual(nextcloud)
	})

	it('is the file the generator writes, and its counts match its entries', () => {
		expect(read(REACH_FILE)).toBe(render())
		for (const name of REACHES) {
			const entries = nextcloud.filter((token) => reach[token].reach === name)
			expect(record.counts[name], name).toBe(entries.length)
		}
	})

	it('gives each token one of the four reaches', () => {
		for (const token of nextcloud) {
			expect(REACHES, token).toContain(reach[token].reach)
		}
	})

	it('proves a directly following token: its stock value reads a Nextcloud variable the design system maps from a set token', () => {
		const following = nextcloud.filter(
			(token) =>
				reach[token].reach === 'follows' && isInherited(token) === false,
		)
		expect(following.length).toBeGreaterThan(0)
		for (const token of following) {
			const { through, driver } = reach[token]
			expect(internal[token].stock, token).toContain(`var(${through}`)
			expect(theme, token).toMatch(
				new RegExp(
					`${through}:\\s*var\\(\\s*(?:--[\\w-]+\\s*,\\s*var\\(\\s*)?${driver}\\s*\\)`,
				),
			)
			expect(defaults, `${token}: ${driver}`).toMatch(
				new RegExp(`(^|[\\s;{])${driver}\\s*:`),
			)
		}
	})

	it('records as following every token whose stock value reads a mapped variable', () => {
		for (const token of nextcloud) {
			const stock = internal[token].stock ?? ''
			if (variablesIn(stock).some(mapsFromSetToken) === true) {
				expect(reach[token].reach, token).toBe('follows')
				expect(isInherited(token), token).toBe(false)
			}
		}
	})

	it('proves a direct literal token: its stock value reads no variable, a fixed colour included', () => {
		for (const token of nextcloud) {
			if (reach[token].reach !== 'literal' || isInherited(token) === true) {
				continue
			}
			expect(typeof internal[token].stock, token).toBe('string')
			expect(internal[token].stock, token).not.toContain('var(')
		}
	})

	it('proves a use-site token: nothing declares it, so only its reader holds a fallback', () => {
		for (const token of nextcloud) {
			if (reach[token].reach !== 'use-site') {
				continue
			}
			expect(internal[token].stock, token).toBeUndefined()
			expect(internal[token].mode, token).toBe('body')
		}
	})

	it('proves a direct unmapped token: it reads variables, and no set token drives any of them', () => {
		for (const token of nextcloud) {
			if (reach[token].reach !== 'unmapped' || isInherited(token) === true) {
				continue
			}
			const read = variablesIn(internal[token].stock)
			expect(read.some(mapsFromSetToken), token).toBe(false)
			expect(reach[token].reads.length, token).toBeGreaterThan(0)
			for (const variable of reach[token].reads) {
				expect(read, token).toContain(variable)
				expect(
					internalVariables.has(variable),
					`${token}: ${variable}`,
				).toBe(false)
			}
		}
	})

	it('gives a token that reads only other internal tokens the reach of the first one it reads', () => {
		const inheriting = nextcloud.filter(isInherited)
		expect(inheriting.length).toBeGreaterThan(0)
		for (const token of inheriting) {
			const parent = reach[token].inherits
			const read = variablesIn(internal[token].stock)
			expect(read[0], token).toBe(internal[parent].variable)
			expect(
				read.every((variable) => internalVariables.has(variable)),
				token,
			).toBe(true)
			const parentReach = reach[parent]?.reach
			if (parentReach !== undefined) {
				expect(reach[token].reach, token).toBe(parentReach)
			}
		}
	})

	it('records the fixed colours of the PDF viewer, code highlighting and the date picker as literal', () => {
		const fixedColours = nextcloud.filter(
			(token) =>
				['alt', 'hljs', 'dp'].includes(internal[token].owner)
				&& internal[token].type === 'color'
				&& typeof internal[token].stock === 'string'
				&& internal[token].stock.includes('var(') === false,
		)
		expect(fixedColours.length).toBeGreaterThan(0)
		for (const token of fixedColours) {
			expect(reach[token].reach, token).toBe('literal')
		}
		expect(reach['--nldesign-nc-alt-text-active-bg-color']).toEqual({
			reach: 'literal',
			inherits: '--nldesign-nc-alt-text-opacity',
		})
	})
})
