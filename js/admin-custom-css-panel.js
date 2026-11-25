/**
 * Custom CSS (the TqEigenCss board), the panel of the Theming page at #css.
 * js/admin.js loads and saves the CSS; this script numbers the editor's
 * lines, says in the header whether the CSS is on and how long it is, and
 * marks the save's answer as passed or refused.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqCustomCssPanel() {
	var input = document.getElementById('nldesign-custom-css-input')
	var toggle = document.getElementById('nldesign-custom-css-enabled')
	var gutter = document.querySelector('.thematiq-css-panel__gutter')
	var feedback = document.getElementById('nldesign-custom-css-feedback')
	if (input === null || toggle === null || gutter === null) {
		return
	}

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
	 * How many lines the CSS has; at least one.
	 *
	 * @return {number} The lines.
	 */
	function lines() {
		return Math.max(1, input.value.split('\n').length)
	}

	/**
	 * Number the editor's lines, one number per line of CSS.
	 *
	 * @return {void}
	 */
	function number() {
		var count = Math.max(lines(), Number(input.getAttribute('rows')) || 1)
		var numbers = []
		for (var i = 1; i <= count; i++) {
			var line = document.createElement('span')
			line.textContent = String(i)
			numbers.push(line)
		}
		gutter.replaceChildren.apply(gutter, numbers)
	}

	/**
	 * The header line: on or off, and how many lines.
	 *
	 * @return {string} The line.
	 */
	function summary() {
		var count = input.value.trim() === '' ? 0 : lines()
		var state = toggle.checked === true ? tr('On') : tr('Off')
		return count === 1
			? tr('{state}, 1 line', { state: state })
			: tr('{state}, {count} lines', { state: state, count: count })
	}

	/**
	 * Put the summary in the page header while the panel is open.
	 *
	 * @return {void}
	 */
	function sayInHeader() {
		if (
			window.ThematiqPanels === undefined
			|| window.ThematiqPanels.current() !== 'css'
		) {
			return
		}
		var intro = document.querySelector(
			'#thematiq-page-header .thematiq-page-header__intro',
		)
		if (intro !== null) {
			intro.textContent = summary()
		}
	}

	input.addEventListener('input', function () {
		number()
		sayInHeader()
	})
	input.addEventListener('scroll', function () {
		gutter.scrollTop = input.scrollTop
	})
	toggle.addEventListener('change', sayInHeader)

	if (feedback !== null && typeof MutationObserver === 'function') {
		// js/admin.js writes the save's answer here and colours a refusal red;
		// the pill follows that.
		new MutationObserver(function () {
			feedback.classList.toggle(
				'thematiq-css-panel__feedback--error',
				feedback.style.color !== '',
			)
			number()
			sayInHeader()
		}).observe(feedback, {
			childList: true,
			characterData: true,
			subtree: true,
			attributes: true,
		})
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('css', {
			title: tr('Custom CSS'),
			intro: summary,
			onOpen: number,
		})
	}
})()
