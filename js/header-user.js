/**
 * Header style "workplace": the name and the role of the signed-in person
 * beside the avatar in the top bar (the DqKop board).
 *
 * Loaded only while the header style resolves to `workplace`, together with
 * css/header-workplace.css. Reads the initial state HeaderUserService
 * provides (`{ name, role }`), puts a label as the first child of
 * Nextcloud's account menu (`#user-menu`) and marks the bar with
 * `data-thematiq-user-chip`. The sheet then hides Nextcloud's own avatar and
 * stretches the menu's button over the label, so a click anywhere on it opens
 * the real menu. The label is `aria-hidden`: the button keeps its own name,
 * and the menu it opens already names the person.
 *
 * Without a name, or without the account menu, it does nothing and the bar
 * keeps Nextcloud's avatar. When the account menu re-renders and drops the
 * label, the label is put back.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
 */
;(function thematiqHeaderUserInit() {
	var CHIP_CLASS = 'thematiq-user-chip'
	var SVG_NS = 'http://www.w3.org/2000/svg'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqHeaderUserMain)
	} else {
		thematiqHeaderUserMain()
	}

	/**
	 * The initials of a name: the first letter of its first and of its last
	 * word, or of its only word.
	 *
	 * @param {string} name The display name.
	 * @return {string} One or two capital letters.
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
	 */
	function initialsOf(name) {
		var words = String(name).trim().split(/\s+/).filter(Boolean)
		if (words.length === 0) {
			return ''
		}
		var first = Array.from(words[0])[0] || ''
		var last =
			words.length > 1 ? Array.from(words[words.length - 1])[0] || '' : ''
		return (first + last).toLocaleUpperCase()
	}

	/**
	 * Build the label: the initials avatar, the name and the role, a chevron.
	 *
	 * @param {{name: string, role: string}} person The person.
	 * @return {HTMLElement} The label.
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
	 */
	function buildChip(person) {
		var chip = document.createElement('span')
		chip.className = CHIP_CLASS
		chip.setAttribute('aria-hidden', 'true')

		var avatar = document.createElement('span')
		avatar.className = CHIP_CLASS + '__avatar'
		avatar.textContent = initialsOf(person.name)
		chip.appendChild(avatar)

		var text = document.createElement('span')
		text.className = CHIP_CLASS + '__text'
		var name = document.createElement('strong')
		name.className = CHIP_CLASS + '__name'
		name.textContent = person.name
		text.appendChild(name)
		if (person.role) {
			var role = document.createElement('span')
			role.className = CHIP_CLASS + '__role'
			role.textContent = person.role
			text.appendChild(role)
		}
		chip.appendChild(text)

		var chevron = document.createElementNS(SVG_NS, 'svg')
		chevron.setAttribute('class', CHIP_CLASS + '__chevron')
		chevron.setAttribute('viewBox', '0 0 24 24')
		chevron.setAttribute('fill', 'none')
		chevron.setAttribute('stroke', 'currentColor')
		chevron.setAttribute('stroke-width', '2.2')
		chevron.setAttribute('stroke-linecap', 'round')
		var path = document.createElementNS(SVG_NS, 'path')
		path.setAttribute('d', 'M6 9l6 6 6-6')
		chevron.appendChild(path)
		chip.appendChild(chevron)

		return chip
	}

	/**
	 * Read the person and keep the label in the account menu.
	 *
	 * @spec openspec/changes/header-style-workplace/specs/workplace-layout/spec.md
	 */
	function thematiqHeaderUserMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}

		var person = null
		try {
			person = OCP.InitialState.loadState('thematiq', 'header-user', null)
		} catch (e) {
			return
		}
		if (
			!person
			|| typeof person.name !== 'string'
			|| person.name.trim() === ''
		) {
			return
		}
		person = {
			name: person.name.trim(),
			role: typeof person.role === 'string' ? person.role.trim() : '',
		}

		var header = document.getElementById('header')
		if (header === null) {
			return
		}

		var place = function () {
			var menu = document.getElementById('user-menu')
			if (menu === null) {
				return false
			}
			if (menu.querySelector(':scope > .' + CHIP_CLASS) === null) {
				menu.insertBefore(buildChip(person), menu.firstChild)
			}
			header.setAttribute('data-thematiq-user-chip', '')
			return true
		}

		place()
		// The account menu mounts after this script on some pages, and a
		// re-render can drop a node it did not create: watch the bar and put
		// the label back.
		if (typeof MutationObserver === 'function') {
			new MutationObserver(function () {
				place()
			}).observe(header, { childList: true, subtree: true })
		}
	}
})()
