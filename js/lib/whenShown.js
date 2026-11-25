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
})()
