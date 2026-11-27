/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The apps menu walkthrough (openspec/specs/apps-menu-walkthrough) starts the first time a person
 * opens the apps menu, and its dimmer takes every click and drag until it is closed. A fresh e2e
 * account has never seen it, so every spec that opens the menu would meet the tour instead of the
 * menu. This marks the tour as seen for the signed-in account, as finishing it would: the seen
 * version is the installed version's core. apps-menu-walkthrough.spec.ts clears it again for its
 * own tests.
 */
import type { Page } from '@playwright/test'

/** The person's seen version of the apps menu tour (WalkthroughPreferences::SEEN). */
const SEEN_URL =
	'/index.php/apps/thematiq/api/preferences/apps_menu_walkthrough_seen_version'

/** The public OpenAPI document, whose info.version is the installed app version. */
const OPENAPI_URL = '/index.php/apps/thematiq/api/openapi.json'

/**
 * Record the apps menu tour as seen for the account signed in on this page. Never throws: an
 * instance without the walkthrough answers 404, which leaves nothing to do.
 *
 * @param page A page of the signed-in account, on any Nextcloud page.
 * @return Resolves once the preference is stored or the attempt is given up.
 */
export async function markMenuTourSeen(page: Page): Promise<void> {
	await page
		.waitForFunction(
			() =>
				typeof (window as unknown as { OC?: { requestToken?: unknown } }).OC
					?.requestToken === 'string',
			null,
			{ timeout: 30_000 },
		)
		.catch(() => undefined)
	await page
		.evaluate(
			async ({ seenUrl, openApiUrl }) => {
				const OC = (window as unknown as { OC?: { requestToken: string } })
					.OC
				if (OC === undefined) return
				const doc = await fetch(openApiUrl).then((r) =>
					r.ok ? r.json() : null,
				)
				const version = String(doc?.info?.version ?? '').split('-')[0]
				if (version === '') return
				await fetch(seenUrl, {
					method: 'PUT',
					headers: {
						requesttoken: OC.requestToken,
						'Content-Type': 'application/json',
					},
					body: JSON.stringify({ value: version }),
				})
			},
			{ seenUrl: SEEN_URL, openApiUrl: OPENAPI_URL },
		)
		.catch(() => undefined)
}
