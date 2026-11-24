/**
 * Email and documents (the TqEmailDocumenten board), the panel of the Theming
 * page at #email. admin.js saves the mail template and its footer and
 * js/admin-documents.js the document profile; this script fills the example
 * mail and letter from the fields and says in the header whether the template
 * is on and whether a document logo is set.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqEmailPanel() {
	var toggle = document.getElementById('nldesign-email-theming-enabled')
	var org = document.getElementById('nldesign-email-footer-org-name')
	var footerLine = document.getElementById('nldesign-documents-footer-line')
	var preview = document.getElementById('nldesign-documents-preview')

	/**
	 * A translated string of the app.
	 *
	 * @param {string} text The English text.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text) {
		return typeof window.t === 'function' ? window.t('thematiq', text) : text
	}

	/**
	 * The header line.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var mail =
			toggle !== null && toggle.checked === true
				? tr('Email template on')
				: tr('Email template off')
		var logo =
			preview !== null && preview.querySelector('img') !== null
				? tr('document logo set')
				: tr('no document logo')
		return mail + ', ' + logo
	}

	/**
	 * Fill the examples from the fields and the profile.
	 *
	 * @return {void}
	 */
	function render() {
		var name = org !== null ? org.value : ''
		document
			.querySelectorAll('.thematiq-email-panel__org')
			.forEach(function (el) {
				el.textContent = name
			})
		var foot = document.querySelector('.thematiq-email-panel__letter-foot')
		if (foot !== null) {
			foot.textContent =
				footerLine !== null && footerLine.value !== ''
					? footerLine.value
					: name
		}
		var logo = document.querySelector('.thematiq-email-panel__letter-logo')
		var img = preview !== null ? preview.querySelector('img') : null
		if (logo !== null) {
			logo.replaceChildren()
			if (img !== null) {
				var copy = img.cloneNode()
				copy.alt = ''
				logo.appendChild(copy)
			}
		}
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'email'
		) {
			var intro = document.querySelector(
				'#thematiq-page-header .thematiq-page-header__intro',
			)
			if (intro !== null) {
				intro.textContent = line()
			}
		}
	}

	;[toggle, org, footerLine].forEach(function (field) {
		if (field !== null) {
			field.addEventListener('input', render)
			field.addEventListener('change', render)
		}
	})
	if (preview !== null && typeof MutationObserver === 'function') {
		new MutationObserver(render).observe(preview, { childList: true })
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('email', {
			title: tr('Email and documents'),
			intro: line,
			onOpen: render,
		})
	}
})()
