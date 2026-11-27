/**
 * Sort and filter from a table's column headers, as the Tq boards draw them.
 *
 * Every data column header of a `table[data-column-controls]` gets the label
 * and a chevron as one sort button, then a 22px filter button with the
 * filter-outline funnel (`aria-haspopup="dialog"`, "Filter by <column>"). The
 * filter button opens a small dialog: a checkbox list of the column's values
 * when there are few, else a "contains" box, with Clear and Apply, the same
 * controls as nextcloud-vue's CnColumnFilterPopover. Skipped headers: an empty
 * or visually hidden one (the Actions column), one that spans columns, and one
 * marked `data-column-controls="off"`.
 *
 * The rows are filled by the panels' own scripts, often after this runs and
 * again on every reload, so sorting and filtering work on the rows in the DOM
 * and are applied again whenever the body's rows change. A row with a single
 * cell spanning the table (an empty state) is never sorted or hidden.
 *
 * `window.ThematiqTableColumnControls.enhance(table)` wires one table; every
 * `table[data-column-controls]` on the page is wired when the page loads.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-table-column-headers-sort-and-filter-req-aset-104
 */
;(function thematiqTableColumnControlsInit() {
	var CHEVRON = 'M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z'
	var FUNNEL =
		'M15,19.88C15.04,20.18 14.94,20.5 14.71,20.71C14.32,21.1 13.69,21.1 13.3,20.71L9.29,16.7C9.06,16.47 8.96,16.16 9,15.87V10.75L4.21,4.62C3.87,4.19 3.95,3.56 4.38,3.22C4.57,3.08 4.78,3 5,3V3H19V3C19.22,3 19.43,3.08 19.62,3.22C20.05,3.56 20.13,4.19 19.79,4.62L15,10.75V19.88M7.04,5L11,10.06V15.58L13,17.58V10.05L16.96,5H7.04Z'
	// Up to this many distinct values the filter is a checkbox list; above it, a
	// "contains" box, because a long list of unique values filters nothing.
	var MAX_OPTIONS = 12
	var sequence = 0

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
	 * A 14px MDI icon.
	 *
	 * @param {string} path The path data.
	 * @return {SVGElement} The icon.
	 */
	function icon(path) {
		var ns = 'http://www.w3.org/2000/svg'
		var svg = document.createElementNS(ns, 'svg')
		svg.setAttribute('width', '14')
		svg.setAttribute('height', '14')
		svg.setAttribute('viewBox', '0 0 24 24')
		svg.setAttribute('aria-hidden', 'true')
		svg.setAttribute('focusable', 'false')
		var p = document.createElementNS(ns, 'path')
		p.setAttribute('d', path)
		p.setAttribute('fill', 'currentColor')
		svg.appendChild(p)
		return svg
	}

	/**
	 * The text a cell sorts and filters by.
	 *
	 * @param {HTMLTableRowElement} row The row.
	 * @param {number} index The column.
	 * @return {string} The cell's text, trimmed, with runs of space collapsed.
	 */
	function cellText(row, index) {
		var cell = row.cells[index]
		if (!cell) {
			return ''
		}
		var value = cell.getAttribute('data-sort-value')
		if (value === null) {
			value = cell.textContent || ''
		}
		return value.replace(/\s+/g, ' ').trim()
	}

	/**
	 * Whether a header gets no controls.
	 *
	 * @param {HTMLTableCellElement} th The header.
	 * @return {boolean} True when it is skipped.
	 */
	function skipped(th) {
		if (th.getAttribute('data-column-controls') === 'off') {
			return true
		}
		if (th.colSpan > 1) {
			return true
		}
		var visible = Array.prototype.filter.call(th.childNodes, function (node) {
			if (node.nodeType === 3) {
				return node.textContent.trim() !== ''
			}
			return (
				node.nodeType === 1 &&
				!node.classList.contains('hidden-visually') &&
				node.textContent.trim() !== ''
			)
		})
		return visible.length === 0
	}

	/**
	 * Wire one table.
	 *
	 * @param {HTMLTableElement} table The table.
	 * @return {object|null} The table's controller, or null when it has no head or body.
	 */
	function enhance(table) {
		if (!table || table.tHead === null || table.tBodies.length === 0) {
			return null
		}
		if (table.__thematiqColumnControls) {
			return table.__thematiqColumnControls
		}
		var body = table.tBodies[0]
		var state = { sort: null, filters: {} }
		var columns = []
		var open = null
		var observer = null

		/**
		 * The rows that hold data (not an empty state spanning the table).
		 *
		 * @return {HTMLTableRowElement[]} The rows.
		 */
		function dataRows() {
			return Array.prototype.filter.call(body.rows, function (row) {
				return !(row.cells.length === 1 && row.cells[0].colSpan > 1)
			})
		}

		/**
		 * Whether a row passes every set column filter.
		 *
		 * @param {HTMLTableRowElement} row The row.
		 * @return {boolean} True when it stays visible.
		 */
		function passes(row) {
			return Object.keys(state.filters).every(function (key) {
				var filter = state.filters[key]
				var text = cellText(row, Number(key))
				if (filter.values) {
					return filter.values.indexOf(text) !== -1
				}
				return text.toLowerCase().indexOf(filter.contains.toLowerCase()) !== -1
			})
		}

		/**
		 * Sort and filter the body's rows by the current state.
		 *
		 * @return {void}
		 */
		function apply() {
			if (observer !== null) {
				observer.disconnect()
			}
			var rows = dataRows()
			if (state.sort !== null) {
				var index = state.sort.index
				var direction = state.sort.direction === 'descending' ? -1 : 1
				rows
					.map(function (row, position) {
						return { row: row, position: position }
					})
					.sort(function (a, b) {
						var order = cellText(a.row, index).localeCompare(
							cellText(b.row, index),
							undefined,
							{ numeric: true, sensitivity: 'base' },
						)
						return order === 0 ? a.position - b.position : order * direction
					})
					.forEach(function (entry) {
						body.appendChild(entry.row)
					})
			}
			// Only rows this filter hid are shown again: a panel may hide rows itself.
			rows.forEach(function (row) {
				if (!passes(row)) {
					row.hidden = true
					row.setAttribute('data-column-filtered', '')
				} else if (row.hasAttribute('data-column-filtered')) {
					row.hidden = false
					row.removeAttribute('data-column-filtered')
				}
			})
			columns.forEach(function (column) {
				var sorted = state.sort !== null && state.sort.index === column.index
				if (sorted) {
					column.th.setAttribute('aria-sort', state.sort.direction)
				} else {
					column.th.removeAttribute('aria-sort')
				}
				column.sort.classList.toggle('thematiq-column-head__sort--sorted', sorted)
				column.sort.classList.toggle(
					'thematiq-column-head__sort--descending',
					sorted && state.sort.direction === 'descending',
				)
				var active = Object.prototype.hasOwnProperty.call(
					state.filters,
					column.index,
				)
				column.filter.classList.toggle('thematiq-column-head__filter--active', active)
				column.filter.setAttribute(
					'aria-label',
					active
						? tr('Filter by {column} (active)', { column: column.label })
						: tr('Filter by {column}', { column: column.label }),
				)
			})
			if (observer !== null) {
				observer.observe(body, { childList: true })
			}
		}

		/**
		 * Close the open filter dialog.
		 *
		 * @param {boolean} [restoreFocus] Return focus to its filter button.
		 * @return {void}
		 */
		function close(restoreFocus) {
			if (open === null) {
				return
			}
			var current = open
			open = null
			current.panel.remove()
			current.column.filter.setAttribute('aria-expanded', 'false')
			document.removeEventListener('mousedown', current.outside, true)
			if (restoreFocus) {
				current.column.filter.focus()
			}
		}

		/**
		 * Open the filter dialog of one column.
		 *
		 * @param {object} column The column.
		 * @return {void}
		 */
		function openFilter(column) {
			close(false)
			var id = 'thematiq-column-filter-' + ++sequence
			var panel = document.createElement('div')
			panel.className = 'thematiq-column-filter'
			panel.setAttribute('role', 'dialog')
			panel.setAttribute('aria-labelledby', id)
			var heading = document.createElement('p')
			heading.id = id
			heading.className = 'thematiq-column-filter__heading'
			heading.textContent = tr('Filter by {column}', { column: column.label })
			panel.appendChild(heading)

			var current = state.filters[column.index] || null
			var values = []
			dataRows().forEach(function (row) {
				var text = cellText(row, column.index)
				if (text !== '' && values.indexOf(text) === -1) {
					values.push(text)
				}
			})
			values.sort(function (a, b) {
				return a.localeCompare(b, undefined, { numeric: true, sensitivity: 'base' })
			})
			var read
			if (values.length <= MAX_OPTIONS) {
				var fieldset = document.createElement('fieldset')
				fieldset.className = 'thematiq-column-filter__options'
				var legend = document.createElement('legend')
				legend.className = 'hidden-visually'
				legend.textContent = heading.textContent
				fieldset.appendChild(legend)
				if (values.length === 0) {
					var none = document.createElement('p')
					none.className = 'thematiq-column-filter__hint'
					none.textContent = tr('No values to filter on')
					fieldset.appendChild(none)
				}
				values.forEach(function (value) {
					var label = document.createElement('label')
					label.className = 'thematiq-column-filter__option'
					var box = document.createElement('input')
					box.type = 'checkbox'
					box.value = value
					box.checked = current !== null && current.values
						? current.values.indexOf(value) !== -1
						: false
					var text = document.createElement('span')
					text.textContent = value
					label.appendChild(box)
					label.appendChild(text)
					fieldset.appendChild(label)
				})
				panel.appendChild(fieldset)
				read = function () {
					var chosen = Array.prototype.filter
						.call(fieldset.querySelectorAll('input'), function (box) {
							return box.checked
						})
						.map(function (box) {
							return box.value
						})
					return chosen.length === 0 ? null : { values: chosen }
				}
			} else {
				var field = document.createElement('label')
				field.className = 'thematiq-column-filter__field'
				var caption = document.createElement('span')
				caption.className = 'thematiq-column-filter__label'
				caption.textContent = tr('Contains')
				var input = document.createElement('input')
				input.type = 'text'
				input.className = 'thematiq-column-filter__input'
				input.value = current !== null && current.contains ? current.contains : ''
				field.appendChild(caption)
				field.appendChild(input)
				panel.appendChild(field)
				read = function () {
					var text = input.value.trim()
					return text === '' ? null : { contains: text }
				}
			}

			var actions = document.createElement('div')
			actions.className = 'thematiq-column-filter__actions'
			var clear = document.createElement('button')
			clear.type = 'button'
			clear.className = 'thematiq-column-filter__button'
			clear.textContent = tr('Clear')
			var confirm = document.createElement('button')
			confirm.type = 'button'
			confirm.className =
				'thematiq-column-filter__button thematiq-column-filter__button--primary'
			confirm.textContent = tr('Apply')
			actions.appendChild(clear)
			actions.appendChild(confirm)
			panel.appendChild(actions)

			/**
			 * Store the dialog's choice and close it.
			 *
			 * @param {object|null} filter The filter, or null for none.
			 * @return {void}
			 */
			function commit(filter) {
				if (filter === null) {
					delete state.filters[column.index]
				} else {
					state.filters[column.index] = filter
				}
				apply()
				close(true)
			}
			clear.addEventListener('click', function () {
				commit(null)
			})
			confirm.addEventListener('click', function () {
				commit(read())
			})
			panel.addEventListener('keydown', function (event) {
				if (event.key === 'Escape') {
					event.preventDefault()
					event.stopPropagation()
					close(true)
				} else if (event.key === 'Enter' && event.target.tagName === 'INPUT') {
					event.preventDefault()
					commit(read())
				}
			})

			var rect = column.filter.getBoundingClientRect()
			panel.style.top = rect.bottom + window.scrollY + 4 + 'px'
			panel.style.left = rect.left + window.scrollX + 'px'
			document.body.appendChild(panel)
			column.filter.setAttribute('aria-expanded', 'true')
			var outside = function (event) {
				if (!panel.contains(event.target) && event.target !== column.filter
					&& !column.filter.contains(event.target)) {
					close(false)
				}
			}
			document.addEventListener('mousedown', outside, true)
			open = { panel: panel, column: column, outside: outside }
			var first = panel.querySelector('input')
			;(first || confirm).focus()
		}

		Array.prototype.forEach.call(table.tHead.rows[0].cells, function (th, index) {
			if (skipped(th)) {
				return
			}
			var label = th.textContent.replace(/\s+/g, ' ').trim()
			var wrap = document.createElement('span')
			wrap.className = 'thematiq-column-head'
			var sort = document.createElement('button')
			sort.type = 'button'
			sort.className = 'thematiq-column-head__sort'
			while (th.firstChild) {
				sort.appendChild(th.firstChild)
			}
			sort.appendChild(icon(CHEVRON))
			var filter = document.createElement('button')
			filter.type = 'button'
			filter.className = 'thematiq-column-head__filter'
			filter.setAttribute('aria-haspopup', 'dialog')
			filter.setAttribute('aria-expanded', 'false')
			filter.setAttribute('aria-label', tr('Filter by {column}', { column: label }))
			filter.title = tr('Filter by {column}', { column: label })
			filter.appendChild(icon(FUNNEL))
			wrap.appendChild(sort)
			wrap.appendChild(filter)
			th.appendChild(wrap)
			var column = { th: th, index: index, label: label, sort: sort, filter: filter }
			sort.addEventListener('click', function () {
				var direction =
					state.sort !== null &&
					state.sort.index === index &&
					state.sort.direction === 'ascending'
						? 'descending'
						: 'ascending'
				state.sort = { index: index, direction: direction }
				apply()
			})
			filter.addEventListener('click', function () {
				if (open !== null && open.column === column) {
					close(true)
					return
				}
				openFilter(column)
			})
			columns.push(column)
		})

		if (typeof window.MutationObserver === 'function') {
			observer = new window.MutationObserver(function () {
				apply()
			})
			observer.observe(body, { childList: true })
		}

		var controller = {
			apply: apply,
			close: close,
			state: state,
		}
		table.__thematiqColumnControls = controller
		return controller
	}

	/**
	 * Wire every marked table on the page.
	 *
	 * @return {void}
	 */
	function enhanceAll() {
		Array.prototype.forEach.call(
			document.querySelectorAll('table[data-column-controls]'),
			enhance,
		)
	}

	window.ThematiqTableColumnControls = { enhance: enhance, enhanceAll: enhanceAll }
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', enhanceAll)
	} else {
		enhanceAll()
	}
})()
