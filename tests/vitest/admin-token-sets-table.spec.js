/**
 * @vitest-environment jsdom
 *
 * Unit tests for js/admin-token-sets-table.js: rows with status, layout and
 * colour, the search, the sort, Show more, and Use this token set going
 * through the picker.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { beforeEach, describe, expect, it, vi } from 'vitest'

const SECTION = `
<select id="nldesign-token-set-select"><option value="zuiddrecht">Z</option><option value="rotterdam">R</option></select>
<section id="thematiq-token-sets">
	<span class="thematiq-token-sets__count"></span>
	<input class="thematiq-token-sets__search">
	<button class="thematiq-token-sets__own"></button>
	<table><thead><tr>
		<th data-column="name"><button>Name</button></th>
		<th data-column="status"><button>Status</button></th>
	</tr></thead><tbody></tbody></table>
	<span class="thematiq-token-sets__shown"></span>
	<button class="thematiq-token-sets__more">Show more</button>
</section>`

function sets(n) {
	const list = [
		{
			id: 'zuiddrecht',
			name: 'Gemeente Zuiddrecht',
			design_system: 'nldesign',
			theming: { primary_color: '#3669a5' },
			workplaceLayout: 'light',
		},
		{
			id: 'rotterdam',
			name: 'Gemeente Rotterdam',
			design_system: 'nldesign',
			theming: { primary_color: '#00811F' },
			licence: { restricted: true, confirmed: false },
		},
		{
			id: 'custom-verkiezingen',
			name: 'Zuiddrecht verkiezingen',
			design_system: 'nldesign',
			custom: true,
			warnings: [{ kind: 'incomplete' }],
		},
	]
	for (let i = 0; i < n; i++) {
		list.push({
			id: 'set-' + i,
			name: 'Set ' + String(i).padStart(2, '0'),
			design_system: 'lasuite',
			theming: { primary_color: '#000000' },
		})
	}
	return list
}

async function load(list, current) {
	window.OCP = {
		InitialState: {
			loadState: (app, key) => (key === 'tokenSets' ? list : current),
		},
	}
	vi.resetModules()
	await import('../../js/admin-token-sets-table.js?t=' + Math.random())
}

const cells = (row) =>
	[...row.querySelectorAll('td')].map((td) => td.textContent.trim())

describe('the token sets table', () => {
	beforeEach(() => {
		document.body.innerHTML = SECTION
		window.OC = { generateUrl: (path) => '/index.php' + path }
	})

	it('puts the active set first with its colour, layout and status', async () => {
		await load(sets(0), 'zuiddrecht')
		const rows = [...document.querySelectorAll('tbody tr')]
		expect(cells(rows[0]).slice(0, 5)).toEqual([
			'Gemeente Zuiddrecht',
			'NL Design System',
			'#3669A5',
			'Light workplace',
			'Active',
		])
		expect(cells(rows[1])[4]).toBe('Confirm licence')
		expect(cells(rows[2])[1]).toBe('NL Design SystemOwn setIncomplete set')
		expect(cells(rows[2])[3]).toBe('Follows Nextcloud')
	})

	it('asks to confirm a licence only for a restricted set', async () => {
		const list = sets(0)
		list[1].licence = { restricted: false, confirmed: false }
		await load(list, 'zuiddrecht')
		expect(cells(document.querySelectorAll('tbody tr')[1])[4]).toBe('Available')
	})

	it('shows 14 rows, then 14 more', async () => {
		await load(sets(20), 'zuiddrecht')
		expect(document.querySelectorAll('tbody tr')).toHaveLength(14)
		expect(
			document.querySelector('.thematiq-token-sets__shown').textContent,
		).toBe('14 of 23')
		document.querySelector('.thematiq-token-sets__more').click()
		expect(document.querySelectorAll('tbody tr')).toHaveLength(23)
		expect(document.querySelector('.thematiq-token-sets__more').hidden).toBe(
			true,
		)
	})

	it('searches by name and sorts by a column', async () => {
		await load(sets(3), 'zuiddrecht')
		const search = document.querySelector('.thematiq-token-sets__search')
		search.value = 'rotter'
		search.dispatchEvent(new Event('input'))
		expect(
			[...document.querySelectorAll('tbody tr')].map((row) => cells(row)[0]),
		).toEqual(['Gemeente Rotterdam'])
		search.value = ''
		search.dispatchEvent(new Event('input'))
		document.querySelector('th[data-column="name"] button').click()
		expect(cells(document.querySelector('tbody tr'))[0]).toBe(
			'Gemeente Rotterdam',
		)
		expect(
			document
				.querySelector('th[data-column="name"]')
				.getAttribute('aria-sort'),
		).toBe('ascending')
	})

	it('uses a set through the picker', async () => {
		await load(sets(0), 'zuiddrecht')
		const picker = document.getElementById('nldesign-token-set-select')
		const changed = vi.fn()
		picker.addEventListener('change', changed)
		const rotterdam = [...document.querySelectorAll('tbody tr')].find(
			(row) => cells(row)[0] === 'Gemeente Rotterdam',
		)
		rotterdam.querySelector('.thematiq-token-sets__toggle').click()
		const use = [...rotterdam.querySelectorAll('[role="menuitem"]')].find(
			(item) => item.textContent === 'Use this token set',
		)
		use.click()
		expect(picker.value).toBe('rotterdam')
		expect(changed).toHaveBeenCalledOnce()
	})

	it('marks the newly applied set active', async () => {
		await load(sets(0), 'zuiddrecht')
		document.dispatchEvent(
			new CustomEvent('thematiq:theme-applied', {
				detail: { tokenSet: 'rotterdam' },
			}),
		)
		expect(cells(document.querySelector('tbody tr')).slice(0, 1)).toEqual([
			'Gemeente Rotterdam',
		])
		expect(cells(document.querySelector('tbody tr'))[4]).toBe('Active')
	})
})
