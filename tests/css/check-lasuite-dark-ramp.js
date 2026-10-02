#!/usr/bin/env node
/**
 * La Suite dark-ramp guard.
 *
 * css/systems/lasuite/element-overrides.css paints the shell with
 * `!important`, so the shared `--nldesign-*` dark layer cannot reach it. Its
 * dark mode works only because every ground-dependent value goes through a
 * token the file's own dark block remaps (the Cunningham contextuals and the
 * `--lasuite-content-*` aliases). Two mistakes break that silently, and the
 * page then renders a white header on a dark instance with nothing in any log:
 *
 *   1. a rule reads a raw `--lasuite-color-gray-*` step directly, which keeps
 *      its light value in dark mode;
 *   2. a rule reads a contextual or alias token that one of the two dark scope
 *      blocks (the `prefers-color-scheme` media block and the explicit
 *      `data-theme` block) does not remap.
 *
 * The guard also refuses any write to the Nextcloud variables reserved for its
 * own dark-mode derivation (REQ-CSS-007).
 *
 * Usage:
 *   node tests/css/check-lasuite-dark-ramp.js [file]
 * Exit codes: 0 clean, 1 findings.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/dark-mode/spec.md#requirement-a-design-system-whose-overrides-consume-its-own-token-ramp-shall-ship-a-dark-counterpart-for-that-ramp
 */

'use strict'

const fs = require('fs')
const path = require('path')

/** Kept in step with lib/Service/CustomCssValidator.php RESERVED_VARIABLES. */
const RESERVED = [
	'--color-main-background',
	'--color-main-background-rgb',
	'--color-main-background-translucent',
	'--color-main-background-blur',
	'--color-background-plain',
	'--color-background-plain-text',
	'--background-invert-if-dark',
	'--background-invert-if-bright',
]

/** Properties whose value is painted against the ground. */
const COLOR_PROPERTY =
	/^(color|background(-color)?|border(-(top|right|bottom|left|block|inline)(-(start|end))?)?(-color)?|outline(-color)?|box-shadow|fill|stroke|caret-color|text-decoration-color|column-rule(-color)?)$/

/** Tokens a dark block has to remap when a rule reads them. */
const REMAPPED_TOKEN = /^--lasuite(--contextuals--|-content-|-active-row-wash$)/

/** A raw light-ramp step. Brand steps are not ground-dependent and stay. */
const RAW_GRAY = /^--lasuite-color-gray-\d+$/

/**
 * Raw gray reads that are right in both modes, each with its reason.
 * Keyed `selector-start|property`.
 */
const ALLOWED_RAW = [
	{
		selector: '#header .avatardiv',
		property: 'color',
		reason: 'initials text on the avatar colour fill, which is the same in both modes',
	},
]

/** Remove comments, keeping offsets irrelevant. */
function stripComments(css) {
	return css.replace(/\/\*[\s\S]*?\*\//g, '')
}

/**
 * Split a stylesheet into flat rules, carrying the at-rule prelude they sit in.
 *
 * @param {string} css Comment-free CSS.
 * @param {string} context The enclosing at-rule prelude, '' at top level.
 * @return {{selector: string, body: string, context: string}[]}
 */
function parseRules(css, context = '') {
	const rules = []
	let i = 0
	while (i < css.length) {
		const open = css.indexOf('{', i)
		if (open === -1) {
			break
		}
		const prelude = css.slice(i, open).trim()
		let depth = 1
		let j = open + 1
		while (j < css.length && depth > 0) {
			if (css[j] === '{') {
				depth++
			} else if (css[j] === '}') {
				depth--
			}
			j++
		}
		const body = css.slice(open + 1, j - 1)
		if (prelude.startsWith('@')) {
			rules.push(...parseRules(body, prelude))
		} else {
			rules.push({ selector: prelude.replace(/\s+/g, ' '), body, context })
		}
		i = j
	}
	return rules
}

/**
 * Split a rule body into declarations. Values hold parentheses but no braces.
 *
 * @param {string} body The rule body.
 * @return {{property: string, value: string}[]}
 */
function parseDeclarations(body) {
	const out = []
	let depth = 0
	let start = 0
	for (let i = 0; i <= body.length; i++) {
		const c = body[i]
		if (c === '(') {
			depth++
		} else if (c === ')') {
			depth--
		} else if ((c === ';' && depth === 0) || i === body.length) {
			const decl = body.slice(start, i).trim()
			start = i + 1
			const colon = decl.indexOf(':')
			if (colon > 0) {
				out.push({
					property: decl.slice(0, colon).trim(),
					value: decl
						.slice(colon + 1)
						.replace(/!important/, '')
						.trim(),
				})
			}
		}
	}
	return out
}

/**
 * The custom properties a value actually reads: every `var()` name that is not
 * itself the fallback of another `var()`. A fallback is used only when the
 * token before it is unset, so it says nothing about what renders.
 *
 * @param {string} value A declaration value.
 * @return {string[]}
 */
function primaryVars(value) {
	const names = []
	let i = 0
	while (i < value.length) {
		const at = value.indexOf('var(', i)
		if (at === -1) {
			break
		}
		let j = at + 4
		while (j < value.length && value[j] !== ',' && value[j] !== ')') {
			j++
		}
		names.push(value.slice(at + 4, j).trim())
		// Skip the whole var(...) including its fallback.
		let depth = 1
		let k = at + 4
		while (k < value.length && depth > 0) {
			if (value[k] === '(') {
				depth++
			} else if (value[k] === ')') {
				depth--
			}
			k++
		}
		i = k
	}
	return names
}

function isMediaDark(rule) {
	return /prefers-color-scheme:\s*dark/.test(rule.context)
}

function isExplicitDark(rule) {
	return (
		rule.context === ''
		&& /data-themes\*=['"]dark['"]|data-theme-dark\]/.test(rule.selector)
	)
}

/**
 * Check one stylesheet.
 *
 * @param {string} css The stylesheet source.
 * @return {{findings: string[], remapped: {media: Set<string>, explicit: Set<string>}}}
 */
function checkDarkRamp(css) {
	const rules = parseRules(stripComments(css))
	const media = new Set()
	const explicit = new Set()
	const findings = []

	for (const rule of rules) {
		const target = isMediaDark(rule)
			? media
			: isExplicitDark(rule)
				? explicit
				: null
		for (const decl of parseDeclarations(rule.body)) {
			if (RESERVED.includes(decl.property)) {
				findings.push(
					`${rule.selector}: writes ${decl.property}, reserved for Nextcloud dark-mode derivation (REQ-CSS-007)`,
				)
			}
			if (target !== null && decl.property.startsWith('--')) {
				target.add(decl.property)
			}
		}
	}

	for (const rule of rules) {
		if (isMediaDark(rule) || isExplicitDark(rule)) {
			continue
		}
		for (const decl of parseDeclarations(rule.body)) {
			if (!COLOR_PROPERTY.test(decl.property)) {
				continue
			}
			for (const name of primaryVars(decl.value)) {
				if (RAW_GRAY.test(name)) {
					const allowed = ALLOWED_RAW.some(
						(a) =>
							rule.selector.startsWith(a.selector)
							&& a.property === decl.property,
					)
					if (!allowed) {
						findings.push(
							`${rule.selector} { ${decl.property} }: reads ${name} directly, so it keeps its light value in dark mode; read a contextual token the dark block remaps`,
						)
					}
				} else if (REMAPPED_TOKEN.test(name)) {
					for (const [label, set] of [
						['prefers-color-scheme', media],
						['data-theme', explicit],
					]) {
						if (!set.has(name)) {
							findings.push(
								`${rule.selector} { ${decl.property} }: reads ${name}, which the ${label} dark block does not remap`,
							)
						}
					}
				}
			}
		}
	}

	return { findings, remapped: { media, explicit } }
}

module.exports = {
	checkDarkRamp,
	parseRules,
	parseDeclarations,
	primaryVars,
	RESERVED,
}

if (require.main === module) {
	const file = path.resolve(
		process.cwd(),
		process.argv[2] || 'css/systems/lasuite/element-overrides.css',
	)
	const { findings } = checkDarkRamp(fs.readFileSync(file, 'utf8'))
	if (findings.length === 0) {
		console.log(
			`lasuite-dark-ramp: OK, every ground-dependent read in ${path.basename(file)} has a dark value`,
		)
		process.exit(0)
	}
	console.error(
		`lasuite-dark-ramp: FAIL, ${findings.length} finding(s) in ${path.basename(file)}:`,
	)
	for (const f of findings) {
		console.error('  ' + f)
	}
	process.exit(1)
}
