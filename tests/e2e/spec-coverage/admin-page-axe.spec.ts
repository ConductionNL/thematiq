/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The admin Theming page against axe's WCAG 2.2 AA rules: the overview, every
 * panel the template declares (read from templates/settings/admin.php, so a new
 * panel is checked without editing this file) and each dialog that opens with
 * a click. Read-only: every dialog is closed with Escape, nothing is saved.
 */
import { test, expect, type Page } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import * as fs from 'fs'
import * as path from 'path'

const THEMING_URL = '/settings/admin/theming'
const TAGS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa']

/**
 * Nextcloud core markup on the page, which thematiq does not draw. Each entry
 * names why it is left out of the scan.
 */
const CORE_EXCLUSIONS: Array<{ selector: string; reason: string }> = [
	{
		selector: '#settings-admin-theming .v-select',
		reason: "core theming's own NcSelect in the Theme card has no input label; its form is core markup thematiq only frames",
	},
]

/**
 * Frames the scan leaves out, and why. axe runs its script inside every frame
 * and waits for the answer; a sandboxed frame without allow-scripts never
 * answers, so the scan hung until the test's timeout.
 */
const FRAME_EXCLUSIONS: Array<{ selector: string; reason: string }> = [
	{
		selector: '.nldesign-own-frame',
		reason: "the #components/own preview: the administrator's own component HTML in an iframe sandboxed without scripts (allow-same-origin only), the way it is shown on purpose; it is the administrator's content, not the page",
	},
]

/** The panels whose hash takes an argument, with the argument the scan opens. */
const PANEL_ARGUMENTS: Record<string, string[]> = {
	set: ['zuiddrecht'],
	components: ['', 'own'],
}

/** The dialogs that open with one click, by the control that opens them. */
const CLICK_DIALOGS: Array<{ name: string; hash: string; opener: string }> = [
	{ name: 'TqTerugNaarNextcloud', hash: '', opener: '#nldesign-reset-theme-btn' },
	{ name: 'TqBasistokens', hash: '', opener: 'label[for=nldesign-base-unlock]' },
	{ name: 'TqOverridesUploaden', hash: '', opener: '#nldesign-import-btn' },
	{
		name: 'TqTokenToevoegen',
		hash: 'own-tokens',
		opener: '#nldesign-own-token-add',
	},
	{
		name: 'TqTokenUitfaseren',
		hash: 'own-tokens',
		opener: '#nldesign-deprecation-add',
	},
]

/**
 * The panel hashes declared in the admin template.
 *
 * @return {string[]} One entry per `data-thematiq-panel` value, in template order.
 */
function panelHashes(): string[] {
	const template = fs.readFileSync(
		path.resolve(__dirname, '../../../templates/settings/admin.php'),
		'utf8',
	)
	const hashes = [...template.matchAll(/data-thematiq-panel="([^"]+)"/g)].map(
		(match) => match[1],
	)
	return [...new Set(hashes)].flatMap((hash) =>
		(PANEL_ARGUMENTS[hash] ?? ['']).map((arg) =>
			arg === '' ? hash : hash + '/' + arg,
		),
	)
}

/**
 * Run axe on the page and fail with a readable list of what it found.
 *
 * @param {Page} page The page.
 * @param {string} what The state being scanned, for the failure message.
 */
async function expectNoViolations(page: Page, what: string): Promise<void> {
	// Let the transitions settle. Only finite ones: an infinite animation (the
	// code editor's blinking cursor on #components/own) never finishes, and
	// waiting for it held the test until its timeout.
	await page.evaluate(() =>
		Promise.all(
			document
				.getAnimations()
				.filter(
					(animation) =>
						animation.effect?.getComputedTiming().iterations
						!== Infinity,
				)
				.map((animation) => animation.finished.catch(() => undefined)),
		),
	)
	let builder = new AxeBuilder({ page }).withTags(TAGS)
	for (const exclusion of [...CORE_EXCLUSIONS, ...FRAME_EXCLUSIONS]) {
		builder = builder.exclude(exclusion.selector)
	}
	const { violations } = await builder.analyze()
	const report = violations.map(
		(violation) =>
			violation.id
			+ ' ('
			+ violation.impact
			+ '): '
			+ violation.nodes
				.slice(0, 5)
				.map(
					(node) =>
						node.target.join(' ')
						+ ' '
						+ node.html.slice(0, 160)
						+ ' '
						+ (node.failureSummary ?? '').replace(/\s+/g, ' '),
				)
				.join(' | '),
	)
	expect(report, what).toEqual([])
}

/**
 * Open the Theming page at a hash and wait until thematiq has drawn it.
 *
 * @param {Page} page The page.
 * @param {string} hash The panel hash, or '' for the overview.
 */
async function openAt(page: Page, hash: string): Promise<void> {
	await page.goto(THEMING_URL + (hash === '' ? '' : '#' + hash))
	await page.waitForLoadState('domcontentloaded')
	await expect(page.locator('#thematiq-page-header')).toBeVisible()
	if (hash !== '') {
		await expect(
			page.locator(
				'section[data-thematiq-panel="' + hash.split('/')[0] + '"]',
			),
		).toBeVisible()
	}
}

test.describe('the admin Theming page against WCAG 2.2 AA', () => {
	test('the overview', async ({ page }) => {
		await openAt(page, '')
		await expectNoViolations(page, 'overview')
	})

	for (const hash of panelHashes()) {
		test('the panel at #' + hash, async ({ page }) => {
			await openAt(page, hash)
			await expectNoViolations(page, '#' + hash)
		})
	}

	for (const dialog of CLICK_DIALOGS) {
		test('the ' + dialog.name + ' dialog', async ({ page }) => {
			await openAt(page, dialog.hash)
			await page.locator(dialog.opener).click()
			const open = page
				.locator('dialog[open], [role="dialog"][aria-modal="true"]:visible')
				.last()
			await expect(open).toBeVisible()
			await expectNoViolations(page, dialog.name)
			await page.keyboard.press('Escape')
			await expect(open).toBeHidden()
		})
	}
})
