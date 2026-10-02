#!/usr/bin/env node

/**
 * Build the inventory of every CSS custom property Nextcloud and the shared
 * Conduction library declare or read.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * WHY THIS EXISTS
 *
 * "thematiq supports all Nextcloud variables" was a claim nobody could check,
 * because nothing recorded what "all" is. The audit in
 * css/systems/nldesign/overrides.css lists the theming app's 111 variables by
 * hand and says nothing about the ~1,000 more that Nextcloud's own components
 * read. This script reads a Nextcloud release and the library build, and
 * writes the full list with a class per variable, so the coverage claim can be
 * tested (tests/vitest/nextcloudVariableInventory.spec.js).
 *
 * INPUTS
 *
 *   --sources <dir>  made by scripts/inventory/fetch-nextcloud-sources.sh:
 *                      nc/         the css/js/mjs files of core/, apps/, dist/
 *                      theming/    default.css dark.css light-highcontrast.css
 *                                  dark-highcontrast.css, as the theming app serves them
 *                      meta.json   { "nextcloud": "<version string>" }
 *   --cn <dir>       the @conduction/nextcloud-vue package directory
 *                    (default: node_modules/@conduction/nextcloud-vue)
 *   --check          compare with the committed file instead of writing it
 *
 * OUTPUT
 *
 *   scripts/mapping/nextcloud-variables.json
 *
 * Status (what thematiq does with a variable) is NOT written here. It is a
 * human decision and lives in scripts/mapping/variable-status.json, so that
 * regenerating the inventory can never lose one.
 */

import { readFileSync, writeFileSync, existsSync } from 'node:fs'
import { join, relative, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'
import { stripComments, walk } from './lib.mjs'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
const OUT = join(ROOT, 'scripts/mapping/nextcloud-variables.json')
const THEMES = ['default', 'dark', 'light-highcontrast', 'dark-highcontrast']

/** Cap per entry, so one hot variable cannot bloat the file. */
const MAX_SELECTORS = 6
const MAX_VALUE = 160

/**
 * Names the rules cannot classify correctly, with the reason. Keep this short:
 * every entry is a place where the rule was wrong.
 */
const FORCED_CLASS = {}

function args() {
	const out = {
		sources: null,
		cn: join(ROOT, 'node_modules/@conduction/nextcloud-vue'),
		check: false,
	}
	const a = process.argv.slice(2)
	for (let i = 0; i < a.length; i++) {
		if (a[i] === '--sources') out.sources = a[++i]
		else if (a[i] === '--cn') out.cn = a[++i]
		else if (a[i] === '--check') out.check = true
	}
	if (!out.sources) {
		console.error(
			'extract-nextcloud-variables: --sources <dir> is required. Make it with scripts/inventory/fetch-nextcloud-sources.sh.',
		)
		process.exit(2)
	}
	return out
}

const isCode = (name) => /\.(css|js|mjs|cjs)$/.test(name) && !name.endsWith('.map')

/**
 * Declarations `--x: value` in text that may be CSS or JS holding CSS strings.
 * Returns [name, value, selector] triples; the selector is the nearest
 * preceding `{`-opener, which is enough to name where a component declares it.
 */
function declarations(text) {
	const out = []
	const re = /(^|[;{\s"'`(])(--[a-zA-Z][a-zA-Z0-9_-]*)\s*:\s*([^;}"'`]{1,400})/g
	let m
	while ((m = re.exec(text)) !== null) {
		const before = text.lastIndexOf('{', m.index)
		const start = Math.max(
			text.lastIndexOf('}', before),
			text.lastIndexOf(';', before),
			before - 200,
		)
		const selector =
			before > 0
				? text
						.slice(start + 1, before)
						.split('{')
						.pop()
						.replace(/\s+/g, ' ')
						.trim()
						.slice(-120)
				: ''
		out.push([m[2], m[3].trim(), selector])
	}
	return out
}

/** Names read with var(--x ...). */
function reads(text) {
	const out = new Set()
	const re = /var\(\s*(--[a-zA-Z][a-zA-Z0-9_-]*)/g
	let m
	while ((m = re.exec(text)) !== null) out.add(m[1])
	return out
}

/**
 * Names JavaScript writes at render time: `setProperty('--x'` and object keys
 * `'--x':` in inline style bindings. A CSS string inside a bundle writes
 * `--x:` unquoted, so the quote is what tells the two apart.
 */
function runtimeWrites(text) {
	const out = new Set()
	const re =
		/(?:setProperty\(\s*["'`]|["'`])(--[a-zA-Z][a-zA-Z0-9_-]*)["'`]\s*[:,]/g
	let m
	while ((m = re.exec(text)) !== null) out.add(m[1])
	return out
}

/** The top-level declarations of one theming stylesheet: name -> value. */
function themeBlock(css) {
	const out = {}
	const text = stripComments(css)
	const open = text.indexOf('{')
	let depth = 0
	let end = open
	for (let i = open; i < text.length; i++) {
		if (text[i] === '{') depth++
		else if (text[i] === '}') {
			depth--
			if (depth === 0) {
				end = i
				break
			}
		}
	}
	// Only declarations at depth 1: nested rules (`.menutoggle { ... }`) are
	// the theme's own element styling, not its vocabulary.
	let d = 0
	let buf = ''
	for (const ch of text.slice(open + 1, end)) {
		if (ch === '{') d++
		else if (ch === '}') {
			d--
			buf = ''
			continue
		}
		if (d === 0) buf += ch
		if (d === 0 && ch === ';') {
			const m = buf.match(/(--[a-zA-Z][a-zA-Z0-9_-]*)\s*:\s*([\s\S]*);$/)
			if (m) out[m[1]] = m[2].trim().slice(0, MAX_VALUE)
			buf = ''
		}
	}
	return out
}

/** The family a variable belongs to: its first name segment. */
const owner = (name) => name.replace(/^--/, '').split('-')[0]

function classify(name, info) {
	if (FORCED_CLASS[name]) return FORCED_CLASS[name].class
	if (name.startsWith('--cn-')) return 'conduction'
	if (info.theme) return 'theme'
	if (
		/^--(original-)?icon-/.test(name)
		|| (info.values.length > 0 && info.values.every((v) => /^url\(/.test(v)))
	)
		return 'icon'
	if (info.runtime) return 'runtime'
	if (info.declared && info.read) return 'component'
	if (info.read) return 'slot'
	return 'unread'
}

function main() {
	const opt = args()
	const meta = JSON.parse(readFileSync(join(opt.sources, 'meta.json'), 'utf8'))
	const cnPkg = JSON.parse(readFileSync(join(opt.cn, 'package.json'), 'utf8'))

	const info = new Map()
	const get = (n) => {
		if (!info.has(n))
			info.set(n, {
				theme: null,
				declared: false,
				read: false,
				runtime: false,
				values: [],
				selectors: new Set(),
				files: new Set(),
			})
		return info.get(n)
	}

	// 1. The theme vocabulary and its stock value per built-in theme.
	for (const t of THEMES) {
		const p = join(opt.sources, 'theming', `${t}.css`)
		if (!existsSync(p)) throw new Error(`missing theming stylesheet ${p}`)
		for (const [n, v] of Object.entries(themeBlock(readFileSync(p, 'utf8')))) {
			const e = get(n)
			e.theme = e.theme || {}
			e.theme[t] = v
		}
	}

	// 2. Every declaration and read in shipped Nextcloud code.
	const ncRoot = join(opt.sources, 'nc')
	for (const f of walk(ncRoot, isCode)) {
		const text = stripComments(readFileSync(f, 'utf8'))
		const rel = relative(ncRoot, f)
		for (const [n, v, sel] of declarations(text)) {
			const e = get(n)
			e.declared = true
			if (e.values.length < 4 && !e.values.includes(v.slice(0, MAX_VALUE)))
				e.values.push(v.slice(0, MAX_VALUE))
			if (sel) e.selectors.add(sel)
			e.files.add(rel.split('/').slice(0, 2).join('/'))
		}
		for (const n of reads(text)) {
			const e = get(n)
			e.read = true
			e.files.add(rel.split('/').slice(0, 2).join('/'))
		}
		if (/\.(js|mjs|cjs)$/.test(f))
			for (const n of runtimeWrites(text)) get(n).runtime = true
	}

	// 3. The shared library's --cn-* names.
	for (const f of walk(join(opt.cn, 'dist'), isCode)) {
		const text = stripComments(readFileSync(f, 'utf8'))
		for (const [n, v, sel] of declarations(text)) {
			if (!n.startsWith('--cn-')) continue
			const e = get(n)
			e.declared = true
			if (e.values.length < 4 && !e.values.includes(v.slice(0, MAX_VALUE)))
				e.values.push(v.slice(0, MAX_VALUE))
			if (sel) e.selectors.add(sel)
			e.files.add('@conduction/nextcloud-vue')
		}
		for (const n of reads(text)) {
			if (!n.startsWith('--cn-')) continue
			const e = get(n)
			e.read = true
			e.files.add('@conduction/nextcloud-vue')
		}
	}

	const variables = {}
	const counts = {}
	for (const name of [...info.keys()].sort()) {
		const e = info.get(name)
		const cls = classify(name, e)
		counts[cls] = (counts[cls] || 0) + 1
		const entry = { class: cls, owner: owner(name) }
		if (e.theme) entry.stock = e.theme
		else if (e.values.length) entry.values = e.values
		if (e.selectors.size)
			entry.selectors = [...e.selectors].sort().slice(0, MAX_SELECTORS)
		entry.sources = [...e.files].sort().slice(0, MAX_SELECTORS)
		variables[name] = entry
	}

	const doc = {
		$comment:
			'GENERATED by scripts/inventory/extract-nextcloud-variables.mjs. Do not edit by hand; statuses live in variable-status.json.',
		nextcloud: meta.nextcloud,
		conductionNextcloudVue: cnPkg.version,
		counts: Object.fromEntries(Object.entries(counts).sort()),
		variables,
	}
	const json = JSON.stringify(doc, null, '\t') + '\n'

	if (opt.check) {
		const committed = existsSync(OUT) ? readFileSync(OUT, 'utf8') : ''
		if (committed === json) {
			console.log(
				`nextcloud-variables.json matches a fresh extraction (${Object.keys(variables).length} variables).`,
			)
			return
		}
		const a = committed.split('\n')
		const b = json.split('\n')
		const i = a.findIndex((line, k) => line !== b[k])
		console.error(
			`nextcloud-variables.json differs from a fresh extraction at line ${i + 1}:`,
		)
		console.error(`  committed: ${a[i]}`)
		console.error(`  extracted: ${b[i]}`)
		process.exit(1)
	}

	writeFileSync(OUT, json)
	console.log(
		`wrote ${relative(ROOT, OUT)}: ${Object.keys(variables).length} variables`,
		doc.counts,
	)
}

main()
