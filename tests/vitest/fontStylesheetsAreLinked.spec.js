/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A stylesheet that declares a typeface is one something links.
 *
 * `css/fonts-conduction.css` declared Figtree and IBM Plex Mono and no design
 * system listed it, so nothing it declared ever loaded, while two comments and
 * a capability's evidence line cited it as the place those faces were served.
 * Both faces are declared by the stylesheet the nldesign system does link.
 *
 * @spec openspec/specs/custom-fonts/spec.md
 */
import { describe, expect, it } from 'vitest'
import fs from 'node:fs'
import path from 'node:path'

const ROOT = path.resolve(__dirname, '..', '..')
const read = (rel) => fs.readFileSync(path.join(ROOT, rel), 'utf8')
const stripComments = (css) => css.replace(/\/\*[\s\S]*?\*\//g, '')

/** The layer portaliq links for a public site; build-fonts.js emits it. */
const PUBLIC_SITE_LAYER = 'css/fonts.css'
/** Linked by CssInjectionService only when the admin turns Marianne on. */
const OPT_IN_LAYER = 'css/systems/lasuite/marianne.css'

/**
 * Every stylesheet under css/ that declares an `@font-face`, token sets aside.
 *
 * @param {string} dir Directory relative to the app root.
 * @return {string[]} Paths relative to the app root.
 */
const fontStylesheets = (dir = 'css') => {
	const found = []
	for (const entry of fs.readdirSync(path.join(ROOT, dir), {
		withFileTypes: true,
	})) {
		const rel = `${dir}/${entry.name}`
		if (entry.isDirectory() === true) {
			if (rel !== 'css/tokens') {
				found.push(...fontStylesheets(rel))
			}
			continue
		}
		if (
			rel.endsWith('.css') === true
			&& /@font-face/.test(stripComments(read(rel))) === true
		) {
			found.push(rel)
		}
	}
	return found.sort()
}

const linked = new Set(
	JSON.parse(read('design-systems.json')).flatMap((system) =>
		(system.stylesheets ?? []).map((sheet) => `css/${sheet}.css`),
	),
)

describe('a stylesheet that declares a typeface is linked', () => {
	it('finds the stylesheets the design systems link (the control)', () => {
		const sheets = fontStylesheets()
		expect(sheets).toContain('css/systems/nldesign/fonts.css')
		expect(sheets).toContain('css/systems/lasuite/fonts.css')
		expect(linked.has('css/systems/nldesign/fonts.css')).toBe(true)
	})

	it('has no font stylesheet outside a design system list and the public site layer', () => {
		const orphans = fontStylesheets().filter(
			(sheet) =>
				linked.has(sheet) === false
				&& sheet !== PUBLIC_SITE_LAYER
				&& sheet !== OPT_IN_LAYER,
		)
		expect(orphans).toEqual([])
	})

	it('links the opt-in Marianne layer from the injection service (the control)', () => {
		expect(read('lib/Service/CssInjectionService.php')).toContain(
			"'file' => 'systems/lasuite/marianne'",
		)
	})

	it('still serves the faces the retired stylesheet named', () => {
		const css = stripComments(read('css/systems/nldesign/fonts.css'))
		for (const [family, file] of [
			['Figtree', 'figtree-latin-variable.woff2'],
			['IBM Plex Mono', 'ibm-plex-mono-latin-400.woff2'],
		]) {
			expect(css, family).toContain(`font-family: '${family}'`)
			expect(css, file).toContain(`fonts/${file}`)
			expect(
				fs.existsSync(path.join(ROOT, 'css/systems/nldesign/fonts', file)),
				file,
			).toBe(true)
		}
	})

	it('serves Inter from the stylesheet the La Suite systems link', () => {
		// css/tokens/frankendesk.css names Inter; its comment used to cite the
		// retired stylesheet, which never declared it.
		expect(linked.has('css/systems/lasuite/fonts.css')).toBe(true)
		expect(stripComments(read('css/systems/lasuite/fonts.css'))).toMatch(
			/font-family:\s*'?Inter'?/,
		)
	})

	it('names the retired stylesheet nowhere a reader would follow it', () => {
		const citing = [
			'css/tokens/frankendesk.css',
			'lib/Service/TokenSetFontAuditService.php',
			'openspec/parity/capabilities.json',
		].filter((rel) => read(rel).includes('fonts-conduction.css'))
		expect(citing).toEqual([])
	})
})
