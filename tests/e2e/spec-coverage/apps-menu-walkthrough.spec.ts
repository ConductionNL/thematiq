/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The apps menu walkthrough (change apps-menu-walkthrough), on a real
 * Nextcloud with the Zuiddrecht set, which carries the workplace header. Every
 * test clears the admin's tour keys and order first and puts them back in a
 * `finally`, so the tour is due again for the next test and the instance keeps
 * what it had.
 *
 * The tour lives inside the menu's popover (js/app-menu.js starts it there),
 * so the card, the dimmer and the spotlight ring are found under
 * `.thematiq-menu-walkthrough`.
 */
import { test, expect, type Locator, type Page } from '@playwright/test'

import { adminContext, api } from './_fixtures'
import { markMenuTourSeen } from '../menu-tour'
import {
	applyThemeState,
	restoreThemeState,
	type ThemeSnapshot,
} from './_theme-state'

const ORDER = '/index.php/apps/thematiq/app-menu/order'
const PINS = '/index.php/apps/thematiq/app-menu/pins'
const SEEN = '/index.php/apps/thematiq/api/preferences/apps_menu_walkthrough_seen_version'
const PROGRESS = '/index.php/apps/thematiq/api/preferences/apps_menu_walkthrough_seen_version-progress'
const SETTINGS = '/index.php/apps/thematiq/app-menu/settings'
const PAGE = '/index.php/settings/user'

const STEPS = ['open', 'apps', 'more', 'reorder', 'pin', 'create', 'settings']
const TITLES: Record<string, string> = {
	open: 'Open the apps menu',
	apps: 'Your apps in a row',
	more: 'More and fewer',
	reorder: 'Put them in your order',
	pin: 'Pin an app to the top bar',
	create: 'Create',
	settings: 'Menu settings',
}
const TARGETS: Record<string, string> = {
	open: 'apps-menu-button',
	apps: 'apps-menu-list',
	more: 'apps-menu-more',
	reorder: 'apps-menu-first-entry',
	pin: 'apps-menu-pins',
	create: 'apps-menu-create',
	settings: 'apps-menu-settings',
}

const grid = (page: Page): Locator => page.locator('.app-menu__grid[data-thematiq-app-menu]')
const card = (page: Page): Locator => page.locator('.thematiq-menu-walkthrough .cn-walkthrough__card')
const counter = (page: Page): Locator => card(page).locator('.cn-walkthrough__counter')
const title = (page: Page): Locator => card(page).locator('.cn-walkthrough__title')
const primary = (page: Page): Locator => card(page).locator('.cn-walkthrough__actions button').last()
const isOpen = async (page: Page): Promise<boolean> =>
	(await page.locator('.app-menu__waffle').getAttribute('aria-expanded')) === 'true'

/** Clear the tour keys of the signed-in person, so the tour is due. */
async function clearTour(page: Page): Promise<void> {
	await api(page, 'PUT', SEEN, { value: '' })
	await api(page, 'PUT', PROGRESS, { value: '' })
	await page.evaluate(() => {
		Object.keys(window.localStorage)
			.filter((key) => key.startsWith('cn-walkthrough-'))
			.forEach((key) => window.localStorage.removeItem(key))
	})
}

/** The visible entries in screen order, by app id. */
async function onScreen(page: Page): Promise<string[]> {
	return grid(page).evaluate((el) =>
		Array.from(el.querySelectorAll(':scope > a.app-item'))
			.filter((a) => !a.hasAttribute('data-thematiq-folded') && !a.hasAttribute('data-thematiq-outlined'))
			.sort((a, b) => Number((a as HTMLElement).style.order) - Number((b as HTMLElement).style.order))
			.map((a) => (/\/apps\/([a-z0-9_]+)/.exec(a.getAttribute('href') || '') || [])[1] || ''),
	)
}

/** The entry at a place on screen (1-based). */
async function entryAt(page: Page, place: number): Promise<Locator> {
	const keys = await onScreen(page)
	return grid(page).locator(`a.app-item[href*="/apps/${keys[place - 1]}/"]`).first()
}

/** Open the menu with a click and wait for the tour's card. */
async function openWithTour(page: Page): Promise<void> {
	await page.locator('.app-menu__waffle').click()
	await expect(grid(page)).toBeVisible()
	await expect(card(page)).toBeVisible({ timeout: 15_000 })
}

/** Press Next until the step with this id shows. */
async function goTo(page: Page, id: string): Promise<void> {
	for (let i = 0; i < STEPS.length; i++) {
		if ((await title(page).textContent())?.trim() === TITLES[id]) {
			return
		}
		await primary(page).click()
	}
	await expect(title(page)).toHaveText(TITLES[id])
}

/** Whether the spotlight ring sits on the element with this hook. */
async function spotlights(page: Page, ref: string): Promise<boolean> {
	return page.evaluate((id) => {
		const target = document.querySelector(`[data-walkthrough-id="${id}"]`)
		const ring = document.querySelector('.thematiq-menu-walkthrough .cn-walkthrough__ring')
		if (!target || !ring) return false
		const t = target.getBoundingClientRect()
		const r = ring.getBoundingClientRect()
		return Math.abs(r.left + 6 - t.left) < 3 && Math.abs(r.top + 6 - t.top) < 3
	}, ref)
}

test.describe('the apps menu walkthrough', () => {
	test.describe.configure({ mode: 'serial' })

	let snapshot: ThemeSnapshot | null = null
	let admin: Page

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			snapshot = await applyThemeState(await ctx.newPage(), { tokenSet: 'zuiddrecht' })
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			const page = await ctx.newPage()
			// Leave the tour seen, as global setup left it, so later specs meet the menu.
			await page.goto('/index.php/settings/user', { waitUntil: 'domcontentloaded' })
			await markMenuTourSeen(page)
			if (snapshot !== null) {
				await restoreThemeState(page, snapshot)
			}
		} finally {
			await ctx.close()
		}
	})

	test.beforeEach(async ({ page }) => {
		admin = page
		await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
		await api(page, 'POST', ORDER, { order: [] })
		await api(page, 'POST', PINS, { pins: [] })
		await clearTour(page)
		await page.reload({ waitUntil: 'domcontentloaded' })
	})

	test.afterEach(async () => {
		await api(admin, 'POST', ORDER, { order: [] })
		await api(admin, 'POST', PINS, { pins: [] })
		await clearTour(admin)
	})

	// @e2e apps-menu-walkthrough::the-tour-walks-every-feature-of-the-menu
	// @e2e apps-menu-walkthrough::a-step-change-is-announced
	test('walks every feature of the menu, spotlighting each part, and Done ends it', async ({ page }) => {
		const settings = (await api(page, 'GET', SETTINGS)).json.effective
		const fold = settings.columns * Math.min(settings.rowsAboveFold, settings.rows)
		await page.locator('.app-menu__waffle').click()
		await expect(grid(page)).toBeVisible()
		const all = await grid(page).evaluate((el) => el.querySelectorAll(':scope > a.app-item:not([data-thematiq-outlined])').length)
		test.skip(all <= fold, `this instance shows ${all} apps; the More step needs more than ${fold} (task 4.2)`)

		await expect(card(page)).toBeVisible({ timeout: 15_000 })
		const total = settings.showCreateRow ? 7 : 6
		await expect(counter(page)).toHaveText(`Step 1 of ${total}`)
		await expect(title(page)).toHaveText('Open the apps menu')
		const shown = STEPS.filter((id) => id !== 'create' || settings.showCreateRow)
		for (let i = 0; i < shown.length; i++) {
			const id = shown[i]
			await expect(title(page)).toHaveText(TITLES[id])
			await expect(counter(page)).toHaveText(`Step ${i + 1} of ${total}`)
			expect(await spotlights(page, TARGETS[id]), `the ${id} step spotlights ${TARGETS[id]}`).toBe(true)
			expect(await isOpen(page)).toBe(true)
			if (i === 0) {
				await primary(page).press('Enter')
				await expect(page.locator('.thematiq-menu-walkthrough .cn-walkthrough__live')).toHaveText(
					`Step 2 of ${total}: Your apps in a row`,
				)
				continue
			}
			if (i === shown.length - 1) {
				await expect(primary(page)).toHaveText(/Done/)
			}
			await primary(page).click()
		}
		await expect(card(page)).toBeHidden()
		expect((await api(page, 'GET', SEEN)).json.value).toMatch(/^\d+\.\d+\.\d+$/)
	})

	// @e2e apps-menu-walkthrough::opening-the-menu-for-the-first-time-starts-the-tour
	// @e2e apps-menu-walkthrough::a-person-who-finished-the-tour-does-not-get-it-again
	test('starts on the first open of the menu, not on page load, and not again once finished', async ({ page }) => {
		await page.waitForTimeout(1000)
		await expect(card(page)).toHaveCount(0)
		expect(await page.locator('script[data-thematiq-menu-walkthrough]').count()).toBe(0)
		await openWithTour(page)
		await expect(title(page)).toHaveText('Open the apps menu')
		await card(page).locator('.cn-walkthrough__close').click()
		await expect(card(page)).toBeHidden()

		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.locator('.app-menu__waffle').click()
		await expect(grid(page)).toBeVisible()
		await page.waitForTimeout(1500)
		await expect(card(page)).toHaveCount(0)
		expect(await page.locator('script[data-thematiq-menu-walkthrough]').count()).toBe(0)
	})

	// @e2e apps-menu-walkthrough::leaving-part-way-resumes-at-the-same-step
	test('resumes at the step left part-way after a reload', async ({ page }) => {
		await openWithTour(page)
		await goTo(page, 'reorder')
		await page.waitForTimeout(500)
		await page.reload({ waitUntil: 'domcontentloaded' })
		await openWithTour(page)
		await expect(title(page)).toHaveText(TITLES.reorder)
	})

	// @e2e apps-menu-walkthrough::reordering-works-on-the-reorder-step
	test('Alt and Arrow up moves an app on the reorder step, and the order is kept', async ({ page }) => {
		await openWithTour(page)
		await goTo(page, 'reorder')
		const before = await onScreen(page)
		await (await entryAt(page, 2)).focus()
		await page.keyboard.press('Alt+ArrowUp')
		await expect.poll(() => onScreen(page)).toEqual([before[1], before[0], ...before.slice(2)])
		await expect(title(page)).toHaveText(TITLES.reorder)
		await page.waitForTimeout(800)
		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.locator('.app-menu__waffle').click()
		await expect(grid(page)).toBeVisible()
		expect((await onScreen(page))[0]).toBe(before[1])
	})

	// @e2e apps-menu-walkthrough::pinning-and-unpinning-work-on-the-pin-step
	test('Alt+P pins and unpins on the pin step', async ({ page }) => {
		await openWithTour(page)
		await goTo(page, 'pin')
		const key = (await onScreen(page))[2]
		const pinned = page.locator(`nav.thematiq-app-pins a[href*="/apps/${key}/"]`)
		await (await entryAt(page, 3)).focus()
		await page.keyboard.press('Alt+p')
		await expect(pinned).toHaveCount(1)
		await expect(title(page)).toHaveText(TITLES.pin)
		await (await entryAt(page, 3)).focus()
		await page.keyboard.press('Alt+p')
		await expect(pinned).toHaveCount(0)
	})

	// @e2e apps-menu-walkthrough::more-apps-unfolds-on-the-more-step-and-an-app-moves-across-the-line
	test('More apps unfolds on the more step, and an app moves across the line', async ({ page }) => {
		const settings = (await api(page, 'GET', SETTINGS)).json.effective
		const fold = settings.columns * Math.min(settings.rowsAboveFold, settings.rows)
		await openWithTour(page)
		const all = await grid(page).evaluate((el) => el.querySelectorAll(':scope > a.app-item:not([data-thematiq-outlined])').length)
		test.skip(all <= fold, `this instance shows ${all} apps; the fold after ${fold} needs more (task 4.2)`)
		await goTo(page, 'more')
		const toggle = grid(page).locator('[data-thematiq-row="toggle"] > button')
		await toggle.click()
		await expect(toggle).toHaveText(/Fewer/)
		const folded = (await onScreen(page))[fold]
		const moving = grid(page).locator(`a.app-item[href*="/apps/${folded}/"]`).first()
		await moving.focus()
		await page.keyboard.press('Alt+ArrowUp')
		await toggle.click()
		await expect(toggle).toHaveText(/More/)
		expect((await onScreen(page)).slice(0, fold)).toContain(folded)
	})

	// @e2e apps-menu-walkthrough::escape-closes-the-tour-not-the-menu
	// @e2e apps-menu-walkthrough::closing-is-remembered-on-the-server
	test('Escape closes the tour and leaves the menu open; a new browser gets no tour', async ({ page, browser }) => {
		await openWithTour(page)
		await goTo(page, 'apps')
		await page.keyboard.press('Escape')
		await expect(card(page)).toBeHidden()
		expect(await isOpen(page)).toBe(true)
		await page.keyboard.press('Escape')
		await expect.poll(() => isOpen(page)).toBe(false)

		const ctx = await adminContext(browser)
		try {
			const fresh = await ctx.newPage()
			await fresh.goto(PAGE, { waitUntil: 'domcontentloaded' })
			await fresh.evaluate(() => window.localStorage.clear())
			await fresh.reload({ waitUntil: 'domcontentloaded' })
			await fresh.locator('.app-menu__waffle').click()
			await expect(grid(fresh)).toBeVisible()
			await fresh.waitForTimeout(1500)
			await expect(card(fresh)).toHaveCount(0)
		} finally {
			await ctx.close()
		}
	})

	// @e2e apps-menu-walkthrough::replay-from-the-account-menu
	test('replays from the Replay walkthrough entry under Help in the account menu', async ({ page }) => {
		await api(page, 'PUT', SEEN, { value: '99.0.0' })
		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.locator('#user-menu button').first().click()
		const entry = page.locator('#thematiq-replay-walkthrough')
		await expect(entry).toBeVisible()
		const names = await page.locator('#header-menu-user-menu li').allTextContents()
		const help = names.findIndex((n) => /Help/.test(n))
		if (help >= 0) {
			expect(names[help + 1].trim()).toBe('Replay walkthrough')
		}
		await entry.click()
		await expect(card(page)).toBeVisible({ timeout: 15_000 })
		await expect(title(page)).toHaveText('Open the apps menu')
		expect(await isOpen(page)).toBe(true)
	})

	// @e2e apps-menu-settings::replay-from-menu-settings
	test('replays from the Replay walkthrough button in Menu settings', async ({ page }) => {
		await api(page, 'PUT', SEEN, { value: '99.0.0' })
		await page.reload({ waitUntil: 'domcontentloaded' })
		await page.locator('.app-menu__waffle').click()
		await expect(grid(page)).toBeVisible()
		await grid(page).locator('.thematiq-app-menu__settings').click()
		const dialog = page.locator('dialog.thematiq-menu-settings')
		await expect(dialog).toBeVisible()
		const foot = await dialog.locator('.thematiq-menu-settings__foot [data-part]').evaluateAll((els) => els.map((el) => el.getAttribute('data-part')))
		expect(foot).toEqual(['reset', 'replay', 'cancel', 'save'])
		await dialog.locator('[data-part="replay"]').click()
		await expect(dialog).toHaveCount(0)
		await expect(card(page)).toBeVisible({ timeout: 15_000 })
		await expect(title(page)).toHaveText('Open the apps menu')
		expect(await isOpen(page)).toBe(true)
	})

	// @e2e apps-menu-walkthrough::a-step-whose-part-is-absent-is-skipped
	test('leaves out the create step when create actions are off for everyone', async ({ page }) => {
		const before = (await api(page, 'GET', SETTINGS)).json
		const defaults = before.defaults || before.effective
		try {
			await api(page, 'POST', '/index.php/apps/thematiq/app-menu/defaults', { settings: { ...defaults, showCreateRow: false } })
			await page.reload({ waitUntil: 'domcontentloaded' })
			await openWithTour(page)
			const total = Number(((await counter(page).textContent()) || '').replace(/^Step 1 of /, ''))
			const fold = before.effective.columns * Math.min(before.effective.rowsAboveFold, before.effective.rows)
			const all = await grid(page).evaluate((el) => el.querySelectorAll(':scope > a.app-item:not([data-thematiq-outlined])').length)
			expect(total).toBe(all > fold ? 6 : 5)
			for (let i = 0; i < total; i++) {
				expect((await title(page).textContent())?.trim()).not.toBe(TITLES.create)
				await primary(page).click()
			}
		} finally {
			await api(page, 'POST', '/index.php/apps/thematiq/app-menu/defaults', { settings: defaults })
		}
	})
})
