/**
 * @vitest-environment jsdom
 *
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The set's 38px quick-filter pills are the list views' (DqZaken). Inside a
 * record card the boards draw the contact thread's channel chips at 36px,
 * radius 18 (DqZaakContact), which nextcloud-vue's board look sets; the set's
 * id-level size rule leaves those chips to it.
 *
 * @spec openspec/changes/zuiddrecht-workplace-theme/specs/zuiddrecht-token-set/spec.md
 */

import fs from 'node:fs'
import path from 'node:path'
import postcss from 'postcss'
import { beforeAll, describe, expect, it } from 'vitest'

const css = fs.readFileSync(path.resolve(__dirname, '../../css/token-overrides/zuiddrecht.css'), 'utf8')

/**
 * The selectors of the set that size a quick-filter chip.
 *
 * @return {string[]} The selectors.
 */
function sizingSelectors() {
	const out = []
	postcss.parse(css).walkRules((rule) => {
		const sizes = rule.nodes.some((n) => n.type === 'decl' && /^(min-height|height|border-radius)$/.test(n.prop))
		if (sizes) {
			out.push(...rule.selectors.filter((s) => s.includes('.cn-quick-filter-bar__tab')))
		}
	})
	return out
}

const matches = (el) => sizingSelectors().filter((s) => el.matches(s))

beforeAll(() => {
	document.body.innerHTML = `
		<div id="app-content-vue">
			<div class="cn-quick-filter-bar"><button id="list-chip" class="cn-quick-filter-bar__tab">Alles</button></div>
			<div class="cn-conversation-thread"><div class="cn-quick-filter-bar">
				<button id="thread-chip" class="cn-quick-filter-bar__tab">Telefoon</button>
			</div></div>
		</div>`
})

describe('zuiddrecht quick-filter chip size', () => {
	it('sizes a list view\'s chip', () => {
		expect(matches(document.getElementById('list-chip')).length).toBeGreaterThan(0)
	})

	it('leaves a conversation thread\'s chip to the library', () => {
		expect(matches(document.getElementById('thread-chip'))).toEqual([])
	})
})
