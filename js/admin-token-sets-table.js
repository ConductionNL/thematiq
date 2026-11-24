/**
 * Token sets as TqTheming draws them: a heading with a count line, a search
 * field and Own token set, then a table of every selectable set with its
 * design system, primary colour, layout and status, sortable by column, a
 * menu per row and Show more. Using a set goes through the picker of the
 * Active token set card, so the apply flow stays the one js/admin.js owns.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqTokenSetsTableInit() {
	var PAGE = 14

	var DESIGN_SYSTEMS = {
		nldesign: 'NL Design System',
		lasuite: 'La Suite',
		cunningham: 'Cunningham',
		'high-contrast': 'High contrast',
		'summer-breeze': 'Summer Breeze',
		none: 'Nextcloud',
	}

	/**
	 * Translate a string of this app.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] Placeholders.
	 * @return {string} The translation.
	 */
	function tr(text, vars) {
		if (typeof window.t === 'function') {
			return window.t('thematiq', text, vars)
		}
		return text.replace(/{(\w+)}/g, function (match, key) {
			return vars && key in vars ? String(vars[key]) : match
		})
	}

	/**
	 * Read an initial state of this app.
	 *
	 * @param {string} key The key.
	 * @param {*} fallback The value when it is absent.
	 * @return {*} The state.
	 */
	function state(key, fallback) {
		try {
			return window.OCP.InitialState.loadState('thematiq', key)
		} catch (e) {
			return fallback
		}
	}

	/**
	 * An element with a class and optional text.
	 *
	 * @param {string} tag The tag name.
	 * @param {string} className The class.
	 * @param {string} [text] The text.
	 * @return {HTMLElement} The element.
	 */
	function el(tag, className, text) {
		var node = document.createElement(tag)
		if (className) {
			node.className = className
		}
		if (typeof text === 'string') {
			node.textContent = text
		}
		return node
	}

	/**
	 * The row of one set: what the table shows and sorts on.
	 *
	 * @param {object} set A token set from the initial state.
	 * @param {string} current The id of the active set.
	 * @return {object} The row.
	 */
	function toRow(set, current) {
		var warnings = Array.isArray(set.warnings) ? set.warnings : []
		var status = 'available'
		if (set.id === current) {
			status = 'active'
		} else if (
			set.licence
			&& set.licence.restricted === true
			&& set.licence.confirmed !== true
		) {
			status = 'licence'
		}
		return {
			id: set.id,
			name: set.name || set.id,
			designSystem:
				DESIGN_SYSTEMS[set.design_system] || set.design_system || '',
			custom: set.custom === true || String(set.id).indexOf('custom-') === 0,
			incomplete: warnings.some(function (w) {
				return w && w.kind === 'incomplete'
			}),
			primary: (set.theming && set.theming.primary_color) || '',
			layout: set.workplaceLayout === 'light' ? 'light' : 'nextcloud',
			status: status,
		}
	}

	var STATUS = {
		active: 'Active',
		licence: 'Confirm licence',
		available: 'Available',
	}

	var LAYOUT = {
		light: 'Light workplace',
		nextcloud: 'Follows Nextcloud',
	}

	/**
	 * The text a column sorts on.
	 *
	 * @param {object} row The row.
	 * @param {string} column The column.
	 * @return {string} The sort key.
	 */
	function sortKey(row, column) {
		if (column === 'default') {
			return (row.status === 'active' ? '0' : '1') + row.name.toLowerCase()
		}
		if (column === 'status') {
			return ['active', 'licence', 'available'].indexOf(row.status) + row.name
		}
		if (column === 'layout') {
			return tr(LAYOUT[row.layout])
		}
		return String(row[column] || '').toLowerCase()
	}

	/**
	 * Build the table into its section.
	 *
	 * @param {HTMLElement} root The section.
	 * @param {object[]} sets The token sets.
	 * @param {string} current The id of the active set.
	 * @return {object} Handles for tests.
	 */
	function build(root, sets, current) {
		var rows = sets.map(function (set) {
			return toRow(set, current)
		})
		var view = { query: '', column: 'default', ascending: true, shown: PAGE }

		var count = root.querySelector('.thematiq-token-sets__count')
		var search = root.querySelector('.thematiq-token-sets__search')
		var body = root.querySelector('tbody')
		var footerCount = root.querySelector('.thematiq-token-sets__shown')
		var more = root.querySelector('.thematiq-token-sets__more')
		var headers = Array.prototype.slice.call(
			root.querySelectorAll('th[data-column]'),
		)
		var own = root.querySelector('.thematiq-token-sets__own')

		/** Close every open row menu. */
		function closeMenus() {
			Array.prototype.forEach.call(
				root.querySelectorAll('.thematiq-token-sets__menu'),
				function (menu) {
					menu.hidden = true
					var toggle = menu.previousSibling
					if (toggle) {
						toggle.setAttribute('aria-expanded', 'false')
					}
				},
			)
		}

		/**
		 * Select a set in the picker; js/admin.js applies it from there.
		 *
		 * @param {string} id The set.
		 */
		function useSet(id) {
			var picker = document.getElementById('nldesign-token-set-select')
			if (picker === null) {
				return
			}
			picker.value = id
			picker.dispatchEvent(new Event('change', { bubbles: true }))
			picker.focus()
		}

		/**
		 * The menu of one row.
		 *
		 * @param {object} row The row.
		 * @return {HTMLElement} The cell.
		 */
		function menuCell(row) {
			var cell = el('td', 'thematiq-token-sets__actions')
			var toggle = el('button', 'thematiq-token-sets__toggle')
			toggle.type = 'button'
			toggle.setAttribute(
				'aria-label',
				tr('More actions for {name}', { name: row.name }),
			)
			toggle.setAttribute('aria-haspopup', 'true')
			toggle.setAttribute('aria-expanded', 'false')
			var menu = el('div', 'thematiq-token-sets__menu')
			menu.setAttribute('role', 'menu')
			menu.hidden = true
			var items = [
				{
					text: tr('Use this token set'),
					run: function () {
						useSet(row.id)
					},
					hidden: row.status === 'active',
				},
				{ text: tr('Open token set'), href: referenceUrl(row.id, false) },
				{
					text: tr('Download token overview'),
					href: referenceUrl(row.id, true),
				},
			]
			items.forEach(function (item) {
				if (item.hidden) {
					return
				}
				var node
				if (item.href) {
					node = el('a', 'thematiq-token-sets__item', item.text)
					node.href = item.href
					if (item.href.indexOf('download=1') === -1) {
						node.target = '_blank'
						node.rel = 'noopener noreferrer'
					}
				} else {
					node = el('button', 'thematiq-token-sets__item', item.text)
					node.type = 'button'
					node.addEventListener('click', function () {
						closeMenus()
						item.run()
					})
				}
				node.setAttribute('role', 'menuitem')
				menu.appendChild(node)
			})
			toggle.addEventListener('click', function (event) {
				event.stopPropagation()
				var open = menu.hidden
				closeMenus()
				menu.hidden = !open
				toggle.setAttribute('aria-expanded', open ? 'true' : 'false')
				if (open && menu.firstChild) {
					menu.firstChild.focus()
				}
			})
			menu.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					closeMenus()
					toggle.focus()
				}
			})
			cell.appendChild(toggle)
			cell.appendChild(menu)
			return cell
		}

		/**
		 * One table row.
		 *
		 * @param {object} row The row.
		 * @return {HTMLElement} The tr.
		 */
		function rowElement(row) {
			var tr0 = el('tr')
			var name = el('td', 'thematiq-token-sets__name')
			var link = el('a', '', row.name)
			link.href = referenceUrl(row.id, false)
			link.target = '_blank'
			link.rel = 'noopener noreferrer'
			name.appendChild(link)
			tr0.appendChild(name)

			var system = el('td')
			var systemWrap = el(
				'span',
				'thematiq-token-sets__system',
				row.designSystem,
			)
			if (row.custom) {
				systemWrap.appendChild(
					el('span', 'thematiq-token-sets__tag', tr('Own set')),
				)
			}
			if (row.incomplete) {
				systemWrap.appendChild(
					el(
						'span',
						'thematiq-token-sets__tag thematiq-token-sets__tag--warning',
						tr('Incomplete set'),
					),
				)
			}
			system.appendChild(systemWrap)
			tr0.appendChild(system)

			var colour = el('td')
			if (row.primary !== '') {
				var colourWrap = el('span', 'thematiq-token-sets__colour')
				var chip = el('span', 'thematiq-token-sets__chip')
				chip.style.background = row.primary
				colourWrap.appendChild(chip)
				colourWrap.appendChild(
					document.createTextNode(row.primary.toUpperCase()),
				)
				colour.appendChild(colourWrap)
			}
			tr0.appendChild(colour)

			tr0.appendChild(el('td', '', tr(LAYOUT[row.layout])))

			var status = el('td')
			status.appendChild(
				el(
					'span',
					'thematiq-token-sets__pill thematiq-token-sets__pill--'
						+ row.status,
					tr(STATUS[row.status]),
				),
			)
			tr0.appendChild(status)

			tr0.appendChild(menuCell(row))
			return tr0
		}

		/** Draw the rows the search, the sort and the page allow. */
		function draw() {
			var query = view.query.trim().toLowerCase()
			var matching = rows.filter(function (row) {
				return (
					query === ''
					|| row.name.toLowerCase().indexOf(query) !== -1
					|| row.designSystem.toLowerCase().indexOf(query) !== -1
				)
			})
			matching.sort(function (a, b) {
				var order = sortKey(a, view.column).localeCompare(
					sortKey(b, view.column),
				)
				return view.ascending ? order : -order
			})
			body.textContent = ''
			matching.slice(0, view.shown).forEach(function (row) {
				body.appendChild(rowElement(row))
			})
			var shown = Math.min(view.shown, matching.length)
			var own = rows.filter(function (row) {
				return row.custom
			}).length
			count.textContent = tr(
				'{shown} of {total} token sets, of which {own} own sets. A set that does not set every token of its design system is marked as incomplete.',
				{ shown: shown, total: rows.length, own: own },
			)
			footerCount.textContent = tr('{shown} of {total}', {
				shown: shown,
				total: matching.length,
			})
			more.hidden = shown >= matching.length
			headers.forEach(function (th) {
				th.setAttribute(
					'aria-sort',
					th.dataset.column === view.column
						? view.ascending
							? 'ascending'
							: 'descending'
						: 'none',
				)
			})
		}

		search.addEventListener('input', function () {
			view.query = search.value
			view.shown = PAGE
			draw()
		})
		more.addEventListener('click', function () {
			view.shown += PAGE
			draw()
		})
		headers.forEach(function (th) {
			th.querySelector('button').addEventListener('click', function () {
				if (view.column === th.dataset.column) {
					view.ascending = !view.ascending
				} else {
					view.column = th.dataset.column
					view.ascending = true
				}
				draw()
			})
		})
		if (own !== null) {
			own.addEventListener('click', function () {
				window.location.hash = 'own-sets'
			})
		}
		document.addEventListener('click', closeMenus)
		document.addEventListener('thematiq:theme-applied', function (event) {
			var id = event.detail && event.detail.tokenSet
			if (id) {
				rows = sets.map(function (set) {
					return toRow(set, id)
				})
				draw()
			}
		})

		draw()
		return { draw: draw, view: view }
	}

	/**
	 * The token reference of a set.
	 *
	 * @param {string} id The set.
	 * @param {boolean} download Whether to download it.
	 * @return {string} The URL.
	 */
	function referenceUrl(id, download) {
		var path =
			'/apps/thematiq/api/token-sets/' + encodeURIComponent(id) + '/reference'
		var base =
			window.OC && typeof OC.generateUrl === 'function'
				? OC.generateUrl(path)
				: path
		return base + '?format=' + (download ? 'md&download=1' : 'html')
	}

	/** Find the section and build the table. */
	function main() {
		var root = document.getElementById('thematiq-token-sets')
		if (root !== null) {
			build(root, state('tokenSets', []), state('currentTokenSet', ''))
		}
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { build: build, toRow: toRow }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', main)
	} else {
		main()
	}
})()
