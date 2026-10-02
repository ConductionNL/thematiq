/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * "Your own tokens" and the deprecations list under the token editor
 * (openspec/specs/own-tokens, openspec/specs/token-deprecations). Adds, edits and removes an
 * administrator's own `--nldesign-org-*` tokens, records deprecations for any
 * thematiq token, and marks deprecated rows in the token editor with a badge.
 *
 * Vanilla JS like admin.js. The two dialogs are native `<dialog>` elements in
 * the template, opened modal, so focus stays inside and Escape closes them;
 * focus returns to the button that opened them.
 *
 * @spec openspec/specs/own-tokens/spec.md#requirement-an-administrator-edits-and-removes-own-tokens
 * @spec openspec/specs/token-deprecations/spec.md#requirement-a-passed-removal-date-is-flagged-never-acted-on
 */

;(function ownTokensModule() {
	'use strict'

	var BASE = '/apps/thematiq/settings/tokens'

	/** The section, the dialogs and the lists, found once the page is ready. */
	var el = {}

	/** The last loaded own tokens and deprecations. */
	var state = { tokens: [], deprecations: [], prefix: '--nldesign-org-' }

	/** The element that opened a dialog, so focus can go back to it. */
	var opener = null

	/**
	 * A request to the token endpoints.
	 *
	 * @param {string} method The HTTP method.
	 * @param {string} path The path under /settings/tokens.
	 * @param {object} [body] The JSON body.
	 * @return {Promise<{ok: boolean, data: object}>} The answer.
	 */
	function request(method, path, body) {
		var options = {
			method: method,
			headers: { requesttoken: OC.requestToken },
		}
		if (body !== undefined) {
			options.headers['Content-Type'] = 'application/json'
			options.body = JSON.stringify(body)
		}
		return fetch(OC.generateUrl(BASE + path), options).then(function (r) {
			return r.json().then(function (data) {
				return { ok: r.ok, data: data || {} }
			})
		})
	}

	/**
	 * Say something in the section's status region.
	 *
	 * @param {string} text The message.
	 * @return {void}
	 */
	function say(text) {
		if (el.status) {
			el.status.textContent = text
		}
	}

	/**
	 * The severity label, as text, so colour is never the only signal.
	 *
	 * @param {string} severity info, warning or critical.
	 * @return {string} The label.
	 */
	function severityLabel(severity) {
		if (severity === 'critical') {
			return t('thematiq', 'Deprecated: critical')
		}
		if (severity === 'info') {
			return t('thematiq', 'Deprecated: info')
		}
		return t('thematiq', 'Deprecated: warning')
	}

	/**
	 * A deprecation badge for a row.
	 *
	 * @param {object} record The deprecation.
	 * @return {HTMLElement} The badge.
	 */
	function badge(record) {
		var span = document.createElement('span')
		span.className =
			'nldesign-deprecation-badge nldesign-deprecation-badge--'
			+ record.severity
		var text = severityLabel(record.severity)
		if (record.due) {
			text += ', ' + t('thematiq', 'Due for removal')
		}
		span.textContent = text
		return span
	}

	/**
	 * A small button.
	 *
	 * @param {string} label The visible text.
	 * @param {string} accessibleName The full name for assistive technology.
	 * @param {Function} onClick The handler.
	 * @return {HTMLButtonElement} The button.
	 */
	function button(label, accessibleName, onClick) {
		var b = document.createElement('button')
		b.type = 'button'
		b.className = 'nldesign-btn nldesign-btn--small'
		b.textContent = label
		b.setAttribute('aria-label', accessibleName)
		b.addEventListener('click', function () {
			onClick(b)
		})
		return b
	}

	/**
	 * The contrast hint of an own colour against the page background (WCAG AA for
	 * non-text contrast is 3:1). Empty when the colour cannot be read.
	 *
	 * @param {string} value The colour.
	 * @return {string} The hint.
	 */
	function contrastHint(value) {
		var converter = window.NldesignTokenConverter
		if (!converter) {
			return ''
		}
		var page =
			getComputedStyle(document.body)
				.getPropertyValue('--color-main-background')
				.trim() || '#ffffff'
		var colour = converter.parseColor(value)
		var background = converter.parseColor(page)
		if (colour === null || background === null) {
			return ''
		}
		var ratio = Math.round(converter.ratio(colour, background) * 100) / 100
		return ratio < 3
			? t(
					'thematiq',
					'Contrast {ratio}:1 on the page background, below 3:1.',
					{ ratio: ratio },
				)
			: t('thematiq', 'Contrast {ratio}:1 on the page background.', {
					ratio: ratio,
				})
	}

	/**
	 * Draw the own tokens list.
	 *
	 * @return {void}
	 */
	function renderTokens() {
		el.tokenList.textContent = ''
		if (state.tokens.length === 0) {
			var empty = document.createElement('li')
			empty.className = 'nldesign-own-token-empty'
			empty.textContent = t(
				'thematiq',
				'No own tokens yet. Add one, then use it in custom CSS.',
			)
			el.tokenList.appendChild(empty)
			return
		}
		state.tokens.forEach(function (token) {
			var item = document.createElement('li')
			item.className = 'nldesign-own-token-row'
			item.setAttribute('data-own-token', token.name)

			var text = document.createElement('span')
			text.className = 'nldesign-own-token-text'
			var label = document.createElement('strong')
			label.textContent = token.label
			var name = document.createElement('code')
			name.textContent = token.name
			var value = document.createElement('span')
			value.textContent =
				token.value
				+ (token.darkValue
					? ' / ' + t('thematiq', 'dark') + ' ' + token.darkValue
					: '')
			text.appendChild(label)
			text.appendChild(document.createTextNode(' '))
			text.appendChild(name)
			text.appendChild(document.createTextNode(' '))
			text.appendChild(value)
			item.appendChild(text)
			var hint = token.type === 'color' ? contrastHint(token.value) : ''
			if (hint !== '') {
				var contrast = document.createElement('span')
				contrast.className = 'nldesign-own-token-contrast'
				contrast.textContent = hint
				item.appendChild(contrast)
			}
			if (token.deprecation) {
				item.appendChild(badge(token.deprecation))
			}

			item.appendChild(
				button(
					t('thematiq', 'Edit'),
					t('thematiq', 'Edit {name}', { name: token.name }),
					function (b) {
						openTokenDialog(b, token)
					},
				),
			)
			item.appendChild(
				button(
					t('thematiq', 'Deprecate'),
					t('thematiq', 'Deprecate {name}', { name: token.name }),
					function (b) {
						openDeprecationDialog(b, token.name, token.deprecation)
					},
				),
			)
			item.appendChild(
				button(
					t('thematiq', 'Remove'),
					t('thematiq', 'Remove {name}', { name: token.name }),
					function () {
						removeToken(token)
					},
				),
			)
			el.tokenList.appendChild(item)
		})
	}

	/**
	 * Draw the deprecations list.
	 *
	 * @return {void}
	 */
	function renderDeprecations() {
		el.deprecationList.textContent = ''
		if (state.deprecations.length === 0) {
			var empty = document.createElement('li')
			empty.textContent = t('thematiq', 'No deprecated tokens.')
			el.deprecationList.appendChild(empty)
			return
		}
		state.deprecations.forEach(function (record) {
			var item = document.createElement('li')
			item.className = 'nldesign-deprecation-row'
			item.setAttribute('data-deprecated-token', record.token)
			var name = document.createElement('code')
			name.textContent = record.token
			item.appendChild(name)
			item.appendChild(document.createTextNode(' '))
			item.appendChild(badge(record))

			var details = []
			if (record.replacement) {
				details.push(
					t('thematiq', 'use {replacement}', {
						replacement: record.replacement,
					}),
				)
			}
			if (record.removalDate) {
				details.push(
					t('thematiq', 'removal {date}', { date: record.removalDate }),
				)
			}
			if (record.state === 'removed') {
				details.push(t('thematiq', 'removed'))
			}
			if (record.own === false) {
				details.push(
					t(
						'thematiq',
						'Notice only: the value comes from the token set',
					),
				)
			}
			if (record.message) {
				details.push(record.message)
			}
			if (details.length > 0) {
				var detail = document.createElement('span')
				detail.className = 'nldesign-deprecation-detail'
				detail.textContent = ' ' + details.join(', ')
				item.appendChild(detail)
			}

			item.appendChild(
				button(
					t('thematiq', 'Edit'),
					t('thematiq', 'Edit the deprecation of {name}', {
						name: record.token,
					}),
					function (b) {
						openDeprecationDialog(b, record.token, record)
					},
				),
			)
			item.appendChild(
				button(
					t('thematiq', 'Withdraw'),
					t('thematiq', 'Withdraw the deprecation of {name}', {
						name: record.token,
					}),
					function () {
						withdraw(record.token)
					},
				),
			)
			el.deprecationList.appendChild(item)
		})
	}

	/**
	 * Put a deprecation badge on each deprecated row of the token editor.
	 *
	 * @return {void}
	 */
	function markEditorRows() {
		var editor = document.getElementById('nldesign-token-editor')
		if (editor === null) {
			return
		}
		editor
			.querySelectorAll('.nldesign-deprecation-badge')
			.forEach(function (old) {
				old.remove()
			})
		state.deprecations.forEach(function (record) {
			var row = editor.querySelector(
				'[data-token-row="' + record.token + '"]',
			)
			if (row === null) {
				return
			}
			var wrap = row.querySelector('.nldesign-token-label-wrap') || row
			wrap.appendChild(badge(record))
		})
	}

	/**
	 * Load both lists and draw them.
	 *
	 * @return {Promise<void>} Done.
	 */
	function load() {
		return Promise.all([
			request('GET', '/own'),
			request('GET', '/deprecations'),
		])
			.then(function (answers) {
				state.tokens = answers[0].data.tokens || []
				state.prefix = answers[0].data.prefix || state.prefix
				state.deprecations = answers[1].data.deprecations || []
				renderTokens()
				renderDeprecations()
				markEditorRows()
			})
			.catch(function () {
				say(t('thematiq', 'Your own tokens could not be loaded.'))
			})
	}

	/**
	 * Open a dialog modal, remembering who opened it.
	 *
	 * @param {HTMLDialogElement} dialog The dialog.
	 * @param {HTMLElement} from The element that opened it.
	 * @return {void}
	 */
	function openDialog(dialog, from) {
		opener = from
		dialog.querySelector('.nldesign-dialog-error').textContent = ''
		if (typeof dialog.showModal === 'function') {
			dialog.showModal()
		} else {
			dialog.setAttribute('open', '')
		}
		var first = dialog.querySelector('input:not([disabled]), select')
		if (first) {
			first.focus()
		}
	}

	/**
	 * Close a dialog and give focus back.
	 *
	 * @param {HTMLDialogElement} dialog The dialog.
	 * @return {void}
	 */
	function closeDialog(dialog) {
		if (typeof dialog.close === 'function') {
			dialog.close()
		} else {
			dialog.removeAttribute('open')
		}
		if (opener && typeof opener.focus === 'function') {
			opener.focus()
		}
	}

	/**
	 * Show the dark value field only for a colour.
	 *
	 * @return {void}
	 */
	function syncDarkField() {
		var form = el.tokenDialog.querySelector('form')
		el.tokenDialog.querySelector('.nldesign-own-token-dark').hidden =
			form.elements.type.value !== 'color'
	}

	/**
	 * Open the add or edit dialog.
	 *
	 * @param {HTMLElement} from The button that opened it.
	 * @param {object|null} token The token to edit, or null to add one.
	 * @return {void}
	 */
	function openTokenDialog(from, token) {
		var form = el.tokenDialog.querySelector('form')
		form.reset()
		form.dataset.editing = token ? token.name : ''
		form.elements.slug.disabled = Boolean(token)
		if (token) {
			form.elements.slug.value = token.name.slice(state.prefix.length)
			form.elements.label.value = token.label
			form.elements.type.value = token.type
			form.elements.value.value = token.value
			form.elements.darkValue.value = token.darkValue || ''
			form.elements.description.value = token.description || ''
		}
		el.tokenDialog.querySelector('.nldesign-dialog-title').textContent =
			token
				? t('thematiq', 'Edit {name}', { name: token.name })
				: t('thematiq', 'Add a token')
		syncDarkField()
		openDialog(el.tokenDialog, from)
	}

	/**
	 * Save the add or edit dialog.
	 *
	 * @return {Promise<void>} Done.
	 */
	function saveToken() {
		var form = el.tokenDialog.querySelector('form')
		var editing = form.dataset.editing
		var body = {
			slug: form.elements.slug.value.trim(),
			label: form.elements.label.value,
			type: form.elements.type.value,
			value: form.elements.value.value,
			darkValue:
				form.elements.type.value === 'color'
					? form.elements.darkValue.value
					: '',
			description: form.elements.description.value,
		}
		var call = editing
			? request('PUT', '/own/' + encodeURIComponent(editing), body)
			: request('POST', '/own', body)
		return call.then(function (answer) {
			if (!answer.ok) {
				el.tokenDialog.querySelector('.nldesign-dialog-error').textContent =
					answer.data.error || t('thematiq', 'The token was not saved.')
				return
			}
			closeDialog(el.tokenDialog)
			say(t('thematiq', 'Token saved.'))
			return load()
		})
	}

	/**
	 * Remove an own token after confirmation, naming its deprecation in the question.
	 *
	 * @param {object} token The token.
	 * @return {Promise<void>|undefined} Done.
	 */
	function removeToken(token) {
		var question = token.deprecation
			? t(
					'thematiq',
					'Remove {name}? It is deprecated ({severity}). Its deprecation is kept, marked as removed.',
					{ name: token.name, severity: token.deprecation.severity },
				)
			: t(
					'thematiq',
					'Remove {name}? Custom CSS or apps that read it lose its value.',
					{ name: token.name },
				)
		if (window.confirm(question) !== true) {
			return undefined
		}
		return request('DELETE', '/own/' + encodeURIComponent(token.name)).then(
			function (answer) {
				say(
					answer.ok
						? t('thematiq', 'Token removed.')
						: answer.data.error
								|| t('thematiq', 'The token was not removed.'),
				)
				return load()
			},
		)
	}

	/**
	 * Open the deprecate dialog.
	 *
	 * @param {HTMLElement} from The button that opened it.
	 * @param {string} name The token, or '' to type one.
	 * @param {object|undefined} record Its deprecation, when editing.
	 * @return {void}
	 */
	function openDeprecationDialog(from, name, record) {
		var form = el.deprecationDialog.querySelector('form')
		form.reset()
		form.elements.token.value = name
		form.elements.token.disabled = name !== ''
		if (record) {
			form.elements.severity.value = record.severity
			form.elements.replacement.value = record.replacement || ''
			form.elements.removalDate.value = record.removalDate || ''
			form.elements.message.value = record.message || ''
		}
		openDialog(el.deprecationDialog, from)
	}

	/**
	 * Save the deprecate dialog.
	 *
	 * @return {Promise<void>} Done.
	 */
	function saveDeprecation() {
		var form = el.deprecationDialog.querySelector('form')
		return request('POST', '/deprecations', {
			token: form.elements.token.value.trim(),
			severity: form.elements.severity.value,
			replacement: form.elements.replacement.value.trim(),
			removalDate: form.elements.removalDate.value,
			message: form.elements.message.value,
		}).then(function (answer) {
			if (!answer.ok) {
				el.deprecationDialog.querySelector(
					'.nldesign-dialog-error',
				).textContent =
					answer.data.error
					|| t('thematiq', 'The deprecation was not saved.')
				return
			}
			closeDialog(el.deprecationDialog)
			say(t('thematiq', 'Deprecation saved.'))
			return load()
		})
	}

	/**
	 * Withdraw a deprecation.
	 *
	 * @param {string} name The token.
	 * @return {Promise<void>} Done.
	 */
	function withdraw(name) {
		return request(
			'DELETE',
			'/deprecations/' + encodeURIComponent(name),
		).then(function () {
			say(t('thematiq', 'Deprecation withdrawn.'))
			return load()
		})
	}

	/**
	 * Wire a dialog's form and cancel button.
	 *
	 * @param {HTMLDialogElement} dialog The dialog.
	 * @param {Function} onSubmit What a submit does.
	 * @return {void}
	 */
	function wireDialog(dialog, onSubmit) {
		dialog.querySelector('form').addEventListener('submit', function (event) {
			event.preventDefault()
			onSubmit()
		})
		dialog
			.querySelector('.nldesign-dialog-cancel')
			.addEventListener('click', function () {
				closeDialog(dialog)
			})
		dialog.addEventListener('cancel', function () {
			if (opener && typeof opener.focus === 'function') {
				opener.focus()
			}
		})
	}

	/**
	 * Find the section and start.
	 *
	 * @return {void}
	 */
	function init() {
		el.section = document.getElementById('nldesign-own-tokens')
		if (el.section === null) {
			return
		}
		el.tokenList = document.getElementById('nldesign-own-token-list')
		el.deprecationList = document.getElementById('nldesign-deprecation-list')
		el.status = document.getElementById('nldesign-own-tokens-status')
		el.tokenDialog = document.getElementById('nldesign-own-token-dialog')
		el.deprecationDialog = document.getElementById(
			'nldesign-deprecation-dialog',
		)

		document
			.getElementById('nldesign-own-token-add')
			.addEventListener('click', function (event) {
				openTokenDialog(event.currentTarget, null)
			})
		document
			.getElementById('nldesign-deprecation-add')
			.addEventListener('click', function (event) {
				openDeprecationDialog(event.currentTarget, '', undefined)
			})
		el.tokenDialog
			.querySelector('select[name="type"]')
			.addEventListener('change', syncDarkField)
		wireDialog(el.tokenDialog, saveToken)
		wireDialog(el.deprecationDialog, saveDeprecation)
		document.addEventListener('thematiq:deprecations-changed', load)

		// The token editor renders after this script; badge its rows when they appear.
		var editor = document.getElementById('nldesign-token-editor')
		if (editor !== null && typeof MutationObserver === 'function') {
			new MutationObserver(function (changes) {
				var added = changes.some(function (change) {
					return Array.prototype.some.call(change.addedNodes, function (node) {
						return (
							node.nodeType === 1
							&& !node.classList.contains('nldesign-deprecation-badge')
						)
					})
				})
				if (added) {
					markEditorRows()
				}
			}).observe(editor, { childList: true, subtree: true })
		}

		load()
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { init: init, severityLabel: severityLabel }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init)
	} else {
		init()
	}
})()
