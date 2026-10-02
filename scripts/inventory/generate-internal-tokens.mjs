#!/usr/bin/env node

/**
 * Write the internal token map and its reference page from the inventory and the status file.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Every settable `component`, `slot` and `conduction` entry becomes one token in
 * scripts/mapping/internal-tokens.json, which the server reads at render time
 * (TokenRegistry::getInternalTokens(), InternalScopesService). Each token records
 * the variable it sets and how it reaches it:
 *
 * - `body`: nothing declares the variable, components only read it with a
 *   fallback, so one declaration on body reaches every reader.
 * - `selectors`: a component declares the variable on its own element, so the
 *   value has to be declared on that element too. `:root` is written as `body`,
 *   because a dark value is declared on body and must be visible where the rule
 *   applies; nothing Nextcloud renders sits outside body.
 *
 * Run with --check to compare instead of writing; the inventory guard in
 * tests/vitest/nextcloudVariableInventory.spec.js does that on every run.
 *
 * @spec openspec/changes/internal-variable-tokens/specs/component-tokens/spec.md
 */

import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { JSDOM } from 'jsdom'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
export const MAP = join(ROOT, 'scripts/mapping/internal-tokens.json')
export const DOC = join(ROOT, 'docs/reference/internal-tokens.md')
export const INTERNAL_CLASSES = ['component', 'slot', 'conduction']

const COLOUR = /#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?|color-mix)\(|var\(--color-/i

/** A colour when Nextcloud's own value is one, so the editor can show a swatch. */
function typeOf(name, values) {
	if (values.some((v) => COLOUR.test(v))) return 'color'
	return /(?:^|-)(?:color|background|bg|fg)(?:-|$)/.test(name.slice(2)) ? 'color' : 'text'
}

/**
 * One selector list from a bundle, as separate clean selectors: minified bundles
 * leave escaped newlines in the text, and a rule can carry a comma list.
 */
export function splitSelectors(list) {
	const clean = list
		.replace(/^[\w$]+\.push\(\[[\w$]+\.id,\s*["']/, '')
		.replace(/\\[ntr]/g, ' ')
		.replace(/\s+/g, ' ')
		.trim()
	const out = []
	let depth = 0
	let start = 0
	for (let i = 0; i < clean.length; i++) {
		const c = clean[i]
		if (c === '(' || c === '[') depth++
		else if (c === ')' || c === ']') depth--
		else if (c === ',' && depth === 0) {
			out.push(clean.slice(start, i).trim())
			start = i + 1
		}
	}
	out.push(clean.slice(start).trim())
	return out.filter((s) => s !== '' && isSelector(s))
}

const probe = new JSDOM('<!doctype html><body></body>').window.document

/**
 * Whether a string is a selector that can match an element in the page. The
 * inventory reads selectors out of minified bundles, and a few picks are code
 * or source-map text rather than CSS; those are dropped here, and the browser
 * check in tests/css/check-internal-scopes.mjs confirms what remains.
 */
export function isSelector(text) {
	if (/["']$|^["']|:host\b|sourcesContent|=>/.test(text)) return false
	try {
		probe.querySelector(text)
		return true
	} catch {
		return false
	}
}

/**
 * The editor group a token is listed under: a named component where the
 * variables carry one, else the app they come from, else "other". Headings
 * are translated in js/admin.js, keyed by these ids.
 */
export const GROUPS = {
	'date-picker': 'Date picker',
	select: 'Select box',
	'media-player': 'Media player',
	'code-highlighting': 'Code highlighting',
	conduction: 'Conduction apps',
	'pdf-viewer': 'PDF viewer',
	'text-editor': 'Text editor',
	files: 'Files',
	photos: 'Photos',
	teams: 'Teams',
	components: 'Nextcloud components',
	other: 'Other variables',
}
const BY_OWNER = { dp: 'date-picker', vs: 'select', plyr: 'media-player', hljs: 'code-highlighting', photos: 'photos' }
const BY_APP = { files_pdfviewer: 'pdf-viewer', text: 'text-editor', files: 'files', circles: 'teams' }
const COMPONENTS = ['app', 'assistant', 'auto', 'avatar', 'checkbox', 'chip', 'contenteditable', 'counter', 'figure', 'form', 'input', 'list', 'nc', 'note', 'open', 'radio', 'secondary', 'size', 'user']
const appOf = (source) => source.replace(/^dist\/([a-z_]+)-.*/, '$1').replace(/^apps\/([a-z_]+).*/, '$1')

export function groupOf(entry) {
	if (entry.class === 'conduction') return 'conduction'
	if (BY_OWNER[entry.owner]) return BY_OWNER[entry.owner]
	const apps = [...new Set((entry.sources ?? []).map(appOf))]
	if (apps.length === 1 && BY_APP[apps[0]]) return BY_APP[apps[0]]
	return COMPONENTS.includes(entry.owner) ? 'components' : 'other'
}

export function build(inventory, status) {
	const tokens = {}
	for (const [name, entry] of Object.entries(inventory.variables)) {
		const decision = status.variables[name]
		if (!INTERNAL_CLASSES.includes(entry.class) || decision?.status !== 'settable') continue
		const selectors = [...new Set((entry.selectors ?? []).flatMap(splitSelectors).map((s) => (s === ':root' ? 'body' : s)))].sort()
		const values = entry.values ?? []
		tokens[decision.token] = {
			variable: name,
			class: entry.class,
			owner: entry.owner,
			group: groupOf(entry),
			type: typeOf(name, values),
			mode: selectors.length > 0 ? 'selectors' : 'body',
			...(selectors.length > 0 ? { selectors } : {}),
			...(values.length > 0 ? { stock: values[0].replace(/\s+/g, ' ').trim() } : {}),
		}
	}

	const sorted = Object.fromEntries(Object.keys(tokens).sort().map((k) => [k, tokens[k]]))

	// Nextcloud's own value per theme for the settable theme variables, so the
	// editor can show what a row replaces without the server reading the inventory.
	const themeStock = {}
	for (const name of Object.keys(status.variables).sort()) {
		const entry = inventory.variables[name]
		if (entry?.class === 'theme' && status.variables[name].status === 'settable' && entry.stock) {
			themeStock[name] = { light: entry.stock.default, dark: entry.stock.dark ?? entry.stock.default }
		}
	}

	return {
		$comment: 'GENERATED by scripts/inventory/generate-internal-tokens.mjs from nextcloud-variables.json and variable-status.json. Edit those, not this file.',
		nextcloud: inventory.nextcloud,
		conductionNextcloudVue: inventory.conductionNextcloudVue,
		tokens: sorted,
		themeStock,
	}
}

const clip = (v, n) => {
	const s = String(v ?? '').replace(/\n/g, ' ')
	return n && s.length > n ? s.slice(0, n - 1) + '…' : s
}
const code = (v, n) => {
	const s = clip(v, n).replace(/`/g, "'").replace(/\|/g, '\\|')
	return s ? '`' + s + '`' : ''
}

export function renderDoc(map) {
	const byOwner = {}
	for (const [token, t] of Object.entries(map.tokens)) (byOwner[t.group] ||= []).push([token, t])
	const lines = ['---', 'sidebar_position: 2.5', '---', '']
	lines.push('# Component variables', '')
	lines.push(
		'<!-- GENERATED by scripts/inventory/generate-internal-tokens.mjs. Edit scripts/mapping/variable-status.json, not this page. -->',
		'',
	)
	lines.push(
		`Nextcloud ${map.nextcloud} and \`@conduction/nextcloud-vue\` ${map.conductionNextcloudVue} style their components with variables of their own, below the theme vocabulary. Each one below has a token a set or an admin override can give a value. A token nobody sets changes nothing.`,
		'',
	)
	lines.push('**Reaches** says how a value gets there:', '')
	lines.push('- *every reader*: components only read the variable, so one value on the page reaches them all.')
	lines.push('- *its component*: the component declares the variable itself, so the value is written onto that element.')
	lines.push('')
	lines.push('An internal token applies in light and dark alike. Give it a value in the dark file to differ in dark.', '')
	lines.push(`${Object.keys(map.tokens).length} tokens in ${Object.keys(byOwner).length} groups.`, '')
	for (const owner of Object.keys(GROUPS).filter((g) => byOwner[g])) {
		lines.push('## ' + GROUPS[owner], '')
		lines.push('| Token | Variable | Reaches | Nextcloud\'s value |', '|---|---|---|---|')
		for (const [token, t] of byOwner[owner]) {
			lines.push(`| ${code(token)} | ${code(t.variable)} | ${t.mode === 'body' ? 'every reader' : 'its component'} | ${code(t.stock, 60)} |`)
		}
		lines.push('')
	}
	return lines.join('\n')
}

const isMain = process.argv[1] && fileURLToPath(import.meta.url) === process.argv[1]
if (isMain) {
	const inventory = JSON.parse(readFileSync(join(ROOT, 'scripts/mapping/nextcloud-variables.json'), 'utf8'))
	const status = JSON.parse(readFileSync(join(ROOT, 'scripts/mapping/variable-status.json'), 'utf8'))
	const map = build(inventory, status)
	const files = [
		[MAP, JSON.stringify(map, null, '\t') + '\n'],
		[DOC, renderDoc(map)],
	]
	if (process.argv.includes('--check')) {
		const stale = files.filter(([path, text]) => !existsSync(path) || readFileSync(path, 'utf8') !== text)
		if (stale.length > 0) {
			process.stderr.write('Stale: ' + stale.map(([p]) => p.slice(ROOT.length + 1)).join(', ') + '. Run: npm run generate:internal-tokens\n')
			process.exit(1)
		}
		process.stdout.write('internal tokens: OK, ' + Object.keys(map.tokens).length + ' tokens match the inventory\n')
	} else {
		for (const [path, text] of files) writeFileSync(path, text)
		process.stdout.write('Wrote ' + Object.keys(map.tokens).length + ' internal tokens.\n')
	}
}
