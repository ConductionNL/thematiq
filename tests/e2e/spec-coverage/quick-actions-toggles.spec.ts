/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The quick actions switch on and off in two places (change
 * quick-actions-toggles, boards AppMenuSnelacties, AppMenuConfiguratie,
 * TqLayout, MlInbox): the Create button with the plus before the bell
 * (`showCreateButton`) and the Create row at the bottom of the apps menu
 * (`showCreateRow`), each a person's own key with the administrator's default.
 *
 * On a real Nextcloud with the Zuiddrecht set, which carries the workplace
 * header. Each test sets the signed-in person's keys through
 * `POST /apps/thematiq/app-menu/settings` and puts every value back in a
 * `finally`: the person's keys to null (the administrator's again), the
 * administrator's to what they were.
 *
 * Gate-19 @e2e traceability:
 *   @e2e apps-menu-settings::both-on
 *   @e2e apps-menu-settings::only-the-create-button
 *   @e2e apps-menu-settings::only-the-create-row
 *   @e2e apps-menu-settings::neither
 *   @e2e apps-menu-settings::the-administrator-switches-the-create-row-off-for-everyone
 *   @e2e workplace-quick-add::files-has-the-button
 *   @e2e workplace-quick-add::settings-has-the-button
 *
 * @spec openspec/specs/apps-menu-settings/spec.md
 * @spec openspec/specs/workplace-quick-add/spec.md
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
const TEMPLATES = '/ocs/v2.php/apps/files/api/v1/templates'

type Switches = { showCreateButton: boolean | null; showCreateRow: boolean | null }

const quickAdd = (page: Page): Locator => page.locator('#header .header-end > .thematiq-quick-add')
const grid = (page: Page): Locator => page.locator('.app-menu__grid[data-thematiq-app-menu]')
const createRow = (page: Page): Locator =>
	grid(page).locator(':scope > [data-thematiq-row="create"]:not([data-thematiq-folded])')

/** Set the signed-in person's own create switches (null follows the administrator). */
async function setOwn(page: Page, switches: Switches): Promise<void> {
	const res = await api(page, 'POST', SETTINGS, { settings: switches })
	expect(res.status, JSON.stringify(res.json)).toBe(200)
}

/** Open a page and check that Quick add stands before the bell, or that there is none. */
async function expectQuickAdd(page: Page, path: string, drawn: boolean): Promise<void> {
	await page.goto(path, { waitUntil: 'domcontentloaded' })
	await expect(page.locator('.app-menu__waffle')).toBeVisible({ timeout: 30_000 })
	if (!drawn) {
		// The apps menu shares its actions at load; give it the moment it takes.
		await page.waitForTimeout(1_000)
		await expect(quickAdd(page)).toHaveCount(0)
		return
	}
	const wrap = quickAdd(page)
	await expect(wrap).toBeVisible({ timeout: 15_000 })
	await expect(wrap.getByRole('button', { name: 'Quick add' })).toHaveAttribute('aria-haspopup', 'menu')
	expect(await wrap.evaluate((el) => el.nextElementSibling?.id)).toBe('notifications')
}

/** Open the apps menu and check the Create row is drawn, or not. */
async function expectCreateRow(page: Page, drawn: boolean): Promise<void> {
	await page.locator('.app-menu__waffle').click()
	await expect(grid(page)).toBeVisible()
	if (drawn) {
		await expect(createRow(page)).toBeVisible({ timeout: 15_000 })
	} else {
		await page.waitForTimeout(1_000)
		await expect(createRow(page)).toHaveCount(0)
	}
	await page.keyboard.press('Escape')
}

test.describe('the Create button and the Create row switch apart', () => {
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
		await setOwn(person, { showCreateButton: null, showCreateRow: null })
	})

	// @e2e apps-menu-settings::both-on
	// @e2e workplace-quick-add::files-has-the-button
	test('both on: Quick add before the bell on Files, and the Create row in the apps menu', async ({ page }) => {
		await setOwn(page, { showCreateButton: true, showCreateRow: true })
		await expectQuickAdd(page, FILES, true)
		await expectCreateRow(page, true)
	})

	// @e2e workplace-quick-add::settings-has-the-button
	test('Settings has the button before the bell', async ({ page }) => {
		await setOwn(page, { showCreateButton: true, showCreateRow: true })
		await expectQuickAdd(page, USER_SETTINGS, true)
	})

	// @e2e apps-menu-settings::only-the-create-button
	test('only the Create button: Quick add lists the Create actions, the apps menu has no Create row', async ({ page }) => {
		await setOwn(page, { showCreateButton: true, showCreateRow: false })
		await expectQuickAdd(page, FILES, true)
		const button = quickAdd(page).getByRole('button', { name: 'Quick add' })
		await button.click()
		const menu = page.locator('#thematiq-quick-add-menu')
		await expect(menu).toBeVisible()
		await expect(menu.getByRole('menuitem').first()).toBeVisible({ timeout: 15_000 })
		await page.keyboard.press('Escape')
		await expectCreateRow(page, false)
	})

	// @e2e apps-menu-settings::only-the-create-row
	test('only the Create row: no Quick add, the Create row in the apps menu', async ({ page }) => {
		await setOwn(page, { showCreateButton: false, showCreateRow: true })
		await expectQuickAdd(page, FILES, false)
		await expectCreateRow(page, true)
	})

	// @e2e apps-menu-settings::neither
	test('neither: no Quick add, no Create row, and the templates API is not asked', async ({ page }) => {
		await setOwn(page, { showCreateButton: false, showCreateRow: false })
		const asked: string[] = []
		page.on('request', (request) => {
			if (request.url().includes(TEMPLATES)) {
				asked.push(request.url())
			}
		})
		await expectQuickAdd(page, FILES, false)
		await expectCreateRow(page, false)
		expect(asked).toEqual([])
	})

	// @e2e apps-menu-settings::the-administrator-switches-the-create-row-off-for-everyone
	test('the administrator switches the Create row off for everyone; a person without their own value gets none', async ({ page }) => {
		const before = (await api(page, 'GET', SETTINGS)).json
		expect(before.admin, 'the spec runs as an administrator').toBe(true)
		const was = before.defaults.showCreateRow
		try {
			const saved = await api(page, 'POST', DEFAULTS, { settings: { showCreateRow: false } })
			expect(saved.status, JSON.stringify(saved.json)).toBe(200)
			expect(saved.json.defaults.showCreateRow).toBe(false)
			await setOwn(page, { showCreateButton: null, showCreateRow: null })
			await page.goto(FILES, { waitUntil: 'domcontentloaded' })
			await expect(page.locator('.app-menu__waffle')).toBeVisible({ timeout: 30_000 })
			await expectCreateRow(page, false)
		} finally {
			await api(page, 'POST', DEFAULTS, { settings: { showCreateRow: was === true ? null : was } })
		}
	})
})
