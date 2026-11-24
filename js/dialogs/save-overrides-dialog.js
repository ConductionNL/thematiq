/**
 * Save the overrides (the TqOverridesOpslaan board): asked before the token
 * editor writes its changes on top of a token set. Lists each changed token
 * with what it was and what it becomes.
 *
 * js/admin.js opens it with `window.ThematiqSaveOverridesDialog.open({opener,
 * setName, changes, onConfirm})`. A change is `{name, label, was, becomes}`.
 * The "do not ask again" box carries the class `nldesign-dialog-dontask`, which
 * admin.js wires to the stored flag.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqSaveOverridesDialog() {
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
	 * Whether a value is a colour, so it gets a swatch beside it.
	 *
	 * @param {string} value The value.
	 * @return {boolean} True for a colour.
	 */
	function isColour(value) {
		if (typeof value !== 'string' || value.trim() === '' || value.indexOf('var(') !== -1) {
			return false
		}
		if (typeof CSS !== 'undefined' && typeof CSS.supports === 'function') {
			return CSS.supports('color', value)
		}
		return /^#[0-9a-f]{3,8}$/i.test(value.trim())
	}

	/**
	 * Open the dialog.
	 *
	 * @param {object} options The dialog.
	 * @param {Element} [options.opener] What gets focus back when it closes.
	 * @param {string} options.setName The token set the overrides go on top of.
	 * @param {Array<object>} options.changes The changed tokens.
	 * @param {Function} options.onConfirm Called once the admin saves.
	 * @return {object|null} The open dialog, or null without the dialog frame.
	 */
	function open(options) {
		var frame = window.ThematiqAdminDialog
		if (frame === undefined) {
			return null
		}
		var h = frame.h
		var changes = options.changes || []
		var title = changes.length === 1
			? tr('Save 1 override?')
			: tr('Save {count} overrides?', { count: changes.length })
		var body = [
			h('p', { class: BLOCK + '__lead', text: tr('They go on top of {set}. Save puts them live for everyone and writes a line in the log.', { set: options.setName }) }),
		]
		if (changes.length > 0) {
			body.push(frame.table([tr('Token'), tr('Was'), tr('Becomes')], changes.map(function (change) {
				return [
					h('span', {}, [
						h('span', { class: BLOCK + '__token-label', text: change.label || change.name }),
						h('code', { text: change.name }),
					]),
					frame.value(change.was, { colour: isColour(change.was), code: true }),
					frame.value(change.becomes, { colour: isColour(change.becomes), code: true }),
				]
			})))
		}
		body.push(h('label', { class: BLOCK + '__check' }, [
			h('input', { type: 'checkbox', class: 'nldesign-dialog-dontask' }),
			h('span', {}, [
				tr('Do not ask again before saving'),
				h('span', { class: BLOCK + '__check-hint', text: tr('Can be reset below the token list.') }),
			]),
		]))

		return frame.open({
			id: 'nldesign-save-dialog-overlay',
			width: 720,
			opener: options.opener,
			focus: '.nldesign-dialog-confirm',
			eyebrow: tr('Components and tokens'),
			title: title,
			body: body,
			actions: [
				{ label: tr('Cancel'), cancel: true, className: 'nldesign-dialog-cancel' },
				{
					label: tr('Save overrides'),
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

	window.ThematiqSaveOverridesDialog = { open: open }
})()
