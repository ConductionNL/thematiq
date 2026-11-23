/**
 * @vitest-environment jsdom
 *
 * js/workplace-apps.js: the boards' words for Mail and Calendar as
 * translated variables, Calendar's week days as "Mon 12" (ClWeek), and the
 * pointer mark that lets Deck's card title drop a focus ring it did not ask
 * for (TbCard). It acts only while workplace-apps/common.css is on the page.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

let sheetLoaded = true
const realComputedStyle = window.getComputedStyle.bind(window)

async function loadScript() {
	vi.resetModules()
	await import('../../js/workplace-apps.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

const frame = () => new Promise((resolve) => setTimeout(resolve, 40))

const WEEK =
	'<div id="content" class="app-calendar"><div class="fc-timeGridWeek-view">'
	+ '<table><tr>'
	+ '<th class="fc-col-header-cell" data-date="2026-11-23"><a class="fc-col-header-cell-cushion">Mon 23/11/2026</a></th>'
	+ '<th class="fc-col-header-cell" data-date="2026-11-01"><a class="fc-col-header-cell-cushion">Sun 01/11/2026</a></th>'
	+ '</tr></table></div></div>'

describe('the workplace app layer script', () => {
	beforeEach(() => {
		sheetLoaded = true
		global.t = (app, text) => (text === 'New event' ? 'Nieuwe afspraak' : text)
		vi.spyOn(window, 'getComputedStyle').mockImplementation((element) => {
			const style = realComputedStyle(element)
			return {
				getPropertyValue: (name) =>
					name === '--thematiq-workplace-apps' && sheetLoaded && element === document.documentElement
						? ' 1'
						: style.getPropertyValue(name),
			}
		})
	})

	afterEach(() => {
		if (window.thematiqWorkplaceAppsObserver) {
			window.thematiqWorkplaceAppsObserver.disconnect()
			delete window.thematiqWorkplaceAppsObserver
		}
		document.body.innerHTML = ''
		document.documentElement.removeAttribute('style')
		document.documentElement.removeAttribute('data-thematiq-pointer')
		delete global.t
		vi.restoreAllMocks()
	})

	it('sets the boards\' words as translated, quoted variables', async () => {
		document.body.innerHTML = '<div id="content" class="app-mail"></div>'
		await loadScript()

		const style = document.documentElement.style
		expect(style.getPropertyValue('--thematiq-mail-empty-title')).toBe('"Select a conversation"')
		expect(style.getPropertyValue('--thematiq-calendar-new-event')).toBe('"Nieuwe afspraak"')
	})

	it('names a week day "Mon 23" from the column\'s own date, also after a re-render', async () => {
		document.body.innerHTML = WEEK
		await loadScript()

		const links = () => Array.from(document.querySelectorAll('.fc-col-header-cell-cushion')).map((a) => a.textContent)
		expect(links()).toEqual(['Mon 23', 'Sun 1'])

		document.querySelector('.fc-col-header-cell-cushion').textContent = 'Mon 23/11/2026'
		await frame()
		expect(links()).toEqual(['Mon 23', 'Sun 1'])
	})

	it('leaves the month view\'s weekday heads alone', async () => {
		document.body.innerHTML = WEEK.replace('fc-timeGridWeek-view', 'fc-dayGridMonth-view')
		await loadScript()

		expect(document.querySelector('.fc-col-header-cell-cushion').textContent).toBe('Mon 23/11/2026')
	})

	it('marks pointer use and clears the mark on a key press', async () => {
		document.body.innerHTML = '<div id="content" class="app-deck"><button>x</button></div>'
		await loadScript()

		document.querySelector('button').dispatchEvent(new window.Event('pointerdown', { bubbles: true }))
		expect(document.documentElement.hasAttribute('data-thematiq-pointer')).toBe(true)
		document.dispatchEvent(new window.KeyboardEvent('keydown', { key: 'Tab', bubbles: true }))
		expect(document.documentElement.hasAttribute('data-thematiq-pointer')).toBe(false)
	})

	it('does nothing without the app layer sheet', async () => {
		sheetLoaded = false
		document.body.innerHTML = WEEK
		await loadScript()

		expect(document.documentElement.getAttribute('style')).toBeNull()
		expect(document.querySelector('.fc-col-header-cell-cushion').textContent).toBe('Mon 23/11/2026')
	})
})

describe('the app sheets show the words only once the script set them', () => {
	const selectors = (rel) => {
		const out = []
		postcss.parse(read(rel)).walkDecls('content', (decl) => {
			out.push([decl.parent.selector.replace(/\s+/g, ' '), decl.value])
		})
		return out
	}

	it('Mail and Calendar read their variable under a guard on the root', () => {
		const mail = selectors('css/workplace-apps/mail.css').find(([, value]) => value.includes('--thematiq-mail-empty-title'))
		const calendar = selectors('css/workplace-apps/calendar.css').find(([, value]) => value.includes('--thematiq-calendar-new-event'))
		expect(mail[0]).toContain(":root[style*='--thematiq-mail-empty-title']")
		expect(calendar[0]).toContain(":root[style*='--thematiq-calendar-new-event']")
	})

	it('common.css carries the mark the script waits for', () => {
		expect(read('css/workplace-apps/common.css')).toMatch(/--thematiq-workplace-apps: 1;/)
	})
})
