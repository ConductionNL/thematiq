/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The Cunningham layer (systems/lasuite, loaded by every set on the lasuite or
 * cunningham design system) draws no rule under a dashboard card's title.
 *
 * @spec openspec/changes/cunningham-dashboard-card-header-rule/specs/cunningham-dashboard-card-header-rule/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const css = read('css/systems/lasuite/element-overrides.css').replace(/\/\*[\s\S]*?\*\//g, '')
const systems = JSON.parse(read('design-systems.json'))

describe('the Cunningham layer: dashboard card titles', () => {
	it('drops the rule under a dashboard card title, leaving the Nextcloud chrome and chromeless cards alone', () => {
		const selector = '.cn-dashboard-page .cn-widget-wrapper:not(.cn-widget-wrapper--nc-dashboard):not(.cn-widget-wrapper--chromeless) > .cn-widget-wrapper__header'
		const at = css.indexOf(selector + ' {')
		expect(at).toBeGreaterThan(-1)
		expect(css.slice(at, css.indexOf('}', at))).toContain('border-bottom: none;')
	})

	it('reaches both design systems built on Cunningham', () => {
		for (const id of ['lasuite', 'cunningham']) {
			expect(systems.find((s) => s.id === id).stylesheets).toContain('systems/lasuite/element-overrides')
		}
	})
})
