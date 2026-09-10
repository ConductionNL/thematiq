#!/usr/bin/env node
/**
 * Materialise `img/logos/openwoo.svg` from the OpenWOO design tokens.
 *
 * WHY A SCRIPT AND NOT A COMMITTED-BY-HAND FILE. The OpenWOO wordmark is not
 * published as an .svg anywhere: the theme ships it INSIDE its token file, as a
 * base64 `data:` URI on `conduction.logo.header.background-image`
 * (`other/openwoo-design-tokens/src/component/conduction/logo.tokens.json` in
 * `@conduction/theme`). The token set already records that package and version
 * in `token-sets.json` (`upstreamPackage` / `upstreamVersion`), so the artwork
 * is derived from a pinned source rather than pasted from somewhere — and when
 * the package moves, this is re-run instead of remembered.
 *
 * The same data URI is repeated on the `header`, `footer` and `navbar` logo
 * tokens, byte for byte; the header one is read.
 *
 * WHERE IT IS FETCHED FROM. jsDelivr, which serves the published package's own
 * files — verified byte-identical to the file inside the npm tarball. That
 * avoids adding `@conduction/theme` (a ~3 MB package, for one logo) as a
 * dependency, and avoids depending on a `tar` binary being on PATH.
 *
 * LICENCE. `other/openwoo-design-tokens/LICENSE.md` in that package: the logo
 * and house style are Conduction's copyright and their use is permitted "alleen
 * ... voor gebruik binnen OpenWOO" — only within OpenWOO. Shipping this file in
 * Thematiq distributes it to every instance that installs the app, which is a
 * decision for Conduction to take deliberately, not a build detail. It is why
 * the artwork is NOT committed by this script's mere existence: someone has to
 * run it.
 *
 * Usage:
 *   node scripts/extract-openwoo-logo.mjs            write img/logos/openwoo.svg
 *   node scripts/extract-openwoo-logo.mjs --check    verify it matches upstream
 *
 * `--check` exits non-zero when the file is missing or has drifted, so it can
 * be wired into a gate later; nothing calls it today.
 */

import { existsSync, readFileSync, writeFileSync } from 'node:fs'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const PACKAGE = '@conduction/theme'
const VERSION = '2.1.0'
const TOKEN_FILE
	= 'other/openwoo-design-tokens/src/component/conduction/logo.tokens.json'
const SOURCE = `https://cdn.jsdelivr.net/npm/${PACKAGE}@${VERSION}/${TOKEN_FILE}`

const repoRoot = join(dirname(fileURLToPath(import.meta.url)), '..')
const target = join(repoRoot, 'img', 'logos', 'openwoo.svg')

/**
 * Decode the wordmark out of the logo token file.
 *
 * @param {object} tokens The parsed logo.tokens.json.
 * @return {Buffer} The SVG bytes.
 */
function decodeWordmark(tokens) {
	const value = tokens?.conduction?.logo?.header?.['background-image']?.value
	if (typeof value !== 'string') {
		throw new Error(
			`${TOKEN_FILE} no longer carries conduction.logo.header.background-image`,
		)
	}

	const match = /^url\("data:image\/svg\+xml;base64,([^"]+)"\)$/.exec(value)
	if (match === null) {
		throw new Error(
			'conduction.logo.header.background-image is no longer a base64 SVG data URI',
		)
	}

	const svg = Buffer.from(match[1], 'base64')
	if (svg.includes('<svg') === false) {
		throw new Error('the decoded payload is not an SVG')
	}

	return svg
}

const response = await fetch(SOURCE)
if (response.ok === false) {
	console.error(`[openwoo-logo] ${SOURCE} → HTTP ${response.status}`)
	process.exit(1)
}

const svg = decodeWordmark(await response.json())
const check = process.argv.includes('--check')

if (check === true) {
	if (existsSync(target) === false) {
		console.error(
			'[openwoo-logo] img/logos/openwoo.svg is absent — run this script without --check to write it.',
		)
		process.exit(1)
	}
	if (readFileSync(target).equals(svg) === false) {
		console.error(
			`[openwoo-logo] img/logos/openwoo.svg differs from ${PACKAGE}@${VERSION}.`,
		)
		process.exit(1)
	}
	console.log(
		`[openwoo-logo] OK — img/logos/openwoo.svg matches ${PACKAGE}@${VERSION} (${svg.length} bytes).`,
	)
	process.exit(0)
}

writeFileSync(target, svg)
console.log(
	`[openwoo-logo] wrote img/logos/openwoo.svg — ${svg.length} bytes, from ${PACKAGE}@${VERSION}.`,
)
