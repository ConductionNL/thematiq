#!/usr/bin/env node

/**
 * Generate a FULL NL Design System token set for one brand.
 *
 * WHY THIS EXISTS
 * ---------------
 * `scripts/generate-tokens.mjs` turns the nl-design-system/themes repo into
 * `--nldesign-*` colour variables. That is enough to tint Nextcloud's own
 * chrome, and it is NOT enough to theme an NL Design System component: the
 * components read `--utrecht-*` / `--ams-*` tokens, and a set that defines
 * only `--nldesign-*` leaves every button, textbox and pagination control
 * rendering in the upstream default. Measured on this repo before this
 * script existed: 4 of 48 token sets defined the component vocabulary. The
 * other 44 produced a page that looks like the generic design system wearing
 * one accent colour.
 *
 * A set generated here covers the component vocabulary, so `portal.theme`
 * changes what a visitor actually sees rather than which class name is in
 * the DOM.
 *
 * HOW A SET IS COMPOSED — three sources, in precedence order
 * ---------------------------------------------------------
 *   A. The brand's own palette, verbatim from its upstream package.
 *   B. The brand's own component mapping, verbatim from its upstream package
 *      (e.g. RODS ships `dist/theme.css` with 582 `--utrecht-*` tokens that
 *      Rotterdam authored). This is authoritative and is never overwritten.
 *   C. The shared role layer (`css/tokens/vng.css`) fills ONLY the component
 *      tokens B does not define, with the colour ramp re-pointed at the
 *      brand. 707 of the role layer's declarations are `var()` aliases onto
 *      that ramp, which is why re-branding means replacing a ramp rather
 *      than re-authoring components.
 *   D. The `--nldesign-*` semantic layer Nextcloud's chrome and Portaliq's
 *      site renderer read, authored in the brand file.
 *
 * C is filtered by NAME against B before it is emitted, so a brand's own
 * decision always beats the shared fallback regardless of source order.
 *
 * The run prints exact coverage — how many component tokens came from the
 * brand, how many from the role layer, and how many literal colours the ramp
 * could not account for. That last number is the one worth reading: an
 * unsubstituted literal is a VNG colour that survived into the brand's file,
 * and it is invisible on screen until someone recognises the wrong blue.
 *
 * ONE FLAT `:root` BLOCK is emitted, deliberately. A shared applier rewrites
 * that selector to scope a set to a single portal; a second block or a
 * compound selector silently escapes the rewrite.
 *
 * Usage:
 *   node scripts/generate-brand-set.mjs <brandId> <pathToUpstreamPackage> [upstreamVersion]
 *
 * Example:
 *   npm pack @gemeente-rotterdam/design-tokens && tar xzf *.tgz
 *   node scripts/generate-brand-set.mjs rotterdam ./package 1.1.0
 */

import { readFileSync, writeFileSync, existsSync } from 'fs'
import { join, dirname } from 'path'
import { fileURLToPath } from 'url'

const __dirname = dirname(fileURLToPath(import.meta.url))

const TOKENS_DIR = join(__dirname, '..', 'css', 'tokens')
const BRANDS_DIR = join(__dirname, 'brands')
const TOKEN_SETS_PATH = join(__dirname, '..', 'token-sets.json')
const ROLE_LAYER_PATH = join(TOKENS_DIR, 'vng.css')

/**
 * Every custom-property declaration in a stylesheet, in source order.
 *
 * Scans rather than pattern-matches, because a token value can legitimately
 * contain the character that ends a declaration. `[^;}]+` looks correct and
 * truncates
 *
 *     --ams-date-input-...-background-image: url('data:image/svg+xml;base64,…');
 *
 * at the `;` inside the data URI, emitting an unterminated `url('…` that
 * leaves the whole `:root` block unparseable from that line onward. Two
 * declarations in the role layer are of this shape, and the resulting file
 * still LOOKS right for its first 2,987 lines.
 *
 * So: `;` and `}` terminate a value only outside quotes and outside
 * parentheses.
 *
 * @param {string} css Stylesheet source.
 * @returns {Array<{name: string, value: string}>} Declarations in order.
 */
function readDeclarations(css) {
	const declarations = []
	const namePattern = /(--[A-Za-z0-9_-]+)\s*:/g
	let match

	while ((match = namePattern.exec(css)) !== null) {
		let index = namePattern.lastIndex
		let depth = 0
		let quote = null
		let value = ''

		while (index < css.length) {
			const character = css[index]

			if (quote !== null) {
				if (character === quote && css[index - 1] !== '\\') {
					quote = null
				}
			} else if (character === '"' || character === "'") {
				quote = character
			} else if (character === '(') {
				depth++
			} else if (character === ')') {
				depth--
			} else if ((character === ';' || character === '}') && depth <= 0) {
				break
			}

			value += character
			index++
		}

		// Collapse internal whitespace: upstream pretty-printers wrap long
		// `var(\n  --name\n)` values across lines, and passing those through
		// verbatim puts raw newlines and foreign indentation inside a block
		// this file otherwise formats consistently. Quoted content is left
		// alone — a data URI has no whitespace to collapse, and rewriting one
		// would corrupt it.
		const collapsed =
			value.includes("'") === true || value.includes('"') === true
				? value.trim()
				: value.replace(/\s+/g, ' ').trim()

		declarations.push({ name: match[1], value: collapsed })
		namePattern.lastIndex = index
	}

	return declarations
}

/**
 * Last-wins de-duplication.
 *
 * Upstream files legitimately redefine a token (RODS resolves
 * `--rods-color-base-green` to a tint further down its own file), so the
 * final occurrence is the effective one and is what must be emitted.
 *
 * @param {Array<{name: string, value: string}>} declarations Input.
 * @returns {Map<string, string>} Effective name → value.
 */
function effective(declarations) {
	const result = new Map()
	for (const { name, value } of declarations) {
		result.set(name, value)
	}
	return result
}

/**
 * Normalise a hex colour so `#FFF`, `#ffffff` and `#FFFFFF` compare equal.
 *
 * Only 3- and 6-digit hexes are folded. An 8-digit hex carries alpha and is
 * left alone, because the ramp's alpha entries are geometry rather than
 * brand and must not be swapped by value.
 *
 * @param {string} hex A hex colour.
 * @returns {string} Lower-case 6-digit form, or the input unchanged.
 */
function normaliseHex(hex) {
	const value = hex.toLowerCase()
	if (/^#[0-9a-f]{3}$/.test(value) === true) {
		return '#' + value[1] + value[1] + value[2] + value[2] + value[3] + value[3]
	}
	return value
}

/**
 * Render one `--name: value;` line.
 *
 * @param {string} name Property name.
 * @param {string} value Property value.
 * @returns {string} The declaration line.
 */
function declaration(name, value) {
	return `\t${name}: ${value};`
}

function main() {
	const [brandId, upstreamPath, upstreamVersion] = process.argv.slice(2)

	// The upstream path is required only by brands that declare one; a brand
	// with an inline palette is generated with the id alone.
	if (!brandId) {
		console.error(
			'Usage: node scripts/generate-brand-set.mjs <brandId> [pathToUpstreamPackage] [upstreamVersion]',
		)
		process.exit(1)
	}

	const brandPath = join(BRANDS_DIR, `${brandId}.json`)
	if (existsSync(brandPath) === false) {
		console.error(`No brand definition at ${brandPath}`)
		process.exit(1)
	}

	const brand = JSON.parse(readFileSync(brandPath, 'utf8'))

	// ---- A + B. the brand's own palette and component mapping ------------
	//
	// TWO WAYS IN, because not every design system publishes an npm package.
	//
	//   `upstream`  — read the brand's palette and its own NL Design System
	//                 mapping out of a published package (RODS does this).
	//   `palette`   — the brand states its palette inline, and contributes NO
	//                 component mapping of its own; section C then fills the
	//                 whole component vocabulary from the shared role layer.
	//
	// The inline form exists for a set whose tokens were READ OFF A RUNNING
	// SITE rather than installed. Those values are transcribed here in full
	// rather than aliased to whichever other set they happen to match today —
	// two design systems agreeing on a colour is a fact about now, not a
	// dependency, and expressing it as one makes the wrong set move when the
	// other one changes.
	const palette = new Map()
	let brandComponents = new Map()

	if (brand.upstream !== undefined) {
		const palettePath = join(upstreamPath, brand.upstream.paletteFile)
		const themePath = join(upstreamPath, brand.upstream.themeFile)

		for (const required of [palettePath, themePath]) {
			if (existsSync(required) === false) {
				console.error(`Upstream file missing: ${required}`)
				process.exit(1)
			}
		}

		const prefix = brand.upstream.palettePrefix
		for (const [name, value] of effective(
			readDeclarations(readFileSync(palettePath, 'utf8')).filter((d) =>
				d.name.startsWith(prefix),
			),
		)) {
			palette.set(name, value)
		}

		// Everything that is NOT the brand's private palette prefix: these are
		// the NL Design System component tokens the brand itself decided on.
		brandComponents = effective(
			readDeclarations(readFileSync(themePath, 'utf8')).filter(
				(d) => d.name.startsWith(prefix) === false,
			),
		)

		// The theme file also restates palette entries; fold them in so every
		// var() reference in B resolves without reaching outside this file.
		for (const { name, value } of readDeclarations(
			readFileSync(themePath, 'utf8'),
		)) {
			if (name.startsWith(prefix) === true) {
				palette.set(name, value)
			}
		}
	} else {
		for (const [name, value] of Object.entries(brand.palette ?? {})) {
			if (name.startsWith('_') === true) {
				continue
			}
			palette.set(name, value)
		}

		if (palette.size === 0) {
			console.error(
				`Brand '${brandId}' declares neither an \`upstream\` package nor an inline \`palette\`.`,
			)
			process.exit(1)
		}

		// A brand whose component mapping was CAPTURED rather than installed.
		// Same role as `upstream.themeFile`: these are the brand's own
		// decisions about NL Design System components, and section C fills only
		// what they leave out.
		//
		// Held in a separate file because it is machine-captured and roughly
		// 900 entries; mixing it into the hand-authored brand file would bury
		// the decisions a reader needs in a wall of measurements.
		if (brand.componentsFile !== undefined) {
			const componentsPath = join(BRANDS_DIR, brand.componentsFile)
			if (existsSync(componentsPath) === false) {
				console.error(`Captured components file missing: ${componentsPath}`)
				process.exit(1)
			}

			const captured = JSON.parse(readFileSync(componentsPath, 'utf8'))
			for (const [name, value] of Object.entries(captured.tokens ?? {})) {
				brandComponents.set(name, value)
			}
		}
	}

	// ---- Deliberate overrides of the brand's own upstream mapping --------
	//
	// Applied IN PLACE rather than appended. Appending wins by cascade order
	// and leaves two declarations of the same custom property in one block —
	// which stylelint rejects, and rightly: a reader looking up the token
	// finds the upstream value first and has no reason to keep scrolling.
	//
	// Each entry states a reason and the run prints it, because an override
	// of the brand's OWN decision that nobody can see is how a set quietly
	// stops being that brand's.
	const upstreamOverrides = Object.entries(brand.upstreamOverrides ?? {}).filter(
		([name]) => name.startsWith('_') === false,
	)

	// Applied to A and B here; section C is built below and takes its pass
	// there, because it does not exist yet at this point. An override that
	// matches nothing in ANY of the three is reported after C is assembled.
	const overrideTargets = new Set(upstreamOverrides.map(([name]) => name))
	const overrideApplied = new Set()

	for (const [name, entry] of upstreamOverrides) {
		if (brandComponents.has(name) === true) {
			brandComponents.set(name, entry.value)
			overrideApplied.add(name)
		} else if (palette.has(name) === true) {
			palette.set(name, entry.value)
			overrideApplied.add(name)
		}
	}

	// ---- C. the shared role layer, ramp re-pointed ----------------------
	const roleLayer = readDeclarations(readFileSync(ROLE_LAYER_PATH, 'utf8'))
	const roleEffective = effective(roleLayer)

	// Name-keyed ramp substitution: --vng-color-<key> / --tilburg-color-<key>.
	// Build the old-value → new-value table at the same time, so the literal
	// colours scattered through the role layer's own component declarations
	// travel with the ramp instead of staying VNG blue.
	const rampByName = new Map()
	const rampByValue = new Map()

	// Literals the role layer hard-codes inside component declarations rather
	// than reaching for the ramp. They have no ramp NAME to key on, so the
	// brand names them by value. Seeded first so a ramp entry can still
	// override one if the two ever collide.
	for (const [oldValue, entry] of Object.entries(brand.literalOverrides ?? {})) {
		rampByValue.set(normaliseHex(oldValue), entry.value)
	}

	for (const [key, entry] of Object.entries(brand.ramp)) {
		const newValue = entry.value
		for (const family of ['--vng-color-', '--tilburg-color-']) {
			const name = family + key
			rampByName.set(name, newValue)

			const oldValue = roleEffective.get(name)
			if (oldValue !== undefined && oldValue.startsWith('#') === true) {
				rampByValue.set(normaliseHex(oldValue), newValue)
			}
		}
	}

	const unsubstituted = new Map()
	const roleFill = []
	let fromRoleLayer = 0

	for (const [name, rawValue] of roleEffective) {
		// The brand's own decision always wins.
		if (brandComponents.has(name) === true) {
			continue
		}

		let value = rawValue

		if (rampByName.has(name) === true) {
			value = rampByName.get(name)
		} else {
			// Value-keyed substitution for the literals the ramp does not name.
			value = value.replace(/#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3})?\b/g, (hex) => {
				const swapped = rampByValue.get(normaliseHex(hex))
				if (swapped !== undefined) {
					return swapped
				}
				unsubstituted.set(
					normaliseHex(hex),
					(unsubstituted.get(normaliseHex(hex)) ?? 0) + 1,
				)
				return hex
			})
		}

		if (name.startsWith('--nldesign-') === true) {
			// D authors these; never let the role layer's copy through.
			continue
		}

		// The role layer's own pass at the deliberate overrides. The logo
		// tokens live HERE rather than in A or B — the role layer bakes a VNG
		// mark into them — so without this an override of one matches nothing
		// and is reported as stale while the wrong logo still ships.
		if (overrideTargets.has(name) === true) {
			const entry = brand.upstreamOverrides[name]
			value = entry.value
			overrideApplied.add(name)
		}

		roleFill.push({ name, value })
		if (
			name.startsWith('--vng-color-') === false
			&& name.startsWith('--tilburg-color-') === false
		) {
			fromRoleLayer++
		}
	}

	// ---- D. the --nldesign-* semantic layer -----------------------------
	const semantic = []
	for (const [key, value] of Object.entries(brand.semantic)) {
		// `_`-prefixed keys are prose for the reader of the brand file, not tokens.
		if (key.startsWith('_') === true) {
			continue
		}
		semantic.push({ name: `--nldesign-color-${key}`, value })
	}
	semantic.push({
		name: '--nldesign-logo-url',
		value: `url('../../${brand.logo}')`,
	})
	semantic.push({
		name: '--nldesign-font-family',
		value: brand.typography.fontFamily,
	})
	// A dark header needs Nextcloud's app-menu icons left white; the defaults
	// layer inverts them to black for its white header. Optional, so a brand
	// with a light header simply omits it.
	if (brand.headerIconFilter !== undefined) {
		semantic.push({
			name: '--nldesign-header-icon-filter',
			value: brand.headerIconFilter,
		})
	}

	// The weight, size and space steps below are OPTIONAL. No layer in the app
	// reads them under the --nldesign- prefix, so the vocabulary audit counts
	// them as foreign names. A brand that wants to pass that audit omits them.
	if (brand.typography.weightNormal !== undefined) {
		semantic.push({
			name: '--nldesign-font-weight-normal',
			value: brand.typography.weightNormal,
		})
	}
	if (brand.typography.weightBold !== undefined) {
		semantic.push({
			name: '--nldesign-font-weight-bold',
			value: brand.typography.weightBold,
		})
	}

	for (const [step, sizes] of Object.entries(brand.typography.scale ?? {})) {
		semantic.push({
			name: `--nldesign-font-size-${step}`,
			value: sizes.fontSize,
		})
		semantic.push({
			name: `--nldesign-line-height-${step}`,
			value: sizes.lineHeight,
		})
	}

	for (const [step, size] of Object.entries(brand.space ?? {})) {
		semantic.push({ name: `--nldesign-space-${step}`, value: size })
	}

	for (const [key, size] of Object.entries(brand.borderRadius)) {
		if (key.startsWith('_') === true) {
			continue
		}
		const name =
			key === 'default'
				? '--nldesign-border-radius'
				: `--nldesign-border-radius-${key}`
		semantic.push({ name, value: size })
	}

	// ---- emit -----------------------------------------------------------
	let provenance = brand.provenance ?? 'an inline palette'
	if (brand.upstream !== undefined) {
		provenance = upstreamVersion
			? `${brand.upstream.package}@${upstreamVersion}`
			: brand.upstream.package
	}

	const lines = []
	lines.push('/**')
	lines.push(` * ${brand.name} Design Tokens`)
	lines.push(' *')
	lines.push(` * GENERATED by scripts/generate-brand-set.mjs from ${provenance}`)
	lines.push(
		` * plus the shared role layer (css/tokens/vng.css). Do not edit by hand —`,
	)
	lines.push(
		' * re-run the generator against scripts/brands/'
			+ brandId
			+ '.json instead.',
	)
	lines.push(' *')
	lines.push(' * Section A is the brand palette, verbatim upstream.')
	lines.push(
		" * Section B is the brand's OWN component mapping, verbatim upstream.",
	)
	lines.push(
		' * Section C is the shared role layer filling only what B leaves undefined,',
	)
	lines.push(' *   with the colour ramp re-pointed at this brand.')
	lines.push(
		' * Section D is the --nldesign-* layer Nextcloud chrome and the Portaliq',
	)
	lines.push(' *   site renderer read.')
	lines.push(' *')
	lines.push(
		' * ONE FLAT :root BLOCK, deliberately — a shared applier rewrites this',
	)
	lines.push(
		' * selector to scope the set to one portal, and a second block or a compound',
	)
	lines.push(' * selector silently escapes that rewrite.')
	lines.push(' */')
	lines.push('')
	lines.push(':root {')

	lines.push(`\t/* --- A. ${brand.name} palette (${provenance}) --- */`)
	for (const [name, value] of palette) {
		lines.push(declaration(name, value))
	}

	lines.push('')
	lines.push(
		`\t/* --- B. ${brand.name}'s own NL Design System component mapping --- */`,
	)
	for (const [name, value] of brandComponents) {
		lines.push(declaration(name, value))
	}

	lines.push('')
	lines.push(
		'\t/* --- C. shared role layer, ramp re-pointed (fills only what B omits) --- */',
	)
	for (const { name, value } of roleFill) {
		lines.push(declaration(name, value))
	}

	// ---- E. gaps neither upstream nor the role layer fills ---------------
	// Filtered against A/B/C rather than trusted: this section exists to fill
	// holes, and an entry that shadows a token one of those already defines is
	// an override wearing a gap-filler's name. Those are reported, not emitted.
	const shadowing = []
	const overrides = Object.entries(brand.componentOverrides ?? {})
		.filter(([name]) => name.startsWith('_') === false)
		.filter(([name]) => {
			const alreadyDefined =
				brandComponents.has(name) === true
				|| palette.has(name) === true
				|| roleFill.some((d) => d.name === name)
			if (alreadyDefined === true) {
				shadowing.push(name)
				return false
			}
			return true
		})

	if (overrides.length > 0) {
		lines.push('')
		lines.push('\t/* --- E. component tokens neither A/B nor C defines --- */')
		for (const [name, value] of overrides) {
			lines.push(declaration(name, value))
		}
	}

	lines.push('')
	lines.push('\t/* --- D. --nldesign-* semantic layer --- */')
	for (const { name, value } of semantic) {
		lines.push(declaration(name, value))
	}

	lines.push('}')
	lines.push('')

	const outputPath = join(TOKENS_DIR, `${brandId}.css`)
	writeFileSync(outputPath, lines.join('\n'), 'utf8')

	// ---- record provenance in the catalogue -----------------------------
	if (existsSync(TOKEN_SETS_PATH) === true) {
		const sets = JSON.parse(readFileSync(TOKEN_SETS_PATH, 'utf8'))
		const entry = sets.find((s) => s.id === brandId)
		if (entry !== undefined) {
			entry.name = brand.name
			entry.description = brand.description
			if (brand.upstream !== undefined) {
				entry.upstreamPackage = brand.upstream.package
				if (upstreamVersion) {
					entry.upstreamVersion = upstreamVersion
				}
			}
			entry.theming = {
				primary_color: brand.semantic.primary,
				background_color: '#FFFFFF',
				logo: brand.logo,
			}
			// TAB-indented, matching the file as committed. Writing spaces here
			// reformats all 46 entries and buries a two-line change in a
			// 440-line diff — the change becomes unreviewable, which is a worse
			// outcome than not recording provenance at all.
			writeFileSync(
				TOKEN_SETS_PATH,
				JSON.stringify(sets, null, '\t') + '\n',
				'utf8',
			)
			console.log(`✓ token-sets.json entry '${brandId}' updated`)
		} else {
			console.log(
				`⚠ token-sets.json has no entry '${brandId}' — catalogue NOT updated, and`,
			)
			console.log(
				'  PortalThemeResolver resolves against that catalogue, so the set will',
			)
			console.log('  not be selectable until an entry exists.')
		}
	}

	// ---- report coverage ------------------------------------------------
	console.log(`\n✓ ${outputPath}`)
	console.log(`  A. brand palette         ${palette.size} tokens`)
	console.log(
		`  B. brand components      ${brandComponents.size} tokens (authoritative)`,
	)
	console.log(
		`  C. role-layer fill       ${fromRoleLayer} component tokens + ${roleFill.length - fromRoleLayer} ramp entries`,
	)
	console.log(`  E. gap fillers           ${overrides.length} tokens`)

	if (upstreamOverrides.length > 0) {
		console.log(`\n  Upstream values REPLACED (${upstreamOverrides.length}):`)
		for (const [name, entry] of upstreamOverrides) {
			console.log(`    ${name}`)
			console.log(`      ${entry.reason}`)
		}
	}

	const missedOverrides = upstreamOverrides
		.map(([name]) => name)
		.filter((name) => (overrideApplied.has(name) === false))

	if (missedOverrides.length > 0) {
		console.log(`\n  ⚠ ${missedOverrides.length} upstreamOverrides entry/entries matched NOTHING upstream`)
		console.log('    and were dropped. They were written to correct a value that no longer')
		console.log('    exists, so the reason attached to them no longer applies:')
		for (const name of missedOverrides) {
			console.log(`      ${name}`)
		}
	}

	console.log(`  D. semantic layer        ${semantic.length} tokens`)

	if (shadowing.length > 0) {
		console.log(
			`\n  ⚠ ${shadowing.length} componentOverrides entries were DROPPED because A, B or C`,
		)
		console.log(
			'    already defines them — that section fills gaps and must not shadow:',
		)
		for (const name of shadowing) {
			console.log(`      ${name}`)
		}
	}

	// Literals the brand KEEPS on purpose. Separated from the warning below so
	// that warning keeps meaning something: a list that always prints ten
	// entries on a correct set is one nobody reads, and the eleventh — the real
	// drift — arrives into an audience that has learned to skip it.
	const keptLiterals = Object.entries(brand.keepLiterals ?? {}).filter(
		([hex]) => hex.startsWith('_') === false,
	)

	for (const [hex] of keptLiterals) {
		unsubstituted.delete(normaliseHex(hex))
	}

	if (keptLiterals.length > 0) {
		console.log(`\n  Role-layer literals KEPT deliberately (${keptLiterals.length}):`)
		for (const [hex, reason] of keptLiterals) {
			console.log(`    ${hex}  ${reason}`)
		}
	}

	if (unsubstituted.size === 0) {
		console.log(
			'\n  Ramp coverage: complete — every remaining role-layer literal is accounted for.',
		)
	} else {
		const total = [...unsubstituted.values()].reduce((a, b) => a + b, 0)
		console.log(
			`\n  ⚠ ${unsubstituted.size} literal colours (${total} occurrences) had no ramp entry`,
		)
		console.log(
			"    and were emitted UNCHANGED — these are still the role layer's own",
		)
		console.log(
			"    colours wearing this brand's name. Add them to the brand ramp:",
		)
		for (const [hex, count] of [...unsubstituted.entries()].sort(
			(a, b) => b[1] - a[1],
		)) {
			console.log(`      ${hex}  ×${count}`)
		}
	}
}

main()
