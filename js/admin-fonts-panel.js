/**
 * Fonts (the TqLettertypen board), the panel of the Theming page at #fonts.
 * js/admin.js uploads, lists and assigns the fonts; this script fills Now in
 * use from those lists, and gives the header its line (how many fonts were
 * uploaded) and its Upload font button, which takes the admin to the form.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqFontsPanel() {
	var list = document.getElementById('nldesign-font-list')
	var library = document.getElementById('nldesign-font-library')

	/**
	 * A translated string of the app, with `{name}` placeholders filled.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] The placeholders.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text, vars) {
		if (typeof window.t === 'function') {
			return window.t('thematiq', text, vars)
		}
		return text.replace(/{(\w+)}/g, function (match, key) {
			return vars && key in vars ? String(vars[key]) : match
		})
	}

	/**
	 * The header line: how many fonts were uploaded.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var count =
			list !== null ? list.querySelectorAll('.thematiq-font-row').length : 0
		return count === 1
			? tr('1 uploaded font')
			: tr('{count} uploaded fonts', { count: count })
	}

	/**
	 * Fill Now in use: the font of the headings and of the body text, from
	 * the rows of the library and the uploads.
	 *
	 * @return {void}
	 */
	function drawNow() {
		var now = document.querySelector('.thematiq-fonts-panel__now')
		if (now === null) {
			return
		}
		var rows = []
		;[
			['heading', tr('Headings')],
			['body', tr('Body text')],
		].forEach(function (role) {
			var row = document.querySelector(
				'.thematiq-font-row--' + role[0] + ' .thematiq-font-row__title',
			)
			if (row === null) {
				return
			}
			var entry = document.createElement('div')
			var label = document.createElement('span')
			label.textContent = role[1]
			var name = document.createElement('strong')
			name.textContent = row.textContent
			entry.appendChild(label)
			entry.appendChild(name)
			rows.push(entry)
		})
		now.replaceChildren.apply(now, rows)
	}

	/**
	 * Redraw what follows the lists: Now in use, and the header line while open.
	 *
	 * @return {void}
	 */
	function follow() {
		drawNow()
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'fonts'
		) {
			var intro = document.querySelector(
				'#thematiq-page-header .thematiq-page-header__intro',
			)
			if (intro !== null) {
				intro.textContent = line()
			}
		}
	}

	/**
	 * The header's Upload font button.
	 *
	 * @return {Array<HTMLElement>} The button.
	 */
	function actions() {
		var button = document.createElement('button')
		button.type = 'button'
		button.className = 'thematiq-page-header__action'
		button.textContent = tr('Upload font')
		button.addEventListener('click', function () {
			var name = document.getElementById('nldesign-font-name')
			if (name !== null) {
				name.scrollIntoView({ block: 'center' })
				name.focus()
			}
		})
		return [button]
	}

	if (typeof MutationObserver === 'function') {
		;[list, library].forEach(function (target) {
			if (target !== null) {
				new MutationObserver(follow).observe(target, { childList: true })
			}
		})
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('fonts', {
			title: tr('Typefaces'),
			intro: line,
			actions: actions,
			onOpen: drawNow,
		})
	}
})()
