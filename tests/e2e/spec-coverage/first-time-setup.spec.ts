/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/changes/first-time-setup/specs/first-time-setup/spec.md
 *
 * Browser proof of the first-time setup change. This file starts with task 4b.4, the switch "Keep
 * the Nextcloud theme in line with the set" on the Theming settings page (REQ-TQFS-013); the wizard
 * walk of tasks 5.1 to 5.4 joins it once the wizard bundle is built.
 *
 * STATE. The test changes the active token set, Nextcloud's core theming and the app value
 * `core_theming_follows_set`. All three are captured first and put back in `finally`: the set
 * through the settings API, core theming with its image bytes (see _core-theming.ts), and the app
 * value through occ, deleted again when it was not stored before, so an instance that was not
 * configured stays that way for the setup wizard.
 */
import { execFileSync } from 'child_process'
import * as fs from 'fs'
import * as path from 'path'
import { test, expect, type Page } from '@playwright/test'
import { ensureNonAdminUser, loginAs, NONADMIN_PASS, NONADMIN_USER } from './_fixtures'
import { getTokenSet, requestToken, setTokenSet } from '../workflows/_helpers'
import { occPrefix } from '../shared-instance'
import { captureCoreTheming, readTheming, restoreCoreTheming } from './_core-theming'

const THEMING_URL = '/settings/admin/theming'
const FOLLOWS_KEY = 'core_theming_follows_set'
const REPO_ROOT = path.resolve(__dirname, '../../..')

/** Two shipped sets that carry a primary colour in their theming metadata. */
const FIRST = 'example-gemeente'
const SECOND = 'cunningham'

type ManifestEntry = { id: string; theming?: Record<string, string> }
const MANIFEST: ManifestEntry[] = JSON.parse(
	fs.readFileSync(path.join(REPO_ROOT, 'token-sets.json'), 'utf8'),
)

/**
 * A set's primary colour as its theming metadata names it, lower-cased.
 *
 * @param id The set.
 * @return The colour.
 */
function primaryOf(id: string): string {
	const colour = MANIFEST.find((entry) => entry.id === id)?.theming?.primary_color
	if (colour === undefined) {
		throw new Error(`${id} has no primary_color in token-sets.json`)
	}
	return colour.toLowerCase()
}

/**
 * Run occ on the instance under test: the prefix of shared-instance.ts, from the server root.
 *
 * @param args The occ arguments.
 * @return The output, trimmed; empty when occ exits non-zero.
 */
function occ(args: string[]): string {
	const prefix = occPrefix()
	try {
		return execFileSync(prefix[0], [...prefix.slice(1), ...args], {
			cwd: path.resolve(REPO_ROOT, '../..'),
			encoding: 'utf8',
		}).trim()
	} catch {
		return ''
	}
}

/**
 * Flip the switch and wait for the save it sends.
 *
 * @param page The settings page.
 * @param on Whether to turn it on.
 */
async function setSwitch(page: Page, on: boolean): Promise<void> {
	const toggle = page.locator('#thematiq-core-theming-follows')
	const saved = page.waitForResponse(
		(r) =>
			r.url().includes('/settings/core-theming-follows')
			&& r.request().method() === 'POST',
	)
	// Nextcloud's `.checkbox` style hides the input and draws its label, so the label is clicked.
	expect(
		await toggle.isChecked(),
		'the switch should start in the other position',
	).toBe(!on)
	await page.locator('label[for="thematiq-core-theming-follows"]').click()
	expect((await saved).status(), 'the switch should save at once').toBe(200)
	expect(await toggle.isChecked()).toBe(on)
}

/**
 * Choose a set in the dropdown and confirm the apply dialog when it opens.
 *
 * @param page The settings page.
 * @param id The set.
 * @return Whether the apply dialog offered the Nextcloud theme sync section.
 */
async function chooseSet(page: Page, id: string): Promise<boolean> {
	const saved = page.waitForResponse(
		(r) =>
			/\/settings\/tokenset(\?|$)/.test(r.url())
			&& r.request().method() === 'POST',
	)
	await page.selectOption('#nldesign-token-set-select', id)
	const dialog = page.locator('#nldesign-apply-dialog-overlay')
	await dialog
		.waitFor({ state: 'visible', timeout: 10_000 })
		.catch(() => undefined)
	let offered = false
	if (await dialog.isVisible()) {
		offered = (await dialog.locator('#nldesign-apply-theming').count()) > 0
		await dialog.locator('.nldesign-dialog-confirm').click()
	}
	expect((await saved).status(), `choosing ${id} should save`).toBe(200)
	return offered
}

test.describe('first-time setup: the Nextcloud theme follows the set', () => {
	test('@e2e the switch brings the set to Nextcloud, a change of set follows without asking, and off asks again', async ({
		page,
	}) => {
		test.setTimeout(180_000)
		await page.goto(THEMING_URL)
		const token = await requestToken(page)
		const previousSet = await getTokenSet(page, token)
		const capture = await captureCoreTheming(page)
		const previousFollows = occ(['config:app:get', 'thematiq', FOLLOWS_KEY])

		try {
			await setTokenSet(page, token, FIRST)
			occ(['config:app:set', 'thematiq', FOLLOWS_KEY, '--value=0'])
			await page.goto(THEMING_URL)
			await expect(
				page.locator('#thematiq-core-theming-follows'),
			).not.toBeChecked()
			await expect(
				page.locator('#thematiq-core-theming-follows'),
			).toHaveAttribute('role', 'switch')

			// On applies the active set to Nextcloud's theme straight away.
			await setSwitch(page, true)
			expect((await readTheming(page)).primary_color.toLowerCase()).toBe(
				primaryOf(FIRST),
			)
			expect(occ(['config:app:get', 'thematiq', FOLLOWS_KEY])).toBe('1')

			// While it is on, a change of set follows without the sync section or dialog.
			const offeredWhileOn = await chooseSet(page, SECOND)
			expect(offeredWhileOn, 'no sync section while the theme follows').toBe(
				false,
			)
			await expect(
				page.locator('#thematiq-core-theming-follows-status'),
			).toHaveText('The Nextcloud theme followed the set.')
			await expect(
				page.locator('#nldesign-theming-dialog-overlay'),
			).toHaveCount(0)
			expect((await readTheming(page)).primary_color.toLowerCase()).toBe(
				primaryOf(SECOND),
			)

			// The stored value survives a reload.
			await page.goto(THEMING_URL)
			await expect(
				page.locator('#thematiq-core-theming-follows'),
			).toBeChecked()

			// Off leaves Nextcloud's theme alone and the next change of set asks again.
			await setSwitch(page, false)
			expect(occ(['config:app:get', 'thematiq', FOLLOWS_KEY])).toBe('0')
			expect((await readTheming(page)).primary_color.toLowerCase()).toBe(
				primaryOf(SECOND),
			)
			// The page asks in one of its two places: the sync section of the apply dialog, or, for
			// a set without an apply preview, the theming sync dialog after the save.
			await page.selectOption('#nldesign-token-set-select', FIRST)
			const applyDialog = page.locator('#nldesign-apply-dialog-overlay')
			await applyDialog
				.waitFor({ state: 'visible', timeout: 5_000 })
				.catch(() => undefined)
			if (await applyDialog.isVisible()) {
				await expect(
					applyDialog.locator('#nldesign-apply-theming'),
				).toHaveCount(1)
			} else {
				await expect(
					page.locator('#nldesign-theming-dialog-overlay'),
				).toBeVisible({ timeout: 10_000 })
			}
			await page.keyboard.press('Escape')
		} finally {
			await setTokenSet(page, token, previousSet)
			await restoreCoreTheming(page, capture)
			if (previousFollows === '') {
				occ(['config:app:delete', 'thematiq', FOLLOWS_KEY])
			} else {
				occ([
					'config:app:set',
					'thematiq',
					FOLLOWS_KEY,
					`--value=${previousFollows}`,
				])
			}
		}
	})
})

/** The app values that decide whether setup is due (SetupStatusService::CONFIGURED_KEYS, setup.*). */
/** Who confirmed which restricted set (TokenSetLicenceService). */
const CONFIRMATIONS = 'restricted_set_confirmations'

// workplace_layout: Apply with the menu on writes the light layout next to
// header_style (LayoutOptionValues::WORKPLACE_TOP_BAR, #2225); left behind it
// restyled the header and the login page of every later spec.
const SETUP_KEYS = [
	'token_set', 'header_style', 'workplace_layout', 'themed_contexts', 'hide_slogan', 'login_watermark',
	'brand_stripe_placement', FOLLOWS_KEY, 'setup.completed', 'setup.skipped',
]

/**
 * thematiq's app values, as occ lists them.
 *
 * @return The values by key.
 */
function appValues(): Record<string, string> {
	try {
		return JSON.parse(occ(['config:list', 'thematiq', '--private', '--output=json'])).apps.thematiq ?? {}
	} catch {
		return {}
	}
}

/** The keys that make setup due when removed: the fixed ones and every `menu.<key>` default. */
function setupKeys(values: Record<string, string>): string[] {
	return Object.keys(values).filter((key) => SETUP_KEYS.includes(key) || key.startsWith('menu.')).concat(SETUP_KEYS)
}

/** Remove the values, so the instance is one nobody set up. */
function makeDue(values: Record<string, string>): void {
	for (const key of new Set(setupKeys(values))) {
		occ(['config:app:delete', 'thematiq', key])
	}
}

/** Put back exactly the values there were. */
function putBack(values: Record<string, string>): void {
	for (const key of new Set(setupKeys(appValues()).concat(setupKeys(values)))) {
		if (key in values) {
			occ(['config:app:set', 'thematiq', key, `--value=${values[key]}`])
		} else {
			occ(['config:app:delete', 'thematiq', key])
		}
	}
}

/** The setup status, as the admin reads it. */
async function setupStatus(page: Page): Promise<{ status: number; json: Record<string, any> }> {
	return page.evaluate(async () => {
		const OC = (window as any).OC
		const r = await fetch(OC.generateUrl('/apps/thematiq/api/setup/status'), { headers: { requesttoken: OC.requestToken } })
		return { status: r.status, json: r.ok ? await r.json() : {} }
	})
}

/**
 * Walk the wizard from the welcome to Done with a set from the gallery, the theme switch and the
 * menu left on, and Apply.
 *
 * @param page The administrator's page, on a page that shows the wizard.
 * @param setName The set's card label.
 * @param restricted Whether the set is restricted (it asks for the statement while not confirmed).
 */
async function applyWithMenu(page: Page, setName: string, restricted: boolean): Promise<void> {
	await next(page).click()
	await page.getByRole('button', { name: /sets in the gallery/ }).click()
	await page.locator('label.tq-setup__card', { hasText: setName }).first().click()
	// A restricted set asks for the statement until someone confirmed it (the full run does).
	const statement = page.locator('label[for="tq-setup-licence-statement"]')
	if (restricted && await statement.waitFor({ timeout: 3_000 }).then(() => true, () => false)) {
		await statement.click()
	}
	await expect(next(page)).toBeEnabled()
	await next(page).click()
	await expect(page.locator('.tq-setup__step h2')).toHaveText('Update the Nextcloud theme')
	await next(page).click()
	await expect(page.locator('.tq-setup__step h2')).toHaveText('The login page')
	await next(page).click()
	await expect(page.locator('.tq-setup__step h2')).toHaveText('The apps menu')
	await expect(next(page)).toHaveText('Apply')
	await next(page).click()
	await expect(page.locator('.tq-setup__step h2')).toHaveText(`Done, ${setName} is set`, { timeout: 120_000 })
}

/** The admin's tour keys, so a test that starts the tour can put them back. */
const TOUR_KEYS = ['apps_menu_walkthrough_seen_version', 'apps_menu_walkthrough_seen_version-progress']

/**
 * Read or write the signed-in person's tour keys.
 *
 * @param page A signed-in page.
 * @param values The values to write, or null to read.
 * @return The values read (or written).
 */
async function tourKeys(page: Page, values: Record<string, unknown> | null): Promise<Record<string, unknown>> {
	return page.evaluate(async ({ keys, write }) => {
		const OC = (window as any).OC
		const out: Record<string, unknown> = {}
		for (const key of keys) {
			const url = OC.generateUrl(`/apps/thematiq/api/preferences/${key}`)
			if (write === null) {
				const r = await fetch(url, { headers: { requesttoken: OC.requestToken } })
				out[key] = r.ok ? ((await r.json()).value ?? '') : ''
			} else {
				await fetch(url, {
					method: 'PUT',
					headers: { requesttoken: OC.requestToken, 'Content-Type': 'application/json' },
					body: JSON.stringify({ value: write[key] ?? '' }),
				})
				out[key] = write[key] ?? ''
			}
		}
		return out
	}, { keys: TOUR_KEYS, write: values })
}

const wizard = (page: Page) => page.locator('#thematiq-setup-wizard')
const next = (page: Page) => page.getByTestId('cn-wizard-next')

/** Open a page and wait for the wizard, or make sure there is none. */
async function expectWizard(page: Page, url: string, shown: boolean): Promise<void> {
	await page.goto(url, { waitUntil: 'domcontentloaded' })
	if (shown) {
		await expect(next(page)).toBeVisible({ timeout: 30_000 })
		await expect(page.getByText('Welcome to Thematiq')).toBeVisible()
		return
	}
	await page.waitForTimeout(3_000)
	await expect(wizard(page)).toHaveCount(0)
}

// @e2e openspec/changes/first-time-setup/specs/first-time-setup/spec.md
test.describe('first-time setup: the wizard', () => {
	test.describe.configure({ mode: 'serial' })
	test.setTimeout(240_000)

	let before: Record<string, string> = {}
	let previousSet = ''
	let capture: Awaited<ReturnType<typeof captureCoreTheming>> | null = null

	test.beforeAll(async ({ browser }) => {
		const context = await browser.newContext()
		const page = await context.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		previousSet = await getTokenSet(page, await requestToken(page))
		capture = await captureCoreTheming(page)
		await ensureNonAdminUser(page)
		await context.close()
		before = appValues()
		// Global setup confirms every restricted set; withdraw amsterdam's, so the statement shows.
		const confirmations = JSON.parse(before[CONFIRMATIONS] || '{}')
		delete confirmations.amsterdam
		occ(['config:app:set', 'thematiq', CONFIRMATIONS, `--value=${JSON.stringify(confirmations)}`])
	})

	test.afterAll(async ({ browser }) => {
		putBack(before)
		if (CONFIRMATIONS in before) {
			occ(['config:app:set', 'thematiq', CONFIRMATIONS, `--value=${before[CONFIRMATIONS]}`])
		} else {
			occ(['config:app:delete', 'thematiq', CONFIRMATIONS])
		}
		const context = await browser.newContext()
		const page = await context.newPage()
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		await setTokenSet(page, await requestToken(page), previousSet)
		if (capture !== null) {
			await restoreCoreTheming(page, capture)
		}
		putBack(before)
		await context.close()
	})

	test.beforeEach(() => {
		// Also the menu defaults an earlier test's Apply stored, which `before` does not name.
		makeDue({ ...before, ...appValues() })
	})

	// @e2e first-time-setup::the-welcome-opens-on-the-dashboard
	test('the welcome opens on the dashboard and in Files; Later writes nothing and it comes back', async ({ page }) => {
		await expectWizard(page, '/index.php/apps/dashboard/', true)
		await next(page).click()
		await page.getByTestId('cn-wizard-cancel').click()
		await expect(wizard(page)).toHaveCount(0)
		expect((await setupStatus(page)).json.completed).toBe(false)
		expect((await setupStatus(page)).json.skipped).toBe(false)
		await expectWizard(page, '/index.php/apps/files/', true)
	})

	// @e2e first-time-setup::a-non-admin-sees-nothing
	test('a non-admin sees nothing, carries no setup status and gets 403', async ({ browser }) => {
		const { page, close } = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		try {
			await expectWizard(page, '/index.php/apps/dashboard/', false)
			expect(await page.locator('#initial-state-thematiq-setup_status').count()).toBe(0)
			expect((await setupStatus(page)).status).toBe(403)
		} finally {
			await close()
		}
	})

	// @e2e first-time-setup::skip-keeps-nextcloud-as-it-is
	test('Skip records the skip, and the next page has no wizard', async ({ page }) => {
		await expectWizard(page, '/index.php/apps/dashboard/', true)
		await page.getByTestId('tq-setup-skip').click()
		await expect(wizard(page)).toHaveCount(0)
		expect((await setupStatus(page)).json.skipped.by).toBe('admin')
		await expectWizard(page, '/index.php/apps/files/', false)
	})

	// @e2e first-time-setup::a-restricted-set-needs-the-statement
	// @e2e first-time-setup::the-version-card-names-who-completed-setup
	test('a full run with amsterdam: the statement holds Next, Apply sets it, the version card names who did it', async ({ page, browser }) => {
		await expectWizard(page, '/index.php/apps/dashboard/', true)
		await next(page).click()
		await page.getByRole('button', { name: /sets in the gallery/ }).click()
		await page.locator('label.tq-setup__card', { hasText: 'Gemeente Amsterdam' }).first().click()
		await expect(page.locator('.tq-setup__licence')).toBeVisible()
		await expect(next(page)).toBeDisabled()
		await page.locator('label[for="tq-setup-licence-statement"]').click()
		await expect(next(page)).toBeEnabled()
		await next(page).click()
		await expect(page.locator('.tq-setup__step h2')).toHaveText('Update the Nextcloud theme')
		await expect(page.locator('#tq-setup-follows')).toBeChecked()
		await next(page).click()
		await expect(page.locator('.tq-setup__step h2')).toHaveText('The login page')
		await page.locator('label[for="tq-setup-slogan"]').click()
		await next(page).click()
		await expect(page.locator('.tq-setup__step h2')).toHaveText('The apps menu')
		await page.locator('#tq-setup-columns').selectOption('3')
		await expect(page.getByText('Workplace', { exact: true })).toHaveCount(0)
		await expect(next(page)).toHaveText('Apply')
		await next(page).click()
		await expect(page.locator('.tq-setup__step h2')).toHaveText('Done, Gemeente Amsterdam is set', { timeout: 120_000 })
		// The hand-off to the walkthrough is offered; what it stores is pinned by tests/vitest/setupWizard.spec.js.
		await expect(page.getByTestId('tq-setup-walkthrough')).toBeVisible()
		const status = (await setupStatus(page)).json
		expect(status.current.tokenSet).toBe('amsterdam')
		expect(status.current.hideSlogan).toBe(true)
		expect(status.completed.by).toBe('admin')
		const theming = await readTheming(page)
		expect(theming.primary_color.toLowerCase()).toBe(primaryOf('amsterdam'))
		// The logo and favicon slots carry exactly what the set's theming metadata brings (ScheduledCoreThemingSync).
		const meta = MANIFEST.find((entry) => entry.id === 'amsterdam')?.theming ?? {}
		expect(meta.logo, 'CONTROL: amsterdam ships a logo').toBeTruthy()
		expect(theming.has_custom_logo, 'the set\'s logo is Nextcloud\'s logo').toBe(true)
		expect(theming.synced_logo).toBe(meta.logo)
		expect(theming.synced_favicon).toBe(meta.favicon ?? '')

		// An anonymous login page has no slogan; a non-admin gets the workplace menu with 3 columns.
		const guest = await browser.newContext({ storageState: undefined })
		try {
			const login = await guest.newPage()
			await login.goto('/index.php/login', { waitUntil: 'domcontentloaded' })
			await expect(login.locator('footer.guest-box')).toBeHidden()
		} finally {
			await guest.close()
		}
		const member = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		try {
			const menu = await member.page.evaluate(async () => {
				const OC = (window as any).OC
				const r = await fetch(OC.generateUrl('/apps/thematiq/app-menu/settings'), { headers: { requesttoken: OC.requestToken } })
				return (await r.json()).effective
			})
			expect(menu.style).toBe('workplace')
			expect(menu.columns).toBe(3)
		} finally {
			await member.close()
		}

		// The version card, and Set up again with the stored set; Later leaves setup done.
		await page.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
		const card = page.locator('#thematiq-version-card')
		await expect(card.locator('.thematiq-version-card__state')).toHaveText(/^Completed by admin on /, { timeout: 30_000 })
		await card.locator('.thematiq-version-card__setup').click()
		await expect(next(page)).toBeVisible({ timeout: 30_000 })
		await next(page).click()
		await expect(page.locator('.tq-setup__card--on').first()).toContainText('Gemeente Amsterdam')
		await page.getByTestId('cn-wizard-cancel').click()
		expect((await setupStatus(page)).json.completed.by).toBe('admin')
		await expectWizard(page, '/index.php/apps/files/', false)
	})

	// @e2e first-time-setup::the-sets-logo-colours-and-favicon-reach-nextcloud
	// @e2e first-time-setup::start-the-walkthrough
	test('zuiddrecht: its logo, colour and favicon reach Nextcloud, and Start the apps menu walkthrough opens the menu with the tour', async ({ page }) => {
		await expectWizard(page, '/index.php/apps/dashboard/', true)
		await applyWithMenu(page, 'Gemeente Zuiddrecht', false)
		const meta = MANIFEST.find((entry) => entry.id === 'zuiddrecht')?.theming ?? {}
		expect(meta.logo && meta.favicon, 'CONTROL: zuiddrecht ships a logo and a favicon').toBeTruthy()
		const theming = await readTheming(page)
		expect(theming.primary_color.toLowerCase()).toBe(primaryOf('zuiddrecht'))
		expect(theming.has_custom_logo).toBe(true)
		expect(theming.has_custom_favicon).toBe(true)
		expect(theming.synced_logo).toBe(meta.logo)
		expect(theming.synced_favicon).toBe(meta.favicon)

		const before = await tourKeys(page, null)
		try {
			await page.getByTestId('tq-setup-walkthrough').click()
			const card = page.locator('.thematiq-menu-walkthrough .cn-walkthrough__card')
			await expect(card).toBeVisible({ timeout: 30_000 })
			await expect(wizard(page)).toHaveCount(0)
			await expect(page.locator('.app-menu__waffle')).toHaveAttribute('aria-expanded', 'true')
			await expect(card.locator('.cn-walkthrough__title')).toHaveText('Open the apps menu')
		} finally {
			await tourKeys(page, before)
		}
	})

	// @e2e first-time-setup::turning-the-menu-on-brings-the-workplace-top-bar
	test('turning the menu on brings the workplace top bar for a set without it (amsterdam)', async ({ page, browser }) => {
		await expectWizard(page, '/index.php/apps/dashboard/', true)
		await applyWithMenu(page, 'Gemeente Amsterdam', true)
		expect(occ(['config:app:get', 'thematiq', 'header_style'])).toBe('workplace')
		const member = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		try {
			await member.page.goto('/index.php/apps/files/', { waitUntil: 'domcontentloaded' })
			// The workplace top bar is css/header-workplace.css with thematiq's apps menu in it.
			await expect(member.page.locator('link[href*="thematiq/css/header-workplace.css"]')).toHaveCount(1)
			expect(await member.page.evaluate(() => {
				try {
					return (window as any).OCP.InitialState.loadState('thematiq', 'app-menu', null) !== null
				} catch {
					return false
				}
			}), 'thematiq draws its apps menu for the member').toBe(true)
		} finally {
			await member.close()
		}
	})
})
