/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The audit log panel and the two downloads on the Theming page, as an
 * administrator meets them. The panel is the table js/admin.js fills from
 * GET /settings/audit (`#nldesign-audit-table-body`); the downloads are the
 * page's own buttons, `#nldesign-audit-download-btn` and
 * `#nldesign-config-bundle-download-btn`, which the visible header buttons
 * press.
 *
 * The empty and escaping scenarios answer GET /settings/audit with a stub
 * (`page.route`), so they hold on an instance that has a log of its own. The
 * recent-entry scenario switches the active set for real, through the same
 * endpoint the dropdown uses, and switches it back afterwards.
 */
import { test, expect, type Page } from '@playwright/test'
import { getTokenSet, requestToken, setTokenSet } from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'
const AUDIT_ROUTE = '**/apps/thematiq/settings/audit?*'
const BODY = '#nldesign-audit-table-body'

/** Open the Theming page and wait until the audit table has been filled. */
async function openWithAudit(page: Page): Promise<void> {
	const loaded = page.waitForResponse((r) =>
		r.url().includes('/apps/thematiq/settings/audit?'),
	)
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await loaded
	await expect(page.locator(`${BODY} tr`).first()).toBeAttached()
}

/** Answer the panel's audit request with `entries`. */
async function stubAudit(page: Page, entries: unknown[]): Promise<void> {
	await page.route(AUDIT_ROUTE, (route) =>
		route.fulfill({
			status: 200,
			contentType: 'application/json',
			body: JSON.stringify({ entries }),
		}),
	)
}

/** Press one of the page's hidden download buttons and return the download. */
async function pressDownload(page: Page, id: string) {
	const download = page.waitForEvent('download')
	await page.evaluate((buttonId) => {
		const button = document.getElementById(buttonId)
		if (button === null) {
			throw new Error(`no #${buttonId} on the page`)
		}
		button.click()
	}, id)
	return download
}

test.describe('admin audit log and downloads', () => {
	test(
		// @e2e admin-settings::empty-state
		'with no entries the panel says so instead of an empty table',
		async ({ page }) => {
			await stubAudit(page, [])
			await openWithAudit(page)
			const rows = page.locator(`${BODY} tr`)
			await expect(rows).toHaveCount(1)
			await expect(rows.first()).toHaveText(
				'No theming changes have been recorded yet.',
			)
		},
	)

	test(
		// @e2e admin-settings::entry-content-is-escaped
		'markup in an entry is shown as text, never parsed',
		async ({ page }) => {
			const name = '<img src=x onerror="window.__thematiqAuditXss=1">'
			await stubAudit(page, [
				{
					ts: '2026-11-24T10:00:00+00:00',
					actor: 'admin',
					action: 'custom_token_set_uploaded',
					old: null,
					new: name,
				},
			])
			await openWithAudit(page)
			await expect(
				page.locator(`${BODY} .nldesign-audit-value`).nth(1),
			).toHaveText(name)
			await expect(page.locator(`${BODY} img`)).toHaveCount(0)
			expect(
				await page.evaluate(
					() =>
						(window as unknown as { __thematiqAuditXss?: number })
							.__thematiqAuditXss,
				),
			).toBeUndefined()
		},
	)

	test(
		// @e2e admin-settings::full-log-download-from-the-panel
		'the full log downloads as nldesign-audit.jsonl, one entry per line',
		async ({ page }) => {
			await openWithAudit(page)
			const download = await pressDownload(page, 'nldesign-audit-download-btn')
			expect(download.suggestedFilename()).toBe('nldesign-audit.jsonl')
			const path = await download.path()
			expect(path).not.toBeNull()
			const { readFileSync } = await import('fs')
			const lines = readFileSync(path as string, 'utf8')
				.split('\n')
				.filter((line) => line.trim() !== '')
			for (const line of lines) {
				expect(JSON.parse(line)).toHaveProperty('action')
			}
		},
	)

	test(
		// @e2e admin-settings::download-configuration-button
		'the configuration downloads as nldesign-config.json with the whole bundle',
		async ({ page }) => {
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const download = await pressDownload(
				page,
				'nldesign-config-bundle-download-btn',
			)
			expect(download.suggestedFilename()).toBe('nldesign-config.json')
			const path = await download.path()
			const { readFileSync } = await import('fs')
			const bundle = JSON.parse(readFileSync(path as string, 'utf8'))
			expect(bundle.app?.id).toBe('thematiq')
			expect(typeof bundle.config?.tokenSet).toBe('string')
			for (const section of [
				'emailFooter',
				'customOverridesCss',
				'customTokenSets',
				'customFonts',
			]) {
				expect(bundle, section).toHaveProperty(section)
			}
		},
	)

	test(
		// @e2e admin-settings::recent-entries-are-shown-after-a-change
		'after a set change the top row is that change, by this admin',
		async ({ page }) => {
			test.setTimeout(120_000)
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const token = await requestToken(page)
			const previous = await getTokenSet(page, token)
			const next = previous === 'zuiddrecht' ? 'vng' : 'zuiddrecht'
			const uid = await page.evaluate(
				() =>
					(
						window as unknown as {
							OC: { getCurrentUser: () => { uid: string } }
						}
					).OC.getCurrentUser().uid,
			)

			// Only the switch, so it is the newest entry: no overrides are touched.
			await setTokenSet(page, token, next)
			try {
				await openWithAudit(page)
				const top = page.locator(`${BODY} tr`).first()
				await expect(top.locator('.nldesign-audit-code')).toHaveText(
					'token_set_changed',
				)
				await expect(top.locator('.nldesign-audit-actor')).toHaveText(uid)
				const values = top.locator('.nldesign-audit-value')
				await expect(values.nth(0)).toContainText(previous)
				await expect(values.nth(1)).toContainText(next)
				await expect(top.locator('.nldesign-audit-date')).not.toHaveText('')
			} finally {
				const restoreToken = await requestToken(page)
				await setTokenSet(page, restoreToken, previous)
			}
		},
	)
})
