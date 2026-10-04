/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/theming-sync/spec.md
 *
 * Browser proof of the theming-sync spec: the token-set metadata the admin
 * page receives, the GET/POST /settings/theming endpoints (status, body and
 * the core theming they leave behind), and the sync dialog the admin sees
 * after switching sets.
 *
 * Three scenarios stay excluded in the spec itself, each with the reason and
 * the PHPUnit test that proves it instead: two are constructor wiring with no
 * HTTP surface, one needs the core theming app disabled, which Nextcloud does
 * not allow.
 *
 * STATE. Validation scenarios send requests that are refused before anything
 * is written, so they change nothing. Every test that does write core theming
 * captures it first and restores it in `finally` (see _core-theming.ts), image
 * bytes included. Tests that switch the active token set put it back, and a
 * shipped set is offered through a throwaway group mapping that is withdrawn.
 */
import * as fs from 'fs'
import * as path from 'path'
import { test, expect, type Page, type Route } from '@playwright/test'
import {
	getTokenSet,
	offerTokenSets,
	requestToken,
	setTokenSet,
	withdrawTokenSetOffer,
} from '../workflows/_helpers'
import {
	adminContext,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'
import {
	captureCoreTheming,
	postTheming,
	readTheming,
	restoreCoreTheming,
	themingUrl,
	type ThemingSnapshot,
} from './_core-theming'

const THEMING_URL = '/settings/admin/theming'

/** The shipped manifest, read from the checkout the suite runs from. */
type ManifestEntry = {
	id: string
	name: string
	theming?: Record<string, string>
}
const MANIFEST: ManifestEntry[] = JSON.parse(
	fs.readFileSync(path.resolve(__dirname, '../../../token-sets.json'), 'utf8'),
)
function manifestEntry(id: string): ManifestEntry {
	const entry = MANIFEST.find((e) => e.id === id)
	if (entry === undefined) {
		throw new Error(`token-sets.json has no entry "${id}"`)
	}
	return entry
}

/**
 * A token set that ships a CSS file but no manifest entry, so it has no
 * `theming` block. `css/tokens/conduction.css` is the one: the older vendored
 * Conduction theme, kept beside the `conduction-new` set that has an entry.
 */
const SET_WITHOUT_THEMING = 'conduction'

/** A set whose theming carries a dark logo (token-sets.json: epe). */
const SET_WITH_DARK_LOGO = 'epe'

/** A set with a logo but no dark logo (token-sets.json: amsterdam). */
const SET_WITHOUT_DARK_LOGO = 'amsterdam'

/** A logo that ships with the app, used wherever a request needs a real file. */
const SHIPPED_LOGO = 'img/logos/amsterdam.svg'

/** Open the admin theming page and wait for the token set dropdown. */
async function openSettings(page: Page): Promise<void> {
	await page.goto(THEMING_URL)
	await page.waitForLoadState('domcontentloaded')
	await expect(page.locator('#nldesign-token-set-select')).toBeVisible({
		timeout: 30_000,
	})
}

/** GET /settings/tokensets, the admin list the dropdown and dialog read. */
async function adminTokenSets(page: Page): Promise<any[]> {
	return page.evaluate(async () => {
		const OC = (
			window as unknown as {
				OC: { generateUrl: (p: string) => string; requestToken: string }
			}
		).OC
		const r = await fetch(OC.generateUrl('/apps/thematiq/settings/tokensets'), {
			headers: { requesttoken: OC.requestToken },
		})
		return (await r.json()).tokenSets
	})
}

/** The URL the running instance serves one of this app's files at. */
async function appFileUrl(page: Page, relative: string): Promise<string> {
	return page.evaluate(
		(rel) =>
			(
				window as unknown as {
					OC: { linkTo: (app: string, file: string) => string }
				}
			).OC.linkTo('thematiq', rel),
		relative,
	)
}

/** Fetch a URL from inside the page; returns status and body text. */
async function fetchText(
	page: Page,
	url: string,
): Promise<{ status: number; text: string }> {
	return page.evaluate(async (u) => {
		const r = await fetch(u, { cache: 'no-store' })
		return { status: r.status, text: await r.text() }
	}, url)
}

/** Core's own copy of an image slot, read from core's public image route. */
async function coreImage(
	page: Page,
	key: string,
): Promise<{ status: number; text: string }> {
	const url = await page.evaluate(
		(k) =>
			(
				window as unknown as { OC: { generateUrl: (p: string) => string } }
			).OC.generateUrl('/apps/theming/image/' + k),
		key,
	)
	return fetchText(page, url)
}

/**
 * What a core image slot holds: its status, and its bytes when there is an
 * image. With no image core answers its HTML 404 page, which carries a fresh
 * CSP nonce and request token on every load, so two reads of the same empty
 * slot never match byte for byte.
 */
function imageState(r: { status: number; text: string }) {
	return { status: r.status, body: r.status === 200 ? r.text : null }
}

/** The fields a refused request must leave untouched. */
function stateOf(s: ThemingSnapshot) {
	return {
		primary_color: s.primary_color,
		background_color: s.background_color,
		has_custom_logo: s.has_custom_logo,
		has_custom_background: s.has_custom_background,
		background_mime: s.background_mime,
		synced_logo: s.synced_logo,
		synced_background: s.synced_background,
	}
}

/**
 * Run `body` with core theming captured before and restored after, whatever
 * happens inside.
 */
async function withCoreThemingRestored(
	page: Page,
	body: (before: ThemingSnapshot) => Promise<void>,
): Promise<void> {
	const capture = await captureCoreTheming(page)
	try {
		await body(capture.snapshot)
	} finally {
		await restoreCoreTheming(page, capture)
	}
}

/** A core theming state the sync dialog will always find different. */
const STUB_CURRENT: ThemingSnapshot = {
	primary_color: '#123456',
	background_color: '#654321',
	logo_url: '',
	background_url: '',
	has_custom_logo: false,
	has_custom_logoheader: false,
	has_custom_favicon: false,
	has_custom_background: false,
	background_mime: '',
	default_primary_color: '#00679e',
	default_background_color: '#00679e',
	synced_logo: '',
	synced_background: '',
	synced_logoheader: '',
	synced_favicon: '',
}

const THEMING_ENDPOINT = /\/apps\/thematiq\/settings\/theming(\?.*)?$/
const PREVIEW_ENDPOINT = /\/apps\/thematiq\/settings\/tokenset-preview\//

/**
 * Switch the dropdown to `setId` and land in the standalone theming-sync
 * dialog, then run `body`. Puts the active set and the offer back afterwards.
 *
 * Two responses are stubbed so the run does not depend on the instance:
 *  - the token preview answers "no token changes", which is the branch where
 *    admin.js saves the set and calls checkAndShowThemingDialog() itself
 *    (the apply dialog only appears when tokens differ);
 *  - GET /settings/theming answers `current`, so the dialog's comparison is
 *    the same on every instance.
 * POST /settings/theming is recorded, and either passed through to the real
 * endpoint (`passThrough`) or answered without writing anything.
 */
async function inSyncDialog(
	page: Page,
	setId: string,
	options: { current?: ThemingSnapshot; passThrough?: boolean },
	body: (ctx: { posts: string[]; token: string }) => Promise<void>,
): Promise<void> {
	await openSettings(page)
	const token = await requestToken(page)
	const previousSet = await getTokenSet(page, token)
	const offer = await offerTokenSets(page, token, [setId])
	const posts: string[] = []
	try {
		// selectOption only fires `change` when the value changes.
		if (previousSet === setId) {
			await setTokenSet(page, token, 'nextcloud')
		}
		// Reload: the dropdown and admin.js's token set data come from the
		// page's initial state, which now includes the offered set.
		await openSettings(page)
		await page.route(PREVIEW_ENDPOINT, (route: Route) =>
			route.fulfill({ json: { resolved: {} } }),
		)
		await page.route(THEMING_ENDPOINT, async (route: Route) => {
			const request = route.request()
			if (request.method() === 'GET') {
				await route.fulfill({ json: options.current ?? STUB_CURRENT })
				return
			}
			posts.push(request.postData() ?? '')
			if (options.passThrough === true) {
				await route.continue()
				return
			}
			await route.fulfill({ json: { status: 'ok', updated: [] } })
		})
		await page.locator('#nldesign-token-set-select').selectOption(setId)
		await body({ posts, token })
	} finally {
		await page.unrouteAll({ behavior: 'ignoreErrors' })
		await setTokenSet(page, token, previousSet)
		await withdrawTokenSetOffer(page, token, offer)
	}
}

test.describe('theming-sync', () => {
	// -----------------------------------------------------------------------
	// Requirement: Theming Metadata in Token Sets
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#token-set-with-full-theming-metadata
	test('rijkshuisstijl carries primary, background and logo in its theming block', async ({
		page,
	}) => {
		const shipped = manifestEntry('rijkshuisstijl').theming ?? {}
		expect(shipped.primary_color).toMatch(/^#[0-9a-fA-F]{6}$/)
		expect(shipped.background_color).toMatch(/^#[0-9a-fA-F]{6}$/)

		await openSettings(page)
		const token = await requestToken(page)
		const offer = await offerTokenSets(page, token, [
			'rijkshuisstijl',
			SET_WITH_DARK_LOGO,
		])
		try {
			const sets = await adminTokenSets(page)
			const rijk = sets.find((s) => s.id === 'rijkshuisstijl')
			expect(rijk, 'rijkshuisstijl is listed once offered').toBeTruthy()
			expect(rijk.theming.primary_color).toBe(shipped.primary_color)
			expect(rijk.theming.background_color).toBe(shipped.background_color)
			// MAY contain a logo: rijkshuisstijl does, epe also has logo_dark.
			expect(rijk.theming.logo).toBe('img/logos/rijkshuisstijl.svg')
			const epe = sets.find((s) => s.id === SET_WITH_DARK_LOGO)
			expect(epe.theming.logo_dark).toBe(
				manifestEntry(SET_WITH_DARK_LOGO).theming?.logo_dark,
			)
			expect(epe.theming.logo_dark).toMatch(/^img\/logos\/.+-dark\.svg$/)
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#token-set-with-logo-and-background-theming
	test('every logo and background path in the manifest is in its folder and served', async ({
		page,
	}) => {
		await openSettings(page)
		const withImages = MANIFEST.filter(
			(e) =>
				e.theming?.logo !== undefined || e.theming?.background !== undefined,
		)
		expect(
			withImages.length,
			'the manifest ships sets with a logo',
		).toBeGreaterThan(0)
		for (const entry of withImages) {
			const logo = entry.theming?.logo
			if (logo !== undefined) {
				expect(logo, `${entry.id} logo folder`).toMatch(
					/^img\/logos\/[^/]+$/,
				)
				const res = await fetchText(page, await appFileUrl(page, logo))
				expect(res.status, `${entry.id} logo ${logo} is served`).toBe(200)
			}
			// No shipped set has a background image today; any that gains one
			// is held to the same rule here.
			const background = entry.theming?.background
			if (background !== undefined) {
				expect(background, `${entry.id} background folder`).toMatch(
					/^img\/backgrounds\/[^/]+$/,
				)
				const res = await fetchText(page, await appFileUrl(page, background))
				expect(
					res.status,
					`${entry.id} background ${background} is served`,
				).toBe(200)
			}
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#dark-logo-path-validated-like-the-light-logo
	test('logo_dark is refused with the same errors as logo, and shipped dark logos are served', async ({
		page,
	}) => {
		await openSettings(page)
		const cases: Array<[string, string]> = [
			[
				'../../etc/passwd',
				'Invalid image path for logo_dark: path traversal not allowed',
			],
			[
				'/etc/passwd',
				'Invalid image path for logo_dark: path traversal not allowed',
			],
			[
				'lib/Controller/SettingsController.php',
				'Invalid image path for logo_dark: must be in img/logos/ or img/backgrounds/',
			],
			[
				'img/logos/nonexistent-dark.svg',
				'Image file not found: img/logos/nonexistent-dark.svg',
			],
		]
		for (const [value, error] of cases) {
			const asDark = await postTheming(page, { logo_dark: value })
			expect(asDark.status, `logo_dark ${value}`).toBe(400)
			expect(asDark.json.error).toBe(error)
			// The same shape as the light logo's error.
			const asLogo = await postTheming(page, { logo: value })
			expect(asLogo.json.error).toBe(error.replace('logo_dark', 'logo'))
		}

		const darkLogos = MANIFEST.filter((e) => e.theming?.logo_dark !== undefined)
		expect(darkLogos.length).toBeGreaterThan(0)
		for (const entry of darkLogos) {
			const dark = entry.theming?.logo_dark as string
			expect(dark).toMatch(/^img\/logos\/[^/]+$/)
			const res = await fetchText(page, await appFileUrl(page, dark))
			expect(res.status, `${entry.id} dark logo is served`).toBe(200)
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#dark-logo-is-not-synced-to-nextcloud-core-theming
	test('a dark logo is never written to core theming; the dark stylesheet carries it', async ({
		page,
	}) => {
		await openSettings(page)
		const dark = manifestEntry(SET_WITH_DARK_LOGO).theming?.logo_dark as string

		const before = await readTheming(page)
		const res = await postTheming(page, { logo_dark: dark })
		expect(res.status).toBe(200)
		expect(res.json).toEqual({ status: 'ok', updated: [] })
		const after = await readTheming(page)
		expect(stateOf(after)).toEqual(stateOf(before))
		expect(after.logo_url).toBe(before.logo_url)

		// Delivered instead by the generated dark variant of the set.
		const cssUrl = await page.evaluate(
			(setId) =>
				(
					window as unknown as {
						OC: { linkTo: (app: string, file: string) => string }
					}
				).OC.linkTo('thematiq', `css/tokens/dark/${setId}.css`),
			SET_WITH_DARK_LOGO,
		)
		const css = await fetchText(page, cssUrl)
		expect(css.status).toBe(200)
		const match = css.text.match(/--nldesign-logo-url:\s*url\('([^']+)'\)/)
		expect(match, 'the dark stylesheet sets --nldesign-logo-url').not.toBeNull()
		expect((match as RegExpMatchArray)[1].endsWith(dark)).toBe(true)
		const resolved = new URL(
			(match as RegExpMatchArray)[1],
			new URL(cssUrl, page.url()),
		).toString()
		expect((await fetchText(page, resolved)).status).toBe(200)
	})

	// @e2e openspec/specs/theming-sync/spec.md#token-set-without-theming-metadata
	test('a set without a manifest entry has no theming field and gets no sync dialog', async ({
		page,
	}) => {
		expect(MANIFEST.some((e) => e.id === SET_WITHOUT_THEMING)).toBe(false)

		await inSyncDialog(
			page,
			SET_WITHOUT_THEMING,
			{},
			async ({ posts, token }) => {
				const listed = (await adminTokenSets(page)).find(
					(s) => s.id === SET_WITHOUT_THEMING,
				)
				expect(
					listed,
					`${SET_WITHOUT_THEMING} is listed once offered`,
				).toBeTruthy()
				expect('theming' in listed).toBe(false)

				// The set is saved without a sync step.
				await expect
					.poll(() => getTokenSet(page, token), { timeout: 15_000 })
					.toBe(SET_WITHOUT_THEMING)
				await page.waitForTimeout(2_000)
				await expect(
					page.locator('#nldesign-theming-dialog-overlay'),
				).toHaveCount(0)
				expect(posts).toEqual([])
			},
		)
	})

	// @e2e openspec/specs/theming-sync/spec.md#theming-metadata-included-in-api-response
	test('GET /settings/tokensets returns the whole theming block, logo_dark included', async ({
		page,
	}) => {
		await openSettings(page)
		const token = await requestToken(page)
		const offer = await offerTokenSets(page, token, [SET_WITH_DARK_LOGO])
		try {
			const epe = (await adminTokenSets(page)).find(
				(s) => s.id === SET_WITH_DARK_LOGO,
			)
			expect(epe.theming).toEqual(manifestEntry(SET_WITH_DARK_LOGO).theming)
		} finally {
			await withdrawTokenSetOffer(page, token, offer)
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#partial-theming-metadata-accepted
	test('a set with only a primary colour offers only that colour, without errors', async ({
		page,
	}) => {
		await openSettings(page)
		const errors: string[] = []
		page.on('pageerror', (e) => errors.push(e.message))

		// An uploaded set derives its theming from what it declares, so a file
		// that declares only the primary colour gives {primary_color} alone.
		const upload = await page.evaluate(async (name) => {
			const OC = (
				window as unknown as {
					OC: { generateUrl: (p: string) => string; requestToken: string }
				}
			).OC
			const r = await fetch(
				OC.generateUrl('/apps/thematiq/settings/tokensets/upload'),
				{
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({
						name,
						raw: true,
						content: ':root { --nldesign-color-primary: #004699; }',
					}),
				},
			)
			return { status: r.status, json: await r.json() }
		}, 'E2E alleen primair ' + Date.now())
		expect(upload.status, JSON.stringify(upload.json)).toBe(200)
		const setId: string = upload.json.id
		expect(setId).toMatch(/^custom-/)

		try {
			const listed = (await adminTokenSets(page)).find((s) => s.id === setId)
			expect(listed.theming).toEqual({ primary_color: '#004699' })

			await inSyncDialog(page, setId, {}, async () => {
				const dialog = page.locator('#nldesign-theming-dialog-overlay')
				await expect(dialog).toBeVisible({ timeout: 15_000 })
				const rows = dialog.locator('.nldesign-dialog-table tbody tr')
				await expect(rows).toHaveCount(1)
				await expect(rows.first()).toContainText('#004699')
				await dialog.locator('.nldesign-dialog-cancel').click()
			})
			expect(errors).toEqual([])
		} finally {
			await page.evaluate(async (id) => {
				const OC = (
					window as unknown as {
						OC: {
							generateUrl: (p: string) => string
							requestToken: string
						}
					}
				).OC
				await fetch(
					OC.generateUrl(
						'/apps/thematiq/settings/tokensets/custom/'
							+ encodeURIComponent(id),
					),
					{ method: 'DELETE', headers: { requesttoken: OC.requestToken } },
				)
			}, setId)
		}
	})

	// -----------------------------------------------------------------------
	// Requirement: Get Current Theming Values
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#retrieve-theming-values
	test('GET /settings/theming answers the six fields with their types', async ({
		page,
	}) => {
		await openSettings(page)
		const values = await readTheming(page)
		expect(typeof values.primary_color).toBe('string')
		expect(typeof values.background_color).toBe('string')
		expect(typeof values.logo_url).toBe('string')
		expect(typeof values.background_url).toBe('string')
		expect(typeof values.has_custom_logo).toBe('boolean')
		expect(typeof values.has_custom_background).toBe('boolean')
	})

	// @e2e openspec/specs/theming-sync/spec.md#no-custom-theming-configured
	test('with core theming reset, colours are empty and no custom images are reported', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const reset = await postTheming(page, { reset: '1' })
			expect(reset.status).toBe(200)
			const values = await readTheming(page)
			expect(values.primary_color).toBe('')
			expect(values.background_color).toBe('')
			expect(values.has_custom_logo).toBe(false)
			expect(values.has_custom_background).toBe(false)
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#custom-theming-previously-configured
	test('a colour and logo set through core theming are what the endpoint reports', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const logoSvg = (
				await fetchText(page, await appFileUrl(page, SHIPPED_LOGO))
			).text
			const status = await page.evaluate(async (svg) => {
				const OC = (
					window as unknown as {
						OC: {
							generateUrl: (p: string) => string
							requestToken: string
						}
					}
				).OC
				const colour = await fetch(
					OC.generateUrl('/apps/theming/ajax/updateStylesheet'),
					{
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							requesttoken: OC.requestToken,
						},
						body: new URLSearchParams({
							setting: 'primary_color',
							value: '#004699',
						}).toString(),
					},
				)
				const fd = new FormData()
				fd.append('key', 'logo')
				fd.append(
					'image',
					new Blob([svg], { type: 'image/svg+xml' }),
					'logo.svg',
				)
				const logo = await fetch(
					OC.generateUrl('/apps/theming/ajax/uploadImage'),
					{
						method: 'POST',
						headers: { requesttoken: OC.requestToken },
						body: fd,
					},
				)
				return [colour.status, logo.status]
			}, logoSvg)
			expect(status).toEqual([200, 200])

			const values = await readTheming(page)
			expect(values.primary_color).toBe('#004699')
			expect(values.has_custom_logo).toBe(true)
			expect(values.logo_url).toContain('/apps/theming/image/logo')
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#values-built-from-buildthemingsnapshot
	test('the snapshot reads the theming app config and the image manager, not its own copy', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			// Written through CORE's endpoints only, so the only way the values
			// can reach Thematiq's response is by reading core's state.
			const status = await page.evaluate(async () => {
				const OC = (
					window as unknown as {
						OC: {
							generateUrl: (p: string) => string
							requestToken: string
						}
					}
				).OC
				const post = (setting: string, value: string) =>
					fetch(OC.generateUrl('/apps/theming/ajax/updateStylesheet'), {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							requesttoken: OC.requestToken,
						},
						body: new URLSearchParams({ setting, value }).toString(),
					}).then((r) => r.status)
				const undo = (setting: string) =>
					fetch(OC.generateUrl('/apps/theming/ajax/undoChanges'), {
						method: 'POST',
						headers: {
							'Content-Type': 'application/x-www-form-urlencoded',
							requesttoken: OC.requestToken,
						},
						body: new URLSearchParams({ setting }).toString(),
					}).then((r) => r.status)
				return [
					await post('primary_color', '#1a2b3c'),
					await post('background_color', '#3c2b1a'),
					await undo('logo'),
				]
			})
			expect(status).toEqual([200, 200, 200])

			const values = await readTheming(page)
			expect(values.primary_color).toBe('#1a2b3c')
			expect(values.background_color).toBe('#3c2b1a')
			expect(values.has_custom_logo).toBe(false)
			// The image manager's answer for an empty slot is core's own logo.
			expect(values.logo_url).toContain('core/img/logo/logo')
			expect((await coreImage(page, 'logo')).status).toBe(404)
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Color Validation
	//
	// Colours are validated before image paths. A request whose colour is
	// valid but whose logo is not answers with the LOGO error, which is how a
	// passing colour shows without writing anything.
	// -----------------------------------------------------------------------

	const TRAVERSAL = '../../etc/passwd'
	const TRAVERSAL_ERROR = 'Invalid image path for logo: path traversal not allowed'

	// @e2e openspec/specs/theming-sync/spec.md#valid-6-digit-hex-color-accepted
	test('a 6-digit hex colour passes colour validation', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, {
			primary_color: '#154273',
			logo: TRAVERSAL,
		})
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(TRAVERSAL_ERROR)
	})

	// @e2e openspec/specs/theming-sync/spec.md#valid-3-digit-hex-color-accepted
	test('a 3-digit hex colour passes colour validation', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, {
			primary_color: '#abc',
			logo: TRAVERSAL,
		})
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(TRAVERSAL_ERROR)
	})

	// @e2e openspec/specs/theming-sync/spec.md#invalid-color-rejected-with-descriptive-error
	test('an invalid colour is refused with the exact message', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, { primary_color: 'not-a-color' })
		expect(res.status).toBe(400)
		expect(res.json).toEqual({
			error: 'Invalid hex color for primary_color: not-a-color',
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#empty-color-field-skipped
	test('an empty colour is skipped by validation', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, { primary_color: '', logo: TRAVERSAL })
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(TRAVERSAL_ERROR)
	})

	// @e2e openspec/specs/theming-sync/spec.md#both-color-fields-validated
	test('both colour fields are checked with the 3-or-6 digit hex rule, first error wins', async ({
		page,
	}) => {
		await openSettings(page)
		const bg = await postTheming(page, { background_color: 'blue' })
		expect(bg.json.error).toBe('Invalid hex color for background_color: blue')

		const both = await postTheming(page, {
			primary_color: 'red',
			background_color: 'blue',
		})
		expect(both.json.error).toBe('Invalid hex color for primary_color: red')

		for (const bad of ['#12345', '#abcd', '#GGGGGG', '123456', '#1234567']) {
			const res = await postTheming(page, { background_color: bad })
			expect(res.status, bad).toBe(400)
			expect(res.json.error).toBe(
				`Invalid hex color for background_color: ${bad}`,
			)
		}
		for (const good of ['#ABCDEF', '#abcdef', '#AbC']) {
			const res = await postTheming(page, {
				background_color: good,
				logo: TRAVERSAL,
			})
			expect(res.json.error, good).toBe(TRAVERSAL_ERROR)
		}
	})

	// -----------------------------------------------------------------------
	// Requirement: Image Path Validation
	//
	// Image paths are validated before the background mode. A request whose
	// path is valid but whose mode is not answers with the MODE error, which is
	// how a passing path shows without writing anything.
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#valid-logo-path-accepted
	test('an existing logo under img/logos/ passes path validation', async ({
		page,
	}) => {
		await openSettings(page)
		const res = await postTheming(page, {
			logo: SHIPPED_LOGO,
			background_mode: 'bogus',
		})
		expect(res.status).toBe(400)
		expect(res.json.error).toBe('Invalid background_mode: bogus')
	})

	// @e2e openspec/specs/theming-sync/spec.md#path-traversal-via-dot-dot-prevented
	test('a path containing .. is refused as traversal', async ({ page }) => {
		await openSettings(page)
		for (const value of [TRAVERSAL, 'img/logos/../../appinfo/info.xml']) {
			const res = await postTheming(page, { logo: value })
			expect(res.status).toBe(400)
			expect(res.json.error).toBe(TRAVERSAL_ERROR)
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#absolute-path-rejected
	test('an absolute path is refused as traversal', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, { logo: '/etc/passwd' })
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(TRAVERSAL_ERROR)
	})

	// @e2e openspec/specs/theming-sync/spec.md#path-outside-allowed-directories-rejected
	test('a path outside the two image folders is refused', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, {
			logo: 'lib/Controller/SettingsController.php',
		})
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(
			'Invalid image path for logo: must be in img/logos/ or img/backgrounds/',
		)
	})

	// @e2e openspec/specs/theming-sync/spec.md#non-existent-image-rejected
	test('a path to a file that does not exist is refused', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, { logo: 'img/logos/nonexistent.svg' })
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(
			'Image file not found: img/logos/nonexistent.svg',
		)
	})

	// @e2e openspec/specs/theming-sync/spec.md#both-image-fields-validated
	test('logo and background are both checked, in that order, against both folders', async ({
		page,
	}) => {
		await openSettings(page)
		const bg = await postTheming(page, { background: '../x.jpg' })
		expect(bg.json.error).toBe(
			'Invalid image path for background: path traversal not allowed',
		)

		const both = await postTheming(page, {
			logo: 'lib/x.svg',
			background: '../x.jpg',
		})
		expect(both.json.error).toBe(
			'Invalid image path for logo: must be in img/logos/ or img/backgrounds/',
		)

		// img/backgrounds/ is an allowed folder: the path gets as far as the
		// existence check.
		const inBackgrounds = await postTheming(page, {
			background: 'img/backgrounds/missing.jpg',
		})
		expect(inBackgrounds.json.error).toBe(
			'Image file not found: img/backgrounds/missing.jpg',
		)
		const outside = await postTheming(page, { background: 'css/admin.css' })
		expect(outside.json.error).toBe(
			'Invalid image path for background: must be in img/logos/ or img/backgrounds/',
		)
	})

	// -----------------------------------------------------------------------
	// Requirement: Apply Colors to Nextcloud Theming
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#primary-color-applied
	test('a primary colour is written to core theming and listed as updated', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const res = await postTheming(page, { primary_color: '#004699' })
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', updated: ['primary_color'] })
			expect((await readTheming(page)).primary_color).toBe('#004699')
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#background-color-applied
	test('a background colour is written to core theming and listed as updated', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const res = await postTheming(page, { background_color: '#FFFFFF' })
			expect(res.status).toBe(200)
			expect(res.json.updated).toContain('background_color')
			expect((await readTheming(page)).background_color).toBe('#FFFFFF')
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#multiple-colors-applied-simultaneously
	test('both colours in one request are both written and both listed', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const res = await postTheming(page, {
				primary_color: '#004699',
				background_color: '#FFFFFF',
			})
			expect(res.status).toBe(200)
			expect(res.json.updated).toEqual(
				expect.arrayContaining(['primary_color', 'background_color']),
			)
			const values = await readTheming(page)
			expect(values.primary_color).toBe('#004699')
			expect(values.background_color).toBe('#FFFFFF')
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#empty-color-ignored
	test('an empty primary colour is not written and not listed', async ({
		page,
	}) => {
		await openSettings(page)
		const before = await readTheming(page)
		const res = await postTheming(page, { primary_color: '' })
		expect(res.status).toBe(200)
		expect(res.json).toEqual({ status: 'ok', updated: [] })
		expect(stateOf(await readTheming(page))).toEqual(stateOf(before))
	})

	// -----------------------------------------------------------------------
	// Requirement: Apply Images to Nextcloud Theming
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#logo-image-applied
	test('a logo path is stored in core theming as that file', async ({ page }) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const res = await postTheming(page, { logo: SHIPPED_LOGO })
			expect(res.status).toBe(200)
			expect(res.json).toEqual({ status: 'ok', updated: ['logo'] })
			const values = await readTheming(page)
			expect(values.has_custom_logo).toBe(true)
			expect(values.synced_logo).toBe(SHIPPED_LOGO)
			const stored = await coreImage(page, 'logo')
			expect(stored.status).toBe(200)
			expect(stored.text).toBe(
				(await fetchText(page, await appFileUrl(page, SHIPPED_LOGO))).text,
			)
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#background-image-applied
	test('a background path is stored in core theming as that file', async ({
		page,
	}) => {
		await openSettings(page)
		// The app ships no file under img/backgrounds/ (only .gitkeep), and the
		// validator accepts either image folder for every slot, so a shipped
		// logo stands in as the background file.
		await withCoreThemingRestored(page, async () => {
			const res = await postTheming(page, { background: SHIPPED_LOGO })
			expect(res.status).toBe(200)
			expect(res.json.updated).toContain('background')
			const values = await readTheming(page)
			expect(values.has_custom_background).toBe(true)
			expect(values.synced_background).toBe(SHIPPED_LOGO)
			const stored = await coreImage(page, 'background')
			expect(stored.status).toBe(200)
			expect(stored.text).toBe(
				(await fetchText(page, await appFileUrl(page, SHIPPED_LOGO))).text,
			)
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#empty-image-path-ignored
	test('an empty logo path is not applied', async ({ page }) => {
		await openSettings(page)
		const before = await readTheming(page)
		const res = await postTheming(page, { logo: '' })
		expect(res.status).toBe(200)
		expect(res.json).toEqual({ status: 'ok', updated: [] })
		const after = await readTheming(page)
		expect(stateOf(after)).toEqual(stateOf(before))
		expect(after.logo_url).toBe(before.logo_url)
	})

	// @e2e openspec/specs/theming-sync/spec.md#app-path-resolved-via-iappmanager
	test('the relative path is resolved against the app directory', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			// Two different logos: core ends up with the bytes of the file at
			// THAT relative path in the app, so the path was joined onto the
			// app's own directory rather than read from anywhere else.
			for (const logo of [SHIPPED_LOGO, 'img/logos/rijkshuisstijl.svg']) {
				const res = await postTheming(page, { logo })
				expect(res.json.updated, logo).toEqual(['logo'])
				const stored = await coreImage(page, 'logo')
				expect(stored.text, logo).toBe(
					(await fetchText(page, await appFileUrl(page, logo))).text,
				)
			}
		})
	})

	// -----------------------------------------------------------------------
	// Requirement: Update Theming API Endpoint
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#successful-theming-update
	test('a valid colour and logo are applied and reported in order', async ({
		page,
	}) => {
		await openSettings(page)
		await withCoreThemingRestored(page, async () => {
			const res = await postTheming(page, {
				primary_color: '#154273',
				logo: 'img/logos/rijkshuisstijl.svg',
			})
			expect(res.status).toBe(200)
			expect(res.json).toEqual({
				status: 'ok',
				updated: ['primary_color', 'logo'],
			})
			const values = await readTheming(page)
			expect(values.primary_color).toBe('#154273')
			expect(values.synced_logo).toBe('img/logos/rijkshuisstijl.svg')
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#color-validation-failure-stops-all-processing
	test('an invalid colour refuses the request and applies nothing', async ({
		page,
	}) => {
		await openSettings(page)
		const before = await readTheming(page)
		const res = await postTheming(page, {
			primary_color: 'invalid',
			logo: SHIPPED_LOGO,
		})
		expect(res.status).toBe(400)
		expect(res.json).toEqual({
			error: 'Invalid hex color for primary_color: invalid',
		})
		const after = await readTheming(page)
		expect(stateOf(after)).toEqual(stateOf(before))
		expect(after.logo_url).toBe(before.logo_url)
	})

	// @e2e openspec/specs/theming-sync/spec.md#image-validation-failure-stops-image-processing
	test('a valid colour with an invalid logo is refused and the colour is not applied', async ({
		page,
	}) => {
		await openSettings(page)
		const before = await readTheming(page)
		// A colour the instance does not already have, so "not applied" shows.
		const colour =
			before.primary_color.toLowerCase() === '#154273' ? '#1a2b3c' : '#154273'
		const res = await postTheming(page, {
			primary_color: colour,
			logo: TRAVERSAL,
		})
		expect(res.status).toBe(400)
		expect(res.json).toEqual({ error: TRAVERSAL_ERROR })
		expect(stateOf(await readTheming(page))).toEqual(stateOf(before))
	})

	// @e2e openspec/specs/theming-sync/spec.md#empty-request-applies-nothing
	test('an empty request passes and applies nothing', async ({ page }) => {
		await openSettings(page)
		const before = await readTheming(page)
		const res = await postTheming(page, {})
		expect(res.status).toBe(200)
		expect(res.json).toEqual({ status: 'ok', updated: [] })
		expect(stateOf(await readTheming(page))).toEqual(stateOf(before))
	})

	// -----------------------------------------------------------------------
	// Requirement: Validation Order
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#colors-validated-before-images
	test('colours are validated first, images only after they pass', async ({
		page,
	}) => {
		await openSettings(page)
		// Both invalid: the colour error is the one returned.
		const both = await postTheming(page, {
			primary_color: 'nope',
			logo: TRAVERSAL,
		})
		expect(both.json.error).toBe('Invalid hex color for primary_color: nope')
		// Colour valid: only then is the image checked.
		const imageOnly = await postTheming(page, {
			primary_color: '#abc',
			logo: TRAVERSAL,
		})
		expect(imageOnly.json.error).toBe(TRAVERSAL_ERROR)
	})

	// @e2e openspec/specs/theming-sync/spec.md#failed-validation-prevents-all-changes
	test('a refused request leaves core colours, images and the sync record unchanged', async ({
		page,
	}) => {
		await openSettings(page)
		const before = await readTheming(page)
		const logoBefore = await coreImage(page, 'logo')
		const res = await postTheming(page, {
			primary_color: 'invalid',
			background_color: '#000000',
			logo: SHIPPED_LOGO,
			background: SHIPPED_LOGO,
		})
		expect(res.status).toBe(400)
		const after = await readTheming(page)
		expect(stateOf(after)).toEqual(stateOf(before))
		expect(imageState(await coreImage(page, 'logo'))).toEqual(
			imageState(logoBefore),
		)
	})

	// @e2e openspec/specs/theming-sync/spec.md#params-read-from-request
	test('parameters are read from the request whatever its encoding', async ({
		page,
	}) => {
		await openSettings(page)
		const url = await themingUrl(page)
		const results = await page.evaluate(async (u) => {
			const OC = (window as unknown as { OC: { requestToken: string } }).OC
			const send = async (target: string, init: RequestInit) => {
				const r = await fetch(target, init)
				return (await r.json()).error as string
			}
			return {
				json: await send(u, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/json',
						requesttoken: OC.requestToken,
					},
					body: JSON.stringify({ primary_color: 'json-value' }),
				}),
				form: await send(u, {
					method: 'POST',
					headers: {
						'Content-Type': 'application/x-www-form-urlencoded',
						requesttoken: OC.requestToken,
					},
					body: 'background_color=form-value',
				}),
				query: await send(u + '?logo=..%2Fquery-value', {
					method: 'POST',
					headers: { requesttoken: OC.requestToken },
				}),
			}
		}, url)
		expect(results.json).toBe('Invalid hex color for primary_color: json-value')
		expect(results.form).toBe(
			'Invalid hex color for background_color: form-value',
		)
		expect(results.query).toBe(TRAVERSAL_ERROR)
	})

	// -----------------------------------------------------------------------
	// Requirement: Theming Sync Dialog (Frontend)
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#dialog-shown-for-token-set-with-theming-metadata
	test('saving a set with theming metadata opens the sync dialog', async ({
		page,
	}) => {
		await inSyncDialog(page, SET_WITHOUT_DARK_LOGO, {}, async () => {
			const dialog = page.locator('#nldesign-theming-dialog-overlay')
			await expect(dialog).toBeVisible({ timeout: 15_000 })
			await expect(dialog.locator('h3')).toContainText(
				manifestEntry(SET_WITHOUT_DARK_LOGO).name,
			)
			await expect(
				dialog.locator('.nldesign-dialog-table tbody tr'),
			).not.toHaveCount(0)
			await dialog.locator('.nldesign-dialog-cancel').click()
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#dialog-shows-color-comparison
	test('the dialog shows current and proposed colours with swatches', async ({
		page,
	}) => {
		const proposed = manifestEntry(SET_WITHOUT_DARK_LOGO).theming ?? {}
		await inSyncDialog(page, SET_WITHOUT_DARK_LOGO, {}, async () => {
			const dialog = page.locator('#nldesign-theming-dialog-overlay')
			await expect(dialog).toBeVisible({ timeout: 15_000 })
			const primaryRow = dialog.locator('.nldesign-dialog-table tbody tr', {
				hasText: STUB_CURRENT.primary_color,
			})
			await expect(primaryRow).toHaveCount(1)
			await expect(primaryRow).toContainText(proposed.primary_color as string)
			const swatches = primaryRow.locator('.nldesign-dialog-swatch')
			await expect(swatches).toHaveCount(2)
			const colours = await swatches.evaluateAll((els) =>
				els.map((el) => getComputedStyle(el).backgroundColor),
			)
			expect(colours[0]).not.toBe(colours[1])
			await dialog.locator('.nldesign-dialog-cancel').click()
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#dialog-offers-the-dark-logo-when-present
	test('a set with a dark logo shows it in a dark row, and never sends it', async ({
		page,
	}) => {
		const dark = manifestEntry(SET_WITH_DARK_LOGO).theming?.logo_dark as string
		await inSyncDialog(page, SET_WITH_DARK_LOGO, {}, async ({ posts }) => {
			const dialog = page.locator('#nldesign-theming-dialog-overlay')
			await expect(dialog).toBeVisible({ timeout: 15_000 })
			const row = dialog.locator('.nldesign-dialog-dark-logo-row')
			await expect(row).toHaveCount(1)
			await expect(
				row.locator('.nldesign-dialog-preview-box--dark'),
			).toHaveCount(1)
			const src = await row.locator('img').getAttribute('src')
			expect(src ?? '').toContain(dark)
			await expect(row.locator('.nldesign-dialog-hint')).toContainText(
				'no dark logo slot',
			)

			// Confirm. The POST is recorded and answered without writing.
			await dialog.locator('.nldesign-dialog-confirm').click()
			await expect.poll(() => posts.length).toBe(1)
			const sent = new URLSearchParams(posts[0])
			expect(sent.has('logo_dark')).toBe(false)
			expect(sent.get('logo')).toBe(
				manifestEntry(SET_WITH_DARK_LOGO).theming?.logo,
			)
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#dialog-omits-the-dark-logo-row-when-absent
	test('a set without a dark logo gets no dark logo row', async ({ page }) => {
		expect(
			manifestEntry(SET_WITHOUT_DARK_LOGO).theming?.logo_dark,
		).toBeUndefined()
		await inSyncDialog(page, SET_WITHOUT_DARK_LOGO, {}, async () => {
			const dialog = page.locator('#nldesign-theming-dialog-overlay')
			await expect(dialog).toBeVisible({ timeout: 15_000 })
			await expect(
				dialog.locator('.nldesign-dialog-dark-logo-row'),
			).toHaveCount(0)
			await dialog.locator('.nldesign-dialog-cancel').click()
		})
	})

	// @e2e openspec/specs/theming-sync/spec.md#dialog-not-shown-for-sets-without-theming-metadata
	test('a set without theming metadata is applied with no sync dialog', async ({
		page,
	}) => {
		await inSyncDialog(
			page,
			SET_WITHOUT_THEMING,
			{},
			async ({ posts, token }) => {
				await expect
					.poll(() => getTokenSet(page, token), { timeout: 15_000 })
					.toBe(SET_WITHOUT_THEMING)
				await page.waitForTimeout(2_000)
				await expect(
					page.locator('#nldesign-theming-dialog-overlay'),
				).toHaveCount(0)
				await expect(page.locator('.nldesign-dialog-overlay')).toHaveCount(0)
				expect(posts).toEqual([])
			},
		)
	})

	// @e2e openspec/specs/theming-sync/spec.md#admin-confirms-theming-sync
	test('confirming posts the proposed values and core theming takes them', async ({
		page,
	}) => {
		const proposed = manifestEntry(SET_WITHOUT_DARK_LOGO).theming ?? {}
		await openSettings(page)
		const capture = await captureCoreTheming(page)
		try {
			await inSyncDialog(
				page,
				SET_WITHOUT_DARK_LOGO,
				{ passThrough: true },
				async ({ posts }) => {
					const dialog = page.locator('#nldesign-theming-dialog-overlay')
					await expect(dialog).toBeVisible({ timeout: 15_000 })
					const response = page.waitForResponse(
						(r) =>
							THEMING_ENDPOINT.test(r.url())
							&& r.request().method() === 'POST',
					)
					await dialog.locator('.nldesign-dialog-confirm').click()
					expect((await response).status()).toBe(200)
					const sent = new URLSearchParams(posts[0])
					expect(sent.get('primary_color')).toBe(proposed.primary_color)
					expect(sent.get('background_color')).toBe(
						proposed.background_color,
					)
					expect(sent.get('logo')).toBe(proposed.logo)
					await expect(dialog).toHaveCount(0)

					await page.unrouteAll({ behavior: 'ignoreErrors' })
					const values = await readTheming(page)
					expect(values.primary_color.toLowerCase()).toBe(
						(proposed.primary_color as string).toLowerCase(),
					)
					expect(values.has_custom_logo).toBe(true)
					expect(values.synced_logo).toBe(proposed.logo)
				},
			)
		} finally {
			await page.unrouteAll({ behavior: 'ignoreErrors' })
			await restoreCoreTheming(page, capture)
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#admin-cancels-theming-sync
	test('cancelling closes the dialog, applies no theming, keeps the set', async ({
		page,
	}) => {
		await openSettings(page)
		const before = await readTheming(page)
		await inSyncDialog(
			page,
			SET_WITHOUT_DARK_LOGO,
			{},
			async ({ posts, token }) => {
				const dialog = page.locator('#nldesign-theming-dialog-overlay')
				await expect(dialog).toBeVisible({ timeout: 15_000 })
				await dialog.locator('.nldesign-dialog-cancel').click()
				await expect(dialog).toHaveCount(0)
				expect(posts).toEqual([])
				expect(await getTokenSet(page, token)).toBe(SET_WITHOUT_DARK_LOGO)
				await page.unrouteAll({ behavior: 'ignoreErrors' })
				expect(stateOf(await readTheming(page))).toEqual(stateOf(before))
			},
		)
	})

	// -----------------------------------------------------------------------
	// Requirement: Route Configuration
	// -----------------------------------------------------------------------

	// @e2e openspec/specs/theming-sync/spec.md#get-theming-route
	test('GET /settings/theming reaches getThemingValues', async ({ page }) => {
		await openSettings(page)
		const values = await readTheming(page)
		// The snapshot keys are getThemingValues()'s and nobody else's.
		for (const key of Object.keys(STUB_CURRENT)) {
			expect(values, `snapshot key ${key}`).toHaveProperty(key)
		}
	})

	// @e2e openspec/specs/theming-sync/spec.md#post-theming-route
	test('POST /settings/theming reaches updateThemingValues', async ({ page }) => {
		await openSettings(page)
		const res = await postTheming(page, { primary_color: 'route-check' })
		expect(res.status).toBe(400)
		expect(res.json.error).toBe(
			'Invalid hex color for primary_color: route-check',
		)
	})

	test.describe('as a non-admin', () => {
		let nonAdmin: { page: Page; close: () => Promise<void> }

		test.beforeAll(async ({ browser }) => {
			// Setup budget: provisioning plus a full form login on a cold instance.
			test.setTimeout(180_000)
			const adminCtx = await adminContext(browser)
			const adminPage = await adminCtx.newPage()
			await adminPage.goto(THEMING_URL, { waitUntil: 'domcontentloaded' })
			await ensureNonAdminUser(adminPage)
			await adminCtx.close()
			nonAdmin = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		})

		test.afterAll(async () => {
			await nonAdmin?.close()
		})

		/** Status of a theming call made from the non-admin's own session. */
		async function nonAdminStatus(method: 'GET' | 'POST'): Promise<number> {
			return nonAdmin.page.evaluate(async (m) => {
				const OC = (
					window as unknown as {
						OC: {
							generateUrl: (p: string) => string
							requestToken: string
						}
					}
				).OC
				const init: RequestInit = {
					method: m,
					headers: { requesttoken: OC.requestToken },
				}
				if (m === 'POST') {
					;(init.headers as Record<string, string>)['Content-Type'] =
						'application/x-www-form-urlencoded'
					init.body = 'primary_color=%23004699'
				}
				const r = await fetch(
					OC.generateUrl('/apps/thematiq/settings/theming'),
					init,
				)
				return r.status
			}, method)
		}

		// @e2e openspec/specs/theming-sync/spec.md#non-admin-access-denied
		test('a non-admin POST is refused and changes nothing', async ({ page }) => {
			await openSettings(page)
			const before = await readTheming(page)
			expect(await nonAdminStatus('POST')).toBe(403)
			expect(stateOf(await readTheming(page))).toEqual(stateOf(before))
			// Control arm: the admin is not refused by the same check.
			const asAdmin = await postTheming(page, { primary_color: 'control' })
			expect(asAdmin.status).toBe(400)
		})

		// @e2e openspec/specs/theming-sync/spec.md#both-routes-admin-only
		test('both theming routes refuse a non-admin and admit an admin', async ({
			page,
		}) => {
			expect(await nonAdminStatus('GET')).toBe(403)
			expect(await nonAdminStatus('POST')).toBe(403)
			await openSettings(page)
			expect((await readTheming(page)).primary_color).toBeDefined()
			expect(
				(await postTheming(page, { primary_color: 'control' })).status,
			).toBe(400)
		})
	})
})
