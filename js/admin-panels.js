/**
 * The pages of the Theming admin page (TqLayout, TqDonker and the others),
 * as panels of the one page, addressed by a URL hash (#layout, #dark).
 *
 * A panel is `<section class="thematiq-panel" data-thematiq-panel="<hash>" hidden>`
 * in templates/settings/admin.php. While it is open the overview is hidden,
 * the page header shows the panel's title, breadcrumb, line and actions, and
 * each `data-thematiq-slot="<id>"` in the panel borrows that control's option
 * from the overview; closing gives everything back. The hash makes the
 * browser's back button and deep links work.
 *
 * `window.ThematiqPanels.register(hash, {title, crumbs, intro, actions, onOpen, onClose})`
 * describes a panel. `crumbs` are the steps between Thematiq and the page itself, which
 * the breadcrumb names `crumb` (the title when left out);
 * `intro` is the line under them, text or nodes; `actions` returns the header buttons.
 * A panel about one thing (one token set) is opened as `#<hash>/<argument>`; its
 * functions and `onOpen(section, argument)` receive the argument.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/design.md
 */
;(function thematiqPanels() {
	var panels = {}
	var current = null
	var argument = ''
	var borrowed = []
	var saved = null

	/**
	 * A translated string of the app.
	 *
	 * @param {string} text The English text.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text) {
		return typeof window.t === 'function' ? window.t('thematiq', text) : text
	}

	/**
	 * The page header's parts.
	 *
	 * @return {object|null} The title, breadcrumb, line and actions, or null without a header.
	 */
	function header() {
		var root = document.getElementById('thematiq-page-header')
		if (root === null) {
			return null
		}
		return {
			title: root.querySelector('.thematiq-page-header__title'),
			crumbs: root.querySelector('.thematiq-page-header__crumbs'),
			intro: root.querySelector('.thematiq-page-header__intro'),
			actions: root.querySelector('.thematiq-page-header__actions'),
		}
	}

	/**
	 * A breadcrumb step: a link, the current page, or plain text.
	 *
	 * @param {string} text The step.
	 * @param {string|null} href Where it leads, or null.
	 * @param {boolean} last Whether it is the current page.
	 * @return {HTMLElement} The step.
	 */
	function crumb(text, href, last) {
		var el = document.createElement(href !== null ? 'a' : 'span')
		el.textContent = text
		if (href !== null) {
			el.setAttribute('href', href)
		}
		if (last === true) {
			el.setAttribute('aria-current', 'page')
		}
		return el
	}

	/**
	 * A panel's setting, called with the open argument when it is a function.
	 *
	 * @param {*} value The setting.
	 * @return {*} Its value.
	 */
	function resolve(value) {
		return typeof value === 'function' ? value(argument) : value
	}

	/**
	 * Show the panel's words and actions in the page header.
	 *
	 * @param {object} config The panel.
	 * @return {void}
	 */
	function dressHeader(config) {
		var parts = header()
		if (parts === null) {
			return
		}
		saved = {
			title: parts.title.textContent,
			crumbs: Array.prototype.slice.call(parts.crumbs.childNodes),
			intro: Array.prototype.slice.call(parts.intro.childNodes),
			actions: Array.prototype.slice.call(parts.actions.childNodes),
		}
		parts.title.textContent = resolve(config.title)
		var crumbs = resolve(config.crumbs)
		var steps = [
			[tr('Administration settings'), null],
			[tr('Themes and appearance'), '#'],
			['Thematiq', null],
		].concat(
			(crumbs || []).map(function (step) {
				return [step.text, step.href || null]
			}),
		)
		steps.push([resolve(config.crumb) || resolve(config.title), null])
		parts.crumbs.replaceChildren()
		steps.forEach(function (step, index) {
			if (index > 0) {
				var slash = document.createElement('span')
				slash.setAttribute('aria-hidden', 'true')
				slash.textContent = '/'
				parts.crumbs.appendChild(slash)
			}
			parts.crumbs.appendChild(
				crumb(step[0], step[1], index === steps.length - 1),
			)
		})
		var intro = resolve(config.intro) || ''
		if (Array.isArray(intro) === true) {
			parts.intro.replaceChildren.apply(parts.intro, intro)
		} else {
			parts.intro.textContent = intro
		}
		parts.actions.replaceChildren.apply(
			parts.actions,
			typeof config.actions === 'function' ? config.actions(argument) : [],
		)
	}

	/**
	 * Give the page header its own words and actions back.
	 *
	 * @return {void}
	 */
	function undressHeader() {
		var parts = header()
		if (parts === null || saved === null) {
			return
		}
		parts.title.textContent = saved.title
		parts.crumbs.replaceChildren.apply(parts.crumbs, saved.crumbs)
		parts.intro.replaceChildren.apply(parts.intro, saved.intro)
		parts.actions.replaceChildren.apply(parts.actions, saved.actions)
		saved = null
	}

	/**
	 * Borrow the overview's options into the panel's slots.
	 *
	 * @param {HTMLElement} section The panel.
	 * @return {void}
	 */
	function borrow(section) {
		section.querySelectorAll('[data-thematiq-slot]').forEach(function (slot) {
			var control = document.getElementById(
				slot.getAttribute('data-thematiq-slot'),
			)
			if (control === null) {
				return
			}
			var option = control.closest('.nldesign-option') || control
			borrowed.push({
				option: option,
				parent: option.parentNode,
				next: option.nextSibling,
			})
			slot.appendChild(option)
		})
	}

	/**
	 * Give the borrowed options back to the overview, where they were.
	 *
	 * @return {void}
	 */
	function giveBack() {
		borrowed.reverse().forEach(function (entry) {
			entry.parent.insertBefore(entry.option, entry.next)
		})
		borrowed = []
	}

	/**
	 * Close the open panel and show the overview.
	 *
	 * @return {void}
	 */
	function close() {
		if (current === null) {
			return
		}
		var hash = current
		var link = document.querySelector(
			'a[href="#' + (argument !== '' ? hash + '/' + argument : hash) + '"]',
		)
		var config = panels[hash]
		var section = document.querySelector(
			'section[data-thematiq-panel="' + hash + '"]',
		)
		giveBack()
		undressHeader()
		if (section !== null) {
			section.hidden = true
		}
		delete document.documentElement.dataset.thematiqOpenPanel
		current = null
		argument = ''
		if (typeof config.onClose === 'function') {
			config.onClose()
		}
		if (link !== null) {
			link.focus()
		}
	}

	/**
	 * Open a panel.
	 *
	 * @param {string} target The panel's hash without the #, perhaps followed by `/<argument>`.
	 * @return {boolean} Whether a panel opened.
	 */
	function open(target) {
		var cut = String(target).indexOf('/')
		var hash = cut === -1 ? String(target) : String(target).slice(0, cut)
		var wanted =
			cut === -1 ? '' : decodeURIComponent(String(target).slice(cut + 1))
		var config = panels[hash]
		var section = document.querySelector(
			'section[data-thematiq-panel="' + hash + '"]',
		)
		if (config === undefined || section === null) {
			return false
		}
		if (current === hash && argument === wanted) {
			return true
		}
		close()
		current = hash
		argument = wanted
		document.documentElement.dataset.thematiqOpenPanel = hash
		section.hidden = false
		borrow(section)
		dressHeader(config)
		if (typeof config.onOpen === 'function') {
			config.onOpen(section, argument)
		}
		window.scrollTo(0, 0)
		var title = document.querySelector(
			'#thematiq-page-header .thematiq-page-header__title',
		)
		if (title !== null) {
			title.setAttribute('tabindex', '-1')
			title.focus()
		}
		return true
	}

	/**
	 * Follow the hash: open its panel, or show the overview.
	 *
	 * @return {void}
	 */
	function follow() {
		var hash = window.location.hash.replace(/^#/, '')
		if (open(hash) === false) {
			close()
		}
	}

	/**
	 * Describe a panel; a page opened on its hash opens it at once.
	 *
	 * @param {string} hash The panel's hash, without the #.
	 * @param {object} config The panel.
	 * @return {void}
	 */
	function register(hash, config) {
		panels[hash] = config
		var wanted = window.location.hash.replace(/^#/, '')
		if (wanted === hash || wanted.indexOf(hash + '/') === 0) {
			open(wanted)
		}
	}

	window.addEventListener('hashchange', follow)

	window.ThematiqPanels = {
		register: register,
		open: open,
		close: close,
		current: function () {
			return current
		},
		argument: function () {
			return argument
		},
	}
})()
