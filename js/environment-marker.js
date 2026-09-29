/**
 * Environment marker: names the OTAP environment on every non-production page.
 *
 * Reads the marker EnvironmentMarkerService provides as initial state, puts a
 * role="note" stripe first in the page and prefixes the tab title with the
 * short label, again whenever an app changes the title.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/environment-marker/spec.md
 */
;(function thematiqEnvironmentMarkerInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqEnvironmentMarkerMain)
	} else {
		thematiqEnvironmentMarkerMain()
	}

	/**
	 * Render the stripe and keep the title prefixed.
	 *
	 * @spec openspec/specs/environment-marker/spec.md
	 */
	function thematiqEnvironmentMarkerMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}

		var marker = OCP.InitialState.loadState('thematiq', 'environment', null)
		if (!marker || !marker.label || document.getElementById('thematiq-env-marker') !== null) {
			return
		}

		var stripe = document.createElement('div')
		stripe.id = 'thematiq-env-marker'
		stripe.className = 'thematiq-env-marker thematiq-env-marker--' + String(marker.style || 'test')
		stripe.setAttribute('role', 'note')

		var label = document.createElement('span')
		label.className = 'thematiq-env-marker__label'
		label.textContent = marker.label
		stripe.appendChild(label)

		document.body.insertBefore(stripe, document.body.firstChild)

		var prefix = String(marker.short || '') + ' '
		if (prefix.trim() === '') {
			return
		}

		function applyPrefix() {
			if (document.title.indexOf(prefix) !== 0) {
				document.title = prefix + document.title
			}
		}

		applyPrefix()

		var title = document.querySelector('title')
		if (title !== null && typeof MutationObserver === 'function') {
			new MutationObserver(applyPrefix).observe(title, { childList: true, characterData: true, subtree: true })
		}
	}
})()
