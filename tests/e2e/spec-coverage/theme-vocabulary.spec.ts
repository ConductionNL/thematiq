/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/theme-vocabulary-complete/specs/nextcloud-variable-mapping/spec.md#unset-under-the-dark-theme
 * @e2e openspec/changes/theme-vocabulary-complete/specs/nextcloud-variable-mapping/spec.md#unset-under-high-contrast
 * @e2e openspec/changes/theme-vocabulary-complete/specs/nextcloud-variable-mapping/spec.md#a-set-gives-the-search-highlight-a-colour
 * @e2e openspec/changes/theme-vocabulary-complete/specs/nextcloud-variable-mapping/spec.md#a-set-gives-a-light-value-only
 * @e2e openspec/changes/theme-vocabulary-complete/specs/nextcloud-variable-mapping/spec.md#a-set-gives-a-light-and-a-dark-value
 * @e2e openspec/changes/theme-vocabulary-complete/specs/css-architecture/spec.md#a-component-scope-falls-back-to-a-theme-scoped-value
 * @e2e openspec/changes/theme-vocabulary-complete/specs/css-architecture/spec.md#a-light-only-admin-value-leaves-dark-mode-alone
 *
 * Settable theme variables (theme-vocabulary-complete) in a real browser.
 *
 * Every check reads a computed custom property on a child of body, where
 * theme-scopes.css redeclares the variable from its capture. "Nextcloud's
 * value" is read from the theming stylesheet the instance serves for the
 * same theme, so the expectation is never a number copied into this file.
 *
 * GLOBAL STATE: writes admin overrides (restored in afterAll) and switches the
 * user's theme through the theming API (reset to default in afterAll).
 */

import { test, expect, type Page } from '@playwright/test'
import { THEMING_URL, getOverrides, requestToken, setOverrides } from '../workflows/_helpers'

/** Switch the current user's Nextcloud theme ('default', 'dark', 'light-highcontrast', ...). */
async function useTheme(page: Page, theme: string): Promise<void> {
	await page.evaluate(async (id) => {
		const token = (window as unknown as { OC: { requestToken: string } }).OC.requestToken
		for (const enabled of ['default', 'dark', 'light', 'light-highcontrast', 'dark-highcontrast']) {
			await fetch(`/ocs/v2.php/apps/theming/api/v1/theme/${enabled}`, {
				method: 'DELETE',
				headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
			})
		}
		await fetch(`/ocs/v2.php/apps/theming/api/v1/theme/${id}/enable`, {
			method: 'PUT',
			headers: { requesttoken: token, 'OCS-APIRequest': 'true' },
		})
	}, theme)
}

/** The value Nextcloud's served theming stylesheet declares for a variable. */
async function nextcloudValue(page: Page, theme: string, name: string): Promise<string> {
	const css = await (await page.request.get(`/apps/theming/theme/${theme}.css?plain=1`)).text()
	const match = css.match(new RegExp(`${name}:([^;]*);`))
	return (match?.[1] ?? '').trim()
}

/** A custom property as computed on the first child of body. */
async function onBodyChild(page: Page, name: string): Promise<string> {
	return page.evaluate((n) => getComputedStyle(document.body.firstElementChild as Element).getPropertyValue(n).trim(), name)
}

test.describe('theme vocabulary: settable variables', () => {
	test.describe.configure({ mode: 'serial', timeout: 90_000 })

	let saved: Record<string, string> | null = null

	test.beforeAll(async ({ browser }) => {
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		saved = await getOverrides(page, await requestToken(page))
		await page.close()
	})

	test.afterAll(async ({ browser }) => {
		const page = await browser.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await setOverrides(page, await requestToken(page), saved ?? {})
		await useTheme(page, 'default')
		await page.close()
	})

	test('Unset under the dark theme: --color-text-selection is Nextcloud\'s dark value', async ({ page }) => {
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		await useTheme(page, 'dark')
		await page.reload({ waitUntil: 'domcontentloaded' })
		const onBody = await page.evaluate(() => getComputedStyle(document.body).getPropertyValue('--color-text-selection').trim())
		expect(onBody).not.toBe('')
		expect(await onBodyChild(page, '--color-text-selection')).toBe(onBody)
	})

	test('Unset under high contrast: --color-loading-light is Nextcloud\'s high-contrast value', async ({ page }) => {
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		await useTheme(page, 'light-highcontrast')
		await page.reload({ waitUntil: 'domcontentloaded' })
		const expected = await nextcloudValue(page, 'light-highcontrast', '--color-loading-light')
		expect(await onBodyChild(page, '--color-loading-light')).toBe(expected)
	})

	test('A set gives the search highlight a colour: <mark> takes the token', async ({ page }) => {
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await useTheme(page, 'default')
		await setOverrides(page, await requestToken(page), { '--color-mark': '#ffe08a' })
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		const background = await page.evaluate(() => {
			const mark = document.createElement('mark')
			mark.textContent = 'hit'
			document.querySelector('#content')?.appendChild(mark)
			return getComputedStyle(mark).backgroundColor
		})
		expect(background).toBe('rgb(255, 224, 138)')
	})

	test('A light-only admin value leaves dark mode alone', async ({ page }) => {
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await setOverrides(page, await requestToken(page), { '--color-main-background': '#fdfcf8' })
		await useTheme(page, 'dark')
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		const expected = await nextcloudValue(page, 'dark', '--color-main-background')
		expect((await onBodyChild(page, '--color-main-background')).toLowerCase()).toBe(expected.toLowerCase())
	})

	test('A set gives a light value only: --color-warning-hover keeps Nextcloud\'s dark value', async ({ page }) => {
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await setOverrides(page, await requestToken(page), { '--color-warning-hover': '#aa5500' })
		await useTheme(page, 'default')
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		expect(await onBodyChild(page, '--color-warning-hover')).toBe('#aa5500')
		await useTheme(page, 'dark')
		await page.reload({ waitUntil: 'domcontentloaded' })
		const expected = await nextcloudValue(page, 'dark', '--color-warning-hover')
		expect((await onBodyChild(page, '--color-warning-hover')).toLowerCase()).toBe(expected.toLowerCase())
	})

	test('A settable variable reaches an element teleported into a modal', async ({ page }) => {
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await useTheme(page, 'default')
		await setOverrides(page, await requestToken(page), { '--color-mark': '#ffe08a' })
		await page.goto('/apps/files/', { waitUntil: 'domcontentloaded' })
		const value = await page.evaluate(() => {
			const modal = document.createElement('div')
			modal.className = 'modal-mask'
			document.body.appendChild(modal)
			return getComputedStyle(modal).getPropertyValue('--color-mark').trim()
		})
		expect(value).toBe('#ffe08a')
	})
})
