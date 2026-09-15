/**
 * Thematiq — component playground: the selector / stage / tokens instrument.
 *
 * SPDX-License-Identifier: EUPL-1.2
 * SPDX-FileCopyrightText: 2026 Conduction B.V.
 *
 * This is not a page. It is built INTO the admin panel's existing token editor
 * (`/settings/admin/theming`), in the place and the shape `js/admin-mock.js`
 * demonstrated: the editor's four tabs move above the preview and become the
 * selector, a row of component chips goes under them, the preview gains a third
 * stage next to its app and login views, and choosing a component filters the
 * editor down to the tokens that component actually reads.
 *
 * Three things follow from building on the editor rather than beside it:
 *
 *  - The token rows are the editor's OWN rows, cloned. An edit is written back
 *    into the original input and re-dispatched, so `admin.js`'s dirty tracking,
 *    its reset buttons and its Save keep working untouched. There is no second
 *    store, no second save path and no merge to get wrong.
 *  - The live recolour is scoped to the preview container, not the document.
 *    The stage sits inside it and inherits from it, so dragging a colour picker
 *    repaints the component being judged and leaves the settings page around it
 *    alone.
 *  - What each component reads is DATA (`js/playground/components.json`),
 *    published by `lib/Settings/Admin.php` over the initial-state channel.
 *    This file renders it and decides nothing about it.
 *
 * Dual-mode like `js/lib/layerSwap.js`: `module.exports` under Node, so the
 * pure selection logic can be unit-tested without a DOM, and
 * `window.ThematiqPlayground` in the browser, where it also boots itself. No
 * `import`/`export`, because this app has no bundler.
 *
 * @spec openspec/changes/component-playground/specs/component-playground/spec.md
 */
;(function (root, factory) {
	var api = factory()
	if (typeof module !== 'undefined' && module.exports) {
		module.exports = api
		return
	}

	root.ThematiqPlayground = api
	if (root.document) {
		if (root.document.readyState === 'loading') {
			root.document.addEventListener('DOMContentLoaded', api.boot)
		} else {
			api.boot()
		}
	}
})(typeof window !== 'undefined' ? window : this, function () {
	'use strict'

	/** The chip that means "no component: show the whole tab". */
	var FULL_VIEW = 'full-view'

	/** How close a tooltip may come to the edge of the stage, in pixels. */
	var EDGE = 8

	/**
	 * The states an admin produces by pointing, which the stage therefore does
	 * NOT draw a copy of.
	 *
	 * A frozen hover is a lie told in the most confusing possible place: it
	 * sits beside the live specimen looking like a second component, an admin
	 * cannot tell which of the two is the real one, and hovering the frozen one
	 * does nothing while hovering the other one does. The real pseudo-class is
	 * already wired on every specimen, so the honest move is to draw the
	 * component once and let it be hovered.
	 *
	 * `active` is deliberately absent: on a button it means :active, but on a
	 * sidebar it means the active tab, which is a persistent state an admin
	 * picks rather than one they hold the mouse down for.
	 */
	var POINTABLE = ['hover', 'focus']

	/**
	 * The Nextcloud majors the header specimen can be drawn as.
	 *
	 * The window `appinfo/info.xml` declares this app supports. 32 and 33 draw
	 * the same header — the app-menu rewrite landed in 34 — and both are listed
	 * anyway, because "my 33 will look like this" is the question an admin
	 * actually asks, and answering it with a version they did not name leaves
	 * them wondering whether it was understood.
	 */
	var HEADER_VERSIONS = [32, 33, 34]

	/**
	 * Whether a state is one the admin makes rather than one the stage draws.
	 *
	 * @param {Object} stateDef The state definition.
	 * @return {boolean} True when the admin produces it by pointing.
	 */
	function isPointable(stateDef) {
		return POINTABLE.indexOf(stateDef.id) !== -1
	}

	/**
	 * The states the stage draws a specimen for.
	 *
	 * @param {Object} component The inventory entry.
	 * @return {Array<Object>} The states that get drawn.
	 */
	function drawnStates(component) {
		return (component.states || []).filter(function (stateDef) {
			return isPointable(stateDef) === false
		})
	}

	/**
	 * The sets a specimen can be picked from: a row, the container that holds
	 * its siblings, and the class the picked one wears.
	 *
	 * Order matters only where one selector could match inside another; these
	 * do not overlap. The container is named rather than assumed to be the
	 * parent, because a table row's siblings live in `tbody` and a navigation
	 * entry's in the column, not in whatever element happens to wrap them.
	 */
	var PICKABLE = [
		{ row: '.nldesign-pg-tab', group: '.nldesign-pg-tabs', on: 'is-active' },
		{ row: '.nldesign-pg-nav-entry', group: '.nldesign-pg-nav', on: 'is-selected active' },
		{ row: '.nldesign-pg-listitem', group: '.nldesign-pg-list', on: 'is-selected active' },
		{ row: '.nldesign-pg-rec', group: '.app-content-list', on: 'is-selected' },
		{ row: '.nldesign-pg-table tbody tr', group: '.nldesign-pg-table tbody', on: 'is-selected' },
		{ row: '.nldesign-pg-crumb', group: '.nldesign-pg-crumbs', on: 'is-current' },
	]

	/**
	 * What the stage reports when each specimen button is pressed.
	 *
	 * Reported on the line below the stage rather than written into the
	 * button's own label — see say(). Dutch and hard-coded, like the specimen
	 * labels themselves: these belong to a drawing of a component, not to the
	 * interface an admin acts on, and running them through t() would put a
	 * translator to work on strings nobody reads for their meaning.
	 */
	var BUTTON_DONE = {
		primary: 'Opgeslagen',
		secondary: 'Geannuleerd',
		tertiary: 'Opties open',
		error: 'Verwijderd',
		success: 'Goedgekeurd',
	}

	/* ---------------------------------------------------------------- */
	/* Pure selection logic                                              */
	/* ---------------------------------------------------------------- */

	/**
	 * A label reduced to the form the URL hash carries.
	 *
	 * @param {string} label Any label.
	 * @return {string} Its slug.
	 */
	function slug(label) {
		return String(label)
			.toLowerCase()
			.replace(/[^a-z0-9]+/g, '-')
			.replace(/^-|-$/g, '')
	}

	/**
	 * The components of one editor tab, in inventory order — which is the order
	 * their chips appear in.
	 *
	 * @param {{components: Array<Object>}} inventory The component inventory.
	 * @param {string} tab An editor tab id.
	 * @return {Array<Object>} The components of that tab.
	 */
	function componentsFor(inventory, tab) {
		return ((inventory && inventory.components) || []).filter(
			function (component) {
				return component.tab === tab
			},
		)
	}

	/**
	 * One component by id.
	 *
	 * @param {{components: Array<Object>}} inventory The component inventory.
	 * @param {string} id A component id.
	 * @return {?Object} The component, or null.
	 */
	function componentById(inventory, id) {
		var found = null
		;((inventory && inventory.components) || []).forEach(function (component) {
			if (component.id === id && found === null) {
				found = component
			}
		})
		return found
	}

	/**
	 * The tokens a component reads, grouped under the state each one paints, in
	 * callout order. This is the order the filtered panel lists them in, so a
	 * row and the numbered marker on the stage always line up.
	 *
	 * @param {Object} component An inventory component.
	 * @return {Array<{state: Object, tokens: Array<Object>, fixed: Array<Object>}>} Rows per state.
	 */
	function rowsByState(component) {
		return (component.states || []).map(function (state) {
			return {
				state: state,
				tokens: (component.tokens || []).filter(function (token) {
					return token.callout === state.n
				}),
				fixed: (component.fixed || []).filter(function (entry) {
					return entry.callout === state.n
				}),
			}
		})
	}

	/**
	 * The selection encoded in a URL hash, when it names one this inventory has.
	 *
	 * @param {string} hash The location hash.
	 * @param {{components: Array<Object>, tabs: Array<Object>}} inventory The component inventory.
	 * @return {?{tab: string, component: string}} The selection, or null.
	 */
	function parseHash(hash, inventory) {
		var match = /^#preview=([a-z]+)\/([a-z0-9-]+)$/.exec(String(hash || ''))
		if (match === null) {
			return null
		}

		var tab = match[1]
		var known = ((inventory && inventory.tabs) || []).some(function (entry) {
			return entry.id === tab
		})
		if (known === false) {
			return null
		}

		if (match[2] === FULL_VIEW) {
			return { tab: tab, component: FULL_VIEW }
		}

		var component = componentById(inventory, match[2])
		if (component === null || component.tab !== tab) {
			return null
		}

		return { tab: tab, component: component.id }
	}

	/**
	 * The hash for a selection.
	 *
	 * @param {string} tab An editor tab id.
	 * @param {string} component A component id, or the full-view sentinel.
	 * @return {string} The hash, including its `#`.
	 */
	function hashFor(tab, component) {
		return '#preview=' + tab + '/' + component
	}

	/**
	 * The `--nldesign-*` values of the active set with the admin's current
	 * overrides folded in, as one flat `:root` block sorted by name — the shape
	 * `css/tokens/*.css` and the custom-set upload both accept.
	 *
	 * An override reaches the file through the `--nldesign-*` token that
	 * Thematiq's own `overrides.css` says the Nextcloud variable reads, so the
	 * mapping is the stylesheet's, not a second copy of it. An override with no
	 * such token cannot be expressed as a token set at all and is reported
	 * rather than dropped.
	 *
	 * @param {Object<string, string>} tokens The resolved --nldesign-* values of the active set.
	 * @param {Object<string, string>} overrides The admin's --color-* overrides.
	 * @param {Object<string, string>} sources Map of --color-* to the --nldesign-* token it reads.
	 * @return {{css: string, unexpressed: Array<string>}} The file, and the overrides no token could carry.
	 */
	function exportCss(tokens, overrides, sources) {
		var merged = {}
		Object.keys(tokens || {}).forEach(function (token) {
			merged[token] = tokens[token]
		})

		var unexpressed = []
		Object.keys(overrides || {}).forEach(function (name) {
			var token = (sources || {})[name]
			if (!token) {
				unexpressed.push(name)
				return
			}
			merged[token] = overrides[name]
		})

		var lines = Object.keys(merged)
			.sort()
			.map(function (token) {
				return '  ' + token + ': ' + String(merged[token]).trim() + ';'
			})

		return {
			css:
				'/* NL Design — custom token set. Generated from an admin upload. Do not edit manually. */\n'
				+ ':root {\n'
				+ lines.join('\n')
				+ '\n}\n',
			unexpressed: unexpressed,
		}
	}

	/* ---------------------------------------------------------------- */
	/* Boot                                                              */
	/* ---------------------------------------------------------------- */

	/**
	 * Wait for admin.js to render the token editor, then build the instrument
	 * into it.
	 *
	 * The editor is fetched and rendered asynchronously, so there is nothing to
	 * attach to at DOMContentLoaded. The Save button is the marker for "the
	 * editor is fully rendered", exactly as the presentation mock used it.
	 *
	 * @return {void}
	 */
	function boot() {
		var host = document.getElementById('nldesign-token-editor')
		if (host === null) {
			return
		}

		if (document.getElementById('nldesign-save-btn') !== null) {
			build()
			return
		}

		var observer = new MutationObserver(function () {
			if (document.getElementById('nldesign-save-btn') !== null) {
				observer.disconnect()
				build()
			}
		})
		observer.observe(host, { childList: true, subtree: true })
	}

	/**
	 * Read a server-provided value out of Nextcloud's initial state (ADR-004).
	 *
	 * @param {string} key The initial-state key.
	 * @param {*} fallback The value to use when the key is absent.
	 * @return {*} The decoded value, or the fallback.
	 */
	function loadState(key, fallback) {
		if (
			typeof OCP === 'undefined'
			|| !OCP.InitialState
			|| typeof OCP.InitialState.loadState !== 'function'
		) {
			return fallback
		}
		try {
			var value = OCP.InitialState.loadState('thematiq', key, fallback)
			return value === undefined || value === null ? fallback : value
		} catch (error) {
			console.error(
				'[thematiq] initial state "' + key + '" could not be read:',
				error,
			)
			return fallback
		}
	}

	/**
	 * Show a transient message without ever breaking the flow that raised it.
	 *
	 * @param {string} message The message.
	 * @return {void}
	 */
	function notify(message) {
		try {
			if (window.OCP && OCP.Toast && typeof OCP.Toast.message === 'function') {
				OCP.Toast.message(message)
				return
			}
		} catch (error) {
			// Fall through to the console.
		}
		console.info('[thematiq] ' + message)
	}

	/**
	 * Build an element.
	 *
	 * @param {string} tag The tag name.
	 * @param {string} [className] Its class.
	 * @param {string} [text] Its text content.
	 * @return {Element} The element.
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
	 * The computed value of a variable on an element.
	 *
	 * @param {Element} element The element to read from.
	 * @param {string} name The variable.
	 * @param {string} fallback The value to use when it resolves to nothing.
	 * @return {string} The computed value.
	 */
	function readVar(element, name, fallback) {
		var value = getComputedStyle(element).getPropertyValue(name).trim()
		return value !== '' ? value : fallback
	}

	/* ---------------------------------------------------------------- */
	/* The instrument                                                    */
	/* ---------------------------------------------------------------- */

	/**
	 * Build the selector, the stage and the filtered token panel into the
	 * editor that admin.js has just rendered.
	 *
	 * @return {void}
	 */
	function build() {
		var editor = document.querySelector('.nldesign-token-editor')
		var preview = document.getElementById('nldesign-preview')
		var tabs = editor !== null ? editor.querySelector('.nldesign-tabs') : null
		if (editor === null || preview === null || tabs === null) {
			return
		}

		var inventory = loadState('playgroundInventory', {
			tabs: [],
			components: [],
		})
		if ((inventory.components || []).length === 0) {
			return
		}

		var state = {
			inventory: inventory,
			reasons: loadState('playgroundReasons', {}),
			tokens: loadState('playgroundTokens', {}),
			sources: loadState('playgroundTokenSources', {}),
			// The version this instance runs, and the one the header specimen
			// is currently drawn as. They start equal: the version you are on
			// is the one you are asking about first.
			serverVersion: loadState('playgroundVersion', 0),
			headerVersion: headerMajor(loadState('playgroundVersion', 0)),
			editor: editor,
			preview: preview,
			tabs: tabs,
			tab: activeTab(tabs),
			component: FULL_VIEW,
		}

		// The selector: the editor's own tab strip, moved above the preview, with
		// the component chips under it. Moving rather than copying is what keeps
		// one set of tabs on the page — two strips that could disagree about
		// which tab is open would be worse than none.
		var selector = el('div', 'nldesign-pg-selector')
		selector.appendChild(tabs)
		state.chips = el('div', 'nldesign-pg-chips')
		state.chips.setAttribute('role', 'radiogroup')
		state.chips.setAttribute('aria-label', t('thematiq', 'Component'))
		selector.appendChild(state.chips)
		preview.parentNode.insertBefore(selector, preview)

		// The App / Login switch is absorbed by the tabs: the login tab shows the
		// login view, every other tab the app view.
		// The App / Login switch is absorbed by the tabs. Hiding it is left to
		// css/playground.css: `.nldesign-preview-switch` carries
		// `display: inline-flex`, which beats the `hidden` attribute outright.

		var head = preview.querySelector('.nldesign-preview-head')
		var title = head !== null ? head.querySelector('h3') : null
		state.crumb = el('span', 'nldesign-pg-crumb')
		if (title !== null) {
			title.appendChild(state.crumb)
		}

		// The third stage, next to the app and login views.
		state.stage = el('div', 'nldesign-preview-stage nldesign-pg-stage')
		state.stage.setAttribute('data-view', 'component')
		state.stage.hidden = true
		preview.appendChild(state.stage)
		bindStage(state.stage)

		// Download and Upload act on the overrides file, so they belong with
		// Save rather than in a header of their own; the token-set export joins
		// them, because it is the third thing an admin leaves this panel with.
		var actions = editor.querySelector('.nldesign-token-editor-actions')
		var saveBar = editor.querySelector('.nldesign-save-bar')
		var saveBtn = document.getElementById('nldesign-save-btn')
		if (actions !== null && saveBar !== null && saveBtn !== null) {
			actions.appendChild(exportButton(state))
			saveBar.insertBefore(actions, saveBtn)
		}
		state.saveBar = saveBar

		// admin.js deactivates tab buttons by querying its OWN container, and the
		// strip no longer lives there, so it can no longer do it: the panels still
		// switch (they did not move) but the buttons would all stay active. The
		// instrument keeps them in sync, and follows with the chips and the stage.
		tabs.querySelectorAll('.nldesign-tab-btn').forEach(function (button) {
			button.addEventListener('click', function () {
				setTab(state, button.getAttribute('data-tab'))
			})
		})

		var wanted = parseHash(window.location.hash, inventory) || {
			tab: 'content',
			component: FULL_VIEW,
		}
		var wantedBtn = tabs.querySelector(
			'.nldesign-tab-btn[data-tab="' + wanted.tab + '"]',
		)
		if (wantedBtn !== null) {
			wantedBtn.click()
		} else {
			setTab(state, state.tab)
		}
		if (wanted.component !== FULL_VIEW) {
			select(state, wanted.component)
		}
	}

	/**
	 * The tab the editor currently has open.
	 *
	 * @param {Element} tabs The tab strip.
	 * @return {string} The active tab id.
	 */
	function activeTab(tabs) {
		var active = tabs.querySelector('.nldesign-tab-btn.active')
		return active !== null ? active.getAttribute('data-tab') : 'login'
	}

	/**
	 * The label of a tab, taken from the button admin.js rendered, so the
	 * breadcrumb reads in the admin's own language without a second catalogue.
	 *
	 * @param {Object} state The instrument state.
	 * @param {string} tab A tab id.
	 * @return {string} Its label.
	 */
	function tabLabel(state, tab) {
		var button = state.tabs.querySelector(
			'.nldesign-tab-btn[data-tab="' + tab + '"]',
		)
		return button !== null ? button.textContent.trim() : tab
	}

	/**
	 * Switch tabs: sync the buttons, leave any open component, and redraw the
	 * chips for the new tab.
	 *
	 * @param {Object} state The instrument state.
	 * @param {string} tab The tab to open.
	 * @return {void}
	 */
	function setTab(state, tab) {
		state.tab = tab
		state.component = FULL_VIEW

		state.tabs.querySelectorAll('.nldesign-tab-btn').forEach(function (button) {
			button.classList.toggle(
				'active',
				button.getAttribute('data-tab') === tab,
			)
		})

		// The instrument owns which panel is open, rather than leaving it to
		// admin.js's own click handler. That handler deactivates buttons by
		// querying the editor container, and the strip no longer lives there —
		// so it can no longer keep the strip and the panels agreeing. One owner
		// is the only arrangement in which they cannot disagree.
		state.editor
			.querySelectorAll('.nldesign-tab-panel')
			.forEach(function (panel) {
				panel.classList.toggle(
					'active',
					panel.getAttribute('data-panel') === tab,
				)
			})

		exitComponent(state)
		showView(state, tab === 'login' ? 'login' : 'app')
		renderChips(state)
		updateCrumb(state)
		updateHash(state)
	}

	/**
	 * Show one of the preview's three stages.
	 *
	 * @param {Object} state The instrument state.
	 * @param {string} view The `data-view` to show.
	 * @return {void}
	 */
	function showView(state, view) {
		state.preview
			.querySelectorAll('.nldesign-preview-stage')
			.forEach(function (node) {
				node.hidden = node.getAttribute('data-view') !== view
			})
	}

	/**
	 * Say where in the instrument the admin is.
	 *
	 * @param {Object} state The instrument state.
	 * @return {void}
	 */
	function updateCrumb(state) {
		var component = componentById(state.inventory, state.component)
		state.crumb.textContent =
			' · '
			+ tabLabel(state, state.tab)
			+ ' · '
			+ (component !== null ? component.title : t('thematiq', 'Full view'))
	}

	/**
	 * Keep the selection in the URL, so a component can be linked to and
	 * survives a reload.
	 *
	 * @param {Object} state The instrument state.
	 * @return {void}
	 */
	function updateHash(state) {
		var hash = hashFor(state.tab, state.component)
		if (window.location.hash !== hash) {
			window.history.replaceState(null, '', hash)
		}
	}

	/**
	 * Draw the chip row for the open tab.
	 *
	 * @param {Object} state The instrument state.
	 * @return {void}
	 */
	function renderChips(state) {
		state.chips.innerHTML = ''

		var entries = [{ id: FULL_VIEW, title: t('thematiq', 'Full view') }].concat(
			componentsFor(state.inventory, state.tab),
		)

		entries.forEach(function (entry) {
			var chip = el('button', 'nldesign-pg-chip', entry.title)
			chip.type = 'button'
			chip.setAttribute('role', 'radio')
			// Read by the hidden bold twin in css/playground.css, which holds
			// every chip at its selected width so the row cannot re-wrap under
			// the pointer when one is clicked.
			chip.setAttribute('data-label', entry.title)
			var on = entry.id === state.component
			chip.classList.toggle('on', on)
			chip.setAttribute('aria-checked', on ? 'true' : 'false')
			chip.addEventListener('click', function () {
				select(state, entry.id)
			})
			state.chips.appendChild(chip)
		})
	}

	/**
	 * Select a chip: either back to the whole tab, or into one component.
	 *
	 * @param {Object} state The instrument state.
	 * @param {string} id A component id, or the full-view sentinel.
	 * @return {void}
	 */
	function select(state, id) {
		var component = componentById(state.inventory, id)

		if (component === null || component.tab !== state.tab) {
			exitComponent(state)
			state.component = FULL_VIEW
			showView(state, state.tab === 'login' ? 'login' : 'app')
		} else {
			state.component = component.id
			enterComponent(state, component)
		}

		renderChips(state)
		updateCrumb(state)
		updateHash(state)
	}

	/**
	 * Enter a component: draw its stage, hide the tab's full token list, and put
	 * the rows it actually reads in its place.
	 *
	 * @param {Object} state The instrument state.
	 * @param {Object} component The component to enter.
	 * @return {void}
	 */
	function enterComponent(state, component) {
		exitComponent(state)
		renderStage(state, component)
		showView(state, 'component')

		// By the tab this instrument has open, not by `.active`: the class is
		// written by two hands (admin.js's handler and setTab above), and the
		// panel that must be replaced is the one whose tokens the chip belongs
		// to, whatever the class says.
		var panel = state.editor.querySelector(
			'.nldesign-tab-panel[data-panel="' + state.tab + '"]',
		)
		if (panel !== null) {
			panel.classList.add('nldesign-pg-hidden')
		}

		var filtered = el('div', 'nldesign-pg-panel')
		filtered.appendChild(panelHead(state, component))

		rowsByState(component).forEach(function (group) {
			group.tokens.forEach(function (spec) {
				filtered.appendChild(cloneRow(state, spec))
			})
			group.fixed.forEach(function (spec) {
				filtered.appendChild(fixedRow(state, spec))
			})
		})

		if (state.saveBar !== null) {
			state.editor.insertBefore(filtered, state.saveBar)
		} else {
			state.editor.appendChild(filtered)
		}
	}

	/**
	 * The head of the filtered panel: how many tokens this component reads, and
	 * the way back to all of the tab's tokens.
	 *
	 * @param {Object} state The instrument state.
	 * @param {Object} component The open component.
	 * @return {Element} The head row.
	 */
	function panelHead(state, component) {
		var head = el('div', 'nldesign-pg-panel-head')

		// `t` with a placeholder rather than `n`: nothing else in this app calls
		// `n`, and a plural helper that is never exercised elsewhere is not
		// worth discovering is unavailable at the moment a panel has to render.
		var count = el('span')
		count.appendChild(
			el(
				'strong',
				'',
				t('thematiq', '{count} tokens', {
					count: (component.tokens || []).length,
				}),
			),
		)
		count.appendChild(document.createTextNode(' · ' + component.title))
		head.appendChild(count)

		// No "show all N tokens of <tab>" link here. The chip row above the
		// stage already carries "Volledig overzicht" as its first chip, which
		// is the same destination and the place an admin is already looking to
		// change what the stage shows.
		return head
	}

	/**
	 * Leave whatever component is open.
	 *
	 * @param {Object} state The instrument state.
	 * @return {void}
	 */
	function exitComponent(state) {
		var open = state.editor.querySelector('.nldesign-pg-panel')
		if (open !== null) {
			open.remove()
		}
		state.editor
			.querySelectorAll('.nldesign-tab-panel.nldesign-pg-hidden')
			.forEach(function (panel) {
				panel.classList.remove('nldesign-pg-hidden')
			})
	}

	/**
	 * A copy of the REAL token row, numbered and annotated with what it paints.
	 *
	 * Edits flow back into the original inputs and are re-dispatched there, so
	 * admin.js's dirty tracking, its custom-value badge and its Save see exactly
	 * what they would have seen if the admin had typed in the full list. The
	 * value is also set on the preview container, whose subtree the stage reads,
	 * so the component repaints as the value changes.
	 *
	 * @param {Object} state The instrument state.
	 * @param {{name: string, paints: string, callout: number}} spec The token to clone.
	 * @return {Element} The cloned row.
	 */
	function cloneRow(state, spec) {
		var original = state.editor.querySelector(
			'.nldesign-token-row[data-token-row="' + spec.name + '"]',
		)

		if (original === null) {
			// The inventory is held to the registry by a unit test, so this is
			// not an expected state; it is rendered rather than skipped because a
			// silently missing row is how a panel starts lying about a component.
			var missing = el('div', 'nldesign-token-row nldesign-pg-row')
			missing.appendChild(el('i', 'nldesign-pg-co', String(spec.callout)))
			var wrap = el('div', 'nldesign-token-label-wrap')
			wrap.appendChild(el('span', 'nldesign-token-label', spec.name))
			wrap.appendChild(
				el(
					'span',
					'nldesign-token-name',
					t('thematiq', 'not in the token registry'),
				),
			)
			missing.appendChild(wrap)
			return missing
		}

		var row = original.cloneNode(true)
		row.classList.add('nldesign-pg-row')
		row.removeAttribute('data-token-row')
		row.insertBefore(
			el('i', 'nldesign-pg-co', String(spec.callout)),
			row.firstChild,
		)

		var labelWrap = row.querySelector('.nldesign-token-label-wrap')
		if (labelWrap !== null) {
			labelWrap.parentNode.insertBefore(
				el('span', 'nldesign-pg-paints', spec.paints),
				labelWrap.nextSibling,
			)
		}

		row.querySelectorAll('input[data-token]').forEach(function (input) {
			input.addEventListener('input', function () {
				var twin = original.querySelector(
					'input.' + input.className.split(' ')[0] + '[data-token]',
				)
				if (twin !== null && twin.value !== input.value) {
					twin.value = input.value
					twin.dispatchEvent(new Event('input', { bubbles: true }))
				}
				var text = row.querySelector('.nldesign-color-text')
				if (input.type === 'color' && text !== null) {
					text.value = input.value
				}
				state.preview.style.setProperty(spec.name, input.value)
			})
		})

		wireReset(state, row, original, spec)

		return row
	}

	/**
	 * The cloned row's reset button drives the real one, then copies the values
	 * it restored back into the clone.
	 *
	 * @param {Object} state The instrument state.
	 * @param {Element} row The cloned row.
	 * @param {Element} original The editor's own row.
	 * @param {{name: string}} spec The token.
	 * @return {void}
	 */
	function wireReset(state, row, original, spec) {
		var reset = row.querySelector('.nldesign-reset-btn')
		var originalReset = original.querySelector('.nldesign-reset-btn')
		if (reset === null || originalReset === null) {
			return
		}

		reset.addEventListener('click', function (event) {
			event.preventDefault()
			originalReset.click()
			state.preview.style.removeProperty(spec.name)

			// After the real reset has run: whatever it put back is the truth.
			window.setTimeout(function () {
				original
					.querySelectorAll('input[data-token]')
					.forEach(function (source) {
						var target = row.querySelector(
							'input.' + source.className.split(' ')[0],
						)
						if (target !== null) {
							target.value = source.value
						}
					})
			}, 0)
		})
	}

	/**
	 * A row for something this component's look depends on that has no token at
	 * all: it carries the reason code the converter uses for the same fact, so
	 * the panel and an import report explain it in the same words.
	 *
	 * @param {Object} state The instrument state.
	 * @param {{callout: number, what: string, why: string, code: string}} spec The fixed fact.
	 * @return {Element} The row.
	 */
	function fixedRow(state, spec) {
		var row = el(
			'div',
			'nldesign-token-row nldesign-pg-row nldesign-pg-row-fixed',
		)
		row.appendChild(el('i', 'nldesign-pg-co lock', String(spec.callout)))

		var wrap = el('div', 'nldesign-token-label-wrap')
		wrap.appendChild(el('span', 'nldesign-token-label', spec.what))
		wrap.appendChild(
			el('span', 'nldesign-token-name', t('thematiq', 'no token')),
		)
		row.appendChild(wrap)

		var why = el(
			'span',
			'nldesign-pg-why',
			(state.reasons[spec.code] || spec.why) + ' ',
		)
		why.appendChild(el('code', '', spec.code))
		row.appendChild(why)

		return row
	}

	/* ---------------------------------------------------------------- */
	/* Vue scope attributes                                              */
	/* ---------------------------------------------------------------- */

	/**
	 * Why the specimens are no longer drawn.
	 *
	 * The settings page this instrument lives on already loads the shipped CSS
	 * for the components the specimens stand for: `dist/theming-settings-admin.css`
	 * pulls in the NcButton, NcInputField and NcCheckboxRadioSwitch chunks. Every
	 * rule in them is Vue-scoped — `.button-vue[data-v-00a99684]`,
	 * `.input-field__label[data-v-8e16cbb5]` — so the only thing keeping them off
	 * a specimen that already carries the right class names is an attribute.
	 *
	 * The hash is per BUNDLE, not per release: the same NcButton is
	 * `data-v-00a99684` in the settings bundle and `data-v-29ac0caf` in the one
	 * the login page loads. That is exactly why it is read at runtime out of the
	 * sheets THIS page has, instead of being written down here.
	 *
	 * @type {?Object<string, Array<string>>}
	 */
	var scopes = null

	/**
	 * Every scope attribute each class name is styled under, read out of the
	 * stylesheets this page has loaded.
	 *
	 * A class can appear under more than one hash — `material-design-icon` is
	 * scoped separately in every component that draws an icon — and an element
	 * may legitimately carry several, which is what Vue itself does at the root
	 * of a child component. So this collects all of them.
	 *
	 * @param {Document} [doc] The document to read; the page's own by default.
	 * @return {Object<string, Array<string>>} Class name → scope attributes.
	 */
	function componentScopes(doc) {
		var found = {}
		var sheets = (doc || document).styleSheets
		for (var i = 0; i < sheets.length; i++) {
			var rules = null
			try {
				rules = sheets[i].cssRules
			} catch (error) {
				// A stylesheet from another origin. Nextcloud serves all of its
				// own from this one, so this is never a sheet we wanted.
				continue
			}
			collectScopes(rules, found)
		}
		return found
	}

	/**
	 * Walk a rule list and record the scope attribute of every scoped class
	 * selector in it.
	 *
	 * @param {CSSRuleList} rules The rules to walk.
	 * @param {Object<string, Array<string>>} found The map to fill.
	 * @return {void}
	 */
	function collectScopes(rules, found) {
		if (!rules) {
			return
		}
		for (var i = 0; i < rules.length; i++) {
			var rule = rules[i]

			// An @import is a rule whose content is a whole other stylesheet,
			// reached through `styleSheet` and NOT through `cssRules`. That is
			// not an edge case here: Nextcloud's settings bundle is one <link>
			// whose entire body is a list of @imports, one per component chunk,
			// so a walker that follows only `cssRules` finds nothing at all in
			// it — and every specimen then renders unstamped, which reads as a
			// theme that has stopped working rather than as a bug in here.
			if (rule.styleSheet) {
				try {
					collectScopes(rule.styleSheet.cssRules, found)
				} catch (error) {
					// Same as above: another origin, nothing we wanted.
				}
				continue
			}

			// Media and supports blocks, where the dark halves of these
			// components live.
			if (rule.cssRules) {
				collectScopes(rule.cssRules, found)
				continue
			}

			if (!rule.selectorText) {
				continue
			}
			var pattern = /\.([A-Za-z0-9_-]+)\[(data-v-[0-9a-f]+)\]/g
			var match = pattern.exec(rule.selectorText)
			while (match !== null) {
				if (found[match[1]] === undefined) {
					found[match[1]] = []
				}
				if (found[match[1]].indexOf(match[2]) === -1) {
					found[match[1]].push(match[2])
				}
				match = pattern.exec(rule.selectorText)
			}
		}
	}

	/**
	 * Put the scope attributes on a freshly drawn specimen.
	 *
	 * @param {Element} root The subtree to stamp.
	 * @param {Object<string, Array<string>>} map Class name → scope attributes.
	 * @return {void}
	 */
	function applyScopes(root, map) {
		var nodes = root.querySelectorAll('*')
		for (var i = 0; i < nodes.length; i++) {
			var node = nodes[i]
			for (var j = 0; j < node.classList.length; j++) {
				var attributes = map[node.classList[j]]
				if (attributes === undefined) {
					continue
				}
				for (var k = 0; k < attributes.length; k++) {
					node.setAttribute(attributes[k], '')
				}
			}
		}
	}

	/**
	 * The scope map, read once and reused.
	 *
	 * Re-read while it is empty rather than caching the emptiness: at boot a
	 * stylesheet can still be in flight. If it stays empty the specimens are
	 * still drawn — `css/playground.css` carries a zero-specificity floor for
	 * each of these components, which the real rules override the moment they
	 * apply — but it is worth saying so out loud, because it means an admin is
	 * looking at this app's approximation of a component rather than at the
	 * component.
	 *
	 * @return {Object<string, Array<string>>} Class name → scope attributes.
	 */
	function ensureScopes() {
		if (typeof document === 'undefined') {
			return {}
		}
		if (scopes === null || Object.keys(scopes).length === 0) {
			scopes = componentScopes()
			if (Object.keys(scopes).length === 0) {
				console.warn(
					'[thematiq] no Vue component styles found on this page; the'
						+ ' specimens fall back to the playground\'s own approximation.',
				)
			}
		}
		return scopes
	}

	/**
	 * Stamp a specimen with whatever scopes this page turned out to have.
	 *
	 * @param {Element} root The subtree to stamp.
	 * @return {void}
	 */
	function scopeSpecimen(root) {
		applyScopes(root, ensureScopes())
	}

	/* ---------------------------------------------------------------- */
	/* The stage                                                         */
	/* ---------------------------------------------------------------- */

	/**
	 * Draw one component on the stage: its title, one cell per state with the
	 * numbered marker that ties it to the token rows, and the legend.
	 *
	 * @param {Object} state The instrument state.
	 * @param {Object} component The component to draw.
	 * @return {void}
	 */
	function renderStage(state, component) {
		state.stage.innerHTML = ''

		// When this page turns out not to carry the component stylesheets, the
		// specimens are this app's approximation of the components rather than
		// the components themselves. css/playground.css keeps a floor under
		// this class so that case draws something honest instead of nothing;
		// when the stylesheets ARE there the class is absent, and every rule in
		// that floor is absent with it.
		state.stage.classList.toggle(
			'nldesign-pg-unscoped',
			Object.keys(ensureScopes()).length === 0,
		)

		var heading = el('div', 'nldesign-pg-stage-title', component.title + ' ')
		heading.appendChild(el('span', 'nldesign-pg-dim', '· ' + component.subtitle))
		state.stage.appendChild(heading)

		var build = STAGES[component.id]

		// The header is the one component whose MARKUP differs between the
		// versions this app supports, so it gets to be drawn as any of them.
		if (component.id === 'header-bar') {
			state.stage.appendChild(versionSwitch(state, component))
		}

		// The surface the component really sits on: the page background for the
		// chrome, the login page's ground for the login parts, a dimmed page for
		// a modal, and the app content surface for everything else. An avatar or
		// a table judged against a neutral card answers a question nobody asked;
		// what an admin needs to know is whether it will look right where it
		// actually lands.
		var ground = el(
			'div',
			'nldesign-pg-ground nldesign-pg-ground--'
				+ (component.ground || 'content'),
		)
		state.stage.appendChild(ground)

		if (component.layout === 'wide') {
			// One specimen, full width, carrying every callout itself. A table,
			// a list, a dialog or a navigation column cannot be judged from a
			// thumbnail of one state: what an admin is deciding about is the
			// rhythm of the whole thing — the rules between rows, the zebra
			// against the hover, the spacing of the crumbs.
			var wide = el('div', 'nldesign-pg-wide')
			wide.innerHTML =
				typeof build === 'function'
					? build(null, component, state.headerVersion)
					: fallbackSample()
			scopeSpecimen(wide)
			ground.appendChild(wide)
		} else {
			var cells = el('div', 'nldesign-pg-states')
			drawnStates(component).forEach(function (stateDef) {
				var cell = el('div', 'nldesign-pg-st')
				var sample = el('div', 'nldesign-pg-sample')
				sample.innerHTML =
					typeof build === 'function'
						? build(stateDef.id, component)
						: fallbackSample()
				scopeSpecimen(sample)
				var mark = el(
					'i',
					'nldesign-pg-co' + (stateDef.fixed ? ' lock' : ''),
					String(stateDef.n),
				)
				mark.setAttribute('data-co', String(stateDef.n))
				sample.appendChild(mark)
				cell.appendChild(sample)
				// The STATE under the specimen ("hover"), not the label — the
				// label says what the state's tokens paint and is the legend's
				// job. Saying it twice leaves the cells unnamed and the legend
				// redundant.
				cell.appendChild(el('span', '', stateDef.id))
				cells.appendChild(cell)
			})
			ground.appendChild(cells)
		}

		// The numbered list that used to sit here said, in a row of its own,
		// what each marker on the drawing meant — which is the same sentence
		// twice for anything with a name as plain as "primary button", and it
		// pushed the specimen up the panel to make room. The markers carry it
		// themselves now: hover or focus one and it says what that state is and
		// which token paints it. See calloutTip().
		var legend = el('div', 'nldesign-pg-callouts')
		if (component.radiusToken) {
			legend.appendChild(
				el(
					'span',
					'nldesign-pg-dim',
					t('thematiq', 'corner radius {value}', {
						value: readVar(state.preview, component.radiusToken, '8px'),
					})
						+ ' ← '
						+ component.radiusToken,
				),
			)
		}
		if (legend.childNodes.length > 0) {
			state.stage.appendChild(legend)
		}

		// Where the stage reports what a specimen just did. Always present and
		// always the same height, so saying something never moves anything.
		// The states an admin makes for themselves get a line saying so, rather
		// than a frozen copy pretending to be one.
		var pointable = component.states.filter(isPointable)
		if (pointable.length > 0) {
			state.stage.appendChild(
				el(
					'div',
					'nldesign-pg-pointable',
					'Beweeg over het onderdeel of geef het focus voor '
						+ pointable
							.map(function (stateDef) {
								return stateDef.id
							})
							.join(' en ')
						+ '.',
				),
			)
		}

		var saidLine = el('div', 'nldesign-pg-say')
		saidLine.setAttribute('aria-live', 'polite')
		state.stage.appendChild(saidLine)

		decorateCallouts(state.stage, component)
		decorateFields(state.stage)
	}

	/**
	 * Let the text fields take text.
	 *
	 * A field an admin cannot type in cannot be judged: what a token set does
	 * to an input is most of what it does to a form, and the caret, the
	 * selection and the text colour only appear once something is in it.
	 *
	 * The select is excluded even though it wears the input class — it is
	 * chosen from, not typed in — and so is anything disabled, which is the
	 * one field whose whole point is that it refuses.
	 *
	 * @param {Element} stage The stage element.
	 * @return {void}
	 */
	function decorateFields(stage) {
		// The text field is a real `<input>` and needs nothing. What is left is
		// the select, which is chosen from rather than typed in, and the
		// textarea, which is still drawn.
		var fields = stage.querySelectorAll(
			'.nldesign-pg-textarea:not(.is-disabled)',
		)
		Array.prototype.forEach.call(fields, function (field) {
			if (field.classList.contains('is-disabled') === true) {
				return
			}

			field.setAttribute('contenteditable', 'true')
			field.setAttribute('role', 'textbox')
			field.setAttribute('spellcheck', 'false')
		})
	}

	/**
	 * The row of Nextcloud versions the header specimen can be drawn as.
	 *
	 * This exists so an admin can answer "what does my theme do to the header
	 * after the upgrade" without upgrading — 34 replaced the app-entry row with
	 * a waffle and a current-app button, and a theme that reached the old shape
	 * may reach nothing in the new one. The version they are running is marked,
	 * so switching away from it is visibly a hypothetical.
	 *
	 * @param {Object} state The instrument state.
	 * @param {Object} component The header component.
	 * @return {Element} The switch.
	 */
	function versionSwitch(state, component) {
		var row = el('div', 'nldesign-pg-versions')
		row.appendChild(
			el('span', 'nldesign-pg-dim', 'Nextcloud-versie'),
		)

		HEADER_VERSIONS.forEach(function (version) {
			var running = version === state.serverVersion
			var button = el(
				'button',
				'nldesign-pg-version'
					+ (version === headerMajor(state.headerVersion) ? ' on' : '')
					+ (running ? ' is-running' : ''),
				String(version),
			)
			button.type = 'button'
			if (running === true) {
				button.title = 'De versie die deze instantie draait'
			}
			button.addEventListener('click', function () {
				state.headerVersion = version
				renderStage(state, component)
			})
			row.appendChild(button)
		})

		return row
	}

	/**
	 * What a marker says when you ask it.
	 *
	 * It says what the marker POINTS AT, in words — the state's own name, then
	 * what is painted there, then the reason behind anything Nextcloud fixes.
	 * Deliberately no token names: the filtered rows under the stage are the
	 * place to read and edit those, they are already numbered to match these
	 * markers, and repeating them here turned a one-line explanation into a
	 * wall of `--color-*` that had to be read before it could be understood.
	 *
	 * Pure, so the wording is testable without a DOM.
	 *
	 * @param {Object} component The inventory entry.
	 * @param {number} n The state number the marker carries.
	 * @return {string} The tooltip text, newline-separated, empty when unknown.
	 */
	function calloutTip(component, n) {
		var lines = []
		var states = component.states || []
		var tokens = component.tokens || []
		var fixed = component.fixed || []
		var paints = []

		states.forEach(function (stateDef) {
			if (stateDef.n === n) {
				lines.push(stateDef.label)
			}
		})
		tokens.forEach(function (token) {
			if (token.callout === n) {
				paints.push(token.paints)
			}
		})
		if (paints.length > 0) {
			lines.push(paints.join(' · '))
		}
		fixed.forEach(function (fact) {
			if (fact.callout === n) {
				lines.push(fact.why)
			}
		})

		return lines.join('\n')
	}

	/**
	 * Turn every marker on the stage into something you can ask.
	 *
	 * Done as a pass over the finished stage rather than inside each builder,
	 * because a wide specimen places its own markers with co() and only it
	 * knows where they go — but both kinds carry the state number, which is all
	 * the lookup needs.
	 *
	 * @param {Element} stage The stage element.
	 * @param {Object} component The inventory entry being drawn.
	 * @return {void}
	 */
	function decorateCallouts(stage, component) {
		var markers = stage.querySelectorAll('.nldesign-pg-co')
		Array.prototype.forEach.call(markers, function (marker) {
			var n = parseInt(marker.getAttribute('data-co') || marker.textContent, 10)
			var tip = calloutTip(component, n)
			if (tip === '') {
				return
			}

			marker.setAttribute('data-tip', tip)
			// Focusable so the explanation is reachable without a pointer; the
			// number stays the label because the token rows below are numbered
			// to match it, and a row of question marks could not be matched up.
			marker.setAttribute('tabindex', '0')
			marker.setAttribute('aria-label', tip)
			// A marker can sit inside a field that decorateFields() makes
			// editable; without this it could be typed over or deleted.
			marker.setAttribute('contenteditable', 'false')
		})
	}

	/**
	 * Wire the stage once: marker tooltips, and specimens that answer back.
	 *
	 * Delegated from the stage rather than bound per specimen, because the
	 * stage is rebuilt from scratch on every selection and per-element
	 * listeners would have to be re-attached each time — and any that were
	 * missed would leave a specimen that silently stops responding.
	 *
	 * @param {Element} stage The stage element.
	 * @return {void}
	 */
	function bindStage(stage) {
		stage.addEventListener('mouseover', function (event) {
			var marker = closestCallout(event.target)
			if (marker !== null) {
				showTip(stage, marker)
			}
		})
		stage.addEventListener('mouseout', function (event) {
			if (closestCallout(event.target) !== null) {
				hideTip(stage)
			}
		})
		stage.addEventListener('focusin', function (event) {
			var marker = closestCallout(event.target)
			if (marker !== null) {
				showTip(stage, marker)
			}
		})
		stage.addEventListener('focusout', function () {
			hideTip(stage)
		})

		// The login card is a real `<form>`, because core's own stylesheet styles
		// one and a div is not one. A real form submits — on Enter in a field,
		// and that would navigate the admin off the settings page mid-edit.
		// Nothing on this stage ever wants a submission.
		stage.addEventListener('submit', function (event) {
			event.preventDefault()
		})

		stage.addEventListener('click', function (event) {
			pressFrom(stage, event.target)
		})

		// The button specimens are real `<button>` elements, which the browser
		// already activates on Enter and Space by firing a click — so the
		// handler above covers them. What is still needed is stopping Space
		// from scrolling the panel out from under the specimen being looked at.
		stage.addEventListener('keydown', function (event) {
			if (event.key !== ' ') {
				return
			}

			var target = event.target
			if (
				target.closest === undefined
				|| target.closest('.nldesign-pg-btn') === null
			) {
				return
			}

			event.preventDefault()
		})
	}

	/**
	 * Press the specimen button an event landed on, if it was one.
	 *
	 * A marker can sit INSIDE a button in a wide specimen, and a marker is for
	 * asking, not for pressing — so a hit on one never reaches the button
	 * underneath it.
	 *
	 * @param {EventTarget} target The event target.
	 * @return {void}
	 */
	function pressFrom(stage, target) {
		if (target === null || typeof target.closest !== 'function') {
			return
		}
		if (target.closest('.nldesign-pg-co') !== null) {
			return
		}

		var pressed = target.closest('.nldesign-pg-btn')
		if (pressed !== null) {
			pressButton(stage, pressed)
			return
		}

		interact(stage, target)
	}

	/**
	 * Everything on a stage that is not a button, answering the way the real
	 * component would.
	 *
	 * An earlier version of this held every element that carried a callout
	 * marker frozen, so the drawing could never contradict its own labels. In
	 * use that was the wrong trade by a wide margin: the marked row is the
	 * selected one in almost every component, so the one row an admin reaches
	 * for was the one that refused, and the whole content area read as dead.
	 * Selection moves now — and the marker that documented it travels with it,
	 * so "selected entry" keeps pointing at the entry that is selected.
	 *
	 * @param {Element} stage The stage element.
	 * @param {Element} target The clicked element.
	 * @return {void}
	 */
	function interact(stage, target) {
		// The actions menu: its trigger is the whole point of an actions menu,
		// and it did nothing at all.
		var toggle = target.closest('.nldesign-pg-actions .menutoggle')
		if (toggle !== null) {
			var menu = toggle.closest('.nldesign-pg-actions')
			var closed = menu.classList.toggle('is-closed')
			say(stage, closed ? 'Menu gesloten' : 'Menu geopend')
			return
		}

		// Choosing from the select writes the choice into the field.
		var option = target.closest('.nldesign-pg-option')
		if (option !== null) {
			var field = stage.querySelector('.nldesign-pg-select')
			if (field !== null) {
				setLabel(field, firstLine(option))
			}
			say(stage, firstLine(option) + ' gekozen')
			return
		}

		// Picking a menu item closes the menu behind it, the way a menu does.
		var item = target.closest('.nldesign-pg-action')
		if (item !== null) {
			var owner = item.closest('.nldesign-pg-actions')
			if (owner !== null) {
				owner.classList.add('is-closed')
			}
			say(stage, firstLine(item))
			return
		}

		var choice = target.closest('.nldesign-pg-choice')
		if (choice !== null) {
			toggleChoice(choice)
			say(
				stage,
				choice.classList.contains('checkbox-radio-switch--checked')
					? 'Aangezet'
					: 'Uitgezet',
			)
			return
		}

		// Everything that is one of a set: rows, entries, tabs, crumbs.
		for (var i = 0; i < PICKABLE.length; i++) {
			var group = PICKABLE[i]
			var row = target.closest(group.row)
			if (row === null) {
				continue
			}

			var within = row.closest(group.group)
			if (within === null) {
				continue
			}

			pick(within, row, group.row, group.on)
			say(stage, firstLine(row) + ' gekozen')
			return
		}
	}

	/**
	 * Move a state class to the element that was picked, and take the marker
	 * that documented it along.
	 *
	 * Moving the marker keeps the tooltip honest: "selected entry" should point
	 * at whichever entry is selected, not at the one that happened to be
	 * selected when the stage was drawn. It only travels when the new element
	 * has no marker of its own — otherwise a single element would end up
	 * wearing two numbers, which reads as a mistake rather than as two facts.
	 *
	 * @param {Element} group The container the set lives in.
	 * @param {Element} row The element that was picked.
	 * @param {string} rowSelector How to find the others.
	 * @param {string} on The class names that mark the picked one.
	 * @return {void}
	 */
	function pick(group, row, rowSelector, on) {
		var classes = on.split(' ')
		var previous = null

		Array.prototype.forEach.call(
			group.querySelectorAll(rowSelector),
			function (candidate) {
				if (candidate.classList.contains(classes[0]) === true) {
					previous = candidate
				}
				classes.forEach(function (name) {
					candidate.classList.remove(name)
				})
			}
		)

		classes.forEach(function (name) {
			row.classList.add(name)
		})

		if (previous === null || previous === row) {
			return
		}

		var marker = previous.querySelector('.nldesign-pg-co')
		if (marker !== null && row.querySelector('.nldesign-pg-co') === null) {
			row.appendChild(marker)
		}
	}

	/**
	 * Flip one checkbox, radio or switch.
	 *
	 * A radio turns its siblings off, because a radio group that allows two
	 * answers is not a radio group; a checkbox and a switch answer only for
	 * themselves.
	 *
	 * @param {Element} choice The choice element.
	 * @return {void}
	 */
	function toggleChoice(choice) {
		var control = choice.querySelector('.checkbox-radio-switch__input')
		var isRadio = control !== null && control.type === 'radio'
		var kind = choice.classList.contains('checkbox-radio-switch-radio')
			? 'radio'
			: choice.classList.contains('checkbox-radio-switch-switch')
				? 'switch'
				: 'checkbox'
		var on = choice.classList.contains('checkbox-radio-switch--checked')

		if (isRadio === true && on === false) {
			var group = choice.parentNode.querySelectorAll(
				'.checkbox-radio-switch-radio',
			)
			Array.prototype.forEach.call(group, function (sibling) {
				setChoice(sibling, 'radio', false)
			})
		}

		setChoice(choice, kind, on === false)
	}

	/**
	 * Put one choice into a state, the way the component itself would.
	 *
	 * The checked look of a Nextcloud checkbox is a DIFFERENT icon, not a class
	 * on the same square, so flipping one means swapping the glyph as well as
	 * the state classes — and flipping the real control underneath, which is
	 * what the component's own `:focus-within` and sibling selectors read.
	 *
	 * @param {Element} choice The choice element.
	 * @param {string} kind checkbox, radio or switch.
	 * @param {boolean} checked The state to put it in.
	 * @return {void}
	 */
	function setChoice(choice, kind, checked) {
		choice.classList.toggle('checkbox-radio-switch--checked', checked)

		var control = choice.querySelector('.checkbox-radio-switch__input')
		if (control !== null) {
			control.checked = checked
		}

		var icon = choice.querySelector('.checkbox-content__icon')
		if (icon !== null) {
			icon.classList.toggle('checkbox-content__icon--checked', checked)
			icon.innerHTML = choiceIcon(kind, checked)
			scopeSpecimen(icon)
		}
	}

	/**
	 * Replace an element's own text without disturbing anything nested in it.
	 *
	 * `textContent = x` would delete the callout marker living inside the
	 * select field along with the label.
	 *
	 * @param {Element} element The element to relabel.
	 * @param {string} text The new label.
	 * @return {void}
	 */
	function setLabel(element, text) {
		Array.prototype.forEach.call(element.childNodes, function (node) {
			if (node.nodeType === 3) {
				node.textContent = ''
			}
		})
		element.insertBefore(document.createTextNode(text), element.firstChild)
	}

	/**
	 * What to call the thing that was just picked.
	 *
	 * Three ways, in order. A row that names itself — a record, a list entry, a
	 * navigation label — says so in a dedicated element. Otherwise the
	 * element's OWN text, which skips a callout marker's digit and a counter
	 * bubble living inside it. Only failing both does it fall back to
	 * everything inside, which is where "Jan BakkerHeeft je uitgenodigd3"
	 * comes from and why it is last.
	 *
	 * @param {Element} element The element to read.
	 * @return {string} Its label.
	 */
	function firstLine(element) {
		var named = element.querySelector(
			'.nldesign-pg-rec-name, .nldesign-pg-listitem-name, .nldesign-pg-nav-label'
		)
		if (named !== null) {
			return named.textContent.trim()
		}

		var own = ''
		Array.prototype.forEach.call(element.childNodes, function (node) {
			if (node.nodeType === 3) {
				own += node.textContent
			}
		})
		if (own.trim() !== '') {
			return own.trim()
		}

		// A table row: its first cell is its name.
		var cell = element.querySelector('td')
		if (cell !== null) {
			return firstLine(cell)
		}

		return element.textContent.trim()
	}

	/**
	 * The marker an event landed on, or null.
	 *
	 * @param {EventTarget} target The event target.
	 * @return {Element|null} The marker.
	 */
	function closestCallout(target) {
		if (target === null || typeof target.closest !== 'function') {
			return null
		}

		return target.closest('.nldesign-pg-co[data-tip]')
	}

	/**
	 * Show one marker's explanation, positioned against the stage.
	 *
	 * The tip is appended to the STAGE, not to the marker, because several
	 * specimens clip their own overflow — the dialog, the sidebar, the app
	 * content — and a tip parented inside one of those would be cut off at
	 * exactly the components whose markers sit deepest inside them.
	 *
	 * @param {Element} stage The stage element.
	 * @param {Element} marker The marker being asked.
	 * @return {void}
	 */
	function showTip(stage, marker) {
		hideTip(stage)

		var tip = el('div', 'nldesign-pg-tip', marker.getAttribute('data-tip'))
		tip.setAttribute('role', 'tooltip')
		stage.appendChild(tip)

		var spot = marker.getBoundingClientRect()
		var frame = stage.getBoundingClientRect()
		var left = spot.left - frame.left + spot.width / 2
		var top = spot.top - frame.top

		tip.style.left = left + 'px'
		tip.style.top = top + 'px'

		// Measure where that actually put it, then bring it back inside the
		// stage. A marker rides the top-right corner of its specimen, so the
		// first thing it does unattended is hang off the edge or sit above the
		// stage entirely.
		var box = tip.getBoundingClientRect()
		var overRight = box.right - (frame.right - EDGE)
		var overLeft = frame.left + EDGE - box.left
		if (overRight > 0) {
			left -= overRight
		}
		if (overLeft > 0) {
			left += overLeft
		}

		if (box.top < frame.top) {
			tip.classList.add('is-below')
			top = spot.top - frame.top + spot.height
		}

		tip.style.left = left + 'px'
		tip.style.top = top + 'px'
	}

	/**
	 * Remove the open explanation, if there is one.
	 *
	 * @param {Element} stage The stage element.
	 * @return {void}
	 */
	function hideTip(stage) {
		var tip = stage.querySelector('.nldesign-pg-tip')
		if (tip !== null) {
			tip.parentNode.removeChild(tip)
		}
	}

	/**
	 * Answer a press on a specimen button.
	 *
	 * A specimen that does nothing when clicked reads as a broken control
	 * rather than as a picture of one, and an admin deciding whether a theme
	 * works wants to see the pressed state without leaving the panel. It is
	 * only a label swap: the button is a drawing, and anything more would
	 * invite it to be mistaken for the real thing.
	 *
	 * @param {Element} button The specimen button.
	 * @return {void}
	 */
	function pressButton(stage, button) {
		var done = button.getAttribute('data-done')
		if (done === null || done === '') {
			return
		}

		// A disabled specimen must stay unresponsive — that IS its state. It is
		// a real `disabled` attribute now rather than a class standing in for
		// one, so the browser would not deliver the click at all; this stays as
		// the belt to that brace.
		if (button.disabled === true) {
			return
		}

		say(stage, done)
	}

	/**
	 * Report what just happened, in the one place on the stage that says so.
	 *
	 * The confirmation used to replace the button's own label. That made the
	 * button resize on every press — "Opslaan" is not as wide as "Opgeslagen" —
	 * and a row of specimens whose widths change under the pointer reads as a
	 * layout defect, which is precisely the judgement this panel exists to
	 * support. The line below the stage has its height reserved, so nothing on
	 * the stage moves when something is said.
	 *
	 * @param {Element} stage The stage element.
	 * @param {string} message What to report.
	 * @return {void}
	 */
	function say(stage, message) {
		var line = stage.querySelector('.nldesign-pg-say')
		if (line === null) {
			return
		}

		line.textContent = message
		if (line.dataset.timer) {
			clearTimeout(Number(line.dataset.timer))
		}
		line.dataset.timer = String(
			setTimeout(function () {
				line.textContent = ''
				delete line.dataset.timer
			}, 2000)
		)
	}

	/**
	 * What a component with no stage markup shows. The inventory test makes this
	 * unreachable; it exists so a data file from a newer version degrades to a
	 * blank swatch rather than to a broken stage.
	 *
	 * @return {string} The markup.
	 */
	function fallbackSample() {
		return '<span class="nldesign-pg-swatch"></span>'
	}

	/**
	 * A numbered callout marker, as markup.
	 *
	 * A wide specimen places its own markers, because only the specimen knows
	 * which part of itself a state refers to — the zebra row, the active tab,
	 * the hovered crumb. The inventory test asserts that a wide specimen carries
	 * one marker per state it declares, so a state can never be listed in the
	 * legend and be invisible on the drawing.
	 *
	 * @param {number} n The state number.
	 * @param {boolean} [fixed] Whether the state is one Nextcloud fixes.
	 * @return {string} The marker markup.
	 */
	function co(n, fixed) {
		return (
			'<i class="nldesign-pg-co'
			+ (fixed ? ' lock' : '')
			+ '" data-co="'
			+ n
			+ '">'
			+ n
			+ '</i>'
		)
	}

	/**
	 * The stage markup per component, keyed by the inventory's component id.
	 *
	 * Called once per state for a component the stage draws as cells, and once
	 * with a null state for a `layout: wide` one, which returns its whole
	 * specimen and places its own callout markers.
	 *
	 * Two rules hold throughout. Every specimen carries the class names
	 * Nextcloud's own components emit — `button-vue`, `notecard`, `list-item`,
	 * `app-navigation-entry` — so that whatever the page already has loaded for
	 * those components styles the specimen exactly as it styles the real thing,
	 * and so Thematiq's own rules for them apply here too. And every value comes
	 * from the REAL Nextcloud variables, never the preview's `--prev-*` scale
	 * model: a second set of values would only show what the preview thinks the
	 * theme does.
	 *
	 * @type {Object<string, function(?string, Object): string>}
	 */
	var STAGES = {
		'header-bar': function (state, component, version) {
			var major = headerMajor(version)
			var modern = major >= 34

			return (
				'<div class="nldesign-pg-header' + (modern ? ' is-v34' : '') + '">'
				+ '<span class="nldesign-pg-header-logo logo"></span>'
				+ (modern ? appMenu34() : appMenu32())
				// 34 moved the search into the middle of the bar; before that it
				// was a magnifier among the glyphs on the right.
				+ (modern ? headerSearch34() : '')
				+ '<span class="nldesign-pg-header-end">'
				+ (modern
					? ''
					: '<span class="unified-search__button nldesign-pg-icon icon-search-white"></span>')
				+ glyph('bell', 'nldesign-pg-bell', 'bellDot')
				+ glyph('contacts', 'nldesign-pg-contacts')
				+ accountPlate()
				+ '</span>'
				+ '</div>'
			)
		},
		'login-card': function () {
			// `layout.guest.php` and `Login.vue` as they actually render, nesting
			// and all: the wrapper that holds the header and the card together
			// and pushes the footer off them, the visually hidden product name,
			// the guest box, and the empty alternative-logins container an
			// instance with SSO fills. Flattening any of it leaves core's own
			// stylesheet with nothing to match.
			return (
				'<div class="nldesign-pg-guestpage">'
				+ '<div class="wrapper"><div class="v-align">'
				// No `id="header"`, though the guest layout has one: this page
				// already has an element with that id and its own scripts look
				// it up. The generated stylesheet rewrites core's `#header`
				// selector to the class the guest layout always pairs it with.
				+ '<header><div class="header-guest">'
				+ '<div class="logo"></div>'
				+ '</div></header>'
				+ '<div class="guest-content">'
				+ '<h1 class="hidden-visually">Nextcloud</h1>'
				+ '<div>'
				+ '<div class="guest-box login-box nldesign-pg-logincard">'
				+ co(1)
				+ '<div class="login-box__wrapper nldesign-pg-loginwrap">'
				+ '<form method="post" name="login" class="login-form">'
				+ '<fieldset class="login-form__fieldset nldesign-pg-loginfields">'
				+ '<h2 class="login-form__headline">Inloggen bij Nextcloud</h2>'
				+ field('Accountnaam of e-mail', 'default', { inside: true })
				+ field('Wachtwoord', 'default', {
					inside: true,
					type: 'password',
					trailing: revealEye(),
				})
				+ choice('checkbox', true, 'Onthoud mij')
				+ button('primary', 'default', 'Inloggen', co(2), {
					icon: submitArrow(),
					wide: true,
					done: 'Bezig met inloggen …',
				})
				+ '</fieldset>'
				+ '</form>'
				// Both of these are NcButtons on the real card, not links: wide,
				// tertiary, stacked under the form, and siblings of it rather
				// than children.
				+ button('tertiary', 'default', 'Inloggen met een apparaat', '', {
					wide: true,
					done: 'Apparaat-login geopend',
				})
				+ button('tertiary', 'default', 'Wachtwoord vergeten?', '', {
					wide: true,
					done: 'Wachtwoordherstel geopend',
				})
				+ '</div>'
				+ '<div class="login-box__alternative-logins"></div>'
				+ '</div>'
				+ '</div>'
				+ '</div>'
				+ '</div></div>'
				// Core's own footer, a sibling of the wrapper rather than a
				// child of it — which is where the real page puts it, and which
				// means the hide-slogan stylesheet reaches it here exactly as it
				// reaches the real one.
				+ '<footer class="guest-box">'
				+ '<p class="info">Een veilige thuisbasis voor al je gegevens</p>'
				+ '</footer>'
				+ '</div>'
			)
		},
		'login-button': function (state) {
			// With the arrow, because `LoginButton` always has one: the chip and
			// the card must not draw the same button two different ways.
			return button('primary', state, 'Inloggen', '', {
				icon: submitArrow(),
				done: 'Bezig met inloggen …',
			})
		},
		'logo-slogan': function () {
			return (
				'<span class="nldesign-pg-loginbg header-guest nldesign-pg-brandbox">'
				+ '<span class="nldesign-pg-header-logo logo"></span>'
				+ '<span class="nldesign-pg-slogan">Een veilige thuisbasis</span>'
				+ '</span>'
			)
		},
		'login-background': function () {
			// The ground under this specimen IS the thing being shown, so the
			// box itself paints nothing. What it carries is the login page in
			// miniature — logo, slogan, card — because a background is judged
			// by what sits on it, and an empty rectangle gave an admin nothing
			// to judge against.
			return (
				'<span class="nldesign-pg-loginbg nldesign-pg-brandbox nldesign-pg-bgdemo">'
				+ '<span class="nldesign-pg-header-logo logo"></span>'
				+ '<span class="nldesign-pg-slogan">Een veilige thuisbasis</span>'
				+ '<span class="nldesign-pg-bgcard"></span>'
				+ '</span>'
			)
		},
		'app-navigation': function () {
			// On the page background, not on the stage's own ground: the
			// navigation column is a surface ON another surface, and the pair is
			// the thing an admin is judging. Floating it on a neutral card makes
			// a set look coherent that is not, and vice versa.
			return (
				'<div class="app-navigation nldesign-pg-nav">'
				+ '<div class="nldesign-pg-nav-caption">Bestanden</div>'
				+ '<ul>'
				+ navEntry('Alle bestanden', '', co(1))
				+ navEntry('Favorieten', 'is-selected active', co(2), '3')
				+ navEntry('Gedeeld met jou', '')
				+ navEntry('Verwijderde bestanden', '')
				+ '</ul>'
				+ '<div class="app-navigation-toggle nldesign-pg-navtoggle"></div>'
				+ '</div>'
			)
		},
		'content-card': function () {
			return (
				'<div class="content nldesign-pg-container">'
				+ co(1)
				+ '<div class="app-content nldesign-pg-appcontent">'
				// A register and one of its publications, because the dividers
				// callout 1 points at are only visible between real rows, and a
				// column of grey bars showed the divider token painting nothing.
				+ '<div class="app-content-list nldesign-pg-col">'
				+ [
					['Woo-verzoek jeugdzorg', 'Gepubliceerd · 12 mrt'],
					['Besluitenlijst college', 'Concept · 9 mrt'],
					['Subsidieregister 2026', 'Gepubliceerd · 2 mrt'],
					['Inkoopcontracten', 'In behandeling · 28 feb'],
				]
					.map(function (row, index) {
						return (
							'<div class="nldesign-pg-rec'
							+ (index === 1 ? ' is-selected' : '')
							+ '"><span class="nldesign-pg-rec-name">'
							+ row[0]
							+ '</span><span class="nldesign-pg-muted is-maxcontrast">'
							+ row[1]
							+ '</span></div>'
						)
					})
					.join('')
				+ '</div>'
				+ '<div class="app-content-detail nldesign-pg-col nldesign-pg-col--detail">'
				+ '<div class="nldesign-pg-rec-title">Besluitenlijst college</div>'
				+ '<dl class="nldesign-pg-meta">'
				+ '<dt>Register</dt><dd>Besluiten</dd>'
				+ '<dt>Status</dt><dd>Concept</dd>'
				+ '<dt>Gewijzigd</dt><dd>9 maart 2026 om 14:02</dd>'
				+ '</dl>'
				+ '<p class="nldesign-pg-paragraph">Wekelijkse besluitenlijst van het '
				+ 'college van burgemeester en wethouders, inclusief bijlagen en '
				+ 'openbaar gemaakte stukken.</p>'
				+ '<span class="nldesign-pg-scrollbar">'
				+ co(2)
				+ '</span>'
				+ '</div>'
				+ '</div></div>'
			)
		},
		table: function () {
			var rows = [
				['Jaarverslag 2025.pdf', '2,4 MB', 'Vandaag', '', ''],
				['Begroting.xlsx', '812 kB', 'Gisteren', 'is-zebra', co(2)],
				['Notulen raad.docx', '64 kB', '3 dagen geleden', '', ''],
				['Bijlage A.png', '1,1 MB', 'Vorige week', 'is-zebra', ''],
				['Archief', '—', 'Vorige maand', '', ''],
			]
			return (
				'<table class="nldesign-pg-table">'
				+ '<thead><tr>'
				+ '<th>Naam'
				+ co(1)
				+ '</th><th>Grootte</th><th>Gewijzigd</th><th></th>'
				+ '</tr></thead><tbody>'
				+ rows
					.map(function (row) {
						return (
							'<tr class="'
							+ row[3]
							+ '">'
							+ '<td>'
							+ row[0]
							+ row[4]
							+ '</td>'
							+ '<td class="nldesign-pg-td--muted">'
							+ row[1]
							+ '</td>'
							+ '<td class="nldesign-pg-td--muted">'
							+ row[2]
							+ '</td>'
							+ '<td class="nldesign-pg-td--actions"><span class="icon-more">⋯</span></td>'
							+ '</tr>'
						)
					})
					.join('')
				+ '</tbody></table>'
			)
		},
		sidebar: function () {
			return (
				'<div class="app-sidebar nldesign-pg-sidebar">'
				+ co(1)
				+ '<div class="nldesign-pg-sidebar-head">'
				+ '<span class="avatardiv nldesign-pg-avatar">JV</span>'
				+ '<span class="nldesign-pg-sidebar-title"><strong>Jaarverslag 2025.pdf</strong>'
				+ '<span class="nldesign-pg-muted is-maxcontrast">2,4 MB · vandaag gewijzigd</span></span>'
				+ '<span class="nldesign-pg-glyph nldesign-pg-glyph--dark"></span>'
				+ '</div>'
				+ '<div class="nldesign-pg-tabs">'
				+ '<span class="nldesign-pg-tab is-active">Delen'
				+ co(2)
				+ '</span>'
				+ '<span class="nldesign-pg-tab">Versies</span>'
				+ '<span class="nldesign-pg-tab">Activiteit</span>'
				+ '</div>'
				// The Delen tab is the active one, so the body shows sharing —
				// a specimen whose tab strip says one thing and whose body
				// shows nothing is the emptiness this panel is meant to expose.
				+ '<div class="nldesign-pg-sidebar-body">'
				+ field('Naam, federated cloud-ID of e-mail', 'default')
				+ '<ul class="nldesign-pg-list nldesign-pg-shares">'
				+ listItem('Marianne de Vries', 'Kan bewerken', '')
				+ listItem('Team Communicatie', 'Kan bekijken', '')
				+ listItem('Openbare link', 'Kan bekijken · verloopt 1 apr', '')
				+ '</ul>'
				+ '</div></div>'
			)
		},
		'list-item': function () {
			return (
				'<ul class="nldesign-pg-list">'
				+ listItem(
					'Marianne de Vries',
					'Deelde "Begroting.xlsx" met jou',
					'',
					co(1),
				)
				+ listItem(
					'Gemeente Voorbeeld',
					'Nieuwe reactie op Jaarverslag',
					'',
				)
				+ listItem(
					'Jan Bakker',
					'Heeft je uitgenodigd voor Overleg',
					'is-selected active',
					co(3),
				)
				+ '</ul>'
			)
		},
		'empty-content': function () {
			return (
				'<div class="empty-content nldesign-pg-empty">'
				+ '<div class="empty-content__icon nldesign-pg-empty-icon">☐</div>'
				+ '<h2 class="empty-content__name nldesign-pg-empty-name">Geen bestanden gevonden</h2>'
				+ '<p class="empty-content__description nldesign-pg-muted is-maxcontrast">'
				+ 'Upload een bestand of maak een nieuwe map om te beginnen.</p>'
				+ button('secondary', 'default', 'Bestand uploaden')
				+ co(1)
				+ '</div>'
			)
		},
		'actions-menu': function () {
			return (
				'<div class="nldesign-pg-actions">'
				+ '<span class="menutoggle icon-more nldesign-pg-glyph nldesign-pg-glyph--dark">⋯</span>'
				+ '<div class="popover nldesign-pg-popover">'
				+ co(1)
				+ '<ul class="popovermenu">'
				+ '<li class="nldesign-pg-action">Hernoemen</li>'
				+ '<li class="nldesign-pg-action">Verplaatsen of kopiëren</li>'
				+ '<li class="nldesign-pg-action">Details openen</li>'
				+ '<li class="nldesign-pg-action">Verwijderen</li>'
				+ '</ul></div></div>'
			)
		},
		avatar: function (state) {
			return avatarPlate('RB', state === 'status', true)
		},
		breadcrumbs: function () {
			return (
				'<nav class="nldesign-pg-crumbs">'
				+ '<span class="nldesign-pg-crumb">Home'
				+ co(1)
				+ '</span>'
				+ '<span class="nldesign-pg-sep">›</span>'
				+ '<span class="nldesign-pg-crumb">Documenten</span>'
				+ '<span class="nldesign-pg-sep">›</span>'
				+ '<span class="nldesign-pg-crumb">Rapportages</span>'
				+ '<span class="nldesign-pg-sep">›</span>'
				+ '<span class="nldesign-pg-crumb is-current">Q3</span>'
				+ '</nav>'
			)
		},
		'settings-section': function () {
			return (
				'<div class="nldesign-pg-section">'
				+ '<h2 class="nldesign-pg-section-title">Achtergrond en kleuren'
				+ co(1)
				+ '</h2>'
				+ '<p class="nldesign-pg-muted is-maxcontrast nldesign-pg-reading">'
				+ 'Kies een kleur die past bij je organisatie. De kleur wordt gebruikt in de '
				+ 'header, op de inlogpagina en in e-mails.</p>'
				+ button('secondary', 'default', 'Wijzigingen opslaan')
				+ '<hr class="nldesign-pg-hr">'
				+ '</div>'
			)
		},
		'text-input': function (state) {
			return field(state === 'invalid' ? 'E-mailadres' : 'Accountnaam', state)
		},
		select: function () {
			return (
				'<div class="nldesign-pg-selectwrap">'
				+ '<span class="nldesign-pg-input nldesign-pg-select">Nederland'
				+ co(1)
				+ '</span>'
				// The marker belongs on the LIST, not on one option: state 2 is
				// "open", which is the list being there at all, and hanging it
				// off a highlighted row made it read as a hover exemplar.
				+ '<ul class="nldesign-pg-options">'
				+ co(2)
				+ '<li class="nldesign-pg-option">België</li>'
				+ '<li class="nldesign-pg-option">Duitsland</li>'
				+ '<li class="nldesign-pg-option">Frankrijk</li>'
				+ '<li class="nldesign-pg-option">Luxemburg</li>'
				+ '</ul></div>'
			)
		},
		'checkbox-switch': function () {
			return (
				'<div class="nldesign-pg-choices">'
				+ choice('checkbox', false, 'Niet aangevinkt', co(1))
				+ choice('checkbox', true, 'Aangevinkt', co(2))
				+ choice('radio', false, 'Niet geselecteerd')
				+ choice('radio', true, 'Geselecteerd')
				+ choice('switch', false, 'Uit')
				+ choice('switch', true, 'Aan')
				+ '</div>'
			)
		},
		textarea: function () {
			return (
				'<span class="nldesign-pg-input nldesign-pg-textarea">'
				+ 'Een langere toelichting, over meerdere regels.</span>'
			)
		},
		dialog: function () {
			return (
				'<div class="modal-container nldesign-pg-dialog">'
				+ co(1)
				+ '<div class="nldesign-pg-dialog-head">'
				+ '<strong>Tokenset toepassen: OpenWOO</strong>'
				+ '<span class="nldesign-pg-glyph nldesign-pg-glyph--dark"></span>'
				+ '</div>'
				+ '<div class="nldesign-pg-dialog-body">'
				+ '<p class="nldesign-pg-paragraph">Deze waarden veranderen. Controleer welke je '
				+ 'wilt toepassen op je eigen overrides.</p>'
				// The dialog this stands for is Thematiq's own apply dialog, so
				// it shows what that one shows: the values about to change.
				+ '<ul class="nldesign-pg-changes">'
				+ [
					['Primaire kleur', '#0082c9', '#23845c'],
					['Hoekradius', '4px', '8px'],
					['Koptekst', '#ffffff', '#11304e'],
				]
					.map(function (row) {
						return (
							'<li><span class="nldesign-pg-change-name">'
							+ row[0]
							+ '</span><span class="nldesign-pg-muted is-maxcontrast">'
							+ row[1]
							+ ' → </span><strong>'
							+ row[2]
							+ '</strong></li>'
						)
					})
					.join('')
				+ '</ul>'
				+ '</div>'
				+ '<div class="nldesign-pg-dialog-foot">'
				+ button('secondary', 'default', 'Annuleren')
				+ button('primary', 'default', 'Toepassen', co(2))
				+ '</div></div>'
			)
		},
		progress: function (state) {
			if (state === 'loading') {
				return '<span class="icon-loading nldesign-pg-spinner"></span>'
			}
			return (
				'<span class="nldesign-pg-progress">'
				+ '<span class="nldesign-pg-progress-fill"></span>'
				+ '</span>'
			)
		},
		'primary-button': function (state) {
			return button('primary', state, 'Opslaan')
		},
		'secondary-button': function (state) {
			return button('secondary', state, 'Annuleren')
		},
		'tertiary-button': function (state) {
			return button('tertiary', state, 'Meer opties')
		},
		'error-button': function (state) {
			return button('error', state, 'Verwijderen')
		},
		'success-button': function (state) {
			return button('success', state, 'Goedkeuren')
		},
		'note-cards': function () {
			return [
				[
					'info',
					1,
					'Deze tokenset is geïmporteerd uit een NL Design System thema.',
				],
				['warning', 2, 'Twee kleuren halen de WCAG AA-drempel niet.'],
				['error', 3, 'De tokenset kon niet worden opgeslagen.'],
				['success', 4, 'De tokenset is toegepast op de hele instantie.'],
			]
				.map(function (def) {
					return (
						'<div class="notecard notecard--'
						+ def[0]
						+ ' nldesign-pg-note is-'
						+ def[0]
						+ '">'
						+ '<span class="nldesign-pg-note-icon"></span>'
						+ '<span>'
						+ def[2]
						+ '</span>'
						+ co(def[1])
						+ '</div>'
					)
				})
				.join('')
		},
		'badge-counter': function (state) {
			if (state === 'favorite') {
				return '<span class="nldesign-pg-star">★</span>'
			}
			return '<span class="nldesign-pg-bubble">12</span>'
		},
		toast: function () {
			return [
				['success', 1, 'Tokenoverrides opgeslagen.'],
				['error', 2, 'Opslaan is niet gelukt.'],
				['warning', 3, 'Eén set is onvolledig.'],
				['info', 4, 'Je bekijkt een voorbeeld in je eigen sessie.'],
			]
				.map(function (def) {
					return (
						'<div class="toastify nldesign-pg-toast is-'
						+ def[0]
						+ '">'
						+ def[2]
						+ co(def[1])
						+ '</div>'
					)
				})
				.join('')
		},
		heading: function () {
			return (
				'<div class="nldesign-pg-type">'
				+ '<h1 class="nldesign-pg-h1">Thema en huisstijl'
				+ co(1)
				+ '</h1>'
				+ '<h2 class="nldesign-pg-h2">Achtergrond en kleuren</h2>'
				+ '<h3 class="nldesign-pg-h3">Eigen tokenset uploaden</h3>'
				+ '<h4 class="nldesign-pg-h4">Let op bij het uploaden</h4>'
				+ '</div>'
			)
		},
		paragraph: function () {
			return (
				'<p class="nldesign-pg-paragraph nldesign-pg-reading">'
				+ 'Kies een tokenset als basis, of pas losse Nextcloud-tokens hieronder aan. '
				+ 'Een tokenset bepaalt de kleuren, de typografie en de vormgeving van elk '
				+ 'onderdeel dat je hier ziet. Wat je opslaat geldt voor iedereen op deze '
				+ 'instantie, op elke pagina.'
				+ co(1)
				+ '</p>'
			)
		},
		link: function () {
			return (
				'<p class="nldesign-pg-paragraph nldesign-pg-reading">'
				+ 'Meer over het NL Design System lees je in de '
				+ '<span class="nldesign-pg-link">documentatie</span>'
				+ co(1)
				+ ', of bekijk de '
				+ '<span class="nldesign-pg-link">voorbeeldthema&rsquo;s</span>'
				+ '.</p>'
			)
		},
		'muted-text': function () {
			return (
				'<div class="nldesign-pg-type">'
				+ '<p class="nldesign-pg-muted is-maxcontrast">Secundaire tekst die nog steeds AA moet halen.'
				+ co(1)
				+ '</p>'
				+ '<p class="nldesign-pg-muted is-light">Tekst een stap lichter dan de body.'
				+ co(2)
				+ '</p>'
				+ '<p class="nldesign-pg-muted is-lighter">De lichtste tekst die Nextcloud gebruikt.'
				+ co(3)
				+ '</p>'
				+ '</div>'
			)
		},
		'status-text': function () {
			return (
				'<div class="nldesign-pg-type">'
				+ '<p class="nldesign-pg-status is-error">Dit veld is verplicht.'
				+ co(1)
				+ '</p>'
				+ '<p class="nldesign-pg-status is-warning">Twee waarden halen de AA-drempel niet.'
				+ co(2)
				+ '</p>'
				+ '<p class="nldesign-pg-status is-success">De wijzigingen zijn opgeslagen.'
				+ co(3)
				+ '</p>'
				+ '</div>'
			)
		},
	}

	/**
	 * An account avatar, in NcAvatar's own class names.
	 *
	 * Drawn as a light plate with dark initials, because that is what Nextcloud
	 * actually puts there: the avatar is an IMAGE — uploaded, or generated from
	 * the account name — and an opaque image covers whatever background colour
	 * the cascade gives `.avatardiv`. Painting the plate with the brand colour,
	 * as an earlier version did, produced a brand disc on a brand-coloured
	 * header that no user will ever see, and that looked like a bug in this
	 * panel rather than what it was: a claim about a token that does not paint
	 * this.
	 *
	 * @param {string} initials The initials to show.
	 * @param {boolean} status Whether to hang a status dot off it.
	 * @param {boolean} [large] Whether to draw it at the size the sidebar uses.
	 * @return {string} The markup.
	 */
	/**
	 * The account the header specimen should show.
	 *
	 * The bar an admin is judging is THEIR bar, and a stranger's initials in
	 * the corner of it is the one detail that makes the whole drawing read as
	 * somebody else's screenshot. `OC.getCurrentUser()` is the same source the
	 * real avatar menu uses.
	 *
	 * Guarded because this file is also loaded under Node by the unit tests,
	 * where there is no `OC` and no session.
	 *
	 * @return {{uid: string, name: string}} The signed-in account, or empties.
	 */
	function currentUser() {
		if (typeof OC === 'undefined' || typeof OC.getCurrentUser !== 'function') {
			return { uid: '', name: '' }
		}

		var who = OC.getCurrentUser() || {}

		return {
			uid: who.uid || '',
			name: who.displayName || who.uid || '',
		}
	}

	/**
	 * The initials Nextcloud would put on an avatar with no picture.
	 *
	 * @param {string} name A display name.
	 * @return {string} One or two letters.
	 */
	function initialsOf(name) {
		var words = String(name).trim().split(/\s+/).filter(Boolean)
		if (words.length === 0) {
			return '?'
		}
		if (words.length === 1) {
			return words[0].charAt(0).toUpperCase()
		}

		return (
			words[0].charAt(0) + words[words.length - 1].charAt(0)
		).toUpperCase()
	}

	/** Make a string safe to sit inside a double-quoted HTML attribute. */
	function attr(value) {
		return String(value)
			.replace(/&/g, '&amp;')
			.replace(/"/g, '&quot;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
	}

	/**
	 * The account glyph at the end of the header: the signed-in user's own
	 * avatar, with the status bubble the real one carries.
	 *
	 * Falls back to initials when there is no session to ask — and the avatar
	 * endpoint itself falls back to initials for an account with no picture,
	 * which is exactly what the real header does.
	 *
	 * @return {string} The markup.
	 */
	function accountPlate() {
		var who = currentUser()
		if (who.uid === '') {
			return avatarPlate('?', true, true)
		}

		var picture = OC.generateUrl('/avatar/{user}/64', { user: who.uid })

		return (
			'<span class="nldesign-pg-avatarwrap">'
			+ '<span class="avatardiv nldesign-pg-avatar nldesign-pg-avatar--lg'
			+ ' nldesign-pg-avatar--photo" title="'
			+ attr(who.name)
			+ '" style="background-image:url('
			+ picture
			+ ')">'
			+ attr(initialsOf(who.name))
			+ '</span>'
			+ statusOnline()
			+ '</span>'
		)
	}

	function avatarPlate(initials, status, large) {
		var plate =
			'<span class="avatardiv nldesign-pg-avatar'
			+ (large ? ' nldesign-pg-avatar--lg' : '')
			+ '">'
			+ initials
			+ '</span>'

		if (status !== true) {
			return plate
		}

		return (
			'<span class="nldesign-pg-avatarwrap">'
			+ plate
			+ statusOnline()
			+ '</span>'
		)
	}

	/**
	 * The glyphs the 34 header carries, as the paths core's own components
	 * render.
	 *
	 * Transcribed from the live header's DOM. They were CSS masks before, which
	 * left a soft edge on every one of them — a mask samples an SVG into an
	 * alpha channel and then paints a box through it, so the result is a
	 * blurred stencil rather than a drawn shape. Core renders `<svg
	 * fill="currentColor">` inline, so the specimen does too and the glyphs
	 * come out identical instead of merely similar.
	 */
	var HEADER_GLYPHS = {
		// IconDotsGrid — nine dots, not a repeating gradient.
		waffle:
			'M12 16C13.1 16 14 16.9 14 18S13.1 20 12 20 10 19.1 10 18 10.9 16 12 16M12 10C13.1'
			+ ' 10 14 10.9 14 12S13.1 14 12 14 10 13.1 10 12 10.9 10 12 10M12 4C13.1 4 14 4.9'
			+ ' 14 6S13.1 8 12 8 10 7.1 10 6 10.9 4 12 4M6 16C7.1 16 8 16.9 8 18S7.1 20 6 20 4'
			+ ' 19.1 4 18 4.9 16 6 16M6 10C7.1 10 8 10.9 8 12S7.1 14 6 14 4 13.1 4 12 4.9 10 6'
			+ ' 10M6 4C7.1 4 8 4.9 8 6S7.1 8 6 8 4 7.1 4 6 4.9 4 6 4M18 16C19.1 16 20 16.9 20'
			+ ' 18S19.1 20 18 20 16 19.1 16 18 16.9 16 18 16M18 10C19.1 10 20 10.9 20 12S19.1'
			+ ' 14 18 14 16 13.1 16 12 16.9 10 18 10M18 4C19.1 4 20 4.9 20 6S19.1 8 18 8 16'
			+ ' 7.1 16 6 16.9 4 18 4Z',
		magnify:
			'M9.5,3A6.5,6.5 0 0,1 16,9.5C16,11.11 15.41,12.59 14.44,13.73L14.71,14H15.5L20.5,19'
			+ 'L19,20.5L14,15.5V14.71L13.73,14.44C12.59,15.41 11.11,16 9.5,16A6.5,6.5 0 0,1 3,'
			+ '9.5A6.5,6.5 0 0,1 9.5,3M9.5,5C7,5 5,7 5,9.5C5,12 7,14 9.5,14C12,14 14,12 14,9.5'
			+ 'C14,7 12,5 9.5,5Z',
		// The notifications bell, in the shape it takes WITH unread items: core
		// swaps in a cut-out bell and draws the dot as a second path, rather
		// than stacking a badge on top of the plain one.
		bell:
			'M 19,11.79 C 18.5,11.92 18,12 17.5,12 14.47,12 12,9.53 12,6.5 12,5.03 12.58,3.7'
			+ ' 13.5,2.71 13.15,2.28 12.61,2 12,2 10.9,2 10,2.9 10,4 V 4.29 C 7.03,5.17 5,7.9'
			+ ' 5,11 v 6 l -2,2 v 1 H 21 V 19 L 19,17 V 11.79 M 12,23 c 1.11,0 2,-0.89'
			+ ' 2,-2 h -4 c 0,1.11 0.9,2 2,2 z',
		bellDot:
			'M 21,6.5 C 21,8.43 19.43,10 17.5,10 15.57,10 14,8.43 14,6.5 14,4.57 15.57,3'
			+ ' 17.5,3 19.43,3 21,4.57 21,6.5',
		contacts:
			'M20,0H4V2H20V0M4,24H20V22H4V24M20,4H4A2,2 0 0,0 2,6V18A2,2 0 0,0 4,20H20A2,2 0 0,'
			+ '0 22,18V6A2,2 0 0,0 20,4M12,6.75A2.25,2.25 0 0,1 14.25,9A2.25,2.25 0 0,1 12,'
			+ '11.25A2.25,2.25 0 0,1 9.75,9A2.25,2.25 0 0,1 12,6.75M17,17H7V15.5C7,13.83 10.33,'
			+ '13 12,13C13.67,13 17,13.83 17,15.5V17Z',
	}

	/**
	 * The "online" status badge, as the account button renders it.
	 *
	 * A check inside a filled circle, not the plain dot the specimen drew: the
	 * dot is what Nextcloud used before statuses carried a meaning, and an
	 * admin comparing the two bars reads the difference immediately. Its own
	 * viewBox, because core's status icons come from a 960-unit set rather than
	 * the 24-unit one the header glyphs use.
	 *
	 * @return {string} The markup.
	 */
	function statusOnline() {
		return (
			'<span class="user-status-icon nldesign-pg-userstatus" aria-hidden="true">'
			+ '<svg viewBox="0 -960 960 960" width="16" height="16">'
			+ '<path d="m424-296 282-282-56-56-226 226-114-114-56 56 170 170Zm56 216q-83 0-156'
			+ '-31.5T197-197q-54-54-85.5-127T80-480q0-83 31.5-156T197-763q54-54 127-85.5T480-880'
			+ 'q83 0 156 31.5T763-763q54 54 85.5 127T880-480q0 83-31.5 156T763-197q-54 54-127'
			+ ' 85.5T480-80Z"></path>'
			+ '</svg></span>'
		)
	}

	/**
	 * The arrow the log-in button carries.
	 *
	 * Core's `LoginButton` puts `vue-material-design-icons/ArrowRight` in
	 * NcButton's icon slot, so the login page's primary button is never a bare
	 * label. This is that icon's own path, at that set's 24-unit viewBox.
	 *
	 * @return {string} The markup.
	 */
	function submitArrow() {
		return (
			'<svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24">'
			+ '<path d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,'
			+ '5.5L16,11H4Z"></path></svg>'
		)
	}

	/**
	 * The reveal button NcPasswordField hangs inside the password box.
	 *
	 * A trailing button, not an adornment: it is a real `button-vue` in
	 * `input-field__trailing-button`, which is why the password box on the
	 * login card is the one field whose text stops short of its own edge.
	 *
	 * @return {string} The markup.
	 */
	function revealEye() {
		return (
			'<span class="input-field__trailing-button button-vue'
			+ ' button-vue--tertiary-no-background nldesign-pg-reveal"'
			// Hidden from assistive technology and out of the tab order: it is
			// the drawing of a toggle, not a toggle, and an unlabelled tab stop
			// that does nothing is worse than no tab stop.
			+ ' aria-hidden="true">'
			+ '<svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24">'
			+ '<path d="M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,'
			+ '0 12,9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,1 '
			+ '12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C17,19.5 '
			+ '21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z"></path></svg></span>'
		)
	}

	/**
	 * One header glyph, drawn the way core draws it.
	 *
	 * @param {string} name A key of HEADER_GLYPHS.
	 * @param {string} [className] Extra classes for the wrapping span.
	 * @param {string} [extra] A second path, for the bell's unread dot.
	 * @return {string} The markup.
	 */
	function glyph(name, className, extra) {
		return (
			'<span class="nldesign-pg-glyphbox '
			+ (className || '')
			+ '" aria-hidden="true">'
			+ '<svg fill="currentColor" width="20" height="20" viewBox="0 0 24 24">'
			+ '<path d="'
			+ HEADER_GLYPHS[name]
			+ '"></path>'
			+ (extra
				? '<path class="nldesign-pg-belldot" d="' + HEADER_GLYPHS[extra] + '"></path>'
				: '')
			+ '</svg></span>'
		)
	}

	/**
	 * The app icons the header specimen draws.
	 *
	 * Core's own pictograms, not blocks. `dist/icons.css` ships every one of
	 * these with a `-white` variant precisely because they sit on a saturated
	 * header, so the specimen can use the icons the real bar uses instead of
	 * standing in for them with squares.
	 */
	var HEADER_ICONS = [
		'icon-category-dashboard-white',
		'icon-files-white',
		'icon-picture-white',
		'icon-music-white',
		'icon-comment-white',
		'icon-category-office-white',
		'icon-category-monitoring-white',
	]

	/**
	 * The app menu as Nextcloud 32 and 33 draw it: a row of app entries.
	 *
	 * Icons, not labels. The real bar is a row of app icons between the logo
	 * and the account glyphs with the active app marked by a bar under it;
	 * labels only appear in the overflow menu, and drawing them here made the
	 * specimen a row of chunky boxes that looks nothing like the bar it stands
	 * for. `core/src/components/AppMenuEntry.vue` is where these class names
	 * come from — it exists in v32.0.0 and v33.0.0 and is gone in v34.0.0.
	 *
	 * @return {string} The markup.
	 */
	function appMenu32() {
		var apps = ''
		HEADER_ICONS.forEach(function (icon, index) {
			var active = index === 1
			apps +=
				'<li class="app-menu-entry'
				+ (active ? ' app-menu-entry--active' : '')
				+ '">'
				+ '<span class="nldesign-pg-icon '
				+ icon
				+ '"></span>'
				+ (active ? co(1) : '')
				+ '</li>'
		})

		return '<nav class="app-menu"><ul class="app-menu__list">' + apps + '</ul></nav>'
	}

	/**
	 * The app menu as Nextcloud 34 draws it: a waffle and the current app.
	 *
	 * 34 deleted the entry row outright. What replaced it is a
	 * `tertiary-no-background` button carrying a dots-grid icon, which opens a
	 * popover grid, and beside it a second button naming the app you are in.
	 * The popover itself is not drawn: floating-vue renders it outside
	 * `#header` — which is why core exposes a `popover-base-class` prop at all
	 * — so a header specimen containing one would be showing something that
	 * never appears inside the header.
	 *
	 * @return {string} The markup.
	 */
	function appMenu34() {
		return (
			'<nav class="app-menu nldesign-pg-appmenu">'
			+ '<span class="app-menu__waffle nldesign-pg-waffle">'
			+ glyph('waffle')
			+ '</span>'
			+ '<span class="app-menu__current-app nldesign-pg-currentapp">'
			+ '<span class="app-menu__current-app-icon nldesign-pg-appicon"></span>'
			+ '<span class="app-menu__current-app-name">Thematiq</span>'
			+ co(1)
			+ '</span>'
			+ '</nav>'
		)
	}

	/**
	 * The header search, as 34 draws it: a field in the middle of the bar.
	 *
	 * 32 and 33 put a magnifier BUTTON among the account glyphs on the right;
	 * 34 replaced it with `UnifiedSearchInput`, a `<search>` element carrying
	 * the placeholder, sitting between the app menu and the glyphs. That is a
	 * change of shape and of position, not just of class name, so the specimen
	 * has to move it rather than rename it.
	 *
	 * @return {string} The markup.
	 */
	function headerSearch34() {
		// The real structure: the <search> element is a centred, click-through
		// track, and the BUTTON inside it is the thing you see. Flattening the
		// two into one element is what made the specimen a left-aligned pill
		// instead of a centred field.
		return (
			'<search class="unified-search-input nldesign-pg-search">'
			+ '<span class="unified-search-input__button nldesign-pg-searchbtn">'
			+ glyph('magnify', 'unified-search-input__icon')
			+ '<span class="unified-search-input__label">'
			+ 'Zoek apps, bestanden, tags, berichten &hellip;'
			+ '</span>'
			+ '</span>'
			+ '</search>'
		)
	}

	/**
	 * The major version a header specimen should be drawn as.
	 *
	 * @param {?number} version The version asked for, if any.
	 * @return {number} A supported major version.
	 */
	function headerMajor(version) {
		if (HEADER_VERSIONS.indexOf(version) !== -1) {
			return version
		}

		return HEADER_VERSIONS[HEADER_VERSIONS.length - 1]
	}

	/**
	 * One navigation entry, in NcAppNavigationItem's own class names.
	 *
	 * @param {string} label The entry label.
	 * @param {string} modifier Extra state classes.
	 * @param {string} [marker] A callout marker to place on it.
	 * @param {string} [counter] A counter bubble value.
	 * @return {string} The markup.
	 */
	function navEntry(label, modifier, marker, counter) {
		return (
			'<li class="app-navigation-entry nldesign-pg-nav-entry '
			+ modifier
			+ '">'
			+ '<span class="nldesign-pg-glyph nldesign-pg-glyph--dark"></span>'
			+ '<span class="nldesign-pg-nav-label">'
			+ label
			+ '</span>'
			+ (counter
				? '<span class="nldesign-pg-bubble">' + counter + '</span>'
				: '')
			+ (marker || '')
			+ '</li>'
		)
	}

	/**
	 * One list row, in NcListItem's own class names.
	 *
	 * @param {string} name The row's name line.
	 * @param {string} subname Its second line.
	 * @param {string} modifier Extra state classes.
	 * @param {string} [marker] A callout marker to place on it.
	 * @return {string} The markup.
	 */
	function listItem(name, subname, modifier, marker) {
		return (
			'<li class="list-item nldesign-pg-listitem '
			+ modifier
			+ '">'
			+ '<span class="avatardiv nldesign-pg-avatar">'
			+ name.charAt(0)
			+ '</span>'
			+ '<span class="list-item-content nldesign-pg-listitem-content">'
			+ '<span class="nldesign-pg-listitem-name">'
			+ name
			+ '</span>'
			+ '<span class="nldesign-pg-muted is-maxcontrast">'
			+ subname
			+ '</span>'
			+ '</span>'
			+ '<span class="nldesign-pg-bubble">3</span>'
			+ (marker || '')
			+ '</li>'
		)
	}

	/** Counter behind specimenId(). */
	var uid = 0

	/**
	 * A specimen-unique id, for the one place the real components need one:
	 * tying a label to the input it names. Prefixed, because the settings page
	 * has its own ids and the real login page's are not ours to reuse.
	 *
	 * @return {string} An id nothing else on the page carries.
	 */
	function specimenId() {
		uid += 1
		return 'nldesign-pg-f' + uid
	}

	/**
	 * One mdi glyph, in the wrapper vue-material-design-icons emits.
	 *
	 * The wrapper is not decoration: NcButton sizes its icon slot through
	 * `.button-vue__icon > *` and NcCheckboxContent through
	 * `.checkbox-content__icon > *`, and the `material-design-icon` span is the
	 * child those rules were measured against.
	 *
	 * @param {string} name The icon's own class, as the icon set names it.
	 * @param {string} path The glyph.
	 * @param {number} size The pixel size the real component asks for.
	 * @return {string} The markup.
	 */
	function mdi(name, path, size) {
		return (
			'<span aria-hidden="true" role="img" class="material-design-icon '
			+ name
			+ '">'
			+ '<svg fill="currentColor" width="'
			+ size
			+ '" height="'
			+ size
			+ '" viewBox="0 0 24 24" class="material-design-icon__svg">'
			+ '<path d="'
			+ path
			+ '"></path></svg></span>'
		)
	}

	/**
	 * The arrow core's `LoginButton` puts in NcButton's icon slot.
	 *
	 * @return {string} The markup.
	 */
	function submitArrow() {
		return mdi(
			'arrow-right-icon submit-wrapper__icon',
			'M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z',
			24,
		)
	}

	/**
	 * The reveal button NcPasswordField hangs inside the password box.
	 *
	 * A whole NcButton — icon-only, tertiary-no-background — carrying
	 * NcInputField's own `input-field__trailing-button`, which is what makes the
	 * password box the one field whose text stops short of its own edge.
	 *
	 * @return {string} The markup.
	 */
	function revealEye() {
		return button('tertiary-no-background', 'default', '', '', {
			extra: 'input-field__trailing-button',
			label: 'Wachtwoord weergeven',
			icon: mdi(
				'eye-icon',
				'M12,9A3,3 0 0,0 9,12A3,3 0 0,0 12,15A3,3 0 0,0 15,12A3,3 0 0,0 12,'
					+ '9M12,17A5,5 0 0,1 7,12A5,5 0 0,1 12,7A5,5 0 0,1 17,12A5,5 0 0,'
					+ '1 12,17M12,4.5C7,4.5 2.73,7.61 1,12C2.73,16.39 7,19.5 12,19.5C'
					+ '17,19.5 21.27,16.39 23,12C21.27,7.61 17,4.5 12,4.5Z',
				18,
			),
		})
	}

	/**
	 * One input, in NcInputField's own DOM.
	 *
	 * A real `<input>` inside a real `input-field__main-wrapper`, because that
	 * is the structure the component's stylesheet is written against: the label
	 * is placed over the input and floats onto its top edge on
	 * `:not(:placeholder-shown)`, the border is a box-shadow on the input
	 * itself, and the trailing button is positioned against the wrapper. None
	 * of that can reach a stack of spans.
	 *
	 * Two label placements, both real. Without `label-outside` — the default,
	 * and what the login form uses — the label sits inside the box. With it the
	 * consumer renders its own caption above, which is what the remaining
	 * specimens show. The input stays typeable on purpose: the float is
	 * behaviour worth seeing, and it is a pseudo-class, not a picture.
	 *
	 * @param {string} label The field label.
	 * @param {string} state default, focus or invalid.
	 * @param {Object} [options] `inside` places the label over the input,
	 *   `trailing` is markup for a trailing button, `type` is the input type.
	 * @return {string} The markup.
	 */
	function field(label, state, options) {
		var settings = options || {}
		var inside = settings.inside === true
		var filled = state === 'focus' || state === 'invalid'
		var id = specimenId()
		var classes = ['input-field', 'nldesign-pg-field']
		if (inside === false) {
			classes.push('input-field--label-outside')
		}
		if (settings.trailing) {
			classes.push('input-field--trailing-icon')
		}
		if (state === 'invalid') {
			classes.push('input-field--error')
		}

		var box =
			'<div class="'
			+ classes.join(' ')
			+ '">'
			+ '<div class="input-field__main-wrapper">'
			+ '<input id="'
			+ id
			+ '" class="input-field__input" type="'
			+ (settings.type || 'text')
			+ '" placeholder="" aria-live="polite" value="'
			+ (filled ? 'Ingevulde waarde' : '')
			+ '">'
			+ (inside
				? '<label for="'
					+ id
					+ '" class="input-field__label">'
					+ label
					+ '</label>'
				: '')
			// Always rendered, always empty, always hidden: the real component
			// emits it whether or not there is a leading icon.
			+ '<div class="input-field__icon input-field__icon--leading"'
			+ ' style="display: none;"></div>'
			+ (settings.trailing || '')
			+ '</div>'
			+ (state === 'invalid'
				? '<p class="input-field__helper-text-message">'
					+ 'Dit veld is verplicht.</p>'
				: '')
			+ '</div>'

		if (inside === true) {
			return box
		}

		// Label-outside means the CONSUMER renders the label, outside the
		// component: NcInputField only stops reserving room for one.
		return (
			'<span class="nldesign-pg-fieldbox">'
			+ '<label for="'
			+ id
			+ '" class="nldesign-pg-field-label">'
			+ label
			+ '</label>'
			+ box
			+ '</span>'
		)
	}

	/**
	 * The mdi glyphs NcCheckboxContent swaps between.
	 *
	 * The checked look of a Nextcloud checkbox is not a CSS state on a square:
	 * the component renders a DIFFERENT icon, and the stylesheet only colours
	 * it. Paths copied from the instance's own bundle.
	 */
	var CHOICE_ICONS = {
		checkbox: {
			offName: 'checkbox-blank-outline-icon',
			onName: 'checkbox-marked-icon',
			off: 'M19,3H5C3.89,3 3,3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5'
				+ 'C21,3.89 20.1,3 19,3M19,5V19H5V5H19Z',
			on: 'M10,17L5,12L6.41,10.58L10,14.17L17.59,6.58L19,8M19,3H5C3.89,3 3,'
				+ '3.89 3,5V19A2,2 0 0,0 5,21H19A2,2 0 0,0 21,19V5C21,3.89 20.1,3 19,3Z',
		},
		radio: {
			offName: 'radiobox-blank-icon',
			onName: 'radiobox-marked-icon',
			off: 'M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,'
				+ '20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,'
				+ '10 0 0,0 12,2Z',
			on: 'M12,20A8,8 0 0,1 4,12A8,8 0 0,1 12,4A8,8 0 0,1 20,12A8,8 0 0,1 12,'
				+ '20M12,2A10,10 0 0,0 2,12A10,10 0 0,0 12,22A10,10 0 0,0 22,12A10,'
				+ '10 0 0,0 12,2M12,7A5,5 0 0,0 7,12A5,5 0 0,0 12,17A5,5 0 0,0 17,'
				+ '12A5,5 0 0,0 12,7Z',
		},
		// The switch is the one glyph still drawn here: NcIconToggleSwitch
		// addresses its own shape through a CSS-module class whose name is a
		// build hash, so there is no selector a specimen can carry to be given
		// it. Everything around the glyph is still the component's own.
		switch: {
			offName: 'toggle-switch-off-icon',
			onName: 'toggle-switch-icon',
			off: 'M17,7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H17A5,5 0 0,0 22,12A5,5 0 0,'
				+ '0 17,7M7,15A3,3 0 0,1 4,12A3,3 0 0,1 7,9A3,3 0 0,1 10,12A3,3 0 0,'
				+ '1 7,15Z',
			on: 'M17,7H7A5,5 0 0,0 2,12A5,5 0 0,0 7,17H17A5,5 0 0,0 22,12A5,5 0 0,'
				+ '0 17,7M17,15A3,3 0 0,1 14,12A3,3 0 0,1 17,9A3,3 0 0,1 20,12A3,3 '
				+ '0 0,1 17,15Z',
		},
	}

	/**
	 * The icon inside a choice, at the size the component asks for.
	 *
	 * @param {string} kind checkbox, radio or switch.
	 * @param {boolean} checked Whether it is on.
	 * @return {string} The markup.
	 */
	function choiceIcon(kind, checked) {
		var set = CHOICE_ICONS[kind] || CHOICE_ICONS.checkbox
		return mdi(
			checked === true ? set.onName : set.offName,
			checked === true ? set.on : set.off,
			kind === 'switch' ? 36 : 24,
		)
	}

	/**
	 * One checkbox, radio or switch, in NcCheckboxRadioSwitch's own DOM.
	 *
	 * @param {string} kind checkbox, radio or switch.
	 * @param {boolean} checked Whether it is on.
	 * @param {string} label Its label.
	 * @param {string} [marker] A callout marker to place on it.
	 * @return {string} The markup.
	 */
	function choice(kind, checked, label, marker) {
		var classes = [
			'checkbox-radio-switch',
			'checkbox-radio-switch-' + kind,
			'nldesign-pg-choice',
		]
		if (checked === true) {
			classes.push('checkbox-radio-switch--checked')
		}

		// The component v-binds these two onto the root under names that are
		// build hashes, and the properties they feed are not. Setting those
		// inline is the one thing a specimen can do to be sized by the
		// component's own rules rather than by an unresolved calc().
		var sizes =
			kind === 'switch'
				? '--icon-size:36px;--icon-height:16px'
				: '--icon-size:24px;--icon-height:24px'

		return (
			'<span style="'
			+ sizes
			+ '" class="'
			+ classes.join(' ')
			+ '">'
			+ '<input class="checkbox-radio-switch__input" type="'
			+ (kind === 'radio' ? 'radio' : 'checkbox')
			+ '"'
			+ (checked === true ? ' checked' : '')
			+ '>'
			+ '<span class="checkbox-content checkbox-radio-switch__content'
			+ ' checkbox-content-'
			+ kind
			+ ' checkbox-content--has-text">'
			+ '<span aria-hidden="true" inert class="checkbox-content__icon'
			+ (checked === true ? ' checkbox-content__icon--checked' : '')
			+ ' checkbox-radio-switch__icon">'
			+ choiceIcon(kind, checked)
			+ '</span>'
			+ '<span class="checkbox-content__wrapper">'
			+ '<span class="checkbox-content__text checkbox-radio-switch__text">'
			+ label
			+ '</span></span></span>'
			+ (marker || '')
			+ '</span>'
		)
	}

	/**
	 * One button, in NcButton's own DOM.
	 *
	 * A real `<button>`: hover, focus, the press and disabled are pseudo-classes
	 * and an attribute on exactly that element, so drawing it as a span means
	 * imitating every one of them.
	 *
	 * The variant class keeps its `vue-` infix, which is what this Nextcloud's
	 * login page actually emits and what Thematiq's element-overrides.css is
	 * written against. Which spans are rendered follows the shape: a button with
	 * no text renders no text span at all, because the component's own `:empty`
	 * and `:has()` rules are what turn an icon-only button square.
	 *
	 * @param {string} kind The NcButton variant: primary, secondary, tertiary,
	 *   tertiary-no-background, error or success.
	 * @param {string} state The state to draw.
	 * @param {string} label Its label.
	 * @param {string} [marker] A callout marker to place on it.
	 * @param {Object} [options] `icon` is markup for the icon slot; `wide` fills
	 *   the container, as NcButton's `wide` prop does; `done` replaces the line
	 *   the press writes below the stage; `extra` is a class the consuming
	 *   component adds, the way NcInputField marks its trailing button; `label`
	 *   is an aria-label, for an icon-only button that has no text to read.
	 * @return {string} The markup.
	 */
	function button(kind, state, label, marker, options) {
		var settings = options || {}
		var hasIcon = Boolean(settings.icon)
		var hasText = Boolean(label)
		var shape = hasIcon && hasText
			? 'icon-and-text'
			: hasIcon
				? 'icon-only'
				: 'text-only'

		var classes = [
			'button-vue',
			'button-vue--size-normal',
			'button-vue--' + shape,
			'button-vue--vue-' + kind,
			'nldesign-pg-btn',
		]
		if (kind.indexOf('tertiary') === 0) {
			classes.push('button-vue--tertiary')
		}
		if (settings.wide === true) {
			classes.push('button-vue--wide')
		}
		if (settings.extra) {
			classes.push(settings.extra)
		}
		// The one state a drawing still has to hold still: `:active` lasts
		// exactly as long as the press, and the cell exists to put the pressed
		// colour beside the resting one.
		if (state === 'active') {
			classes.push('is-active')
		}

		var element =
			'<button type="button" class="'
			+ classes.join(' ')
			+ '" data-done="'
			+ (settings.done || BUTTON_DONE[kind] || '')
			+ '"'
			+ (settings.label ? ' aria-label="' + settings.label + '"' : '')
			+ (state === 'disabled' ? ' disabled' : '')
			+ '><span class="button-vue__wrapper">'
			+ (hasIcon
				? '<span aria-hidden="true" class="button-vue__icon">'
					+ settings.icon
					+ '</span>'
				: '')
			+ (hasText
				? '<span class="button-vue__text">' + label + '</span>'
				: '')
			+ '</span></button>'

		if (!marker) {
			return element
		}

		// Outside the button, not in it: NcButton clips its own overflow, and a
		// marker pinned to a corner from the inside is a marker with its corner
		// cut off.
		return (
			'<span class="nldesign-pg-btnwrap'
			+ (settings.wide === true ? ' is-wide' : '')
			+ '">'
			+ element
			+ marker
			+ '</span>'
		)
	}

	/* ---------------------------------------------------------------- */
	/* Export as token set                                               */
	/* ---------------------------------------------------------------- */

	/**
	 * The button that serialises the active set plus the admin's saved
	 * overrides into one token set file.
	 *
	 * This is how a set authored in this panel leaves it: the custom-set upload
	 * and `css/tokens/*.css` both expect a whole set, not a patch.
	 *
	 * @param {Object} state The instrument state.
	 * @return {Element} The button.
	 */
	function exportButton(state) {
		var button = el(
			'button',
			'nldesign-btn nldesign-btn--small',
			t('thematiq', 'Export as token set'),
		)
		button.type = 'button'
		button.id = 'nldesign-pg-export-btn'
		button.addEventListener('click', function () {
			downloadTokenSet(state)
		})
		return button
	}

	/**
	 * The token values the page is ACTUALLY wearing, not the ones the server
	 * read out of a file.
	 *
	 * The two differ whenever the file is not the whole story, and for the
	 * `nextcloud` set they differ by construction: that set is resolved from
	 * the running instance, so its values exist only in the cascade. Reading
	 * the cascade is also the only approach that cannot go stale — a computed
	 * value is whatever this Nextcloud version actually decided, on every
	 * version, with nothing to keep in sync.
	 *
	 * The server's map stays as the fallback and as the KEY SET: it defines
	 * which tokens a set is made of, and a token the cascade has no value for
	 * still belongs in the export.
	 *
	 * Takes the reader as an argument rather than reaching for the document, so
	 * the merge itself stays pure and testable without a DOM.
	 *
	 * @param {Object} tokens The server-published token map.
	 * @param {Function} read Given (name, fallback), returns the live value.
	 * @return {Object} The same keys, resolved against the live cascade.
	 */
	function liveTokens(tokens, read) {
		var live = {}
		Object.keys(tokens).forEach(function (name) {
			live[name] = read(name, tokens[name])
		})
		return live
	}

	/**
	 * Fetch the overrides as they are SAVED — not as they are typed — and offer
	 * the resulting token set as a file.
	 *
	 * Saved and not typed, because a token set file is a thing an admin hands to
	 * another instance: exporting unsaved edits would produce a file that no
	 * instance, including this one, is actually wearing.
	 *
	 * @param {Object} state The instrument state.
	 * @return {void}
	 */
	function downloadTokenSet(state) {
		fetch(OC.generateUrl('/apps/thematiq/settings/overrides'), {
			headers: { requesttoken: OC.requestToken },
		})
			.then(function (response) {
				return response.json()
			})
			.then(function (data) {
				var result = exportCss(
					liveTokens(state.tokens, function (name, fallback) {
						return readVar(document.documentElement, name, fallback)
					}),
					data.overrides || {},
					state.sources,
				)

				var blob = new Blob([result.css], { type: 'text/css' })
				var url = URL.createObjectURL(blob)
				var link = document.createElement('a')
				link.href = url
				link.download = 'token-set.css'
				document.body.appendChild(link)
				link.click()
				document.body.removeChild(link)
				URL.revokeObjectURL(url)

				if (result.unexpressed.length > 0) {
					// Said out loud rather than dropped quietly: a token set has
					// no vocabulary for these, so the file cannot carry them and
					// the admin would otherwise re-import a set that had lost an
					// override without saying so.
					notify(
						t(
							'thematiq',
							'{count} saved overrides are not part of the token set vocabulary and are not in the file: {names}',
							{
								count: result.unexpressed.length,
								names: result.unexpressed.join(', '),
							},
						),
					)
				}
			})
			.catch(function (error) {
				console.error('[thematiq] exporting the token set failed:', error)
				notify(t('thematiq', 'The token set could not be exported.'))
			})
	}

	return {
		// Pure, unit-tested without a DOM.
		slug: slug,
		componentsFor: componentsFor,
		componentById: componentById,
		rowsByState: rowsByState,
		parseHash: parseHash,
		hashFor: hashFor,
		exportCss: exportCss,
		liveTokens: liveTokens,
		calloutTip: calloutTip,
		componentScopes: componentScopes,
		applyScopes: applyScopes,
		PICKABLE: PICKABLE,
		POINTABLE: POINTABLE,
		STAGES: STAGES,
		FULL_VIEW: FULL_VIEW,
		// The browser entry point.
		boot: boot,
	}
})
