/**
 * SPDX-FileCopyrightText: 2026 Conduction / NL Design System Contributors
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Pure design-token / colour transforms for the NL Design System admin
 * settings page.
 *
 * These functions are framework-light and side-effect-free so they can be
 * unit-tested offline (Vitest, `tests/vitest/`) and reused. The admin settings
 * script (`js/admin.js`) consumes them via the `window.NldesignTokenTransforms`
 * global it registers, falling back to inline copies if this module is not
 * loaded (so a missing script can never break the live theming page).
 *
 * Dual-mode: exports via `module.exports` under Node (Vitest) and assigns to
 * `window.NldesignTokenTransforms` in the browser. No `import`/`export` syntax
 * so it can be served as a plain <script> alongside admin.js without a bundler.
 */

;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	}
	if (root) {
		root.NldesignTokenTransforms = api
	}
})(typeof self !== 'undefined' ? self : this, function () {
	'use strict'

	/**
	 * Darken a 6-digit hex colour by the given fraction (0–1).
	 * Returns the original value unchanged if it is not a #RRGGBB hex string.
	 *
	 * @param {string} hex A #RRGGBB colour.
	 * @param {number} fraction The fraction to darken by (0 = unchanged, 1 = black).
	 * @return {string} The darkened #rrggbb colour, or the original input.
	 */
	function darkenHex(hex, fraction) {
		var m = /^#([0-9a-fA-F]{6})$/.exec(hex)
		if (m === null) {
			return hex
		}
		var f = Math.min(1, Math.max(0, Number(fraction) || 0))
		var r = Math.max(0, Math.round(parseInt(m[1].substring(0, 2), 16) * (1 - f)))
		var g = Math.max(0, Math.round(parseInt(m[1].substring(2, 4), 16) * (1 - f)))
		var b = Math.max(0, Math.round(parseInt(m[1].substring(4, 6), 16) * (1 - f)))
		return '#' + pad2(r) + pad2(g) + pad2(b)
	}

	/**
	 * Two-digit lower-case hex for a 0–255 channel value.
	 *
	 * @param {number} value A channel value.
	 * @return {string} Two hex digits.
	 */
	function pad2(value) {
		return ('0' + value.toString(16)).slice(-2)
	}

	/**
	 * Derive the preview palette for a token set from its theming metadata.
	 *
	 * Mirrors the `getPreviewColors` logic in admin.js: the primary colour comes
	 * from `tokenSet.theming.primary_color`, the hover is a 10%-darker shade, and
	 * the primary text is always white. Falls back to a neutral dark when no
	 * theming metadata is present.
	 *
	 * @param {?object} tokenSet A token-set entry (from token-sets.json), or null.
	 * @return {{primary: string, primaryHover: string, primaryText: string}} The palette.
	 */
	function getPreviewColors(tokenSet) {
		var primary =
			tokenSet && tokenSet.theming && tokenSet.theming.primary_color
				? tokenSet.theming.primary_color
				: '#333333'
		return {
			primary: primary,
			primaryHover: darkenHex(primary, 0.1),
			primaryText: '#ffffff',
		}
	}

	/**
	 * Normalise a colour value into a #RRGGBB string suitable for an
	 * `<input type="color">` picker. Handles #RRGGBB (passthrough), #RGB
	 * (expanded), an `r, g, b` triplet (the shape the `-rgb` tokens take),
	 * and empty/invalid (→ #000000). Named colours / other CSS
	 * colour syntaxes are NOT resolved here (admin.js does that via a canvas in
	 * the browser); this pure helper returns the safe fallback for them.
	 *
	 * @param {?string} value A colour value.
	 * @return {string} A #RRGGBB string.
	 */
	function normaliseColorForPicker(value) {
		if (value === undefined || value === null || value === '') {
			return '#000000'
		}
		var v = String(value).trim()
		if (/^#[0-9a-fA-F]{6}$/.test(v) === true) {
			return v.toLowerCase()
		}
		if (/^#[0-9a-fA-F]{3}$/.test(v) === true) {
			return ('#' + v[1] + v[1] + v[2] + v[2] + v[3] + v[3]).toLowerCase()
		}
		var triplet = /^(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})$/.exec(v)
		if (triplet !== null) {
			return (
				'#'
				+ triplet
					.slice(1)
					.map(function (channel) {
						return (
							'0' + Math.min(255, Number(channel)).toString(16)
						).slice(-2)
					})
					.join('')
			)
		}
		return null
	}

	/**
	 * A colour as the `r, g, b` triplet the `-rgb` tokens hold.
	 *
	 * Nextcloud 32 mixes its note-card fills from these (`rgba(var(--color-
	 * success-rgb), 0.1)`), so the value has to be the bare triplet, not a
	 * colour. The editor offers a colour picker for such a token and writes the
	 * triplet, so nobody has to work one out by hand.
	 *
	 * @param {?string} value A #RRGGBB / #RGB colour, or a triplet already.
	 * @return {?string} `r, g, b`, or null when the value is not one of those.
	 */
	function hexToRgbTriplet(value) {
		var hex = normaliseColorForPicker(value)
		if (
			hex === null
			|| value === undefined
			|| value === null
			|| String(value).trim() === ''
		) {
			return null
		}
		return [1, 3, 5]
			.map(function (at) {
				return parseInt(hex.slice(at, at + 2), 16)
			})
			.join(', ')
	}

	/**
	 * Resolve the human-readable display name for a design-system id.
	 *
	 * @param {string} dsId A design-system id (`none`, `nldesign`, …).
	 * @return {string} The display name, or the id itself when unknown.
	 */
	function designSystemLabel(dsId) {
		var names = {
			none: 'Stock Nextcloud',
			nldesign: 'NL Design System',
		}
		return names[dsId] || dsId
	}

	/**
	 * Group a flat list of DTCG import diagnostics (`{path, reason, detail?}`,
	 * the shape a custom-token-set upload response's `skipped`/`errors` arrays
	 * carry) by `reason`, sorted alphabetically by reason so rendering is
	 * stable. Framework-free and i18n-free — the caller (admin.js) turns each
	 * `reason` code into a localised label; this only groups.
	 *
	 * @param {Array<{path: string, reason: string, detail?: string}>} entries Diagnostic entries.
	 * @return {Array<{reason: string, items: Array<{path: string, reason: string, detail?: string}>}>}
	 *   One group per distinct reason, alphabetically ordered.
	 */
	function groupDiagnosticsByReason(entries) {
		if (!Array.isArray(entries) || entries.length === 0) {
			return []
		}

		var byReason = {}
		entries.forEach(function (entry) {
			var reason = (entry && entry.reason) || 'unknown'
			if (byReason[reason] === undefined) {
				byReason[reason] = []
			}
			byReason[reason].push(entry)
		})

		return Object.keys(byReason)
			.sort()
			.map(function (reason) {
				return { reason: reason, items: byReason[reason] }
			})
	}

	/**
	 * The CSS named colours, and `transparent`. Mirrors `TokenValueValidator::NAMED_COLOURS`.
	 */
	var NAMED_COLOURS =
		'aliceblue antiquewhite aqua aquamarine azure beige bisque black blanchedalmond blue blueviolet brown burlywood cadetblue chartreuse chocolate coral cornflowerblue cornsilk crimson cyan darkblue darkcyan darkgoldenrod darkgray darkgreen darkgrey darkkhaki darkmagenta darkolivegreen darkorange darkorchid darkred darksalmon darkseagreen darkslateblue darkslategray darkslategrey darkturquoise darkviolet deeppink deepskyblue dimgray dimgrey dodgerblue firebrick floralwhite forestgreen fuchsia gainsboro ghostwhite gold goldenrod gray green greenyellow grey honeydew hotpink indianred indigo ivory khaki lavender lavenderblush lawngreen lemonchiffon lightblue lightcoral lightcyan lightgoldenrodyellow lightgray lightgreen lightgrey lightpink lightsalmon lightseagreen lightskyblue lightslategray lightslategrey lightsteelblue lightyellow lime limegreen linen magenta maroon mediumaquamarine mediumblue mediumorchid mediumpurple mediumseagreen mediumslateblue mediumspringgreen mediumturquoise mediumvioletred midnightblue mintcream mistyrose moccasin navajowhite navy oldlace olive olivedrab orange orangered orchid palegoldenrod palegreen paleturquoise palevioletred papayawhip peachpuff peru pink plum powderblue purple rebeccapurple red rosybrown royalblue saddlebrown salmon sandybrown seagreen sienna silver skyblue slateblue slategray slategrey snow springgreen steelblue tan teal thistle tomato turquoise violet wheat white whitesmoke yellow yellowgreen transparent'.split(
			' ',
		)

	/**
	 * Whether a value is valid for its token type. Mirrors `TokenValueValidator::isValid()`;
	 * both run tests/Unit/fixtures/token-value-grammar.json.
	 *
	 * @param {string} type `color`, `duration`, `easing`, `rgb` or `text`.
	 * @param {string} value The value.
	 * @return {boolean} True when it passes.
	 */
	function isValidTokenValue(type, value) {
		var v = String(value).trim()
		var number = '\\s*-?[0-9.]+%?\\s*'
		var match

		switch (type) {
			case 'color':
				v = v.toLowerCase()
				return (
					/^#([0-9a-f]{3}|[0-9a-f]{4}|[0-9a-f]{6}|[0-9a-f]{8})$/.test(v)
					|| new RegExp(
						'^(rgb|rgba|hsl|hsla)\\(('
							+ number
							+ ',){2}'
							+ number
							+ '(,'
							+ number
							+ ')?\\)$',
					).test(v)
					|| NAMED_COLOURS.indexOf(v) !== -1
				)
			case 'duration':
				match = /^([0-9]+(?:\.[0-9]+)?)(ms|s)$/.exec(v)
				return (
					match !== null
					&& parseFloat(match[1]) <= (match[2] === 's' ? 5 : 5000)
				)
			case 'easing':
				if (
					['linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out'].indexOf(
						v,
					) !== -1
				) {
					return true
				}
				var n = '\\s*(-?[0-9]*\\.?[0-9]+)\\s*'
				match = new RegExp(
					'^cubic-bezier\\(' + n + ',' + n + ',' + n + ',' + n + '\\)$',
				).exec(v)
				return (
					match !== null
					&& parseFloat(match[1]) >= 0
					&& parseFloat(match[1]) <= 1
					&& parseFloat(match[3]) >= 0
					&& parseFloat(match[3]) <= 1
				)
			case 'rgb':
				match = /^([0-9]{1,3})\s*,\s*([0-9]{1,3})\s*,\s*([0-9]{1,3})$/.exec(
					v,
				)
				return (
					match !== null
					&& Math.max(+match[1], +match[2], +match[3]) <= 255
				)
			default:
				return v !== ''
		}
	}

	/**
	 * Split a colour into the six-digit hex the native picker takes and an opacity percentage.
	 *
	 * @param {string} value `#rgb`, `#rgba`, `#rrggbb`, `#rrggbbaa` or `rgb()`/`rgba()`.
	 * @return {{hex: string, alpha: number}|null} The parts, or null when it cannot be read.
	 */
	function splitAlpha(value) {
		var v = String(value).trim().toLowerCase()
		var hex = /^#([0-9a-f]{3,4}|[0-9a-f]{6}|[0-9a-f]{8})$/.exec(v)
		var channels
		var alpha = 1

		if (hex !== null) {
			var digits = hex[1]
			if (digits.length <= 4) {
				digits = digits
					.split('')
					.map(function (c) {
						return c + c
					})
					.join('')
			}
			channels = [0, 2, 4].map(function (i) {
				return parseInt(digits.slice(i, i + 2), 16)
			})
			if (digits.length === 8) {
				alpha = parseInt(digits.slice(6, 8), 16) / 255
			}
		} else {
			var rgb =
				/^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*([\d.]+)\s*)?\)$/.exec(
					v,
				)
			if (rgb === null) {
				return null
			}
			channels = [+rgb[1], +rgb[2], +rgb[3]]
			if (rgb[4] !== undefined) {
				alpha = Math.min(1, parseFloat(rgb[4]))
			}
		}

		return {
			hex:
				'#'
				+ channels
					.map(function (c) {
						return ('0' + Math.min(255, c).toString(16)).slice(-2)
					})
					.join(''),
			alpha: Math.round(alpha * 100),
		}
	}

	/**
	 * Join a six-digit hex and an opacity percentage: `#rrggbbaa`, or `#rrggbb` at 100.
	 *
	 * @param {string} hex `#rrggbb`.
	 * @param {number} alpha 0 to 100.
	 * @return {string} The colour.
	 */
	function joinAlpha(hex, alpha) {
		var percent = Math.max(0, Math.min(100, Math.round(Number(alpha))))
		if (percent === 100) {
			return hex
		}

		return hex + ('0' + Math.round((percent / 100) * 255).toString(16)).slice(-2)
	}

	return {
		isValidTokenValue: isValidTokenValue,
		splitAlpha: splitAlpha,
		joinAlpha: joinAlpha,
		darkenHex: darkenHex,
		getPreviewColors: getPreviewColors,
		normaliseColorForPicker: normaliseColorForPicker,
		hexToRgbTriplet: hexToRgbTriplet,
		designSystemLabel: designSystemLabel,
		groupDiagnosticsByReason: groupDiagnosticsByReason,
	}
})
