/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright config for nldesign.
 * Base URL: resolved in tests/e2e/base-url.ts. It still defaults to
 * http://localhost:8080, but that is the shared dev instance and a run has to
 * name it before the suite will go there.
 * globalSetup logs in once and saves session to tests/e2e/.auth/admin.json.
 */
import { defineConfig } from '@playwright/test'
import * as path from 'path'

import { resolveBaseUrl } from './tests/e2e/base-url'

export default defineConfig({
	testDir: './tests/e2e',
	globalSetup: path.resolve(__dirname, 'tests/e2e/global-setup.ts'),
	timeout: 30_000,
	expect: { timeout: 10_000 },
	fullyParallel: false,
	retries: 0,
	workers: 1,
	// The shared CI job caps this suite at `timeout-minutes: 45`, and a job
	// cancelled by that cap produces NO verdict at all: Playwright never prints
	// its tally, the `if: failure()` trace upload never fires, and the run shows
	// as "cancelled", which reads like an infrastructure hiccup rather than a
	// suite that ran out of budget. Exiting on our own clock a few minutes early
	// means the tally and the artifacts always exist. Measured: the baseline run
	// (31086399980) executed 100 of 110 tests in 5.9m, so 38m is ~6x headroom
	// over the full suite and cannot mask a real regression — it can only turn a
	// silent cancellation into a reported timeout.
	globalTimeout: 38 * 60_000,
	reporter: [
		['list'],
		['html', { open: 'never', outputFolder: 'tests/e2e/playwright-report' }],
	],
	outputDir: 'tests/e2e/test-results',

	use: {
		// Resolved in tests/e2e/base-url.ts, which is also where the
		// shared-instance opt-in is checked. NEXTCLOUD_URL is still read there.
		baseURL: resolveBaseUrl(),
		// `on-first-retry` PAIRED WITH `retries: 0` writes zero traces, ever.
		// There is no first retry to trigger on, so every CI failure in this
		// repo's history has been debugged from a single screenshot and a stack
		// frame — the trace artifact the shared workflow faithfully uploads on
		// failure has always been empty of traces. `retain-on-failure` is the
		// setting that matches `retries: 0`: capture always, keep only what
		// failed. (Raising `retries` instead would ALSO have produced traces,
		// but at the price of letting a flake pass on the second attempt, which
		// is the opposite of what this suite is for.)
		trace: 'retain-on-failure',
		screenshot: 'only-on-failure',
		storageState: 'tests/e2e/.auth/admin.json',
	},

	projects: [
		{
			name: 'chromium',
			// Visual specs run only under the opt-in `visual` project (GAP-5).
			testIgnore: ['**/visual/**'],
			use: {
				storageState: 'tests/e2e/.auth/admin.json',
			},
		},
		// Visual-regression project (GAP-5). Opt-in / non-gating:
		//   PW_VISUAL=1 npx playwright test --project visual
		//   PW_VISUAL=1 npx playwright test --project visual --update-snapshots
		// Fixed viewport + authenticated session => deterministic shots.
		// Baselines live in tests/e2e/visual/*-snapshots/ and ARE committed.
		//
		// PLATFORM CAVEAT: PNG baselines are host-font/GPU specific, so a CI
		// Linux runner will not byte-match a dev-container baseline; the visual
		// project must regenerate its baselines in-CI before it can gate.
		//
		// The env gate is what makes "opt-in" true. It was not: the project was
		// declared unconditionally, and `npx playwright test` — which is exactly
		// what the shared CI job runs, with no `--project` — runs EVERY declared
		// project. `testIgnore: ['**/visual/**']` on chromium only stops chromium
		// picking the files up; it does nothing about the visual project running
		// them itself. So the first CI execution of this suite failed on
		// `theming-admin.png` with "Expected an image 1751px by 800px, received
		// 1280px by 800px" — a baseline captured on another host, gating a job
		// its own documentation says it cannot gate (run 30889958278).
		//
		// Not a skip and not a deleted test: with PW_VISUAL=1 the project is
		// declared and runs exactly as before. What changed is that the default
		// invocation no longer silently includes it.
		...(process.env.PW_VISUAL === '1'
			? [
					{
						name: 'visual',
						testMatch: /visual\/.*\.visual\.spec\.ts$/,
						use: {
							viewport: { width: 1280, height: 800 },
							storageState: 'tests/e2e/.auth/admin.json',
						},
						timeout: 90_000,
					},
				]
			: []),
	],
})
