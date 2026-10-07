/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * The portal reads `--nldesign-*` names of its own (portaliq
 * css/site-theme.css). TokenSetVocabularyTest only lets a set declare a
 * name that something reads, and it looks inside this repository. A name
 * only the portal read looked unread: #1105 took Zuiddrecht's two menu-mark
 * tokens out for that reason, and the blue bar under the menu item on screen
 * disappeared from the live site. This file holds that every name the portal
 * reads is read here too (css/public-bridge.css carries a role for it), so a
 * set may declare it.
 *
 * The fixture is measured from the portal; when the portal reads a new name,
 * add it there and give it a role in the bridge.
 */

import fs from 'fs'
import path from 'path'
import { describe, it, expect } from 'vitest'

const ROOT = path.resolve(__dirname, '../..')
const FIXTURE = JSON.parse(
	fs.readFileSync(
		path.join(ROOT, 'tests/vitest/fixtures/portal-site-nldesign-readers.json'),
		'utf8',
	),
)

/**
 * Every CSS file under css/ that is not a token set: the layers whose reads
 * make up the vocabulary.
 *
 * @return {string[]} The file contents, comments stripped.
 */
function readerLayers() {
	const out = []
	const walk = (dir) => {
		for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
			const full = path.join(dir, entry.name)
			if (entry.isDirectory()) {
				if (full !== path.join(ROOT, 'css/tokens')) {
					walk(full)
				}
			} else if (entry.name.endsWith('.css')) {
				out.push(
					fs.readFileSync(full, 'utf8').replace(/\/\*[\s\S]*?\*\//g, ''),
				)
			}
		}
	}
	walk(path.join(ROOT, 'css'))
	return out
}

describe('the names the portal reads', () => {
	const css = readerLayers().join('\n')

	it('is a real list, measured from the portal', () => {
		expect(FIXTURE.names.length).toBeGreaterThan(20)
		expect(FIXTURE.names).toContain('--nldesign-website-nav-current-in-line')
		expect(FIXTURE.names).toContain('--nldesign-website-nav-current-color')
	})

	it.each(FIXTURE.names)('%s is known to this repository too', (name) => {
		// The vocabulary counts a name a layer reads or gives a default.
		const read = new RegExp(`${name.replace(/-/g, '\\-')}(?![a-z0-9-])`)
		expect(read.test(css)).toBe(true)
	})
})

describe("the website tokens are Zuiddrecht's alone (the control)", () => {
	const NEW = [
		'--nldesign-website-page-max-width',
		'--nldesign-website-band-max-width',
		'--nldesign-website-page-gutter',
		'--nldesign-website-header-max-width',
		'--nldesign-website-header-gutter',
		'--nldesign-website-content-font-size',
		'--nldesign-website-nav-current-in-line',
		'--nldesign-website-nav-current-color',
		'--nldesign-website-button-font-weight',
		'--nldesign-website-color-text-muted',
		'--nldesign-website-placeholder-background-color',
	]
	const sets = fs
		.readdirSync(path.join(ROOT, 'css/tokens'))
		.filter((f) => f.endsWith('.css') && f !== 'zuiddrecht.css')

	it('walks the shipped sets', () => {
		expect(sets.length).toBeGreaterThan(50)
	})

	it.each(sets)('%s declares none of them', (file) => {
		const text = fs.readFileSync(path.join(ROOT, 'css/tokens', file), 'utf8')
		expect(NEW.filter((name) => text.includes(name + ':'))).toEqual([])
	})

	it('the site grey reaches AA on white, the site surface and the photo ground', () => {
		const lum = (hex) => {
			const c = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255)
			const l = c.map((v) =>
				v <= 0.04045 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4,
			)
			return 0.2126 * l[0] + 0.7152 * l[1] + 0.0722 * l[2]
		}
		const ratio = (a, b) => {
			const [x, y] = [lum(a), lum(b)].sort((p, q) => q - p)
			return (x + 0.05) / (y + 0.05)
		}
		for (const ground of ['#FFFFFF', '#F4F6F9', '#D9E3EF']) {
			expect(ratio('#4A4A4A', ground), ground).toBeGreaterThanOrEqual(4.5)
		}
	})
})
