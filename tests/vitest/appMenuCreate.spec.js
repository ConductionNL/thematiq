/**
 * @vitest-environment jsdom
 *
 * The "Apps" title, the chevron on the toggle and the Create row of the
 * workplace apps menu: js/app-menu.js asks the Files templates API which
 * files this person can create, shows one chip per kind an installed app
 * offers, and More unfolds every creator.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/apps-menu-create-pins-settings/specs/apps-menu-create/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
const PPTX = 'application/vnd.openxmlformats-officedocument.presentationml.presentation'

const CREATORS = [
	{ app: 'text', label: 'Text document', extension: '.md', mimetypes: ['text/markdown'], actionLabel: 'Create new text file' },
	{ app: 'onlyoffice', label: 'New document', extension: '.docx', mimetypes: [DOCX] },
	{ app: 'onlyoffice', label: 'New spreadsheet', extension: '.xlsx', mimetypes: [XLSX] },
	{ app: 'onlyoffice', label: 'New presentation', extension: '.pptx', mimetypes: [PPTX] },
	{ app: 'bad', label: 'Bad', extension: 'docx/../x', mimetypes: [DOCX] },
]

const LABELS = {
	title: 'Apps',
	allApps: 'Alle apps',
	hideApps: 'Apps verbergen',
	create: 'Nieuw maken',
	newDocument: 'Nieuw document',
	newSpreadsheet: 'Nieuwe spreadsheet',
	newPresentation: 'Nieuwe presentatie',
	newWhiteboard: 'Nieuw whiteboard',
	more: 'Meer',
	createFailed: 'Het bestand kon niet worden gemaakt.',
}

const APPS = ['files', 'dossiq', 'calendar', 'pipelinq', 'mail', 'contacts', 'deck', 'photos', 'activity', 'talk']

function installState(state, coreActions = []) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => {
				if (app === 'thematiq' && key === 'app-menu') {
					return state
				}
				if (app === 'core' && key === 'navigationActions') {
					return coreActions
				}
				return fallback
			},
		},
	}
}

async function flush() {
	for (let i = 0; i < 20; i++) {
		await Promise.resolve()
	}
	await new Promise((resolve) => setTimeout(resolve, 0))
}

async function loadScript() {
	vi.resetModules()
	delete window.thematiqAppMenuOpening
	await import('../../js/app-menu.js?t=' + Math.random())
	await flush()
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
		+ '</div></div></div>'
	return document.querySelector('.app-menu__grid')
}

function respond(body, status = 200) {
	return Promise.resolve({
		ok: status >= 200 && status < 300,
		status,
		json: () => Promise.resolve(body),
	})
}

describe('the title, the chevron and the Create row', () => {
	let requests
	let createStatus

	beforeEach(() => {
		requests = []
		createStatus = []
		global.OC = {
			webroot: '',
			requestToken: 'token',
			generateUrl: (url, params = {}) => url.replace(/\{(\w+)\}/g, (all, name) => params[name]),
		}
		global.fetch = vi.fn((url, options = {}) => {
			requests.push({ url, options })
			if (url.indexOf('/templates/create') !== -1) {
				const status = createStatus.length > 0 ? createStatus.shift() : 200
				return respond({ ocs: { data: { fileid: 42 } } }, status)
			}
			if (url.indexOf('/templates') !== -1) {
				return respond({ ocs: { data: CREATORS } })
			}
			return respond({})
		})
		delete window.thematiqAppMenuCreators
	})

	afterEach(() => {
		if (window.thematiqAppMenuObserver) {
			window.thematiqAppMenuObserver.disconnect()
			delete window.thematiqAppMenuObserver
		}
		delete window.thematiqAppMenuCreators
		document.body.innerHTML = ''
		delete global.OCP
		delete global.OC
		delete global.fetch
		vi.restoreAllMocks()
	})

	it('titles the panel and draws a chevron that turns with the toggle', async () => {
		const grid = openMenu()
		installState({ labels: LABELS, order: [] })
		await loadScript()

		const heading = grid.querySelector('[data-thematiq-row="title"] h2')
		expect(heading.textContent).toBe('Apps')
		const button = grid.querySelector('[data-thematiq-row="toggle"] button')
		const down = button.querySelector('svg path').getAttribute('d')
		expect(button.querySelector('svg').getAttribute('aria-hidden')).toBe('true')
		expect(button.textContent).toBe('Alle apps')
		button.click()
		expect(button.textContent).toBe('Apps verbergen')
		expect(button.querySelector('svg path').getAttribute('d')).not.toBe(down)
	})

	it('shows a chip per kind an installed app offers, and no whiteboard without one', async () => {
		const grid = openMenu()
		installState({ labels: LABELS, order: [] })
		await loadScript()

		const bar = grid.querySelector('[data-thematiq-row="create"]')
		expect(bar.getAttribute('role')).toBe('group')
		expect(grid.querySelector('#' + bar.getAttribute('aria-labelledby')).textContent).toBe('Nieuw maken')
		expect(Array.from(bar.querySelectorAll('button')).map((b) => b.textContent)).toEqual([
			'Nieuw document',
			'Nieuwe spreadsheet',
			'Nieuwe presentatie',
			'Meer',
		])
		expect(requests.filter((r) => r.url.indexOf('/templates') !== -1)).toHaveLength(1)
		expect(requests[0].options.headers['OCS-APIRequest']).toBe('true')
	})

	it('unfolds every creator and Nextcloud action under More', async () => {
		const grid = openMenu()
		installState({ labels: LABELS, order: [] }, [{ id: 'note', name: 'New note', href: '/apps/notes/new' }])
		await loadScript()

		const more = grid.querySelector('[data-thematiq-row="create-more"]')
		const button = grid.querySelector('[aria-controls="' + more.id + '"]')
		expect(more.hasAttribute('data-thematiq-folded')).toBe(true)
		button.click()
		expect(button.getAttribute('aria-expanded')).toBe('true')
		expect(more.hasAttribute('data-thematiq-folded')).toBe(false)
		expect(Array.from(more.querySelectorAll('button')).map((b) => b.textContent)).toEqual([
			'Create new text file',
			'New document',
			'New spreadsheet',
			'New presentation',
			'New note',
		])
		button.click()
		expect(more.hasAttribute('data-thematiq-folded')).toBe(true)
	})

	it('creates the file in the home folder, numbers a taken name, and opens it', async () => {
		const assign = vi.fn()
		vi.spyOn(window, 'location', 'get').mockReturnValue({ assign })
		createStatus = [403, 200]
		const grid = openMenu()
		installState({ labels: LABELS, order: [] })
		await loadScript()

		grid.querySelector('[data-thematiq-row="create"] button').click()
		await flush()
		const creates = requests.filter((r) => r.url.indexOf('/templates/create') !== -1)
		expect(creates.map((r) => JSON.parse(r.options.body).filePath)).toEqual([
			'/New document.docx',
			'/New document (2).docx',
		])
		expect(creates[0].options.method).toBe('POST')
		expect(assign).toHaveBeenCalledWith('/f/42')
	})

	it('says so when the file could not be made', async () => {
		createStatus = [500]
		const grid = openMenu()
		installState({ labels: LABELS, order: [] })
		await loadScript()

		grid.querySelector('[data-thematiq-row="create"] button').click()
		await flush()
		expect(grid.querySelector('[data-thematiq-row="live"]').textContent).toBe(
			'Het bestand kon niet worden gemaakt.',
		)
	})

	it('draws no row when nothing can be created', async () => {
		global.fetch = vi.fn(() => respond({ ocs: { data: [] } }))
		const grid = openMenu()
		installState({ labels: LABELS, order: [] })
		await loadScript()

		expect(grid.querySelector('[data-thematiq-row="create"]')).toBeNull()
	})

	it('draws no row and asks nothing when the create actions are switched off', async () => {
		const grid = openMenu()
		installState({ labels: LABELS, order: [], settings: { showCreateActions: false } })
		await loadScript()

		expect(grid.querySelector('[data-thematiq-row="create"]')).toBeNull()
		expect(requests).toHaveLength(0)
	})
})
