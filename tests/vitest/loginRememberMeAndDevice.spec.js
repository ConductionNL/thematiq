/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The workplace login keeps Nextcloud's Remember me and Log in with a device,
 * laid out as werkplek/NcLogin draws them: the checkbox on the fields' left
 * edge in regular 14px words, Forgot your password? before the device link,
 * and the device link with a phone icon in a strip of its own.
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const css = fs
	.readFileSync(
		path.resolve(__dirname, '..', '..', 'css/workplace-layout.css'),
		'utf8',
	)
	.replace(/\/\*[\s\S]*?\*\//g, '')

/** The declarations of every block whose selector ends with `tail`. */
function block(tail) {
	const re = /([^{}]+)\{([^{}]*)\}/g
	let m
	const out = []
	while ((m = re.exec(css)) !== null) {
		const selectors = m[1].split(',').map((s) => s.replace(/\s+/g, ' ').trim())
		if (
			selectors.some(
				(s) => s.startsWith('body#body-login') && s.endsWith(tail),
			)
		) {
			out.push(m[2])
		}
	}
	return out.join('\n')
}

describe('Remember me and Log in with a device on the login card', () => {
	it("puts the checkbox box on the fields' edge and its words in 14px regular", () => {
		expect(block('.checkbox-radio-switch .checkbox-content')).toMatch(
			/margin-inline-start:\s*-3px/,
		)
		expect(block('.checkbox-radio-switch .checkbox-content__wrapper')).toMatch(
			/font-size:\s*14px/,
		)
		expect(block('.checkbox-radio-switch .checkbox-content__wrapper')).toMatch(
			/font-weight:\s*400/,
		)
	})

	it('shows Forgot your password? before the device link', () => {
		expect(block('> #lost-password')).toMatch(/order:\s*1/)
		expect(block('> .button-vue--vue-tertiary:not(#lost-password)')).toMatch(
			/order:\s*2/,
		)
	})

	it('gives the device link a strip of its own with a phone icon', () => {
		expect(block('> .button-vue--vue-tertiary:not(#lost-password)')).toMatch(
			/border-top:\s*1px solid var\(--color-border\)/,
		)
		expect(
			block(
				'> .button-vue--vue-tertiary:not(#lost-password) .button-vue__wrapper::before',
			),
		).toMatch(/mask:\s*url\("data:image\/svg\+xml/)
	})
})
