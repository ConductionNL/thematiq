/**
 * Update the Nextcloud theme (the TqThemingBijwerken board): asked after a
 * token set is chosen whose house style differs from Nextcloud's own theming.
 * Lists each setting that differs with its current and its proposed value.
 * Choosing the stock set asks the same with Nextcloud's defaults proposed.
 *
 * js/admin.js opens it with `window.ThematiqThemingUpdateDialog.open({opener,
 * mode, setName, diffs, onConfirm})`, where a diff is `{label, kind ('color'
 * or 'text'), current, proposed, proposedNote}`, and sends the update itself
 * in `onConfirm(dialog, button)`.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqThemingUpdateDialog() {
	var BLOCK = 'thematiq-admin-dialog'

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
	 * A value: a swatch for a colour, then the value.
	 *
	 * @param {Function} h The element helper.
	 * @param {object} diff The setting.
	 * @param {string} value The value.
	 * @param {string} [note] A word after the value.
	 * @return {HTMLElement} The value.
	 */
	function value(h, diff, value, note) {
		var swatch = null
		var text = h('span', { text: value || '' })
		if (diff.kind === 'color' && value) {
			swatch = h('span', { class: BLOCK + '__swatch ' + BLOCK + '__swatch--large', 'aria-hidden': 'true' })
			swatch.style.background = value
			text = h('code', { text: value })
		}
		return h('span', { class: BLOCK + '__value' }, [swatch, text, note ? ' ' : null, note ? h('span', { class: BLOCK + '__value-note', text: note }) : null])
	}

	/**
	 * Open the dialog.
	 *
	 * @param {object} options The dialog.
	 * @param {Element} [options.opener] What gets focus back when it closes.
	 * @param {'match'|'reset'} options.mode Match the chosen set, or go back to Nextcloud's defaults.
	 * @param {string} options.setName The chosen token set.
	 * @param {Array<object>} options.diffs The settings that differ.
	 * @param {Function} options.onConfirm Called with the open dialog and the button pressed.
	 * @return {object|null} The open dialog, or null without the dialog frame.
	 */
	function open(options) {
		var frame = window.ThematiqAdminDialog
		if (frame === undefined) {
			return null
		}
		var h = frame.h
		var reset = options.mode === 'reset'
		var dialog = frame.open({
			id: 'nldesign-theming-dialog-overlay',
			width: 720,
			opener: options.opener,
			eyebrow: tr('After choosing a token set'),
			title: reset
				? tr('Reset the Nextcloud theme to the default?')
				: tr('Update the Nextcloud theme to {name}?', { name: options.setName }),
			body: [
				h('p', { class: BLOCK + '__lead', text: tr('Thematiq aligns the Nextcloud values with the token set, so sign-in page, email and apps show the same house style. Only values that differ are listed here; without a proposed value something stays as it is.') }),
				frame.table([tr('Setting'), tr('Current'), tr('Proposed')], (options.diffs || []).map(function (diff) {
					return [diff.label, value(h, diff, diff.current), value(h, diff, diff.proposed, diff.proposedNote)]
				})),
				frame.notice(reset
					? tr("Stock Nextcloud means Nextcloud's own colors and logo. Everything Thematiq synced into Nextcloud theming is undone; the token set itself is already applied.")
					: tr('If you choose the token set Nextcloud, the same dialog asks "Reset the Nextcloud theme to the default?" with the Nextcloud default values in the Proposed column.'), 'neutral'),
			],
			actions: [
				{ label: tr('Cancel'), cancel: true, className: 'nldesign-dialog-cancel' },
				{
					label: reset ? tr('Reset theme') : tr('Update theme'),
					variant: 'primary',
					icon: 'check',
					className: 'nldesign-dialog-confirm',
					onClick: function (handle) {
						options.onConfirm(handle, handle.el.querySelector('.nldesign-dialog-confirm'))
					},
				},
			],
		})
		dialog.el.addEventListener('click', function (event) {
			if (event.target === dialog.el) {
				dialog.close()
			}
		})
		return dialog
	}

	window.ThematiqThemingUpdateDialog = { open: open }
})()
