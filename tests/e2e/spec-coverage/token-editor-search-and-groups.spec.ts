/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The token editor's search field, its collapsed component groups and the
 * Advanced group's warning, as an administrator meets them on the Theming
 * page. The selectors are the ones js/admin.js builds: the search field
 * `#nldesign-token-search`, a group `.nldesign-token-group[data-group]` with
 * its disclosure button `.nldesign-token-group-toggle` and its panel, and a
 * row `[data-token-row="<token>"]`.
 *
 * These tests only read the page and type into the search field; they save
 * nothing, so they change no state on the instance.
 */
import { test, expect, type Page } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

/** The date picker's hover colour, owned by the date picker group. */
const DP_HOVER = '--nldesign-nc-dp-hover-color'

/** Open the Theming page and wait until the token editor is drawn. */
async function openEditor(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await expect(page.locator('#nldesign-token-search')).toBeAttached({
		timeout: 30_000,
	})
}

/** A component group by its id. */
function group(page: Page, id: string) {
	return page.locator(`.nldesign-token-group[data-group="${id}"]`)
}

test.describe('token editor search and groups', () => {
	test(
		// @e2e token-editor-ui::the-search-field-has-a-label
		'the search field is named for what it searches',
		async ({ page }) => {
			await openEditor(page)
			const field = page.locator('#nldesign-token-search')
			await field.focus()
			await expect(field).toBeFocused()
			await expect(field).toHaveAccessibleName(/search tokens/i)
		},
	)

	test(
		// @e2e token-editor-ui::search-by-css-name
		'a part of a CSS name finds the token and hides the groups without a match',
		async ({ page }) => {
			await openEditor(page)
			await page.locator('#nldesign-token-search').fill('dp-hover')

			await expect(
				page.locator(`[data-token-row="${DP_HOVER}"]`),
			).toBeVisible()
			// Every group still shown holds a match; at least one group is hidden.
			const groups = page.locator('.nldesign-token-group')
			const count = await groups.count()
			let hidden = 0
			for (let i = 0; i < count; i++) {
				const g = groups.nth(i)
				if (await g.isHidden()) {
					hidden++
					continue
				}
				await expect(
					g.locator('.nldesign-token-row:visible').first(),
					`group ${await g.getAttribute('data-group')} is shown without a match`,
				).toBeVisible()
			}
			expect(hidden, 'groups without a match are hidden').toBeGreaterThan(0)
		},
	)

	test(
		// @e2e token-editor-ui::page-load-builds-no-groups-rows
		'on load no collapsed group has rows in the document',
		async ({ page }) => {
			await openEditor(page)
			await expect(
				page.locator('.nldesign-token-group').first(),
			).toBeAttached()
			await expect(
				page.locator('.nldesign-token-group-panel .nldesign-token-row'),
			).toHaveCount(0)
		},
	)

	test(
		// @e2e token-editor-ui::the-date-picker-has-its-own-group
		"the date picker group lists its hover colour under the component's own name",
		async ({ page }) => {
			await openEditor(page)
			const datePicker = group(page, 'date-picker')
			await datePicker.locator('.nldesign-token-group-toggle').click()
			const row = datePicker.locator(`[data-token-row="${DP_HOVER}"]`)
			await expect(row).toBeVisible()
			await expect(row.locator('.nldesign-token-label')).toHaveText(
				'--dp-hover-color',
			)
		},
	)

	test(
		// @e2e token-editor-ui::the-group-starts-collapsed
		'the Advanced group starts collapsed',
		async ({ page }) => {
			await openEditor(page)
			const advanced = group(page, 'advanced')
			await expect(
				advanced.locator('.nldesign-token-group-toggle'),
			).toHaveAttribute('aria-expanded', 'false')
			await expect(
				advanced.locator('.nldesign-token-group-panel'),
			).toBeHidden()
		},
	)

	test(
		// @e2e token-editor-ui::opening-shows-the-warning-first
		'opening the Advanced group shows the warning, in words, above the first field',
		async ({ page }) => {
			await openEditor(page)
			const advanced = group(page, 'advanced')
			await advanced.locator('.nldesign-token-group-toggle').click()
			await expect(
				advanced.locator('.nldesign-token-group-toggle'),
			).toHaveAttribute('aria-expanded', 'true')
			const warning = advanced.locator('.nldesign-token-advanced-warning')
			await expect(warning).toBeVisible()
			// In text, not colour alone.
			await expect(warning).toContainText(/layout/i)

			const order = await advanced
				.locator('.nldesign-token-group-panel')
				.evaluate((panel) => {
					const note = panel.querySelector(
						'.nldesign-token-advanced-warning',
					)
					const field = panel.querySelector('input, select, textarea')
					if (note === null || field === null) {
						return 'missing'
					}
					return note.compareDocumentPosition(field)
						& Node.DOCUMENT_POSITION_FOLLOWING
						? 'warning-first'
						: 'field-first'
				})
			expect(order).toBe('warning-first')
		},
	)

	test(
		// @e2e token-editor-ui::a-note-is-shown
		'a row shows its inventory note',
		async ({ page }) => {
			await openEditor(page)
			await page.locator('#nldesign-token-search').fill('Selected text color')
			const row = page
				.locator('[data-token-row="--nldesign-nc-color-text-selection"]')
				.first()
			await expect(row).toBeVisible()
			await expect(row.locator('.nldesign-token-note')).toContainText(
				'Selected text',
			)
		},
	)
})
