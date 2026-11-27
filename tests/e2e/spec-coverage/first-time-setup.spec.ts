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
import { getTokenSet, requestToken, setTokenSet } from '../workflows/_helpers'
import { occPrefix } from '../shared-instance'
import {
	captureCoreTheming,
	readTheming,
	restoreCoreTheming,
} from './_core-theming'

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
		(r) => r.url().includes('/settings/core-theming-follows') && r.request().method() === 'POST',
	)
	if (on) {
		await toggle.check()
	} else {
		await toggle.uncheck()
	}
	expect((await saved).status(), 'the switch should save at once').toBe(200)
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
		(r) => /\/settings\/tokenset(\?|$)/.test(r.url()) && r.request().method() === 'POST',
	)
	await page.selectOption('#nldesign-token-set-select', id)
	const dialog = page.locator('#nldesign-apply-dialog-overlay')
	await dialog.waitFor({ state: 'visible', timeout: 10_000 }).catch(() => undefined)
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
		await page.goto(THEMING_URL)
		const token = await requestToken(page)
		const previousSet = await getTokenSet(page, token)
		const capture = await captureCoreTheming(page)
		const previousFollows = occ(['config:app:get', 'thematiq', FOLLOWS_KEY])

		try {
			await setTokenSet(page, token, FIRST)
			occ(['config:app:set', 'thematiq', FOLLOWS_KEY, '--value=0'])
			await page.goto(THEMING_URL)
			await expect(page.locator('#thematiq-core-theming-follows')).not.toBeChecked()
			await expect(page.locator('#thematiq-core-theming-follows')).toHaveAttribute('role', 'switch')

			// On applies the active set to Nextcloud's theme straight away.
			await setSwitch(page, true)
			expect((await readTheming(page)).primary_color.toLowerCase()).toBe(primaryOf(FIRST))
			expect(occ(['config:app:get', 'thematiq', FOLLOWS_KEY])).toBe('1')

			// While it is on, a change of set follows without the sync section or dialog.
			const offeredWhileOn = await chooseSet(page, SECOND)
			expect(offeredWhileOn, 'no sync section while the theme follows').toBe(false)
			await expect(page.locator('#thematiq-core-theming-follows-status')).toHaveText(
				'The Nextcloud theme followed the set.',
			)
			await expect(page.locator('#nldesign-theming-dialog-overlay')).toHaveCount(0)
			expect((await readTheming(page)).primary_color.toLowerCase()).toBe(primaryOf(SECOND))

			// The stored value survives a reload.
			await page.goto(THEMING_URL)
			await expect(page.locator('#thematiq-core-theming-follows')).toBeChecked()

			// Off leaves Nextcloud's theme alone and the next change of set asks again.
			await setSwitch(page, false)
			expect(occ(['config:app:get', 'thematiq', FOLLOWS_KEY])).toBe('0')
			expect((await readTheming(page)).primary_color.toLowerCase()).toBe(primaryOf(SECOND))
			await page.selectOption('#nldesign-token-set-select', FIRST)
			const dialog = page.locator('#nldesign-apply-dialog-overlay')
			await expect(dialog).toBeVisible({ timeout: 10_000 })
			await expect(dialog.locator('#nldesign-apply-theming')).toHaveCount(1)
			await page.keyboard.press('Escape')
		} finally {
			await setTokenSet(page, token, previousSet)
			await restoreCoreTheming(page, capture)
			if (previousFollows === '') {
				occ(['config:app:delete', 'thematiq', FOLLOWS_KEY])
			} else {
				occ(['config:app:set', 'thematiq', FOLLOWS_KEY, `--value=${previousFollows}`])
			}
		}
	})
})
