/**
 * @vitest-environment jsdom
 *
 * The workplace apps menu (the AppMenu board): js/app-menu.js arranges
 * Nextcloud's open app grid by the token set's sections.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-board/specs/workplace-layout/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'

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
	await import('../../js/app-menu.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function tile(id, { active = false, outlined = false, href } = {}) {
	return (
		`<a class="app-item${active ? ' app-item--active' : ''}${outlined ? ' app-item--outlined' : ''}" role="menuitem" href="${href || '/index.php/apps/' + id + '/'}">`
		+ `<span class="app-item__circle"><img class="app-item__icon" src="/apps/${id}/img/app.svg"></span>`
		+ `<span class="app-item__label">${id}</span></a>`
	)
}

function openMenu() {
	document.body.innerHTML =
		'<div class="app-menu__popover-base"><div class="app-menu__popover"><div class="app-menu__grid">'
		+ tile('files')
		+ tile('dossiq', { active: true })
		+ tile('calendar')
		+ tile('pipelinq')
		+ tile('more-apps', { outlined: true, href: '/index.php/settings/apps' })
		+ '</div></div></div>'
}

const MENU = {
	groups: [
		{
			title: 'Werk',
			link: { label: 'Mijn werkdag in launchpad', url: '/apps/launchpad/' },
			apps: [
				{ id: 'dossiq', description: 'Zaken en Woo' },
				{ id: 'pipelinq', description: 'Klantcontact' },
			],
		},
	],
	othersTitle: 'Nextcloud',
	hint: 'Pijltjestoetsen bladeren, Enter opent, Escape sluit.',
	manageLabel: 'Apps beheren',
}

const order = (el) => Number(el.style.order)

describe('the apps menu of the board', () => {
	afterEach(() => {
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		document.body.innerHTML = ''
		delete global.OCP
	})

	it('puts the section heading, its apps, then the other apps in order', async () => {
		openMenu()
		installState(MENU)
		await loadScript()

		const grid = document.querySelector('.app-menu__grid')
		expect(grid.hasAttribute('data-thematiq-app-menu')).toBe(true)
		const heading = grid.querySelector('[data-thematiq-row="group-0"]')
		const others = grid.querySelector('[data-thematiq-row="others"]')
		const at = (id) => grid.querySelector(`a[href*="/apps/${id}/"]`)
		expect(heading.querySelector('h2').textContent).toBe('Werk')
		expect(heading.querySelector('a').getAttribute('href')).toBe(
			'/apps/launchpad/',
		)
		expect(order(heading)).toBeLessThan(order(at('dossiq')))
		expect(order(at('dossiq'))).toBeLessThan(order(at('pipelinq')))
		expect(order(at('pipelinq'))).toBeLessThan(order(others))
		expect(order(others)).toBeLessThan(order(at('files')))
		expect(order(at('files'))).toBeLessThan(order(at('calendar')))
		expect(at('dossiq').classList.contains('thematiq-app-menu__featured')).toBe(
			true,
		)
		expect(at('files').classList.contains('thematiq-app-menu__featured')).toBe(
			false,
		)
	})

	it('adds the description, hidden from assistive technology, and hands each icon to its circle', async () => {
		openMenu()
		installState(MENU)
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

	it('turns the outlined tile into the footer link', async () => {
		openMenu()
		installState(MENU)
		await loadScript()

		const grid = document.querySelector('.app-menu__grid')
		const footer = grid.querySelector('[data-thematiq-row="footer"]')
		expect(footer.textContent).toContain('Pijltjestoetsen bladeren')
		expect(footer.querySelector('a').getAttribute('href')).toBe(
			'/index.php/settings/apps',
		)
		expect(footer.querySelector('a').textContent).toBe('Apps beheren')
		expect(grid.hasAttribute('data-thematiq-app-menu-manage')).toBe(true)
	})

	it('draws no headings without sections, and is idempotent', async () => {
		openMenu()
		installState({ hint: 'Hint' })
		await loadScript()
		document
			.querySelector('.app-menu__grid')
			.appendChild(document.createElement('span'))
		await new Promise((resolve) => setTimeout(resolve, 0))

		const grid = document.querySelector('.app-menu__grid')
		expect(grid.querySelectorAll('.thematiq-app-menu__heading')).toHaveLength(0)
		expect(grid.querySelectorAll('[data-thematiq-row="footer"]')).toHaveLength(1)
	})

	it('does nothing without its initial state', async () => {
		openMenu()
		installState(undefined)
		await loadScript()

		expect(
			document
				.querySelector('.app-menu__grid')
				.hasAttribute('data-thematiq-app-menu'),
		).toBe(false)
	})

	it('adds OpenProject\'s modules as a section of links, once, and drops what may not be linked', async () => {
		openMenu()
		installState({
			openproject: {
				title: 'OpenProject',
				url: 'https://op.example.org',
				modules: [
					{ key: 'work_packages', label: 'Werkpakketten', url: 'https://op.example.org/work_packages', icon: 'M3 6h18M3 12h18M3 18h18' },
					{ key: 'boards', label: 'Borden', url: 'https://op.example.org/boards', icon: '<script>' },
					{ key: 'bad', label: 'Fout', url: 'javascript:alert(1)', icon: 'M0 0' },
				],
			},
		})
		await loadScript()
		document.querySelector('.app-menu__grid').appendChild(document.createElement('span'))
		await new Promise((resolve) => setTimeout(resolve, 0))

		const grid = document.querySelector('.app-menu__grid')
		const links = grid.querySelectorAll('a[data-thematiq-external]')
		expect(Array.from(links).map((a) => a.getAttribute('href'))).toEqual([
			'https://op.example.org/work_packages',
			'https://op.example.org/boards',
		])
		expect(links[0].textContent).toBe('Werkpakketten')
		expect(links[0].querySelector('path').getAttribute('d')).toBe('M3 6h18M3 12h18M3 18h18')
		expect(links[1].querySelector('path'), 'an icon that is not path data is left out').toBeNull()
		const heading = grid.querySelector('[data-thematiq-row="openproject"]')
		expect(heading.querySelector('h2').textContent).toBe('OpenProject')
		expect(Number(heading.style.order)).toBeLessThan(Number(links[0].style.order))
		// After Nextcloud's own apps, and they keep their own order: the links are not counted as tiles.
		const files = Array.from(grid.querySelectorAll('a.app-item:not([data-thematiq-external])')).map((a) => Number(a.style.order))
		expect(Math.max(...files.filter((o) => o < 90000))).toBeLessThan(Number(heading.style.order))
		expect(links[0].style.order).toBe('90001')
	})
})
