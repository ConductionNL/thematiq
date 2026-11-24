/**
 * Dark variant (the TqDonker board), the panel of the Theming page at #dark.
 * It reads the active set's light token file and its generated dark file,
 * draws the set in light and in dark, lists the main colours light beside
 * dark with their contrast on the dark background, and sums that up in the
 * contrast check. The dark variant's switch is lent from the overview.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqDarkPanel() {
	/** The colours the table lists: label, token, and what the colour is measured as. */
	var ROWS = [
		['Background', '--nldesign-color-background', 'surface'],
		['Top bar', '--nldesign-color-header-background', 'surface'],
		['Line', '--nldesign-color-border', 'surface'],
		['Line dark', '--nldesign-color-border-dark', 'border'],
		['Text', '--nldesign-color-text', 'text'],
		['Grey text', '--nldesign-color-text-muted', 'text'],
		['Primary', '--nldesign-color-primary', 'text'],
		['Primary on hover', '--nldesign-color-primary-hover', 'text'],
		['Link', '--nldesign-color-link', 'text'],
		['Text on button', '--nldesign-color-button-primary-text', 'button'],
		['Accent', '--nldesign-color-accent', 'text'],
		['Accent text', '--nldesign-color-accent-text', 'text'],
		['Accent soft', '--nldesign-color-accent-light', 'surface'],
		['Warning', '--nldesign-color-warning', 'text'],
		['Success', '--nldesign-color-success', 'text'],
	]
	/** Nextcloud's own variable for a colour a light set file may leave out. */
	var NEXTCLOUD = {
		'--nldesign-color-background': '--color-main-background',
		'--nldesign-color-text': '--color-main-text',
		'--nldesign-color-border': '--color-border',
	}
	var state = { light: {}, dark: {}, version: '', rows: [] }

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
	 * The custom properties a token file declares, the first value of each.
	 *
	 * @param {string} css The stylesheet.
	 * @return {Object<string,string>} Token => value.
	 */
	function declarations(css) {
		var found = {}
		var pattern = /(--[a-z0-9-]+)\s*:\s*([^;}]+)/gi
		var match
		while ((match = pattern.exec(css)) !== null) {
			if (found[match[1]] === undefined) {
				found[match[1]] = match[2].trim()
			}
		}
		return found
	}

	/**
	 * A colour as six hex digits in lower case, or null.
	 *
	 * @param {string} value A CSS colour.
	 * @return {string|null} The colour.
	 */
	function hex(value) {
		var text = String(value || '')
			.trim()
			.toLowerCase()
		var short = /^#([0-9a-f])([0-9a-f])([0-9a-f])$/.exec(text)
		if (short !== null) {
			return (
				'#' + short[1] + short[1] + short[2] + short[2] + short[3] + short[3]
			)
		}
		return /^#[0-9a-f]{6}$/.test(text) ? text : null
	}

	/**
	 * The WCAG contrast ratio of two colours.
	 *
	 * @param {string} a A colour as #rrggbb.
	 * @param {string} b A colour as #rrggbb.
	 * @return {number} The ratio.
	 */
	function ratio(a, b) {
		var lum = function (colour) {
			var c = [1, 3, 5].map(function (i) {
				var v = parseInt(colour.substr(i, 2), 16) / 255
				return v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
			})
			return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]
		}
		var x = lum(a)
		var y = lum(b)
		return (Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)
	}

	/**
	 * A ratio the way the boards write it: one decimal, two just under 4.5.
	 *
	 * @param {number} value The ratio.
	 * @return {string} The ratio.
	 */
	function formatRatio(value) {
		var digits = value >= 4.4 && value < 4.5 ? 2 : 1
		return tr('{ratio} : 1', { ratio: value.toFixed(digits) })
	}

	/**
	 * The active token set: its id and name.
	 *
	 * @return {{id: string, name: string}} The set.
	 */
	function activeSet() {
		var select = document.getElementById('nldesign-token-set-select')
		if (select === null) {
			return { id: '', name: '' }
		}
		var option = select.options[select.selectedIndex]
		return {
			id: select.value,
			name: option !== undefined ? option.textContent.trim() : select.value,
		}
	}

	/**
	 * Measure each row: its light and dark value and its verdict.
	 *
	 * @return {void}
	 */
	function measure() {
		var page = getComputedStyle(document.body)
		var ground = hex(state.dark['--nldesign-color-background']) || '#171a20'
		var button = hex(state.dark['--nldesign-color-button-primary-background'])
		state.rows = ROWS.map(function (row) {
			var light = hex(
				state.light[row[1]]
					|| page.getPropertyValue(row[1])
					|| page.getPropertyValue(NEXTCLOUD[row[1]] || ''),
			)
			var dark = hex(state.dark[row[1]])
			var entry = {
				label: tr(row[0]),
				name: row[1],
				kind: row[2],
				light: light,
				dark: dark,
				pass: true,
				verdict: tr('Surface'),
				ratio: null,
			}
			if (dark === null || row[2] === 'surface') {
				entry.verdict = dark === null ? '–' : tr('Surface')
				return entry
			}
			var against = row[2] === 'button' && button !== null ? button : ground
			entry.ratio = ratio(dark, against)
			entry.pass = entry.ratio >= (row[2] === 'border' ? 3 : 4.5)
			entry.verdict =
				row[2] === 'button'
					? tr('{ratio} on button', { ratio: formatRatio(entry.ratio) })
					: formatRatio(entry.ratio)
			entry.unchanged = light !== null && light === dark
			return entry
		})
	}

	/**
	 * A swatch and a value.
	 *
	 * @param {string|null} value The colour.
	 * @return {HTMLElement} The cell.
	 */
	function colourCell(value) {
		var cell = el('span', 'thematiq-dark-panel__colour')
		cell.setAttribute('role', 'cell')
		var chip = el('span', 'thematiq-dark-panel__chip')
		if (value !== null) {
			chip.style.background = value
		}
		cell.appendChild(chip)
		cell.appendChild(
			document.createTextNode(value !== null ? value.toUpperCase() : '–'),
		)
		return cell
	}

	/**
	 * Draw the table of tokens, light beside dark.
	 *
	 * @param {HTMLElement} panel The panel.
	 * @return {void}
	 */
	function drawRows(panel) {
		var only = panel.querySelector('.thematiq-dark-panel__only-warnings')
		var rows = panel.querySelector('.thematiq-dark-panel__rows')
		var head = el(
			'div',
			'thematiq-dark-panel__row thematiq-dark-panel__row--head',
		)
		head.setAttribute('role', 'row')
		;[tr('Token'), tr('Light'), tr('Dark'), tr('Contrast')].forEach(
			function (text) {
				var cell = el('span', '', text)
				cell.setAttribute('role', 'columnheader')
				head.appendChild(cell)
			},
		)
		var lines = [head]
		state.rows.forEach(function (entry) {
			if (only !== null && only.checked === true && entry.pass === true) {
				return
			}
			var row = el('div', 'thematiq-dark-panel__row')
			row.setAttribute('role', 'row')
			var name = el('span', 'thematiq-dark-panel__name')
			name.setAttribute('role', 'cell')
			name.appendChild(el('span', 'thematiq-dark-panel__label', entry.label))
			name.appendChild(el('code', 'thematiq-dark-panel__token', entry.name))
			if (entry.unchanged === true) {
				name.appendChild(
					el(
						'span',
						'thematiq-dark-panel__note',
						tr('Not converted by the generator'),
					),
				)
			}
			row.appendChild(name)
			row.appendChild(colourCell(entry.light))
			row.appendChild(colourCell(entry.dark))
			var verdict = el('span', 'thematiq-dark-panel__verdict')
			verdict.setAttribute('role', 'cell')
			verdict.appendChild(
				el(
					'span',
					'thematiq-dark-panel__pill thematiq-dark-panel__pill--'
						+ (entry.ratio === null
							? 'surface'
							: entry.pass
								? 'pass'
								: 'warn'),
					entry.verdict,
				),
			)
			row.appendChild(verdict)
			lines.push(row)
		})
		rows.replaceChildren.apply(rows, lines)
	}

	/**
	 * The measured rows that are text or border colours.
	 *
	 * @return {Array<object>} The rows.
	 */
	function measured() {
		return state.rows.filter(function (entry) {
			return entry.ratio !== null
		})
	}

	/**
	 * Draw the contrast check: the score, the bar and what falls short.
	 *
	 * @param {HTMLElement} panel The panel.
	 * @return {void}
	 */
	function drawCheck(panel) {
		var rows = measured()
		var passing = rows.filter(function (entry) {
			return entry.pass
		})
		panel.querySelector('.thematiq-dark-panel__score').textContent = tr(
			'{passing} of {total}',
			{ passing: passing.length, total: rows.length },
		)
		panel.querySelector('.thematiq-dark-panel__bar span').style.width =
			(rows.length > 0 ? Math.round((passing.length / rows.length) * 100) : 0)
			+ '%'
		var list = panel.querySelector('.thematiq-dark-panel__problems')
		list.replaceChildren.apply(
			list,
			rows
				.filter(function (entry) {
					return entry.pass === false
				})
				.map(function (entry) {
					var item = el('li', '')
					item.appendChild(el('strong', '', entry.label))
					item.appendChild(
						document.createTextNode(
							' '
								+ (entry.unchanged === true
									? tr(
											'is not converted and stays {value}: {ratio}.',
											{
												value: entry.dark.toUpperCase(),
												ratio: formatRatio(entry.ratio),
											},
										)
									: tr('comes out at {ratio}.', {
											ratio: formatRatio(entry.ratio),
										})),
						),
					)
					return item
				}),
		)
	}

	/**
	 * A small drawing of the set in light or dark.
	 *
	 * @param {string} title The title.
	 * @param {string} note The file it is drawn from.
	 * @param {Object<string,string>} tokens The tokens.
	 * @param {boolean} dark Whether it draws the dark variant.
	 * @return {HTMLElement} The card.
	 */
	function preview(title, note, tokens, dark) {
		var page = getComputedStyle(document.body)
		var pick = function (name, fallback) {
			return tokens[name] || page.getPropertyValue(name).trim() || fallback
		}
		var card = el(
			'section',
			'thematiq-card thematiq-panel__card thematiq-dark-panel__preview',
		)
		var head = el('div', 'thematiq-panel__card-head')
		head.appendChild(
			el(
				'h2',
				'thematiq-panel__card-title thematiq-dark-panel__aside-title',
				title,
			),
		)
		head.appendChild(el('span', 'thematiq-panel__fact-hint', note))
		card.appendChild(head)
		var frame = el('div', 'thematiq-dark-panel__frame')
		frame.style.setProperty(
			'--frame-ground',
			dark === true
				? pick('--nldesign-color-background', 'transparent')
				: page
						.getPropertyValue(
							'--nldesign-component-content-surface-background-color',
						)
						.trim() || 'transparent',
		)
		frame.style.setProperty(
			'--frame-ink',
			pick('--nldesign-color-text', 'inherit'),
		)
		frame.style.setProperty(
			'--frame-bar',
			pick('--nldesign-color-header-background', 'transparent'),
		)
		frame.style.setProperty(
			'--frame-line',
			pick('--nldesign-color-border', 'transparent'),
		)
		frame.style.setProperty(
			'--frame-card',
			dark === true
				? pick('--nldesign-color-background-dark', 'transparent')
				: page.getPropertyValue('--color-main-background').trim()
						|| 'transparent',
		)
		frame.style.setProperty(
			'--frame-soft',
			pick('--nldesign-color-accent-light', 'transparent'),
		)
		frame.style.setProperty(
			'--frame-soft-ink',
			pick('--nldesign-color-accent-text', 'inherit'),
		)
		frame.style.setProperty(
			'--frame-muted',
			pick('--nldesign-color-text-muted', 'inherit'),
		)
		frame.style.setProperty(
			'--frame-link',
			pick('--nldesign-color-link', 'inherit'),
		)
		frame.style.setProperty(
			'--frame-button',
			pick('--nldesign-color-button-primary-background', 'transparent'),
		)
		frame.style.setProperty(
			'--frame-button-ink',
			pick('--nldesign-color-button-primary-text', 'inherit'),
		)
		var bar = el('div', 'thematiq-dark-panel__bar-row')
		bar.appendChild(el('span', 'thematiq-preview__logo'))
		bar.appendChild(el('strong', '', tr('Files')))
		bar.appendChild(el('span', 'thematiq-preview__spacer'))
		frame.appendChild(bar)
		var body = el('div', 'thematiq-dark-panel__body')
		var nav = el('div', 'thematiq-dark-panel__nav')
		nav.appendChild(el('span', 'thematiq-dark-panel__active', tr('All files')))
		nav.appendChild(el('span', '', tr('Recent')))
		nav.appendChild(el('span', '', tr('Shared')))
		var main = el('div', 'thematiq-dark-panel__main')
		var item = el('div', 'thematiq-dark-panel__item')
		item.appendChild(el('strong', '', tr('Annual report 2026')))
		item.appendChild(
			el('span', 'thematiq-dark-panel__muted', tr('2.4 MB · today')),
		)
		item.appendChild(
			el('span', 'thematiq-dark-panel__link', tr('View documents')),
		)
		var actions = el('div', 'thematiq-dark-panel__actions')
		actions.appendChild(el('span', 'thematiq-dark-panel__button', tr('Save')))
		actions.appendChild(
			el('span', 'thematiq-dark-panel__accent', tr('Deadline passed')),
		)
		main.appendChild(item)
		main.appendChild(actions)
		body.appendChild(nav)
		body.appendChild(main)
		frame.appendChild(body)
		card.appendChild(frame)
		return card
	}

	/**
	 * Draw the panel from the files read.
	 *
	 * @return {void}
	 */
	function draw() {
		var panel = document.querySelector('section[data-thematiq-panel="dark"]')
		if (panel === null) {
			return
		}
		measure()
		var set = activeSet()
		var previews = panel.querySelector('.thematiq-dark-panel__previews')
		previews.replaceChildren(
			preview(tr('Light'), 'tokens/' + set.id + '.css', state.light, false),
			preview(tr('Dark'), 'tokens/dark/' + set.id + '.css', state.dark, true),
		)
		var ground = hex(state.dark['--nldesign-color-background'])
		panel.querySelector('.thematiq-dark-panel__measured').textContent = tr(
			'Own dark values are set per token in the token editor. Contrast measured on the dark background {colour}.',
			{ colour: ground !== null ? ground.toUpperCase() : '–' },
		)
		drawRows(panel)
		drawCheck(panel)
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'dark'
		) {
			var intro = document.querySelector(
				'#thematiq-page-header .thematiq-page-header__intro',
			)
			if (intro !== null) {
				intro.replaceChildren.apply(intro, introNodes())
			}
		}
	}

	/**
	 * The line under the breadcrumb: whether the variant is generated, its warnings and the generator.
	 *
	 * @return {Array<Node>} The line.
	 */
	function introNodes() {
		var nodes = []
		var generated = Object.keys(state.dark).length > 0
		nodes.push(
			el(
				'span',
				'thematiq-dark-panel__status thematiq-dark-panel__status--'
					+ (generated ? 'generated' : 'none'),
				generated ? tr('Generated') : tr('Not generated'),
			),
		)
		var warnings = measured().filter(function (entry) {
			return entry.pass === false
		}).length
		if (warnings > 0) {
			nodes.push(
				el(
					'span',
					'thematiq-dark-panel__status thematiq-dark-panel__status--warn',
					warnings === 1
						? tr('1 contrast warning')
						: tr('{count} contrast warnings', { count: warnings }),
				),
			)
		}
		if (state.version !== '') {
			nodes.push(
				el(
					'span',
					'thematiq-panel__fact-hint',
					tr('Generator version {version}', { version: state.version }),
				),
			)
		}
		return nodes
	}

	/**
	 * Read the active set's light and dark token files, then draw.
	 *
	 * @return {Promise<void>} Done.
	 */
	function load() {
		var set = activeSet()
		var file = function (path) {
			return fetch(window.OC.linkTo('thematiq', path))
				.then(function (r) {
					return r.ok ? r.text() : ''
				})
				.catch(function () {
					return ''
				})
		}
		return Promise.all([
			file('css/tokens/' + set.id + '.css'),
			file('css/tokens/dark/' + set.id + '.css'),
		]).then(function (files) {
			state.light = declarations(files[0])
			state.dark = declarations(files[1])
			var version = /DarkPaletteService v(\d+)/.exec(files[1])
			state.version = version !== null ? version[1] : ''
			draw()
		})
	}

	var only = document.querySelector(
		'section[data-thematiq-panel="dark"] .thematiq-dark-panel__only-warnings',
	)
	if (only !== null) {
		only.addEventListener('change', function () {
			drawRows(document.querySelector('section[data-thematiq-panel="dark"]'))
		})
	}
	document.addEventListener('thematiq:theme-applied', function () {
		if (
			window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'dark'
		) {
			load()
		}
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('dark', {
			title: tr('Dark variant'),
			crumb: tr('Dark'),
			crumbs: function () {
				return [
					{ text: tr('Token sets'), href: '#' },
					{ text: activeSet().name },
				]
			},
			intro: introNodes,
			onOpen: function () {
				load()
			},
		})
	}
})()
