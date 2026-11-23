/**
 * @vitest-environment jsdom
 *
 * The brand block at the start of the workplace top bar (the DqKop board) for
 * an app that draws none: js/header-brand.js draws the emblem, the
 * organisation and the open app's name, and steps aside for an app that
 * draws its own brand. css/workplace-apps/brand.css is then only the
 * fallback.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')

const APPS = {
	dashboard: { id: 'dashboard', name: 'Dashboard', href: '/apps/dashboard/', active: false },
	files: { id: 'files', name: 'Files', href: '/apps/files/', active: true },
}

const CAPABILITIES = {
	theming: { name: 'Municipality of Zuiddrecht' },
	nldesign: { logos: { emblem: '/custom_apps/thematiq/img/logos/zuiddrecht-emblem.svg' } },
}

function installState(apps, capabilities) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) => {
				if (app === 'core' && key === 'apps' && apps !== undefined) {
					return apps
				}
				if (app === 'core' && key === 'capabilities' && capabilities !== undefined) {
					return capabilities
				}
				return fallback
			},
		},
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/header-brand.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 40))

const BAR =
	'<header id="header"><div class="header-start"><nav class="app-menu"></nav></div>'
	+ '<div class="header-end"></div></header>'

// jsdom applies no stylesheet: stand in for css/header-workplace.css, whose
// rule for the host sets --thematiq-brand-block: 1.
let sheetLoaded = true
const realComputedStyle = window.getComputedStyle.bind(window)

describe('the brand block for an app that draws none', () => {
	beforeEach(() => {
		document.body.innerHTML = BAR
		sheetLoaded = true
		vi.spyOn(window, 'getComputedStyle').mockImplementation((element) => {
			const style = realComputedStyle(element)
			return {
				getPropertyValue: (name) =>
					name === '--thematiq-brand-block'
					&& sheetLoaded
					&& element.classList.contains('thematiq-brand-host')
						? '1'
						: style.getPropertyValue(name),
			}
		})
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete global.OCP
		vi.restoreAllMocks()
	})

	it('leaves the bar alone when the browser holds an older sheet without the block\'s rules', async () => {
		sheetLoaded = false
		installState(APPS, CAPABILITIES)
		await loadScript()

		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
		expect(
			document.getElementById('header').hasAttribute('data-thematiq-brand'),
		).toBe(false)
	})

	it('puts the emblem, the organisation and the open app first in the bar', async () => {
		installState(APPS, CAPABILITIES)
		await loadScript()

		const header = document.getElementById('header')
		const host = header.firstElementChild
		expect(host.className).toBe('thematiq-brand-host')
		expect(header.hasAttribute('data-thematiq-brand')).toBe(true)

		const link = host.querySelector('a.thematiq-brand')
		expect(link.getAttribute('href')).toBe('/apps/files/')
		const emblem = link.querySelector('img.thematiq-brand__emblem')
		expect(emblem.getAttribute('src')).toBe(
			'/custom_apps/thematiq/img/logos/zuiddrecht-emblem.svg',
		)
		expect(emblem.getAttribute('alt')).toBe('')
		expect(link.querySelector('.thematiq-brand__caption').textContent).toBe(
			'Municipality of Zuiddrecht',
		)
		expect(link.querySelector('.thematiq-brand__name').textContent).toBe('Files')
		expect(
			host.querySelector('.thematiq-brand__divider').getAttribute('aria-hidden'),
		).toBe('true')
	})

	it('writes the names as text, never as markup', async () => {
		installState(
			{ files: { ...APPS.files, name: '<b>Files</b>' } },
			{ theming: { name: '<i>Org</i>' } },
		)
		await loadScript()

		expect(document.querySelector('.thematiq-brand b')).toBeNull()
		expect(document.querySelector('.thematiq-brand i')).toBeNull()
		expect(document.querySelector('.thematiq-brand__name').textContent).toBe(
			'<b>Files</b>',
		)
	})

	it('draws the name alone without an emblem or an organisation', async () => {
		installState(APPS, {})
		await loadScript()

		expect(document.querySelector('.thematiq-brand__emblem')).toBeNull()
		expect(document.querySelector('.thematiq-brand__caption')).toBeNull()
		expect(document.querySelector('.thematiq-brand__name').textContent).toBe('Files')
	})

	it('draws nothing on a page of no app', async () => {
		installState({ files: { ...APPS.files, active: false } }, CAPABILITIES)
		await loadScript()

		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
		expect(
			document.getElementById('header').hasAttribute('data-thematiq-brand'),
		).toBe(false)
	})

	it('leaves the bar to an app that draws its own brand', async () => {
		const own = document.createElement('div')
		own.className = 'cn-brand-bar-host'
		document.getElementById('header').prepend(own)
		installState(APPS, CAPABILITIES)
		await loadScript()

		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
		expect(document.getElementById('header').firstElementChild).toBe(own)
	})

	it('steps aside when the app draws its brand later, in the bar or its navigation', async () => {
		installState(APPS, CAPABILITIES)
		await loadScript()
		expect(document.querySelector('.thematiq-brand-host')).not.toBeNull()

		const nav = document.createElement('div')
		nav.className = 'cn-app-nav__brand'
		document.body.appendChild(nav)
		await settle()

		expect(document.querySelector('.thematiq-brand-host')).toBeNull()
		expect(
			document.getElementById('header').hasAttribute('data-thematiq-brand'),
		).toBe(false)
	})

	it('puts the block back when the bar re-renders without it', async () => {
		installState(APPS, CAPABILITIES)
		await loadScript()

		document.querySelector('.thematiq-brand-host').remove()
		await settle()

		expect(document.getElementById('header').firstElementChild.className).toBe(
			'thematiq-brand-host',
		)
	})
})

describe('the brand sheets', () => {
	it('shows Nextcloud\'s logo and app name only while the block is not drawn', () => {
		postcss.parse(read('css/workplace-apps/brand.css')).walkRules((rule) => {
			for (const selector of rule.selector.split(',')) {
				expect(selector).toContain('#header:not([data-thematiq-brand])')
			}
		})
	})

	it('draws both organisation lines in the muted text colour, over the bar\'s forced header colour', () => {
		const captions = []
		postcss.parse(read('css/header-workplace.css')).walkRules((rule) => {
			if (/__caption\b/.test(rule.selector)) {
				rule.walkDecls('color', (decl) => {
					captions.push([rule.selector, decl.value, decl.important])
				})
			}
		})
		expect(captions.map((entry) => entry[0])).toEqual([
			'body:not(#body-login) #header .cn-brand-bar__caption',
			'body:not(#body-login) #header .thematiq-brand__caption',
		])
		for (const [, value, important] of captions) {
			expect(value).toBe('var(--color-text-maxcontrast)')
			expect(important).toBe(true)
		}
	})
})
