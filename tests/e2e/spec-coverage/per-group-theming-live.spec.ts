/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Per-group theming as users meet it: a member of a mapped group gets the
 * mapped set's stylesheets, someone in no mapped group and the anonymous
 * login page get the instance default, a saved mapping applies on the very
 * next request, and the admin reads the mapping and the stated limitation.
 *
 * GLOBAL STATE: a fresh group is created and mapped to a shipped set
 * (offerTokenSets), the non-admin fixture user is added to it, and both are
 * removed again (withdrawTokenSetOffer deletes the group, which also ends
 * the membership).
 */
import { test, expect, type Page } from '@playwright/test'
import {
	adminContext,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'
import { anonymousPage, openLoginPage } from './_theme-state'
import { indexOfCss, openThemedPage, stylesheetPaths } from './_token-css'
import {
	confirmAdminPassword,
	getTokenSet,
	offerTokenSets,
	requestToken,
	withdrawTokenSetOffer,
	type TokenSetOffer,
} from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

/** Add `user` to `group` through the provisioning API. */
async function addToGroup(admin: Page, user: string, group: string): Promise<void> {
	await confirmAdminPassword(admin)
	const status = await admin.evaluate(
		async ({ u, g }) => {
			const r = await fetch(
				`/ocs/v2.php/cloud/users/${encodeURIComponent(u)}/groups?format=json`,
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'OCS-APIRequest': 'true',
						requesttoken: (
							window as unknown as { OC: { requestToken: string } }
						).OC.requestToken,
					},
					body: JSON.stringify({ groupid: g }),
				},
			)
			return r.status
		},
		{ u: user, g: group },
	)
	expect(status, `adding ${user} to ${group}`).toBe(200)
}

test.describe('per-group theming, live', () => {
	test.describe.configure({ mode: 'serial', timeout: 180_000 })

	/** A shipped set that is not the instance default, chosen in beforeAll. */
	let mapped = ''
	let instanceDefault = ''
	let offer: TokenSetOffer | null = null

	test.beforeAll(async ({ browser }) => {
		const ctx = await adminContext(browser)
		const admin = await ctx.newPage()
		try {
			await admin.goto(THEMING_URL)
			await admin.waitForLoadState('domcontentloaded')
			const token = await requestToken(admin)
			instanceDefault = await getTokenSet(admin, token)
			mapped = instanceDefault === 'amsterdam' ? 'zuiddrecht' : 'amsterdam'
			await ensureNonAdminUser(admin)
			offer = await offerTokenSets(admin, token, [mapped])
			await addToGroup(admin, NONADMIN_USER, offer.groups[0])
		} finally {
			await ctx.close()
		}
	})

	test.afterAll(async ({ browser }) => {
		if (offer === null) return
		const ctx = await adminContext(browser)
		const admin = await ctx.newPage()
		try {
			await admin.goto(THEMING_URL)
			await admin.waitForLoadState('domcontentloaded')
			// The offer may already have been withdrawn by the last test.
			if (offer !== null) {
				await withdrawTokenSetOffer(admin, await requestToken(admin), offer)
			}
		} finally {
			await ctx.close()
		}
	})

	test(
		// @e2e per-group-theming::admin-reads-the-current-mapping
		'the admin reads the mapping in priority order with the group and set options',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const token = await requestToken(page)
			const body = await page.evaluate(async (t) => {
				const OC = (
					window as unknown as {
						OC: { generateUrl: (p: string) => string }
					}
				).OC
				const r = await fetch(
					OC.generateUrl('/apps/thematiq/settings/group-theming'),
					{
						headers: { requesttoken: t },
					},
				)
				return { status: r.status, json: await r.json() }
			}, token)
			expect(body.status).toBe(200)
			const mapping = body.json.mapping as {
				group: string
				tokenSet: string
			}[]
			// The offer was appended, so it is the last, lowest-priority entry.
			expect(mapping[mapping.length - 1]).toEqual({
				group: (offer as TokenSetOffer).groups[0],
				tokenSet: mapped,
			})
			expect(Array.isArray(body.json.groups)).toBe(true)
			expect(JSON.stringify(body.json.groups)).toContain(
				(offer as TokenSetOffer).groups[0],
			)
			expect(body.json.tokenSets).toBeTruthy()
		},
	)

	test(
		// @e2e per-group-theming::limitation-is-stated-in-the-admin-ui
		'the group section states that logo, mail and core branding follow the default set',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			await expect(
				page.getByText(
					'Only the thematiq token set differs per group. Logo, email template and the rest of the Nextcloud house style always follow the default token set.',
				),
			).toBeAttached()
		},
	)

	test(
		// @e2e per-group-theming::user-in-a-mapped-group-gets-the-mapped-set
		'a member of the mapped group gets the mapped set',
		async ({ browser }) => {
			const session = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
			try {
				await openThemedPage(session.page)
				const paths = await stylesheetPaths(session.page)
				expect(indexOfCss(paths, `tokens/${mapped}`)).toBeGreaterThanOrEqual(
					0,
				)
				expect(indexOfCss(paths, `tokens/${instanceDefault}`)).toBe(-1)
			} finally {
				await session.close()
			}
		},
	)

	test(
		// @e2e per-group-theming::unmapped-user-and-anonymous-pages-get-the-instance-default
		'the admin, in no mapped group, and the anonymous login page get the instance default',
		async ({ page, browser }) => {
			await openThemedPage(page)
			const adminPaths = await stylesheetPaths(page)
			expect(indexOfCss(adminPaths, `tokens/${mapped}`)).toBe(-1)
			if (instanceDefault !== 'nextcloud') {
				expect(
					indexOfCss(adminPaths, `tokens/${instanceDefault}`),
				).toBeGreaterThanOrEqual(0)
			}

			const anon = await anonymousPage(browser)
			try {
				await openLoginPage(anon.page)
				const loginPaths = await stylesheetPaths(anon.page)
				expect(indexOfCss(loginPaths, `tokens/${mapped}`)).toBe(-1)
				if (instanceDefault !== 'nextcloud') {
					expect(
						indexOfCss(loginPaths, `tokens/${instanceDefault}`),
					).toBeGreaterThanOrEqual(0)
				}
			} finally {
				await anon.close()
			}
		},
	)

	test(
		// @e2e per-group-theming::saving-the-mapping-takes-effect-immediately
		"after the mapping is saved without the group, the member's next page is the default",
		async ({ browser }) => {
			const session = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
			try {
				await openThemedPage(session.page)
				expect(
					indexOfCss(
						await stylesheetPaths(session.page),
						`tokens/${mapped}`,
					),
				).toBeGreaterThanOrEqual(0)

				// Save the mapping without the group: the generation moves.
				const ctx = await adminContext(browser)
				const admin = await ctx.newPage()
				try {
					await admin.goto(THEMING_URL)
					await admin.waitForLoadState('domcontentloaded')
					await withdrawTokenSetOffer(
						admin,
						await requestToken(admin),
						offer as TokenSetOffer,
					)
					offer = null
				} finally {
					await ctx.close()
				}

				await openThemedPage(session.page)
				expect(
					indexOfCss(
						await stylesheetPaths(session.page),
						`tokens/${mapped}`,
					),
				).toBe(-1)
			} finally {
				await session.close()
			}
		},
	)
})
