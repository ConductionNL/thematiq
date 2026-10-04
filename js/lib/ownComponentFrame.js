/**
 * The preview frame of the playground's "Your component" stage.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * Three locks keep pasted markup inert: the frame is sandboxed with exactly
 * `allow-same-origin` (no scripts, forms, popups, modals or navigation), its
 * `srcdoc` document carries its own Content Security Policy (no scripts, no load
 * outside 'self' and data:), and the markup is cleaned first (markupSanitizer.js).
 * Same origin lets the parent set custom properties on the frame's root, so a
 * token edit repaints the frame without rebuilding it.
 *
 * Checked in Chromium 151 on 2 Oct 2026 under Nextcloud's settings page policy
 * (default-src 'none', no frame-src): the frame renders, the parent reaches it,
 * a pasted script is refused by the sandbox and an external image by both policies.
 *
 * Dual-mode like `layerSwap.js`: `module.exports` under Node, `window.NldesignOwnComponentFrame`
 * in the browser.
 *
 * @spec openspec/specs/own-component-preview/spec.md#requirement-pasted-markup-renders-in-a-sandboxed-frame-without-scripts
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.NldesignOwnComponentFrame = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	/** The sandbox attribute, fixed: a unit test fails if it changes. */
	var SANDBOX = 'allow-same-origin'

	/** The frame document's own policy, fixed the same way. */
	var POLICY =
		"default-src 'none'; style-src 'unsafe-inline'; font-src 'self' data:; img-src 'self' data:"

	/**
	 * The policy with the page's origin named next to 'self' for fonts.
	 *
	 * 'self' in a srcdoc document should mean the page's origin, but Firefox
	 * blocked the house style fonts under it (#940). Naming the origin as well
	 * lets the same same-origin fonts in on every engine and nothing more. An
	 * origin that is not a plain scheme and host is ignored.
	 *
	 * @param {string} [origin] The page's origin, such as `https://cloud.example.nl`.
	 * @return {string} The policy.
	 */
	function policyFor(origin) {
		if (!/^https?:\/\/[A-Za-z0-9.-]+(:[0-9]+)?$/.test(String(origin || ''))) {
			return POLICY
		}
		return POLICY.replace("font-src 'self'", "font-src 'self' " + origin)
	}

	/**
	 * The custom properties the code reads, in order of first use.
	 *
	 * @param {string} html The HTML (its `style` attributes are read too).
	 * @param {string} css The CSS.
	 * @return {Array<string>} The names.
	 */
	function scanVars(html, css) {
		var names = []
		var pattern = /var\(\s*(--[A-Za-z0-9_-]+)/g
		var text = String(css || '') + '\n' + String(html || '')
		var match
		while ((match = pattern.exec(text)) !== null) {
			if (names.indexOf(match[1]) === -1) {
				names.push(match[1])
			}
		}
		return names
	}

	/**
	 * The frame document.
	 *
	 * @param {string} html Cleaned HTML.
	 * @param {string} css Cleaned CSS.
	 * @param {string} [fontCss] The house style's @font-face rules.
	 * @param {string} [origin] The page's origin, named in the font policy.
	 * @return {string} The srcdoc.
	 */
	function srcdoc(html, css, fontCss, origin) {
		return (
			'<!doctype html><html><head><meta charset="utf-8">'
			+ '<meta http-equiv="Content-Security-Policy" content="'
			+ policyFor(origin)
			+ '">'
			+ '<style>'
			+ String(fontCss || '')
			+ '\nbody{margin:16px;font-family:var(--nldesign-font-family,sans-serif)}\n'
			+ String(css || '')
			+ '</style></head><body>'
			+ String(html || '')
			+ '</body></html>'
		)
	}

	/**
	 * A sandboxed frame.
	 *
	 * @param {Document} doc The document to create it in.
	 * @param {string} title The frame's accessible name.
	 * @return {HTMLIFrameElement} The frame.
	 */
	function createFrame(doc, title) {
		var frame = doc.createElement('iframe')
		frame.setAttribute('sandbox', SANDBOX)
		frame.setAttribute('title', title)
		frame.className = 'nldesign-own-frame'
		return frame
	}

	/**
	 * Copy each name's value onto the frame's root.
	 *
	 * @param {HTMLIFrameElement} frame The frame.
	 * @param {Array<string>} names The names the code reads.
	 * @param {Function} read name => value, '' when there is none.
	 * @return {number} How many values were set.
	 */
	function applyVars(frame, names, read) {
		var doc = frame.contentDocument
		if (!doc || !doc.documentElement) {
			return 0
		}
		var set = 0
		names.forEach(function (name) {
			var value = String(read(name) || '').trim()
			if (value === '') {
				doc.documentElement.style.removeProperty(name)
				return
			}
			doc.documentElement.style.setProperty(name, value)
			set++
		})
		return set
	}

	return {
		SANDBOX: SANDBOX,
		POLICY: POLICY,
		policyFor: policyFor,
		scanVars: scanVars,
		srcdoc: srcdoc,
		createFrame: createFrame,
		applyVars: applyVars,
	}
})
