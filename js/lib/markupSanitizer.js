/**
 * Markup sanitiser for the playground's "Your component" stage.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Cleans pasted HTML and CSS against an allowlist before it reaches the preview
 * frame, and reports what it removed so a builder is never left guessing. It is
 * the third lock: the frame is already sandboxed without scripts, and its own
 * Content Security Policy refuses every load outside 'self' and data:.
 *
 * The HTML is parsed with DOMParser, which never runs scripts. Kept: structural
 * and text elements, lists, tables, form controls without a form, `details`,
 * `dialog` and SVG drawing elements. Removed with their content: everything else,
 * among them script, style, iframe, object, embed, link, meta, base, form,
 * template and noscript. Attributes: an allowlist, every `on*` removed, `href`
 * only as `#fragment`, `src` only as `data:image/...`, and `url()` only for `data:`.
 *
 * Dual-mode like `layerSwap.js`: `module.exports` under Node, `window.NldesignMarkupSanitizer`
 * in the browser.
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-markup-is-cleaned-against-an-allowlist-every-time-it-renders
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.NldesignMarkupSanitizer = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	var ELEMENTS = (
		'div span p br hr h1 h2 h3 h4 h5 h6 strong em b i u s small mark sub sup code pre kbd '
		+ 'blockquote q cite abbr time address article aside section header footer main nav figure '
		+ 'figcaption ul ol li dl dt dd table thead tbody tfoot tr th td caption colgroup col a img '
		+ 'picture button input select option optgroup textarea label fieldset legend details '
		+ 'summary dialog progress meter output svg g path circle ellipse line polyline polygon rect '
		+ 'text tspan defs lineargradient radialgradient stop use symbol title desc'
	).split(' ')

	var ATTRIBUTES = (
		'class id role style title alt type name value placeholder disabled checked selected for '
		+ 'open tabindex href src width height colspan rowspan scope lang dir hidden min max step '
		+ 'readonly required multiple size cols rows datetime '
		+ 'viewbox d fill stroke stroke-width stroke-linecap stroke-linejoin cx cy r rx ry x y x1 '
		+ 'y1 x2 y2 points transform opacity fill-rule clip-rule offset stop-color stop-opacity '
		+ 'xmlns focusable preserveaspectratio'
	).split(' ')

	/**
	 * A tally of what was removed, by kind.
	 *
	 * @return {{counts: Object<string, number>, add: Function}} The tally.
	 */
	function tally() {
		var counts = {}
		return {
			counts: counts,
			add: function (kind) {
				counts[kind] = (counts[kind] || 0) + 1
			},
		}
	}

	/**
	 * Keep only `url()` references to data: URIs.
	 *
	 * @param {string} css The CSS text.
	 * @param {{add: Function}} removed The tally.
	 * @return {string} The CSS without external addresses.
	 */
	function cleanUrls(css, removed) {
		return String(css).replace(
			/url\(\s*(['"]?)([^'")]*)\1\s*\)/gi,
			function (match, quote, address) {
				if (/^data:/i.test(address.trim())) {
					return match
				}
				removed.add('external address')
				return 'none'
			},
		)
	}

	/**
	 * Clean the CSS field: no @import, no external url(), nothing that ends the style element.
	 *
	 * @param {string} css The pasted CSS.
	 * @return {{css: string, removed: Object<string, number>}} The cleaned CSS and the tally.
	 */
	function sanitizeCss(css) {
		var removed = tally()
		var out = String(css || '').replace(/@import[^;]*;?/gi, function () {
			removed.add('import')
			return ''
		})
		out = cleanUrls(out, removed)
		out = out.replace(/<\/?style/gi, function () {
			removed.add('style tag')
			return ''
		})
		out = out.replace(/expression\s*\(|javascript:|behavior\s*:/gi, function () {
			removed.add('script')
			return ''
		})
		return { css: out, removed: removed.counts }
	}

	/**
	 * Clean one attribute in place.
	 *
	 * @param {Element} node The element.
	 * @param {Attr} attribute The attribute.
	 * @param {{add: Function}} removed The tally.
	 * @return {void}
	 */
	function cleanAttribute(node, attribute, removed) {
		var name = attribute.name.toLowerCase()
		var value = attribute.value
		if (name.indexOf('on') === 0) {
			removed.add('event handler')
			node.removeAttribute(attribute.name)
			return
		}
		var allowed =
			ATTRIBUTES.indexOf(name) !== -1
			|| name.indexOf('aria-') === 0
			|| name.indexOf('data-') === 0
		if (!allowed) {
			removed.add('attribute')
			node.removeAttribute(attribute.name)
			return
		}
		if (name === 'href' && !/^#[\w-]*$/.test(value.trim())) {
			removed.add(/^\s*javascript:/i.test(value) ? 'script link' : 'link')
			node.removeAttribute(attribute.name)
			return
		}
		if (name === 'src' && !/^data:image\//i.test(value.trim())) {
			removed.add('external address')
			node.removeAttribute(attribute.name)
			return
		}
		if (name === 'style') {
			var cleaned = cleanUrls(value, removed)
			if (cleaned !== value) {
				node.setAttribute('style', cleaned)
			}
		}
	}

	/**
	 * Walk a subtree, removing what is not on the allowlist.
	 *
	 * @param {Element} parent The node whose children to clean.
	 * @param {{add: Function}} removed The tally.
	 * @return {void}
	 */
	function walk(parent, removed) {
		Array.prototype.slice.call(parent.childNodes).forEach(function (node) {
			if (node.nodeType === 8) {
				parent.removeChild(node)
				return
			}
			if (node.nodeType !== 1) {
				return
			}
			var tag = node.nodeName.toLowerCase()
			if (ELEMENTS.indexOf(tag) === -1) {
				removed.add(tag === 'script' ? 'script' : 'element')
				parent.removeChild(node)
				return
			}
			Array.prototype.slice
				.call(node.attributes)
				.forEach(function (attribute) {
					cleanAttribute(node, attribute, removed)
				})
			walk(node, removed)
		})
	}

	/**
	 * Clean pasted HTML.
	 *
	 * @param {string} html The pasted HTML.
	 * @param {Function} [Parser] A DOMParser constructor; the window's by default.
	 * @return {{html: string, removed: Object<string, number>}} The cleaned HTML and the tally.
	 */
	function sanitizeHtml(html, Parser) {
		var removed = tally()
		var ParserClass =
			Parser || (typeof DOMParser !== 'undefined' ? DOMParser : null)
		if (ParserClass === null) {
			return { html: '', removed: { element: 1 } }
		}
		var doc = new ParserClass().parseFromString(
			'<!doctype html><body>' + String(html || '') + '</body>',
			'text/html',
		)
		walk(doc.body, removed)
		return { html: doc.body.innerHTML, removed: removed.counts }
	}

	/**
	 * Merge tallies.
	 *
	 * @param {...Object<string, number>} parts The tallies.
	 * @return {Object<string, number>} The sum.
	 */
	function merge() {
		var out = {}
		Array.prototype.forEach.call(arguments, function (part) {
			Object.keys(part || {}).forEach(function (kind) {
				out[kind] = (out[kind] || 0) + part[kind]
			})
		})
		return out
	}

	return {
		sanitizeHtml: sanitizeHtml,
		sanitizeCss: sanitizeCss,
		merge: merge,
		ELEMENTS: ELEMENTS,
	}
})
