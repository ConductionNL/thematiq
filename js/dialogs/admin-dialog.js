/**
 * The frame every dialog of the admin page is drawn in, as the Tq dialog
 * boards draw it: a line naming where the dialog comes from over its title,
 * a round close button, the body, and a footer with its buttons on the right.
 *
 * `window.ThematiqAdminDialog.open(options)` draws a native modal <dialog>,
 * gives focus to its first field or button and, when it closes (Escape, the
 * close button, Cancel or an action), removes it and gives focus back to the
 * opener. Text only, never markup: every label is set with textContent.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqAdminDialog() {
	var SVG_NS = 'http://www.w3.org/2000/svg'
	var BLOCK = 'thematiq-admin-dialog'
	var ICONS = {
		close: 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z',
		check: 'M21,7L9,19L3.5,13.5L4.91,12.09L9,16.17L19.59,5.59L21,7Z',
		warning: 'M12,2L1,21H23M12,6L19.53,19H4.47M11,10V14H13V10M11,16V18H13V16',
		info: 'M11,9H13V7H11M12,20C7.59,20 4,16.41 4,12C4,7.59 7.59,4 12,4C16.41,4 20,7.59 20,12C20,16.41 16.41,20 12,20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,10 0 0,0 12,2M11,17H13V11H11V17Z',
		swap: 'M21,9L17,5V8H10V10H17V13M7,11L3,15L7,19V16H14V14H7V11Z',
		restore:
			'M13,3A9,9 0 0,0 4,12H1L4.89,15.89L4.96,16.03L9,12H6A7,7 0 0,1 13,5A7,7 0 0,1 20,12A7,7 0 0,1 13,19C11.07,19 9.32,18.21 8.06,16.94L6.64,18.36C8.27,20 10.5,21 13,21A9,9 0 0,0 22,12A9,9 0 0,0 13,3Z',
	}

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
	 * An element with attributes and children. A string child becomes text.
	 *
	 * @param {string} tag The tag.
	 * @param {object} attrs The attributes; `text` sets the text, `on` the listeners.
	 * @param {Array} children The children.
	 * @return {HTMLElement} The element.
	 */
	function h(tag, attrs, children) {
		var el = document.createElement(tag)
		Object.keys(attrs || {}).forEach(function (name) {
			var value = attrs[name]
			if (value === null || value === undefined || value === false) {
				return
			}
			if (name === 'text') {
				el.textContent = value
			} else if (name === 'on') {
				Object.keys(value).forEach(function (type) {
					el.addEventListener(type, value[type])
				})
			} else {
				el.setAttribute(name, value === true ? '' : String(value))
			}
		})
		;(children || []).forEach(function (child) {
			if (child !== null && child !== undefined && child !== false) {
				el.appendChild(
					typeof child === 'string'
						? document.createTextNode(child)
						: child,
				)
			}
		})
		return el
	}

	/**
	 * An icon in the text colour, hidden from assistive technology.
	 *
	 * @param {string} name A key of ICONS, or path data.
	 * @param {number} size The size in pixels.
	 * @return {SVGElement} The icon.
	 */
	function icon(name, size) {
		var el = document.createElementNS(SVG_NS, 'svg')
		el.setAttribute('viewBox', '0 0 24 24')
		el.setAttribute('width', String(size || 18))
		el.setAttribute('height', String(size || 18))
		el.setAttribute('fill', 'currentColor')
		el.setAttribute('aria-hidden', 'true')
		var path = document.createElementNS(SVG_NS, 'path')
		path.setAttribute('d', ICONS[name] || name || '')
		el.appendChild(path)
		return el
	}

	var LIST_ICONS = { goes: 'close', stays: 'check', changes: 'swap' }

	/**
	 * A list of what an action removes (a cross), keeps (a tick) or changes (two arrows).
	 *
	 * @param {string[]} items The lines.
	 * @param {'goes'|'stays'|'changes'} kind Which list.
	 * @return {HTMLElement} The list.
	 */
	function list(items, kind) {
		return h(
			'ul',
			{ class: BLOCK + '__list ' + BLOCK + '__list--' + kind },
			items.map(function (item) {
				return h('li', {}, [
					icon(LIST_ICONS[kind] || 'check', 18),
					h('span', { text: item }),
				])
			}),
		)
	}

	/**
	 * A notice in the body: a warning, a piece of information, or a grey aside.
	 *
	 * @param {string} text The notice.
	 * @param {'warning'|'caution'|'info'|'neutral'} kind Which notice.
	 * @return {HTMLElement} The notice.
	 */
	function notice(text, kind) {
		return h(
			'div',
			{ class: BLOCK + '__notice ' + BLOCK + '__notice--' + kind },
			[
				icon(
					kind === 'warning' || kind === 'caution' ? 'warning' : 'info',
					18,
				),
				h('span', { text: text }),
			],
		)
	}

	/**
	 * A value in a table: a swatch beside a colour, then the value, then a note.
	 *
	 * @param {string} value The value.
	 * @param {object} [options] How to draw it.
	 * @param {boolean} [options.colour] Whether the value is a colour.
	 * @param {boolean} [options.large] An 18px swatch instead of 14px.
	 * @param {boolean} [options.code] Show the value as code (always for a colour).
	 * @param {string} [options.note] A word after the value.
	 * @return {HTMLElement} The value.
	 */
	function value(value, options) {
		var opts = options || {}
		var shown = typeof value === 'string' && value !== '' ? value : '–'
		var swatch = null
		if (opts.colour === true && typeof value === 'string' && value !== '') {
			swatch = h('span', {
				class:
					BLOCK
					+ '__swatch'
					+ (opts.large === true ? ' ' + BLOCK + '__swatch--large' : ''),
				'aria-hidden': 'true',
			})
			swatch.style.background = value
		}
		var text =
			opts.colour === true || opts.code === true
				? h('code', { text: shown })
				: h('span', { text: shown })
		return h('span', { class: BLOCK + '__value' }, [
			swatch,
			text,
			opts.note ? ' ' : null,
			opts.note
				? h('span', { class: BLOCK + '__value-note', text: opts.note })
				: null,
		])
	}

	/**
	 * A table of the body: column heads and rows of cells.
	 *
	 * @param {string[]} heads The column heads.
	 * @param {Array<Array<Node|string>>} rows Each row's cells; a cell is a node or text.
	 * @return {HTMLElement} The table.
	 */
	function table(heads, rows) {
		return h('table', { class: BLOCK + '__table' }, [
			h('thead', {}, [
				h(
					'tr',
					{},
					heads.map(function (head) {
						return h('th', { scope: 'col', text: head })
					}),
				),
			]),
			h(
				'tbody',
				{},
				rows.map(function (cells) {
					return h(
						'tr',
						{},
						cells.map(function (cell) {
							return h('td', {}, [cell])
						}),
					)
				}),
			),
		])
	}

	/**
	 * A text field with its label over it, a hint under it and a line for a refusal.
	 *
	 * @param {object} options The field.
	 * @param {string} options.id The input's id; the hint and the refusal take it as their prefix.
	 * @param {string} options.label The label.
	 * @param {string} [options.placeholder] The placeholder.
	 * @param {string} [options.hint] The line under the field.
	 * @return {HTMLElement} The field.
	 */
	function field(options) {
		var hintId = options.hint ? options.id + '-hint' : null
		return h('div', { class: BLOCK + '__field' }, [
			h('label', { for: options.id, text: options.label }),
			h('input', {
				type: 'text',
				id: options.id,
				placeholder: options.placeholder,
				'aria-describedby': hintId,
			}),
			hintId
				? h('span', {
						class: BLOCK + '__hint',
						id: hintId,
						text: options.hint,
					})
				: null,
			h('p', {
				class: BLOCK + '__error',
				id: options.id.replace(/-name$/, '') + '-error',
				role: 'alert',
				hidden: true,
			}),
		])
	}

	/**
	 * Open a dialog.
	 *
	 * @param {object} options The dialog.
	 * @param {string} options.eyebrow The line over the title: where the dialog comes from.
	 * @param {string} options.title The title.
	 * @param {Array<Node>} options.body The body's content.
	 * @param {Array<object>} options.actions The footer buttons, left to right: `{label, variant ('secondary',
	 *   'primary', 'danger' or 'link'), icon, className, cancel (closes without acting), onClick(dialog)}`.
	 * @param {number} [options.width] The widest the dialog grows, in pixels (560 when left out).
	 * @param {string} [options.id] The dialog's id.
	 * @param {string} [options.focus] A selector for what gets focus when it opens.
	 * @param {Element} [options.opener] What gets focus back when the dialog closes.
	 * @param {Function} [options.onClose] Called once the dialog has closed.
	 * @param {Function} [options.onDismiss] Called instead of closing on Escape and the close button.
	 * @return {{el: HTMLDialogElement, close: Function, busy: Function}} The open dialog.
	 */
	function open(options) {
		var opener = options.opener || document.activeElement
		var titleId = BLOCK + '-title-' + Math.random().toString(36).slice(2)
		var closed = false
		var el = h('dialog', {
			class: BLOCK,
			id: options.id,
			'aria-labelledby': titleId,
			style:
				'max-width: min('
				+ (options.width || 560)
				+ 'px, calc(100vw - 32px))',
		})

		/**
		 * Close the dialog and give focus back.
		 *
		 * @return {void}
		 */
		function close() {
			if (closed) {
				return
			}
			closed = true
			if (typeof el.close === 'function' && el.open) {
				el.close()
			}
			el.remove()
			if (opener && typeof opener.focus === 'function') {
				opener.focus()
			}
			if (typeof options.onClose === 'function') {
				options.onClose()
			}
		}

		/**
		 * Lock the dialog's controls while a request is in flight.
		 *
		 * @param {boolean} on Whether a request is in flight.
		 * @return {void}
		 */
		function busy(on) {
			el.querySelectorAll('button, input, select, textarea').forEach(
				function (control) {
					control.disabled = on
				},
			)
			el.setAttribute('aria-busy', on ? 'true' : 'false')
		}

		var handle = { el: el, close: close, busy: busy }
		var actions = (options.actions || []).map(function (action) {
			return h(
				'button',
				{
					type: 'button',
					class:
						BLOCK
						+ '__button '
						+ BLOCK
						+ '__button--'
						+ (action.variant || 'secondary')
						+ (action.className ? ' ' + action.className : ''),
					on: {
						click: function () {
							if (action.cancel === true) {
								close()
								return
							}
							if (typeof action.onClick === 'function') {
								action.onClick(handle)
							}
						},
					},
				},
				[action.icon ? icon(action.icon, 16) : null, action.label],
			)
		})

		/**
		 * Escape or the close button: the dialog's own dismissal when it has one.
		 *
		 * @return {void}
		 */
		function dismiss() {
			if (typeof options.onDismiss === 'function') {
				options.onDismiss()
				return
			}
			close()
		}

		el.appendChild(
			h('div', { class: BLOCK + '__head' }, [
				h('div', { class: BLOCK + '__heading' }, [
					options.eyebrow
						? h('span', {
								class: BLOCK + '__eyebrow',
								text: options.eyebrow,
							})
						: null,
					h('h2', {
						class: BLOCK + '__title',
						id: titleId,
						text: options.title,
					}),
				]),
				h(
					'button',
					{
						type: 'button',
						class: BLOCK + '__close',
						'aria-label': tr('Close'),
						on: { click: dismiss },
					},
					[icon('close', 18)],
				),
			]),
		)
		el.appendChild(h('div', { class: BLOCK + '__body' }, options.body || []))
		el.appendChild(h('div', { class: BLOCK + '__foot' }, actions))

		el.addEventListener('cancel', function (event) {
			event.preventDefault()
			dismiss()
		})
		el.addEventListener('keydown', function (event) {
			if (event.key === 'Escape') {
				event.preventDefault()
				dismiss()
			}
		})

		document.body.appendChild(el)
		if (typeof el.showModal === 'function') {
			el.showModal()
		} else {
			el.setAttribute('open', '')
		}
		var first =
			(options.focus ? el.querySelector(options.focus) : null)
			|| el.querySelector(
				'.'
					+ BLOCK
					+ '__body input[type="text"], .'
					+ BLOCK
					+ '__body select, .'
					+ BLOCK
					+ '__body textarea',
			)
			|| el.querySelector('.' + BLOCK + '__foot button')
		if (first !== null) {
			first.focus()
		}

		return handle
	}

	window.ThematiqAdminDialog = {
		open: open,
		h: h,
		icon: icon,
		list: list,
		notice: notice,
		field: field,
		table: table,
		value: value,
	}
})()
