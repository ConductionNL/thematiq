/**
 * Environment marker (the TqOmgevingsmarkering board), the panel of the
 * Theming page at #environment. The environment is read-only: this script
 * only names the marker the declared environment gets, says in the header
 * where it comes from, and keeps the overview's Documentation link.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqEnvironmentPanel() {
	var section = document.querySelector('section[data-thematiq-panel="environment"]')
	var docs = null
	document.querySelectorAll('#thematiq-page-header .thematiq-page-header__actions a[href]').forEach(function (link) {
		if (docs === null) {
			docs = link.getAttribute('href')
		}
	})

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
	 * The declared environment.
	 *
	 * @return {string} The value, or ''.
	 */
	function environment() {
		return section !== null ? section.getAttribute('data-environment') || '' : ''
	}

	/**
	 * The marker the environment gets, or '' for none.
	 *
	 * @return {string} The marker's words.
	 */
	function marker() {
		var value = environment()
		if (value === '' || value === 'production') {
			return ''
		}
		return {
			development: tr('Development environment'),
			test: tr('Test environment'),
			acceptance: tr('Acceptance environment'),
		}[value] || tr('Unknown environment')
	}

	/**
	 * The header line.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var value = environment()
		return value === '' ? tr('No environment set') : tr('Environment: {environment}, set in config.php', { environment: value })
	}

	/**
	 * Name the marker beside the chip.
	 *
	 * @return {void}
	 */
	function render() {
		var el = document.querySelector('.thematiq-environment-panel__marker')
		if (el !== null) {
			el.textContent = marker() === '' ? tr('No page of this server carries a marker.') : tr('Every page of this server carries the marker {marker}.', { marker: marker() })
		}
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('environment', {
			title: tr('Environment marker'),
			intro: line,
			actions: function () {
				if (docs === null) {
					return []
				}
				var link = document.createElement('a')
				link.className = 'thematiq-page-header__action'
				link.href = docs
				link.target = '_blank'
				link.rel = 'noopener noreferrer'
				link.textContent = tr('Documentation')
				return [link]
			},
			onOpen: render,
		})
	}
})()
