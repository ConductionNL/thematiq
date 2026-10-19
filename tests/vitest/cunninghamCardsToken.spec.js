/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A set that follows Cunningham's cards says so in its own tokens
 * (`--nldesign-component-dashboard-card-header-border-style: none`), and the
 * nldesign layer reads that token for a dashboard card's header rule; a set
 * that declares nothing keeps the card's own rule.
 *
 * @spec openspec/changes/cunningham-cards-token/specs/cunningham-cards-token/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')
const TOKEN = '--nldesign-component-dashboard-card-header-border-style'

describe('the Cunningham card marker', () => {
	it('is read by the nldesign layer, with the card\'s own solid rule as the fallback', () => {
		const css = read('css/systems/nldesign/element-overrides.css')
		const selector = '.cn-dashboard-page .cn-widget-wrapper:not(.cn-widget-wrapper--nc-dashboard):not(.cn-widget-wrapper--chromeless) > .cn-widget-wrapper__header'
		const at = css.indexOf(selector + ' {')
		expect(at).toBeGreaterThan(-1)
		expect(css.slice(at, css.indexOf('}', at))).toContain(`border-bottom-style: var(${TOKEN}, solid);`)
	})

	it.each(['zuiddrecht', 'vaartveld', 'wilgenboom', 'esdoornveen', 'warmtepompacademie'])('is declared by %s, which follows Cunningham', (set) => {
		expect(read(`css/tokens/${set}.css`)).toMatch(new RegExp(`${TOKEN}:\\s*none;`))
	})
})
