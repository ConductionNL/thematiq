/**
 * @vitest-environment jsdom
 *
 * The version card on the Theming settings page (js/admin-version-card.js, board TqSetupOpnieuw):
 * the setup's state in the board's words, Set up again opens the wizard with the stored choices,
 * the walkthrough button replays the tour, and under a configuration source Set up again is disabled.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/first-time-setup/spec.md#requirement-the-version-card-shows-the-setup-and-runs-it-again-req-tqfs-012
 */

import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'

const CARD = `
	<section id="thematiq-version-card">
		<dd class="thematiq-version-card__version">-</dd>
		<span class="thematiq-version-card__state">-</span>
		<span class="thematiq-version-card__detail"></span>
		<button type="button" class="thematiq-version-card__setup">Set up again</button>
		<button type="button" class="thematiq-version-card__walkthrough">Apps menu walkthrough</button>
		<p class="thematiq-version-card__locked" hidden>Managed</p>
	</section>`

const COMPLETED = {
	version: '1.8.0',
	completed: { by: 'admin', at: '2026-11-27T13:52:00' },
	skipped: false,
	configured: true,
	current: { tokenSet: 'zuiddrecht', nextcloudThemeFollows: true },
}

let answers

/**
 * Load the card script over the page.
 *
 * @return {Promise<void>}
 */
async function load() {
	vi.resetModules()
	// The script fills the card itself on load; wait for its two reads.
	await import('../../js/admin-version-card.js?t=' + Math.random())
	await new Promise((resolve) => setTimeout(resolve, 0))
}

describe('the version card', () => {
	beforeEach(() => {
		document.body.innerHTML = CARD
		window.OC = { requestToken: 'token', generateUrl: (path) => '/index.php' + path, filePath: (app, dir, file) => '/apps/' + app + '/' + dir + '/' + file }
		answers = {
			'/index.php/apps/thematiq/api/setup/status': COMPLETED,
			'/index.php/apps/thematiq/settings/config-source': { managed: false },
		}
		vi.stubGlobal('fetch', vi.fn((url) => Promise.resolve({ ok: url in answers, json: () => Promise.resolve(answers[url]) })))
	})

	afterEach(() => {
		document.body.innerHTML = ''
		document.head.querySelectorAll('script').forEach((script) => script.remove())
		vi.unstubAllGlobals()
		delete window.OC
		delete window.thematiqSetupWizard
		delete window.thematiqSetupWizardManual
		delete window.thematiqAppMenuReplay
	})

	it('names who completed setup and when, with the set and the theme switch', async () => {
		await load()
		expect(document.querySelector('.thematiq-version-card__version').textContent).toBe('1.8.0')
		expect(document.querySelector('.thematiq-version-card__state').textContent).toBe('Completed by admin on 27-11-2026, 13:52')
		expect(document.querySelector('.thematiq-version-card__detail').textContent).toBe('Token set zuiddrecht applied · Nextcloud theme kept in line')
		expect(document.querySelector('.thematiq-version-card__setup').disabled).toBe(false)
	})

	it('says skipped, set up by an earlier version, or not set up yet', async () => {
		await load()
		const { describe: lines } = window.thematiqVersionCard
		expect(lines({ skipped: { by: 'anna', at: '2026-11-27T09:05:00' } }).state).toBe('Skipped by anna on 27-11-2026, 09:05')
		expect(lines({ configured: true, current: { tokenSet: 'amsterdam' } })).toEqual({ state: 'Set up by an earlier version', detail: 'Token set amsterdam' })
		expect(lines({}).state).toBe('Not set up yet')
	})

	it('Set up again loads the wizard by hand and opens it with the stored choices', async () => {
		await load()
		document.querySelector('.thematiq-version-card__setup').click()
		const script = document.head.querySelector('script[data-thematiq-setup-wizard-manual]')
		expect(script.getAttribute('src')).toBe('/apps/thematiq/js/vendor/setupWizard.js')
		expect(window.thematiqSetupWizardManual).toBe(true)

		const open = vi.fn()
		window.thematiqSetupWizard = { open }
		script.dispatchEvent(new Event('load'))
		expect(open).toHaveBeenCalledWith(COMPLETED)

		document.querySelector('.thematiq-version-card__setup').click()
		expect(open).toHaveBeenCalledTimes(2)
		expect(document.head.querySelectorAll('script[data-thematiq-setup-wizard-manual]')).toHaveLength(1)
	})

	it('replays the apps menu walkthrough', async () => {
		window.thematiqAppMenuReplay = vi.fn()
		await load()
		document.querySelector('.thematiq-version-card__walkthrough').click()
		expect(window.thematiqAppMenuReplay).toHaveBeenCalled()
	})

	it('disables Set up again under a configuration source, with the note', async () => {
		answers['/index.php/apps/thematiq/settings/config-source'] = { managed: true }
		await load()
		expect(document.querySelector('.thematiq-version-card__setup').disabled).toBe(true)
		expect(document.querySelector('.thematiq-version-card__locked').hidden).toBe(false)
	})
})
