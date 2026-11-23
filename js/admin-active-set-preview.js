/**
 * The preview in the Active token set card, as TqTheming draws it: two tabs,
 * Workplace and Login page. Workplace shows the set's colour swatches and a
 * sample of the workplace in them; Login page shows the login card. Both read
 * the colours the page is wearing, so they follow the set the page shows and
 * redraw when another set is applied.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqActiveSetPreviewInit() {
	/**
	 * The swatches: a label and the custom properties to read, first one set wins.
	 *
	 * @type {Array<{label: string, vars: string[]}>}
	 */
	var SWATCHES = [
		{ label: 'Action', vars: ['--nldesign-color-primary', '--color-primary-element'] },
		{ label: 'Action dark', vars: ['--nldesign-color-primary-hover', '--color-primary-element-hover'] },
		{ label: 'Action soft', vars: ['--nldesign-color-primary-light', '--color-primary-element-light'] },
		{ label: 'Accent', vars: ['--nldesign-color-accent', '--color-primary'] },
		{ label: 'Accent soft', vars: ['--nldesign-color-accent-light', '--color-primary-light'] },
		{ label: 'Ink', vars: ['--color-main-text'] },
		{ label: 'Grey text', vars: ['--color-text-maxcontrast'] },
		{ label: 'Work area', vars: ['--color-background-dark', '--color-background-hover'] },
	]

	/**
	 * Translate a string of this app.
	 *
	 * @param {string} text The English text.
	 * @return {string} The translation.
	 */
	function tr(text) {
		return typeof window.t === 'function' ? window.t('thematiq', text) : text
	}

	/**
	 * The value of the first of the custom properties that is set.
	 *
	 * @param {CSSStyleDeclaration} style The computed style to read.
	 * @param {string[]} vars The custom properties, in order.
	 * @return {string} The value, or an empty string.
	 */
	function readVar(style, vars) {
		for (var i = 0; i < vars.length; i++) {
			var value = style.getPropertyValue(vars[i]).trim()
			if (value !== '') {
				return value
			}
		}
		return ''
	}

	/**
	 * A colour as #RRGGBB when it is a hex or rgb() colour; anything else as it is.
	 *
	 * @param {string} value A CSS colour.
	 * @return {string} The colour for the label.
	 */
	function toHex(value) {
		var rgb = /^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/i.exec(value)
		if (rgb !== null) {
			return '#' + [rgb[1], rgb[2], rgb[3]].map(function (n) {
				return ('0' + parseInt(n, 10).toString(16)).slice(-2)
			}).join('').toUpperCase()
		}
		return /^#[0-9a-f]{3,8}$/i.test(value) ? value.toUpperCase() : value
	}

	/**
	 * An element with a class and optional text.
	 *
	 * @param {string} tag The tag name.
	 * @param {string} className The class.
	 * @param {string} [text] The text.
	 * @return {HTMLElement} The element.
	 */
	function el(tag, className, text) {
		var node = document.createElement(tag)
		node.className = className
		if (typeof text === 'string') {
			node.textContent = text
		}
		return node
	}

	/**
	 * Fill the swatch strip from the page's colours.
	 *
	 * @param {HTMLElement} strip The strip.
	 */
	function drawSwatches(strip) {
		var style = getComputedStyle(document.body)
		strip.textContent = ''
		SWATCHES.forEach(function (swatch) {
			var value = readVar(style, swatch.vars)
			var item = el('div', 'thematiq-swatch')
			var chip = el('span', 'thematiq-swatch__colour')
			chip.style.background = value
			item.appendChild(chip)
			item.appendChild(el('span', 'thematiq-swatch__name', tr(swatch.label)))
			item.appendChild(el('span', 'thematiq-swatch__value', toHex(value)))
			strip.appendChild(item)
		})
	}

	/**
	 * The two initials of the signed-in user.
	 *
	 * @return {string} The initials.
	 */
	function initials() {
		var name = (window.OC && typeof OC.getCurrentUser === 'function' && OC.getCurrentUser().displayName) || ''
		return name.split(/\s+/).filter(Boolean).slice(0, 2).map(function (part) {
			return part.charAt(0).toUpperCase()
		}).join('')
	}

	/**
	 * The workplace sample: a bar with the name and the user, then the controls.
	 *
	 * @return {HTMLElement} The sample.
	 */
	function workplaceSample() {
		var sample = el('div', 'thematiq-set-sample')
		var bar = el('div', 'thematiq-set-sample__bar')
		bar.appendChild(el('span', 'thematiq-set-sample__logo'))
		bar.appendChild(el('span', 'thematiq-set-sample__name', (window.OC && OC.theme && OC.theme.name) || 'Nextcloud'))
		bar.appendChild(el('span', 'thematiq-set-sample__spacer'))
		bar.appendChild(el('span', 'thematiq-set-sample__avatar', initials()))
		sample.appendChild(bar)
		var controls = el('div', 'thematiq-set-sample__controls')
		controls.appendChild(el('span', 'thematiq-set-sample__nav', tr('All cases')))
		controls.appendChild(el('span', 'thematiq-set-sample__primary', tr('Primary button')))
		controls.appendChild(el('span', 'thematiq-set-sample__secondary', tr('Second button')))
		controls.appendChild(el('span', 'thematiq-set-sample__pill', tr('In progress')))
		sample.appendChild(controls)
		return sample
	}

	/**
	 * The login sample: the logo, the login card and the slogan.
	 *
	 * @return {HTMLElement} The sample.
	 */
	function loginSample() {
		var sample = el('div', 'thematiq-login-sample')
		sample.appendChild(el('span', 'thematiq-login-sample__logo'))
		var card = el('div', 'thematiq-login-sample__card')
		card.appendChild(el('span', 'thematiq-login-sample__label', tr('Username or email')))
		card.appendChild(el('span', 'thematiq-login-sample__field'))
		card.appendChild(el('span', 'thematiq-login-sample__label', tr('Password')))
		card.appendChild(el('span', 'thematiq-login-sample__field'))
		card.appendChild(el('span', 'thematiq-login-sample__button', tr('Sign in')))
		sample.appendChild(card)
		var slogan = (window.OC && OC.theme && OC.theme.slogan) || ''
		if (slogan !== '') {
			sample.appendChild(el('span', 'thematiq-login-sample__slogan', slogan))
		}
		return sample
	}

	/**
	 * Select a tab and show its panel.
	 *
	 * @param {HTMLElement[]} tabs The tabs.
	 * @param {HTMLElement[]} panels The panels, in tab order.
	 * @param {number} index The tab to select.
	 * @param {boolean} focus Whether to move focus to it.
	 */
	function select(tabs, panels, index, focus) {
		tabs.forEach(function (tab, i) {
			var on = i === index
			tab.setAttribute('aria-selected', on ? 'true' : 'false')
			tab.tabIndex = on ? 0 : -1
			panels[i].hidden = !on
		})
		if (focus) {
			tabs[index].focus()
		}
	}

	/**
	 * Build the preview into its container.
	 *
	 * @param {HTMLElement} root The container.
	 */
	function build(root) {
		root.textContent = ''
		var head = el('div', 'thematiq-active-set-preview__head')
		head.appendChild(el('span', 'thematiq-active-set-preview__title', tr('Preview')))
		var tablist = el('div', 'thematiq-active-set-preview__tabs')
		tablist.setAttribute('role', 'tablist')
		tablist.setAttribute('aria-label', tr('Preview of'))
		head.appendChild(tablist)
		var note = el('span', 'thematiq-active-set-preview__note')
		head.appendChild(note)
		root.appendChild(head)

		var names = [tr('Workplace'), tr('Login page')]
		var notes = [
			tr('The login page shows the logo, the login card with the primary button and the slogan, in the chosen set.'),
			tr('Sign-in page tab: this is how the set draws the sign-in page. Logo and slogan come from the Nextcloud theme.'),
		]
		var tabs = []
		var panels = []
		names.forEach(function (name, i) {
			var tab = el('button', 'thematiq-active-set-preview__tab', name)
			tab.type = 'button'
			tab.id = 'thematiq-preview-tab-' + i
			tab.setAttribute('role', 'tab')
			tab.setAttribute('aria-controls', 'thematiq-preview-panel-' + i)
			tablist.appendChild(tab)
			tabs.push(tab)
			var panel = el('div', 'thematiq-active-set-preview__panel')
			panel.id = 'thematiq-preview-panel-' + i
			panel.setAttribute('role', 'tabpanel')
			panel.setAttribute('aria-labelledby', tab.id)
			panels.push(panel)
			root.appendChild(panel)
		})

		var strip = el('div', 'thematiq-swatches')
		var frame = el('div', 'thematiq-active-set-preview__frame')
		frame.appendChild(strip)
		frame.appendChild(workplaceSample())
		panels[0].appendChild(frame)
		panels[1].appendChild(el('span', 'thematiq-active-set-preview__note', notes[1]))
		panels[1].appendChild(loginSample())
		drawSwatches(strip)

		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () {
				select(tabs, panels, i, false)
			})
			tab.addEventListener('keydown', function (event) {
				var next = { ArrowRight: i + 1, ArrowLeft: i - 1, Home: 0, End: tabs.length - 1 }[event.key]
				if (next === undefined) {
					return
				}
				event.preventDefault()
				select(tabs, panels, (next + tabs.length) % tabs.length, true)
			})
		})
		note.textContent = notes[0]
		select(tabs, panels, 0, false)

		document.addEventListener('thematiq:theme-applied', function () {
			drawSwatches(strip)
		})
	}

	/** Find the container and build the preview. */
	function main() {
		var root = document.getElementById('thematiq-active-set-preview')
		if (root !== null) {
			build(root)
		}
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { build: build, toHex: toHex, readVar: readVar, SWATCHES: SWATCHES }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', main)
	} else {
		main()
	}
})()
