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

	return {
		darkenHex: darkenHex,
		getPreviewColors: getPreviewColors,
		normaliseColorForPicker: normaliseColorForPicker,
		hexToRgbTriplet: hexToRgbTriplet,
		designSystemLabel: designSystemLabel,
		groupDiagnosticsByReason: groupDiagnosticsByReason,
	}
})
