/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Show titles and Show titles in the top bar (change apps-menu-show-titles,
 * boards AppMenuZonderTitels, AppMenuBalkTitels, AppMenuVastzetten) on a real
 * Nextcloud with the Zuiddrecht set, which carries the workplace header. The
 * administrator's titles, topBarTitles and the admin's pins are put back in
 * afterAll.
 *
 * Written from the change's scenarios; runs in the nightly against a live
 * instance (it is not run in the cloud sandbox).
 *
 * @spec openspec/changes/apps-menu-show-titles/specs/apps-menu-settings/spec.md
 * @spec openspec/changes/apps-menu-show-titles/specs/menu-labels/spec.md
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
const PINS = '/index.php/apps/thematiq/app-menu/pins'
const PAGE = '/index.php/apps/files/'

const grid = (page: Page): Locator =>
	page.locator('.app-menu__grid[data-thematiq-app-menu]')
const tooltip = (page: Page): Locator => page.locator('#thematiq-app-menu__tooltip')
const entry = (page: Page, app: string): Locator =>
	grid(page).locator(`a.app-item[href*="/apps/${app}/"]`).first()
const pin = (page: Page, app: string): Locator =>
	page.locator(`#header nav.thematiq-app-pins a[data-thematiq-pin="${app}"]`)

async function setDefaults(page: Page, settings: Record<string, unknown>): Promise<void> {
	const saved = await api(page, 'POST', DEFAULTS, { settings })
	expect(saved.status).toBe(200)
	await page.reload({ waitUntil: 'domcontentloaded' })
}

async function openMenu(page: Page): Promise<void> {
	await page.locator('.app-menu__waffle').click()
	await expect(grid(page)).toBeVisible()
}

test.describe('show titles in the apps menu and the top bar', () => {
	test.describe.configure({ mode: 'serial' })

	let snapshot: ThemeSnapshot | null = null
	let before: { titles: boolean, topBarTitles: boolean, payoff: boolean } = {
		titles: true,
		topBarTitles: false,
		payoff: true,
	}
	let pinsBefore: string[] = []

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			const page = await ctx.newPage()
			snapshot = await applyThemeState(page, { tokenSet: 'zuiddrecht' })
			await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
			const effective = (await api(page, 'GET', SETTINGS)).json.effective
			before = {
				titles: effective.titles !== false,
				topBarTitles: effective.topBarTitles === true,
				payoff: effective.payoff !== false,
			}
			// The pins come with the page's menu state, not with the settings.
			pinsBefore = await page.evaluate(() => {
				const state = (window as unknown as { OCP?: { InitialState?: { loadState: (a: string, k: string, f: unknown) => { pins?: string[] } | null } } })
					.OCP?.InitialState?.loadState('thematiq', 'app-menu', null)
				return Array.isArray(state?.pins) ? state.pins : []
			})
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			const page = await ctx.newPage()
			await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
			await api(page, 'POST', DEFAULTS, { settings: before })
			await api(page, 'POST', PINS, { pins: pinsBefore })
			if (snapshot !== null) {
				await restoreThemeState(page, snapshot)
			}
		} finally {
			await ctx.close()
		}
	})

	test.beforeEach(async ({ page }) => {
		await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
	})

	// @e2e apps-menu-settings::the-administrator-switches-titles-off
	test('titles off: every app entry is its icon, named by aria-label, without payoff', async ({ page }) => {
		try {
			await setDefaults(page, { titles: false, payoff: true })
			await openMenu(page)
			await expect(grid(page)).toHaveAttribute('data-titles', 'false')
			const files = entry(page, 'files')
			await expect(files).toHaveAttribute('aria-label', /\S/)
			await expect(files.locator('.app-item__label')).toBeHidden()
			await expect(grid(page).locator('.thematiq-app-menu__description')).toHaveCount(0)
			// Headings keep their text.
			await expect(grid(page).locator('h2.thematiq-app-menu__apps-title')).toBeVisible()
		} finally {
			await api(page, 'POST', DEFAULTS, { settings: { titles: before.titles } })
		}
	})

	// @e2e apps-menu-settings::keyboard-focus-shows-the-name
	// @e2e apps-menu-settings::escape-dismisses-the-tooltip-and-keeps-the-menu-open
	test('keyboard focus shows the name at once, off the focus ring; Escape hides it and keeps the menu open', async ({ page }) => {
		try {
			await setDefaults(page, { titles: false })
			await openMenu(page)
			await page.keyboard.press('ArrowRight')
			const focused = grid(page).locator('a.app-item:focus')
			await expect(focused).toHaveCount(1)
			await expect(tooltip(page)).toBeVisible()
			await expect(tooltip(page)).toHaveAttribute('role', 'tooltip')
			const name = await focused.getAttribute('data-thematiq-name')
			await expect(tooltip(page)).toHaveText(name ?? '')
			expect(await focused.getAttribute('aria-describedby')).toBeNull()

			const entryBox = await focused.boundingBox()
			const tipBox = await tooltip(page).boundingBox()
			expect(entryBox).not.toBeNull()
			expect(tipBox).not.toBeNull()
			const overlaps =
				tipBox!.y < entryBox!.y + entryBox!.height && tipBox!.y + tipBox!.height > entryBox!.y
			expect(overlaps).toBe(false)

			await page.keyboard.press('Escape')
			await expect(tooltip(page)).toBeHidden()
			await expect(grid(page)).toBeVisible()
			await expect(focused).toBeFocused()
		} finally {
			await api(page, 'POST', DEFAULTS, { settings: { titles: before.titles } })
		}
	})

	// @e2e apps-menu-settings::the-tooltip-can-be-hovered
	test('the tooltip shows after the pointer rests and stays while the pointer is on it', async ({ page }) => {
		try {
			await setDefaults(page, { titles: false })
			await openMenu(page)
			await entry(page, 'files').hover()
			await expect(tooltip(page)).toBeVisible()
			await tooltip(page).hover()
			await page.waitForTimeout(400)
			await expect(tooltip(page)).toBeVisible()
		} finally {
			await api(page, 'POST', DEFAULTS, { settings: { titles: before.titles } })
		}
	})

	// @e2e apps-menu-settings::the-administrator-switches-titles-off-in-menu-settings
	test('Menu settings, Theming: Show titles off disables Show payoff with its note', async ({ page }) => {
		await openMenu(page)
		await grid(page).locator('.thematiq-app-menu__settings').click()
		const dialog = page.locator('dialog.thematiq-menu-settings')
		await expect(dialog).toBeVisible()
		await dialog.locator('#thematiq-menu-settings-tab-theming').click()
		const titles = dialog.locator('#thematiq-menu-settings-switch-titles')
		const payoff = dialog.locator('#thematiq-menu-settings-switch-payoff')
		await expect(titles).toBeChecked()
		await titles.click()
		await expect(payoff).toBeDisabled()
		await expect(dialog).toContainText('Only with Show titles.')
		await dialog.locator('[data-part="cancel"]').click()
		await expect(dialog).toHaveCount(0)
	})

	// @e2e apps-menu-settings::a-pin-shows-its-name-on-focus
	test('titles in the top bar off: a pin shows its name in the tooltip on focus', async ({ page }) => {
		await api(page, 'POST', PINS, { pins: ['files', 'dashboard'] })
		await setDefaults(page, { topBarTitles: false })
		const files = pin(page, 'files')
		await expect(files).toBeVisible()
		await expect(files.locator('.thematiq-app-pins__name')).toHaveCount(0)
		await files.focus()
		await expect(tooltip(page)).toBeVisible()
		await expect(tooltip(page)).toHaveText((await files.getAttribute('aria-label')) ?? '')
	})

	// @e2e apps-menu-settings::pins-show-their-names
	// @e2e menu-labels::checkbox-change-triggers-save
	test('titles in the top bar on: each pin draws its name next to the icon', async ({ page }) => {
		await api(page, 'POST', PINS, { pins: ['files', 'dashboard'] })
		try {
			await setDefaults(page, { topBarTitles: true })
			const files = pin(page, 'files')
			await expect(files.locator('.thematiq-app-pins__name')).toBeVisible()
			await expect(files.locator('.thematiq-app-pins__name')).toHaveText(
				(await files.getAttribute('aria-label')) ?? '',
			)
			// The workplace menu never loads Nextcloud's text-label sheet next to it.
			await expect(page.locator('link[href*="show-menu-labels"]')).toHaveCount(0)
		} finally {
			await api(page, 'POST', DEFAULTS, { settings: { topBarTitles: before.topBarTitles } })
		}
	})
})
