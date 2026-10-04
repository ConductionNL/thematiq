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

import { readFileSync, readdirSync, statSync, writeFileSync } from 'fs'
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
 * Design systems that read their OWN token vocabulary instead of
 * `--nldesign-*`: the prefix their stylesheets read and the token that carries
 * the brand primary. A set of such a system is audited against that vocabulary
 * (thematiq#1022).
 *
 * Kept byte-identical to TokenSetVocabularyAuditService::OWN_VOCABULARIES.
 */
const OWN_VOCABULARIES = {
	'summer-breeze': { prefix: '--summer-', primary: '--summer-color-primary' },
}

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

/** Escape a string for use inside a RegExp. */
function escapeRegExp(text) {
	return text.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
}

/**
 * The custom properties under one prefix (`--nldesign-` unless told otherwise)
 * the given CSS DECLARES, name => value.
 *
 * The trailing `;` is required, and `!important` stripped, so this matches
 * `CssParserService::parseDeclarations()` — the parser the PHP service reuses —
 * declaration for declaration.
 */
function nldesignDeclarations(css, prefix = '--nldesign-') {
	const declarations = {}
	const pattern = new RegExp(
		`(${escapeRegExp(prefix)}[A-Za-z0-9_-]+)\\s*:\\s*([^;]+);`,
		'g',
	)
	for (const match of stripComments(css).matchAll(pattern)) {
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
 * The own-prefix names the non-token CSS layers read through `var()`, and the
 * ones they declare (mirror of TokenSetVocabularyAuditService::ownVocabulary()).
 */
function ownVocabulary(root, prefix) {
	const quoted = escapeRegExp(prefix)
	const readPattern = new RegExp(`var\\(\\s*(${quoted}[A-Za-z0-9_-]+)`, 'g')
	const declaredPattern = new RegExp(`(${quoted}[A-Za-z0-9_-]+)\\s*:`, 'g')
	const read = new Set()
	const declared = new Set()
	for (const file of collectCssFiles(join(root, 'css'))) {
		const css = stripComments(readFileSync(file, 'utf8'))
		for (const match of css.matchAll(readPattern)) {
			read.add(match[1])
		}
		for (const match of css.matchAll(declaredPattern)) {
			declared.add(match[1])
		}
	}
	return { read, declared }
}

/**
 * The design-system ids whose own stylesheet stack reads `--nldesign-*` tokens
 * at all. A set of `none` cannot be judged against this vocabulary and is
 * reported as not auditable; a set of a system in OWN_VOCABULARIES
 * (`summer-breeze`) is audited against its own vocabulary instead.
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

/** Audit a set of a design system with its own vocabulary (same three rules). */
function auditOwnVocabulary(root, id, meta, designSystem) {
	const { prefix, primary } = OWN_VOCABULARIES[designSystem]
	const declarations = nldesignDeclarations(
		readFileSync(join(root, 'css/tokens', `${id}.css`), 'utf8'),
		prefix,
	)
	const declaredNames = Object.keys(declarations)
	const layers = ownVocabulary(root, prefix)

	const missingRequired = [...layers.read]
		.filter((name) => layers.declared.has(name) === false)
		.filter((name) => declaredNames.includes(name) === false)
		.sort()
	const foreignNldesignNames = declaredNames
		.filter((name) => layers.read.has(name) === false)
		.filter((name) => layers.declared.has(name) === false)
		.sort()

	const declaredPrimary = normaliseHex(meta.theming?.primary_color)
	const cssPrimary = normaliseHex(declarations[primary])
	const primaryMismatch =
		declaredPrimary !== null
		&& cssPrimary !== null
		&& declaredPrimary !== cssPrimary

	return {
		id,
		designSystem,
		auditable: true,
		missingRequired,
		foreignNldesignNames,
		primaryMismatch,
		declaredPrimary,
		cssPrimary,
		complete:
			missingRequired.length === 0
			&& foreignNldesignNames.length === 0
			&& primaryMismatch === false,
	}
}

/** Audit one token set file. */
function auditSet(root, id, meta, vocabulary, consumingSystems) {
	const designSystem =
		typeof meta.design_system === 'string' ? meta.design_system : 'nldesign'
	if (Object.hasOwn(OWN_VOCABULARIES, designSystem) === true) {
		return auditOwnVocabulary(root, id, meta, designSystem)
	}
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

/* ==========================================================================
 * STAGE 2 — INSTANCE COVERAGE
 *
 * Stage 1 above answers "does this set declare the 26 semantic tokens the
 * design system reads?". Every shipped set has passed that since thematiq#1006,
 * and the allow-list has been empty since, which read as "every shipped set is
 * complete". It is not the same question as "does a Nextcloud instance on this
 * set look like this organisation", and on 2026-10-04 the gap between the two
 * was: 14 sets dressed every component in Rijkshuisstijl, 33 named a typeface
 * nothing served, 36 showed the Nextcloud logo, and 8 could not be judged for
 * contrast at all.
 *
 * So stage 2 measures the four things an administrator actually sees, per set:
 *
 *   bridge   — how many of the `--utrecht-*` names css/systems/nldesign/
 *              utrecht-bridge.css reads the set declares. Zero means every
 *              button, table and form control keeps the Rijkshuisstijl default,
 *              whatever the set's accent colour is.
 *   font     — whether the first family the set names has an @font-face in a
 *              stylesheet its own design system LINKS. A face declared in a
 *              stylesheet nothing links never loads.
 *   logo     — whether the set points Nextcloud at a logo, or leaves the
 *              Nextcloud one in the header.
 *   contrast — the shipped contrast verdict, read from
 *              docs/reference/contrast-report.json so this script and
 *              ShippedTokenSetAuditService cannot give two answers.
 *
 * WHERE EACH DIMENSION IS ENFORCED, and why not all four here. A fact is gated
 * once, in the language that owns it:
 *   - bridge and logo are file facts: tests/vitest/tokenSetCoverage.spec.js.
 *   - font has a PHP service because the admin UI needs the same verdict at
 *     runtime: tests/Unit/Service/TokenSetFontAuditTest.php.
 *   - contrast is PHP (relative luminance, colour parsing):
 *     tests/Unit/TokenSetContrastAuditTest.php.
 * This script prints all four together, because an administrator choosing a
 * theme cares about the set, not about which runtime measured what.
 * ========================================================================== */

const BRIDGE_CSS = 'css/systems/nldesign/utrecht-bridge.css'

const BRIDGE_STYLESHEET = 'systems/nldesign/utrecht-bridge'

const CONTRAST_JSON = 'docs/reference/contrast-report.json'

const COVERAGE_ALLOWLIST_PATH = join(
	REPO_ROOT,
	'tests/Unit/fixtures/token-set-coverage-allowlist.json',
)

const COVERAGE_REPORT_PATH = join(REPO_ROOT, 'docs/reference/token-set-coverage.md')

/** The four coverage dimensions, in the order they are reported. */
const DIMENSIONS = ['bridge', 'font', 'logo', 'contrast']

/**
 * The dimensions the gate FAILS on. Every dimension in `DIMENSIONS` is measured
 * and published; only these three are a bar.
 *
 * WHY `bridge` IS NOT A BAR, measured rather than assumed. The first version of
 * this audit barred it, reasoning that a set declaring none of the 87
 * `--utrecht-*` names "keeps the Rijkshuisstijl default, so every button, table
 * and form control is the wrong brand". That reasoning was wrong, and wrong in
 * the direction that overstates the defect.
 *
 * `css/systems/nldesign/utrecht-bridge.css` holds 84 declarations shaped
 * `--nldesign-component-X: var(--utrecht-Y, <fallback>)`. Counted outside
 * comments:
 *
 *   42  fall back to a `var(--nldesign-*)` token the SET declares
 *   38  fall back to a non-colour literal: 1rem, 1px, 0.5rem, 700,
 *       transparent, underline - geometry and type scale, not a brand colour
 *    3  fall back to a colour literal, and those three are #e5e5e5 / #696969
 *       (disabled button) and #ffffff (textbox fill)
 *    1  falls back to another var()
 *
 * Resolved over the real cascade (defaults -> set -> bridge), `amsterdam` and
 * `rijkshuisstijl` are BOTH bridge-zero and differ on 32 of the 84 component
 * tokens: Amsterdam's buttons compute #004699 and Rijkshuisstijl's #154273. So
 * a bridge-zero set adopts the NL Design System's component GEOMETRY and TYPE
 * SCALE while branding the component COLOURS from its own semantic layer. That
 * is a legitimate theme, and barring it withheld 8 working sets from the admin
 * picker.
 *
 * The figure is still worth measuring and publishing: a set with its own
 * `--utrecht-*` layer owns its radii, spacing and type scale, which is part of
 * conformance to a house style. It is reported, not gated.
 *
 * Demoting a dimension is the one edit that lowers the allow-list count with no
 * set improving, which is how a ratchet quietly stops ratcheting. So it is not a
 * silent edit: `coverageFindings()` fails unless every non-barred dimension
 * carries a written reason under `$reported` in the allow-list fixture, and
 * fails again if `$reported` names a dimension that is barred after all.
 */
const BARRED_DIMENSIONS = ['font', 'logo', 'contrast']

/**
 * Families the browser resolves with no webfont: the CSS generics, and the
 * faces an operating system supplies.
 *
 * Kept in step with TokenSetFontAuditService::SYSTEM_FAMILIES by
 * tests/vitest/tokenSetCoverage.spec.js, which reads both lists.
 */
const SYSTEM_FAMILIES = [
	'-apple-system',
	'Arial',
	'BlinkMacSystemFont',
	'Courier New',
	'cursive',
	'fantasy',
	'Georgia',
	'Helvetica',
	'Helvetica Neue',
	'inherit',
	'monospace',
	'sans-serif',
	'Segoe UI',
	'serif',
	'system-ui',
	'Tahoma',
	'Times New Roman',
	'Trebuchet MS',
	'ui-sans-serif',
	'Verdana',
]

/** Every custom property the given CSS declares, name => value. */
function allDeclarations(css) {
	const declarations = {}
	for (const match of stripComments(css).matchAll(
		/(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);/g,
	)) {
		declarations[match[1]] = match[2].replace(/\s*!\s*important\s*$/i, '').trim()
	}
	return declarations
}

/**
 * Follow a `var()` value to its literal, the way a browser would.
 *
 * Mirrors CssParserService::resolveVarChain(), same four-hop cap. Without it a
 * set that writes `--nldesign-font-family: var(--x)` reads as naming a family
 * called "var(--x)", which is indistinguishable from naming nothing.
 */
function resolveVarChain(value, declarations, depth = 0) {
	const trimmed = String(value).trim()
	const match = /^var\(\s*(--[\w-]+)\s*(?:,([\s\S]*))?\)$/.exec(trimmed)
	if (match === null) {
		return trimmed
	}
	if (depth >= 4) {
		return null
	}
	const reference = match[1]
	const fallback = (match[2] ?? '').trim()
	if (Object.prototype.hasOwnProperty.call(declarations, reference) === true) {
		return resolveVarChain(declarations[reference], declarations, depth + 1)
	}
	if (fallback !== '') {
		return resolveVarChain(fallback, declarations, depth + 1)
	}
	return null
}

/**
 * The `--utrecht-*` names the bridge reads, outside comments.
 *
 * Comments matter here: the bridge's own documentation writes
 * `--nldesign-component-X: var(--utrecht-Y, <fallback>)` as prose, and counting
 * `--utrecht-Y` would make the denominator 88 instead of 87.
 */
function bridgeTokenNames(root) {
	const css = stripComments(readFileSync(join(root, BRIDGE_CSS), 'utf8'))
	return [
		...new Set([...css.matchAll(/--utrecht-[A-Za-z0-9_-]+/g)].map((m) => m[0])),
	]
}

/** design-systems.json, parsed, or an empty list. */
function designSystems(root) {
	try {
		const parsed = JSON.parse(
			readFileSync(join(root, 'design-systems.json'), 'utf8'),
		)
		return Array.isArray(parsed) ? parsed : []
	} catch {
		return []
	}
}

/** The design system ids whose stylesheet stack links the Utrecht bridge. */
function bridgedSystems(root) {
	return designSystems(root)
		.filter(
			(system) =>
				(system.stylesheets ?? []).includes(BRIDGE_STYLESHEET) === true,
		)
		.map((system) => system.id)
}

/** The font families a design system's own linked stylesheets declare. */
function selfHostedFamilies(root, designSystem) {
	const families = new Set()
	for (const system of designSystems(root)) {
		if (system.id !== designSystem) {
			continue
		}
		for (const stylesheet of system.stylesheets ?? []) {
			const path = join(root, 'css', `${stylesheet}.css`)
			let css
			try {
				css = readFileSync(path, 'utf8')
			} catch {
				continue
			}
			for (const match of stripComments(css).matchAll(
				/font-family:\s*'([^']+)'/g,
			)) {
				families.add(match[1])
			}
		}
	}
	return families
}

/** The committed contrast verdicts, keyed by set id. */
function contrastVerdicts(root) {
	try {
		const parsed = JSON.parse(readFileSync(join(root, CONTRAST_JSON), 'utf8'))
		return parsed.sets ?? {}
	} catch {
		return {}
	}
}

/** Measure one set's four coverage dimensions. */
function coverageFor(root, id, meta, context) {
	const designSystem =
		typeof meta.design_system === 'string' ? meta.design_system : 'nldesign'
	const declarations = allDeclarations(
		readFileSync(join(root, 'css/tokens', `${id}.css`), 'utf8'),
	)

	// bridge: only meaningful for a design system that links the bridge.
	const bridgeApplies = context.bridgedSystems.includes(designSystem)
	const bridgeDeclared = context.bridgeTokens.filter(
		(name) => Object.prototype.hasOwnProperty.call(declarations, name) === true,
	).length

	// font: the FIRST family of the stack, var() chain resolved.
	const stack = declarations['--nldesign-font-family'] ?? null
	let family = null
	if (stack !== null) {
		const resolved = resolveVarChain(stack, declarations)
		if (resolved !== null) {
			const first = resolved
				.split(',')[0]
				.trim()
				.replace(/^["']|["']$/g, '')
			family = first === '' ? null : first
		}
	}
	const hosted = selfHostedFamilies(root, designSystem)
	let fontKind = 'undeclared'
	if (family === null || SYSTEM_FAMILIES.includes(family) === true) {
		fontKind = 'system'
	} else if (hosted.has(family) === true) {
		fontKind = 'self-hosted'
	} else if (
		meta.font !== undefined
		&& meta.font !== null
		&& typeof meta.font.licence === 'string'
		&& typeof meta.font.action === 'string'
		&& typeof meta.font.note === 'string'
	) {
		fontKind = 'declared'
	}

	// logo: what Nextcloud's theming is pointed at.
	const logo =
		typeof meta.theming?.logo === 'string' && meta.theming.logo !== ''
			? meta.theming.logo
			: null

	const contrast = context.contrast[id]?.verdict ?? 'not measured'

	return {
		id,
		designSystem,
		bridge: {
			applies: bridgeApplies,
			declared: bridgeDeclared,
			total: context.bridgeTokens.length,
			ok: bridgeApplies === false || bridgeDeclared > 0,
		},
		font: { family, kind: fontKind, ok: fontKind !== 'undeclared' },
		logo: { path: logo, ok: logo !== null },
		contrast: { verdict: contrast, ok: contrast === 'pass' },
	}
}

/** Measure every shipped set, ordered by id. */
function coverageAll(root) {
	const manifest = readManifest(root)
	const consumingSystems = nldesignConsumingSystems(root)
	const context = {
		bridgeTokens: bridgeTokenNames(root),
		bridgedSystems: bridgedSystems(root),
		contrast: contrastVerdicts(root),
	}

	const results = []
	for (const file of readdirSync(join(root, 'css/tokens'))) {
		if (file.endsWith('.css') === false) {
			continue
		}
		const id = basename(file, '.css')
		const meta = manifest[id] ?? {}
		const designSystem =
			typeof meta.design_system === 'string' ? meta.design_system : 'nldesign'

		// A set belonging to a design system that reads no --nldesign-* token at
		// all (`none`, `summer-breeze`) is judged by its own system's stack, not
		// by this vocabulary, exactly as stage 1 treats it.
		if (consumingSystems.has(designSystem) === false) {
			continue
		}

		// A token file with no entry in token-sets.json cannot be selected, so
		// no administrator ever sees it: css/tokens/conduction.css is the shared
		// role layer scripts/generate-brand-set.mjs copies into a brand set, not
		// a theme. Stage 1 still audits it, because its vocabulary matters to the
		// sets built from it; "what does an instance on it look like" has no
		// answer for a set nobody can apply.
		if (Object.prototype.hasOwnProperty.call(manifest, id) === false) {
			continue
		}

		results.push(coverageFor(root, id, meta, context))
	}

	results.sort((a, b) => a.id.localeCompare(b.id))
	return results
}

/** The coverage allow-list: dimension => { set id: reason }. */
function readCoverageAllowlist() {
	let parsed
	try {
		parsed = JSON.parse(readFileSync(COVERAGE_ALLOWLIST_PATH, 'utf8'))
	} catch {
		parsed = {}
	}

	const allowlist = {}
	for (const dimension of DIMENSIONS) {
		const entries = parsed[dimension]
		allowlist[dimension] =
			entries !== null
			&& typeof entries === 'object'
			&& Array.isArray(entries) === false
				? entries
				: {}
	}

	// The written reason each non-barred dimension carries, so demoting a
	// dimension out of the gate cannot be a silent edit.
	allowlist.$reported =
		parsed.$reported !== null
		&& typeof parsed.$reported === 'object'
		&& Array.isArray(parsed.$reported) === false
			? parsed.$reported
			: {}

	return allowlist
}

/**
 * Compare the coverage results against the allow-list.
 *
 * A ratchet that can only go down needs BOTH halves: a set below the bar that
 * nobody wrote down is a regression, and a set on the list that now passes is
 * progress that must be recorded by deleting the entry. Each entry also needs a
 * non-empty REASON, because "allow-listed" with no reason is how a baseline
 * becomes permanent.
 */
function coverageFindings(results, allowlist) {
	const unexpected = []
	const stale = []
	const reasonless = []

	for (const row of results) {
		for (const dimension of BARRED_DIMENSIONS) {
			const listed = Object.prototype.hasOwnProperty.call(
				allowlist[dimension],
				row.id,
			)
			if (row[dimension].ok === false && listed === false) {
				unexpected.push(
					`${dimension}/${row.id}: ${describeDimension(row, dimension)}`,
				)
			}
			if (row[dimension].ok === true && listed === true) {
				stale.push(`${dimension}/${row.id}`)
			}
		}
	}

	const known = new Set(results.map((row) => row.id))
	for (const dimension of DIMENSIONS) {
		for (const [id, reason] of Object.entries(allowlist[dimension])) {
			if (typeof reason !== 'string' || reason.trim().length < 20) {
				reasonless.push(`${dimension}/${id}`)
			}
			if (known.has(id) === false) {
				stale.push(`${dimension}/${id} (no such shipped set)`)
			}
			// An entry under a dimension nothing bars is dead weight: it
			// records a set as known-bad against a rule that cannot fail.
			if (BARRED_DIMENSIONS.includes(dimension) === false) {
				stale.push(
					`${dimension}/${id} (${dimension} is reported-only, so this entry gates nothing)`,
				)
			}
		}
	}

	// Demoting a dimension out of the gate must be written down, and a
	// dimension that is barred must not claim to be reported-only.
	for (const dimension of DIMENSIONS) {
		const recorded = allowlist.$reported?.[dimension]
		const barred = BARRED_DIMENSIONS.includes(dimension)
		if (
			barred === false
			&& (typeof recorded !== 'string' || recorded.trim().length < 20)
		) {
			reasonless.push(
				`$reported/${dimension} (measured but not gated, with no recorded reason)`,
			)
		}
		if (barred === true && recorded !== undefined) {
			stale.push(
				`$reported/${dimension} (this dimension IS gated — delete the entry)`,
			)
		}
	}

	return { unexpected, stale, reasonless }
}

/** One line saying what a dimension found for a set. */
function describeDimension(row, dimension) {
	if (dimension === 'bridge') {
		return `declares 0 of the ${row.bridge.total} --utrecht-* names the bridge reads, so every component keeps the Rijkshuisstijl default`
	}
	if (dimension === 'font') {
		return `names ${row.font.family}, which no stylesheet of design system "${row.designSystem}" serves and token-sets.json does not declare`
	}
	if (dimension === 'logo') {
		return 'points Nextcloud at no logo, so the Nextcloud logo stays in the header'
	}
	return `contrast verdict is "${row.contrast.verdict}", not pass`
}

/** The generated coverage reference page. */
function renderCoverageMarkdown(results, allowlist) {
	const lines = []
	lines.push(
		'<!-- GENERATED by scripts/audit-token-sets.mjs --markdown — do not edit by hand.',
	)
	lines.push('     Regenerate with: npm run audit:token-sets:report -->')
	lines.push('')
	lines.push('# Shipped Token-Set Instance Coverage')
	lines.push('')
	lines.push(
		'What an administrator sees after applying each shipped token set, measured',
	)
	lines.push(
		'rather than described. One row per set whose design system reads the',
	)
	lines.push('`--nldesign-*` vocabulary.')
	lines.push('')
	lines.push(
		'- **bridge** = of the `--utrecht-*` names `css/systems/nldesign/utrecht-bridge.css`',
	)
	lines.push(
		'  reads, how many the set declares. MEASURED AND REPORTED, NOT GATED: 42 of the',
	)
	lines.push(
		"  bridge's 84 declarations fall back to a `--nldesign-*` token the set itself",
	)
	lines.push(
		'  declares, 38 to a non-colour literal (1rem, 1px, transparent, 700) and 3 to a',
	)
	lines.push(
		'  colour literal, so a set at 0 adopts the design system component geometry and',
	)
	lines.push(
		'  type scale and still brands the component colours from its own semantic layer.',
	)
	lines.push(
		'  A higher figure means the set owns more of its own radii, spacing and type scale.',
	)
	lines.push(
		'- **font** = whether the first family the set names has an `@font-face` in a',
	)
	lines.push(
		'  stylesheet its own design system LINKS. `declared` means the family cannot be',
	)
	lines.push(
		'  redistributed and the set says so, with the action an administrator takes.',
	)
	lines.push('- **logo** = whether the set points Nextcloud theming at a logo.')
	lines.push(
		'- **contrast** = the verdict in `docs/reference/contrast-report.md`, same engine.',
	)
	lines.push('')
	lines.push(
		'A cell marked `(known)` is on `tests/Unit/fixtures/token-set-coverage-allowlist.json`',
	)
	lines.push(
		'with a recorded reason. The list can only shrink: the gate fails on a set below',
	)
	lines.push(
		'the bar that is not listed, on a listed set that has started passing, and on an',
	)
	lines.push('entry under a dimension nothing gates.')
	lines.push('')
	lines.push(
		'The gated dimensions are font, logo and contrast. `bridge` is measured and',
	)
	lines.push(
		'published but never fails, and the measurement behind that decision is recorded',
	)
	lines.push(
		'under `$reported.bridge` in the fixture: demoting a dimension is the one edit',
	)
	lines.push(
		'that lowers the allow-list count with nothing fixed, so it has to be written down.',
	)
	lines.push('')
	lines.push('| Token set | bridge | font | logo | contrast |')
	lines.push('|-----------|-------:|:-----|:----:|:--------:|')

	for (const row of results) {
		const cells = DIMENSIONS.map((dimension) => {
			const listed = Object.prototype.hasOwnProperty.call(
				allowlist[dimension],
				row.id,
			)
			const suffix =
				row[dimension].ok === false && listed === true ? ' (known)' : ''
			if (dimension === 'bridge') {
				return (
					(row.bridge.applies === false
						? 'n/a'
						: `${row.bridge.declared}/${row.bridge.total}`) + suffix
				)
			}
			if (dimension === 'font') {
				return `${row.font.kind}${suffix}`
			}
			if (dimension === 'logo') {
				return (row.logo.ok === true ? 'yes' : 'no') + suffix
			}
			return `${row.contrast.verdict}${suffix}`
		})
		lines.push(`| ${row.id} | ${cells.join(' | ')} |`)
	}

	lines.push('')
	for (const line of coverageSummaryLines(results)) {
		lines.push(`- ${line}`)
	}
	lines.push('')

	return lines.join('\n') + '\n'
}

/** The counting lines printed by the CLI and written into the report. */
function coverageSummaryLines(results) {
	const bridged = results.filter((row) => row.bridge.applies === true)
	const atZero = bridged.filter((row) => row.bridge.declared === 0).length
	const fontKinds = {}
	for (const row of results) {
		fontKinds[row.font.kind] = (fontKinds[row.font.kind] ?? 0) + 1
	}

	return [
		`${results.length} sets measured; ${
			results.filter((row) =>
				BARRED_DIMENSIONS.every((d) => row[d].ok === true),
			).length
		} pass every gated dimension (${BARRED_DIMENSIONS.join(', ')}).`,
		`bridge (measured, NOT gated): ${atZero} of ${bridged.length} bridged sets declare none of the ${
			bridged[0]?.bridge.total ?? 0
		} --utrecht-* names; median ${median(bridged.map((row) => row.bridge.declared))}.`,
		`font: ${Object.entries(fontKinds)
			.sort()
			.map(([kind, count]) => `${count} ${kind}`)
			.join(', ')}.`,
		`logo: ${results.filter((row) => row.logo.ok === false).length} of ${
			results.length
		} point Nextcloud at no logo.`,
		`contrast: ${results.filter((row) => row.contrast.ok === false).length} of ${
			results.length
		} do not pass.`,
	]
}

/** The median of a list of numbers (0 for an empty list). */
function median(values) {
	if (values.length === 0) {
		return 0
	}
	const sorted = [...values].sort((a, b) => a - b)
	const middle = Math.floor(sorted.length / 2)
	return sorted.length % 2 === 1
		? sorted[middle]
		: Math.round((sorted[middle - 1] + sorted[middle]) / 2)
}

/** Print the stage-2 coverage table. */
function printCoverage(results, allowlist) {
	console.log('')
	console.log(
		`${pad('Token set', 24)}${padStart('bridge', 8)}  ${pad('font', 13)}${pad('logo', 6)}${pad('contrast', 13)}Verdict`,
	)
	console.log('-'.repeat(24 + 8 + 2 + 13 + 6 + 13 + 7))

	for (const row of results) {
		const failing = DIMENSIONS.filter((dimension) => row[dimension].ok === false)
		const known = failing.filter((dimension) =>
			Object.prototype.hasOwnProperty.call(allowlist[dimension], row.id),
		)
		let verdict = 'ready'
		if (failing.length > 0) {
			verdict =
				known.length === failing.length
					? `incomplete (known: ${failing.join(',')})`
					: `INCOMPLETE (${failing.filter((d) => known.includes(d) === false).join(',')})`
		}

		console.log(
			pad(row.id, 24)
				+ padStart(
					row.bridge.applies === false
						? 'n/a'
						: `${row.bridge.declared}/${row.bridge.total}`,
					8,
				)
				+ '  '
				+ pad(row.font.kind, 13)
				+ pad(row.logo.ok === true ? 'yes' : 'no', 6)
				+ pad(row.contrast.verdict, 13)
				+ verdict,
		)
	}

	console.log('')
	for (const line of coverageSummaryLines(results)) {
		console.log(`[${LABEL}] ${line}`)
	}
}

function main() {
	const args = process.argv.slice(2)
	const check = args.includes('--check')
	const asJson = args.includes('--json')
	const verbose = args.includes('--verbose')

	const markdown = args.includes('--markdown')

	const results = auditAll(REPO_ROOT)
	const allowlist = readAllowlist()
	const coverage = coverageAll(REPO_ROOT)
	const coverageAllowlist = readCoverageAllowlist()
	const coverage2 = coverageFindings(coverage, coverageAllowlist)

	const audited = results.filter((row) => row.auditable === true)
	const incomplete = audited.filter((row) => row.complete === false)
	const unexpected = incomplete
		.filter((row) => allowlist.includes(row.id) === false)
		.map((row) => row.id)
	const staleAllowlist = allowlist.filter(
		(id) =>
			audited.some((row) => row.id === id && row.complete === false) === false,
	)

	if (markdown === true) {
		const rendered = renderCoverageMarkdown(coverage, coverageAllowlist)
		if (check === true) {
			let committed = null
			try {
				committed = readFileSync(COVERAGE_REPORT_PATH, 'utf8')
			} catch {
				committed = null
			}
			if (committed !== rendered) {
				console.error(
					`[${LABEL}] docs/reference/token-set-coverage.md is stale — run \`npm run audit:token-sets:report\``,
				)
				process.exit(1)
			}
			console.log(
				`[${LABEL}] docs/reference/token-set-coverage.md is up to date.`,
			)
			return
		}
		writeFileSync(COVERAGE_REPORT_PATH, rendered)
		console.log(`[${LABEL}] wrote docs/reference/token-set-coverage.md.`)
		return
	}

	if (asJson === true) {
		console.log(
			JSON.stringify(
				{
					results,
					allowlist,
					unexpected,
					staleAllowlist,
					coverage,
					coverageAllowlist,
					coverageFindings: coverage2,
				},
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

		printCoverage(coverage, coverageAllowlist)
		const listed = DIMENSIONS.reduce(
			(total, dimension) =>
				total + Object.keys(coverageAllowlist[dimension]).length,
			0,
		)
		console.log(
			`[${LABEL}] coverage allow-list: ${listed} entr(ies), each with a recorded reason.`,
		)
		for (const finding of coverage2.unexpected) {
			console.log(`[${LABEL}] NOT allow-listed: ${finding}`)
		}
		for (const finding of coverage2.stale) {
			console.log(
				`[${LABEL}] coverage allow-list entry that now passes (delete it): ${finding}`,
			)
		}
		for (const finding of coverage2.reasonless) {
			console.log(
				`[${LABEL}] coverage allow-list entry with no usable reason: ${finding}`,
			)
		}
	}

	const failed =
		unexpected.length > 0
		|| staleAllowlist.length > 0
		|| coverage2.unexpected.length > 0
		|| coverage2.stale.length > 0
		|| coverage2.reasonless.length > 0

	if (check === true && failed === true) {
		process.exit(1)
	}
}

// Exported so tests/vitest/tokenSetCoverage.spec.js can run the same functions
// the CLI runs, rather than a second copy of the rules.
export {
	auditAll,
	coverageAll,
	coverageFindings,
	readAllowlist,
	readCoverageAllowlist,
	renderCoverageMarkdown,
	BARRED_DIMENSIONS,
	DIMENSIONS,
	REPO_ROOT,
	REQUIRED_TOKENS,
	SYSTEM_FAMILIES,
}

// `main()` only when this file IS the command, so importing it in a test does
// not run the audit (and does not call process.exit).
if (
	process.argv[1] !== undefined
	&& basename(process.argv[1]) === 'audit-token-sets.mjs'
) {
	main()
}
