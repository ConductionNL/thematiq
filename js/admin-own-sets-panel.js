/**
 * Own token sets (the TqEigenSets board), the panel of the Theming page at
 * #own-sets. js/admin.js uploads, creates, lists and deletes the sets and
 * sends the list as `thematiq:custom-sets`; this script writes the counts,
 * the scheduled switches in the Used column and the contrast warnings in the
 * aside, and gives the header its Create house style button.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqOwnSetsPanel() {
	var sets = null
	var switches = []

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
	 * How many token sets the page offers in all.
	 *
	 * @return {number} The count.
	 */
	function total() {
		var select = document.getElementById('nldesign-token-set-select')
		return select !== null ? select.options.length : 0
	}

	/**
	 * The header line.
	 *
	 * @return {string} The line.
	 */
	function line() {
		var own = sets !== null ? sets.length : 0
		return tr('{own} own sets, {total} token sets in total', {
			own: own,
			total: total(),
		})
	}

	/**
	 * Add the scheduled switches to the Used column.
	 *
	 * @return {void}
	 */
	function markScheduled() {
		document
			.querySelectorAll('.nldesign-custom-set-row[data-set-id]')
			.forEach(function (row) {
				var cell = row.querySelector('.nldesign-custom-set-used')
				if (cell === null) {
					return
				}
				cell.querySelectorAll('.thematiq-own-sets-panel__scheduled').forEach(
					function (old) {
						old.remove()
					},
				)
				switches
					.filter(function (entry) {
						return (
							entry.tokenSet === row.getAttribute('data-set-id')
							&& (entry.status || 'planned') === 'planned'
						)
					})
					.forEach(function (entry) {
						var item = document.createElement('span')
						item.className = 'thematiq-own-sets-panel__scheduled'
						item.textContent = tr('Scheduled for {date}', {
							date: new Date(entry.startAt).toLocaleDateString([], {
								day: 'numeric',
								month: 'short',
								year: 'numeric',
							}),
						})
						cell.appendChild(item)
					})
			})
	}

	/**
	 * Write the counts and the contrast warnings.
	 *
	 * @return {void}
	 */
	function render() {
		var list = sets || []
		var count = document.querySelector('.thematiq-own-sets-panel__count')
		if (count !== null) {
			count.textContent =
				list.length === 1
					? tr('1 own token set')
					: tr('{count} own token sets', { count: list.length })
		}
		var warned = list.filter(function (set) {
			return (set.warnings || []).some(function (warning) {
				return warning && warning.kind !== 'incomplete'
			})
		})
		var card = document.querySelector('.thematiq-own-sets-panel__warnings')
		var text = document.querySelector('.thematiq-own-sets-panel__warning-text')
		if (card !== null && text !== null) {
			card.hidden = warned.length === 0
			text.textContent = tr(
				'{names}: a colour pair is below the WCAG AA threshold. The set works, but text on it can be hard to read.',
				{
					names: warned
						.map(function (set) {
							return set.name || set.id
						})
						.join(', '),
				},
			)
		}
		markScheduled()
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'own-sets'
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
	 * The header button: to the form that starts from colours.
	 *
	 * @return {Array<HTMLElement>} The button.
	 */
	function actions() {
		var create = document.createElement('button')
		create.type = 'button'
		create.className =
			'thematiq-page-header__action thematiq-page-header__action--primary'
		create.textContent = tr('Create house style')
		create.addEventListener('click', function () {
			var tab = document.getElementById('nldesign-create-tab-colours')
			if (tab !== null) {
				tab.click()
			}
			var name = document.getElementById('nldesign-brand-name')
			if (name !== null) {
				name.focus()
			}
		})
		return [create]
	}

	document.addEventListener('thematiq:custom-sets', function (event) {
		sets =
			event.detail && Array.isArray(event.detail.sets) === true
				? event.detail.sets
				: []
		render()
	})
	document.addEventListener('thematiq:scheduled-switches', function (event) {
		switches =
			event.detail && Array.isArray(event.detail.switches) === true
				? event.detail.switches
				: []
		markScheduled()
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('own-sets', {
			title: tr('Own token sets'),
			intro: line,
			actions: actions,
			onOpen: render,
		})
	}
})()
