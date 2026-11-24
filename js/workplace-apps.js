/**
 * Workplace app layer: what the Mail, Calendar and Deck boards need that a
 * stylesheet cannot do alone. Acts only while css/workplace-apps/common.css
 * is on the page (it sets `--thematiq-workplace-apps: 1`), so the light
 * layout's other apps and the default layout are left alone.
 *
 * - The words the boards use where the app's own differ, as translated CSS
 *   variables the app sheets show in place of the app's text: Mail's empty
 *   reading pane reads "Select a conversation" (MlInbox), Calendar's new
 *   event button "New event" (ClMonth, ClWeek).
 * - Calendar's week view names a day "Mon 12" (ClWeek), where the locale
 *   format writes "Mon 23/11/2026": the weekday the app wrote, then the day
 *   of the month from the column's own date, in a span the sheet styles.
 * - Deck's card faces as TbBoard draws them: the card's number next to its
 *   type label, the due date as a short date ("16 Oct") from the full date
 *   Deck keeps on the badge, and the number of open cards under the board
 *   title, each as a data attribute the Deck sheet shows. From the board's
 *   cards as Deck's API returns them: the lines "From mail: <sender>" and
 *   "Project: <name>" a description starts with, shown under the title, and
 *   a label named "Priority high" shown as the priority mark.
 * - The input in use: `data-thematiq-pointer` on the root while the person
 *   works with a pointer, so the sheets can leave out a focus ring that an
 *   app puts on a heading it focuses itself (Deck's card title, TbCard). A
 *   key press removes it again.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
 */
;(function thematiqWorkplaceAppsInit() {
	var POINTER = 'data-thematiq-pointer'
	var DAY_LINK = '.fc-timeGridWeek-view .fc-col-header-cell[data-date] .fc-col-header-cell-cushion'
	var DAY_NUMBER = 'thematiq-day-number'
	var PRIORITY_LABELS = ['priority high', 'high priority', 'prioriteit hoog']
	var deckCards = { board: null, at: 0, byId: {}, loading: false }
	var MONTHS = ['january', 'february', 'march', 'april', 'may', 'june', 'july', 'august', 'september', 'october', 'november', 'december']

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqWorkplaceAppsMain)
	} else {
		thematiqWorkplaceAppsMain()
	}

	/**
	 * A CSS string literal holding the text.
	 *
	 * @param {string} text The text.
	 * @return {string} The quoted text.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function cssString(text) {
		return '"' + String(text).replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/\n/g, ' ') + '"'
	}

	/**
	 * Write "Mon 12" on every day of Calendar's week view that still reads
	 * otherwise, the day of the month in a span of its own: ClWeek sets the
	 * number apart and marks today on the number alone.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function shortenDays() {
		document.querySelectorAll(DAY_LINK).forEach(function (link) {
			var date = link.closest('[data-date]').getAttribute('data-date')
			var day = /^\d{4}-\d{2}-(\d{2})$/.exec(date || '')
			var weekday = (link.textContent || '').trim().split(/\s+/)[0]
			if (day === null || weekday === '') {
				return
			}
			var number = String(Number(day[1]))
			var current = link.querySelector(':scope > .' + DAY_NUMBER)
			if (
				link.textContent === weekday + ' ' + number
				&& current !== null
				&& link.childNodes.length === 2
			) {
				return
			}
			var span = document.createElement('span')
			span.className = DAY_NUMBER
			span.textContent = number
			link.textContent = weekday + ' '
			link.appendChild(span)
		})
	}

	/**
	 * The short date ("16 Oct") of a full date Deck writes in English
	 * ("Friday, 16 October 2026 17:00" or "Friday, October 16, 2026 5:00 PM"),
	 * in the page's locale; null when the text is not such a date.
	 *
	 * @param {string} text The full date.
	 * @param {string} locale The page's locale, for example "en-GB".
	 * @return {string|null} The short date.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function shortDate(text, locale) {
		var dayFirst = /(\d{1,2})(?:st|nd|rd|th)? ([A-Za-z]+),? (\d{4})/.exec(text || '')
		var monthFirst = /([A-Za-z]+) (\d{1,2}),? (\d{4})/.exec(text || '')
		var day = null
		var month = -1
		var year = null
		if (dayFirst !== null && MONTHS.indexOf(dayFirst[2].toLowerCase()) >= 0) {
			day = Number(dayFirst[1])
			month = MONTHS.indexOf(dayFirst[2].toLowerCase())
			year = Number(dayFirst[3])
		} else if (monthFirst !== null && MONTHS.indexOf(monthFirst[1].toLowerCase()) >= 0) {
			day = Number(monthFirst[2])
			month = MONTHS.indexOf(monthFirst[1].toLowerCase())
			year = Number(monthFirst[3])
		}
		if (day === null) {
			return null
		}
		try {
			return new Intl.DateTimeFormat(locale, { day: 'numeric', month: 'short' }).format(new Date(year, month, day))
		} catch (e) {
			return null
		}
	}

	/**
	 * Set an attribute only when it changes, so the watcher does not wake itself.
	 *
	 * @param {Element} element The element.
	 * @param {string} name The attribute.
	 * @param {string|null} value The value, or null to remove it.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function setData(element, name, value) {
		if (value === null) {
			if (element.hasAttribute(name)) {
				element.removeAttribute(name)
			}
			return
		}
		if (element.getAttribute(name) !== value) {
			element.setAttribute(name, value)
		}
	}

	/**
	 * The lines "From mail: <sender>" and "Project: <name>" at the start of a
	 * card's description.
	 *
	 * @param {string} description The description.
	 * @return {{mail: string|null, project: string|null}} The values found.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function cardLines(description) {
		var found = { mail: null, project: null }
		var lines = String(description || '').split(/\r?\n/).slice(0, 4)
		lines.forEach(function (line) {
			var mail = /^\s*(?:From mail|Uit mail):\s*(.+?)\s*$/i.exec(line)
			var project = /^\s*(?:Project):\s*(.+?)\s*$/i.exec(line)
			if (mail !== null && found.mail === null) {
				found.mail = mail[1]
			} else if (project !== null && found.project === null) {
				found.project = project[1]
			}
		})
		return found
	}

	/**
	 * Load the open board's cards from Deck's API, at most every 30 seconds
	 * (5 when a card is missing), and draw again once they are in.
	 *
	 * @param {string} board The board id.
	 * @param {boolean} force A card is missing: load after 5 seconds already.
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function loadDeckCards(board, force) {
		var now = Date.now()
		if (deckCards.loading || typeof fetch !== 'function' || (deckCards.board === board && now - deckCards.at < (force ? 5000 : 30000))) {
			return
		}
		deckCards.loading = true
		var token = (window.OC && window.OC.requestToken) || (document.head && document.head.getAttribute('data-requesttoken')) || ''
		var webroot = (window.OC && window.OC.webroot) || ''
		fetch(webroot + '/ocs/v2.php/apps/deck/api/v1.0/stacks/' + encodeURIComponent(board) + '?format=json', {
			credentials: 'same-origin',
			headers: { 'OCS-APIRequest': 'true', requesttoken: token, Accept: 'application/json' },
		}).then(function (response) {
			return response.ok ? response.json() : null
		}).then(function (body) {
			var stacks = body && body.ocs ? body.ocs.data : body
			var byId = {}
			;(Array.isArray(stacks) ? stacks : []).forEach(function (stack) {
				;(stack.cards || []).forEach(function (card) {
					byId[String(card.id)] = cardLines(card.description)
				})
			})
			deckCards = { board: board, at: Date.now(), byId: byId, loading: false }
			drawDeckCards()
		}).catch(function () {
			deckCards.loading = false
			deckCards.at = Date.now()
			deckCards.board = board
		})
	}

	/**
	 * Give Deck's cards their number beside the type, their due date as a
	 * short date, and the board title the number of open cards (TbBoard).
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function drawDeckCards() {
		var root = document.documentElement
		var locale = (root.getAttribute('data-locale') || root.lang || 'en').replace('_', '-')
		var route = /\/apps\/deck\/(?:#\/)?board\/(\d+)/.exec(window.location.pathname + window.location.hash)
		var board = route !== null ? route[1] : null
		if (board !== null && deckCards.board !== board) {
			loadDeckCards(board, false)
		}
		document.querySelectorAll('.app-deck .card').forEach(function (card) {
			var labels = card.querySelector('.card-labels .labels')
			var number = card.querySelector('.cardid span')
			if (labels !== null) {
				setData(labels, 'data-thematiq-number', number !== null ? (number.textContent || '').trim() || null : null)
			}
			var id = number !== null ? (number.textContent || '').replace(/[^0-9]/g, '') : ''
			var lines = deckCards.byId[id] || null
			if (id !== '' && lines === null && board !== null) {
				loadDeckCards(board, true)
			}
			var upper = card.querySelector('.card-upper')
			var controls = card.querySelector('.card-controls')
			if (upper !== null) {
				setData(upper, 'data-thematiq-project', lines !== null ? lines.project : null)
			}
			if (controls !== null) {
				var from = lines !== null && lines.mail !== null
					? (typeof t === 'function' ? t('thematiq', 'From mail: {sender}', { sender: lines.mail }) : 'From mail: ' + lines.mail)
					: null
				setData(controls, 'data-thematiq-mail', from)
			}
			var high = false
			card.querySelectorAll('.card-labels .labels li').forEach(function (label) {
				var priority = PRIORITY_LABELS.indexOf((label.textContent || '').trim().toLowerCase()) >= 0
				high = high || priority
				setData(label, 'data-thematiq-priority', priority ? 'high' : null)
			})
			var head = card.querySelector('.card-labels')
			if (head !== null) {
				setData(head, 'data-thematiq-priority', high ? (typeof t === 'function' ? t('thematiq', 'Priority high') : 'Priority high') : null)
			}
			var due = card.querySelector('.duedate .due')
			if (due !== null) {
				setData(due, 'data-thematiq-due', shortDate(due.getAttribute('name'), locale))
			}
		})
		var title = document.querySelector('.app-deck .controls .board-title')
		if (title !== null) {
			var open = document.querySelectorAll('.app-deck .stack:not(.stack--done-column) .dnd-container > .smooth-dnd-draggable-wrapper:not(.smooth-dnd-ghost)').length
			var words = typeof n === 'function' ? n('thematiq', '%n open card', '%n open cards', open) : open + ' open cards'
			setData(title, 'data-thematiq-open', document.querySelector('.app-deck .stack') !== null ? words : null)
		}
	}

	/**
	 * Set the words, mark the input in use, and keep Calendar's day names short.
	 *
	 * @spec openspec/specs/workplace-layout/spec.md#requirement-the-light-layout-draws-mail-calendar-and-deck-as-the-boards
	 */
	function thematiqWorkplaceAppsMain() {
		var root = document.documentElement
		if (getComputedStyle(root).getPropertyValue('--thematiq-workplace-apps').trim() !== '1') {
			return
		}

		if (typeof t === 'function') {
			root.style.setProperty('--thematiq-mail-empty-title', cssString(t('thematiq', 'Select a conversation')))
			root.style.setProperty('--thematiq-calendar-new-event', cssString(t('thematiq', 'New event')))
		}

		document.addEventListener('pointerdown', function () {
			root.setAttribute(POINTER, '')
		}, true)
		document.addEventListener('keydown', function () {
			root.removeAttribute(POINTER)
		}, true)

		var calendar = document.querySelector('#content.app-calendar, #content-vue.app-calendar') !== null
		var deck = document.querySelector('#content.app-deck, #content-vue.app-deck') !== null
		if (!calendar && !deck) {
			return
		}
		var redraw = calendar ? shortenDays : drawDeckCards
		redraw()
		if (typeof MutationObserver === 'function') {
			var queued = false
			// One watcher per page, even when the script is evaluated again.
			if (window.thematiqWorkplaceAppsObserver) {
				window.thematiqWorkplaceAppsObserver.disconnect()
			}
			window.thematiqWorkplaceAppsObserver = new MutationObserver(function () {
				if (queued) {
					return
				}
				queued = true
				window.requestAnimationFrame(function () {
					queued = false
					redraw()
				})
			})
			var watch = { childList: true, subtree: true, characterData: true }
			if (deck) {
				// Deck writes a changed due date into the badge's name attribute.
				watch.attributes = true
				watch.attributeFilter = ['name']
			}
			window.thematiqWorkplaceAppsObserver.observe(document.body, watch)
		}
	}
})()
