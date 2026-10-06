/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared fixtures for the spec-coverage suites.
 *
 * Deliberately NOT named `*.spec.ts`: Playwright refuses to let one test file
 * import another, and a helper that lives in a spec file also gets collected as
 * a test file in its own right. The leading underscore matches the existing
 * `tests/e2e/workflows/_helpers.ts` convention in this repo.
 */
import { expect, type Browser, type Page } from '@playwright/test'
import * as path from 'path'

import { confirmAdminPassword } from '../workflows/_helpers'

/**
 * The admin session saved by tests/e2e/global-setup.ts.
 *
 * `browser.newContext()` does NOT inherit the project's `use.storageState`, so
 * a context created from the raw `browser` fixture is ANONYMOUS. Passing this
 * explicitly is what makes an "admin" context actually an admin — without it
 * the provisioning call below is made by nobody and fails in a way that looks
 * like the fixture user simply refusing to log in.
 */
export const ADMIN_STORAGE_STATE = path.resolve(__dirname, '../.auth/admin.json')

/** Open an authenticated ADMIN context. Caller must close it. */
export async function adminContext(browser: Browser) {
	return browser.newContext({ storageState: ADMIN_STORAGE_STATE })
}

/** Credentials for the dedicated non-admin fixture user. */
export const NONADMIN_USER = process.env.NC_NONADMIN_USER ?? 'e2enonadmin'
/** Nextcloud silently rejects passwords under 10 characters — keep this long. */
export const NONADMIN_PASS =
	process.env.NC_NONADMIN_PASS ?? 'nldesign-e2e-nonadmin-pw'

/**
 * Create the non-admin fixture user, idempotently, via the provisioning API.
 *
 * A suite MUST provision its own fixtures. The first version of these tests
 * assumed the account existed because it existed on the machine they were
 * written on; against a fresh CI instance every test that needed it died in
 * `beforeAll` with "Login failed for e2enonadmin". A test that cannot run looks
 * nothing like a test that passes — but it proves exactly as little.
 *
 * HTTP 200 means created. OCS status 102 (or an "already exists" message) means
 * the account is already there, which is success for our purposes. Anything
 * else is raised, because a silently absent fixture is how this failed the
 * first time.
 *
 * @param adminPage a page authenticated as an admin, already navigated
 *                  somewhere that carries `OC.requestToken`
 */
export async function ensureNonAdminUser(adminPage: Page): Promise<void> {
	await confirmAdminPassword(adminPage)
	const result = await adminPage.evaluate(
		async ({ user, pass }) => {
			const body = new URLSearchParams({ userid: user, password: pass })
			const res = await fetch('/ocs/v2.php/cloud/users?format=json', {
				method: 'POST',
				headers: {
					requesttoken: (window as any).OC.requestToken,
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body: body.toString(),
			})
			return { http: res.status, text: await res.text() }
		},
		{ user: NONADMIN_USER, pass: NONADMIN_PASS },
	)

	const alreadyExists =
		result.text.includes('"statuscode":102')
		|| result.text.toLowerCase().includes('already exists')
	if (result.http !== 200 && alreadyExists === false) {
		throw new Error(
			`Could not provision the non-admin fixture user '${NONADMIN_USER}': `
				+ `HTTP ${result.http} — ${result.text.slice(0, 300)}`,
		)
	}
}

/**
 * Log a user in through the real login form and return the authenticated page.
 *
 * Mirrors tests/e2e/global-setup.ts: the form is Vue-rendered so the inputs do
 * not exist in the initial HTML, and the post-login redirect must be observed by
 * polling the URL rather than with `waitForURL` — the submit click is
 * `noWaitAfter`, so the navigation has usually already happened and
 * `waitForURL` would sit waiting for a second one that never comes.
 */
export async function loginAs(
	browser: Browser,
	user: string,
	pass: string,
): Promise<{ page: Page; close: () => Promise<void> }> {
	const context = await browser.newContext({ storageState: undefined })
	const page = await context.newPage()

	await page.goto('/index.php/login', { waitUntil: 'domcontentloaded' })
	const userField = page.locator('input[name="user"]')
	await userField.waitFor({ state: 'visible', timeout: 30_000 })
	await userField.fill(user)
	await page.locator('input[name="password"]').fill(pass)
	await page.locator('button[type="submit"]').first().click({ noWaitAfter: true })

	const deadline = Date.now() + 60_000
	while (Date.now() < deadline) {
		if (!/\/login(\?|$|\/)/.test(page.url())) break
		await page.waitForTimeout(500)
	}
	if (/\/login(\?|$|\/)/.test(page.url())) {
		throw new Error(
			`Login failed for ${user} — still on ${page.url()}. `
				+ 'The fixture user is created by ensureNonAdminUser(); if that ran, check the password policy.',
		)
	}

	return {
		page,
		close: async () => {
			await context.close()
		},
	}
}

/**
 * Issue a request from inside an authenticated page, with a valid CSRF token.
 *
 * Returns the numeric status alongside the parsed body so assertions can name
 * it. A body parsed as "empty" hides whether a call was refused (403) or merely
 * CSRF-rejected (412), and those mean opposite things when the subject under
 * test is authorization.
 */
export async function api(
	page: Page,
	method: string,
	path: string,
	body?: unknown,
): Promise<{ status: number; json: any }> {
	// `OC` arrives with the page's scripts: wait for it rather than read an
	// undefined global from a page that is still loading.
	await page.waitForFunction(
		() => typeof (window as any).OC?.requestToken === 'string',
		null,
		{ timeout: 30_000 },
	)
	return page.evaluate(
		async ({ method, path, body }) => {
			const headers: Record<string, string> = {
				requesttoken: (window as any).OC.requestToken,
			}
			if (body !== undefined) headers['Content-Type'] = 'application/json'
			const res = await fetch(path, {
				method,
				headers,
				body: body === undefined ? undefined : JSON.stringify(body),
			})
			let json: any = null
			try {
				json = await res.json()
			} catch {
				json = null
			}
			return { status: res.status, json }
		},
		{ method, path, body },
	)
}

/**
 * The instance-wide active token set, as `{ tokenSet }`, read from the public
 * capability: the supported read path since GET /settings/tokenset was removed
 * for #664.
 */
export async function activeTokenSet(page: Page): Promise<{ tokenSet: string }> {
	const res = await api(page, 'GET', '/ocs/v2.php/cloud/capabilities?format=json')
	return { tokenSet: res.json?.ocs?.data?.capabilities?.nldesign?.tokenSet?.id }
}

/** Assert a fixture precondition loudly rather than letting it fail obscurely later. */
export function requireFixture(value: unknown, what: string): void {
	expect(value, `fixture precondition: ${what}`).toBeTruthy()
}

/** Every custom set an e2e spec creates gets an id with this prefix. */
export const E2E_CUSTOM_SET_PREFIX = 'custom-e2e-'

/**
 * Delete every custom token set an e2e run left behind (#181).
 *
 * A spec that creates a set deletes it as its last step, so a test that dies
 * earlier (a timeout, a 503) leaves the set on the instance, and the next run
 * sees extra rows. Calling this before and after the specs that upload makes
 * cleanup independent of how far a test got. It only touches ids starting with
 * E2E_CUSTOM_SET_PREFIX, so an administrator's own sets are never removed.
 *
 * @param browser the worker's browser
 * @return the ids that were deleted
 */
export async function removeE2eCustomSets(browser: Browser): Promise<string[]> {
	const context = await adminContext(browser)
	try {
		const page = await context.newPage()
		await page.goto('/settings/admin/theming', { waitUntil: 'domcontentloaded' })
		return await page.evaluate(async (prefix) => {
			const token = (window as any).OC.requestToken
			const base = (window as any).OC.generateUrl(
				'/apps/thematiq/settings/tokensets/custom',
			)
			const res = await fetch(base, { headers: { requesttoken: token } })
			if (!res.ok) {
				return []
			}
			const sets = ((await res.json()).sets ?? []) as Array<{ id?: string }>
			const deleted: string[] = []
			for (const set of sets) {
				const id = String(set.id ?? '')
				if (!id.startsWith(prefix)) {
					continue
				}
				const del = await fetch(`${base}/${encodeURIComponent(id)}`, {
					method: 'DELETE',
					headers: { requesttoken: token },
				})
				if (del.ok) {
					deleted.push(id)
				}
			}
			return deleted
		}, E2E_CUSTOM_SET_PREFIX)
	} finally {
		await context.close()
	}
}
