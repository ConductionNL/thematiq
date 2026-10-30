/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Under nldesign the app shell (NcContent paints `--color-main-background-blur`)
 * is the solid main background, not Nextcloud's translucent frosted surface.
 * The login page keeps its frosted card.
 *
 * @spec openspec/specs/nldesign-solid-app-shell/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const css = fs.readFileSync(path.join(ROOT, 'css/systems/nldesign/theme.css'), 'utf8').replace(/\/\*[\s\S]*?\*\//g, '')

/** The declaration blocks whose selector list is exactly `selector`. */
function blocks(selector) {
	const out = []
	const re = /([^{}]+)\{([^{}]*)\}/g
	let m
	while ((m = re.exec(css)) !== null) {
		if (m[1].trim().replace(/\s+/g, ' ') === selector) {
			out.push(m[2])
		}
	}
	return out.join('\n')
}

describe('the nldesign app shell', () => {
	it('maps the blurred surface to the solid main background outside the login page', () => {
		expect(blocks('body:not(#body-login)')).toMatch(
			/--color-main-background-blur:\s*var\(--color-main-background\)\s*!important;/,
		)
	})

	it('leaves the login page frosted', () => {
		expect(blocks('body[data-themes], body')).not.toContain('--color-main-background-blur')
		expect(blocks('#body-login')).not.toContain('--color-main-background-blur')
	})
})
