/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The menu shape on every icon of the apps menu and the top bar (change
 * header-buttons-follow-shape, apps-menu-settings REQ-TQMS-001), on a real
 * Nextcloud with the Zuiddrecht set, which carries the workplace header. The
 * administrator's shape is put back in afterAll.
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
const PAGE = '/index.php/settings/user'

const bell = (page: Page): Locator =>
	page.locator('#header #notifications .header-menu__trigger').first()
const contacts = (page: Page): Locator =>
	page.locator('#header #contactsmenu .header-menu__trigger').first()
const plus = (page: Page): Locator =>
	page.locator('#header .thematiq-quick-add__button').first()
const grid = (page: Page): Locator =>
	page.locator('.app-menu__grid[data-thematiq-app-menu]')

/** The computed radius of `--border-radius-element`, as the menu's square icons use it. */
async function squareRadius(page: Page): Promise<string> {
	return page.evaluate(() => {
		const probe = document.createElement('div')
		probe.style.borderRadius = 'var(--border-radius-element, 8px)'
		document.body.appendChild(probe)
		const radius = getComputedStyle(probe).borderTopLeftRadius
		probe.remove()
		return radius
	})
}

async function radiusOf(target: Locator): Promise<string> {
	return target.evaluate((el) => getComputedStyle(el).borderTopLeftRadius)
}

/** Hover a button and read the radius its hover background is drawn with. */
async function hoverRadius(page: Page, target: Locator): Promise<string> {
	await target.hover()
	const radius = await radiusOf(target)
	await page.mouse.move(0, 0)
	return radius
}

async function setShape(page: Page, shape: string): Promise<void> {
	const saved = await api(page, 'POST', DEFAULTS, { settings: { shape } })
	expect(saved.status).toBe(200)
	await page.reload({ waitUntil: 'domcontentloaded' })
	await expect(page.locator('html')).toHaveAttribute('data-thematiq-shape', shape)
}

test.describe('the menu shape on every icon of the menu and the bar', () => {
	test.describe.configure({ mode: 'serial' })

	let snapshot: ThemeSnapshot | null = null
	let before = 'round'

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			const page = await ctx.newPage()
			snapshot = await applyThemeState(page, { tokenSet: 'zuiddrecht' })
			await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
			before = (await api(page, 'GET', SETTINGS)).json.effective.shape
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		try {
			const page = await ctx.newPage()
			await page.goto(PAGE, { waitUntil: 'domcontentloaded' })
			await api(page, 'POST', DEFAULTS, { settings: { shape: before } })
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

	// @e2e apps-menu-settings::square-buttons-in-the-bar
	test('square: the bell, the contacts menu and the Create button hover as a rounded square', async ({
		page,
	}) => {
		await setShape(page, 'square')
		const square = await squareRadius(page)

		await expect(bell(page)).toBeVisible()
		expect(await hoverRadius(page, bell(page))).toBe(square)
		await expect(contacts(page)).toBeVisible()
		expect(await hoverRadius(page, contacts(page))).toBe(square)
		await expect(plus(page)).toBeVisible()
		expect(await hoverRadius(page, plus(page))).toBe(square)
	})

	// The contacts menu ships without a hover background in Nextcloud; the
	// bar gives it the one its other icon buttons have.
	test('the contacts menu shows a hover background', async ({ page }) => {
		await expect(contacts(page)).toBeVisible()
		const rest = await contacts(page).evaluate(
			(el) => getComputedStyle(el).backgroundColor,
		)
		await contacts(page).hover()
		const hovered = await contacts(page).evaluate(
			(el) => getComputedStyle(el).backgroundColor,
		)
		expect(hovered).not.toBe('rgba(0, 0, 0, 0)')
		expect(hovered).not.toBe(rest)
	})

	// @e2e apps-menu-settings::square-icons-everywhere-in-the-menu
	test('square: every icon in the open menu is a rounded square', async ({
		page,
	}) => {
		await setShape(page, 'square')
		const square = await squareRadius(page)

		await page.locator('.app-menu__waffle').click()
		await expect(grid(page)).toBeVisible()
		const icons = grid(page).locator(
			'.app-item__circle, .thematiq-app-menu__create-icon',
		)
		await expect(icons.first()).toBeVisible()
		const radii = await icons.evaluateAll((els) =>
			els
				.filter((el) => (el as HTMLElement).offsetParent !== null)
				.map((el) => ({
					what: el.className,
					radius: getComputedStyle(el).borderTopLeftRadius,
				})),
		)
		expect(radii.length).toBeGreaterThan(0)
		for (const icon of radii) {
			expect(icon.radius, icon.what).toBe(square)
		}
	})

	// @e2e apps-menu-settings::round-again
	test('round again: the bell, the contacts menu and the Create button hover round', async ({
		page,
	}) => {
		await setShape(page, 'round')

		await expect(bell(page)).toBeVisible()
		expect(await hoverRadius(page, bell(page))).toBe('22px')
		expect(await hoverRadius(page, contacts(page))).toBe('22px')
		await expect(plus(page)).toBeVisible()
		expect(await hoverRadius(page, plus(page))).toBe('22px')
	})
})
