/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Snapshot and restore Nextcloud's own (core) theming around a test that
 * syncs a token set into it.
 *
 * The theming-sync endpoint writes core state that no Thematiq endpoint can
 * read back in full: colours, the background mode, and up to four images that
 * live in core's app data. A spec that applies a logo and only "resets" after
 * itself would wipe the admin's own logo. So the capture keeps the image bytes
 * themselves, read from core's public image route, and the restore puts back
 * exactly what was there, through core's own theming endpoints (the ones the
 * admin panel uses): `ajax/uploadImage`, `ajax/undoChanges` and
 * `ajax/updateStylesheet`.
 *
 * Not a `*.spec.ts` on purpose, like `_fixtures.ts`: Playwright refuses to let
 * one test file import another.
 */
import { expect, type Page } from '@playwright/test'

/** The four core image slots the sync can write (ThemingService::applyImages). */
export const IMAGE_KEYS = ['logo', 'logoheader', 'favicon', 'background'] as const

/** GET /settings/theming, as SettingsController::buildThemingSnapshot() returns it. */
export type ThemingSnapshot = {
	primary_color: string
	background_color: string
	logo_url: string
	background_url: string
	has_custom_logo: boolean
	has_custom_logoheader: boolean
	has_custom_favicon: boolean
	has_custom_background: boolean
	background_mime: string
	default_primary_color: string
	default_background_color: string
	synced_logo: string
	synced_background: string
	synced_logoheader: string
	synced_favicon: string
}

/** One stored core image, carried as base64 so it survives page navigations. */
type StoredImage = { base64: string; type: string }

/** Everything `restoreCoreTheming` needs to put core theming back. */
export type CoreThemingCapture = {
	snapshot: ThemingSnapshot
	images: Partial<Record<(typeof IMAGE_KEYS)[number], StoredImage>>
}

/** The fields a restore must bring back exactly. */
const COMPARED_FIELDS: Array<keyof ThemingSnapshot> = [
	'primary_color',
	'background_color',
	'has_custom_logo',
	'has_custom_logoheader',
	'has_custom_favicon',
	'has_custom_background',
	'background_mime',
	'synced_logo',
	'synced_background',
	'synced_logoheader',
	'synced_favicon',
]

/** The app-relative URL of Thematiq's theming endpoint, as the page builds it. */
export async function themingUrl(page: Page): Promise<string> {
	return page.evaluate(() =>
		(
			window as unknown as { OC: { generateUrl: (p: string) => string } }
		).OC.generateUrl('/apps/thematiq/settings/theming'),
	)
}

/** Read GET /settings/theming. The page must be an authenticated admin page. */
export async function readTheming(page: Page): Promise<ThemingSnapshot> {
	const url = await themingUrl(page)
	const res = await page.evaluate(async (u) => {
		const r = await fetch(u, {
			headers: {
				requesttoken: (window as unknown as { OC: { requestToken: string } })
					.OC.requestToken,
			},
		})
		return { status: r.status, json: await r.json() }
	}, url)
	expect(res.status, 'GET /settings/theming should answer 200').toBe(200)
	return res.json as ThemingSnapshot
}

/**
 * POST /settings/theming exactly as the page's own sync dialog sends it: a
 * form-encoded body. Returns the status and the parsed JSON body.
 */
export async function postTheming(
	page: Page,
	params: Record<string, string>,
): Promise<{ status: number; json: any }> {
	const url = await themingUrl(page)
	return page.evaluate(
		async ({ u, p }) => {
			const r = await fetch(u, {
				method: 'POST',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
					requesttoken: (
						window as unknown as { OC: { requestToken: string } }
					).OC.requestToken,
				},
				body: new URLSearchParams(p).toString(),
			})
			let json: any = null
			try {
				json = await r.json()
			} catch {
				json = null
			}
			return { status: r.status, json }
		},
		{ u: url, p: params },
	)
}

/**
 * Capture core theming: the snapshot plus the bytes of every custom image.
 *
 * Call it BEFORE any `page.route()` stub of the theming endpoint is installed,
 * or the snapshot is the stub.
 */
export async function captureCoreTheming(page: Page): Promise<CoreThemingCapture> {
	const snapshot = await readTheming(page)
	const wanted = IMAGE_KEYS.filter(
		(key) => snapshot[`has_custom_${key}` as keyof ThemingSnapshot] === true,
	)
	const images = await page.evaluate(async (keys) => {
		const OC = (
			window as unknown as { OC: { generateUrl: (p: string) => string } }
		).OC
		const out: Record<string, { base64: string; type: string }> = {}
		for (const key of keys) {
			const r = await fetch(OC.generateUrl('/apps/theming/image/' + key))
			if (!r.ok) {
				throw new Error(`could not read core image ${key}: HTTP ${r.status}`)
			}
			const bytes = new Uint8Array(await r.arrayBuffer())
			let binary = ''
			for (let i = 0; i < bytes.length; i += 0x8000) {
				binary += String.fromCharCode(...bytes.subarray(i, i + 0x8000))
			}
			out[key] = {
				base64: btoa(binary),
				type: r.headers.get('Content-Type') || 'application/octet-stream',
			}
		}
		return out
	}, wanted)
	return { snapshot, images }
}

/**
 * Put core theming back to a capture, then assert it is back.
 *
 * Order matters and each step undoes what the one before it may disturb:
 *  1. `reset=1` clears every value the sync writes, including Thematiq's own
 *     `synced_*` record, which nothing else can delete.
 *  2. Re-apply each recorded `synced_*` path, so that record is restored.
 *  3. Re-upload each captured image (or undo the slot when there was none).
 *     A background upload recomputes core's background colour, so
 *  4. the colours come after the images, and
 *  5. the background mode comes last.
 */
export async function restoreCoreTheming(
	page: Page,
	capture: CoreThemingCapture,
): Promise<void> {
	const problems = await page.evaluate(
		async ({ cap, keys }) => {
			const OC = (
				window as unknown as {
					OC: { generateUrl: (p: string) => string; requestToken: string }
				}
			).OC
			const errs: string[] = []
			const form = (o: Record<string, string>) => ({
				body: new URLSearchParams(o).toString(),
				type: 'application/x-www-form-urlencoded',
			})
			const post = async (
				path: string,
				payload: { body: BodyInit; type?: string },
				what: string,
			) => {
				const headers: Record<string, string> = {
					requesttoken: OC.requestToken,
				}
				if (payload.type) headers['Content-Type'] = payload.type
				const r = await fetch(OC.generateUrl(path), {
					method: 'POST',
					headers,
					body: payload.body,
				})
				if (!r.ok) errs.push(`${what}: HTTP ${r.status}`)
			}
			const snap = cap.snapshot as Record<string, string | boolean>

			await post(
				'/apps/thematiq/settings/theming',
				form({ reset: '1' }),
				'reset',
			)

			for (const key of keys) {
				const synced = snap['synced_' + key]
				if (typeof synced === 'string' && synced !== '') {
					await post(
						'/apps/thematiq/settings/theming',
						form({ [key]: synced }),
						`re-sync ${key}`,
					)
				}
			}

			for (const key of keys) {
				const stored = (
					cap.images as Record<string, { base64: string; type: string }>
				)[key]
				if (stored) {
					const binary = atob(stored.base64)
					const bytes = new Uint8Array(binary.length)
					for (let i = 0; i < binary.length; i++)
						bytes[i] = binary.charCodeAt(i)
					const fd = new FormData()
					fd.append('key', key)
					fd.append('image', new Blob([bytes], { type: stored.type }), key)
					await post(
						'/apps/theming/ajax/uploadImage',
						{ body: fd },
						`upload ${key}`,
					)
				} else {
					await post(
						'/apps/theming/ajax/undoChanges',
						form({ setting: key }),
						`undo ${key}`,
					)
				}
			}

			for (const colour of ['primary_color', 'background_color']) {
				const value = snap[colour]
				if (typeof value === 'string' && value !== '') {
					await post(
						'/apps/theming/ajax/updateStylesheet',
						form({ setting: colour, value }),
						`set ${colour}`,
					)
				} else {
					await post(
						'/apps/theming/ajax/undoChanges',
						form({ setting: colour }),
						`undo ${colour}`,
					)
				}
			}

			if (snap.background_mime === '') {
				await post(
					'/apps/theming/ajax/undoChanges',
					form({ setting: 'backgroundMime' }),
					'undo backgroundMime',
				)
			} else if (snap.background_mime === 'backgroundColor') {
				await post(
					'/apps/theming/ajax/updateStylesheet',
					form({ setting: 'backgroundMime', value: 'backgroundColor' }),
					'set backgroundMime',
				)
			}

			return errs
		},
		{ cap: capture, keys: [...IMAGE_KEYS] },
	)

	expect(problems, 'every restore call should succeed').toEqual([])

	const after = await readTheming(page)
	for (const field of COMPARED_FIELDS) {
		expect(after[field], `core theming ${field} restored`).toEqual(
			capture.snapshot[field],
		)
	}
}
