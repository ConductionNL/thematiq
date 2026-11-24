/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Licence confirmations for the restricted token sets the suite applies.
 *
 * A restricted set (a municipality's house style) is refused with 403 until an
 * administrator confirms the organisation may use it. Many specs apply such a
 * set (amsterdam, rijkshuisstijl, vng, ...) to test the theming itself, not the
 * licence gate. Global setup confirms every restricted set that is not yet
 * confirmed and records which ones it confirmed; global teardown withdraws
 * exactly those, so the instance keeps the confirmations it had before.
 */

import { request, type APIRequestContext } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'

/** Where setup records the sets it confirmed, for teardown to withdraw. */
export const CONFIRMED_FILE = path.resolve(
	__dirname,
	'.auth',
	'confirmed-restricted-sets.json',
)

const LICENCES = '/index.php/apps/thematiq/settings/tokensets'

interface LicenceRow {
	id: string
	licence?: { restricted?: boolean }
	confirmation: unknown
}

/**
 * An API client authenticated as the administrator.
 *
 * @param baseURL The Nextcloud base URL.
 * @param user The administrator's user name.
 * @param password The administrator's password.
 * @return {Promise<APIRequestContext>} The client.
 */
async function adminApi(
	baseURL: string,
	user: string,
	password: string,
): Promise<APIRequestContext> {
	return request.newContext({
		baseURL,
		// Sent up front: Nextcloud's app routes answer 401 without the
		// challenge that would make Playwright send httpCredentials.
		extraHTTPHeaders: {
			Authorization:
				'Basic ' + Buffer.from(`${user}:${password}`).toString('base64'),
			'OCS-APIRequest': 'true',
			Accept: 'application/json',
		},
	})
}

/**
 * Confirm every restricted set that has no confirmation yet.
 *
 * @param baseURL The Nextcloud base URL.
 * @param user The administrator's user name.
 * @param password The administrator's password.
 * @return {Promise<string[]>} The ids this call confirmed.
 */
export async function confirmRestrictedSets(
	baseURL: string,
	user: string,
	password: string,
): Promise<string[]> {
	const api = await adminApi(baseURL, user, password)
	const confirmed: string[] = []
	try {
		const res = await api.get(`${LICENCES}/licences`)
		if (!res.ok()) {
			throw new Error(
				`GET ${LICENCES}/licences answered ${res.status()}, so no restricted set was confirmed.`,
			)
		}
		const rows = ((await res.json()).sets ?? []) as LicenceRow[]
		for (const row of rows) {
			if (row.licence?.restricted !== true || row.confirmation !== null) {
				continue
			}
			const done = await api.post(
				`${LICENCES}/${encodeURIComponent(row.id)}/licence/confirmation`,
				{ data: { confirmed: true } },
			)
			if (!done.ok()) {
				throw new Error(
					`Confirming the licence of ${row.id} answered ${done.status()}.`,
				)
			}
			confirmed.push(row.id)
		}
	} finally {
		fs.mkdirSync(path.dirname(CONFIRMED_FILE), { recursive: true })
		fs.writeFileSync(CONFIRMED_FILE, JSON.stringify(confirmed))
		await api.dispose()
	}
	return confirmed
}

/**
 * Withdraw the confirmations setup recorded.
 *
 * A set still applied somewhere answers 409 and keeps its confirmation; that
 * is reported, not thrown, so one leftover does not hide the run's verdict.
 *
 * @param baseURL The Nextcloud base URL.
 * @param user The administrator's user name.
 * @param password The administrator's password.
 * @return {Promise<void>}
 */
export async function withdrawRecordedConfirmations(
	baseURL: string,
	user: string,
	password: string,
): Promise<void> {
	if (!fs.existsSync(CONFIRMED_FILE)) {
		return
	}
	const ids = JSON.parse(fs.readFileSync(CONFIRMED_FILE, 'utf8')) as string[]
	const api = await adminApi(baseURL, user, password)
	const kept: string[] = []
	try {
		for (const id of ids) {
			const res = await api.delete(
				`${LICENCES}/${encodeURIComponent(id)}/licence/confirmation`,
			)
			if (!res.ok()) {
				kept.push(`${id} (${res.status()})`)
			}
		}
	} finally {
		await api.dispose()
	}
	fs.rmSync(CONFIRMED_FILE)
	if (kept.length > 0) {
		console.warn(
			'[global-teardown] these licence confirmations could not be withdrawn: '
				+ kept.join(', '),
		)
	}
}
