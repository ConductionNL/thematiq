/**
 * The apps menu of the workplace top bar, as the AppMenu board draws it.
 *
 * Loaded with the workplace header style (AppMenuService), with the menu's
 * sections as initial state (`thematiq/app-menu`): groups ([{title, link?,
 * apps: [{id, description?}]}]), othersTitle, hint and manageLabel. Every
 * key is optional.
 *
 * Nextcloud's menu is one grid of app tiles. When it opens, this script marks
 * the grid `data-thematiq-app-menu` (css/header-workplace.css then lays it
 * out as a 12-column grid without an inner scrollbar), gives each icon to its
 * circle as `--thematiq-app-icon` so the sheet can colour it, and arranges
 * the tiles by CSS `order`: each section's heading, its apps (wider tiles
 * with their description), then the other apps under their own heading, and
 * a footer with the keyboard hint and the "manage apps" link Nextcloud's own
 * outlined tile points to. The tiles stay where Nextcloud put them in the
 * DOM, so its keyboard handling keeps working. Text only, never markup.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
 */
;(function thematiqAppMenuInit() {
	var BLOCK = 'thematiq-app-menu'
	var OTHERS = 9000
	var FOOTER = 99999
	var EXTERNAL = 90000

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
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
	 */
	function text(source, key) {
		return source && typeof source[key] === 'string' ? source[key].trim() : ''
	}

	/**
	 * Whether an address may be linked: http(s) or a path on this server.
	 *
	 * @param {string} url The address.
	 * @return {boolean} True when it may be linked.
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
	 */
	function linkable(url) {
		return typeof url === 'string' && /^(https?:\/\/|\/(?!\/))/i.test(url)
	}

	/**
	 * The app id a tile links to: the segment after `/apps/`.
	 *
	 * @param {HTMLAnchorElement} tile The tile.
	 * @return {string} The id, or the empty string.
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
	 */
	function appIdOf(tile) {
		var match = /\/apps\/([a-z0-9_]+)/.exec(tile.getAttribute('href') || '')
		return match ? match[1] : ''
	}

	/**
	 * A row that spans the grid: a section heading, or the footer.
	 *
	 * @param {Element} grid The grid.
	 * @param {string} key A key that finds the row again.
	 * @param {number} order Its place.
	 * @return {HTMLElement} The row.
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
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
	 * A heading row: the title and, beside it, an optional link.
	 *
	 * @param {Element} grid The grid.
	 * @param {string} key The row key.
	 * @param {number} order Its place.
	 * @param {string} title The title.
	 * @param {object|undefined} link The link, {label, url}.
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
	 */
	function heading(grid, key, order, title, link) {
		var el = row(grid, key, order)
		el.className = BLOCK + '__heading'
		if (el.childElementCount > 0) {
			return
		}
		var h = document.createElement('h2')
		h.className = BLOCK + '__title'
		h.textContent = title
		el.appendChild(h)
		if (link && text(link, 'label') !== '' && linkable(link.url)) {
			var a = document.createElement('a')
			a.className = BLOCK + '__link'
			a.href = link.url
			a.textContent = text(link, 'label')
			el.appendChild(a)
		}
	}

	/**
	 * OpenProject's modules as a section of their own, after Nextcloud's apps and before the
	 * footer (openproject-in-the-app-menu). Links, not Nextcloud tiles: they are
	 * added once and keep their place. Text and path data only, never markup.
	 *
	 * @param {Element} grid The grid.
	 * @param {object|undefined} section {title, url, modules: [{key, label, url, icon}]}.
	 * @spec openspec/changes/openproject-in-the-app-menu/specs/openproject-in-the-app-menu/spec.md
	 */
	function external(grid, section) {
		if (!section || !Array.isArray(section.modules) || text(section, 'title') === '') {
			return
		}
		var modules = section.modules.filter(function (module) {
			return text(module, 'label') !== '' && /^https?:\/\//i.test(module.url || '')
		})
		if (modules.length === 0 || grid.querySelector(':scope > [data-thematiq-external]') !== null) {
			return
		}
		heading(grid, 'openproject', EXTERNAL, section.title)
		var svgNs = 'http://www.w3.org/2000/svg'
		modules.forEach(function (module, i) {
			var a = document.createElement('a')
			a.className = 'app-item ' + BLOCK + '__external'
			a.setAttribute('data-thematiq-external', text(module, 'key'))
			a.href = module.url
			a.style.order = String(EXTERNAL + 1 + i)
			var circle = document.createElement('span')
			circle.className = 'app-item__circle'
			var svg = document.createElementNS(svgNs, 'svg')
			svg.setAttribute('viewBox', '0 0 24 24')
			svg.setAttribute('width', '20')
			svg.setAttribute('height', '20')
			svg.setAttribute('aria-hidden', 'true')
			svg.setAttribute('fill', 'none')
			svg.setAttribute('stroke', 'currentColor')
			svg.setAttribute('stroke-width', '1.75')
			svg.setAttribute('stroke-linecap', 'round')
			svg.setAttribute('stroke-linejoin', 'round')
			if (/^[MmLlHhVvCcSsQqTtAaZz0-9 .,-]+$/.test(module.icon || '')) {
				var path = document.createElementNS(svgNs, 'path')
				path.setAttribute('d', module.icon)
				svg.appendChild(path)
			}
			circle.appendChild(svg)
			var label = document.createElement('span')
			label.className = 'app-item__label'
			label.textContent = text(module, 'label')
			a.appendChild(circle)
			a.appendChild(label)
			grid.appendChild(a)
		})
	}

	/**
	 * Arrange one open grid.
	 *
	 * @param {Element} grid The grid.
	 * @param {object} menu The menu's sections.
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
	 */
	function arrange(grid, menu) {
		grid.setAttribute('data-' + BLOCK, '')
		var groups = Array.isArray(menu.groups) ? menu.groups : []
		var place = {}
		groups.forEach(function (group, g) {
			;(group.apps || []).forEach(function (app, i) {
				if (app && typeof app.id === 'string' && !(app.id in place)) {
					place[app.id] = { group: g, index: i, app: app }
				}
			})
		})

		var tiles = Array.prototype.slice.call(
			grid.querySelectorAll(':scope > a.app-item:not([data-thematiq-external])'),
		)
		var used = {}
		var others = 0
		var manage = null
		tiles.forEach(function (tile, n) {
			var circle = tile.querySelector('.app-item__circle')
			var icon = tile.querySelector('img.app-item__icon')
			if (circle !== null && icon !== null && icon.getAttribute('src')) {
				circle.style.setProperty(
					'--thematiq-app-icon',
					'url("' + icon.getAttribute('src').replace(/["\\]/g, '') + '")',
				)
			}
			if (tile.classList.contains('app-item--outlined')) {
				// "More apps" and the app store: the footer's link instead.
				if (manage === null) {
					manage = tile.getAttribute('href')
				}
				tile.classList.add(BLOCK + '__outlined')
				tile.style.order = String(FOOTER - 1)
				return
			}
			var spot = place[appIdOf(tile)]
			if (spot) {
				used[spot.group] = true
				tile.classList.add(BLOCK + '__featured')
				tile.style.order = String((spot.group + 1) * 100 + spot.index + 1)
				var description = text(spot.app, 'description')
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
				return
			}
			others++
			tile.style.order = String(OTHERS + 1 + n)
		})

		groups.forEach(function (group, g) {
			if (used[g] && text(group, 'title') !== '') {
				heading(grid, 'group-' + g, (g + 1) * 100, group.title, group.link)
			}
		})
		if (
			Object.keys(used).length > 0
			&& others > 0
			&& text(menu, 'othersTitle') !== ''
		) {
			heading(grid, 'others', OTHERS, menu.othersTitle)
		}

		external(grid, menu.openproject)

		var hint = text(menu, 'hint')
		var label = text(menu, 'manageLabel')
		if (
			(hint !== '' || (label !== '' && linkable(manage)))
			&& grid.querySelector(':scope > [data-thematiq-row="footer"]') === null
		) {
			var footer = row(grid, 'footer', FOOTER)
			footer.className = BLOCK + '__footer'
			var tip = document.createElement('span')
			tip.className = BLOCK + '__hint'
			tip.textContent = hint
			footer.appendChild(tip)
			if (label !== '' && linkable(manage)) {
				var a = document.createElement('a')
				a.className = BLOCK + '__manage'
				a.href = manage
				a.textContent = label
				footer.appendChild(a)
				grid.setAttribute('data-thematiq-app-menu-manage', '')
			}
		}
	}

	/**
	 * Watch for the menu to open and arrange it.
	 *
	 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
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
						arrange(grid, menu)
					})
			} finally {
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
