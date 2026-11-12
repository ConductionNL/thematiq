/**
 * Inert markup renderer for the admin page and the component playground.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * admin.js and playground.js build their panels as markup strings from escaped
 * values. This puts such a string into an element without assigning innerHTML:
 * the markup is parsed with DOMParser, which never runs scripts; script-capable
 * elements, `on*` handler attributes and `javascript:` URLs are dropped; then the
 * parsed nodes replace the element's children. Everything else, `data-*`,
 * `style` and form attributes included, is kept, because the panels rely on it.
 * Pasted, untrusted markup goes through markupSanitizer.js instead.
 *
 * Dual-mode like `layerSwap.js`: `module.exports` under Node,
 * `window.NldesignMarkupRender` in the browser.
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.NldesignMarkupRender = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	var DROPPED_ELEMENTS = 'script, iframe, object, embed, frame, frameset, base, meta, link'
	var URL_ATTRIBUTES = ['href', 'src', 'xlink:href', 'action', 'formaction']

	/**
	 * Whether an attribute can run code: an event handler, or a URL attribute
	 * holding a `javascript:` URL.
	 *
	 * @param {Attr} attribute The attribute.
	 * @return {boolean} True when it must be removed.
	 *
	 * @spec exclude rendering helper; the panels it fills carry their own specs
	 */
	function isActive(attribute) {
		var name = attribute.name.toLowerCase()
		if (name.indexOf('on') === 0) {
			return true
		}
		return (
			URL_ATTRIBUTES.indexOf(name) !== -1
			// eslint-disable-next-line no-control-regex
			&& /^[\s\u0000-\u001f]*javascript:/i.test(attribute.value)
		)
	}

	/**
	 * Parse markup into inert nodes, without anything that can run code.
	 *
	 * @param {string} html The markup.
	 * @return {Node[]} The top-level nodes.
	 *
	 * @spec exclude rendering helper; the panels it fills carry their own specs
	 */
	function parse(html) {
		var body = new DOMParser().parseFromString(String(html), 'text/html').body
		body.querySelectorAll(DROPPED_ELEMENTS).forEach(function (node) {
			node.remove()
		})
		body.querySelectorAll('*').forEach(function (node) {
			Array.prototype.slice.call(node.attributes).forEach(function (attribute) {
				if (isActive(attribute)) {
					node.removeAttribute(attribute.name)
				}
			})
		})
		return Array.prototype.slice.call(body.childNodes)
	}

	/**
	 * Replace an element's children with the parsed markup.
	 *
	 * @param {Element} target The element.
	 * @param {string} html The markup.
	 *
	 * @spec exclude rendering helper; the panels it fills carry their own specs
	 */
	function render(target, html) {
		target.replaceChildren.apply(target, parse(html))
	}

	return {
		parse: parse,
		render: render,
	}
})
