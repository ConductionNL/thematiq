/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * No signed-in page pays more layout CSS than Mail paid before the light
 * layout became one shell for every app: the stylesheets LayoutStylesheets
 * gives a page under the Zuiddrecht set, summed raw and gzipped at level 9,
 * stay under what Mail's page weighed on development at ae4a4e0d3.
 *
 * The list follows LayoutStylesheets::forOptions() and forApp() for the set's
 * own layout block in token-sets.json, so a new option the set switches on is
 * counted too. The login page's sheet (workplace-login) is not loaded on a
 * user's page and is not counted.
 *
 * @spec openspec/changes/same-shell-every-page/specs/workplace-layout/spec.md#requirement-no-page-pays-more-layout-css-than-mail-paid-req-tqss-007
 */

import fs from 'node:fs'
import path from 'node:path'
import zlib from 'node:zlib'
import { describe, expect, it } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const RAW_CAP = 94592
const GZIP_CAP = 24669

const zuiddrecht = (() => {
	const sets = JSON.parse(
		fs.readFileSync(path.join(ROOT, 'token-sets.json'), 'utf8'),
	)
	const list = Array.isArray(sets) ? sets : Object.values(sets)
	return list.find((set) => set && set.id === 'zuiddrecht')
})()

/**
 * The stylesheets of a user's page, as LayoutStylesheets orders them.
 *
 * @param {object} layout The set's layout block.
 * @param {string|null} app The open app.
 * @return {Array<string>} The files under css/, without the extension.
 */
function sheetsFor(layout, app) {
	const files = []
	const light = layout.workplace_layout === 'light'
	if (light) {
		files.push('workplace-layout')
		if (layout.login_watermark === false) {
			files.push('login-watermark-off')
		}
		if (layout.header_style === 'workplace') {
			files.push('header-workplace')
		}
	}
	if (layout.brand_stripe === true) {
		files.push('brand-stripe')
		if (layout.brand_stripe_placement === 'header') {
			files.push('brand-stripe-header-only')
		} else if (layout.brand_stripe_placement === 'login') {
			files.push('brand-stripe-login-only')
		}
	}
	if (layout.navigation_width !== undefined && layout.navigation_width !== null) {
		files.push('navigation-width')
	}
	if (layout.navigation_active_style === 'soft') {
		files.push('navigation-active-soft')
	}
	if (light && ['mail', 'calendar', 'deck'].includes(app)) {
		files.push('workplace-apps/' + app)
	}
	return files
}

describe('the layout CSS a page pays under Zuiddrecht', () => {
	it('reads the set with the light layout and the workplace bar', () => {
		expect(zuiddrecht.layout.workplace_layout).toBe('light')
		expect(zuiddrecht.layout.header_style).toBe('workplace')
		expect(sheetsFor(zuiddrecht.layout, 'deck')).toEqual([
			'workplace-layout',
			'header-workplace',
			'brand-stripe',
			'brand-stripe-login-only',
			'navigation-width',
			'navigation-active-soft',
			'workplace-apps/deck',
		])
	})

	it.each([
		['Files', 'files'],
		['Mail', 'mail'],
		['Calendar', 'calendar'],
		['Deck', 'deck'],
		['a page without an app', null],
	])("%s stays under Mail's old weight, raw and gzipped", (label, app) => {
		let raw = 0
		let gzipped = 0
		for (const file of sheetsFor(zuiddrecht.layout, app)) {
			const content = fs.readFileSync(path.join(ROOT, 'css', file + '.css'))
			raw += content.length
			gzipped += zlib.gzipSync(content, { level: 9 }).length
		}
		expect(raw, label + ' raw bytes').toBeLessThanOrEqual(RAW_CAP)
		expect(gzipped, label + ' gzipped bytes').toBeLessThanOrEqual(GZIP_CAP)
	})
})
