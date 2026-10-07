/**
 * @vitest-environment jsdom
 *
 * The workplace top bar (the DqKop board): js/header-user.js puts the name and
 * the role of the signed-in person in Nextcloud's account menu, and
 * css/header-workplace.css only hides Nextcloud's own avatar once the label is
 * there.
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

function installState(state) {
	global.OCP = {
		InitialState: {
			loadState: (app, key, fallback) =>
				app === 'thematiq' && key === 'header-user' && state !== undefined
					? state
					: fallback,
		},
	}
}

async function loadScript() {
	vi.resetModules()
	await import('../../js/header-user.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

const BAR =
	'<header id="header"><div class="header-end">'
	+ '<div id="notifications"></div>'
	+ '<nav id="user-menu" class="header-menu account-menu">'
	+ '<button class="header-menu__trigger" aria-label="Settings menu"><span class="account-menu__avatar"></span></button>'
	+ '</nav></div></header>'

describe('the name and the role in the top bar', () => {
	beforeEach(() => {
		document.body.innerHTML = BAR
	})

	afterEach(() => {
		document.body.innerHTML = ''
		delete global.OCP
	})

	it('puts the initials, the name and the role first in the account menu, hidden from assistive technology', async () => {
		installState({ name: 'Pieter Jansen', role: 'Woo-coördinator' })
		await loadScript()

		const chip = document.querySelector('#user-menu > .thematiq-user-chip')
		expect(chip).not.toBeNull()
		expect(document.getElementById('user-menu').firstElementChild).toBe(chip)
		expect(chip.getAttribute('aria-hidden')).toBe('true')
		expect(chip.querySelector('.thematiq-user-chip__avatar').textContent).toBe(
			'PJ',
		)
		expect(chip.querySelector('.thematiq-user-chip__name').textContent).toBe(
			'Pieter Jansen',
		)
		expect(chip.querySelector('.thematiq-user-chip__role').textContent).toBe(
			'Woo-coördinator',
		)
		expect(
			document
				.getElementById('header')
				.hasAttribute('data-thematiq-user-chip'),
		).toBe(true)
		// The real menu button stays, with its own name.
		expect(
			document
				.querySelector('#user-menu .header-menu__trigger')
				.getAttribute('aria-label'),
		).toBe('Settings menu')
	})

	it('shows the name alone when the profile names no role', async () => {
		installState({ name: 'admin', role: '' })
		await loadScript()

		expect(
			document.querySelector('.thematiq-user-chip__avatar').textContent,
		).toBe('A')
		expect(document.querySelector('.thematiq-user-chip__role')).toBeNull()
	})

	it('writes the name as text, never as markup', async () => {
		installState({
			name: '<img src=x onerror=alert(1)> Jansen',
			role: '<b>role</b>',
		})
		await loadScript()

		expect(document.querySelector('.thematiq-user-chip img')).toBeNull()
		expect(document.querySelector('.thematiq-user-chip b')).toBeNull()
		expect(document.querySelector('.thematiq-user-chip__role').textContent).toBe(
			'<b>role</b>',
		)
	})

	it('leaves the bar alone without a person or without an account menu', async () => {
		installState(undefined)
		await loadScript()
		expect(document.querySelector('.thematiq-user-chip')).toBeNull()
		expect(
			document
				.getElementById('header')
				.hasAttribute('data-thematiq-user-chip'),
		).toBe(false)

		document.body.innerHTML = '<header id="header"></header>'
		installState({ name: 'Pieter Jansen', role: '' })
		await loadScript()
		expect(
			document
				.getElementById('header')
				.hasAttribute('data-thematiq-user-chip'),
		).toBe(false)
	})

	it('puts the label back when the account menu re-renders without it', async () => {
		installState({ name: 'Pieter Jansen', role: 'Woo-coördinator' })
		await loadScript()

		document.querySelector('.thematiq-user-chip').remove()
		await new Promise((resolve) => setTimeout(resolve, 0))
		expect(
			document.querySelectorAll('#user-menu > .thematiq-user-chip'),
		).toHaveLength(1)
	})
})

describe('the workplace bar sheet', () => {
	const rules = []
	postcss.parse(read('css/header-workplace.css')).walkRules((rule) => {
		const decls = {}
		rule.walkDecls((decl) => {
			decls[decl.prop] = decl.value
		})
		rules.push({ selector: rule.selector.replace(/\s+/g, ' '), decls })
	})

	it('hides the avatar and stretches the menu button only once the label is there', () => {
		const touching = rules.filter((rule) =>
			/account-menu__avatar|#user-menu > \.header-menu__trigger/.test(
				rule.selector,
			),
		)
		expect(touching.length).toBeGreaterThan(0)
		for (const rule of touching) {
			for (const selector of rule.selector.split(',')) {
				expect(selector, selector).toContain(
					'#header[data-thematiq-user-chip]',
				)
			}
		}
	})

	it('writes no colour literal: every colour reads a token or a Nextcloud variable', () => {
		const literal = /#[0-9a-f]{3,8}\b|rgba?\(|hsla?\(/i
		for (const rule of rules) {
			for (const [prop, value] of Object.entries(rule.decls)) {
				if (/color|background|fill|stroke|border/.test(prop)) {
					expect(
						literal.test(value),
						`${rule.selector} { ${prop}: ${value} }`,
					).toBe(false)
				}
			}
		}
	})

	it('never reaches the login page', () => {
		for (const rule of rules) {
			for (const selector of rule.selector.split(',')) {
				expect(
					/body:not\(#body-login\)|#header \.thematiq-user-chip/.test(
						selector,
					),
					selector,
				).toBe(true)
			}
		}
	})
})
