/**
 * Brand per app (the TqMerkPerApp board), the panel of the Theming page at
 * #brands. js/admin-app-brands.js lists and saves the brands and sends what it
 * loaded as `thematiq:app-brands`; admin.js draws the theme per app list. This
 * script says in the header how many apps have a brand of their own and how
 * many follow the house style, and gives the header its Add brand button.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqBrandsPanel() {
	var brands = 0

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
		var boxes = document.querySelectorAll(
			'#nldesign-app-theming-list input[type="checkbox"][data-app-id]',
		)
		var themed = Array.prototype.filter.call(boxes, function (box) {
			return box.checked === true
		}).length
		var own =
			brands === 1
				? tr('1 app with its own brand')
				: tr('{count} apps with their own brand', { count: brands })
		if (boxes.length === 0) {
			return own
		}
		return (
			own
			+ ', '
			+ tr('{themed} of {total} apps follow the house style', {
				themed: themed,
				total: boxes.length,
			})
		)
	}

	/**
	 * Write the header line while the panel is open.
	 *
	 * @return {void}
	 */
	function sayInHeader() {
		if (
			window.ThematiqPanels === undefined
			|| window.ThematiqPanels.current() !== 'brands'
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

	/**
	 * The header button: to the form that adds a brand.
	 *
	 * @return {Array<HTMLElement>} The button.
	 */
	function actions() {
		var add = document.createElement('button')
		add.type = 'button'
		add.className =
			'thematiq-page-header__action thematiq-page-header__action--primary'
		add.textContent = tr('Add brand')
		add.addEventListener('click', function () {
			var app = document.getElementById('nldesign-app-brands-app')
			if (app !== null) {
				app.focus()
			}
		})
		return [add]
	}

	document.addEventListener('thematiq:app-brands', function (event) {
		brands =
			event.detail && event.detail.brands
				? Object.keys(event.detail.brands).length
				: 0
		sayInHeader()
	})
	var list = document.getElementById('nldesign-app-theming-list')
	if (list !== null) {
		list.addEventListener('change', sayInHeader)
		if (typeof MutationObserver === 'function') {
			new MutationObserver(sayInHeader).observe(list, { childList: true })
		}
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('brands', {
			title: tr('Brand per app'),
			intro: line,
			actions: actions,
		})
	}
})()
