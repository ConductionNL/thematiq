/**
 * @vitest-environment jsdom
 *
 * The apps menu drawn from the menu settings: js/app-menu.js takes the
 * columns, the rows above the fold, the shape, the tiles, the icon size, the
 * second line, the hidden entries and the two switches from the effective
 * settings, and shows the gear "Menu settings" when the dialog is loaded.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/apps-menu-settings/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

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

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'app-menu' ? state : fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	await import('../../js/app-menu.js?t=' + Math.random())
	for (let i = 0; i < 5; i++) {
		await Promise.resolve()
	}
}

function openMenu() {
	document.body.innerHTML =
		'<button class="app-menu__waffle" aria-expanded="true"></button>'
		+ '<div class="app-menu__popover-base"><div class="app-menu__popover"><div class="app-menu__grid">'
		+ APPS.map(
			(id) =>
				`<a class="app-item" role="menuitem" tabindex="-1" href="/index.php/apps/${id}/">`
				+ `<span class="app-item__circle"></span><span class="app-item__label">${id}</span></a>`,
		).join('')
		+ '<a class="app-item app-item--outlined" href="/index.php/settings/apps"><span class="app-item__label">More apps</span></a>'
		+ '</div></div></div>'
	return document.querySelector('.app-menu__grid')
}

function onScreen(grid) {
	return Array.from(grid.querySelectorAll(':scope > a.app-item'))
		.filter(
			(a) =>
				!a.hasAttribute('data-thematiq-folded')
				&& !a.hasAttribute('data-thematiq-outlined'),
		)
		.map(
			(a) =>
				a.getAttribute('data-thematiq-external')
				|| /\/apps\/([a-z0-9_]+)/.exec(a.getAttribute('href'))[1],
		)
}

function menu(settings = {}, extra = {}) {
	return {
		groups: [
			{ title: 'Werk', apps: [{ id: 'dossiq', description: 'Zaken en Woo' }] },
		],
		manageLabel: 'Apps beheren',
		labels: {
			allApps: 'Alle apps',
			hideApps: 'Apps verbergen',
			menuSettings: 'Menu-instellingen',
			dragHint: 'Sleep',
		},
		order: [],
		settings,
		external: {
			title: 'Externe apps',
			note: 'opent in een nieuw tabblad',
			apps: [
				{
					key: 'external-code',
					label: 'Forgejo',
					line: 'Code',
					url: 'https://code.example.org',
					icon: 'M0 0',
				},
			],
		},
		...extra,
	}
}

describe('the apps menu drawn from the settings', () => {
	beforeEach(() => {
		global.OC = { generateUrl: (url) => url, requestToken: 'token' }
		global.fetch = vi.fn(() => Promise.resolve({ ok: true }))
	})

	afterEach(() => {
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		delete window.ThematiqMenuSettingsDialog
		document.body.innerHTML = ''
		delete global.OCP
		delete global.OC
		delete global.fetch
	})

	it('shows four columns of two rows by default, round, large, in the brand colour and without tiles', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		expect(onScreen(grid)).toHaveLength(8)
		expect(grid.style.getPropertyValue('--thematiq-app-menu-columns')).toBe('4')
		expect(grid.style.getPropertyValue('--thematiq-app-menu-rows')).toBe('6')
		expect(grid.getAttribute('data-thematiq-shape')).toBe('round')
		expect(grid.getAttribute('data-thematiq-icon-size')).toBe('large')
		expect(grid.getAttribute('data-thematiq-icon-colour')).toBe('brand')
		expect(grid.hasAttribute('data-thematiq-tiles')).toBe(false)
	})

	it('folds after columns times rows, and takes the shape, the tiles and the icon size', async () => {
		const grid = openMenu()
		installState(
			menu({
				columns: 3,
				rowsAboveFold: 3,
				shape: 'square',
				tiles: true,
				iconSize: 'small',
				iconColour: 'neutral',
			}),
		)
		await loadScript()

		expect(onScreen(grid)).toHaveLength(9)
		expect(grid.style.getPropertyValue('--thematiq-app-menu-columns')).toBe('3')
		expect(grid.getAttribute('data-thematiq-shape')).toBe('square')
		expect(grid.getAttribute('data-thematiq-icon-size')).toBe('small')
		expect(grid.getAttribute('data-thematiq-icon-colour')).toBe('neutral')
		expect(grid.hasAttribute('data-thematiq-tiles')).toBe(true)
	})

	it('keeps out a value it cannot use', async () => {
		const grid = openMenu()
		installState(menu({ columns: 40, rowsAboveFold: 'many', shape: 7 }))
		await loadScript()

		expect(grid.style.getPropertyValue('--thematiq-app-menu-columns')).toBe('5')
		expect(onScreen(grid)).toHaveLength(10)
		expect(grid.getAttribute('data-thematiq-shape')).toBe('round')
	})

	it('leaves out hidden entries, the second line, and the external apps when switched off', async () => {
		const grid = openMenu()
		installState(
			menu({
				hidden: ['dossiq', 'files'],
				payoff: false,
				showExternalApps: false,
			}),
		)
		await loadScript()
		grid.querySelector('[data-thematiq-row="toggle"] button').click()

		expect(onScreen(grid)).not.toContain('dossiq')
		expect(onScreen(grid)).not.toContain('files')
		expect(onScreen(grid)).not.toContain('external-code')
		expect(grid.querySelector('.thematiq-app-menu__description')).toBeNull()
	})

	it('shows the external apps and the second line by default', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()
		grid.querySelector('[data-thematiq-row="toggle"] button').click()

		expect(onScreen(grid)).toContain('external-code')
		expect(
			grid.querySelector(
				'a[href*="/apps/dossiq/"] .thematiq-app-menu__description',
			).textContent,
		).toBe('Zaken en Woo')
	})

	it('puts the gear in the footer when the settings dialog is loaded, and opens it', async () => {
		const open = vi.fn()
		window.ThematiqMenuSettingsDialog = { open }
		const grid = openMenu()
		installState(menu({ columns: 3 }))
		await loadScript()

		const footer = grid.querySelector('[data-thematiq-row="footer"]')
		const gear = footer.querySelector('button.thematiq-app-menu__settings')
		expect(gear.textContent).toBe('Menu-instellingen')
		expect(gear.getAttribute('aria-haspopup')).toBe('dialog')
		expect(footer.querySelector('a')).toBeNull()
		gear.click()
		expect(open).toHaveBeenCalledTimes(1)
		expect(open.mock.calls[0][0].opener).toBe(gear)
		const entries = open.mock.calls[0][0].entries
		expect(entries[0]).toMatchObject({
			key: 'dossiq',
			name: 'dossiq',
			description: 'Zaken en Woo',
			external: false,
		})
		expect(entries.some((entry) => entry.external)).toBe(true)
	})

	it('keeps the Manage apps link until the dialog is loaded', async () => {
		const grid = openMenu()
		installState(menu())
		await loadScript()

		const footer = grid.querySelector('[data-thematiq-row="footer"]')
		expect(footer.querySelector('button')).toBeNull()
		expect(footer.querySelector('a').textContent).toBe('Apps beheren')
	})

	it('marks the first section as work apps, and draws filled or line icons for external apps', async () => {
		const grid = openMenu()
		installState(
			menu(
				{},
				{
					external: {
						title: 'Externe apps',
						note: 'opent in een nieuw tabblad',
						apps: [
							{
								key: 'external-code',
								label: 'Forgejo',
								line: 'Code',
								url: 'https://code.example.org',
								icon: 'M0 0',
								stroke: true,
							},
							{
								key: 'external-wiki',
								label: 'XWiki',
								line: 'Wiki',
								url: 'http://localhost:8078',
								icon: 'M1 1',
							},
						],
					},
				},
			),
		)
		await loadScript()

		expect(
			grid
				.querySelector('a[href*="/apps/dossiq/"]')
				.hasAttribute('data-thematiq-work'),
		).toBe(true)
		expect(
			grid
				.querySelector('a[href*="/apps/files/"]')
				.hasAttribute('data-thematiq-work'),
		).toBe(false)
		const code = grid.querySelector(
			'a[data-thematiq-external="external-code"] svg',
		)
		const wiki = grid.querySelector(
			'a[data-thematiq-external="external-wiki"] svg',
		)
		expect(code.getAttribute('fill')).toBe('none')
		expect(code.getAttribute('stroke')).toBe('currentColor')
		expect(wiki.getAttribute('fill')).toBe('currentColor')
		expect(wiki.hasAttribute('stroke')).toBe(false)
	})
})
