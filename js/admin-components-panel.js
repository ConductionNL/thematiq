/**
 * Components and tokens (the TqComponenten board), the panel of the Theming
 * page at #components; #components/own opens it on Your components (the
 * TqEigenComponent board). The instrument itself, the component selector, the
 * preview and the token editor (js/playground.js over admin.js's editor), is
 * lent to the panel while it is open and given back when it closes, so it
 * saves exactly as on the overview. The header carries Download, a More menu
 * and Save, each pressing the editor's own control.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqComponentsPanel() {
	/** The instrument's parts, in the order the panel shows them. */
	var PARTS = ['.nldesign-pg-selector', '#nldesign-preview', '#nldesign-token-editor']
	var lent = []
	var waiting = null

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
	 * The active token set: its id and name.
	 *
	 * @return {{id: string, name: string}} The set.
	 */
	function activeSet() {
		var select = document.getElementById('nldesign-token-set-select')
		if (select === null) {
			return { id: '', name: '' }
		}
		var option = select.options[select.selectedIndex]
		return { id: select.value, name: option !== undefined ? option.textContent.trim() : select.value }
	}

	/**
	 * Press one of the editor's own controls.
	 *
	 * @param {string} id The control's id.
	 * @return {void}
	 */
	function press(id) {
		var control = document.getElementById(id)
		if (control !== null) {
			control.click()
		}
	}

	/**
	 * A header button that presses one of the editor's own controls.
	 *
	 * @param {string} label The text.
	 * @param {string} id The control's id.
	 * @param {boolean} primary Whether it is the main action.
	 * @return {HTMLElement} The button.
	 */
	function headerButton(label, id, primary) {
		var button = document.createElement('button')
		button.type = 'button'
		button.className = 'thematiq-page-header__action' + (primary === true ? ' thematiq-page-header__action--primary' : '')
		button.textContent = label
		button.addEventListener('click', function () {
			press(id)
		})
		return button
	}

	/**
	 * The header's buttons: Download (the overrides file), More and Save.
	 *
	 * @return {Array<HTMLElement>} The buttons.
	 */
	function actions() {
		var reference = document.getElementById('nldesign-token-reference-download')
		var items = [
			{ text: tr('Upload'), hint: tr('Read an overrides file into this theme'), run: function () { press('nldesign-import-btn') } },
			{ text: tr('Export as token set'), hint: tr('All values together as a new, complete set'), run: function () { press('nldesign-pg-export-btn') } },
			{ text: tr('Own component'), hint: tr('Preview HTML and CSS safely'), href: '#components/own' },
			{ text: tr('Motion tokens'), hint: tr('Speed and curve of every transition'), href: '#motion' },
		]
		if (reference !== null && reference.getAttribute('href')) {
			items.push({ text: tr('Download token overview'), hint: tr('All tokens of this set as Markdown'), href: reference.getAttribute('href') })
		}
		return [
			headerButton(tr('Download'), 'nldesign-export-btn', false),
			window.ThematiqPanels.menu(tr('More'), items),
			headerButton(tr('Save'), 'nldesign-save-btn', true),
		]
	}

	/**
	 * The line under the breadcrumb: Active and the number of editable tokens.
	 *
	 * @return {Array<Node>} The line.
	 */
	function introNodes() {
		var nodes = []
		var active = document.createElement('span')
		active.className = 'thematiq-set-panel__status thematiq-set-panel__status--active'
		active.textContent = tr('Active')
		nodes.push(active)
		var count = document.getElementById('nldesign-token-count')
		if (count !== null && count.textContent.trim() !== '') {
			var line = document.createElement('span')
			line.className = 'thematiq-set-panel__line'
			line.textContent = count.textContent.trim()
			nodes.push(line)
		}
		return nodes
	}

	/**
	 * Lend the instrument to the panel, each part leaving a marker where it stood.
	 *
	 * @param {HTMLElement} section The panel.
	 * @return {void}
	 */
	function lend(section) {
		var slot = section.querySelector('.thematiq-components-panel__instrument')
		PARTS.forEach(function (selector) {
			var part = document.querySelector(selector)
			if (part === null || slot.contains(part)) {
				return
			}
			var marker = document.createComment('thematiq: ' + selector)
			part.parentNode.insertBefore(marker, part)
			lent.push({ part: part, marker: marker })
			slot.appendChild(part)
		})
	}

	/**
	 * Give the instrument back to the overview. A selector the playground built inside the
	 * panel meanwhile goes back in front of the preview, where it builds it.
	 *
	 * @param {HTMLElement} section The panel.
	 * @return {void}
	 */
	function giveBack(section) {
		lent.forEach(function (entry) {
			entry.marker.parentNode.insertBefore(entry.part, entry.marker)
			entry.marker.parentNode.removeChild(entry.marker)
		})
		lent = []
		var selector = section.querySelector('.nldesign-pg-selector')
		var preview = document.getElementById('nldesign-preview')
		if (selector !== null && preview !== null) {
			preview.parentNode.insertBefore(selector, preview)
		}
	}

	/**
	 * Open the tab the argument names: `own` opens Your components. The playground builds
	 * its selector once the editor has rendered, which can be after a deep link opened the
	 * panel; the tab is then chosen as soon as it appears.
	 *
	 * @param {HTMLElement} section The panel.
	 * @param {string} argument The panel's argument.
	 * @return {void}
	 */
	function chooseTab(section, argument) {
		if (waiting !== null) {
			waiting.disconnect()
			waiting = null
		}
		if (argument !== 'own') {
			return
		}
		var find = function () {
			return section.querySelector('.nldesign-pg-selector .nldesign-tab-btn[data-tab="own"]')
		}
		if (find() !== null) {
			find().click()
			return
		}
		if (typeof MutationObserver === 'undefined') {
			return
		}
		waiting = new MutationObserver(function () {
			if (find() !== null) {
				waiting.disconnect()
				waiting = null
				find().click()
			}
		})
		waiting.observe(section, { childList: true, subtree: true })
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { PARTS: PARTS }
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('components', {
			title: tr('Components and tokens'),
			crumb: tr('Components'),
			crumbs: function () {
				var set = activeSet()
				return [{ text: tr('Token sets'), href: '#' }, { text: set.id, href: '#set/' + encodeURIComponent(set.id) }]
			},
			intro: introNodes,
			actions: actions,
			onOpen: function (section, argument) {
				lend(section)
				chooseTab(section, argument)
			},
			onClose: function () {
				var section = document.querySelector('section[data-thematiq-panel="components"]')
				if (section !== null) {
					chooseTab(section, '')
					giveBack(section)
				}
			},
		})
	}
})()
