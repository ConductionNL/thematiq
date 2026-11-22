/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The shared role layer, filled into a CONVERTED set with only the colours
 * that set's upstream publishes (openspec/changes/archive/2026-11-22-token-set-coverage-next-waves,
 * task 3.2).
 *
 * The nightly sync converts each organisation's theme onto the nldesign
 * vocabulary. A theme that publishes few `--utrecht-*` component tokens of its
 * own leaves most NL Design System components on their defaults. This step
 * adds the role layer's component tokens (css/tokens/vng.css) the set does not
 * declare, with the role layer's colour ramp re-pointed at the colours the
 * converter APPLIED from upstream (its primary, text, status and border
 * roles). Every role-layer token that would carry a colour the upstream does
 * not publish, and every role-layer typeface, is left out, so Nextcloud keeps
 * its own value there: the same rule as a partial brand file
 * (`namesLeftToNextcloud()` in scripts/generate-brand-set.mjs).
 *
 * It runs inside the sync, so the fill is recomputed from upstream on every
 * run and the set keeps receiving upstream releases.
 */

import { readFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import {
	buildRampTables,
	effective,
	namesLeftToNextcloud,
	readDeclarations,
	substituteLiterals,
} from '../generate-brand-set.mjs'
import { toHex } from './partial-semantic.mjs'

const ROLE_LAYER_PATH = join(dirname(fileURLToPath(import.meta.url)), '..', '..', 'css', 'tokens', 'vng.css')

/** The heading of the section this step writes. */
export const FILL_HEADING = '/* 3b. Role layer, partial fill'

/**
 * Which role-layer ramp steps a semantic role colours. A step is re-pointed
 * only when the converter applied that role from upstream.
 */
export const RAMP_ROLES = Object.freeze([
	['--nldesign-color-primary', ['blue-500']],
	['--nldesign-color-text', ['black', 'black-txt']],
	['--nldesign-color-error', ['red-300']],
	['--nldesign-color-success', ['green-300']],
	['--nldesign-color-warning', ['orange-300']],
	['--nldesign-color-border', ['gray-300']],
	['--nldesign-color-text-muted', ['gray-500']],
	['manifest:theming.background_color', ['white']],
])

/**
 * The ramp a converted set's upstream colours: one entry per step whose role
 * the converter applied from upstream, naming the role it came from.
 *
 * @param {Array<object>} report The converter's report.
 * @return {Object<string, {value: string, from: string}>} The ramp.
 */
export function rampFromReport(report) {
	const applied = new Map()
	for (const entry of report) {
		if (entry.action === 'applied' && typeof entry.target === 'string') {
			applied.set(entry.target, entry.value)
		}
	}
	const ramp = {}
	for (const [role, steps] of RAMP_ROLES) {
		const hex = applied.has(role) === true ? toHex(applied.get(role)) : null
		if (hex === null) {
			continue
		}
		for (const step of steps) {
			ramp[step] = { value: hex, from: role }
		}
	}
	return ramp
}

/** Foreground and background pairs whose names do not share a stem. */
const EXTRA_PAIRS = Object.freeze([['--tilburg-logo-color', '--tilburg-header-background-color']])

/**
 * The foreground colours whose background would not come from the same
 * source. A text colour from the role layer over a background the set leaves
 * to the bridge (or the reverse) pairs two schemes nobody matched, so the
 * foreground is left out too and both sides keep their bridge values.
 *
 * @param {Map<string, string>} roleLayer The role layer.
 * @param {Map<string, string>} declared The set's own declarations.
 * @param {Set<string>} left The role-layer names already left out.
 * @return {string[]} The foreground names to leave out.
 */
export function halfPairs(roleLayer, declared, left) {
	const pairs = [...EXTRA_PAIRS]
	for (const name of roleLayer.keys()) {
		const match = /^(.*)-color$/.exec(name)
		if (match !== null && match[1].endsWith('-background') === false && roleLayer.has(`${match[1]}-background-color`) === true) {
			pairs.push([name, `${match[1]}-background-color`])
		}
	}
	const filled = (name) => declared.has(name) === true || (roleLayer.has(name) === true && left.has(name) === false)
	return pairs
		.filter(([foreground, background]) => declared.has(foreground) === false && filled(foreground) === true && filled(background) === false)
		.map(([foreground]) => foreground)
}

/**
 * The role-layer declarations a converted set gains.
 *
 * @param {Map<string, string>} declared The set's own declarations.
 * @param {Object<string, {value: string}>} ramp The ramp from rampFromReport().
 * @param {Map<string, string>} [roleLayer] The role layer, last-wins (vng.css by default).
 * @return {Array<Array<string>>} `[name, value]` pairs, in role-layer order.
 */
export function partialRoleFill(declared, ramp, roleLayer = effective(readDeclarations(readFileSync(ROLE_LAYER_PATH, 'utf8')))) {
	const { rampByName, rampByValue } = buildRampTables({ ramp }, roleLayer)
	const first = namesLeftToNextcloud(roleLayer, rampByName, rampByValue)
	const left = namesLeftToNextcloud(roleLayer, rampByName, rampByValue, halfPairs(roleLayer, declared, first))
	const fill = []
	for (const [name, raw] of roleLayer) {
		// --denhaag-* reach a converted set through the Den Haag bridge in
		// css/public-bridge.css, which reads the set's own semantic colours.
		if (declared.has(name) === true || left.has(name) === true || name.startsWith('--nldesign-') === true || name.startsWith('--denhaag-') === true) {
			continue
		}
		fill.push([name, rampByName.get(name) ?? substituteLiterals(raw, rampByValue, new Map())])
	}
	return fill
}

/**
 * The converted CSS with its partial role-layer fill, placed before the
 * provenance comment (or the closing brace). A fill already in the CSS is
 * replaced, not doubled.
 *
 * @param {string} css The converter's output.
 * @param {Array<object>} report The converter's report.
 * @param {Map<string, string>} [roleLayer] The role layer, for tests.
 * @return {{css: string, count: number}} The CSS and how many tokens were added.
 */
export function withPartialRoleFill(css, report, roleLayer = undefined) {
	const withoutFill = removeFill(css)
	const declared = new Map()
	for (const { name, value } of readDeclarations(withoutFill)) {
		declared.set(name, value)
	}
	const fill = partialRoleFill(declared, rampFromReport(report), roleLayer)
	if (fill.length === 0) {
		return { css: withoutFill, count: 0 }
	}
	const block = [
		`\t${FILL_HEADING}: the shared role layer (css/tokens/vng.css), re-pointed at`,
		'\t *    the colours upstream publishes. A token that would carry any other colour is',
		'\t *    left out, so Nextcloud keeps its own value. Regenerated by the token sync. */',
		...fill.map(([name, value]) => `\t${name}: ${value};`),
		'',
		'',
	].join('\n')
	const provenance = withoutFill.indexOf('\t/* 4. Provenance')
	const at = provenance !== -1 ? provenance : withoutFill.lastIndexOf('}')
	return { css: withoutFill.slice(0, at) + block + withoutFill.slice(at), count: fill.length }
}

/**
 * The CSS without a fill section this step wrote before.
 *
 * @param {string} css The CSS.
 * @return {string} The CSS without it.
 */
function removeFill(css) {
	const start = css.indexOf(`\t${FILL_HEADING}`)
	if (start === -1) {
		return css
	}
	const end = css.indexOf('\n\n', css.indexOf('*/', start))
	return css.slice(0, start) + css.slice(end + 2)
}
