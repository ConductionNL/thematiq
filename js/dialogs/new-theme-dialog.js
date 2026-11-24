/**
 * Save these changes as a new theme (the TqNieuwThema board): asked when the
 * admin saves edits made on the stock Nextcloud theme. Keep them as a token
 * set of their own, or write them over the Nextcloud theme.
 *
 * js/admin.js opens it with `window.ThematiqNewThemeDialog.open({opener, proceed})`.
 * `proceed('overrides')` writes over the Nextcloud theme; `proceed('new-set',
 * name, {done, fail})` creates the set. The dialog stays open across that
 * request, so a refused name can be changed and tried again.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqNewThemeDialog() {
	var NAME_ID = 'nldesign-save-newset-name'
	var ERROR_ID = 'nldesign-save-newset-error'

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
	 * Show, or clear, a refusal under the name field.
	 *
	 * @param {string} message The message, or '' to clear it.
	 * @return {void}
	 */
	function showError(message) {
		var errorEl = document.getElementById(ERROR_ID)
		var nameEl = document.getElementById(NAME_ID)
		if (errorEl === null || nameEl === null) {
			return
		}
		errorEl.textContent = message
		errorEl.hidden = message === ''
		if (message === '') {
			nameEl.removeAttribute('aria-invalid')
			return
		}
		nameEl.setAttribute('aria-invalid', 'true')
		nameEl.focus()
		nameEl.select()
	}

	/**
	 * Open the dialog.
	 *
	 * @param {object} options The dialog.
	 * @param {Element} [options.opener] What gets focus back when it closes.
	 * @param {Function} options.proceed Called with the chosen route.
	 * @return {object|null} The open dialog, or null without the dialog frame.
	 */
	function open(options) {
		var frame = window.ThematiqAdminDialog
		if (frame === undefined) {
			return null
		}
		var h = frame.h

		return frame.open({
			id: 'nldesign-save-dialog-overlay',
			width: 640,
			opener: options.opener,
			eyebrow: tr('Save'),
			title: tr('Save these changes as a new theme?'),
			body: [
				frame.notice(tr('This question only comes when you work on the Nextcloud default theme and save.'), 'info'),
				h('p', { class: 'thematiq-admin-dialog__lead', text: tr('Save the changes as a custom token set, or write them over the Nextcloud theme itself. Writing over Nextcloud changes Nextcloud for everyone; with Back to the Nextcloud theme you undo that later.') }),
				frame.field({
					id: NAME_ID,
					label: tr('Name of the new theme'),
					placeholder: tr('My organisation'),
					hint: tr('You then choose it in the active token set list.'),
				}),
				h('p', { class: 'thematiq-admin-dialog__note', text: tr('Values that do not fit in a token set are kept as overrides. You then see which.') }),
			],
			actions: [
				{
					label: tr('Change the Nextcloud theme'),
					variant: 'link',
					className: 'nldesign-dialog-overwrite',
					onClick: function (dialog) {
						dialog.close()
						options.proceed('overrides')
					},
				},
				{ label: tr('Cancel'), cancel: true, className: 'nldesign-dialog-cancel' },
				{
					label: tr('Save as new theme'),
					variant: 'primary',
					icon: 'check',
					className: 'nldesign-dialog-confirm',
					onClick: function (dialog) {
						var name = document.getElementById(NAME_ID).value.trim()
						if (name === '') {
							showError(tr('Give the new theme a name.'))
							return
						}
						dialog.busy(true)
						showError('')
						options.proceed('new-set', name, {
							done: function () {
								dialog.busy(false)
								dialog.close()
							},
							fail: function (message) {
								dialog.busy(false)
								showError(message)
							},
						})
					},
				},
			],
		})
	}

	window.ThematiqNewThemeDialog = { open: open }
})()
