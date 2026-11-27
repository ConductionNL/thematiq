/**
 * Edit Nextcloud's base tokens (the TqBasistokens board): asked before the
 * token editor unlocks the tokens Nextcloud itself is built on, with three of
 * them and what reads them.
 *
 * js/admin.js opens it from the "Also edit Nextcloud's base tokens" box with
 * `window.ThematiqBaseTokensDialog.open({opener, onConfirm})`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqBaseTokensDialog() {
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
	 * Open the dialog.
	 *
	 * @param {object} options The dialog.
	 * @param {Element} [options.opener] What gets focus back when it closes.
	 * @param {Function} options.onConfirm Called once the admin chooses to edit them.
	 * @return {object|null} The open dialog, or null without the dialog frame.
	 */
	function open(options) {
		var frame = window.ThematiqAdminDialog
		if (frame === undefined) {
			return null
		}
		var h = frame.h
		var examples = [
			['--color-main-text', tr('all text on every page')],
			['--color-border', tr('borders of fields, tables and cards')],
			['--border-radius-element', tr('corners of buttons and fields')],
		]

		return frame.open({
			id: 'nldesign-base-unlock-overlay',
			width: 720,
			opener: options.opener,
			eyebrow: tr("Also edit Nextcloud's base tokens"),
			title: tr("Edit Nextcloud's base tokens?"),
			body: [
				h('p', {
					class: 'thematiq-admin-dialog__lead',
					text: tr(
						'These are the colours and sizes Nextcloud itself is built on, such as the colour of regular text. Change one and every part of Nextcloud that reads it changes. That is far more than the component you are looking at, and more than this panel shows.',
					),
				}),
				frame.table(
					[tr('Base token'), tr('Read by')],
					examples.map(function (row) {
						return [h('code', { text: row[0] }), row[1]]
					}),
				),
				frame.notice(
					tr(
						'To change a component, use its own rows. Those affect only that component.',
					),
					'info',
				),
			],
			actions: [
				{
					label: tr('Cancel'),
					cancel: true,
					className: 'nldesign-dialog-cancel',
				},
				{
					label: tr('Edit anyway'),
					variant: 'primary',
					// The board's pencil (TqBasistokens).
					icon: 'M14.06,9L15,9.94L5.92,19H5V18.08L14.06,9M17.66,3C17.41,3 17.15,3.1 16.96,3.29L15.13,5.12L18.88,8.87L20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18.17,3.09 17.92,3 17.66,3M14.06,6.19L3,17.25V21H6.75L17.81,9.94L14.06,6.19Z',
					className: 'nldesign-dialog-confirm',
					onClick: function (dialog) {
						dialog.close()
						options.onConfirm()
					},
				},
			],
		})
	}

	window.ThematiqBaseTokensDialog = { open: open }
})()
