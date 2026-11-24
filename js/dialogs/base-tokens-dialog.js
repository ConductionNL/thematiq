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
