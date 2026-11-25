/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * House style of my groups in the personal Appearance section (TqPersoonlijk): a subadmin of
 * a group whose house style the administrator released sees the block at
 * /settings/user/theming under Nextcloud's own theme settings, with the thematiq chip, a
 * card per group and a contrast pill per set; someone without such a group does not.
 *
 * The fixture is a group of its own with the non-admin fixture user as its subadmin and one
 * delegated mapping entry; the mapping as it was is put back afterwards and the group removed.
 */
import { test, expect, type Page } from '@playwright/test'

import {
	adminContext,
	api,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'

const GROUP = 'e2e-delegated-house-style'
const APP = '/index.php/apps/thematiq'

/** A provisioning API call from the admin page. */
async function ocs(
	page: Page,
	method: string,
	path: string,
	body: Record<string, string> = {},
): Promise<number> {
	return page.evaluate(
		async ({ method, path, body }) => {
			const res = await fetch('/ocs/v2.php/cloud/' + path + '?format=json', {
				method,
				headers: {
					requesttoken: (window as any).OC.requestToken,
					'OCS-APIRequest': 'true',
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				body:
					method === 'GET'
						? undefined
						: new URLSearchParams(body).toString(),
			})
			return res.status
		},
		{ method, path, body },
	)
}

test.describe('house style of my groups in the personal Appearance section', () => {
	test.describe.configure({ mode: 'serial' })
	let before: unknown[] = []

	test.beforeAll(async ({ browser }) => {
		const context = await adminContext(browser)
		const page = await context.newPage()
		await page.goto('/settings/admin/theming', { waitUntil: 'domcontentloaded' })
		await ensureNonAdminUser(page)
		await ocs(page, 'POST', 'groups', { groupid: GROUP })
		await ocs(page, 'POST', `users/${NONADMIN_USER}/groups`, { groupid: GROUP })
		await ocs(page, 'POST', `users/${NONADMIN_USER}/subadmins`, {
			groupid: GROUP,
		})
		const current = await api(page, 'GET', `${APP}/settings/group-theming`)
		before = current.json.mapping ?? []
		const sets = (current.json.tokenSets ?? [])
			.map((set: { id: string }) => set.id)
			.slice(0, 2)
		const saved = await api(page, 'POST', `${APP}/settings/group-theming`, {
			mapping: [
				...before,
				{
					group: GROUP,
					tokenSet: sets[0],
					delegated: true,
					allowedTokenSets: sets,
				},
			],
		})
		expect(saved.status).toBe(200)
		await context.close()
	})

	test.afterAll(async ({ browser }) => {
		const context = await adminContext(browser)
		const page = await context.newPage()
		await page.goto('/settings/admin/theming', { waitUntil: 'domcontentloaded' })
		await api(page, 'POST', `${APP}/settings/group-theming`, { mapping: before })
		await ocs(page, 'DELETE', `users/${NONADMIN_USER}/subadmins`, {
			groupid: GROUP,
		})
		await ocs(page, 'DELETE', `groups/${GROUP}`)
		await context.close()
	})

	test('a subadmin of a released group sees the block with a card per group', async ({
		browser,
	}) => {
		const subadmin = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		try {
			await subadmin.page.goto('/settings/user/theming')
			const block = subadmin.page.locator(
				'#thematiq-my-groups.thematiq-my-groups--personal',
			)
			await expect(block).toBeVisible({ timeout: 30_000 })
			await expect(block.locator('h2')).toHaveText('House style of my groups')
			await expect(block.locator('.thematiq-my-groups__chip')).toHaveText(
				'thematiq',
			)
			const card = block.locator('fieldset.thematiq-my-group')
			await expect(card).toHaveCount(1)
			await expect(card.locator('legend')).toHaveText(GROUP)
			await expect(
				card.locator('.thematiq-my-group-contrast').first(),
			).toContainText('Contrast:')
			await expect(block.locator('.thematiq-my-groups__why')).toBeVisible()
		} finally {
			await subadmin.close()
		}
	})

	test('the admin, who has no delegated group, does not see it', async ({
		page,
	}) => {
		await page.goto('/settings/user/theming')
		await expect(page.locator('#theming, .theming').first()).toBeAttached({
			timeout: 30_000,
		})
		await expect(page.locator('#thematiq-my-groups')).toHaveCount(0)
	})
})
