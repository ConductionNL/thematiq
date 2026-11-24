/**
 * Reset theme to Nextcloud (the TqTerugNaarNextcloud board): what the reset
 * removes, what it keeps, and that it cannot be undone.
 *
 * js/admin.js opens it from "Back to the Nextcloud theme" with
 * `window.ThematiqResetThemeDialog.open({opener, themeName, overrideCount, onConfirm})`
 * and does the reset itself in `onConfirm(dialog)`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqResetThemeDialog() {
	/**
	 * A translated string of the app, with `{name}` placeholders filled.
	 *
	 * @param {string} text The English text.
	 * @param {object} [values] The placeholders.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text, values) {
		return typeof window.t === 'function' ? window.t('thematiq', text, values) : text
	}

	/**
	 * The line about the active theme's own overrides.
	 *
	 * @param {string} themeName The active theme's name.
	 * @param {number|null} count How many overrides it has, or null when unknown.
	 * @return {string} The line.
	 */
	function overridesLine(themeName, count) {
		if (typeof count !== 'number') {
			return tr('The overrides of the current theme {name}', { name: themeName })
		}
		if (count === 1) {
			return tr('The override of the current theme {name}', { name: themeName })
		}
		return tr('The {count} overrides of the current theme {name}', { count: count, name: themeName })
	}

	/**
	 * Open the dialog.
	 *
	 * @param {object} options The dialog.
	 * @param {Element} [options.opener] The button that opened it.
	 * @param {string} options.themeName The active theme's name.
	 * @param {number|null} [options.overrideCount] How many overrides the active theme has.
	 * @param {Function} options.onConfirm Called with the open dialog when the admin confirms.
	 * @return {object|null} The open dialog, or null without the dialog frame.
	 */
	function open(options) {
		var frame = window.ThematiqAdminDialog
		if (frame === undefined) {
			return null
		}
		var h = frame.h

		return frame.open({
			id: 'thematiq-reset-theme-dialog',
			width: 560,
			opener: options.opener,
			eyebrow: tr('Active token set'),
			title: tr('Reset theme to Nextcloud?'),
			body: [
				h('p', { class: 'thematiq-admin-dialog__lead', text: tr('This resets the theme completely to the Nextcloud default theme.') }),
				h('h3', { class: 'thematiq-admin-dialog__subtitle', text: tr('This goes') }),
				frame.list([
					overridesLine(options.themeName, options.overrideCount),
					tr('Every change written over the Nextcloud theme'),
					tr('Colours and logo of Nextcloud: these go back to the default'),
				], 'goes'),
				h('h3', { class: 'thematiq-admin-dialog__subtitle', text: tr('This stays') }),
				frame.list([tr('Own token sets'), tr('Typefaces'), tr('Custom CSS')], 'stays'),
				frame.notice(tr('This cannot be undone.'), 'warning'),
			],
			actions: [
				{ label: tr('Cancel'), cancel: true },
				{
					label: tr('Reset to Nextcloud'),
					variant: 'danger',
					icon: 'restore',
					onClick: function (dialog) {
						options.onConfirm(dialog)
					},
				},
			],
		})
	}

	window.ThematiqResetThemeDialog = { open: open }
})()
