/**
 * AI assistant block: the approved mark. An administrator turns it on, sets
 * the organisation name and logo (both default to what the house style
 * already has) and sees a preview of the mark as users get it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/assistant-approved-mark/spec.md
 */
;(function thematiqAssistantMarkInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqAssistantMarkMain)
	} else {
		thematiqAssistantMarkMain()
	}

	/**
	 * Draw the mark as the assistant panel shows it.
	 *
	 * @param {HTMLElement} preview The preview element.
	 * @param {object} mark The mark from the server.
	 */
	function renderPreview(preview, mark) {
		preview.textContent = ''
		if (!mark || mark.enabled !== true) {
			return
		}
		var figure = document.createElement('p')
		figure.className = 'nldesign-assistant-mark-sample'
		if (mark.logo && mark.logo.url) {
			var img = document.createElement('img')
			img.src = mark.logo.url
			img.alt = mark.logo.alt
			img.height = 24
			img.style.verticalAlign = 'middle'
			img.style.marginInlineEnd = '0.5em'
			figure.appendChild(img)
		}
		figure.appendChild(document.createTextNode(mark.label))
		preview.appendChild(figure)
	}

	/**
	 * Load the settings, wire the form.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqAssistantMarkMain() {
		var toggle = document.getElementById('nldesign-assistant-mark-enabled')
		var name = document.getElementById('nldesign-assistant-mark-organisation')
		var logo = document.getElementById('nldesign-assistant-mark-logo')
		var preview = document.getElementById('nldesign-assistant-mark-preview')
		var saveBtn = document.getElementById('nldesign-assistant-mark-save')
		var feedback = document.getElementById('nldesign-assistant-mark-feedback')
		if (!toggle || !name || !logo || !saveBtn || typeof OC === 'undefined') {
			return undefined
		}
		var url = OC.generateUrl('/apps/thematiq/settings/assistant-mark')

		function fill(settings) {
			toggle.checked = settings.enabled === true
			name.value = settings.organisation || ''
			name.placeholder = settings.organisationDefault || ''
			logo.value = settings.logo || ''
			logo.placeholder = settings.logoDefault || ''
		}

		saveBtn.addEventListener('click', function () {
			feedback.textContent = ''
			saveBtn.disabled = true
			fetch(url, {
				method: 'POST',
				headers: {
					requesttoken: OC.requestToken,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({
					enabled: toggle.checked,
					organisation: name.value,
					logo: logo.value,
				}),
			})
				.then(function (res) {
					return res.json().then(function (body) {
						return { ok: res.ok, body: body }
					})
				})
				.then(function (result) {
					if (!result.ok) {
						feedback.textContent =
							result.body.error
							|| t(
								'thematiq',
								'The AI assistant settings could not be saved.',
							)
						if (result.body.error) {
							toggle.checked = false
						}
						return
					}
					fill(result.body)
					renderPreview(preview, result.body.preview)
					feedback.textContent = t(
						'thematiq',
						'AI assistant settings saved.',
					)
				})
				.catch(function () {
					feedback.textContent = t(
						'thematiq',
						'The AI assistant settings could not be saved.',
					)
				})
				.then(function () {
					saveBtn.disabled = false
				})
		})

		return fetch(url, { headers: { requesttoken: OC.requestToken } })
			.then(function (res) {
				return res.json()
			})
			.then(function (settings) {
				fill(settings)
				return fetch(OC.generateUrl('/apps/thematiq/api/assistant-mark'), {
					headers: { requesttoken: OC.requestToken },
				})
			})
			.then(function (res) {
				return res.json()
			})
			.then(function (mark) {
				renderPreview(preview, mark)
			})
			.catch(function (err) {
				console.error('Error loading the AI assistant settings:', err)
			})
	}

	window.ThematiqAssistantMark = { renderPreview: renderPreview }
})()
