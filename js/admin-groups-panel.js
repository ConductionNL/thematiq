/**
 * House style per group (the TqGroepen board), the panel of the Theming page
 * at #groups. js/admin.js loads, edits and saves the mapping and sends the rows
 * as `thematiq:group-theming` whenever it draws them; this script writes the
 * counts in the header, above the delegated card and in the aside, names the
 * default token set and gives the header its Add connection button.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqGroupsPanel() {
	var rows = []

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
	 * How many rows are delegated.
	 *
	 * @return {number} The count.
	 */
	function delegated() {
		return rows.filter(function (row) {
			return row.delegated === true
		}).length
	}

	/**
	 * The header line.
	 *
	 * @return {string} The line.
	 */
	function line() {
		return tr('{linked} groups linked, {delegated} with delegated choice', {
			linked: rows.length,
			delegated: delegated(),
		})
	}

	/**
	 * Write the counts and the default token set.
	 *
	 * @return {void}
	 */
	function render() {
		var set = function (selector, text) {
			var el = document.querySelector(selector)
			if (el !== null) {
				el.textContent = text
			}
		}
		set(
			'.thematiq-groups-panel__delegated',
			tr('{delegated} of {linked} groups choose themselves', {
				linked: rows.length,
				delegated: delegated(),
			}),
		)
		set('.thematiq-groups-panel__linked', String(rows.length))
		set('.thematiq-groups-panel__delegated-count', String(delegated()))
		var select = document.getElementById('nldesign-token-set-select')
		if (select !== null && select.selectedIndex >= 0) {
			set(
				'.thematiq-groups-panel__default',
				select.options[select.selectedIndex].textContent.trim(),
			)
		}
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'groups'
		) {
			set('#thematiq-page-header .thematiq-page-header__intro', line())
		}
	}

	/**
	 * The header button: a new row, as the card's own button adds it.
	 *
	 * @return {Array<HTMLElement>} The button.
	 */
	function actions() {
		var add = document.createElement('button')
		add.type = 'button'
		add.className =
			'thematiq-page-header__action thematiq-page-header__action--primary'
		add.textContent = tr('Add connection')
		add.addEventListener('click', function () {
			var own = document.getElementById('nldesign-group-theming-add')
			if (own !== null) {
				own.click()
			}
		})
		return [add]
	}

	document.addEventListener('thematiq:group-theming', function (event) {
		rows =
			event.detail && Array.isArray(event.detail.rows) === true
				? event.detail.rows
				: []
		render()
	})
	document.addEventListener('change', function (event) {
		if (
			event.target !== null
			&& event.target.getAttribute('data-field') === 'delegated'
		) {
			render()
		}
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('groups', {
			title: tr('House style per group'),
			crumb: tr('Groups'),
			intro: line,
			actions: actions,
			onOpen: render,
		})
	}
})()
