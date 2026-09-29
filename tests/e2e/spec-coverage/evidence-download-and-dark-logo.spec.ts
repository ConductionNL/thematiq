/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The contrast evidence report is reachable from the settings page, and the
 * Epe dark logo reaches the generated dark stylesheet. Both were built but
 * unreachable until 29 Sep 2026.
 */
import { test, expect } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

test.describe('contrast evidence report and dark logo', () => {
	// @e2e compliance-evidence::an-administrator-downloads-the-contrast-evidence-report-from-the-settings-page
	test('an administrator downloads the contrast evidence report from the settings page', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		const section = page.locator('#nldesign-compliance-report')
		await section.waitFor({ state: 'visible', timeout: 20_000 })

		const json = section.locator('#nldesign-compliance-report-json')
		const markdown = section.locator('#nldesign-compliance-report-markdown')
		await expect(json).toBeVisible()
		await expect(markdown).toBeVisible()
		await expect(json).toHaveAttribute(
			'href',
			/\/apps\/thematiq\/settings\/compliance-report\?format=json$/,
		)
		await expect(markdown).toHaveAttribute(
			'href',
			/\/apps\/thematiq\/settings\/compliance-report\?format=markdown$/,
		)

		const href = (await json.getAttribute('href')) as string
		const result = await page.evaluate(async (url) => {
			const res = await fetch(url, {
				headers: { requesttoken: (window as any).OC.requestToken },
			})
			return {
				status: res.status,
				disposition: res.headers.get('content-disposition') ?? '',
				body: await res.text(),
			}
		}, href)
		expect(result.status).toBe(200)
		expect(result.disposition).toContain('attachment')
		expect(() => JSON.parse(result.body)).not.toThrow()
	})

	// @e2e dark-mode::a-user-in-dark-mode-sees-the-epe-logo-in-light-ink
	test('a user in dark mode sees the Epe logo in light ink', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page
			.locator('#nldesign-settings')
			.waitFor({ state: 'visible', timeout: 20_000 })

		// OC.linkTo resolves the app's web root, whether it lives in apps/ or custom_apps/.
		const result = await page.evaluate(async () => {
			const oc = (window as any).OC
			const css = await fetch(oc.linkTo('thematiq', 'css/tokens/dark/epe.css'))
			const logo = await fetch(oc.linkTo('thematiq', 'img/logos/epe-dark.svg'))
			return {
				cssStatus: css.status,
				cssBody: await css.text(),
				logoStatus: logo.status,
			}
		})
		expect(result.cssStatus).toBe(200)
		expect(result.cssBody).toContain(
			"--nldesign-logo-url: url('../../../img/logos/epe-dark.svg')",
		)
		expect(result.logoStatus).toBe(200)
	})
})
