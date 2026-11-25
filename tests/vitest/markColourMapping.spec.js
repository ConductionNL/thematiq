/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * `--color-mark` follows a set's mark colour only where it stays legible
 * (token-set-coverage-next-waves 2.2.2, Q-thematiq-12).
 *
 * Nextcloud paints `<mark>` (search hits) with `--color-mark` behind the
 * inherited body text, `--color-main-text`, not behind the set's
 * `--utrecht-mark-color`. A set's `--utrecht-mark-background-color` may
 * become the mark colour only when that pair keeps 4.5:1 in the light theme
 * and in both dark scopes. Every other set keeps Nextcloud's own tint
 * (#fff0c7, dark #4d3800). The measured ratios per set are recorded in
 * docs/reference/token-set-mark-colour.md.
 *
 * @spec openspec/specs/token-sets/spec.md
 */

import { describe, expect, it } from 'vitest'
import {
	ENVIRONMENTS,
	SETS,
	bundle,
	cascade,
	exists,
	reachesBody,
	read,
	ratio,
	resolve,
} from './support/setCascade.js'

// The sets whose mark colour is Nextcloud's mark colour (Q-thematiq-12, 19 Nov 2026).
const MAPPED = ['hoeksche-waard']

const MARK_SETS = SETS.filter((s) =>
	/--utrecht-mark-background-color\s*:/.test(read(`css/tokens/${s.id}.css`)),
)

/**
 * What a set gives `<mark>` in one environment: its own mark background, the
 * mapped token, and the body text above it.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {{mark: string|null, mapped: string|null, text: string, ratio: number|null}} The resolved colours.
 */
function paint(set, env) {
	const vars = cascade(bundle(set), env, (s) => reachesBody(s, env))
	const text = resolve('var(--color-main-text)', vars)
	const mark =
		vars['--utrecht-mark-background-color'] === undefined
			? null
			: resolve('var(--utrecht-mark-background-color)', vars)
	const mapped =
		vars['--nldesign-nc-color-mark'] === undefined
			? null
			: resolve('var(--nldesign-nc-color-mark)', vars)
	return { mark, mapped, text, ratio: mark === null ? null : ratio(text, mark) }
}

/**
 * Whether a set ships the environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {boolean} True when it does.
 */
function ships(set, env) {
	return env.themes === 'light' || exists(`css/tokens/dark/${set.id}.css`)
}

describe('--color-mark follows the set only where it stays legible (next-waves 2.2.2)', () => {
	it('stays unmapped in overrides.css, for every set', () => {
		expect(read('css/systems/nldesign/overrides.css')).not.toMatch(
			/^\s*--color-mark\s*:/m,
		)
	})

	it('measures the sets that declare a mark background', () => {
		expect(MARK_SETS.length).toBeGreaterThanOrEqual(7)
		for (const id of MAPPED) {
			expect(MARK_SETS.map((s) => s.id)).toContain(id)
		}
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`maps exactly the sets whose mark keeps body text at 4.5:1 in every mode, in the ${envName}`, () => {
			const mappedHere = []
			const failures = {}
			for (const set of MARK_SETS.filter((s) => ships(s, env))) {
				const p = paint(set, env)
				if (p.mapped !== null) {
					mappedHere.push(set.id)
					if (p.mapped !== p.mark) {
						failures[set.id] = `maps ${p.mapped}, its mark is ${p.mark}`
					} else if (p.ratio < 4.5) {
						failures[set.id] =
							`${p.text} on ${p.mark} = ${p.ratio.toFixed(2)}:1`
					}
				}
			}
			expect(failures).toEqual({})
			expect(mappedHere.sort()).toEqual(
				MAPPED.filter((id) =>
					ships(
						SETS.find((s) => s.id === id),
						env,
					),
				),
			)
		})
	}

	it("leaves Nextcloud's tint on every set where some mode would fall under 4.5:1", () => {
		for (const set of MARK_SETS.filter((s) => !MAPPED.includes(s.id))) {
			const below = Object.values(ENVIRONMENTS)
				.filter((env) => ships(set, env))
				.some((env) => {
					const p = paint(set, env)
					return p.ratio === null || p.ratio < 4.5
				})
			expect([set.id, below]).toEqual([set.id, true])
		}
	})
})
