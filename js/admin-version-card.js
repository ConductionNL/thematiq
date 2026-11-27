/**
 * The version card on thematiq's Theming settings page (first-time-setup REQ-TQFS-012, board
 * TqSetupOpnieuw): the installed version, the first-time setup's state and detail, and Set up again,
 * Apps menu walkthrough and Help us. Set up again loads the wizard bundle and opens it with the stored
 * choices filled in; nothing changes until Apply there. Under a configuration source Set up again is
 * disabled with the note who manages the house style.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-version-card-shows-the-setup-and-runs-it-again-req-tqfs-012
 */
;(function thematiqVersionCard(win, doc) {
	var BUNDLE = 'vendor/setupWizard.js'

	/**
	 * A translated string of the app, with `{name}` placeholders filled.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] The placeholders.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text, vars) {
		if (typeof win.t === 'function') {
			return win.t('thematiq', text, vars)
		}
		return text.replace(/{(\w+)}/g, function (match, key) {
			return vars && key in vars ? String(vars[key]) : match
		})
	}

	/**
	 * The address of a route of this app.
	 *
	 * @param {string} path The path under /apps/thematiq.
	 * @return {string} The URL.
	 */
	function url(path) {
		var OC = win.OC || {}
		return typeof OC.generateUrl === 'function'
			? OC.generateUrl('/apps/thematiq' + path)
			: '/index.php/apps/thematiq' + path
	}

	/**
	 * GET a JSON route of this app.
	 *
	 * @param {string} path The path under /apps/thematiq.
	 * @return {Promise<object|null>} The body, or null when it failed.
	 */
	function read(path) {
		return win
			.fetch(url(path), {
				headers: {
					requesttoken: (win.OC || {}).requestToken || '',
					'OCS-APIRequest': 'true',
				},
			})
			.then(function (response) {
				return response.ok ? response.json() : null
			})
			.catch(function () {
				return null
			})
	}

	/**
	 * A stored time as the board writes it: 27-11-2026, 13:52.
	 *
	 * @param {string} at An ISO time.
	 * @return {string} The date and time.
	 */
	function when(at) {
		var date = new Date(at)
		if (isNaN(date.getTime())) {
			return String(at || '')
		}
		var two = function (n) {
			return (n < 10 ? '0' : '') + n
		}
		return (
			two(date.getDate()) + '-' + two(date.getMonth() + 1) + '-' + date.getFullYear()
			+ ', ' + two(date.getHours()) + ':' + two(date.getMinutes())
		)
	}

	/**
	 * The setup's state line and detail from the status document.
	 *
	 * @param {object} status The answer of GET /api/setup/status.
	 * @return {{state: string, detail: string}} The two lines.
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-version-card-shows-the-setup-and-runs-it-again-req-tqfs-012
	 */
	function describe(status) {
		var current = status.current || {}
		if (status.completed) {
			return {
				state: tr('Completed by {user} on {date}', { user: status.completed.by, date: when(status.completed.at) }),
				detail: tr('Token set {set} applied', { set: current.tokenSet || '' })
					+ (current.nextcloudThemeFollows ? ' · ' + tr('Nextcloud theme kept in line') : ''),
			}
		}
		if (status.skipped) {
			return {
				state: tr('Skipped by {user} on {date}', { user: status.skipped.by, date: when(status.skipped.at) }),
				detail: '',
			}
		}
		if (status.configured) {
			return { state: tr('Set up by an earlier version'), detail: tr('Token set {set}', { set: current.tokenSet || '' }) }
		}
		return { state: tr('Not set up yet'), detail: '' }
	}

	/**
	 * Load the wizard bundle once and open it with the stored choices.
	 *
	 * @param {object} status The setup status.
	 * @return {void}
	 * @spec openspec/changes/first-time-setup/specs/first-time-setup/spec.md#requirement-the-version-card-shows-the-setup-and-runs-it-again-req-tqfs-012
	 */
	function setUpAgain(status) {
		if (win.thematiqSetupWizard && typeof win.thematiqSetupWizard.open === 'function') {
			win.thematiqSetupWizard.open(status)
			return
		}
		// The bundle opens itself on load unless told it is opened by hand.
		win.thematiqSetupWizardManual = true
		var script = doc.createElement('script')
		script.src =
			win.OC && typeof win.OC.filePath === 'function'
				? win.OC.filePath('thematiq', 'js', BUNDLE)
				: '/apps/thematiq/js/' + BUNDLE
		script.setAttribute('data-thematiq-setup-wizard-manual', '')
		script.addEventListener('load', function () {
			if (win.thematiqSetupWizard) {
				win.thematiqSetupWizard.open(status)
			}
		})
		doc.head.appendChild(script)
	}

	/**
	 * Fill and wire the card.
	 *
	 * @return {Promise<void>}
	 */
	function init() {
		var card = doc.getElementById('thematiq-version-card')
		if (card === null) {
			return Promise.resolve()
		}
		var setup = card.querySelector('.thematiq-version-card__setup')
		var walkthrough = card.querySelector('.thematiq-version-card__walkthrough')
		var status = null
		setup.disabled = true
		walkthrough.addEventListener('click', function () {
			if (typeof win.thematiqAppMenuReplay === 'function') {
				win.thematiqAppMenuReplay()
			}
		})
		setup.addEventListener('click', function () {
			if (status !== null) {
				setUpAgain(status)
			}
		})
		return Promise.all([read('/api/setup/status'), read('/settings/config-source')]).then(function (answers) {
			status = answers[0]
			var source = answers[1] || {}
			if (status === null) {
				return
			}
			var lines = describe(status)
			card.querySelector('.thematiq-version-card__version').textContent = status.version || '-'
			card.querySelector('.thematiq-version-card__state').textContent = lines.state
			card.querySelector('.thematiq-version-card__detail').textContent = lines.detail
			var managed = source.managed === true
			setup.disabled = managed
			card.querySelector('.thematiq-version-card__locked').hidden = !managed
		})
	}

	win.thematiqVersionCard = { describe: describe, init: init }
	if (doc.readyState === 'loading') {
		doc.addEventListener('DOMContentLoaded', init)
	} else {
		init()
	}
})(window, document)
