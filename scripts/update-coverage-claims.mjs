#!/usr/bin/env node

/**
 * Rewrite the measured token-set coverage stated in the bridge and the docs.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * css/public-bridge.css and docs/features/public-portals-as-consumers.md state how
 * many listed sets carry the `--utrecht-*` role layer, how many only `--nldesign-*`,
 * and how many Den Haag component properties. tests/vitest/denhaagBridge.spec.js
 * recomputes the machine line and fails when it drifts. A converted set changes
 * those numbers, so the token-set gate runs this script after every change.
 * The counting rule is the test's, copied exactly.
 *
 * Usage:
 *   node scripts/update-coverage-claims.mjs           rewrite the numbers
 *   node scripts/update-coverage-claims.mjs --check   exit 1 when a number is stale
 *
 * @spec openspec/specs/token-sync-workflow/spec.md#requirement-converted-and-gated-sync
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..')
const BRIDGE = 'css/public-bridge.css'
const DOC = 'docs/features/public-portals-as-consumers.md'

/**
 * Count the coverage the way tests/vitest/denhaagBridge.spec.js does.
 *
 * @param {string} root Repository root.
 * @return {Object} `{listed, roleLayer, nldesignOnly, denhaagComponents}`.
 */
export function measure(root) {
	const sets = JSON.parse(readFileSync(join(root, 'token-sets.json'), 'utf8'))
	const counts = {
		listed: sets.length,
		roleLayer: 0,
		nldesignOnly: 0,
		denhaagComponents: 0,
	}
	for (const set of sets) {
		const file = join(root, 'css', 'tokens', `${set.id}.css`)
		if (existsSync(file) === false) {
			continue
		}
		const css = readFileSync(file, 'utf8')
		const utrecht = (css.match(/--utrecht-[a-z0-9-]+\s*:/g) ?? []).length
		const nldesign = (css.match(/--nldesign-[a-z0-9-]+\s*:/g) ?? []).length
		const denhaag = (css.match(/--denhaag-(?!color-)[a-z0-9-]+\s*:/g) ?? [])
			.length
		if (utrecht >= 100) {
			counts.roleLayer++
		}
		if (utrecht === 0 && nldesign > 0) {
			counts.nldesignOnly++
		}
		if (denhaag > 0) {
			counts.denhaagComponents++
		}
	}
	return counts
}

/**
 * Apply the counts to the bridge comment.
 *
 * @param {string} text The bridge file.
 * @param {Object} c The counts.
 * @return {string} The rewritten file.
 */
export function rewriteBridge(text, c) {
	return text
		.replace(
			/coverage: listed=\d+ roleLayer=\d+ nldesignOnly=\d+ denhaagComponents=\d+/,
			`coverage: listed=${c.listed} roleLayer=${c.roleLayer} nldesignOnly=${c.nldesignOnly} denhaagComponents=${c.denhaagComponents}`,
		)
		.replace(
			/(Of the listed sets, )\d+( carry the full `--utrecht-\*` role layer, )\d+( declare\s*\n\s*\*\s*only `--nldesign-\*`, and )\d+( declare Den Haag)/,
			`$1${c.roleLayer}$2${c.nldesignOnly}$3${c.denhaagComponents}$4`,
		)
}

/**
 * Apply the counts to the docs table.
 *
 * @param {string} text The docs page.
 * @param {Object} c The counts.
 * @return {string} The rewritten page.
 */
export function rewriteDoc(text, c) {
	return text
		.replace(/(\(`token-sets\.json`, )\d+( sets\))/, `$1${c.listed}$2`)
		.replace(
			/(\| the full `--utrecht-\*` role layer \| )\d+( \|)/,
			`$1${c.roleLayer}$2`,
		)
		.replace(/(\| only `--nldesign-\*` \| )\d+( \|)/, `$1${c.nldesignOnly}$2`)
		.replace(
			/(\| Den Haag component properties \| )\d+( \|)/,
			`$1${c.denhaagComponents}$2`,
		)
}

/**
 * Entry point.
 *
 * @return {number} Exit code.
 */
function main() {
	const check = process.argv.includes('--check')
	const counts = measure(ROOT)
	let stale = false
	for (const [path, rewrite] of [
		[BRIDGE, rewriteBridge],
		[DOC, rewriteDoc],
	]) {
		const before = readFileSync(join(ROOT, path), 'utf8')
		const after = rewrite(before, counts)
		if (after === before) {
			continue
		}
		stale = true
		if (check === false) {
			writeFileSync(join(ROOT, path), after, 'utf8')
			console.log(`Updated the coverage in ${path}.`)
		} else {
			console.error(
				`${path} states stale coverage. Run: node scripts/update-coverage-claims.mjs`,
			)
		}
	}
	return check === true && stale === true ? 1 : 0
}

if (
	process.argv[1] !== undefined
	&& resolve(process.argv[1]) === fileURLToPath(import.meta.url)
) {
	process.exitCode = main()
}
