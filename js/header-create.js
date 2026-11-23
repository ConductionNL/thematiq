/**
 * Header style "workplace": the Quick add button in the top bar, before the
 * bell, on the apps whose boards draw it (Mail: MlInbox, Calendar: ClWeek,
 * Deck: TbBoard). A 44px round button with a plus opens a menu of what the
 * person can create.
 *
 * The menu lists the apps menu's own Create actions
 * (`window.thematiqCreateActions`, shared by js/app-menu.js), so both list
 * the same things in the same order. They are fetched on the first open.
 * Without that source, or with the Create actions switched off, no button is
 * drawn.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
;(function thematiqHeaderCreateInit() {
	var BLOCK = 'thematiq-quick-add'
	var SVG_NS = 'http://www.w3.org/2000/svg'
	// The apps whose boards draw Quick add in the bar.
	var APPS = ['mail', 'calendar', 'deck']
	var PLUS = 'M12 5v14M5 12h14'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqHeaderCreateMain)
	} else {
		thematiqHeaderCreateMain()
	}

	/**
	 * The id of the open app, from Nextcloud's navigation entries.
	 *
	 * @return {string} The id, or the empty string.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function openApp() {
		var apps = null
		try {
			apps = OCP.InitialState.loadState('core', 'apps', null)
		} catch (e) {
			return ''
		}
		var id = ''
		if (apps !== null && typeof apps === 'object') {
			Object.keys(apps).forEach(function (key) {
				if (id === '' && apps[key] && apps[key].active === true) {
					id = String(apps[key].id || key)
				}
			})
		}
		return id
	}

	/**
	 * An icon: a filled path, or the board's stroked plus.
	 *
	 * @param {string} d The path data.
	 * @param {boolean} stroked Draw the path as a 1.75px line.
	 * @param {number} size The size in px.
	 * @return {SVGElement} The icon.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function icon(d, stroked, size) {
		var svg = document.createElementNS(SVG_NS, 'svg')
		svg.setAttribute('viewBox', '0 0 24 24')
		svg.setAttribute('width', String(size))
		svg.setAttribute('height', String(size))
		svg.setAttribute('aria-hidden', 'true')
		if (stroked) {
			svg.setAttribute('fill', 'none')
			svg.setAttribute('stroke', 'currentColor')
			svg.setAttribute('stroke-width', '1.75')
			svg.setAttribute('stroke-linecap', 'round')
		} else {
			svg.setAttribute('fill', 'currentColor')
		}
		var path = document.createElementNS(SVG_NS, 'path')
		path.setAttribute('d', d)
		svg.appendChild(path)
		return svg
	}

	/**
	 * Build the button and its menu, and wire them.
	 *
	 * @param {object} source The shared Create actions.
	 * @return {HTMLElement} The wrapper holding both.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function build(source) {
		var wrap = document.createElement('div')
		wrap.className = BLOCK

		var button = document.createElement('button')
		button.type = 'button'
		button.className = BLOCK + '__button'
		button.setAttribute('aria-label', t('thematiq', 'Quick add'))
		button.setAttribute('aria-haspopup', 'menu')
		button.setAttribute('aria-expanded', 'false')
		button.appendChild(icon(PLUS, true, 22))
		wrap.appendChild(button)

		var panel = document.createElement('div')
		panel.className = BLOCK + '__menu'
		panel.id = BLOCK + '-menu'
		panel.setAttribute('role', 'menu')
		panel.setAttribute('aria-label', source.title || t('thematiq', 'Quick add'))
		panel.hidden = true
		button.setAttribute('aria-controls', panel.id)
		wrap.appendChild(panel)

		var status = document.createElement('p')
		status.className = BLOCK + '__status'
		status.setAttribute('role', 'status')

		var loaded = null
		var items = function () {
			return Array.prototype.slice.call(panel.querySelectorAll('[role="menuitem"]'))
		}
		var close = function (refocus) {
			panel.hidden = true
			button.setAttribute('aria-expanded', 'false')
			if (refocus) {
				button.focus()
			}
		}
		var fill = function (entries) {
			panel.textContent = ''
			if (entries.length === 0) {
				status.textContent = t('thematiq', 'Nothing to create here.')
				panel.appendChild(status)
				return
			}
			entries.forEach(function (entry) {
				var item = document.createElement('button')
				item.type = 'button'
				item.className = BLOCK + '__item'
				item.setAttribute('role', 'menuitem')
				item.tabIndex = -1
				var mark = document.createElement('span')
				mark.className = BLOCK + '__mark'
				mark.appendChild(icon(entry.icon, false, 18))
				var name = document.createElement('span')
				name.textContent = entry.name
				item.appendChild(mark)
				item.appendChild(name)
				item.addEventListener('click', function () {
					close(false)
					entry.run(function () {
						status.textContent = source.failed
						panel.appendChild(status)
						panel.hidden = false
						button.setAttribute('aria-expanded', 'true')
					})
				})
				panel.appendChild(item)
			})
		}
		var open = function () {
			panel.hidden = false
			button.setAttribute('aria-expanded', 'true')
			if (loaded === null) {
				loaded = Promise.resolve(source.list()).then(fill, function () {
					fill([])
				})
			}
			loaded.then(function () {
				var first = items()[0]
				if (first && !panel.hidden) {
					first.focus()
				}
			})
		}

		button.addEventListener('click', function () {
			if (panel.hidden) {
				open()
			} else {
				close(false)
			}
		})
		panel.addEventListener('keydown', function (event) {
			var list = items()
			var at = list.indexOf(document.activeElement)
			if (event.key === 'Escape') {
				event.preventDefault()
				close(true)
			} else if (event.key === 'ArrowDown' && list.length > 0) {
				event.preventDefault()
				list[(at + 1) % list.length].focus()
			} else if (event.key === 'ArrowUp' && list.length > 0) {
				event.preventDefault()
				list[(at - 1 + list.length) % list.length].focus()
			} else if (event.key === 'Tab') {
				close(false)
			}
		})
		document.addEventListener('click', function (event) {
			if (!panel.hidden && !wrap.contains(event.target)) {
				close(false)
			}
		})

		return wrap
	}

	/**
	 * Draw the button before the bell on the apps whose boards have it, once
	 * the apps menu has shared its Create actions, and keep it in the bar.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function thematiqHeaderCreateMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof t !== 'function'
			|| APPS.indexOf(openApp()) === -1
		) {
			return
		}

		var wrap = null
		var place = function () {
			var source = window.thematiqCreateActions
			if (!source || typeof source.list !== 'function') {
				return
			}
			if (wrap === null) {
				wrap = build(source)
			}
			var end = document.querySelector('#header .header-end')
			if (end === null || end.contains(wrap)) {
				return
			}
			var bell = end.querySelector(':scope > #notifications')
			end.insertBefore(wrap, bell)
		}

		place()
		document.addEventListener('thematiq:create-actions', place)
		// Nextcloud re-renders the bar's end after this script on some pages.
		if (typeof MutationObserver === 'function') {
			var header = document.getElementById('header')
			if (header !== null) {
				new MutationObserver(function () {
					if (wrap !== null && !document.contains(wrap)) {
						place()
					}
				}).observe(header, { childList: true, subtree: true })
			}
		}
	}
})()
