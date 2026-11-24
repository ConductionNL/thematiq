/**
 * Website: lettering, as TqTheming draws it: each size the active set gives
 * the website, named with its size in pixels and drawn at 60 percent. The
 * sizes are read from the page, so they follow the set it wears.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqWebsiteLetteringInit() {
	var SCALE = 0.6

	var SIZES = [
		{
			label: 'Page title',
			sample: 'Waste and recycling',
			token: '--nldesign-website-heading-1-font-size',
			weight: 700,
		},
		{
			label: 'Heading 2',
			sample: 'When is it collected?',
			token: '--nldesign-website-heading-2-font-size',
			weight: 700,
		},
		{
			label: 'Heading 3',
			sample: 'Bulky waste',
			token: '--nldesign-website-heading-3-font-size',
			weight: 700,
		},
		{
			label: 'Lead',
			sample: 'Everything about collection days.',
			token: '--nldesign-website-lead-font-size',
			weight: 400,
		},
		{
			label: 'Body text',
			sample: 'Put the bin out before 7:30.',
			token: '--nldesign-website-content-font-size',
			weight: 400,
		},
	]

	/**
	 * Translate a string of this app.
	 *
	 * @param {string} text The English text.
	 * @param {object} [vars] Placeholders.
	 * @return {string} The translation.
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
	 * A size in pixels, or null when the value is not one.
	 *
	 * @param {string} value A CSS length.
	 * @return {number|null} The pixels.
	 */
	function pixels(value) {
		var match = /^(\d+(?:\.\d+)?)(px|rem)$/.exec(String(value).trim())
		if (match === null) {
			return null
		}
		return match[2] === 'rem' ? parseFloat(match[1]) * 16 : parseFloat(match[1])
	}

	/**
	 * Draw the rows into the list.
	 *
	 * @param {HTMLElement} list The list.
	 */
	function draw(list) {
		var style = getComputedStyle(document.body)
		list.textContent = ''
		SIZES.forEach(function (size) {
			var px = pixels(style.getPropertyValue(size.token))
			if (px === null) {
				return
			}
			var row = document.createElement('div')
			row.className = 'thematiq-lettering__row'
			var name = document.createElement('span')
			name.className = 'thematiq-lettering__name'
			name.textContent = tr('{label} {size}', {
				label: tr(size.label),
				size: Math.round(px),
			})
			var sample = document.createElement('span')
			sample.className = 'thematiq-lettering__sample'
			sample.style.fontSize = Math.round(px * SCALE) + 'px'
			sample.style.fontWeight = String(size.weight)
			sample.textContent = tr(size.sample)
			row.appendChild(name)
			row.appendChild(sample)
			list.appendChild(row)
		})
		var card = list.closest('section')
		if (card !== null) {
			card.hidden = list.childElementCount === 0
		}
	}

	/**
	 * The set the page wears, from the initial state.
	 *
	 * @return {string} The set id, or an empty string.
	 */
	function currentSet() {
		try {
			return window.OCP.InitialState.loadState('thematiq', 'currentTokenSet')
		} catch (e) {
			return ''
		}
	}

	/**
	 * Point the In the token set link at the token reference of a set.
	 *
	 * @param {HTMLAnchorElement|null} link The link.
	 * @param {string} id The set id.
	 */
	function pointLink(link, id) {
		if (link === null || !id) {
			return
		}
		var path =
			'/apps/thematiq/api/token-sets/' + encodeURIComponent(id) + '/reference'
		link.href =
			(window.OC && typeof OC.generateUrl === 'function'
				? OC.generateUrl(path)
				: path) + '?format=html'
	}

	/** Find the list and draw it, again when another set is applied. */
	function main() {
		var list = document.querySelector(
			'#thematiq-website-lettering .thematiq-lettering',
		)
		if (list === null) {
			return
		}
		var link = document.querySelector(
			'#thematiq-website-lettering .thematiq-lettering__link',
		)
		draw(list)
		pointLink(link, currentSet())
		document.addEventListener('thematiq:theme-applied', function (event) {
			draw(list)
			pointLink(link, event.detail && event.detail.tokenSet)
		})
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = { pixels: pixels }
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', main)
	} else {
		main()
	}
})()
