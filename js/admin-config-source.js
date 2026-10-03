/**
 * Theme as code: the configuration bundle block says when the house style is
 * managed from deployment configuration (thematiq.config_source in config.php).
 *
 * It shows the package path, the last applied revision and time, the last
 * error and drift. While thematiq.config_source_lock is on it also disables
 * every control on the page; the server answers 423 to a change either way.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/theme-as-code/spec.md
 */
;(function thematiqConfigSourceInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqConfigSourceMain)
	} else {
		thematiqConfigSourceMain()
	}

	/**
	 * Add one paragraph to the block.
	 *
	 * @param {HTMLElement} block The block.
	 * @param {string} text The text.
	 * @param {string} className An extra class.
	 * @return {HTMLElement} The paragraph.
	 */
	function line(block, text, className) {
		var p = document.createElement('p')
		p.className = 'settings-hint ' + (className || '')
		p.textContent = text
		block.appendChild(p)
		return p
	}

	/**
	 * Disable every control on the settings page, now and as admin.js renders more.
	 *
	 * @param {HTMLElement} root The settings root.
	 */
	function lockControls(root) {
		var selector = 'input, select, textarea, button'
		function disableAll(scope) {
			scope.querySelectorAll(selector).forEach(function (control) {
				control.disabled = true
				control.setAttribute('aria-disabled', 'true')
			})
		}
		disableAll(root)
		if (typeof MutationObserver === 'function') {
			new MutationObserver(function () {
				disableAll(root)
			}).observe(root, { childList: true, subtree: true })
		}
	}

	/**
	 * Render the status into the block.
	 *
	 * @param {HTMLElement} block The block.
	 * @param {object} status The status from GET /settings/config-source.
	 */
	function render(block, status) {
		block.textContent = ''
		if (!status || status.managed !== true) {
			block.hidden = true
			return
		}
		block.hidden = false
		line(
			block,
			t(
				'thematiq',
				'The house style is managed from deployment configuration: {path}.',
				{ path: status.path },
				undefined,
				{ escape: false },
			),
			'nldesign-config-source-managed',
		)
		if (status.revision || status.appliedAt) {
			line(
				block,
				t(
					'thematiq',
					'Last applied: revision {revision}, at {time}.',
					{
						revision: status.revision || t('thematiq', 'unknown'),
						time: status.appliedAt || t('thematiq', 'unknown'),
					},
					undefined,
					{ escape: false },
				),
			)
		} else {
			line(block, t('thematiq', 'The package has not been applied yet.'))
		}
		if (status.lastError && Array.isArray(status.lastError.errors)) {
			line(
				block,
				t(
					'thematiq',
					'The last package was not applied. Nothing changed. Fix these errors in the package:',
				),
				'nldesign-config-source-error',
			)
			var list = document.createElement('ul')
			list.className = 'nldesign-config-source-errors'
			status.lastError.errors.forEach(function (error) {
				var item = document.createElement('li')
				item.textContent =
					(error.section ? '[' + error.section + '] ' : '')
					+ (error.message || '')
				list.appendChild(item)
			})
			block.appendChild(list)
		}
		if (status.drift === true) {
			line(
				block,
				t(
					'thematiq',
					'The running configuration differs from the package. The changes made here stay until the package changes or an operator runs occ thematiq:config:apply --force.',
				),
				'nldesign-config-source-drift',
			)
		}
		if (status.locked === true) {
			line(
				block,
				t(
					'thematiq',
					'The settings on this page are locked. Change the house style in the package.',
				),
				'nldesign-config-source-locked',
			)
			var root = document.getElementById('nldesign-settings')
			if (root) {
				lockControls(root)
			}
		}
	}

	/**
	 * Load the status and render it.
	 *
	 * @return {Promise|undefined} The fetch.
	 */
	function thematiqConfigSourceMain() {
		var block = document.getElementById('nldesign-config-source')
		if (!block || typeof OC === 'undefined') {
			return undefined
		}
		return fetch(OC.generateUrl('/apps/thematiq/settings/config-source'), {
			headers: { requesttoken: OC.requestToken },
		})
			.then(function (res) {
				return res.ok ? res.json() : null
			})
			.then(function (status) {
				render(block, status)
			})
			.catch(function (err) {
				console.error('Error loading the configuration source status:', err)
			})
	}

	window.ThematiqConfigSource = { render: render, init: thematiqConfigSourceMain }
})()
