/**
 * Theme gallery and updates (the TqGalerij board), the panel of the Theming
 * page at #gallery. js/admin.js loads the gallery and the upstream check and
 * sends what it loaded as `thematiq:gallery` and `thematiq:upstream-freshness`;
 * this script says in the header whether the gallery is on, how many house
 * styles it lists and how many notices the source gave.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqGalleryPanel() {
	var gallery = null
	var freshness = null

	/**
	 * A translated string of the app.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] The placeholders.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text, vars) {
		if (typeof window.t === 'function') {
			return window.t('thematiq', text, vars, undefined, { escape: false })
		}
		return Object.keys(vars || {}).reduce(function (acc, key) {
			return acc.replace('{' + key + '}', vars[key])
		}, text)
	}

	/**
	 * The header line.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var parts = []
		if (gallery !== null && gallery.enabled === true) {
			var entries =
				Array.isArray(gallery.entries) === true ? gallery.entries.length : 0
			parts.push(tr('Gallery on, {count} house styles', { count: entries }))
		} else {
			parts.push(tr('Gallery off'))
		}
		var notices =
			freshness !== null && Array.isArray(freshness.notices) === true
				? freshness.notices.length
				: 0
		parts.push(tr('{count} notices from the source', { count: notices }))
		return parts.join(', ')
	}

	/**
	 * Write the header line while the panel is open.
	 *
	 * @return {void}
	 */
	function sayInHeader() {
		if (
			window.ThematiqPanels === undefined
			|| window.ThematiqPanels.current() !== 'gallery'
		) {
			return
		}
		var intro = document.querySelector(
			'#thematiq-page-header .thematiq-page-header__intro',
		)
		if (intro !== null) {
			intro.textContent = line()
		}
	}

	document.addEventListener('thematiq:gallery', function (event) {
		gallery = event.detail || null
		sayInHeader()
	})
	document.addEventListener('thematiq:upstream-freshness', function (event) {
		freshness = event.detail || null
		sayInHeader()
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('gallery', {
			title: tr('Theme gallery and updates'),
			crumb: tr('Theme gallery'),
			intro: line,
		})
	}
})()
