#!/usr/bin/env node
/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * A token set without an action colour renders as it did before the action
 * colour existed. This check proves it in a browser.
 *
 * The action colour (`--nldesign-color-action`, with `-hover` and `-text`) is
 * optional. Every stylesheet that reads it is written
 * `var(--nldesign-color-action…, <what the line was before>)`. So the
 * stylesheets as they were are still in the tree: take each reader and keep
 * only its fallback. This check does that, then loads every shipped token set
 * twice in Chromium, once with the stylesheets as they are and once with the
 * readers unwrapped, in the order CssInjectionService links them, in the
 * light and in the dark theme. Every custom property on the root, the body and
 * a child, and the colours of five buttons at rest and on hover, must be the
 * same in both.
 *
 * A check that cannot see a difference proves nothing, so there is a control:
 * with an action colour declared, the two must differ for the bundles that
 * read it.
 *
 * Usage:
 *   node tests/css/check-action-colour-unset.mjs            (two pages at a time)
 *   node tests/css/check-action-colour-unset.mjs --workers 1
 *
 * Exit codes:
 *   0  no set renders differently, and the control sees the action colour
 *   1  a set renders differently, a reader has no fallback, or the control is blind
 *   2  Chromium could not be started (nothing was checked)
 *
 * @spec openspec/changes/action-colour-token/specs/action-colour/spec.md#requirement-a-set-without-an-action-colour-renders-as-before
 */

import { existsSync, readFileSync, readdirSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')

/** The names a reader may have in front of its fallback. */
const OPTIONAL = [
	'--nldesign-color-action-hover',
	'--nldesign-color-action-text',
	'--nldesign-color-action',
	'--thematiq-action-hover',
]

/** The internal hover step, the one property that is new. Empty without an action colour. */
const STEP = '--thematiq-action-hover'

/** The bundles that read the action colour. */
const READING_SYSTEMS = ['nldesign', 'lasuite', 'cunningham']

const read = (rel) => readFileSync(join(ROOT, rel), 'utf8')
const exists = (rel) => existsSync(join(ROOT, rel))

/**
 * A stylesheet as it was before the action colour: each reader replaced by
 * its fallback, and the hover step's own declaration removed.
 *
 * @param {string} css The stylesheet.
 * @return {string} The stylesheet without action readers.
 */
export function unwrap(css) {
	let out = css.replace(/\/\*[\s\S]*?\*\//g, '')
	// The step's declaration: `--thematiq-action-hover: var(…);`
	out = out.replace(
		new RegExp(STEP + '\\s*:\\s*var\\([\\s\\S]*?\\)\\s*\\)\\s*;', 'g'),
		'',
	)
	const opener = new RegExp('var\\(\\s*(?:' + OPTIONAL.join('|') + ')\\s*,')
	for (let guard = 0; guard < 500; guard++) {
		const match = opener.exec(out)
		if (match === null) {
			break
		}
		// Walk to the parenthesis that closes this var().
		let depth = 1
		let end = match.index + match[0].length
		for (; end < out.length && depth > 0; end++) {
			if (out[end] === '(') depth++
			if (out[end] === ')') depth--
		}
		const fallback = out.slice(match.index + match[0].length, end - 1).trim()
		out = out.slice(0, match.index) + fallback + out.slice(end)
	}
	return out
}

/**
 * The stylesheets a set puts on a page, in cascade order.
 *
 * @param {object} set The token-sets.json entry (or `{id}`).
 * @param {object} systems Design systems by id.
 * @return {string[]} Paths under css/, without extension.
 */
export function stylesheetsOf(set, systems) {
	const system = systems[set.design_system ?? 'nldesign']
	const files = [...(system.stylesheets ?? [])]
	if (system.id === 'none') {
		return files
	}
	files.push('tokens/' + set.id)
	if (exists(`css/token-overrides/${set.id}.css`)) {
		files.push('token-overrides/' + set.id)
	}
	if (exists(`css/tokens/dark/${set.id}.css`)) {
		files.push('tokens/dark/' + set.id)
	}
	files.push('icon-contrast', 'error-contrast', 'theme-scopes', 'component-scopes')
	return files
}

/** Every shipped set: the manifest's, and any token file the manifest does not list. */
export function shippedSets() {
	const sets = JSON.parse(read('token-sets.json'))
	for (const file of readdirSync(join(ROOT, 'css/tokens'))) {
		const id = file.replace(/\.css$/, '')
		if (file.endsWith('.css') && !sets.some((s) => s.id === id)) {
			sets.push({ id })
		}
	}
	return sets
}

const BODY = `
<div id="content">
<button id="b1" style="transition:none!important" class="button-vue button-vue--primary button-vue--vue-primary"><span class="button-vue__wrapper"><span class="button-vue__text">x</span></span></button>
<button id="b2" style="transition:none!important" class="primary">x</button>
<input id="b3" style="transition:none!important" class="primary" type="submit" value="x">
<a id="b4" style="transition:none!important" class="primary">x</a>
<button id="b5" style="transition:none!important" class="button-vue button-vue--secondary button-vue--vue-secondary">x</button>
</div>`

/**
 * What a page computes for one set of stylesheets.
 *
 * @param {import('playwright-core').Page} page The page.
 * @param {string[]} sheets The stylesheet texts, in order.
 * @param {string} scheme `light` or `dark`.
 * @return {Promise<Record<string, string>>} Flat name => value.
 */
async function measure(page, sheets, scheme) {
	const attr =
		scheme === 'dark'
			? 'data-theme-dark data-themes="dark"'
			: 'data-theme-light data-themes="light"'
	const styles = sheets.map((css) => `<style>${css}</style>`).join('')
	await page.setContent(
		`<!doctype html><html><head>${styles}</head><body ${attr}>${BODY}</body></html>`,
	)
	await page.mouse.move(0, 0)
	const rest = await page.evaluate(() => {
		const flat = {}
		const dump = (label, el) => {
			const cs = getComputedStyle(el)
			for (const p of cs) {
				if (p.startsWith('--')) {
					flat[label + ' ' + p] = cs.getPropertyValue(p).trim()
				}
			}
		}
		dump('root', document.documentElement)
		dump('body', document.body)
		dump('content', document.getElementById('content'))
		for (const id of ['b1', 'b2', 'b3', 'b4', 'b5']) {
			const cs = getComputedStyle(document.getElementById(id))
			flat[id + ' background'] = cs.backgroundColor
			flat[id + ' border'] = cs.borderTopColor
			flat[id + ' color'] = cs.color
		}
		flat['b1 label'] = getComputedStyle(
			document.querySelector('#b1 .button-vue__text'),
		).color
		return flat
	})
	for (const id of ['b1', 'b2']) {
		await page.hover('#' + id)
		const hover = await page.evaluate((target) => {
			const cs = getComputedStyle(document.getElementById(target))
			return [cs.backgroundColor, cs.borderTopColor, cs.color]
		}, id)
		rest[id + ' hover background'] = hover[0]
		rest[id + ' hover border'] = hover[1]
		rest[id + ' hover color'] = hover[2]
	}
	return rest
}

/**
 * Compare every shipped set with and without its action readers.
 *
 * @param {import('playwright-core').Browser} browser A Chromium.
 * @param {{workers?: number}} [options] How many pages work at a time.
 * @return {Promise<{sets: number, values: number, differences: string[], blindControls: string[], leftovers: string[]}>} The findings.
 */
export async function compare(browser, options = {}) {
	const workers = Math.max(1, options.workers ?? 2)
	const systems = Object.fromEntries(
		JSON.parse(read('design-systems.json')).map((s) => [s.id, s]),
	)
	const sets = shippedSets()

	const cache = new Map()
	const sheet = (file) => {
		if (!cache.has(file)) {
			const css = read(`css/${file}.css`)
			cache.set(file, { now: css, before: unwrap(css) })
		}
		return cache.get(file)
	}

	const jobs = []
	for (const set of sets) {
		for (const scheme of ['light', 'dark']) {
			jobs.push({ set, scheme })
		}
	}

	const differences = []
	const withActionColour = new Set()
	const blindControls = []
	const leftovers = new Set()
	let values = 0

	const work = async () => {
		const page = await browser.newPage()
		for (;;) {
			const job = jobs.shift()
			if (job === undefined) {
				break
			}
			const { set, scheme } = job
			const files = stylesheetsOf(set, systems)
			// A set that declares an action colour is meant to render
			// differently, so it is not part of this proof. It is counted,
			// not skipped in silence.
			if (
				/--nldesign-color-action[\w-]*\s*:/.test(
					read(`css/tokens/${set.id}.css`),
				)
			) {
				withActionColour.add(set.id)
				continue
			}
			for (const file of files) {
				if (
					/nldesign-color-action|thematiq-action-hover/.test(
						sheet(file).before,
					)
				) {
					leftovers.add(`css/${file}.css`)
				}
			}
			const now = await measure(
				page,
				files.map((f) => sheet(f).now),
				scheme,
			)
			const before = await measure(
				page,
				files.map((f) => sheet(f).before),
				scheme,
			)

			for (const key of new Set([
				...Object.keys(now),
				...Object.keys(before),
			])) {
				if (key.endsWith(' ' + STEP)) {
					if (now[key] !== '') {
						differences.push(
							`${set.id} ${scheme}: ${key} is "${now[key]}", expected empty`,
						)
					}
					continue
				}
				values++
				if (now[key] !== before[key]) {
					differences.push(
						`${set.id} ${scheme}: ${key} was "${before[key]}", is "${now[key]}"`,
					)
				}
			}

			// The control: with an action colour, the bundles that read it must show it.
			if (
				scheme === 'light'
				&& READING_SYSTEMS.includes(set.design_system ?? 'nldesign')
			) {
				const withAction = await measure(
					page,
					[
						...files.map((f) => sheet(f).now),
						':root{--nldesign-color-action:#12ab34}',
					],
					scheme,
				)
				if (
					withAction['body --color-primary-element'] !== '#12ab34'
					|| withAction['b1 background'] !== 'rgb(18, 171, 52)'
				) {
					blindControls.push(
						`${set.id}: with an action colour the element is "${withAction['body --color-primary-element']}" and the main button "${withAction['b1 background']}"`,
					)
				}
			}
		}
		await page.close()
	}

	await Promise.all(Array.from({ length: workers }, work))

	return {
		sets: sets.length - withActionColour.size,
		withActionColour: [...withActionColour].sort(),
		values,
		differences,
		blindControls,
		leftovers: [...leftovers].sort(),
	}
}

async function main() {
	const flag = process.argv.indexOf('--workers')
	const workers = flag === -1 ? 2 : Number(process.argv[flag + 1])

	let browser
	try {
		const { chromium } = await import('playwright-core')
		browser = await chromium.launch({ headless: true })
	} catch (error) {
		console.error(
			'[action-colour-unset] Chromium could not be started, so NOTHING was checked: '
				+ error.message.split('\n')[0],
		)
		console.error(
			'[action-colour-unset] Run `npx playwright install chromium` first.',
		)
		process.exit(2)
	}

	const result = await compare(browser, { workers })
	await browser.close()

	for (const file of result.leftovers) {
		console.error(
			`[action-colour-unset] FAIL: ${file} reads the action colour without a fallback.`,
		)
	}
	for (const line of result.differences.slice(0, 40)) {
		console.error('[action-colour-unset] FAIL: ' + line)
	}
	for (const line of result.blindControls.slice(0, 10)) {
		console.error('[action-colour-unset] FAIL (control): ' + line)
	}
	const failed =
		result.leftovers.length
		+ result.differences.length
		+ result.blindControls.length
	console.log(
		`[action-colour-unset] ${result.sets} sets without an action colour, light and dark, ${result.values} values compared `
			+ `(${result.withActionColour.length} set(s) declare one and are left out: ${result.withActionColour.join(', ') || 'none'}): `
			+ `${result.differences.length} difference(s), ${result.blindControls.length} blind control(s), `
			+ `${result.leftovers.length} reader(s) without a fallback.`,
	)
	process.exit(failed === 0 ? 0 : 1)
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
	main()
}
