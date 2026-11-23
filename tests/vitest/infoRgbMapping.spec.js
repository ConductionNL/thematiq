/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * `--color-info-rgb` follows the set's info colour (token-set-coverage-next-waves 2.2).
 *
 * overrides.css maps Nextcloud's `--color-info-rgb` to
 * `--nldesign-color-info-rgb`. The triple must name the same colour as
 * `--color-info`, and a wash of it behind body text must keep that text
 * legible: Nextcloud draws its warning box as
 * `rgba(var(--color-warning-rgb), 0.2)` under `--color-main-text`, and an info
 * wash is measured the same way, on the set's page background (Nextcloud's own where the set sets none), in the light
 * theme and in both dark scopes.
 *
 * @spec openspec/specs/token-sets/spec.md
 */

import { describe, expect, it } from 'vitest'
import { ENVIRONMENTS, SETS, bundle, cascade, exists, reachesBody, read, ratio, resolve, rgb } from './support/setCascade.js'

const WASH_ALPHA = 0.2

// Nextcloud's own --color-main-background (DefaultTheme, DarkTheme), for a set that sets no page colour.
const NC_LIGHT_PAGE = '#ffffff'
const NC_DARK_PAGE = '#171717'

/**
 * A hex colour for an `r, g, b` triple.
 *
 * @param {string} triple The triple.
 * @return {string} #rrggbb.
 */
function hexOf(triple) {
	const parts = triple.split(',').map((x) => Number(x.trim()))
	if (
		parts.length !== 3
		|| parts.some((x) => !Number.isInteger(x) || x < 0 || x > 255)
	) {
		throw new Error(`not an r, g, b triple: "${triple}"`)
	}
	return '#' + parts.map((x) => x.toString(16).padStart(2, '0')).join('')
}

/**
 * The opaque channels of a colour as #rrggbb, its alpha dropped: the triple
 * carries no alpha, and a translucent info colour (xxllnc) keeps its own.
 *
 * @param {string} value #rgb, #rrggbb, #rrggbbaa or rgb()/rgba().
 * @return {string} #rrggbb.
 */
function channels(value) {
	const m = value.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i)
	if (m) {
		return hexOf(m.slice(1, 4).join(','))
	}
	return (
		'#'
		+ rgb(value)
			.map((x) => x.toString(16).padStart(2, '0'))
			.join('')
	)
}

/**
 * The colour a wash of `top` at `alpha` gives over `base`.
 *
 * @param {string} top The washed colour.
 * @param {string} base The opaque colour below.
 * @param {number} alpha The wash opacity.
 * @return {string} #rrggbb.
 */
function over(top, base, alpha) {
	const t = rgb(top)
	const b = rgb(base)
	return (
		'#'
		+ t
			.map((x, i) =>
				Math.round(alpha * x + (1 - alpha) * b[i])
					.toString(16)
					.padStart(2, '0'),
			)
			.join('')
	)
}

/**
 * What one set paints, in one environment.
 *
 * @param {object} set The token-sets.json entry.
 * @param {object} env The environment.
 * @return {object} The resolved colours.
 */
function paint(set, env) {
	const vars = cascade(bundle(set), env, (s) => reachesBody(s, env))
	const info = resolve('var(--color-info)', vars)
	const infoRgb = hexOf(resolve('var(--color-info-rgb)', vars))
	const dark = env.scheme === 'dark' || env.themes === 'dark'
	const page = resolve(
		`var(--nldesign-color-background, ${dark ? NC_DARK_PAGE : NC_LIGHT_PAGE})`,
		vars,
	)
	const text = resolve('var(--color-main-text)', vars)
	return { info, infoRgb, page, text, wash: over(infoRgb, page, WASH_ALPHA) }
}

/**
 * Run a check on every set that ships the environment.
 *
 * @param {object} env The environment.
 * @param {(set: object, p: object) => string|null} check A failure, or null.
 * @return {Record<string, string>} Failures by set id.
 */
function everySet(env, check) {
	const all = {}
	for (const set of SETS) {
		if (
			(env.scheme === 'dark' || env.themes !== 'light')
			&& !exists(`css/tokens/dark/${set.id}.css`)
		) {
			continue
		}
		const failure = check(set, paint(set, env))
		if (failure !== null) {
			all[set.id] = failure
		}
	}
	return all
}

describe('--color-info-rgb follows the set (token-set-coverage-next-waves 2.2)', () => {
	it('is mapped in overrides.css, not left to Nextcloud', () => {
		expect(read('css/systems/nldesign/overrides.css')).toMatch(
			/^\s*--color-info-rgb:\s*var\(--nldesign-color-info-rgb\)\s*!important;/m,
		)
	})

	it('covers the shipped sets and their dark variants', () => {
		expect(SETS.length).toBeGreaterThan(40)
		expect(
			SETS.filter((s) => exists(`css/tokens/dark/${s.id}.css`)).length,
		).toBeGreaterThan(40)
	})

	for (const [envName, env] of Object.entries(ENVIRONMENTS)) {
		it(`names the same colour as --color-info in the ${envName}`, () => {
			expect(
				everySet(env, (set, p) =>
					p.infoRgb === channels(p.info)
						? null
						: `${p.infoRgb} vs ${p.info}`,
				),
			).toEqual({})
		})

		it(`keeps body text at 4.5:1 on a ${WASH_ALPHA * 100}% info wash in the ${envName}`, () => {
			expect(
				everySet(env, (set, p) => {
					const r = ratio(p.text, p.wash)
					return r >= 4.5
						? null
						: `${p.text} on ${p.wash} (${p.infoRgb} over ${p.page}) = ${r.toFixed(2)}:1`
				}),
			).toEqual({})
		})
	}
})
