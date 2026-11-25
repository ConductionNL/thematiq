/**
 * A token set from the inside (the TqTokenSet board), the panel of the Theming
 * page at #set/<id>, opened from a row of the Token sets table. It reads the
 * set's token file and its generated dark file, sorts the tokens into the
 * board's tabs (colours, typography, shape, components, website), measures each
 * colour against white, draws the set in light and dark, and names what the
 * set brings: its design system, layout, logo, dark variant and brand stripe.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V. <info@conduction.nl>
 *
 * @spec openspec/changes/admin-pages-as-the-tq-boards/specs/admin-settings/spec.md
 */
;(function thematiqTokenSetPanel() {
	var DESIGN_SYSTEMS = {
		nldesign: 'NL Design System',
		lasuite: 'La Suite',
		cunningham: 'Cunningham',
		'high-contrast': 'High contrast',
		'summer-breeze': 'Summer Breeze',
		none: 'Nextcloud',
	}

	/** The colours the Colours tab names, per group: label, token and what it is measured as. */
	var GROUPS = [
		[
			'Surface',
			[
				['Background', '--nldesign-color-background', 'surface'],
				['Top bar', '--nldesign-color-header-background', 'surface'],
				['Menu', '--nldesign-color-nav-background', 'surface'],
				['Hover', '--nldesign-color-background-hover', 'surface'],
				['Line', '--nldesign-color-border', 'surface'],
				['Line dark', '--nldesign-color-border-dark', 'border'],
			],
		],
		[
			'Ink',
			[
				['Text', '--nldesign-color-text', 'text'],
				['Grey text', '--nldesign-color-text-muted', 'text'],
				['Top bar text', '--nldesign-color-header-text', 'text'],
				['Link', '--nldesign-color-link', 'text'],
				['Link on hover', '--nldesign-color-link-hover', 'text'],
			],
		],
		[
			'Action',
			[
				['Primary', '--nldesign-color-primary', 'text'],
				['Text on primary', '--nldesign-color-primary-text', 'on-primary'],
				['Primary on hover', '--nldesign-color-primary-hover', 'text'],
				['Primary soft', '--nldesign-color-primary-light', 'surface'],
				['Focus', '--nldesign-color-focus', 'text'],
			],
		],
		[
			'Accent',
			[
				['Accent', '--nldesign-color-accent', 'text'],
				['Accent soft', '--nldesign-color-accent-light', 'surface'],
				['Accent text', '--nldesign-color-accent-text', 'text'],
				[
					'Selected menu item',
					'--nldesign-component-navigation-active-color',
					'text',
				],
			],
		],
		[
			'Status',
			[
				['Error', '--nldesign-color-error', 'text'],
				['Warning', '--nldesign-color-warning', 'text'],
				['Success', '--nldesign-color-success', 'text'],
				['Information', '--nldesign-color-info', 'text'],
			],
		],
	]

	var TABS = [
		['colours', 'Colours'],
		['typography', 'Typography'],
		['shape', 'Shape'],
		['components', 'Components'],
		['website', 'Website'],
	]

	var state = {
		id: '',
		tokens: {},
		dark: {},
		darkVersion: '',
		tab: 'colours',
		mode: 'light',
		query: '',
	}

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
	 * A value of the page's initial state.
	 *
	 * @param {string} key The key.
	 * @param {*} fallback The value without it.
	 * @return {*} The value.
	 */
	function initial(key, fallback) {
		try {
			return window.OCP.InitialState.loadState('thematiq', key)
		} catch (e) {
			return fallback
		}
	}

	/**
	 * The custom properties a token file declares, in order, the first value of each.
	 *
	 * @param {string} css The stylesheet.
	 * @return {Object<string,string>} Token => value.
	 */
	function declarations(css) {
		var found = {}
		var text = String(css || '').replace(/\/\*[\s\S]*?\*\//g, '')
		var pattern = /(--[a-z0-9_-]+)\s*:\s*([^;}]+)/gi
		var match
		while ((match = pattern.exec(text)) !== null) {
			if (found[match[1]] === undefined) {
				found[match[1]] = match[2].trim()
			}
		}
		return found
	}

	/**
	 * A value with the `var()` references the file itself declares filled in.
	 *
	 * @param {string} value The value.
	 * @param {Object<string,string>} tokens The file's tokens.
	 * @return {string} The value.
	 */
	function resolve(value, tokens) {
		var text = String(value || '')
		for (var depth = 0; depth < 8 && text.indexOf('var(') !== -1; depth++) {
			var next = text.replace(
				/var\(\s*(--[a-z0-9_-]+)\s*(?:,\s*([^()]*))?\)/gi,
				function (all, name, fallback) {
					if (tokens[name] !== undefined) {
						return tokens[name]
					}
					return fallback !== undefined ? fallback.trim() : all
				},
			)
			if (next === text) {
				break
			}
			text = next
		}
		return text
	}

	/**
	 * A colour as six hex digits in lower case, or null when the value is no plain colour.
	 *
	 * @param {string} value A CSS value.
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
		var long = /^#([0-9a-f]{6})([0-9a-f]{2})?$/.exec(text)
		if (long !== null) {
			return '#' + long[1]
		}
		var rgb = /^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)/.exec(text)
		if (rgb !== null) {
			return (
				'#'
				+ [rgb[1], rgb[2], rgb[3]]
					.map(function (part) {
						return (
							'0' + Math.min(255, parseInt(part, 10)).toString(16)
						).slice(-2)
					})
					.join('')
			)
		}
		if (text === 'white') {
			return '#ffffff'
		}
		return text === 'black' ? '#000000' : null
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
	 * The tab a token belongs to.
	 *
	 * @param {string} name The token.
	 * @param {string} value Its resolved value.
	 * @return {string} The tab.
	 */
	function tabOf(name, value) {
		if (/^--nldesign-(website|hero)-/.test(name)) {
			return 'website'
		}
		if (/^--nldesign-component-/.test(name)) {
			return 'components'
		}
		if (hex(value) !== null || /-colou?r(-|$)/.test(name)) {
			return 'colours'
		}
		if (/font|line-height|letter-spacing|text-transform/.test(name)) {
			return 'typography'
		}
		return 'shape'
	}

	/**
	 * A readable label for a token the board does not name.
	 *
	 * @param {string} name The token.
	 * @return {string} The label.
	 */
	function labelOf(name) {
		var words = name
			.replace(/^--/, '')
			.replace(/^.*?-colou?r-/, '')
			.replace(/^(nldesign|nl)-/, '')
			.replace(/-/g, ' ')
		return words.charAt(0).toUpperCase() + words.slice(1)
	}

	/**
	 * What a colour the board does not name is measured as.
	 *
	 * @param {string} name The token.
	 * @param {string} colour The colour.
	 * @return {string} surface, border or text.
	 */
	function kindOf(name, colour) {
		if (
			/text|ink|link|-color$|foreground/.test(name)
			&& !/background/.test(name)
		) {
			return 'text'
		}
		if (/border|line|outline/.test(name)) {
			return /dark|strong/.test(name) ? 'border' : 'surface'
		}
		return ratio(colour, '#ffffff') < 3 ? 'surface' : 'text'
	}

	/**
	 * One row of the tokens list: label, token, value, and the contrast pill for a colour.
	 *
	 * @param {string} label The label.
	 * @param {string} name The token.
	 * @param {string|null} kind What a colour is measured as, or null to guess.
	 * @param {Object<string,string>} tokens The set's tokens.
	 * @return {object} The row.
	 */
	function row(label, name, kind, tokens) {
		var value = resolve(tokens[name], tokens)
		var colour = hex(value)
		var entry = {
			label: label,
			name: name,
			value: value,
			colour: colour,
			kind: null,
			ratio: null,
			pass: true,
			verdict: '',
		}
		if (colour === null) {
			return entry
		}
		entry.kind = kind || kindOf(name, colour)
		if (entry.kind === 'on-primary') {
			var primary =
				hex(resolve(tokens['--nldesign-color-primary'], tokens)) || '#ffffff'
			entry.ratio = ratio(colour, primary)
			entry.pass = entry.ratio >= 4.5
			entry.verdict = tr('{ratio} on action', {
				ratio: formatRatio(entry.ratio),
			})
			return entry
		}
		entry.ratio = ratio(colour, '#ffffff')
		if (entry.kind === 'surface') {
			entry.verdict = tr('Surface, {ratio}', {
				ratio: formatRatio(entry.ratio),
			})
			return entry
		}
		entry.pass = entry.ratio >= (entry.kind === 'border' ? 3 : 4.5)
		entry.verdict = formatRatio(entry.ratio)
		return entry
	}

	/**
	 * The set's tokens sorted into the board's tabs; the Colours tab in its groups.
	 *
	 * @param {Object<string,string>} tokens The set's tokens.
	 * @return {Object<string,Array<{title: string, rows: Array<object>}>>} Tab => groups.
	 */
	function classify(tokens) {
		var named = {}
		var tabs = {
			colours: [],
			typography: [],
			shape: [],
			components: [],
			website: [],
		}
		GROUPS.forEach(function (group) {
			var rows = group[1]
				.filter(function (item) {
					return tokens[item[1]] !== undefined
				})
				.map(function (item) {
					named[item[1]] = true
					return row(tr(item[0]), item[1], item[2], tokens)
				})
			if (rows.length > 0) {
				tabs.colours.push({ title: tr(group[0]), rows: rows })
			}
		})
		var rest = {
			colours: [],
			typography: [],
			shape: [],
			components: [],
			website: [],
		}
		Object.keys(tokens).forEach(function (name) {
			if (named[name] === true) {
				return
			}
			rest[tabOf(name, resolve(tokens[name], tokens))].push(
				row(labelOf(name), name, null, tokens),
			)
		})
		if (rest.colours.length > 0) {
			tabs.colours.push({ title: tr('Other colours'), rows: rest.colours })
		}
		;['typography', 'shape', 'components', 'website'].forEach(function (tab) {
			if (rest[tab].length > 0) {
				tabs[tab].push({ title: '', rows: rest[tab] })
			}
		})
		return tabs
	}

	/**
	 * How many tokens a tab holds.
	 *
	 * @param {Array<object>} groups The tab's groups.
	 * @return {number} The count.
	 */
	function count(groups) {
		return groups.reduce(function (sum, group) {
			return sum + group.rows.length
		}, 0)
	}

	/**
	 * Whether a row matches the search.
	 *
	 * @param {object} entry The row.
	 * @param {string} query The search, in lower case.
	 * @return {boolean} Whether it matches.
	 */
	function matches(entry, query) {
		return (
			query === ''
			|| (entry.label + ' ' + entry.name + ' ' + entry.value)
				.toLowerCase()
				.indexOf(query) !== -1
		)
	}

	/**
	 * The text colours of the Colours tab that fall short on white.
	 *
	 * @param {Array<object>} groups The Colours tab.
	 * @return {Array<object>} The rows.
	 */
	function shortfalls(groups) {
		var rows = []
		groups.forEach(function (group) {
			group.rows.forEach(function (entry) {
				if (entry.ratio !== null && entry.pass === false) {
					rows.push(entry)
				}
			})
		})
		return rows
	}

	/**
	 * The set with this id from the page's list of sets.
	 *
	 * @param {string} id The set.
	 * @return {object} The set, or a stand-in with only its id.
	 */
	function setById(id) {
		var sets = initial('tokenSets', [])
		var found =
			Array.isArray(sets) === true
				? sets.filter(function (set) {
						return set.id === id
					})[0]
				: undefined
		return found || { id: id, name: id }
	}

	/**
	 * The id of the active set.
	 *
	 * @return {string} The set.
	 */
	function activeId() {
		return state.active !== undefined
			? state.active
			: initial('currentTokenSet', '')
	}

	/**
	 * The first family of the set's font.
	 *
	 * @param {Object<string,string>} tokens The set's tokens.
	 * @return {string} The family, or an empty string.
	 */
	function fontOf(tokens) {
		var family = resolve(
			tokens['--nldesign-font-family']
				|| tokens['--nldesign-typography-sans-serif-font-family']
				|| '',
			tokens,
		)
		return family.split(',')[0].replace(/["']/g, '').trim()
	}

	/**
	 * The token reference of a set.
	 *
	 * @param {string} id The set.
	 * @param {boolean} download Whether to download it.
	 * @return {string} The URL.
	 */
	function referenceUrl(id, download) {
		var path =
			'/apps/thematiq/api/token-sets/' + encodeURIComponent(id) + '/reference'
		var base =
			window.OC && typeof window.OC.generateUrl === 'function'
				? window.OC.generateUrl(path)
				: path
		return base + '?format=' + (download ? 'md&download=1' : 'html')
	}

	/**
	 * The panel.
	 *
	 * @return {HTMLElement|null} The panel.
	 */
	function panel() {
		return document.querySelector('section[data-thematiq-panel="set"]')
	}

	/**
	 * Draw the tabs with their counts.
	 *
	 * @param {HTMLElement} root The panel.
	 * @param {object} tabs The classified tokens.
	 * @return {void}
	 */
	function drawTabs(root, tabs) {
		var list = root.querySelector('.thematiq-set-panel__tabs')
		list.replaceChildren.apply(
			list,
			TABS.map(function (tab) {
				var button = el('button', 'thematiq-set-panel__tab')
				button.type = 'button'
				button.id = 'thematiq-set-tab-' + tab[0]
				button.dataset.tab = tab[0]
				button.setAttribute('role', 'tab')
				button.setAttribute('aria-controls', 'thematiq-set-tokens')
				var selected = state.tab === tab[0]
				button.setAttribute('aria-selected', selected ? 'true' : 'false')
				button.tabIndex = selected ? 0 : -1
				button.appendChild(document.createTextNode(tr(tab[1])))
				var n = count(tabs[tab[0]])
				if (n > 0 && tab[0] !== 'typography' && tab[0] !== 'shape') {
					button.appendChild(document.createTextNode(' '))
					button.appendChild(
						el('span', 'thematiq-set-panel__tab-count', String(n)),
					)
				}
				button.setAttribute(
					'aria-label',
					n === 1
						? tr('{tab} 1 token', { tab: tr(tab[1]) })
						: tr('{tab} {count} tokens', { tab: tr(tab[1]), count: n }),
				)
				return button
			}),
		)
		root.querySelector('.thematiq-set-panel__tokens').setAttribute(
			'aria-labelledby',
			'thematiq-set-tab-' + state.tab,
		)
	}

	/**
	 * Draw the open tab's rows, as far as the search allows.
	 *
	 * @param {HTMLElement} root The panel.
	 * @param {object} tabs The classified tokens.
	 * @return {void}
	 */
	function drawRows(root, tabs) {
		var query = state.query.trim().toLowerCase()
		var list = root.querySelector('.thematiq-set-panel__groups')
		var nodes = []
		tabs[state.tab].forEach(function (group) {
			var rows = group.rows.filter(function (entry) {
				return matches(entry, query)
			})
			if (rows.length === 0) {
				return
			}
			var block = el('div', 'thematiq-set-panel__group')
			if (group.title !== '') {
				block.appendChild(
					el('h3', 'thematiq-set-panel__group-title', group.title),
				)
			}
			var items = el('div', 'thematiq-set-panel__group-rows')
			items.setAttribute('role', 'list')
			block.appendChild(items)
			rows.forEach(function (entry) {
				var line = el('div', 'thematiq-set-panel__row')
				line.setAttribute('role', 'listitem')
				var chip = el('span', 'thematiq-set-panel__swatch')
				chip.setAttribute('aria-hidden', 'true')
				if (entry.colour !== null) {
					chip.style.background = entry.colour
				} else {
					chip.classList.add('thematiq-set-panel__swatch--none')
				}
				line.appendChild(chip)
				var name = el('span', 'thematiq-set-panel__name')
				name.appendChild(
					el('span', 'thematiq-set-panel__label', entry.label),
				)
				name.appendChild(el('code', 'thematiq-set-panel__token', entry.name))
				line.appendChild(name)
				line.appendChild(
					el(
						'code',
						'thematiq-set-panel__value',
						entry.colour !== null && entry.value.charAt(0) === '#'
							? entry.value.toUpperCase()
							: entry.value,
					),
				)
				var verdict = el('span', 'thematiq-set-panel__verdict')
				if (entry.ratio !== null) {
					verdict.appendChild(
						el(
							'span',
							'thematiq-set-panel__pill thematiq-set-panel__pill--'
								+ (entry.kind === 'surface'
									? 'surface'
									: entry.pass
										? 'pass'
										: 'warn'),
							entry.verdict,
						),
					)
				}
				line.appendChild(verdict)
				items.appendChild(line)
			})
			nodes.push(block)
		})
		if (nodes.length === 0) {
			nodes.push(
				el(
					'p',
					'thematiq-panel__fact-hint thematiq-set-panel__empty',
					query !== ''
						? tr('No token matches the search.')
						: tr('This set brings no tokens of this kind.'),
				),
			)
		}
		list.replaceChildren.apply(list, nodes)
		var short = shortfalls(tabs.colours)
		var summary = root.querySelector('.thematiq-set-panel__summary')
		summary.classList.toggle(
			'thematiq-set-panel__summary--warn',
			short.length > 0,
		)
		summary.textContent =
			short.length === 0
				? tr('All text colours reach 4.5 : 1 on white')
				: short.length === 1
					? tr('1 colour falls short on white')
					: tr('{count} colours fall short on white', {
							count: short.length,
						})
	}

	/**
	 * Draw the sample in the set's light or dark tokens.
	 *
	 * @param {HTMLElement} root The panel.
	 * @return {void}
	 */
	function drawPreview(root) {
		var dark = state.mode === 'dark' && Object.keys(state.dark).length > 0
		var tokens = dark
			? Object.assign({}, state.tokens, state.dark)
			: state.tokens
		var pick = function (names, fallback) {
			for (var i = 0; i < names.length; i++) {
				var value = hex(resolve(tokens[names[i]], tokens))
				if (value !== null) {
					return value
				}
			}
			return fallback
		}
		var frame = root.querySelector('.thematiq-set-panel__frame')
		var vars = {
			'--frame-ground': pick(
				dark
					? ['--nldesign-color-background']
					: [
							'--nldesign-component-content-surface-background-color',
							'--nldesign-color-background',
						],
				dark ? '#171a20' : '#f5f6f8',
			),
			'--frame-card': pick(
				dark
					? [
							'--nldesign-color-background-dark',
							'--nldesign-color-background',
						]
					: ['--nldesign-color-background'],
				dark ? '#22262e' : '#ffffff',
			),
			'--frame-bar': pick(
				['--nldesign-color-header-background'],
				dark ? '#22262e' : '#ffffff',
			),
			'--frame-bar-ink': pick(
				['--nldesign-color-header-text', '--nldesign-color-text'],
				dark ? '#e2e3e4' : '#1b1c1d',
			),
			'--frame-ink': pick(
				['--nldesign-color-text'],
				dark ? '#e2e3e4' : '#1b1c1d',
			),
			'--frame-muted': pick(['--nldesign-color-text-muted'], '#5e6168'),
			'--frame-line': pick(['--nldesign-color-border'], '#e4e6ea'),
			'--frame-link': pick(
				['--nldesign-color-link', '--nldesign-color-primary'],
				'#3669a5',
			),
			'--frame-button': pick(
				[
					'--nldesign-color-button-primary-background',
					'--nldesign-color-primary',
				],
				'#3669a5',
			),
			'--frame-button-ink': pick(
				[
					'--nldesign-color-button-primary-text',
					'--nldesign-color-primary-text',
				],
				'#ffffff',
			),
			'--frame-active': pick(
				['--nldesign-color-accent-light', '--nldesign-color-primary-light'],
				'#eaf0f7',
			),
			'--frame-active-ink': pick(
				[
					'--nldesign-component-navigation-active-color',
					'--nldesign-color-accent-text',
					'--nldesign-color-primary',
				],
				'#234a78',
			),
			'--frame-info': pick(
				[
					'--nldesign-component-status-badge-info-background-color',
					'--nldesign-color-primary-light',
				],
				'#eaf0f7',
			),
			'--frame-info-ink': pick(
				[
					'--nldesign-component-status-badge-info-color',
					'--nldesign-color-primary-hover',
				],
				'#234a78',
			),
			'--frame-alert': pick(
				[
					'--nldesign-color-accent-light',
					'--nldesign-component-status-badge-error-background-color',
				],
				'#fcedec',
			),
			'--frame-alert-ink': pick(
				['--nldesign-color-accent-text', '--nldesign-color-error'],
				'#a30000',
			),
		}
		Object.keys(vars).forEach(function (name) {
			frame.style.setProperty(name, vars[name])
		})
		root.querySelectorAll('.thematiq-set-panel__mode').forEach(
			function (button) {
				var pressed = button.dataset.mode === state.mode
				button.setAttribute('aria-pressed', pressed ? 'true' : 'false')
				button.disabled =
					button.dataset.mode === 'dark'
					&& Object.keys(state.dark).length === 0
			},
		)
	}

	/**
	 * Draw the brand stripe card, or hide it for a set without one.
	 *
	 * @param {HTMLElement} root The panel.
	 * @param {object} set The set.
	 * @return {void}
	 */
	function drawStripe(root, set) {
		var card = root.querySelector('.thematiq-set-panel__stripe')
		var colours = [1, 2, 3]
			.map(function (i) {
				return hex(
					resolve(
						state.tokens['--nldesign-brand-stripe-color-' + i],
						state.tokens,
					),
				)
			})
			.filter(function (colour) {
				return colour !== null
			})
		var layout = set.layout || {}
		card.hidden = colours.length === 0 || layout.brand_stripe === false
		if (card.hidden === true) {
			return
		}
		var ratios = colours.map(function (colour, i) {
			var value = parseFloat(
				state.tokens['--nldesign-brand-stripe-ratio-' + (i + 1)],
			)
			return isNaN(value) ? 1 : value
		})
		var bar = card.querySelector('.thematiq-set-panel__stripe-bar')
		bar.replaceChildren.apply(
			bar,
			colours.map(function (colour, i) {
				var part = el('span', '')
				part.style.background = colour
				part.style.flexGrow = String(ratios[i])
				return part
			}),
		)
		var height = resolve(
			state.tokens['--nldesign-brand-stripe-height'] || '',
			state.tokens,
		)
		card.querySelector('.thematiq-set-panel__stripe-line').textContent = tr(
			'{colours} in {ratios}, {height} high',
			{
				colours: colours
					.map(function (colour) {
						return colour.toUpperCase()
					})
					.join(', '),
				ratios: ratios.join(' : '),
				height: height || '–',
			},
		)
		var placement = layout.brand_stripe_placement || 'header-and-login'
		card.querySelector('.thematiq-set-panel__stripe-where').textContent =
			placement === 'login'
				? tr('Drawn on the sign-in card')
				: placement === 'header'
					? tr('Drawn under the top bar')
					: tr('Drawn under the top bar and on the sign-in card')
	}

	/**
	 * Draw the facts about the set and its links.
	 *
	 * @param {HTMLElement} root The panel.
	 * @param {object} set The set.
	 * @return {void}
	 */
	function drawAbout(root, set) {
		var layout = set.layout || {}
		var theming = set.theming || {}
		var facts = root.querySelector('.thematiq-set-panel__facts')
		var logo = theming.logo ? String(theming.logo).split('/').pop() : ''
		var rows = [
			[
				tr('Design system'),
				DESIGN_SYSTEMS[set.design_system] || set.design_system || '–',
			],
			[
				tr('Layout'),
				layout.workplace_layout === 'light'
					? layout.navigation_width
						? tr('Light workplace, menu {width} px', {
								width: layout.navigation_width,
							})
						: tr('Light workplace')
					: tr('Follows Nextcloud'),
			],
			[
				tr('Logo'),
				logo === ''
					? tr("None, Nextcloud's own")
					: theming.logo_dark
						? tr('{file} and a dark version', { file: logo })
						: logo,
			],
			[
				tr('Dark'),
				Object.keys(state.dark).length === 0
					? tr('Not generated')
					: state.darkVersion !== ''
						? tr('Generated, version {version}', {
								version: state.darkVersion,
							})
						: tr('Generated'),
			],
		]
		var nodes = []
		rows.forEach(function (fact) {
			nodes.push(el('dt', '', fact[0]))
			nodes.push(el('dd', '', fact[1]))
		})
		facts.replaceChildren.apply(facts, nodes)
		root.querySelector('.thematiq-set-panel__dark-link').hidden =
			set.id !== activeId() || Object.keys(state.dark).length === 0
		root.querySelector('.thematiq-set-panel__components-link').hidden =
			set.id !== activeId()
		root.querySelector('.thematiq-set-panel__download').href = referenceUrl(
			set.id,
			true,
		)
	}

	/**
	 * Draw the whole panel from the files read.
	 *
	 * @return {void}
	 */
	function draw() {
		var root = panel()
		if (root === null) {
			return
		}
		var set = setById(state.id)
		var tabs = classify(state.tokens)
		drawTabs(root, tabs)
		drawRows(root, tabs)
		drawPreview(root)
		drawStripe(root, set)
		drawAbout(root, set)
		var intro = document.querySelector(
			'#thematiq-page-header .thematiq-page-header__intro',
		)
		if (
			intro !== null
			&& window.ThematiqPanels !== undefined
			&& window.ThematiqPanels.current() === 'set'
		) {
			intro.replaceChildren.apply(intro, introNodes(state.id))
		}
	}

	/**
	 * The line under the breadcrumb: active, light and dark, and the set in one line.
	 *
	 * @param {string} id The set.
	 * @return {Array<Node>} The line.
	 */
	function introNodes(id) {
		var set = setById(id)
		var nodes = []
		if (id === activeId()) {
			nodes.push(
				el(
					'span',
					'thematiq-set-panel__status thematiq-set-panel__status--active',
					tr('Active'),
				),
			)
		}
		if (state.id === id && Object.keys(state.dark).length > 0) {
			nodes.push(
				el(
					'span',
					'thematiq-set-panel__status thematiq-set-panel__status--dark',
					tr('Light and dark'),
				),
			)
		}
		var parts = [DESIGN_SYSTEMS[set.design_system] || set.design_system || '']
		if (state.id === id) {
			var total = Object.keys(state.tokens).length
			parts.push(
				total === 1 ? tr('1 token') : tr('{count} tokens', { count: total }),
			)
			var font = fontOf(state.tokens)
			if (font !== '') {
				parts.push(font)
			}
		}
		nodes.push(
			el(
				'span',
				'thematiq-set-panel__line',
				parts
					.filter(function (part) {
						return part !== ''
					})
					.join(', '),
			),
		)
		return nodes
	}

	/**
	 * The header's buttons: More (the token reference and its download) and, for a set that
	 * is not active, Use this token set.
	 *
	 * @param {string} id The set.
	 * @return {Array<HTMLElement>} The buttons.
	 */
	function actions(id) {
		var wrap = window.ThematiqPanels.menu(tr('More'), [
			{
				text: tr('Open the token reference'),
				hint: tr('All tokens of this set on one page'),
				href: referenceUrl(id, false),
			},
			{
				text: tr('Download token overview'),
				hint: tr('All tokens of this set as Markdown'),
				href: referenceUrl(id, true),
			},
		])
		var buttons = [wrap]
		if (id !== activeId()) {
			var use = el(
				'button',
				'thematiq-page-header__action thematiq-page-header__action--primary thematiq-set-panel__use',
				tr('Use this token set'),
			)
			use.type = 'button'
			use.addEventListener('click', function () {
				var picker = document.getElementById('nldesign-token-set-select')
				if (picker === null) {
					return
				}
				window.location.hash = ''
				picker.value = id
				picker.dispatchEvent(new Event('change', { bubbles: true }))
			})
			buttons.push(use)
		}
		return buttons
	}

	/**
	 * Read the set's token file and its dark file, then draw.
	 *
	 * @param {string} id The set.
	 * @return {Promise<void>} Done.
	 */
	function load(id) {
		state.id = id
		state.tab = 'colours'
		state.query = ''
		state.mode = 'light'
		state.tokens = {}
		state.dark = {}
		state.darkVersion = ''
		var root = panel()
		if (root !== null) {
			root.querySelector('.thematiq-set-panel__search').value = ''
		}
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
			file('css/tokens/' + id + '.css'),
			file('css/tokens/dark/' + id + '.css'),
		]).then(function (files) {
			if (state.id !== id) {
				return
			}
			state.tokens = declarations(files[0])
			state.dark = declarations(files[1])
			var version = /DarkPaletteService v(\d+)/.exec(files[1])
			state.darkVersion = version !== null ? version[1] : ''
			draw()
		})
	}

	/**
	 * Wire the panel's own controls once.
	 *
	 * @param {HTMLElement} root The panel.
	 * @return {void}
	 */
	function wire(root) {
		var tabs = root.querySelector('.thematiq-set-panel__tabs')
		tabs.addEventListener('click', function (event) {
			var tab = event.target.closest('[role="tab"]')
			if (tab !== null) {
				state.tab = tab.dataset.tab
				draw()
				root.querySelector('#thematiq-set-tab-' + state.tab).focus()
			}
		})
		tabs.addEventListener('keydown', function (event) {
			if (event.key !== 'ArrowRight' && event.key !== 'ArrowLeft') {
				return
			}
			event.preventDefault()
			var at = TABS.map(function (tab) {
				return tab[0]
			}).indexOf(state.tab)
			state.tab =
				TABS[
					(at + (event.key === 'ArrowRight' ? 1 : TABS.length - 1))
						% TABS.length
				][0]
			draw()
			root.querySelector('#thematiq-set-tab-' + state.tab).focus()
		})
		root.querySelector('.thematiq-set-panel__search').addEventListener(
			'input',
			function (event) {
				state.query = event.target.value
				drawRows(root, classify(state.tokens))
			},
		)
		root.querySelectorAll('.thematiq-set-panel__mode').forEach(
			function (button) {
				button.addEventListener('click', function () {
					state.mode = button.dataset.mode
					drawPreview(root)
				})
			},
		)
	}

	if (typeof module !== 'undefined' && module.exports) {
		module.exports = {
			declarations: declarations,
			resolve: resolve,
			hex: hex,
			tabOf: tabOf,
			labelOf: labelOf,
			classify: classify,
			shortfalls: shortfalls,
			count: count,
		}
	}

	var root = panel()
	if (root !== null) {
		wire(root)
	}
	document.addEventListener('thematiq:theme-applied', function (event) {
		state.active =
			event.detail && event.detail.tokenSet
				? event.detail.tokenSet
				: state.active
	})

	if (window.ThematiqPanels !== undefined) {
		window.ThematiqPanels.register('set', {
			title: function (id) {
				return setById(id).name || id
			},
			crumb: function (id) {
				return id
			},
			crumbs: [{ text: tr('Token sets'), href: '#' }],
			intro: introNodes,
			actions: actions,
			onOpen: function (section, id) {
				load(id)
			},
		})
	}
})()
