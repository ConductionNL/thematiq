/**
 * Answer the Theming admin page's first reads from its own render.
 *
 * The page's blocks each GET their first state as the page opens. In the
 * browser those requests queue behind one another, so the page waited
 * 0.8-1.8 s for answers the server gives in 40-70 ms each. The render now
 * carries those answers as the initial state `bootReads` (path => body, see
 * lib/Settings/AdminBootReads.php), and this script answers a GET for one of
 * those paths from it, once per path, so no block needs to change how it
 * loads; a second read of the same path goes to the server.
 *
 * It answers only while nothing on the page can have changed: until the
 * admin first presses a key or a pointer, or anything sends a request other
 * than a GET. From then on every read goes to the server as before, and
 * `window.fetch` is the browser's own again. A path the render did not
 * answer, another app's URL, or a request object always goes to the server.
 *
 * Load it before the scripts whose reads it answers. A page that sets
 * `window.ThematiqBootReadsOff = true` before it loads (an e2e test that
 * stubs one of these reads with page.route) gets no answers from the render.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/admin-settings/spec.md
 */
;(function thematiqBootReadsInit() {
	var APP_PATH = '/apps/thematiq'

	/** How long after the page opens a read the render answered is answered again, in milliseconds. */
	var REPEAT_WINDOW_MS = 10000

	/**
	 * The boot-read key of a URL: its path after /apps/thematiq, with its query.
	 *
	 * @param {*} input The fetch input.
	 * @param {string} base The page URL, to resolve a relative input against.
	 * @return {string|null} The key, or null for another origin, another app or a non-string input.
	 */
	function keyOf(input, base) {
		if (typeof input !== 'string') {
			return null
		}
		var url
		try {
			url = new URL(input, base)
		} catch (e) {
			return null
		}
		if (url.origin !== new URL(base).origin) {
			return null
		}
		var prefix = url.pathname.indexOf(APP_PATH + '/')
		if (prefix === -1 || (prefix !== 0 && url.pathname.slice(0, prefix) !== '/index.php')) {
			return null
		}

		return url.pathname.slice(prefix + APP_PATH.length) + url.search
	}

	/**
	 * Whether a fetch is a plain read.
	 *
	 * @param {object|undefined} options The fetch options.
	 * @return {boolean} True for a GET without a body.
	 */
	function isRead(options) {
		var method = options && typeof options.method === 'string' ? options.method.toUpperCase() : 'GET'

		return method === 'GET' && !(options && options.body !== undefined && options.body !== null)
	}

	/**
	 * Build the answering fetch.
	 *
	 * @param {Function} realFetch The browser's fetch.
	 * @param {object} reads Path => JSON body, from the render.
	 * @param {string} base The page URL.
	 * @param {Function} [clock] Milliseconds now; Date.now unless a test passes its own.
	 * @return {{fetch: Function, stop: Function, active: Function}} The fetch, a way to stop answering, and whether it still answers.
	 */
	function create(realFetch, reads, base, clock) {
		var answering = reads !== null && typeof reads === 'object' && Object.keys(reads).length > 0
		reads = answering === true ? Object.assign({}, reads) : {}
		var now = typeof clock === 'function' ? clock : Date.now
		var started = now()
		var answered = {}

		function stop() {
			answering = false
		}

		function bootFetch(input, options) {
			if (answering === true) {
				if (isRead(options) === false) {
					stop()
				} else {
					var key = keyOf(input, base)
					// Two blocks that open with the same read (the version card and the config
					// source line both read /settings/config-source) are both answered while the
					// page boots; after REPEAT_WINDOW_MS a repeated read, such as a poll, goes to
					// the server so it never keeps a stale answer.
					if (key !== null && Object.prototype.hasOwnProperty.call(reads, key) === true
						&& (answered[key] !== true || now() - started <= REPEAT_WINDOW_MS)) {
						var body = reads[key]
						answered[key] = true
						return Promise.resolve(
							new Response(JSON.stringify(body), {
								status: 200,
								headers: { 'Content-Type': 'application/json' },
							}),
						)
					}
				}
			}

			return realFetch(input, options)
		}

		return {
			fetch: bootFetch,
			stop: stop,
			active: function () {
				return answering
			},
		}
	}

	window.ThematiqBootReads = { create: create, keyOf: keyOf }

	if (
		window.ThematiqBootReadsOff === true
		|| typeof window.fetch !== 'function'
		|| typeof Response === 'undefined'
		|| typeof OCP === 'undefined'
		|| !OCP.InitialState
		|| typeof OCP.InitialState.loadState !== 'function'
	) {
		return
	}

	var reads = null
	try {
		reads = OCP.InitialState.loadState('thematiq', 'bootReads', null)
	} catch (e) {
		reads = null
	}

	var realFetch = window.fetch
	var boot = create(realFetch.bind(window), reads, window.location.href)
	if (boot.active() === false) {
		return
	}

	/** The admin acts on the page: answer nothing more from the render. */
	function release() {
		boot.stop()
		if (window.fetch === shim) {
			window.fetch = realFetch
		}
		document.removeEventListener('pointerdown', release, true)
		document.removeEventListener('keydown', release, true)
	}

	function shim(input, options) {
		var answer = boot.fetch(input, options)
		if (boot.active() === false) {
			release()
		}

		return answer
	}

	window.fetch = shim
	document.addEventListener('pointerdown', release, true)
	document.addEventListener('keydown', release, true)
})()
