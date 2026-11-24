/**
 * Scheduled switches (the TqWissels board), the panel of the Theming page at
 * #switches. js/admin.js plans, lists and cancels the switches and sends what
 * it loaded as `thematiq:scheduled-switches`; this script writes the counts in
 * the header and above the table, and the background-job mode as a pill.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqSwitchesPanel() {
	var loaded = null

	/**
	 * A translated string of the app.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] The placeholders.
	 * @return {string} The text in the reader's language.
	 */
	function tr(text, vars) {
		if (typeof window.t === 'function') {
			return window.t('thematiq', text, vars, undefined, { escape: false })
		}
		return Object.keys(vars || {}).reduce(function (acc, key) {
			return acc.replace('{' + key + '}', vars[key])
		}, text)
	}

	/**
	 * The switches of the last load.
	 *
	 * @return {Array<object>} The switches.
	 */
	function switches() {
		return loaded !== null && Array.isArray(loaded.switches) === true ? loaded.switches : []
	}

	/**
	 * The header line: how many are scheduled and when the next starts.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var planned = switches().filter(function (entry) {
			return (entry.status || 'planned') === 'planned'
		})
		if (planned.length === 0) {
			return tr('Nothing scheduled')
		}
		var next = planned.map(function (entry) {
			return entry.startAt
		}).sort()[0]
		var day = new Date(next).toLocaleDateString([], { day: 'numeric', month: 'long', year: 'numeric' })
		return tr('{count} scheduled, next on {day}', { count: planned.length, day: day })
	}

	/**
	 * The line above the table: how many are scheduled and how many failed.
	 *
	 * @return {string} The line.
	 */
	function summary() {
		var count = { planned: 0, failed: 0 }
		switches().forEach(function (entry) {
			var status = entry.status || 'planned'
			if (count[status] !== undefined) {
				count[status]++
			}
		})
		return tr('{planned} scheduled, {failed} failed. A running switch sits at the top; a failed one shows the reason.', count)
	}

	/**
	 * Write the counts and the background-job mode.
	 *
	 * @return {void}
	 */
	function render() {
		var lead = document.querySelector('.thematiq-switches-panel__summary')
		if (lead !== null) {
			lead.textContent = summary()
		}
		var mode = document.querySelector('.thematiq-switches-panel__mode')
		var status = loaded !== null && loaded.status ? loaded.status : {}
		if (mode !== null) {
			var cron = status.cronMode || ''
			mode.textContent = cron === 'ajax' ? 'AJAX' : (cron === 'webcron' ? 'Webcron' : (cron === '' ? '' : 'Cron'))
			mode.hidden = cron === ''
			mode.classList.toggle('thematiq-contrast-panel__pill--pass', cron !== '' && cron !== 'ajax')
			mode.classList.toggle('thematiq-contrast-panel__pill--fail', cron === 'ajax')
		}
		if (window.ThematiqPanels !== undefined && window.ThematiqPanels.current() === 'switches') {
			var intro = document.querySelector('#thematiq-page-header .thematiq-page-header__intro')
			if (intro !== null) {
				intro.textContent = line()
			}
		}
	}

	/**
	 * The header button: to the form's first field.
	 *
	 * @return {Array<HTMLElement>} The button.
	 */
	function actions() {
		var plan = document.createElement('button')
		plan.type = 'button'
		plan.className = 'thematiq-page-header__action thematiq-page-header__action--primary'
		plan.textContent = tr('Plan a switch')
		plan.addEventListener('click', function () {
			var field = document.getElementById('nldesign-scheduled-set')
			if (field !== null) {
				field.focus()
			}
		})
		return [plan]
	}

	document.addEventListener('thematiq:scheduled-switches', function (event) {
		loaded = event.detail || null
		render()
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('switches', {
			title: tr('Scheduled switches'),
			intro: line,
			actions: actions,
			onOpen: render,
		})
	}
})()
