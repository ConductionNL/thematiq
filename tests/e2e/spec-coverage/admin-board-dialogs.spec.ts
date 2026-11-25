/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The dialogs of the admin Theming page (the Tq dialog boards of
 * admin-pages-as-the-tq-boards, tasks 2.x): each opens from its real trigger,
 * its main action works, and Escape closes it with the focus back on the
 * control that opened it.
 *
 * Nothing is left changed. The own token and its deprecation are created here
 * and removed here; the other dialogs are closed without their write (resetting
 * the theme, uploading overrides and saving overrides change the shared
 * instance and are covered by their unit tests).
 */
import { test, expect, type Page, type Locator } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

/**
 * Open the Theming page, at a panel when a hash is given.
 *
 * @param {Page} page The page.
 * @param {string} hash The panel hash, or '' for the overview.
 */
async function openAt(page: Page, hash = ''): Promise<void> {
	await page.goto(THEMING_URL + (hash === '' ? '' : '#' + hash))
	await page.waitForLoadState('domcontentloaded')
	await expect(page.locator('#thematiq-page-header')).toBeVisible({
		timeout: 30_000,
	})
	if (hash !== '') {
		await expect(
			page.locator('section[data-thematiq-panel="' + hash + '"]'),
		).toBeVisible()
	}
}

/**
 * The one open modal dialog.
 *
 * @param {Page} page The page.
 * @return {Locator} The dialog.
 */
function openDialog(page: Page): Locator {
	return page.locator('dialog[open]')
}

/**
 * Press Escape and check the dialog closed and focus went back to its opener.
 *
 * @param {Page} page The page.
 * @param {Locator} opener The control that opened the dialog.
 */
async function escapeReturnsFocus(page: Page, opener: Locator): Promise<void> {
	await page.keyboard.press('Escape')
	await expect(openDialog(page)).toHaveCount(0)
	await expect(opener).toBeFocused()
}

test.describe('the dialogs of the admin Theming page', () => {
	test('TqTerugNaarNextcloud opens from Back to Nextcloud, Cancel and Escape close it', async ({
		page,
	}) => {
		await openAt(page)
		const opener = page.locator('#nldesign-reset-theme-btn')
		await opener.click()
		const dialog = openDialog(page)
		await expect(dialog).toBeVisible()
		await expect(
			dialog.locator('.thematiq-admin-dialog__button--danger'),
		).toBeEnabled()

		await dialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(dialog).toHaveCount(0)
		await expect(opener).toBeFocused()

		await opener.click()
		await expect(openDialog(page)).toBeVisible()
		await escapeReturnsFocus(page, opener)
	})

	test('TqBasistokens unlocks the base tokens on confirm, and Escape leaves them locked', async ({
		page,
	}) => {
		await openAt(page)
		const box = page.locator('#nldesign-base-unlock')
		const label = page.locator('label[for=nldesign-base-unlock]')
		await expect(box).not.toBeChecked()

		await label.click()
		await expect(openDialog(page)).toBeVisible()
		await escapeReturnsFocus(page, box)
		await expect(box).not.toBeChecked()

		await label.click()
		const dialog = openDialog(page)
		await dialog.locator('.thematiq-admin-dialog__foot button').last().click()
		await expect(dialog).toHaveCount(0)
		await expect(box).toBeChecked()

		// Locking again needs no dialog and is client state only.
		await label.click()
		await expect(box).not.toBeChecked()
	})

	test('TqOverridesUploaden opens from Import, offers the file, and Escape closes it', async ({
		page,
	}) => {
		await openAt(page)
		const opener = page.locator('#nldesign-import-btn')
		await opener.click()
		const dialog = openDialog(page)
		await expect(dialog).toBeVisible()
		await expect(dialog.locator('.thematiq-admin-dialog__file')).toBeVisible()
		await escapeReturnsFocus(page, opener)
	})

	test('TqOverridesOpslaan or TqNieuwThema opens when an edited token is saved, and Escape keeps the edit unsaved', async ({
		page,
	}) => {
		await openAt(page)
		// With "do not ask again" stored, Save would write without a dialog.
		const asks = await page.evaluate(() =>
			['nldesign-confirm-save-theme', 'nldesign-confirm-save-stock'].every(
				(id) =>
					(document.getElementById(id) as HTMLInputElement | null)?.checked
					!== true,
			),
		)
		test.skip(
			!asks,
			'saving is set not to ask on this instance, so Save would write',
		)
		const field = page
			.locator('.nldesign-color-text:not([disabled]):visible')
			.first()
		test.skip(
			(await field.count()) === 0,
			'no editable colour field is open in the token editor',
		)
		const token = await field.getAttribute('data-token')
		await field.fill('#a1b2c3')
		await field.dispatchEvent('input')
		const save = page.locator('#nldesign-save-btn')
		await save.click()
		// On the stock theme the question is TqNieuwThema, on any other set TqOverridesOpslaan.
		const dialog = openDialog(page)
		await expect(dialog).toBeVisible()
		const stock =
			(await dialog.getByText('Save these changes as a new theme?').count())
			> 0
		if (stock) {
			await expect(
				dialog.getByRole('button', { name: 'Save as new theme' }),
			).toBeVisible()
		} else {
			await expect(dialog).toContainText(token ?? '')
		}
		await escapeReturnsFocus(page, save)
		await expect(page.locator('#nldesign-save-status')).toHaveText(/unsaved/i)
	})

	test('TqTokenToevoegen and TqTokenUitfaseren add, deprecate, withdraw and remove an own token', async ({
		page,
	}) => {
		const slug = 'e2e-dialog-probe-' + Date.now()
		const name = '--nldesign-org-' + slug
		await openAt(page, 'own-tokens')
		const add = page.locator('#nldesign-own-token-add')

		await add.click()
		await expect(page.locator('#nldesign-own-token-dialog')).toBeVisible()
		await escapeReturnsFocus(page, add)

		const deprecate = page.locator('#nldesign-deprecation-add')
		await deprecate.click()
		await expect(page.locator('#nldesign-deprecation-dialog')).toBeVisible()
		await escapeReturnsFocus(page, deprecate)

		page.on('dialog', (question) => question.accept())
		try {
			await add.click()
			const tokenDialog = page.locator('#nldesign-own-token-dialog')
			await tokenDialog.locator('input[name="slug"]').fill(slug)
			await tokenDialog.locator('input[name="label"]').fill('E2E dialog probe')
			await tokenDialog.locator('input[name="value"]').fill('#123456')
			await tokenDialog.locator('button[type="submit"]').click()
			await expect(tokenDialog).toBeHidden()
			const row = page.locator('#nldesign-own-token-list tr', {
				hasText: name,
			})
			await expect(row).toBeVisible()

			await row.getByRole('button', { name: 'Deprecate ' + name }).click()
			const deprecationDialog = page.locator('#nldesign-deprecation-dialog')
			await expect(
				deprecationDialog.locator('input[name="token"]'),
			).toHaveValue(name)
			await deprecationDialog
				.locator('input[name="message"]')
				.fill('E2E probe, removed by the test.')
			await deprecationDialog.locator('button[type="submit"]').click()
			await expect(deprecationDialog).toBeHidden()
			const deprecated = page.locator('#nldesign-deprecation-list tr', {
				hasText: name,
			})
			await expect(deprecated).toBeVisible()

			await deprecated
				.getByRole('button', { name: 'Withdraw the deprecation of ' + name })
				.click()
			await expect(deprecated).toHaveCount(0)
			await page
				.locator('#nldesign-own-token-list tr', { hasText: name })
				.getByRole('button', { name: 'Remove ' + name })
				.click()
			await expect(
				page.locator('#nldesign-own-token-list tr', { hasText: name }),
			).toHaveCount(0)
		} finally {
			await page.evaluate(async (token) => {
				const oc = (
					window as unknown as {
						OC: {
							generateUrl: (p: string) => string
							requestToken: string
						}
					}
				).OC
				const headers = { requesttoken: oc.requestToken }
				const base = '/apps/thematiq/settings/tokens'
				await fetch(
					oc.generateUrl(
						base + '/deprecations/' + encodeURIComponent(token),
					),
					{ method: 'DELETE', headers },
				)
				await fetch(
					oc.generateUrl(base + '/own/' + encodeURIComponent(token)),
					{ method: 'DELETE', headers },
				)
			}, name)
		}
	})

	test('TqVersieHerstellen opens from a version in the log, Cancel and Escape close it', async ({
		page,
	}) => {
		// The log answers with one version and its preview; the restore itself
		// is refused here, so nothing can be written.
		await page.route(
			(url) => url.pathname.endsWith('/apps/thematiq/settings/audit'),
			(route) =>
				route.fulfill({
					json: {
						entries: [
							{
								ts: '2026-11-20T10:00:00Z',
								actor: 'admin',
								action: 'token_set_changed',
								new: 'zuiddrecht',
								versionId: '20261120100000-0001',
							},
						],
					},
				}),
		)
		await page.route('**/apps/thematiq/settings/versions/**', (route) =>
			route.request().url().endsWith('/preview')
				? route.fulfill({
						json: {
							valid: true,
							errors: [],
							changes: [
								{
									field: 'tokenSet',
									from: 'nextcloud',
									to: 'zuiddrecht',
								},
							],
							customTokenSets: { add: [], remove: [] },
							missingFonts: [],
						},
					})
				: route.abort(),
		)
		await openAt(page, 'log')
		const restore = page.locator('.nldesign-audit-restore').first()
		await restore.click()
		const dialog = openDialog(page)
		await expect(dialog).toBeVisible()
		await expect(
			dialog.getByRole('button', { name: 'Restore version' }),
		).toBeVisible()
		await dialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(dialog).toHaveCount(0)
		await expect(restore).toBeFocused()

		await restore.click()
		await expect(openDialog(page)).toBeVisible()
		await escapeReturnsFocus(page, restore)
	})

	test('TqMerkenKiezen opens after uploading a file with several brands, and Escape imports nothing', async ({
		page,
	}) => {
		// The upload is answered here with two brands; nothing reaches the server.
		let uploads = 0
		await page.route('**/apps/thematiq/settings/tokensets/upload', (route) => {
			uploads++
			return route.fulfill({
				json: {
					multiBrand: true,
					stored: false,
					brands: [
						{ key: 'north', name: 'North', tokenCount: 12 },
						{ key: 'south', name: 'South', tokenCount: 9 },
					],
				},
			})
		})
		await openAt(page, 'own-sets')
		await page.locator('#nldesign-upload-name').fill('E2E brands probe')
		const chooser = page.waitForEvent('filechooser')
		await page.locator('#nldesign-upload-btn').click()
		await (
			await chooser
		).setFiles({
			name: 'brands.json',
			mimeType: 'application/json',
			buffer: Buffer.from('{}'),
		})
		const dialog = openDialog(page)
		await expect(dialog).toBeVisible()
		await expect(
			dialog.locator('.nldesign-brand-picker input[type="checkbox"]'),
		).toHaveCount(2)
		await page.keyboard.press('Escape')
		await expect(dialog).toHaveCount(0)
		expect(uploads).toBe(1)
	})

	test('the font delete dialog (TqLettertypen) opens from Delete, and Cancel and Escape keep the font', async ({
		page,
	}) => {
		// The uploaded-fonts list is answered with one font; a delete is refused here.
		await page.route(
			(url) => url.pathname.endsWith('/apps/thematiq/settings/fonts'),
			(route) =>
				route.fulfill({
					json: {
						fonts: [
							{
								id: 'e2e-probe',
								name: 'E2E Probe Sans',
								role: 'body',
								size: 2048,
							},
						],
					},
				}),
		)
		await page.route('**/apps/thematiq/settings/fonts/e2e-probe', (route) =>
			route.abort(),
		)
		await openAt(page, 'fonts')
		const remove = page
			.locator('#nldesign-font-list .thematiq-font-row', {
				hasText: 'E2E Probe Sans',
			})
			.getByRole('button', { name: /Delete/ })
		await remove.click()
		const dialog = openDialog(page)
		await expect(dialog).toContainText('E2E Probe Sans')
		await dialog.getByRole('button', { name: 'Cancel' }).click()
		await expect(dialog).toHaveCount(0)
		await expect(remove).toBeFocused()

		await remove.click()
		await expect(openDialog(page)).toBeVisible()
		await escapeReturnsFocus(page, remove)
	})

	test('TqThemingBijwerken and TqNieuwThema close on Escape and give focus back', async ({
		page,
	}) => {
		// Their real triggers apply a token set or save over the stock theme,
		// which changes the shared instance; the frame's behaviour is checked
		// by opening them as admin.js does.
		await openAt(page)
		const opener = page.locator('#nldesign-import-btn')
		await opener.focus()
		await page.evaluate(() => {
			const w = window as unknown as {
				ThematiqThemingUpdateDialog: { open: (o: object) => void }
			}
			w.ThematiqThemingUpdateDialog.open({
				opener: document.getElementById('nldesign-import-btn'),
				mode: 'match',
				setName: 'Gemeente Zuiddrecht',
				diffs: [
					{
						label: 'Primary colour',
						kind: 'color',
						current: '#00679e',
						proposed: '#3669a5',
					},
				],
				onConfirm: () => undefined,
			})
		})
		await expect(openDialog(page)).toContainText('Primary colour')
		await escapeReturnsFocus(page, opener)

		await page.evaluate(() => {
			const w = window as unknown as {
				ThematiqNewThemeDialog: { open: (o: object) => void }
			}
			w.ThematiqNewThemeDialog.open({
				opener: document.getElementById('nldesign-import-btn'),
				proceed: () => undefined,
			})
		})
		await expect(openDialog(page)).toBeVisible()
		await escapeReturnsFocus(page, opener)
	})
})
