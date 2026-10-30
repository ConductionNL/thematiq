/**
 * The words in the top bar's search field, per app (the DqKop board:
 * "Zoek zaken, documenten of contacten").
 *
 * Loaded only when SearchPlaceholderService found words for the app being
 * rendered. Reads them from initial state and puts them where Nextcloud's
 * unified search shows its own: the collapsed field's label, the open
 * field's placeholder, and the search dialog's field label. The button and
 * fields keep working as before; only the words change. When the search
 * re-renders and puts its own words back, they are replaced again.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
;(function thematiqSearchPlaceholderInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqSearchPlaceholderMain)
	} else {
		thematiqSearchPlaceholderMain()
	}

	/**
	 * Put the words in every place the unified search shows its own.
	 *
	 * @param {string} text The words.
	 * @return {void}
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function apply(text) {
		document.querySelectorAll('.unified-search-input__label').forEach(function (label) {
			if (label.textContent !== text) {
				label.textContent = text
			}
		})
		document.querySelectorAll('.unified-search-input input, .unified-search-modal input[data-cy-unified-search-input]').forEach(function (input) {
			if (input.getAttribute('placeholder') !== null && input.getAttribute('placeholder') !== text) {
				input.setAttribute('placeholder', text)
			}
		})
		document.querySelectorAll('.unified-search-modal .input-field__label').forEach(function (label) {
			if (label.closest('.input-field') && label.closest('.input-field').querySelector('input[data-cy-unified-search-input]') && label.textContent !== text) {
				label.textContent = text
			}
		})
	}

	/**
	 * Read the words and keep them in the search field.
	 *
	 * @return {void}
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function thematiqSearchPlaceholderMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}

		var text = ''
		try {
			text = OCP.InitialState.loadState('thematiq', 'search-placeholder', '')
		} catch (e) {
			return
		}
		if (typeof text !== 'string' || text.trim() === '') {
			return
		}
		text = text.trim()

		apply(text)
		// The search mounts after this script and opens its dialog later:
		// watch the page and replace Nextcloud's words wherever they appear.
		if (typeof MutationObserver === 'function') {
			new MutationObserver(function () {
				apply(text)
			}).observe(document.body, { childList: true, subtree: true, characterData: true })
		}
	}
})()
