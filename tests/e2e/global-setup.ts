/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Playwright globalSetup — logs into Nextcloud once and saves session.
 */
import { chromium, request, type FullConfig } from '@playwright/test'
import * as path from 'path'
import * as fs from 'fs'

import { resolveBaseUrl } from './base-url'

const AUTH_DIR = path.resolve(__dirname, '.auth')
const STORAGE_STATE = path.join(AUTH_DIR, 'admin.json')

async function ensureNextcloudReachable(baseURL: string): Promise<void> {
	const ctx = await request.newContext()
	try {
		const res = await ctx.get(`${baseURL}/status.php`, {
			failOnStatusCode: false,
		})
		if (!res.ok()) {
			throw new Error(
				`Nextcloud status.php returned ${res.status()} at ${baseURL}.`,
			)
		}
		const body = await res.json().catch(() => ({}))
		if (!body || body.installed !== true) {
			throw new Error(`Nextcloud at ${baseURL} is not installed.`)
		}
	} finally {
		await ctx.dispose()
	}
}

export default async function globalSetup(config: FullConfig): Promise<void> {
	// The literal that stood here was a second entrance: it logged in before
	// any spec ran, so it reached the shared instance even when the config had
	// been pointed elsewhere. Same resolver, same guard.
	const baseURL =
		(config.projects[0]?.use?.baseURL as string | undefined) ?? resolveBaseUrl()
	const username = process.env.NC_ADMIN_USER ?? 'admin'
	const password = process.env.NC_ADMIN_PASS ?? 'admin'

	await ensureNextcloudReachable(baseURL)
	fs.mkdirSync(AUTH_DIR, { recursive: true })

	const browser = await chromium.launch()
	const context = await browser.newContext({ baseURL })
	const page = await context.newPage()

	// `domcontentloaded`, not the default `load`: this instance keeps long-lived
	// requests open, so waiting for the full load event times out on a page that
	// is already interactive.
	await page.goto('/index.php/login', { waitUntil: 'domcontentloaded' })
	// The Nextcloud login form is client-rendered (Vue): the inputs do not
	// exist in the initial HTML, so filling immediately after goto() races the
	// hydration and fails with "element not found". Wait for the real field.
	const userField = page.locator('input[name="user"]')
	await userField.waitFor({ state: 'visible', timeout: 30_000 })
	await userField.fill(username)
	await page.locator('input[name="password"]').fill(password)
	// `noWaitAfter` because Nextcloud lands on the Dashboard, whose widgets keep
	// issuing requests long after the page is usable. Playwright's default
	// post-click wait for "scheduled navigations to finish" therefore hangs even
	// though the click and the navigation both already succeeded — the call log
	// shows the redirect completing and the click timing out anyway.
	await page.locator('button[type="submit"]').first().click({ noWaitAfter: true })

	// Wait for NAVIGATION, not for an element.
	//
	// This previously waited for `#header, header.header` to be visible and hung
	// for the full timeout while the log said "locator resolved to visible
	// <header id="header">" — a contradiction that made the harness look dead.
	// The cause is that Nextcloud's post-login shell mounts in Vue and detaches
	// and re-attaches #header while it settles, so the visibility check never
	// finds a stable frame to resolve against, however plainly the element is
	// there. Waiting on the URL leaving /login is a signal that cannot be
	// re-rendered out from under the check.
	// Polled rather than `waitForURL`: because the click above is `noWaitAfter`,
	// the redirect has usually ALREADY happened by the time we get here, and
	// waitForURL then sits waiting for a further navigation that never comes —
	// timing out while its own log says "navigated to /apps/dashboard/". Reading
	// the current URL has no such ordering assumption.
	const deadline = Date.now() + 60_000
	while (Date.now() < deadline) {
		if (!/\/login(\?|$|\/)/.test(page.url())) break
		await page.waitForTimeout(500)
	}

	const currentUrl = page.url()
	if (/\/login(\?|$|\/)/.test(currentUrl)) {
		throw new Error(`Login failed — still on ${currentUrl}.`)
	}
	// A last sanity check that the shell rendered — but NOT a gate. Leaving
	// /login already proves the credentials worked and the session cookie is
	// set, which is the only thing this setup exists to produce. On a loaded
	// instance the Dashboard can take longer than any timeout worth hard-coding,
	// and failing here would abort every suite over a slow render rather than a
	// real problem. Warn and continue.
	try {
		await page
			.locator('#header, header.header')
			.first()
			.waitFor({ state: 'attached', timeout: 30_000 })
	} catch {
		console.warn(
			'[global-setup] logged in, but the app shell had not rendered yet — continuing',
		)
	}

	await context.storageState({ path: STORAGE_STATE })
	await browser.close()
}
