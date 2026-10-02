/*
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * @e2e openspec/specs/nextcloud-variable-mapping/spec.md
 *
 * Proves the Nextcloud variable mapping against the running Nextcloud.
 *
 * The variables Nextcloud defines are read from its own theme stylesheets
 * (`link.theme`, served by the theming app) through the CSSOM, so the audit
 * is checked against the Nextcloud version under test, not against a list
 * copied into this file. The mapping is read from the served overrides.css.
 * The documentation table, docs/reference/mappings.md, is not served by
 * Nextcloud, so it is read from the checkout the instance runs. It is
 * generated from the variable inventory (scripts/mapping/), and
 * `npm run test:inventory` fails when the two drift, so the inventory is read
 * alongside it for the classes the page lists by count rather than by row.
 *
 * Cascade claims run two ways: in an isolated document built from the served
 * stylesheets (no instance state touched), and on a live page with Gemeente
 * Amsterdam active.
 *
 * GLOBAL STATE: the live describe switches the active set to `amsterdam` and
 * empties that set's overrides. Both are restored in afterAll.
 */
import { test, expect, type Page } from '@playwright/test'
import * as fs from 'fs'
import * as path from 'path'
import {
	activateTokenSet,
	declarations,
	indexOfCss,
	normaliseCss,
	openThemedPage,
	rootVars,
	servedCss,
	stripComments,
	stylesheetPaths,
} from './_token-css'

const DEFAULTS = 'systems/nldesign/defaults.css'
const OVERRIDES = 'systems/nldesign/overrides.css'
const MAPPINGS_MD = path.resolve(__dirname, '../../../docs/reference/mappings.md')
const INVENTORY = path.resolve(
	__dirname,
	'../../../scripts/mapping/nextcloud-variables.json',
)
const STATUSES = path.resolve(
	__dirname,
	'../../../scripts/mapping/variable-status.json',
)

/** Amsterdam's primary, from css/tokens/amsterdam.css. */
const AMSTERDAM_PRIMARY = '#004699'

type Entries = {
	/** Nextcloud variable to the `--nldesign-*` token it is mapped to. */
	mapped: Map<string, string>
	/** Raw declaration values, to check their shape. */
	mappedValues: Map<string, string>
	/** Nextcloud variable to its comment: `{ kind, reason }`. */
	commented: Map<string, { kind: string; reason: string }>
}

/** Parse overrides.css into its mapped and commented entries. */
function parseOverrides(css: string): Entries {
	const mappedValues = declarations(stripComments(css), '--')
	const mapped = new Map<string, string>()
	for (const [name, value] of mappedValues) {
		const token = /var\(\s*(--nldesign-[\w-]+)/.exec(value)
		mapped.set(name, token !== null ? token[1] : '')
	}
	const commented = new Map<string, { kind: string; reason: string }>()
	for (const match of css.matchAll(/\/\*\s*(--[\w-]+)\s*:\s*([\s\S]*?)\*\//g)) {
		const body = match[2].trim()
		const shaped =
			/^(unmapped|intentionally not overridden)\s*[—–-]+\s*(.*)$/s.exec(body)
		commented.set(match[1], {
			kind: shaped !== null ? shaped[1] : '',
			reason: shaped !== null ? shaped[2].trim() : '',
		})
	}
	return { mapped, mappedValues, commented }
}

type DocRow = {
	/** `mapped`, `settable` or `excluded`. */
	status: string
	/** The `--nldesign-*` token, empty when the row names none. */
	token: string
	/** The Note cell; null in tables that have no Note column. */
	note: string | null
	/** The `###` (or `##`) heading the row sits under. */
	section: string
}

/**
 * Every row of every variable table in docs/reference/mappings.md. The page
 * has two table shapes (theme: Variable, Status, Token, Default, Dark, Note;
 * component, slot and library: Variable, Family, Status, Token, Value), so
 * cells are read by their header, not by position.
 */
function mappingRows(): Map<string, DocRow> {
	const rows = new Map<string, DocRow>()
	const cells = (line: string) =>
		line
			.split('|')
			.slice(1, -1)
			.map((c) => c.trim().replace(/`/g, ''))
	let header: string[] | null = null
	let section = ''
	for (const line of fs.readFileSync(MAPPINGS_MD, 'utf8').split('\n')) {
		const heading = /^#{2,3}\s+(.*)$/.exec(line)
		if (heading !== null) {
			section = heading[1].trim()
			header = null
			continue
		}
		if (/^\|\s*Variable\s*\|/.test(line)) {
			header = cells(line)
			continue
		}
		const name = /^\|\s*`(--[\w-]+)`/.exec(line)
		if (name === null || header === null) continue
		const row = cells(line)
		const cell = (h: string) => {
			const i = header?.indexOf(h) ?? -1
			return i === -1 ? null : (row[i] ?? '')
		}
		rows.set(name[1], {
			status: cell('Status') ?? '',
			token: cell('Token') ?? '',
			note: cell('Note'),
			section,
		})
	}
	return rows
}

/** Every name the inventory records, whether or not it has its own row. */
function inventoryNames(): Set<string> {
	const inv = JSON.parse(fs.readFileSync(INVENTORY, 'utf8'))
	return new Set(Object.keys(inv.variables))
}

/** The recorded status and reason of every inventory entry. */
function inventoryStatuses(): Map<string, { status: string; reason: string }> {
	const raw = JSON.parse(fs.readFileSync(STATUSES, 'utf8'))
	const out = new Map<string, { status: string; reason: string }>()
	for (const [name, v] of Object.entries(raw.variables)) {
		const e = v as { status?: string; reason?: string }
		out.set(name, { status: e.status ?? '', reason: e.reason ?? '' })
	}
	return out
}

/**
 * Every custom property Nextcloud's theme stylesheets declare on this page.
 * These are the `link.theme` stylesheets the theming app injects, one per
 * theme, including the ones gated behind a media query.
 */
async function nextcloudVariables(page: Page): Promise<string[]> {
	await page.waitForLoadState('load')
	return page.evaluate(() => {
		const names = new Set<string>()
		const walk = (rules: CSSRuleList) => {
			for (const rule of Array.from(rules)) {
				if (rule instanceof CSSStyleRule) {
					for (let i = 0; i < rule.style.length; i++) {
						const prop = rule.style[i]
						if (prop.startsWith('--')) names.add(prop)
					}
				}
				const nested = (rule as CSSGroupingRule).cssRules
				if (nested !== undefined) walk(nested)
			}
		}
		for (const sheet of Array.from(document.styleSheets)) {
			if ((sheet.href ?? '').includes('/apps/theming/theme/') === false)
				continue
			try {
				walk(sheet.cssRules)
			} catch {
				// A sheet that failed to load has no rules to read.
			}
		}
		return [...names].sort()
	})
}

/** Resolve custom properties in a document that carries only `sheets`. */
async function isolatedCascade(
	page: Page,
	sheets: string[],
	names: string[],
): Promise<Record<string, string>> {
	const styles = sheets.map((css) => `<style>${css}</style>`).join('')
	await page.setContent(
		`<!doctype html><html><head>${styles}</head><body></body></html>`,
	)
	return rootVars(page, names)
}

/** A stand-in for Nextcloud's theme: every name set to one marker value. */
function nextcloudSheet(names: string[], value: string): string {
	return `:root { ${names.map((n) => `${n}: ${value};`).join(' ')} }`
}

test.describe('nextcloud-variable-mapping', () => {
	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#all-nextcloud-variables-are-accounted-for
	'every variable the running Nextcloud defines has an entry in overrides.css', async ({
		page,
	}) => {
		await openThemedPage(page)
		const ncVars = await nextcloudVariables(page)
		// Over a hundred on Nextcloud 35; a floor guards against reading none.
		expect(ncVars.length).toBeGreaterThanOrEqual(50)

		const entries = parseOverrides(await servedCss(page, OVERRIDES))
		const missing = ncVars.filter(
			(n) =>
				entries.mapped.has(n) === false
				&& entries.commented.has(n) === false,
		)
		expect(missing, `no entry in overrides.css: ${missing.join(', ')}`).toEqual(
			[],
		)

		// Each entry maps to an --nldesign-* token or is commented with a reason.
		const unexplained = ncVars.filter((n) => {
			if (entries.mapped.has(n)) return entries.mapped.get(n) === ''
			return (entries.commented.get(n)?.reason ?? '') === ''
		})
		expect(
			unexplained,
			`neither mapped nor reasoned: ${unexplained.join(', ')}`,
		).toEqual([])
	})

	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#new-nextcloud-variable-is-added-upstream
	'mappings.md and overrides.css both list every variable of the Nextcloud under test', async ({
		page,
	}) => {
		// The maintainers' review cannot be watched, its outcome can: once
		// Nextcloud ships a variable neither file names, this fails and names it.
		await openThemedPage(page)
		const ncVars = await nextcloudVariables(page)
		expect(ncVars.length).toBeGreaterThanOrEqual(50)
		const entries = parseOverrides(await servedCss(page, OVERRIDES))
		const rows = mappingRows()
		// The icon, runtime and unread classes are listed by count, not by row.
		const inventory = inventoryNames()
		const documented = (n: string) => rows.has(n) || inventory.has(n)

		const notInDocs = ncVars.filter((n) => documented(n) === false)
		expect(
			notInDocs,
			`missing from mappings.md: ${notInDocs.join(', ')}`,
		).toEqual([])
		const notInCss = ncVars.filter(
			(n) =>
				entries.mapped.has(n) === false
				&& entries.commented.has(n) === false,
		)
		expect(
			notInCss,
			`missing from overrides.css: ${notInCss.join(', ')}`,
		).toEqual([])
		// And the two files describe the same set of variables.
		const cssOnly = [
			...entries.mapped.keys(),
			...entries.commented.keys(),
		].filter((n) => documented(n) === false)
		expect(
			cssOnly,
			`in overrides.css but not in mappings.md: ${cssOnly.join(', ')}`,
		).toEqual([])
	})

	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#mapped-variable
	'a mapped variable is var(--nldesign-*) !important and beats a later Nextcloud value', async ({
		page,
	}) => {
		await openThemedPage(page)
		const defaults = await servedCss(page, DEFAULTS)
		const overrides = await servedCss(page, OVERRIDES)
		const entries = parseOverrides(overrides)
		expect(entries.mapped.size).toBeGreaterThanOrEqual(50)
		const badShape = [...entries.mappedValues].filter(
			([, v]) =>
				/^var\(\s*--nldesign-[\w-]+\s*\)\s*!important$/.test(normaliseCss(v))
				=== false,
		)
		expect(badShape.map(([n]) => n)).toEqual([])

		// Nextcloud's own value comes LATER in this document and still loses.
		const mappedNames = [...entries.mapped.keys()]
		const v = await isolatedCascade(
			page,
			[defaults, overrides, nextcloudSheet(mappedNames, 'rgb(1, 2, 3)')],
			[...mappedNames, ...entries.mapped.values()],
		)
		const lost = mappedNames.filter(
			(n) =>
				v[n] === ''
				|| normaliseCss(v[n])
					!== normaliseCss(v[entries.mapped.get(n) ?? '']),
		)
		expect(
			lost,
			`not carrying their --nldesign-* value: ${lost.join(', ')}`,
		).toEqual([])
		expect(v['--color-primary-element'].toLowerCase()).toBe(
			v['--nldesign-color-primary'].toLowerCase(),
		)
	})

	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#unmapped-variable
	'an unmapped variable is a reasoned comment and keeps the Nextcloud value', async ({
		page,
	}) => {
		await openThemedPage(page)
		const defaults = await servedCss(page, DEFAULTS)
		const overrides = await servedCss(page, OVERRIDES)
		const entries = parseOverrides(overrides)
		const unmapped = [...entries.commented].filter(
			([, c]) => c.kind === 'unmapped',
		)
		expect(unmapped.length).toBeGreaterThan(0)
		const unreasoned = unmapped
			.filter(([, c]) => c.reason.length < 3)
			.map(([n]) => n)
		expect(
			unreasoned,
			`unmapped without a reason: ${unreasoned.join(', ')}`,
		).toEqual([])

		const names = unmapped.map(([n]) => n)
		const v = await isolatedCascade(
			page,
			[nextcloudSheet(names, 'rgb(1, 2, 3)'), defaults, overrides],
			names,
		)
		const taken = names.filter((n) => normaliseCss(v[n]) !== 'rgb(1,2,3)')
		expect(
			taken,
			`overridden although commented out: ${taken.join(', ')}`,
		).toEqual([])
	})

	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#intentionally-unoverridden-variable
	'--color-main-background is commented "intentionally not overridden" and stays Nextcloud\'s', async ({
		page,
	}) => {
		await openThemedPage(page)
		const defaults = await servedCss(page, DEFAULTS)
		const overrides = await servedCss(page, OVERRIDES)
		const entries = parseOverrides(overrides)
		const main = entries.commented.get('--color-main-background')
		expect(main?.kind).toBe('intentionally not overridden')
		expect(main?.reason ?? '').toMatch(/\w{3,}/)
		expect(entries.mapped.has('--color-main-background')).toBe(false)

		const v = await isolatedCascade(
			page,
			[
				nextcloudSheet(['--color-main-background'], '#abcdef'),
				defaults,
				overrides,
			],
			['--color-main-background'],
		)
		expect(v['--color-main-background'].toLowerCase()).toBe('#abcdef')
	})

	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#developer-looks-up-a-nextcloud-variable
	'mappings.md has a full row for --color-primary-element that matches overrides.css', async ({
		page,
	}) => {
		await openThemedPage(page)
		const entries = parseOverrides(await servedCss(page, OVERRIDES))
		const rows = mappingRows()
		const row = rows.get('--color-primary-element')
		expect(row?.status).toBe('mapped')
		expect(row?.token).toBe('--nldesign-color-primary')
		expect(row?.section).toBe('Primary Colors')
		expect(row?.note ?? '').toMatch(/\w{3,}/)
		expect(entries.mapped.get('--color-primary-element')).toBe(row?.token)

		// Every other mapped variable is documented as mapped, and a theme
		// row (the tables with a Note column) names the token overrides.css
		// uses. A component row may name the component-level token that
		// resolves through it instead.
		const wrong = [...entries.mapped]
			.filter(([n, token]) => {
				const r = rows.get(n)
				if (r === undefined || r.status !== 'mapped') return true
				return r.note !== null && r.token !== token
			})
			.map(
				([n, token]) =>
					`${n}: css ${token}, docs ${rows.get(n)?.status} ${rows.get(n)?.token}`,
			)
		expect(wrong, wrong.join('; ')).toEqual([])
	})

	test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#unmapped-variable-in-documentation
	'every excluded entry shows excluded in mappings.md and has a recorded reason', async ({
		page,
	}) => {
		await openThemedPage(page)
		const rows = mappingRows()
		const excluded = [...inventoryStatuses()].filter(
			([, e]) => e.status === 'excluded',
		)
		expect(excluded.length).toBeGreaterThan(0)

		// The reason is recorded in variable-status.json, the source the page
		// is generated from. Only theme tables carry a Note column, so the
		// reason is checked on the row itself where the row has one.
		const wrong = excluded
			.filter(([n, e]) => {
				if (e.reason.length < 3) return true
				const r = rows.get(n)
				if (r === undefined) return false
				if (r.status !== 'excluded') return true
				return r.note !== null && r.note.length < 3
			})
			.map(([n]) => `${n}: docs "${rows.get(n)?.status}"`)
		expect(wrong, wrong.join('; ')).toEqual([])
	})

	test.describe('on a live page with Amsterdam active', () => {
		test.describe.configure({ mode: 'serial', timeout: 90_000 })

		let restore: (() => Promise<void>) | null = null

		test.beforeAll(async ({ browser }) => {
			restore = await activateTokenSet(browser, 'amsterdam')
		})

		test.afterAll(async () => {
			if (restore !== null) {
				await restore()
				restore = null
			}
		})

		test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#token-has-no-organization-specific-override
		'a token Amsterdam does not define keeps the defaults.css value', async ({
			page,
		}) => {
			await openThemedPage(page)
			const amsterdam = declarations(
				await servedCss(page, 'tokens/amsterdam.css'),
				'--nldesign-',
			)
			const defaults = declarations(
				await servedCss(page, DEFAULTS),
				'--nldesign-',
			)
			expect(amsterdam.has('--nldesign-color-favorite')).toBe(false)
			const expected = (
				defaults.get('--nldesign-color-favorite') ?? ''
			).toLowerCase()
			expect(expected).toMatch(/^#[0-9a-f]{6}$/)

			const v = await rootVars(page, ['--nldesign-color-favorite'])
			expect(v['--nldesign-color-favorite'].toLowerCase()).toBe(expected)
		})

		test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#token-is-overridden-by-organization
		'a token Amsterdam defines wins over the defaults.css value', async ({
			page,
		}) => {
			await openThemedPage(page)
			const defaults = declarations(
				await servedCss(page, DEFAULTS),
				'--nldesign-',
			)
			const fallback = (
				defaults.get('--nldesign-color-primary') ?? ''
			).toLowerCase()
			expect(fallback).toMatch(/^#[0-9a-f]{6}$/)
			expect(fallback).not.toBe(AMSTERDAM_PRIMARY)

			const v = await rootVars(page, ['--nldesign-color-primary'])
			expect(v['--nldesign-color-primary'].toLowerCase()).toBe(
				AMSTERDAM_PRIMARY,
			)
		})

		test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#new-nldesign-token-is-added
		'every --nldesign-* token the stylesheets read without a fallback has a default, and resolves on an unchanged set', async ({
			page,
		}) => {
			await openThemedPage(page)
			const defaults = declarations(
				await servedCss(page, DEFAULTS),
				'--nldesign-',
			)
			const consumed = new Set<string>()
			for (const file of [
				OVERRIDES,
				'systems/nldesign/theme.css',
				'systems/nldesign/element-overrides.css',
				'systems/nldesign/utrecht-bridge.css',
			]) {
				const css = stripComments(await servedCss(page, file))
				for (const m of css.matchAll(/var\(\s*(--nldesign-[\w-]+)\s*\)/g)) {
					consumed.add(m[1])
				}
			}
			expect(consumed.size).toBeGreaterThan(50)
			const noDefault = [...consumed].filter((n) => defaults.has(n) === false)
			expect(
				noDefault,
				`read with no default: ${noDefault.join(', ')}`,
			).toEqual([])

			// Amsterdam's token file was not written with these in mind, and
			// still every one of them has a value.
			const live = await rootVars(page, [...consumed])
			const empty = [...consumed].filter((n) => live[n] === '')
			expect(
				empty,
				`resolve to nothing with Amsterdam: ${empty.join(', ')}`,
			).toEqual([])
		})

		test(// @e2e openspec/specs/nextcloud-variable-mapping/spec.md#css-files-load-in-correct-order
		'the design system stylesheets load in declared order, then the token set, then custom overrides', async ({
			page,
		}) => {
			await openThemedPage(page)
			const paths = await stylesheetPaths(page)
			const order = [
				'systems/nldesign/fonts',
				'systems/nldesign/defaults',
				'systems/nldesign/utrecht-bridge',
				'systems/nldesign/theme',
				'systems/nldesign/overrides',
				'systems/nldesign/element-overrides',
				'tokens/amsterdam',
				'custom-overrides',
			]
			const positions = order.map((file) => indexOfCss(paths, file))
			expect(
				positions.filter((p) => p < 0).length,
				`page carries ${paths.join(', ')}`,
			).toBe(0)
			expect(positions).toEqual([...positions].sort((a, b) => a - b))

			// The later token file wins over the earlier defaults.
			const v = await rootVars(page, ['--nldesign-color-primary'])
			expect(v['--nldesign-color-primary'].toLowerCase()).toBe(
				AMSTERDAM_PRIMARY,
			)
		})
	})
})
