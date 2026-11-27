/**
 * @vitest-environment jsdom
 *
 * Sort and filter from the column headers (js/lib/tableColumnControls.js): the
 * header buttons the Tq boards draw, sorting both ways, the filter dialog with
 * a value list or a contains box, the active state, and both holding when the
 * panel replaces its rows.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * @spec openspec/specs/admin-settings/spec.md#requirement-table-column-headers-sort-and-filter-req-aset-104
 */

import { afterEach, beforeEach, describe, expect, it } from 'vitest'

const TABLE = `
	<div id="nldesign-settings">
		<table class="nldesign-audit-table" data-column-controls>
			<thead>
				<tr>
					<th scope="col">Token set</th>
					<th scope="col">Status</th>
					<th scope="col" colspan="2">Both</th>
					<th scope="col"><span class="hidden-visually">Actions</span></th>
				</tr>
			</thead>
			<tbody>
				<tr><td>Utrecht</td><td>Scheduled</td><td></td><td></td><td></td></tr>
				<tr><td>Amsterdam</td><td>Failed</td><td></td><td></td><td></td></tr>
				<tr><td>zuiddrecht 10</td><td>Scheduled</td><td></td><td></td><td></td></tr>
				<tr><td>zuiddrecht 9</td><td>Running</td><td></td><td></td><td></td></tr>
			</tbody>
		</table>
	</div>
`

function names() {
	return Array.from(document.querySelectorAll('tbody tr'))
		.filter((row) => !row.hidden)
		.map((row) => row.cells[0].textContent)
}

function header(index) {
	return document.querySelectorAll('thead th')[index]
}

async function load() {
	await import('../../js/lib/tableColumnControls.js?t=' + Math.random())
}

describe('table column controls', () => {
	beforeEach(async () => {
		window.t = (app, text, vars) =>
			Object.keys(vars || {}).reduce(
				(acc, key) => acc.replace('{' + key + '}', vars[key]),
				text,
			)
		document.body.innerHTML = TABLE
		await load()
	})

	afterEach(() => {
		document.querySelectorAll('.thematiq-column-filter').forEach((el) => el.remove())
		document.body.innerHTML = ''
		delete window.ThematiqTableColumnControls
	})

	it('gives every data header a sort button and a filter button', () => {
		const sort = header(0).querySelector('.thematiq-column-head__sort')
		const filter = header(0).querySelector('.thematiq-column-head__filter')
		expect(sort.textContent.trim()).toBe('Token set')
		expect(sort.querySelector('svg')).not.toBeNull()
		expect(filter.getAttribute('aria-haspopup')).toBe('dialog')
		expect(filter.getAttribute('aria-label')).toBe('Filter by Token set')
		expect(header(0).textContent.trim()).toBe('Token set')
	})

	it('skips a spanning header and the visually hidden Actions header', () => {
		expect(header(2).querySelector('.thematiq-column-head')).toBeNull()
		expect(header(3).querySelector('.thematiq-column-head')).toBeNull()
		expect(header(3).querySelector('.hidden-visually').textContent).toBe('Actions')
	})

	it('wires a table only once', () => {
		const table = document.querySelector('table')
		const first = window.ThematiqTableColumnControls.enhance(table)
		expect(window.ThematiqTableColumnControls.enhance(table)).toBe(first)
		expect(header(0).querySelectorAll('.thematiq-column-head')).toHaveLength(1)
	})

	it('sorts ascending, then descending, with numbers in order', () => {
		const sort = header(0).querySelector('.thematiq-column-head__sort')
		sort.click()
		expect(names()).toEqual(['Amsterdam', 'Utrecht', 'zuiddrecht 9', 'zuiddrecht 10'])
		expect(header(0).getAttribute('aria-sort')).toBe('ascending')
		expect(sort.classList.contains('thematiq-column-head__sort--sorted')).toBe(true)
		sort.click()
		expect(names()).toEqual(['zuiddrecht 10', 'zuiddrecht 9', 'Utrecht', 'Amsterdam'])
		expect(header(0).getAttribute('aria-sort')).toBe('descending')
		expect(sort.classList.contains('thematiq-column-head__sort--descending')).toBe(true)
	})

	it('moves the sort mark to the column sorted last', () => {
		header(0).querySelector('.thematiq-column-head__sort').click()
		header(1).querySelector('.thematiq-column-head__sort').click()
		expect(header(0).hasAttribute('aria-sort')).toBe(false)
		expect(header(1).getAttribute('aria-sort')).toBe('ascending')
		expect(names()).toEqual(['Amsterdam', 'zuiddrecht 9', 'Utrecht', 'zuiddrecht 10'])
	})

	it('filters on ticked values and shows the filter as active', () => {
		const filter = header(1).querySelector('.thematiq-column-head__filter')
		filter.click()
		const dialog = document.querySelector('.thematiq-column-filter')
		expect(dialog.getAttribute('role')).toBe('dialog')
		expect(filter.getAttribute('aria-expanded')).toBe('true')
		const options = Array.from(dialog.querySelectorAll('input[type="checkbox"]'))
		expect(options.map((box) => box.value)).toEqual(['Failed', 'Running', 'Scheduled'])
		options[2].checked = true
		dialog.querySelector('.thematiq-column-filter__button--primary').click()
		expect(names()).toEqual(['Utrecht', 'zuiddrecht 10'])
		expect(document.querySelector('.thematiq-column-filter')).toBeNull()
		expect(filter.getAttribute('aria-label')).toBe('Filter by Status (active)')
		expect(filter.classList.contains('thematiq-column-head__filter--active')).toBe(true)
		expect(document.activeElement).toBe(filter)
	})

	it('clears a filter', () => {
		const filter = header(1).querySelector('.thematiq-column-head__filter')
		filter.click()
		document.querySelectorAll('.thematiq-column-filter input')[0].checked = true
		document.querySelector('.thematiq-column-filter__button--primary').click()
		expect(names()).toEqual(['Amsterdam'])
		filter.click()
		expect(document.querySelectorAll('.thematiq-column-filter input')[0].checked).toBe(true)
		document.querySelector('.thematiq-column-filter__button:not(.thematiq-column-filter__button--primary)').click()
		expect(names()).toHaveLength(4)
		expect(filter.getAttribute('aria-label')).toBe('Filter by Status')
	})

	it('uses a contains box when the column has many values', () => {
		const body = document.querySelector('tbody')
		for (let i = 0; i < 12; i++) {
			const row = document.createElement('tr')
			row.innerHTML = `<td>Set ${i}</td><td>x</td><td></td><td></td><td></td>`
			body.appendChild(row)
		}
		header(0).querySelector('.thematiq-column-head__filter').click()
		const dialog = document.querySelector('.thematiq-column-filter')
		expect(dialog.querySelector('input[type="checkbox"]')).toBeNull()
		const input = dialog.querySelector('.thematiq-column-filter__input')
		expect(dialog.querySelector('.thematiq-column-filter__label').textContent).toBe('Contains')
		input.value = 'ZUID'
		input.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter', bubbles: true }))
		expect(names()).toEqual(['zuiddrecht 10', 'zuiddrecht 9'])
	})

	it('closes the dialog on Escape and returns focus to the filter button', () => {
		const filter = header(0).querySelector('.thematiq-column-head__filter')
		filter.click()
		const dialog = document.querySelector('.thematiq-column-filter')
		dialog.querySelector('input').dispatchEvent(
			new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }),
		)
		expect(document.querySelector('.thematiq-column-filter')).toBeNull()
		expect(filter.getAttribute('aria-expanded')).toBe('false')
		expect(document.activeElement).toBe(filter)
		expect(names()).toHaveLength(4)
	})

	it('keeps sort and filter when the panel replaces its rows', async () => {
		header(0).querySelector('.thematiq-column-head__sort').click()
		header(1).querySelector('.thematiq-column-head__filter').click()
		const boxes = document.querySelectorAll('.thematiq-column-filter input')
		boxes[2].checked = true
		document.querySelector('.thematiq-column-filter__button--primary').click()
		const body = document.querySelector('tbody')
		body.innerHTML = `
			<tr><td>Zwolle</td><td>Scheduled</td><td></td><td></td><td></td></tr>
			<tr><td>Delft</td><td>Failed</td><td></td><td></td><td></td></tr>
			<tr><td>Breda</td><td>Scheduled</td><td></td><td></td><td></td></tr>`
		await new Promise((resolve) => setTimeout(resolve, 0))
		expect(names()).toEqual(['Breda', 'Zwolle'])
	})

	it('never sorts or hides an empty-state row', async () => {
		document.querySelector('tbody').innerHTML =
			'<tr><td colspan="5">Nothing scheduled</td></tr>'
		await new Promise((resolve) => setTimeout(resolve, 0))
		header(1).querySelector('.thematiq-column-head__filter').click()
		const dialog = document.querySelector('.thematiq-column-filter')
		expect(dialog.querySelector('.thematiq-column-filter__hint').textContent).toBe(
			'No values to filter on',
		)
		header(0).querySelector('.thematiq-column-head__sort').click()
		expect(document.querySelector('tbody tr').hidden).toBe(false)
	})

	it('leaves rows a panel hid itself hidden', () => {
		const rows = document.querySelectorAll('tbody tr')
		rows[0].hidden = true
		header(0).querySelector('.thematiq-column-head__sort').click()
		expect(rows[0].hidden).toBe(true)
	})
})
