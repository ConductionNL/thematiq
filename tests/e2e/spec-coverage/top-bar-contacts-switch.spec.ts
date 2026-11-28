/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The contacts menu in the top bar switches off (change top-bar-contacts-switch,
 * boards AppMenuConfiguratie, AppMenu, TqLayout): `showContactsMenu`, a person's
 * own key with the administrator's default. Off, Nextcloud's `#contactsmenu` is
 * not displayed, not focusable and not in the accessibility tree, from the first
 * paint; the Contacts app stays in the apps menu.
 *
 * On a real Nextcloud with the Zuiddrecht set, which carries the workplace
 * header. Each test sets the keys through `POST /apps/thematiq/app-menu/settings`
 * or `/defaults` and puts every value back in a `finally`.
 *
 * Gate-19 @e2e traceability:
 *   @e2e apps-menu-settings::a-person-switches-the-contacts-menu-off
 *   @e2e apps-menu-settings::on-by-default
 *   @e2e apps-menu-settings::the-administrator-switches-the-contacts-menu-off-for-everyone
 *
 * @spec openspec/specs/apps-menu-settings/spec.md
 */
import { test, expect, type Locator, type Page } from '@playwright/test'

import { adminContext, api } from './_fixtures'
import {
	applyThemeState,
	restoreThemeState,
	type ThemeSnapshot,
} from './_theme-state'

const SETTINGS = '/index.php/apps/thematiq/app-menu/settings'
const DEFAULTS = '/index.php/apps/thematiq/app-menu/defaults'
const FILES = '/index.php/apps/files/'
const USER_SETTINGS = '/index.php/settings/user'

/** Set the signed-in person's own contacts menu switch (null follows the administrator). */
async function setOwn(page: Page, value: boolean | null): Promise<void> {
	const res = await api(page, 'POST', SETTINGS, { settings: { showContactsMenu: value } })
	expect(res.status, JSON.stringify(res.json)).toBe(200)
}

/**
 * The bell in the top bar. The notifications app mounts on a `#notifications` div and draws its
 * header menu with the same id inside it, so the id alone matches two elements: take the header menu.
 */
function bellMenu(page: Page): Locator {
	return page.locator('#header #notifications.header-menu, #header #notifications .header-menu').first()
}

/** Open Files and wait for the top bar. */
async function openFiles(page: Page): Promise<void> {
	await page.goto(FILES, { waitUntil: 'domcontentloaded' })
	await expect(bellMenu(page)).toBeVisible({ timeout: 30_000 })
}

/** The contacts menu is gone: not displayed, not focusable, and Tab from the bell passes it. */
async function expectNoContactsMenu(page: Page): Promise<void> {
	const menu = page.locator('#header #contactsmenu')
	if ((await menu.count()) > 0) {
		await expect(menu).toBeHidden()
		expect(await menu.evaluate((el) => getComputedStyle(el).display)).toBe('none')
	}
	const bell = bellMenu(page).locator('button, [role="button"]').first()
	await bell.focus()
	await page.keyboard.press('Tab')
	const inContacts = await page.evaluate(
		() => document.activeElement !== null && document.activeElement.closest('#contactsmenu') !== null,
	)
	expect(inContacts).toBe(false)
	// The bar still ends bell, divider, person: the last shown control before the person carries the divider.
	const divider = await page.evaluate(() => {
		let el = document.querySelector('#header #user-menu')?.previousElementSibling ?? null
		while (el !== null && getComputedStyle(el).display === 'none') {
			el = el.previousElementSibling
		}
		return el === null ? null : getComputedStyle(el, '::after').width
	})
	expect(divider, 'the divider before the person').toBe('1px')
}

test.describe('the contacts menu in the top bar switches off', () => {
	test.describe.configure({ mode: 'serial' })

	let snapshot: ThemeSnapshot | null = null
	let person: Page

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			snapshot = await applyThemeState(await ctx.newPage(), { tokenSet: 'zuiddrecht' })
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		if (snapshot === null) {
			return
		}
		const ctx = await adminContext(browser)
		try {
			await restoreThemeState(await ctx.newPage(), snapshot)
		} finally {
			await ctx.close()
		}
	})

	test.beforeEach(async ({ page }) => {
		person = page
		await page.goto(USER_SETTINGS, { waitUntil: 'domcontentloaded' })
	})

	test.afterEach(async () => {
		await setOwn(person, null)
	})

	// @e2e apps-menu-settings::on-by-default
	test('on by default: the contacts menu stands after the bell', async ({ page }) => {
		await setOwn(page, null)
		const before = (await api(page, 'GET', SETTINGS)).json
		test.skip(before.defaults?.showContactsMenu === false, 'the administrator switched it off on this instance')
		await openFiles(page)
		const menu = page.locator('#header #contactsmenu')
		await expect(menu).toBeVisible({ timeout: 15_000 })
		const after = await page.evaluate(() => {
			const bell = document.getElementById('notifications')
			const contacts = document.getElementById('contactsmenu')
			return bell !== null && contacts !== null
				&& (bell.compareDocumentPosition(contacts) & Node.DOCUMENT_POSITION_FOLLOWING) !== 0
		})
		expect(after).toBe(true)
	})

	// @e2e apps-menu-settings::a-person-switches-the-contacts-menu-off
	test('a person switches it off: no contacts menu, Tab from the bell passes it, the Contacts app stays', async ({ page }) => {
		await setOwn(page, false)
		const css: string[] = []
		page.on('request', (request) => {
			if (request.url().includes('top-bar-no-contacts')) {
				css.push(request.url())
			}
		})
		await openFiles(page)
		expect(css.length, 'the stylesheet comes with the page').toBeGreaterThan(0)
		await expectNoContactsMenu(page)

		// The Contacts app stays in the apps menu, when it is installed here.
		await page.locator('.app-menu__waffle').click()
		const grid = page.locator('.app-menu__grid[data-thematiq-app-menu]')
		await expect(grid).toBeVisible()
		const contactsApp = grid.locator('a.app-item[href*="/apps/contacts/"]')
		if ((await contactsApp.count()) > 0) {
			await expect(contactsApp.first()).toBeAttached()
		}
		await page.keyboard.press('Escape')
	})

	// @e2e apps-menu-settings::the-administrator-switches-the-contacts-menu-off-for-everyone
	test('the administrator switches it off for everyone; a person without their own value gets none', async ({ page }) => {
		const before = (await api(page, 'GET', SETTINGS)).json
		expect(before.admin, 'the spec runs as an administrator').toBe(true)
		const was = before.defaults.showContactsMenu
		try {
			const saved = await api(page, 'POST', DEFAULTS, { settings: { showContactsMenu: false } })
			expect(saved.status, JSON.stringify(saved.json)).toBe(200)
			expect(saved.json.defaults.showContactsMenu).toBe(false)
			await setOwn(page, null)
			await openFiles(page)
			await expectNoContactsMenu(page)

			// A person's own value wins again.
			await setOwn(page, true)
			await openFiles(page)
			await expect(page.locator('#header #contactsmenu')).toBeVisible({ timeout: 15_000 })
		} finally {
			await api(page, 'POST', DEFAULTS, { settings: { showContactsMenu: was === false ? false : null } })
		}
	})
})
