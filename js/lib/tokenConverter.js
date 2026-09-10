/**
 * NL Design System theme to Nextcloud token set converter — the JS runtime.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * The second of the two thin runtimes over `scripts/mapping/nlds-to-nextcloud.json`
 * (the first being `lib/Service/TokenSetConverterService.php`). Both load the
 * same table, so the admin upload path, the CLI and the nightly sync cannot
 * drift apart. Its output MUST stay byte-identical to the PHP service's for the
 * same input; anything else is a bug in one of the two.
 *
 * PURE BY CONSTRUCTION. This module never touches the filesystem and never
 * looks at globals: the mapping table, its SHA-256, the app's `--nldesign-*`
 * vocabulary and the list of available fonts are all passed IN. That is what
 * lets the same code run under Node for the CLI, under Vitest for the fixtures,
 * and in the browser for a live preview, and it is why the caller (not this
 * module) decides how a hash is computed — Node has `crypto`, a browser has
 * only the async `SubtleCrypto`.
 *
 * Dual-mode: exports via `module.exports` under Node and assigns to
 * `window.NldesignTokenConverter` in the browser. No `import`/`export` syntax,
 * matching `js/lib/tokenTransforms.js` — this app has no JS bundler and serves
 * these files directly as `<script>`.
 *
 * @spec openspec/changes/nlds-theme-converter/specs/token-set-converter/spec.md
 */

;(function tokenConverterModule() {
	'use strict'

	/**
	 * Component-layer prefixes emitted verbatim. Mirrors
	 * `TokenSetConverterService::COMPONENT_PREFIXES`.
	 */
	var COMPONENT_PREFIXES = ['--utrecht-', '--ams-', '--denhaag-', '--conduction-']

	/**
	 * Role heads that mean "a raw design-token value". Mirrors
	 * `TokenSetConverterService::PALETTE_ROLE_HEADS`.
	 */
	var PALETTE_ROLE_HEADS = [
		'color',
		'colour',
		'size',
		'sizing',
		'font-size',
		'font-weight',
		'font-family',
		'typography',
		'space',
		'spacing',
		'radius',
		'shadow',
		'breakpoint',
		'opacity',
		'z-index',
		'border-radius',
		'line-height',
	]

	var MAX_VAR_DEPTH = 10
	var MAX_CONTRAST_STEPS = 10

	/** Font families this app bundles itself. Mirrors `fontIsAvailable()`. */
	var BUNDLED_FONTS = ['marianne', 'inter']
	var GENERIC_FONTS = ['sans-serif', 'serif', 'monospace', 'system-ui', 'cursive', 'fantasy']

	// ---------------------------------------------------------------- parsing

	/**
	 * Split a CSS string into custom-property declarations.
	 *
	 * A character scanner, not a regex, for the same reason
	 * `CssParserService::parseDeclarations()` is one: a `;` only terminates a
	 * declaration outside strings and parens, and the first `;` in
	 * `url("data:image/svg+xml;base64,…")` is inside a string. Comments are
	 * skipped rather than buffered, because an apostrophe in comment prose
	 * ("the theme's own steps") would otherwise open a string that stays open.
	 *
	 * @param {string} content Raw CSS.
	 * @return {Object<string,string>} name => value.
	 */
	function parseDeclarations(content) {
		var parsed = {}
		var buffer = ''
		var depth = 0
		var quote = null
		var i

		for (i = 0; i < content.length; i++) {
			var char = content.charAt(i)

			if (quote !== null) {
				buffer += char
				if (char === '\\' && (i + 1) < content.length) {
					buffer += content.charAt(i + 1)
					i++
					continue
				}
				if (char === quote) {
					quote = null
				}
				continue
			}

			if (char === '/' && content.charAt(i + 1) === '*') {
				var end = content.indexOf('*/', i + 2)
				if (end === -1) {
					break
				}
				i = end + 1
				continue
			}

			if (char === '"' || char === '\'') {
				quote = char
				buffer += char
				continue
			}

			if (char === '(') {
				depth++
				buffer += char
				continue
			}

			if (char === ')') {
				depth = Math.max(0, depth - 1)
				buffer += char
				continue
			}

			if ((char === ';' && depth === 0) || char === '}') {
				collectDeclaration(buffer, parsed)
				buffer = ''
				if (char === '}') {
					depth = 0
				}
				continue
			}

			buffer += char
		}

		collectDeclaration(buffer, parsed)

		return parsed
	}

	/**
	 * Add one scanned chunk to the map when it is a custom property.
	 *
	 * @param {string} chunk The text between two terminators.
	 * @param {Object<string,string>} parsed The map, mutated.
	 * @return {void}
	 */
	function collectDeclaration(chunk, parsed) {
		if (chunk.trim() === '') {
			return
		}

		var match = /(--[\w-]+)\s*:\s*([\s\S]*)$/.exec(chunk)
		if (match === null) {
			return
		}

		var value = match[2].trim().replace(/\s*!\s*important\s*$/i, '').trim()
		if (value === '') {
			return
		}

		parsed[match[1].trim()] = value
	}

	// ------------------------------------------------------------- role logic

	/**
	 * Everything after the vendor prefix — the part that says what a token does.
	 *
	 * @param {string} name Declaration name.
	 * @return {string} The role.
	 */
	function roleOf(name) {
		var bare = name.replace(/^-+/, '')
		var separator = bare.indexOf('-')

		if (separator === -1) {
			return bare
		}

		return bare.slice(separator + 1)
	}

	/**
	 * Whether a role names a palette/scale value rather than a component part.
	 *
	 * @param {string} role The role.
	 * @return {boolean} True when palette.
	 */
	function isPaletteRole(role) {
		for (var i = 0; i < PALETTE_ROLE_HEADS.length; i++) {
			var head = PALETTE_ROLE_HEADS[i]
			if (role === head || role.indexOf(head + '-') === 0) {
				return true
			}
		}

		return false
	}

	/**
	 * Whether a name carries one of the recognised design-system prefixes.
	 *
	 * @param {string} name Declaration name.
	 * @return {boolean} True when the prefix is known.
	 */
	function hasKnownPrefix(name) {
		for (var i = 0; i < COMPONENT_PREFIXES.length; i++) {
			if (name.indexOf(COMPONENT_PREFIXES[i]) === 0) {
				return true
			}
		}

		return false
	}

	/**
	 * Index declarations by role so a rule source matches any prefix. A known
	 * component prefix wins over an unknown one for the same role.
	 *
	 * @param {Object<string,string>} declarations Resolved input.
	 * @return {Object<string,string>} role => value.
	 */
	function indexByRole(declarations) {
		var byRole = {}
		var fromKnown = {}

		Object.keys(declarations).forEach(function (name) {
			var role = roleOf(name)
			if (role === '') {
				return
			}

			var isKnown = hasKnownPrefix(name)

			if (Object.prototype.hasOwnProperty.call(byRole, role)
				&& (isKnown === false || Object.prototype.hasOwnProperty.call(fromKnown, role))) {
				return
			}

			byRole[role] = declarations[name]
			if (isKnown === true) {
				fromKnown[role] = true
			}
		})

		return byRole
	}

	// ------------------------------------------------------------ colour maths

	/**
	 * Parse a CSS colour literal into [r, g, b]. Mirrors
	 * `ContrastService::parseColor()`: #rgb, #rrggbb, rgb(), rgba() only.
	 *
	 * @param {string} value Raw value.
	 * @return {Array<number>|null} The triple, or null.
	 */
	function parseColor(value) {
		var trimmed = String(value).trim()
		var hex = /^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.exec(trimmed)

		if (hex !== null) {
			var digits = hex[1]
			if (digits.length === 3) {
				digits = digits.charAt(0) + digits.charAt(0)
					+ digits.charAt(1) + digits.charAt(1)
					+ digits.charAt(2) + digits.charAt(2)
			}

			return [
				parseInt(digits.slice(0, 2), 16),
				parseInt(digits.slice(2, 4), 16),
				parseInt(digits.slice(4, 6), 16),
			]
		}

		var rgb = /^rgba?\(\s*(\d{1,3})\s*,\s*(\d{1,3})\s*,\s*(\d{1,3})\s*(?:,\s*[\d.]+\s*)?\)$/.exec(trimmed)
		if (rgb !== null) {
			return [
				Math.min(255, parseInt(rgb[1], 10)),
				Math.min(255, parseInt(rgb[2], 10)),
				Math.min(255, parseInt(rgb[3], 10)),
			]
		}

		return null
	}

	/**
	 * WCAG 2.1 relative luminance. Mirrors `ContrastService`.
	 *
	 * @param {Array<number>} rgb The colour.
	 * @return {number} Luminance in [0, 1].
	 */
	function relativeLuminance(rgb) {
		var channels = rgb.map(function (value) {
			var srgb = value / 255
			if (srgb <= 0.03928) {
				return srgb / 12.92
			}

			return Math.pow((srgb + 0.055) / 1.055, 2.4)
		})

		return (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2])
	}

	/**
	 * WCAG contrast ratio between two colours.
	 *
	 * @param {Array<number>} first First colour.
	 * @param {Array<number>} second Second colour.
	 * @return {number} The ratio.
	 */
	function ratio(first, second) {
		var a = relativeLuminance(first)
		var b = relativeLuminance(second)

		return (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)
	}

	/**
	 * Lower-case `#rrggbb`, clamped. Mirrors `toHex()`.
	 *
	 * @param {Array<number>} rgb The colour.
	 * @return {string} Hex string.
	 */
	function toHex(rgb) {
		return '#' + rgb.map(function (value) {
			var clamped = Math.max(0, Math.min(255, value))

			return ('0' + clamped.toString(16)).slice(-2)
		}).join('')
	}

	/**
	 * Darken a colour by a fraction.
	 *
	 * @param {string} value The colour.
	 * @param {number} fraction 0 unchanged, 1 black.
	 * @return {string} The darkened colour, or the input.
	 */
	function darken(value, fraction) {
		var rgb = parseColor(value)
		if (rgb === null) {
			return value
		}

		var factor = 1 - Math.min(1, Math.max(0, fraction))

		return toHex(rgb.map(function (channel) {
			return Math.max(0, Math.round(channel * factor))
		}))
	}

	/**
	 * Mix a colour with another; `weight` is the share of the base retained.
	 *
	 * @param {string} value Base colour.
	 * @param {string} withValue Colour mixed in.
	 * @param {number} weight Share of the base (0-1).
	 * @return {string} The mixed colour, or the input.
	 */
	function mix(value, withValue, weight) {
		var base = parseColor(value)
		var other = parseColor(withValue)
		if (base === null || other === null) {
			return value
		}

		var share = Math.min(1, Math.max(0, weight))

		return toHex([0, 1, 2].map(function (index) {
			return Math.round((base[index] * share) + (other[index] * (1 - share)))
		}))
	}

	/**
	 * The bare `r, g, b` triplet Nextcloud's `-rgb` tokens want.
	 *
	 * @param {string} value The colour.
	 * @return {string} The triplet, or the input.
	 */
	function rgbTriplet(value) {
		var rgb = parseColor(value)
		if (rgb === null) {
			return value
		}

		return rgb[0] + ', ' + rgb[1] + ', ' + rgb[2]
	}

	/**
	 * Format a number the way PHP's `number_format($v, 2)` plus trailing-zero
	 * trimming does, so `alpha` and `radiusScale` agree across runtimes.
	 *
	 * @param {number} value The number.
	 * @return {string} The formatted number.
	 */
	function trimNumber(value) {
		return value.toFixed(2).replace(/0+$/, '').replace(/\.$/, '')
	}

	/**
	 * Render a colour as `rgba(r, g, b, a)`.
	 *
	 * @param {string} value The colour.
	 * @param {number} fraction Alpha channel.
	 * @return {string} The rgba value, or the input.
	 */
	function alpha(value, fraction) {
		var rgb = parseColor(value)
		if (rgb === null) {
			return value
		}

		var clamped = Math.min(1, Math.max(0, fraction))

		return 'rgba(' + rgb[0] + ', ' + rgb[1] + ', ' + rgb[2] + ', ' + trimNumber(clamped) + ')'
	}

	/**
	 * Scale a px radius and clamp it.
	 *
	 * @param {string} value The radius.
	 * @param {number} factor Multiplier.
	 * @param {number} clampPx Maximum in px.
	 * @return {string} The scaled radius, or the input.
	 */
	function radiusScale(value, factor, clampPx) {
		var match = /^\s*([\d.]+)px\s*$/.exec(value)
		if (match === null) {
			return value
		}

		return trimNumber(Math.min(clampPx, parseFloat(match[1]) * factor)) + 'px'
	}

	/**
	 * Pick the legible foreground for a background.
	 *
	 * @param {string} background The background.
	 * @param {string} light Light candidate.
	 * @param {string} dark Dark candidate.
	 * @return {string} Whichever contrasts better.
	 */
	function onColor(background, light, dark) {
		var rgb = parseColor(background)
		if (rgb === null) {
			return dark
		}

		var lightRgb = parseColor(light) || [255, 255, 255]
		var darkRgb = parseColor(dark) || [0, 0, 0]

		if (ratio(rgb, lightRgb) >= ratio(rgb, darkRgb)) {
			return light
		}

		return dark
	}

	// ------------------------------------------------------------- transforms

	/**
	 * Apply one of the closed transform set. Mirrors `transform()`.
	 *
	 * @param {string} kind Transform name.
	 * @param {string} value Input value.
	 * @param {Object} args Arguments.
	 * @param {Object<string,string>} semantic Semantic layer so far.
	 * @return {string} The transformed value.
	 */
	function applyTransform(kind, value, args, semantic) {
		var options = (args || {})

		switch (kind) {
			case 'darken':
				return darken(value, numberOr(options.fraction, 0.1))

			case 'mix':
				var withValue = (options.with === undefined ? '#ffffff' : String(options.with))
				if (withValue.indexOf('--') === 0) {
					withValue = (semantic[withValue] === undefined ? '#ffffff' : semantic[withValue])
				}

				return mix(value, withValue, numberOr(options.weight, 0.5))

			case 'rgbTriplet':
				return rgbTriplet(value)

			case 'alpha':
				return alpha(value, numberOr(options.fraction, 0.5))

			case 'radiusScale':
				return radiusScale(value, numberOr(options.factor, 2), numberOr(options.clampPx, 16))

			case 'onColor':
				return onColor(
					value,
					(options.light === undefined ? '#ffffff' : String(options.light)),
					(options.dark === undefined ? '#000000' : String(options.dark))
				)

			case 'literal':
				var template = (options.template === undefined ? '{value}' : String(options.template))

				return template.split('{value}').join(value)

			case 'copy':
			default:
				return value
		}
	}

	/**
	 * Coerce to a number with a default, the way PHP's `(float)` cast does.
	 *
	 * @param {*} value The raw value.
	 * @param {number} fallbackValue Default when absent.
	 * @return {number} The number.
	 */
	function numberOr(value, fallbackValue) {
		if (value === undefined || value === null) {
			return fallbackValue
		}

		var parsed = Number(value)

		return (isNaN(parsed) ? fallbackValue : parsed)
	}

	// ------------------------------------------------------------------ rules

	/**
	 * Evaluate a rule's `when` condition. Mirrors `whenSatisfied()`.
	 *
	 * @param {Object} when The condition.
	 * @param {Object<string,string>} semantic Semantic layer so far.
	 * @return {boolean} True when the rule should run.
	 */
	function whenSatisfied(when, semantic) {
		var kind = String(when.kind || '')

		if (kind === 'sameColor') {
			var left = semantic[String(when.left || '')]
			var right = semantic[String(when.right || '')]
			if (left === undefined || right === undefined) {
				return false
			}

			return left.trim().toLowerCase() === right.trim().toLowerCase()
		}

		if (kind === 'isDark') {
			var reference = semantic[String(when.ref || '')]
			if (reference === undefined) {
				return false
			}

			var rgb = parseColor(reference)
			if (rgb === null) {
				return false
			}

			return ratio(rgb, [255, 255, 255]) >= 3.0
		}

		return true
	}

	/**
	 * Pick a value out of the brand's own neutral ramp. Mirrors `pickFromRamp()`.
	 *
	 * @param {Object} spec The ramp spec.
	 * @param {Object<string,string>} declarations Resolved input.
	 * @param {Object<string,string>} semantic Semantic layer so far.
	 * @return {string|null} The picked colour, or null.
	 */
	function pickFromRamp(spec, declarations, semantic) {
		var ramp = []
		var seen = {}

		Object.keys(declarations).forEach(function (name) {
			var rgb = parseColor(declarations[name])
			if (rgb === null) {
				return
			}

			if ((Math.max.apply(null, rgb) - Math.min.apply(null, rgb)) > 12) {
				return
			}

			var hex = toHex(rgb)
			if (seen[hex] === true) {
				return
			}

			seen[hex] = true
			ramp.push({ hex: hex, rgb: rgb, sum: rgb[0] + rgb[1] + rgb[2] })
		})

		if (ramp.length === 0) {
			return null
		}

		var criterion = String(spec.criterion || 'darkest')

		if (criterion === 'darkest') {
			return ramp.slice().sort(function (a, b) {
				return a.sum - b.sum
			})[0].hex
		}

		if (criterion === 'nearestLuminance') {
			var targetRgb = parseColor(String(spec.target || '#f2f2f2'))
			if (targetRgb === null) {
				return null
			}

			var targetSum = targetRgb[0] + targetRgb[1] + targetRgb[2]
			var best = null
			var bestDistance = null
			ramp.forEach(function (step) {
				var distance = Math.abs(step.sum - targetSum)
				if (bestDistance === null || distance < bestDistance) {
					bestDistance = distance
					best = step.hex
				}
			})

			return best
		}

		if (criterion === 'minContrast') {
			var againstValue = semantic[String(spec.against || '')]
			var againstRgb = parseColor(againstValue === undefined ? '#ffffff' : againstValue)
			if (againstRgb === null) {
				againstRgb = [255, 255, 255]
			}

			var min = numberOr(spec.min, 4.5)
			var lightestFirst = ramp.slice().sort(function (a, b) {
				return b.sum - a.sum
			})

			for (var i = 0; i < lightestFirst.length; i++) {
				if (ratio(lightestFirst[i].rgb, againstRgb) >= min) {
					return lightestFirst[i].hex
				}
			}

			return null
		}

		return null
	}

	/**
	 * Run a rule's fallback. Mirrors `applyFallback()`.
	 *
	 * @param {Object|null} fallback The spec.
	 * @param {Object<string,string>} semantic Semantic layer so far.
	 * @param {Object<string,string>} declarations Resolved input.
	 * @return {Object|null} `{value, source, reason}` or null.
	 */
	function applyFallback(fallback, semantic, declarations) {
		if (!fallback) {
			return null
		}

		var kind = String(fallback.kind || '')

		if (kind === 'literal') {
			return {
				value: (fallback.value === undefined ? '' : String(fallback.value)),
				source: '(Nextcloud default)',
				reason: 'nextcloud-default-used',
			}
		}

		if (kind === 'manifest') {
			return null
		}

		if (kind === 'derive') {
			var from = String(fallback.from || '')
			var base = semantic[from]
			if (base === undefined) {
				return null
			}

			return {
				value: applyTransform(
					String(fallback.transform || 'copy'),
					base,
					fallback.args,
					semantic
				),
				source: from,
				reason: 'derived-from-brand',
			}
		}

		if (kind === 'ramp') {
			var picked = pickFromRamp(fallback, declarations, semantic)
			if (picked === null) {
				return null
			}

			return { value: picked, source: '(brand ramp)', reason: 'derived-from-brand' }
		}

		return null
	}

	/**
	 * Whether the first family of a font stack is available. Mirrors
	 * `fontIsAvailable()`, with the uploaded-font list supplied by the caller
	 * (a browser and a CI runner cannot see Nextcloud's app data).
	 *
	 * @param {string} stack The font-family value.
	 * @param {Array<string>} fonts Available family names.
	 * @return {boolean} True when available.
	 */
	function fontIsAvailable(stack, fonts) {
		var first = String(stack).split(',')[0].trim().replace(/^["']|["']$/g, '')
		if (first === '') {
			return false
		}

		var lower = first.toLowerCase()
		if (GENERIC_FONTS.indexOf(lower) !== -1) {
			return true
		}

		var available = (fonts || [])
		for (var i = 0; i < available.length; i++) {
			if (String(available[i]).toLowerCase() === lower) {
				return true
			}
		}

		return BUNDLED_FONTS.indexOf(lower) !== -1
	}

	/**
	 * Apply a rule's guard. Mirrors `applyGuard()`.
	 *
	 * @param {Object} guard The spec.
	 * @param {string} value The value.
	 * @param {Object<string,string>} semantic Semantic layer so far.
	 * @param {Array<string>} fonts Available fonts.
	 * @return {Object} `{value, reason}`.
	 */
	function applyGuard(guard, value, semantic, fonts) {
		var kind = String(guard.kind || '')

		if (kind === 'fontAvailable') {
			if (fontIsAvailable(value, fonts) === false) {
				return { value: value, reason: String(guard.onFail || 'font-not-bundled') }
			}

			return { value: value, reason: null }
		}

		if (kind !== 'contrast') {
			return { value: value, reason: null }
		}

		var againstValue = semantic[String(guard.against || '')]
		if (againstValue === undefined) {
			return { value: value, reason: null }
		}

		var rgb = parseColor(value)
		var againstRgb = parseColor(againstValue)
		if (rgb === null || againstRgb === null) {
			return { value: value, reason: null }
		}

		var min = numberOr(guard.min, 4.5)
		if (ratio(rgb, againstRgb) >= min) {
			return { value: value, reason: null }
		}

		var candidate = value
		for (var step = 0; step < MAX_CONTRAST_STEPS; step++) {
			candidate = darken(candidate, 0.1)
			var candidateRgb = parseColor(candidate)
			if (candidateRgb === null) {
				break
			}

			if (ratio(candidateRgb, againstRgb) >= min) {
				return { value: candidate, reason: String(guard.onFail || 'contrast-adjusted') }
			}
		}

		return { value: candidate, reason: String(guard.onFail || 'contrast-adjusted') }
	}

	// ------------------------------------------------------------------ policy

	/**
	 * Substitute the brand-prefix placeholder.
	 *
	 * @param {string} name Table name.
	 * @param {string} slug Brand slug.
	 * @return {string} Concrete name.
	 */
	function withSlug(name, slug) {
		return String(name).split('{p}').join(slug)
	}

	/**
	 * Turn a `*` pattern into an anchored regex, escaping everything else.
	 *
	 * @param {string} pattern The pattern.
	 * @return {RegExp} The regex.
	 */
	function patternToRegex(pattern) {
		var escaped = pattern.replace(/[.\\+?^$[\](){}|/-]/g, '\\$&')

		return new RegExp('^' + escaped.split('*').join('.*') + '$')
	}

	/**
	 * The `never` policy entry matching a name, by name or by role. Mirrors
	 * `policyFor()`.
	 *
	 * @param {string} name Declaration name.
	 * @param {string} slug Brand slug.
	 * @param {Object} table The mapping table.
	 * @return {Object|null} The entry, or null.
	 */
	function policyFor(name, slug, table) {
		var role = roleOf(name)
		var never = (table.never || [])

		for (var i = 0; i < never.length; i++) {
			var pattern = withSlug(String(never[i].match || ''), slug)
			if (pattern === '') {
				continue
			}

			if (patternToRegex(pattern).test(name) === true) {
				return never[i]
			}

			var rolePattern = roleOf(pattern)
			if (rolePattern === '' || rolePattern === pattern) {
				continue
			}

			if (patternToRegex(rolePattern).test(role) === true) {
				return never[i]
			}
		}

		return null
	}

	/**
	 * Every rule target, source and source role. Mirrors `ruleTargets()`.
	 *
	 * @param {string} slug Brand slug.
	 * @param {Object} table The mapping table.
	 * @return {Object} `{targets, sources, roles}` lookup maps.
	 */
	function ruleTargets(slug, table) {
		var targets = {}
		var sources = {}
		var roles = {}

		;(table.rules || []).forEach(function (rule) {
			var target = String(rule.target || '')
			if (target !== '' && target.indexOf('manifest:') !== 0) {
				targets[target] = true
			}

			;(rule.sources || []).forEach(function (source) {
				var concrete = withSlug(String(source), slug)
				sources[concrete] = true

				var role = roleOf(concrete)
				if (role !== '') {
					roles[role] = true
				}
			})
		})

		return { targets: targets, sources: sources, roles: roles }
	}

	// ------------------------------------------------------------- conversion

	/**
	 * Determine which of the four accepted shapes the content is. Mirrors
	 * `detectInput()`.
	 *
	 * @param {string} content Raw content.
	 * @return {string} `A`, `B`, `C` or `D`.
	 * @throws {Error} When nothing matches (`code` 422).
	 */
	function detectInput(content) {
		var trimmed = content.trim()

		if (trimmed !== '' && (trimmed.charAt(0) === '{' || trimmed.charAt(0) === '[')) {
			var decoded = null
			try {
				decoded = JSON.parse(trimmed)
			} catch (error) {
				decoded = null
			}

			if (decoded !== null && typeof decoded === 'object') {
				return (hasDtcgLeaf(decoded, 0) === true ? 'B' : 'C')
			}
		}

		var stripped = content.replace(/\/\*[\s\S]*?\*\//g, '')
		if (/--[\w-]+\s*:/.test(stripped) === false) {
			throw converterError(
				'The content is not a theme this app can convert. Accepted: built theme CSS with a'
				+ ' class-scoped block of --custom-properties, a W3C Design Tokens JSON document, a'
				+ ' Style Dictionary tokens.json, or an existing :root token set.',
				422
			)
		}

		if (/:root\s*\{/i.test(stripped) === true && /--nldesign-/.test(stripped) === true) {
			return 'D'
		}

		return 'A'
	}

	/**
	 * Whether any node is a DTCG leaf (`$value`). Mirrors `hasDtcgLeaf()`.
	 *
	 * @param {*} node Current node.
	 * @param {number} depth Recursion depth.
	 * @return {boolean} True when found.
	 */
	function hasDtcgLeaf(node, depth) {
		if (node === null || typeof node !== 'object' || depth > 12) {
			return false
		}

		if (Object.prototype.hasOwnProperty.call(node, '$value')) {
			return true
		}

		var keys = Object.keys(node)
		for (var i = 0; i < keys.length; i++) {
			if (hasDtcgLeaf(node[keys[i]], depth + 1) === true) {
				return true
			}
		}

		return false
	}

	/**
	 * An error carrying an HTTP-ish status, matching the PHP RuntimeException.
	 *
	 * @param {string} message The message.
	 * @param {number} code The status.
	 * @return {Error} The error.
	 */
	function converterError(message, code) {
		var error = new Error(message)
		error.code = code

		return error
	}

	/**
	 * Collect declarations from every selector block, reporting at-rules.
	 * Mirrors `parseCssBlocks()`.
	 *
	 * @param {string} content Raw CSS.
	 * @param {Array} report The report, mutated.
	 * @return {Object<string,string>} Declarations.
	 */
	function parseCssBlocks(content, report) {
		var stripped = content.replace(/\/\*[\s\S]*?\*\//g, '')
		var atRules = stripped.match(/@([a-z-]+)[^{;]*/gi)

		if (atRules !== null) {
			var seenAtRules = {}
			atRules.forEach(function (atRule) {
				if (seenAtRules[atRule] === true) {
					return
				}
				seenAtRules[atRule] = true
				report.push({
					source: atRule.trim(),
					target: '',
					action: 'skipped',
					reason: 'at-rule-not-converted',
					value: null,
				})
			})
		}

		stripped = stripped.replace(/@[a-z-]+[^{;]*\{(?:[^{}]*\{[^{}]*\})*[^{}]*\}/gi, '')
		stripped = stripped.replace(/@[a-z-]+[^;{]*;/gi, '')

		var declarations = {}
		var blocks = /([^{}]+)\{([^{}]*)\}/g
		var block

		while ((block = blocks.exec(stripped)) !== null) {
			var parsed = parseDeclarations(block[2])
			Object.keys(parsed).forEach(function (name) {
				declarations[name] = parsed[name]
			})
		}

		return declarations
	}

	/**
	 * Flatten a Style Dictionary tree. Mirrors `collectStyleDictionaryLeaves()`.
	 *
	 * @param {*} node Current node.
	 * @param {Array<string>} path Path so far.
	 * @param {string} slug Brand slug.
	 * @param {Object<string,string>} declarations Collected, mutated.
	 * @param {number} depth Recursion depth.
	 * @return {void}
	 */
	function collectStyleDictionaryLeaves(node, path, slug, declarations, depth) {
		if (node === null || typeof node !== 'object' || depth > 12) {
			return
		}

		if (Object.prototype.hasOwnProperty.call(node, 'value')
			&& (node.value === null || typeof node.value !== 'object')) {
			var segments = path.map(function (segment) {
				return String(segment).replace(/[^A-Za-z0-9]+/g, '-').toLowerCase()
			}).filter(function (segment) {
				return segment !== ''
			})

			var joined = segments.join('-').replace(/^-+|-+$/g, '')
			if (joined === '') {
				return
			}

			var prefixed = '--' + slug + '-' + joined
			for (var i = 0; i < COMPONENT_PREFIXES.length; i++) {
				var bare = COMPONENT_PREFIXES[i].replace(/^-+|-+$/g, '')
				if (joined.indexOf(bare + '-') === 0) {
					prefixed = '--' + joined
					break
				}
			}

			declarations[prefixed] = String(node.value).trim()

			return
		}

		Object.keys(node).forEach(function (key) {
			if (key.indexOf('$') === 0) {
				return
			}

			collectStyleDictionaryLeaves(node[key], path.concat([key]), slug, declarations, depth + 1)
		})
	}

	/**
	 * Drop declarations pointing at a remote host. Mirrors `stripExternalUrls()`.
	 *
	 * @param {Object<string,string>} declarations Parsed declarations.
	 * @param {Array} report The report, mutated.
	 * @return {Object<string,string>} The kept declarations.
	 */
	function stripExternalUrls(declarations, report) {
		var kept = {}

		Object.keys(declarations).forEach(function (name) {
			if (/url\(\s*['"]?(?:https?:)?\/\//i.test(declarations[name]) === true) {
				report.push({
					source: name,
					target: '',
					action: 'skipped',
					reason: 'external-url-blocked',
					value: null,
				})

				return
			}

			kept[name] = declarations[name]
		})

		return kept
	}

	/**
	 * Resolve one value's `var()` references. Mirrors `resolveValue()`.
	 *
	 * @param {string} value Raw value.
	 * @param {Object<string,string>} declarations Full map.
	 * @param {Object<string,boolean>} seen Cycle guard.
	 * @param {number} depth Current depth.
	 * @return {string|null} The literal, or null.
	 */
	function resolveValue(value, declarations, seen, depth) {
		if (value.indexOf('var(') === -1) {
			return value.trim()
		}

		if (depth >= MAX_VAR_DEPTH) {
			return null
		}

		var failed = false
		var result = value.replace(
			/var\(\s*(--[\w-]+)\s*(?:,\s*([^()]*(?:\([^()]*\)[^()]*)*))?\)/g,
			function (whole, reference, fallbackValue) {
				if (seen[reference] !== true
					&& Object.prototype.hasOwnProperty.call(declarations, reference)) {
					var nextSeen = Object.assign({}, seen)
					nextSeen[reference] = true
					var inner = resolveValue(declarations[reference], declarations, nextSeen, depth + 1)
					if (inner !== null) {
						return inner
					}
				}

				if (fallbackValue !== undefined && String(fallbackValue).trim() !== '') {
					var innerFallback = resolveValue(String(fallbackValue).trim(), declarations, seen, depth + 1)
					if (innerFallback !== null) {
						return innerFallback
					}
				}

				failed = true

				return ''
			}
		)

		if (failed === true) {
			return null
		}

		return result.trim()
	}

	/**
	 * Resolve every `var()` chain to a literal. Mirrors `resolveVars()`.
	 *
	 * @param {Object<string,string>} declarations Parsed declarations.
	 * @param {Array} report The report, mutated.
	 * @return {Object<string,string>} Declarations with literal values.
	 */
	function resolveVars(declarations, report) {
		var resolved = {}

		Object.keys(declarations).forEach(function (name) {
			var seen = {}
			seen[name] = true
			var literal = resolveValue(declarations[name], declarations, seen, 0)

			if (literal === null) {
				report.push({
					source: name,
					target: '',
					action: 'skipped',
					reason: 'unresolved-var',
					value: declarations[name],
				})

				return
			}

			resolved[name] = literal
		})

		return resolved
	}

	/**
	 * Decode a base64 payload to a byte string (one code unit per byte).
	 *
	 * Latin-1 rather than UTF-8 on purpose: the payload may be a PNG, and the
	 * caller writes it back out with the matching encoding, so the bytes make
	 * the round trip unchanged and `.length` is the byte count PHP's `strlen()`
	 * reports for the same input.
	 *
	 * @param {string} payload The base64 text.
	 * @return {string|null} The bytes, or null when the payload is not base64.
	 */
	function base64ToBytes(payload) {
		var clean = payload.replace(/\s+/g, '')

		try {
			if (typeof Buffer !== 'undefined' && typeof Buffer.from === 'function') {
				var buffer = Buffer.from(clean, 'base64')
				// Node accepts sloppy base64 silently; a re-encode that does not
				// round-trip is the same rejection PHP's strict decode makes.
				if (buffer.toString('base64').replace(/=+$/, '') !== clean.replace(/=+$/, '')) {
					return null
				}
				return buffer.toString('latin1')
			}
			return atob(clean)
		} catch (error) {
			return null
		}
	}

	/**
	 * Percent-decode a payload to a byte string. Mirrors PHP `rawurldecode()`,
	 * which decodes `%XX` and leaves `+` alone.
	 *
	 * @param {string} payload The percent-encoded text.
	 * @return {string} The bytes.
	 */
	function percentToBytes(payload) {
		return payload.replace(/%([0-9A-Fa-f]{2})/g, function (match, hex) {
			return String.fromCharCode(parseInt(hex, 16))
		})
	}

	/**
	 * Decode a `data:image/*` URI. Mirrors `decodeLogoUri()`.
	 *
	 * @param {string} uri The `url()` target.
	 * @param {Object} spec The table's `logo` block.
	 * @return {Object|null} `{extension, contents}`, or null.
	 */
	function decodeLogoUri(uri, spec) {
		var match = /^data:image\/([a-z0-9.+-]+)\s*(;[^,]*)?,([\s\S]*)$/i.exec(uri)
		if (match === null) {
			return null
		}

		var types = (spec.types || {})
		var type = match[1].toLowerCase()
		if (types[type] === undefined) {
			return null
		}

		var parameters = String(match[2] || '').toLowerCase()
		var contents = parameters.indexOf('base64') === -1
			? percentToBytes(match[3])
			: base64ToBytes(match[3])

		if (contents === null) {
			return null
		}

		var maxBytes = (spec.maxBytes === undefined ? 262144 : Number(spec.maxBytes))
		if (contents === '' || contents.length > maxBytes) {
			return null
		}

		return { extension: String(types[type]), contents: contents }
	}

	/**
	 * Normalise a relative `url()` target to an app-relative asset path.
	 * Mirrors `localLogoPath()`.
	 *
	 * @param {string} target The `url()` target.
	 * @param {Object} spec The table's `logo` block.
	 * @return {string|null} The path, or null.
	 */
	function localLogoPath(target, spec) {
		if (/^([a-z][a-z0-9+.-]*:|\/\/)/i.test(target) === true) {
			return null
		}

		var path = target.replace(/^(\.\.\/)+/, '').replace(/^\/+/, '')
		var directory = String(spec.directory) + '/'

		if (path.indexOf(directory) !== 0 || path.indexOf('..') !== -1) {
			return null
		}

		return path
	}

	/**
	 * The logo tokens to try, in the table's priority order. Mirrors
	 * `logoCandidates()`.
	 *
	 * @param {Object<string,string>} declarations Resolved input.
	 * @param {string} slug Brand slug.
	 * @param {Object} spec The table's `logo` block.
	 * @return {Array<string>} The candidate names, in order.
	 */
	function logoCandidates(declarations, slug, spec) {
		var candidates = []

		;(spec.sources || []).forEach(function (source) {
			var name = withSlug(String(source), slug)
			if (declarations[name] !== undefined && candidates.indexOf(name) === -1) {
				candidates.push(name)
			}
		})

		var roles = {}
		;(spec.roles || []).forEach(function (role) {
			roles[String(role)] = true
		})

		Object.keys(declarations).forEach(function (name) {
			if (roles[roleOf(name)] === true && candidates.indexOf(name) === -1) {
				candidates.push(name)
			}
		})

		return candidates
	}

	/**
	 * Every other declaration carrying the same artwork. Mirrors
	 * `logoAliases()`.
	 *
	 * @param {Object<string,string>} declarations Resolved input.
	 * @param {string} source The chosen source declaration.
	 * @param {string} value The chosen source's value.
	 * @return {Array<string>} The alias names.
	 */
	function logoAliases(declarations, source, value) {
		var aliases = []

		Object.keys(declarations).forEach(function (name) {
			if (name !== source && declarations[name] === value) {
				aliases.push(name)
			}
		})

		return aliases
	}

	/**
	 * Lift the theme's logo out of the declarations into a writable asset.
	 * Mirrors `extractLogo()` — see that method for why the logo cannot be a
	 * `rules[]` entry.
	 *
	 * @param {Object<string,string>} declarations Resolved input.
	 * @param {string} slug Brand slug.
	 * @param {string} assetName Base name for the written file, without extension.
	 * @param {Object} table The mapping table.
	 * @param {Array} report The report, mutated.
	 * @return {Object|null} `{source, aliases, path, css, contents}`, or null.
	 */
	function extractLogo(declarations, slug, assetName, table, report) {
		var spec = table.logo
		if (spec === undefined || spec === null) {
			return null
		}

		var candidates = logoCandidates(declarations, slug, spec)

		for (var index = 0; index < candidates.length; index++) {
			var name = candidates[index]
			var value = declarations[name]
			var urlMatch = /url\(\s*(['"]?)([\s\S]*?)\1\s*\)/i.exec(value)
			var target = (urlMatch === null ? '' : urlMatch[2].trim())

			if (target === '' || target.toLowerCase() === 'none') {
				continue
			}

			var decoded = decodeLogoUri(target, spec)

			if (decoded === null) {
				var existing = localLogoPath(target, spec)
				if (existing === null) {
					report.push({
						source: name,
						target: '--nldesign-logo-url',
						action: 'skipped',
						reason: 'logo-format-unsupported',
						value: (value.length <= 120 ? value : value.slice(0, 117) + '…'),
					})
					continue
				}

				return {
					source: name,
					aliases: logoAliases(declarations, name, value),
					path: existing,
					css: "url('../../" + existing + "')",
					contents: null,
				}
			}

			var path = String(spec.directory) + '/' + assetName + '.' + decoded.extension

			report.push({
				source: name,
				target: '--nldesign-logo-url',
				action: 'applied',
				reason: String(spec.reason || 'logo-extracted'),
				value: path,
			})

			return {
				source: name,
				aliases: logoAliases(declarations, name, value),
				path: path,
				css: "url('../../" + path + "')",
				contents: decoded.contents,
			}
		}

		return null
	}

	/**
	 * Split the input into palette, component and pre-existing semantic layers.
	 * Mirrors `classify()`.
	 *
	 * @param {Object<string,string>} declarations Resolved input.
	 * @param {string} slug Brand slug.
	 * @param {Object} table The mapping table.
	 * @param {Object<string,boolean>} vocabulary The app's `--nldesign-*` names.
	 * @param {Array} report The report, mutated.
	 * @return {Object} `{palette, component, semantic}`.
	 */
	function classify(declarations, slug, table, vocabulary, report) {
		var palette = {}
		var component = {}
		var semantic = {}
		var lookup = ruleTargets(slug, table)

		Object.keys(declarations).forEach(function (name) {
			var value = declarations[name]
			var policy = policyFor(name, slug, table)

			if (policy !== null && String(policy.action || '') === 'skip') {
				report.push({
					source: name,
					target: '',
					action: 'skipped',
					reason: String(policy.reason),
					value: value,
				})

				return
			}

			var role = roleOf(name)
			var isComponent = (role !== '' && isPaletteRole(role) === false)

			if (isComponent === true) {
				var emitted = (hasKnownPrefix(name) === true ? name : '--' + slug + '-' + role)
				component[emitted] = value

				if (policy !== null && String(policy.action || '') === 'keep') {
					report.push({
						source: name,
						target: emitted,
						action: 'kept',
						reason: String(policy.reason),
						value: value,
					})

					return
				}

				if (lookup.sources[name] !== true && lookup.roles[role] !== true) {
					report.push({
						source: name,
						target: emitted,
						action: 'kept',
						reason: 'unmapped',
						value: value,
					})
				}

				return
			}

			if (name.indexOf('--nldesign-') === 0) {
				if (vocabulary[name] === true || lookup.targets[name] === true) {
					semantic[name] = value
					report.push({
						source: name,
						target: name,
						action: 'kept',
						reason: 'kept-existing-value',
						value: value,
					})

					return
				}

				var reprefixed = '--' + slug + '-' + name.slice('--nldesign-'.length)
				palette[reprefixed] = value
				report.push({
					source: name,
					target: reprefixed,
					action: 'adapted',
					reason: 'palette-reprefixed',
					value: value,
				})

				return
			}

			var renamed = (name.indexOf('--' + slug + '-') === 0 ? name : '--' + slug + '-' + role)
			palette[renamed] = value

			if (policy !== null && String(policy.action || '') === 'keep') {
				report.push({
					source: name,
					target: renamed,
					action: 'kept',
					reason: String(policy.reason),
					value: value,
				})

				return
			}

			if (lookup.sources[name] !== true && lookup.roles[role] !== true) {
				report.push({
					source: name,
					target: renamed,
					action: 'kept',
					reason: 'unmapped',
					value: value,
				})
			}
		})

		return { palette: palette, component: component, semantic: semantic }
	}

	/**
	 * Produce the semantic layer from the table. Mirrors `runRules()`.
	 *
	 * @param {Object<string,string>} declarations Resolved input.
	 * @param {Object<string,string>} existing Values the input already declared.
	 * @param {string} slug Brand slug.
	 * @param {Object} table The mapping table.
	 * @param {Array<string>} fonts Available fonts.
	 * @param {Object} manifest Manifest targets, mutated.
	 * @param {Array} report The report, mutated.
	 * @return {Object<string,string>} The semantic layer.
	 */
	function runRules(declarations, existing, slug, table, fonts, manifest, report) {
		var semantic = Object.assign({}, existing)
		var byRole = indexByRole(declarations)

		;(table.rules || []).forEach(function (rule) {
			var target = String(rule.target || '')
			if (target === '') {
				return
			}

			var isManifest = (target.indexOf('manifest:') === 0)

			if (isManifest === false && Object.prototype.hasOwnProperty.call(semantic, target)) {
				return
			}

			if (rule.when !== undefined && whenSatisfied(rule.when, semantic) === false) {
				return
			}

			var value = null
			var action = 'applied'
			var reason = (rule.reason === undefined ? null : rule.reason)
			var sourceName = ''
			var sources = (rule.sources || [])

			for (var i = 0; i < sources.length; i++) {
				var concrete = withSlug(String(sources[i]), slug)

				if (Object.prototype.hasOwnProperty.call(declarations, concrete)) {
					value = declarations[concrete]
					sourceName = concrete
					break
				}

				var role = roleOf(concrete)
				if (role !== '' && Object.prototype.hasOwnProperty.call(byRole, role)) {
					value = byRole[role]
					sourceName = role + ' (by role)'
					break
				}
			}

			if (value === null) {
				var derived = applyFallback(rule.fallback, semantic, declarations)
				if (derived === null) {
					return
				}

				value = derived.value
				action = 'adapted'
				sourceName = derived.source
				if (reason === null) {
					reason = derived.reason
				}
			}

			if (rule.transform !== undefined && sourceName !== '' && action === 'applied') {
				value = applyTransform(String(rule.transform), value, rule.args, semantic)
			}

			var entry = {
				source: sourceName,
				target: target,
				action: action,
				reason: reason,
				value: value,
			}

			if (rule.guard !== undefined) {
				var guarded = applyGuard(rule.guard, value, semantic, fonts)
				if (guarded.value !== value) {
					entry.original = value
					entry.action = 'adapted'
					value = guarded.value
					entry.value = value
				}

				if (guarded.reason !== null) {
					entry.reason = guarded.reason
				}
			}

			if (isManifest === true) {
				manifest[target.slice('manifest:'.length)] = value
			} else {
				semantic[target] = value
			}

			report.push(entry)
		})

		return semantic
	}

	// -------------------------------------------------------------- emission

	/**
	 * Tally the report by action. Mirrors `countActions()`.
	 *
	 * @param {Array} report The report.
	 * @return {Object<string,number>} Action => count.
	 */
	function countActions(report) {
		var counts = { applied: 0, adapted: 0, skipped: 0, kept: 0 }

		report.forEach(function (entry) {
			if (counts[entry.action] !== undefined) {
				counts[entry.action]++
			}
		})

		return counts
	}

	/**
	 * A human label for an input kind. Mirrors `inputKindLabel()`.
	 *
	 * @param {string} kind The kind letter.
	 * @return {string} The label.
	 */
	function inputKindLabel(kind) {
		var labels = {
			A: 'built theme CSS',
			B: 'W3C Design Tokens document',
			C: 'Style Dictionary tokens.json',
			D: 'existing token set, add-only',
		}

		return (labels[kind] === undefined ? 'unknown' : labels[kind])
	}

	/**
	 * Emit the token set file. Byte-identical to `buildCss()` — the four
	 * commented sections, each sorted by name, then the provenance block.
	 *
	 * @param {Object} sections `{palette, component, semantic}`.
	 * @param {Object} meta `{slug, inputKind, sourceName, sourceVersion, counts, table, tableHash}`.
	 * @return {string} The complete CSS file.
	 */
	function buildCss(sections, meta) {
		var lines = []

		lines.push('/* GENERATED by Thematiq TokenSetConverterService — do not edit by hand.')
		lines.push(' * Re-run the conversion instead; hand edits are lost on the next run. */')
		lines.push(':root {')

		lines.push('\t/* 1. Brand palette — the theme\'s own steps, under the set\'s own prefix so')
		lines.push('\t *    they can never masquerade as app vocabulary. */')
		pushSorted(lines, sections.palette)

		lines.push('')
		lines.push('\t/* 2. Component layer — emitted verbatim. css/systems/nldesign/utrecht-bridge.css')
		lines.push('\t *    reads these into --nldesign-component-*, and Conduction\'s apps read them')
		lines.push('\t *    directly, which is why components Nextcloud lacks are kept rather than dropped. */')
		pushSorted(lines, sections.component)

		lines.push('')
		lines.push('\t/* 3. Semantic layer — what Nextcloud\'s chrome actually reads, via')
		lines.push('\t *    css/systems/nldesign/theme.css and overrides.css. */')
		pushSorted(lines, sections.semantic)

		lines.push('')
		lines.push('\t/* 4. Provenance — enough to tell two runs apart without running anything.')
		lines.push('\t *    input kind:      ' + meta.inputKind + ' (' + inputKindLabel(meta.inputKind) + ')')
		lines.push('\t *    source:          ' + (meta.sourceName === null || meta.sourceName === undefined ? '(pasted)' : meta.sourceName))
		lines.push('\t *    source version:  ' + (meta.sourceVersion === null || meta.sourceVersion === undefined ? '(not declared)' : meta.sourceVersion))
		lines.push('\t *    slug:            ' + meta.slug)
		lines.push('\t *    converter:       ' + String(meta.table.converterVersion === undefined ? '0' : meta.table.converterVersion))
		lines.push('\t *    mapping table:   v' + String(meta.table.version === undefined ? '0' : meta.table.version) + ' sha256:' + String(meta.tableHash || '').slice(0, 16))
		lines.push('\t *    applied:         ' + meta.counts.applied)
		lines.push('\t *    adapted:         ' + meta.counts.adapted)
		lines.push('\t *    kept:            ' + meta.counts.kept)
		lines.push('\t *    skipped:         ' + meta.counts.skipped)
		lines.push('\t */')
		lines.push('}')
		lines.push('')

		return lines.join('\n')
	}

	/**
	 * Append a section's declarations, sorted by name.
	 *
	 * PHP's `ksort()` and a plain JS sort agree for the ASCII names token sets
	 * use, which is what keeps the two runtimes byte-identical here.
	 *
	 * @param {Array<string>} lines The output lines, mutated.
	 * @param {Object<string,string>} declarations The section.
	 * @return {void}
	 */
	function pushSorted(lines, declarations) {
		Object.keys(declarations).sort().forEach(function (name) {
			lines.push('\t' + name + ': ' + declarations[name] + ';')
		})
	}

	/**
	 * Build the `token-sets.json` entry. Mirrors `buildManifestEntry()`.
	 *
	 * @param {Object} meta `{slug, displayName, sourceName, sourceVersion}`.
	 * @param {Object<string,string>} semantic The semantic layer.
	 * @param {Object<string,string>} manifest Manifest values from the rules.
	 * @param {Object|null} [logo] The extracted logo, from `extractLogo()`.
	 * @return {Object} The manifest entry.
	 */
	function buildManifestEntry(meta, semantic, manifest, logo) {
		var theming = {}
		var primary = manifest['theming.primary_color']

		if (primary === undefined) {
			primary = semantic['--nldesign-color-primary']
		}

		if (primary !== undefined) {
			theming.primary_color = primary
		}

		if (manifest['theming.background_color'] !== undefined) {
			theming.background_color = manifest['theming.background_color']
		}

		// The path the theming-sync dialog offers to Nextcloud's own logo slot.
		if (logo !== null && logo !== undefined) {
			theming.logo = logo.path
		}

		var entry = {
			id: meta.slug,
			name: meta.displayName,
			description: 'Converted from ' + (meta.sourceName || 'a pasted design-system theme') + '.',
			design_system: 'nldesign',
		}

		if (Object.keys(theming).length > 0) {
			entry.theming = theming
		}

		if (meta.sourceName !== null && meta.sourceName !== undefined) {
			entry.upstreamRef = meta.sourceName
		}

		if (meta.sourceVersion !== null && meta.sourceVersion !== undefined) {
			entry.upstreamVersion = meta.sourceVersion
		}

		return entry
	}

	// ------------------------------------------------------------------- API

	/**
	 * Convert a design-system theme into a Nextcloud token set.
	 *
	 * @param {string} content The raw theme content.
	 * @param {Object} options Conversion options.
	 * @param {string} options.slug The token set slug and brand prefix.
	 * @param {string} options.displayName Human-readable set name.
	 * @param {Object} options.table The decoded mapping table.
	 * @param {string} [options.tableHash] SHA-256 of the table file, for provenance.
	 * @param {Object<string,boolean>} [options.vocabulary] The app's `--nldesign-*` names.
	 * @param {Array<string>} [options.fonts] Available font family names.
	 * @param {string|null} [options.sourceName] Provenance label.
	 * @param {string} [options.assetName] Base name for an extracted logo, without extension.
	 *        Defaults to the slug; the admin upload path passes `custom-{slug}` so an uploaded
	 *        theme can never overwrite a shipped `img/logos/{slug}.svg`.
	 * @return {Object} `{css, manifestEntry, report, inputKind, counts, logoAsset}`.
	 * @throws {Error} With `code` 422 when the content matches no accepted shape.
	 */
	function convert(content, options) {
		var settings = (options || {})
		var slug = String(settings.slug || '')
		var table = settings.table

		if (!table || !table.rules) {
			throw converterError('A decoded mapping table with a rule list is required.', 500)
		}

		var report = []
		var inputKind = detectInput(content)
		var sourceVersion = (settings.sourceVersion === undefined ? null : settings.sourceVersion)
		var declarations

		if (inputKind === 'C') {
			declarations = {}
			collectStyleDictionaryLeaves(JSON.parse(content.trim()), [], slug, declarations, 0)
		} else if (inputKind === 'B') {
			// DTCG belongs to DesignTokensMapper on the PHP side; this runtime
			// has no equivalent, so the caller must pre-map a DTCG document.
			throw converterError(
				'A W3C Design Tokens document must be converted through the server, which owns the'
				+ ' DTCG mapping (DesignTokensMapper). Export the theme as CSS or a Style Dictionary'
				+ ' tokens.json to convert it here.',
				422
			)
		} else {
			declarations = parseCssBlocks(content, report)
		}

		if (Object.keys(declarations).length === 0) {
			throw converterError(
				'No custom properties were found. Paste a built theme CSS (a class-scoped block of'
				+ ' --custom-properties), a W3C Design Tokens JSON document, a Style Dictionary'
				+ ' tokens.json, or an existing :root token set.',
				422
			)
		}

		declarations = stripExternalUrls(declarations, report)
		declarations = resolveVars(declarations, report)

		// Lift the theme's logo out of the stylesheet and into a file before the
		// sections are built; see `extractLogo()`.
		var logo = extractLogo(
			declarations,
			slug,
			String(settings.assetName || slug),
			table,
			report
		)

		if (logo !== null) {
			delete declarations[logo.source]
			logo.aliases.forEach(function (alias) {
				declarations[alias] = 'var(--nldesign-logo-url)'
			})
		}

		var vocabulary = (settings.vocabulary || {})
		var sections = classify(declarations, slug, table, vocabulary, report)
		var manifest = {}
		var semantic = runRules(
			declarations,
			sections.semantic,
			slug,
			table,
			(settings.fonts || []),
			manifest,
			report
		)

		if (logo !== null) {
			semantic['--nldesign-logo-url'] = logo.css
		}

		var counts = countActions(report)
		var sourceName = (settings.sourceName === undefined ? null : settings.sourceName)

		return {
			css: buildCss(
				{ palette: sections.palette, component: sections.component, semantic: semantic },
				{
					slug: slug,
					inputKind: inputKind,
					sourceName: sourceName,
					sourceVersion: sourceVersion,
					counts: counts,
					table: table,
					tableHash: (settings.tableHash || ''),
				}
			),
			manifestEntry: buildManifestEntry(
				{
					slug: slug,
					displayName: String(settings.displayName || slug),
					sourceName: sourceName,
					sourceVersion: sourceVersion,
				},
				semantic,
				manifest,
				logo
			),
			report: report,
			inputKind: inputKind,
			counts: counts,
			// Bytes only when the logo was DECODED out of the theme; a theme
			// already pointing at a file on disk yields a path and nothing to
			// write. `contents` is a latin-1 byte string — see base64ToBytes().
			logoAsset: (logo === null || logo.contents === null)
				? null
				: { path: logo.path, contents: logo.contents },
		}
	}

	/**
	 * Extract the app's `--nldesign-*` vocabulary from the two stylesheets that
	 * declare it, so the caller can hand it to `convert()`. Mirrors
	 * `TokenSetConverterService::vocabulary()`.
	 *
	 * @param {Array<string>} stylesheets Contents of defaults.css and utrecht-bridge.css.
	 * @return {Object<string,boolean>} name => true.
	 */
	function vocabularyFrom(stylesheets) {
		var names = {}

		;(stylesheets || []).forEach(function (css) {
			var matches = String(css).match(/--nldesign-[\w-]+/g)
			if (matches === null) {
				return
			}

			matches.forEach(function (name) {
				names[name] = true
			})
		})

		return names
	}

	var api = {
		convert: convert,
		vocabularyFrom: vocabularyFrom,
		// Exposed for the parity fixtures and for reuse by the CLI.
		parseDeclarations: parseDeclarations,
		detectInput: detectInput,
		roleOf: roleOf,
		isPaletteRole: isPaletteRole,
		policyFor: policyFor,
		darken: darken,
		mix: mix,
		rgbTriplet: rgbTriplet,
		alpha: alpha,
		radiusScale: radiusScale,
		onColor: onColor,
		parseColor: parseColor,
		ratio: ratio,
		COMPONENT_PREFIXES: COMPONENT_PREFIXES,
		PALETTE_ROLE_HEADS: PALETTE_ROLE_HEADS,
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else if (typeof window !== 'undefined') {
		window.NldesignTokenConverter = api
	}
})()
