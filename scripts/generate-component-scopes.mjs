#!/usr/bin/env node

/**
 * Turn the component-token mapping into the two stylesheets that apply it.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * WHY THIS EXISTS
 *
 * Nextcloud has around sixty global variables and every component draws from
 * them, so `--color-primary-element` paints the primary button, the selected
 * navigation entry, the sidebar's active tab, a focused text input, the checked
 * checkbox, the progress bar, the dialog's confirm button and the counter
 * bubble. The token editor could only write those globals, which is why moving
 * `Primary button` in the playground moved all eight at once.
 *
 * `scripts/mapping/component-tokens.json` says, for each component, which
 * global it consumes and which component token replaces it. This generator
 * turns that table into CSS so the mapping is stated once and the stylesheets
 * cannot drift from the registry PHP builds out of the same file.
 *
 * WHAT IT EMITS
 *
 *   css/component-scopes.css  the per-component re-scope, always loaded
 *   css/primary-lock.css      the `primary_drives_components` toggle's layer
 *
 * Neither file declares a component token. They only redirect which value an
 * already-existing Nextcloud variable resolves to, so an instance that has
 * never set a component token renders byte-identically to one without this
 * layer at all.
 *
 * THE `--thematiq-global-*` CAPTURE IS LOAD-BEARING
 *
 * A scope rule cannot say
 *
 *     --color-primary-element: var(--X, var(--color-primary-element))
 *
 * because a custom property whose value depends on itself is discarded as
 * invalid — the same cycle `StockTokensService` documents for
 * `--nldesign-color-primary`. So `:root` copies each global under a
 * `--thematiq-global-*` name first and the scopes fall back to the copy. The
 * copy resolves at `:root`, where the global still holds its ordinary value,
 * so there is no cycle and an unset component token is indistinguishable from
 * stock.
 *
 * Note that the capture is deliberately NOT `!important`. custom-overrides.css
 * writes admin-set globals at `:root` with `!important` and loads later, so the
 * capture has to lose to it: an admin who moves the brand primary must move
 * every component that has not been given a value of its own.
 *
 * WHY THE SCOPES DO NOT NEED `!important`
 *
 * Custom properties cascade per element. A declaration on `.app-navigation`
 * beats one inherited from `:root` whatever its importance, because inheritance
 * only supplies a value to an element that has no declaration of its own. So
 * these rules win inside their subtree without competing on weight, and
 * Nextcloud's own stylesheets keep doing the painting.
 */

import { readFileSync, writeFileSync, mkdtempSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'
import { tmpdir } from 'os'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..')
const MAPPING_PATH = join(ROOT, 'scripts/mapping/component-tokens.json')
const SCOPES_PATH = join(ROOT, 'css/component-scopes.css')
const LOCK_PATH = join(ROOT, 'css/primary-lock.css')

const mapping = JSON.parse(readFileSync(MAPPING_PATH, 'utf-8'))

/**
 * The `--thematiq-global-*` name a global is captured under.
 *
 * @param {string} global A Nextcloud custom property name, with its leading dashes.
 *
 * @return {string} The capture name.
 */
function captureName(global) {
	return mapping.capturePrefix + global.replace(/^--/, '')
}

/*
 * A component that mapped two of its tokens onto the SAME global would emit two
 * declarations of one property in one rule, and the second would silently win —
 * one of the two chips' controls would do nothing. Caught here rather than in a
 * browser.
 */
const collisions = []
for (const [id, component] of Object.entries(mapping.components)) {
	const seen = new Map()
	for (const [name, token] of Object.entries(component.tokens)) {
		if (seen.has(token.global) === true) {
			collisions.push(
				id
					+ ': '
					+ seen.get(token.global)
					+ ' and '
					+ name
					+ ' both re-scope '
					+ token.global,
			)
		}
		seen.set(token.global, name)
	}
}
if (collisions.length > 0) {
	process.stderr.write(
		'generate-component-scopes: a component maps two tokens onto one global.\n  '
			+ collisions.join('\n  ')
			+ '\n',
	)
	process.exit(1)
}

// REUSE-IgnoreStart -- the SPDX tags below are DATA, not this file's own: they
// are the header written INTO the two generated stylesheets. Same licence as
// this file, but REUSE should read it off the emitted files, not twice here.
const BANNER = [
	'/**',
	' * GENERATED FILE — do not edit.',
	' *',
	' * SPDX-License-Identifier: EUPL-1.2',
	' * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>',
	' *',
	' * Source: scripts/mapping/component-tokens.json',
	' * Regenerate: npm run generate:component-scopes',
	' */',
	'',
]
// REUSE-IgnoreEnd

/* ------------------------------------------------------------------ scopes */

const scopes = [
	...BANNER,
	'/*',
	' * Component-scoped tokens.',
	' *',
	" * Each rule below redeclares, inside one component's subtree, the Nextcloud",
	' * variable that component consumes — so the component follows its own token',
	" * when one is set and the brand-wide global when one is not. Nextcloud's own",
	' * stylesheets keep doing the painting; only the value they see changes.',
	' *',
	' * The :root block captures each global under a name the scopes can fall back',
	' * to. Falling back to the global directly would be a self-referential custom',
	' * property, which CSS discards as invalid.',
	' */',
	'',
	':root {',
]

const captured = []
for (const component of Object.values(mapping.components)) {
	for (const token of Object.values(component.tokens)) {
		if (captured.includes(token.global) === false) {
			captured.push(token.global)
		}
	}
}
for (const global of captured) {
	scopes.push('\t' + captureName(global) + ': var(' + global + ');')
}

/*
 * The playground stage, named after the component it is drawing.
 *
 * A specimen carries the component's real CLASS names, which is enough for
 * every component whose rules are class-scoped. It is not enough for the four
 * that are id-scoped -- `#header` and the three under `#body-login` -- because
 * a specimen cannot carry an id belonging to the page it is drawn on. Listing
 * the stage attribute beside the real selectors lets the specimen resolve the
 * same tokens the real component does. js/playground.js sets the attribute.
 */
function specimenSelector(id) {
	return "[data-thematiq-component='" + id + "']"
}

/**
 * The `--thematiq-alias-*` name an aliased component token is captured under.
 *
 * @param {string} target The `--nldesign-component-*` name being redirected.
 *
 * @return {string} The capture name.
 */
function aliasCaptureName(target) {
	return '--thematiq-alias-' + target.replace(/^--/, '')
}

/*
 * Aliases: a component whose own rules read ANOTHER component's tokens.
 *
 * Re-scoping a Nextcloud global only works where a Nextcloud rule reads it. The
 * login button is painted by thematiq itself, from the PRIMARY button's tokens,
 * so there is no global left in the chain to redirect — and a more specific
 * rule is not an option either: the label is claimed at (1,3,0) by
 * `#body-login …:not(…) .button-vue__text` and by its `#content` twin, which
 * nothing this layer can write outranks.
 *
 * So the redirect happens one level up: inside the component, the token its
 * rules read IS its own token. The rules that already win then resolve to the
 * right value untouched — on the real page and on the playground specimen
 * alike, which is what makes the specimen honest without a single
 * playground-specific selector in the design-system stylesheet.
 *
 * Captured for the same reason the globals are: `X: var(Y, var(X))` on one
 * element is self-referential, and CSS discards it.
 */
function aliasesOf(component) {
	return component.aliases === undefined ? {} : component.aliases
}

const aliasTargets = []
for (const component of Object.values(mapping.components)) {
	for (const target of Object.keys(aliasesOf(component))) {
		if (aliasTargets.includes(target) === false) {
			aliasTargets.push(target)
		}
	}
}

if (aliasTargets.length > 0) {
	scopes.push(
		'',
		'\t/* Component tokens that a component redirects onto its own. */',
	)
	for (const target of aliasTargets) {
		scopes.push('\t' + aliasCaptureName(target) + ': var(' + target + ');')
	}
}
scopes.push('}', '')

for (const [id, component] of Object.entries(mapping.components)) {
	scopes.push('/* ' + id + ' */')
	scopes.push(component.selectors.concat(specimenSelector(id)).join(',\n') + ' {')
	for (const [name, token] of Object.entries(component.tokens)) {
		scopes.push('\t' + token.global + ': var(')
		scopes.push('\t\t' + name + ',')
		scopes.push('\t\tvar(' + captureName(token.global) + ')')
		scopes.push('\t);')
	}
	for (const [target, source] of Object.entries(aliasesOf(component))) {
		scopes.push('\t' + target + ': var(')
		scopes.push('\t\t' + source + ',')
		scopes.push('\t\tvar(' + aliasCaptureName(target) + ')')
		scopes.push('\t);')
	}
	scopes.push('}', '')
}

const scopesOutput = scopes.join('\n')

/* -------------------------------------------------------------------- lock */

const lock = [
	...BANNER,
	'/*',
	' * The `primary_drives_components` toggle, as a stylesheet.',
	' *',
	' * Emitted by CssInjectionService only while the admin setting is on, and',
	' * emitted AFTER custom-overrides.css so it outranks any per-component value',
	' * already stored there. Nothing is deleted: turning the setting back off',
	' * drops this layer and the stored values take effect again.',
	' *',
	' * Only the tokens flagged `primary` in the mapping appear here — the ones the',
	' * brand primary used to drive before components could be themed separately.',
	' * A component token for a border radius or a font weight is untouched, and',
	' * stays editable while the toggle is on.',
	' */',
	'',
	':root {',
]

for (const [id, component] of Object.entries(mapping.components)) {
	const locked = Object.entries(component.tokens).filter(
		([, token]) => token.primary === true,
	)
	if (locked.length === 0) {
		continue
	}
	lock.push('\t/* ' + id + ' */')
	for (const [name, token] of locked) {
		lock.push(
			'\t' + name + ': var(' + captureName(token.global) + ') !important;',
		)
	}
}
lock.push('}', '')

const lockOutput = lock.join('\n')

/* ------------------------------------------------------------------- write */

const outputs = [
	{ path: SCOPES_PATH, label: 'css/component-scopes.css', content: scopesOutput },
	{ path: LOCK_PATH, label: 'css/primary-lock.css', content: lockOutput },
]

if (process.argv.includes('--check') === false) {
	for (const output of outputs) {
		writeFileSync(output.path, output.content, 'utf-8')
		process.stdout.write(
			'generate-component-scopes: wrote ' + output.label + '\n',
		)
	}
	process.exit(0)
}

let stale = false
for (const output of outputs) {
	let committed = ''
	try {
		committed = readFileSync(output.path, 'utf-8')
	} catch {
		process.stderr.write(
			'generate-component-scopes: ' + output.label + ' is missing.\n',
		)
		stale = true
		continue
	}

	if (committed === output.content) {
		continue
	}

	stale = true
	const temporary = join(
		mkdtempSync(join(tmpdir(), 'thematiq-scopes-')),
		output.label.replace('css/', ''),
	)
	writeFileSync(temporary, output.content, 'utf-8')

	const committedLines = committed.split('\n')
	const generatedLines = output.content.split('\n')
	for (
		let i = 0;
		i < Math.max(committedLines.length, generatedLines.length);
		i++
	) {
		if (committedLines[i] === generatedLines[i]) {
			continue
		}
		process.stderr.write(
			'generate-component-scopes: '
				+ output.label
				+ ' is stale.\n'
				+ '  first difference at line '
				+ (i + 1)
				+ '\n'
				+ '  committed: '
				+ (committedLines[i] ?? '<end of file>')
				+ '\n'
				+ '  generated: '
				+ (generatedLines[i] ?? '<end of file>')
				+ '\n'
				+ '  regenerated to '
				+ temporary
				+ '\n',
		)
		break
	}
}

if (stale === true) {
	process.exit(1)
}

process.stdout.write('generate-component-scopes: OK — both committed files match.\n')
process.exit(0)
