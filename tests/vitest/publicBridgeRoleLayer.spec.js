/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The public bridge carries the whole role layer the portal site reads, the
 * accent and the brand stripe (openspec/changes/brand-motif-on-portals).
 *
 * Measured before this change: a portal on `wilgenboom` resolved 116 of the
 * 489 component roles the site reads, against 489 on `example-basisschool`,
 * so the header, the logo and the DigiD button drew in browser defaults. The
 * tests below resolve a set the way a portal page does: the bridge first,
 * then the set, `var()` followed to its end.
 *
 * @spec openspec/changes/brand-motif-on-portals/specs/brand-motif-on-portals/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const SITE_ROLES = JSON.parse(
	read('tests/vitest/fixtures/portal-site-roles.json'),
).roles
const SCHOOLS = ['wilgenboom', 'vaartveld', 'esdoornveen', 'warmtepompacademie']

/**
 * Custom properties declared on a stylesheet's top-level `:root` rules.
 *
 * @param {string} css The stylesheet.
 * @return {Record<string, string>} Property to raw value.
 */
function rootTokens(css) {
	const tokens = {}
	postcss.parse(css).walkRules((rule) => {
		if (rule.parent.type === 'root' && rule.selector.trim() === ':root') {
			rule.walkDecls(/^--/, (d) => {
				tokens[d.prop] = d.value.replace(/\s+/g, ' ').trim()
			})
		}
	})
	return tokens
}

const BRIDGE = rootTokens(read('css/public-bridge.css'))

/**
 * The tokens a portal page ends up with: the bridge, then the set on top.
 *
 * @param {string} set The set id.
 * @return {Record<string, string>} The merged declarations.
 */
function pageTokens(set) {
	return { ...BRIDGE, ...rootTokens(read(`css/tokens/${set}.css`)) }
}

/**
 * Follow `var()` to its end, the way a browser computes a custom property.
 * An undeclared name without a fallback is the guaranteed-invalid value,
 * returned as null.
 *
 * @param {Record<string, string>} tokens The declarations.
 * @param {string} value A raw value.
 * @param {number} depth Recursion guard.
 * @return {string|null} The resolved value, or null.
 */
function resolve(tokens, value, depth = 0) {
	if (depth > 20) {
		return null
	}
	const start = value.indexOf('var(')
	if (start === -1) {
		return value
	}
	let level = 0
	let end = start + 4
	for (; end < value.length; end++) {
		if (value[end] === '(') level++
		if (value[end] === ')') {
			if (level === 0) break
			level--
		}
	}
	const inner = value.slice(start + 4, end)
	const comma = inner.indexOf(',')
	const name = (comma === -1 ? inner : inner.slice(0, comma)).trim()
	const fallback = comma === -1 ? null : inner.slice(comma + 1).trim()
	let replacement = null
	if (tokens[name] !== undefined) {
		replacement = resolve(tokens, tokens[name], depth + 1)
	}
	if (replacement === null && fallback !== null) {
		replacement = resolve(tokens, fallback, depth + 1)
	}
	if (replacement === null) {
		return null
	}
	return resolve(
		tokens,
		value.slice(0, start) + replacement + value.slice(end + 1),
		depth + 1,
	)
}

const token = (set, name) => {
	const tokens = pageTokens(set)
	return tokens[name] === undefined ? null : resolve(tokens, tokens[name])
}

describe('the bridge names every role the portal site reads', () => {
	it('declares each of them, so a set with only --nldesign-* paints the whole site', () => {
		const missing = SITE_ROLES.filter((role) => BRIDGE[role] === undefined)
		expect(missing).toEqual([])
	})

	it('holds at least the roles that were missing on 5 October (the control)', () => {
		expect(SITE_ROLES.length).toBeGreaterThan(350)
		for (const role of [
			'--conduction-logo-header-background-image',
			'--tilburg-navigation-background-color',
			'--utrecht-button-primary-action-border-color',
			'--tilburg-space-row-elephant',
		]) {
			expect(SITE_ROLES, role).toContain(role)
		}
	})
})

describe.each([...SCHOOLS, 'zuiddrecht'])('%s on a portal', (set) => {
	const primary = token(set, '--nldesign-color-primary')

	it('draws the primary button and the header account button in its primary', () => {
		expect(token(set, '--utrecht-button-primary-action-background-color')).toBe(
			primary,
		)
		expect(token(set, '--tilburg-navigation-background-color')).toBe(primary)
	})

	it('shows its own logo in the header and a light one on the footer band', () => {
		expect(token(set, '--conduction-logo-header-background-image')).toContain(
			`img/logos/${set}.svg`,
		)
		expect(token(set, '--conduction-logo-header-block-size')).toBe('50px')
		// The portal names the light logo in --nldesign-logo-inverse-url; with it
		// absent the footer falls back to the set's own logo.
		expect(token(set, '--conduction-logo-footer-background-image')).toContain(
			`img/logos/${set}.svg`,
		)
		expect(token(set, '--thematiq-logo-text-font-size')).toBe('0')
	})

	it('reads its accent through the three vocabulary tokens', () => {
		expect(token(set, '--thematiq-accent-color')).toBe(
			token(set, '--nldesign-color-accent'),
		)
		expect(token(set, '--thematiq-accent-light-color')).toBe(
			token(set, '--nldesign-color-accent-light'),
		)
		expect(token(set, '--thematiq-accent-text-color')).toBe(
			token(set, '--nldesign-color-accent-text'),
		)
		expect(token(set, '--nldesign-color-accent')).not.toBe(primary)
	})

	it('hands its brand stripe to the site under the library names', () => {
		expect(token(set, '--cn-brand-stripe-height')).toBe(
			token(set, '--nldesign-brand-stripe-height'),
		)
		expect(token(set, '--cn-brand-stripe-color-1')).toBe(
			token(set, '--nldesign-brand-stripe-color-1'),
		)
	})
})

describe.each(SCHOOLS)('%s accent agrees with its own palette', (set) => {
	it('declares the palette entries as the vocabulary values', () => {
		for (const step of ['', '-light', '-text']) {
			expect(token(set, `--nldesign-color-accent${step}`)).toBe(
				token(set, `--${set}-color-accent${step}`),
			)
		}
	})
})

describe('the stripe and the motif', () => {
	it('draws the motif image on the portal, and its inverse on a dark band', () => {
		expect(token('wilgenboom', '--cn-brand-stripe-image')).toMatch(
			/^url\("data:image\/svg\+xml,/,
		)
		expect(token('wilgenboom', '--cn-brand-stripe-image-inverse')).toContain(
			'%235F9A78',
		)
		expect(token('esdoornveen', '--cn-brand-stripe-image-inverse')).toContain(
			'238deg',
		)
		// No inverse of its own: the one image again.
		expect(token('warmtepompacademie', '--cn-brand-stripe-image-inverse')).toBe(
			token('warmtepompacademie', '--cn-brand-stripe-image'),
		)
	})

	it('keeps zuiddrecht at three bands: red, blue, red, 6 : 3 : 1, 5px, no image', () => {
		expect(token('zuiddrecht', '--cn-brand-stripe-image')).toBeNull()
		expect(
			[1, 2, 3].map((n) =>
				token('zuiddrecht', `--cn-brand-stripe-color-${n}`),
			),
		).toEqual(['#CC0000', '#3669A5', '#CC0000'])
		expect(
			[1, 2, 3].map((n) =>
				token('zuiddrecht', `--cn-brand-stripe-ratio-${n}`),
			),
		).toEqual(['6', '3', '1'])
		expect(token('zuiddrecht', '--cn-brand-stripe-height')).toBe('5px')
	})

	it('draws nothing for a set that declares no stripe', () => {
		expect(token('vng', '--cn-brand-stripe-height')).toBeNull()
		expect(token('vng', '--cn-brand-stripe-image')).toBeNull()
	})
})

describe('the footer bottom band', () => {
	it('is the footer ground a step darker for a set that names one, nothing otherwise', () => {
		expect(token('wilgenboom', '--thematiq-footer-legal-background-color')).toBe(
			'color-mix( in srgb, #1F4A33 80%, #000 )',
		)
		expect(token('vng', '--thematiq-footer-legal-background-color')).toBeNull()
	})
})

describe('a set with a role layer of its own keeps it', () => {
	it('example-basisschool still paints its primary button with its own value', () => {
		const own = rootTokens(read('css/tokens/example-basisschool.css'))
		const role = '--utrecht-button-primary-action-background-color'
		expect(own[role]).toBeDefined()
		expect(pageTokens('example-basisschool')[role]).toBe(own[role])
	})

	it('a set without an accent still marks the current item, in its primary', () => {
		expect(token('vng', '--thematiq-accent-color')).toBe(
			token('vng', '--nldesign-color-primary'),
		)
	})
})
