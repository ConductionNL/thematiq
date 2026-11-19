#!/usr/bin/env node

/**
 * Write the recorded reach of the Nextcloud internal tokens.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Every `component` and `slot` token of scripts/mapping/internal-tokens.json gets
 * one reach, read from its stock value and css/systems/nldesign/theme.css:
 *
 * - `follows`: the stock value reads a Nextcloud variable that theme.css maps
 *   from a set token, so the component already wears the set.
 * - `literal`: the stock value is a fixed value, a colour included (the PDF
 *   viewer, code highlighting and the date picker), so a set has no value of
 *   its own to give.
 * - `use-site`: nothing declares the variable; the reader holds a fallback.
 * - `unmapped`: the stock value reads only variables theme.css does not map
 *   from a set token (Nextcloud's own layout and theming variables, or a
 *   library variable outside the inventory), so it follows that variable.
 *
 * A token whose stock value reads only other internal tokens inherits the
 * reach of the first one it reads, and records it as `inherits`.
 *
 * Run with --check to compare instead of writing.
 *
 * @spec openspec/specs/component-tokens/spec.md
 */

/* global console, process */

import { readFileSync, writeFileSync } from 'node:fs'
import { join, dirname } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..', '..')
export const REACH_FILE = join(ROOT, 'scripts/mapping/nextcloud-token-reach.json')
const INVENTORY = join(ROOT, 'scripts/mapping/internal-tokens.json')
const THEME = join(ROOT, 'css/systems/nldesign/theme.css')

const USE_SITE_NOTE = 'Nothing declares the variable; Nextcloud reads it with a fallback where it is used.'

/**
 * The Nextcloud variables theme.css maps from a set token, with that token.
 *
 * @spec openspec/specs/component-tokens/spec.md
 * @param {string} css The design system stylesheet.
 * @return {Map<string, string>} Variable to the set token that drives it.
 */
export function mappedVariables(css) {
	const stripped = css.replace(/\/\*[\s\S]*?\*\//g, '')
	const mapped = new Map()
	const pattern = /(--[\w-]+):\s*var\(\s*(?:--[\w-]+\s*,\s*var\(\s*)?(--nldesign-[\w-]+)\s*\)/g
	for (const match of stripped.matchAll(pattern)) {
		if (mapped.has(match[1]) === false) {
			mapped.set(match[1], match[2])
		}
	}
	return mapped
}

/**
 * The variables a value reads, in order.
 *
 * @spec openspec/specs/component-tokens/spec.md
 * @param {string} value A CSS value.
 * @return {string[]} The variable names.
 */
export function readVariables(value) {
	return [...value.matchAll(/var\(\s*(--[\w-]+)/g)].map((match) => match[1])
}

/**
 * The reach of every Nextcloud internal token.
 *
 * @spec openspec/specs/component-tokens/spec.md
 * @param {object} tokens The `tokens` of the internal token map.
 * @param {Map<string, string>} mapped Variable to driving set token.
 * @return {object} Token name to its reach entry.
 */
export function buildReach(tokens, mapped) {
	const byVariable = new Map()
	for (const [name, token] of Object.entries(tokens)) {
		byVariable.set(token.variable, name)
	}

	const reach = {}
	const resolve = (name, seen = new Set()) => {
		if (reach[name] !== undefined) {
			return reach[name]
		}
		const stock = tokens[name].stock
		if (stock === undefined) {
			reach[name] = { reach: 'use-site', note: USE_SITE_NOTE }
			return reach[name]
		}
		const read = readVariables(stock)
		const through = read.find((variable) => mapped.has(variable))
		if (through !== undefined) {
			reach[name] = { reach: 'follows', through, driver: mapped.get(through) }
			return reach[name]
		}
		if (read.length === 0) {
			reach[name] = { reach: 'literal', stock }
			return reach[name]
		}
		const internal = read.filter((variable) => byVariable.has(variable))
		if (internal.length !== read.length) {
			reach[name] = { reach: 'unmapped', reads: read.filter((variable) => byVariable.has(variable) === false) }
			return reach[name]
		}
		const parent = byVariable.get(internal[0])
		if (seen.has(parent) === true) {
			reach[name] = { reach: 'literal', stock }
			return reach[name]
		}
		seen.add(name)
		reach[name] = { reach: resolve(parent, seen).reach, inherits: parent }
		return reach[name]
	}

	for (const name of Object.keys(tokens).sort()) {
		if (tokens[name].owner !== 'cn') {
			resolve(name)
		}
	}

	const sorted = {}
	for (const name of Object.keys(reach).sort()) {
		sorted[name] = reach[name]
	}
	return sorted
}

/**
 * The record as written to disk.
 *
 * @spec openspec/specs/component-tokens/spec.md
 * @return {string} The JSON text.
 */
export function render() {
	const inventory = JSON.parse(readFileSync(INVENTORY, 'utf8'))
	const tokens = buildReach(inventory.tokens, mappedVariables(readFileSync(THEME, 'utf8')))
	const counts = { follows: 0, literal: 0, 'use-site': 0, unmapped: 0 }
	for (const entry of Object.values(tokens)) {
		counts[entry.reach]++
	}
	const record = {
		$comment: [
			'What a token set can do for each Nextcloud component and slot token of scripts/mapping/internal-tokens.json. Generated by scripts/inventory/generate-nextcloud-token-reach.mjs and proved by tests/vitest/nextcloudTokenReach.spec.js.',
			'follows: the stock value reads a Nextcloud variable css/systems/nldesign/theme.css maps from the set token named as driver. literal: the stock value is fixed, a colour included, so a set has no value of its own to give. use-site: nothing declares the variable. unmapped: the stock value reads only variables no set token drives.',
			'inherits: the stock value reads only other internal tokens, and the token takes the reach of the first one it reads.',
		],
		nextcloud: inventory.nextcloud,
		counts,
		tokens,
	}
	return JSON.stringify(record, null, '\t') + '\n'
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
	const text = render()
	if (process.argv.includes('--check') === true) {
		if (readFileSync(REACH_FILE, 'utf8') !== text) {
			console.error('scripts/mapping/nextcloud-token-reach.json is stale; run scripts/inventory/generate-nextcloud-token-reach.mjs')
			process.exit(1)
		}
	} else {
		writeFileSync(REACH_FILE, text)
	}
}
