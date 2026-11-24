/**
 * Layout and options (the TqLayout board), the panel of the Theming page at
 * #layout. js/admin-panels.js opens it and lends it the Layout card's options;
 * this script says under each what the active token set chooses, lists the
 * choices made in this session, fills the website and icon facts, and gives
 * the header its Everything follows the theme action.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqLayoutPanel() {
	/**
	 * What each option is called, and what a set that names nothing gives it.
	 * `key` is the option's name in a set's layout block.
	 */
	var OPTIONS = [
		{
			id: 'thematiq-workplace-layout',
			key: 'workplace_layout',
			fallback: 'default',
		},
		{ id: 'thematiq-header-style', key: 'header_style', fallback: 'default' },
		{
			id: 'thematiq-navigation-active-style',
			key: 'navigation_active_style',
			fallback: 'default',
		},
		{
			id: 'thematiq-navigation-width-input',
			key: 'navigation_width',
			fallback: '',
		},
		{ id: 'thematiq-brand-stripe', key: 'brand_stripe', fallback: '0' },
		{
			id: 'thematiq-brand-stripe-placement',
			key: 'brand_stripe_placement',
			fallback: 'header-and-login',
		},
		{ id: 'thematiq-login-watermark', key: 'login_watermark', fallback: '1' },
	]
	var CHECKS = [
		'nldesign-show-menu-labels',
		'nldesign-hide-slogan',
		'nldesign-primary-drives-components',
		'nldesign-dark-variants',
	]
	var session = {}

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
	 * The active token set's catalogue entry.
	 *
	 * @return {object} The entry, or an empty one.
	 */
	function activeSet() {
		var select = document.getElementById('nldesign-token-set-select')
		var sets = []
		try {
			sets = window.OCP.InitialState.loadState('thematiq', 'tokenSets', [])
		} catch (e) {
			sets = []
		}
		var id = select !== null ? select.value : ''
		return (
			(sets || []).find(function (set) {
				return set.id === id
			}) || { name: id }
		)
	}

	/**
	 * What the active set chooses for an option, as the control's value.
	 *
	 * @param {object} option The option.
	 * @param {object} layout The set's layout block.
	 * @return {string} The value.
	 */
	function themeValue(option, layout) {
		var value = layout[option.key]
		if (value === undefined || value === null) {
			return option.fallback
		}
		if (typeof value === 'boolean') {
			return value === true ? '1' : '0'
		}
		return String(value)
	}

	/**
	 * How a value reads in a sentence: the option's own words, starting small.
	 *
	 * @param {HTMLElement} control The select or field.
	 * @param {string} value The value.
	 * @return {string} The words.
	 */
	function words(control, value) {
		if (control.tagName !== 'SELECT') {
			return value === ''
				? tr("Nextcloud's own width")
				: tr('{width} px', { width: value })
		}
		var match = Array.prototype.find.call(control.options, function (opt) {
			return opt.value === value
		})
		var text = match !== undefined ? match.textContent.trim() : value
		return text.charAt(0).toLowerCase() + text.slice(1)
	}

	/**
	 * Write under each option what the theme chooses.
	 *
	 * @return {void}
	 */
	function sayWhatTheThemeSays() {
		var layout = activeSet().layout || {}
		OPTIONS.forEach(function (option) {
			var control = document.getElementById(option.id)
			if (control === null) {
				return
			}
			var holder = control.closest('.nldesign-option') || control.parentNode
			var line = holder.querySelector('.thematiq-panel__now')
			if (line === null) {
				line = document.createElement('span')
				line.className = 'thematiq-panel__now'
				control.insertAdjacentElement('afterend', line)
			}
			var theme = themeValue(option, layout)
			line.textContent =
				control.value !== '' && control.value === theme
					? tr('Chosen, the theme says the same')
					: tr('The theme says: {choice}', {
							choice: words(control, theme),
						})
		})
	}

	/**
	 * The label of a control, as the page shows it.
	 *
	 * @param {HTMLElement} control The control.
	 * @return {string} The label.
	 */
	function labelOf(control) {
		var label = document.querySelector('label[for="' + control.id + '"]')
		return label !== null ? label.textContent.trim() : control.id
	}

	/**
	 * List the choices made in this session.
	 *
	 * @return {void}
	 */
	function drawSession() {
		var panel = document.querySelector('[data-thematiq-panel="layout"]')
		if (panel === null) {
			return
		}
		var ids = Object.keys(session)
		panel.querySelector('.thematiq-panel__count').textContent = String(
			ids.length,
		)
		panel.querySelector('.thematiq-layout-panel__summary').textContent =
			ids.length === 1
				? tr('1 choice in this session.')
				: tr('{count} choices in this session.', { count: ids.length })
		var list = panel.querySelector('.thematiq-layout-panel__changes')
		list.replaceChildren.apply(
			list,
			ids.map(function (id) {
				var row = document.createElement('div')
				row.className = 'thematiq-layout-panel__change'
				var name = document.createElement('span')
				name.className = 'thematiq-layout-panel__change-name'
				name.textContent = session[id].label
				var value = document.createElement('span')
				value.className = 'thematiq-layout-panel__change-value'
				value.textContent = session[id].value
				row.appendChild(name)
				row.appendChild(value)
				return row
			}),
		)
	}

	/**
	 * Remember a choice for the session list.
	 *
	 * @param {Event} event The change.
	 * @return {void}
	 */
	function remember(event) {
		var control = event.target
		if (!(control instanceof HTMLElement) || control.id === '') {
			return
		}
		var option = OPTIONS.find(function (entry) {
			return entry.id === control.id
		})
		var value
		if (option !== undefined) {
			value =
				control.value === ''
					? tr('Follow the theme')
					: words(control, control.value)
		} else if (CHECKS.indexOf(control.id) !== -1) {
			value = control.checked === true ? tr('on') : tr('off')
		} else {
			return
		}
		session[control.id] = { label: labelOf(control), value: value }
		drawSession()
		sayWhatTheThemeSays()
		drawPreviews()
	}

	/**
	 * A size in pixels, or null when the value is not one.
	 *
	 * @param {string} value A CSS length.
	 * @return {number|null} The pixels.
	 */
	function pixels(value) {
		var match = /^(\d+(?:\.\d+)?)(px|rem)$/.exec(String(value).trim())
		if (match === null) {
			return null
		}
		return Math.round(
			match[2] === 'rem' ? parseFloat(match[1]) * 16 : parseFloat(match[1]),
		)
	}

	/**
	 * Fill the website fact from the sizes the set gives the website.
	 *
	 * @return {void}
	 */
	function drawWebsite() {
		var panel = document.querySelector('[data-thematiq-panel="layout"]')
		if (panel === null) {
			return
		}
		var style = getComputedStyle(document.body)
		var parts = []
		var heading = pixels(
			style.getPropertyValue('--nldesign-website-heading-1-font-size'),
		)
		var body = pixels(
			style.getPropertyValue('--nldesign-website-content-font-size'),
		)
		var page = pixels(
			style.getPropertyValue('--nldesign-website-page-max-width'),
		)
		if (heading !== null) {
			parts.push(tr('Heading 1 {size} px', { size: heading }))
		}
		if (body !== null) {
			parts.push(tr('text {size} px', { size: body }))
		}
		if (page !== null) {
			parts.push(tr('page {size} px', { size: page }))
		}
		panel.querySelector('.thematiq-layout-panel__website').textContent =
			parts.length > 0
				? parts.join(', ')
				: tr('The set gives the website no sizes.')
		var source = document.querySelector('.thematiq-lettering__link')
		var link = panel.querySelector('.thematiq-layout-panel__website-link')
		if (source !== null && source.getAttribute('href')) {
			link.setAttribute('href', source.getAttribute('href'))
			link.hidden = false
		} else {
			link.hidden = true
		}
	}

	/**
	 * What an option comes to: the admin's choice, or the theme's.
	 *
	 * @param {string} id The control's id.
	 * @return {string} The value.
	 */
	function effective(id) {
		var option = OPTIONS.find(function (entry) {
			return entry.id === id
		})
		var control = document.getElementById(id)
		if (option === undefined || control === null) {
			return ''
		}
		return control.value !== ''
			? control.value
			: themeValue(option, activeSet().layout || {})
	}

	/**
	 * An element with a class and, optionally, text.
	 *
	 * @param {string} tag The tag.
	 * @param {string} className The class.
	 * @param {string} [text] The text.
	 * @return {HTMLElement} The element.
	 */
	function el(tag, className, text) {
		var node = document.createElement(tag)
		node.className = className
		if (text !== undefined) {
			node.textContent = text
		}
		return node
	}

	/**
	 * A small drawing of the workspace: top bar, menu and content.
	 *
	 * @param {boolean} after Whether to draw the choices, or Nextcloud's default.
	 * @return {HTMLElement} The drawing.
	 */
	function workspaceDrawing(after) {
		var light =
			after === true && effective('thematiq-workplace-layout') === 'light'
		var workplace =
			light === true && effective('thematiq-header-style') === 'workplace'
		var soft =
			after === true
			&& effective('thematiq-navigation-active-style') === 'soft'
		var user =
			typeof window.OC !== 'undefined'
			&& typeof window.OC.getCurrentUser === 'function'
				? window.OC.getCurrentUser()
				: null
		var name = user !== null && user.displayName ? user.displayName : ''
		var initials = name
			.split(/\s+/)
			.map(function (part) {
				return part.charAt(0)
			})
			.join('')
			.slice(0, 2)
			.toUpperCase()

		var frame = el('div', 'thematiq-preview')
		var bar = el(
			'div',
			'thematiq-preview__bar'
				+ (light === true ? ' thematiq-preview__bar--light' : ''),
		)
		bar.appendChild(el('span', 'thematiq-preview__logo'))
		if (workplace === true) {
			var app = el('span', 'thematiq-preview__app')
			app.appendChild(
				el(
					'span',
					'thematiq-preview__org',
					window.OC && window.OC.theme ? window.OC.theme.name : '',
				),
			)
			app.appendChild(el('strong', 'thematiq-preview__app-name', tr('Files')))
			bar.appendChild(app)
			bar.appendChild(el('span', 'thematiq-preview__search'))
		} else {
			for (var i = 0; i < 4; i++) {
				bar.appendChild(el('span', 'thematiq-preview__icon'))
			}
			bar.appendChild(el('span', 'thematiq-preview__spacer'))
		}
		bar.appendChild(el('span', 'thematiq-preview__avatar', initials))
		if (workplace === true) {
			bar.appendChild(el('strong', 'thematiq-preview__user', name))
		}
		frame.appendChild(bar)

		var body = el('div', 'thematiq-preview__body')
		var nav = el('div', 'thematiq-preview__nav')
		;[tr('All files'), tr('Recent'), tr('Favourites'), tr('Shared')].forEach(
			function (label, index) {
				var entry = el('span', 'thematiq-preview__entry', label)
				if (index === 0) {
					entry.classList.add(
						soft === true
							? 'thematiq-preview__entry--soft'
							: 'thematiq-preview__entry--filled',
					)
				}
				nav.appendChild(entry)
			},
		)
		var content = el('div', 'thematiq-preview__content')
		content.appendChild(el('span', 'thematiq-preview__line'))
		content.appendChild(el('span', 'thematiq-preview__block'))
		content.appendChild(
			el('span', 'thematiq-preview__block thematiq-preview__block--small'),
		)
		body.appendChild(nav)
		body.appendChild(content)
		frame.appendChild(body)
		return frame
	}

	/**
	 * What the choices come to, in one or two sentences.
	 *
	 * @return {string} The sentences.
	 */
	function afterCaption() {
		var light = effective('thematiq-workplace-layout') === 'light'
		var bar =
			light === true
				? effective('thematiq-header-style') === 'workplace'
					? tr('White top bar with your name and role')
					: tr('White top bar')
				: tr('Top bar in the primary colour')
		var width = effective('thematiq-navigation-width-input')
		var parts = [bar]
		if (width !== '') {
			parts.push(tr('menu of {width} px', { width: width }))
		}
		parts.push(
			effective('thematiq-navigation-active-style') === 'soft'
				? tr('the chosen entry in a soft tint')
				: tr('the chosen entry filled'),
		)
		var stripe = effective('thematiq-brand-stripe') === '1'
		var placement = effective('thematiq-brand-stripe-placement')
		var stripeLine =
			stripe === false
				? tr('No brand stripe.')
				: placement === 'login'
					? tr('The brand stripe is only on the login card.')
					: placement === 'header'
						? tr('The brand stripe is only under the top bar.')
						: tr(
								'The brand stripe is under the top bar and on the login card.',
							)
		return parts.join(', ') + '. ' + stripeLine
	}

	/**
	 * A small drawing of the login card.
	 *
	 * @return {HTMLElement} The drawing.
	 */
	function loginDrawing() {
		var stripe =
			effective('thematiq-brand-stripe') === '1'
			&& effective('thematiq-brand-stripe-placement') !== 'header'
		var stage = el('div', 'thematiq-preview-login')
		if (effective('thematiq-login-watermark') === '1') {
			stage.appendChild(el('span', 'thematiq-preview-login__watermark'))
		}
		var card = el('div', 'thematiq-preview-login__card')
		if (stripe === true) {
			var bands = el('div', 'thematiq-preview-login__stripe')
			bands.appendChild(el('span', ''))
			bands.appendChild(el('span', ''))
			bands.appendChild(el('span', ''))
			card.appendChild(bands)
		}
		var form = el('div', 'thematiq-preview-login__form')
		form.appendChild(el('span', 'thematiq-preview__logo'))
		form.appendChild(el('span', 'thematiq-preview-login__field'))
		form.appendChild(el('span', 'thematiq-preview-login__field'))
		form.appendChild(el('span', 'thematiq-preview-login__button', tr('Log in')))
		card.appendChild(form)
		stage.appendChild(card)
		return stage
	}

	/**
	 * What the login card shows, in one sentence.
	 *
	 * @return {string} The sentence.
	 */
	function loginCaption() {
		var parts = []
		var stripe =
			effective('thematiq-brand-stripe') === '1'
			&& effective('thematiq-brand-stripe-placement') !== 'header'
		parts.push(
			stripe === true
				? tr('Brand stripe on the card')
				: tr('No brand stripe on the card'),
		)
		parts.push(
			effective('thematiq-login-watermark') === '1'
				? tr('emblem large and faint behind it')
				: tr('no emblem behind it'),
		)
		var slogan = document.getElementById('nldesign-hide-slogan')
		parts.push(
			slogan !== null && slogan.checked === true
				? tr('no slogan')
				: tr('with the slogan'),
		)
		return parts.join(', ') + '.'
	}

	/**
	 * A preview card of the aside.
	 *
	 * @param {string} title The title.
	 * @param {string|null} pill The pill beside the title.
	 * @param {HTMLElement} drawing The drawing.
	 * @param {string} caption The line under it.
	 * @param {string} modifier A class for the card.
	 * @return {HTMLElement} The card.
	 */
	function previewCard(title, pill, drawing, caption, modifier) {
		var card = el(
			'section',
			'thematiq-card thematiq-panel__card thematiq-layout-panel__preview '
				+ modifier,
		)
		var head = el('div', 'thematiq-panel__card-head')
		head.appendChild(el('h2', 'thematiq-panel__card-title', title))
		if (pill !== null) {
			head.appendChild(el('span', 'thematiq-layout-panel__pill', pill))
		}
		card.appendChild(head)
		card.appendChild(drawing)
		card.appendChild(el('span', 'thematiq-panel__fact-hint', caption))
		return card
	}

	/**
	 * Draw Before, After and the login card in the aside.
	 *
	 * @return {void}
	 */
	function drawPreviews() {
		var aside = document.querySelector(
			'[data-thematiq-panel="layout"] .thematiq-panel__aside',
		)
		if (aside === null) {
			return
		}
		aside
			.querySelectorAll('.thematiq-layout-panel__preview')
			.forEach(function (card) {
				card.remove()
			})
		aside.appendChild(
			previewCard(
				tr('Before'),
				tr('Nextcloud default'),
				workspaceDrawing(false),
				tr(
					'Top bar in the primary colour, icons only, the chosen entry filled.',
				),
				'thematiq-layout-panel__preview--before',
			),
		)
		aside.appendChild(
			previewCard(
				tr('After'),
				tr('With these choices'),
				workspaceDrawing(true),
				afterCaption(),
				'thematiq-layout-panel__preview--after',
			),
		)
		aside.appendChild(
			previewCard(
				tr('Login card'),
				null,
				loginDrawing(),
				loginCaption(),
				'thematiq-layout-panel__preview--login',
			),
		)
	}

	/**
	 * Let every option follow the theme again.
	 *
	 * @return {void}
	 */
	function followTheTheme() {
		OPTIONS.forEach(function (option) {
			var control = document.getElementById(option.id)
			if (control === null || control.value === '') {
				return
			}
			control.value = ''
			control.dispatchEvent(new Event('change', { bubbles: true }))
		})
	}

	/**
	 * The header's actions for this panel.
	 *
	 * @return {Array<HTMLElement>} The buttons.
	 */
	function actions() {
		var button = document.createElement('button')
		button.type = 'button'
		button.className =
			'thematiq-page-header__action thematiq-layout-panel__follow'
		button.textContent = tr('Everything follows the theme')
		button.addEventListener('click', followTheTheme)
		return [button]
	}

	document.addEventListener('change', remember)
	document.addEventListener('thematiq:theme-applied', function () {
		sayWhatTheThemeSays()
		drawWebsite()
		drawPreviews()
	})
	var form = document.querySelector('[data-thematiq-panel="layout"] form')
	if (form !== null) {
		form.addEventListener('submit', function (event) {
			event.preventDefault()
		})
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('layout', {
			title: tr('Layout and options'),
			intro: function () {
				return tr(
					'How the workspace looks on top of the colours. Every option you leave empty follows the active token set, now {name}. Your choice here always wins.',
					{ name: activeSet().name || '' },
				)
			},
			actions: actions,
			onOpen: function () {
				sayWhatTheThemeSays()
				drawSession()
				drawWebsite()
				drawPreviews()
			},
		})
	}
})()
