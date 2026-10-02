/**
 * Helpers shared by the inventory extractor, the mappings generator and the
 * inventory guard, so all three read a stylesheet the same way.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 */

import { readFileSync, readdirSync, statSync } from 'node:fs'
import { join } from 'node:path'

/** Remove CSS block comments, so an annotation is never read as a declaration. */
export const stripComments = (text) => text.replace(/\/\*[\s\S]*?\*\//g, '')

/**
 * Whether `value` only refers back to `name` itself (`--x: var(--x)` or
 * `var(--x, fallback)`), which assigns nothing.
 */
export function isSelfReference(name, value) {
	const escaped = name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
	return new RegExp(
		`^var\\(\\s*${escaped}\\s*(,[\\s\\S]*)?\\)\\s*(!important)?$`,
	).test(value.trim())
}

/**
 * Every real assignment in a stylesheet: comments stripped, self-references
 * dropped. Returns a Map of name -> array of values.
 */
export function assignments(css) {
	const out = new Map()
	const re = /(?<![\w-])(--[a-zA-Z][\w-]*)\s*:\s*([^;{}]+)/g
	let m
	const text = stripComments(css)
	while ((m = re.exec(text)) !== null) {
		const [, name, value] = m
		if (isSelfReference(name, value)) continue
		if (!out.has(name)) out.set(name, [])
		out.get(name).push(value.trim())
	}
	return out
}

/** Merge the assignments of several files into one Map. */
export function assignmentsOf(files) {
	const out = new Map()
	for (const f of files) {
		for (const [name, values] of assignments(readFileSync(f, 'utf8'))) {
			if (!out.has(name)) out.set(name, [])
			out.get(name).push(...values)
		}
	}
	return out
}

/** Files under `dir` whose name passes `keep`, sorted, skipping node_modules. */
export function walk(dir, keep) {
	const files = []
	for (const name of readdirSync(dir)) {
		const p = join(dir, name)
		if (statSync(p).isDirectory()) {
			if (name !== 'node_modules') files.push(...walk(p, keep))
		} else if (keep(name)) {
			files.push(p)
		}
	}
	return files.sort()
}

/** Prefixes of Nextcloud's own variables, for the unknown-name check. */
export const NEXTCLOUD_PREFIX =
	/^--(color|border|font|default|header|background|gradient|image|primary|filter|animation|clickable|sidebar|navigation|body|breakpoint|footer)-/
