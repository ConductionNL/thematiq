/**
 * Configuration bundle (the TqBundel board), the panel of the Theming page at
 * #bundle. It reads the bundle the export endpoint would download and lists
 * what is in it; the header's Download configuration and Upload configuration
 * press the buttons js/admin.js wires.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqBundlePanel() {
	/**
	 * A translated string of the app, with `{name}` placeholders filled.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] The placeholders.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text, vars) {
		if (typeof window.t === 'function') {
			return window.t('thematiq', text, vars)
		}
		return text.replace(/{(\w+)}/g, function (match, key) {
			return vars && key in vars ? String(vars[key]) : match
		})
	}

	/**
	 * A count in words: one, or many.
	 *
	 * @param {number} count The count.
	 * @param {string} one The text for one.
	 * @param {string} many The text for any other count, with {count}.
	 * @return {string} The words.
	 */
	function count(count, one, many) {
		return count === 1 ? tr(one) : tr(many, { count: count })
	}

	/**
	 * What the bundle holds, one row per part.
	 *
	 * @param {object} bundle The bundle.
	 * @return {Array<Array<string>>} Label and value per row.
	 */
	function contents(bundle) {
		var config = bundle.config || {}
		var options = Object.keys(config.layoutOptions || {}).length
			+ ['hideSlogan', 'showMenuLabels', 'primaryDrivesComponents'].filter(function (key) {
				return key in config
			}).length
		var select = document.getElementById('nldesign-token-set-select')
		var option = select !== null ? select.querySelector('option[value="' + config.tokenSet + '"]') : null
		var overrides = (String(bundle.customOverridesCss || '').match(/--[a-z0-9-]+\s*:/gi) || []).length
		var footer = bundle.emailFooter || {}
		var footerSet = Object.keys(footer).some(function (key) {
			return typeof footer[key] === 'string' && footer[key] !== ''
		})
		return [
			[tr('Active token set'), option !== null ? option.textContent.trim() : String(config.tokenSet || '')],
			[tr('Switches and layout'), count(options, '1 option', '{count} options')],
			[tr('Theme per app'), count((config.disabledApps || []).length, '1 app off', '{count} apps off')],
			[tr('Token overrides'), count(overrides, '1 token', '{count} tokens')],
			[tr('Own token sets'), count((bundle.customTokenSets || []).length, '1 set', '{count} sets')],
			[tr('Email footer'), footerSet === true ? tr('set') : tr('not set')],
			[tr('Updates from the source'), config.upstreamFreshnessEnabled === true ? tr('on') : tr('off')],
		]
	}

	/**
	 * Read the bundle and list what is in it.
	 *
	 * @return {Promise<void>} Done.
	 */
	function load() {
		var rows = document.querySelector('.thematiq-bundle-panel__rows')
		if (rows === null) {
			return Promise.resolve()
		}
		return fetch(window.OC.generateUrl('/apps/thematiq/settings/config/export'), {
			headers: { requesttoken: window.OC.requestToken },
		})
			.then(function (r) {
				return r.json()
			})
			.then(function (bundle) {
				rows.replaceChildren.apply(rows, contents(bundle || {}).map(function (row) {
					var line = document.createElement('div')
					var label = document.createElement('span')
					label.textContent = row[0]
					var value = document.createElement('strong')
					value.textContent = row[1]
					line.appendChild(label)
					line.appendChild(value)
					return line
				}))
			})
			.catch(function () {
				var line = document.createElement('p')
				line.className = 'settings-hint'
				line.textContent = tr('What is in the bundle could not be read.')
				rows.replaceChildren(line)
			})
	}

	/**
	 * The header's download and upload buttons.
	 *
	 * @return {Array<HTMLElement>} The buttons.
	 */
	function actions() {
		return [
			['nldesign-config-bundle-download-btn', tr('Download configuration'), false],
			['nldesign-config-bundle-upload-btn', tr('Upload configuration'), true],
		].map(function (entry) {
			var button = document.createElement('button')
			button.type = 'button'
			button.className = 'thematiq-page-header__action' + (entry[2] === true ? ' thematiq-page-header__action--primary' : '')
			button.textContent = entry[1]
			button.addEventListener('click', function () {
				var target = document.getElementById(entry[0])
				if (target !== null) {
					target.click()
				}
			})
			return button
		})
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('bundle', {
			title: tr('Configuration bundle'),
			intro: function () {
				return tr('The complete configuration as one file, for every environment.')
			},
			actions: actions,
			onOpen: load,
		})
	}
})()
