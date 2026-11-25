/**
 * Contrast report (the TqContrastrapport board), the panel of the Theming page
 * at #contrast. It reads the report the export endpoint gives as JSON and draws
 * the totals, the colour pairs with an example, their ratio, standard and
 * verdict, and what the report is about. The header's download buttons press
 * the report links js/admin.js wires.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqContrastPanel() {
	var report = null

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
	 * An element with a class and, optionally, text.
	 *
	 * @param {string} tag The tag.
	 * @param {string} className The class.
	 * @param {string} [text] The text.
	 * @return {HTMLElement} The element.
	 */
	function el(tag, className, text) {
		var node = document.createElement(tag)
		if (className) {
			node.className = className
		}
		if (text !== undefined) {
			node.textContent = text
		}
		return node
	}

	/**
	 * A ratio in the reader's way of writing numbers.
	 *
	 * @param {number} value The ratio.
	 * @param {number} digits The decimals.
	 * @return {string} The ratio.
	 */
	function ratio(value, digits) {
		return (
			Number(value).toLocaleString(
				document.documentElement.lang || undefined,
				{
					minimumFractionDigits: digits,
					maximumFractionDigits: digits,
				},
			) + ':1'
		)
	}

	/**
	 * A colour cell: swatch, token and value.
	 *
	 * @param {string} token The token.
	 * @param {string|null} value The colour.
	 * @return {HTMLElement} The cell.
	 */
	function colourCell(token, value) {
		var cell = el('td', '')
		var wrap = el('span', 'thematiq-contrast-panel__colour')
		var swatch = el('span', 'thematiq-contrast-panel__swatch')
		if (value) {
			swatch.style.background = value
		}
		var text = el('span', 'thematiq-contrast-panel__colour-text')
		text.appendChild(el('code', '', token))
		text.appendChild(
			el(
				'span',
				'thematiq-contrast-panel__value',
				value
					? /^#[0-9a-f]{3,8}$/i.test(value)
						? String(value).toUpperCase()
						: String(value)
					: '–',
			),
		)
		wrap.appendChild(swatch)
		wrap.appendChild(text)
		cell.appendChild(wrap)
		return cell
	}

	/**
	 * A cell with one element in it.
	 *
	 * @param {HTMLElement} child The element.
	 * @return {HTMLElement} The cell.
	 */
	function cellWith(child) {
		var cell = el('td', '')
		cell.appendChild(child)
		return cell
	}

	/**
	 * Draw the colour pairs.
	 *
	 * @param {HTMLElement} panel The panel.
	 * @return {void}
	 */
	function drawRows(panel) {
		var body = panel.querySelector('.thematiq-contrast-panel__rows')
		body.replaceChildren.apply(
			body,
			(report.pairs || []).map(function (pair) {
				var row = el('tr', '')
				row.appendChild(colourCell(pair.foreground, pair.foregroundValue))
				row.appendChild(colourCell(pair.background, pair.backgroundValue))
				var example = el(
					'span',
					'thematiq-contrast-panel__example',
					tr('Text Aa'),
				)
				if (pair.foregroundValue && pair.backgroundValue) {
					example.style.color = pair.foregroundValue
					example.style.background = pair.backgroundValue
				}
				row.appendChild(cellWith(example))
				row.appendChild(
					cellWith(
						el(
							'strong',
							'thematiq-contrast-panel__ratio',
							typeof pair.ratio === 'number'
								? ratio(pair.ratio, 2)
								: '–',
						),
					),
				)
				row.appendChild(
					cellWith(
						el(
							'span',
							'',
							pair.basis === 'ui-component'
								? tr('{ratio} part', {
										ratio: ratio(pair.threshold, 1),
									})
								: tr('{ratio} text', {
										ratio: ratio(pair.threshold, 1),
									}),
						),
					),
				)
				var verdict =
					pair.verdict === 'pass'
						? tr('Meets')
						: pair.verdict === 'fail'
							? tr('Too low')
							: tr('Cannot be assessed')
				var pill = el(
					'span',
					'thematiq-contrast-panel__pill thematiq-contrast-panel__pill--'
						+ pair.verdict,
					verdict,
				)
				if (pair.note) {
					pill.title = pair.note
				}
				row.appendChild(cellWith(pill))
				return row
			}),
		)
	}

	/**
	 * Draw the four totals.
	 *
	 * @param {HTMLElement} panel The panel.
	 * @return {void}
	 */
	function drawTiles(panel) {
		var summary = report.summary || {}
		var total = (report.pairs || []).length
		var tiles = panel.querySelector('.thematiq-contrast-panel__tiles')
		tiles.replaceChildren.apply(
			tiles,
			[
				[total, tr('pairs measured'), 'total'],
				[summary.passed || 0, tr('pass WCAG 2.1 AA'), 'pass'],
				[summary.failed || 0, tr('too low'), 'fail'],
				[summary.unevaluated || 0, tr('cannot be assessed'), 'unevaluated'],
			].map(function (tile) {
				var card = el(
					'div',
					'thematiq-card thematiq-contrast-panel__tile thematiq-contrast-panel__tile--'
						+ tile[2],
				)
				card.appendChild(
					el('span', 'thematiq-contrast-panel__number', String(tile[0])),
				)
				card.appendChild(
					el('span', 'thematiq-contrast-panel__label', tile[1]),
				)
				return card
			}),
		)
	}

	/**
	 * Draw what the report is about.
	 *
	 * @param {HTMLElement} panel The panel.
	 * @return {void}
	 */
	function drawAbout(panel) {
		var meta = report.metadata || {}
		var made = meta.generatedAt ? new Date(meta.generatedAt) : null
		var rows = [
			[tr('Token set'), meta.tokenSet ? meta.tokenSet.name : ''],
			[
				tr('Design system'),
				{ nldesign: 'NL Design System', lasuite: 'La Suite' }[
					meta.designSystem
				]
					|| meta.designSystem
					|| '',
			],
			[
				tr('Made'),
				made !== null && !isNaN(made.getTime())
					? made.toLocaleString(
							document.documentElement.lang || undefined,
							{ dateStyle: 'medium', timeStyle: 'short' },
						)
					: '',
			],
		]
		var about = panel.querySelector('.thematiq-contrast-panel__about')
		about.replaceChildren.apply(
			about,
			rows.map(function (row) {
				var line = el('div', '')
				line.appendChild(el('span', '', row[0]))
				line.appendChild(el('strong', '', String(row[1])))
				return line
			}),
		)
	}

	/**
	 * The header line: how many pairs pass, for which set.
	 *
	 * @return {string} The line.
	 */
	function line() {
		if (report === null) {
			return ''
		}
		var meta = report.metadata || {}
		return tr('{passed} of {total} pairs pass, token set {name}', {
			passed: (report.summary || {}).passed || 0,
			total: (report.pairs || []).length,
			name: meta.tokenSet ? meta.tokenSet.name : '',
		})
	}

	/**
	 * Draw the panel from the report.
	 *
	 * @return {void}
	 */
	function draw() {
		var panel = document.querySelector('section[data-thematiq-panel="contrast"]')
		if (panel === null || report === null) {
			return
		}
		drawTiles(panel)
		drawRows(panel)
		drawAbout(panel)
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'contrast'
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
	 * Read the report as JSON, then draw.
	 *
	 * @return {Promise<void>} Done.
	 */
	function load() {
		return fetch(
			window.OC.generateUrl('/apps/thematiq/settings/compliance-report')
				+ '?format=json',
			{
				headers: { requesttoken: window.OC.requestToken },
			},
		)
			.then(function (r) {
				return r.json()
			})
			.then(function (data) {
				report = data || null
				draw()
			})
			.catch(function () {
				var rows = document.querySelector('.thematiq-contrast-panel__rows')
				if (rows !== null) {
					rows.replaceChildren(el('tr', ''))
					rows.firstChild.appendChild(
						el(
							'td',
							'settings-hint',
							tr('The contrast report could not be loaded.'),
						),
					)
					rows.firstChild.firstChild.setAttribute('colspan', '6')
				}
			})
	}

	/**
	 * The header's download buttons.
	 *
	 * @return {Array<HTMLElement>} The buttons.
	 */
	function actions() {
		return [
			['nldesign-compliance-report-markdown', tr('Download as Markdown')],
			['nldesign-compliance-report-json', tr('Download as JSON')],
		].map(function (entry) {
			var button = el('button', 'thematiq-page-header__action', entry[1])
			button.type = 'button'
			button.addEventListener('click', function () {
				var link = document.getElementById(entry[0])
				if (link !== null) {
					link.click()
				}
			})
			return button
		})
	}

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('contrast', {
			title: tr('Contrast report'),
			intro: line,
			actions: actions,
			onOpen: load,
		})
	}
})()
