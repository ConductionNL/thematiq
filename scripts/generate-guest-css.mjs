#!/usr/bin/env node

/**
 * Scope Nextcloud's own login-page stylesheet into the component playground.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * WHY THIS EXISTS
 *
 * The playground draws its specimens on `/settings/admin/theming`, and that
 * page already loads the shipped CSS for most of what they stand for: the
 * NcButton, NcInputField and NcCheckboxRadioSwitch chunks are all imported by
 * `dist/theming-settings-admin.css`. A specimen that carries the right class
 * names and the right Vue scope attribute is painted by the component's own
 * stylesheet rather than by a transcription of it.
 *
 * `core/css/guest.css` is the exception: Nextcloud emits it on the login page
 * and nowhere else. It is also the file that owns everything an admin actually
 * looks at on a login specimen — the card (`.guest-box`: the translucent
 * surface, the blur, `--border-radius-container`, the shadow), the logo's
 * 175x130 box, the `h2`, the bold links, the footer that carries the slogan.
 * So that part of the login page could only ever be hand-drawn here, and a
 * hand-drawn card is right on the day it is written and quietly wrong after
 * the next release.
 *
 * This generator re-emits every rule in the vendored upstream file under one
 * scope, so the login specimen is painted by core's own declarations without a
 * single one of them reaching the settings page around it.
 *
 * THE SCOPE IS WRAPPED IN `:where()`, and that is load-bearing
 *
 * A plain prefix would not just place these rules, it would PROMOTE them. On
 * the real login page `button { background-color: var(--color-main-background) }`
 * is one element selector, specificity (0,0,1), and it loses to NcButton's own
 * `.button-vue[data-v-…]` at (0,2,0) — which is why the log-in button is not a
 * white box. Prefixing that rule with an id and a class would make it (1,2,0)
 * and it would start winning, inverting the cascade the whole file was written
 * inside. `:where()` contributes zero specificity, so every rule below keeps
 * exactly the weight it has upstream and the component stylesheets keep beating
 * it exactly where they beat it on the real page.
 *
 * THE SIX REWRITES, and why each one is safe
 *
 *  1. `html` is dropped. Its only declaration is `height: 100%`, which means
 *     nothing on an element that is not the document.
 *  2. `body` becomes the scope root, minus the declarations that only mean
 *     something on the viewport element (see VIEWPORT_ONLY). The background is
 *     among them on purpose: the stage already paints the login ground with
 *     core's own two declarations, and a second painter inside it would draw
 *     the instance's background image again at a different offset.
 *  3. `#body-login` becomes the scope root too — on the real page that id IS
 *     the body, and the specimen's page area is what stands in for it.
 *  4. `#header` becomes `.header-guest`. The guest layout always gives that
 *     element both, and the settings page already has an element with the id
 *     `header`; emitting a second one would be a duplicate id in a document
 *     whose own scripts look that id up.
 *  5. `@keyframes` blocks are RENAMED, not dropped. A keyframe name is global
 *     and cannot be scoped, so re-emitting `rotate` verbatim would redefine it
 *     for the whole settings page — but dropping it left the login card's
 *     spinner referring to a name nothing on this page defines, so the one
 *     specimen whose entire point is that it moves stood still. Every name is
 *     re-emitted under `nldesign-pg-guest-`, and the `animation` declarations
 *     that referred to it are repointed at the new name.
 *  6. A `prefers-reduced-motion: reduce` block is APPENDED, under the scope.
 *     The source has none of its own and two of its declarations animate here,
 *     one of them an infinite spinner. See reducedMotion().
 *
 * Relative `url()` references are re-expressed: the source resolves them
 * against `core/css/`, the output is read from this app's `css/` directory.
 *
 * Usage:
 *   node scripts/generate-guest-css.mjs [outputPath]
 *   node scripts/generate-guest-css.mjs --check
 *
 * `--check` regenerates to a temporary file and diffs it against the committed
 * `css/playground-guest.css`, exiting non-zero on any difference — the same
 * check/--write shape as scripts/generate-lasuite-tokens.mjs.
 */

import { readFileSync, writeFileSync, mkdtempSync } from 'fs'
import { join, dirname, resolve, posix } from 'path'
import { fileURLToPath } from 'url'
import { tmpdir } from 'os'

const __filename = fileURLToPath(import.meta.url)
const __dirname = dirname(__filename)
const REPO_ROOT = resolve(__dirname, '..')

/**
 * The vendored upstream file: `core/css/guest.css` as Nextcloud ships it, byte
 * for byte.
 */
const SOURCE_PATH = join(REPO_ROOT, 'scripts', 'sources', 'nextcloud-guest.css')

/** The release the vendored copy was taken from. */
const SOURCE_RELEASE = 'nextcloud/server v34.0.0'

const DEFAULT_OUTPUT_PATH = join(REPO_ROOT, 'css', 'playground-guest.css')

const SCOPE_ROOT = '#nldesign-preview'
const SCOPE_CLASS = '.nldesign-pg-guestpage'

/**
 * What a descendant selector is placed under, at no specificity cost.
 *
 * The LOGIN CARD specimen, and only it. The login tab also carries two
 * deliberate miniatures — a brand box and a background demo — and core's own
 * 175x130 logo box landing on either of those would replace the miniature with
 * the thing it is a miniature OF.
 */
const SCOPE = ':where(' + SCOPE_ROOT + ' ' + SCOPE_CLASS + ')'

/**
 * What the `body` and `#body-login` rules become.
 *
 * The specimen's page area stands in for the document body, and that rule is
 * one element selector upstream — so this keeps a single class's worth of
 * weight rather than an id's, and the stage's own rule for the same element
 * still wins.
 */
const SCOPE_SELF = ':where(' + SCOPE_ROOT + ') ' + SCOPE_CLASS

/**
 * Declarations dropped from the `body` rule.
 *
 * Each of these is about the viewport, not about a box: a fixed background
 * attachment, a height measured against the window, the document's own
 * scrolling. Keeping them would make the specimen paint the page background a
 * second time, inside the ground that already paints it.
 */
const VIEWPORT_ONLY = [
	'background-color',
	'background-image',
	'background-attachment',
	'background-size',
	'background-position',
	'min-height',
	'height',
	'overflow',
	'position',
]

/**
 * Split a block of CSS into top-level rules.
 *
 * A brace counter rather than a parser: the source is one minified file of
 * plain rules, at-rules and nested blocks, and the only structure this needs to
 * respect is nesting.
 *
 * @param {string} css The CSS to split.
 * @return {Array<{prelude: string, body: string, kind: string}>} The rules.
 */
function split(css) {
	const rules = []
	let depth = 0
	let start = 0
	let preludeEnd = -1

	for (let i = 0; i < css.length; i++) {
		const char = css[i]
		if (char === '{') {
			if (depth === 0) {
				preludeEnd = i
			}
			depth += 1
			continue
		}
		if (char !== '}') {
			continue
		}
		depth -= 1
		if (depth > 0) {
			continue
		}
		const prelude = css.slice(start, preludeEnd).trim()
		rules.push({
			prelude,
			body: css.slice(preludeEnd + 1, i),
			kind: prelude.startsWith('@') ? prelude.split(/[\s(]/)[0] : 'rule',
		})
		start = i + 1
	}

	return rules
}

/**
 * Strip comments, which the vendored file carries as licence headers and a
 * trailing source-map pointer.
 *
 * @param {string} css The CSS to clean.
 * @return {string} The CSS without comments.
 */
function stripComments(css) {
	return css.replace(/\/\*[\s\S]*?\*\//g, '')
}

/**
 * Place one selector under the scope, applying the documented rewrites.
 *
 * @param {string} selector One selector from a comma-separated list.
 * @return {?string} The scoped selector, or null if the rule is dropped.
 */
function scopeSelector(selector) {
	const trimmed = selector.trim()
	if (trimmed === '' || trimmed === 'html') {
		return null
	}
	if (trimmed === 'body' || trimmed === '#body-login') {
		return SCOPE_SELF
	}

	// `body.body-login-container` and friends: the body IS the scope root, so
	// what is left of the selector qualifies that root rather than a descendant.
	let rest = trimmed
	if (rest.startsWith('body') && /^body[.:#[]/.test(rest)) {
		return SCOPE_SELF + rest.slice('body'.length)
	}
	if (rest.startsWith('#body-login ')) {
		rest = rest.slice('#body-login '.length)
	}

	rest = rest.replace(/#header(?![A-Za-z0-9_-])/g, '.header-guest')

	return SCOPE + ' ' + rest
}

/**
 * Re-express a relative `url()` so it resolves from this app's css directory.
 *
 * The source resolves them against `core/css/`; the output is read from
 * `custom_apps/thematiq/css/`, which reaches the server root by going up three.
 *
 * @param {string} body A declaration block.
 * @return {string} The block with its urls re-pointed.
 */
function repointUrls(body) {
	return body.replace(/url\((["']?)([^"')]+)\1\)/g, (whole, quote, href) => {
		if (/^(https?:|data:|\/|#)/.test(href)) {
			return whole
		}
		// posix.resolve, not resolve: a URL is a URL on every platform, and the
		// Windows resolver would stamp a drive letter onto it.
		return 'url("../../..' + posix.resolve('/core/css', href) + '")'
	})
}

/**
 * Drop the declarations that only mean something on the viewport element.
 *
 * @param {string} body The `body` rule's declarations.
 * @return {string} What is left of them.
 */
function stripViewportOnly(body) {
	return body
		.split(';')
		.filter((declaration) => {
			const name = declaration.split(':')[0].trim().toLowerCase()
			return VIEWPORT_ONLY.indexOf(name) === -1
		})
		.join(';')
}

/**
 * The prefix every keyframe name from the vendored file is re-emitted under.
 *
 * `rotate` is the name upstream, and it is not the app's to define: a bare
 * `@keyframes rotate` here would be a global redefinition reaching every
 * animation on the settings page that happens to use that name.
 */
const KEYFRAME_PREFIX = 'nldesign-pg-guest-'

/**
 * The keyframe names the vendored file defines, in the order it defines them.
 *
 * @param {Array<{prelude: string, kind: string}>} rules The parsed top-level rules.
 * @return {Array<string>} The names.
 */
function keyframeNames(rules) {
	const names = []
	for (const rule of rules) {
		if (rule.kind.indexOf('@') !== 0 || /keyframes/i.test(rule.kind) === false) {
			continue
		}
		const name = rule.prelude.replace(/^@[-\w]*keyframes\s+/i, '').trim()
		if (name !== '' && names.indexOf(name) === -1) {
			names.push(name)
		}
	}
	return names
}

/**
 * Rewrite a `@keyframes` prelude to its prefixed name.
 *
 * @param {string} prelude The at-rule prelude, e.g. `@-webkit-keyframes rotate`.
 * @return {string} The prelude with the name prefixed.
 */
function renameKeyframes(prelude) {
	return prelude.replace(
		/^(@[-\w]*keyframes\s+)(.+)$/i,
		(whole, at, name) => at + KEYFRAME_PREFIX + name.trim(),
	)
}

/**
 * Repoint every reference to a renamed keyframe inside a declaration body.
 *
 * Both spellings the source uses: the `animation` shorthand, where the name is
 * one word among the timing values, and `animation-name`. Only the names this
 * file actually defines are touched, so an animation core owns elsewhere is
 * left pointing where it pointed.
 *
 * @param {string} body A declaration body.
 * @return {string} The body with animation names repointed.
 */
function repointAnimations(body) {
	let result = body
	for (const name of RENAMED_KEYFRAMES) {
		result = result.replace(
			new RegExp(
				'((?:^|[;{]|-webkit-)\\s*animation(?:-name)?\\s*:[^;}]*?\\b)'
					+ name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
					+ '\\b',
				'g',
			),
			(whole, before) => before + KEYFRAME_PREFIX + name,
		)
	}
	return result
}

/**
 * Filled by generate() before any rule is emitted, because the names are
 * needed while rewriting rules that appear BEFORE the keyframes that define
 * them — the source declares `rotate` at both ends of the file.
 *
 * @type {Array<string>}
 */
let RENAMED_KEYFRAMES = []

/**
 * Re-emit one rule under the scope.
 *
 * @param {{prelude: string, body: string, kind: string}} rule The rule.
 * @return {string} The scoped rule, or an empty string if it is dropped.
 */
function emit(rule) {
	if (rule.kind.indexOf('@') === 0) {
		// A keyframe name is global — it cannot be scoped, and re-emitting it
		// verbatim would redefine `rotate` for the whole settings page. So it
		// is RENAMED instead of dropped, and every animation that referred to
		// it is repointed at the new name below.
		if (/keyframes/i.test(rule.kind)) {
			return (
				renameKeyframes(rule.prelude) + ' {\n' + rule.body.trim() + '\n}\n\n'
			)
		}
		const inner = split(rule.body).map(emit).join('')
		if (inner.trim() === '') {
			return ''
		}
		return rule.prelude + ' {\n' + inner + '}\n'
	}

	const isBody = rule.prelude
		.split(',')
		.some((selector) => selector.trim() === 'body')

	const selectors = rule.prelude
		.split(',')
		.map(scopeSelector)
		.filter((selector) => selector !== null)

	if (selectors.length === 0) {
		return ''
	}

	let body = isBody ? stripViewportOnly(rule.body) : rule.body
	body = repointAnimations(repointUrls(body)).trim()
	if (body === '' || body === ';') {
		return ''
	}

	return selectors.join(',\n') + ' {\n\t' + body.replace(/;$/, '') + ';\n}\n\n'
}

/**
 * Build the whole output file.
 *
 * @param {string} sourceCss The vendored upstream stylesheet.
 * @return {string} The generated stylesheet.
 */
function generate(sourceCss) {
	const header = [
		'/**',
		" * Nextcloud's own login-page stylesheet, scoped to the playground stage.",
		' *',
		' * SPDX-License-Identifier: AGPL-3.0-or-later',
		' * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors',
		' * SPDX-FileCopyrightText: 2016 ownCloud, Inc.',
		' *',
		' * GENERATED FILE — do not edit. Run `npm run generate:guest-css` after',
		' * updating scripts/sources/nextcloud-guest.css, and `npm run',
		' * test:guest-css` to check the committed copy still matches.',
		' *',
		' * Source: ' + SOURCE_RELEASE + ', core/css/guest.css, vendored verbatim.',
		' * Scope:  ' + SCOPE,
		' *',
		' * The scope is a :where(), so every rule keeps the specificity it has',
		' * upstream and the component stylesheets still beat it wherever they',
		' * beat it on the real login page. The login page is the one part of',
		' * Nextcloud whose stylesheet the settings page does not load, so it is',
		' * the one part a specimen could not otherwise be painted by. See',
		' * scripts/generate-guest-css.mjs for what is rewritten and why.',
		' */',
		'',
		'',
	].join('\n')

	const rules = split(stripComments(sourceCss))
	RENAMED_KEYFRAMES = keyframeNames(rules)

	const body = rules.map(emit).join('').trimEnd()

	return header + body + '\n\n' + reducedMotion()
}

/**
 * REWRITE 6: motion, under the scope, honouring the user's setting.
 *
 * The source carries no `prefers-reduced-motion` block of its own — on the
 * real login page it does not need one, because nothing there animates for
 * longer than a submit — but two of its declarations reach this stage: the
 * submit icon's `transition` and, at `.icon-loading:after`, an INFINITE
 * spinner. Moving content that starts on its own and runs past five seconds
 * is WCAG 2.2 AA territory in its own right (SC 2.2.2 Pause, Stop, Hide), not
 * only a gate failure, and `css/playground.css` already answers the same
 * question for the specimens it draws itself.
 *
 * Emitted here rather than added to the committed CSS by hand: that file is
 * byte-compared against a fresh run of this generator, so a hand edit is
 * exactly what the drift check exists to catch.
 *
 * @return {string} The reduced-motion block.
 */
function reducedMotion() {
	return (
		'@media (prefers-reduced-motion: reduce) {\n'
		+ '\t' + SCOPE + ' *,\n'
		+ '\t' + SCOPE + ' *::before,\n'
		+ '\t' + SCOPE + ' *::after {\n'
		+ '\t\ttransition: none !important;\n'
		+ '\t\tanimation: none !important;\n'
		+ '\t}\n'
		+ '}\n'
	)
}

const args = process.argv.slice(2)
const check = args.indexOf('--check') !== -1
const positional = args.filter((argument) => argument.indexOf('--') !== 0)
const output = generate(readFileSync(SOURCE_PATH, 'utf-8'))

if (check === false) {
	const target = positional[0]
		? resolve(process.cwd(), positional[0])
		: DEFAULT_OUTPUT_PATH
	writeFileSync(target, output, 'utf-8')
	process.stdout.write('generate-guest-css: wrote ' + target + '\n')
	process.exit(0)
}

let committed = ''
try {
	committed = readFileSync(DEFAULT_OUTPUT_PATH, 'utf-8')
} catch (error) {
	process.stderr.write(
		'generate-guest-css: ' + DEFAULT_OUTPUT_PATH + ' is missing.\n',
	)
	process.exit(1)
}

if (committed === output) {
	process.stdout.write('generate-guest-css: OK — the committed file matches.\n')
	process.exit(0)
}

const temporary = join(
	mkdtempSync(join(tmpdir(), 'thematiq-guest-')),
	'playground-guest.css',
)
writeFileSync(temporary, output, 'utf-8')

const committedLines = committed.split('\n')
const generatedLines = output.split('\n')
for (let i = 0; i < Math.max(committedLines.length, generatedLines.length); i++) {
	if (committedLines[i] === generatedLines[i]) {
		continue
	}
	process.stderr.write(
		'generate-guest-css: the committed file is stale.\n'
			+ '  first difference at line ' + (i + 1) + '\n'
			+ '  committed: ' + (committedLines[i] ?? '<end of file>') + '\n'
			+ '  generated: ' + (generatedLines[i] ?? '<end of file>') + '\n'
			+ '  regenerated to ' + temporary + '\n',
	)
	break
}
process.exit(1)
