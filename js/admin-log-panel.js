/**
 * Log (the TqLogboek board), the panel of the Theming page at #log.
 * js/admin.js fills the table and announces it with `thematiq:audit-rendered`;
 * this script writes the counts, the header line and the header's download
 * button, which presses the table's own download.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqLogPanel() {
	var summary = { count: 0, versions: 0, since: null }

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
	 * The header line: how many changes, since when.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var since = summary.since !== null ? new Date(summary.since) : null
		if (since === null || isNaN(since.getTime())) {
			return summary.count === 1
				? tr('1 change')
				: tr('{count} changes', { count: summary.count })
		}
		var date = since.toLocaleDateString(
			document.documentElement.lang || undefined,
			{ day: 'numeric', month: 'short', year: 'numeric' },
		)
		return summary.count === 1
			? tr('1 change since {date}', { date: date })
			: tr('{count} changes since {date}', {
					count: summary.count,
					date: date,
				})
	}

	/**
	 * Write the counts into the panel, and the line into the header when open.
	 *
	 * @return {void}
	 */
	function draw() {
		var count = document.querySelector('.thematiq-log-panel__count')
		if (count !== null) {
			count.textContent = tr('{shown} of {total} changes', {
				shown: summary.count,
				total: summary.count,
			})
		}
		var versions = document.querySelector('.thematiq-log-panel__versions')
		if (versions !== null) {
			versions.textContent = tr('{count} of 50', { count: summary.versions })
		}
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'log'
		) {
			var intro = document.querySelector(
				'#thematiq-page-header .thematiq-page-header__intro',
			)
			if (intro !== null) {
				intro.textContent = line()
			}
		}
	}

	/**
	 * The header's download button.
	 *
	 * @return {Array<HTMLElement>} The button.
	 */
	function actions() {
		var button = document.createElement('button')
		button.type = 'button'
		button.className = 'thematiq-page-header__action'
		button.textContent = tr('Download full log')
		button.addEventListener('click', function () {
			var download = document.getElementById('nldesign-audit-download-btn')
			if (download !== null) {
				download.click()
			}
		})
		return [button]
	}

	document.addEventListener('thematiq:audit-rendered', function (event) {
		summary = Object.assign(
			{ count: 0, versions: 0, since: null },
			event.detail || {},
		)
		draw()
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('log', {
			title: tr('Log'),
			intro: line,
			actions: actions,
			onOpen: draw,
		})
	}
})()
