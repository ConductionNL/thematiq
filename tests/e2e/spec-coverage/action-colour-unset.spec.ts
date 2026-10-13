/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/action-colour-token/specs/action-colour/spec.md
 *
 * A token set without an action colour renders as it did before the action
 * colour existed, and a declared action colour reaches the filled primary
 * buttons and nothing else: every shipped set, light and dark, in a real browser.
 *
 * The comparison lives in tests/css/check-action-colour-unset.mjs, which also
 * runs on its own (`npm run test:action-colour-unset`). This spec is its home
 * in CI, because the Playwright job is the one that has a browser. It reads
 * the stylesheets from this checkout and never opens the Nextcloud instance.
 */
import { expect, test } from '@playwright/test'

test('unset, the action colour changes nothing; set, it reaches the main button and nothing else', async ({
	browser,
}) => {
	test.setTimeout(300_000)
	const { compare } = await import('../../css/check-action-colour-unset.mjs')

	const result = await compare(browser, { workers: 2 })

	expect(result.sets).toBeGreaterThan(60)
	expect(result.values).toBeGreaterThan(100_000)
	expect(
		result.leftovers,
		'a stylesheet reads the action colour without a fallback',
	).toEqual([])
	expect(result.differences.slice(0, 20), 'a set renders differently').toEqual([])
	// With an action colour declared: the filled primary buttons wear it, and nothing else does.
	expect(
		result.reached.length,
		'no button took a declared action colour',
	).toBeGreaterThan(5)
	expect(
		result.notReached.slice(0, 20),
		'a primary button did not take the action colour',
	).toEqual([])
	expect(
		result.leaked.slice(0, 20),
		'something other than a primary button changed with the action colour',
	).toEqual([])
})
