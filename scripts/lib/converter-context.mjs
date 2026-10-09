/**
 * The one place the Node callers of the theme converter load what it needs.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * `scripts/convert-nlds-theme.mjs`, `scripts/check-converter-fixtures.mjs` and
 * the vitest suites read the mapping table, its SHA-256 and the app's
 * `--nldesign-*` vocabulary through here, so the provenance hash every
 * converted file carries is computed one way. The PHP runtime hashes the same
 * raw bytes (`TokenSetConverterService`, `hash('sha256', $raw)`), and the
 * parity test compares the emitted provenance line, so the two cannot drift
 * without a red test.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md#requirement-converted-output-shape-and-provenance
 */

import { readFileSync, readdirSync, statSync } from 'node:fs'
import { createHash } from 'node:crypto'
import { dirname, join, resolve } from 'node:path'
import { fileURLToPath } from 'node:url'
import { createRequire } from 'node:module'

const require = createRequire(import.meta.url)

export const repoRoot = resolve(dirname(fileURLToPath(import.meta.url)), '../..')
export const converter = require(join(repoRoot, 'js/lib/tokenConverter.js'))
export const FIXTURE_DIR = join(repoRoot, 'tests/Unit/fixtures/converter')

/**
 * SHA-256 of the mapping table's raw bytes, hex.
 *
 * @param {string} raw The table file as read from disk.
 * @return {string} The hex digest.
 */
export function tableHash(raw) {
	return createHash('sha256').update(raw, 'utf8').digest('hex')
}

/**
 * Load the mapping table, its hash and the vocabulary.
 *
 * @return {{table: Object, tableHash: string, vocabulary: Object<string, boolean>}} The context.
 */
export function loadConverterContext() {
	const raw = readFileSync(
		join(repoRoot, 'scripts/mapping/nlds-to-nextcloud.json'),
		'utf8',
	)

	return {
		table: JSON.parse(raw),
		tableHash: tableHash(raw),
		vocabulary: converter.vocabularyFrom(vocabularyStylesheets()),
	}
}

/**
 * Every stylesheet under css/ that defines or reads the app vocabulary: all of
 * them except the token sets and the two runtime files an admin writes. The
 * same walk as TokenSetVocabularyAuditService and
 * TokenSetConverterService::vocabulary(), so the three agree on what "app
 * vocabulary" means.
 *
 * @param {string} [directory] The directory to walk.
 * @return {Array<string>} The stylesheet contents.
 */
export function vocabularyStylesheets(directory = join(repoRoot, 'css')) {
	const out = []
	for (const entry of readdirSync(directory).sort()) {
		const path = join(directory, entry)
		if (entry === 'tokens') {
			continue
		}
		if (statSync(path).isDirectory()) {
			out.push(...vocabularyStylesheets(path))
			continue
		}
		if (
			entry.endsWith('.css')
			&& entry !== 'custom-overrides.css'
			&& entry !== 'custom-css.css'
		) {
			out.push(readFileSync(path, 'utf8'))
		}
	}

	return out
}

/**
 * The parity fixtures: `tests/Unit/fixtures/converter/fixtures.json`, read by
 * this module, the vitest suite and `TokenSetConverterParityTest`.
 *
 * @return {Array<{name: string, input: string, slug: string, displayName: string, sourceName: string}>} The fixtures.
 */
export function converterFixtures() {
	return JSON.parse(readFileSync(join(FIXTURE_DIR, 'fixtures.json'), 'utf8'))
		.fixtures
}

/**
 * Convert one fixture into the expectation shape both runtimes are held to.
 *
 * @param {Object} fixture One entry of `converterFixtures()`.
 * @param {Object} context The result of `loadConverterContext()`.
 * @return {Object} `{inputKind, css, manifestEntry, report, counts, logoAsset}`.
 */
export function convertFixture(fixture, context) {
	const content = readFileSync(join(repoRoot, fixture.input), 'utf8')
	const result = converter.convert(content, {
		slug: fixture.slug,
		displayName: fixture.displayName,
		sourceName: fixture.sourceName,
		table: context.table,
		tableHash: context.tableHash,
		vocabulary: context.vocabulary,
		fonts: [],
	})

	return {
		inputKind: result.inputKind,
		css: result.css,
		manifestEntry: result.manifestEntry,
		report: result.report,
		counts: result.counts,
		logoAsset:
			result.logoAsset === null
				? null
				: {
						path: result.logoAsset.path,
						base64: Buffer.from(
							result.logoAsset.contents,
							'latin1',
						).toString('base64'),
					},
	}
}
