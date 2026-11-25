/**
 * Own tokens (the TqEigenTokens board), the panel of the Theming page at
 * #own-tokens. js/ownTokens.js draws the tables and opens the dialogs, and
 * sends what it drew as `thematiq:own-tokens`; this script writes the counts
 * and gives the header its Deprecate token and Add token buttons.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqOwnTokensPanel() {
	var counts = { tokens: 0, deprecated: 0 }

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
	 * How many own tokens, in words.
	 *
	 * @return {string} The words.
	 */
	function tokens() {
		return counts.tokens === 1
			? tr('1 own token')
			: tr('{count} own tokens', { count: counts.tokens })
	}

	/**
	 * How many deprecated tokens, in words.
	 *
	 * @return {string} The words.
	 */
	function deprecated() {
		return tr('{count} deprecated', { count: counts.deprecated })
	}

	/**
	 * Write the counts.
	 *
	 * @return {void}
	 */
	function render() {
		var set = function (selector, text) {
			var el = document.querySelector(selector)
			if (el !== null) {
				el.textContent = text
			}
		}
		set('.thematiq-own-tokens-panel__count', tokens())
		set('.thematiq-own-tokens-panel__deprecated', deprecated())
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'own-tokens'
		) {
			set(
				'#thematiq-page-header .thematiq-page-header__intro',
				tokens() + ', ' + deprecated(),
			)
		}
	}

	/**
	 * A header button that presses one of the panel's own buttons.
	 *
	 * @param {string} label The text.
	 * @param {string} id The panel button's id.
	 * @param {boolean} primary Whether it is the main action.
	 * @return {HTMLElement} The button.
	 */
	function headerButton(label, id, primary) {
		var button = document.createElement('button')
		button.type = 'button'
		button.className =
			'thematiq-page-header__action'
			+ (primary === true ? ' thematiq-page-header__action--primary' : '')
		button.textContent = label
		button.addEventListener('click', function () {
			var own = document.getElementById(id)
			if (own !== null) {
				own.click()
			}
		})
		return button
	}

	document.addEventListener('thematiq:own-tokens', function (event) {
		var detail = event.detail || {}
		counts.tokens =
			Array.isArray(detail.tokens) === true ? detail.tokens.length : 0
		counts.deprecated =
			Array.isArray(detail.deprecations) === true
				? detail.deprecations.length
				: 0
		render()
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('own-tokens', {
			title: tr('Own tokens'),
			intro: function () {
				return tokens() + ', ' + deprecated()
			},
			actions: function () {
				return [
					headerButton(
						tr('Deprecate token'),
						'nldesign-deprecation-add',
						false,
					),
					headerButton(tr('Add token'), 'nldesign-own-token-add', true),
				]
			},
			onOpen: render,
		})
	}
})()
