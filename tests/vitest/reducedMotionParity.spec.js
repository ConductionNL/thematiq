/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Every animated selector is reset under `prefers-reduced-motion`.
 *
 * The motion declarations in this app are `!important` and carry class- or
 * attribute-level specificity, so a universal `* { transition: none }` reset
 * does not reach them: between two author `!important` declarations the more
 * specific selector wins and `*` has specificity 0. The bundles therefore
 * repeat the selectors VERBATIM inside the reduced-motion block, where equal
 * specificity plus later source order is what actually wins.
 *
 * "Verbatim" is a contract that no linter enforces and that a reader will miss.
 * It was missed: qualifying the button rules to `:not(.action-button)` left the
 * resets on the bare selectors, which silently handed every button its
 * transitions back for an admin who had asked for none — on two bundles at
 * once, through review, because both halves still looked right in isolation.
 *
 * @spec openspec/specs/css-architecture/spec.md
 */

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'

const ROOT = path.resolve(__dirname, '../..')
const MEDIA = '@media (prefers-reduced-motion: reduce)'

/** Every stylesheet this app ships. */
const sheets = (function read(dir) {
	return fs.readdirSync(dir, { withFileTypes: true }).flatMap((entry) => {
		const full = path.join(dir, entry.name)
		if (entry.isDirectory()) {
			return read(full)
		}
		return entry.name.endsWith('.css') ? [full] : []
	})
})(path.join(ROOT, 'css'))

/** The selectors carrying a real (non-`none`) transition or animation. */
const moving = (body) => {
	const found = new Set()
	const rules = body
		.replace(/\/\*[\s\S]*?\*\//g, '')
		.matchAll(/([^{}]+)\{([^{}]*)\}/g)
	for (const rule of rules) {
		if (!/(^|[;\s])(transition|animation)\s*:/.test(rule[2])) {
			continue
		}
		if (/(transition|animation)\s*:\s*none/.test(rule[2])) {
			continue
		}
		rule[1]
			.split(',')
			.map((selector) => selector.trim())
			.filter(Boolean)
			.forEach((selector) => found.add(selector))
	}
	return found
}

/** The selectors the reduced-motion block silences. */
const reset = (block) =>
	new Set(
		[...block.matchAll(/([^{}]+)\{/g)]
			.flatMap((rule) => rule[1].split(',').map((selector) => selector.trim()))
			.filter(Boolean),
	)

describe('reduced motion, per stylesheet', () => {
	sheets.forEach((file) => {
		const name = path.relative(ROOT, file).split(path.sep).join('/')
		const source = fs.readFileSync(file, 'utf8')
		const at = source.indexOf(MEDIA)
		const before = at < 0 ? source : source.slice(0, at)
		const animated = [...moving(before)]

		if (animated.length === 0) {
			return
		}

		it(`${name} silences every selector it animates`, () => {
			// A file that animates at all must carry the block.
			expect(at, `${name} animates but has no ${MEDIA} block`).toBeGreaterThan(
				-1,
			)

			const silenced = reset(source.slice(at))
			const unreset = animated.filter((selector) => !silenced.has(selector))

			// Verbatim, not merely present: a reset whose selector differs by so
			// much as a :not() loses to the rule it is meant to undo.
			expect(unreset).toEqual([])
		})
	})
})
