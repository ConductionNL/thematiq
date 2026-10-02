/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The inventory guard: what thematiq claims about every Nextcloud variable
 * must be true of its stylesheets.
 *
 * scripts/mapping/nextcloud-variables.json lists every custom property a
 * Nextcloud release and @conduction/nextcloud-vue declare or read.
 * scripts/mapping/variable-status.json says, per entry, whether thematiq maps
 * it, lets a theme set it, or leaves it alone and why. This suite fails when
 * the two disagree, when an entry has no status, when a mapping is claimed but
 * not in the CSS, and when the count of mapped or settable entries moves
 * without the baseline moving with it, in either direction.
 *
 * @spec openspec/changes/nc-variable-inventory/specs/nextcloud-variable-inventory/spec.md
 */

import { describe, expect, it } from 'vitest'
import { readFileSync } from 'node:fs'
import { join } from 'node:path'
import { globSync } from 'node:fs'

const ROOT = join(__dirname, '..', '..')
const read = (p) => readFileSync(join(ROOT, p), 'utf8')
const json = (p) => JSON.parse(read(p))

const lib = await import(join(ROOT, 'scripts/inventory/lib.mjs'))
const docs = await import(join(ROOT, 'scripts/inventory/generate-mappings-doc.mjs'))
const internalGen = await import(join(ROOT, 'scripts/inventory/generate-internal-tokens.mjs'))

const inventory = json('scripts/mapping/nextcloud-variables.json')
const status = json('scripts/mapping/variable-status.json')
const baseline = json('scripts/mapping/variable-baseline.json')
const vars = inventory.variables
const st = status.variables

const STATUSES = ['mapped', 'settable', 'excluded']
const files = (pattern) =>
	globSync(pattern, { cwd: ROOT })
		.sort()
		.map((f) => join(ROOT, f))
const nldesign = lib.assignmentsOf(files('css/systems/nldesign/*.css'))
const allCss = lib.assignmentsOf(files('css/**/*.css'))
const nextcloudFacing = lib.assignmentsOf([
	...files('css/systems/*/*.css'),
	...files('css/*.css'),
])

describe('the stylesheet reader', () => {
	it('does not count a commented-out mapping', () => {
		const found = lib.assignments(
			'/* --color-mark: var(--nldesign-color-mark); */ :root { --color-main-text: red; }',
		)
		expect([...found.keys()]).toEqual(['--color-main-text'])
	})

	it('does not count a self-reference as an assignment', () => {
		const found = lib.assignments(
			'body { --x: var(--x); --y: var(--y, #fff) !important; --z: var(--nldesign-z); }',
		)
		expect([...found.keys()]).toEqual(['--z'])
	})
})

describe('the inventory', () => {
	it('names the versions it was read from', () => {
		expect(inventory.nextcloud).toMatch(/^\d+\.\d+\.\d+/)
		expect(inventory.conductionNextcloudVue).toMatch(/^\d+\.\d+\.\d+/)
	})

	it('classes every entry', () => {
		const allowed = [
			'theme',
			'component',
			'slot',
			'icon',
			'runtime',
			'unread',
			'conduction',
		]
		const unclassed = Object.entries(vars)
			.filter(([, e]) => !allowed.includes(e.class))
			.map(([n]) => n)
		expect(unclassed).toEqual([])
	})

	it('records a stock value for every theme entry, keyed by the theme that declares it', () => {
		// Not every theme declares every variable: --color-text-warning exists
		// only in dark high contrast. So the rule is one value at least, per theme.
		const themes = ['default', 'dark', 'light-highcontrast', 'dark-highcontrast']
		const missing = Object.entries(vars)
			.filter(([, e]) => e.class === 'theme')
			.filter(
				([, e]) =>
					!e.stock
					|| Object.keys(e.stock).length === 0
					|| Object.keys(e.stock).some((t) => !themes.includes(t)),
			)
			.map(([n]) => n)
		expect(missing).toEqual([])
	})

	it('records distinct light and dark values where Nextcloud gives them', () => {
		expect(vars['--color-main-text'].stock.default).not.toBe(
			vars['--color-main-text'].stock.dark,
		)
	})

	it('counts its own entries correctly', () => {
		const counted = {}
		for (const e of Object.values(vars))
			counted[e.class] = (counted[e.class] || 0) + 1
		expect(counted).toEqual(inventory.counts)
	})
})

describe('the status file', () => {
	it('gives every inventory entry a status', () => {
		expect(Object.keys(vars).filter((n) => !st[n])).toEqual([])
	})

	it('holds no status for a name the inventory does not know', () => {
		expect(Object.keys(st).filter((n) => !vars[n])).toEqual([])
	})

	it('uses only the three statuses', () => {
		expect(
			Object.entries(st)
				.filter(([, s]) => !STATUSES.includes(s.status))
				.map(([n]) => n),
		).toEqual([])
	})

	it('gives every excluded entry a reason', () => {
		const silent = Object.entries(st)
			.filter(
				([, s]) => s.status === 'excluded' && !(s.reason && s.reason.trim()),
			)
			.map(([n]) => n)
		expect(silent).toEqual([])
	})

	it('excludes no theme entry outside the icon class without a recorded reason', () => {
		const unexplained = Object.entries(st)
			.filter(
				([n, s]) =>
					vars[n].class === 'theme'
					&& s.status === 'excluded'
					&& /no recorded reason/.test(s.reason),
			)
			.map(([n]) => n)
		expect(unexplained).toEqual([])
	})
})

describe('a mapping is only claimed where the CSS makes it', () => {
	it('every theme entry mapped via a stylesheet is assigned by the nldesign stylesheets', () => {
		const claimed = Object.entries(st).filter(
			([n, s]) =>
				vars[n].class === 'theme'
				&& s.status === 'mapped'
				&& s.via === 'stylesheet',
		)
		expect(claimed.map(([n]) => n).filter((n) => !nldesign.has(n))).toEqual([])
	})

	it('every other entry mapped via a stylesheet is assigned somewhere in css/', () => {
		const claimed = Object.entries(st).filter(
			([n, s]) =>
				vars[n].class !== 'theme'
				&& s.status === 'mapped'
				&& s.via === 'stylesheet',
		)
		expect(claimed.map(([n]) => n).filter((n) => !allCss.has(n))).toEqual([])
	})

	it('a token named for a mapping is the one the stylesheet uses', () => {
		const wrong = Object.entries(st)
			.filter(
				([, s]) =>
					s.status === 'mapped' && s.via === 'stylesheet' && s.token,
			)
			.filter(
				([n, s]) => !(allCss.get(n) || []).some((v) => v.includes(s.token)),
			)
			.map(([n]) => n)
		expect(wrong).toEqual([])
	})

	it('a mapping through Nextcloud theming says why', () => {
		const silent = Object.entries(st)
			.filter(
				([, s]) =>
					s.via === 'nextcloud-theming' && !(s.reason && s.reason.trim()),
			)
			.map(([n]) => n)
		expect(silent).toEqual([])
	})

	it('every theme variable the nldesign stylesheets assign is recorded as mapped', () => {
		const unrecorded = [...nldesign.keys()].filter(
			(n) => vars[n]?.class === 'theme' && st[n]?.status !== 'mapped',
		)
		expect(unrecorded).toEqual([])
	})

	it('thematiq assigns no Nextcloud-style name that Nextcloud does not have', () => {
		const known = new Set(Object.keys(status.deadAssignments || {}))
		const unknown = [...nextcloudFacing.keys()].filter(
			(n) => lib.NEXTCLOUD_PREFIX.test(n) && !vars[n] && !known.has(n),
		)
		expect(unknown).toEqual([])
	})

	it('the recorded dead assignments are still dead and still present, so the list can only shrink', () => {
		const stale = Object.keys(status.deadAssignments || {}).filter(
			(n) => vars[n] || !nextcloudFacing.has(n),
		)
		expect(stale).toEqual([])
	})
})

describe('a settable variable is settable in the CSS, and only when set', () => {
	const settable = Object.entries(st).filter(
		([n, e]) => vars[n].class === 'theme' && e.status === 'settable',
	)
	const themeScopes = lib.stripComments(read('css/theme-scopes.css'))
	const captures = lib.stripComments(read('css/component-scopes.css'))
	const resetBlocks = themeScopes.slice(themeScopes.indexOf('@media'))
	const escape = (x) => x.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')

	it('names a --nldesign-nc-* token for each one', () => {
		const wrong = settable
			.filter(([n, e]) => e.token !== '--nldesign-nc-' + n.slice(2))
			.map(([n]) => n)
		expect(wrong).toEqual([])
	})

	it('redeclares each one on body children from its capture', () => {
		const missing = settable
			.filter(
				([n]) =>
					new RegExp(
						escape(n)
							+ '\\s*:\\s*var\\(\\s*--thematiq-global-'
							+ escape(n.slice(2))
							+ '\\s*\\)',
					).test(themeScopes) === false,
			)
			.map(([n]) => n)
		expect(missing).toEqual([])
	})

	it("captures each one preferring its token, then Nextcloud's own value", () => {
		const missing = settable
			.filter(
				([n, e]) =>
					new RegExp(
						'--thematiq-global-'
							+ escape(n.slice(2))
							+ '\\s*:\\s*var\\(\\s*'
							+ escape(e.token)
							+ '\\s*,\\s*var\\(\\s*'
							+ escape(n)
							+ '\\s*\\)\\s*\\)',
					).test(captures) === false,
			)
			.map(([n]) => n)
		expect(missing).toEqual([])
	})

	it('resets exactly the variables Nextcloud gives a different dark value', () => {
		const expected = settable
			.filter(([, e]) => e.perScheme === true)
			.map(([, e]) => e.token)
			.sort()
		const reset = [
			...new Set(
				[
					...resetBlocks.matchAll(/(--nldesign-nc-[\w-]+)\s*:\s*initial/g),
				].map((m) => m[1]),
			),
		].sort()
		expect(reset).toEqual(expected)
	})

	it('gives no settable token a default, so unset means unset', () => {
		const defaults = lib.assignmentsOf(files('css/systems/*/defaults.css'))
		const defaulted = settable
			.filter(([, e]) => defaults.has(e.token))
			.map(([n]) => n)
		expect(defaulted).toEqual([])
	})

	it('flags exactly the thirteen structural variables advanced', () => {
		const advanced = settable
			.filter(([, e]) => e.advanced === true)
			.map(([n]) => n)
			.sort()
		expect(advanced).toEqual([
			'--body-container-margin',
			'--body-height',
			'--breakpoint-mobile',
			'--clickable-area-large',
			'--clickable-area-small',
			'--default-clickable-area',
			'--default-grid-baseline',
			'--filter-background-blur',
			'--header-height',
			'--header-menu-item-height',
			'--navigation-width',
			'--sidebar-max-width',
			'--sidebar-min-width',
		])
	})
})

describe('an internal variable is settable through its token, and only when set', () => {
	const internal = Object.entries(st).filter(
		([n, e]) => internalGen.INTERNAL_CLASSES.includes(vars[n].class) && e.status === 'settable',
	)
	const map = json('scripts/mapping/internal-tokens.json').tokens

	it('names --nldesign-nc-* for Nextcloud and --nldesign-cn-* for the shared library', () => {
		const wrong = internal
			.filter(([n, e]) => e.token !== (n.startsWith('--cn-') ? '--nldesign-cn-' + n.slice(5) : '--nldesign-nc-' + n.slice(2)))
			.map(([n]) => n)
		expect(wrong).toEqual([])
	})

	it('gives every one a token in the map the server reads, and nothing else', () => {
		expect(Object.keys(map).sort()).toEqual(internal.map(([, e]) => e.token).sort())
		expect(json('scripts/mapping/internal-tokens.json')).toEqual(internalGen.build(inventory, status))
	})

	it('reaches each declared variable on the selectors that declare it', () => {
		// A declared variable falls back to body only when none of its recorded
		// selectors is valid CSS; none does today, so the list must stay empty.
		const lost = Object.values(map)
			.filter((t) => vars[t.variable].selectors?.length > 0 && t.mode !== 'selectors')
			.map((t) => t.variable)
		expect(lost).toEqual([])
	})

	it('ships no value for any internal token, so unset means unset', () => {
		const declared = Object.keys(map).filter((token) => allCss.has(token))
		expect(declared).toEqual([])
	})

	it('excludes every variable written at render time', () => {
		const vueBound = Object.keys(vars).filter((n) => /^--v?[0-9a-f]{8}$/.test(n))
		expect(vueBound.filter((n) => st[n].status !== 'excluded')).toEqual([])
		expect(Object.values(map).filter((t) => vars[t.variable].class === 'runtime')).toEqual([])
	})
})

describe('coverage cannot move without the baseline moving with it', () => {
	it('matches the baseline exactly, in both directions', () => {
		const counted = {}
		for (const [n, s] of Object.entries(st)) {
			const key = `${vars[n].class}/${s.status}`
			counted[key] = (counted[key] || 0) + 1
		}
		expect(counted).toEqual(baseline.counts)
	})
})

describe('the mappings page', () => {
	it('is exactly what the inventory and the status file generate', () => {
		expect(read('docs/reference/mappings.md')).toBe(
			docs.render(inventory, status),
		)
	})
})
