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
