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
 * With the workplace apps menu, the account menu also gets a "Replay
 * walkthrough" entry under Help, which starts the apps menu walkthrough.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md
 */
;(function thematiqHeaderUserInit() {
	var CHIP_CLASS = 'thematiq-user-chip'
	var SVG_NS = 'http://www.w3.org/2000/svg'

	var REPLAY_ID = 'thematiq-replay-walkthrough'

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqHeaderUserMain)
		document.addEventListener('DOMContentLoaded', thematiqReplayEntryMain)
	} else {
		thematiqHeaderUserMain()
		thematiqReplayEntryMain()
	}

	/**
	 * Build the "Replay walkthrough" entry as a copy of an entry of the
	 * account menu, so it looks like its neighbours.
	 *
	 * @param {Element} model The entry to copy (`li`).
	 * @param {string} label The entry's text.
	 * @return {Element} The entry.
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-can-be-replayed-req-tqwt-006
	 */
	function buildReplayEntry(model, label) {
		var entry = model.cloneNode(true)
		entry.setAttribute('data-thematiq-replay', '')
		var link = entry.querySelector('a')
		if (link === null) {
			link = document.createElement('a')
			entry.textContent = ''
			entry.appendChild(link)
		}
		link.id = REPLAY_ID
		link.setAttribute('href', '#')
		link.removeAttribute('target')
		var icon = link.querySelector('img')
		if (icon !== null) {
			icon.remove()
		}
		var name = link.querySelector('.list-item-content__name')
		if (name !== null) {
			name.textContent = label
			var sub = link.querySelector('.list-item-content__subname')
			if (sub !== null) {
				sub.remove()
			}
		} else {
			link.textContent = label
		}
		link.addEventListener('click', function (event) {
			event.preventDefault()
			var trigger = document.querySelector(
				'#user-menu [aria-expanded="true"]',
			)
			if (trigger !== null) {
				trigger.click()
			}
			if (typeof window.thematiqAppMenuReplay === 'function') {
				window.thematiqAppMenuReplay()
			}
		})
		return entry
	}

	/**
	 * Keep a "Replay walkthrough" entry in the account menu, directly under
	 * Nextcloud's Help entry, or last when Help is not there. Only with the
	 * workplace apps menu, whose state carries the tour.
	 *
	 * @spec openspec/changes/apps-menu-walkthrough/specs/apps-menu-walkthrough/spec.md#requirement-the-tour-can-be-replayed-req-tqwt-006
	 */
	function thematiqReplayEntryMain() {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return
		}
		var menu = null
		try {
			menu = OCP.InitialState.loadState('thematiq', 'app-menu', null)
		} catch (e) {
			return
		}
		var tour = menu && menu.walkthrough
		if (
			!tour
			|| typeof tour.replayLabel !== 'string'
			|| tour.replayLabel === ''
		) {
			return
		}
		var header = document.getElementById('header')
		if (header === null) {
			return
		}
		var place = function () {
			var list = header.querySelector(
				'#header-menu-user-menu ul, #user-menu ul',
			)
			if (list === null || list.querySelector('#' + REPLAY_ID) !== null) {
				return
			}
			var help = list.querySelector('#help')
			var after = help !== null ? help.closest('li') : null
			var model = after || list.querySelector('li.account-menu-entry')
				|| list.querySelector('li')
			if (model === null) {
				return
			}
			var entry = buildReplayEntry(model, tour.replayLabel)
			if (after !== null && after.parentNode === list) {
				list.insertBefore(entry, after.nextSibling)
			} else {
				list.appendChild(entry)
			}
		}
		place()
		if (typeof MutationObserver === 'function') {
			new MutationObserver(place).observe(header, {
				childList: true,
				subtree: true,
			})
		}
	}

	/**
	 * The initials of a name: the first letter of its first and of its last
	 * word, or of its only word.
	 *
	 * @param {string} name The display name.
	 * @return {string} One or two capital letters.
	 * @spec openspec/specs/workplace-layout/spec.md
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
	 * @spec openspec/specs/workplace-layout/spec.md
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
		chevron.setAttribute('fill', 'currentColor')
		var path = document.createElementNS(SVG_NS, 'path')
		// Material Design Icons chevron-down, as the board draws it.
		path.setAttribute(
			'd',
			'M7.41,8.58L12,13.17L16.59,8.58L18,10L12,16L6,10L7.41,8.58Z',
		)
		chevron.appendChild(path)
		chip.appendChild(chevron)

		return chip
	}

	/**
	 * Read the person and keep the label in the account menu.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md
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
