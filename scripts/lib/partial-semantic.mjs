/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The --nldesign-* tokens a PARTIAL brand file does not name, completed
 * without inventing a colour (openspec/changes/archive/2026-11-22-token-set-coverage-next-waves,
 * task 1.2).
 *
 * The converter reads the generated set the way it reads any theme, and each
 * of its answers is kept only when it is the organisation's own value or
 * Nextcloud's:
 *   - a value the set itself assigns to the role (the brand's own component
 *     mapping, or a palette step it names);
 *   - Nextcloud's own default, or Nextcloud's own derivation from a published
 *     colour (a hover darkened from the primary, the light tint, an `r, g, b`
 *     triple, the text colour that reads on a background);
 *   - a step picked from the set's palette only when that step is one the
 *     organisation publishes and is as light or dark as the role's Nextcloud
 *     default; otherwise that default.
 * A colour the converter adjusted for contrast is replaced by the rule's own
 * fallback, so no shade appears that neither the organisation nor Nextcloud
 * chose.
 */

/**
 * A colour as lower-case 6-digit hex, or null for anything else. Reads hex
 * and the space-separated `hsl()` the NL Design System packages publish.
 *
 * @param {string} value A colour value.
 * @return {string|null} The hex, or null.
 */
export function toHex(value) {
	const text = String(value).trim().toLowerCase()
	if (/^#[0-9a-f]{6}$/.test(text) === true) {
		return text
	}
	if (/^#[0-9a-f]{3}$/.test(text) === true) {
		return '#' + text[1] + text[1] + text[2] + text[2] + text[3] + text[3]
	}
	const hsl = /^hsl\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%\s*\)$/.exec(text)
	if (hsl === null) {
		return null
	}
	const [h, s, l] = [Number(hsl[1]) / 360, Number(hsl[2]) / 100, Number(hsl[3]) / 100]
	const q = l < 0.5 ? l * (1 + s) : l + s - l * s
	const p = 2 * l - q
	const channel = (t) => {
		const u = t < 0 ? t + 1 : (t > 1 ? t - 1 : t)
		if (u < 1 / 6) return p + (q - p) * 6 * u
		if (u < 1 / 2) return q
		if (u < 2 / 3) return p + (q - p) * (2 / 3 - u) * 6
		return p
	}
	const rgb = s === 0 ? [l, l, l] : [channel(h + 1 / 3), channel(h), channel(h - 1 / 3)]
	return '#' + rgb.map((c) => Math.round(c * 255).toString(16).padStart(2, '0')).join('')
}

/**
 * Relative luminance of a hex colour (WCAG 2).
 *
 * @param {string} hex A 6-digit hex colour.
 * @return {number} The luminance.
 */
function luminance(hex) {
	const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
		.map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
	return 0.2126 * r + 0.7152 * g + 0.0722 * b
}

/**
 * The light or dark colour that reads better on a background, as Nextcloud
 * picks its text colour on the primary.
 *
 * @param {string} background The background hex.
 * @param {{light?: string, dark?: string}} args The two candidates.
 * @return {string} The candidate with the higher contrast.
 */
function onColor(background, args) {
	const light = args?.light ?? '#ffffff'
	const dark = args?.dark ?? '#000000'
	const ratio = (a, b) => {
		const [x, y] = [luminance(a), luminance(b)].sort((m, n) => n - m)
		return (x + 0.05) / (y + 0.05)
	}
	return ratio(background, light) >= ratio(background, dark) ? light : dark
}

/**
 * Whether a published colour picked for a role is as light or as dark as the
 * role's Nextcloud default. A small palette offers the picker a text grey for
 * a background step; that pick is refused and Nextcloud's default kept.
 *
 * @param {string} hex The picked colour.
 * @param {string|null} fallback The role's Nextcloud default.
 * @return {boolean} True when the pick suits the role.
 */
function suitsRole(hex, fallback) {
	const reference = fallback === null ? null : toHex(fallback)
	if (reference === null) {
		return true
	}
	return Math.abs(luminance(hex) - luminance(reference)) <= 0.35
}

/** Reasons whose value is Nextcloud's own, or derived the way Nextcloud derives it. */
const NEXTCLOUD_REASONS = ['nextcloud-default-used', 'geometry-kept-nextcloud', 'derived-from-header']

/**
 * The value to keep for one converter answer, or null to leave the token out.
 *
 * @param {object} entry The converter's report entry.
 * @param {object|undefined} rule The mapping rule for the entry's target.
 * @param {Set<string>} published The hex colours the organisation publishes.
 * @param {Map<string, string>} chosen The values kept so far, by target.
 * @return {string|null} The value, or null.
 */
export function keptValue(entry, rule, published, chosen) {
	if (entry.action === 'applied') {
		// A colour role answered with no colour (`transparent`) reads as nothing
		// against its pair; Nextcloud's own default stands in.
		const isColourRole = /-color-/.test(String(entry.target)) === true && /-rgb$/.test(String(entry.target)) === false
		if (isColourRole === true && /#|rgb|hsl/i.test(String(entry.value)) === false) {
			return rule?.fallback?.default ?? (rule?.fallback?.kind === 'literal' ? rule.fallback.value : null)
		}
		return entry.value
	}
	if (entry.action !== 'adapted') {
		return null
	}
	if (NEXTCLOUD_REASONS.includes(entry.reason) === true) {
		return entry.value
	}
	if (entry.reason === 'derived-from-brand') {
		if (String(entry.source).startsWith('--nldesign-') === true) {
			return entry.value
		}
		const hex = toHex(entry.value)
		const fallback = rule?.fallback?.default ?? null
		return hex !== null && published.has(hex) === true && suitsRole(hex, fallback) === true ? entry.value : fallback
	}
	if (entry.reason === 'contrast-adjusted') {
		const fallback = rule?.fallback
		if (fallback?.kind === 'derive' && fallback.transform === 'onColor' && chosen.has(fallback.from) === true) {
			const base = toHex(chosen.get(fallback.from))
			return base === null ? null : onColor(base, fallback.args)
		}
		return fallback?.default ?? (fallback?.kind === 'literal' ? fallback.value : null)
	}
	return null
}

/**
 * Complete a partial brand's semantic layer.
 *
 * @param {object} options What to complete.
 * @param {Array<object>} options.report The converter's report over the generated set.
 * @param {Array<object>} options.rules The mapping table's rules.
 * @param {Set<string>} options.declared The --nldesign-* names the brand already declares.
 * @param {Map<string, string>} options.declaredValues Those names with their values.
 * @param {Set<string>} options.published The hex colours the organisation publishes.
 * @return {Array<{name: string, value: string}>} The tokens to add, in report order.
 */
export function completeSemanticLayer({ report, rules, declared, declaredValues, published }) {
	const ruleByTarget = new Map(rules.map((rule) => [rule.target, rule]))
	const chosen = new Map(declaredValues)
	const added = []
	for (const entry of report) {
		const target = String(entry.target ?? '')
		if (target.startsWith('--nldesign-') === false || declared.has(target) === true || chosen.has(target) === true) {
			continue
		}
		const value = keptValue(entry, ruleByTarget.get(target), published, chosen)
		if (value !== null && value !== '') {
			chosen.set(target, value)
			added.push({ name: target, value })
		}
	}
	return added
}
