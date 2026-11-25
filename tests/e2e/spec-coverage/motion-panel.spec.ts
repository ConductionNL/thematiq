/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Motion tokens (TqBeweging), the panel of the Theming page at #motion: it shows the
 * overview's token editor with only its three motion rows, plays the preview with their
 * values, carries Download and Save in the header, and gives the editor back on close.
 * Read-only: nothing here saves.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('the motion tokens panel', () => {
	test('shows the three motion rows of the token editor and nothing else of it', async ({
		page,
	}) => {
		await page.goto(THEMING_URL + '#motion')
		const panel = page.locator('section[data-thematiq-panel="motion"]')
		await expect(panel).toBeVisible()
		await expect(
			page.locator('#thematiq-page-header .thematiq-page-header__title'),
		).toHaveText('Motion tokens')
		await expect(
			panel.locator('[data-token-row="--animation-quick"]'),
		).toBeVisible()
		await expect(page).toHaveURL(/#motion$/)
		await expect(
			panel.locator('[data-token-row="--animation-quick"]'),
		).toBeVisible()
		await expect(
			panel.locator('[data-token-row="--animation-slow"]'),
		).toBeVisible()
		await expect(
			panel.locator('[data-token-row="--nldesign-animation-easing"]'),
		).toBeVisible()
		await expect(
			panel.locator('[data-token-row="--color-primary"]'),
		).toBeHidden()
		await expect(panel.locator('#nldesign-token-search')).toBeHidden()
		await expect(panel.locator('#nldesign-base-unlock')).toBeAttached()
		await expect(panel.locator('.thematiq-motion-panel__notice')).toContainText(
			'Nextcloud base tokens',
		)
	})

	test('Play motion reads the rows into the readout', async ({ page }) => {
		await page.goto(THEMING_URL + '#motion')
		const panel = page.locator('section[data-thematiq-panel="motion"]')
		await panel.getByRole('button', { name: 'Play motion' }).click()
		await expect(panel.locator('.thematiq-motion-panel__readout')).toContainText(
			/Duration \d+ ms, easing /,
		)
	})

	test('the header has Download and Save, and the components panel leads here', async ({
		page,
	}) => {
		await page.goto(THEMING_URL + '#components')
		const actions = page.locator(
			'#thematiq-page-header .thematiq-page-header__actions',
		)
		await actions.locator('.thematiq-page-header__more').click()
		await actions.getByRole('menuitem', { name: /Motion tokens/ }).click()
		await expect(page).toHaveURL(/#motion$/)
		await expect(
			actions.getByRole('button', { name: 'Download', exact: true }),
		).toBeVisible()
		await expect(
			actions.getByRole('button', { name: 'Save', exact: true }),
		).toBeVisible()
	})

	test('closing the panel gives the token editor back to the overview', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		await page.evaluate(() => {
			window.location.hash = 'motion'
		})
		await expect(
			page.locator(
				'section[data-thematiq-panel="motion"] #nldesign-token-editor',
			),
		).toBeAttached()
		await page.goBack()
		await expect(
			page.locator('section[data-thematiq-panel="motion"]'),
		).toBeHidden()
		await expect(
			page.locator('#nldesign-settings > #nldesign-token-editor'),
		).toBeAttached()
	})
})
