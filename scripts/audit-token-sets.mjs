#!/usr/bin/env node

/**
 * Token-set vocabulary audit — Node mirror of TokenSetVocabularyAuditService.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Answers the stage-1 question "does this shipped token set actually define the
 * vocabulary the design system reads?" without a PHP runtime, so the audit can
 * be run locally (`npm run audit:token-sets`) by anyone touching a token set.
 *
 * The rules implemented here are the SAME three rules
 * `lib/Service/TokenSetVocabularyAuditService.php` implements, in the same
 * order, over the same inputs:
 *
 *   1. missingRequired      — required semantic tokens the set file itself does
 *                             not declare. A set that omits them renders as
 *                             whatever `css/systems/nldesign/defaults.css`
 *                             (Rijkshuisstijl) says, not as its own brand.
 *   2. foreignNldesignNames — `--nldesign-*` names the set declares that no
 *                             layer in the app ever reads. Raw upstream palette
 *                             steps belong under the brand prefix
 *                             (`--zwolle-color-blue-40`), never under
 *                             `--nldesign-`.
 *   3. primaryMismatch      — `--nldesign-color-primary` disagrees with
 *                             `token-sets.json`'s `theming.primary_color`. One
 *                             value, one source of truth.
 *
 * WCAG contrast is deliberately NOT re-implemented here: it is already audited
 * by `ShippedTokenSetAuditService` and gated by
 * `tests/Unit/TokenSetContrastAuditTest.php`.
 *
 * The known-incomplete allow-list is read from
 * `tests/Unit/fixtures/token-set-vocabulary-allowlist.json` — the SAME file the
 * PHPUnit gate reads, so the two can never disagree about what is
 * known-broken. Entries are deleted as the converter regenerates the sets; the
 * file must hold an empty `sets` array once every shipped set is complete.
 *
 * Usage:
 *   node scripts/audit-token-sets.mjs            # table, always exits 0
 *   node scripts/audit-token-sets.mjs --check    # exits 1 on any set that is
 *                                                # incomplete and NOT allow-listed,
 *                                                # or allow-listed but now complete
 *   node scripts/audit-token-sets.mjs --json     # machine-readable results
 *   node scripts/audit-token-sets.mjs --verbose  # list every missing/foreign name
 */

import { readFileSync, readdirSync, statSync } from 'fs'
import { join, dirname, basename, resolve } from 'path'
import { fileURLToPath } from 'url'

const LABEL = 'audit:token-sets'

const REPO_ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..')

/**
 * The required semantic vocabulary a correct shipped set declares itself.
 *
 * Kept byte-identical to TokenSetVocabularyAuditService::REQUIRED_TOKENS —
 * change both or neither.
 */
const REQUIRED_TOKENS = [
	'--nldesign-color-primary',
	'--nldesign-color-primary-text',
	'--nldesign-color-primary-hover',
	'--nldesign-color-primary-light',
	'--nldesign-color-primary-light-hover',
	'--nldesign-color-header-background',
	'--nldesign-color-header-text',
	'--nldesign-color-nav-background',
	'--nldesign-color-text',
	'--nldesign-color-text-muted',
	'--nldesign-color-border',
	'--nldesign-color-border-dark',
	'--nldesign-color-link',
	'--nldesign-color-link-hover',
	'--nldesign-color-error',
	'--nldesign-color-error-rgb',
	'--nldesign-color-warning',
	'--nldesign-color-warning-rgb',
	'--nldesign-color-success',
	'--nldesign-color-success-rgb',
	'--nldesign-color-info',
	'--nldesign-color-info-rgb',
	'--nldesign-font-family',
	'--nldesign-border-radius',
	'--nldesign-border-radius-small',
	'--nldesign-border-radius-large',
]

/**
 * Runtime-generated CSS files under css/ that are admin data, not app source.
 * They are excluded from the vocabulary scan so a stray admin value can never
 * widen the accepted vocabulary.
 */
const RUNTIME_CSS_FILES = ['custom-overrides.css', 'custom-css.css']

const ALLOWLIST_PATH = join(
	REPO_ROOT,
	'tests/Unit/fixtures/token-set-vocabulary-allowlist.json',
)

/** Strip CSS comments so a commented-out token never counts. */
function stripComments(css) {
	return css.replace(/\/\*[\s\S]*?\*\//g, '')
}

/**
 * Every `--nldesign-*` name that appears anywhere in the given CSS.
 *
 * The character class matches `CssParserService::parseDeclarations()`'s
 * `[\w-]+`, so a camelCase name (nijmegen's upstream
 * `--nldesign-tokenSetOrder-0` metadata leak) is scanned exactly as it is
 * parsed.
 */
function nldesignNames(css) {
	return [...stripComments(css).matchAll(/--nldesign-[A-Za-z0-9_-]+/g)].map(
		(m) => m[0],
	)
}

/**
 * The `--nldesign-*` custom properties the given CSS DECLARES, name => value.
 *
 * The trailing `;` is required, and `!important` stripped, so this matches
 * `CssParserService::parseDeclarations()` — the parser the PHP service reuses —
 * declaration for declaration.
 */
function nldesignDeclarations(css) {
	const declarations = {}
	for (const match of stripComments(css).matchAll(
		/(--nldesign-[A-Za-z0-9_-]+)\s*:\s*([^;]+);/g,
	)) {
		declarations[match[1]] = match[2].replace(/\s*!\s*important\s*$/i, '').trim()
	}
	return declarations
}

/** Recursively collect .css files under `dir`, skipping the token-set directory. */
function collectCssFiles(dir, out = []) {
	for (const entry of readdirSync(dir, { withFileTypes: true })) {
		const path = join(dir, entry.name)
		if (entry.isDirectory() === true) {
			if (entry.name === 'tokens') {
				continue
			}
			collectCssFiles(path, out)
			continue
		}
		if (
			entry.name.endsWith('.css') === true
			&& RUNTIME_CSS_FILES.includes(entry.name) === false
		) {
			out.push(path)
		}
	}
	return out
}

/**
 * The declared `--nldesign-*` vocabulary: every name any non-token-set CSS
 * layer in the app declares a default for or reads. A name outside this set is
 * dead weight in a token set — nothing can ever consume it.
 */
function declaredVocabulary(root) {
	const vocabulary = new Set()
	for (const file of collectCssFiles(join(root, 'css'))) {
		for (const name of nldesignNames(readFileSync(file, 'utf8'))) {
			vocabulary.add(name)
		}
	}
	return vocabulary
}

/**
 * The design-system ids whose own stylesheet stack reads `--nldesign-*` tokens
 * at all. A set belonging to any other system (`none`, `summer-breeze`) cannot
 * be judged against this vocabulary and is reported as not auditable.
 */
function nldesignConsumingSystems(root) {
	const systems = new Set()
	let manifest
	try {
		manifest = JSON.parse(
			readFileSync(join(root, 'design-systems.json'), 'utf8'),
		)
	} catch {
		return systems
	}
	if (Array.isArray(manifest) === false) {
		return systems
	}

	for (const system of manifest) {
		if (
			system === null
			|| typeof system !== 'object'
			|| typeof system.id !== 'string'
		) {
			continue
		}
		for (const stylesheet of system.stylesheets ?? []) {
			const path = join(root, 'css', `${stylesheet}.css`)
			try {
				if (statSync(path).isFile() === false) {
					continue
				}
			} catch {
				continue
			}
			if (nldesignNames(readFileSync(path, 'utf8')).length > 0) {
				systems.add(system.id)
				break
			}
		}
	}

	return systems
}

/** Normalise a hex colour to lowercase 6-digit form, or null when not a hex literal. */
function normaliseHex(value) {
	if (typeof value !== 'string') {
		return null
	}
	const match = /^#([0-9a-f]{3}|[0-9a-f]{6})$/.exec(value.trim().toLowerCase())
	if (match === null) {
		return null
	}
	const digits = match[1]
	if (digits.length === 3) {
		return `#${digits[0]}${digits[0]}${digits[1]}${digits[1]}${digits[2]}${digits[2]}`
	}
	return `#${digits}`
}

/** token-sets.json indexed by id (empty object when the manifest is unusable). */
function readManifest(root) {
	let manifest
	try {
		manifest = JSON.parse(readFileSync(join(root, 'token-sets.json'), 'utf8'))
	} catch {
		return {}
	}
	if (Array.isArray(manifest) === false) {
		return {}
	}

	const byId = {}
	for (const set of manifest) {
		if (set !== null && typeof set === 'object' && typeof set.id === 'string') {
			byId[set.id] = set
		}
	}
	return byId
}

/** Audit one token set file. */
function auditSet(root, id, meta, vocabulary, consumingSystems) {
	const designSystem =
		typeof meta.design_system === 'string' ? meta.design_system : 'nldesign'
	const auditable = consumingSystems.has(designSystem)

	const declarations = nldesignDeclarations(
		readFileSync(join(root, 'css/tokens', `${id}.css`), 'utf8'),
	)
	const declaredNames = Object.keys(declarations)

	const missingRequired = REQUIRED_TOKENS.filter(
		(token) => declaredNames.includes(token) === false,
	)
	const foreignNldesignNames = declaredNames
		.filter((name) => vocabulary.has(name) === false)
		.sort()

	const declaredPrimary = normaliseHex(meta.theming?.primary_color)
	const cssPrimary = normaliseHex(declarations['--nldesign-color-primary'])
	// A missing/non-literal --nldesign-color-primary is already reported as a
	// missing required token; only a genuine disagreement between two resolvable
	// values is a mismatch, so the two findings never double-count.
	const primaryMismatch =
		declaredPrimary !== null
		&& cssPrimary !== null
		&& declaredPrimary !== cssPrimary

	return {
		id,
		designSystem,
		auditable,
		missingRequired,
		foreignNldesignNames,
		primaryMismatch,
		declaredPrimary,
		cssPrimary,
		complete:
			auditable === false
			|| (missingRequired.length === 0
				&& foreignNldesignNames.length === 0
				&& primaryMismatch === false),
	}
}

/** Audit every shipped set in css/tokens/, ordered by id. */
function auditAll(root) {
	const vocabulary = declaredVocabulary(root)
	const consumingSystems = nldesignConsumingSystems(root)
	const manifest = readManifest(root)

	const results = []
	for (const file of readdirSync(join(root, 'css/tokens'))) {
		if (file.endsWith('.css') === false) {
			continue
		}
		const id = basename(file, '.css')
		results.push(
			auditSet(root, id, manifest[id] ?? {}, vocabulary, consumingSystems),
		)
	}

	results.sort((a, b) => a.id.localeCompare(b.id))
	return results
}

/** The allow-listed known-incomplete set ids. */
function readAllowlist() {
	try {
		const parsed = JSON.parse(readFileSync(ALLOWLIST_PATH, 'utf8'))
		return Array.isArray(parsed.sets) ? parsed.sets : []
	} catch {
		return []
	}
}

function pad(value, width) {
	const text = String(value)
	return text.length >= width ? text : text + ' '.repeat(width - text.length)
}

function padStart(value, width) {
	const text = String(value)
	return text.length >= width ? text : ' '.repeat(width - text.length) + text
}

function printTable(results, allowlist, verbose) {
	console.log('')
	console.log(
		`${pad('Token set', 24)}${pad('Design system', 16)}${padStart('Missing', 8)}${padStart('Foreign', 8)}  ${pad('Primary', 9)}Verdict`,
	)
	console.log('-'.repeat(24 + 16 + 8 + 8 + 2 + 9 + 8))

	for (const row of results) {
		let verdict = 'complete'
		if (row.auditable === false) {
			verdict = 'not audited'
		} else if (row.complete === false) {
			verdict =
				allowlist.includes(row.id) === true
					? 'incomplete (known)'
					: 'INCOMPLETE'
		}

		let primary = '—'
		if (row.auditable === true) {
			if (row.primaryMismatch === true) {
				primary = 'MISMATCH'
			} else if (row.declaredPrimary === null) {
				primary = 'unset'
			} else if (row.cssPrimary === null) {
				primary = 'no css'
			} else {
				primary = 'ok'
			}
		}

		console.log(
			pad(row.id, 24)
				+ pad(row.designSystem, 16)
				+ padStart(
					row.auditable === true ? row.missingRequired.length : '—',
					8,
				)
				+ padStart(
					row.auditable === true ? row.foreignNldesignNames.length : '—',
					8,
				)
				+ '  '
				+ pad(primary, 9)
				+ verdict,
		)

		if (verbose === true && row.auditable === true) {
			if (row.missingRequired.length > 0) {
				console.log(`      missing: ${row.missingRequired.join(' ')}`)
			}
			if (row.foreignNldesignNames.length > 0) {
				console.log(`      foreign: ${row.foreignNldesignNames.join(' ')}`)
			}
			if (row.primaryMismatch === true) {
				console.log(
					`      primary: css ${row.cssPrimary} vs manifest ${row.declaredPrimary}`,
				)
			}
		}
	}
}

function main() {
	const args = process.argv.slice(2)
	const check = args.includes('--check')
	const asJson = args.includes('--json')
	const verbose = args.includes('--verbose')

	const results = auditAll(REPO_ROOT)
	const allowlist = readAllowlist()

	const audited = results.filter((row) => row.auditable === true)
	const incomplete = audited.filter((row) => row.complete === false)
	const unexpected = incomplete
		.filter((row) => allowlist.includes(row.id) === false)
		.map((row) => row.id)
	const staleAllowlist = allowlist.filter(
		(id) =>
			audited.some((row) => row.id === id && row.complete === false) === false,
	)

	if (asJson === true) {
		console.log(
			JSON.stringify(
				{ results, allowlist, unexpected, staleAllowlist },
				null,
				'\t',
			),
		)
	} else {
		printTable(results, allowlist, verbose)
		console.log('')
		console.log(
			`[${LABEL}] ${results.length} shipped sets, ${audited.length} audited, ${incomplete.length} incomplete, ${audited.length - incomplete.length} complete.`,
		)
		console.log(
			`[${LABEL}] allow-list: ${allowlist.length} known-incomplete set(s) — must be empty once every shipped set is complete.`,
		)
		if (unexpected.length > 0) {
			console.log(`[${LABEL}] NOT allow-listed: ${unexpected.join(', ')}`)
		}
		if (staleAllowlist.length > 0) {
			console.log(
				`[${LABEL}] allow-list entries that now pass (delete them): ${staleAllowlist.join(', ')}`,
			)
		}
		if (verbose === false) {
			console.log(
				`[${LABEL}] run with --verbose to list every missing/foreign token name.`,
			)
		}
	}

	if (check === true && (unexpected.length > 0 || staleAllowlist.length > 0)) {
		process.exit(1)
	}
}

main()
