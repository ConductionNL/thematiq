/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The overview and the panels of the admin Theming page (the Tq page boards
 * of admin-pages-as-the-tq-boards, tasks 1.x and 3.5 to 3.20): each panel
 * opens from its real trigger on the overview, shows its title in the page
 * header, its main control works, and the browser's back button returns to
 * the overview. The token set (3.1) and components panels (3.2, 3.3) have
 * their own specs: token-set-panel.spec.ts and components-panel.spec.ts.
 *
 * Read-only: what a test changes is page state that is never saved.
 */
import { test, expect, type Page, type Locator } from '@playwright/test'

const THEMING_URL = '/settings/admin/theming'

/**
 * Open the overview of the Theming page.
 *
 * @param {Page} page The page.
 */
async function openOverview(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await expect(page.locator('#thematiq-page-header')).toBeVisible({
		timeout: 30_000,
	})
}

/**
 * The section of a panel.
 *
 * @param {Page} page The page.
 * @param {string} hash The panel hash.
 * @return {Locator} The section.
 */
function panel(page: Page, hash: string): Locator {
	return page.locator('section[data-thematiq-panel="' + hash + '"]')
}

/** Each panel: its hash, the link on the overview that opens it, and its title. */
const PANELS: Array<{ task: string; hash: string; trigger: string; title: string }> =
	[
		{
			task: '3.5',
			hash: 'layout',
			trigger: '#nldesign-settings a.thematiq-card__link[href="#layout"]',
			title: 'Layout and options',
		},
		{
			task: '3.6',
			hash: 'dark',
			trigger: '#nldesign-settings a.thematiq-card__link[href="#dark"]',
			title: 'Dark variant',
		},
		{
			task: '3.7',
			hash: 'own-sets',
			trigger: '.thematiq-tools a[href="#own-sets"]',
			title: 'Own token sets',
		},
		{
			task: '3.8',
			hash: 'own-tokens',
			trigger: '.thematiq-tools a[href="#own-tokens"]',
			title: 'Own tokens',
		},
		{
			task: '3.9',
			hash: 'brands',
			trigger: '.thematiq-tools a[href="#brands"]',
			title: 'Brand per app',
		},
		{
			task: '3.10',
			hash: 'groups',
			trigger: '.thematiq-tools a[href="#groups"]',
			title: 'House style per group',
		},
		{
			task: '3.11',
			hash: 'fonts',
			trigger: '.thematiq-tools a[href="#fonts"]',
			title: 'Typefaces',
		},
		{
			task: '3.12',
			hash: 'email',
			trigger: '.thematiq-tools a[href="#email"]',
			title: 'Email and documents',
		},
		{
			task: '3.13',
			hash: 'css',
			trigger: '.thematiq-tools a[href="#css"]',
			title: 'Custom CSS',
		},
		{
			task: '3.14',
			hash: 'switches',
			trigger: '.thematiq-tools a[href="#switches"]',
			title: 'Scheduled switches',
		},
		{
			task: '3.15',
			hash: 'gallery',
			trigger: '.thematiq-tools a[href="#gallery"]',
			title: 'Theme gallery and updates',
		},
		{
			task: '3.16',
			hash: 'bundle',
			trigger: '.thematiq-tools a[href="#bundle"]',
			title: 'Configuration bundle',
		},
		{
			task: '3.17',
			hash: 'contrast',
			trigger: '.thematiq-tools a[href="#contrast"]',
			title: 'Contrast report',
		},
		{
			task: '3.18',
			hash: 'log',
			trigger: '.thematiq-tools a[href="#log"]',
			title: 'Log',
		},
		{
			task: '3.19',
			hash: 'assistant',
			trigger: '.thematiq-tools a[href="#assistant"]',
			title: 'AI assistant',
		},
		{
			task: '3.20',
			hash: 'environment',
			trigger: '.thematiq-tools a[href="#environment"]',
			title: 'Environment marker',
		},
	]

/**
 * The main control of a panel, worked without saving. A panel without an
 * entry is checked for its cards only.
 */
const ACTIONS: Record<string, (page: Page, section: Locator) => Promise<void>> = {
	dark: async (page, section) => {
		const rows = section.locator(
			'.thematiq-dark-panel__row:not(.thematiq-dark-panel__row--head)',
		)
		// The rows are drawn from a request after the panel opens: count them
		// once they are there, or `all` is 0 and the last check fails on 15.
		await expect(rows.first()).toBeAttached()
		const all = await rows.count()
		const only = section.locator('.thematiq-dark-panel__only-warnings')
		await only.check()
		expect(await rows.count()).toBeLessThanOrEqual(all)
		await only.uncheck()
		await expect(rows).toHaveCount(all)
	},
	'own-sets': async (page, section) => {
		const colours = section.locator('#nldesign-create-tab-colours')
		await colours.click()
		await expect(colours).toHaveAttribute('aria-selected', 'true')
		await expect(section.locator('#nldesign-brand-primary')).toBeVisible()
		await section.locator('#nldesign-create-tab-upload').click()
		await expect(section.locator('#nldesign-upload-btn')).toBeVisible()
	},
	'own-tokens': async (page, section) => {
		await expect(section.locator('#nldesign-own-token-add')).toBeVisible()
		await expect(section.locator('#nldesign-deprecation-add')).toBeVisible()
	},
	groups: async (page, section) => {
		const rows = section.locator('.nldesign-group-theming-row')
		const add = section.locator('#nldesign-group-theming-add')
		// Add is enabled once the stored mappings have rendered: count after.
		await expect(add).toBeEnabled()
		const before = await rows.count()
		await add.click()
		await expect(rows).toHaveCount(before + 1)
		await expect(rows.last().locator('[data-field="group"]')).toBeFocused()
	},
	css: async (page, section) => {
		await expect(section.locator('#nldesign-custom-css-input')).toBeVisible()
		await expect(section.locator('#nldesign-custom-css-save')).toBeEnabled()
	},
	switches: async (page, section) => {
		expect(
			await section.locator('#nldesign-scheduled-set option').count(),
		).toBeGreaterThan(1)
		await expect(section.locator('#nldesign-scheduled-submit')).toBeVisible()
	},
	contrast: async (page, section) => {
		await expect(section.locator('tbody tr').first()).toBeVisible({
			timeout: 20_000,
		})
	},
	email: async (page, section) => {
		await expect(
			section.locator('#nldesign-email-footer-org-name'),
		).toBeVisible()
		await expect(
			section.locator('#nldesign-documents-footer-line'),
		).toBeVisible()
	},
	assistant: async (page, section) => {
		await expect(
			section.locator('#nldesign-assistant-mark-enabled'),
		).toBeAttached()
		await expect(section.locator('#nldesign-assistant-mark-save')).toBeVisible()
	},
}

test.describe('the overview of the admin Theming page', () => {
	test('1.1 to 1.6: header, active token set tabs, token sets table and its row menu', async ({
		page,
	}) => {
		await openOverview(page)
		const header = page.locator('#thematiq-page-header')
		await expect(header.locator('.thematiq-page-header__title')).not.toBeEmpty()

		const tabs = page.locator('.thematiq-active-set-preview__tabs [role="tab"]')
		await expect(tabs).toHaveCount(2)
		await tabs.nth(1).click()
		await expect(tabs.nth(1)).toHaveAttribute('aria-selected', 'true')
		await tabs.nth(0).click()
		await expect(tabs.nth(0)).toHaveAttribute('aria-selected', 'true')

		const table = page.locator('#thematiq-token-sets')
		await table.locator('.thematiq-token-sets__search').fill('Zuiddrecht')
		const row = table.locator('tbody tr', { hasText: 'Gemeente Zuiddrecht' })
		await expect(row).toBeVisible()
		const toggle = row.locator('.thematiq-token-sets__toggle')
		await toggle.click()
		const menu = row.locator('[role="menu"]')
		await expect(menu).toBeVisible()
		await expect(menu.getByRole('menuitem').first()).toBeFocused()
		await expect(
			menu.getByRole('menuitem', { name: 'Open token set' }),
		).toHaveAttribute('href', '#set/zuiddrecht')
		await page.keyboard.press('Escape')
		await expect(menu).toBeHidden()
		await expect(toggle).toBeFocused()

		// Every More settings link leads to a panel this spec or its own spec
		// covers: components (3.2) and motion (3.4) have specs of their own.
		const covered = new Set(
			PANELS.map((entry) => entry.hash).concat(['components', 'motion']),
		)
		const hashes = await page
			.locator('.thematiq-tools a')
			.evaluateAll((links) =>
				links.map((link) =>
					(link.getAttribute('href') ?? '').replace(/^#/, ''),
				),
			)
		expect(hashes.filter((hash) => !covered.has(hash))).toEqual([])
	})
})

test.describe('the panels of the admin Theming page', () => {
	for (const entry of PANELS) {
		test(
			entry.task
				+ ' #'
				+ entry.hash
				+ ' opens from the overview and goes back',
			async ({ page }) => {
				await openOverview(page)
				await page.locator(entry.trigger).first().click()
				await expect(page).toHaveURL(new RegExp('#' + entry.hash + '$'))
				const section = panel(page, entry.hash)
				await expect(section).toBeVisible()
				await expect(
					page.locator(
						'#thematiq-page-header .thematiq-page-header__title',
					),
				).toHaveText(entry.title)
				await expect(section.locator('h2').first()).toBeVisible()

				const action = ACTIONS[entry.hash]
				if (action !== undefined) {
					await action(page, section)
				}

				await page.goBack()
				await expect(section).toBeHidden()
				await expect(page.locator('#thematiq-token-sets')).toBeVisible()
			},
		)
	}
})
