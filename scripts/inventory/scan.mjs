/**
 * Pure scanning and classification for the Nextcloud variable inventory.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * Kept free of file-system access so the guard's unit tests can feed it
 * fixtures. `extract-nextcloud-variables.mjs` does the reading and writing.
 *
 * @spec openspec/changes/nc-variable-inventory/specs/nextcloud-variable-inventory/spec.md
 */

/** The four built-in Nextcloud themes whose served stylesheet is the authority for theme values. */
export const THEMES = ['default', 'dark', 'light-highcontrast', 'dark-highcontrast']

/** Every class an inventory entry can carry, in the order the design table lists them. */
export const CLASSES = ['theme', 'icon', 'runtime', 'component', 'slot', 'unread', 'conduction']

/** At most this many example selectors and values are kept per entry, so the file stays readable. */
const KEEP = 5

/**
 * Remove CSS comments.
 *
 * In a JavaScript bundle a comment marker can also sit inside a string (a glob
 * such as `src/**\/*.js`), so the match is bounded: a "comment" longer than
 * 5,000 characters is left alone rather than swallowing half a bundle.
 *
 * @param {string} text Source text.
 * @return {string} The text without comments.
 */
export function stripComments(text) {
	return text.replace(/\/\*[\s\S]{0,5000}?\*\//g, ' ')
}

/**
 * True when a declaration only restates its own variable (`--x: var(--x)`).
 *
 * @param {string} name  Custom property name.
 * @param {string} value Its declared value.
 * @return {boolean} Whether the declaration is a self-reference.
 */
export function isSelfReference(name, value) {
	return value.replace(/\s+/g, '').replace(/!important$/, '') === `var(${name})`
}

/**
 * The selector text that opens the block a declaration at `index` sits in.
 *
 * Works on raw text so it can read CSS embedded in a JavaScript string, where
 * no parser can be run. Returns '' when no opening brace is found.
 *
 * @param {string} text  Source text.
 * @param {number} index Offset of the declaration.
 * @return {string} The selector, whitespace collapsed.
 */
export function selectorAt(text, index) {
	const open = text.lastIndexOf('{', index)
	if (open === -1 || text.lastIndexOf('}', index) > open) {
		return ''
	}
	const before = text.slice(Math.max(0, open - 400), open)
	const cut = Math.max(
		...['}', '{', ';', '"', "'", '`', '\\n'].map((mark) => {
			const at = before.lastIndexOf(mark)
			return at === -1 ? -1 : at + mark.length
		}),
	)
	return before.slice(cut === -1 ? 0 : cut).replace(/\s+/g, ' ').trim()
}

/**
 * Collect what one source file does with custom properties.
 *
 * @param {string} raw Source text of a CSS or JavaScript file.
 * @return {{declared: Map<string, {selectors: string[], values: string[]}>, read: Set<string>, runtime: Set<string>}}
 *   Declared names with where and how, names read through var() or
 *   getPropertyValue(), and names written from JavaScript.
 */
export function scanSource(raw) {
	// CSS embedded in a JavaScript string carries its line breaks and tabs as
	// `\\n` and `\\t`. Left in, the `t` of `\\t--x: 1` reads as a word
	// character in front of the name and hides the declaration.
	const text = stripComments(raw).replace(/\\[nrt]/g, ' ')
	const declared = new Map()
	const read = new Set()
	const runtime = new Set()

	for (const match of text.matchAll(/setProperty\(\s*["'`](--[\w-]+)/g)) {
		runtime.add(match[1])
	}
	// An inline style binding: `style: { '--x': value }` or a template string `--x: ${value}`.
	for (const match of text.matchAll(/["'](--[A-Za-z_][\w-]+)["']\s*:/g)) {
		runtime.add(match[1])
	}
	for (const match of text.matchAll(/(?<![\w-])(--[A-Za-z_][\w-]+)\s*:\s*\$\{/g)) {
		runtime.add(match[1])
	}
	for (const match of text.matchAll(/var\(\s*(--[A-Za-z_][\w-]*)/g)) {
		read.add(match[1])
	}
	for (const match of text.matchAll(/getPropertyValue\(\s*["'`](--[\w-]+)/g)) {
		read.add(match[1])
	}

	// A declaration: the name, then a colon. Not preceded by a word character
	// or a dash (a BEM modifier such as `.list--open`), `&`, `.` or `#` (SCSS
	// source kept in a bundle: `&--open:hover`), `!` or `<` (an HTML comment
	// `<!--SPDX-...:`), a quote (an inline style binding, counted as runtime
	// above) or `?` (minified `a?--b:c` is a decrement, not CSS).
	const declaration = /(?<![\w"'`?&.#!<-])(--[A-Za-z_][\w-]{1,})\s*:(?!:)\s*([^;}{"'`]*)/g
	for (const match of text.matchAll(declaration)) {
		const [, name, rawValue] = match
		const value = rawValue.replace(/\s+/g, ' ').trim()
		if (value.startsWith('${') || isSelfReference(name, value)) {
			continue
		}
		const entry = declared.get(name) ?? { selectors: [], values: [] }
		const selector = selectorAt(text, match.index)
		if (selector !== '' && entry.selectors.includes(selector) === false) {
			entry.selectors.push(selector)
		}
		if (value !== '' && entry.values.includes(value) === false) {
			entry.values.push(value)
		}
		declared.set(name, entry)
	}

	return { declared, read, runtime }
}

/**
 * Parse a theming stylesheet into name → value.
 *
 * The served `/apps/theming/theme/{id}.css` opens with one block of
 * declarations for the theme's attribute selector. Later blocks (the
 * high-contrast themes restyle `.menutoggle`, `#app-navigation`, ...) are
 * element rules, not vocabulary, so only the first block is read.
 *
 * @param {string} css Stylesheet text.
 * @return {{selector: string, values: Map<string, string>}} The block's selector and its declarations.
 */
export function parseThemeStylesheet(css) {
	const text = stripComments(css)
	const open = text.indexOf('{')
	const close = text.indexOf('}', open)
	const values = new Map()
	for (const match of text.slice(open + 1, close).matchAll(/(--[\w-]+)\s*:([^;]*);/g)) {
		values.set(match[1], match[2].trim())
	}
	return { selector: text.slice(0, open).replace(/\s+/g, ' ').trim(), values }
}

/**
 * The owner of a source file: a Nextcloud app id, `core`, or a dist bundle's prefix.
 *
 * @param {string} file Path relative to the release root, with forward slashes.
 * @return {string} The owner id.
 */
export function ownerOfFile(file) {
	const parts = file.split('/')
	if (parts[0] === 'apps' && parts.length > 2) {
		return parts[1]
	}
	if (parts[0] === 'dist') {
		const prefix = parts[1].replace(/\.(m?js|css)$/, '').split('-')[0]
		return /^\d+$/.test(prefix) === true ? 'shared' : prefix
	}
	return 'core'
}

/** File owners that are not one component: Nextcloud's shared bundles. */
const SHARED_OWNERS = new Set(['core', 'shared', 'common'])

/**
 * The owner of a variable.
 *
 * A name seen in the files of one app only belongs to that app. Otherwise it
 * comes from a library many bundles carry (@nextcloud/vue above all), so the
 * file says nothing and the name prefix decides; with no known prefix it is
 * `nextcloud-vue` when three or more owners carry it, else the owner seen most
 * often (ties broken alphabetically).
 *
 * @param {string}   name       Custom property name.
 * @param {string[]} fileOwners Owners of every file that declares or reads it.
 * @param {Array<[string, string]>} prefixes Ordered `[prefix, owner]` pairs.
 * @return {string} The owner id.
 */
export function ownerOfVariable(name, fileOwners, prefixes) {
	const distinct = [...new Set(fileOwners)].sort()
	if (distinct.length === 1 && SHARED_OWNERS.has(distinct[0]) === false) {
		return distinct[0]
	}
	for (const [prefix, owner] of prefixes) {
		if (name.startsWith(prefix) === true) {
			return owner
		}
	}
	if (distinct.length >= 3) {
		return 'nextcloud-vue'
	}
	const counts = new Map()
	for (const owner of fileOwners) {
		counts.set(owner, (counts.get(owner) ?? 0) + 1)
	}
	return [...counts.entries()].sort((a, b) => b[1] - a[1] || a[0].localeCompare(b[0]))[0]?.[0] ?? 'core'
}

/**
 * Apply the class rules of design.md to one name.
 *
 * Order matters: a forced class wins, then the library prefix, then the
 * theming vocabulary, then icons, runtime writes, and finally whether the
 * name is declared, read, or both.
 *
 * @param {object}   facts                What the scan saw for this name.
 * @param {string}   facts.name           Custom property name.
 * @param {boolean}  facts.theme          Declared by a theming stylesheet.
 * @param {boolean}  facts.declared       Declared in shipped CSS or JS.
 * @param {boolean}  facts.read           Read in shipped CSS or JS.
 * @param {boolean}  facts.runtime        Written from JavaScript.
 * @param {string[]} facts.values         Declared values.
 * @param {boolean}  facts.conduction     Seen in the shared library.
 * @param {Object<string, string>} forced Name → class overrides from the config.
 * @return {string} One of CLASSES.
 */
export function classify(facts, forced = {}) {
	if (forced[facts.name] !== undefined) {
		return forced[facts.name]
	}
	if (facts.conduction === true && facts.name.startsWith('--cn-') === true) {
		return 'conduction'
	}
	if (facts.theme === true) {
		return 'theme'
	}
	if (/^--(original-)?icon-/.test(facts.name) === true || facts.values.some((value) => /url\(/.test(value)) === true) {
		return 'icon'
	}
	// Vue compiles `v-bind()` in a style block to a hashed property (`--a475b540`,
	// `--7ba5bd90-color`) that it writes on the element at render time.
	if (facts.runtime === true || /^--v?[0-9a-f]{8}(-|$)/.test(facts.name) === true) {
		return 'runtime'
	}
	if (facts.declared === true && facts.read === true) {
		return 'component'
	}
	if (facts.declared === true) {
		return 'unread'
	}
	return 'slot'
}

/**
 * Keep the first few items of a list.
 *
 * @param {string[]} list Items in discovery order.
 * @return {string[]} At most KEEP items.
 */
export function keep(list) {
	return list.slice(0, KEEP)
}

/**
 * Serialise the inventory deterministically: tab indent and a trailing newline. Callers sort the names.
 *
 * @param {object} inventory The inventory object.
 * @return {string} JSON text.
 */
export function serialise(inventory) {
	return `${JSON.stringify(inventory, null, '\t')}\n`
}
