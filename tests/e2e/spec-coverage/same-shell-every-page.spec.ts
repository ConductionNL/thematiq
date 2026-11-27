/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/same-shell-every-page/specs/workplace-layout/spec.md
 * @e2e openspec/changes/same-shell-every-page/specs/workplace-header-brand/spec.md
 *
 * No thematiq page differs from another (change same-shell-every-page): on a
 * real Nextcloud with the Zuiddrecht set, which carries the light layout and
 * the workplace bar, every app gets the boards' navigation rows, list rows and
 * sidebar, and the bar starts with the organisation and the page's name, Files
 * and Settings as much as Mail. The token set is put back through
 * withThemeState(). An app the instance does not have is reported as skipped
 * with its name, not passed.
 */
import { test, expect, type Page } from '@playwright/test'

import { api } from './_fixtures'
import { withThemeState } from './_theme-state'

const FILES = '/index.php/apps/files/'
const PERSONAL = '/index.php/settings/user'
const ADMIN = '/index.php/settings/admin'

/** Whether an app is enabled for the current user, read from its navigation entry. */
async function appEnabled(page: Page, appId: string): Promise<boolean> {
	const res = await api(
		page,
		'GET',
		'/ocs/v2.php/core/navigation/apps?format=json',
	)
	const entries: Array<{ id: string }> = res.json?.ocs?.data ?? []
	return entries.some((entry) => entry.id === appId)
}

/** The organisation over the page's name at the start of the bar, once the brand block is drawn. */
async function brandStart(
	page: Page,
): Promise<{ caption: string; name: string | null }> {
	const bar = page.locator('#header[data-thematiq-brand] .thematiq-brand').first()
	await expect(bar).toBeVisible()
	return bar.evaluate((link) => ({
		caption: (
			link.querySelector('.thematiq-brand__caption')?.textContent ?? ''
		).trim(),
		name:
			link.querySelector('.thematiq-brand__name')?.textContent?.trim() ?? null,
	}))
}

/** Every navigation entry's text sits inside its entry, and the page does not scroll sideways. */
async function layoutHolds(page: Page): Promise<void> {
	const overflow = await page.evaluate(
		() =>
			document.documentElement.scrollWidth
			- document.documentElement.clientWidth,
	)
	expect(overflow, 'the page scrolls sideways').toBeLessThanOrEqual(1)
	const clipped = await page.evaluate(() =>
		Array.from(
			document.querySelectorAll('#app-navigation-vue .app-navigation-entry'),
		)
			.filter((entry) => (entry as HTMLElement).offsetParent !== null)
			.filter((entry) => {
				const name = entry.querySelector('.app-navigation-entry__name')
				if (name === null) {
					return false
				}
				const outer = entry.getBoundingClientRect()
				const inner = name.getBoundingClientRect()
				return inner.top < outer.top - 1 || inner.bottom > outer.bottom + 1
			})
			.map((entry) => (entry.textContent ?? '').trim()),
	)
	expect(clipped, 'navigation entries whose text falls outside them').toEqual([])
}

test.describe('the same shell on every page', () => {
	test.describe.configure({ mode: 'serial' })

	test("@e2e Files has the boards' navigation", async ({ page, browser }) => {
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			await page.setViewportSize({ width: 1440, height: 900 })
			await page.goto(FILES, { waitUntil: 'domcontentloaded' })
			const entry = page
				.locator('#app-navigation-vue .app-navigation-entry')
				.first()
			await expect(entry).toBeVisible()
			const row = await entry.evaluate((el) => {
				const link = el.querySelector(
					'.app-navigation-entry-link',
				) as HTMLElement
				const name = el.querySelector(
					'.app-navigation-entry__name',
				) as HTMLElement
				return {
					height: el.getBoundingClientRect().height,
					fontSize: getComputedStyle(name ?? link).fontSize,
				}
			})
			expect(row.height).toBe(34)
			expect(row.fontSize).toBe('14px')
			const active = page
				.locator(
					'#app-navigation-vue .app-navigation-entry.active .app-navigation-entry__name',
				)
				.first()
			if ((await active.count()) > 0) {
				expect(
					await active.evaluate((el) => getComputedStyle(el).fontWeight),
				).toBe('600')
			}
			const brand = await brandStart(page)
			expect(brand.caption).not.toBe('')
			expect(brand.name).not.toBeNull()
		})
	})

	test("@e2e Contacts' list rows", async ({ page, browser }) => {
		await page.goto(PERSONAL, { waitUntil: 'domcontentloaded' })
		test.skip(
			!(await appEnabled(page, 'contacts')),
			'Contacts is not installed on this instance',
		)
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			await page.goto('/index.php/apps/contacts/', {
				waitUntil: 'domcontentloaded',
			})
			const row = page.locator('#app-content-vue .list-item').first()
			test.skip(
				(await row.count()) === 0,
				'Contacts has no contact to list on this instance',
			)
			await expect(row).toBeVisible()
			const look = await row.evaluate((el) => {
				const style = getComputedStyle(el)
				return {
					radius: style.borderTopLeftRadius,
					edge: style.borderBottomWidth,
				}
			})
			expect(look.radius).toBe('0px')
			expect(look.edge).toBe('1px')
		})
	})

	test('@e2e A help link in Settings keeps its underline', async ({
		page,
		browser,
	}) => {
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			await page.goto(ADMIN, { waitUntil: 'domcontentloaded' })
			const help = page
				.locator('#app-content .section a[href^="http"]')
				.first()
			test.skip(
				(await help.count()) === 0,
				'no help link in the administration settings of this instance',
			)
			const line = await help.evaluate(
				(el) => getComputedStyle(el).textDecorationLine,
			)
			expect(line).toContain('underline')
		})
	})

	test('@e2e The Files sidebar', async ({ page, browser }) => {
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			await page.setViewportSize({ width: 1440, height: 900 })
			await page.goto(FILES, { waitUntil: 'domcontentloaded' })
			const row = page.locator('tr.files-list__row').first()
			await expect(row).toBeVisible({ timeout: 60000 })
			await row
				.locator('.files-list__row-name')
				.first()
				.click({ button: 'right' })
			const details = page
				.getByRole('menuitem', {
					name: /details|open details|details openen/i,
				})
				.first()
			test.skip(
				(await details.count()) === 0,
				'the file menu offers no details entry on this instance',
			)
			await details.click()
			const title = page
				.locator('#app-sidebar-vue .app-sidebar-header__mainname')
				.first()
			await expect(title).toBeVisible()
			const look = await title.evaluate((el) => {
				const style = getComputedStyle(el)
				return { size: style.fontSize, weight: style.fontWeight }
			})
			expect(look.size).toBe('20px')
			expect(look.weight).toBe('700')
			const tab = page
				.locator('#app-sidebar-vue .app-sidebar-tabs__tab')
				.first()
			if ((await tab.count()) > 0) {
				expect(
					await tab.evaluate((el) => getComputedStyle(el).height),
				).toMatch(/^3[67]px$/)
			}
		})
	})

	test('@e2e Settings names its section', async ({ page, browser }) => {
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			await page.goto(PERSONAL, { waitUntil: 'domcontentloaded' })
			const brand = await brandStart(page)
			expect(brand.caption).not.toBe('')
			const expected = await page.evaluate(() => {
				try {
					const entries = (window as any).OCP.InitialState.loadState(
						'core',
						'settingsNavEntries',
						{},
					)
					const active = Object.values(entries ?? {}).find(
						(entry: any) => entry && entry.active === true,
					) as any
					return active ? String(active.name).trim() : null
				} catch (e) {
					return null
				}
			})
			expect(
				expected,
				'Nextcloud names the active settings section',
			).not.toBeNull()
			expect(brand.name).toBe(expected)
		})
	})

	test('@e2e The four apps in the light layout', async ({ page, browser }) => {
		await page.goto(PERSONAL, { waitUntil: 'domcontentloaded' })
		const pages: Array<[string, string, string | null]> = [
			['Files', FILES, 'files'],
			['Personal settings', PERSONAL, null],
			['Administration settings', ADMIN, null],
			['Talk', '/index.php/apps/spreed/', 'spreed'],
			['Contacts', '/index.php/apps/contacts/', 'contacts'],
		]
		const missing: string[] = []
		for (const [label, , app] of pages) {
			if (app !== null && !(await appEnabled(page, app))) {
				missing.push(label)
			}
		}
		await withThemeState(browser, { tokenSet: 'zuiddrecht' }, async () => {
			for (const width of [1440, 390]) {
				await page.setViewportSize({ width, height: 900 })
				for (const [label, url, app] of pages) {
					if (app !== null && missing.includes(label)) {
						continue
					}
					await test.step(`${label} at ${width}px`, async () => {
						await page.goto(url, { waitUntil: 'domcontentloaded' })
						await page.waitForLoadState('load')
						await layoutHolds(page)
						const brand = await brandStart(page)
						expect(brand.caption, label).not.toBe('')
						if (width === 1440) {
							const side = await page.evaluate(() => {
								const nav =
									document.querySelector('#app-navigation-vue')
								const content = document.querySelector(
									'#app-content-vue, #app-content',
								)
								if (nav === null || content === null) {
									return null
								}
								return (
									content.getBoundingClientRect().left
									>= nav.getBoundingClientRect().right - 1
								)
							})
							if (side !== null) {
								expect(
									side,
									`${label}: the content sits beside the navigation`,
								).toBe(true)
							}
						}
					})
				}
			}
		})
		test.info().annotations.push(
			...missing.map((label) => ({
				type: 'skipped',
				description: `${label} is not installed on this instance`,
			})),
		)
	})
})
