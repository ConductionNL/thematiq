/**
 * Header style "workplace": the brand block at the start of the top bar (the
 * DqKop board) for the apps that draw none of their own: Files, Mail,
 * Calendar, Deck and every other app.
 *
 * The block is the one @conduction/nextcloud-vue's CnBrandBar draws for a
 * Conduction app: a 237px link to the app with the set's emblem, the
 * organisation (Nextcloud's theming name) over the app's name, then a thin
 * divider. The emblem comes from the capabilities thematiq publishes
 * (`nldesign.logos.emblem`), the page's name and link from Nextcloud's own
 * navigation entry for the open app, or, on a page without an open app,
 * from the active entry of Nextcloud's settings navigation
 * (`core/settingsNavEntries`, which the account menu reads too), such as
 * "Personal settings", translated as Nextcloud translates it. With neither,
 * the block shows the emblem and the organisation alone, never an empty name.
 *
 * An app that draws its own brand wins: when CnBrandBar's host
 * (`.cn-brand-bar-host`) is in the bar, or the app's navigation carries the
 * brand (`.cn-app-nav__brand`), this block is not drawn, and it is taken out
 * again when either appears later. With nothing to show at all (no emblem,
 * no organisation, no name), nothing is drawn and the bar keeps the fallback
 * css/header-workplace.css gives it.
 *
 * While the block is in the bar, the bar is marked `data-thematiq-brand`.
 * The block is drawn only when css/header-workplace.css styles it, so a
 * browser that cached an older sheet keeps the bar it had.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 * @spec openspec/changes/same-shell-every-page/specs/workplace-header-brand/spec.md#requirement-every-app-gets-the-brand-block-in-the-workplace-bar-req-tqwh-001
 */
;(function thematiqHeaderBrandInit() {
	var HOST_CLASS = 'thematiq-brand-host'
	var BRAND_ATTR = 'data-thematiq-brand'
	var OWN_BRAND = '#header > .cn-brand-bar-host, .cn-app-nav__brand'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqHeaderBrandMain)
	} else {
		thematiqHeaderBrandMain()
	}

	/**
	 * Read one value of Nextcloud's initial state, or the fallback.
	 *
	 * @param {string} app The app the state belongs to.
	 * @param {string} key The key.
	 * @param {*} fallback The value without a state.
	 * @return {*} The state.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function readState(app, key, fallback) {
		try {
			return OCP.InitialState.loadState(app, key, fallback)
		} catch (e) {
			return fallback
		}
	}

	/**
	 * The active entry of one of Nextcloud's navigation states (an object
	 * keyed by id, or a list), or null.
	 *
	 * @param {*} entries The state.
	 * @return {object|null} The active entry with a name.
	 * @spec openspec/changes/same-shell-every-page/specs/workplace-header-brand/spec.md#requirement-every-app-gets-the-brand-block-in-the-workplace-bar-req-tqwh-001
	 */
	function activeEntry(entries) {
		if (entries === null || typeof entries !== 'object') {
			return null
		}
		var found = null
		Object.keys(entries).forEach(function (key) {
			var entry = entries[key]
			if (
				found === null
				&& entry
				&& entry.active === true
				&& typeof entry.name === 'string'
				&& entry.name.trim() !== ''
			) {
				found = entry
			}
		})
		return found
	}

	/**
	 * What the block shows: the emblem, the organisation and the page's name:
	 * the open app's, else the active settings section's, else none.
	 *
	 * @return {{emblem: string, caption: string, name: string, href: string}|null} The brand, or null with nothing to show.
	 * @spec openspec/specs/workplace-layout/spec.md
	 * @spec openspec/changes/same-shell-every-page/specs/workplace-header-brand/spec.md#requirement-every-app-gets-the-brand-block-in-the-workplace-bar-req-tqwh-001
	 */
	function resolveBrand() {
		var open =
			activeEntry(readState('core', 'apps', null))
			|| activeEntry(readState('core', 'settingsNavEntries', null))

		var capabilities = readState('core', 'capabilities', {}) || {}
		var logos = (capabilities.nldesign && capabilities.nldesign.logos) || {}
		var theming = capabilities.theming || {}
		var brand = {
			emblem: typeof logos.emblem === 'string' ? logos.emblem : '',
			caption: typeof theming.name === 'string' ? theming.name.trim() : '',
			name: open !== null ? open.name.trim() : '',
			href: open !== null && typeof open.href === 'string' ? open.href : '',
		}
		if (brand.emblem === '' && brand.caption === '' && brand.name === '') {
			return null
		}
		if (brand.href === '') {
			brand.href = (window.OC && typeof OC.webroot === 'string' ? OC.webroot : '') + '/'
		}
		return brand
	}

	/**
	 * Build the block: the brand link and the divider, as CnBrandBar draws them.
	 *
	 * @param {{emblem: string, caption: string, name: string, href: string}} brand The brand.
	 * @return {HTMLElement} The host element.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function buildBrand(brand) {
		var host = document.createElement('div')
		host.className = HOST_CLASS

		var link = document.createElement('a')
		link.className = 'thematiq-brand'
		link.href = brand.href

		if (brand.emblem !== '') {
			// Decorative beside the name: the name already says whose app it is.
			var emblem = document.createElement('img')
			emblem.className = 'thematiq-brand__emblem'
			emblem.src = brand.emblem
			emblem.alt = ''
			link.appendChild(emblem)
		}

		var text = document.createElement('span')
		text.className = 'thematiq-brand__text'
		if (brand.caption !== '') {
			var caption = document.createElement('span')
			caption.className = 'thematiq-brand__caption'
			caption.textContent = brand.caption
			text.appendChild(caption)
		}
		if (brand.name !== '') {
			var name = document.createElement('strong')
			name.className = 'thematiq-brand__name'
			name.textContent = brand.name
			text.appendChild(name)
		}
		link.appendChild(text)
		host.appendChild(link)

		var divider = document.createElement('span')
		divider.className = 'thematiq-brand__divider'
		divider.setAttribute('aria-hidden', 'true')
		host.appendChild(divider)

		return host
	}

	/**
	 * Whether css/header-workplace.css styles the block: its rule for the
	 * host sets `--thematiq-brand-block: 1`.
	 *
	 * @param {HTMLElement} host The host, in the bar.
	 * @return {boolean} True when the sheet's rules reach it.
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function styled(host) {
		return (
			window
				.getComputedStyle(host)
				.getPropertyValue('--thematiq-brand-block')
				.trim() === '1'
		)
	}

	/**
	 * Draw the block when the open app has no brand of its own, and keep it
	 * first in the bar.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md
	 */
	function thematiqHeaderBrandMain() {
		if (typeof OCP === 'undefined' || !OCP.InitialState) {
			return
		}
		var header = document.getElementById('header')
		if (header === null) {
			return
		}
		var brand = resolveBrand()
		if (brand === null) {
			return
		}

		var host = buildBrand(brand)
		// Whether the sheet styles the block cannot change while the page lives, so it is read once.
		var sheetStylesBlock = null
		var sync = function () {
			if (document.querySelector(OWN_BRAND) !== null) {
				if (host.parentNode !== null) {
					host.parentNode.removeChild(host)
				}
				header.removeAttribute(BRAND_ATTR)
				return
			}
			if (header.firstChild !== host) {
				header.insertBefore(host, header.firstChild)
			}
			// A browser that still holds an older css/header-workplace.css has
			// no rules for the block and would draw the emblem at full size.
			if (sheetStylesBlock === null) {
				sheetStylesBlock = styled(host)
			}
			if (!sheetStylesBlock) {
				header.removeChild(host)
				header.removeAttribute(BRAND_ATTR)
				return
			}
			if (!header.hasAttribute(BRAND_ATTR)) {
				header.setAttribute(BRAND_ATTR, '')
			}
		}

		// Only a change to the bar's own children, or an app's brand coming or
		// going, can change the block. A clock or a counter elsewhere on the
		// page changes the body every second and must not re-run the sync.
		var concernsBrand = function (node) {
			return (
				node.nodeType === 1
				&& (node === host
					|| node.matches(OWN_BRAND)
					|| node.querySelector(OWN_BRAND) !== null)
			)
		}
		var relevant = function (records) {
			for (var i = 0; i < records.length; i++) {
				var record = records[i]
				if (record.target === header) {
					return true
				}
				var nodes = Array.prototype.slice
					.call(record.addedNodes)
					.concat(Array.prototype.slice.call(record.removedNodes))
				for (var j = 0; j < nodes.length; j++) {
					if (concernsBrand(nodes[j])) {
						return true
					}
				}
			}
			return false
		}

		sync()
		// A Conduction app mounts its own brand after this script, and a
		// re-render of the bar can drop a node it did not create.
		if (typeof MutationObserver === 'function') {
			var queued = false
			new MutationObserver(function (records) {
				if (queued || !relevant(records)) {
					return
				}
				queued = true
				window.requestAnimationFrame(function () {
					queued = false
					sync()
				})
			}).observe(document.body, { childList: true, subtree: true })
		}
	}
})()
