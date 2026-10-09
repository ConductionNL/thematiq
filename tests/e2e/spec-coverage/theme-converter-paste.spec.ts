/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/custom-token-sets/spec.md
 *
 * The paste path of the theme converter (nlds-theme-converter 9.5): an admin pastes the contents
 * of a built theme stylesheet, converts it, sees what was used and what was left out, and finds
 * the new set in the dropdown without reloading. The conversion rules themselves are covered by
 * tests/vitest/tokenConverter.spec.js and the PHPUnit converter suites.
 */
import { test, expect } from '@playwright/test'

import { removeE2eCustomSets } from './_fixtures'

const THEMING_URL = '/settings/admin/theming'

const THEME = [
	'.e2e-theme {',
	'  --e2e-color-green-500: #23845c;',
	'  --utrecht-button-primary-action-background-color: var(--e2e-color-green-500);',
	'  --utrecht-button-primary-action-color: #ffffff;',
	'  --utrecht-page-max-inline-size: 1200px;',
	'  --utrecht-document-font-size: 18px;',
	'}',
].join('\n')

test.describe('theme-converter-paste', () => {
	test.beforeAll(async ({ browser }) => {
		await removeE2eCustomSets(browser)
	})

	test.afterEach(async ({ browser }) => {
		await removeE2eCustomSets(browser)
	})

	test(// @e2e openspec/specs/custom-token-sets/spec.md#pasting-the-contents-of-a-theme-file-creates-the-same-set-as-uploading-it
	'A pasted theme becomes a custom set', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		const setName = 'E2E Paste ' + Date.now()
		await page.fill('#nldesign-upload-name', setName)
		await page.fill('#nldesign-upload-content', THEME)
		await page.click('#nldesign-convert-btn')

		await expect(page.locator('#nldesign-upload-result')).toBeVisible({
			timeout: 10000,
		})
		await expect(page.locator('#nldesign-custom-set-list')).toContainText(
			setName,
			{ timeout: 10000 },
		)
	})

	test(// @e2e openspec/specs/custom-token-sets/spec.md#a-design-systems-built-css-becomes-a-selectable-token-set
	'A built theme stylesheet uploaded as a file becomes a selectable set', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		const setName = 'E2E Built ' + Date.now()
		await page.fill('#nldesign-upload-name', setName)
		await page.locator('#nldesign-upload-input').setInputFiles({
			name: 'design-tokens.css',
			mimeType: 'text/css',
			buffer: Buffer.from(THEME),
		})

		await expect(page.locator('#nldesign-custom-set-list')).toContainText(
			setName,
			{ timeout: 10000 },
		)
	})

	test(// @e2e openspec/specs/custom-token-sets/spec.md#the-report-groups-skipped-tokens-by-reason
	'The result names what was left out, grouped by reason, with the counts', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		await page.fill('#nldesign-upload-name', 'E2E Report ' + Date.now())
		await page.fill('#nldesign-upload-content', THEME)
		await page.click('#nldesign-convert-btn')

		const result = page.locator('#nldesign-upload-result')
		await expect(result.locator('.nldesign-conversion-counts')).toContainText(
			'skipped',
			{ timeout: 10000 },
		)
		const groups = result.locator('.nldesign-conversion-report li')
		await expect(
			groups.filter({ hasText: '--utrecht-page-max-inline-size' }),
		).toHaveCount(1)
		await expect(
			groups.filter({ hasText: '--utrecht-document-font-size' }),
		).toHaveCount(1)
	})

	test(// @e2e openspec/specs/custom-token-sets/spec.md#the-new-set-appears-in-the-dropdown-without-a-page-reload
	'The new set is in the dropdown without a reload', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		let reloaded = false
		page.on('framenavigated', (frame) => {
			if (frame === page.mainFrame()) {
				reloaded = true
			}
		})

		const setName = 'E2E Dropdown ' + Date.now()
		await page.fill('#nldesign-upload-name', setName)
		await page.fill('#nldesign-upload-content', THEME)
		await page.click('#nldesign-convert-btn')

		await expect(
			page.locator('#nldesign-token-set-select option', { hasText: setName }),
		).toHaveCount(1, { timeout: 10000 })
		expect(reloaded).toBe(false)
	})

	test(// @e2e openspec/specs/custom-token-sets/spec.md#an-empty-paste-and-an-empty-file-picker-are-the-same-error
	'An empty paste sends nothing and says what is missing', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')

		let uploads = 0
		page.on('request', (request) => {
			if (request.url().includes('/tokensets/upload')) {
				uploads++
			}
		})

		await page.fill('#nldesign-upload-name', 'E2E Empty')
		await page.fill('#nldesign-upload-content', '   ')
		await page.click('#nldesign-convert-btn')

		await expect(page.locator('.toastify').first()).toBeVisible({
			timeout: 5000,
		})
		expect(uploads).toBe(0)
	})
})
