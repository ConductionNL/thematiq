/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/lasuite-shell-geometry/specs/lasuite-stack/spec.md
 *
 * Shell geometry of the `lasuite` set, measured in the browser against La
 * Suite Docs' numbers (suitenumerique/docs at 9de17b3f, see the header of
 * css/systems/lasuite/shell-nc35.css): a 64px header row, a 300px left panel,
 * content flush under the header.
 *
 * The header height is version-scoped: the layer loads on Nextcloud 35 only.
 * The tests that assert 64px read the server major first and, on another
 * major, assert the stock behaviour instead (no shell stylesheet linked), so
 * they prove something on every server and never skip.
 *
 * GLOBAL STATE: activating a set mutates the instance-wide active token set.
 * The prior set is read in beforeAll and restored in afterAll, the same way
 * lasuite-parity.spec.ts does it.
 */
import { test, expect, type Page } from '@playwright/test'
import { requestToken, getTokenSet, setTokenSet } from '../workflows/_helpers'

const SHELL_STYLESHEET = 'css/systems/lasuite/shell-nc35.css'
const SHELL_MAJORS = ['35']
const LASUITE_HEADER_HEIGHT = 64
const LASUITE_NAVIGATION_WIDTH = 300
const SEARCH_FIELD_HEIGHT = 34

/** The server's major version, as the page reports it. */
async function serverMajor(page: Page): Promise<string> {
	const version = await page.evaluate(() =>
		String((window as any).OC?.config?.version ?? ''),
	)
	expect(version, 'the page must expose OC.config.version').not.toBe('')
	return version.split('.')[0]
}

/** Whether the page links the shell geometry stylesheet. */
async function shellLinked(page: Page): Promise<boolean> {
	return await page.evaluate(
		(file) =>
			Array.from(
				document.querySelectorAll<HTMLLinkElement>('link[rel="stylesheet"]'),
			).some((link) =>
				new URL(link.href, location.href).pathname.endsWith(file),
			),
		SHELL_STYLESHEET,
	)
}

/** The rendered box of the first visible match, or fail. */
async function box(page: Page, selector: string) {
	const element = page.locator(selector).first()
	await expect(element, `${selector} must render`).toBeVisible()
	const rect = await element.boundingBox()
	expect(rect, `${selector} must have a box`).not.toBeNull()
	return rect!
}

test.describe('lasuite shell geometry', () => {
	test.describe.configure({ mode: 'serial' })

	let baselineTokenSet = ''

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage()
		await page.goto('/settings/admin/theming')
		const token = await requestToken(page)
		baselineTokenSet = await getTokenSet(page, token)
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		if (baselineTokenSet === '') {
			return
		}
		const page = await browser.newPage()
		await page.goto('/settings/admin/theming')
		const token = await requestToken(page)
		await setTokenSet(page, token, baselineTokenSet)
		await page.close()
	})

	/** Activate a set, then open a page with the full shell. */
	async function openWith(
		page: Page,
		set: string,
		path = '/apps/files/',
	): Promise<void> {
		await page.goto('/settings/admin/theming')
		const token = await requestToken(page)
		await setTokenSet(page, token, set)
		await page.goto(path)
		await page.waitForLoadState('domcontentloaded')
	}

	test(// @e2e openspec/specs/lasuite-stack/spec.md#on-nextcloud-35-the-lasuite-header-row-is-64px-tall
	'lasuite on Nextcloud 35 draws a 64px header row', async ({ page }) => {
		await openWith(page, 'lasuite')
		const header = await box(page, '#header')
		if (SHELL_MAJORS.includes(await serverMajor(page))) {
			expect(header.height).toBeCloseTo(LASUITE_HEADER_HEIGHT, 0)
		} else {
			expect(header.height).not.toBeCloseTo(LASUITE_HEADER_HEIGHT, 0)
		}
	})

	test(// @e2e openspec/specs/lasuite-stack/spec.md#the-content-starts-directly-under-the-taller-header
	'the content starts directly under the header', async ({ page }) => {
		await openWith(page, 'lasuite')
		const header = await box(page, '#header')
		const content = await box(page, '#content-vue')
		expect(Math.abs(content.y - (header.y + header.height))).toBeLessThanOrEqual(
			1,
		)
	})

	test(// @e2e openspec/specs/lasuite-stack/spec.md#the-search-field-stays-centred-in-the-header
	'the search field stays centred in the header', async ({ page }) => {
		await openWith(page, 'lasuite')
		const header = await box(page, '#header')
		const search = await box(page, '#header .unified-search-input')
		expect(search.height).toBeCloseTo(SEARCH_FIELD_HEIGHT, 0)
		const headerCentre = header.y + header.height / 2
		const searchCentre = search.y + search.height / 2
		expect(Math.abs(searchCentre - headerCentre)).toBeLessThanOrEqual(1)
	})

	test(// @e2e openspec/specs/lasuite-stack/spec.md#the-app-navigation-keeps-la-suite-docs-300px-width
	'the app navigation keeps the 300px La Suite Docs width', async ({ page }) => {
		await page.setViewportSize({ width: 1280, height: 800 })
		await openWith(page, 'lasuite')
		const navigation = await box(page, '#app-navigation-vue, .app-navigation')
		expect(navigation.width).toBeCloseTo(LASUITE_NAVIGATION_WIDTH, 0)
	})

	test(// @e2e openspec/specs/lasuite-stack/spec.md#the-shell-layer-loads-exactly-on-the-nextcloud-majors-it-lists
	'the shell stylesheet is linked exactly on the majors it lists', async ({
		page,
	}) => {
		await openWith(page, 'lasuite')
		const expected = SHELL_MAJORS.includes(await serverMajor(page))
		expect(await shellLinked(page)).toBe(expected)
		const headerHeight = await page.evaluate(() =>
			getComputedStyle(document.body)
				.getPropertyValue('--header-height')
				.trim(),
		)
		if (expected) {
			expect(headerHeight).toBe(`${LASUITE_HEADER_HEIGHT}px`)
		} else {
			expect(headerHeight).not.toBe(`${LASUITE_HEADER_HEIGHT}px`)
		}
	})

	test(// @e2e openspec/specs/lasuite-stack/spec.md#the-cunningham-sibling-keeps-the-stock-header-height
	'the cunningham sibling keeps the stock header height', async ({ page }) => {
		await openWith(page, 'cunningham')
		expect(await shellLinked(page)).toBe(false)
		const header = await box(page, '#header')
		expect(header.height).not.toBeCloseTo(LASUITE_HEADER_HEIGHT, 0)
	})
})
