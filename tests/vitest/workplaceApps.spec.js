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
		const numbers = () => Array.from(document.querySelectorAll('.fc-col-header-cell-cushion > .thematiq-day-number')).map((s) => s.textContent)
		expect(numbers(), 'the day of the month sits in a span of its own').toEqual(['23', '1'])

		document.querySelector('.fc-col-header-cell-cushion').textContent = 'Mon 23/11/2026'
		await frame()
		expect(links()).toEqual(['Mon 23', 'Sun 1'])
		expect(numbers()).toEqual(['23', '1'])
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

	it('gives Deck\'s cards their number, a short due date and the board its open cards, also after a change', async () => {
		document.documentElement.setAttribute('data-locale', 'en_GB')
		global.n = (app, one, many, count) => (count === 1 ? one : many).replace('%n', count)
		document.body.innerHTML = '<div id="content" class="app-deck">'
			+ '<div class="controls"><div class="board-title"><h2>Service desk rollout</h2></div></div>'
			+ '<div class="stack"><div class="dnd-container">'
			+ '<div class="smooth-dnd-draggable-wrapper"><div class="card">'
			+ '<div class="card-labels"><ul class="labels"><li><span>Task</span></li></ul></div>'
			+ '<div class="badges"><div class="duedate"><div class="due" name="Friday, 16 October 2026 17:00"><span class="due--label">in 3 days</span></div></div>'
			+ '<div class="inline-badges"><div class="cardid icon-badge"><span>#7</span></div></div></div>'
			+ '</div></div>'
			+ '<div class="smooth-dnd-draggable-wrapper"><div class="card"><div class="badges">'
			+ '<div class="duedate"><div class="due" name="next week"><span class="due--label">in 7 days</span></div></div>'
			+ '</div></div></div>'
			+ '</div></div>'
			+ '<div class="stack stack--done-column"><div class="dnd-container"><div class="smooth-dnd-draggable-wrapper"><div class="card"></div></div></div></div>'
			+ '</div>'
		await loadScript()

		const labels = document.querySelector('.labels')
		const dues = document.querySelectorAll('.due')
		expect(labels.getAttribute('data-thematiq-number')).toBe('#7')
		expect(dues[0].getAttribute('data-thematiq-due')).toBe('16 Oct')
		expect(dues[1].hasAttribute('data-thematiq-due'), 'a date it cannot read keeps Deck\'s own text').toBe(false)
		expect(document.querySelector('.board-title').getAttribute('data-thematiq-open'), 'the done column does not count').toBe('2 open cards')

		dues[0].setAttribute('name', 'Monday, 2 November 2026 09:00')
		await frame()
		expect(dues[0].getAttribute('data-thematiq-due')).toBe('2 Nov')

		document.documentElement.removeAttribute('data-locale')
		delete global.n
	})

	it('draws a card\'s mail and project lines from Deck\'s API and the priority label as a mark', async () => {
		window.history.replaceState({}, '', '/apps/deck/board/2')
		global.t = (app, text, vars) => text.replace(/{(\w+)}/g, (whole, key) => (vars && key in vars ? vars[key] : whole))
		global.fetch = vi.fn(() => Promise.resolve({
			ok: true,
			json: () => Promise.resolve({ ocs: { data: [{ cards: [{ id: 7, description: 'From mail: Hanneke Visser\nProject: Website\n\nText', assignedUsers: [{ participant: { uid: 'sanne', displayname: 'Sanne de Vries' } }] }] }] } }),
		}))
		document.body.innerHTML = '<div id="content" class="app-deck"><div class="stack"><div class="dnd-container"><div class="smooth-dnd-draggable-wrapper"><div class="card">'
			+ '<div class="card-upper"><h4><span>Prepare the demo</span></h4></div>'
			+ '<div class="card-labels"><ul class="labels"><li><span>Task</span></li><li><span>Priority high</span></li></ul></div>'
			+ '<div class="card-controls"><div class="badges"><div class="inline-badges"><div class="cardid icon-badge"><span>#7</span></div></div>'
			+ '<div class="avatars"><div class="avatardiv"><img src="/avatar/sanne/64"></div></div></div></div>'
			+ '</div></div></div></div></div>'
		await loadScript()
		await frame()

		expect(document.querySelector('.avatardiv').getAttribute('data-thematiq-initials'), 'first and last word').toBe('SV')

		expect(global.fetch.mock.calls[0][0]).toContain('/ocs/v2.php/apps/deck/api/v1.0/stacks/2')
		expect(document.querySelector('.card-controls').getAttribute('data-thematiq-mail')).toBe('From mail: Hanneke Visser')
		expect(document.querySelector('.card-upper').getAttribute('data-thematiq-project')).toBe('Website')
		const labels = document.querySelectorAll('.labels li')
		expect(labels[0].hasAttribute('data-thematiq-priority')).toBe(false)
		expect(labels[1].getAttribute('data-thematiq-priority')).toBe('high')
		expect(document.querySelector('.card-labels').getAttribute('data-thematiq-priority')).toBe('Priority high')

		delete global.fetch
		window.history.replaceState({}, '', '/')
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

/**
 * @spec openspec/specs/workplace-layout/spec.md#scenario-the-sheets-use-tokens-only
 */
describe('every rule of the app sheets starts at an allowed root', () => {
	const SHEETS = fs.readdirSync(path.join(ROOT, 'css/workplace-apps')).filter((name) => name.endsWith('.css'))
	const ROOTS = [':is(#content, #content-vue)', 'body:not(#body-login) #header', '#openregister-mail-sidebar', '.event-popover']
	const CONTENT = ':is(#content, #content-vue)'

	// Split a selector list on its top-level commas only, so ":is(a, b)" stays whole.
	const splitList = (selector) => {
		const parts = []
		let depth = 0
		let current = ''
		for (const char of selector) {
			if (char === '(' || char === '[') depth++
			if (char === ')' || char === ']') depth--
			if (char === ',' && depth === 0) {
				parts.push(current.trim())
				current = ''
				continue
			}
			current += char
		}
		parts.push(current.trim())
		return parts.map((part) => part.replace(/\s+/g, ' '))
	}

	// A leading ":root[...]" compound only guards on the root's own state; the rule still starts after it.
	const withoutRootGuard = (selector) => selector.replace(/^:root(\[[^\]]*\])+ /, '')

	// The outermost rules: nested rules and keyframe steps hang below them.
	const topRules = (rel) => {
		const rules = []
		postcss.parse(read(rel)).walkRules((rule) => {
			let parent = rule.parent
			while (parent && parent.type === 'atrule') {
				if (/keyframes$/.test(parent.name)) {
					return
				}
				parent = parent.parent
			}
			if (parent && parent.type === 'rule') {
				return
			}
			rules.push(rule)
		})
		return rules
	}

	const hasNestedHas = (rule) => {
		let found = false
		rule.walkRules((inner) => {
			if (inner.selector.includes(':has(')) {
				found = true
			}
		})
		return found
	}

	it('finds the five sheets', () => {
		expect(SHEETS.sort()).toEqual(['brand.css', 'calendar.css', 'common.css', 'deck.css', 'mail.css'])
	})

	it.each(SHEETS)('%s: every selector starts at the content element, the header, a mounted panel or the event popover', (sheet) => {
		const strays = []
		for (const rule of topRules('css/workplace-apps/' + sheet)) {
			// The bare root only carries the mark and the custom properties the script and sheets read.
			if (rule.selector.trim() === ':root') {
				rule.each((node) => {
					if (node.type === 'decl' && !node.prop.startsWith('--')) {
						strays.push(':root { ' + node.prop + ' }')
					}
				})
				continue
			}
			for (const selector of splitList(rule.selector)) {
				const start = withoutRootGuard(selector)
				if (!ROOTS.some((root) => start.startsWith(root))) {
					strays.push(selector)
				}
			}
		}
		expect(strays).toEqual([])
	})

	it.each(SHEETS)('%s: :has( is used only under the content element', (sheet) => {
		const strays = []
		for (const rule of topRules('css/workplace-apps/' + sheet)) {
			const nested = hasNestedHas(rule)
			for (const selector of splitList(rule.selector)) {
				if ((selector.includes(':has(') || nested) && !withoutRootGuard(selector).startsWith(CONTENT)) {
					strays.push(selector)
				}
			}
		}
		expect(strays).toEqual([])
	})

	it('the checks catch a stray root and a stray :has(', () => {
		const sheet = postcss.parse('.app-mail .x { color: var(--a); } #header:has(.y) { color: var(--a); }')
		const starts = sheet.nodes.map((rule) => withoutRootGuard(rule.selector))
		expect(starts.filter((start) => !ROOTS.some((root) => start.startsWith(root)))).toEqual(['.app-mail .x', '#header:has(.y)'])
		expect(splitList(':is(#content, #content-vue).app-mail a, .b')).toEqual([':is(#content, #content-vue).app-mail a', '.b'])
		expect(withoutRootGuard(":root[style*='--v'] :is(#content, #content-vue) a")).toBe(':is(#content, #content-vue) a')
	})
})

/**
 * @spec openspec/changes/workplace-app-sheets-entry-states/specs/workplace-app-sheets/spec.md#requirement-calendar-fades-entries-that-have-passed-req-tqas-101
 * @spec openspec/changes/workplace-app-sheets-entry-states/specs/workplace-app-sheets/spec.md#requirement-calendar-draws-an-entry-not-answered-yet-with-a-dashed-frame-req-tqas-102
 * @spec openspec/changes/workplace-app-sheets-entry-states/specs/workplace-app-sheets/spec.md#requirement-deck-lifts-a-card-mid-move-over-a-dashed-slot-req-tqas-103
 */
describe('the entry states of Calendar and the card mid-move of Deck', () => {
	const CONTENT = ':is(#content, #content-vue)'
	const COLOUR_LITERAL = /#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i

	/**
	 * The rules of a sheet whose selector names the given class, with the at-rule they sit in.
	 *
	 * @param {string} rel The sheet.
	 * @param {string} name The class, without its dot.
	 * @return {Array<{selector: string, decls: Record<string, string>, media: string|null}>} The rules.
	 */
	const rulesNaming = (rel, name) => {
		const out = []
		postcss.parse(read(rel)).walkRules((rule) => {
			if (!rule.selector.includes('.' + name)) {
				return
			}
			const decls = {}
			rule.walkDecls((decl) => {
				decls[decl.prop] = decl.value + (decl.important ? ' !important' : '')
			})
			out.push({
				selector: rule.selector.replace(/\s+/g, ' '),
				decls,
				media: rule.parent.type === 'atrule' ? rule.parent.params : null,
			})
		})
		return out
	}

	const startsAtContent = (rules) => rules.every((rule) => rule.selector.split(/,(?![^(]*\))/).every((part) => part.trim().startsWith(CONTENT)))
	const writesNoColour = (rules) => rules.every((rule) => Object.values(rule.decls).every((value) => !COLOUR_LITERAL.test(value)))

	// The WCAG contrast of two #RRGGBB colours, and a colour mixed over white as `color-mix(in srgb)` does.
	const channels = (hex) => [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16))
	const luminance = (rgb) => {
		const [r, g, b] = rgb.map((c) => c / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4))
		return 0.2126 * r + 0.7152 * g + 0.0722 * b
	}
	const contrast = (a, b) => {
		const [hi, lo] = [luminance(a), luminance(b)].sort((x, y) => y - x)
		return (hi + 0.05) / (lo + 0.05)
	}
	const mixOverWhite = (hex, share) => channels(hex).map((c) => Math.round(c * share + 255 * (1 - share)))

	it('records the classes the apps put on the states', () => {
		const calendar = read('css/workplace-apps/calendar.css')
		for (const name of ['fc-event-past', 'fc-event-nc-tentative', 'fc-event-nc-needs-action', '--nc-event-color']) {
			expect(calendar).toContain('`' + name + '`')
		}
		expect(read('css/workplace-apps/deck.css')).toContain('`.smooth-dnd-ghost`')
	})

	it('fades a past entry in the week and the month view, from tokens and the content element', () => {
		const rules = rulesNaming('css/workplace-apps/calendar.css', 'fc-event-past')
		const selectors = rules.map((rule) => rule.selector).join(' ')
		expect(selectors).toContain('.fc-timegrid-event')
		expect(selectors).toContain('.fc-daygrid-dot-event')
		expect(rules.some((rule) => /6%/.test(rule.decls.background || ''))).toBe(true)
		expect(rules.some((rule) => rule.decls.color === 'var(--color-text-maxcontrast) !important')).toBe(true)
		expect(rules.every((rule) => rule.decls.opacity === undefined || rule.selector.includes('.fc-daygrid-event-dot'))).toBe(true)
		expect(startsAtContent(rules)).toBe(true)
		expect(writesNoColour(rules)).toBe(true)
	})

	it('keeps a faded entry\'s text at 4.5:1 under Zuiddrecht, whatever the calendar\'s colour', () => {
		const muted = channels('#5E6168')
		for (const colour of ['#3669A5', '#000000', '#0082C9', '#E9322D', '#1B1C1D', '#A30000']) {
			expect(contrast(muted, mixOverWhite(colour, 0.06))).toBeGreaterThanOrEqual(4.5)
		}
	})

	it('draws an entry not answered yet, or tentative, with a dashed frame and a dashed 3px edge on white', () => {
		const tentative = rulesNaming('css/workplace-apps/calendar.css', 'fc-event-nc-tentative')
		const unanswered = rulesNaming('css/workplace-apps/calendar.css', 'fc-event-nc-needs-action')
		expect(tentative.map((rule) => rule.selector)).toEqual(unanswered.map((rule) => rule.selector))
		const block = tentative.find((rule) => rule.selector.includes('.fc-timegrid-event'))
		expect(block.decls.border).toMatch(/^1px dashed var\(--nc-event-color/)
		expect(block.decls['border-inline-start-width']).toBe('3px !important')
		expect(block.decls.background).toBe('var(--color-main-background) !important')
		const dot = tentative.find((rule) => / \.fc-daygrid-event-dot$/.test(rule.selector))
		expect(dot.decls['border-style']).toBe('dashed !important')
		expect(dot.decls['background-image']).toBe('none !important')
		expect(startsAtContent(tentative)).toBe(true)
		expect(writesNoColour(tentative)).toBe(true)
	})

	it('lifts the dragged card from Deck\'s content element, the tilt only without reduced motion', () => {
		const rules = rulesNaming('css/workplace-apps/deck.css', 'smooth-dnd-ghost')
		const lift = rules.find((rule) => rule.media === null)
		const tilt = rules.find((rule) => rule.decls.transform !== undefined)
		expect(lift.decls['box-shadow']).toContain('var(')
		expect(lift.decls.transform).toBeUndefined()
		expect(tilt.media).toBe('(prefers-reduced-motion: no-preference)')
		expect(tilt.decls.transform).toMatch(/^rotate\(/)
		expect(startsAtContent(rules)).toBe(true)
		expect(writesNoColour(rules)).toBe(true)
	})
})
