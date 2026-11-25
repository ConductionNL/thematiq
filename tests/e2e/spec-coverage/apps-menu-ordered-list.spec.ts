/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workplace apps menu as one ordered list (change apps-menu-ordered-list),
 * on a real Nextcloud with the Zuiddrecht set, which carries the workplace
 * header. Every test puts back what it changed: the admin's own order and the
 * external apps of the menu settings are put back in a `finally`.
 *
 * WHICH NEXTCLOUD. Nextcloud 33 and 34 open the menu on a click only, so the
 * hover tests prove the script's own hover-open there. On the fork's
 * Nextcloud 35 the core opens on hover itself and the same tests prove the two
 * together.
 */
import { test, expect, type Locator, type Page } from '@playwright/test'

import { adminContext, api } from './_fixtures'
import {
	applyThemeState,
	restoreThemeState,
	withThemeState,
	type ThemeSnapshot,
} from './_theme-state'

const ORDER = '/index.php/apps/thematiq/app-menu/order'
const SETTINGS = '/index.php/apps/thematiq/app-menu/settings'
const DEFAULTS = '/index.php/apps/thematiq/app-menu/defaults'
const PAGE = '/index.php/settings/user'

const grid = (page: Page): Locator =>
	page.locator('.app-menu__grid[data-thematiq-app-menu]')

/** The visible entries in screen order, by key. */
async function onScreen(page: Page): Promise<string[]> {
	return grid(page).evaluate((el) =>
		Array.from(el.querySelectorAll(':scope > a.app-item'))
			.filter(
				(a) =>
					!a.hasAttribute('data-thematiq-folded')
					&& !a.hasAttribute('data-thematiq-outlined'),
			)
			.sort(
				(a, b) =>
					Number((a as HTMLElement).style.order)
					- Number((b as HTMLElement).style.order),
			)
			.map(
				(a) =>
					a.getAttribute('data-thematiq-external')
					|| (/\/apps\/([a-z0-9_]+)/.exec(a.getAttribute('href') || '')
						|| [])[1]
					|| '',
			),
	)
}

async function openByClick(page: Page): Promise<void> {
	await page.locator('.app-menu__waffle').click()
	await expect(grid(page)).toBeVisible()
}

async function closeMenu(page: Page): Promise<void> {
	await page.keyboard.press('Escape')
	await expect(grid(page)).toBeHidden()
}

const entry = (page: Page, key: string): Locator =>
	key.startsWith('external-')
		? grid(page).locator(`a[data-thematiq-external="${key}"]`)
		: grid(page).locator(`a.app-item[href*="/apps/${key}/"]`).first()

const toggle = (page: Page): Locator =>
	grid(page).locator('[data-thematiq-row="toggle"] > button')

test.describe('the apps menu as one ordered list', () => {
	test.describe.configure({ mode: 'serial' })

	let admin: Page
	let snapshot: ThemeSnapshot | null = null

	// The thematiq apps menu exists only under a set with the workplace header
	// (AppMenuService::inject() returns early otherwise), so every test here
	// needs Zuiddrecht, not only the first. Without this the later tests ran on
	// whatever set the instance had, found no `.app-menu__grid[data-thematiq-app-menu]`
	// and, in serial mode, took the rest of the block down with them.
	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			snapshot = await applyThemeState(await ctx.newPage(), {
				tokenSet: 'zuiddrecht',
			})
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		if (snapshot === null) return
		const ctx = await adminContext(browser)
		try {
			await restoreThemeState(await ctx.newPage(), snapshot)
		} finally {
			await ctx.close()
		}
	})

	test.beforeEach(async ({ page }) => {
		admin = page
		await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
		await api(page, 'POST', ORDER, { order: [] })
		await page.reload({ waitUntil: 'domcontentloaded' })
	})

	test.afterEach(async () => {
		await api(admin, 'POST', ORDER, { order: [] })
	})

	// @e2e workplace-layout::zuiddrechts-apps-menu
	// @e2e workplace-layout::all-apps-unfolds-the-rest
	// The fold sits after `columns` x `rowsAboveFold` entries of the menu
	// settings (apps-menu-settings), 4 x 2 by default.
	test('shows the apps above the fold, the group apps first, and unfolds the rest', async ({
		page,
		browser,
	}) => {
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			await page.reload({ waitUntil: 'domcontentloaded' })
			const settings = (await api(page, 'GET', SETTINGS)).json.effective
			const fold =
				settings.columns * Math.min(settings.rowsAboveFold, settings.rows)
			await openByClick(page)
			const all = await grid(page).evaluate(
				(el) =>
					el.querySelectorAll(
						':scope > a.app-item:not([data-thematiq-outlined])',
					).length,
			)
			test.skip(
				all <= fold,
				`this instance shows ${all} apps; the fold after ${fold} needs more`,
			)

			const shown = await onScreen(page)
			expect(shown).toHaveLength(fold)
			const werk = [
				'launchpad',
				'dossiq',
				'pipelinq',
				'decidiq',
				'learniq',
				'portaliq',
			]
			const installed = werk.filter((id) => shown.includes(id))
			expect(shown.slice(0, installed.length)).toEqual(installed)
			await expect(toggle(page)).toHaveAttribute('aria-expanded', 'false')
			// The footer's gear opens the Menu settings dialog (apps-menu-settings).
			await expect(
				grid(page).locator(
					'[data-thematiq-row="footer"] button[aria-haspopup="dialog"]',
				),
			).toHaveText(/^(Menu settings|Menu-instellingen)$/)

			await toggle(page).click()
			await expect(toggle(page)).toHaveAttribute('aria-expanded', 'true')
			expect(await onScreen(page)).toHaveLength(all)
			await expect(
				grid(page).locator('[data-thematiq-row="line"]'),
			).toBeVisible()

			await toggle(page).click()
			expect(await onScreen(page)).toHaveLength(fold)
		})
	})

	// @e2e workplace-layout::the-arrow-keys-follow-the-screen
	test('the arrow keys follow the screen and skip the folded rest', async ({
		page,
	}) => {
		await openByClick(page)
		const shown = await onScreen(page)
		await entry(page, shown[0]).focus()
		await page.keyboard.press('ArrowRight')
		await expect(entry(page, shown[1])).toBeFocused()
		await page.keyboard.press('End')
		// With a fold, End lands on the "All apps" toggle and one step back is
		// the last app on screen. Without one (an instance with no more apps
		// than the fold holds), End lands on that last app and one step back is
		// the app before it.
		const folded = (await toggle(page).count()) > 0
		const last = folded ? toggle(page) : entry(page, shown[shown.length - 1])
		await expect(last).toBeFocused()
		await page.keyboard.press('ArrowLeft')
		await expect(
			entry(page, shown[shown.length - (folded ? 1 : 2)]),
		).toBeFocused()
	})

	// @e2e apps-menu-order::move-an-app-with-the-keyboard
	test('Alt and an arrow key move an app, keep focus and say where', async ({
		page,
	}) => {
		await openByClick(page)
		const before = await onScreen(page)
		const second = entry(page, before[1])
		await second.focus()
		await page.keyboard.press('Alt+ArrowUp')
		const after = await onScreen(page)
		expect(after[0]).toBe(before[1])
		expect(after[1]).toBe(before[0])
		await expect(second).toBeFocused()
		await expect(grid(page).locator('[data-thematiq-row="live"]')).toContainText(
			/ 1 /,
		)
	})

	// @e2e apps-menu-order::drag-an-app-into-the-first-nine
	// @e2e apps-menu-order::the-order-follows-the-person-to-another-browser
	test('a dragged app keeps its place, in another browser too', async ({
		page,
		browser,
	}) => {
		await openByClick(page)
		if ((await toggle(page).count()) > 0) {
			await toggle(page).click()
		}
		const before = await onScreen(page)
		const moved = before[Math.min(before.length - 1, 10)]
		const source = await entry(page, moved).boundingBox()
		const target = await entry(page, before[0]).boundingBox()
		expect(source && target).toBeTruthy()
		await page.mouse.move(
			source!.x + source!.width / 2,
			source!.y + source!.height / 2,
		)
		await page.mouse.down()
		await page.mouse.move(
			source!.x + source!.width / 2 + 10,
			source!.y + source!.height / 2,
			{ steps: 3 },
		)
		await page.mouse.move(target!.x + 4, target!.y + target!.height / 2, {
			steps: 12,
		})
		await page.mouse.up()
		expect((await onScreen(page))[0]).toBe(moved)
		await expect(page).toHaveURL(new RegExp(PAGE.replace(/\//g, '\\/')))

		if (
			(await toggle(page).count()) > 0
			&& (await toggle(page).getAttribute('aria-expanded')) === 'true'
		) {
			await toggle(page).click()
		}
		expect((await onScreen(page))[0]).toBe(moved)

		await expect
			.poll(
				async () =>
					(await api(page, 'POST', ORDER, { order: await onScreen(page) }))
						.status,
			)
			.toBe(200)
		const ctx = await adminContext(browser)
		try {
			const other = await ctx.newPage()
			await other.goto(PAGE, { waitUntil: 'domcontentloaded' })
			await openByClick(other)
			expect((await onScreen(other))[0]).toBe(moved)
		} finally {
			await ctx.close()
		}
	})

	// @e2e apps-menu-opening::pointing-opens-leaving-closes
	// @e2e apps-menu-opening::a-click-pins-the-menu
	test('pointing opens, leaving closes, and a click pins', async ({ page }) => {
		const waffle = page.locator('.app-menu__waffle')
		await page.mouse.move(1200, 650)
		await waffle.hover()
		await expect(grid(page)).toBeVisible({ timeout: 3000 })
		await page.mouse.move(1200, 650)
		await expect(grid(page)).toBeHidden({ timeout: 3000 })

		await waffle.hover()
		await expect(grid(page)).toBeVisible({ timeout: 3000 })
		await waffle.click()
		await page.mouse.move(1200, 650)
		await page.waitForTimeout(800)
		await expect(grid(page)).toBeVisible()
		await closeMenu(page)
	})

	// @e2e apps-menu-opening::the-keyboard-opens-and-closes-the-menu
	test('the keyboard opens and closes the menu', async ({ page }) => {
		const waffle = page.locator('.app-menu__waffle')
		await waffle.focus()
		await page.keyboard.press('Enter')
		await expect(grid(page)).toBeVisible()
		expect(
			await page.evaluate(
				() => document.activeElement?.closest('.app-menu__popover') !== null,
			),
		).toBe(true)
		await page.keyboard.press('Escape')
		await expect(grid(page)).toBeHidden()
		await expect(waffle).toBeFocused()
	})

	// @e2e external-apps-in-the-app-menu::an-administrator-gives-two-addresses
	// @e2e external-apps-in-the-app-menu::an-address-that-is-not-one
	test('external apps from the menu settings are listed, a bad address is refused', async ({
		page,
	}) => {
		const before = await api(page, 'GET', SETTINGS)
		expect(before.status).toBe(200)
		const restore = before.json.defaults.externalApps
		const app = (id: string, title: string, url: string) => ({
			id,
			title,
			payoff: 'Code',
			url,
			icon: { library: 'link' },
			visible: true,
			order: 0,
		})
		try {
			const saved = await api(page, 'POST', DEFAULTS, {
				settings: {
					externalApps: [
						app('code', 'Forgejo', 'https://code.example.org'),
					],
				},
			})
			expect(saved.status).toBe(200)

			const refused = await api(page, 'POST', DEFAULTS, {
				settings: {
					externalApps: [app('chat', 'Element', 'javascript:alert(1)')],
				},
			})
			expect(refused.status).toBe(400)
			expect(refused.json.field).toBe('externalApps')

			await page.reload({ waitUntil: 'domcontentloaded' })
			await openByClick(page)
			const folded = (await toggle(page).count()) > 0
			if (folded) {
				await toggle(page).click()
			}
			// Listed after the Nextcloud apps (the spec). The section heading is
			// drawn only over the external apps below the fold, so it is there
			// only on an instance with more apps than the fold holds.
			const listed = await onScreen(page)
			const at = listed.indexOf('external-code')
			expect(at, 'Forgejo is listed').toBeGreaterThanOrEqual(0)
			expect(
				listed.slice(at).every((key) => key.startsWith('external-')),
				`Forgejo comes after the Nextcloud apps: ${listed.join(', ')}`,
			).toBe(true)
			if (folded) {
				await expect(
					grid(page).locator('[data-thematiq-row="external"] h2'),
				).toBeVisible()
			}
			const forgejo = entry(page, 'external-code')
			await expect(forgejo).toHaveAttribute('href', 'https://code.example.org')
			await expect(forgejo).toHaveAttribute('target', '_blank')
			await expect(forgejo).toHaveAttribute('rel', 'noopener noreferrer')
			await expect(
				forgejo.locator('.thematiq-app-menu__description'),
			).toHaveText(/\S/)
		} finally {
			await api(page, 'POST', DEFAULTS, {
				settings: { externalApps: restore },
			})
		}
	})
})
