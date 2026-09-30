/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The browser half of the simple brand form: derives a complete token set from a
 * primary and a background colour by the rules in `scripts/mapping/brand-form.json`,
 * exactly as `lib/Service/BrandFormService.php` does, so the preview shows what is stored.
 * The rules and the defaults layer come from the server (initial state `brandForm`).
 *
 * Dual-mode like `tokenConverter.js`: `module.exports` under Node, `window.NldesignBrandForm`
 * in the browser. Needs `window.NldesignTokenConverter` (or `./tokenConverter.js` under Node).
 *
 * @spec openspec/specs/simple-brand-form/spec.md
 */

;(function brandFormModule() {
	'use strict'

	var converter =
		typeof module !== 'undefined' && module.exports
			? require('./tokenConverter.js')
			: window.NldesignTokenConverter

	var TEXT_MIN = 4.5
	var UI_MIN = 3

	/**
	 * A validated, lower-case six-digit hex colour, or null.
	 *
	 * @param {string} value The input.
	 * @return {string|null} The colour.
	 */
	function hex(value) {
		var trimmed = String(value).trim().toLowerCase()
		if (!/^#([0-9a-f]{3}|[0-9a-f]{6})$/.test(trimmed)) {
			return null
		}

		return converter.mix(trimmed, trimmed, 1)
	}

	/**
	 * The contrast ratio of two colours, two decimals; 1 when either is not a colour.
	 *
	 * @param {string} first One colour.
	 * @param {string} second The other.
	 * @return {number} The ratio.
	 */
	function ratio(first, second) {
		var one = converter.parseColor(first)
		var two = converter.parseColor(second)
		if (one === null || two === null) {
			return 1
		}

		return Math.round(converter.ratio(one, two) * 100) / 100
	}

	/**
	 * Black or white, whichever contrasts better; white on a tie.
	 *
	 * @param {string} background The background.
	 * @return {string} The text colour.
	 */
	function onColor(background) {
		return ratio(background, '#ffffff') >= ratio(background, '#000000')
			? '#ffffff'
			: '#000000'
	}

	/**
	 * Apply one rule.
	 *
	 * @param {Array} rule [op, ...operands].
	 * @param {Object<string,string>} known primary, background and tokens so far.
	 * @param {Object<string,string>} defaults The defaults layer.
	 * @param {string} token The token.
	 * @return {string} The value, or ''.
	 */
	function apply(rule, known, defaults, token) {
		var first = known[rule[1]] || ''
		var fallback = defaults[token] || ''

		switch (rule[0]) {
			case 'from':
				return first
			case 'onColor':
				return onColor(first)
			case 'darken':
				return converter.darken(first, Number(rule[2] || 0))
			case 'mix':
				return converter.mix(
					first,
					known[rule[2]] || '',
					Number(rule[3] === undefined ? 1 : rule[3]),
				)
			case 'legible':
				return ratio(first, known.background) >= TEXT_MIN ? first : fallback
			default:
				return fallback
		}
	}

	/**
	 * Derive the set.
	 *
	 * @param {{rules: Object<string,Array>, defaults: Object<string,string>}} inputs Rules and defaults.
	 * @param {string} primary The primary colour.
	 * @param {string} background The background colour.
	 * @return {{declarations: Object<string,string>, textOnPrimary: string, textRatio: number, uiRatio: number}|null}
	 *   The result, or null when a colour is not a hex colour.
	 */
	function derive(inputs, primary, background) {
		var base = { primary: hex(primary), background: hex(background) }
		if (base.primary === null || base.background === null) {
			return null
		}

		var declarations = {}
		Object.keys(inputs.rules).forEach(function (token) {
			declarations[token] = apply(
				inputs.rules[token],
				Object.assign({}, base, declarations),
				inputs.defaults,
				token,
			)
		})

		var onPrimary = onColor(base.primary)

		return {
			declarations: declarations,
			textOnPrimary: onPrimary,
			textRatio: ratio(base.primary, onPrimary),
			uiRatio: ratio(base.primary, base.background),
		}
	}

	var api = { derive: derive, TEXT_MIN: TEXT_MIN, UI_MIN: UI_MIN }

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else if (typeof window !== 'undefined') {
		window.NldesignBrandForm = api
	}
})()
