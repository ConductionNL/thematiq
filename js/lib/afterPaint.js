/**
 * Run work that reads computed style once the page has painted.
 *
 * Reading `getComputedStyle(...).getPropertyValue` while the Theming admin page
 * is still booting forces the browser to resolve the style of the whole
 * document before its first paint. The previews and colour pickers that read
 * the live colours only need them a moment later, so they wait for the
 * browser's first idle moment (at most one second) instead. Measured on the
 * sandbox reference stack: the page is usable ~100-180 ms sooner.
 *
 * `window.ThematiqAfterPaint(run)` calls `run()` exactly once: in an idle
 * callback where the browser has `requestIdleCallback`, else after a timeout.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
;(function thematiqAfterPaintInit() {
	/** The longest a deferred read waits, in milliseconds. */
	var LATEST = 1000

	/**
	 * Call `run` in the browser's first idle moment after now.
	 *
	 * @param {Function} run The work that reads computed style.
	 * @return {void}
	 */
	function afterPaint(run) {
		if (typeof window.requestIdleCallback === 'function') {
			window.requestIdleCallback(
				function () {
					run()
				},
				{ timeout: LATEST },
			)
			return
		}
		window.setTimeout(run, 1)
	}

	window.ThematiqAfterPaint = afterPaint
})()
