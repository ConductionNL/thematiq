/**
 * The Menu settings dialog of the workplace apps menu (the AppMenuInstellingen,
 * AppMenuConfiguratie, AppMenuVormgeving and AppMenuExterneApp boards).
 *
 * js/app-menu.js opens it from the gear in the menu's footer with
 * `window.ThematiqMenuSettingsDialog.open({menu, entries, opener})`. It asks
 * the server for the settings (`GET /apps/thematiq/app-menu/settings`), draws
 * a native modal <dialog> with three tabs, and saves to the person's own
 * settings or, for an administrator choosing "For everyone", to the defaults.
 * The server checks every value again. After a save or a reset the page
 * reloads, so the menu, the pins and the stylesheets follow at once.
 *
 * Text only, never markup: every label is set with textContent.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
 */
;(function thematiqMenuSettingsDialog() {
	var SVG_NS = 'http://www.w3.org/2000/svg'
	var BLOCK = 'thematiq-menu-settings'
	var PERSONAL = ['order', 'pinned', 'hidden', 'externalApps']
	var MAX_PINNED = 5
	var RANGES = { columns: [2, 5], rows: [1, 12], rowsAboveFold: [1, 12] }
	var ICONS = {
		close: 'M19,6.41L17.59,5L12,10.59L6.41,5L5,6.41L10.59,12L5,17.59L6.41,19L12,13.41L17.59,19L19,17.59L13.41,12L19,6.41Z',
		handle: 'M9,3H11V5H9V3M13,3H15V5H13V3M9,7H11V9H9V7M13,7H15V9H13V7M9,11H11V13H9V11M13,11H15V13H13V11M9,15H11V17H9V15M13,15H15V17H13V15M9,19H11V21H9V19M13,19H15V21H13V19Z',
		up: 'M13,20H11V8L5.5,13.5L4.08,12.08L12,4.16L19.92,12.08L18.5,13.5L13,8V20Z',
		down: 'M11,4H13V16L18.5,10.5L19.92,11.92L12,19.84L4.08,11.92L5.5,10.5L11,16V4Z',
		pinOn: 'M16,12V4H17V2H7V4H8V12L6,14V16H11.2V22H12.8V16H18V14L16,12Z',
		pinOff: 'M16,12V4H17V2H7V4H8V12L6,14V16H11.2V22H12.8V16H18V14L16,12M8.8,14L10,12.8V4H14V12.8L15.2,14H8.8Z',
		plus: 'M19,13H13V19H11V13H5V11H11V5H13V11H19V13Z',
		minus: 'M19,13H5V11H19V13Z',
		edit: 'M20.71,7.04C21.1,6.65 21.1,6 20.71,5.63L18.37,3.29C18,2.9 17.35,2.9 16.96,3.29L15.12,5.12L18.87,8.87M3,17.25V21H6.75L17.81,9.93L14.06,6.18L3,17.25Z',
		trash: 'M19,4H15.5L14.5,3H9.5L8.5,4H5V6H19M6,19A2,2 0 0,0 8,21H16A2,2 0 0,0 18,19V7H6V19Z',
		external:
			'M14,3V5H17.59L7.76,14.83L9.17,16.24L19,6.41V10H21V3M19,19H5V5H12V3H5C3.89,3 3,3.9 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V12H19V19Z',
		reset: 'M12,4C14.1,4 16.1,4.8 17.6,6.3C20.7,9.4 20.7,14.5 17.6,17.6C15.8,19.5 13.3,20.2 10.9,19.9L11.4,17.9C13.1,18.1 14.9,17.5 16.2,16.2C18.5,13.9 18.5,10.1 16.2,7.7C15.1,6.6 13.5,6 12,6V10.6L7,5.6L12,0.6V4M6.3,17.6C3.7,15 3.3,11 5.1,7.9L6.6,9.4C5.5,11.6 5.9,14.4 7.8,16.2C8.3,16.7 8.9,17.1 9.6,17.4L9,19.4C8,19 7.1,18.4 6.3,17.6Z',
		upload: 'M9,16V10H5L12,3L19,10H15V16H9M5,20V18H19V20H5Z',
	}
	var LIBRARY_LABELS = {
		link: 'iconLink',
		globe: 'iconGlobe',
		building: 'iconBuilding',
		news: 'iconNews',
		book: 'iconBook',
		chart: 'iconChart',
		map: 'iconMap',
		people: 'iconPeople',
		support: 'iconSupport',
		euro: 'iconEuro',
		scales: 'iconScales',
		truck: 'iconTruck',
		key: 'iconKey',
		shield: 'iconShield',
		tree: 'iconTree',
		star: 'iconStar',
	}

	/**
	 * Fill a text with `{name}` placeholders.
	 *
	 * @param {string} template The text.
	 * @param {object} values The values.
	 * @return {string} The text.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	function fill(template, values) {
		return String(template || '').replace(/\{(\w+)\}/g, function (all, name) {
			return name in values ? String(values[name]) : all
		})
	}

	/**
	 * An element with attributes and children. A string child becomes text.
	 *
	 * @param {string} tag The tag.
	 * @param {object} attrs The attributes; `text` sets the text, `on` the listeners.
	 * @param {Array} children The children.
	 * @return {HTMLElement} The element.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
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
	 * A 24px icon in the text colour, hidden from assistive technology.
	 *
	 * @param {string} d The path data.
	 * @param {number} size The size in pixels.
	 * @return {SVGElement} The icon.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	function svg(d, size) {
		var el = document.createElementNS(SVG_NS, 'svg')
		el.setAttribute('viewBox', '0 0 24 24')
		el.setAttribute('width', String(size || 18))
		el.setAttribute('height', String(size || 18))
		el.setAttribute('fill', 'currentColor')
		el.setAttribute('aria-hidden', 'true')
		var path = document.createElementNS(SVG_NS, 'path')
		path.setAttribute('d', d || '')
		el.appendChild(path)
		return el
	}

	/**
	 * A request to the settings endpoints.
	 *
	 * @param {string} method The method.
	 * @param {string} path The path under /apps/thematiq.
	 * @param {object|FormData|undefined} body The body.
	 * @return {Promise<object>} The answer's JSON; rejects on a failure.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-checked-before-they-are-stored
	 */
	function request(method, path, body) {
		var headers = { requesttoken: OC.requestToken, Accept: 'application/json' }
		var payload = body
		if (
			body !== undefined
			&& !(typeof FormData !== 'undefined' && body instanceof FormData)
		) {
			headers['Content-Type'] = 'application/json'
			payload = JSON.stringify(body)
		}
		return fetch(OC.generateUrl('/apps/thematiq' + path), {
			method: method,
			headers: headers,
			body: payload,
		}).then(function (res) {
			if (!res.ok) {
				throw new Error(String(res.status))
			}
			return res.json()
		})
	}

	/**
	 * The entries in an order: the named keys first, then the rest by rank.
	 *
	 * @param {Array<object>} entries The entries {key, rank}.
	 * @param {Array<string>} order The keys.
	 * @return {Array<object>} The entries, sorted.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	function inOrder(entries, order) {
		var index = {}
		;(order || []).forEach(function (key, i) {
			if (!(key in index)) {
				index[key] = i
			}
		})
		var length = Object.keys(index).length
		return entries.slice().sort(function (a, b) {
			var ra = a.key in index ? index[a.key] : length + a.rank
			var rb = b.key in index ? index[b.key] : length + b.rank
			return ra - rb
		})
	}

	/**
	 * A fresh id for a new external app.
	 *
	 * @return {string} 12 hexadecimal characters.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	function newId() {
		var out = ''
		for (var i = 0; i < 12; i++) {
			out += Math.floor(Math.random() * 16).toString(16)
		}
		return out
	}

	/**
	 * Whether an address may be saved: https with a host.
	 *
	 * @param {string} value The address.
	 * @return {boolean} True when it may.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-external-apps-are-checked-on-the-server
	 */
	function isHttps(value) {
		if (!/^https:\/\/[^\s/]+/i.test(value || '')) {
			return false
		}
		try {
			return new URL(value).protocol === 'https:'
		} catch (e) {
			return false
		}
	}

	/**
	 * The working copy of one scope: every setting, the apps in their order,
	 * and the external apps of that scope.
	 *
	 * @param {object} data The server's answer.
	 * @param {string} scope `everyone` or `me`.
	 * @return {object} The form.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-menu-settings-are-admin-defaults-with-per-user-overrides
	 */
	function formFor(data, scope) {
		var base = scope === 'everyone' ? data.defaults : data.effective
		var form = JSON.parse(JSON.stringify(base))
		form.externalApps = (
			scope === 'everyone'
				? data.defaults.externalApps
				: data.effective.externalApps.filter(function (app) {
						return app.own
					})
		).map(function (app) {
			var copy = Object.assign({}, app)
			delete copy.own
			return copy
		})
		return form
	}

	/**
	 * The dialog for one opening.
	 *
	 * @param {object} data The server's answer.
	 * @param {object} options {menu, entries, opener}.
	 * @return {object} {el, close}.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	function build(data, options) {
		var L = data.labels || {}
		var admin = data.admin === true
		var scope = admin ? 'everyone' : 'me'
		var tab = 'items'
		var forms = {
			everyone: admin ? formFor(data, 'everyone') : null,
			me: formFor(data, 'me'),
		}
		var form = forms[scope]
		var touched = {}
		var apps = (options.entries || []).filter(function (entry) {
			return !entry.external
		})
		var t = function (key, values) {
			return fill(L[key] || '', values || {})
		}

		var live = h('div', {
			class: 'hidden-visually',
			role: 'status',
			'aria-live': 'polite',
		})
		var say = function (message) {
			live.textContent = message
		}
		var alertEl = h('p', {
			class: BLOCK + '__error',
			role: 'alert',
			hidden: true,
		})
		var body = h('div', { class: BLOCK + '__body' })
		var dialog = h('dialog', {
			class: BLOCK,
			'aria-labelledby': BLOCK + '-title',
		})

		var editable = function (key) {
			return scope === 'everyone' || PERSONAL.indexOf(key) !== -1
		}

		var close = function () {
			if (dialog.open) {
				dialog.close()
			}
		}
		dialog.addEventListener('close', function () {
			dialog.remove()
			var back =
				options.opener && document.contains(options.opener)
					? options.opener
					: document.querySelector('.app-menu__waffle')
			if (back) {
				back.focus()
			}
		})

		var render = function (focusSelector) {
			var tabs = [
				['items', t('tabItems')],
				['configuration', t('tabConfiguration')],
			]
			if (admin && scope === 'everyone') {
				tabs.push(['theming', t('tabTheming')])
			}
			if (
				!tabs.some(function (item) {
					return item[0] === tab
				})
			) {
				tab = 'items'
			}
			body.textContent = ''
			if (admin) {
				body.appendChild(scopeSwitch())
			}
			body.appendChild(tabList(tabs))
			var panel = h('div', {
				class: BLOCK + '__panel ' + BLOCK + '__panel--' + tab,
				role: 'tabpanel',
				id: BLOCK + '-panel-' + tab,
				'aria-labelledby': BLOCK + '-tab-' + tab,
				tabindex: '0',
			})
			if (tab === 'items') {
				itemsPanel(panel)
			} else if (tab === 'configuration') {
				configurationPanel(panel)
			} else {
				themingPanel(panel)
			}
			body.appendChild(panel)
			if (focusSelector) {
				var target = dialog.querySelector(focusSelector)
				if (target) {
					target.focus()
				}
			}
		}

		var scopeSwitch = function () {
			var choices = [
				['everyone', t('everyone')],
				['me', t('onlyMe')],
			]
			var group = h('div', {
				class: BLOCK + '__scope',
				role: 'radiogroup',
				'aria-label': t('scope'),
				'aria-describedby': BLOCK + '-scope-help',
			})
			choices.forEach(function (choice, i) {
				var on = choice[0] === scope
				group.appendChild(
					h('button', {
						type: 'button',
						role: 'radio',
						class: BLOCK + '__segment',
						'aria-checked': on ? 'true' : 'false',
						tabindex: on ? '0' : '-1',
						'data-scope': choice[0],
						text: choice[1],
						on: {
							click: function () {
								scope = choice[0]
								form = forms[scope]
								render('[data-scope="' + scope + '"]')
							},
							keydown: function (event) {
								if (
									[
										'ArrowLeft',
										'ArrowRight',
										'ArrowUp',
										'ArrowDown',
									].indexOf(event.key) === -1
								) {
									return
								}
								event.preventDefault()
								scope = choices[(i + 1) % 2][0]
								form = forms[scope]
								render('[data-scope="' + scope + '"]')
							},
						},
					}),
				)
			})
			return h('div', { class: BLOCK + '__scope-row' }, [
				group,
				h('span', {
					id: BLOCK + '-scope-help',
					class: BLOCK + '__help',
					text: scope === 'everyone' ? t('everyoneHelp') : t('onlyMeHelp'),
				}),
			])
		}

		var tabList = function (tabs) {
			var list = h('div', {
				class: BLOCK + '__tabs',
				role: 'tablist',
				'aria-label': t('tabs'),
			})
			tabs.forEach(function (item, i) {
				var on = item[0] === tab
				list.appendChild(
					h('button', {
						type: 'button',
						role: 'tab',
						class: BLOCK + '__tab',
						id: BLOCK + '-tab-' + item[0],
						'aria-selected': on ? 'true' : 'false',
						'aria-controls': BLOCK + '-panel-' + item[0],
						tabindex: on ? '0' : '-1',
						text: item[1],
						on: {
							click: function () {
								tab = item[0]
								render('#' + BLOCK + '-tab-' + tab)
							},
							keydown: function (event) {
								var next = -1
								if (event.key === 'ArrowRight') {
									next = (i + 1) % tabs.length
								} else if (event.key === 'ArrowLeft') {
									next = (i - 1 + tabs.length) % tabs.length
								} else if (event.key === 'Home') {
									next = 0
								} else if (event.key === 'End') {
									next = tabs.length - 1
								}
								if (next === -1) {
									return
								}
								event.preventDefault()
								tab = tabs[next][0]
								render('#' + BLOCK + '-tab-' + tab)
							},
						},
					}),
				)
			})
			return h('div', { class: BLOCK + '__tabs-row' }, [list])
		}

		var swatch = function (on, id, labelledby, onToggle, disabled) {
			return h(
				'button',
				{
					type: 'button',
					role: 'switch',
					class: BLOCK + '__switch',
					id: id,
					'aria-checked': on ? 'true' : 'false',
					'aria-labelledby': labelledby,
					'aria-label': labelledby ? null : id,
					disabled: disabled,
					on: { click: onToggle },
				},
				[h('span', { class: BLOCK + '__knob', 'aria-hidden': 'true' })],
			)
		}

		var iconButton = function (label, d, onClick, extra) {
			return h(
				'button',
				Object.assign(
					{
						type: 'button',
						class: BLOCK + '__icon-button',
						'aria-label': label,
						on: { click: onClick },
					},
					extra || {},
				),
				[svg(d, 16)],
			)
		}

		// The apps of the Menu items tab, in the form's order.
		var moveIn = function (list, key, by) {
			var from = list.indexOf(key)
			var to = from + by
			if (from === -1 || to < 0 || to >= list.length) {
				return false
			}
			list.splice(from, 1)
			list.splice(to, 0, key)
			return true
		}

		var itemsPanel = function (panel) {
			var ordered = inOrder(apps, form.order)
			var keys = ordered.map(function (entry) {
				return entry.key
			})
			var pinnedCount = form.pinned.length
			var list = h('ul', { class: BLOCK + '__list ' + BLOCK + '__list--apps' })
			ordered.forEach(function (entry) {
				var shown = form.hidden.indexOf(entry.key) === -1
				var pinned = form.pinned.indexOf(entry.key) !== -1
				var move = function (by, selector) {
					if (!editable('order')) {
						return
					}
					form.order = keys.slice()
					touched.order = true
					if (moveIn(form.order, entry.key, by)) {
						say(
							fill((options.menu.labels || {}).moved, {
								app: entry.name,
								place: form.order.indexOf(entry.key) + 1,
								total: form.order.length,
							}),
						)
						render('[data-key="' + entry.key + '"] ' + selector)
					}
				}
				var row = h(
					'li',
					{
						class:
							BLOCK
							+ '__row'
							+ (shown ? '' : ' ' + BLOCK + '__row--off'),
						'data-key': entry.key,
						on: {
							keydown: function (event) {
								if (
									!event.altKey
									|| (event.key !== 'ArrowUp'
										&& event.key !== 'ArrowDown')
								) {
									return
								}
								event.preventDefault()
								var cls = event.target.getAttribute('data-part')
								move(
									event.key === 'ArrowUp' ? -1 : 1,
									cls
										? '[data-part="' + cls + '"]'
										: '[data-part="up"]',
								)
							},
						},
					},
					[
						h(
							'span',
							{
								class: BLOCK + '__handle',
								'aria-hidden': 'true',
								'data-drag': entry.key,
							},
							[svg(ICONS.handle, 18)],
						),
						h('span', {
							class:
								BLOCK
								+ '__app-icon'
								+ (entry.work
									? ' ' + BLOCK + '__app-icon--work'
									: ''),
							'aria-hidden': 'true',
							style: entry.icon
								? '--thematiq-app-icon: url("'
									+ entry.icon.replace(/["\\]/g, '')
									+ '")'
								: null,
						}),
						h('span', { class: BLOCK + '__name', text: entry.name }),
						iconButton(
							t('moveUp', { app: entry.name }),
							ICONS.up,
							function () {
								move(-1, '[data-part="up"]')
							},
							{ 'data-part': 'up' },
						),
						iconButton(
							t('moveDown', { app: entry.name }),
							ICONS.down,
							function () {
								move(1, '[data-part="down"]')
							},
							{ 'data-part': 'down' },
						),
						iconButton(
							t('pin', { app: entry.name }),
							pinned ? ICONS.pinOn : ICONS.pinOff,
							function () {
								touched.pinned = true
								if (pinned) {
									form.pinned = form.pinned.filter(function (key) {
										return key !== entry.key
									})
								} else if (form.pinned.length < MAX_PINNED) {
									form.pinned = form.pinned.concat([entry.key])
								}
								render(
									'[data-key="'
										+ entry.key
										+ '"] [data-part="pin"]',
								)
							},
							{
								'data-part': 'pin',
								'aria-pressed': pinned ? 'true' : 'false',
								class: BLOCK + '__icon-button ' + BLOCK + '__pin',
								disabled: !pinned && pinnedCount >= MAX_PINNED,
							},
						),
						h('span', { class: BLOCK + '__switch-wrap' }, [
							swatch(
								shown,
								null,
								null,
								function () {
									touched.hidden = true
									form.hidden = shown
										? form.hidden.concat([entry.key])
										: form.hidden.filter(function (key) {
												return key !== entry.key
											})
									render(
										'[data-key="'
											+ entry.key
											+ '"] [data-part="show"]',
									)
								},
								false,
							),
						]),
					],
				)
				var sw = row.querySelector('[role="switch"]')
				sw.setAttribute('aria-label', t('show', { app: entry.name }))
				sw.removeAttribute('id')
				sw.setAttribute('data-part', 'show')
				list.appendChild(row)
			})
			dragSort(list, function (key, beforeKey) {
				touched.order = true
				form.order = keys.filter(function (other) {
					return other !== key
				})
				var at =
					beforeKey === null
						? form.order.length
						: form.order.indexOf(beforeKey)
				form.order.splice(at, 0, key)
				render()
			})
			panel.appendChild(
				h(
					'section',
					{
						class: BLOCK + '__section',
						'aria-labelledby': BLOCK + '-apps',
					},
					[
						h('div', { class: BLOCK + '__section-head' }, [
							h('h3', { id: BLOCK + '-apps', text: t('apps') }),
							h('span', {
								class: BLOCK + '__help',
								text: t('appsHelp', {
									count: pinnedCount,
									max: MAX_PINNED,
								}),
							}),
						]),
						list,
					],
				),
			)
			panel.appendChild(externalSection())
		}

		// Pointer drag inside a list: the row follows the handle, and lands
		// before the row under the pointer.
		var dragSort = function (list, done) {
			var drag = null
			list.addEventListener('pointerdown', function (event) {
				var handle = event.target.closest
					? event.target.closest('[data-drag]')
					: null
				if (handle && event.button === 0) {
					drag = {
						key: handle.getAttribute('data-drag'),
						y: event.clientY,
						moved: false,
					}
					event.preventDefault()
				}
			})
			var move = function (event) {
				if (drag === null) {
					return
				}
				if (!drag.moved && Math.abs(event.clientY - drag.y) < 6) {
					return
				}
				drag.moved = true
				var rows = Array.prototype.slice.call(list.children)
				var before = rows.filter(function (row) {
					var box = row.getBoundingClientRect()
					return event.clientY < box.top + box.height / 2
				})[0]
				drag.before = before ? before.getAttribute('data-key') : null
				rows.forEach(function (row) {
					row.toggleAttribute('data-drop-before', row === before)
					row.toggleAttribute(
						'data-dragging',
						row.getAttribute('data-key') === drag.key,
					)
				})
			}
			var end = function () {
				if (drag === null) {
					return
				}
				var finished = drag
				drag = null
				document.removeEventListener('pointermove', move)
				if (finished.moved && finished.before !== finished.key) {
					done(
						finished.key,
						finished.before === undefined ? null : finished.before,
					)
				}
			}
			list.addEventListener('pointerdown', function () {
				document.addEventListener('pointermove', move)
				document.addEventListener('pointerup', end, { once: true })
			})
		}

		var externalSection = function () {
			var list = h('ul', { class: BLOCK + '__list' })
			var own = form.externalApps
			own.forEach(function (app, i) {
				var host = ''
				try {
					host = new URL(app.url).host
				} catch (e) {
					host = ''
				}
				var move = function (by, part) {
					var to = i + by
					if (to < 0 || to >= own.length) {
						return
					}
					touched.externalApps = true
					own.splice(i, 1)
					own.splice(to, 0, app)
					own.forEach(function (other, n) {
						other.order = n
					})
					say(
						fill((options.menu.labels || {}).moved, {
							app: app.title,
							place: to + 1,
							total: own.length,
						}),
					)
					render(
						'[data-external="'
							+ app.id
							+ '"] [data-part="'
							+ part
							+ '"]',
					)
				}
				list.appendChild(
					h(
						'li',
						{
							class:
								BLOCK
								+ '__row'
								+ (app.visible ? '' : ' ' + BLOCK + '__row--off'),
							'data-external': app.id,
							on: {
								keydown: function (event) {
									if (
										event.altKey
										&& (event.key === 'ArrowUp'
											|| event.key === 'ArrowDown')
									) {
										event.preventDefault()
										move(
											event.key === 'ArrowUp' ? -1 : 1,
											event.target.getAttribute('data-part')
												|| 'up',
										)
									}
								},
							},
						},
						[
							h(
								'span',
								{ class: BLOCK + '__handle', 'aria-hidden': 'true' },
								[svg(ICONS.handle, 18)],
							),
							appIcon(app, 18),
							h(
								'span',
								{ class: BLOCK + '__name ' + BLOCK + '__name--two' },
								[
									h('span', { text: app.title }),
									h('span', {
										class: BLOCK + '__sub',
										text: [app.payoff, host]
											.filter(Boolean)
											.join(' · '),
									}),
								],
							),
							iconButton(
								t('moveUp', { app: app.title }),
								ICONS.up,
								function () {
									move(-1, 'up')
								},
								{ 'data-part': 'up' },
							),
							iconButton(
								t('moveDown', { app: app.title }),
								ICONS.down,
								function () {
									move(1, 'down')
								},
								{ 'data-part': 'down' },
							),
							h('span', { class: BLOCK + '__switch-wrap' }, [
								(function () {
									var sw = swatch(
										app.visible,
										null,
										null,
										function () {
											touched.externalApps = true
											app.visible = !app.visible
											render(
												'[data-external="'
													+ app.id
													+ '"] [data-part="show"]',
											)
										},
										false,
									)
									sw.removeAttribute('id')
									sw.setAttribute(
										'aria-label',
										t('show', { app: app.title }),
									)
									sw.setAttribute('data-part', 'show')
									return sw
								})(),
							]),
							iconButton(
								t('edit', { app: app.title }),
								ICONS.edit,
								function (event) {
									externalForm(app, event.currentTarget)
								},
								{ 'aria-haspopup': 'dialog', 'data-part': 'edit' },
							),
							iconButton(
								t('delete', { app: app.title }),
								ICONS.trash,
								function () {
									touched.externalApps = true
									form.externalApps = own.filter(function (other) {
										return other !== app
									})
									render('[data-part="add-external"]')
								},
								{ 'data-part': 'delete' },
							),
						],
					),
				)
			})
			return h(
				'section',
				{
					class: BLOCK + '__section',
					'aria-labelledby': BLOCK + '-external',
				},
				[
					h('div', { class: BLOCK + '__section-head' }, [
						h('div', {}, [
							h('h3', {
								id: BLOCK + '-external',
								text: t('external'),
							}),
							h('span', {
								class: BLOCK + '__help',
								text: t('externalHelp'),
							}),
						]),
						h(
							'button',
							{
								type: 'button',
								class: BLOCK + '__button',
								'aria-haspopup': 'dialog',
								'data-part': 'add-external',
								on: {
									click: function (event) {
										externalForm(null, event.currentTarget)
									},
								},
							},
							[svg(ICONS.plus, 18), t('addExternal')],
						),
					]),
					own.length > 0
						? list
						: h('p', { class: BLOCK + '__help', text: t('noExternal') }),
				],
			)
		}

		var appIcon = function (app, size) {
			var circle = h('span', {
				class: BLOCK + '__app-icon',
				'aria-hidden': 'true',
			})
			if (app.icon && app.icon.upload) {
				circle.appendChild(
					h('img', {
						src: String(data.iconUrl || '').replace(
							'__id__',
							app.icon.upload,
						),
						alt: '',
					}),
				)
			} else {
				var name = app.icon && app.icon.library
				var drawn = svg((data.library || {})[name] || '', size)
				if ((data.stroke || []).indexOf(name) !== -1) {
					drawn.setAttribute('fill', 'none')
					drawn.setAttribute('stroke', 'currentColor')
					drawn.setAttribute('stroke-width', '1.75')
					drawn.setAttribute('stroke-linecap', 'round')
					drawn.setAttribute('stroke-linejoin', 'round')
				}
				circle.appendChild(drawn)
			}
			return circle
		}

		var stepper = function (key, nameKey, helpKey, lessKey, moreKey, disabled) {
			var id = BLOCK + '-' + key
			var range = RANGES[key]
			var set = function (value) {
				var number = Math.max(
					range[0],
					Math.min(range[1], Math.round(Number(value))),
				)
				if (!isNaN(number) && number !== form[key]) {
					form[key] = number
					render('#' + id)
				}
			}
			return h('div', { class: BLOCK + '__stepper' }, [
				h('span', { class: BLOCK + '__stepper-text' }, [
					h('label', { for: id, text: t(nameKey) }),
					h('span', {
						id: id + '-help',
						class: BLOCK + '__help',
						text: t(helpKey),
					}),
				]),
				h('span', { class: BLOCK + '__stepper-controls' }, [
					iconButton(
						t(lessKey),
						ICONS.minus,
						function () {
							set(form[key] - 1)
						},
						{ disabled: disabled || form[key] <= range[0] },
					),
					h('input', {
						id: id,
						type: 'number',
						inputmode: 'numeric',
						min: range[0],
						max: range[1],
						value: form[key],
						'aria-describedby': id + '-help',
						disabled: disabled,
						on: {
							change: function (event) {
								set(event.target.value)
							},
						},
					}),
					iconButton(
						t(moreKey),
						ICONS.plus,
						function () {
							set(form[key] + 1)
						},
						{ disabled: disabled || form[key] >= range[1] },
					),
				]),
			])
		}

		var radioCards = function (name, legend, choices, key, disabled) {
			var cards = h('div', { class: BLOCK + '__cards' })
			choices.forEach(function (choice) {
				var on = form[key] === choice.value
				var input = h('input', {
					type: 'radio',
					name: BLOCK + '-' + name,
					value: choice.value,
					checked: on,
					disabled: disabled,
					on: {
						change: function () {
							form[key] = choice.value
							render(
								'input[name="'
									+ BLOCK
									+ '-'
									+ name
									+ '"][value="'
									+ choice.value
									+ '"]',
							)
						},
					},
				})
				cards.appendChild(
					h(
						'label',
						{
							class:
								BLOCK
								+ '__card'
								+ (on ? ' ' + BLOCK + '__card--on' : ''),
						},
						[
							h('span', { class: BLOCK + '__card-head' }, [
								input,
								h('span', {
									class: BLOCK + '__card-name',
									text: choice.label,
								}),
							]),
							choice.sample || null,
							choice.help
								? h('span', {
										class: BLOCK + '__help',
										text: choice.help,
									})
								: null,
						],
					),
				)
			})
			return h('fieldset', { class: BLOCK + '__fieldset' }, [
				h('legend', { text: legend }),
				cards,
			])
		}

		var configurationPanel = function (panel) {
			var locked = !editable('columns')
			if (locked) {
				panel.appendChild(
					h('p', { class: BLOCK + '__note', text: t('setByAdmin') }),
				)
			}
			panel.appendChild(
				radioCards(
					'style',
					t('menuStyle'),
					[
						{
							value: 'workplace',
							label: t('workplace'),
							help: t('workplaceHelp'),
							sample: h('span', {
								class:
									BLOCK
									+ '__style-sample '
									+ BLOCK
									+ '__style-sample--workplace',
								'aria-hidden': 'true',
							}),
						},
						{
							value: 'classic',
							label: t('classic'),
							help: t('classicHelp'),
							sample: h('span', {
								class:
									BLOCK
									+ '__style-sample '
									+ BLOCK
									+ '__style-sample--classic',
								'aria-hidden': 'true',
							}),
						},
					],
					'style',
					locked,
				),
			)
			var fold = Math.min(form.rowsAboveFold, form.rows)
			var preview = h('div', {
				class: BLOCK + '__grid-preview',
				'aria-hidden': 'true',
				style: '--columns: ' + form.columns,
			})
			for (var i = 0; i < form.columns * fold; i++) {
				preview.appendChild(h('span', { class: BLOCK + '__cell' }))
			}
			preview.appendChild(
				h('span', { class: BLOCK + '__fold', text: t('moreApps') }),
			)
			for (var j = 0; j < form.columns * (form.rows - fold); j++) {
				preview.appendChild(
					h('span', {
						class: BLOCK + '__cell ' + BLOCK + '__cell--below',
					}),
				)
			}
			panel.appendChild(
				h(
					'section',
					{
						class: BLOCK + '__section ' + BLOCK + '__section--grid',
						'aria-labelledby': BLOCK + '-grid',
					},
					[
						h('div', { class: BLOCK + '__section-head' }, [
							h('h3', { id: BLOCK + '-grid', text: t('grid') }),
							h('span', {
								class: BLOCK + '__help',
								text: t('gridHelp'),
							}),
						]),
						h('div', { class: BLOCK + '__steppers' }, [
							stepper(
								'columns',
								'columns',
								'columnsHelp',
								'fewerColumns',
								'moreColumns',
								locked,
							),
							stepper(
								'rows',
								'rows',
								'rowsHelp',
								'fewerRows',
								'moreRows',
								locked,
							),
							stepper(
								'rowsAboveFold',
								'rowsAboveFold',
								'rowsAboveFoldHelp',
								'fewerRowsAboveFold',
								'moreRowsAboveFold',
								locked,
							),
						]),
						h('figure', { class: BLOCK + '__figure' }, [
							preview,
							h('figcaption', {
								class: BLOCK + '__help',
								text: t('gridPreview', {
									columns: form.columns,
									fold: fold,
									rows: form.rows,
								}),
							}),
						]),
					],
				),
			)
			var toggles = [
				['showCreateActions', 'showCreateActionsHelp'],
				['showExternalApps', 'showExternalAppsHelp'],
			]
			panel.appendChild(
				h(
					'section',
					{
						class: BLOCK + '__section',
						'aria-labelledby': BLOCK + '-sections',
					},
					[
						h('h3', { id: BLOCK + '-sections', text: t('sections') }),
					].concat(
						toggles.map(function (item) {
							return toggleRow(item[0], item[1], locked)
						}),
					),
				),
			)
			if (admin && typeof data.manageUrl === 'string') {
				panel.appendChild(
					h('div', { class: BLOCK + '__admin' }, [
						h(
							'a',
							{
								href: data.manageUrl,
								target: '_blank',
								rel: 'noopener noreferrer',
								class: BLOCK + '__manage',
							},
							[
								t('manageApps'),
								svg(ICONS.external, 16),
								h('span', {
									class: 'hidden-visually',
									text: ' ' + t('newTab'),
								}),
							],
						),
						h('span', {
							class: BLOCK + '__badge',
							text: t('adminsOnly'),
						}),
						h('span', {
							class: BLOCK + '__help',
							text: t('manageHelp'),
						}),
					]),
				)
			}
		}

		var toggleRow = function (key, helpKey, locked) {
			var labelId = BLOCK + '-label-' + key
			return h('div', { class: BLOCK + '__toggle' }, [
				h('span', { class: BLOCK + '__toggle-text' }, [
					h('span', {
						id: labelId,
						class: BLOCK + '__toggle-name',
						text: t(key),
					}),
					h('span', { class: BLOCK + '__help', text: t(helpKey) }),
				]),
				swatch(
					form[key] === true,
					BLOCK + '-switch-' + key,
					labelId,
					function () {
						form[key] = !form[key]
						render('#' + BLOCK + '-switch-' + key)
					},
					locked,
				),
			])
		}

		var themingPanel = function (panel) {
			panel.appendChild(
				radioCards(
					'shape',
					t('shape'),
					[
						{
							value: 'round',
							label: t('round'),
							sample: h(
								'span',
								{
									class: BLOCK + '__shape-sample',
									'aria-hidden': 'true',
								},
								[h('span'), h('span'), h('span')],
							),
						},
						{
							value: 'square',
							label: t('square'),
							sample: h(
								'span',
								{
									class:
										BLOCK
										+ '__shape-sample '
										+ BLOCK
										+ '__shape-sample--square',
									'aria-hidden': 'true',
								},
								[h('span'), h('span'), h('span')],
							),
						},
					],
					'shape',
					false,
				),
			)
			panel.appendChild(
				h(
					'section',
					{ class: BLOCK + '__section', 'aria-label': t('look') },
					[
						toggleRow('tiles', 'tilesHelp', false),
						toggleRow('payoff', 'payoffHelp', false),
					],
				),
			)
			var sizes = [
				['small', t('small')],
				['large', t('large')],
			]
			var group = h('span', {
				class: BLOCK + '__scope',
				role: 'radiogroup',
				'aria-label': t('iconSize'),
			})
			sizes.forEach(function (size, i) {
				var on = form.iconSize === size[0]
				group.appendChild(
					h('button', {
						type: 'button',
						role: 'radio',
						class: BLOCK + '__segment',
						'aria-checked': on ? 'true' : 'false',
						tabindex: on ? '0' : '-1',
						'data-size': size[0],
						text: size[1],
						on: {
							click: function () {
								form.iconSize = size[0]
								render('[data-size="' + size[0] + '"]')
							},
							keydown: function (event) {
								if (
									[
										'ArrowLeft',
										'ArrowRight',
										'ArrowUp',
										'ArrowDown',
									].indexOf(event.key) === -1
								) {
									return
								}
								event.preventDefault()
								form.iconSize = sizes[(i + 1) % 2][0]
								render('[data-size="' + form.iconSize + '"]')
							},
						},
					}),
				)
			})
			group.removeAttribute('aria-label')
			group.setAttribute('aria-labelledby', BLOCK + '-icon-size')
			panel.appendChild(
				h('div', { class: BLOCK + '__inline-field' }, [
					h('span', {
						id: BLOCK + '-icon-size',
						class: BLOCK + '__field-name',
						text: t('iconSize'),
					}),
					group,
				]),
			)
			var colours = h('div', { class: BLOCK + '__inline-radios' })
			;[
				['brand', t('brand'), ''],
				['neutral', t('neutral'), ' ' + BLOCK + '__swatch--neutral'],
			].forEach(function (choice) {
				colours.appendChild(
					h('label', { class: BLOCK + '__inline-radio' }, [
						h('input', {
							type: 'radio',
							name: BLOCK + '-colour',
							value: choice[0],
							checked: form.iconColour === choice[0],
							on: {
								change: function () {
									form.iconColour = choice[0]
									render(
										'input[name="'
											+ BLOCK
											+ '-colour"][value="'
											+ choice[0]
											+ '"]',
									)
								},
							},
						}),
						h('span', {
							class: BLOCK + '__swatch' + choice[2],
							'aria-hidden': 'true',
						}),
						h('span', { text: choice[1] }),
					]),
				)
			})
			panel.appendChild(
				h('fieldset', { class: BLOCK + '__fieldset' }, [
					h('legend', { text: t('iconColour') }),
					colours,
				]),
			)
			var sample = h('div', {
				class: BLOCK + '__sample',
				'aria-hidden': 'true',
				'data-shape': form.shape,
				'data-icon-size': form.iconSize,
				'data-icon-colour': form.iconColour,
				'data-tiles': form.tiles ? 'true' : 'false',
			})
			apps.slice(0, 4).forEach(function (entry) {
				sample.appendChild(
					h('span', { class: BLOCK + '__sample-item' }, [
						h('span', {
							class:
								BLOCK
								+ '__app-icon'
								+ (entry.work
									? ' ' + BLOCK + '__app-icon--work'
									: ''),
							style: entry.icon
								? '--thematiq-app-icon: url("'
									+ entry.icon.replace(/["\\]/g, '')
									+ '")'
								: null,
						}),
						h('span', { class: BLOCK + '__sample-text' }, [
							h('span', { text: entry.name }),
							form.payoff && entry.description
								? h('span', {
										class: BLOCK + '__sub',
										text: entry.description,
									})
								: null,
						]),
					]),
				)
			})
			var sentence = t('previewSentence', {
				shape: form.shape === 'square' ? t('square') : t('round'),
				size: form.iconSize === 'small' ? t('sizeSmall') : t('sizeLarge'),
				colour:
					form.iconColour === 'neutral'
						? t('colourNeutral')
						: t('colourBrand'),
				payoff: form.payoff ? t('withPayoff') : t('withoutPayoff'),
				tiles: form.tiles ? t('withTiles') : t('withoutTiles'),
			})
			panel.appendChild(
				h('figure', { class: BLOCK + '__figure' }, [
					h('span', { class: BLOCK + '__field-name', text: t('preview') }),
					sample,
					h('figcaption', { class: BLOCK + '__help', text: sentence }),
				]),
			)
		}

		// The Add or Edit external app dialog, over this one.
		var externalForm = function (app, opener) {
			var draft = app
				? JSON.parse(JSON.stringify(app))
				: {
						id: newId(),
						title: '',
						payoff: '',
						url: '',
						icon: { library: 'link' },
						visible: true,
						order: form.externalApps.length,
					}
			var sub = h('dialog', {
				class: BLOCK + ' ' + BLOCK + '--form',
				'aria-labelledby': BLOCK + '-form-title',
			})
			var error = {}
			var field = function (key, type, nameKey, helpKey) {
				var id = BLOCK + '-field-' + key
				var input = h('input', {
					id: id,
					type: type,
					value: draft[key],
					placeholder: key === 'url' ? 'https://' : null,
					'aria-describedby':
						id + '-help' + (error[key] ? ' ' + id + '-error' : ''),
					'aria-invalid': error[key] ? 'true' : null,
					on: {
						input: function (event) {
							draft[key] = event.target.value
							updatePreview()
						},
					},
				})
				return h('div', { class: BLOCK + '__field' }, [
					h('label', { for: id, text: t(nameKey) }),
					input,
					h('span', {
						id: id + '-help',
						class: BLOCK + '__help',
						text: t(helpKey),
					}),
					error[key]
						? h('span', {
								id: id + '-error',
								class: BLOCK + '__field-error',
								text: error[key],
							})
						: null,
				])
			}
			var previewIcon = h('span', { class: BLOCK + '__preview-icon' })
			var previewTitle = h('span', { class: BLOCK + '__preview-title' })
			var previewPayoff = h('span', { class: BLOCK + '__sub' })
			var updatePreview = function () {
				previewIcon.textContent = ''
				previewIcon.appendChild(appIcon(draft, 22))
				previewTitle.textContent = draft.title
				previewPayoff.textContent = draft.payoff
			}
			var library = Array.isArray(data.picker)
				? data.picker
				: Object.keys(data.library || {})
			var iconGroup = function () {
				var group = h('div', {
					class: BLOCK + '__icon-grid',
					role: 'radiogroup',
					'aria-label': t('iconLibrary'),
				})
				var current = draft.icon.library
					? library.indexOf(draft.icon.library)
					: -1
				library.forEach(function (name, i) {
					var on = i === current
					group.appendChild(
						h(
							'button',
							{
								type: 'button',
								role: 'radio',
								class: BLOCK + '__icon-choice',
								'aria-checked': on ? 'true' : 'false',
								'aria-label':
									t(LIBRARY_LABELS[name] || name) || name,
								tabindex:
									on || (current === -1 && i === 0) ? '0' : '-1',
								'data-icon': name,
								on: {
									click: function () {
										draft.icon = { library: name }
										redraw('[data-icon="' + name + '"]')
									},
									keydown: function (event) {
										var step = {
											ArrowRight: 1,
											ArrowLeft: -1,
											ArrowDown: 8,
											ArrowUp: -8,
										}[event.key]
										if (!step) {
											return
										}
										event.preventDefault()
										var next =
											library[
												(i + step + library.length)
													% library.length
											]
										draft.icon = { library: next }
										redraw('[data-icon="' + next + '"]')
									},
								},
							},
							[svg(data.library[name], 22)],
						),
					)
				})
				return group
			}
			var fileInput = h('input', {
				type: 'file',
				accept: '.svg,.png,image/svg+xml,image/png',
				class: 'hidden-visually',
				tabindex: '-1',
				'aria-hidden': 'true',
				on: {
					change: function (event) {
						var file = event.target.files && event.target.files[0]
						if (!file) {
							return
						}
						var payload = new FormData()
						payload.append('icon', file)
						request('POST', '/app-menu/icons', payload)
							.then(function (result) {
								draft.icon = { upload: result.id }
								error.icon = ''
								redraw('[data-part="upload"]')
							})
							.catch(function () {
								error.icon = t('uploadFailed')
								redraw('[data-part="upload"]')
							})
					},
				},
			})
			var content = h('div', { class: BLOCK + '__body' })
			var redraw = function (focusSelector) {
				content.textContent = ''
				content.appendChild(
					field('title', 'text', 'fieldTitle', 'fieldTitleHelp'),
				)
				content.appendChild(
					field('payoff', 'text', 'fieldPayoff', 'fieldPayoffHelp'),
				)
				content.appendChild(field('url', 'url', 'fieldUrl', 'fieldUrlHelp'))
				content.appendChild(
					h('fieldset', { class: BLOCK + '__fieldset' }, [
						h('legend', { text: t('icon') }),
						iconGroup(),
						h('div', { class: BLOCK + '__upload' }, [
							h(
								'button',
								{
									type: 'button',
									class: BLOCK + '__button',
									'data-part': 'upload',
									'aria-describedby': BLOCK + '-upload-help',
									on: {
										click: function () {
											fileInput.click()
										},
									},
								},
								[
									svg(ICONS.upload, 18),
									draft.icon.upload ? t('uploaded') : t('upload'),
								],
							),
							h('span', {
								id: BLOCK + '-upload-help',
								class: BLOCK + '__help',
								text: t('uploadHelp'),
							}),
							error.icon
								? h('span', {
										class: BLOCK + '__field-error',
										role: 'alert',
										text: error.icon,
									})
								: null,
							fileInput,
						]),
					]),
				)
				content.appendChild(
					h('div', { class: BLOCK + '__preview' }, [
						h('span', {
							class: BLOCK + '__help',
							text: t('formPreview'),
						}),
						h(
							'span',
							{
								class: BLOCK + '__preview-item',
								'aria-hidden': 'true',
							},
							[
								previewIcon,
								h('span', {}, [previewTitle, previewPayoff]),
							],
						),
					]),
				)
				content.appendChild(
					h('p', { class: BLOCK + '__note' }, [
						svg(ICONS.external, 16),
						t('alwaysNewTab'),
					]),
				)
				updatePreview()
				if (focusSelector) {
					var target = sub.querySelector(focusSelector)
					if (target) {
						target.focus()
					}
				}
			}
			var submit = function () {
				draft.title = String(draft.title || '').trim()
				draft.payoff = String(draft.payoff || '').trim()
				draft.url = String(draft.url || '').trim()
				error = {}
				if (draft.title === '') {
					error.title = t('titleRequired')
				}
				if (!isHttps(draft.url)) {
					error.url = t('urlRequired')
				}
				if (error.title || error.url) {
					redraw(
						error.title
							? '#' + BLOCK + '-field-title'
							: '#' + BLOCK + '-field-url',
					)
					return
				}
				if (app) {
					touched.externalApps = true
					Object.assign(app, draft)
				} else {
					touched.externalApps = true
					form.externalApps = form.externalApps.concat([draft])
				}
				sub.close()
				render('[data-external="' + draft.id + '"] [data-part="edit"]')
			}
			sub.appendChild(
				h('div', { class: BLOCK + '__head' }, [
					h('h2', {
						id: BLOCK + '-form-title',
						text: app ? t('editExternal') : t('addExternal'),
					}),
					h(
						'button',
						{
							type: 'button',
							class: BLOCK + '__close',
							'aria-label': t('close'),
							on: {
								click: function () {
									sub.close()
								},
							},
						},
						[svg(ICONS.close, 22)],
					),
				]),
			)
			sub.appendChild(content)
			sub.appendChild(
				h('div', { class: BLOCK + '__foot' }, [
					h('span'),
					h('div', { class: BLOCK + '__foot-end' }, [
						h('button', {
							type: 'button',
							class: BLOCK + '__button',
							text: t('cancel'),
							on: {
								click: function () {
									sub.close()
								},
							},
						}),
						h('button', {
							type: 'button',
							class: BLOCK + '__button ' + BLOCK + '__button--primary',
							'data-part': 'submit',
							text: app ? t('save') : t('add'),
							on: { click: submit },
						}),
					]),
				]),
			)
			sub.addEventListener('close', function () {
				sub.remove()
				if (opener && document.contains(opener)) {
					opener.focus()
				}
			})
			redraw()
			dialog.appendChild(sub)
			if (typeof sub.showModal === 'function') {
				sub.showModal()
			} else {
				sub.setAttribute('open', '')
			}
			var first = sub.querySelector('#' + BLOCK + '-field-title')
			if (first) {
				first.focus()
			}
		}

		var payload = function () {
			var keys =
				scope === 'everyone'
					? Object.keys(form)
					: PERSONAL.filter(function (key) {
							return touched[key]
						})
			var out = {}
			keys.forEach(function (key) {
				out[key] = form[key]
			})
			if (!('order' in out)) {
				return out
			}
			out.order = inOrder(apps, form.order)
				.map(function (entry) {
					return entry.key
				})
				.concat(
					(form.order || []).filter(function (key) {
						return !apps.some(function (entry) {
							return entry.key === key
						})
					}),
				)
			return out
		}

		var save = function () {
			alertEl.hidden = true
			request(
				'POST',
				scope === 'everyone' ? '/app-menu/defaults' : '/app-menu/settings',
				{ settings: payload() },
			)
				.then(function () {
					close()
					window.location.reload()
				})
				.catch(function () {
					alertEl.textContent = t('saveFailed')
					alertEl.hidden = false
				})
		}

		var reset = function () {
			if (
				!window.confirm(
					scope === 'everyone' ? t('resetEveryone') : t('resetOnlyMe'),
				)
			) {
				return
			}
			request(
				'DELETE',
				scope === 'everyone' ? '/app-menu/defaults' : '/app-menu/settings',
			)
				.then(function () {
					close()
					window.location.reload()
				})
				.catch(function () {
					alertEl.textContent = t('saveFailed')
					alertEl.hidden = false
				})
		}

		dialog.appendChild(
			h('div', { class: BLOCK + '__head' }, [
				h('h2', { id: BLOCK + '-title', text: t('title') }),
				h(
					'button',
					{
						type: 'button',
						class: BLOCK + '__close',
						'aria-label': t('close'),
						on: { click: close },
					},
					[svg(ICONS.close, 22)],
				),
			]),
		)
		dialog.appendChild(body)
		dialog.appendChild(alertEl)
		dialog.appendChild(
			h('div', { class: BLOCK + '__foot' }, [
				h(
					'button',
					{
						type: 'button',
						class: BLOCK + '__button ' + BLOCK + '__reset',
						'data-part': 'reset',
						on: { click: reset },
					},
					[svg(ICONS.reset, 18), t('reset')],
				),
				h('div', { class: BLOCK + '__foot-end' }, [
					h('button', {
						type: 'button',
						class: BLOCK + '__button',
						'data-part': 'cancel',
						text: t('cancel'),
						on: { click: close },
					}),
					h('button', {
						type: 'button',
						class: BLOCK + '__button ' + BLOCK + '__button--primary',
						'data-part': 'save',
						text: t('save'),
						on: { click: save },
					}),
				]),
			]),
		)
		dialog.appendChild(live)
		render()
		return { el: dialog, close: close }
	}

	/**
	 * Open the dialog: fetch the settings, then show it as a modal.
	 *
	 * @param {object} options {menu, entries, opener}.
	 * @return {Promise<object|null>} The dialog, or null when the settings could not be read.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	function open(options) {
		var opts = options || {}
		return request('GET', '/app-menu/settings')
			.then(function (data) {
				var built = build(data, opts)
				document.body.appendChild(built.el)
				if (typeof built.el.showModal === 'function') {
					built.el.showModal()
				} else {
					built.el.setAttribute('open', '')
				}
				var first = built.el.querySelector(
					'[role="tab"][aria-selected="true"]',
				)
				if (first) {
					first.focus()
				}
				return built
			})
			.catch(function () {
				var labels = (opts.menu && opts.menu.labels) || {}
				var live = document.getElementById('thematiq-app-pins-live')
				if (live !== null && labels.settingsFailed) {
					live.textContent = labels.settingsFailed
				}
				return null
			})
	}

	window.ThematiqMenuSettingsDialog = { open: open }
})()
