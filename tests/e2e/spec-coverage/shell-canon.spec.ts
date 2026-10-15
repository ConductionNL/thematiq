/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/shell-canon-check/specs/shell-canon-check/spec.md
 *
 * The shell against the canon the OpenProject plugin is built to, on the
 * Zuiddrecht set, the set the boards draw. The measurement lives in
 * tests/css/check-shell-canon.mjs, which also runs on its own against any
 * instance. The active set is put back afterwards.
 */
import { test, expect } from '@playwright/test'
import { adminContext } from './_fixtures'
import { applyThemeState, restoreThemeState } from './_theme-state'

test('the shell matches the canon', async ({ browser }) => {
	test.setTimeout(180_000)
	const { measure, judge } = await import('../../css/check-shell-canon.mjs')
	const context = await adminContext(browser)
	const page = await context.newPage()
	await page.setViewportSize({ width: 1440, height: 1000 })
	const snapshot = await applyThemeState(page, { tokenSet: 'zuiddrecht' })
	try {
		await page.goto('/apps/files/')
		const result = judge(await measure(page))
		await test.info().attach('canon', { body: JSON.stringify(result, null, 1), contentType: 'application/json' })
		expect(result.failures).toEqual([])
		expect(result.gaps).toEqual([])
		expect(result.matches.length).toBe(7)
	} finally {
		await restoreThemeState(page, snapshot)
		await context.close()
	}
})
