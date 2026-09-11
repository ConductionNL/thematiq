/**
 * Stylesheet layer swap — apply a token set to the page you are on.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * A token set reaches a page as a run of `<link rel="stylesheet">` elements
 * (the design-system files, the set's token file, its element overrides, its
 * dark variant, the contrast fixes) plus one inline `<style>` carrying the
 * logo URL. `CssInjectionService` emits that run on the server; applying a set
 * without a reload means replacing that run with the one the server would
 * emit for the new set. This module does the replacing. It never decides WHAT
 * the run is — it asks the server (`GET /settings/tokenset-stylesheets/{id}`)
 * for both the current set's manifest and the new one's, so there is exactly
 * one owner of the cascade and no copy of it in JavaScript.
 *
 * Why swap elements instead of writing the resolved `--color-*` variables
 * inline on `<html>`: inline variables cannot reproduce the set's
 * `token-overrides/*.css` rules, its logo `background-image`, or the dark
 * variant's media-query-scoped rules. The page would look almost right, which
 * is worse than a reload. Swapping the real stylesheets gives the real
 * cascade, including `none` ↔ design-system in both directions.
 *
 * Strategy: insert every element of the new manifest, in order, at the place
 * the old run occupies; wait for each `<link>` to load; then remove the old
 * run. While both are present the later one wins the cascade, so there is no
 * flash of unstyled chrome and no moment where the page has neither set.
 *
 * Dual-mode like `tokenTransforms.js`: `module.exports` under Node (so the
 * pure parts are unit-testable without a DOM) and `window.NldesignLayerSwap`
 * in the browser. No `import`/`export`, because `js/admin.js` is not built.
 *
 * @spec openspec/changes/apply-without-reload/specs/css-architecture/spec.md
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
	} else {
		root.NldesignLayerSwap = api
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	/**
	 * How long to wait for one stylesheet before giving up on its `load`
	 * event. A slow sheet still ends up applied; the wait only decides when
	 * the OLD run is removed.
	 *
	 * @type {number}
	 */
	var LOAD_TIMEOUT_MS = 8000

	/**
	 * The pathname of an href, without origin, query or hash.
	 *
	 * The page's own `<link>`s carry a cache-busting `?v=<hash>` the server
	 * template computed; the manifest carries a different `?v=`. Matching on
	 * the pathname is what makes the two refer to the same file.
	 *
	 * @param {string} href An absolute or root-relative URL.
	 * @return {string} The pathname.
	 */
	function pathnameOf(href) {
		var value = String(href || '')
		var withoutOrigin = value.replace(/^[a-z][a-z0-9+.-]*:\/\/[^/]*/i, '')
		return withoutOrigin.split('?')[0].split('#')[0]
	}

	/**
	 * The identity of a manifest layer: a file by its pathname, an inline
	 * style by its element id.
	 *
	 * @param {{kind: string, href?: string, id?: string}} layer A manifest layer.
	 * @return {string} A key stable across cache-busting query strings.
	 */
	function layerKey(layer) {
		if (layer.kind === 'inline') {
			return 'inline:' + String(layer.id || '')
		}
		return 'file:' + pathnameOf(layer.href)
	}

	/**
	 * Which layers differ between two manifests.
	 *
	 * Pure. The swap itself always replaces the whole run (see the file
	 * header), so this exists for the caller to know whether anything would
	 * change at all, and for tests.
	 *
	 * @param {Array<Object>} current The current manifest's `layers`.
	 * @param {Array<Object>} next The new manifest's `layers`.
	 * @return {{removed: Array<Object>, added: Array<Object>, unchanged: Array<Object>}} The diff, in manifest order.
	 */
	function diffLayers(current, next) {
		var currentKeys = {}
		var nextKeys = {}
		;(current || []).forEach(function (layer) {
			currentKeys[layerKey(layer)] = true
		})
		;(next || []).forEach(function (layer) {
			nextKeys[layerKey(layer)] = true
		})

		return {
			removed: (current || []).filter(function (layer) {
				return nextKeys[layerKey(layer)] !== true
			}),
			added: (next || []).filter(function (layer) {
				return currentKeys[layerKey(layer)] !== true
			}),
			unchanged: (next || []).filter(function (layer) {
				return currentKeys[layerKey(layer)] === true
			}),
		}
	}

	/**
	 * The elements on the page that a manifest describes.
	 *
	 * A `<link>` is matched by pathname; an inline `<style>` by id. Elements
	 * the manifest names but the page does not have are simply absent from the
	 * result — a set that was already partially swapped, or a page rendered
	 * before the logo `<style>` carried an id.
	 *
	 * @param {Document} doc The document.
	 * @param {{layers: Array<Object>}} manifest A stylesheet manifest.
	 * @return {Array<Element>} The matching elements, in document order.
	 */
	function findLayerElements(doc, manifest) {
		var wanted = {}
		;((manifest && manifest.layers) || []).forEach(function (layer) {
			wanted[layerKey(layer)] = true
		})

		var found = []
		var links = doc.querySelectorAll('link[rel="stylesheet"][href]')
		for (var index = 0; index < links.length; index++) {
			if (
				wanted['file:' + pathnameOf(links[index].getAttribute('href'))]
				=== true
			) {
				found.push(links[index])
			}
		}

		var styles = doc.querySelectorAll('style[id]')
		for (var styleIndex = 0; styleIndex < styles.length; styleIndex++) {
			if (wanted['inline:' + styles[styleIndex].id] === true) {
				found.push(styles[styleIndex])
			}
		}

		return found.sort(function (left, right) {
			// eslint-disable-next-line no-bitwise
			return (left.compareDocumentPosition(right) & 4) === 4 ? -1 : 1
		})
	}

	/**
	 * Where a new run goes when the page has no old run to take the place of.
	 *
	 * The set layers must sit BEFORE the admin's own layers (custom-overrides,
	 * freeform CSS) so those keep winning, and before core theming's `.theme`
	 * links, which the server prints after every app stylesheet. A page with
	 * neither gets the run appended to `<head>`.
	 *
	 * @param {Document} doc The document.
	 * @return {Element|null} The element to insert before, or null to append.
	 */
	function insertBeforeAnchor(doc) {
		return (
			doc.querySelector(
				'link[rel="stylesheet"][href*="/thematiq/css/custom-overrides.css"]',
			)
			|| doc.querySelector('link[rel="stylesheet"].theme')
			|| null
		)
	}

	/**
	 * Build the element for one manifest layer.
	 *
	 * @param {Document} doc The document.
	 * @param {Object} layer A manifest layer.
	 * @return {Element} A `<link>` or `<style>`, not yet in the document.
	 */
	function createLayerElement(doc, layer) {
		if (layer.kind === 'inline') {
			var style = doc.createElement('style')
			if (layer.id) {
				style.id = layer.id
			}
			style.setAttribute('data-nldesign-layer', layer.layer || 'inline')
			style.textContent = String(layer.css || '')
			return style
		}

		var link = doc.createElement('link')
		link.rel = 'stylesheet'
		link.href = layer.href
		link.setAttribute('data-nldesign-layer', layer.layer || 'file')
		return link
	}

	/**
	 * Resolve once a `<link>` has loaded, failed, or taken too long, SAYING
	 * WHICH.
	 *
	 * Failure never rejects — one 404 must not abandon the rest of the run
	 * mid-flight — but it must be distinguishable, because the caller is about
	 * to delete a stylesheet run that currently works. A timeout counts as a
	 * failure for the same reason: after eight seconds we do not know that the
	 * new sheet is in effect, and removing the old one on a guess is what turns
	 * a slow network into an unstyled page.
	 *
	 * @param {Element} element The element just inserted.
	 * @return {Promise<boolean>} True when the sheet loaded; false on error or timeout.
	 */
	function whenLoaded(element) {
		if (element.tagName !== 'LINK') {
			return Promise.resolve(true)
		}

		return new Promise(function (resolve) {
			var done = false
			var finish = function (ok) {
				if (done === false) {
					done = true
					resolve(ok)
				}
			}
			element.addEventListener('load', function () {
				finish(true)
			})
			element.addEventListener('error', function () {
				finish(false)
			})
			setTimeout(function () {
				finish(false)
			}, LOAD_TIMEOUT_MS)
		})
	}

	/**
	 * Replace the current set's run of elements with the new set's.
	 *
	 * @param {Document} doc The document.
	 * @param {{layers: Array<Object>}|null} currentManifest The manifest of the set now on the page, or null when unknown.
	 * The old run is removed ONLY once every new sheet has actually loaded. If
	 * any of them 404s, errors or times out, the new run is taken back out and
	 * the old one is left exactly as it was, because a page that still carries
	 * the previous theme is recoverable and a half-styled one is not. The caller
	 * sees `ok: false` and can fall back to a reload.
	 *
	 * @param {{layers: Array<Object>}} nextManifest The manifest of the set to apply.
	 * @return {Promise<{ok: boolean, added: Array<Element>, removed: Array<Element>}>} What changed in the document.
	 */
	function swap(doc, currentManifest, nextManifest) {
		var old = currentManifest ? findLayerElements(doc, currentManifest) : []
		var head = doc.head || doc.getElementsByTagName('head')[0]

		// Insert after the last element of the old run; with no old run, before
		// the admin layers so the set still sits under them.
		var after = old.length > 0 ? old[old.length - 1] : null
		var before = after === null ? insertBeforeAnchor(doc) : null

		var added = []
		;((nextManifest && nextManifest.layers) || []).forEach(function (layer) {
			var element = createLayerElement(doc, layer)
			if (after !== null) {
				after.parentNode.insertBefore(element, after.nextSibling)
				after = element
			} else if (before !== null) {
				before.parentNode.insertBefore(element, before)
			} else {
				head.appendChild(element)
			}
			added.push(element)
		})

		return Promise.all(added.map(whenLoaded)).then(function (results) {
			var ok = results.every(function (loaded) {
				return loaded === true
			})

			if (ok === false) {
				// Roll back: take the half-arrived run out again and leave the
				// page on the theme it already had.
				added.forEach(function (element) {
					if (element.parentNode) {
						element.parentNode.removeChild(element)
					}
				})

				return { ok: false, added: [], removed: [] }
			}

			old.forEach(function (element) {
				// An inline style the new run re-declares with the same id would
				// otherwise leave two elements with one id; the old one goes.
				if (element.parentNode) {
					element.parentNode.removeChild(element)
				}
			})

			return { ok: true, added: added, removed: old }
		})
	}

	/**
	 * Re-request core theming's own stylesheets so a just-synced primary
	 * colour, background and logo reach the page.
	 *
	 * `/apps/theming/theme/*.css` is generated from the theming app values;
	 * after `POST /settings/theming` those values changed but the page still
	 * holds the old sheets. Core's admin panel does exactly this after a save
	 * (its `refreshStyles`): bump the `v=` on every `.theme` link so the
	 * browser fetches them again.
	 *
	 * @param {Document} doc The document.
	 * @param {string} [version] The cache-buster to use; defaults to the time.
	 * @return {Array<Element>} The links that were refreshed.
	 */
	function refreshThemeStylesheets(doc, version) {
		return refreshStylesheets(
			doc,
			'link[rel="stylesheet"][href*="/apps/theming/"]',
			version,
		)
	}

	/**
	 * Re-request the stylesheets a selector matches by bumping their `v=`.
	 *
	 * For a file whose CONTENT changed under the same URL — core theming's
	 * generated sheets after a sync, `custom-overrides.css` after the apply
	 * dialog wrote to it — replacing the element is unnecessary; the browser
	 * just needs a reason to fetch it again.
	 *
	 * @param {Document} doc The document.
	 * @param {string} selector A CSS selector for `<link>` elements.
	 * @param {string} [version] The cache-buster to use; defaults to the time.
	 * @return {Array<Element>} The links that were refreshed.
	 */
	function refreshStylesheets(doc, selector, version) {
		var stamp = version || String(Date.now())
		var links = doc.querySelectorAll(selector)
		var refreshed = []
		for (var index = 0; index < links.length; index++) {
			var link = links[index]
			var href = link.getAttribute('href')
			link.setAttribute('href', bumpVersion(href, stamp))
			refreshed.push(link)
		}
		return refreshed
	}

	/**
	 * Replace (or add) the `v=` query parameter of a URL.
	 *
	 * Pure, so the rewrite is testable without a document.
	 *
	 * @param {string} href The URL.
	 * @param {string} stamp The new value.
	 * @return {string} The URL with `v=<stamp>`.
	 */
	function bumpVersion(href, stamp) {
		var value = String(href || '')
		if (/[?&]v=/.test(value)) {
			return value.replace(/([?&]v=)[^&#]*/, '$1' + stamp)
		}
		return value + (value.indexOf('?') === -1 ? '?' : '&') + 'v=' + stamp
	}

	return {
		pathnameOf: pathnameOf,
		layerKey: layerKey,
		diffLayers: diffLayers,
		bumpVersion: bumpVersion,
		findLayerElements: findLayerElements,
		insertBeforeAnchor: insertBeforeAnchor,
		createLayerElement: createLayerElement,
		swap: swap,
		refreshStylesheets: refreshStylesheets,
		refreshThemeStylesheets: refreshThemeStylesheets,
	}
})
