/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/action-colour-token/specs/action-colour/spec.md
 *
 * A token set without an action colour renders as it did before the action
 * colour existed: every shipped set, light and dark, in a real browser.
 *
 * The comparison lives in tests/css/check-action-colour-unset.mjs, which also
 * runs on its own (`npm run test:action-colour-unset`). This spec is its home
 * in CI, because the Playwright job is the one that has a browser. It reads
 * the stylesheets from this checkout and never opens the Nextcloud instance.
 */
import { expect, test } from '@playwright/test'

test('a token set without an action colour renders as before, in every set and both themes', async ({
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
	expect(
		result.blindControls,
		'the check could not see a declared action colour',
	).toEqual([])
})
