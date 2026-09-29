/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Shared helpers for the nldesign DEEP data-dependent theming workflows.
 *
 * These workflows mutate GLOBAL instance theming state (the generated
 * css/custom-overrides.css file, the nldesign.token_set / hide_slogan /
 * show_menu_labels app config). Every spec MUST snapshot the prior state in
 * beforeAll and RESTORE it in afterAll so the dev instance is never left
 * themed. The helpers below centralise that snapshot/restore via the same
 * admin-authenticated HTTP endpoints the editor UI uses.
 */
import { type Page, expect } from '@playwright/test'

// Nextcloud's global OC helper is available in the browser context (page.evaluate).
declare const OC: { generateUrl: (path: string) => string; requestToken: string }

export const THEMING_URL = '/settings/admin/theming'

/** Read the CSRF request token from the loaded admin page. */
export async function requestToken(page: Page): Promise<string> {
	const token = await page.evaluate(
		() =>
			(window as unknown as { OC: { requestToken: string } }).OC.requestToken,
	)
	if (!token) {
		throw new Error('Could not read OC.requestToken — page not authenticated?')
	}
	return token
}

/**
 * Open the admin theming page and wait for the token editor to mount.
 *
 * The editor mounts via an async fetch after OC is ready. Rewriting
 * custom-overrides.css (which earlier tests do) changes the CSS bundle hash and
 * can trigger a Nextcloud SCSS recompile on the next load, so the mount is given
 * a generous timeout and one retry to absorb that transient slowness.
 */
export async function openTheming(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await page.waitForFunction(
		() => typeof (window as unknown as { OC?: unknown }).OC !== 'undefined',
		null,
		{ timeout: 30_000 },
	)
	try {
		await page.waitForSelector('#nldesign-token-editor .nldesign-token-editor', {
			timeout: 30_000,
		})
	} catch {
		// Absorb a transient recompile/mount delay with one reload + longer wait.
		await page.reload()
		await page.waitForLoadState('domcontentloaded')
		await page.waitForSelector('#nldesign-token-editor .nldesign-token-editor', {
			timeout: 30_000,
		})
	}
}

/** Fetch the persisted custom token overrides from the backend (GET /settings/overrides). */
export async function getOverrides(
	page: Page,
	token: string,
): Promise<Record<string, string>> {
	return await page.evaluate(async (t) => {
		const r = await fetch(OC.generateUrl('/apps/thematiq/settings/overrides'), {
			headers: { requesttoken: t },
		})
		const j = await r.json()
		return j.overrides || {}
	}, token)
}

/** Persist a full override map (POST /settings/overrides). Fully replaces the file. */
export async function setOverrides(
	page: Page,
	token: string,
	overrides: Record<string, string>,
): Promise<void> {
	const res = await page.evaluate(
		async ({ t, ov }) => {
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/overrides'),
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json', requesttoken: t },
					body: JSON.stringify({ overrides: ov }),
				},
			)
			return { status: r.status, body: await r.json() }
		},
		{ t: token, ov: overrides },
	)
	expect(res.status, `setOverrides should 200, got ${res.status}`).toBe(200)
	expect(res.body.status).toBe('ok')
}

/**
 * Resolve a static asset URL under this app's web root, as the running
 * Nextcloud itself would build it.
 *
 * The path must NOT be hardcoded. `/custom_apps/nldesign/...` is only correct
 * on the docker dev image, where apps live in `custom_apps/`; CI checks the app
 * out into `apps/nldesign`, where that URL matches nothing on disk, falls
 * through the front controller into index.php, matches no route, and comes back
 * as an HTML 404. To an `expect(res.ok())` that is indistinguishable from the
 * asset being missing, and the spec reports it as a broken file rather than a
 * wrong URL. `OC.filePath()` is the platform's own resolver and is right in
 * both layouts.
 */
export async function appAssetUrl(
	page: Page,
	type: string,
	file: string,
): Promise<string> {
	return page.evaluate(
		({ t, f }) =>
			(
				window as unknown as {
					OC: { filePath: (a: string, t: string, f: string) => string }
				}
			).OC.filePath('thematiq', t, f),
		{ t: type, f: file },
	)
}

/** Read the raw served custom-overrides.css (the actual generated file on disk). */
export async function getServedOverrideCss(page: Page): Promise<string> {
	const url = await appAssetUrl(page, 'css', 'custom-overrides.css')
	const res = await page.request.get(url)
	expect(
		res.ok(),
		`custom-overrides.css should be served (${url}, HTTP ${res.status()})`,
	).toBeTruthy()
	return await res.text()
}

/** Read the active token set (GET /settings/tokenset). */
export async function getTokenSet(page: Page, token: string): Promise<string> {
	return await page.evaluate(async (t) => {
		const r = await fetch(OC.generateUrl('/apps/thematiq/settings/tokenset'), {
			headers: { requesttoken: t },
		})
		const j = await r.json()
		return j.tokenSet
	}, token)
}

/** Set the active token set (POST /settings/tokenset). */
export async function setTokenSet(
	page: Page,
	token: string,
	tokenSet: string,
): Promise<void> {
	const res = await page.evaluate(
		async ({ t, ts }) => {
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/tokenset'),
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json', requesttoken: t },
					body: JSON.stringify({ tokenSet: ts }),
				},
			)
			return { status: r.status, body: await r.json() }
		},
		{ t: token, ts: tokenSet },
	)
	expect(res.status).toBe(200)
	expect(res.body.status).toBe('ok')
}

/** Set the hide-slogan boolean app setting (POST /settings/slogan). */
export async function setSlogan(
	page: Page,
	token: string,
	hideSlogan: boolean,
): Promise<void> {
	const res = await page.evaluate(
		async ({ t, v }) => {
			const r = await fetch(OC.generateUrl('/apps/thematiq/settings/slogan'), {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', requesttoken: t },
				body: JSON.stringify({ hideSlogan: v }),
			})
			return { status: r.status, body: await r.json() }
		},
		{ t: token, v: hideSlogan },
	)
	expect(res.status).toBe(200)
	expect(res.body.status).toBe('ok')
}

/** Set the show-menu-labels boolean app setting (POST /settings/menulabels). */
export async function setMenuLabels(
	page: Page,
	token: string,
	show: boolean,
): Promise<void> {
	const res = await page.evaluate(
		async ({ t, v }) => {
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/menulabels'),
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json', requesttoken: t },
					body: JSON.stringify({ showMenuLabels: v }),
				},
			)
			return { status: r.status, body: await r.json() }
		},
		{ t: token, v: show },
	)
	expect(res.status).toBe(200)
	expect(res.body.status).toBe('ok')
}

/** Read the live computed value of a CSS custom property on the document root. */
export async function liveCssVar(page: Page, name: string): Promise<string> {
	return await page.evaluate(
		(n) => getComputedStyle(document.documentElement).getPropertyValue(n).trim(),
		name,
	)
}

/** One entry of the per-group theming mapping. */
export type GroupTokenSet = { group: string; tokenSet: string }

/** A token-set offer made by `offerTokenSets`, and what to put back. */
export type TokenSetOffer = { previousMapping: GroupTokenSet[]; groups: string[] }

/** Replace the whole per-group theming mapping (POST /settings/group-theming). */
async function setGroupMapping(
	page: Page,
	token: string,
	mapping: GroupTokenSet[],
): Promise<void> {
	const res = await page.evaluate(
		async ({ t, m }) => {
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/group-theming'),
				{
					method: 'POST',
					headers: { 'Content-Type': 'application/json', requesttoken: t },
					body: JSON.stringify({ mapping: m }),
				},
			)
			return { status: r.status, body: await r.json() }
		},
		{ t: token, m: mapping },
	)
	expect(res.status, JSON.stringify(res.body)).toBe(200)
}

/**
 * Make shipped token sets selectable in the admin dropdown.
 *
 * The dropdown offers only `TokenSetService::SELECTABLE_SHIPPED_SETS` (today
 * `nextcloud` alone), plus the active set, imported `custom-*` sets and any set
 * a per-group mapping points at. See openspec/specs/token-sets/spec.md,
 * "Only Fully Functional Brands Are Selectable". A spec that drives the
 * dropdown to a shipped brand therefore has to establish that precondition
 * first, and the mapping is the one path that does not change what the admin
 * running the test sees: each set is mapped to a fresh, empty group.
 *
 * Returns what `withdrawTokenSetOffer` needs to put the instance back.
 */
export async function offerTokenSets(
	page: Page,
	token: string,
	tokenSets: string[],
): Promise<TokenSetOffer> {
	const previousMapping = await page.evaluate(async (t) => {
		const r = await fetch(
			OC.generateUrl('/apps/thematiq/settings/group-theming'),
			{ headers: { requesttoken: t } },
		)
		return ((await r.json()).mapping ?? []) as GroupTokenSet[]
	}, token)

	const suffix = Date.now().toString(36)
	const groups = tokenSets.map((set) => `e2e-offer-${set}-${suffix}`)
	for (const group of groups) {
		const status = await page.evaluate(
			async ({ t, g }) => {
				const r = await fetch('/ocs/v2.php/cloud/groups?format=json', {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						'OCS-APIRequest': 'true',
						requesttoken: t,
					},
					body: JSON.stringify({ groupid: g }),
				})
				return r.status
			},
			{ t: token, g: group },
		)
		expect(status, `creating the offer group ${group}`).toBe(200)
	}

	await setGroupMapping(page, token, [
		...previousMapping,
		...tokenSets.map((tokenSet, i) => ({ group: groups[i], tokenSet })),
	])

	return { previousMapping, groups }
}

/** Undo `offerTokenSets`: restore the mapping, then delete the offer groups. */
export async function withdrawTokenSetOffer(
	page: Page,
	token: string,
	offer: TokenSetOffer,
): Promise<void> {
	await setGroupMapping(page, token, offer.previousMapping)
	for (const group of offer.groups) {
		await page.evaluate(
			async ({ t, g }) => {
				await fetch(
					`/ocs/v2.php/cloud/groups/${encodeURIComponent(g)}?format=json`,
					{
						method: 'DELETE',
						headers: { 'OCS-APIRequest': 'true', requesttoken: t },
					},
				)
			},
			{ t: token, g: group },
		)
	}
}
