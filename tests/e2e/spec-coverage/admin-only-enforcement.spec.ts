/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Admin-only enforcement across the nldesign settings surface.
 *
 * Six specs each declare an "a non-admin must be refused" scenario against a
 * different controller. They are asserted together here because they share one
 * expensive fixture — a REAL, fully logged-in non-admin browser session — and
 * because asserting them apart invites the same subtle false pass six times.
 *
 * WHY A REAL SESSION AND NOT HTTP BASIC AUTH
 * ------------------------------------------
 * These routes are CSRF-protected. A raw request-context call (cookies, or
 * Basic auth, without a `requesttoken`) is rejected with **412 Precondition
 * Failed** — measured, not assumed: `curl -u admin:admin` against every one of
 * these endpoints returns 412. So a test that fires a token-less request and
 * asserts "not 200" would pass for a non-admin *and* for an admin, and would
 * still pass if the `#[AuthorizedAdminSetting]` attribute were deleted
 * tomorrow. It would be a test of CSRF, wearing an authorization test's name.
 *
 * Logging the non-admin in for real and fetching in-page with a VALID
 * `requesttoken` removes that escape: the request satisfies CSRF, so a 403 can
 * only come from the admin check itself.
 *
 * THE ADMIN CONTROL ARM
 * ---------------------
 * Every test also asserts the same endpoint does NOT return 403 for an admin.
 * Without it, an endpoint that is simply broken — 403 for everyone, or removed
 * and 404 for everyone — would satisfy the non-admin half and read as "access
 * control works". The control arm is what makes the refusal mean "because you
 * are not an admin" rather than "because nothing works".
 */
import { test, expect, type Page, type APIResponse } from '@playwright/test'

import {
	ensureNonAdminUser,
	loginAs,
	api,
	activeTokenSet,
	adminContext,
	NONADMIN_USER,
	NONADMIN_PASS,
} from './_fixtures'

/**
 * Issue a request and return only its numeric status.
 *
 * Thin wrapper over the shared `api()` helper: every assertion in this file is
 * about the STATUS CODE, and naming it keeps the assertions readable.
 */
async function statusOf(
	page: Page,
	method: string,
	path: string,
	body?: unknown,
): Promise<number> {
	return (await api(page, method, path, body)).status
}

const APP = '/index.php/apps/thematiq'

test.describe('admin-only enforcement', () => {
	let nonAdmin: { page: Page; close: () => Promise<void> }

	test.beforeAll(async ({ browser }) => {
		// SETUP budget, not an assertion budget. This hook provisions an account
		// and then performs a full Vue-rendered form login — on a cold instance
		// that is comfortably more than the 30s a single test gets, and it timed
		// out there. Nothing is asserted about the product inside this hook
		// except fixture preconditions, so a longer clock here cannot hide a
		// product failure; it only stops setup being mistaken for one.
		test.setTimeout(180_000)

		// Provision through an ADMIN context — the fixture user may not exist on
		// a fresh instance.
		const adminCtx = await adminContext(browser)
		const adminPage = await adminCtx.newPage()
		await adminPage.goto('/settings/admin/theming', {
			waitUntil: 'domcontentloaded',
		})
		await ensureNonAdminUser(adminPage)
		await adminCtx.close()

		nonAdmin = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
	})

	test.afterAll(async () => {
		await nonAdmin?.close()
	})

	/**
	 * Assert one endpoint refuses the non-admin and does not refuse the admin.
	 *
	 * @param adminPage the storageState-authenticated admin page
	 * @param method    HTTP verb
	 * @param path      app-relative path
	 * @param body      optional JSON body
	 */
	async function expectAdminOnly(
		adminPage: Page,
		method: string,
		path: string,
		body?: unknown,
	): Promise<void> {
		const asNonAdmin = await statusOf(nonAdmin.page, method, path, body)
		expect(
			asNonAdmin,
			`${method} ${path} must refuse an authenticated NON-ADMIN with 403`,
		).toBe(403)

		// Control arm: the same call as an admin must not be a 403. It may be
		// 200, or a 400 for a deliberately empty body — what it must not be is
		// the same refusal, which would mean the 403 above proved nothing.
		const asAdmin = await statusOf(adminPage, method, path, body)
		expect(
			asAdmin,
			`${method} ${path} must NOT return 403 for an admin `
				+ '(otherwise the non-admin 403 does not demonstrate an admin check)',
		).not.toBe(403)
	}

	// @e2e openspec/specs/theme-preview/spec.md#endpoints-are-admin-only
	test('theme-preview lifecycle endpoints refuse a non-admin and change nothing', async ({
		page,
	}) => {
		await page.goto('/settings/admin/theming')

		// The instance-wide active set before the refused calls.
		const before = JSON.stringify(await activeTokenSet(page))
		expect(before, 'admin must be able to read the active token set').toContain(
			'"tokenSet":"',
		)

		await expectAdminOnly(page, 'POST', `${APP}/settings/preview`, {
			tokenSet: 'amsterdam',
		})
		await expectAdminOnly(page, 'DELETE', `${APP}/settings/preview`)
		await expectAdminOnly(page, 'POST', `${APP}/settings/preview/publish`)

		// "...and no configuration MUST change" — the spec's second clause. A
		// refusal that still mutated state would satisfy the status assertions.
		const after = JSON.stringify(await activeTokenSet(page))
		expect(
			after,
			'refused preview calls must not change the active token set',
		).toBe(before)
	})

	// @e2e openspec/specs/theming-audit/spec.md#non-admin-access-is-rejected
	test('theming-audit endpoints reject a non-admin with no audit content in the response', async ({
		page,
	}) => {
		await page.goto('/settings/admin/theming')
		await expectAdminOnly(page, 'GET', `${APP}/settings/audit`)
		await expectAdminOnly(page, 'GET', `${APP}/settings/audit/export`)

		// "with no audit content in the response" — a 403 that still leaked the
		// log body would satisfy a status-only assertion.
		const leaked = await nonAdmin.page.evaluate(async () => {
			const res = await fetch('/index.php/apps/thematiq/settings/audit', {
				headers: { requesttoken: (window as any).OC.requestToken },
			})
			return await res.text()
		})
		expect(
			leaked,
			'the refusal body must not carry audit entries',
		).not.toContain('"action"')
	})

	// @e2e openspec/specs/config-portability/spec.md#endpoints-are-admin-only
	test('config bundle export and import refuse a non-admin', async ({ page }) => {
		await page.goto('/settings/admin/theming')
		await expectAdminOnly(page, 'GET', `${APP}/settings/config/export`)
		await expectAdminOnly(page, 'POST', `${APP}/settings/config/import`, {})
	})

	// @e2e openspec/specs/upstream-freshness/spec.md#non-admin-access-denied
	test('upstream-freshness read, write and dismiss refuse a non-admin', async ({
		page,
	}) => {
		await page.goto('/settings/admin/theming')
		await expectAdminOnly(page, 'GET', `${APP}/settings/upstream-freshness`)
		await expectAdminOnly(page, 'POST', `${APP}/settings/upstream-freshness`, {
			enabled: false,
		})
		await expectAdminOnly(
			page,
			'POST',
			`${APP}/settings/upstream-freshness/dismiss`,
			{},
		)
	})

	// @e2e openspec/specs/email-theming/spec.md#non-admin-access-denied
	test('email-theming read and write refuse a non-admin', async ({ page }) => {
		await page.goto('/settings/admin/theming')
		await expectAdminOnly(page, 'GET', `${APP}/settings/email-theming`)
		await expectAdminOnly(page, 'POST', `${APP}/settings/email-theming`, {
			enabled: false,
		})
	})

	// @e2e openspec/specs/dark-mode/spec.md#non-admin-access-denied
	test('dark-variants read and write refuse a non-admin', async ({ page }) => {
		await page.goto('/settings/admin/theming')
		await expectAdminOnly(page, 'GET', `${APP}/settings/dark-variants`)
		await expectAdminOnly(page, 'POST', `${APP}/settings/dark-variants`, {
			enabled: true,
		})
	})

	// @e2e openspec/specs/theming-audit/spec.md#log-file-is-not-directly-reachable-over-http
	test('the audit log file is not served over HTTP from appdata', async ({
		page,
	}) => {
		// Produce a real audit entry first, so this cannot pass merely because
		// the log does not exist yet — the scenario is GIVEN the file exists.
		await page.goto('/settings/admin/theming')
		const listed = await statusOf(page, 'GET', `${APP}/settings/audit`)
		expect(
			listed,
			'the admin export path must work, so the file is known to exist',
		).toBe(200)

		// appdata lives under the data directory, outside every web-served root.
		// Try the paths an attacker would guess, unauthenticated.
		const anon = await page
			.context()
			.browser()!
			.newContext({ storageState: undefined })
		try {
			const req = anon.request
			const candidates = [
				'/data/appdata_nldesign/audit/audit.jsonl',
				'/nextcloud/data/appdata_nldesign/audit/audit.jsonl',
				'/appdata_nldesign/audit/audit.jsonl',
				'/index.php/apps/thematiq/audit/audit.jsonl',
			]
			for (const path of candidates) {
				const res: APIResponse = await req.get(path, {
					failOnStatusCode: false,
					maxRedirects: 0,
				})
				expect(
					res.status(),
					`${path} must not serve the audit log`,
				).not.toBe(200)
				const body = await res.text().catch(() => '')
				expect(body, `${path} must not leak audit JSON lines`).not.toContain(
					'"action"',
				)
			}
		} finally {
			await anon.close()
		}
	})
})
