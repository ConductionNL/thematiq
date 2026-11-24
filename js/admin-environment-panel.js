/**
 * Environment marker (the TqOmgevingsmarkering board), the panel of the
 * Theming page at #environment. The environment is read-only and the template
 * writes everything the panel says about it; this script puts the template's
 * line in the header and shows a copy of the overview's Documentation link.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqEnvironmentPanel() {
	/**
	 * A translated string of the app.
	 *
	 * @param {string} text The English text.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text) {
		return typeof window.t === 'function' ? window.t('thematiq', text) : text
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('environment', {
			title: tr('Environment marker'),
			intro: function () {
				var line = document.querySelector('.thematiq-environment-panel__line')
				return line !== null ? line.textContent.trim() : ''
			},
			actions: function () {
				var docs = document.getElementById('nldesign-doc-link')
				if (docs === null) {
					return []
				}
				var link = docs.cloneNode(true)
				link.removeAttribute('id')
				return [link]
			},
		})
	}
})()
