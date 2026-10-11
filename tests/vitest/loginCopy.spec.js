/**
 * @vitest-environment jsdom
 *
 * The login page of the light workplace (the NcLogin board): js/login-copy.js
 * writes the set's copy into Nextcloud's login card, puts the SSO button above
 * the form with a divider, and keeps Nextcloud's legal links in the footer.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/login-page-board-parity/specs/workplace-layout/spec.md
 */

import { afterEach, describe, expect, it, vi } from 'vitest'

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'login-copy' && state !== undefined
					? state
					: fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/login-copy.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

function page({ sso = 0, legal = true } = {}) {
	const buttons = Array.from(
		{ length: sso },
		(_, i) =>
			`<a class="button-vue" href="/sso/${i}"><span class="button-vue__wrapper"><span class="button-vue__text">Login with provider ${i}</span></span></a>`,
	).join('')
	document.body.innerHTML =
		'<div class="guest-content"><div id="login"><div class="guest-box login-box">'
		+ '<div class="login-box__wrapper"><form class="login-form"><fieldset>'
		+ '<h2 class="login-form__headline">Inloggen bij Gemeente Zuiddrecht</h2>'
		+ '<label for="user">Gebruikersnaam of email</label><input id="user">'
		+ '<button type="submit">Inloggen</button>'
		+ '</fieldset></form></div>'
		+ `<div id="alternative-logins">${buttons}</div>`
		+ '</div></div></div>'
		+ '<footer class="guest-box"><p class="info">'
		+ '<a class="entity-name" href="https://x">Gemeente Zuiddrecht</a> – a safe home'
		+ (legal
			? '<br/><span class="footer__legal-links"><a class="legal" href="https://x/privacy">Privacy</a></span>'
			: '')
		+ '</p></footer>'
}

const ZUIDDRECHT = {
	title: 'Inloggen bij de werkplek',
	subtitle: 'Voor medewerkers van Gemeente Zuiddrecht.',
	ssoLabel: 'Inloggen met uw gemeenteaccount',
	usernameLabel: 'Gebruikersnaam of e-mail',
	divider: 'of',
	footer: 'Gemeente Zuiddrecht · werkplek op Nextcloud',
}

describe('the login page of the board', () => {
	afterEach(() => {
		document.body.innerHTML = ''
		delete global.OCP
	})

	it('puts the title and the subtitle first in the card', async () => {
		page()
		installState(ZUIDDRECHT)
		await loadScript()

		const box = document.querySelector('.login-box')
		const intro = box.firstElementChild
		expect(intro.className).toBe('thematiq-login-intro')
		expect(intro.querySelector('h2').textContent).toBe(
			'Inloggen bij de werkplek',
		)
		expect(intro.querySelector('p').textContent).toBe(
			'Voor medewerkers van Gemeente Zuiddrecht.',
		)
		expect(box.hasAttribute('data-thematiq-login-intro')).toBe(true)
		expect(document.querySelector('label[for="user"]').textContent).toBe(
			'Gebruikersnaam of e-mail',
		)
	})

	it("repeats Nextcloud's headline when the set names no title", async () => {
		page()
		installState({ divider: 'of' })
		await loadScript()

		expect(document.querySelector('.thematiq-login-intro h2').textContent).toBe(
			'Inloggen bij Gemeente Zuiddrecht',
		)
		expect(document.querySelector('.thematiq-login-intro p')).toBeNull()
	})

	it('moves a single SSO button above the form, labels it and adds the divider', async () => {
		page({ sso: 1 })
		installState(ZUIDDRECHT)
		await loadScript()

		const box = document.querySelector('.login-box')
		const order = Array.from(box.children).map((el) => el.id || el.className)
		expect(order).toEqual([
			'thematiq-login-intro',
			'alternative-logins',
			'thematiq-login-divider',
			'login-box__wrapper',
		])
		expect(
			box.querySelector('#alternative-logins .button-vue__text').textContent,
		).toBe('Inloggen met uw gemeenteaccount')
		expect(box.querySelector('.thematiq-login-divider').textContent).toBe('of')
		expect(box.hasAttribute('data-thematiq-sso')).toBe(true)
	})

	it('keeps the names of several SSO buttons', async () => {
		page({ sso: 2 })
		installState(ZUIDDRECHT)
		await loadScript()

		const names = Array.from(
			document.querySelectorAll('#alternative-logins .button-vue__text'),
		).map((el) => el.textContent)
		expect(names).toEqual(['Login with provider 0', 'Login with provider 1'])
	})

	it('adds no divider and no SSO mark without an alternative login', async () => {
		page()
		installState(ZUIDDRECHT)
		await loadScript()

		expect(document.querySelector('.thematiq-login-divider')).toBeNull()
		expect(
			document.querySelector('.login-box').hasAttribute('data-thematiq-sso'),
		).toBe(false)
	})

	it('replaces the name and the slogan in the footer and keeps the legal links', async () => {
		page()
		installState(ZUIDDRECHT)
		await loadScript()

		const info = document.querySelector('footer p.info')
		expect(info.firstChild.textContent).toBe(
			'Gemeente Zuiddrecht · werkplek op Nextcloud',
		)
		expect(info.querySelector('.entity-name')).toBeNull()
		expect(info.querySelector('a.legal').textContent).toBe('Privacy')
	})

	it('adds the footer hint with a link on this server, and never a script link', async () => {
		page({ legal: false })
		installState({
			footerHint: 'Bent u ouder of verzorger?',
			footerLinkLabel: 'Ga naar Mijn Wilgenboom',
			footerLinkUrl: '/apps/portaliq/',
		})
		await loadScript()

		const hint = document.querySelector('.thematiq-login-footer-hint')
		expect(hint.textContent).toBe(
			'Bent u ouder of verzorger? Ga naar Mijn Wilgenboom',
		)
		expect(hint.querySelector('a').getAttribute('href')).toBe('/apps/portaliq/')

		document.body.innerHTML = ''
		page({ legal: false })
		installState({
			footerLinkLabel: 'Klik',
			footerLinkUrl: 'javascript:alert(1)',
		})
		await loadScript()
		expect(document.querySelector('.thematiq-login-footer-hint')).toBeNull()
	})

	it('does nothing without its initial state', async () => {
		page({ sso: 1 })
		installState(undefined)
		await loadScript()

		expect(document.querySelector('.thematiq-login-intro')).toBeNull()
		expect(
			document.querySelector('.login-box').firstElementChild.className,
		).toBe('login-box__wrapper')
	})
})
