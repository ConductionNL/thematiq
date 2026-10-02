/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The supported-range mirror the selector-liveness survey relies on
 * (thematiq#270).
 *
 * MAX_SUPPORTED_NC already refused to defer a selector to a newer server once
 * the survey reached the newest one. Its mirror did not exist, so eight
 * selectors stayed excused as "fallbacks for older Nextcloud releases" while
 * they matched nothing on NC 32, the oldest release this app is offered to.
 */

import fs from 'fs'
import path from 'path'
import { fileURLToPath } from 'url'
import postcss from 'postcss'
import { describe, it, expect } from 'vitest'
import {
	MAX_SUPPORTED_NC,
	MIN_SUPPORTED_NC,
	excusesAsOlderServerFallback,
	expiredOlderServerExcuses,
} from '../e2e/spec-coverage/_supported-range'

const root = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '../..')

/**
 * The `<nextcloud>` dependency range appinfo/info.xml declares.
 *
 * @return {{min: number, max: number}} The two majors.
 */
function declaredRange(): { min: number; max: number } {
	const xml = fs.readFileSync(path.join(root, 'appinfo/info.xml'), 'utf8')
	const match = xml.match(
		/<nextcloud\s+min-version="(\d+)"\s+max-version="(\d+)"\s*\/>/,
	)
	expect(match, 'appinfo/info.xml declares a <nextcloud> range').not.toBeNull()

	return { min: Number(match![1]), max: Number(match![2]) }
}

describe('supported Nextcloud range', () => {
	it('mirrors both ends of appinfo/info.xml', () => {
		expect(MIN_SUPPORTED_NC).toBe(declaredRange().min)
		expect(MAX_SUPPORTED_NC).toBe(declaredRange().max)
	})
})

describe('older-server excuses expire on the oldest server', () => {
	const reasons: Record<string, string> = {
		'#header .header-left a':
			'Pre-Vue header classes retained as fallbacks for older Nextcloud releases.',
		'#app-navigation .x':
			'Deliberate pre-NC34 fallback; kept so the theme still applies on older servers.',
		'.modal-container .x':
			'Transient overlays, only in the DOM while open, never on a page at rest.',
	}
	const reasonFor = (selector: string): string | null => reasons[selector] ?? null
	const dead = Object.keys(reasons).concat(['.unexcused'])

	it('reads an older-server claim off the reason text', () => {
		expect(excusesAsOlderServerFallback(reasons['#header .header-left a'])).toBe(
			true,
		)
		expect(excusesAsOlderServerFallback(reasons['#app-navigation .x'])).toBe(
			true,
		)
		expect(excusesAsOlderServerFallback(reasons['.modal-container .x'])).toBe(
			false,
		)
	})

	it('refuses those excuses when the survey ran on the oldest supported major', () => {
		expect(expiredOlderServerExcuses(dead, reasonFor, MIN_SUPPORTED_NC)).toEqual(
			['#app-navigation .x', '#header .header-left a'],
		)
	})

	it('keeps them while a newer major was surveyed, or the major is unknown', () => {
		expect(
			expiredOlderServerExcuses(dead, reasonFor, MIN_SUPPORTED_NC + 1),
		).toEqual([])
		expect(expiredOlderServerExcuses(dead, reasonFor, 0)).toEqual([])
	})
})

describe('the eight selectors excused as older-server fallbacks', () => {
	// Measured 0 on NC 32.0.12 and NC 34.0.2 alike (thematiq#270), and absent
	// from the NC 32.0.5 and 35.0.1 server sources. `.header-appname` was the
	// eighth: it stays, because public pages render it in the header
	// (core/templates/layout.public.php, 32 through 35).
	const DEAD = [
		'#header .header-left a',
		'#header .header-right a',
		'#header .header-right button',
		'#header .menutoggle',
		'#header .unified-search__button',
		'#header .header-start .icon-vue',
		'#header .unified-search__input',
	]

	it('are gone from the La Suite element overrides the survey reads', () => {
		const css = fs.readFileSync(
			path.join(root, 'css/systems/lasuite/element-overrides.css'),
			'utf8',
		)
		const selectors: string[] = []
		postcss.parse(css).walkRules((rule) => {
			rule.selectors.forEach((selector) =>
				selectors.push(selector.replace(/\s+/g, ' ')),
			)
		})

		expect(DEAD.filter((selector) => selectors.includes(selector))).toEqual([])
		expect(selectors).toContain('#header .header-appname')
	})
})
