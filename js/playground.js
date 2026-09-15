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
		filtered.appendChild(panelHead(state, component, panel))

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
	 * @param {?Element} panel The tab panel that was hidden.
	 * @return {Element} The head row.
	 */
	function panelHead(state, component, panel) {
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

		var all = el(
			'a',
			'',
			t('thematiq', 'Show all {count} tokens of {tab}', {
				count:
					panel !== null
						? panel.querySelectorAll('.nldesign-token-row').length
						: 0,
				tab: tabLabel(state, state.tab),
			}),
		)
		all.href = '#'
		all.addEventListener('click', function (event) {
			event.preventDefault()
			select(state, FULL_VIEW)
		})
		head.appendChild(all)

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
				sample.appendChild(
					el(
						'i',
						'nldesign-pg-co' + (stateDef.fixed ? ' lock' : ''),
						String(stateDef.n),
					),
				)
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

		var legend = el('div', 'nldesign-pg-callouts')
		component.states.forEach(function (stateDef) {
			var item = el('span')
			item.appendChild(
				el(
					'i',
					'nldesign-pg-co' + (stateDef.fixed ? ' lock' : ''),
					String(stateDef.n),
				),
			)
			item.appendChild(document.createTextNode(stateDef.label))
			legend.appendChild(item)
		})
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
		state.stage.appendChild(legend)
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
			return '<span class="nldesign-pg-loginbg nldesign-pg-brandbox"></span>'
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
				+ '<div class="app-content-list nldesign-pg-col">'
				+ '<div class="nldesign-pg-line"></div><div class="nldesign-pg-line short"></div>'
				+ '<div class="nldesign-pg-line"></div><div class="nldesign-pg-line short"></div>'
				+ '</div>'
				+ '<div class="app-content-detail nldesign-pg-col nldesign-pg-col--detail">'
				+ '<div class="nldesign-pg-line wide"></div><div class="nldesign-pg-line"></div>'
				+ '<div class="nldesign-pg-line short"></div>'
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
				+ '<div class="nldesign-pg-sidebar-body">'
				+ '<div class="nldesign-pg-line wide"></div><div class="nldesign-pg-line"></div>'
				+ '<div class="nldesign-pg-line short"></div>'
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
				+ '<div class="nldesign-pg-line wide"></div><div class="nldesign-pg-line"></div>'
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

		return (
			'<span class="'
			+ classes.join(' ')
			+ '"><span class="button-vue__wrapper"><span class="button-vue__text">'
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
					state.tokens,
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
		STAGES: STAGES,
		FULL_VIEW: FULL_VIEW,
		// The browser entry point.
		boot: boot,
	}
})
