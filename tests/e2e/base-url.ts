/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The one place the e2e suite learns which Nextcloud it talks to.
 *
 * Two places used to resolve the target on their own, and they could disagree.
 * `playwright.config.ts` read `process.env.NEXTCLOUD_URL || 'http://localhost:8080'`
 * and `tests/e2e/global-setup.ts` repeated the same expression for the login it
 * performs before any spec runs. Both now call `resolveBaseUrl()`, so a target
 * enters this suite once.
 *
 * The default is unchanged on purpose. This module adds a guard, it does not
 * change which instance an unset environment picks: with nothing set the suite
 * still resolves `http://localhost:8080`, and `assertInstancePermitted()` then
 * refuses it unless the run named that origin. An accident that used to be
 * silent is now an error you can read.
 *
 * Every name the fleet uses is accepted. The shared `ConductionNL/.github`
 * quality workflow exports `BASE_URL`, `NEXTCLOUD_URL` and `NC_BASE_URL`, and a
 * sibling repo that read `PLAYWRIGHT_BASE_URL` only hard-failed every CI run
 * because of it.
 */

import { assertInstancePermitted } from './shared-instance'

/** Environment variables that may carry the target, in priority order. */
const CANDIDATES = [
	'PLAYWRIGHT_BASE_URL',
	'NEXTCLOUD_URL',
	'NC_BASE_URL',
	'BASE_URL',
] as const

/** The target used when the environment names none. Unchanged by this module. */
const DEFAULT_BASE_URL = 'http://localhost:8080'

/**
 * Resolve the base URL of the Nextcloud under test.
 *
 * @throws {Error} When the resolved URL is the shared development instance and
 * the run did not name it in the opt-in variable.
 * @return {string} The base URL, without a trailing slash.
 */
export function resolveBaseUrl(): string {
	let target = DEFAULT_BASE_URL
	for (const name of CANDIDATES) {
		const value = process.env[name]
		if (value && value.trim() !== '') {
			target = value.trim()
			break
		}
	}

	return assertInstancePermitted(target.replace(/\/+$/, ''))
}
