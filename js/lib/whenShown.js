/**
 * Run a block's loader only once the block can be seen.
 *
 * A block inside a closed panel (`<section data-thematiq-panel hidden>` of the
 * Theming admin page) asks the server for nothing until its panel opens; a
 * block on the overview, or outside any panel, loads at once. Opening the
 * panel unhides its section, which is what this watches, so the order the
 * page's scripts load in does not matter.
 *
 * `window.ThematiqWhenShown(element, run)` calls `run()` exactly once and
 * returns what it returned when it ran at once, or undefined when it waits.
 *
 * `window.ThematiqWhenNear(elements, run, options)` calls `run()` exactly once,
 * when a block far below the fold nears the viewport or once the page has
 * settled after its load event, so building it does not hold up the first paint.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
;(function thematiqWhenShownInit() {
	/**
	 * Call `run` now when `element` is visible, else when its panel opens.
	 *
	 * @param {HTMLElement|null} element The block the loader fills.
	 * @param {Function} run The loader.
	 * @return {*} What `run` returned when it ran at once, else undefined.
	 */
	function whenShown(element, run) {
		var section =
			element && typeof element.closest === 'function'
				? element.closest('section[data-thematiq-panel]')
				: null
		if (
			section === null
			|| section.hidden !== true
			|| typeof MutationObserver === 'undefined'
		) {
			return run()
		}
		var observer = new MutationObserver(function () {
			if (section.hidden === true) {
				return
			}
			observer.disconnect()
			run()
		})
		observer.observe(section, { attributes: true, attributeFilter: ['hidden'] })
		return undefined
	}

	window.ThematiqWhenShown = whenShown

	/**
	 * Call `run` once: when one of `elements` comes within `margin` px of the
	 * viewport, or `settle` ms after the page's load event, whichever is first.
	 * For a block far below the fold whose build would hold up the first paint:
	 * the page paints without it and it is there before it is scrolled to.
	 * Without IntersectionObserver, or without an element, it runs at once.
	 *
	 * @param {Array<HTMLElement|null>} elements The blocks to watch.
	 * @param {Function} run The builder.
	 * @param {{margin?: number, settle?: number}} [options] Distance in px (1000) and delay after load in ms (1500).
	 * @return {*} What `run` returned when it ran at once, else undefined.
	 */
	function whenNear(elements, run, options) {
		var opts = options || {}
		var margin = opts.margin === undefined ? 1000 : opts.margin
		var settle = opts.settle === undefined ? 1500 : opts.settle
		var targets = (elements || []).filter(Boolean)
		if (targets.length === 0 || typeof IntersectionObserver === 'undefined') {
			return run()
		}
		var done = false
		var timer = null
		var observer = null
		var onLoad = null
		var start = function () {
			if (done === true) {
				return
			}
			done = true
			observer.disconnect()
			if (timer !== null) {
				clearTimeout(timer)
			}
			window.removeEventListener('load', onLoad)
			run()
		}
		onLoad = function () {
			timer = setTimeout(start, settle)
		}
		observer = new IntersectionObserver(
			function (entries) {
				var near = entries.some(function (entry) {
					return entry.isIntersecting
				})
				if (near === true) {
					start()
				}
			},
			{ rootMargin: margin + 'px 0px' },
		)
		targets.forEach(function (target) {
			observer.observe(target)
		})
		if (document.readyState === 'complete') {
			onLoad()
		} else {
			window.addEventListener('load', onLoad)
		}
		return undefined
	}

	window.ThematiqWhenNear = whenNear
})()
