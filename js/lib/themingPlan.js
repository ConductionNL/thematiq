/**
 * What syncing Nextcloud's own theming to a token set would do.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * `js/admin.js` shows the plan in the apply dialog (or a dialog of its own)
 * and executes it; the decision of what to offer lives here, so it can be
 * tested without a page.
 *
 * Dual-mode like `appTheming.js`: `module.exports` under Node and
 * `window.NldesignThemingPlan` in the browser. The translation function is
 * passed in, so the module reads no globals.
 *
 * @spec openspec/specs/admin-js-test-coverage/spec.md
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	}
	if (root) {
		root.NldesignThemingPlan = api
	}
})(typeof window !== 'undefined' ? window : null, function () {
	'use strict'

	/**
	 * The note next to a colour core gets as the blend of a translucent one.
	 *
	 * @param {string|undefined} original The set's own value, when it was translucent.
	 * @param {Function} t The translation function.
	 * @return {string} The note, or '' for an opaque colour.
	 */
	function opaqueNote(original, t) {
		if (!original) {
			return ''
		}
		return t(
			'thematiq',
			"The set says {original}. Nextcloud's own theming has no transparency. It gets this colour instead.",
			{ original: original },
		)
	}

	/**
	 * The plan for a stock-based theme that captured no branding: reset
	 * Nextcloud's branding to its defaults, undoing everything synced.
	 *
	 * @param {object} currentTheming What core holds now.
	 * @param {Function} t The translation function.
	 * @return {Array<object>} The rows the reset changes.
	 */
	function resetDiffs(currentTheming, t) {
		var defaultLabel = t('thematiq', 'Nextcloud default')
		var diffs = []
		if (currentTheming.primary_color) {
			diffs.push({
				label: t('thematiq', 'Primary color'),
				key: 'primary_color',
				kind: 'color',
				current: currentTheming.primary_color,
				proposed: currentTheming.default_primary_color || '#00679e',
				proposedNote: defaultLabel,
			})
		}
		if (currentTheming.background_color) {
			diffs.push({
				label: t('thematiq', 'Background color'),
				key: 'background_color',
				kind: 'color',
				current: currentTheming.background_color,
				proposed: currentTheming.default_background_color || '#00679e',
				proposedNote: defaultLabel,
			})
		}
		if (currentTheming.has_custom_logo === true) {
			diffs.push({
				label: t('thematiq', 'Logo'),
				key: 'logo',
				kind: 'text',
				current: t('thematiq', '(custom logo)'),
				proposed: t('thematiq', 'Nextcloud logo'),
			})
		}
		if (currentTheming.has_custom_background === true) {
			diffs.push({
				label: t('thematiq', 'Background image'),
				key: 'background',
				kind: 'text',
				current: t('thematiq', '(custom)'),
				proposed: defaultLabel,
			})
		}
		return diffs
	}

	/**
	 * Offer one colour of the set when core holds a different one, ignoring case.
	 *
	 * @param {object} plan `{diffs, payload}` to add to.
	 * @param {string} key `primary_color` or `background_color`.
	 * @param {string} label The row label.
	 * @param {object} proposed The set's theming.
	 * @param {object} currentTheming What core holds now.
	 * @param {Function} t The translation function.
	 */
	function addColourDiff(plan, key, label, proposed, currentTheming, t) {
		if (
			!proposed[key]
			|| proposed[key].toLowerCase()
				=== (currentTheming[key] || '').toLowerCase()
		) {
			return
		}
		plan.diffs.push({
			label: label,
			key: key,
			kind: 'color',
			current: currentTheming[key],
			proposed: proposed[key],
			proposedNote: opaqueNote(proposed[key + '_original'], t),
		})
		plan.payload[key] = proposed[key]
	}

	/**
	 * Offer one image of the set when the slot does not ALREADY hold it.
	 *
	 * Core records just that a custom image exists, not which one, so the
	 * comparison uses `synced_*`: the path Thematiq itself last applied.
	 * Without this the dialog offered the same unchanged logo on every apply
	 * and could never stop appearing. (An admin who uploads a logo through
	 * core's own panel after a sync leaves `synced_logo` behind; the row is
	 * then skipped once, until the next sync rewrites it.)
	 *
	 * @param {object} plan `{diffs, payload}` to add to.
	 * @param {string} key `logo`, `logoheader`, `favicon` or `background`.
	 * @param {string} label The row label.
	 * @param {string} customLabel How the current value reads when core holds a custom image.
	 * @param {object} proposed The set's theming.
	 * @param {object} currentTheming What core holds now.
	 * @param {Function} t The translation function.
	 */
	function addImageDiff(
		plan,
		key,
		label,
		customLabel,
		proposed,
		currentTheming,
		t,
	) {
		var hasCustom = currentTheming['has_custom_' + key]
		if (
			!proposed[key]
			|| (hasCustom === true
				&& currentTheming['synced_' + key] === proposed[key])
		) {
			return
		}
		plan.diffs.push({
			label: label,
			key: key,
			kind: 'text',
			current: hasCustom ? customLabel : t('thematiq', '(default)'),
			proposed: proposed[key].split('/').pop(),
		})
		plan.payload[key] = proposed[key]
	}

	/**
	 * Offer the background state a captured theme knows: removed ("color") or
	 * left as Nextcloud's own ("default"). Offered only when the page is in a
	 * different state; an image of the theme's own is the background row.
	 *
	 * @param {object} plan `{diffs, payload}` to add to.
	 * @param {string|undefined} mode The theme's background mode.
	 * @param {object} currentTheming What core holds now.
	 * @param {Function} t The translation function.
	 */
	function addBackgroundModeDiff(plan, mode, currentTheming, t) {
		var removedNow = currentTheming.background_mime === 'backgroundColor'
		var differs =
			mode === 'color'
				? removedNow === false
				: mode === 'default'
					&& (removedNow === true
						|| currentTheming.has_custom_background === true)
		if (differs === false) {
			return
		}
		var current = removedNow
			? t('thematiq', 'Removed (plain color)')
			: currentTheming.has_custom_background
				? t('thematiq', '(custom)')
				: t('thematiq', '(default)')
		plan.diffs.push({
			label: t('thematiq', 'Background image'),
			key: 'background_mode',
			kind: 'text',
			current: current,
			proposed:
				mode === 'color'
					? t('thematiq', 'Removed (plain color)')
					: t('thematiq', 'Nextcloud default'),
		})
	}

	/**
	 * What syncing core theming to a set would do, computed once and used
	 * by both surfaces: the section inside the apply dialog (the normal
	 * path) and the standalone dialog (when the apply dialog has no token
	 * changes to show). `mode` is `match` (POST the set's values), `reset`
	 * (stock Nextcloud: DELETE, undo everything synced) or `none`.
	 *
	 * Stock is the ABSENCE of a synced theme, not a manifest to match:
	 * matching would keep the previous set's logo (the manifest has none)
	 * and pin a stale primary.
	 *
	 * @param {object|undefined} tokenSetData The set, as the admin page lists it.
	 * @param {object|undefined} currentTheming What core holds now.
	 * @param {Function} t The translation function.
	 * @return {{mode: string, diffs: Array<object>, payload: object|null}} The plan.
	 */
	function computeThemingPlan(tokenSetData, currentTheming, t) {
		var none = { mode: 'none', diffs: [], payload: null }
		if (!tokenSetData || !currentTheming) {
			return none
		}

		// A stock-based theme resets Nextcloud's branding to its defaults,
		// unless it CAPTURED the branding it was saved with, in which case
		// that is what it brings back, like any other theme.
		var captured = Boolean(
			tokenSetData.theming && tokenSetData.theming.captured === true,
		)
		if (
			(tokenSetData.design_system || 'nldesign') === 'none'
			&& captured === false
		) {
			var reset = resetDiffs(currentTheming, t)
			return reset.length === 0
				? none
				: { mode: 'reset', diffs: reset, payload: null }
		}

		var proposed = tokenSetData.theming
		if (!proposed) {
			return none
		}
		var plan = { diffs: [], payload: {} }

		addColourDiff(
			plan,
			'primary_color',
			t('thematiq', 'Primary color'),
			proposed,
			currentTheming,
			t,
		)
		addColourDiff(
			plan,
			'background_color',
			t('thematiq', 'Background color'),
			proposed,
			currentTheming,
			t,
		)
		addImageDiff(
			plan,
			'logo',
			t('thematiq', 'Logo'),
			t('thematiq', '(custom logo)'),
			proposed,
			currentTheming,
			t,
		)
		// The navigation-bar logo and the favicon, compared the same way as
		// the logo: by the file Thematiq last put in the slot.
		addImageDiff(
			plan,
			'logoheader',
			t('thematiq', 'Navigation bar logo'),
			t('thematiq', '(custom)'),
			proposed,
			currentTheming,
			t,
		)
		addImageDiff(
			plan,
			'favicon',
			t('thematiq', 'Favicon'),
			t('thematiq', '(custom)'),
			proposed,
			currentTheming,
			t,
		)
		addImageDiff(
			plan,
			'background',
			t('thematiq', 'Background image'),
			t('thematiq', '(custom)'),
			proposed,
			currentTheming,
			t,
		)

		var mode = proposed.background_mode
		addBackgroundModeDiff(plan, mode, currentTheming, t)

		if (plan.diffs.length === 0) {
			return none
		}

		// Sent with anything that is applied, so a colour change on its own
		// cannot drop the background image the theme was saved with.
		if (mode === 'image' || mode === 'color' || mode === 'default') {
			plan.payload.background_mode = mode
		}

		return { mode: 'match', diffs: plan.diffs, payload: plan.payload }
	}

	return {
		computeThemingPlan: computeThemingPlan,
	}
})
