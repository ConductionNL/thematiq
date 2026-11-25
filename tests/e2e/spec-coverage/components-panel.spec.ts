/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Components and tokens (TqComponenten), the panel of the Theming page at
 * #components, and #components/own on Your components (TqEigenComponent): it
 * shows the overview's component selector, preview and token editor, its
 * header carries Download, More and Save, and closing it gives the instrument
 * back to the overview. Read-only: nothing here saves.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('the components and tokens panel', () => {
	test('shows the instrument, and a chip filters the editor to that component', async ({ page }) => {
		await page.goto(THEMING_URL + '#components')
		const panel = page.locator('section[data-thematiq-panel="components"]')
		await expect(panel).toBeVisible()
		await expect(page.locator('#thematiq-page-header .thematiq-page-header__title')).toHaveText('Components and tokens')
		await expect(panel.locator('.nldesign-pg-selector')).toBeVisible()
		await expect(panel.locator('#nldesign-preview')).toBeVisible()
		await expect(panel.locator('#nldesign-token-editor')).toBeVisible()
		await expect(page.locator('#thematiq-token-sets')).toBeHidden()

		const chip = panel.locator('.nldesign-pg-chip').nth(1)
		await chip.click()
		await expect(chip).toHaveAttribute('aria-pressed', 'true')
	})

	test('the header has Download and Save, and a More menu that Escape closes', async ({ page }) => {
		await page.goto(THEMING_URL + '#components')
		const actions = page.locator('#thematiq-page-header .thematiq-page-header__actions')
		await expect(actions.getByRole('button', { name: 'Download', exact: true })).toBeVisible()
		await expect(actions.getByRole('button', { name: 'Save', exact: true })).toBeVisible()
		const more = actions.locator('.thematiq-page-header__more')
		await more.click()
		const menu = actions.locator('.thematiq-page-header__menu')
		await expect(menu).toBeVisible()
		await expect(menu.getByRole('menuitem')).toHaveCount(5)
		await page.keyboard.press('Escape')
		await expect(menu).toBeHidden()
		await expect(more).toBeFocused()
	})

	test('closing the panel gives the instrument back to the overview', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		await page.evaluate(() => {
			window.location.hash = 'components'
		})
		await expect(page.locator('section[data-thematiq-panel="components"] #nldesign-token-editor')).toBeVisible()
		await page.goBack()
		await expect(page.locator('section[data-thematiq-panel="components"]')).toBeHidden()
		await expect(page.locator('#nldesign-settings > #nldesign-token-editor')).toBeAttached()
		await expect(page.locator('#nldesign-settings > #nldesign-preview')).toBeAttached()
	})

	test('#components/own opens Your components, and More leads there', async ({ page }) => {
		await page.goto(THEMING_URL + '#components/own')
		const panel = page.locator('section[data-thematiq-panel="components"]')
		const own = panel.locator('.nldesign-pg-selector .nldesign-tab-btn[data-tab="own"]')
		await expect(own).toHaveClass(/active/)

		await page.goto(THEMING_URL + '#components')
		await expect(panel.locator('.nldesign-pg-selector')).toBeVisible()
		const actions = page.locator('#thematiq-page-header .thematiq-page-header__actions')
		await actions.locator('.thematiq-page-header__more').click()
		await actions.getByRole('menuitem', { name: /Own component/ }).click()
		await expect(page).toHaveURL(/#components\/own$/)
		await expect(own).toHaveClass(/active/)
	})
})
