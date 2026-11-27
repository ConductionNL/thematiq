/**
 * The first-time setup's loader: on an administrator's page while setup is due (the server only adds
 * this script then), add the wizard bundle once the page is idle. While Nextcloud's own first-run
 * wizard is open it waits, so two modals never stack.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-wizard-opens-for-an-administrator-on-the-first-nextcloud-page-while-setup-is-due-req-tqfs-001
 */
;(function thematiqSetupWizardLoader(win, doc) {
	var BUNDLE = 'vendor/setupWizard.js'
	var WAIT_MS = 1000

	/**
	 * Whether Nextcloud's first-run wizard is on screen.
	 *
	 * @return {boolean} True while it is open.
	 */
	function firstRunOpen() {
		return (
			doc.querySelector(
				'#firstrunwizard, .first-run-wizard, [data-firstrunwizard]',
			) !== null
		)
	}

	/**
	 * The URL of a script of this app.
	 *
	 * @param {string} file The file under js/.
	 * @return {string} The URL.
	 */
	function scriptUrl(file) {
		if (win.OC && typeof win.OC.filePath === 'function') {
			return win.OC.filePath('thematiq', 'js', file)
		}
		return (
			(win.OC && win.OC.webroot ? win.OC.webroot : '')
			+ '/apps/thematiq/js/'
			+ file
		)
	}

	/**
	 * Add the bundle once, unless the first-run wizard is open; then try again shortly.
	 *
	 * @return {void}
	 */
	function load() {
		if (doc.querySelector('script[data-thematiq-setup-wizard]') !== null) {
			return
		}
		if (firstRunOpen() === true) {
			win.setTimeout(load, WAIT_MS)
			return
		}
		var script = doc.createElement('script')
		script.src = scriptUrl(BUNDLE)
		script.defer = true
		script.setAttribute('data-thematiq-setup-wizard', '')
		var nonce = doc.querySelector('script[nonce]')
		if (nonce !== null) {
			script.nonce = nonce.nonce || nonce.getAttribute('nonce')
		}
		doc.head.appendChild(script)
	}

	/**
	 * Wait for an idle moment after the page loaded.
	 *
	 * @return {void}
	 */
	function whenIdle() {
		if (typeof win.requestIdleCallback === 'function') {
			win.requestIdleCallback(load, { timeout: 3000 })
		} else {
			win.setTimeout(load, 200)
		}
	}

	if (doc.readyState === 'complete') {
		whenIdle()
	} else {
		win.addEventListener('load', whenIdle)
	}
})(window, document)
