#!/usr/bin/env node
/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Two proofs about the action colour, both in a browser.
 *
 * 1. UNSET CHANGES NOTHING. The action colour (`--nldesign-color-action`, with
 *    `-hover` and `-text`) is optional. Every stylesheet that reads it is
 *    written `var(--nldesign-color-action…, <what the line was before>)`. So
 *    the stylesheets as they were are still in the tree: take each reader and
 *    keep only its fallback. This check does that, then loads every shipped
 *    token set twice in Chromium, once with the stylesheets as they are and
 *    once with the readers unwrapped, in the order CssInjectionService links
 *    them, in the light and in the dark theme. Every custom property on the
 *    root, the body and a child, the buttons and the other controls must be
 *    the same in both.
 *
 * 2. SET, IT REACHES THE MAIN BUTTON AND NOTHING ELSE. With an action colour
 *    declared, the filled primary buttons must wear it, and a checked
 *    checkbox, radio button and switch, a progress bar, the selected
 *    navigation entry, a counter badge, a focused input, a folder icon and
 *    `--color-primary-element` itself must stay what they were. A bundle that
 *    does not read the token must not change at all.
 *
 * The page holds no Nextcloud stylesheet, so for the other controls the check
 * reads the variables Nextcloud paints them with, on elements that carry
 * Nextcloud's own class names: thematiq scopes those variables per component.
 *
 * Usage:
 *   node tests/css/check-action-colour-unset.mjs            (two pages at a time)
 *   node tests/css/check-action-colour-unset.mjs --workers 1
 *
 * Exit codes:
 *   0  no set renders differently, and a declared action colour reaches the buttons only
 *   1  a set renders differently, a reader has no fallback, a button did not
 *      take the action colour, or something other than a button did
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

const STILL = 'style="transition:none!important"'
const BODY = `
<div id="content">
<button id="b1" ${STILL} class="button-vue button-vue--primary button-vue--vue-primary"><span class="button-vue__wrapper"><span class="button-vue__text">x</span></span></button>
<button id="b2" ${STILL} class="primary">x</button>
<input id="b3" ${STILL} class="primary" type="submit" value="x">
<a id="b4" ${STILL} class="primary">x</a>
<button id="b5" ${STILL} class="button-vue button-vue--secondary button-vue--vue-secondary">x</button>
<div class="modal-container"><button id="b6" ${STILL} class="button-vue button-vue--primary">confirm</button></div>
<form class="login-form"><button id="b7" ${STILL} type="submit">Log in</button></form>
<span class="checkbox-radio-switch checkbox-radio-switch--checked checkbox-radio-switch-checkbox"><span class="checkbox-radio-switch__content"><span id="p-checkbox" class="checkbox-radio-switch__icon"></span></span></span>
<span class="checkbox-radio-switch checkbox-radio-switch--checked checkbox-radio-switch-radio"><span class="checkbox-radio-switch__content"><span id="p-radio" class="checkbox-radio-switch__icon"></span></span></span>
<span class="checkbox-radio-switch checkbox-radio-switch--checked checkbox-radio-switch-switch"><span class="checkbox-radio-switch__content"><span id="p-switch" class="checkbox-radio-switch__icon"></span></span></span>
<progress id="p-progress" class="progress-bar" value="50" max="100"></progress>
<div id="app-navigation-vue"><ul><li id="p-navigation" class="app-navigation-entry active"><a class="app-navigation-entry-link">x</a><span id="p-counter" class="counter-bubble__counter">1</span></li></ul></div>
<span class="input-field"><input id="p-input" class="input-field__input" type="text"></span>
<span class="files-list__row-icon"><span id="p-folder" class="folder-icon"></span></span>
</div>`

/** The filled primary buttons, by probe id, and where each is drawn. */
export const BUTTONS = {
	b1: 'NcButton primary (.button-vue--primary and .button-vue--vue-primary)',
	b2: 'button.primary',
	b3: 'input.primary / input[type=submit].primary',
	b6: 'the confirm button of a dialog (.modal-container .button-vue--primary)',
}

/** Buttons only the NL Design System bundle paints from the main button tokens. */
export const NLDESIGN_BUTTONS = {
	b4: 'a.primary',
	b7: 'the login button (.login-form button[type=submit])',
}

/** What must stay with the primary, by probe id. */
export const OTHERS = {
	'p-checkbox': 'a checked checkbox',
	'p-radio': 'a selected radio button',
	'p-switch': 'a switch that is on',
	'p-progress': 'a progress bar',
	'p-navigation': 'the selected navigation entry',
	'p-counter': 'a counter badge',
	'p-input': 'a text input and its focus ring',
	'p-folder': 'a folder icon',
	b5: 'a secondary button',
}

/** The action colour the second proof declares, and its computed form. */
const ACTION = '#12ab34'
const ACTION_RGB = 'rgb(18, 171, 52)'

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
		for (const el of document.querySelectorAll('#content [id]')) {
			const cs = getComputedStyle(el)
			flat[el.id + ' background'] = cs.backgroundColor
			flat[el.id + ' border'] = cs.borderTopColor
			flat[el.id + ' color'] = cs.color
			flat[el.id + ' outline'] = cs.outlineColor
			// The variables Nextcloud paints this control with, as it sees them.
			for (const name of ['', '-hover', '-text', '-light', '-light-text']) {
				flat[el.id + ' --color-primary-element' + name] = cs
					.getPropertyValue('--color-primary-element' + name)
					.trim()
			}
		}
		flat['b1 label'] = getComputedStyle(
			document.querySelector('#b1 .button-vue__text'),
		).color
		return flat
	})
	for (const id of ['b1', 'b2', 'b6']) {
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
 * @return {Promise<{sets: number, values: number, differences: string[], notReached: string[], leaked: string[], reached: string[], unmoved: number, leftovers: string[]}>} The findings.
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
	const notReached = []
	const leaked = []
	const reached = new Set()
	let unmoved = 0
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

			// The second proof: a declared action colour reaches the buttons and nothing else.
			const system = set.design_system ?? 'nldesign'
			const reads = READING_SYSTEMS.includes(system)
			const declared = `:root{--nldesign-color-action:${ACTION};--nldesign-color-action-text:#fefefe}`
			const withAction = await measure(
				page,
				[...files.map((f) => sheet(f).now), declared],
				scheme,
			)
			const where = `${set.id} ${scheme}`
			// A generated dark variant restates the main button's colours as
			// literals, derived from the set's own file. A token declared for
			// this check alone cannot reach them, so in dark the buttons are
			// only expected to follow where the bundle reads the token direct.
			const buttons = {
				...BUTTONS,
				...(system === 'nldesign' ? NLDESIGN_BUTTONS : {}),
			}
			const followsHere =
				reads && (scheme === 'light' || system !== 'nldesign')
			for (const [id, name] of Object.entries(buttons)) {
				if (followsHere && withAction[id + ' background'] !== ACTION_RGB) {
					notReached.push(
						`${where}: ${name} is ${withAction[id + ' background']}, not the action colour`,
					)
				}
				if (followsHere) reached.add(`${system}: ${name}`)
			}
			for (const [key, value] of Object.entries(withAction)) {
				const id = key.split(' ')[0]
				const isButton = id in buttons
				const isAction =
					/nldesign-color-action|thematiq-action-hover|button-primary-action|login-button|dialog-confirm/.test(
						key,
					)
				if ((isButton && reads) || isAction) {
					continue
				}
				if (value !== now[key]) {
					leaked.push(
						`${where}: ${OTHERS[id] ?? key} changed: ${key} was "${now[key]}", is "${value}"`,
					)
				}
				unmoved++
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
		notReached,
		leaked,
		reached: [...reached].sort(),
		unmoved,
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
	for (const line of result.notReached.slice(0, 20)) {
		console.error(
			'[action-colour-unset] FAIL (a button did not follow): ' + line,
		)
	}
	for (const line of result.leaked.slice(0, 20)) {
		console.error(
			'[action-colour-unset] FAIL (not a button, and it changed): ' + line,
		)
	}
	const failed =
		result.leftovers.length
		+ result.differences.length
		+ result.notReached.length
		+ result.leaked.length
	console.log(
		`[action-colour-unset] Unset: ${result.sets} sets without an action colour, light and dark, ${result.values} values compared `
			+ `(${result.withActionColour.length} set(s) declare one and are left out: ${result.withActionColour.join(', ') || 'none'}): `
			+ `${result.differences.length} difference(s), ${result.leftovers.length} reader(s) without a fallback.`,
	)
	console.log(
		`[action-colour-unset] Set: ${result.reached.length} button kind(s) took the action colour, ${result.notReached.length} did not; `
			+ `${result.unmoved} other values checked, ${result.leaked.length} changed.`,
	)
	for (const line of result.reached) {
		console.log('[action-colour-unset]   follows: ' + line)
	}
	process.exit(failed === 0 ? 0 : 1)
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
	main()
}
