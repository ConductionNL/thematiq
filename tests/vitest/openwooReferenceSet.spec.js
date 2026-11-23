/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The OpenWOO reference set is a playground export the vocabulary audit rates
 * complete, and the committed file is exactly what the playground exports.
 *
 * @spec openspec/specs/token-sets/spec.md
 * @spec openspec/specs/token-sets/spec.md
 */

import { describe, it, expect } from 'vitest'
import * as fs from 'fs'
import * as path from 'path'
import { auditAll, REPO_ROOT } from '../../scripts/audit-token-sets.mjs'
import { declaredTokens, exportSet } from '../../scripts/export-playground-set.mjs'
import { compare } from '../../scripts/diff-converter-reference.mjs'

const OVERRIDES = path.join(REPO_ROOT, 'scripts/playground/openwoo.overrides.json')
const REFERENCE = path.join(REPO_ROOT, 'css/tokens/openwoo.css')

describe('OpenWOO reference set', () => {
	it('is byte-identical to a fresh playground export', () => {
		const result = exportSet(OVERRIDES)
		expect(result.unexpressed).toEqual([])
		expect(result.overruled).toEqual([])
		expect(fs.readFileSync(REFERENCE, 'utf8')).toBe(result.css)
	})

	it('is rated complete by the vocabulary audit', () => {
		const row = auditAll(REPO_ROOT).find((entry) => entry.id === 'openwoo')
		expect(row).toBeDefined()
		expect(row.auditable).toBe(true)
		expect(row.missingRequired).toEqual([])
		expect(row.foreignNldesignNames).toEqual([])
		expect(row.complete).toBe(true)
	})

	it('keeps only the base set tokens that carry no brand value', () => {
		const spec = JSON.parse(fs.readFileSync(OVERRIDES, 'utf8'))
		const kept = Object.keys(declaredTokens(exportSet(OVERRIDES).css))
			.filter((name) => spec.overrides[name] === undefined)
		expect(kept).toEqual(['--nldesign-animation-quick', '--nldesign-animation-slow', '--nldesign-border-radius-pill', '--nldesign-border-radius-rounded', '--nldesign-color-text-light'])
	})
})

describe('compare', () => {
	it('names each token same, different, or one side only', () => {
		const rows = compare({ '--a': '#FFF', '--b': '1px', '--c': 'x' }, { '--a': '#fff', '--b': '2px', '--d': 'y' })
		expect(rows.map((row) => row.verdict)).toEqual(['same', 'different', 'reference only', 'converter only'])
	})
})
