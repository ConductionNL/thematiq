/**
 * The apps menu of the workplace top bar: one ordered list.
 *
 * Loaded with the workplace header style (AppMenuService), with the menu as
 * initial state (`thematiq/app-menu`): groups ([{title, apps: [{id,
 * description?}]}]) for the default order and each app's second line, order
 * (the signed-in user's keys), external ({title, note, apps: [{key, label,
 * line, url, icon}]}), labels, hint and manageLabel. Every key is optional.
 *
 * Nextcloud's menu is one grid of app tiles rendered by Vue. When it opens,
 * this script marks the grid `data-thematiq-app-menu` and lays the entries
 * out by CSS `order`: the first nine, an "All apps" button, the folded rest
 * (Nextcloud apps, then the external apps under their own heading), and a
 * footer with the hints and the "manage apps" link Nextcloud's outlined tile
 * points to. The tiles stay where Vue put them in the DOM and are folded with
 * an attribute, never a class, because Vue rewrites `class` on every render.
 * Text only, never markup.
 *
 * A person reorders the entries by dragging or with Alt and an arrow key; the
 * order is saved on the server for that person. Because the screen order is
 * no longer Nextcloud's data order, the script handles the grid's arrow keys
 * itself. The menu also opens on hover and pins on click (`opening()`).
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
;(function thematiqAppMenuInit() {
	var BLOCK = 'thematiq-app-menu'
	// The menu settings where nobody set one (AppMenuSettings::DEFAULTS).
	var DEFAULTS = {
		style: 'workplace',
		columns: 4,
		rows: 6,
		rowsAboveFold: 2,
		showCreateActions: true,
		showExternalApps: true,
		shape: 'round',
		tiles: false,
		payoff: true,
		iconSize: 'large',
		iconColour: 'brand',
		hidden: [],
	}
	var RANGES = { columns: [2, 5], rows: [1, 12], rowsAboveFold: [1, 12] }
	var FOOTER = 99999
	var DRAG_START = 6
	var HOVER_OPEN = 150
	var HOVER_CLOSE = 180
	var REOPEN_WINDOW = 600
	var SAVE_DELAY = 400
	var TRIGGERS = '.app-menu__waffle, .app-menu__current-app'
	var SVG_NS = 'http://www.w3.org/2000/svg'
	var CREATE_ROW = 99900
	var PINS_MAX = 5
	var PINS = 'thematiq-app-pins'
	var CREATE_MAX = 4
	var CREATE_TRIES = 20
	var TEMPLATES_URL = '/ocs/v2.php/apps/files/api/v1/templates'
	var CHEVRON_DOWN =
		'M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z'
	var CHEVRON_UP = 'M7.41,15.41L12,10.83L16.59,15.41L18,14L12,8L6,14L7.41,15.41Z'
	// The kinds of the Create row, in the row's order: the label key, the
	// icon, and the mimetypes or app a Files template creator must offer.
	var KINDS = [
		{
			kind: 'document',
			label: 'newDocument',
			icon: 'M6,2A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2H6M6,4H13V9H18V20H6V4M8,12V14H16V12H8M8,16V18H13V16H8Z',
			mimetypes: [
				'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
				'application/vnd.oasis.opendocument.text',
				'text/markdown',
			],
		},
		{
			kind: 'spreadsheet',
			label: 'newSpreadsheet',
			icon: 'M4,3H20A2,2 0 0,1 22,5V19A2,2 0 0,1 20,21H4A2,2 0 0,1 2,19V5A2,2 0 0,1 4,3M4,7V10H8V7H4M10,7V10H14V7H10M20,10V7H16V10H20M4,12V15H8V12H4M4,20H8V17H4V20M10,12V15H14V12H10M10,20H14V17H10V20M20,20V17H16V20H20M20,12H16V15H20V12Z',
			mimetypes: [
				'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
				'application/vnd.oasis.opendocument.spreadsheet',
			],
		},
		{
			kind: 'presentation',
			label: 'newPresentation',
			icon: 'M2,3H10A2,2 0 0,1 12,1A2,2 0 0,1 14,3H22V5H21V16H15.25L17,22H15L13.25,16H10.75L9,22H7L8.75,16H3V5H2V3M5,5V14H19V5H5Z',
			mimetypes: [
				'application/vnd.openxmlformats-officedocument.presentationml.presentation',
				'application/vnd.oasis.opendocument.presentation',
			],
		},
		{
			kind: 'whiteboard',
			label: 'newWhiteboard',
			icon: 'M20.71,4.63L19.37,3.29C19,2.9 18.35,2.9 17.96,3.29L9,12.25L11.75,15L20.71,6.04C21.1,5.65 21.1,5 20.71,4.63M7,14A3,3 0 0,0 4,17C4,18.31 2.84,19 2,19C2.92,20.22 4.5,21 6,21A4,4 0 0,0 10,17A3,3 0 0,0 7,14Z',
			app: 'whiteboard',
			mimetypes: ['application/vnd.excalidraw+json'],
		},
	]
	var COG =
		'M12,15.5A3.5,3.5 0 0,1 8.5,12A3.5,3.5 0 0,1 12,8.5A3.5,3.5 0 0,1 15.5,12A3.5,3.5 0 0,1 12,15.5M19.43,12.97C19.47,12.65 19.5,12.33 19.5,12C19.5,11.67 19.47,11.34 19.43,11L21.54,9.37C21.73,9.22 21.78,8.95 21.66,8.73L19.66,5.27C19.54,5.05 19.27,4.96 19.05,5.05L16.56,6.05C16.04,5.66 15.5,5.32 14.87,5.07L14.5,2.42C14.46,2.18 14.25,2 14,2H10C9.75,2 9.54,2.18 9.5,2.42L9.13,5.07C8.5,5.32 7.96,5.66 7.44,6.05L4.95,5.05C4.73,4.96 4.46,5.05 4.34,5.27L2.34,8.73C2.21,8.95 2.27,9.22 2.46,9.37L4.57,11C4.53,11.34 4.5,11.67 4.5,12C4.5,12.33 4.53,12.65 4.57,12.97L2.46,14.63C2.27,14.78 2.21,15.05 2.34,15.27L4.34,18.73C4.46,18.95 4.73,19.03 4.95,18.95L7.44,17.94C7.96,18.34 8.5,18.68 9.13,18.93L9.5,21.58C9.54,21.82 9.75,22 10,22H14C14.25,22 14.46,21.82 14.5,21.58L14.87,18.93C15.5,18.67 16.04,18.34 16.56,17.94L19.05,18.95C19.27,19.03 19.54,18.95 19.66,18.73L21.66,15.27C21.78,15.05 21.73,14.78 21.54,14.63L19.43,12.97Z'
	var MORE_ICON =
		'M16,12A2,2 0 0,1 18,10A2,2 0 0,1 20,12A2,2 0 0,1 18,14A2,2 0 0,1 16,12M10,12A2,2 0 0,1 12,10A2,2 0 0,1 14,12A2,2 0 0,1 12,14A2,2 0 0,1 10,12M4,12A2,2 0 0,1 6,10A2,2 0 0,1 8,12A2,2 0 0,1 6,14A2,2 0 0,1 4,12Z'
	var FILE_ICON =
		'M14,2H6A2,2 0 0,0 4,4V20A2,2 0 0,0 6,22H18A2,2 0 0,0 20,20V8L14,2M18,20H6V4H13V9H18V20Z'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqAppMenuMain)
	} else {
		thematiqAppMenuMain()
	}

	/**
	 * A non-empty string from an object, or the empty string.
	 *
	 * @param {object} source The object.
	 * @param {string} key The key.
	 * @return {string} The text.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function text(source, key) {
		return source && typeof source[key] === 'string' ? source[key].trim() : ''
	}

	/**
	 * Whether an address may be linked: http(s) or a path on this server.
	 *
	 * @param {string} url The address.
	 * @return {boolean} True when it may be linked.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function linkable(url) {
		return typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url)
	}

	/**
	 * The app id a tile links to: the segment after `/apps/`.
	 *
	 * @param {HTMLAnchorElement} tile The tile.
	 * @return {string} The id, or the empty string.
	 * @spec openspec/specs/apps-menu-order/spec.md
	 */
	function appIdOf(tile) {
		var match = /\/apps\/([a-z0-9_]+)/.exec(tile.getAttribute('href') || '')
		return match ? match[1] : ''
	}

	/**
	 * A row that spans the grid: the toggle, a heading, the line or the footer.
	 *
	 * @param {Element} grid The grid.
	 * @param {string} key A key that finds the row again.
	 * @param {number} order Its place.
	 * @return {HTMLElement} The row.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function row(grid, key, order) {
		var el = grid.querySelector(':scope > [data-thematiq-row="' + key + '"]')
		if (el === null) {
			el = document.createElement('div')
			el.setAttribute('data-thematiq-row', key)
			el.setAttribute('role', 'none')
			grid.appendChild(el)
		}
		el.style.order = String(order)
		return el
	}

	/**
	 * Fold an element away, or show it.
	 *
	 * @param {Element} el The element.
	 * @param {boolean} folded Whether it is folded.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function fold(el, folded) {
		if (folded) {
			el.setAttribute('data-thematiq-folded', '')
		} else {
			el.removeAttribute('data-thematiq-folded')
		}
	}

	/**
	 * The menu settings of this page: the shipped values, overlaid with the
	 * effective settings the server sent (the administrator's, then the
	 * person's own). A value of the wrong kind keeps the shipped one; a number
	 * is held inside its range.
	 *
	 * @param {object} menu The menu.
	 * @return {object} Every key of DEFAULTS.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-is-drawn-from-the-settings
	 */
	function settingsOf(menu) {
		var given = menu.settings && typeof menu.settings === 'object' ? menu.settings : {}
		var out = {}
		Object.keys(DEFAULTS).forEach(function (key) {
			out[key] = typeof given[key] === typeof DEFAULTS[key] ? given[key] : DEFAULTS[key]
		})
		out.hidden = Array.isArray(given.hidden) ? given.hidden : []
		Object.keys(RANGES).forEach(function (key) {
			out[key] = Math.max(RANGES[key][0], Math.min(RANGES[key][1], Math.round(out[key])))
		})
		return out
	}

	/**
	 * A 24px icon, drawn in the text colour, hidden from assistive technology.
	 *
	 * @param {string} d The path data.
	 * @return {SVGElement} The icon.
	 * @spec openspec/specs/apps-menu-create/spec.md
	 */
	function icon(d) {
		var svg = document.createElementNS(SVG_NS, 'svg')
		svg.setAttribute('viewBox', '0 0 24 24')
		svg.setAttribute('width', '18')
		svg.setAttribute('height', '18')
		svg.setAttribute('fill', 'currentColor')
		svg.setAttribute('aria-hidden', 'true')
		var path = document.createElementNS(SVG_NS, 'path')
		path.setAttribute('d', d)
		svg.appendChild(path)
		return svg
	}

	/**
	 * The "Apps" title at the top of the panel, added once per grid.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} labels The texts.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-apps-menu-has-a-title-and-a-chevron-on-its-toggle
	 */
	function title(grid, labels) {
		if (
			text(labels, 'title') === ''
			|| grid.querySelector(':scope > [data-thematiq-row="title"]') !== null
		) {
			return
		}
		var el = row(grid, 'title', 1)
		el.className = BLOCK + '__title-row'
		var h = document.createElement('h2')
		h.className = BLOCK + '__apps-title'
		h.id = 'thematiq-app-menu-title'
		h.textContent = text(labels, 'title')
		el.appendChild(h)
		if (grid.getAttribute('role') === 'menu') {
			grid.setAttribute('aria-labelledby', h.id)
		}
	}

	/**
	 * The template creators Nextcloud's Files offers this person, fetched once
	 * per page from the Files templates API. Resolves to an empty list when
	 * Files does not answer.
	 *
	 * @return {Promise<Array<object>>} The creators.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function creators() {
		if (window.thematiqAppMenuCreators) {
			return window.thematiqAppMenuCreators
		}
		if (typeof fetch !== 'function' || typeof OC === 'undefined') {
			return Promise.resolve([])
		}
		window.thematiqAppMenuCreators = fetch(
			(OC.webroot || '') + TEMPLATES_URL + '?format=json',
			{
				headers: {
					'OCS-APIRequest': 'true',
					Accept: 'application/json',
					requesttoken: OC.requestToken,
				},
			},
		)
			.then(function (res) {
				return res.ok ? res.json() : null
			})
			.then(function (body) {
				var data = body && body.ocs ? body.ocs.data : null
				return Array.isArray(data)
					? data.filter(function (creator) {
						return (
							creator
							&& text(creator, 'label') !== ''
							&& /^\.[a-z0-9]{1,10}$/i.test(creator.extension || '')
						)
					})
					: []
			})
			.catch(function () {
				return []
			})
		return window.thematiqAppMenuCreators
	}

	/**
	 * The creator for each kind of the Create row, in the row's order. A kind
	 * no installed app offers is left out.
	 *
	 * @param {Array<object>} list The creators.
	 * @return {Array<{kind: object, creator: object}>} The row.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function rowCreators(list) {
		var taken = []
		return KINDS.map(function (kind) {
			var found = null
			kind.mimetypes.some(function (type) {
				found = list.filter(function (creator) {
					return (
						taken.indexOf(creator) === -1
						&& (Array.isArray(creator.mimetypes)
							? creator.mimetypes.indexOf(type) !== -1
							: false)
					)
				})[0] || null
				return found !== null
			})
			if (found === null && kind.app) {
				found = list.filter(function (creator) {
					return creator.app === kind.app && taken.indexOf(creator) === -1
				})[0] || null
			}
			if (found !== null) {
				taken.push(found)
			}
			return { kind: kind, creator: found }
		}).filter(function (item) {
			return item.creator !== null
		})
	}

	/**
	 * A file name that Files accepts: the creator's label without slashes,
	 * numbered from the second try, with the creator's extension.
	 *
	 * @param {object} creator The creator.
	 * @param {number} attempt The try, from 1.
	 * @return {string} The name.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-a-create-action-makes-the-file-and-opens-it
	 */
	function fileName(creator, attempt) {
		var base = text(creator, 'label').replace(/[\\/]/g, ' ').trim()
		return base + (attempt > 1 ? ' (' + attempt + ')' : '') + creator.extension
	}

	/**
	 * Create an empty file of a creator in the person's home folder through
	 * the Files templates API, and open it. A name that is taken is tried
	 * again with a number.
	 *
	 * @param {object} creator The creator.
	 * @param {Function} failed Called when the file could not be made.
	 * @return {Promise<void>} Settles when done.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-a-create-action-makes-the-file-and-opens-it
	 */
	function createFile(creator, failed) {
		var attempt = function (n) {
			return fetch((OC.webroot || '') + TEMPLATES_URL + '/create?format=json', {
				method: 'POST',
				headers: {
					'OCS-APIRequest': 'true',
					Accept: 'application/json',
					'Content-Type': 'application/json',
					requesttoken: OC.requestToken,
				},
				body: JSON.stringify({
					filePath: '/' + fileName(creator, n),
					templatePath: '',
					templateType: 'user',
				}),
			}).then(function (res) {
				if (res.status === 403 && n < CREATE_TRIES) {
					return attempt(n + 1)
				}
				if (!res.ok) {
					throw new Error('create')
				}
				return res.json().then(function (body) {
					var id = body && body.ocs && body.ocs.data ? body.ocs.data.fileid : null
					if (typeof id !== 'number' && !/^\d+$/.test(String(id))) {
						throw new Error('create')
					}
					window.location.assign(OC.generateUrl('/f/{id}', { id: id }))
				})
			})
		}
		return attempt(1).catch(failed)
	}

	/**
	 * Run one of Nextcloud's own navigation actions: a link opens, an action
	 * without one goes to the app that registered it over the event bus.
	 *
	 * @param {object} action The navigation entry.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function runAction(action) {
		if (linkable(action.href)) {
			window.location.assign(action.href)
		} else if (window._nc_event_bus && typeof window._nc_event_bus.emit === 'function') {
			window._nc_event_bus.emit('core:navigation:action', action)
		}
	}

	/**
	 * Nextcloud's own navigation actions (`core/navigationActions`), the
	 * entries its app menu draws in its action row. Empty before Nextcloud 35.
	 *
	 * @return {Array<object>} The actions with a name.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function coreActions() {
		var list = []
		try {
			list = OCP.InitialState.loadState('core', 'navigationActions', [])
		} catch (e) {
			list = []
		}
		return Array.isArray(list)
			? list.filter(function (action) {
				return action && text(action, 'name') !== ''
			})
			: []
	}

	/**
	 * A button of the Create row: a round chip with the icon and the name.
	 *
	 * @param {string} name The name.
	 * @param {string} d The icon's path data.
	 * @param {Function} onClick What it does.
	 * @return {HTMLButtonElement} The button.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function chip(name, d, onClick) {
		var button = document.createElement('button')
		button.type = 'button'
		button.className = BLOCK + '__create'
		var circle = document.createElement('span')
		circle.className = BLOCK + '__create-icon'
		circle.appendChild(icon(d))
		var label = document.createElement('span')
		label.className = BLOCK + '__create-label'
		label.textContent = name
		button.appendChild(circle)
		button.appendChild(label)
		button.addEventListener('click', onClick)
		return button
	}

	/**
	 * Offer the Create row's actions to the other scripts of the bar (the top
	 * bar's Quick add menu, js/header-create.js), so both list the same things
	 * in the same order: `window.thematiqCreateActions.list()` resolves to
	 * `{name, icon, run}` entries: the row's kinds, the other creators, then
	 * Nextcloud's navigation actions. Empty while the setting is off.
	 * Announced with the `thematiq:create-actions` event.
	 *
	 * @param {object} menu The menu.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function shareCreateActions(menu) {
		var labels = menu.labels || {}
		var on = text(labels, 'create') !== '' && settingsOf(menu).showCreateActions
		window.thematiqCreateActions = {
			title: text(labels, 'create'),
			failed: text(labels, 'createFailed'),
			list: function () {
				if (!on) {
					return Promise.resolve([])
				}
				return creators().then(function (list) {
					var row = rowCreators(list)
					var entries = row.map(function (item) {
						return {
							name: text(labels, item.kind.label) || text(item.creator, 'label'),
							icon: item.kind.icon,
							run: function (failed) {
								return createFile(item.creator, failed)
							},
						}
					})
					list.forEach(function (creator) {
						var inRow = row.some(function (item) {
							return item.creator === creator
						})
						if (!inRow) {
							entries.push({
								name: text(creator, 'actionLabel') || text(creator, 'label'),
								icon: FILE_ICON,
								run: function (failed) {
									return createFile(creator, failed)
								},
							})
						}
					})
					coreActions().forEach(function (action) {
						entries.push({
							name: text(action, 'name'),
							icon: MORE_ICON,
							run: function () {
								runAction(action)
							},
						})
					})
					return entries
				})
			},
		}
		if (typeof window.CustomEvent === 'function') {
			document.dispatchEvent(new window.CustomEvent('thematiq:create-actions'))
		}
	}

	/**
	 * The Create row above the footer: New document, New spreadsheet, New
	 * presentation and New whiteboard when an installed app offers them, and
	 * More, which unfolds every creator and every Nextcloud navigation action.
	 * Drawn once the creators are known; hidden when there are none.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @spec openspec/specs/apps-menu-create/spec.md#requirement-the-create-row-offers-what-nextcloud-offers
	 */
	function createRow(grid, menu) {
		var labels = menu.labels || {}
		var off = text(labels, 'create') === '' || !settingsOf(menu).showCreateActions
		var heading = grid.querySelector(':scope > [data-thematiq-row="create-title"]')
		if (off) {
			;['create-title', 'create', 'create-more'].forEach(function (key) {
				var el = grid.querySelector(':scope > [data-thematiq-row="' + key + '"]')
				if (el !== null) {
					fold(el, true)
				}
			})
			return
		}
		if (heading !== null) {
			fold(heading, false)
			var drawn = grid.querySelector(':scope > [data-thematiq-row="create"]')
			if (drawn !== null) {
				fold(drawn, false)
			}
			return
		}
		creators().then(function (list) {
			if (grid.querySelector(':scope > [data-thematiq-row="create-title"]') !== null) {
				return
			}
			var actions = coreActions()
			var items = rowCreators(list).slice(0, CREATE_MAX)
			if (list.length === 0 && actions.length === 0) {
				return
			}
			var live = function (message) {
				var region = grid.querySelector(':scope > [data-thematiq-row="live"]')
				if (region !== null && message !== '') {
					region.textContent = message
				}
			}
			var failed = function () {
				live(text(labels, 'createFailed'))
			}
			var head = row(grid, 'create-title', CREATE_ROW)
			head.className = BLOCK + '__heading ' + BLOCK + '__create-heading'
			var h = document.createElement('h3')
			h.className = BLOCK + '__title'
			h.id = 'thematiq-app-menu-create'
			h.textContent = text(labels, 'create')
			head.appendChild(h)

			var bar = row(grid, 'create', CREATE_ROW + 1)
			bar.className = BLOCK + '__create-row'
			bar.setAttribute('role', 'group')
			bar.setAttribute('aria-labelledby', h.id)
			items.forEach(function (item) {
				var name = text(labels, item.kind.label) || text(item.creator, 'label')
				bar.appendChild(
					chip(name, item.kind.icon, function () {
						createFile(item.creator, failed)
					}),
				)
			})

			var more = row(grid, 'create-more', CREATE_ROW + 2)
			more.className = BLOCK + '__create-more'
			more.id = 'thematiq-app-menu-create-more'
			fold(more, true)
			list.forEach(function (creator) {
				more.appendChild(
					chip(
						text(creator, 'actionLabel') || text(creator, 'label'),
						FILE_ICON,
						function () {
							createFile(creator, failed)
						},
					),
				)
			})
			actions.forEach(function (action) {
				more.appendChild(
					chip(text(action, 'name'), MORE_ICON, function () {
						runAction(action)
					}),
				)
			})
			var moreButton = chip(text(labels, 'more'), MORE_ICON, function () {
				var open = moreButton.getAttribute('aria-expanded') === 'true'
				moreButton.setAttribute('aria-expanded', open ? 'false' : 'true')
				fold(more, open)
			})
			moreButton.setAttribute('aria-expanded', 'false')
			moreButton.setAttribute('aria-controls', more.id)
			bar.appendChild(moreButton)
		})
	}

	/**
	 * The external apps as links of their own, added once per grid. Text and
	 * path data only, never markup.
	 *
	 * @param {Element} grid The grid.
	 * @param {object|undefined} section {title, note, apps: [{key, label, line, url, icon}]}.
	 * @return {Array<HTMLAnchorElement>} The links, in the section's order.
	 * @spec openspec/specs/external-apps-in-the-app-menu/spec.md
	 */
	function externalLinks(grid, section) {
		var existing = Array.prototype.slice.call(
			grid.querySelectorAll(':scope > a[data-thematiq-external]'),
		)
		if (existing.length > 0 || !section || !Array.isArray(section.apps)) {
			return existing
		}
		var note = text(section, 'note')
		return section.apps
			.filter(function (app) {
				return (
					text(app, 'label') !== ''
					&& /^[a-z0-9_-]{1,80}$/.test(app.key || '')
					&& /^https?:\/\//i.test(app.url || '')
				)
			})
			.map(function (app) {
				var a = document.createElement('a')
				a.className = 'app-item ' + BLOCK + '__external'
				a.setAttribute('data-thematiq-external', app.key)
				a.setAttribute('role', 'menuitem')
				a.setAttribute('tabindex', '-1')
				a.href = app.url
				a.target = '_blank'
				a.rel = 'noopener noreferrer'
				var circle = document.createElement('span')
				circle.className = 'app-item__circle'
				var svg = document.createElementNS(SVG_NS, 'svg')
				svg.setAttribute('viewBox', '0 0 24 24')
				svg.setAttribute('width', '20')
				svg.setAttribute('height', '20')
				svg.setAttribute('aria-hidden', 'true')
				if (app.stroke === true) {
					svg.setAttribute('fill', 'none')
					svg.setAttribute('stroke', 'currentColor')
					svg.setAttribute('stroke-width', '1.75')
					svg.setAttribute('stroke-linecap', 'round')
					svg.setAttribute('stroke-linejoin', 'round')
				} else {
					svg.setAttribute('fill', 'currentColor')
				}
				if (/^[MmLlHhVvCcSsQqTtAaZz0-9 .,-]+$/.test(app.icon || '')) {
					var path = document.createElementNS(SVG_NS, 'path')
					path.setAttribute('d', app.icon)
					svg.appendChild(path)
				}
				if (linkable(app.iconUrl) && !/^https?:/i.test(app.iconUrl)) {
					var img = document.createElement('img')
					img.className = BLOCK + '__external-icon'
					img.src = app.iconUrl
					img.alt = ''
					circle.appendChild(img)
				} else {
					circle.appendChild(svg)
				}
				var label = document.createElement('span')
				label.className = 'app-item__label'
				label.textContent = text(app, 'label')
				a.appendChild(circle)
				a.appendChild(label)
				if (text(app, 'line') !== '') {
					var line = document.createElement('span')
					line.className = BLOCK + '__description'
					line.textContent = text(app, 'line')
					a.appendChild(line)
				}
				if (note !== '') {
					var hidden = document.createElement('span')
					hidden.className = 'hidden-visually'
					hidden.textContent = ' (' + note + ')'
					a.appendChild(hidden)
				}
				grid.appendChild(a)
				return a
			})
	}

	/**
	 * Every entry of an open grid: Nextcloud's tiles and the external links,
	 * each with its key and its place in the default order. The outlined
	 * "more apps" tile is not an entry: it becomes the footer's link.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @return {{entries: Array<{key: string, el: Element, external: boolean, rank: number}>, manage: (string|null)}} The entries and the manage address.
	 * @spec openspec/specs/apps-menu-order/spec.md
	 */
	function collect(grid, menu) {
		var settings = settingsOf(menu)
		var payoff = settings.payoff
		var place = {}
		var groups = Array.isArray(menu.groups) ? menu.groups : []
		var n = 0
		// The first section is the organisation's own work apps; the menu
		// draws them in the brand's wash, every other app in grey.
		groups.forEach(function (group, g) {
			;(group.apps || []).forEach(function (app) {
				if (app && typeof app.id === 'string' && !(app.id in place)) {
					place[app.id] = { rank: n++, app: app, work: g === 0 }
				}
			})
		})

		var entries = []
		var seen = {}
		var manage = null
		var tiles = grid.querySelectorAll(
			':scope > a.app-item:not([data-thematiq-external])',
		)
		Array.prototype.forEach.call(tiles, function (tile, i) {
			var circle = tile.querySelector('.app-item__circle')
			var icon = tile.querySelector('img.app-item__icon')
			if (circle !== null && icon !== null && icon.getAttribute('src')) {
				circle.style.setProperty(
					'--thematiq-app-icon',
					'url("' + icon.getAttribute('src').replace(/["\\]/g, '') + '")',
				)
			}
			if (tile.classList.contains('app-item--outlined')) {
				if (manage === null) {
					manage = tile.getAttribute('href')
				}
				tile.setAttribute('data-thematiq-outlined', '')
				tile.style.order = String(FOOTER - 1)
				return
			}
			var id = appIdOf(tile) || 'tile-' + i
			var key = id
			for (var k = 2; key in seen; k++) {
				key = id + '-' + k
			}
			seen[key] = true
			var spot = place[id]
			var description = spot && payoff ? text(spot.app, 'description') : ''
			var drawn = tile.querySelector('.' + BLOCK + '__description')
			if (!payoff && drawn !== null) {
				drawn.remove()
			}
			if (description !== '' && drawn === null) {
				var span = document.createElement('span')
				span.className = BLOCK + '__description'
				span.setAttribute('aria-hidden', 'true')
				span.textContent = description
				tile.appendChild(span)
			}
			if (spot && spot.work) {
				tile.setAttribute('data-thematiq-work', '')
			} else {
				tile.removeAttribute('data-thematiq-work')
			}
			entries.push({
				key: key,
				el: tile,
				external: false,
				work: !!(spot && spot.work),
				rank: spot ? spot.rank : 1000 + i,
			})
		})
		var external = []
		if (settings.showExternalApps) {
			external = externalLinks(grid, menu.external)
		} else {
			grid.querySelectorAll(':scope > a[data-thematiq-external]').forEach(function (a) {
				fold(a, true)
			})
		}
		if (!payoff) {
			external.forEach(function (a) {
				var line = a.querySelector('.' + BLOCK + '__description')
				if (line !== null) {
					line.remove()
				}
			})
		}
		external.forEach(function (a, i) {
			entries.push({
				key: a.getAttribute('data-thematiq-external'),
				el: a,
				external: true,
				rank: 100000 + i,
			})
		})
		return { entries: entries, manage: manage }
	}

	/**
	 * The entries in the person's order: the stored keys first, in that
	 * order, then every other entry in the default order.
	 *
	 * @param {Array<object>} entries The entries.
	 * @param {Array<string>} order The stored keys.
	 * @return {Array<object>} The entries, sorted.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 */
	function sorted(entries, order) {
		var index = {}
		;(Array.isArray(order) ? order : []).forEach(function (key, i) {
			if (typeof key === 'string' && !(key in index)) {
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
	 * Fill a text with `{name}` placeholders.
	 *
	 * @param {string} template The text.
	 * @param {object} values The values.
	 * @return {string} The text.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
	 */
	function fill(template, values) {
		return template.replace(/\{(\w+)\}/g, function (all, name) {
			return name in values ? String(values[name]) : all
		})
	}

	/**
	 * The visible name of an entry, for the live region.
	 *
	 * @param {Element} el The entry.
	 * @return {string} The name.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
	 */
	function nameOf(el) {
		var label = el.querySelector('.app-item__label')
		return (label ? label.textContent : el.textContent).trim()
	}

	/**
	 * Lay one open grid out, and wire it once.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu; `menu.order` is updated by a move.
	 * @param {Function} save Saves the order.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	function arrange(grid, menu, save) {
		grid.setAttribute('data-' + BLOCK, '')
		var labels = menu.labels || {}
		var settings = settingsOf(menu)
		var shown = settings.columns * Math.min(settings.rowsAboveFold, settings.rows)
		grid.style.setProperty('--thematiq-app-menu-columns', String(settings.columns))
		grid.style.setProperty('--thematiq-app-menu-rows', String(settings.rows))
		grid.setAttribute('data-thematiq-shape', settings.shape)
		grid.setAttribute('data-thematiq-icon-size', settings.iconSize)
		grid.setAttribute('data-thematiq-icon-colour', settings.iconColour)
		if (settings.tiles) {
			grid.setAttribute('data-thematiq-tiles', '')
		} else {
			grid.removeAttribute('data-thematiq-tiles')
		}
		var found = collect(grid, menu)
		var list = sorted(found.entries, menu.order).filter(function (entry) {
			var hidden = settings.hidden.indexOf(entry.key) !== -1
			if (hidden) {
				fold(entry.el, true)
			}
			return !hidden
		})
		var expanded = grid.hasAttribute('data-thematiq-expanded')
		var many = list.length > shown
		var rest = list.slice(shown)
		var restApps = rest.filter(function (entry) {
			return !entry.external
		})
		var restExternal = rest.filter(function (entry) {
			return entry.external
		})

		list.slice(0, shown).forEach(function (entry, i) {
			entry.el.style.order = String(100 + i)
			fold(entry.el, false)
		})
		restApps.forEach(function (entry, i) {
			entry.el.style.order = String(1000 + i)
			fold(entry.el, !expanded)
		})
		restExternal.forEach(function (entry, i) {
			entry.el.style.order = String(5000 + i)
			fold(entry.el, !expanded)
		})

		var toggle = grid.querySelector(':scope > [data-thematiq-row="toggle"]')
		if (many && text(labels, 'allApps') !== '') {
			toggle = row(grid, 'toggle', 500)
			toggle.className = BLOCK + '__toggle-row'
			var button = toggle.querySelector('button')
			if (button === null) {
				button = document.createElement('button')
				button.type = 'button'
				button.className = BLOCK + '__toggle'
				button.setAttribute('role', 'menuitem')
				button.setAttribute('tabindex', '-1')
				button.addEventListener('click', function () {
					if (grid.hasAttribute('data-thematiq-expanded')) {
						grid.removeAttribute('data-thematiq-expanded')
					} else {
						grid.setAttribute('data-thematiq-expanded', '')
					}
					arrange(grid, menu, save)
				})
				button.appendChild(icon(''))
				button.appendChild(document.createElement('span'))
				toggle.appendChild(button)
			}
			button.setAttribute('aria-expanded', expanded ? 'true' : 'false')
			button
				.querySelector('path')
				.setAttribute('d', expanded ? CHEVRON_UP : CHEVRON_DOWN)
			var caption = text(labels, expanded ? 'hideApps' : 'allApps')
			var captionEl = button.querySelector('span')
			if (captionEl.textContent !== caption) {
				captionEl.textContent = caption
			}
			fold(toggle, false)
		} else if (toggle !== null) {
			fold(toggle, true)
		}

		var line = row(grid, 'line', 900)
		line.className = BLOCK + '__line'
		fold(line, !(many && expanded))

		var section = menu.external || {}
		if (restExternal.length > 0 && text(section, 'title') !== '') {
			var heading = row(grid, 'external', 4999)
			heading.className = BLOCK + '__heading'
			if (heading.childElementCount === 0) {
				var h = document.createElement('h2')
				h.className = BLOCK + '__title'
				h.textContent = text(section, 'title')
				heading.appendChild(h)
				if (text(section, 'note') !== '') {
					var note = document.createElement('span')
					note.className = BLOCK + '__note'
					note.setAttribute('aria-hidden', 'true')
					note.textContent = text(section, 'note')
					heading.appendChild(note)
				}
			}
			fold(heading, !expanded)
		} else {
			var old = grid.querySelector(':scope > [data-thematiq-row="external"]')
			if (old !== null) {
				fold(old, true)
			}
		}

		title(grid, labels)
		createRow(grid, menu)
		footer(grid, menu, found.manage)
		wire(grid, menu, save)
	}

	/**
	 * The footer: the hints and the "manage apps" link, and the live region.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @param {string|null} manage The address of Nextcloud's outlined tile.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	function footer(grid, menu, manage) {
		var labels = menu.labels || {}
		var live = row(grid, 'live', FOOTER + 1)
		if (!live.hasAttribute('aria-live')) {
			live.className = 'hidden-visually'
			live.setAttribute('role', 'status')
			live.setAttribute('aria-live', 'polite')
		}
		if (grid.querySelector(':scope > [data-thematiq-row="footer"]') !== null) {
			return
		}
		var lines = [text(labels, 'dragHint'), text(labels, 'moveHint')].filter(
			Boolean,
		)
		if (lines.length === 0 && text(menu, 'hint') !== '') {
			lines = [text(menu, 'hint')]
		}
		var label = text(menu, 'manageLabel')
		if (lines.length === 0 && !(label !== '' && linkable(manage))) {
			return
		}
		var el = row(grid, 'footer', FOOTER)
		el.className = BLOCK + '__footer'
		var hints = document.createElement('span')
		hints.className = BLOCK + '__hint'
		lines.forEach(function (value) {
			if (value !== '') {
				var span = document.createElement('span')
				span.textContent = value
				hints.appendChild(span)
			}
		})
		el.appendChild(hints)
		var dialog = window.ThematiqMenuSettingsDialog
		if (dialog && typeof dialog.open === 'function' && text(labels, 'menuSettings') !== '') {
			var gear = document.createElement('button')
			gear.type = 'button'
			gear.className = BLOCK + '__settings'
			gear.setAttribute('aria-haspopup', 'dialog')
			gear.appendChild(icon(COG))
			gear.appendChild(document.createTextNode(text(labels, 'menuSettings')))
			gear.addEventListener('click', function () {
				dialog.open({ menu: menu, entries: dialogEntries(grid, menu), opener: gear })
			})
			el.appendChild(gear)
			grid.setAttribute('data-thematiq-app-menu-manage', '')
		} else if (label !== '' && linkable(manage)) {
			var a = document.createElement('a')
			a.className = BLOCK + '__manage'
			a.href = manage
			a.textContent = label
			el.appendChild(a)
			grid.setAttribute('data-thematiq-app-menu-manage', '')
		}
	}

	/**
	 * What the Menu settings dialog lists: every entry of the grid, hidden
	 * ones too, with its name, icon, second line and place in the default
	 * order.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @return {Array<object>} {key, name, icon, description, rank, external}.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-menu-settings-dialog
	 */
	function dialogEntries(grid, menu) {
		var descriptions = {}
		;(Array.isArray(menu.groups) ? menu.groups : []).forEach(function (group) {
			;(group.apps || []).forEach(function (app) {
				if (app && typeof app.id === 'string') {
					descriptions[app.id] = text(app, 'description')
				}
			})
		})
		return sorted(collect(grid, menu).entries, menu.order).map(function (entry) {
			var img = entry.el.querySelector('img.app-item__icon')
			return {
				key: entry.key,
				name: nameOf(entry.el),
				icon: img ? img.getAttribute('src') || '' : '',
				description: descriptions[entry.key] || '',
				rank: entry.rank,
				external: entry.external,
				work: entry.work === true,
			}
		})
	}

	/**
	 * The entries and the toggle a person can reach now, in screen order.
	 *
	 * @param {Element} grid The grid.
	 * @return {Array<Element>} The elements.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	function reachable(grid) {
		var els = Array.prototype.slice.call(
			grid.querySelectorAll(
				':scope > a.app-item:not([data-thematiq-outlined]), :scope > [data-thematiq-row="toggle"] > button',
			),
		)
		return els
			.filter(function (el) {
				var holder = el.tagName === 'BUTTON' ? el.parentNode : el
				return !holder.hasAttribute('data-thematiq-folded')
			})
			.map(function (el, i) {
				var holder = el.tagName === 'BUTTON' ? el.parentNode : el
				return { el: el, order: Number(holder.style.order) || 0, i: i }
			})
			.sort(function (a, b) {
				return a.order - b.order || a.i - b.i
			})
			.map(function (item) {
				return item.el
			})
	}

	/**
	 * Move focus to an element and make it the grid's one tab stop.
	 *
	 * @param {Element} grid The grid.
	 * @param {Element} el The element.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	function focusOn(grid, el) {
		reachable(grid).forEach(function (other) {
			other.setAttribute('tabindex', other === el ? '0' : '-1')
		})
		el.focus()
	}

	/**
	 * Move an entry to a new place in the person's order, lay the grid out
	 * again and save.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @param {Function} save Saves the order.
	 * @param {string} key The entry's key.
	 * @param {number} to Its new place, from 0.
	 * @return {Array<string>} The new order.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
	 */
	function move(grid, menu, save, key, to) {
		var keys = sorted(collect(grid, menu).entries, menu.order).map(
			function (entry) {
				return entry.key
			},
		)
		var from = keys.indexOf(key)
		if (from === -1) {
			return keys
		}
		keys.splice(from, 1)
		keys.splice(Math.max(0, Math.min(to, keys.length)), 0, key)
		menu.order = keys
		arrange(grid, menu, save)
		save(keys)
		return keys
	}

	/**
	 * The key of an entry element.
	 *
	 * @param {Element} el The entry.
	 * @param {object} menu The menu.
	 * @param {Element} grid The grid.
	 * @return {string} The key, or the empty string.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
	 */
	function keyOf(el, menu, grid) {
		var hit = collect(grid, menu).entries.filter(function (entry) {
			return entry.el === el
		})[0]
		return hit ? hit.key : ''
	}

	/**
	 * The entry an event happened on, if any.
	 *
	 * @param {Element} grid The grid.
	 * @param {EventTarget} target The target.
	 * @return {Element|null} The entry.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
	 */
	function entryAt(grid, target) {
		var el = target && target.closest ? target.closest('a.app-item') : null
		return el !== null
			&& el.parentNode === grid
			&& !el.hasAttribute('data-thematiq-outlined')
			? el
			: null
	}

	/**
	 * The keyboard and the pointer on one grid, wired once.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @param {Function} save Saves the order.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
	 */
	function wire(grid, menu, save) {
		if (grid.thematiqWired) {
			return
		}
		grid.thematiqWired = true
		var labels = menu.labels || {}

		grid.addEventListener(
			'keydown',
			function (event) {
				var els = reachable(grid)
				var index = els.indexOf(document.activeElement)
				if (index === -1 || event.ctrlKey || event.metaKey) {
					return
				}
				var current = els[index]
				if (
					event.altKey
					&& !event.shiftKey
					&& (event.code === 'KeyP' || String(event.key).toLowerCase() === 'p')
				) {
					event.preventDefault()
					event.stopImmediatePropagation()
					var pinKey = current.tagName === 'A' ? keyOf(current, menu, grid) : ''
					if (pinKey !== '' && menu.pinBar) {
						menu.pinBar.toggle(pinKey, nameOf(current))
					}
					return
				}
				var earlier = event.key === 'ArrowUp' || event.key === 'ArrowLeft'
				var later = event.key === 'ArrowDown' || event.key === 'ArrowRight'
				if (event.altKey && !event.shiftKey && (earlier || later)) {
					event.preventDefault()
					event.stopImmediatePropagation()
					var key =
						current.tagName === 'A' ? keyOf(current, menu, grid) : ''
					if (key === '') {
						return
					}
					var keys = sorted(collect(grid, menu).entries, menu.order).map(
						function (entry) {
							return entry.key
						},
					)
					var at = keys.indexOf(key) + (earlier ? -1 : 1)
					if (at < 0 || at >= keys.length) {
						return
					}
					keys = move(grid, menu, save, key, at)
					var live = grid.querySelector(
						':scope > [data-thematiq-row="live"]',
					)
					if (live !== null && text(labels, 'moved') !== '') {
						live.textContent = fill(text(labels, 'moved'), {
							app: nameOf(current),
							place: keys.indexOf(key) + 1,
							total: keys.length,
						})
					}
					focusOn(grid, current)
					return
				}
				if (event.altKey || event.shiftKey) {
					return
				}
				var next = -1
				if (later) {
					next = Math.min(index + 1, els.length - 1)
				} else if (earlier) {
					next = Math.max(index - 1, 0)
				} else if (event.key === 'Home') {
					next = 0
				} else if (event.key === 'End') {
					next = els.length - 1
				} else if (event.key === 'Enter' || event.key === ' ') {
					event.preventDefault()
					event.stopImmediatePropagation()
					current.click()
					return
				} else {
					return
				}
				event.preventDefault()
				event.stopImmediatePropagation()
				focusOn(grid, els[next])
			},
			true,
		)

		var drag = null
		var swallowUntil = 0
		grid.addEventListener('dragstart', function (event) {
			event.preventDefault()
		})
		grid.addEventListener(
			'click',
			function (event) {
				if (Date.now() < swallowUntil) {
					event.preventDefault()
					event.stopImmediatePropagation()
				}
			},
			true,
		)
		grid.addEventListener('pointerdown', function (event) {
			var el = entryAt(grid, event.target)
			if (el !== null && event.button === 0) {
				drag = { el: el, x: event.clientX, y: event.clientY, moved: false }
			}
		})
		var onMove = function (event) {
			if (drag === null) {
				return
			}
			if (!drag.moved) {
				if (
					Math.abs(event.clientX - drag.x)
						+ Math.abs(event.clientY - drag.y)
					< DRAG_START
				) {
					return
				}
				drag.moved = true
				drag.el.setAttribute('data-thematiq-dragging', '')
				window.thematiqAppMenuDragging = true
			}
			var bar = menu.pinBar
			drag.overBar = !!bar && bar.over(event.clientX, event.clientY)
			if (bar) {
				var dragKey = keyOf(drag.el, menu, grid)
				var pinnable = drag.overBar && !bar.has(dragKey)
				bar.slot(pinnable)
				bar.ghost(
					pinnable ? resolvePin(menu, dragKey) : null,
					event.clientX,
					event.clientY,
					'',
				)
			}
			if (drag.overBar) {
				return
			}
			var under = document.elementsFromPoint
				? document.elementsFromPoint(event.clientX, event.clientY)
				: []
			var over = under
				.map(function (node) {
					return entryAt(grid, node)
				})
				.filter(function (node) {
					return node !== null && node !== drag.el
				})[0]
			if (!over) {
				return
			}
			var keys = sorted(collect(grid, menu).entries, menu.order).map(
				function (entry) {
					return entry.key
				},
			)
			var key = keyOf(drag.el, menu, grid)
			var target = keyOf(over, menu, grid)
			var box = over.getBoundingClientRect()
			var after = event.clientX >= box.left + box.width / 2
			keys.splice(keys.indexOf(key), 1)
			var at = keys.indexOf(target) + (after ? 1 : 0)
			keys.splice(at, 0, key)
			menu.order = keys
			arrange(grid, menu, save)
		}
		var onEnd = function () {
			if (drag === null) {
				return
			}
			var done = drag
			drag = null
			if (!done.moved) {
				return
			}
			done.el.removeAttribute('data-thematiq-dragging')
			window.thematiqAppMenuDragging = false
			swallowUntil = Date.now() + 300
			save(menu.order)
			if (menu.pinBar) {
				menu.pinBar.slot(false)
				menu.pinBar.ghost(null)
				var dropKey = keyOf(done.el, menu, grid)
				if (done.overBar && !menu.pinBar.has(dropKey)) {
					menu.pinBar.toggle(dropKey, nameOf(done.el))
				}
			}
		}
		document.addEventListener('pointermove', onMove)
		document.addEventListener('pointerup', onEnd)
		document.addEventListener('pointercancel', onEnd)
	}

	/**
	 * The app id in a Nextcloud address: the segment after `/apps/`.
	 *
	 * @param {string} href The address.
	 * @return {string} The id, or the empty string.
	 * @spec openspec/specs/apps-menu-pins/spec.md#requirement-a-person-pins-apps-to-the-top-bar
	 */
	function appIdOfHref(href) {
		var match = /\/apps\/([a-z0-9_]+)/.exec(href || '')
		return match ? match[1] : ''
	}

	/**
	 * What the top bar needs to draw a pinned entry: Nextcloud's own app
	 * (`core/apps`) or an external app of the menu.
	 *
	 * @param {object} menu The menu.
	 * @param {string} key The entry's key.
	 * @return {object|null} {key, name, href, iconUrl?, path?, external, active}.
	 * @spec openspec/specs/apps-menu-pins/spec.md#requirement-a-person-pins-apps-to-the-top-bar
	 */
	function resolvePin(menu, key) {
		var apps = []
		try {
			apps = OCP.InitialState.loadState('core', 'apps', [])
		} catch (e) {
			apps = []
		}
		var list = Array.isArray(apps) ? apps : Object.values(apps || {})
		var app = list.filter(function (entry) {
			return entry && (entry.id === key || appIdOfHref(entry.href) === key)
		})[0]
		if (app && linkable(app.href) && text(app, 'name') !== '') {
			return {
				key: key,
				name: text(app, 'name'),
				href: app.href,
				iconUrl: typeof app.icon === 'string' ? app.icon : '',
				external: false,
				active: app.active === true,
			}
		}
		var external = menu.external && Array.isArray(menu.external.apps)
			? menu.external.apps.filter(function (entry) {
				return entry && entry.key === key
			})[0]
			: null
		if (external && /^https?:\/\//i.test(external.url || '') && text(external, 'label') !== '') {
			return {
				key: key,
				name: text(external, 'label'),
				href: external.url,
				path: /^[MmLlHhVvCcSsQqTtAaZz0-9 .,-]+$/.test(external.icon || '') ? external.icon : '',
				external: true,
				active: false,
			}
		}
		return null
	}

	/**
	 * The circle of a pinned entry or of the dragged chip: the app's icon as a
	 * mask in the text colour, or an external app's path.
	 *
	 * @param {object} info The resolved entry.
	 * @return {HTMLElement} The circle.
	 * @spec openspec/specs/apps-menu-pins/spec.md#requirement-a-person-pins-apps-to-the-top-bar
	 */
	function pinIcon(info) {
		var circle = document.createElement('span')
		circle.className = PINS + '__icon'
		circle.setAttribute('aria-hidden', 'true')
		if (info.iconUrl) {
			circle.style.setProperty(
				'--thematiq-app-icon',
				'url("' + info.iconUrl.replace(/["\\]/g, '') + '")',
			)
		} else if (info.path) {
			circle.appendChild(icon(info.path))
		}
		return circle
	}

	/**
	 * The pinned apps in the top bar, next to the grid button, and everything
	 * that pins or unpins: dragging an app onto the bar, dragging a pin off
	 * it, and Alt+P. The pins are saved on the server for the person.
	 *
	 * @param {object} menu The menu; `menu.pins` holds the keys.
	 * @return {object} The bar's operations.
	 * @spec openspec/specs/apps-menu-pins/spec.md#requirement-a-person-pins-apps-to-the-top-bar
	 */
	function pinBar(menu) {
		var labels = menu.labels || {}
		var timer = null
		var ghost = null
		var drop = null
		var dropHint = null
		menu.pins = Array.isArray(menu.pins)
			? menu.pins.filter(function (key) {
				return typeof key === 'string'
			}).slice(0, PINS_MAX)
			: []

		var liveRegion = function () {
			var live = document.getElementById(PINS + '-live')
			if (live === null) {
				live = document.createElement('div')
				live.id = PINS + '-live'
				live.className = 'hidden-visually'
				live.setAttribute('role', 'status')
				live.setAttribute('aria-live', 'polite')
				document.body.appendChild(live)
			}
			return live
		}
		var announce = function (message) {
			if (message !== '') {
				liveRegion().textContent = message
			}
		}
		var persist = function () {
			if (
				typeof OC === 'undefined'
				|| typeof OC.generateUrl !== 'function'
				|| typeof fetch !== 'function'
			) {
				return
			}
			clearTimeout(timer)
			timer = setTimeout(function () {
				var failed = function () {
					announce(text(labels, 'pinSaveFailed'))
				}
				fetch(OC.generateUrl('/apps/thematiq/app-menu/pins'), {
					method: 'POST',
					headers: {
						requesttoken: OC.requestToken,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({ pins: menu.pins }),
				})
					.then(function (res) {
						if (!res.ok) {
							failed()
						}
					})
					.catch(failed)
			}, SAVE_DELAY)
		}
		var anchor = function () {
			return document.querySelector('#header .app-menu, .header-start .app-menu')
		}
		var nav = function () {
			var el = document.querySelector('nav.' + PINS)
			var after = anchor()
			if (after === null || !after.parentNode) {
				return el
			}
			if (el === null) {
				el = document.createElement('nav')
				el.className = PINS
				el.setAttribute('aria-label', text(labels, 'pinned') || 'Pinned apps')
			}
			if (after.nextSibling !== el) {
				after.parentNode.insertBefore(el, after.nextSibling)
			}
			return el
		}

		var bar = {
			has: function (key) {
				return menu.pins.indexOf(key) !== -1
			},
			nav: nav,
			announce: announce,
			ensure: function () {
				var el = nav()
				if (el !== null && el.getAttribute('data-thematiq-pins') !== menu.pins.join(',')) {
					bar.render()
				}
			},
			render: function () {
				var el = nav()
				if (el === null) {
					return
				}
				var focused = document.activeElement
				var focusKey =
					focused && focused.parentNode === el
						? focused.getAttribute('data-thematiq-pin')
						: null
				Array.prototype.slice.call(el.querySelectorAll(':scope > a')).forEach(function (a) {
					a.remove()
				})
				menu.pins.forEach(function (key) {
					var info = resolvePin(menu, key)
					if (info === null) {
						return
					}
					var a = document.createElement('a')
					a.className = PINS + '__pin'
					a.href = info.href
					a.setAttribute('data-thematiq-pin', key)
					a.setAttribute('aria-label', info.name)
					a.title = info.name
					if (info.external) {
						a.target = '_blank'
						a.rel = 'noopener noreferrer'
					}
					if (info.active) {
						a.setAttribute('aria-current', 'page')
					}
					a.appendChild(pinIcon(info))
					el.insertBefore(a, drop && drop.parentNode === el ? drop : null)
					if (key === focusKey) {
						a.focus()
					}
				})
				el.hidden = el.childElementCount === 0
				el.setAttribute('data-thematiq-pins', menu.pins.join(','))
				if (!bar.wired) {
					bar.wired = true
					wirePins(menu, bar, el)
				}
			},
			toggle: function (key, name) {
				if (bar.has(key)) {
					menu.pins = menu.pins.filter(function (other) {
						return other !== key
					})
					announce(fill(text(labels, 'unpinnedApp'), { app: name }))
				} else if (menu.pins.length >= PINS_MAX) {
					announce(fill(text(labels, 'pinFull'), { max: PINS_MAX }))
					return false
				} else if (resolvePin(menu, key) === null) {
					return false
				} else {
					menu.pins = menu.pins.concat([key])
					announce(fill(text(labels, 'pinnedApp'), { app: name }))
				}
				bar.render()
				persist()
				return true
			},
			move: function (key, by) {
				var from = menu.pins.indexOf(key)
				var to = from + by
				if (from === -1 || to < 0 || to >= menu.pins.length) {
					return
				}
				menu.pins.splice(from, 1)
				menu.pins.splice(to, 0, key)
				bar.render()
				persist()
				var info = resolvePin(menu, key)
				announce(
					fill(text(labels, 'moved'), {
						app: info ? info.name : key,
						place: to + 1,
						total: menu.pins.length,
					}),
				)
			},
			over: function (x, y) {
				var header = document.getElementById('header')
				if (header === null) {
					return false
				}
				var box = header.getBoundingClientRect()
				return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom
			},
			inBar: function (x, y) {
				var el = nav()
				if (el === null || el.hidden) {
					return false
				}
				var box = el.getBoundingClientRect()
				return x >= box.left && x <= box.right && y >= box.top && y <= box.bottom
			},
			slot: function (on) {
				var el = nav()
				if (el === null) {
					return
				}
				if (on && drop === null) {
					drop = document.createElement('span')
					drop.className = PINS + '__drop'
					drop.setAttribute('aria-hidden', 'true')
					el.appendChild(drop)
					el.hidden = false
					dropHint = document.createElement('span')
					dropHint.className = PINS + '__hint ' + PINS + '__drop-hint'
					dropHint.setAttribute('role', 'status')
					dropHint.textContent = text(labels, 'dropToPin')
					var box = drop.getBoundingClientRect()
					dropHint.style.left = box.left + box.width / 2 + 'px'
					dropHint.style.top = box.bottom + 6 + 'px'
					document.body.appendChild(dropHint)
				} else if (!on && drop !== null) {
					drop.remove()
					drop = null
					if (dropHint !== null) {
						dropHint.remove()
						dropHint = null
					}
					el.hidden = el.querySelector(':scope > a') === null
				}
			},
			ghost: function (info, x, y, hintText) {
				if (info === null) {
					if (ghost !== null) {
						ghost.remove()
						ghost = null
					}
					return
				}
				if (ghost === null) {
					ghost = document.createElement('div')
					ghost.className = PINS + '__ghost'
					ghost.setAttribute('aria-hidden', 'true')
					ghost.appendChild(pinIcon(info))
					var name = document.createElement('span')
					name.className = PINS + '__ghost-name'
					name.textContent = info.name
					ghost.appendChild(name)
					var hint = document.createElement('span')
					hint.className = PINS + '__hint ' + PINS + '__ghost-hint'
					ghost.appendChild(hint)
					document.body.appendChild(ghost)
				}
				ghost.style.left = x + 12 + 'px'
				ghost.style.top = y + 12 + 'px'
				var hintEl = ghost.querySelector('.' + PINS + '__ghost-hint')
				hintEl.textContent = hintText || ''
				hintEl.hidden = !hintText
			},
		}
		return bar
	}

	/**
	 * The pointer and the keyboard on the pinned apps: drag a pin off the bar
	 * to unpin it, Alt+P unpins the focused pin, Alt with an arrow key moves it.
	 *
	 * @param {object} menu The menu.
	 * @param {object} bar The pin bar.
	 * @param {Element} el The bar's element.
	 * @spec openspec/specs/apps-menu-pins/spec.md#requirement-a-person-unpins-an-app
	 */
	function wirePins(menu, bar, el) {
		var labels = menu.labels || {}
		var drag = null
		var swallowUntil = 0
		var pinOf = function (target) {
			var a = target && target.closest ? target.closest('a[data-thematiq-pin]') : null
			return a !== null && a.parentNode === el ? a : null
		}
		el.addEventListener('dragstart', function (event) {
			event.preventDefault()
		})
		el.addEventListener(
			'click',
			function (event) {
				if (Date.now() < swallowUntil) {
					event.preventDefault()
					event.stopImmediatePropagation()
				}
			},
			true,
		)
		el.addEventListener('keydown', function (event) {
			var a = pinOf(event.target)
			if (a === null || !event.altKey || event.ctrlKey || event.metaKey) {
				return
			}
			var key = a.getAttribute('data-thematiq-pin')
			if (event.code === 'KeyP' || String(event.key).toLowerCase() === 'p') {
				event.preventDefault()
				var next = a.nextElementSibling || a.previousElementSibling
				bar.toggle(key, a.getAttribute('aria-label'))
				var target = next && next.matches('a[data-thematiq-pin]')
					? el.querySelector('a[data-thematiq-pin="' + next.getAttribute('data-thematiq-pin') + '"]')
					: document.querySelector('.app-menu__waffle')
				if (target !== null) {
					target.focus()
				}
			} else if (event.key === 'ArrowLeft' || event.key === 'ArrowUp') {
				event.preventDefault()
				bar.move(key, -1)
			} else if (event.key === 'ArrowRight' || event.key === 'ArrowDown') {
				event.preventDefault()
				bar.move(key, 1)
			}
		})
		el.addEventListener('pointerdown', function (event) {
			var a = pinOf(event.target)
			if (a !== null && event.button === 0) {
				drag = { el: a, x: event.clientX, y: event.clientY, moved: false }
			}
		})
		document.addEventListener('pointermove', function (event) {
			if (drag === null) {
				return
			}
			if (!drag.moved) {
				if (Math.abs(event.clientX - drag.x) + Math.abs(event.clientY - drag.y) < DRAG_START) {
					return
				}
				drag.moved = true
				drag.info = resolvePin(menu, drag.el.getAttribute('data-thematiq-pin'))
				drag.el.setAttribute('data-thematiq-dragging', '')
			}
			var off = !bar.inBar(event.clientX, event.clientY)
			bar.ghost(drag.info, event.clientX, event.clientY, off ? text(labels, 'releaseToRemove') : '')
		})
		var end = function (event) {
			if (drag === null) {
				return
			}
			var done = drag
			drag = null
			if (!done.moved) {
				return
			}
			done.el.removeAttribute('data-thematiq-dragging')
			bar.ghost(null)
			swallowUntil = Date.now() + 300
			if (event.type === 'pointerup' && !bar.inBar(event.clientX, event.clientY)) {
				bar.toggle(done.el.getAttribute('data-thematiq-pin'), done.el.getAttribute('aria-label'))
			}
		}
		document.addEventListener('pointerup', end)
		document.addEventListener('pointercancel', end)
	}

	/**
	 * A saver that posts the order a moment after the last move, and says so
	 * in the live region when it fails.
	 *
	 * @param {object} menu The menu.
	 * @return {Function} Saves an order.
	 * @spec openspec/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
	 */
	function saver(menu) {
		var timer = null
		return function (keys) {
			if (
				typeof OC === 'undefined'
				|| typeof OC.generateUrl !== 'function'
				|| typeof fetch !== 'function'
			) {
				return
			}
			clearTimeout(timer)
			timer = setTimeout(function () {
				var failed = function () {
					var live = document.querySelector(
						'.app-menu__grid > [data-thematiq-row="live"]',
					)
					var message = text(menu.labels || {}, 'saveFailed')
					if (live !== null && message !== '') {
						live.textContent = message
					}
				}
				fetch(OC.generateUrl('/apps/thematiq/app-menu/order'), {
					method: 'POST',
					headers: {
						requesttoken: OC.requestToken,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({ order: keys }),
				})
					.then(function (res) {
						if (!res.ok) {
							failed()
						}
					})
					.catch(failed)
			}, SAVE_DELAY)
		}
	}

	/**
	 * Hover to open, click to pin. Nextcloud 33 and 34 open the menu on a
	 * click only; the fork's Nextcloud 35 opens it on hover itself
	 * (`.app-menu__trigger` exists there) but closes a click-opened menu when
	 * the pointer leaves. A pinned or click-opened menu ignores the pointer
	 * leaving; Escape, a click outside and a click on the button close it.
	 *
	 * @spec openspec/specs/apps-menu-opening/spec.md#requirement-the-apps-menu-opens-on-hover-and-pins-on-click
	 */
	function opening() {
		if (window.thematiqAppMenuOpening) {
			return
		}
		window.thematiqAppMenuOpening = true
		var peek = false
		var pinned = false
		var synthetic = false
		var openTimer = null
		var closeTimer = null
		var reopenUntil = 0
		var watched = null
		var watchButton = function () {
			var button = waffle()
			if (
				button === null
				|| button === watched
				|| typeof MutationObserver !== 'function'
			) {
				return
			}
			watched = button
			new MutationObserver(function () {
				if (pinned && !isOpen() && Date.now() < reopenUntil) {
					reopenUntil = 0
					toggle()
				}
			}).observe(button, {
				attributes: true,
				attributeFilter: ['aria-expanded'],
			})
		}
		var zone =
			TRIGGERS
			+ ', .app-menu, .app-menu__popover, .app-menu__trigger, .' + PINS

		var waffle = function () {
			return document.querySelector('.app-menu__waffle')
		}
		var isOpen = function () {
			var button = waffle()
			return button !== null && button.getAttribute('aria-expanded') === 'true'
		}
		var coreHovers = function () {
			return document.querySelector('.app-menu__trigger') !== null
		}
		var canHover = function () {
			var fine = window.matchMedia
				? window.matchMedia('(hover: hover) and (pointer: fine)').matches
				: false
			return fine && document.hasFocus()
		}
		var toggle = function () {
			var button = waffle()
			if (button !== null) {
				synthetic = true
				try {
					button.click()
				} finally {
					synthetic = false
				}
			}
		}
		var settle = function () {
			if (!isOpen()) {
				peek = false
				pinned = false
			}
		}
		var inZone = function (node) {
			return !!(node && node.closest && node.closest(zone))
		}

		document.addEventListener('pointerover', function (event) {
			if (!inZone(event.target)) {
				return
			}
			clearTimeout(closeTimer)
			settle()
			var onButton = event.target.closest(TRIGGERS) !== null
			if (!onButton || isOpen() || coreHovers() || !canHover()) {
				return
			}
			clearTimeout(openTimer)
			openTimer = setTimeout(function () {
				if (!isOpen()) {
					peek = true
					toggle()
				}
			}, HOVER_OPEN)
		})
		document.addEventListener('pointerout', function (event) {
			if (!inZone(event.target) || inZone(event.relatedTarget)) {
				return
			}
			clearTimeout(openTimer)
			if (!peek || pinned || coreHovers()) {
				return
			}
			clearTimeout(closeTimer)
			closeTimer = setTimeout(function () {
				if (peek && !pinned && isOpen() && !window.thematiqAppMenuDragging) {
					toggle()
				}
				peek = false
			}, HOVER_CLOSE)
		})
		window.addEventListener(
			'click',
			function (event) {
				if (
					synthetic
					|| !event.target.closest
					|| event.target.closest(TRIGGERS) === null
				) {
					return
				}
				clearTimeout(openTimer)
				clearTimeout(closeTimer)
				settle()
				watchButton()
				if (isOpen() && !pinned) {
					event.preventDefault()
					event.stopImmediatePropagation()
					peek = false
					pinned = true
					// The popover library hears this click before any other
					// script and hides the menu a frame later: open it again.
					reopenUntil = Date.now() + REOPEN_WINDOW
					return
				}
				pinned = !isOpen()
				peek = false
			},
			true,
		)
		// A press on a button while a look is open would reach the popover's
		// outside-press handler, or move focus out of the popover, and either
		// closes it before the click can pin it.
		;['pointerdown', 'mousedown'].forEach(function (type) {
			window.addEventListener(
				type,
				function (event) {
					if (
						!synthetic
						&& !pinned
						&& isOpen()
						&& event.target.closest
						&& event.target.closest(TRIGGERS) !== null
					) {
						event.preventDefault()
						event.stopImmediatePropagation()
					}
				},
				true,
			)
		})
		;['mouseleave', 'pointerleave', 'mouseout', 'pointerout'].forEach(
			function (type) {
				window.addEventListener(
					type,
					function (event) {
						if (pinned && isOpen() && inZone(event.target)) {
							event.stopImmediatePropagation()
						}
					},
					true,
				)
			},
		)
	}

	/**
	 * The classic menu: Nextcloud's apps menu stays as it ships. An
	 * administrator gets one row with the gear at its end, the way back to
	 * the Menu settings.
	 *
	 * @param {object} menu The menu.
	 * @spec openspec/specs/apps-menu-settings/spec.md#requirement-the-classic-style-leaves-nextclouds-apps-menu-alone
	 */
	function classic(menu) {
		var labels = menu.labels || {}
		if (menu.admin !== true || text(labels, 'menuSettings') === '') {
			return
		}
		var add = function () {
			var dialog = window.ThematiqMenuSettingsDialog
			if (!dialog || typeof dialog.open !== 'function') {
				return
			}
			document.querySelectorAll('.app-menu__grid').forEach(function (grid) {
				if (grid.querySelector(':scope > .' + BLOCK + '__classic') !== null) {
					return
				}
				var gear = document.createElement('button')
				gear.type = 'button'
				gear.className = BLOCK + '__settings'
				gear.setAttribute('aria-haspopup', 'dialog')
				gear.appendChild(icon(COG))
				gear.appendChild(document.createTextNode(text(labels, 'menuSettings')))
				gear.addEventListener('click', function () {
					dialog.open({ menu: menu, entries: dialogEntries(grid, menu), opener: gear })
				})
				var holder = document.createElement('div')
				holder.className = BLOCK + '__classic'
				holder.appendChild(gear)
				grid.appendChild(holder)
			})
		}
		add()
		if (typeof MutationObserver === 'function') {
			new MutationObserver(add).observe(document.body, { childList: true, subtree: true })
		}
	}

	/**
	 * Watch for the menu to open and arrange it.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function thematiqAppMenuMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}
		var menu = null
		try {
			menu = OCP.InitialState.loadState('thematiq', 'app-menu', null)
		} catch (e) {
			return
		}
		if (!menu || typeof menu !== 'object') {
			return
		}
		if (settingsOf(menu).style === 'classic') {
			classic(menu)
			return
		}
		var save = saver(menu)
		opening()
		menu.pinBar = pinBar(menu)
		shareCreateActions(menu)

		var busy = false
		var scan = function () {
			if (busy) {
				return
			}
			busy = true
			try {
				menu.pinBar.ensure()
				document
					.querySelectorAll('.app-menu__grid')
					.forEach(function (grid) {
						arrange(grid, menu, save)
					})
			} finally {
				// The script's own changes are mutations too: drop them, or the
				// watcher would call scan again for ever.
				if (window.thematiqAppMenuObserver) {
					window.thematiqAppMenuObserver.takeRecords()
				}
				busy = false
			}
		}
		scan()
		if (typeof MutationObserver === 'function') {
			// One watcher per page, even when the script is evaluated again.
			if (window.thematiqAppMenuObserver) {
				window.thematiqAppMenuObserver.disconnect()
			}
			window.thematiqAppMenuObserver = new MutationObserver(scan)
			window.thematiqAppMenuObserver.observe(document.body, {
				childList: true,
				subtree: true,
			})
		}
	}
})()
