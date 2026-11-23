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

		if (document.querySelector('#content.app-calendar, #content-vue.app-calendar') === null) {
			return
		}
		shortenDays()
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
					shortenDays()
				})
			})
			window.thematiqWorkplaceAppsObserver.observe(document.body, {
				childList: true,
				subtree: true,
				characterData: true,
			})
		}
	}
})()
