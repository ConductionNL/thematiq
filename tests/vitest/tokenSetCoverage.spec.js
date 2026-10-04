/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The stage-2 instance-coverage ratchet.
 *
 * `npm run audit:token-sets` measures four things per shipped set: how much of
 * the Utrecht component vocabulary it declares, whether the typeface it names is
 * served, whether it points Nextcloud at a logo, and its contrast verdict. This
 * spec is what makes the measurement bite.
 *
 * WHY A RATCHET AND NOT A TARGET. The stage-1 allow-list was an empty array of
 * ids with no reasons, and every shipped set passed it, so the repo's own audit
 * said "complete" while 8 sets dressed every component in Rijkshuisstijl and 34
 * showed the Nextcloud logo. A number that can only go down is the difference
 * between a baseline and a claim: this spec fails on a set below the bar that
 * nobody wrote down, on an allow-list entry that has started passing, and on an
 * entry with no usable reason.
 *
 * Each assertion below also has to be able to FAIL, so each one is paired with a
 * probe that moves the real data and shows the rule noticing.
 */
import { describe, expect, it } from 'vitest'
import fs from 'fs'
import path from 'path'
import {
	coverageAll,
	coverageFindings,
	readCoverageAllowlist,
	renderCoverageMarkdown,
	DIMENSIONS,
	REPO_ROOT,
	SYSTEM_FAMILIES,
} from '../../scripts/audit-token-sets.mjs'

const coverage = coverageAll(REPO_ROOT)
const allowlist = readCoverageAllowlist()

describe('shipped token-set instance coverage', () => {
	it('measures every selectable set whose design system reads the vocabulary', () => {
		const manifest = JSON.parse(
			fs.readFileSync(path.join(REPO_ROOT, 'token-sets.json'), 'utf8'),
		)
		const ids = coverage.map((row) => row.id)

		expect(ids.length).toBeGreaterThan(50)
		expect(new Set(ids).size).toBe(ids.length)
		// Every measured set is a selectable one, and the two sets on a design
		// system that reads no --nldesign-* token are not measured against it.
		for (const id of ids) {
			expect(manifest.some((set) => set.id === id)).toBe(true)
		}
		expect(ids).not.toContain('nextcloud')
		expect(ids).not.toContain('summer-breeze')
		expect(ids).not.toContain('conduction')
	})

	it('every dimension of every set is either at the bar or allow-listed with a reason', () => {
		const findings = coverageFindings(coverage, allowlist)

		expect(findings.unexpected, 'below the bar and not allow-listed').toEqual([])
		expect(
			findings.stale,
			'allow-listed but now passing — delete the entry',
		).toEqual([])
		expect(findings.reasonless, 'allow-listed with no usable reason').toEqual([])
	})

	it('notices a set that drops below the bar, and one that climbs above it', () => {
		// A set the allow-list does not cover, made to fail: the gate must report
		// it. Without this the assertion above would also pass on a rule that
		// has stopped looking.
		const dropped = coverage.map((row) =>
			row.id === 'vng' ? { ...row, logo: { path: null, ok: false } } : row,
		)
		expect(coverageFindings(dropped, allowlist).unexpected).toEqual([
			expect.stringContaining('logo/vng'),
		])

		// And an allow-listed set that has started passing must be reported as a
		// stale entry, so progress is recorded rather than hidden.
		const climbed = coverage.map((row) =>
			row.id === 'zwolle'
				? { ...row, bridge: { ...row.bridge, declared: 40, ok: true } }
				: row,
		)
		expect(coverageFindings(climbed, allowlist).stale).toEqual(['bridge/zwolle'])

		// And a reason too short to be a reason must be rejected.
		const thin = {
			...allowlist,
			logo: { ...allowlist.logo, zwolle: 'todo' },
		}
		expect(coverageFindings(coverage, thin).reasonless).toEqual(['logo/zwolle'])
	})

	it('the allow-list only names dimensions the audit measures', () => {
		const file = JSON.parse(
			fs.readFileSync(
				path.join(
					REPO_ROOT,
					'tests/Unit/fixtures/token-set-coverage-allowlist.json',
				),
				'utf8',
			),
		)
		const keys = Object.keys(file).filter((key) => key.startsWith('$') === false)

		expect(keys.sort()).toEqual([...DIMENSIONS].sort())
	})

	it('the bridge denominator is the bridge, not a number typed in', () => {
		const css = fs
			.readFileSync(
				path.join(REPO_ROOT, 'css/systems/nldesign/utrecht-bridge.css'),
				'utf8',
			)
			.replace(/\/\*[\s\S]*?\*\//g, '')
		const names = new Set(
			[...css.matchAll(/--utrecht-[A-Za-z0-9_-]+/g)].map((m) => m[0]),
		)

		for (const row of coverage) {
			if (row.bridge.applies === false) {
				continue
			}
			expect(row.bridge.total).toBe(names.size)
			expect(row.bridge.declared).toBeLessThanOrEqual(names.size)
		}
	})

	it('a set declaring part of the vocabulary counts those names and no others', () => {
		// A control on the counting itself: the bridge count must be exactly the
		// number of bridge names the set's own file declares, computed here from
		// the file rather than taken from the audit.
		const row = coverage.find((entry) => entry.id === 'vng')
		const css = fs
			.readFileSync(path.join(REPO_ROOT, 'css/tokens/vng.css'), 'utf8')
			.replace(/\/\*[\s\S]*?\*\//g, '')
		const declared = new Set(
			[...css.matchAll(/(--utrecht-[A-Za-z0-9_-]+)\s*:/g)].map((m) => m[1]),
		)
		const bridge = new Set(
			[
				...fs
					.readFileSync(
						path.join(
							REPO_ROOT,
							'css/systems/nldesign/utrecht-bridge.css',
						),
						'utf8',
					)
					.replace(/\/\*[\s\S]*?\*\//g, '')
					.matchAll(/--utrecht-[A-Za-z0-9_-]+/g),
			].map((m) => m[0]),
		)

		expect(row.bridge.declared).toBe(
			[...bridge].filter((name) => declared.has(name)).length,
		)
		expect(row.bridge.declared).toBeGreaterThan(0)
	})

	it('the Node and PHP system-family lists agree', () => {
		// Two runtimes classify a family as "the operating system supplies this",
		// and a family on one list but not the other would make the admin UI and
		// the CLI disagree about whether a set needs an upload.
		const php = fs.readFileSync(
			path.join(REPO_ROOT, 'lib/Service/TokenSetFontAuditService.php'),
			'utf8',
		)
		const block = /public const SYSTEM_FAMILIES = \[([\s\S]*?)\];/.exec(php)
		expect(
			block,
			'TokenSetFontAuditService must declare SYSTEM_FAMILIES',
		).not.toBeNull()

		const phpFamilies = [...block[1].matchAll(/'([^']+)'/g)].map((m) => m[1])

		expect(phpFamilies).toEqual(SYSTEM_FAMILIES)
	})

	it('the committed coverage report is what the audit renders now', () => {
		expect(
			fs.readFileSync(
				path.join(REPO_ROOT, 'docs/reference/token-set-coverage.md'),
				'utf8',
			),
		).toBe(renderCoverageMarkdown(coverage, allowlist))
	})

	it('the report records the baseline counts it was generated from', () => {
		const report = fs.readFileSync(
			path.join(REPO_ROOT, 'docs/reference/token-set-coverage.md'),
			'utf8',
		)

		expect(report).toContain('sets measured;')
		expect(report).toContain('--utrecht-* names')
		expect(report).toContain('point Nextcloud at no logo')
	})
})
