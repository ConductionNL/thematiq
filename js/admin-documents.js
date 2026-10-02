/**
 * Documents block: the document house style profile. An administrator
 * uploads a document logo and a cover image, adds a footer line, and sees
 * the profile the apps that generate documents will read.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/document-house-style/spec.md
 */
;(function thematiqDocumentsInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqDocumentsMain)
	} else {
		thematiqDocumentsMain()
	}

	/**
	 * Add a labelled line to the preview.
	 *
	 * @param {HTMLElement} list The definition list.
	 * @param {string} term The label.
	 * @param {string} value The value.
	 */
	function row(list, term, value) {
		var dt = document.createElement('dt')
		dt.textContent = term
		var dd = document.createElement('dd')
		dd.textContent = value
		list.appendChild(dt)
		list.appendChild(dd)
	}

	/**
	 * Show the profile.
	 *
	 * @param {HTMLElement} preview The preview element.
	 * @param {object} profile The profile from the server.
	 */
	function renderPreview(preview, profile) {
		preview.textContent = ''
		if (!profile) {
			return
		}
		if (profile.logo && profile.logo.url) {
			var img = document.createElement('img')
			img.src = profile.logo.url
			img.alt = t('thematiq', 'Document logo')
			img.height = 48
			preview.appendChild(img)
		}
		var list = document.createElement('dl')
		list.className = 'nldesign-documents-profile'
		row(list, t('thematiq', 'House style'), profile.tokenSet.name)
		row(list, t('thematiq', 'Primary color'), profile.colours.primary || '')
		row(list, t('thematiq', 'Text color'), profile.colours.text || '')
		row(list, t('thematiq', 'Heading font'), profile.fonts.heading.family || '')
		row(list, t('thematiq', 'Body font'), profile.fonts.body.family || '')
		row(list, t('thematiq', 'Footer'), profile.footer.lines.join(' · '))
		preview.appendChild(list)
		if (profile.warnings && profile.warnings.length > 0) {
			var warning = document.createElement('p')
			warning.className = 'nldesign-documents-warning'
			warning.textContent = t(
				'thematiq',
				'The text color does not reach 4.5:1 contrast on the document background. Documents may be hard to read.',
			)
			preview.appendChild(warning)
		}
	}

	/**
	 * Load the settings and wire the form.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqDocumentsMain() {
		var preview = document.getElementById('nldesign-documents-preview')
		var feedback = document.getElementById('nldesign-documents-feedback')
		var footerLine = document.getElementById('nldesign-documents-footer-line')
		var footerSave = document.getElementById('nldesign-documents-footer-save')
		if (!preview || !feedback || !footerLine || typeof OC === 'undefined') {
			return undefined
		}
		var base = OC.generateUrl('/apps/thematiq/settings/document-style')

		function show(settings) {
			footerLine.value = settings.footerLine || ''
			renderPreview(preview, settings.profile)
		}

		function send(url, options, done) {
			feedback.textContent = ''
			options.headers = Object.assign(
				{ requesttoken: OC.requestToken },
				options.headers || {},
			)
			return fetch(url, options)
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
								'The document settings could not be saved.',
							)
						return
					}
					show(result.body)
					feedback.textContent = done
				})
				.catch(function () {
					feedback.textContent = t(
						'thematiq',
						'The document settings could not be saved.',
					)
				})
		}

		;['logo', 'cover'].forEach(function (kind) {
			var input = document.getElementById('nldesign-documents-' + kind)
			var remove = document.getElementById(
				'nldesign-documents-' + kind + '-remove',
			)
			if (input) {
				input.addEventListener('change', function () {
					if (!input.files || input.files.length === 0) {
						return
					}
					var form = new FormData()
					form.append('file', input.files[0])
					send(
						base + '/' + kind,
						{ method: 'POST', body: form },
						t('thematiq', 'Image saved.'),
					)
				})
			}
			if (remove) {
				remove.addEventListener('click', function () {
					send(
						base + '/' + kind,
						{ method: 'DELETE' },
						t('thematiq', 'Image removed.'),
					)
				})
			}
		})

		if (footerSave) {
			footerSave.addEventListener('click', function () {
				send(
					base,
					{
						method: 'POST',
						headers: { 'Content-Type': 'application/json' },
						body: JSON.stringify({ footerLine: footerLine.value }),
					},
					t('thematiq', 'Footer line saved.'),
				)
			})
		}

		return fetch(base, { headers: { requesttoken: OC.requestToken } })
			.then(function (res) {
				return res.json()
			})
			.then(show)
			.catch(function (err) {
				console.error('Error loading the document settings:', err)
			})
	}

	window.ThematiqDocuments = { renderPreview: renderPreview }
})()
