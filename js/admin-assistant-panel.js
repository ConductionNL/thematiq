/**
 * AI assistant (the TqAiAssistent board), the panel of the Theming page at
 * #assistant. js/admin-assistant-mark.js loads, previews and saves the mark;
 * this script puts the same preview in the footer of the sample assistant
 * panel, marks the save's answer as passed or refused, and says in the
 * header whether the mark is on.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqAssistantPanel() {
	var preview = document.getElementById('nldesign-assistant-mark-preview')
	var foot = document.querySelector('.thematiq-assistant-panel__sample-foot')
	var toggle = document.getElementById('nldesign-assistant-mark-enabled')
	var feedback = document.getElementById('nldesign-assistant-mark-feedback')

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
	 * The header line: whether the mark is on.
	 *
	 * @return {string} The line.
	 */
	function line() {
		return toggle !== null && toggle.checked === true
			? tr('On, users see the approved mark')
			: tr('Off, users see no mark')
	}

	/**
	 * Copy the preview into the sample panel's footer.
	 *
	 * @return {void}
	 */
	function mirror() {
		if (preview === null || foot === null) {
			return
		}
		foot.replaceChildren.apply(
			foot,
			Array.prototype.map.call(preview.childNodes, function (node) {
				return node.cloneNode(true)
			}),
		)
	}

	/**
	 * Say in the header whether the mark is on, while the panel is open.
	 *
	 * @return {void}
	 */
	function sayInHeader() {
		if (
			window.ThematiqPanels === undefined
			|| window.ThematiqPanels.current() !== 'assistant'
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

	if (typeof MutationObserver === 'function') {
		if (preview !== null) {
			new MutationObserver(mirror).observe(preview, {
				childList: true,
				subtree: true,
				characterData: true,
			})
		}
		if (feedback !== null) {
			new MutationObserver(function () {
				// js/admin-assistant-mark.js writes the save's answer here; anything but
				// the saved line is a refusal.
				var text = feedback.textContent
				feedback.classList.toggle(
					'thematiq-css-panel__feedback--error',
					text !== '' && text !== tr('AI assistant settings saved.'),
				)
			}).observe(feedback, {
				childList: true,
				characterData: true,
				subtree: true,
			})
		}
	}
	if (toggle !== null) {
		toggle.addEventListener('change', sayInHeader)
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('assistant', {
			title: tr('AI assistant'),
			intro: line,
			onOpen: mirror,
		})
	}
})()
