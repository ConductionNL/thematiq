/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/token-set-picker-search/spec.md
 *
 * The search field above the token set list, on the real admin page: typing
 * hides the sets that do not match, the selected set stays, and Escape shows
 * every set again. Nothing is applied: the test never changes the selection.
 */
import { test, expect } from '@playwright/test'
import { adminContext } from './_fixtures'

test('the token set list can be searched by name', async ({ browser }) => {
	const context = await adminContext(browser)
	const page = await context.newPage()
	await page.goto('/settings/admin/theming')

	const search = page.locator('#nldesign-token-set-search')
	const select = page.locator('#nldesign-token-set-select')
	await expect(search).toBeVisible()
	const total = await select.locator('option').count()
	const selected = await select.inputValue()

	await search.fill('rijks')
	const shown = await select
		.locator('option:not([hidden])')
		.evaluateAll((options) => options.map((o) => (o as HTMLOptionElement).value))
	expect(shown).toContain('rijkshuisstijl')
	expect(shown).toContain(selected)
	expect(shown.length).toBeLessThan(total)
	await expect(page.locator('#nldesign-token-set-search-status')).toContainText(
		`of ${total}`,
	)
	expect(await select.inputValue(), 'searching changes no selection').toBe(
		selected,
	)

	await search.press('Escape')
	await expect(search).toHaveValue('')
	await expect(select.locator('option:not([hidden])')).toHaveCount(total)

	await context.close()
})
