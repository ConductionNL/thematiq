/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Load tolerance for the e2e suite (#181).
 *
 * A saturated Apache answers 503 to ordinary requests, static assets
 * included, while `status.php` keeps answering 200. Specs that run into that
 * fail with "element not found" or a navigation timeout on pages that exist,
 * and the failure says nothing about the product. Two things here keep that
 * apart from a real regression:
 *
 * - `waitForCalmInstance()` runs once in global setup. It does not start the
 *   suite until the instance has answered a page asset with 200 several times
 *   in a row, so a run never begins inside someone else's load spike. When the
 *   instance stays busy it fails with a message that names the cause.
 * - `retriesFor()` gives `playwright.config.ts` its retry count: none in CI,
 *   where a retry would let a flaky test pass, and one on a developer box,
 *   where a single 503 is the likeliest reason a passing test fails.
 */

import { request } from '@playwright/test'

/** An asset every Nextcloud serves, cheap enough to poll. */
const PROBE_PATHS = ['/status.php', '/core/img/logo/logo.svg'] as const

/** Consecutive healthy rounds before the suite may start. */
const CALM_ROUNDS = 3

/**
 * Wait until the instance answers every probe with 200, CALM_ROUNDS times in a row.
 *
 * @param {string} baseURL The instance under test.
 * @param {number} timeoutMs How long to wait before giving up.
 * @param {number} intervalMs The pause between rounds.
 * @throws {Error} When the instance is still answering 5xx after the timeout.
 * @return {Promise<void>}
 */
export async function waitForCalmInstance(
	baseURL: string,
	timeoutMs = Number(process.env.PW_CALM_TIMEOUT_MS ?? 120_000),
	intervalMs = 2_000,
): Promise<void> {
	const ctx = await request.newContext()
	const deadline = Date.now() + timeoutMs
	let healthy = 0
	let last = ''
	try {
		while (healthy < CALM_ROUNDS) {
			const statuses: number[] = []
			for (const probe of PROBE_PATHS) {
				const res = await ctx
					.get(`${baseURL}${probe}`, { failOnStatusCode: false, timeout: 15_000 })
					.catch(() => null)
				statuses.push(res === null ? 0 : res.status())
			}
			last = PROBE_PATHS.map((p, i) => `${p}=${statuses[i]}`).join(' ')
			healthy = statuses.every((s) => s === 200) ? healthy + 1 : 0
			if (healthy >= CALM_ROUNDS) {
				break
			}
			if (Date.now() > deadline) {
				throw new Error(
					`The instance at ${baseURL} is still overloaded after ${timeoutMs} ms (${last}). `
						+ 'Its web server is refusing requests, so specs would fail for that reason '
						+ 'and not for the product. Wait until it is calm, or raise PW_CALM_TIMEOUT_MS.',
				)
			}
			await new Promise((resolve) => setTimeout(resolve, intervalMs))
		}
	} finally {
		await ctx.dispose()
	}
}

/**
 * The retry count for this run.
 *
 * @param {NodeJS.ProcessEnv} env The environment to read.
 * @return {number} 0 in CI, else PW_RETRIES (default 1).
 */
export function retriesFor(env: NodeJS.ProcessEnv = process.env): number {
	if (Boolean(env.CI) || Boolean(env.GITHUB_ACTIONS)) {
		return 0
	}
	const requested = Number(env.PW_RETRIES ?? 1)
	return Number.isInteger(requested) && requested >= 0 ? requested : 1
}
