/**
 * Multi-brand source reader, the browser and CLI mirror of lib/Service/MultiBrandSource.php.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Finds the brands in one token source (a Tokens Studio document with two or more
 * `$themes`, or built CSS with two or more `.{key}-theme` blocks) and cuts one brand out
 * as single-brand input. PHP and this file answer the same for the fixtures in
 * tests/Unit/fixtures/multi-brand (expected.json, checked by both runtimes).
 *
 * Dual-mode like `layerSwap.js`: `module.exports` under Node, `window.NldesignMultiBrandSource`
 * in the browser.
 *
 * @spec openspec/changes/authoring-multi-brand-token-source/tasks.md#task-2.4
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.NldesignMultiBrandSource = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	var BRAND_SELECTOR = /^\.([a-z0-9]+(?:-[a-z0-9]+)*)-theme$/

	function isObject(value) {
		return value !== null && typeof value === 'object' && !Array.isArray(value)
	}

	function parseJson(content) {
		try {
			var decoded = JSON.parse(content)
			return isObject(decoded) || Array.isArray(decoded) ? decoded : null
		} catch (error) {
			return null
		}
	}

	function isToken(node) {
		return (
			Object.prototype.hasOwnProperty.call(node, '$value')
			|| (Object.prototype.hasOwnProperty.call(node, 'value')
				&& !isObject(node.value)
				&& !Array.isArray(node.value))
		)
	}

	function leafPaths(node, prefix) {
		if (isToken(node)) {
			return [prefix]
		}
		var paths = []
		Object.keys(node).forEach(function (key) {
			if (isObject(node[key]) && key.charAt(0) !== '$') {
				paths = paths.concat(
					leafPaths(node[key], prefix === '' ? key : prefix + '.' + key),
				)
			}
		})
		return paths
	}

	function replaceTree(base, over) {
		Object.keys(over).forEach(function (key) {
			var node = over[key]
			if (
				isObject(node)
				&& !isToken(node)
				&& isObject(base[key])
				&& !isToken(base[key])
			) {
				base[key] = replaceTree(base[key], node)
				return
			}
			base[key] = node
		})
		return base
	}

	function mergeSets(doc, theme, statuses) {
		var selected = theme.selectedTokenSets || {}
		var order = ((doc.$metadata && doc.$metadata.tokenSetOrder) || []).filter(
			function (n) {
				return typeof n === 'string'
			},
		)
		var names = Object.keys(selected).filter(function (name) {
			return statuses.indexOf(selected[name]) !== -1
		})
		names.sort(function (a, b) {
			var ia = order.indexOf(a)
			var ib = order.indexOf(b)
			if (ia === -1 || ib === -1) {
				if (ia === -1 && ib === -1) {
					return a < b ? -1 : a > b ? 1 : 0
				}
				return ia === -1 ? 1 : -1
			}
			return ia - ib
		})
		var merged = {}
		names.forEach(function (name) {
			if (isObject(doc[name])) {
				merged = replaceTree(merged, JSON.parse(JSON.stringify(doc[name])))
			}
		})
		return merged
	}

	function blocks(css) {
		var clean = String(css).replace(/\/\*[\s\S]*?\*\//g, '')
		var out = []
		var pattern = /([^{}@;]+)\{([^{}]*)\}/g
		var match
		while ((match = pattern.exec(clean)) !== null) {
			var declarations = {}
			var decl = /(--[A-Za-z0-9_-]+)\s*:\s*([^;]+);?/g
			var d
			while ((d = decl.exec(match[2])) !== null) {
				declarations[d[1]] = d[2].trim()
			}
			out.push({ selector: match[1].trim(), declarations: declarations })
		}
		return out
	}

	/**
	 * The brands of a source; [] for one brand.
	 *
	 * @param {string} content The document.
	 * @return {Array<{key: string, name: string, tokenCount: number, group?: string}>}
	 */
	function detectBrands(content) {
		var doc = parseJson(content)
		var brands = []
		if (doc !== null) {
			if (!Array.isArray(doc.$themes) || doc.$themes.length < 2) {
				return []
			}
			doc.$themes.forEach(function (theme) {
				if (!isObject(theme) || typeof theme.id !== 'string') {
					return
				}
				var brand = {
					key: theme.id,
					name: String(theme.name !== undefined ? theme.name : theme.id),
					tokenCount: leafPaths(mergeSets(doc, theme, ['enabled']), '')
						.length,
				}
				if (typeof theme.group === 'string') {
					brand.group = theme.group
				}
				brands.push(brand)
			})
			return brands.length >= 2 ? brands : []
		}
		blocks(content).forEach(function (block) {
			var match = BRAND_SELECTOR.exec(block.selector)
			if (match !== null) {
				brands.push({
					key: match[1],
					name: (
						match[1].charAt(0).toUpperCase() + match[1].slice(1)
					).replace(/-/g, ' '),
					tokenCount: Object.keys(block.declarations).length,
				})
			}
		})
		return brands.length >= 2 ? brands : []
	}

	/**
	 * One brand as single-brand input.
	 *
	 * @param {string} content The document.
	 * @param {string} key The brand.
	 * @return {{content: string, referenceOnlyPaths: Array<string>}}
	 */
	function cut(content, key) {
		var doc = parseJson(content)
		if (doc !== null) {
			var theme = (doc.$themes || []).filter(function (entry) {
				return isObject(entry) && entry.id === key
			})[0]
			if (!theme) {
				throw new Error('unknown brand: ' + key)
			}
			var merged = mergeSets(doc, theme, ['enabled', 'source'])
			var enabled = leafPaths(mergeSets(doc, theme, ['enabled']), '')
			return {
				content: JSON.stringify(merged),
				referenceOnlyPaths: leafPaths(merged, '').filter(function (path) {
					return enabled.indexOf(path) === -1
				}),
			}
		}
		var shared = []
		var own = null
		blocks(content).forEach(function (block) {
			var match = BRAND_SELECTOR.exec(block.selector)
			if (match !== null) {
				if (match[1] === key) {
					own = block
				}
				return
			}
			if (/^\.([a-z0-9-]+)-theme--/.test(block.selector)) {
				return
			}
			shared.push(block)
		})
		if (own === null) {
			throw new Error('unknown brand: ' + key)
		}
		var css = shared
			.concat([own])
			.map(function (block) {
				return (
					block.selector
					+ ' {\n'
					+ Object.keys(block.declarations)
						.map(function (name) {
							return (
								'  ' + name + ': ' + block.declarations[name] + ';'
							)
						})
						.join('\n')
					+ '\n}\n'
				)
			})
			.join('')
		return { content: css, referenceOnlyPaths: [] }
	}

	return { detectBrands: detectBrands, cut: cut }
})
