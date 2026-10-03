/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * openspec/specs/prometheus-metrics/spec.md, proven over HTTP from the browser.
 *
 * Both endpoints are plain GETs a browser can make: `/api/metrics` with the
 * admin session the suite already holds, `/api/health` from a context with no
 * session at all. The Prometheus text is asserted line by line.
 *
 * Scenarios that need a collaborator to throw, an app value to be unset, or a
 * source-level property are proven in PHPUnit instead; each carries its own
 * `@e2e exclude` in the spec naming the test.
 *
 * Expected values come from the running instance and the repo, never from a
 * hard-coded number: the token-set count from the public catalogue, the app
 * version from the provisioning API, the Nextcloud version from status.php,
 * the declared health checks from src/manifest.json.
 */
import { test, expect, type Page } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'

import {
	api,
	ensureNonAdminUser,
	loginAs,
	NONADMIN_PASS,
	NONADMIN_USER,
} from './_fixtures'
import {
	getOverrides,
	getTokenSet,
	requestToken,
	setOverrides,
	setTokenSet,
} from '../workflows/_helpers'

const METRICS = '/index.php/apps/thematiq/api/metrics'
const HEALTH = '/index.php/apps/thematiq/api/health'
const THEMING_URL = '/settings/admin/theming'
const REPO = path.resolve(__dirname, '../../..')

/**
 * No cookies, no origins. `playwright.request.newContext()` inside a test
 * takes the project's `use.storageState` (the admin session) unless it is
 * given one, so a context meant to be anonymous has to say so.
 */
const NO_SESSION = { cookies: [], origins: [] }

/** The seven metric families MetricsController::index() emits. */
const FAMILIES: Record<string, { help: string; type: string }> = {
	nldesign_info: { help: 'Application information', type: 'gauge' },
	nldesign_up: { help: 'Whether the application is up', type: 'gauge' },
	nldesign_token_sets_total: {
		help: 'Total number of available token sets',
		type: 'gauge',
	},
	nldesign_active_token_set: { help: 'Currently active token set', type: 'gauge' },
	nldesign_custom_overrides_total: {
		help: 'Total custom CSS overrides',
		type: 'gauge',
	},
	nldesign_theming_syncs_total: {
		help: 'Total theming sync operations',
		type: 'counter',
	},
	nldesign_audit_entries_total: {
		help: 'Total theming audit entries written',
		type: 'counter',
	},
}

/** Scrape the metrics endpoint with the page's admin session and no CSRF token. */
async function scrape(page: Page): Promise<string[]> {
	const res = await page.request.get(METRICS, {
		headers: { Accept: 'text/plain' },
	})
	expect(res.status(), `GET ${METRICS} as admin`).toBe(200)
	return (await res.text()).split('\n').filter((line) => line !== '')
}

/** The sample lines of one metric family (`name value` or `name{labels} value`). */
function samples(lines: string[], name: string): string[] {
	return lines.filter(
		(line) => line.startsWith(`${name} `) || line.startsWith(`${name}{`),
	)
}

/** The integer value of a family that has exactly one unlabelled sample. */
function value(lines: string[], name: string): number {
	const found = samples(lines, name)
	expect(found, `exactly one ${name} sample`).toHaveLength(1)
	const match = found[0].match(new RegExp(`^${name} (\\d+)$`))
	expect(match, `${name} sample is an integer: ${found[0]}`).not.toBeNull()
	return Number((match as RegExpMatchArray)[1])
}

/** Assert HELP, TYPE and the first sample of a family sit on three consecutive lines. */
function expectFamilyBlock(
	lines: string[],
	name: string,
	sample: string | RegExp,
): void {
	const helpAt = lines.indexOf(`# HELP ${name} ${FAMILIES[name].help}`)
	expect(helpAt, `# HELP line for ${name}`).toBeGreaterThan(-1)
	expect(lines[helpAt + 1]).toBe(`# TYPE ${name} ${FAMILIES[name].type}`)
	if (typeof sample === 'string') {
		expect(lines[helpAt + 2]).toBe(sample)
	} else {
		expect(lines[helpAt + 2]).toMatch(sample)
	}
}

/** The app version Nextcloud reports for thematiq (provisioning API, admin only). */
async function installedAppVersion(page: Page): Promise<string> {
	const res = await page.request.get(
		'/ocs/v2.php/cloud/apps/thematiq?format=json',
		{
			headers: { 'OCS-APIRequest': 'true' },
		},
	)
	expect(res.status(), 'provisioning API app info').toBe(200)
	const version = (await res.json()).ocs.data.version
	expect(typeof version, 'app version from the provisioning API').toBe('string')
	return version
}

test.describe('prometheus-metrics', () => {
	test(// @e2e openspec/specs/prometheus-metrics/spec.md#metrics-endpoint-rejects-unauthenticated-requests
	'metrics refuse an anonymous caller and a signed-in non-admin', async ({
		page,
		browser,
		playwright,
		baseURL,
	}) => {
		const anonymous = await playwright.request.newContext({
			baseURL,
			storageState: NO_SESSION,
		})
		try {
			const res = await anonymous.get(METRICS, {
				headers: { Accept: 'text/plain' },
			})
			expect(res.status(), 'anonymous caller').toBe(401)
			expect(await res.text()).not.toContain('nldesign_')
		} finally {
			await anonymous.dispose()
		}

		await page.goto(THEMING_URL)
		await ensureNonAdminUser(page)
		const nonAdmin = await loginAs(browser, NONADMIN_USER, NONADMIN_PASS)
		try {
			const res = await nonAdmin.page.request.get(METRICS, {
				headers: { Accept: 'text/plain' },
			})
			expect(res.status(), 'signed-in non-admin').toBe(403)
			expect(await res.text()).not.toContain('nldesign_')
		} finally {
			await nonAdmin.close()
		}
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#metrics-endpoint-serves-an-authenticated-admin-without-a-csrf-token
	// @e2e openspec/specs/prometheus-metrics/spec.md#route-registration-is-unchanged
	'metrics serve an admin session without a CSRF token, as Prometheus text', async ({
		page,
	}) => {
		// page.request sends the admin session cookies and nothing else: no
		// requesttoken header, so a CSRF check would refuse it.
		const res = await page.request.get(METRICS)
		expect(res.status()).toBe(200)
		expect(res.headers()['content-type']).toBe(
			'text/plain; version=0.0.4; charset=utf-8',
		)
		// Only metrics#index produces this body, so the route still maps there.
		expect(await res.text()).toContain('\nnldesign_up 1\n')
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#metrics-endpoint-returns-all-metric-families-for-an-authenticated-admin
	// @e2e openspec/specs/prometheus-metrics/spec.md#help-line-format
	// @e2e openspec/specs/prometheus-metrics/spec.md#type-line-format
	'every metric family has one HELP, one TYPE and at least one sample', async ({
		page,
	}) => {
		const lines = await scrape(page)

		const helps = lines.filter((line) => line.startsWith('# HELP '))
		const types = lines.filter((line) => line.startsWith('# TYPE '))
		for (const line of helps) {
			expect(line).toMatch(/^# HELP [a-zA-Z_:][a-zA-Z0-9_:]* \S.*$/)
		}
		for (const line of types) {
			expect(line).toMatch(
				/^# TYPE [a-zA-Z_:][a-zA-Z0-9_:]* (counter|gauge|histogram|summary|untyped)$/,
			)
		}

		const helpNames = helps.map((line) => line.split(' ')[2])
		const typeNames = types.map((line) => line.split(' ')[2])
		expect([...helpNames].sort()).toEqual(Object.keys(FAMILIES).sort())
		expect([...typeNames].sort()).toEqual(Object.keys(FAMILIES).sort())

		for (const name of Object.keys(FAMILIES)) {
			expect(helps).toContain(`# HELP ${name} ${FAMILIES[name].help}`)
			expect(types).toContain(`# TYPE ${name} ${FAMILIES[name].type}`)
			expect(
				samples(lines, name).length,
				`${name} samples`,
			).toBeGreaterThanOrEqual(1)
		}

		// Nothing else: every non-comment line belongs to a declared family.
		for (const line of lines.filter((l) => !l.startsWith('#'))) {
			const name = line.split(/[{ ]/)[0]
			expect(Object.keys(FAMILIES), `sample ${line}`).toContain(name)
		}
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#info-gauge-with-version-labels
	// @e2e openspec/specs/prometheus-metrics/spec.md#info-gauge-format
	// @e2e openspec/specs/prometheus-metrics/spec.md#versions-read-from-correct-sources
	'the info gauge carries the installed app, PHP and Nextcloud versions', async ({
		page,
	}) => {
		const lines = await scrape(page)
		const pattern =
			/^nldesign_info\{version="([^"]*)",php_version="([^"]*)",nextcloud_version="([^"]*)"\} 1$/
		expectFamilyBlock(lines, 'nldesign_info', pattern)

		const info = samples(lines, 'nldesign_info')
		expect(info).toHaveLength(1)
		const [, version, phpVersion, ncVersion] = info[0].match(
			pattern,
		) as RegExpMatchArray

		expect(version).toBe(await installedAppVersion(page))
		expect(phpVersion).toMatch(/^\d+\.\d+\.\d+/)

		const status = await (await page.request.get('/status.php')).json()
		expect(ncVersion).toBe(status.version)
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#app-is-healthy
	// @e2e openspec/specs/prometheus-metrics/spec.md#up-gauge-always-1-when-endpoint-responds
	// @e2e openspec/specs/prometheus-metrics/spec.md#up-gauge-format
	'the up gauge is 1 on every successful scrape', async ({ page }) => {
		for (let i = 0; i < 2; i++) {
			const lines = await scrape(page)
			expectFamilyBlock(lines, 'nldesign_up', 'nldesign_up 1')
			expect(value(lines, 'nldesign_up')).toBe(1)
		}
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#token-sets-counted-from-filesystem
	// @e2e openspec/specs/prometheus-metrics/spec.md#token-set-metric-with-help-and-type
	'the token-set gauge counts every set in css/tokens', async ({ page }) => {
		// The public catalogue is built from the same getAvailableTokenSets()
		// scan, one entry per css/tokens/*.css file. A session caller needs the
		// CSRF token (412 without it), so it is read from inside the page.
		await page.goto(THEMING_URL)
		const res = await api(page, 'GET', '/index.php/apps/thematiq/api/token-sets')
		expect(res.status).toBe(200)
		const catalogue = res.json.tokenSets as Array<{ id: string }>

		const shipped = fs
			.readdirSync(path.join(REPO, 'css/tokens'))
			.filter((file: string) => file.endsWith('.css'))
		expect(catalogue.length).toBeGreaterThanOrEqual(shipped.length)
		for (const file of shipped) {
			expect(catalogue.map((set) => set.id)).toContain(
				file.replace(/\.css$/, ''),
			)
		}

		const lines = await scrape(page)
		expectFamilyBlock(
			lines,
			'nldesign_token_sets_total',
			`nldesign_token_sets_total ${catalogue.length}`,
		)
		expect(value(lines, 'nldesign_token_sets_total')).toBe(catalogue.length)
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#active-token-set-reported
	// @e2e openspec/specs/prometheus-metrics/spec.md#active-token-set-with-help-and-type
	// @e2e openspec/specs/prometheus-metrics/spec.md#label-values-properly-escaped
	'the active-token-set gauge names the set the admin switched to', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		const original = await getTokenSet(page, token)
		const target = original === 'amsterdam' ? 'utrecht' : 'amsterdam'

		await setTokenSet(page, token, target)
		try {
			const lines = await scrape(page)
			expectFamilyBlock(
				lines,
				'nldesign_active_token_set',
				`nldesign_active_token_set{name="${target}"} 1`,
			)
			expect(samples(lines, 'nldesign_active_token_set')).toEqual([
				`nldesign_active_token_set{name="${target}"} 1`,
			])

			// Label values go out unescaped, which is safe only while none of
			// them can hold a quote, a backslash or a newline.
			for (const line of lines.filter((l) => l.includes('{'))) {
				const labels = line.slice(
					line.indexOf('{') + 1,
					line.lastIndexOf('}'),
				)
				for (const pair of labels.split(',')) {
					expect(pair, `label in ${line}`).toMatch(/^[a-z_]+="[^"\\\n]*"$/)
				}
			}
		} finally {
			await setTokenSet(page, token, original)
		}
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#custom-overrides-counted
	// @e2e openspec/specs/prometheus-metrics/spec.md#no-custom-overrides
	// @e2e openspec/specs/prometheus-metrics/spec.md#custom-overrides-with-help-and-type
	'the overrides gauge counts the saved custom overrides', async ({ page }) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)
		const original = await getOverrides(page, token)

		try {
			await setOverrides(page, token, {
				'--color-primary': '#123456',
				'--color-primary-text': '#ffffff',
				'--color-primary-hover': '#234567',
				'--color-primary-element': '#345678',
				'--color-primary-element-hover': '#456789',
			})
			let lines = await scrape(page)
			expectFamilyBlock(
				lines,
				'nldesign_custom_overrides_total',
				'nldesign_custom_overrides_total 5',
			)

			await setOverrides(page, token, {})
			lines = await scrape(page)
			expect(value(lines, 'nldesign_custom_overrides_total')).toBe(0)
		} finally {
			await setOverrides(page, token, original)
		}
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#theming-syncs-counter-reported
	// @e2e openspec/specs/prometheus-metrics/spec.md#theming-syncs-with-help-and-type
	// @e2e openspec/specs/prometheus-metrics/spec.md#counter-format
	'a theming sync moves the syncs and audit counters up by one each', async ({
		page,
	}) => {
		await page.goto(THEMING_URL)
		await page.waitForLoadState('domcontentloaded')
		const token = await requestToken(page)

		const before = await scrape(page)
		const syncsBefore = value(before, 'nldesign_theming_syncs_total')
		const auditBefore = value(before, 'nldesign_audit_entries_total')

		// A sync with no fields changes no theming value, but it is a
		// successful sync: it counts, and it writes one audit entry.
		const status = await page.evaluate(async (t) => {
			const r = await fetch('/index.php/apps/thematiq/settings/theming', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json', requesttoken: t },
				body: JSON.stringify({}),
			})
			return r.status
		}, token)
		expect(status, 'empty theming sync').toBe(200)

		const after = await scrape(page)
		expectFamilyBlock(
			after,
			'nldesign_theming_syncs_total',
			`nldesign_theming_syncs_total ${syncsBefore + 1}`,
		)
		expectFamilyBlock(
			after,
			'nldesign_audit_entries_total',
			`nldesign_audit_entries_total ${auditBefore + 1}`,
		)
	})

	test(// @e2e openspec/specs/prometheus-metrics/spec.md#health-check-returns-the-canonical-envelope
	// @e2e openspec/specs/prometheus-metrics/spec.md#health-endpoint-is-publicly-accessible-without-csrf
	// @e2e openspec/specs/prometheus-metrics/spec.md#route-registration
	// @e2e openspec/specs/prometheus-metrics/spec.md#health-controller-is-engine-owned
	'health answers an anonymous caller with the manifest-declared checks', async ({
		page,
		playwright,
		baseURL,
	}) => {
		const manifest = JSON.parse(
			fs.readFileSync(path.join(REPO, 'src/manifest.json'), 'utf8'),
		)
		const declared = (
			manifest.observability.health.checks as Array<{ id: string }>
		)
			.map((check) => check.id)
			.sort()

		const anonymous = await playwright.request.newContext({
			baseURL,
			storageState: NO_SESSION,
		})
		let body: any
		try {
			const res = await anonymous.get(HEALTH)
			body = await res.json()
			expect(
				body.checks?.openregister,
				'OpenRegister must be installed: without it the engine is absent and health degrades',
			).toBeUndefined()
			expect(res.status(), JSON.stringify(body)).toBe(200)
		} finally {
			await anonymous.dispose()
		}

		expect(Object.keys(body).sort()).toEqual([
			'app',
			'checks',
			'status',
			'version',
		])
		expect(body.status).toBe('ok')
		expect(body.app).toBe('thematiq')
		expect(body.version).toBe(await installedAppVersion(page))
		expect(Object.keys(body.checks).sort()).toEqual(declared)
		for (const id of declared) {
			expect(body.checks[id], `checks.${id}`).toBe('ok')
		}
	})
})
