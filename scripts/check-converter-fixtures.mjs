#!/usr/bin/env node
/**
 * `npm run convert:theme:check`: convert every parity fixture and fail when the
 * committed expectation would change.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * The expectations under tests/Unit/fixtures/converter/ are what the PHP
 * runtime is compared against (TokenSetConverterParityTest), so a converter or
 * mapping-table change that moves the output must refresh them in the same
 * commit. `--write` refreshes them.
 *
 * Exit codes: 0 every expectation current (or written), 1 at least one stale.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md#requirement-semantic-mapping-is-table-driven-and-shared
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { join } from 'node:path'
import { FIXTURE_DIR, convertFixture, converterFixtures, loadConverterContext } from './lib/converter-context.mjs'

const LABEL = 'convert:theme:check'
const write = process.argv.includes('--write')
const context = loadConverterContext()
let stale = 0

for (const fixture of converterFixtures()) {
	const path = join(FIXTURE_DIR, `${fixture.name}.expected.json`)
	const next = `${JSON.stringify(convertFixture(fixture, context), null, '\t')}\n`
	const current = existsSync(path) ? readFileSync(path, 'utf8') : ''

	if (current === next) {
		console.log(`[${LABEL}] ${fixture.name}: current`)
		continue
	}

	if (write) {
		writeFileSync(path, next, 'utf8')
		console.log(`[${LABEL}] ${fixture.name}: written`)
		continue
	}

	stale++
	console.error(`[${LABEL}] ${fixture.name}: STALE, the converter now emits something else. Run \`npm run convert:theme:check -- --write\` and review the diff.`)
}

process.exit(stale === 0 ? 0 : 1)
