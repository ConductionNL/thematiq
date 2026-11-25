/**
 * @vitest-environment jsdom
 *
 * The own token sets panel (js/admin-own-sets-panel.js, the TqEigenSets
 * board): the counts, the scheduled switches in the Used column, the
 * contrast warnings in the aside and the header's Create house style.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const PAGE = `
	<section id="thematiq-page-header">
		<h1 class="thematiq-page-header__title">Themes and appearance</h1>
		<div class="thematiq-page-header__actions"></div>
		<nav class="thematiq-page-header__crumbs"></nav>
		<p class="thematiq-page-header__intro"></p>
	</section>
	<div id="nldesign-settings">
		<select id="nldesign-token-set-select"><option>a</option><option>b</option><option>c</option></select>
		<section class="thematiq-panel" data-thematiq-panel="own-sets" hidden>
			<button id="nldesign-create-tab-colours" type="button"></button>
			<input id="nldesign-brand-name">
			<span class="thematiq-own-sets-panel__count"></span>
			<table><tbody>
				<tr class="nldesign-custom-set-row" data-set-id="custom-a"><td class="nldesign-custom-set-used"></td></tr>
				<tr class="nldesign-custom-set-row" data-set-id="custom-b"><td class="nldesign-custom-set-used"></td></tr>
			</tbody></table>
			<section class="thematiq-own-sets-panel__warnings" hidden><p class="thematiq-own-sets-panel__warning-text"></p></section>
		</section>
	</div>
`

const SETS = [
	{ id: 'custom-a', name: 'Elections', warnings: [] },
	{
		id: 'custom-b',
		name: "King's Day",
		warnings: [{ pair: 'text on primary', ratio: 3.5 }],
	},
	{ id: 'custom-c', name: 'Broken', warnings: [{ kind: 'incomplete' }] },
]

function send(type, detail) {
	document.dispatchEvent(new CustomEvent(type, { detail }))
}

describe('Own token sets panel', () => {
	let added = []

	beforeEach(async () => {
		window.t = (app, text, vars) =>
			Object.keys(vars || {}).reduce(
				(acc, key) => acc.replace('{' + key + '}', vars[key]),
				text,
			)
		window.scrollTo = vi.fn()
		document.body.innerHTML = PAGE
		window.history.replaceState(null, '', window.location.pathname)
		for (const target of [window, document]) {
			const original = target.addEventListener.bind(target)
			vi.spyOn(target, 'addEventListener').mockImplementation(
				(type, listener, options) => {
					added.push([target, type, listener])
					original(type, listener, options)
				},
			)
		}
		vi.resetModules()
		await import('../../js/admin-panels.js?t=' + Math.random())
		await import('../../js/admin-own-sets-panel.js?t=' + Math.random())
		window.history.replaceState(null, '', '#own-sets')
		window.dispatchEvent(new HashChangeEvent('hashchange'))
	})

	afterEach(() => {
		vi.restoreAllMocks()
		added.forEach(([target, type, listener]) =>
			target.removeEventListener(type, listener),
		)
		added = []
		document.body.innerHTML = ''
		delete window.ThematiqPanels
		delete document.documentElement.dataset.thematiqOpenPanel
	})

	it('counts the own sets against all sets', () => {
		expect(
			document.querySelector('.thematiq-page-header__title').textContent,
		).toBe('Own token sets')
		send('thematiq:custom-sets', { sets: SETS })
		expect(
			document.querySelector('.thematiq-page-header__intro').textContent,
		).toBe('3 own sets, 3 token sets in total')
		expect(
			document.querySelector('.thematiq-own-sets-panel__count').textContent,
		).toBe('3 own token sets')
	})

	it('names the sets with a contrast warning, not the incomplete one', () => {
		const card = document.querySelector('.thematiq-own-sets-panel__warnings')
		send('thematiq:custom-sets', { sets: [SETS[0]] })
		expect(card.hidden).toBe(true)
		send('thematiq:custom-sets', { sets: SETS })
		expect(card.hidden).toBe(false)
		expect(card.textContent.startsWith("King's Day: a colour pair")).toBe(true)
	})

	it('shows a scheduled switch in the Used column of its set', () => {
		send('thematiq:scheduled-switches', {
			switches: [
				{
					tokenSet: 'custom-a',
					startAt: '2027-03-02T06:00:00Z',
					status: 'planned',
				},
				{
					tokenSet: 'custom-b',
					startAt: '2026-10-01T07:00:00Z',
					status: 'failed',
				},
			],
		})
		const day = new Date('2027-03-02T06:00:00Z').toLocaleDateString([], {
			day: 'numeric',
			month: 'short',
			year: 'numeric',
		})
		expect(
			document.querySelector(
				'[data-set-id="custom-a"] .nldesign-custom-set-used',
			).textContent,
		).toBe('Scheduled for ' + day)
		expect(
			document.querySelector(
				'[data-set-id="custom-b"] .nldesign-custom-set-used',
			).textContent,
		).toBe('')
		send('thematiq:custom-sets', { sets: SETS })
		expect(
			document.querySelectorAll('.thematiq-own-sets-panel__scheduled'),
		).toHaveLength(1)
	})

	it('opens the colours form from the header button', () => {
		const tab = document.getElementById('nldesign-create-tab-colours')
		const clicked = vi.fn()
		tab.addEventListener('click', clicked)
		const button = document.querySelector(
			'.thematiq-page-header__actions button',
		)
		expect(button.textContent).toBe('Create house style')
		button.click()
		expect(clicked).toHaveBeenCalled()
		expect(document.activeElement.id).toBe('nldesign-brand-name')
	})
})
