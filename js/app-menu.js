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
 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
 */
;(function thematiqAppMenuInit() {
	var BLOCK = 'thematiq-app-menu'
	var SHOWN = 9
	var FOOTER = 99999
	var DRAG_START = 6
	var HOVER_OPEN = 300
	var HOVER_CLOSE = 180
	var SAVE_DELAY = 400
	var TRIGGERS = '.app-menu__waffle, .app-menu__current-app'
	var SVG_NS = 'http://www.w3.org/2000/svg'

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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
	 */
	function text(source, key) {
		return source && typeof source[key] === 'string' ? source[key].trim() : ''
	}

	/**
	 * Whether an address may be linked: http(s) or a path on this server.
	 *
	 * @param {string} url The address.
	 * @return {boolean} True when it may be linked.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
	 */
	function linkable(url) {
		return typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url)
	}

	/**
	 * The app id a tile links to: the segment after `/apps/`.
	 *
	 * @param {HTMLAnchorElement} tile The tile.
	 * @return {string} The id, or the empty string.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
	 */
	function fold(el, folded) {
		if (folded) {
			el.setAttribute('data-thematiq-folded', '')
		} else {
			el.removeAttribute('data-thematiq-folded')
		}
	}

	/**
	 * The external apps as links of their own, added once per grid. Text and
	 * path data only, never markup.
	 *
	 * @param {Element} grid The grid.
	 * @param {object|undefined} section {title, note, apps: [{key, label, line, url, icon}]}.
	 * @return {Array<HTMLAnchorElement>} The links, in the section's order.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md
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
				svg.setAttribute('fill', 'none')
				svg.setAttribute('stroke', 'currentColor')
				svg.setAttribute('stroke-width', '1.75')
				svg.setAttribute('stroke-linecap', 'round')
				svg.setAttribute('stroke-linejoin', 'round')
				if (/^[MmLlHhVvCcSsQqTtAaZz0-9 .,-]+$/.test(app.icon || '')) {
					var path = document.createElementNS(SVG_NS, 'path')
					path.setAttribute('d', app.icon)
					svg.appendChild(path)
				}
				circle.appendChild(svg)
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md
	 */
	function collect(grid, menu) {
		var place = {}
		var groups = Array.isArray(menu.groups) ? menu.groups : []
		var n = 0
		groups.forEach(function (group) {
			;(group.apps || []).forEach(function (app) {
				if (app && typeof app.id === 'string' && !(app.id in place)) {
					place[app.id] = { rank: n++, app: app }
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
			var description = spot ? text(spot.app, 'description') : ''
			if (
				description !== ''
				&& tile.querySelector('.' + BLOCK + '__description') === null
			) {
				var span = document.createElement('span')
				span.className = BLOCK + '__description'
				span.setAttribute('aria-hidden', 'true')
				span.textContent = description
				tile.appendChild(span)
			}
			entries.push({
				key: key,
				el: tile,
				external: false,
				rank: spot ? spot.rank : 1000 + i,
			})
		})
		externalLinks(grid, menu.external).forEach(function (a, i) {
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
	 */
	function arrange(grid, menu, save) {
		grid.setAttribute('data-' + BLOCK, '')
		var labels = menu.labels || {}
		var found = collect(grid, menu)
		var list = sorted(found.entries, menu.order)
		var expanded = grid.hasAttribute('data-thematiq-expanded')
		var many = list.length > SHOWN
		var rest = list.slice(SHOWN)
		var restApps = rest.filter(function (entry) {
			return !entry.external
		})
		var restExternal = rest.filter(function (entry) {
			return entry.external
		})

		list.slice(0, SHOWN).forEach(function (entry, i) {
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
				toggle.appendChild(button)
			}
			button.setAttribute('aria-expanded', expanded ? 'true' : 'false')
			var caption = text(labels, expanded ? 'hideApps' : 'allApps')
			if (button.textContent !== caption) {
				button.textContent = caption
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

		footer(grid, menu, found.manage)
		wire(grid, menu, save)
	}

	/**
	 * The footer: the hints and the "manage apps" link, and the live region.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu.
	 * @param {string|null} manage The address of Nextcloud's outlined tile.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
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
		var hint = text(menu, 'hint')
		var drag = [text(labels, 'dragHint'), text(labels, 'moveHint')]
			.filter(Boolean)
			.join(' ')
		var label = text(menu, 'manageLabel')
		if (hint === '' && drag === '' && !(label !== '' && linkable(manage))) {
			return
		}
		var el = row(grid, 'footer', FOOTER)
		el.className = BLOCK + '__footer'
		var hints = document.createElement('span')
		hints.className = BLOCK + '__hint'
		;[drag, hint].forEach(function (value) {
			if (value !== '') {
				var span = document.createElement('span')
				span.textContent = value
				hints.appendChild(span)
			}
		})
		el.appendChild(hints)
		if (label !== '' && linkable(manage)) {
			var a = document.createElement('a')
			a.className = BLOCK + '__manage'
			a.href = manage
			a.textContent = label
			el.appendChild(a)
			grid.setAttribute('data-thematiq-app-menu-manage', '')
		}
	}

	/**
	 * The entries and the toggle a person can reach now, in screen order.
	 *
	 * @param {Element} grid The grid.
	 * @return {Array<Element>} The elements.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md#requirement-the-workplace-header-draws-the-apps-menu-of-the-board
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-a-person-orders-the-apps-menu
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
			swallowUntil = Date.now() + 300
			save(menu.order)
		}
		document.addEventListener('pointermove', onMove)
		document.addEventListener('pointerup', onEnd)
		document.addEventListener('pointercancel', onEnd)
	}

	/**
	 * A saver that posts the order a moment after the last move, and says so
	 * in the live region when it fails.
	 *
	 * @param {object} menu The menu.
	 * @return {Function} Saves an order.
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md#requirement-the-order-is-stored-for-the-person-on-the-server
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
	 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-opening/spec.md#requirement-the-apps-menu-opens-on-hover-and-pins-on-click
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
		var zone = TRIGGERS + ', .app-menu__popover, .app-menu__trigger'

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
				if (peek && !pinned && isOpen()) {
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
				if (isOpen() && !pinned) {
					event.preventDefault()
					event.stopImmediatePropagation()
					peek = false
					pinned = true
					return
				}
				pinned = !isOpen()
				peek = false
			},
			true,
		)
		window.addEventListener(
			'mouseleave',
			function (event) {
				if (pinned && isOpen() && inZone(event.target)) {
					event.stopImmediatePropagation()
				}
			},
			true,
		)
	}

	/**
	 * Watch for the menu to open and arrange it.
	 *
	 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
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
		var save = saver(menu)
		opening()

		var busy = false
		var scan = function () {
			if (busy) {
				return
			}
			busy = true
			try {
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
