/**
 * @vitest-environment jsdom
 *
 * Nextcloud's "page not found" page in the light workplace (the NietGevonden
 * board): js/not-found.js writes the set's copy and links into the card.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/not-found-page-board/specs/workplace-layout/spec.md
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'not-found-copy' && state !== undefined
					? state
					: fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/not-found.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

const PAGE =
	'<div class="guest-content"><div class="body-login-container update">'
	+ '<div class="icon-big icon-search"></div>'
	+ '<h2>Pagina niet gevonden</h2>'
	+ '<p class="infogroup">Deze pagina kan niet worden gevonden op de server.</p>'
	+ '<p><a class="button primary" href="/">Terug naar Gemeente Zuiddrecht</a></p>'
	+ '</div></div>'

const ZUIDDRECHT = {
	eyebrow: 'Foutcode 404',
	title: 'Pagina niet gevonden',
	text: 'Deze pagina bestaat niet (meer).',
	linksHeading: 'Of ga verder naar',
	links: [{ label: 'De homepage', url: '/' }],
}

describe('the not-found page of the board', () => {
	beforeEach(() => {
		document.body.innerHTML = PAGE
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete global.OCP
	})

	it('puts the error code above the title and writes the text', async () => {
		installState(ZUIDDRECHT)
		await loadScript()

		const card = document.querySelector('.body-login-container')
		expect(card.hasAttribute('data-thematiq-not-found')).toBe(true)
		const eyebrow = card.querySelector('.thematiq-not-found__eyebrow')
		expect(eyebrow.textContent).toBe('Foutcode 404')
		expect(eyebrow.nextElementSibling.tagName).toBe('H2')
		expect(card.querySelector('p.infogroup').textContent).toBe(
			'Deze pagina bestaat niet (meer).',
		)
	})

	it('adds the heading and the links, and the back button gives way', async () => {
		installState(ZUIDDRECHT)
		await loadScript()

		const card = document.querySelector('.body-login-container')
		expect(card.querySelector('.thematiq-not-found__heading').textContent).toBe(
			'Of ga verder naar',
		)
		const link = card.querySelector('a.thematiq-not-found__link')
		expect(link.textContent).toBe('De homepage')
		expect(link.getAttribute('href')).toBe('/')
		expect(link.querySelector('svg').getAttribute('aria-hidden')).toBe('true')
		expect(
			card
				.querySelector('a.button.primary')
				.parentElement.classList.contains('thematiq-not-found__replaced'),
		).toBe(true)
	})

	it('keeps the back button and adds no list without links, and never a script link', async () => {
		installState({
			title: 'Niet gevonden',
			links: [{ label: 'Kwaad', url: 'javascript:alert(1)' }],
		})
		await loadScript()

		const card = document.querySelector('.body-login-container')
		expect(card.querySelector('.thematiq-not-found__links')).toBeNull()
		expect(card.querySelector('.thematiq-not-found__replaced')).toBeNull()
		expect(card.querySelector('h2').textContent).toBe('Niet gevonden')
	})

	it('does nothing without its initial state', async () => {
		installState(undefined)
		await loadScript()

		expect(
			document
				.querySelector('.body-login-container')
				.hasAttribute('data-thematiq-not-found'),
		).toBe(false)
	})
})
