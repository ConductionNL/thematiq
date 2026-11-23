/**
 * @vitest-environment jsdom
 *
 * The workplace apps menu as one ordered list: js/app-menu.js lays Nextcloud's
 * open app grid out by the person's order, shows the first nine, folds the
 * rest behind "All apps", lists the external apps under their own heading,
 * and lets a person reorder by dragging or with Alt and an arrow key.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-ordered-list/specs/workplace-layout/spec.md
 * @spec openspec/changes/apps-menu-ordered-list/specs/apps-menu-order/spec.md
 * @spec openspec/changes/apps-menu-ordered-list/specs/external-apps-in-the-app-menu/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'app-menu' && state !== undefined
					? state
					: fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	await import('../../js/app-menu.js?t=' + Math.random())
	await tick()
}

async function tick() {
	for (let i = 0; i < 5; i++) {
		await Promise.resolve()
	}
}

function tile(id, { active = false, outlined = false, href } = {}) {
	return (
		`<a class="app-item${active ? ' app-item--active' : ''}${outlined ? ' app-item--outlined' : ''}" role="menuitem" tabindex="-1" href="${href || '/index.php/apps/' + id + '/'}">`
		+ `<span class="app-item__circle"><img class="app-item__icon" src="/apps/${id}/img/app.svg"></span>`
		+ `<span class="app-item__label">${id}</span></a>`
	)
}

const APPS = [
	'files',
	'dossiq',
	'calendar',
	'pipelinq',
	'mail',
	'contacts',
	'deck',
	'photos',
	'activity',
	'talk',
	'notes',
	'tasks',
]

function openMenu(apps = APPS) {
	document.body.innerHTML =
		'<button class="app-menu__waffle" aria-expanded="true"></button>'
		+ '<div class="app-menu__popover-base"><div class="app-menu__popover"><div class="app-menu__grid">'
		+ apps.map((id) => tile(id)).join('')
		+ tile('more-apps', { outlined: true, href: '/index.php/settings/apps' })
		+ '</div></div></div>'
	return document.querySelector('.app-menu__grid')
}

const LABELS = {
	allApps: 'Alle apps',
	hideApps: 'Apps verbergen',
	dragHint: 'Sleep een app om de volgorde te wijzigen.',
	moveHint: 'Alt met een pijltjestoets verplaatst de app.',
	moved: '{app} staat nu op plaats {place} van {total}.',
	saveFailed: 'De volgorde kon niet worden opgeslagen.',
}

function menu(extra = {}) {
	return {
		groups: [
			{
				title: 'Werk',
				apps: [
					{ id: 'dossiq', description: 'Zaken en Woo' },
					{ id: 'pipelinq', description: 'Klantcontact' },
				],
			},
		],
		hint: 'Pijltjestoetsen bladeren, Enter opent, Escape sluit.',
		manageLabel: 'Apps beheren',
		labels: LABELS,
		order: [],
		...extra,
	}
}

const EXTERNAL = {
	title: 'Externe apps',
	note: 'opent in een nieuw tabblad',
	apps: [
		{
			key: 'external-code',
			label: 'Forgejo',
			line: 'Code',
			url: 'https://code.example.org',
			icon: 'M8 7l-5 5 5 5',
		},
		{
			key: 'external-chat',
			label: 'Element',
			line: 'Chat',
			url: 'javascript:alert(1)',
			icon: 'M0 0',
		},
		{
			key: 'external-wiki',
			label: 'XWiki',
			line: 'Kennisbank',
			url: 'https://wiki.example.org',
			icon: '<script>',
		},
	],
}

/** The visible entries' keys in screen order. */
function onScreen(grid) {
	return Array.from(grid.querySelectorAll(':scope > a.app-item'))
		.filter(
			(a) =>
				!a.hasAttribute('data-thematiq-folded')
				&& !a.hasAttribute('data-thematiq-outlined'),
		)
		.sort((a, b) => Number(a.style.order) - Number(b.style.order))
		.map(
			(a) =>
				a.getAttribute('data-thematiq-external')
				|| /\/apps\/([a-z0-9_]+)/.exec(a.getAttribute('href'))[1],
		)
}

const toggle = (grid) => grid.querySelector('[data-thematiq-row="toggle"] > button')

function key(target, name, extra = {}) {
	const event = new KeyboardEvent('keydown', {
		key: name,
		bubbles: true,
		cancelable: true,
		...extra,
	})
	target.dispatchEvent(event)
	return event
}

describe('the apps menu as one ordered list', () => {
	let posted

	beforeEach(() => {
		vi.useRealTimers()
		posted = []
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn((url, options) => {
			posted.push({ url, body: JSON.parse(options.body) })
			return Promise.resolve({ ok: true })
		})
	})

	afterEach(() => {
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		document.body.innerHTML = ''
		delete global.OCP
		delete global.OC
		delete global.fetch
	})

	it('puts the group apps first, shows nine and folds the rest', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		expect(grid.hasAttribute('data-thematiq-app-menu')).toBe(true)
		expect(onScreen(grid)).toEqual([
			'dossiq',
			'pipelinq',
			'files',
			'calendar',
			'mail',
			'contacts',
			'deck',
			'photos',
			'activity',
		])
		expect(toggle(grid).textContent).toBe('Alle apps')
		expect(toggle(grid).getAttribute('aria-expanded')).toBe('false')
		expect(
			grid
				.querySelector('[data-thematiq-row="line"]')
				.hasAttribute('data-thematiq-folded'),
		).toBe(true)
		expect(grid.querySelectorAll('.thematiq-app-menu__heading')).toHaveLength(0)
	})

	it('unfolds the rest under a line, and folds it again', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		toggle(grid).click()
		expect(onScreen(grid)).toHaveLength(12)
		expect(toggle(grid).textContent).toBe('Apps verbergen')
		expect(toggle(grid).getAttribute('aria-expanded')).toBe('true')
		const line = grid.querySelector('[data-thematiq-row="line"]')
		expect(line.hasAttribute('data-thematiq-folded')).toBe(false)
		const talk = grid.querySelector('a[href*="/apps/talk/"]')
		expect(Number(line.style.order)).toBeLessThan(Number(talk.style.order))

		toggle(grid).click()
		expect(onScreen(grid)).toHaveLength(9)
	})

	it('draws no toggle with nine entries', async () => {
		const grid = openMenu(APPS.slice(0, 9))
		installState(menu())
		await loadScript()

		expect(onScreen(grid)).toHaveLength(9)
		expect(toggle(grid)).toBeNull()
	})

	it('adds the description, hidden from assistive technology, and hands each icon to its circle', async () => {
		openMenu()
		installState(menu())
		await loadScript()

		const dossiq = document.querySelector('a[href*="/apps/dossiq/"]')
		const description = dossiq.querySelector('.thematiq-app-menu__description')
		expect(description.textContent).toBe('Zaken en Woo')
		expect(description.getAttribute('aria-hidden')).toBe('true')
		expect(
			dossiq
				.querySelector('.app-item__circle')
				.style.getPropertyValue('--thematiq-app-icon'),
		).toContain('/apps/dossiq/img/app.svg')
	})

	it('turns the outlined tile into the footer link, with the hints', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const footer = grid.querySelector('[data-thematiq-row="footer"]')
		expect(footer.textContent).toContain('Pijltjestoetsen bladeren')
		expect(footer.textContent).toContain('Sleep een app')
		expect(footer.querySelector('a').getAttribute('href')).toBe(
			'/index.php/settings/apps',
		)
		expect(footer.querySelector('a').textContent).toBe('Apps beheren')
		expect(grid.hasAttribute('data-thematiq-app-menu-manage')).toBe(true)
	})

	it('settles after its own changes instead of watching them for ever', async () => {
		const grid = openMenu()
		installState(menu({ external: EXTERNAL }))
		await loadScript()
		let seen = 0
		const counter = new MutationObserver((records) => {
			seen += records.length
		})
		counter.observe(grid, { childList: true, subtree: true })
		grid.appendChild(document.createElement('span'))
		await new Promise((resolve) => setTimeout(resolve, 20))
		counter.disconnect()
		expect(seen).toBeLessThan(10)
	})

	it('is idempotent when the observer runs again', async () => {
		const grid = openMenu()
		installState(menu({ external: EXTERNAL }))
		await loadScript()
		grid.appendChild(document.createElement('span'))
		await tick()

		expect(grid.querySelectorAll('[data-thematiq-row="footer"]')).toHaveLength(1)
		expect(grid.querySelectorAll('[data-thematiq-row="toggle"]')).toHaveLength(1)
		expect(grid.querySelectorAll('a[data-thematiq-external]')).toHaveLength(2)
	})

	it('does nothing without its initial state', async () => {
		const grid = openMenu()
		installState(undefined)
		await loadScript()

		expect(grid.hasAttribute('data-thematiq-app-menu')).toBe(false)
	})

	it('follows the stored order, and an app the order does not name follows the named ones', async () => {
		const grid = openMenu()
		installState(menu({ order: ['talk', 'files', 'gone', 'dossiq'] }))
		await loadScript()

		expect(onScreen(grid).slice(0, 4)).toEqual([
			'talk',
			'files',
			'dossiq',
			'pipelinq',
		])
	})

	it('lists the external apps under their own heading, in a new tab, and drops what may not be linked', async () => {
		const grid = openMenu()
		installState(menu({ external: EXTERNAL }))
		await loadScript()

		const links = grid.querySelectorAll('a[data-thematiq-external]')
		expect(Array.from(links).map((a) => a.getAttribute('href'))).toEqual([
			'https://code.example.org',
			'https://wiki.example.org',
		])
		expect(links[0].target).toBe('_blank')
		expect(links[0].rel).toBe('noopener noreferrer')
		expect(links[0].querySelector('.app-item__label').textContent).toBe(
			'Forgejo',
		)
		expect(
			links[0].querySelector('.thematiq-app-menu__description').textContent,
		).toBe('Code')
		expect(links[0].textContent).toContain('opent in een nieuw tabblad')
		expect(links[0].querySelector('path').getAttribute('d')).toBe(
			'M8 7l-5 5 5 5',
		)
		expect(links[1].querySelector('path')).toBeNull()

		const heading = grid.querySelector('[data-thematiq-row="external"]')
		expect(heading.querySelector('h2').textContent).toBe('Externe apps')
		expect(heading.hasAttribute('data-thematiq-folded')).toBe(true)
		toggle(grid).click()
		expect(heading.hasAttribute('data-thematiq-folded')).toBe(false)
		const lastApp = grid.querySelector('a[href*="/apps/tasks/"]')
		expect(Number(lastApp.style.order)).toBeLessThan(Number(heading.style.order))
		expect(Number(heading.style.order)).toBeLessThan(
			Number(links[0].style.order),
		)
		expect(onScreen(grid).slice(-2)).toEqual(['external-code', 'external-wiki'])
	})

	it('keeps an external app the person moved into the nine there, without the heading', async () => {
		const grid = openMenu(APPS.slice(0, 4))
		installState(menu({ external: EXTERNAL, order: ['external-code'] }))
		await loadScript()

		expect(onScreen(grid)[0]).toBe('external-code')
		expect(
			grid
				.querySelector('[data-thematiq-row="external"]')
				?.hasAttribute('data-thematiq-folded') ?? true,
		).toBe(true)
	})

	it('moves an app with Alt and an arrow key, keeps focus, says where, and saves', async () => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const pipelinq = grid.querySelector('a[href*="/apps/pipelinq/"]')
		pipelinq.focus()
		const event = key(pipelinq, 'ArrowUp', { altKey: true })
		expect(event.defaultPrevented).toBe(true)
		expect(onScreen(grid).slice(0, 2)).toEqual(['pipelinq', 'dossiq'])
		expect(document.activeElement).toBe(pipelinq)
		expect(pipelinq.getAttribute('tabindex')).toBe('0')
		expect(grid.querySelector('[data-thematiq-row="live"]').textContent).toBe(
			'pipelinq staat nu op plaats 1 van 12.',
		)

		key(pipelinq, 'ArrowDown', { altKey: true })
		key(pipelinq, 'ArrowRight', { altKey: true })
		expect(onScreen(grid).slice(0, 3)).toEqual(['dossiq', 'files', 'pipelinq'])

		vi.advanceTimersByTime(500)
		expect(posted).toHaveLength(1)
		expect(posted[0].url).toBe('/apps/thematiq/app-menu/order')
		expect(posted[0].body.order.slice(0, 3)).toEqual([
			'dossiq',
			'files',
			'pipelinq',
		])
		vi.useRealTimers()
	})

	it('moving the ninth app later folds it away with the rest', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const activity = grid.querySelector('a[href*="/apps/activity/"]')
		activity.focus()
		key(activity, 'ArrowDown', { altKey: true })
		expect(activity.hasAttribute('data-thematiq-folded')).toBe(true)
		expect(onScreen(grid)).toContain('talk')
	})

	it('moves focus in screen order and skips the folded rest', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const dossiq = grid.querySelector('a[href*="/apps/dossiq/"]')
		dossiq.focus()
		const event = key(dossiq, 'ArrowRight')
		expect(event.defaultPrevented).toBe(true)
		expect(document.activeElement).toBe(
			grid.querySelector('a[href*="/apps/pipelinq/"]'),
		)
		key(document.activeElement, 'End')
		expect(document.activeElement).toBe(toggle(grid))
		key(document.activeElement, 'ArrowLeft')
		expect(document.activeElement).toBe(
			grid.querySelector('a[href*="/apps/activity/"]'),
		)
		key(document.activeElement, 'Home')
		expect(document.activeElement).toBe(dossiq)
	})

	it('keeps the grid keys from Nextcloud while it handles them', async () => {
		const grid = openMenu()
		installState(menu())
		const nextcloud = vi.fn()
		grid.addEventListener('keydown', nextcloud)
		await loadScript()

		const dossiq = grid.querySelector('a[href*="/apps/dossiq/"]')
		dossiq.focus()
		key(dossiq, 'ArrowRight')
		key(document.activeElement, 'Tab')
		expect(nextcloud).toHaveBeenCalledTimes(1)
	})

	it('opens the focused entry with Enter', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const dossiq = grid.querySelector('a[href*="/apps/dossiq/"]')
		const clicked = vi.fn((event) => event.preventDefault())
		dossiq.addEventListener('click', clicked)
		dossiq.focus()
		key(dossiq, 'Enter')
		expect(clicked).toHaveBeenCalledTimes(1)
	})

	it('reorders by dragging, and a drag does not open the app', async () => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const calendar = grid.querySelector('a[href*="/apps/calendar/"]')
		const dossiq = grid.querySelector('a[href*="/apps/dossiq/"]')
		dossiq.getBoundingClientRect = () => ({
			left: 0,
			width: 100,
			top: 0,
			height: 60,
		})
		document.elementsFromPoint = () => [
			dossiq.querySelector('.app-item__label'),
			dossiq,
		]

		calendar.dispatchEvent(
			new MouseEvent('pointerdown', {
				bubbles: true,
				button: 0,
				clientX: 300,
				clientY: 10,
			}),
		)
		document.dispatchEvent(
			new MouseEvent('pointermove', {
				bubbles: true,
				clientX: 302,
				clientY: 11,
			}),
		)
		expect(
			calendar.hasAttribute('data-thematiq-dragging'),
			'under 6 px is no drag',
		).toBe(false)
		document.dispatchEvent(
			new MouseEvent('pointermove', {
				bubbles: true,
				clientX: 10,
				clientY: 10,
			}),
		)
		expect(calendar.hasAttribute('data-thematiq-dragging')).toBe(true)
		expect(onScreen(grid)[0]).toBe('calendar')
		document.dispatchEvent(new MouseEvent('pointerup', { bubbles: true }))
		expect(calendar.hasAttribute('data-thematiq-dragging')).toBe(false)

		const opened = vi.fn()
		calendar.addEventListener('click', opened)
		const click = new MouseEvent('click', { bubbles: true, cancelable: true })
		calendar.dispatchEvent(click)
		expect(click.defaultPrevented).toBe(true)
		expect(opened).not.toHaveBeenCalled()

		vi.advanceTimersByTime(500)
		expect(posted[0].body.order[0]).toBe('calendar')
		delete document.elementsFromPoint
		vi.useRealTimers()
	})

	it('says so when the order could not be saved', async () => {
		vi.useFakeTimers({ toFake: ['setTimeout', 'clearTimeout'] })
		global.fetch = vi.fn(() => Promise.resolve({ ok: false }))
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const pipelinq = grid.querySelector('a[href*="/apps/pipelinq/"]')
		pipelinq.focus()
		key(pipelinq, 'ArrowUp', { altKey: true })
		vi.advanceTimersByTime(500)
		vi.useRealTimers()
		await tick()
		expect(grid.querySelector('[data-thematiq-row="live"]').textContent).toBe(
			'De volgorde kon niet worden opgeslagen.',
		)
	})
})
