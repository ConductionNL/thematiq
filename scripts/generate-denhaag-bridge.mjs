/**
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 * SPDX-License-Identifier: EUPL-1.2
 *
 * Writes the Den Haag section of css/public-bridge.css, inside its one `:root` block.
 *
 * The @gemeente-denhaag component CSS a portal adopts paints from
 * `--denhaag-*` (and `--nl-data-badge-*`) properties with almost no
 * fallbacks. Almost no token set declares them. This script gives every one of
 * them a value, once, in the bridge a portal links before the set:
 *
 *   - a colour follows the set: `var(--nldesign-color-x, <defaults value>)`;
 *   - a font family follows the set font;
 *   - a radius reads the set's corner radius first;
 *   - all other geometry is Den Haag's own, resolved to literals from the
 *     pinned @gemeente-denhaag/design-tokens-components;
 *   - geometry that package does not define is named in the mapping file,
 *     with the scale step chosen and why.
 *
 * The rules are data: scripts/mapping/denhaag-component-tokens.json. The
 * pinned package CSS is vendored under scripts/sources/denhaag/ so a run needs
 * no network. A property the component CSS reads without a fallback, and that
 * no rule covers, stops the run; so does a resolved value that is a colour the
 * mapping does not name, because a Den Haag colour leaking into every set is
 * exactly the defect this file exists to prevent.
 *
 * Usage:
 *   node scripts/generate-denhaag-bridge.mjs           write the section
 *   node scripts/generate-denhaag-bridge.mjs --check   fail when the committed section differs
 *
 * @spec openspec/changes/denhaag-component-tokens/specs/denhaag-component-tokens/spec.md
 */

import { createHash } from 'crypto'
import * as prettier from 'prettier'
import { readFileSync, writeFileSync, readdirSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const ROOT = join(dirname(fileURLToPath(import.meta.url)), '..')
const MAPPING_PATH = join(ROOT, 'scripts', 'mapping', 'denhaag-component-tokens.json')
const BRIDGE_PATH = join(ROOT, 'css', 'public-bridge.css')
const DEFAULTS_PATH = join(ROOT, 'css', 'systems', 'nldesign', 'defaults.css')

export const BEGIN = '/* BEGIN GENERATED denhaag-component-tokens */'
export const END = '/* END GENERATED denhaag-component-tokens */'

const COLOUR_LITERAL = /#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i

/**
 * Every `--name: value;` declaration in a stylesheet, last one wins.
 *
 * @param {string} css The stylesheet.
 * @return {Map<string, string>} name => raw value.
 */
export function declarations(css) {
	const out = new Map()
	for (const match of css.matchAll(/(--[a-z0-9-]+)\s*:\s*([^;]*);/gi)) {
		out.set(match[1], match[2].trim())
	}
	return out
}

/**
 * The Den Haag and badge properties a stylesheet reads, split by whether a
 * fallback was given at every use.
 *
 * @param {string} css The component stylesheet.
 * @return {{bare: Set<string>, all: Set<string>}} Names read without a fallback somewhere, and all names read.
 */
export function readProperties(css) {
	const bare = new Set()
	const all = new Set()
	for (const match of css.matchAll(/var\(\s*(--(?:denhaag|nl-data-badge)-[a-z0-9-]+)\s*(,)?/g)) {
		all.add(match[1])
		if (match[2] === undefined) {
			bare.add(match[1])
		}
	}
	return { bare, all }
}

/**
 * Resolve every var() in a value against a declaration map, to literals.
 *
 * @param {string} value The raw value.
 * @param {Map<string, string>} decl The declarations.
 * @param {number} depth Recursion guard.
 * @return {string|null} The literal value, or null when a reference cannot be resolved.
 */
export function resolve(value, decl, depth = 0) {
	if (depth > 25) {
		return null
	}
	let unresolved = false
	const out = value.replace(/var\(\s*(--[a-z0-9-]+)\s*(?:,\s*([^()]*(?:\([^()]*\))?[^()]*))?\)/gi, (whole, name, fallback) => {
		if (decl.has(name) === true) {
			const inner = resolve(decl.get(name), decl, depth + 1)
			if (inner === null) {
				unresolved = true
				return whole
			}
			return inner
		}
		if (fallback !== undefined) {
			return fallback.trim()
		}
		unresolved = true
		return whole
	})
	if (unresolved === true) {
		return null
	}
	return out.includes('var(') === true ? resolve(out, decl, depth + 1) : out
}

/**
 * Build the generated section from the mapping, the vendored sources and the defaults.
 *
 * @param {object} mapping The parsed mapping file.
 * @param {string} mappingText The mapping file as text, for its hash.
 * @param {(file: string) => string} readSource Reads a vendored file by name.
 * @param {Map<string, string>} defaults The --nldesign-* defaults.
 * @return {{section: string, count: number}} The section text and how many properties it declares.
 */
export function buildSection(mapping, mappingText, readSource, defaults) {
	const { components, tokens } = mapping.sources
	const needBare = new Set()
	const needAll = new Set()
	for (const [name, version] of Object.entries(components)) {
		const { bare, all } = readProperties(readSource(`${name}@${version}.css`))
		bare.forEach((p) => needBare.add(p))
		all.forEach((p) => needAll.add(p))
	}

	const tokenDecl = new Map()
	for (const [name, version] of Object.entries(tokens)) {
		for (const [k, v] of declarations(readSource(`${name}@${version}.css`))) {
			tokenDecl.set(k, v)
		}
	}

	const fallbackOf = (token) => {
		const literal = defaults.get(token)
		if (literal !== undefined && literal.includes('var(') === false) {
			return literal
		}
		if (token === '--nldesign-color-background') {
			return '#fff'
		}
		throw new Error(`No literal default for ${token} in css/systems/nldesign/defaults.css`)
	}
	const colourExpr = (rule) => {
		if (rule.ref !== undefined) {
			if (mapping.derived?.[rule.ref] === undefined) {
				throw new Error(`${rule.ref} is referenced but not declared under "derived"`)
			}
			return `var(${rule.ref})`
		}
		if (rule.shade !== undefined) {
			// Darker text from the set's own hue: the status colour mixed with
			// black, so the hue stays and only the lightness drops.
			return `color-mix(in srgb, var(${rule.shade}, ${fallbackOf(rule.shade)}) ${rule.percent}%, #000)`
		}
		if (rule.token !== undefined) {
			return `var(${rule.token}, ${fallbackOf(rule.token)})`
		}
		if (rule.tint !== undefined) {
			const rgb = fallbackOf(`${rule.tint}-rgb`)
			return `rgba(var(${rule.tint}-rgb, ${rgb}), ${rule.alpha})`
		}
		return rule.value.replace('{focus}', `var(--nldesign-color-focus, ${fallbackOf('--nldesign-color-focus')})`)
	}

	const lines = []
	// Derived tokens first: the Den Haag properties below read them.
	for (const [name, rule] of Object.entries(mapping.derived ?? {})) {
		lines.push(`\t${name}: ${colourExpr(rule)};`)
	}
	const problems = []
	const names = [...needAll].sort()
	for (const name of names) {
		const colour = mapping.colours[name]
		const explicit = mapping.explicit[name]
		const alias = mapping.aliases[name]
		let value = null

		if (colour !== undefined) {
			value = colourExpr(colour)
		} else if (/-font-family$/.test(name) === true) {
			// Den Haag names TheSans, which this app does not ship: the set font wins,
			// and a generic family is the only honest fallback.
			value = `var(${mapping.fontFamily.token}, sans-serif)`
		} else if (tokenDecl.has(name) === true) {
			const literal = resolve(tokenDecl.get(name), tokenDecl)
			if (literal === null) {
				problems.push(`${name}: its value in the tokens package does not resolve`)
				continue
			}
			if (COLOUR_LITERAL.test(literal) === true) {
				problems.push(`${name}: resolves to a Den Haag colour (${literal}) the mapping does not name`)
				continue
			}
			value = /-border-radius$/.test(name) === true ? `var(${mapping.radiusToken}, ${literal})` : literal
		} else if (explicit !== undefined) {
			value = explicit.radius !== undefined ? `var(${mapping.radiusToken}, ${explicit.radius})` : explicit.value
		} else if (alias !== undefined) {
			value = `var(${alias.alias})`
		} else if (needBare.has(name) === true) {
			problems.push(`${name}: read without a fallback, and no rule gives it a value`)
			continue
		} else {
			// Read only with a fallback of the component's own: leave it to the component.
			continue
		}

		if (value === '') {
			// An empty token value means "unset" upstream; leave the component's own behaviour.
			continue
		}
		// A website vocabulary token refines the role: a set that names it wins,
		// every other set keeps the value above as the fallback
		// (openspec/changes/zuiddrecht-website-type-and-controls).
		const website = mapping.website?.[name]
		if (website !== undefined) {
			value = `var(${website.token}, ${value})`
		}
		lines.push(`\t${name}: ${value};`)
	}

	for (const name of Object.keys(mapping.colours)) {
		if (needAll.has(name) === false) {
			problems.push(`${name}: in the mapping, but no pinned component reads it`)
		}
	}
	for (const [name, rule] of Object.entries(mapping.website ?? {})) {
		if (name.startsWith('_') === true) {
			// The section's own comment.
			continue
		}
		if (needAll.has(name) === false) {
			problems.push(`${name}: under "website", but no pinned component reads it`)
		}
		if (/^--nldesign-website-[a-z0-9-]+$/.test(rule.token ?? '') === false) {
			problems.push(`${name}: a "website" rule must name a --nldesign-website-* token`)
		}
	}
	for (const alias of Object.values(mapping.aliases)) {
		if (tokenDecl.has(alias.alias) === false && mapping.explicit[alias.alias] === undefined) {
			problems.push(`${alias.alias}: an alias target with no value`)
		}
	}

	if (problems.length > 0) {
		throw new Error('The Den Haag mapping is incomplete:\n  ' + problems.join('\n  '))
	}

	const hash = createHash('sha256').update(mappingText).digest('hex')
	const versions = Object.entries({ ...components, ...tokens })
		.map(([name, version]) => `@gemeente-denhaag/${name} ${version}`)
		.join(', ')

	// Inside the bridge's one `:root` block: a second `:root` is a duplicate
	// selector, and a shared applier rewrites one block only.
	const section = [
		'\t' + BEGIN,
		'\t/*',
		'\t * Den Haag component properties for every token set. GENERATED by',
		'\t * scripts/generate-denhaag-bridge.mjs from scripts/mapping/denhaag-component-tokens.json.',
		'\t * Do not edit by hand. Colours follow the set; geometry is Den Haag\'s.',
		`\t * Mapping sha256: ${hash}`,
		`\t * Sources: ${versions}`,
		'\t */',
		...lines,
		'\t' + END,
	].join('\n')

	return { section, count: lines.length }
}

/**
 * Put the section into the bridge, replacing an earlier one.
 *
 * @param {string} bridge The bridge stylesheet.
 * @param {string} section The generated section.
 * @return {string} The new bridge text.
 */
export function placeSection(bridge, section) {
	const start = bridge.indexOf('\t' + BEGIN)
	const end = bridge.indexOf(END)
	if (start === -1 && end === -1) {
		// First run: just before the closing brace of the one `:root` block.
		const close = bridge.lastIndexOf('\n}')
		if (close === -1) {
			throw new Error('css/public-bridge.css has no :root block to extend')
		}
		return bridge.slice(0, close) + '\n\n' + section + bridge.slice(close)
	}
	if (start === -1 || end === -1 || end < start) {
		throw new Error('css/public-bridge.css has a broken generated-section marker pair')
	}
	return bridge.slice(0, start) + section + bridge.slice(end + END.length)
}

/**
 * Place the section and format the bridge the way `npm run format` checks it.
 *
 * The bridge is in prettier's scope, so the generator writes exactly what
 * prettier would; otherwise the drift check and the format check would fight.
 *
 * @param {string} bridge The bridge stylesheet.
 * @param {string} section The generated section.
 * @return {Promise<string>} The formatted bridge text.
 */
export async function renderBridge(bridge, section) {
	const config = (await prettier.resolveConfig(BRIDGE_PATH)) ?? {}
	return prettier.format(placeSection(bridge, section), { ...config, filepath: BRIDGE_PATH })
}

async function main() {
	const check = process.argv.includes('--check')
	const mappingText = readFileSync(MAPPING_PATH, 'utf8')
	const mapping = JSON.parse(mappingText)
	const sourceDir = join(ROOT, mapping.sources.directory)
	const available = new Set(readdirSync(sourceDir))
	const readSource = (file) => {
		if (available.has(file) === false) {
			throw new Error(`Vendored source missing: ${mapping.sources.directory}/${file}`)
		}
		return readFileSync(join(sourceDir, file), 'utf8')
	}

	const { section, count } = buildSection(mapping, mappingText, readSource, declarations(readFileSync(DEFAULTS_PATH, 'utf8')))
	const bridge = readFileSync(BRIDGE_PATH, 'utf8')
	const next = await renderBridge(bridge, section)

	if (check === true) {
		if (next !== bridge) {
			console.error('css/public-bridge.css: the Den Haag section is stale or edited by hand. Run npm run generate:denhaag-bridge.')
			process.exit(1)
		}
		console.log(`css/public-bridge.css: Den Haag section up to date (${count} properties).`)
		return
	}

	writeFileSync(BRIDGE_PATH, next)
	console.log(`css/public-bridge.css: wrote ${count} Den Haag properties.`)
}

if (process.argv[1] !== undefined && fileURLToPath(import.meta.url) === process.argv[1]) {
	main().catch((error) => {
		console.error(error.message)
		process.exit(1)
	})
}
