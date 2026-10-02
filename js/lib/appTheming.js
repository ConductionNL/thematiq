/**
 * Per-app theming list: the pure parts of the dropdown on the admin page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * `js/admin.js` builds the "Apps to theme" dropdown out of checkboxes, filters
 * it as the admin types, labels the trigger with a count and posts the
 * unchecked apps as the exclusion list. The DOM work stays in admin.js; the
 * three decisions it makes live here, so they can be tested without a page.
 *
 * Dual-mode like `layerSwap.js`: `module.exports` under Node and
 * `window.NldesignAppTheming` in the browser. No `import`/`export`, because
 * `js/admin.js` is not built.
 *
 * @spec openspec/specs/admin-js-test-coverage/spec.md
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	}
	if (root) {
		root.NldesignAppTheming = api
	}
})(typeof window !== 'undefined' ? window : null, function () {
	'use strict'

	/**
	 * Whether an app stays visible for a search query: an empty query shows
	 * every app, otherwise the query must occur in the app name, ignoring case
	 * and surrounding spaces.
	 *
	 * @param {string} appName The app's display name.
	 * @param {string} query What the admin typed.
	 * @return {boolean} True when the app matches.
	 */
	function matchesAppSearch(appName, query) {
		var q = String(query || '')
			.trim()
			.toLowerCase()
		if (q === '') {
			return true
		}
		return (
			String(appName || '')
				.toLowerCase()
				.indexOf(q) !== -1
		)
	}

	/**
	 * Count the themed apps for the trigger label ("3 of 12 apps themed").
	 *
	 * @param {Array<{checked: boolean}>} states One entry per app checkbox.
	 * @return {{themed: number, total: number}} The counts.
	 */
	function countThemed(states) {
		var list = states || []
		var themed = 0
		for (var i = 0; i < list.length; i++) {
			if (list[i].checked === true) {
				themed++
			}
		}
		return { themed: themed, total: list.length }
	}

	/**
	 * The apps to exclude from theming: every app whose box is unchecked.
	 *
	 * @param {Array<{id: string, checked: boolean}>} states One entry per app checkbox.
	 * @return {string[]} The app ids to post as `disabledApps`.
	 */
	function buildDisabledAppsPayload(states) {
		var list = states || []
		var disabled = []
		for (var i = 0; i < list.length; i++) {
			if (list[i].checked !== true) {
				disabled.push(list[i].id)
			}
		}
		return disabled
	}

	return {
		matchesAppSearch: matchesAppSearch,
		countThemed: countThemed,
		buildDisabledAppsPayload: buildDisabledAppsPayload,
	}
})
