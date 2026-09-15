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

		var heading = el('div', 'nldesign-pg-stage-title', component.title + ' ')
		heading.appendChild(el('span', 'nldesign-pg-dim', '· ' + component.subtitle))
		state.stage.appendChild(heading)

		var build = STAGES[component.id]

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
					? build(null, component)
					: fallbackSample()
			ground.appendChild(wide)
		} else {
			var cells = el('div', 'nldesign-pg-states')
			component.states.forEach(function (stateDef) {
				var cell = el('div', 'nldesign-pg-st')
				var sample = el('div', 'nldesign-pg-sample')
				sample.innerHTML =
					typeof build === 'function'
						? build(stateDef.id, component)
						: fallbackSample()
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
		var fields = stage.querySelectorAll(
			'.nldesign-pg-input:not(.nldesign-pg-select):not(.is-disabled), .nldesign-pg-textarea'
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

		stage.addEventListener('click', function (event) {
			pressFrom(stage, event.target)
		})

		// Enter and Space, because the specimens are spans carrying
		// role="button": the role promises keyboard activation and nothing
		// would deliver it otherwise.
		stage.addEventListener('keydown', function (event) {
			if (event.key !== 'Enter' && event.key !== ' ') {
				return
			}

			var target = event.target
			if (target.closest === undefined || target.closest('.nldesign-pg-btn') === null) {
				return
			}

			// Space scrolls the panel otherwise, which throws the specimen the
			// admin is looking at off the screen.
			event.preventDefault()
			pressFrom(stage, target)
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
				choice.classList.contains('is-checked') ? 'Aangezet' : 'Uitgezet'
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
		var isRadio = choice.querySelector('.nldesign-pg-radio') !== null
		if (isRadio === true && choice.classList.contains('is-checked') === false) {
			var group = choice.parentNode.querySelectorAll('.nldesign-pg-choice')
			Array.prototype.forEach.call(group, function (sibling) {
				if (sibling.querySelector('.nldesign-pg-radio') !== null) {
					sibling.classList.remove('is-checked', 'checkbox-radio-switch--checked')
				}
			})
		}

		choice.classList.toggle('is-checked')
		choice.classList.toggle('checkbox-radio-switch--checked')
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

		// A disabled specimen must stay unresponsive — that IS its state.
		if (button.classList.contains('is-disabled') === true) {
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
		'header-bar': function () {
			// Icons, not labels: the real header is a row of app icons between
			// the logo and the account glyphs, with the active app marked by a
			// bar under it. Labels only appear in the app menu you open from the
			// waffle, and drawing them here made the specimen a row of chunky
			// boxes that looks nothing like the bar it stands for.
			// Core's own pictograms, not blocks. `dist/icons.css` ships every one
			// of these with a `-white` variant precisely because they sit on a
			// saturated header, so the specimen can use the icons the real bar
			// uses instead of standing in for them with squares.
			var icons = [
				'icon-category-dashboard-white',
				'icon-files-white',
				'icon-picture-white',
				'icon-music-white',
				'icon-comment-white',
				'icon-category-office-white',
				'icon-category-monitoring-white',
			]
			var apps = ''
			icons.forEach(function (icon, index) {
				var modifier = ''
				var marker = ''
				if (index === 1) {
					modifier = ' app-menu-entry--active'
					marker = co(1)
				}
				if (index === 3) {
					modifier = ' is-hover'
					marker = co(2)
				}
				apps +=
					'<li class="app-menu-entry'
					+ modifier
					+ '">'
					+ '<span class="nldesign-pg-icon '
					+ icon
					+ '"></span>'
					+ marker
					+ '</li>'
			})

			return (
				'<div class="nldesign-pg-header">'
				+ '<span class="nldesign-pg-header-logo logo"></span>'
				+ '<nav class="app-menu"><ul>'
				+ apps
				+ '</ul></nav>'
				+ '<span class="nldesign-pg-header-end">'
				+ '<span class="unified-search__button nldesign-pg-icon icon-search-white"></span>'
				+ '<span class="nldesign-pg-icon icon-comment-white"></span>'
				+ '<span class="nldesign-pg-icon icon-contacts-white"></span>'
				+ avatarPlate('RB', true)
				+ '</span>'
				+ '</div>'
			)
		},
		'login-card': function () {
			return (
				'<div class="body-login-container nldesign-pg-loginbg">'
				+ '<div class="nldesign-pg-slogan">Een veilige thuisbasis voor al je gegevens</div>'
				+ '<div class="login-box guest-box nldesign-pg-logincard">'
				+ co(1)
				+ '<h2 class="login-form__headline">Inloggen bij Nextcloud</h2>'
				+ '<div class="login-form">'
				+ field('Accountnaam of e-mail', 'default')
				+ field('Wachtwoord', 'default')
				+ button('primary', 'default', 'Inloggen', co(2))
				+ '</div>'
				+ '<p class="login-box__alternative-logins">Inloggen met een apparaat</p>'
				+ '</div></div>'
			)
		},
		'login-button': function (state) {
			return button('primary', state, 'Inloggen')
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
				+ navEntry('Gedeeld met jou', 'is-hover', co(3))
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
				['Notulen raad.docx', '64 kB', '3 dagen geleden', 'is-hover', co(3)],
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
					'is-hover',
					co(2),
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
				+ '<li class="nldesign-pg-action is-hover">Verplaatsen of kopiëren'
				+ co(2)
				+ '</li>'
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
				+ '<span class="nldesign-pg-crumb is-hover">Documenten'
				+ co(2)
				+ '</span>'
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
				+ '<ul class="nldesign-pg-options">'
				+ '<li class="nldesign-pg-option">België</li>'
				+ '<li class="nldesign-pg-option is-hover">Duitsland'
				+ co(2)
				+ '</li>'
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
				+ '<span class="nldesign-pg-link is-hover">voorbeeldthema&rsquo;s</span>'
				+ co(2)
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
			+ '<span class="user-status-icon nldesign-pg-status-dot"></span>'
			+ '</span>'
		)
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

	/**
	 * One labelled input, in NcInputField's own class names.
	 *
	 * @param {string} label The field label.
	 * @param {string} state default, focus or invalid.
	 * @return {string} The markup.
	 */
	function field(label, state) {
		var classes = ['input-field', 'nldesign-pg-field']
		if (state === 'focus' || state === 'invalid') {
			classes.push('is-' + state)
		}
		return (
			'<span class="'
			+ classes.join(' ')
			+ '">'
			+ '<span class="input-field__label nldesign-pg-field-label">'
			+ label
			+ '</span>'
			+ '<span class="nldesign-pg-input">'
			+ (state === 'focus' || state === 'invalid'
				? 'Ingevulde waarde'
				: 'Placeholder')
			+ '</span>'
			+ (state === 'invalid'
				? '<span class="nldesign-pg-status is-error">Dit veld is verplicht.</span>'
				: '')
			+ '</span>'
		)
	}

	/**
	 * One checkbox, radio or switch with its label, in
	 * NcCheckboxRadioSwitch's own class names.
	 *
	 * @param {string} kind checkbox, radio or switch.
	 * @param {boolean} checked Whether it is on.
	 * @param {string} label Its label.
	 * @param {string} [marker] A callout marker to place on it.
	 * @return {string} The markup.
	 */
	function choice(kind, checked, label, marker) {
		return (
			'<span class="checkbox-radio-switch nldesign-pg-choice'
			+ (checked ? ' checkbox-radio-switch--checked is-checked' : '')
			+ '">'
			+ '<span class="nldesign-pg-'
			+ kind
			+ '"></span>'
			+ '<span>'
			+ label
			+ '</span>'
			+ (marker || '')
			+ '</span>'
		)
	}

	/**
	 * One button in one state, in the class names Nextcloud's own NcButton uses
	 * so the shipped stylesheets style it exactly as they style the real thing.
	 *
	 * @param {string} kind primary, secondary, tertiary or error.
	 * @param {string} state The state to draw.
	 * @param {string} label Its label.
	 * @return {string} The markup.
	 */
	function button(kind, state, label, marker) {
		// The modifier this Nextcloud emits is `button-vue--vue-<type>`, which
		// is also what Thematiq's element-overrides.css targets. Newer
		// @nextcloud/vue dropped the `vue-` infix; when this instance follows,
		// those overrides and this specimen move together, which is the point of
		// naming the class here rather than painting the button ourselves.
		var classes = ['button-vue', 'nldesign-pg-btn', 'is-' + kind]
		if (kind !== 'tertiary') {
			classes.push('button-vue--vue-' + kind)
		}
		if (state && state !== 'default') {
			classes.push('is-' + state)
		}

		// Focusable and announced as a button unless it is the disabled
		// specimen — which must be reachable by neither, since that is the
		// state it stands for. Giving it a real role and a real tab stop is
		// what lets :hover, :focus-visible and :active fire on the specimen
		// itself, so the cell labelled "hover" and the cell you actually hover
		// are drawn by the same rule instead of by a class that imitates it.
		var live = ''
		if (state !== 'disabled') {
			live = ' role="button" tabindex="0"'
		}

		return (
			'<span class="'
			+ classes.join(' ')
			+ '" data-done="'
			+ (BUTTON_DONE[kind] || '')
			+ '"'
			+ live
			+ '><span class="button-vue__wrapper"><span class="button-vue__text">'
			+ label
			+ '</span></span>'
			+ (marker || '')
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
		PICKABLE: PICKABLE,
		STAGES: STAGES,
		FULL_VIEW: FULL_VIEW,
		// The browser entry point.
		boot: boot,
	}
})
