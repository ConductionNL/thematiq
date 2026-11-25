#!/usr/bin/env node

/* global process, console */

/**
 * Export a token set the way the component playground's "Export as token set" does.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Runs the playground's own `exportCss()` (js/playground.js) over the tokens a
 * shipped set declares, read the way `TokenSetPreviewService::getDeclaredTokens()`
 * reads them, with a recorded set of saved overrides folded in. The result is
 * byte-identical to the file the button downloads for that set and those
 * overrides, so a set authored in the playground can be committed and checked.
 *
 * The overrides file names the base set, the design system and the overrides:
 *   { "base": "vng", "designSystem": "nldesign", "overrides": { "--nldesign-…": "…" } }
 *
 * Usage:
 *   node scripts/export-playground-set.mjs <overrides.json> --out css/tokens/<id>.css
 *   node scripts/export-playground-set.mjs <overrides.json> --check css/tokens/<id>.css
 *
 * --check exits 1 when the committed file differs from a fresh export.
 *
 * @spec openspec/specs/token-sets/spec.md
 */

import { readFileSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const LABEL = 'export:playground-set'
const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const require = createRequire(import.meta.url)
const playground = require(join(REPO_ROOT, 'js/playground.js'))

/**
 * The --nldesign-* tokens a shipped set declares, as the server publishes them
 * to the playground: every `--name: value;` line, the last one winning.
 *
 * @param {string} css The token set file.
 * @return {Object<string, string>} Token name to value.
 */
export function declaredTokens(css) {
	const tokens = {}
	for (const match of css.matchAll(/^\s*(--[\w-]+)\s*:\s*([^;]+);/gm)) {
		const name = match[1].trim()
		if (name.startsWith('--nldesign-') === true) {
			tokens[name] = match[2].trim()
		}
	}
	return tokens
}

/**
 * Export the set an overrides file describes.
 *
 * @param {string} overridesPath Path to the overrides file.
 * @param {string} [root] Repository root.
 * @return {{css: string, unexpressed: Array<string>, overruled: Array<Object>}} The export.
 */
export function exportSet(overridesPath, root = REPO_ROOT) {
	const spec = JSON.parse(readFileSync(overridesPath, 'utf8'))
	const base = readFileSync(join(root, 'css/tokens', `${spec.base}.css`), 'utf8')
	return playground.exportCss(
		declaredTokens(base),
		spec.overrides,
		{},
		spec.designSystem,
	)
}

function main() {
	const args = process.argv.slice(2)
	const input = args[0]
	const outIndex = args.indexOf('--out')
	const checkIndex = args.indexOf('--check')
	if (input === undefined || (outIndex === -1 && checkIndex === -1)) {
		console.error(`[${LABEL}] usage: node scripts/export-playground-set.mjs <overrides.json> (--out|--check) <file>`)
		process.exit(1)
	}

	const result = exportSet(resolve(input))
	if (result.unexpressed.length > 0 || result.overruled.length > 0) {
		console.error(`[${LABEL}] overrides no token carries: ${JSON.stringify(result)}`)
		process.exit(1)
	}

	if (checkIndex !== -1) {
		const target = resolve(args[checkIndex + 1])
		if (readFileSync(target, 'utf8') !== result.css) {
			console.error(`[${LABEL}] ${target} differs from a fresh export of ${input}`)
			process.exit(1)
		}
		console.log(`[${LABEL}] ${target} matches a fresh export.`)
		return
	}

	const target = resolve(args[outIndex + 1])
	writeFileSync(target, result.css, 'utf8')
	console.log(`[${LABEL}] wrote ${target}`)
}

if (process.argv[1] !== undefined && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	main()
}
