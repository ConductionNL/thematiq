/**
 * OpenProject's address for the apps menu: one field and a save button.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md#requirement-the-apps-menu-lists-openprojects-modules
 */
;(function thematiqOpenProjectLinkInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqOpenProjectLinkMain)
	} else {
		thematiqOpenProjectLinkMain()
	}

	/**
	 * Load the stored address and wire the save button.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqOpenProjectLinkMain() {
		var input = document.getElementById('nldesign-openproject-url')
		var save = document.getElementById('nldesign-openproject-save')
		var feedback = document.getElementById('nldesign-openproject-feedback')
		if (!input || !save || !feedback || typeof OC === 'undefined') {
			return undefined
		}
		var url = OC.generateUrl('/apps/thematiq/settings/openproject')
		var request = function (options) {
			options = options || {}
			options.headers = Object.assign(
				{ requesttoken: OC.requestToken, 'Content-Type': 'application/json' },
				options.headers || {},
			)
			return fetch(url, options).then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, body: body }
				})
			})
		}
		save.addEventListener('click', function () {
			request({
				method: 'POST',
				body: JSON.stringify({ url: input.value.trim() }),
			}).then(function (result) {
				feedback.textContent = result.ok
					? t('thematiq', 'Address saved.')
					: result.body.error
				if (result.ok) {
					input.value = result.body.url || ''
				}
			})
		})
		return request().then(function (result) {
			if (result.ok) {
				input.value = result.body.url || ''
			}
		})
	}
})()
