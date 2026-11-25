/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A token set from the inside (TqTokenSet), the panel of the Theming page at
 * #set/<id>: opened from a row of the Token sets table, it lists the set's
 * tokens per tab with their contrast, searches them, opens the More menu and
 * goes back to the overview with the browser's back button. Read-only: nothing
 * here changes a setting.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('the token set panel', () => {
	test('opens from the Token sets table and lists the set\'s tokens', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const table = page.locator('#thematiq-token-sets')
		await table.locator('.thematiq-token-sets__search').fill('Zuiddrecht')
		await table.getByRole('link', { name: 'Gemeente Zuiddrecht' }).click()

		await expect(page).toHaveURL(/#set\/zuiddrecht$/)
		const panel = page.locator('section[data-thematiq-panel="set"]')
		await expect(panel).toBeVisible()
		await expect(page.locator('#thematiq-page-header .thematiq-page-header__title')).toHaveText('Gemeente Zuiddrecht')
		await expect(page.locator('#thematiq-page-header .thematiq-page-header__intro')).toContainText('NL Design System')
		await expect(page.locator('#thematiq-token-sets')).toBeHidden()

		const primary = panel.locator('.thematiq-set-panel__row', { hasText: '--nldesign-color-primary' }).first()
		await expect(primary.locator('.thematiq-set-panel__value')).toHaveText('#3669A5')
		await expect(primary.locator('.thematiq-set-panel__pill')).toHaveText('5.6 : 1')
		await expect(panel.locator('.thematiq-set-panel__summary')).not.toBeEmpty()
		await expect(panel.locator('.thematiq-set-panel__facts')).toContainText('Light workplace, menu 264 px')
		await expect(panel.locator('.thematiq-set-panel__stripe')).toBeVisible()
	})

	test('switches tabs, searches and opens the More menu', async ({ page }) => {
		await page.goto(THEMING_URL + '#set/zuiddrecht')
		const panel = page.locator('section[data-thematiq-panel="set"]')
		await expect(panel).toBeVisible()

		await panel.getByRole('tab', { name: /^Website/ }).click()
		await expect(panel.getByRole('tab', { name: /^Website/ })).toHaveAttribute('aria-selected', 'true')
		await expect(panel.locator('.thematiq-set-panel__groups')).toContainText('--nldesign-website-')

		await panel.getByRole('tab', { name: /^Colours/ }).click()
		const search = panel.locator('.thematiq-set-panel__search')
		await search.fill('--nldesign-color-link-hover')
		await expect(panel.locator('.thematiq-set-panel__row')).toHaveCount(1)
		await search.fill('')

		const more = page.locator('#thematiq-page-header .thematiq-set-panel__more')
		await more.click()
		const menu = page.locator('#thematiq-set-panel-menu')
		await expect(menu).toBeVisible()
		await expect(menu.getByRole('menuitem', { name: /Download token overview/ })).toHaveAttribute('href', /format=md&download=1/)
		await page.keyboard.press('Escape')
		await expect(menu).toBeHidden()
		await expect(more).toBeFocused()
	})

	test('the browser\'s back button returns to the overview', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		await page.evaluate(() => {
			window.location.hash = 'set/zuiddrecht'
		})
		await expect(page.locator('section[data-thematiq-panel="set"]')).toBeVisible()
		await page.goBack()
		await expect(page.locator('section[data-thematiq-panel="set"]')).toBeHidden()
		await expect(page.locator('#thematiq-token-sets')).toBeVisible()
	})
})
