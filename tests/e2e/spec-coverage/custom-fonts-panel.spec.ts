/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The fonts on the Theming page: the list of uploaded fonts, and assigning a
 * font the app bundles to a role, without uploading anything.
 *
 * The uploaded-fonts list is drawn from GET /settings/fonts; these tests
 * answer it with a stub (`page.route`), so they hold whatever fonts an
 * instance has. The library test assigns Barlow to the heading role for real
 * and puts back whatever held the role before.
 */
import { test, expect, type Page } from '@playwright/test'
import { openThemedPage } from './_token-css'
import { requestToken } from '../workflows/_helpers'

const THEMING_URL = '/settings/admin/theming'

type LibraryFont = { name: string; licence?: string; roles?: string[] }

/** Answer the uploaded-fonts request (and only that one) with `fonts`. */
async function stubFonts(page: Page, fonts: unknown[]): Promise<void> {
	await page.route(
		(url) => url.pathname.endsWith('/apps/thematiq/settings/fonts'),
		(route) =>
			route.fulfill({
				status: 200,
				contentType: 'application/json',
				body: JSON.stringify({ fonts }),
			}),
	)
}

/** GET or change the font library through the endpoints the page uses. */
async function library(
	page: Page,
	method: 'GET' | 'POST' | 'DELETE',
	path = '',
	body?: unknown,
): Promise<{ status: number; json: { fonts?: LibraryFont[] } }> {
	const token = await requestToken(page)
	return page.evaluate(
		async ({ t, m, p, b }) => {
			const OC = (
				window as unknown as { OC: { generateUrl: (u: string) => string } }
			).OC
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/fonts/library' + p),
				{
					method: m,
					headers: { requesttoken: t, 'Content-Type': 'application/json' },
					body: b === undefined ? undefined : JSON.stringify(b),
				},
			)
			let json = {}
			try {
				json = await r.json()
			} catch {
				json = {}
			}
			return { status: r.status, json }
		},
		{ t: token, m: method, p: path, b: body },
	)
}

test.describe('custom fonts on the Theming page', () => {
	test(
		// @e2e custom-fonts::admin-sees-the-stored-fonts-listed
		'stored fonts are listed with name, role, size and a delete action; none says so',
		async ({ page }) => {
			await stubFonts(page, [
				{
					id: 'body-font',
					name: 'Gemeente Sans',
					role: 'body',
					size: 40960,
				},
				{
					id: 'heading-font',
					name: 'Gemeente Kop',
					role: 'heading',
					size: 20480,
				},
			])
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const list = page.locator('#nldesign-font-list')
			const rows = list.locator('.thematiq-font-row')
			await expect(rows).toHaveCount(2)
			await expect(
				rows.nth(0).locator('.thematiq-font-row__title'),
			).toHaveText('Gemeente Sans')
			await expect(
				rows.nth(0).locator('.thematiq-font-row__meta'),
			).toContainText('40 kB')
			await expect(
				rows.nth(1).locator('.thematiq-font-row__title'),
			).toHaveText('Gemeente Kop')
			await expect(
				rows.nth(1).locator('.thematiq-font-row__meta'),
			).toContainText('20 kB')
			// The role each font holds, in words.
			await expect(rows.nth(0)).toHaveClass(/thematiq-font-row--body/)
			await expect(rows.nth(1)).toHaveClass(/thematiq-font-row--heading/)
			await expect(
				rows.nth(0).locator('.thematiq-font-row__pill'),
			).toContainText(/in use for/i)
			for (let i = 0; i < 2; i++) {
				await expect(
					rows.nth(i).getByRole('button', { name: 'Delete' }),
				).toBeVisible()
			}

			await page.unrouteAll({ behavior: 'ignoreErrors' })
			await stubFonts(page, [])
			await page.reload({ waitUntil: 'domcontentloaded' })
			await expect(list).toContainText('No fonts uploaded yet.')
			await expect(list.locator('.thematiq-font-row')).toHaveCount(0)
		},
	)

	test(
		// @e2e custom-fonts::an-administrator-assigns-a-bundled-font
		'Barlow is listed with its licence and, assigned to headings, becomes the heading font',
		async ({ page }) => {
			test.setTimeout(120_000)
			await page.goto(THEMING_URL)
			await page.waitForLoadState('domcontentloaded')
			const before = await library(page, 'GET')
			expect(before.status).toBe(200)
			const previous = (before.json.fonts ?? []).find((f) =>
				(f.roles ?? []).includes('heading'),
			)

			try {
				const row = page
					.locator('#nldesign-font-library .thematiq-font-row')
					.filter({
						has: page.locator('.thematiq-font-row__title', {
							hasText: /^Barlow$/,
						}),
					})
				await expect(row).toHaveCount(1)
				await expect(
					row.locator('.thematiq-font-row__licence'),
				).not.toHaveText('')
				// Nothing to upload: the library has no file field.
				await expect(
					page.locator('#nldesign-font-library input[type="file"]'),
				).toHaveCount(0)

				const assign = row.getByRole('button', { name: 'Use for headings' })
				if ((await assign.count()) > 0) {
					const response = page.waitForResponse(
						(r) =>
							r.url().includes('/apps/thematiq/settings/fonts/library')
							&& r.request().method() === 'POST',
					)
					await assign.click()
					expect((await response).status()).toBe(200)
				}

				await openThemedPage(page)
				const heading = await page.evaluate(() =>
					getComputedStyle(document.documentElement)
						.getPropertyValue(
							'--nldesign-typography-heading-font-family',
						)
						.trim(),
				)
				const families = heading
					.split(',')
					.map((f) => f.trim().replace(/["']/g, ''))
				expect(families[0]).toBe('Barlow')
				expect(
					families.length,
					'Barlow comes with its fallback chain',
				).toBeGreaterThan(1)
			} finally {
				await page.goto(THEMING_URL)
				await page.waitForLoadState('domcontentloaded')
				if (previous !== undefined) {
					await library(page, 'POST', '', {
						name: previous.name,
						role: 'heading',
					})
				} else {
					await library(page, 'DELETE', '/heading')
				}
			}
		},
	)
})
