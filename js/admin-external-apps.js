/**
 * The external apps of the apps menu: one address field per app and one save button.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
 */
;(function thematiqExternalAppsInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqExternalAppsMain)
	} else {
		thematiqExternalAppsMain()
	}

	/**
	 * Fill the fields from the server and wire the save button.
	 *
	 * @return {Promise|undefined} The request.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-the-external-apps-that-have-an-address
	 */
	function thematiqExternalAppsMain() {
		var block = document.getElementById('nldesign-external-apps')
		var save = document.getElementById('nldesign-external-apps-save')
		var feedback = document.getElementById('nldesign-external-apps-feedback')
		if (!block || !save || !feedback || typeof OC === 'undefined') {
			return undefined
		}
		var inputs = Array.prototype.slice.call(
			block.querySelectorAll('input[data-external-app]'),
		)
		var url = OC.generateUrl('/apps/thematiq/settings/external-apps')
		var request = function (options) {
			options = options || {}
			options.headers = {
				requesttoken: OC.requestToken,
				'Content-Type': 'application/json',
			}
			return fetch(url, options).then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, body: body }
				})
			})
		}
		var fill = function (apps) {
			var byKey = {}
			;(apps || []).forEach(function (app) {
				byKey[app.key] = app.url || ''
			})
			inputs.forEach(function (input) {
				var key = input.getAttribute('data-external-app')
				input.value = byKey[key] || ''
				input.removeAttribute('aria-invalid')
			})
		}
		save.addEventListener('click', function () {
			var urls = {}
			inputs.forEach(function (input) {
				urls[input.getAttribute('data-external-app')] = input.value.trim()
			})
			return request({
				method: 'POST',
				body: JSON.stringify({ urls: urls }),
			}).then(function (result) {
				if (result.ok) {
					fill(result.body.apps)
					feedback.textContent = t('thematiq', 'Addresses saved.')
					return
				}
				var refused = result.body.refused || []
				inputs.forEach(function (input) {
					if (refused.indexOf(input.getAttribute('data-external-app')) !== -1) {
						input.setAttribute('aria-invalid', 'true')
					} else {
						input.removeAttribute('aria-invalid')
					}
				})
				feedback.textContent = result.body.error || ''
			})
		})
		return request().then(function (result) {
			if (result.ok) {
				fill(result.body.apps)
			}
		})
	}
})()
