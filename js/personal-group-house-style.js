/**
 * House style of my groups: a group subadmin picks the house style of a
 * delegated group from the sets an administrator allows. One radio group per
 * group; a choice saves at once and members see it on their next page load.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/specs/per-group-theming/spec.md
 */
;(function thematiqMyGroupsInit() {
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', thematiqMyGroupsMain)
	} else {
		thematiqMyGroupsMain()
	}

	/**
	 * The contrast result of a set, in words.
	 *
	 * @param {string|null} level The WCAG level, or null.
	 * @return {string} The text.
	 */
	function contrastText(level) {
		if (level) {
			return t('thematiq', 'Contrast: WCAG {level}', { level: level })
		}
		return t('thematiq', 'Contrast: below WCAG AA')
	}

	/**
	 * Save a choice.
	 *
	 * @param {object} group The group.
	 * @param {string} setId The chosen set.
	 * @param {HTMLElement} feedback The status line.
	 * @return {Promise} The request.
	 */
	function save(group, setId, feedback) {
		feedback.textContent = ''
		return fetch(
			OC.generateUrl(
				'/apps/thematiq/api/my-groups/'
					+ encodeURIComponent(group.group)
					+ '/house-style',
			),
			{
				method: 'POST',
				headers: {
					requesttoken: OC.requestToken,
					'Content-Type': 'application/json',
				},
				body: JSON.stringify({ tokenSet: setId }),
			},
		)
			.then(function (res) {
				return res.json().then(function (body) {
					return { ok: res.ok, body: body }
				})
			})
			.then(function (result) {
				if (!result.ok) {
					checkActive(group)
					feedback.textContent =
						result.body.error
						|| t('thematiq', 'The house style could not be saved.')
					return
				}
				group.tokenSet = setId
				feedback.textContent = t(
					'thematiq',
					'Saved. Members of {group} see the new house style on their next page.',
					{ group: group.displayName },
					undefined,
					{ escape: false },
				)
			})
			.catch(function () {
				checkActive(group)
				feedback.textContent = t(
					'thematiq',
					'The house style could not be saved.',
				)
			})
	}

	/**
	 * Check the radio of the set that is active for a group again, after a
	 * refused save left the clicked one checked.
	 *
	 * @param {object} group The group.
	 */
	function checkActive(group) {
		var input = document.getElementById(
			'thematiq-my-group-' + group.group + '-' + group.tokenSet,
		)
		if (input !== null) {
			input.checked = true
		}
	}

	/**
	 * Render one group as a radio group.
	 *
	 * @param {object} group The group.
	 * @param {HTMLElement} feedback The status line.
	 * @return {HTMLElement} The fieldset.
	 */
	function renderGroup(group, feedback) {
		var fieldset = document.createElement('fieldset')
		fieldset.className = 'thematiq-my-group'
		var legend = document.createElement('legend')
		legend.textContent = group.displayName
		fieldset.appendChild(legend)
		group.allowedTokenSets.forEach(function (set) {
			var id = 'thematiq-my-group-' + group.group + '-' + set.id
			var row = document.createElement('div')
			row.className = 'thematiq-my-group-option'
			var input = document.createElement('input')
			input.type = 'radio'
			input.className = 'radio'
			input.name = 'thematiq-my-group-' + group.group
			input.id = id
			input.value = set.id
			input.checked = set.id === group.tokenSet
			input.addEventListener('change', function () {
				save(group, set.id, feedback)
			})
			var label = document.createElement('label')
			label.setAttribute('for', id)
			if (set.primaryColor) {
				var swatch = document.createElement('span')
				swatch.className = 'thematiq-my-group-swatch'
				swatch.setAttribute('aria-hidden', 'true')
				swatch.style.backgroundColor = set.primaryColor
				swatch.style.display = 'inline-block'
				swatch.style.width = '1em'
				swatch.style.height = '1em'
				swatch.style.marginInlineEnd = '0.5em'
				swatch.style.verticalAlign = 'middle'
				swatch.style.border = '1px solid var(--color-border-dark)'
				label.appendChild(swatch)
			}
			label.appendChild(document.createTextNode(set.name + ' '))
			var contrast = document.createElement('span')
			contrast.className = 'settings-hint'
			contrast.textContent = '(' + contrastText(set.wcagLevel) + ')'
			label.appendChild(contrast)
			row.appendChild(input)
			row.appendChild(label)
			fieldset.appendChild(row)
		})
		return fieldset
	}

	/**
	 * Load the groups and render them.
	 *
	 * @return {Promise|undefined} The request.
	 */
	function thematiqMyGroupsMain() {
		var list = document.getElementById('thematiq-my-groups-list')
		var feedback = document.getElementById('thematiq-my-groups-feedback')
		if (!list || !feedback || typeof OC === 'undefined') {
			return undefined
		}
		return fetch(OC.generateUrl('/apps/thematiq/api/my-groups/house-style'), {
			headers: { requesttoken: OC.requestToken },
		})
			.then(function (res) {
				return res.json()
			})
			.then(function (data) {
				list.textContent = ''
				var groups = (data && data.groups) || []
				if (groups.length === 0) {
					var empty = document.createElement('p')
					empty.className = 'settings-hint'
					empty.textContent = t(
						'thematiq',
						'None of your groups can choose its own house style.',
					)
					list.appendChild(empty)
					return
				}
				groups.forEach(function (group) {
					list.appendChild(renderGroup(group, feedback))
				})
			})
			.catch(function (err) {
				console.error('Error loading the house style of my groups:', err)
			})
	}

	window.ThematiqMyGroups = { init: thematiqMyGroupsMain }
})()
