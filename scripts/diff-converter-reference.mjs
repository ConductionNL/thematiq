#!/usr/bin/env node

/* global process, console */

/**
 * Diff the converter's output for a theme against a reference token set.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Converts the input with `js/lib/tokenConverter.js` (as `convert-nlds-theme.mjs`
 * does, writing nothing) and compares the `--nldesign-*` tokens of the result
 * with the ones the reference declares. Prints a Markdown table: tokens with the
 * same value, a different value, or present on one side only.
 *
 * Usage:
 *   node scripts/diff-converter-reference.mjs <input> --slug openwoo --reference css/tokens/openwoo.css
 *
 * @spec openspec/changes/archive/2026-11-22-token-set-coverage-next-waves/tasks.md#task-5.2
 */

import { readFileSync } from 'node:fs'
import { basename, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { converter, loadConverterContext } from './lib/converter-context.mjs'
import { declaredTokens } from './export-playground-set.mjs'

const LABEL = 'diff:converter-reference'

/**
 * Compare two token maps.
 *
 * @param {Object<string, string>} reference The reference tokens.
 * @param {Object<string, string>} converted The converter's tokens.
 * @return {Array<{token: string, reference: ?string, converted: ?string, verdict: string}>} One row per token.
 */
export function compare(reference, converted) {
	const names = [...new Set([...Object.keys(reference), ...Object.keys(converted)])].sort()
	return names.map((token) => {
		const left = reference[token] ?? null
		const right = converted[token] ?? null
		let verdict = 'same'
		if (left === null) {
			verdict = 'converter only'
		} else if (right === null) {
			verdict = 'reference only'
		} else if (left.toLowerCase() !== right.toLowerCase()) {
			verdict = 'different'
		}
		return { token, reference: left, converted: right, verdict }
	})
}

function main() {
	const args = process.argv.slice(2)
	const input = args[0]
	const slug = args[args.indexOf('--slug') + 1]
	const referencePath = args[args.indexOf('--reference') + 1]
	if (input === undefined || args.includes('--slug') === false || args.includes('--reference') === false) {
		console.error(`[${LABEL}] usage: node scripts/diff-converter-reference.mjs <input> --slug <slug> --reference <file>`)
		process.exit(1)
	}

	const context = loadConverterContext()
	const result = converter.convert(readFileSync(resolve(input), 'utf8'), {
		slug,
		displayName: slug,
		sourceName: basename(input),
		table: context.table,
		tableHash: context.tableHash,
		vocabulary: context.vocabulary,
		fonts: [],
	})

	const rows = compare(
		declaredTokens(readFileSync(resolve(referencePath), 'utf8')),
		declaredTokens(result.css),
	)
	const counts = {}
	for (const row of rows) {
		counts[row.verdict] = (counts[row.verdict] ?? 0) + 1
	}

	console.log(`| Verdict | Tokens |\n|---|---|`)
	for (const verdict of ['same', 'different', 'reference only', 'converter only']) {
		console.log(`| ${verdict} | ${counts[verdict] ?? 0} |`)
	}
	console.log('\n| Token | Reference | Converter | Verdict |\n|---|---|---|---|')
	for (const row of rows.filter((entry) => entry.verdict !== 'converter only')) {
		console.log(`| \`${row.token}\` | ${row.reference ?? ''} | ${row.converted ?? ''} | ${row.verdict} |`)
	}
}

if (process.argv[1] !== undefined && resolve(process.argv[1]) === fileURLToPath(import.meta.url)) {
	main()
}
