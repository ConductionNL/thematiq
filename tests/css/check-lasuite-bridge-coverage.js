#!/usr/bin/env node
// SPDX-License-Identifier: EUPL-1.2
// SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
//
// lasuite bridge coverage check.
//
// css/systems/nldesign/overrides.css is the audited Nextcloud `--color-*`
// variable surface (the `nextcloud-variable-mapping` canonical audit — 68
// variables, mapped or reasoned-comment). This script asserts that
// css/systems/lasuite/bridge.css accounts for every one of those 68
// variables too — as an active mapping OR a commented line with a reason —
// so the lasuite bridge can never silently leave a Nextcloud variable at its
// stock value. Also enforces REQ-CSS-007: the six dark-mode-compatibility
// variables must never be ACTIVELY set by the bridge (comment-only).
//
// Mirrors tests/l10n/check-l10n-completeness.js's dependency-free, pure-Node
// pattern so it needs no build step and no Nextcloud runtime.
//
// Usage:
//   node tests/css/check-lasuite-bridge-coverage.js
//
// Exit codes:
//   0  every audited --color-* variable is accounted for in bridge.css
//   1  one or more audited variables are missing from bridge.css (or a
//      dark-mode-compat variable is actively overridden)
//
// @spec openspec/specs/lasuite-parity/spec.md

'use strict'

const fs = require('fs')
const path = require('path')

const ROOT = path.resolve(__dirname, '..', '..')
const AUDITED_FILE = path.join(ROOT, 'css', 'systems', 'nldesign', 'overrides.css')
const BRIDGE_FILE = path.join(ROOT, 'css', 'systems', 'lasuite', 'bridge.css')
const STATUS_FILE = path.join(ROOT, 'scripts', 'mapping', 'variable-status.json')
const THEME_SCOPES_FILE = path.join(ROOT, 'css', 'theme-scopes.css')
const DESIGN_SYSTEMS_FILE = path.join(ROOT, 'design-systems.json')

// REQ-CSS-007: Nextcloud derives dark-mode calculations from these — the
// bridge must never actively set them, only ever leave them as a reasoned
// comment.
const DARK_MODE_COMPAT_VARS = [
	'--color-main-background',
	'--color-main-background-rgb',
	'--color-main-background-translucent',
	'--color-background-plain',
	'--background-invert-if-dark',
	'--background-invert-if-bright',
]

/**
 * Extract the set of `--color-*` variable names that appear in `text` either
 * as an active declaration (`--color-x: ...;` at the start of a line) or as
 * a commented-out declaration (`/* --color-x: ... `).
 */
function extractColorVarNames(text) {
	const mapped = new Set()
	const commented = new Set()

	for (const line of text.split('\n')) {
		const activeMatch = line.match(/^\s*(--color-[a-zA-Z0-9-]+)\s*:/)
		if (activeMatch) {
			mapped.add(activeMatch[1])
			continue
		}
		const commentMatch = line.match(/\/\*\s*(--color-[a-zA-Z0-9-]+)\s*:/)
		if (commentMatch) {
			commented.add(commentMatch[1])
		}
	}

	return { mapped, commented, all: new Set([...mapped, ...commented]) }
}

function readFile(p) {
	if (!fs.existsSync(p)) {
		console.error(`[lasuite-bridge-coverage] file not found: ${p}`)
		process.exit(2)
	}
	return fs.readFileSync(p, 'utf8')
}

function main() {
	const audited = extractColorVarNames(readFile(AUDITED_FILE))
	const bridge = extractColorVarNames(readFile(BRIDGE_FILE))

	const missing = [...audited.all].filter((name) => !bridge.all.has(name)).sort()

	const activelyOverriddenCompatVars = DARK_MODE_COMPAT_VARS.filter((name) =>
		bridge.mapped.has(name),
	)

	let failed = false

	if (missing.length > 0) {
		failed = true
		console.error(
			`[lasuite-bridge-coverage] FAIL — ${missing.length} audited --color-* variable(s) missing from bridge.css (neither mapped nor commented):`,
		)
		for (const name of missing) {
			console.error(`  - ${name}`)
		}
	}

	if (activelyOverriddenCompatVars.length > 0) {
		failed = true
		console.error(
			'[lasuite-bridge-coverage] FAIL — REQ-CSS-007 violation: the following dark-mode-compatibility variables are ACTIVELY set in bridge.css (must be comment-only):',
		)
		for (const name of activelyOverriddenCompatVars) {
			console.error(`  - ${name}`)
		}
	}

	// theme-vocabulary-complete: the settable theme variables reach lasuite
	// through css/theme-scopes.css, which CssInjectionService loads for every
	// design system. Each one must be redeclared there. A bridge mapping of one
	// (lasuite maps the three hover colours) stays valid: it is declared on
	// body, so the capture reads it as the value to fall back to.
	const settable = Object.entries(JSON.parse(readFile(STATUS_FILE)).variables)
		.filter(([, entry]) => entry.status === 'settable')
		.map(([name]) => name)
	const themeScopes = readFile(THEME_SCOPES_FILE)
	const notScoped = settable.filter((name) => new RegExp('^\\t' + name + ': var\\(--thematiq-global-', 'm').test(themeScopes) === false)
	const lasuiteLoaded = JSON.parse(readFile(DESIGN_SYSTEMS_FILE)).some((system) => system.id === 'lasuite')
	if (notScoped.length > 0 || lasuiteLoaded === false) {
		failed = true
		console.error(`[lasuite-bridge-coverage] FAIL: settable theme variables not redeclared in theme-scopes.css: ${notScoped.join(', ') || 'none'}`)
	}

	if (failed) {
		process.exit(1)
	}

	console.log(`[lasuite-bridge-coverage] OK: ${settable.length} settable theme variables reach lasuite through css/theme-scopes.css.`)

	console.log(
		`[lasuite-bridge-coverage] OK — all ${audited.all.size} audited --color-* variables are accounted for in bridge.css (${bridge.mapped.size} mapped, ${bridge.commented.size} reasoned comments), and no dark-mode-compat variable is actively overridden.`,
	)
	process.exit(0)
}

main()
